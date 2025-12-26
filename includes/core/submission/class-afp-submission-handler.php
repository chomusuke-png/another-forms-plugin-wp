<?php
if (!defined('ABSPATH')) exit;

/**
 * Maneja el procesamiento del formulario (POST), guardado en DB y envío de correo.
 */
class AFP_Submission_Handler {

    public function __construct() {
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle'));
        add_action('admin_post_process_another_form', array($this, 'handle'));
    }

    /**
     * Método principal que orquesta la validación, guardado y envío.
     */
    public function handle() {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) {
            wp_die('Error de seguridad: Nonce inválido.');
        }

        $form_id  = intval($_POST['afp_form_id']);
        $settings = get_post_meta($form_id, '_afp_settings', true);
        $fields   = get_post_meta($form_id, '_afp_fields', true);
        if (!$fields) $fields = array();

        // 1. Validar Captcha
        $this->verify_recaptcha($settings);

        // 2. Procesar Archivos
        $attachments = AFP_File_Processor::process_recursive($fields, array());

        // 3. Procesar Datos
        $raw_data = isset($_POST['afp_data']) ? $_POST['afp_data'] : array();
        
        // Extraemos email para Reply-To
        $reply_to = AFP_Data_Processor::extract_reply_to($fields, $raw_data);
        
        // Preparamos datos estructurados (Labels y Valores) para Email y DB
        $structured_data = AFP_Data_Processor::prepare_email_data($fields, $raw_data);

        // 4. GUARDAR EN BASE DE DATOS (Prevención de pérdida de datos)
        $entry_id = $this->save_entry_to_db($form_id, $structured_data, $raw_data);

        // 5. Enviar Correo
        $this->send_email($settings, $form_id, $structured_data, $reply_to, $attachments);
    }

    /**
     * Crea un post 'afp_entry' con los datos recibidos.
     * * @param int $form_id ID del formulario padre.
     * @param array $structured_data Datos formateados (label/value).
     * @param array $raw_data Datos crudos del POST.
     * @return int|WP_Error ID del post creado.
     */
    private function save_entry_to_db($form_id, $structured_data, $raw_data) {
        $form_title = get_the_title($form_id);
        $entry_title = sprintf('Entry #%s - %s', date('Ymd-His'), $form_title);

        $post_data = array(
            'post_title'  => $entry_title,
            'post_type'   => 'afp_entry',
            'post_status' => 'publish',
        );

        $entry_id = wp_insert_post($post_data);

        if (!is_wp_error($entry_id)) {
            // Guardamos la relación con el formulario padre
            update_post_meta($entry_id, '_afp_parent_form_id', $form_id);
            
            // Guardamos el JSON estructurado para recuperarlo fácilmente si hacemos un visor en admin
            update_post_meta($entry_id, '_afp_entry_data', $structured_data);
            
            // Opcional: Guardar raw data por redundancia
            update_post_meta($entry_id, '_afp_entry_raw', $raw_data);
        }

        return $entry_id;
    }

    /**
     * Envía el correo de notificación.
     * * @param array $settings Configuración del formulario.
     * @param int $form_id ID del formulario.
     * @param array $data Datos estructurados.
     * @param string $reply_to Email para responder.
     * @param array $attachments Rutas de archivos adjuntos.
     */
    private function send_email($settings, $form_id, $data, $reply_to, $attachments) {
        $to = !empty($settings['email']) ? $settings['email'] : get_option('admin_email');
        $subject = isset($settings['subject']) ? $settings['subject'] : 'Nuevo Mensaje';
        $subject = "[$subject] " . get_the_title($form_id);
        
        // Renderizar Template
        ob_start();
        $primary_color = isset($settings['btn_color']) ? $settings['btn_color'] : '#1a428a';
        $form_title = get_the_title($form_id);
        $site_name = get_bloginfo('name');
        
        // Ajusta la ruta según tu estructura real si ha cambiado
        $template_path = plugin_dir_path(dirname(dirname(__DIR__))) . 'templates/emails/notification.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo "Error: Template not found.";
        }
        
        $body = ob_get_clean();

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to && is_email($reply_to)) {
            $headers[] = "Reply-To: <$reply_to>";
        }

        $sent = wp_mail($to, $subject, $body, $headers, $attachments);
        
        // Redirección segura
        $status = $sent ? 'success' : 'error';
        $redirect_url = add_query_arg('afp_status', $status, wp_get_referer());
        
        if (!headers_sent()) {
            wp_redirect($redirect_url);
        } else {
            // Fallback JS si los headers ya se enviaron (raro en admin-post pero posible)
            echo "<script>window.location.href='" . esc_url($redirect_url) . "';</script>";
        }
        exit;
    }

    /**
     * Verifica reCAPTCHA v2.
     * @param array $settings
     */
    private function verify_recaptcha($settings) {
        $secret_key = isset($settings['recaptcha_secret_key']) ? $settings['recaptcha_secret_key'] : '';
        
        if (empty($secret_key)) return; // No hay captcha configurado

        $response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        
        if (empty($response)) {
            $this->redirect_with_status('captcha_error');
        }

        $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret'   => $secret_key,
                'response' => $response
            )
        ));

        if (is_wp_error($verify)) {
             $this->redirect_with_status('captcha_error');
        }

        $body = json_decode(wp_remote_retrieve_body($verify));
        if (!$body->success) {
            $this->redirect_with_status('captcha_error');
        }
    }

    /**
     * Helper para redirección de error.
     * @param string $status
     */
    private function redirect_with_status($status) {
        $url = add_query_arg('afp_status', $status, wp_get_referer());
        if (!headers_sent()) {
            wp_redirect($url);
        } else {
            echo "<script>window.location.href='" . esc_url($url) . "';</script>";
        }
        exit;
    }
}