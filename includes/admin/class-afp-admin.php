<?php
if (!defined('ABSPATH')) exit;

/**
 * Controller del área de administración.
 * Gestiona el guardado y carga de datos, soportando estructura JSON anidada.
 */
class AFP_Admin {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
    }

    public function add_meta_boxes() {
        add_meta_box('afp_settings', 'Configuración General', array($this, 'render_settings_box'), 'afp_form', 'side', 'high');
        add_meta_box('afp_builder', 'Constructor Visual (Drag & Drop)', array($this, 'render_builder_box'), 'afp_form', 'normal', 'high');
        add_meta_box('afp_entry_detail', 'Datos del Envío', array($this, 'render_entry_detail_box'), 'afp_entry', 'normal', 'high');
    }

    /**
     * Renderiza la tabla de datos en la pantalla de "Entrada".
     */
    public function render_entry_detail_box($post) {
        $data = get_post_meta($post->ID, '_afp_entry_data', true);

        if (empty($data) || !is_array($data)) {
            echo '<p>No hay datos registrados para esta entrada.</p>';
            return;
        }

        echo '<table class="widefat striped" style="border:1px solid #ddd;">';
        echo '<thead><tr><th style="width:30%;">Campo</th><th>Valor</th></tr></thead>';
        echo '<tbody>';

        foreach ($data as $item) {
            $type  = isset($item['type']) ? $item['type'] : '';
            $label = isset($item['label']) ? $item['label'] : '';
            
            // Caso Sección (Solo título visual)
            if ($type === 'section') {
                echo '<tr style="background:#f0f6fc;">';
                echo '<td colspan="2"><strong>' . esc_html($label) . '</strong></td>';
                echo '</tr>';
                continue;
            }

            // Caso Repeater (Tabla anidada compleja)
            if ($type === 'repeater') {
                $rows = isset($item['rows']) ? $item['rows'] : array();
                echo '<tr>';
                echo '<td colspan="2">';
                echo '<strong>' . esc_html($label) . '</strong>';
                
                if (!empty($rows)) {
                    echo '<div style="margin-top:10px; border-left:3px solid #2271b1; padding-left:10px;">';
                    foreach ($rows as $idx => $row) {
                        echo '<p><strong>Registro #' . ($idx + 1) . '</strong></p>';
                        echo '<ul style="margin-bottom:15px; list-style:disc; margin-left:20px;">';
                        foreach ($row as $sub_label => $sub_value) {
                            echo '<li><strong>' . esc_html($sub_label) . ':</strong> ' . wp_kses_post($sub_value) . '</li>';
                        }
                        echo '</ul>';
                    }
                    echo '</div>';
                }
                echo '</td>';
                echo '</tr>';
                continue;
            }

            // Caso Campo Normal
            $value = isset($item['value']) ? $item['value'] : '';
            echo '<tr>';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td>' . wp_kses_post($value) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        
        // Metadata extra (Info técnica)
        $raw = get_post_meta($post->ID, '_afp_entry_raw', true);
        if ($raw) {
            echo '<details style="margin-top:20px; color:#888;">';
            echo '<summary style="cursor:pointer; font-size:11px;">Ver JSON crudo (Debug)</summary>';
            echo '<pre style="background:#f9f9f9; padding:10px; font-size:10px; overflow:auto;">' . esc_html(json_encode($raw, JSON_PRETTY_PRINT)) . '</pre>';
            echo '</details>';
        }
    }

    /**
     * Renderiza el contenedor del constructor.
     * Pasa los campos anidados directamente a la UI.
     */
    public function render_builder_box($post) {
        $fields = get_post_meta($post->ID, '_afp_fields', true);
        if (empty($fields) || !is_array($fields)) $fields = array();
        AFP_Builder_Core::render($fields);
    }

    /**
     * Renderiza la caja lateral de configuración (Email, Captcha, Estilos).
     */
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

    /**
     * Guarda la metadata del post.
     * Procesa el JSON estructural enviado por JS.
     */
    public function save_meta_data($post_id) {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_save_data')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (get_post_type($post_id) !== 'afp_form') return;

        // 1. Guardar Configuración General
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

        // 2. Guardar Campos (Desde JSON Estructural)
        // El JS intercepta el submit y llena este input hidden con la estructura del DOM
        if (isset($_POST['afp_form_structure_json'])) {
            $json_raw = stripslashes($_POST['afp_form_structure_json']);
            $fields_tree = json_decode($json_raw, true);

            if (is_array($fields_tree)) {
                // Sanitizamos recursivamente antes de guardar
                $clean_fields = $this->sanitize_fields_recursive($fields_tree);
                update_post_meta($post_id, '_afp_fields', $clean_fields);
            }
        } elseif (isset($_POST['afp_save_data']) && !isset($_POST['afp_form_structure_json'])) {
            // Si se envió el form pero el JSON vino vacío o nulo, asumimos borrado total o error JS
            // (Opcional: podrías no hacer nada para prevenir borrados accidentales)
            // update_post_meta($post_id, '_afp_fields', array());
        }
    }

    /**
     * Sanitiza recursivamente el árbol de campos.
     * Fundamental para la seguridad al recibir JSON complejo.
     */
    private function sanitize_fields_recursive($fields) {
        $clean = array();

        foreach ($fields as $f) {
            // 1. Sanitización de propiedades base
            $item = array(
                'type'      => sanitize_text_field(isset($f['type']) ? $f['type'] : 'text'),
                'label'     => sanitize_text_field(isset($f['label']) ? $f['label'] : ''),
                'name'      => sanitize_title(isset($f['name']) ? $f['name'] : ''),
                'required'  => !empty($f['required']) ? 1 : 0,
                'width'     => sanitize_text_field(isset($f['width']) ? $f['width'] : '100'),
                'options'   => sanitize_textarea_field(isset($f['options']) ? $f['options'] : ''),
                
                // Propiedades numéricas
                'min_value' => sanitize_text_field(isset($f['min_value']) ? $f['min_value'] : ''),
                'max_value' => sanitize_text_field(isset($f['max_value']) ? $f['max_value'] : ''),
                
                // Propiedades de archivo
                'allowed_ext' => sanitize_text_field(isset($f['allowed_ext']) ? $f['allowed_ext'] : ''),
                'max_size'    => intval(isset($f['max_size']) ? $f['max_size'] : 0),
            );

            // 2. Recursión: Si tiene hijos (Secciones/Repeaters), límpialos también
            if (isset($f['sub_fields']) && is_array($f['sub_fields'])) {
                $item['sub_fields'] = $this->sanitize_fields_recursive($f['sub_fields']);
            }

            $clean[] = $item;
        }

        return $clean;
    }
}