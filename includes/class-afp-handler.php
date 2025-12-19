<?php
if (!defined('ABSPATH')) exit;

/**
 * Procesa el envío del formulario, manejando validaciones, 
 * campos repetibles (arrays multidimensionales) y chips.
 */
class AFP_Handler {

    public function __construct() {
        add_action('admin_post_nopriv_process_another_form', array($this, 'handle_submission'));
        add_action('admin_post_process_another_form', array($this, 'handle_submission'));
    }

    public function handle_submission() {
        // 1. Verificaciones de seguridad
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_verify_action')) {
            wp_die('Error de seguridad. Intenta recargar la página.');
        }

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

        // 2. Preparar datos
        $fields   = get_post_meta($form_id, '_afp_fields', true);
        $raw_data = isset($_POST['afp_data']) ? $_POST['afp_data'] : array();

        // Creamos un mapa de Labels para usarlo dentro de los repeaters
        // array('slug' => 'Etiqueta Real')
        $field_labels_map = array();
        foreach ($fields as $f) {
            if (!empty($f['name'])) {
                $field_labels_map[$f['name']] = $f['label'];
            }
        }

        // 3. Construir mensaje
        $message_content = "";
        $reply_to_email  = "";
        $skip_processing = false; // Bandera para saltar sub-campos de repeaters ya procesados

        foreach ($fields as $field) {
            $type = $field['type'];

            // -- Si encontramos el fin de un bloque, reactivamos el procesamiento --
            if ($type === 'repeater_end') {
                $skip_processing = false;
                continue;
            }

            // -- Si estamos dentro de un repeater ya procesado, saltamos --
            if ($skip_processing) continue;

            // -- Caso 1: Título de Sección --
            if ($type === 'section') {
                $message_content .= "<h3 style='background:#f0f0f1; padding:10px; margin-top:20px; color:#444; border-bottom:2px solid #ddd;'>" . esc_html($field['label']) . "</h3>";
                continue;
            }

            // -- Caso 2: INICIO REPEATER (Grupo Repetible) --
            if ($type === 'repeater_start') {
                $group_slug  = $field['name'];
                $group_label = $field['label'];
                $skip_processing = true; // Dejamos de procesar individualmente hasta encontrar el 'end'

                // Verificar si hay datos para este grupo
                if (isset($raw_data[$group_slug]) && is_array($raw_data[$group_slug])) {
                    $message_content .= "<div style='border:1px solid #e5e5e5; padding:15px; margin:15px 0; background:#fafafa;'>";
                    $message_content .= "<h4 style='color:#1a428a; margin-top:0; border-bottom:1px solid #ccc; padding-bottom:5px;'>" . esc_html($group_label) . "</h4>";
                    
                    foreach ($raw_data[$group_slug] as $idx => $row_data) {
                        $counter = $idx + 1;
                        $message_content .= "<div style='background:#fff; border:1px solid #ddd; padding:10px; margin-bottom:10px; border-left:4px solid #1a428a;'>";
                        $message_content .= "<strong style='display:block; margin-bottom:5px; color:#555;'>Registro #$counter</strong>";
                        
                        foreach ($row_data as $sub_key => $sub_val) {
                            // Buscamos la etiqueta bonita, si no existe usamos la key
                            $pretty_label = isset($field_labels_map[$sub_key]) ? $field_labels_map[$sub_key] : ucfirst($sub_key);
                            
                            // Formateamos el valor
                            $formatted_val = $this->format_value($sub_val);
                            
                            $message_content .= "<div style='margin-bottom:4px;'><span style='color:#777;'>$pretty_label:</span> <strong>$formatted_val</strong></div>";
                        }
                        $message_content .= "</div>";
                    }
                    $message_content .= "</div>";
                }
                continue;
            }

            // -- Caso 3: Campos Normales (Texto, Email, Chips, etc) --
            $key = $field['name'];
            if (!isset($raw_data[$key])) continue;

            $val = $raw_data[$key];
            
            // Detectar email para Reply-To
            if ($type === 'email' && empty($reply_to_email)) {
                $reply_to_email = sanitize_email($val);
            }

            $formatted_val = $this->format_value($val);
            $message_content .= "<p style='margin-bottom:10px;'><strong>" . esc_html($field['label']) . ":</strong><br>" . $formatted_val . "</p>";
        }

        // 4. Configurar Cabeceras y Enviar
        $to      = !empty($settings['email']) ? $settings['email'] : get_option('admin_email');
        $subject = !empty($settings['subject']) ? $settings['subject'] : 'Nuevo Contacto Web';
        $subject_full = "[$subject] Nuevo mensaje recibido";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to_email) {
            $headers[] = "Reply-To: <$reply_to_email>";
        }

        // Plantilla HTML del correo
        $body  = "<div style='font-family: Helvetica, Arial, sans-serif; padding: 20px; color:#333; line-height:1.5;'>";
        $body .= "<div style='background:#f7f7f7; padding:20px; border-bottom:3px solid #1a428a;'>";
        $body .= "<h2 style='margin:0; color:#1a428a;'>" . get_the_title($form_id) . "</h2>";
        $body .= "</div>";
        $body .= "<div style='padding:20px; border:1px solid #e5e5e5; border-top:none;'>";
        $body .= $message_content;
        $body .= "</div>";
        $body .= "<p style='font-size:12px; color:#999; margin-top:15px;'>Enviado desde " . get_bloginfo('name') . "</p>";
        $body .= "</div>";

        $sent = wp_mail($to, $subject_full, $body, $headers);
        
        $this->redirect_with_status($sent ? 'success' : 'error');
    }

    /**
     * Formatea valores (arrays a strings, saltos de línea, sanitización)
     */
    private function format_value($val) {
        if (is_array($val)) {
            // Para Checkboxes o Chips
            $clean_array = array_map('sanitize_text_field', $val);
            return implode(", ", $clean_array);
        }
        return nl2br(sanitize_textarea_field($val));
    }

    private function redirect_with_status($status) {
        $redirect_url = add_query_arg('afp_status', $status, wp_get_referer());
        wp_redirect($redirect_url);
        exit;
    }
}