<?php
if (!defined('ABSPATH')) exit;

class AFP_Handler {

    public function __construct() {
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle_submission'));
        add_action('admin_post_process_another_form', array($this, 'handle_submission'));
    }

    public function handle_submission() {
        // 1. Verificaciones de seguridad básicas
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) {
            wp_die('Error de seguridad.');
        }

        // 2. VERIFICACIÓN DE CAPTCHA
        $captcha_input = isset($_POST['afp_captcha_input']) ? sanitize_text_field($_POST['afp_captcha_input']) : '';
        $captcha_hash  = isset($_POST['afp_captcha_hash']) ? $_POST['afp_captcha_hash'] : '';

        // Comparamos el hash de lo que escribió el usuario con el hash esperado
        if (wp_hash($captcha_input) !== $captcha_hash) {
            $redirect_url = wp_get_referer();
            $redirect_url = add_query_arg('afp_status', 'captcha_error', $redirect_url);
            wp_redirect($redirect_url);
            exit;
        }

        $form_id = isset($_POST['afp_form_id']) ? intval($_POST['afp_form_id']) : 0;
        if (!$form_id) wp_die('ID de formulario inválido.');

        // 3. Cargar configuración
        $settings = get_post_meta($form_id, '_afp_settings', true);
        $fields   = get_post_meta($form_id, '_afp_fields', true);
        $raw_data = isset($_POST['afp_data']) ? $_POST['afp_data'] : array();

        // 4. Construir cuerpo del correo
        $message_content = "";
        $reply_to_email  = "";
        $reply_to_name   = "Usuario";

        foreach ($fields as $field) {
            $key   = $field['name'];
            $label = $field['label'];
            $value = isset($raw_data[$key]) ? $raw_data[$key] : '';

            if ($field['type'] === 'email') {
                $value = sanitize_email($value);
                if (empty($reply_to_email)) $reply_to_email = $value;
            } elseif ($field['type'] === 'textarea') {
                $value = sanitize_textarea_field($value);
            } else {
                $value = sanitize_text_field($value);
                if ((strpos($key, 'name') !== false || strpos($key, 'nombre') !== false) && $reply_to_name === "Usuario") {
                    $reply_to_name = $value;
                }
            }
            $message_content .= "<p><strong>$label:</strong><br>" . nl2br($value) . "</p>";
        }

        // 5. Enviar
        $to      = !empty($settings['email']) ? $settings['email'] : get_option('admin_email');
        $subject = !empty($settings['subject']) ? $settings['subject'] : 'Nuevo Contacto';
        $subject = "[$subject] mensaje de $reply_to_name";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to_email) {
            $headers[] = "Reply-To: $reply_to_name <$reply_to_email>";
        }

        $body  = "<div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee;'>";
        $body .= "<h2 style='color: #333;'>" . get_the_title($form_id) . "</h2>";
        $body .= $message_content;
        $body .= "<hr><small>Enviado desde el sitio web.</small>";
        $body .= "</div>";

        $sent = wp_mail($to, $subject, $body, $headers);

        $redirect_url = wp_get_referer();
        $status = $sent ? 'success' : 'error';
        $redirect_url = add_query_arg('afp_status', $status, $redirect_url);
        
        wp_redirect($redirect_url);
        exit;
    }
}