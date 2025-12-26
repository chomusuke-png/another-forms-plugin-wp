<?php
if (!defined('ABSPATH')) exit;

class AFP_Submission_Handler {

    public function __construct() {
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle'));
        add_action('admin_post_process_another_form', array($this, 'handle'));
    }

    public function handle() {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) wp_die('Security Error');

        $form_id  = intval($_POST['afp_form_id']);
        $settings = get_post_meta($form_id, '_afp_settings', true);
        $fields   = get_post_meta($form_id, '_afp_fields', true);
        if (!$fields) $fields = array();

        // 1. Validar Captcha
        $this->verify_recaptcha($settings);

        // 2. Procesar Archivos (Delegado)
        $attachments = AFP_File_Processor::process_recursive($fields, array());

        // 3. Procesar Datos (Delegado)
        $raw_data = isset($_POST['afp_data']) ? $_POST['afp_data'] : array();
        $reply_to = AFP_Data_Processor::extract_reply_to($fields, $raw_data);
        $structured_data = AFP_Data_Processor::prepare_email_data($fields, $raw_data);

        // 4. Enviar
        $this->send_email($settings, $form_id, $structured_data, $reply_to, $attachments);
    }

    private function send_email($settings, $form_id, $data, $reply_to, $attachments) {
        $to = !empty($settings['email']) ? $settings['email'] : get_option('admin_email');
        $subject = "[$settings[subject]] " . get_the_title($form_id);
        
        // Renderizar Template
        ob_start();
        $primary_color = isset($settings['btn_color']) ? $settings['btn_color'] : '#1a428a'; // Para template
        $form_title = get_the_title($form_id);
        $site_name = get_bloginfo('name');
        
        $template_path = plugin_dir_path(dirname(dirname(__DIR__))) . 'templates/emails/notification.php';
        if (file_exists($template_path)) include $template_path;
        $body = ob_get_clean();

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to) $headers[] = "Reply-To: <$reply_to>";

        $sent = wp_mail($to, $subject, $body, $headers, $attachments);
        
        $status = $sent ? 'success' : 'error';
        wp_redirect(add_query_arg('afp_status', $status, wp_get_referer()));
        exit;
    }

    private function verify_recaptcha($settings) {
        $secret_key = isset($settings['recaptcha_secret_key']) ? $settings['recaptcha_secret_key'] : '';
        if (empty($secret_key)) return;
        $response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        if (empty($response)) $this->redirect_with_status('captcha_error');
        $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array('body' => array('secret' => $secret_key, 'response' => $response)));
        if (is_wp_error($verify) || !json_decode(wp_remote_retrieve_body($verify))->success) $this->redirect_with_status('captcha_error');
    }
}