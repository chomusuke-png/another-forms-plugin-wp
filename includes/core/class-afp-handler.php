<?php
if (!defined('ABSPATH')) exit;

/**
 * Class AFP_Handler
 * Procesa el envío del formulario.
 * Actualizado para soportar Archivos en Repeaters y anidación profunda.
 */
class AFP_Handler {

    public function __construct() {
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle_submission'));
        add_action('admin_post_process_another_form', array($this, 'handle_submission'));
    }

    public function handle_submission() {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) {
            wp_die('Error de seguridad.');
        }

        $form_id  = intval($_POST['afp_form_id']);
        $settings = get_post_meta($form_id, '_afp_settings', true);
        
        $this->verify_recaptcha($settings);

        $fields = get_post_meta($form_id, '_afp_fields', true);
        if (!$fields) $fields = array();

        // 1. Procesar Archivos (Recursivo y robusto)
        // Pasamos un array vacío como "path" inicial
        $attachments = $this->process_attachments_recursive($fields, array());

        // 2. Procesar Datos de Texto
        $raw_data = isset($_POST['afp_data']) ? $_POST['afp_data'] : array();
        
        $reply_to = $this->extract_reply_to_recursive($fields, $raw_data);
        $structured_data = $this->prepare_email_data_recursive($fields, $raw_data);

