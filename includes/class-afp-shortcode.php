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
        $btn_color  = !empty($settings['btn_color']) ? $settings['btn_color'] : '#1a428a';
        $hide_title = !empty($settings['hide_title']);

        $unique_id = 'afp-wrapper-' . $post_id;

        // --- Lógica de Captcha Matemático Simple ---
        $n1 = rand(1, 9);
        $n2 = rand(1, 9);
        $captcha_sum = $n1 + $n2;
        // Hash de la respuesta correcta para validación segura
        $captcha_hash = wp_hash((string)$captcha_sum);

        ob_start();
        $this->render_messages();
        ?>

        <style>
            #<?php echo $unique_id; ?> .afp-btn {
                background-color: <?php echo esc_attr($btn_color); ?>;
            }
            #<?php echo $unique_id; ?> .afp-btn:hover {
                /* Oscurecer solo un poco para mantener el estilo plano */
                filter: brightness(0.9); 
            }
        </style>

        <div id="<?php echo $unique_id; ?>" class="afp-form-container">
            
            <?php if (!$hide_title): ?>
                <h3 class="afp-title"><?php echo esc_html($form_title); ?></h3>
            <?php endif; ?>

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="afp-form">
                
                <input type="hidden" name="action" value="process_another_form">
                <input type="hidden" name="afp_form_id" value="<?php echo esc_attr($post_id); ?>">
                
                <input type="hidden" name="afp_captcha_hash" value="<?php echo esc_attr($captcha_hash); ?>">

                <?php wp_nonce_field('afp_verify_action', 'afp_nonce'); ?>

                <?php foreach ($fields as $field) : 
                    $field_id = 'afp_field_' . esc_attr($field['name']) . '_' . $post_id;
                    $required = $field['required'] ? 'required' : '';
                ?>
                    <div class="afp-row">
                        <label for="<?php echo $field_id; ?>">
                            <?php echo esc_html($field['label']); ?> 
                            <?php if ($required) echo '<span style="color:#d9534f">*</span>'; ?>
                        </label>

                        <?php if ($field['type'] === 'textarea') : ?>
                            <textarea name="afp_data[<?php echo esc_attr($field['name']); ?>]" id="<?php echo $field_id; ?>" rows="5" <?php echo $required; ?>></textarea>
                        <?php else : ?>
                            <input type="<?php echo esc_attr($field['type']); ?>" name="afp_data[<?php echo esc_attr($field['name']); ?>]" id="<?php echo $field_id; ?>" <?php echo $required; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="afp-row afp-captcha-row">
                    <span class="afp-captcha-label">CAPTCHA: ¿Cuánto es <?php echo $n1; ?> + <?php echo $n2; ?>?</span>
                    <input type="number" name="afp_captcha_input" class="afp-captcha-input" required placeholder="?">
                </div>

                <button type="submit" class="afp-btn"><?php echo esc_html($btn_text); ?></button>
            </form>
        </div>

        <?php
        return ob_get_clean();
    }

    private function render_messages() {
        if (isset($_GET['afp_status'])) {
            if ($_GET['afp_status'] === 'success') {
                echo '<div class="afp-alert success">Mensaje enviado correctamente.</div>';
            } elseif ($_GET['afp_status'] === 'captcha_error') {
                echo '<div class="afp-alert error">Error: La respuesta de seguridad es incorrecta.</div>';
            } else {
                echo '<div class="afp-alert error">Error al enviar. Inténtalo de nuevo.</div>';
            }
        }
    }
}