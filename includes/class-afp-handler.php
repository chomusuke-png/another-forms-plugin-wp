<?php
if (!defined('ABSPATH')) exit;

class AFP_Handler {

    public function __construct() {
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle_submission'));
        add_action('admin_post_process_another_form', array($this, 'handle_submission'));
    }

    public function handle_submission() {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) die('Sec Error');

        $form_id  = intval($_POST['afp_form_id']);
        $settings = get_post_meta($form_id, '_afp_settings', true);

        // --- VALIDACIÓN RECAPTCHA V2 ---
        $secret_key = isset($settings['recaptcha_secret_key']) ? $settings['recaptcha_secret_key'] : '';
        
        if (!empty($secret_key)) {
            $captcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
            
            if (empty($captcha_response)) {
                $this->redirect_with_status('captcha_error');
            }

            $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array(
                    'secret'   => $secret_key,
                    'response' => $captcha_response
                )
            ));

            if (is_wp_error($verify_response)) {
                $this->redirect_with_status('error');
            }

            $response_body = wp_remote_retrieve_body($verify_response);
            $result = json_decode($response_body);

            if (!$result->success) {
                $this->redirect_with_status('captcha_error');
            }
        }
        // --- FIN VALIDACIÓN ---

        $fields   = get_post_meta($form_id, '_afp_fields', true);
        $raw_data = isset($_POST['afp_data']) ? $_POST['afp_data'] : array();

        $message = "";
        $reply_to = "";
        $name_ref = "Usuario";

        foreach ($fields as $field) {
            if ($field['type'] === 'section') {
                $message .= "<h3 style='background:#eee; padding:5px;'>".esc_html($field['label'])."</h3>";
                continue;
            }

            $key = $field['name'];
            if (!isset($raw_data[$key])) continue;

            $val = $raw_data[$key];

            if (is_array($val)) {
                $val = implode(", ", array_map('sanitize_text_field', $val));
            } else {
                if ($field['type'] === 'textarea') $val = sanitize_textarea_field($val);
                else $val = sanitize_text_field($val);
            }

            if ($field['type'] === 'email' && empty($reply_to)) $reply_to = $val;
            
            $message .= "<p><strong>".esc_html($field['label']).":</strong><br>".nl2br($val)."</p>";
        }

        $to = $settings['email'];
        $subject = "[$settings[subject]] Nuevo contacto";
        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to) $headers[] = "Reply-To: <$reply_to>";

        $sent = wp_mail($to, $subject, $message, $headers);
        
        $this->redirect_with_status($sent ? 'success' : 'error');
    }

    private function redirect_with_status($status) {
        $redirect_url = add_query_arg('afp_status', $status, wp_get_referer());
        wp_redirect($redirect_url);
        exit;
    }
}