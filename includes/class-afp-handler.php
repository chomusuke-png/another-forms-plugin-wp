<?php
if (!defined('ABSPATH')) exit;

class AFP_Handler {

    public function __construct() {
        // Escuchamos el envío del formulario (POST)
        // 'admin_post_' es un hook nativo de WP para manejar formularios custom
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle_submission'));
        add_action('admin_post_process_another_form', array($this, 'handle_submission'));
    }

    public function handle_submission() {
        // 1. Seguridad: Verificar Nonce
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) {
            wp_die('Error de seguridad: Acceso no autorizado.');
        }

        // 2. Sanitización (Limpieza de datos)
        $name    = sanitize_text_field($_POST['afp_name']);
        $email   = sanitize_email($_POST['afp_email']);
        $subject = sanitize_text_field($_POST['afp_subject']);
        $message = sanitize_textarea_field($_POST['afp_message']);

        // 3. Lógica de Envío (Correo)
        $admin_email = get_option('admin_email');
        $headers     = array('Content-Type: text/html; charset=UTF-8', "Reply-To: $name <$email>");
        
        $body  = "<h3>Nuevo contacto desde la web</h3>";
        $body .= "<p><strong>Nombre:</strong> $name</p>";
        $body .= "<p><strong>Email:</strong> $email</p>";
        $body .= "<p><strong>Mensaje:</strong><br>" . nl2br($message) . "</p>";

        $sent = wp_mail($admin_email, "Contacto: $subject", $body, $headers);

        // 4. Redirección
        $redirect_url = wp_get_referer(); // Vuelve a la página donde estaba el usuario
        
        if ($sent) {
            $redirect_url = add_query_arg('afp_status', 'success', $redirect_url);
        } else {
            $redirect_url = add_query_arg('afp_status', 'error', $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }
}