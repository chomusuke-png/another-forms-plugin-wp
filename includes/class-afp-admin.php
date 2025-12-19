<?php
if (!defined('ABSPATH')) exit;

/**
 * Controller del área de administración.
 */
class AFP_Admin {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
    }

    public function add_meta_boxes() {
        add_meta_box('afp_settings', 'Configuración General', array($this, 'render_settings_box'), 'afp_form', 'side', 'high');
        
        // CORRECCIÓN: Usamos $this->render_builder_box como intermediario
        // en lugar de llamar directamente a la clase UI.
        add_meta_box('afp_builder', 'Constructor de Campos (Drag & Drop)', array($this, 'render_builder_box'), 'afp_form', 'normal', 'high');
    }

    /**
     * Intermediario: Obtiene los datos y llama a la Vista (UI).
     */
    public function render_builder_box($post) {
        // 1. Recuperamos los campos guardados
        $fields = get_post_meta($post->ID, '_afp_fields', true);
        
        if (empty($fields) || !is_array($fields)) {
            $fields = array();
        }

        // 2. Llamamos a la clase visual pasándole los datos limpios
        AFP_Builder_UI::render_app($fields);
    }

    public function render_settings_box($post) {
        wp_nonce_field('afp_save_data', 'afp_nonce');
        $settings = get_post_meta($post->ID, '_afp_settings', true);
        
        $email      = isset($settings['email']) ? $settings['email'] : get_option('admin_email');
        $subject    = isset($settings['subject']) ? $settings['subject'] : 'Nuevo Mensaje';
        $btn_text   = isset($settings['btn_text']) ? $settings['btn_text'] : 'Enviar';
        $btn_color  = isset($settings['btn_color']) ? $settings['btn_color'] : '#1a428a';
        $hide_title = isset($settings['hide_title']) ? $settings['hide_title'] : 0;
        
        $site_key   = isset($settings['recaptcha_site_key']) ? $settings['recaptcha_site_key'] : '';
        $secret_key = isset($settings['recaptcha_secret_key']) ? $settings['recaptcha_secret_key'] : '';
        ?>
        <p>
            <label><strong>Email Receptor</strong></label>
            <input type="email" name="afp_settings[email]" value="<?php echo esc_attr($email); ?>" class="widefat">
        </p>
        <p>
            <label><strong>Asunto</strong></label>
            <input type="text" name="afp_settings[subject]" value="<?php echo esc_attr($subject); ?>" class="widefat">
        </p>
        <hr>
        <p><strong>Google reCAPTCHA v2</strong></p>
        <p>
            <label>Site Key:</label>
            <input type="text" name="afp_settings[recaptcha_site_key]" value="<?php echo esc_attr($site_key); ?>" class="widefat" placeholder="Clave del sitio">
        </p>
        <p>
            <label>Secret Key:</label>
            <input type="text" name="afp_settings[recaptcha_secret_key]" value="<?php echo esc_attr($secret_key); ?>" class="widefat" placeholder="Clave secreta">
        </p>
        <hr>
        <p>
            <label><strong>Texto Botón</strong></label>
            <input type="text" name="afp_settings[btn_text]" value="<?php echo esc_attr($btn_text); ?>" class="widefat">
        </p>
        <p>
            <label><strong>Color Botón</strong></label><br>
            <input type="color" name="afp_settings[btn_color]" value="<?php echo esc_attr($btn_color); ?>">
        </p>
        <p>
            <label>
                <input type="checkbox" name="afp_settings[hide_title]" value="1" <?php checked($hide_title, 1); ?>>
                Ocultar Título
            </label>
        </p>
        <?php
    }

    public function save_meta_data($post_id) {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_save_data')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Guardar Configuración
        if (isset($_POST['afp_settings'])) {
            $clean_settings = array(
                'email'                => sanitize_email($_POST['afp_settings']['email']),
                'subject'              => sanitize_text_field($_POST['afp_settings']['subject']),
                'btn_text'             => sanitize_text_field($_POST['afp_settings']['btn_text']),
                'btn_color'            => sanitize_hex_color($_POST['afp_settings']['btn_color']),
                'hide_title'           => isset($_POST['afp_settings']['hide_title']) ? 1 : 0,
                'recaptcha_site_key'   => sanitize_text_field($_POST['afp_settings']['recaptcha_site_key']),
                'recaptcha_secret_key' => sanitize_text_field($_POST['afp_settings']['recaptcha_secret_key']),
            );
            update_post_meta($post_id, '_afp_settings', $clean_settings);
        }

        // Guardar Campos
        if (isset($_POST['afp_fields'])) {
            $clean_fields = array();
            $fields = array_values($_POST['afp_fields']); 

            foreach ($fields as $field) {
                // Validación: Label es obligatorio (excepto para repeater_end)
                if (empty($field['label']) && isset($field['type']) && $field['type'] !== 'repeater_end') {
                    continue; 
                }

                $clean_field = array(
                    'type'      => sanitize_text_field($field['type']),
                    'label'     => sanitize_text_field($field['label']),
                    'name'      => sanitize_title($field['name']),
                    'required'  => isset($field['required']) ? 1 : 0,
                    'width'     => isset($field['width']) ? sanitize_text_field($field['width']) : '100',
                    'options'   => isset($field['options']) ? sanitize_textarea_field($field['options']) : '',
                    'min_value' => isset($field['min_value']) ? sanitize_text_field($field['min_value']) : '',
                    'max_value' => isset($field['max_value']) ? sanitize_text_field($field['max_value']) : '',
                );
                $clean_fields[] = $clean_field;
            }
            update_post_meta($post_id, '_afp_fields', $clean_fields);
        } else {
            // Si el array está vacío (se borraron todos los campos), actualizamos a vacío
            update_post_meta($post_id, '_afp_fields', array());
        }
    }
}