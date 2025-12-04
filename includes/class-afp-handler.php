<?php
if (!defined('ABSPATH')) exit;

class AFP_Handler {

    public function __construct() {
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle_submission'));
        add_action('admin_post_process_another_form', array($this, 'handle_submission'));
    }

    public function handle_submission() {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) {
            wp_die('Error de seguridad: Acceso no autorizado.');
        }

        $name    = sanitize_text_field($_POST['afp_name']);
        $email   = sanitize_email($_POST['afp_email']);
        // Aquí recibimos el asunto automático (hidden)
        $subject = sanitize_text_field($_POST['afp_subject']); 
        $message = sanitize_textarea_field($_POST['afp_message']);

        // Configuración del correo
        $admin_email = get_option('admin_email');
        $headers     = array('Content-Type: text/html; charset=UTF-8', "Reply-To: $name <$email>");
        
        // Cuerpo del mensaje mejorado
        $body  = "<div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee;'>";
        $body .= "<h2 style='color: #947e1e;'>$subject</h2>"; // Título con el asunto automático
        $body .= "<p><strong>De:</strong> $name ($email)</p>";
        $body .= "<hr>";
        $body .= "<p><strong>Mensaje:</strong></p>";
        $body .= "<p style='background: #f9f9f9; padding: 15px;'>" . nl2br($message) . "</p>";
        $body .= "</div>";

        // El asunto del correo real llevará el prefijo automático
        $sent = wp_mail($admin_email, "[$subject] Nuevo mensaje de $name", $body, $headers);

        $redirect_url = wp_get_referer();
        
        if ($sent) {
            $redirect_url = add_query_arg('afp_status', 'success', $redirect_url);
        } else {
            $redirect_url = add_query_arg('afp_status', 'error', $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }
}