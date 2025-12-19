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
            .afp-captcha-container { margin-bottom: 20px; }
        </style>

        <div id="<?php echo $unique_id; ?>" class="afp-form-container">
            <?php if (!$hide_title): ?>
                <h3 class="afp-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="afp-form">
                <input type="hidden" name="action" value="process_another_form">
                <input type="hidden" name="afp_form_id" value="<?php echo esc_attr($post_id); ?>">
                <?php wp_nonce_field('afp_verify_action', 'afp_nonce'); ?>

                <div class="afp-fields-wrapper">
                    <?php foreach ($fields as $field) { $this->render_field_html($field, $post_id); } ?>
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

    private function render_field_html($field, $form_id) {
        $type  = $field['type'];
        $label = $field['label'];
        $name  = isset($field['name']) ? $field['name'] : '';
        $req   = !empty($field['required']) ? 'required' : '';
        $width = isset($field['width']) ? $field['width'] : '100';
        $field_id = 'afp_' . $name . '_' . $form_id . '_' . rand(100,999);
        
        $style = ($width !== '100') ? "float:left; width:{$width}%; padding-right:15px;" : "";
        
        if ($type === 'section') {
            echo '<div class="afp-section-break" style="clear:both;"><h4>' . esc_html($label) . '</h4></div>';
            return;
        }

        echo '<div class="afp-row" style="' . esc_attr($style) . '">';
        echo '<label for="' . esc_attr($field_id) . '">' . esc_html($label) . ($req ? ' <span class="req">*</span>' : '') . '</label>';

        switch ($type) {
            case 'textarea':
                echo '<textarea name="afp_data['.$name.']" id="'.$field_id.'" rows="4" '.$req.'></textarea>';
                break;
            case 'select':
                $opts = explode("\n", $field['options']);
                echo '<select name="afp_data['.$name.']" id="'.$field_id.'" '.$req.'>';
                echo '<option value="">-- Seleccionar --</option>';
                foreach ($opts as $opt) {
                    $opt = trim($opt);
                    if ($opt) echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                }
                echo '</select>';
                break;
            case 'radio':
                $opts = explode("\n", $field['options']);
                echo '<div class="afp-radio-group">';
                foreach ($opts as $k => $opt) {
                    $opt = trim($opt);
                    if ($opt) echo '<label class="afp-inline-label"><input type="radio" name="afp_data['.$name.']" value="'.esc_attr($opt).'" '.$req.'> '.esc_html($opt).'</label>';
                }
                echo '</div>';
                break;
            case 'checkbox':
                if (!empty($field['options'])) {
                    $opts = explode("\n", $field['options']);
                    echo '<div class="afp-check-group">';
                    foreach ($opts as $opt) {
                        $opt = trim($opt);
                        if ($opt) echo '<label class="afp-inline-label"><input type="checkbox" name="afp_data['.$name.'][]" value="'.esc_attr($opt).'"> '.esc_html($opt).'</label>';
                    }
                    echo '</div>';
                } else {
                    echo '<label class="afp-inline-label"><input type="checkbox" name="afp_data['.$name.']" value="Sí" '.$req.'> Confirmar</label>';
                }
                break;
            default:
                // CASO GENÉRICO (Texto, Email, Date, Number)
                // Aquí aplicamos los atributos Min y Max si existen y si el campo es numérico
                $extra_attrs = '';
                if ($type === 'number') {
                    if (isset($field['min_value']) && $field['min_value'] !== '') $extra_attrs .= ' min="'.esc_attr($field['min_value']).'"';
                    if (isset($field['max_value']) && $field['max_value'] !== '') $extra_attrs .= ' max="'.esc_attr($field['max_value']).'"';
                }
                
                echo '<input type="'.$type.'" name="afp_data['.$name.']" id="'.$field_id.'" '.$req.' '.$extra_attrs.'>';
                break;
        }
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