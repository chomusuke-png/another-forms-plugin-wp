<?php
if (!defined('ABSPATH')) exit;

/**
 * Class AFP_Handler
 * Procesa el envío del formulario, gestiona subidas de archivos y delega el email.
 * Actualizado para soportar estructura anidada (arrays de secciones y repeaters).
 */
class AFP_Handler {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle_submission'));
        add_action('admin_post_process_another_form', array($this, 'handle_submission'));
    }

    /**
     * Maneja el envío POST.
     */
    public function handle_submission() {
        // 1. Verificación de Seguridad
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) {
            wp_die('Error de seguridad. Intenta recargar la página.');
        }

        $form_id  = intval($_POST['afp_form_id']);
        $settings = get_post_meta($form_id, '_afp_settings', true);
        
        // 2. Validación ReCaptcha
        $this->verify_recaptcha($settings);

        // 3. Obtener definición de campos (Árbol anidado)
        $fields = get_post_meta($form_id, '_afp_fields', true);
        if (!$fields) $fields = array();

        // 4. Procesamiento de Archivos
        // (Simplificado: soporta archivos en nivel raíz y dentro de secciones)
        $attachments = $this->process_attachments_recursive($fields);

        // 5. Procesamiento de Datos de Texto
        $raw_data = isset($_POST['afp_data']) ? $_POST['afp_data'] : array();
        
        // Extraer Reply-To recursivamente
        $reply_to = $this->extract_reply_to_recursive($fields, $raw_data);
        
        // Estructurar datos para la vista del email
        $structured_data = $this->prepare_email_data_recursive($fields, $raw_data);

        // 6. Renderizado y Envío
        $email_content = $this->render_email_template($form_id, $structured_data, $settings);

        $this->send_email($settings, $form_id, $email_content, $reply_to, $attachments);
    }

    /**
     * Estructura los datos del formulario recursivamente para el email.
     * Soporta Secciones y Repeaters anidados.
     */
    private function prepare_email_data_recursive($fields, $data_context) {
        $output = array();

        foreach ($fields as $field) {
            $type = isset($field['type']) ? $field['type'] : 'text';

            // --- CASO 1: SECCIÓN ---
            if ($type === 'section') {
                // Añadimos cabecera visual
                $output[] = array(
                    'type'  => 'section',
                    'label' => $field['label']
                );
                
                // Procesamos hijos (mismo contexto de datos, las secciones son transparentes)
                $sub_fields = isset($field['sub_fields']) ? $field['sub_fields'] : array();
                $children_data = $this->prepare_email_data_recursive($sub_fields, $data_context);
                
                $output = array_merge($output, $children_data);
                continue;
            }

            // --- CASO 2: REPEATER ---
            if ($type === 'repeater') {
                $group_slug = $field['name'];
                $sub_fields = isset($field['sub_fields']) ? $field['sub_fields'] : array();
                
                $processed_rows = array();

                if (isset($data_context[$group_slug]) && is_array($data_context[$group_slug])) {
                    foreach ($data_context[$group_slug] as $row_data) {
                        // Aplanamos la fila a pares Label => Valor para la plantilla de email
                        $processed_rows[] = $this->flatten_repeater_row($sub_fields, $row_data);
                    }
                }

                $output[] = array(
                    'type'  => 'repeater',
                    'label' => $field['label'],
                    'rows'  => $processed_rows
                );
                continue;
            }

            // --- CASO 3: CAMPO NORMAL ---
            $key = isset($field['name']) ? $field['name'] : '';
            if ($key && isset($data_context[$key])) {
                $output[] = array(
                    'type'  => 'field',
                    'label' => $field['label'],
                    'value' => $this->format_value($data_context[$key])
                );
            }
        }

        return $output;
    }

    /**
     * Auxiliar para convertir los campos de una fila de repeater en array simple [Label => Valor].
     */
    private function flatten_repeater_row($fields, $row_data) {
        $flat = array();
        foreach ($fields as $f) {
            // Si hay sección dentro de repeater, ignoramos el header y procesamos hijos
            if ($f['type'] === 'section' && !empty($f['sub_fields'])) {
                $flat = array_merge($flat, $this->flatten_repeater_row($f['sub_fields'], $row_data));
                continue;
            }
            
            // Campo normal
            $name = isset($f['name']) ? $f['name'] : '';
            if ($name && isset($row_data[$name])) {
                $flat[$f['label']] = $this->format_value($row_data[$name]);
            }
        }
        return $flat;
    }

    /**
     * Busca recursivamente el primer campo de email para usar como Reply-To.
     */
    private function extract_reply_to_recursive($fields, $data) {
        foreach ($fields as $field) {
            // Caso directo
            if ($field['type'] === 'email' && isset($data[$field['name']])) {
                return sanitize_email($data[$field['name']]);
            }
            
            // Caso Sección (Recursión)
            if ($field['type'] === 'section' && !empty($field['sub_fields'])) {
                $found = $this->extract_reply_to_recursive($field['sub_fields'], $data);
                if ($found) return $found;
            }
            
            // Nota: No buscamos dentro de repeaters para reply-to generalmente.
        }
        return null;
    }

    /**
     * Procesa subidas de archivos (Soporta Nivel Raíz y Secciones).
     */
    private function process_attachments_recursive($fields) {
        $attachments = array();
        
        // Cargar uploader si no existe
        if (!class_exists('AFP_File_Uploader')) {
            require_once plugin_dir_path(dirname(__DIR__)) . 'includes/utils/class-afp-file-uploader.php';
        }

        foreach ($fields as $field) {
            // Recursión en secciones
            if ($field['type'] === 'section' && !empty($field['sub_fields'])) {
                $sub_att = $this->process_attachments_recursive($field['sub_fields']);
                $attachments = array_merge($attachments, $sub_att);
                continue;
            }

            // Procesar File
            if ($field['type'] === 'file') {
                $key = $field['name'];
                
                // Verificamos $_FILES['afp_data']
                if (isset($_FILES['afp_data']['name'][$key]) && !empty($_FILES['afp_data']['name'][$key])) {
                    
                    $file_info = array(
                        'name'     => $_FILES['afp_data']['name'][$key],
                        'type'     => $_FILES['afp_data']['type'][$key],
                        'tmp_name' => $_FILES['afp_data']['tmp_name'][$key],
                        'error'    => $_FILES['afp_data']['error'][$key],
                        'size'     => $_FILES['afp_data']['size'][$key],
                    );

                    $allowed = isset($field['allowed_ext']) ? $field['allowed_ext'] : '';
                    $max_mb  = isset($field['max_size']) ? $field['max_size'] : 5;

                    $upload = AFP_File_Uploader::handle_upload($file_info, $allowed, $max_mb);

                    if (is_wp_error($upload)) {
                        wp_die($upload->get_error_message());
                    }

                    if ($upload) {
                        $attachments[] = $upload['file'];
                        // Inyectamos la URL en $_POST para que aparezca en el email
                        $_POST['afp_data'][$key] = $upload['url']; 
                    }
                }
            }
        }
        return $attachments;
    }

    /**
     * Carga la plantilla HTML y retorna el string renderizado.
     */
    private function render_email_template($form_id, $data, $settings) {
        $form_title = get_the_title($form_id);
        $site_name  = get_bloginfo('name');
        $colors     = array('btn_color' => isset($settings['btn_color']) ? $settings['btn_color'] : '#1a428a');

        ob_start();
        $template_path = plugin_dir_path(dirname(__DIR__)) . 'templates/emails/notification.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo "<p>Error: Plantilla de correo no encontrada.</p>";
        }

        return ob_get_clean();
    }

    /**
     * Formatea valores para visualización.
     */
    private function format_value($val) {
        if (is_array($val)) {
            $clean = array_map('sanitize_text_field', $val);
            return implode(", ", $clean);
        }
        return nl2br(sanitize_textarea_field($val));
    }

    /**
     * Envía el correo final con adjuntos.
     */
    private function send_email($settings, $form_id, $body, $reply_to, $attachments = array()) {
        $to      = !empty($settings['email']) ? $settings['email'] : get_option('admin_email');
        $subject = !empty($settings['subject']) ? $settings['subject'] : 'Nuevo Contacto';
        $full_subject = "[$subject] " . get_the_title($form_id);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to) {
            $headers[] = "Reply-To: <$reply_to>";
        }

        $sent = wp_mail($to, $full_subject, $body, $headers, $attachments);
        
        // Limpieza de adjuntos temporales si fuera necesario
        // foreach ($attachments as $file) { @unlink($file); }

        $this->redirect_with_status($sent ? 'success' : 'error');
    }

    /**
     * Verificación de ReCaptcha.
     */
    private function verify_recaptcha($settings) {
        $secret_key = isset($settings['recaptcha_secret_key']) ? $settings['recaptcha_secret_key'] : '';
        if (empty($secret_key)) return;

        $response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        if (empty($response)) $this->redirect_with_status('captcha_error');

        $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array('secret' => $secret_key, 'response' => $response)
        ));

        if (is_wp_error($verify)) $this->redirect_with_status('error');

        $result = json_decode(wp_remote_retrieve_body($verify));
        if (!$result->success) $this->redirect_with_status('captcha_error');
    }

    private function redirect_with_status($status) {
        $url = add_query_arg('afp_status', $status, wp_get_referer());
        wp_redirect($url);
        exit;
    }
}