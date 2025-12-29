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
                    // Llamada recursiva inicial
                    $this->render_fields_recursive($fields, $post_id); 
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
     * Renderiza campos recursivamente soportando Secciones y Repeaters anidados.
     * * @param array $fields Lista de campos (anidados).
     * @param int $form_id ID del formulario.
     * @param string|null $parent_slug Slug del padre (para nombres de inputs en repeaters).
     * @param int|null $row_index Índice de fila (si estamos dentro de un repeater).
     */
    private function render_fields_recursive($fields, $form_id, $parent_slug = null, $row_index = null) {
        if (empty($fields)) return;

        foreach ($fields as $field) {
            $type = isset($field['type']) ? $field['type'] : 'text';

            // --- CASO 1: SECCIÓN (Contenedor Visual) ---
            if ($type === 'section') {
                $sub_fields = isset($field['sub_fields']) ? $field['sub_fields'] : array();
                
                echo '<div class="afp-section-container afp-col-100">';
                echo '<div class="afp-section-break"><h4>' . esc_html($field['label']) . '</h4></div>';
                echo '<div class="afp-fields-wrapper">';
                
                // Recursión para el contenido de la sección
                // Nota: Las secciones no alteran el "name" de los inputs, son solo visuales/agrupadores.
                $this->render_fields_recursive($sub_fields, $form_id, $parent_slug, $row_index);
                
                echo '</div>'; // Fin container sección
                continue;
            }

            // --- CASO 2: REPEATER (Contenedor Lógico) ---
            if ($type === 'repeater') {
                $sub_fields = isset($field['sub_fields']) ? $field['sub_fields'] : array();
                $this->render_repeater_block($field, $sub_fields, $form_id);
                continue;
            }

            // --- CASO 3: CAMPO NORMAL ---
            // Construir el nombre del input
            if ($parent_slug !== null && $row_index !== null) {
                // Estamos dentro de un repeater: afp_data[parent][index][field_name]
                $orig_name = $field['name'];
                $input_name_override = "afp_data[$parent_slug][$row_index][$orig_name]";
                AFP_Field_Renderer::render_input($field, $form_id, $input_name_override);
            } else {
                // Campo raíz normal
                AFP_Field_Renderer::render_input($field, $form_id);
            }
        }
    }

    private function render_repeater_block($head, $subfields, $form_id) {
        $group_slug = $head['name'];
        $group_label = $head['label'];
        
        echo '<div class="afp-col-100">'; 
        echo '<div class="afp-repeater-group" data-group="'.$group_slug.'">';
        echo '<h4>'.esc_html($group_label).'</h4>';
        
        echo '<div class="afp-repeater-rows-container">';
        // Renderizamos una fila inicial (vacía o por defecto)
        $this->render_repeater_row_html($subfields, $form_id, $group_slug, 0);
        echo '</div>';
        
        echo '<button type="button" class="afp-add-repeater-row">+ Agregar otro</button>';
        
        // Template para JS
        echo '<script type="text/template" class="afp-repeater-template">';
        $this->render_repeater_row_html($subfields, $form_id, $group_slug, '{{idx}}');
        echo '</script>';
        
        echo '</div>'; 
        echo '</div>';
    }

    private function render_repeater_row_html($subfields, $form_id, $group_slug, $index) {
        echo '<div class="afp-repeater-row" data-index="'.$index.'">';
        echo '<div class="afp-fields-wrapper">'; 

        // Usamos la misma función recursiva para renderizar los hijos dentro de la fila
        // Pasamos el slug del grupo y el índice para que construya bien los names.
        $this->render_fields_recursive($subfields, $form_id, $group_slug, $index);
        
        echo '</div>'; 
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
?>