        // 3. Renderizado y Envío
        $email_content = $this->render_email_template($form_id, $structured_data, $settings);
        $this->send_email($settings, $form_id, $email_content, $reply_to, $attachments);
    }

    /**
     * Procesa subidas de archivos, soportando anidación profunda.
     * @param array $fields Campos del nivel actual.
     * @param array $current_path Ruta acumulada de claves (ej: ['mi_repeater', 0]).
     */
    private function process_attachments_recursive($fields, $current_path) {
        $attachments = array();
        
        if (!class_exists('AFP_File_Uploader')) {
            require_once plugin_dir_path(dirname(__DIR__)) . 'includes/utils/class-afp-file-uploader.php';
        }

        foreach ($fields as $field) {
            $type = isset($field['type']) ? $field['type'] : '';

            // --- Caso Sección (Transparente) ---
            if ($type === 'section' && !empty($field['sub_fields'])) {
                // Las secciones no añaden nada al path
                $sub_att = $this->process_attachments_recursive($field['sub_fields'], $current_path);
                $attachments = array_merge($attachments, $sub_att);
                continue;
            }

            // --- Caso Repeater ---
            if ($type === 'repeater' && !empty($field['sub_fields'])) {
                $slug = $field['name'];
                
                // Verificar cuántas filas hay en $_POST para saber cuánto iterar
                // Navegamos en $_POST usando el current_path + slug
                $post_data_slice = $this->get_data_from_path($_POST['afp_data'], array_merge($current_path, array($slug)));
                
                if (is_array($post_data_slice)) {
                    foreach ($post_data_slice as $index => $row_data) {
                        // Nuevo path: [...prev, 'slug', index]
                        $new_path = array_merge($current_path, array($slug, $index));
                        $sub_att = $this->process_attachments_recursive($field['sub_fields'], $new_path);
                        $attachments = array_merge($attachments, $sub_att);
                    }
                }
                continue;
            }

            // --- Caso Archivo ---
            if ($type === 'file') {
                $slug = $field['name'];
                // Construimos el path completo al archivo: [...prev, 'slug_archivo']
                $file_path = array_merge($current_path, array($slug));
                
                // Recuperamos el archivo normalizado desde $_FILES
                $file_info = $this->get_file_from_path($file_path);

                if ($file_info && !empty($file_info['name'])) {
                    $allowed = isset($field['allowed_ext']) ? $field['allowed_ext'] : '';
                    $max_mb  = isset($field['max_size']) ? $field['max_size'] : 5;

                    $upload = AFP_File_Uploader::handle_upload($file_info, $allowed, $max_mb);

                    if (!is_wp_error($upload) && $upload) {
                        $attachments[] = $upload['file'];
                        
                        // Inyectar URL en $_POST para que salga en el email
                        // Esto requiere una referencia, pero para simplificar, modificamos directamente el array global
                        $this->set_data_at_path($_POST['afp_data'], $file_path, $upload['url']);
                    } else if (is_wp_error($upload)) {
                         wp_die($upload->get_error_message());
                    }
                }
            }
        }
        return $attachments;
    }

    /**
     * Helper Mágico: Extrae un archivo de la estructura loca de $_FILES de PHP.
     * PHP pivota los arrays: $_FILES['afp_data']['name']['repeater'][0]['file']
     */
    private function get_file_from_path($path) {
        if (!isset($_FILES['afp_data'])) return null;

        $file = array();
        $props = array('name', 'type', 'tmp_name', 'error', 'size');
        $found = true;

        foreach ($props as $prop) {
            // Empezamos en $_FILES['afp_data']['name']...
            $value = $_FILES['afp_data'][$prop];
            
            // Navegamos hacia abajo siguiendo el path
            foreach ($path as $key) {
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    $found = false;
                    break;
                }
            }
            if (!$found) break;
            $file[$prop] = $value;
        }

        return $found ? $file : null;
    }

    /** Helper: Navega $_POST */
    private function get_data_from_path($data, $path) {
        foreach ($path as $key) {
            if (isset($data[$key])) {
                $data = $data[$key];
            } else {
                return null;
            }
        }
        return $data;
    }

    /** Helper: Escribe en $_POST (para poner la URL del archivo) */
    private function set_data_at_path(&$data, $path, $value) {
        $temp = &$data;
        foreach ($path as $key) {
            $temp = &$temp[$key];
        }
        $temp = $value;
    }

    // --- Resto de funciones (prepare_email, template, etc) igual que antes ---
    
    private function prepare_email_data_recursive($fields, $data_context) {
        $output = array();
        foreach ($fields as $field) {
            $type = isset($field['type']) ? $field['type'] : 'text';

            if ($type === 'section') {
                $output[] = array('type' => 'section', 'label' => $field['label']);
                if (!empty($field['sub_fields'])) {
                    $output = array_merge($output, $this->prepare_email_data_recursive($field['sub_fields'], $data_context));
                }
                continue;
            }

            if ($type === 'repeater') {
                $slug = $field['name'];
                $processed_rows = array();
                if (isset($data_context[$slug]) && is_array($data_context[$slug])) {
                    foreach ($data_context[$slug] as $row_data) {
                        $processed_rows[] = $this->flatten_repeater_row($field['sub_fields'], $row_data);
                    }
                }
                $output[] = array('type' => 'repeater', 'label' => $field['label'], 'rows' => $processed_rows);
                continue;
            }

            $key = $field['name'];
            if (isset($data_context[$key])) {
                $output[] = array('type' => 'field', 'label' => $field['label'], 'value' => $this->format_value($data_context[$key]));
            }
        }
        return $output;
    }

    private function flatten_repeater_row($fields, $row_data) {
        $flat = array();
        foreach ($fields as $f) {
            if ($f['type'] === 'section' && !empty($f['sub_fields'])) {
                $flat = array_merge($flat, $this->flatten_repeater_row($f['sub_fields'], $row_data));
            } elseif (isset($row_data[$f['name']])) {
                $flat[$f['label']] = $this->format_value($row_data[$f['name']]);
            }
        }
        return $flat;
    }

    // Métodos estándar (sin cambios importantes)
    private function extract_reply_to_recursive($fields, $data) {
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && isset($data[$field['name']])) return sanitize_email($data[$field['name']]);
            if ($field['type'] === 'section' && !empty($field['sub_fields'])) {
                $found = $this->extract_reply_to_recursive($field['sub_fields'], $data);
                if ($found) return $found;
            }
        }
        return null;
    }

    private function render_email_template($form_id, $data, $settings) {
        $form_title = get_the_title($form_id);
        $site_name = get_bloginfo('name');
        $colors = array('btn_color' => isset($settings['btn_color']) ? $settings['btn_color'] : '#1a428a');
        ob_start();
        $template_path = plugin_dir_path(dirname(__DIR__)) . 'templates/emails/notification.php';
        if (file_exists($template_path)) include $template_path;
        return ob_get_clean();
    }

    private function format_value($val) {
        if (is_array($val)) return implode(", ", array_map('sanitize_text_field', $val));
        return nl2br(sanitize_textarea_field($val));
    }

    private function send_email($settings, $form_id, $body, $reply_to, $attachments = array()) {
        $to = !empty($settings['email']) ? $settings['email'] : get_option('admin_email');
        $subject = !empty($settings['subject']) ? $settings['subject'] : 'Nuevo Contacto';
        $full_subject = "[$subject] " . get_the_title($form_id);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to) $headers[] = "Reply-To: <$reply_to>";
        $sent = wp_mail($to, $full_subject, $body, $headers, $attachments);
        $this->redirect_with_status($sent ? 'success' : 'error');
    }

    private function verify_recaptcha($settings) {
        $secret_key = isset($settings['recaptcha_secret_key']) ? $settings['recaptcha_secret_key'] : '';
        if (empty($secret_key)) return;
        $response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        if (empty($response)) $this->redirect_with_status('captcha_error');
        $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array('body' => array('secret' => $secret_key, 'response' => $response)));
        if (is_wp_error($verify) || !json_decode(wp_remote_retrieve_body($verify))->success) $this->redirect_with_status('captcha_error');
    }

    private function redirect_with_status($status) {
        wp_redirect(add_query_arg('afp_status', $status, wp_get_referer()));
        exit;
    }
}
?>