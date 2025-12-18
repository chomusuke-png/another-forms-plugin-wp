<?php
if (!defined('ABSPATH')) exit;

class AFP_Shortcode {

    public function __construct() {
        add_shortcode('another_form', array($this, 'render_form'));
    }

    public function render_form($atts) {
        $a = shortcode_atts(array('id' => 0), $atts);
        if (empty($a['id'])) return '<p>Error: ID de formulario no especificado.</p>';

        $post_id  = intval($a['id']);
        $settings = get_post_meta($post_id, '_afp_settings', true);
        $fields   = get_post_meta($post_id, '_afp_fields', true);
        
        if (!$settings || empty($fields)) return '<p>Formulario no encontrado o sin campos.</p>';

        $form_title = get_the_title($post_id);
        $btn_text   = !empty($settings['btn_text']) ? $settings['btn_text'] : 'Enviar';
        $btn_color  = !empty($settings['btn_color']) ? $settings['btn_color'] : '#947e1e';

        // Generamos un ID único para aislar los estilos de este formulario específico
        $unique_id = 'afp-wrapper-' . $post_id;

        ob_start();
        $this->render_messages();
        ?>

        <style>
            #<?php echo $unique_id; ?> .afp-btn {
                background-color: <?php echo esc_attr($btn_color); ?>;
            }
            /* Oscurece el botón automáticamente al pasar el mouse */
            #<?php echo $unique_id; ?> .afp-btn:hover {
                background-color: <?php echo esc_attr($btn_color); ?>;
                filter: brightness(0.85); 
            }
            /* Sombra suave usando el mismo color */
            #<?php echo $unique_id; ?> .afp-btn:focus {
                box-shadow: 0 0 0 4px <?php echo esc_attr($btn_color); ?>33; /* 33 = ~20% opacidad */
            }
        </style>

        <div id="<?php echo $unique_id; ?>" class="afp-form-container">
            <h3 class="afp-title"><?php echo esc_html($form_title); ?></h3>

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="afp-form">
                
                <input type="hidden" name="action" value="process_another_form">
                <input type="hidden" name="afp_form_id" value="<?php echo esc_attr($post_id); ?>">
                <?php wp_nonce_field('afp_verify_action', 'afp_nonce'); ?>

                <?php foreach ($fields as $field) : 
                    $field_id = 'afp_field_' . esc_attr($field['name']) . '_' . $post_id; // ID único para evitar conflictos si hay varios forms
                    $required = $field['required'] ? 'required' : '';
                ?>
                    <div class="afp-row">
                        <label for="<?php echo $field_id; ?>">
                            <?php echo esc_html($field['label']); ?> 
                            <?php if ($required) echo '<span style="color:red">*</span>'; ?>
                        </label>

                        <?php if ($field['type'] === 'textarea') : ?>
                            <textarea name="afp_data[<?php echo esc_attr($field['name']); ?>]" id="<?php echo $field_id; ?>" rows="5" <?php echo $required; ?>></textarea>
                        <?php else : ?>
                            <input type="<?php echo esc_attr($field['type']); ?>" name="afp_data[<?php echo esc_attr($field['name']); ?>]" id="<?php echo $field_id; ?>" <?php echo $required; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="afp-btn"><?php echo esc_html($btn_text); ?></button>
            </form>
        </div>

        <?php
        return ob_get_clean();
    }

    private function render_messages() {
        if (isset($_GET['afp_status']) && $_GET['afp_status'] === 'success') {
            echo '<div class="afp-alert success">Mensaje enviado correctamente.</div>';
        }
        if (isset($_GET['afp_status']) && $_GET['afp_status'] === 'error') {
            echo '<div class="afp-alert error">Error al enviar. Verifica los campos.</div>';
        }
    }
}