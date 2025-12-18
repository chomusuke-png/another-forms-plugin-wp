<?php
if (!defined('ABSPATH')) exit;

class AFP_CPT {

    public function __construct() {
        add_action('init', array($this, 'register_cpt'));
        add_action('add_meta_boxes', array($this, 'add_custom_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
        add_filter('manage_afp_form_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_afp_form_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
    }

    public function register_cpt() {
        $labels = array(
            'name'               => 'Formularios',
            'singular_name'      => 'Formulario',
            'menu_name'          => 'Formularios AFP',
            'add_new'            => 'Añadir Nuevo',
            'add_new_item'       => 'Añadir Nuevo Formulario',
            'edit_item'          => 'Editar Formulario',
            'all_items'          => 'Todos los Formularios',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'supports'           => array('title'),
            'menu_icon'          => 'dashicons-feedback',
            'capability_type'    => 'post',
        );

        register_post_type('afp_form', $args);
    }

    public function add_custom_meta_boxes() {
        add_meta_box('afp_form_config', 'Configuración del Formulario', array($this, 'render_config_metabox'), 'afp_form', 'normal', 'high');
    }

    public function render_config_metabox($post) {
        wp_nonce_field('afp_save_form_data', 'afp_nonce');

        $settings = get_post_meta($post->ID, '_afp_settings', true);
        $fields   = get_post_meta($post->ID, '_afp_fields', true);

        // Valores por defecto
        $recipient_email = isset($settings['email']) ? $settings['email'] : get_option('admin_email');
        $email_subject   = isset($settings['subject']) ? $settings['subject'] : 'Nuevo Mensaje';
        $btn_text        = isset($settings['btn_text']) ? $settings['btn_text'] : 'Enviar';
        $btn_color       = isset($settings['btn_color']) ? $settings['btn_color'] : '#1a428a'; 
        
        // Nueva opción: Ocultar Título
        $hide_title      = isset($settings['hide_title']) ? $settings['hide_title'] : 0;

        if (empty($fields)) $fields = array(); 
        ?>
        <div class="afp-admin-panel">
            <style>
                .afp-field-item { display: flex; gap: 10px; padding: 10px; background: #fff; border: 1px solid #eee; margin-bottom: 5px; align-items: center; }
                .afp-field-item input[type="text"], .afp-field-item select { flex: 1; }
            </style>

            <h4>Ajustes Generales</h4>
            <table class="form-table">
                <tr>
                    <th><label>Email Receptor</label></th>
                    <td><input type="email" name="afp_settings[email]" value="<?php echo esc_attr($recipient_email); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Asunto del Correo</label></th>
                    <td><input type="text" name="afp_settings[subject]" value="<?php echo esc_attr($email_subject); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Opciones Visuales</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="afp_settings[hide_title]" value="1" <?php checked($hide_title, 1); ?>>
                            Ocultar Título del formulario
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label>Botón de Enviar</label></th>
                    <td>
                        <input type="text" name="afp_settings[btn_text]" value="<?php echo esc_attr($btn_text); ?>" placeholder="Texto del botón">
                        <input type="color" name="afp_settings[btn_color]" value="<?php echo esc_attr($btn_color); ?>" style="vertical-align: middle; margin-left: 10px;">
                    </td>
                </tr>
            </table>

            <hr>

            <h4>Campos del Formulario</h4>
            <div id="afp-fields-container">
                <?php if (!empty($fields)) : foreach ($fields as $index => $field) : ?>
                    <div class="afp-field-item">
                        <input type="text" name="afp_fields[<?php echo $index; ?>][label]" placeholder="Etiqueta" value="<?php echo esc_attr($field['label']); ?>" required>
                        <input type="text" name="afp_fields[<?php echo $index; ?>][name]" placeholder="ID Campo (slug)" value="<?php echo esc_attr($field['name']); ?>" required>
                        <select name="afp_fields[<?php echo $index; ?>][type]">
                            <option value="text" <?php selected($field['type'], 'text'); ?>>Texto</option>
                            <option value="email" <?php selected($field['type'], 'email'); ?>>Email</option>
                            <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>Área de Texto</option>
                        </select>
                        <label><input type="checkbox" name="afp_fields[<?php echo $index; ?>][required]" value="1" <?php checked(isset($field['required']) && $field['required']); ?>> Req.</label>
                        <button type="button" class="button remove-row">X</button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            
            <p><button type="button" class="button button-primary" id="add-afp-field">Añadir Campo</button></p>

            <script>
                jQuery(document).ready(function($) {
                    var container = $('#afp-fields-container');
                    $('#add-afp-field').on('click', function() {
                        var index = container.children().length + 100; 
                        var html = `
                            <div class="afp-field-item">
                                <input type="text" name="afp_fields[`+index+`][label]" placeholder="Etiqueta" required>
                                <input type="text" name="afp_fields[`+index+`][name]" placeholder="ID Campo (slug)" required>
                                <select name="afp_fields[`+index+`][type]">
                                    <option value="text">Texto</option>
                                    <option value="email">Email</option>
                                    <option value="textarea">Área de Texto</option>
                                </select>
                                <label><input type="checkbox" name="afp_fields[`+index+`][required]" value="1"> Req.</label>
                                <button type="button" class="button remove-row">X</button>
                            </div>`;
                        container.append(html);
                    });
                    $(document).on('click', '.remove-row', function() { $(this).closest('.afp-field-item').remove(); });
                });
            </script>
        </div>
        <?php
    }

    public function save_meta_data($post_id) {
        if (!isset($_POST['afp_nonce']) || !wp_verify_nonce($_POST['afp_nonce'], 'afp_save_form_data')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['afp_settings'])) {
            $clean_settings = array(
                'email'      => sanitize_email($_POST['afp_settings']['email']),
                'subject'    => sanitize_text_field($_POST['afp_settings']['subject']),
                'btn_text'   => sanitize_text_field($_POST['afp_settings']['btn_text']),
                'btn_color'  => sanitize_hex_color($_POST['afp_settings']['btn_color']),
                'hide_title' => isset($_POST['afp_settings']['hide_title']) ? 1 : 0, // Guardamos el checkbox
            );
            update_post_meta($post_id, '_afp_settings', $clean_settings);
        }

        if (isset($_POST['afp_fields'])) {
            $clean_fields = array();
            foreach ($_POST['afp_fields'] as $field) {
                if (!empty($field['label']) && !empty($field['name'])) {
                    $clean_fields[] = array(
                        'label'    => sanitize_text_field($field['label']),
                        'name'     => sanitize_title($field['name']),
                        'type'     => sanitize_text_field($field['type']),
                        'required' => isset($field['required']) ? 1 : 0
                    );
                }
            }
            update_post_meta($post_id, '_afp_fields', $clean_fields);
        } else {
            update_post_meta($post_id, '_afp_fields', array());
        }
    }

    public function set_custom_columns($columns) {
        $columns['shortcode'] = 'Shortcode';
        return $columns;
    }

    public function render_custom_columns($column, $post_id) {
        if ($column === 'shortcode') echo '<code style="user-select:all;">[another_form id="' . $post_id . '"]</code>';
    }
}