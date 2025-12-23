<?php
if (!defined('ABSPATH')) exit;

class AFP_Renderer {

    public function __construct() {
        add_shortcode('another_form', array($this, 'render_form'));
    }

    public function render_form($atts) {
        $a = shortcode_atts(array('id' => 0), $atts);
        if (empty($a['id'])) return '';

        $post_id  = intval($a['id']);
        $settings = get_post_meta($post_id, '_afp_settings', true);
        $fields   = get_post_meta($post_id, '_afp_fields', true);
        
        if (!$settings || empty($fields)) return '<p>Formulario vacío.</p>';

        $btn_text   = !empty($settings['btn_text']) ? $settings['btn_text'] : 'Enviar';
        $btn_color  = !empty($settings['btn_color']) ? $settings['btn_color'] : '#1a428a';
        $hide_title = !empty($settings['hide_title']);
        $title      = get_the_title($post_id);
        $unique_id  = 'afp-' . $post_id;
        
        $site_key = isset($settings['recaptcha_site_key']) ? $settings['recaptcha_site_key'] : '';
        $use_captcha = !empty($site_key);

        if ($use_captcha) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        }

        ob_start();
        $this->render_messages();
        ?>
        
        <style>
            #<?php echo $unique_id; ?> .afp-btn { background-color: <?php echo esc_attr($btn_color); ?>; }
            #<?php echo $unique_id; ?> .afp-btn:hover { filter: brightness(0.9); }
        </style>

        <div id="<?php echo $unique_id; ?>" class="afp-form-container">
            <?php if (!$hide_title): ?>
                <h3 class="afp-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="afp-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="process_another_form">
                <input type="hidden" name="afp_form_id" value="<?php echo esc_attr($post_id); ?>">
                <?php wp_nonce_field('afp_verify_action', 'afp_nonce'); ?>

                <div class="afp-fields-wrapper">
                    <?php 
                    // === LÓGICA DE REPEATER ===
                    $in_repeater = false;
                    $repeater_buffer = array();
                    $repeater_head = null;

                    foreach ($fields as $field) {
                        
                        if ($field['type'] === 'repeater_start') {
                            $in_repeater = true;
                            $repeater_head = $field;
                            $repeater_buffer = array(); 
                            continue;
                        }

                        if ($field['type'] === 'repeater_end') {
                            if ($in_repeater && $repeater_head) {
                                $this->render_repeater_block($repeater_head, $repeater_buffer, $post_id);
                            }
                            $in_repeater = false;
                            $repeater_buffer = array();
                            $repeater_head = null;
                            continue;
                        }

                        if ($in_repeater) {
                            $repeater_buffer[] = $field;
                        } else {
                            // DELEGACIÓN A LA NUEVA CLASE VISUAL
                            AFP_Field_Renderer::render_input($field, $post_id);
                        }
                    }
                    ?>
                </div>

                <?php if ($use_captcha): ?>
                    <div class="afp-captcha-container">
                        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($site_key); ?>"></div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="afp-btn"><?php echo esc_html($btn_text); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_repeater_block($head, $subfields, $form_id) {
        $group_slug = $head['name'];
        $group_label = $head['label'];
        
        echo '<div class="afp-col-100">';
        echo '<div class="afp-repeater-group" data-group="'.$group_slug.'">';
        echo '<h4>'.esc_html($group_label).'</h4>';
        echo '<div class="afp-repeater-rows-container">';
        
        $this->render_repeater_row_html($subfields, $form_id, $group_slug, 0);
        
        echo '</div>';
        echo '<button type="button" class="afp-add-repeater-row">+ Agregar otro</button>';
        
        echo '<script type="text/template" class="afp-repeater-template">';
        $this->render_repeater_row_html($subfields, $form_id, $group_slug, '{{idx}}');
        echo '</script>';
        
        echo '</div>';
    }

    private function render_repeater_row_html($subfields, $form_id, $group_slug, $index) {
        echo '<div class="afp-repeater-row" data-index="'.$index.'">';
        
        // --- CORRECCIÓN: Wrapper para activar Flexbox/Grid ---
        echo '<div class="afp-fields-wrapper">'; 
        
        foreach ($subfields as $field) {
            $orig_name = $field['name'];
            $new_name_attr = "afp_data[$group_slug][$index][$orig_name]";
            // Renderiza el campo
            AFP_Field_Renderer::render_input($field, $form_id, $new_name_attr);
        }
        
        echo '</div>'; // --- Cierre del Wrapper ---

        echo '<button type="button" class="afp-remove-repeater-row" title="Eliminar fila">&times;</button>';
        echo '</div>';
    }

    private function render_messages() {
        if (isset($_GET['afp_status'])) {
            if ($_GET['afp_status'] === 'success') echo '<div class="afp-alert success">Enviado correctamente.</div>';
            elseif ($_GET['afp_status'] === 'captcha_error') echo '<div class="afp-alert error">Error: Verifica que no eres un robot.</div>';
            else echo '<div class="afp-alert error">Error al enviar. Inténtalo de nuevo.</div>';
        }
    }
}