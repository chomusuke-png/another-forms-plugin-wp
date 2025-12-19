<?php
if (!defined('ABSPATH')) exit;

/**
 * Class AFP_Handler
 * Procesa el envío del formulario y delega el renderizado del email a una vista.
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
        
        // 2. Validación ReCaptcha (Delegada a método privado para limpieza)
        $this->verify_recaptcha($settings);

        // 3. Procesamiento de Datos
        $fields   = get_post_meta($form_id, '_afp_fields', true);
        $raw_data = isset($_POST['afp_data']) ? $_POST['afp_data'] : array();

        // Extraer email para Reply-To automáticamente
        $reply_to = $this->extract_reply_to($fields, $raw_data);
        
        // Preparar datos estructurados para la vista (No HTML aquí)
        $structured_data = $this->prepare_email_data($fields, $raw_data);

        // 4. Renderizado del Email (Carga de Plantilla)
        $email_content = $this->render_email_template($form_id, $structured_data, $settings);

        // 5. Envío
        $this->send_email($settings, $form_id, $email_content, $reply_to);
    }

    /**
     * Estructura los datos del formulario en un array limpio para la vista.
     */
    private function prepare_email_data($fields, $raw_data) {
        $data_output = array();
        
        // Mapa de etiquetas para subcampos de repeaters
        $labels_map = array();
        foreach ($fields as $f) {
            if (!empty($f['name'])) $labels_map[$f['name']] = $f['label'];
        }

        $skip_processing = false;

        foreach ($fields as $field) {
            $type = $field['type'];

            if ($type === 'repeater_end') {
                $skip_processing = false;
                continue;
            }

            if ($skip_processing) continue;

            // -- SECCIÓN --
            if ($type === 'section') {
                $data_output[] = array(
                    'type'  => 'section',
                    'label' => $field['label']
                );
                continue;
            }

            // -- REPEATER GROUP --
            if ($type === 'repeater_start') {
                $group_slug = $field['name'];
                $skip_processing = true;

                if (isset($raw_data[$group_slug]) && is_array($raw_data[$group_slug])) {
                    $processed_rows = array();

                    foreach ($raw_data[$group_slug] as $row_data) {
                        $row_clean = array();
                        foreach ($row_data as $sub_key => $sub_val) {
                            $label = isset($labels_map[$sub_key]) ? $labels_map[$sub_key] : ucfirst($sub_key);
                            $row_clean[$label] = $this->format_value($sub_val);
                        }
                        $processed_rows[] = $row_clean;
                    }

                    $data_output[] = array(
                        'type'  => 'repeater',
                        'label' => $field['label'],
                        'rows'  => $processed_rows
                    );
                }
                continue;
            }

            // -- CAMPO STANDARD --
            $key = $field['name'];
            if (isset($raw_data[$key])) {
                $data_output[] = array(
                    'type'  => 'field',
                    'label' => $field['label'],
                    'value' => $this->format_value($raw_data[$key])
                );
            }
        }

        return $data_output;
    }

    /**
     * Carga la plantilla HTML y retorna el string renderizado.
     */
    private function render_email_template($form_id, $data, $settings) {
        // Variables para la vista
        $form_title = get_the_title($form_id);
        $site_name  = get_bloginfo('name');
        $colors     = array('btn_color' => isset($settings['btn_color']) ? $settings['btn_color'] : '#1a428a');

        ob_start();
        
        // Ruta a la plantilla
        $template_path = AFP_PATH . 'templates/emails/notification.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo "<p>Error: Plantilla de correo no encontrada.</p>";
            // Fallback simple para debug
            echo '<pre>' . print_r($data, true) . '</pre>';
        }

        return ob_get_clean();
    }

    /**
     * Formatea valores para visualización.
     */
    private function format_value($val) {
        if (is_array($val)) {
            // Arrays simples (checkboxes/chips)
            $clean = array_map('sanitize_text_field', $val);
            return implode(", ", $clean);
        }
        // nl2br permite ver saltos de línea de textareas en el HTML
        return nl2br(sanitize_textarea_field($val));
    }

    /**
     * Envía el correo final.
     */
    private function send_email($settings, $form_id, $body, $reply_to) {
        $to      = !empty($settings['email']) ? $settings['email'] : get_option('admin_email');
        $subject = !empty($settings['subject']) ? $settings['subject'] : 'Nuevo Contacto';
        $full_subject = "[$subject] " . get_the_title($form_id);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to) {
            $headers[] = "Reply-To: <$reply_to>";
        }

        $sent = wp_mail($to, $full_subject, $body, $headers);
        $this->redirect_with_status($sent ? 'success' : 'error');
    }

    /**
     * Busca el primer campo de email para usarlo como Reply-To.
     */
    private function extract_reply_to($fields, $raw_data) {
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && isset($raw_data[$field['name']])) {
                return sanitize_email($raw_data[$field['name']]);
            }
        }
        return null;
    }

    /**
     * Lógica de verificación de ReCaptcha.
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