<?php
if (!defined('ABSPATH')) exit;

class AFP_Card_Renderer {

    /**
     * Renderiza la tarjeta completa (Header + Body + Dropzone si es container).
     */
    public static function render($field, $index) {
        $type = isset($field['type']) ? $field['type'] : 'text';
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        
        $is_container = in_array($type, ['section', 'repeater']);
        
        // Clases para colores de borde
        $card_class = 'afp-card';
        if ($type === 'section') $card_class .= ' afp-section-card';
        if ($type === 'repeater') $card_class .= ' afp-repeater-start-card'; 

        ?>
        <div class="<?php echo $card_class; ?>" data-type="<?php echo esc_attr($type); ?>" id="card_<?php echo esc_attr($index); ?>">
            
            <div class="afp-card-header">
                <span class="afp-handle dashicons dashicons-move"></span>
                
                <input type="hidden" class="afp-js-val" data-key="type" value="<?php echo esc_attr($type); ?>">
                
                <strong style="font-size:12px; text-transform:uppercase; margin-right:10px;"><?php echo esc_html($type); ?></strong>
                
                <input type="text" class="afp-input-label-preview afp-js-val" data-key="label" value="<?php echo esc_attr($label); ?>" placeholder="Etiqueta">
                
                <div class="afp-actions">
                    <button type="button" class="afp-toggle-body dashicons dashicons-arrow-down-alt2"></button>
                    <button type="button" class="afp-remove-row dashicons dashicons-trash"></button>
                </div>
            </div>

            <div class="afp-card-body">
                <div class="afp-form-row">
                    <label>Slug: <input type="text" class="widefat afp-js-val" data-key="name" value="<?php echo esc_attr($name); ?>"></label>
                </div>
                
                <?php if (!$is_container): ?>
                    <?php self::render_settings($field, $type); ?>
                <?php endif; ?>
            </div>

            <?php if ($is_container): ?>
                <div class="afp-dropzone-wrapper">
                    <div class="afp-nested-sortable">
                        <?php 
                        if (!empty($field['sub_fields'])) {
                            // Llamamos al Core para renderizar los hijos recursivamente
                            AFP_Builder_Core::render_children($field['sub_fields']);
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderiza los inputs de configuración específicos (Copiado del original).
     */
    private static function render_settings($field, $type) {
        // Extraer valores
        $req = isset($field['required']) ? $field['required'] : 0;
        $width = isset($field['width']) ? $field['width'] : '100';
        $options = isset($field['options']) ? $field['options'] : '';
        $min_val = isset($field['min_value']) ? $field['min_value'] : '';
        $max_val = isset($field['max_value']) ? $field['max_value'] : '';
        $allowed_ext = isset($field['allowed_ext']) ? $field['allowed_ext'] : 'pdf, jpg, png';
        $max_size    = isset($field['max_size']) ? $field['max_size'] : '5';

        // Lógica de visibilidad (basada en el tipo)
        $show_options   = in_array($type, ['select', 'radio', 'checkbox', 'chips']);
        $show_number    = ($type === 'number');
        $show_file_opts = ($type === 'file');

        ?>
        
        <div class="afp-form-row afp-options-wrapper" style="display:<?php echo $show_options ? 'block' : 'none'; ?>">
            <label>Opciones:<textarea class="widefat afp-js-val" data-key="options" rows="3"><?php echo esc_textarea($options); ?></textarea></label>
        </div>

        <div class="afp-form-row afp-number-wrapper afp-flex" style="display:<?php echo $show_number ? 'flex' : 'none'; ?>; gap: 15px;">
            <label>Min:<input type="number" class="widefat afp-js-val" data-key="min_value" value="<?php echo esc_attr($min_val); ?>" style="width:80px;"></label>
            <label>Max:<input type="number" class="widefat afp-js-val" data-key="max_value" value="<?php echo esc_attr($max_val); ?>" style="width:80px;"></label>
        </div>

        <div class="afp-form-row afp-flex afp-settings-row">
            <label>
                <input type="checkbox" class="afp-js-val" data-key="required" value="1" <?php checked($req, 1); ?>> 
                Obligatorio
            </label>

            <div class="afp-width-control" style="margin-left: 20px;">
                <span class="afp-control-label">Ancho:</span>
                <select class="afp-js-val" data-key="width" style="margin-left:5px;">
                    <option value="100" <?php selected($width, '100'); ?>>100%</option>
                    <option value="50" <?php selected($width, '50'); ?>>50%</option>
                    <option value="33" <?php selected($width, '33'); ?>>33%</option>
                </select>
            </div>
        </div>

        <div class="afp-form-row afp-file-settings" style="display:<?php echo $show_file_opts ? 'flex' : 'none'; ?>; gap: 15px; background: #fff8e5; padding: 10px; border: 1px dashed #e5cca8;">
            <label style="flex:1;">Extensiones:
                <input type="text" class="widefat afp-js-val" data-key="allowed_ext" value="<?php echo esc_attr($allowed_ext); ?>" placeholder="pdf, jpg">
            </label>
            <label style="width: 120px;">Max MB:
                <input type="number" class="widefat afp-js-val" data-key="max_size" value="<?php echo esc_attr($max_size); ?>">
            </label>
        </div>
        <?php
    }
}
?>