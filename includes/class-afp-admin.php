<?php
if (!defined('ABSPATH')) exit;

class AFP_Admin {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
    }

    public function add_meta_boxes() {
        add_meta_box('afp_settings', 'Configuración General', array($this, 'render_settings_box'), 'afp_form', 'side', 'high');
        add_meta_box('afp_builder', 'Constructor de Campos (Drag & Drop)', array($this, 'render_builder_box'), 'afp_form', 'normal', 'high');
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
            <input type="text" name="afp_settings[recaptcha_site_key]" value="<?php echo esc_attr($site_key); ?>" class="widefat">
        </p>
        <p>
            <label>Secret Key:</label>
            <input type="text" name="afp_settings[recaptcha_secret_key]" value="<?php echo esc_attr($secret_key); ?>" class="widefat">
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

    public function render_builder_box($post) {
        $fields = get_post_meta($post->ID, '_afp_fields', true);
        if (empty($fields)) $fields = array();
        ?>
        <div id="afp-builder-app">
            <div class="afp-toolbar">
                <button type="button" class="button afp-add-field" data-type="text">Texto</button>
                <button type="button" class="button afp-add-field" data-type="email">Email</button>
                <button type="button" class="button afp-add-field" data-type="textarea">Área Texto</button>
                <button type="button" class="button afp-add-field" data-type="number">Número</button>
                <button type="button" class="button afp-add-field" data-type="select">Dropdown</button>
                <button type="button" class="button afp-add-field" data-type="checkbox">Checkbox</button>
                <button type="button" class="button afp-add-field" data-type="radio">Radio</button>
                <button type="button" class="button afp-add-field" data-type="date">Fecha</button>
                <span class="afp-separator">|</span>
                <button type="button" class="button afp-add-field button-primary" data-type="section"><strong>+ Sección</strong></button>
            </div>

            <div id="afp-fields-container" class="ui-sortable">
                <?php 
                if (!empty($fields)) {
                    foreach ($fields as $index => $field) {
                        $this->render_field_item($index, $field);
                    }
                }
                ?>
            </div>

            <div id="afp-field-template" style="display:none;">
                <?php $this->render_field_item('__INDEX__', array('type' => 'text', 'label' => '', 'name' => '', 'width' => '100')); ?>
            </div>
            
            <p class="description">Arrastra para reordenar. Cambia el tipo usando el selector.</p>
        </div>
        <?php
    }

    private function render_field_item($index, $field) {
        $type = isset($field['type']) ? $field['type'] : 'text';
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $req = isset($field['required']) ? $field['required'] : 0;
        $width = isset($field['width']) ? $field['width'] : '100';
        $options = isset($field['options']) ? $field['options'] : '';
        
        // Nuevos campos Min/Max
        $min_val = isset($field['min_value']) ? $field['min_value'] : '';
        $max_val = isset($field['max_value']) ? $field['max_value'] : '';
        
        $is_option_field = in_array($type, array('select', 'radio', 'checkbox'));
        $is_number_field = ($type === 'number');
        $is_section = ($type === 'section');
        
        $display_options = $is_option_field ? 'block' : 'none';
        $display_number  = $is_number_field ? 'flex' : 'none'; // Flex para que se pongan lado a lado
        $card_class = $is_section ? 'afp-card afp-section-card' : 'afp-card';
        
        $available_types = array(
            'text'     => 'Texto',
            'email'    => 'Email',
            'textarea' => 'Área Texto',
            'number'   => 'Número',
            'select'   => 'Dropdown',
            'checkbox' => 'Checkbox',
            'radio'    => 'Radio',
            'date'     => 'Fecha',
            'section'  => 'SECCIÓN'
        );
        ?>
        <div class="<?php echo $card_class; ?>" data-index="<?php echo $index; ?>">
            <div class="afp-card-header">
                <span class="afp-handle dashicons dashicons-move"></span>
                
                <select name="afp_fields[<?php echo $index; ?>][type]" class="afp-type-selector">
                    <?php foreach($available_types as $val => $txt): ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($type, $val); ?>>
                            <?php echo esc_html($txt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" class="afp-input-label-preview" name="afp_fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="Etiqueta del campo">
                
                <div class="afp-actions">
                    <button type="button" class="afp-toggle-body dashicons dashicons-arrow-down-alt2"></button>
                    <button type="button" class="afp-remove-row dashicons dashicons-trash"></button>
                </div>
            </div>

            <div class="afp-card-body">
                <div class="afp-form-row afp-slug-row" style="<?php echo $is_section ? 'display:none;' : ''; ?>">
                    <label>ID/Name (Slug):
                        <input type="text" name="afp_fields[<?php echo $index; ?>][name]" value="<?php echo esc_attr($name); ?>" class="widefat">
                    </label>
                </div>

                <div class="afp-form-row afp-options-wrapper" style="display:<?php echo $display_options; ?>">
                    <label>Opciones (Una por línea):
                        <textarea name="afp_fields[<?php echo $index; ?>][options]" rows="3" class="widefat"><?php echo esc_textarea($options); ?></textarea>
                    </label>
                </div>

                <div class="afp-form-row afp-number-wrapper afp-flex" style="display:<?php echo $display_number; ?>; gap: 15px;">
                    <label>Mínimo:
                        <input type="number" name="afp_fields[<?php echo $index; ?>][min_value]" value="<?php echo esc_attr($min_val); ?>" class="widefat" style="width: 80px;">
                    </label>
                    <label>Máximo:
                        <input type="number" name="afp_fields[<?php echo $index; ?>][max_value]" value="<?php echo esc_attr($max_val); ?>" class="widefat" style="width: 80px;">
                    </label>
                </div>

                <div class="afp-form-row afp-flex afp-settings-row" style="<?php echo $is_section ? 'display:none;' : ''; ?>">
                    <label>
                        <input type="checkbox" name="afp_fields[<?php echo $index; ?>][required]" value="1" <?php checked($req, 1); ?>> Obligatorio
                    </label>
                    <label style="margin-left: 20px;">Ancho:
                        <select name="afp_fields[<?php echo $index; ?>][width]">
                            <option value="100" <?php selected($width, '100'); ?>>100%</option>
                            <option value="50" <?php selected($width, '50'); ?>>50%</option>
                            <option value="33" <?php selected($width, '33'); ?>>33%</option>
                        </select>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_meta_data($post_id) {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_save_data')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

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

        if (isset($_POST['afp_fields'])) {
            $clean_fields = array();
            $fields = array_values($_POST['afp_fields']); 

            foreach ($fields as $field) {
                if (empty($field['label'])) continue;

                $clean_field = array(
                    'type'      => sanitize_text_field($field['type']),
                    'label'     => sanitize_text_field($field['label']),
                    'name'      => sanitize_title($field['name']),
                    'required'  => isset($field['required']) ? 1 : 0,
                    'width'     => sanitize_text_field($field['width']),
                    'options'   => isset($field['options']) ? sanitize_textarea_field($field['options']) : '',
                    // Guardamos Min/Max
                    'min_value' => isset($field['min_value']) ? sanitize_text_field($field['min_value']) : '',
                    'max_value' => isset($field['max_value']) ? sanitize_text_field($field['max_value']) : '',
                );
                $clean_fields[] = $clean_field;
            }
            update_post_meta($post_id, '_afp_fields', $clean_fields);
        } else {
            update_post_meta($post_id, '_afp_fields', array());
        }
    }
}