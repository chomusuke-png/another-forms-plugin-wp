<?php
if (!defined('ABSPATH')) exit;

/**
 * Class AFP_Field_Renderer
 * Se encarga de generar el HTML de los inputs en el frontend.
 */
class AFP_Field_Renderer {

    public static function render_input($field, $form_id, $name_override = null) {
        $type  = $field['type'];
        $label = $field['label'];
        $req   = !empty($field['required']) ? 'required' : '';
        $width = isset($field['width']) ? $field['width'] : '100';
        
        $base_name = isset($field['name']) ? $field['name'] : '';
        $input_name = $name_override ? $name_override : "afp_data[$base_name]";
        
        // ID único
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
                $chip_name = $input_name; 
                echo '<div class="afp-chips-wrapper" data-name="'.esc_attr($chip_name).'">';
                echo '<div class="afp-chips-container">';
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
                
            default: // Inputs básicos
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
}