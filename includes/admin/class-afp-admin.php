<?php
if (!defined('ABSPATH')) exit;

/**
 * Controller del área de administración.
 * Gestiona la conversión de datos planos (UI) a anidados (DB) y viceversa.
 */
class AFP_Admin {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
    }

    public function add_meta_boxes() {
        add_meta_box('afp_settings', 'Configuración General', array($this, 'render_settings_box'), 'afp_form', 'side', 'high');
        add_meta_box('afp_builder', 'Constructor de Campos (Drag & Drop)', array($this, 'render_builder_box'), 'afp_form', 'normal', 'high');
    }

    /**
     * Prepara los datos para el UI del constructor.
     * Convierte la estructura anidada de la DB en una lista plana para el JS sortable.
     * * @param WP_Post $post Objeto post actual.
     */
    public function render_builder_box($post) {
        $fields = get_post_meta($post->ID, '_afp_fields', true);
        
        if (empty($fields) || !is_array($fields)) {
            $fields = array();
        }

        // APLANAR: Convertimos árbol -> lista plana para el UI
        $flat_fields = $this->flatten_fields($fields);

        AFP_Builder_UI::render_app($flat_fields);
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
        <p><label><strong>Email Receptor</strong></label><input type="email" name="afp_settings[email]" value="<?php echo esc_attr($email); ?>" class="widefat"></p>
        <p><label><strong>Asunto</strong></label><input type="text" name="afp_settings[subject]" value="<?php echo esc_attr($subject); ?>" class="widefat"></p>
        <hr>
        <p><strong>Google reCAPTCHA v2</strong></p>
        <p><label>Site Key:</label><input type="text" name="afp_settings[recaptcha_site_key]" value="<?php echo esc_attr($site_key); ?>" class="widefat"></p>
        <p><label>Secret Key:</label><input type="text" name="afp_settings[recaptcha_secret_key]" value="<?php echo esc_attr($secret_key); ?>" class="widefat"></p>
        <hr>
        <p><label><strong>Texto Botón</strong></label><input type="text" name="afp_settings[btn_text]" value="<?php echo esc_attr($btn_text); ?>" class="widefat"></p>
        <p><label><strong>Color Botón</strong></label><br><input type="color" name="afp_settings[btn_color]" value="<?php echo esc_attr($btn_color); ?>"></p>
        <p><label><input type="checkbox" name="afp_settings[hide_title]" value="1" <?php checked($hide_title, 1); ?>> Ocultar Título</label></p>
        <?php
    }

    /**
     * Guarda la metadata del post.
     * Convierte la lista plana enviada por POST en una estructura anidada.
     * * @param int $post_id ID del post.
     */
    public function save_meta_data($post_id) {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_save_data')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // 1. Guardar Configuración
        if (isset($_POST['afp_settings'])) {
            $clean_settings = array_map('sanitize_text_field', $_POST['afp_settings']);
            // Excepciones de sanitización específicas
            $clean_settings['email'] = sanitize_email($_POST['afp_settings']['email']);
            $clean_settings['btn_color'] = sanitize_hex_color($_POST['afp_settings']['btn_color']);
            $clean_settings['hide_title'] = isset($_POST['afp_settings']['hide_title']) ? 1 : 0;
            update_post_meta($post_id, '_afp_settings', $clean_settings);
        }

        // 2. Guardar Campos (ANIDAR)
        if (isset($_POST['afp_fields'])) {
            $raw_fields = array_values($_POST['afp_fields']);
            
            // Sanitización básica inicial
            $sanitized_fields = array_map(function($f) {
                return array(
                    'type'      => sanitize_text_field($f['type']),
                    'label'     => sanitize_text_field($f['label']),
                    'name'      => sanitize_title($f['name']),
                    'required'  => isset($f['required']) ? 1 : 0,
                    'width'     => isset($f['width']) ? sanitize_text_field($f['width']) : '100',
                    'options'   => isset($f['options']) ? sanitize_textarea_field($f['options']) : '',
                    'min_value' => isset($f['min_value']) ? sanitize_text_field($f['min_value']) : '',
                    'max_value' => isset($f['max_value']) ? sanitize_text_field($f['max_value']) : '',
                    'allowed_ext' => isset($f['allowed_ext']) ? sanitize_text_field($f['allowed_ext']) : '',
                    'max_size'    => isset($f['max_size']) ? intval($f['max_size']) : 5,
                );
            }, $raw_fields);

            // Convertir a Árbol (Nesting)
            $nested_fields = $this->nest_fields($sanitized_fields);
            
            update_post_meta($post_id, '_afp_fields', $nested_fields);
        } else {
            update_post_meta($post_id, '_afp_fields', array());
        }
    }

    /**
     * Convierte una lista plana (con repeater_start/end y sections) en un árbol anidado.
     * * @param array $flat_fields Lista plana de campos.
     * @return array Estructura anidada.
     */
    private function nest_fields($flat_fields) {
        $tree = array();
        $pointer_stack = array(&$tree); // Pila de referencias para saber dónde insertar
        
        // El puntero actual siempre es el último elemento de la pila
        // Inicialmente apunta a la raíz ($tree)

        foreach ($flat_fields as $field) {
            $current_scope = &$pointer_stack[count($pointer_stack) - 1];

            if ($field['type'] === 'section') {
                // Las secciones agrupan todo lo que sigue hasta la próxima sección.
                // Si estamos dentro de una sección (pero no dentro de un repeater), salimos al root primero.
                // Simplificación: Asumimos que las secciones son de nivel superior o directo bajo root.
                
                // Si ya estamos dentro de una "section", volvemos al root (nivel 0)
                // Nota: Esto asume que no hay "Sección dentro de Sección".
                if (count($pointer_stack) > 1 && $this->is_section_scope($pointer_stack)) {
                    array_pop($pointer_stack);
                    $current_scope = &$pointer_stack[count($pointer_stack) - 1];
                }

                // Creamos el contenedor de sección
                $new_section = $field;
                $new_section['sub_fields'] = array();
                
                // Añadimos al scope actual (usando índice para mantener referencia)
                $current_scope[] = $new_section;
                $last_idx = count($current_scope) - 1;
                
                // Apuntamos ahora a los sub_fields de esta nueva sección
                $pointer_stack[] = &$current_scope[$last_idx]['sub_fields'];

            } elseif ($field['type'] === 'repeater_start') {
                // Convertimos a tipo 'repeater' contenedor
                $repeater = $field;
                $repeater['type'] = 'repeater'; // Cambio de nombre interno
                $repeater['sub_fields'] = array();

                $current_scope[] = $repeater;
                $last_idx = count($current_scope) - 1;
                
                // Entramos en el repeater
                $pointer_stack[] = &$current_scope[$last_idx]['sub_fields'];

            } elseif ($field['type'] === 'repeater_end') {
                // Salimos del repeater (Pop stack)
                if (count($pointer_stack) > 1) {
                    array_pop($pointer_stack);
                }

            } else {
                // Campos normales
                $current_scope[] = $field;
            }
        }

        return $tree;
    }

    /**
     * Verifica si el scope actual pertenece a una Sección (para lógica de auto-cierre).
     */
    private function is_section_scope($stack) {
        // Esta es una comprobación heurística básica. 
        // En una implementación real más compleja podríamos rastrear el tipo de scope en la pila.
        // Aquí asumimos que si profundidad es 2 (Root -> Section), estamos en sección.
        // Si profundidad es 3 (Root -> Section -> Repeater), no cerramos sección con otra sección.
        return count($stack) == 2; 
    }

    /**
     * Convierte la estructura anidada de vuelta a lista plana para el UI.
     * Recrea los 'repeater_start' y 'repeater_end'.
     * * @param array $nested_fields Árbol de campos.
     * @return array Lista plana.
     */
    private function flatten_fields($nested_fields) {
        $flat = array();

        foreach ($nested_fields as $field) {
            // Caso Sección
            if ($field['type'] === 'section') {
                $sub = isset($field['sub_fields']) ? $field['sub_fields'] : array();
                unset($field['sub_fields']); // Quitamos hijos del nodo padre
                
                $flat[] = $field; // Añadimos la cabecera de sección
                
                // Recursión para el contenido de la sección
                $children = $this->flatten_fields($sub);
                foreach ($children as $child) $flat[] = $child;

            // Caso Repeater
            } elseif ($field['type'] === 'repeater') {
                $sub = isset($field['sub_fields']) ? $field['sub_fields'] : array();
                unset($field['sub_fields']);

                // Convertimos 'repeater' -> 'repeater_start'
                $field['type'] = 'repeater_start';
                $flat[] = $field;

                // Recursión hijos
                $children = $this->flatten_fields($sub);
                foreach ($children as $child) $flat[] = $child;

                // Añadimos 'repeater_end'
                $flat[] = array(
                    'type' => 'repeater_end',
                    'label' => '', 
                    'width' => '100'
                );

            } else {
                // Campo normal
                $flat[] = $field;
            }
        }
        return $flat;
    }
}
?>