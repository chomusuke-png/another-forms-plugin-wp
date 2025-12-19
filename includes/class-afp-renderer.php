<?php
if (!defined('ABSPATH')) exit;

/**
 * Renderiza el formulario en el frontend.
 */
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
        
        // Credenciales reCAPTCHA
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

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="afp-form">
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
                        
                        // 1. Detectar Inicio Repeater
                        if ($field['type'] === 'repeater_start') {
                            $in_repeater = true;
                            $repeater_head = $field;
                            $repeater_buffer = array(); 
                            continue;
                        }

                        // 2. Detectar Fin Repeater
                        if ($field['type'] === 'repeater_end') {
                            if ($in_repeater && $repeater_head) {
                                // Renderizar el bloque completo
                                $this->render_repeater_block($repeater_head, $repeater_buffer, $post_id);
                            }
                            $in_repeater = false;
                            $repeater_buffer = array();
                            $repeater_head = null;
                            continue;
                        }

                        // 3. Procesar Campos
                        if ($in_repeater) {
                            $repeater_buffer[] = $field; // Guardar en memoria temporal
                        } else {
                            $this->render_field_html($field, $post_id); // Render directo
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

    /**
     * Renderiza el bloque repetible (Contenedor + Fila 0 + Template JS)
     */
    private function render_repeater_block($head, $subfields, $form_id) {
        $group_slug = $head['name'];
        $group_label = $head['label'];
        
        echo '<div class="afp-repeater-group" data-group="'.$group_slug.'">';
        echo '<h4>'.esc_html($group_label).'</h4>';
        echo '<div class="afp-repeater-rows-container">';
        
        // Fila 0 (Inicial visible)
        $this->render_repeater_row_html($subfields, $form_id, $group_slug, 0);
        
        echo '</div>'; // Fin rows-container
        echo '<button type="button" class="afp-add-repeater-row">+ Agregar otro</button>';
        
        // Template Oculto para JS (usa {{idx}} como placeholder)
        echo '<script type="text/template" class="afp-repeater-template">';
        $this->render_repeater_row_html($subfields, $form_id, $group_slug, '{{idx}}');
        echo '</script>';
        
        echo '</div>'; // Fin group
    }

    /**
     * Renderiza una fila individual del repeater.
     * Modifica el 'name' de los inputs para ser array multidimensional.
     */
    private function render_repeater_row_html($subfields, $form_id, $group_slug, $index) {
        echo '<div class="afp-repeater-row" data-index="'.$index.'">';
        foreach ($subfields as $field) {
            $orig_name = $field['name'];
            // Formato de array: afp_data[grupo][indice][campo]
            $new_name_attr = "afp_data[$group_slug][$index][$orig_name]";
            
            // Renderizamos usando la función base pero con nombre sobreescrito
            $this->render_field_html($field, $form_id, $new_name_attr);
        }
        echo '<button type="button" class="afp-remove-repeater-row" title="Eliminar fila">&times;</button>';
        echo '</div>';
    }

    /**
     * Renderiza el HTML de un campo individual.
     */
    private function render_field_html($field, $form_id, $name_override = null) {
        $type  = $field['type'];
        $label = $field['label'];
        $req   = !empty($field['required']) ? 'required' : '';
        $width = isset($field['width']) ? $field['width'] : '100';
        
        // Usamos name_override si viene del repeater, sino el estándar
        $base_name = isset($field['name']) ? $field['name'] : '';
        $input_name = $name_override ? $name_override : "afp_data[$base_name]";
        
        // Generamos ID único. Si es template {{idx}}, usamos ID genérico para no romper JS
        $field_id = 'afp_' . $base_name . '_' . $form_id . '_' . rand(1000,9999);
        if (strpos($input_name, '{{idx}}') !== false) {
             $field_id = 'afp_' . $base_name . '_tpl'; 
        }

        $style = ($width !== '100') ? "float:left; width:{$width}%; padding-right:15px;" : "";
        
        if ($type === 'section') {
            echo '<div class="afp-section-break" style="clear:both;"><h4>' . esc_html($label) . '</h4></div>';
            return;
        }

        echo '<div class="afp-row" style="' . esc_attr($style) . '">';
        echo '<label>' . esc_html($label) . ($req ? ' <span class="req">*</span>' : '') . '</label>';

        switch ($type) {
            case 'textarea':
                echo '<textarea name="'.$input_name.'" rows="4" '.$req.'></textarea>';
                break;
                
            case 'select':
                $opts = explode("\n", $field['options']);
                echo '<select name="'.$input_name.'" '.$req.'>';
                echo '<option value="">-- Seleccionar --</option>';
                foreach ($opts as $opt) { 
                    $opt = trim($opt); 
                    if($opt) echo '<option value="'.esc_attr($opt).'">'.esc_html($opt).'</option>'; 
                }
                echo '</select>';
                break;
                
            case 'radio':
                $opts = explode("\n", $field['options']);
                echo '<div class="afp-radio-group">';
                foreach ($opts as $opt) {
                    $opt = trim($opt);
                    if ($opt) echo '<label class="afp-inline-label"><input type="radio" name="'.$input_name.'" value="'.esc_attr($opt).'" '.$req.'> '.esc_html($opt).'</label>';
                }
                echo '</div>';
                break;
                
            case 'checkbox':
                if (!empty($field['options'])) {
                    $opts = explode("\n", $field['options']);
                    echo '<div class="afp-check-group">';
                    foreach ($opts as $opt) {
                        $opt = trim($opt);
                        if ($opt) echo '<label class="afp-inline-label"><input type="checkbox" name="'.$input_name.'[]" value="'.esc_attr($opt).'"> '.esc_html($opt).'</label>';
                    }
                    echo '</div>';
                } else {
                    echo '<label class="afp-inline-label"><input type="checkbox" name="'.$input_name.'" value="Sí" '.$req.'> Confirmar</label>';
                }
                break;
                
            case 'chips':
                // Chips requiere manejo especial de array en el name
                $chip_name = $input_name; 
                echo '<div class="afp-chips-wrapper" data-name="'.esc_attr($chip_name).'">';
                echo '<div class="afp-chips-container">';
                
                // Input Oculto o Select Oculto (según si hay opciones definidas)
                if (!empty($field['options'])) {
                    $opts = explode("\n", $field['options']);
                    echo '<select class="afp-new-chip-input" style="display:none;">';
                    echo '<option value="">Seleccionar...</option>';
                    foreach ($opts as $opt) {
                        $opt = trim($opt);
                        if ($opt) echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                } else {
                    echo '<input type="text" class="afp-new-chip-input" style="display:none;" placeholder="Escribe...">';
                }

                echo '<button type="button" class="afp-add-chip-trigger">+ Añadir</button>';
                echo '</div></div>';
                break;
                
            default: // Text, email, number, date...
                $extra_attrs = '';
                if ($type === 'number') {
                    if (isset($field['min_value']) && $field['min_value'] !== '') $extra_attrs .= ' min="'.esc_attr($field['min_value']).'"';
                    if (isset($field['max_value']) && $field['max_value'] !== '') $extra_attrs .= ' max="'.esc_attr($field['max_value']).'"';
                }
                echo '<input type="'.$type.'" name="'.$input_name.'" '.$req.' '.$extra_attrs.'>';
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