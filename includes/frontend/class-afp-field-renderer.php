<?php
if (!defined('ABSPATH')) exit;

/**
 * Class AFP_Field_Renderer
 * Genera el HTML de los inputs en el frontend utilizando clases CSS limpias.
 */
class AFP_Field_Renderer {

    /**
     * Renderiza el campo.
     * * @param array  $field Configuración del campo.
     * @param int    $form_id ID del post formulario.
     * @param string $name_override (Opcional) Nombre para inputs en repeaters.
     */
    public static function render_input($field, $form_id, $name_override = null) {
        $type  = isset($field['type']) ? $field['type'] : 'text';
        $label = isset($field['label']) ? $field['label'] : '';
        $req   = !empty($field['required']);
        $width = isset($field['width']) ? $field['width'] : '100';
        
        $base_name  = isset($field['name']) ? $field['name'] : '';
        $input_name = $name_override ? $name_override : "afp_data[$base_name]";

        // Generación de ID Determinista (Accesibilidad + Caché compatible)
        // Usamos un hash corto del nombre + form_id para que sea único pero constante.
        $id_suffix = substr(md5($input_name . $form_id), 0, 6);
        $field_id  = "afp_{$base_name}_{$id_suffix}";

        // Si es plantilla JS (repeater), usamos un ID genérico
        if (strpos($input_name, '{{idx}}') !== false) {
             $field_id = "afp_{$base_name}_tpl"; 
        }

        // Mapeo de Ancho a Clases CSS
        $width_class = 'afp-col-100';
        if ($width === '50') $width_class = 'afp-col-50';
        if ($width === '33') $width_class = 'afp-col-33';

        // Renderizado de Sección (Header)
        if ($type === 'section') {
            echo '<div class="afp-section-break"><h4>' . esc_html($label) . '</h4></div>';
            return;
        }

        ?>
        <div class="afp-row <?php echo esc_attr($width_class); ?>">
            <label for="<?php echo esc_attr($field_id); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($req): ?> <span class="req">*</span> <?php endif; ?>
            </label>

            <?php self::render_control($type, $input_name, $field_id, $field, $req); ?>
        </div>
        <?php
    }

    /**
     * Renderiza el control específico (input, select, etc) separado de la estructura.
     */
    private static function render_control($type, $name, $id, $field, $is_req) {
        $req_attr = $is_req ? 'required' : '';
        $options  = isset($field['options']) ? $field['options'] : '';

        switch ($type) {
            case 'textarea':
                echo '<textarea id="'.esc_attr($id).'" name="'.esc_attr($name).'" rows="4" '.$req_attr.'></textarea>';
                break;
                
            case 'select':
                $opts = explode("\n", $options);
                echo '<select id="'.esc_attr($id).'" name="'.esc_attr($name).'" '.$req_attr.'>';
                echo '<option value="">-- Seleccionar --</option>';
                foreach ($opts as $opt) { 
                    $opt = trim($opt); 
                    if($opt) echo '<option value="'.esc_attr($opt).'">'.esc_html($opt).'</option>'; 
                }
                echo '</select>';
                break;
                
            case 'radio':
                $opts = explode("\n", $options);
                echo '<div class="afp-radio-group">';
                foreach ($opts as $idx => $opt) {
                    $opt = trim($opt);
                    // ID único para cada opción del radio para accesibilidad
                    $opt_id = $id . '_r_' . $idx; 
                    if ($opt) {
                        echo '<label class="afp-inline-label" for="'.esc_attr($opt_id).'">';
                        echo '<input type="radio" id="'.esc_attr($opt_id).'" name="'.esc_attr($name).'" value="'.esc_attr($opt).'" '.$req_attr.'> '.esc_html($opt);
                        echo '</label>';
                    }
                }
                echo '</div>';
                break;
                
            case 'checkbox':
                if (!empty($options)) {
                    $opts = explode("\n", $options);
                    echo '<div class="afp-check-group">';
                    foreach ($opts as $idx => $opt) {
                        $opt = trim($opt);
                        $opt_id = $id . '_c_' . $idx;
                        if ($opt) {
                            echo '<label class="afp-inline-label" for="'.esc_attr($opt_id).'">';
                            echo '<input type="checkbox" id="'.esc_attr($opt_id).'" name="'.esc_attr($name).'[]" value="'.esc_attr($opt).'"> '.esc_html($opt);
                            echo '</label>';
                        }
                    }
                    echo '</div>';
                } else {
                    echo '<label class="afp-inline-label" for="'.esc_attr($id).'">';
                    echo '<input type="checkbox" id="'.esc_attr($id).'" name="'.esc_attr($name).'" value="Sí" '.$req_attr.'> Confirmar';
                    echo '</label>';
                }
                break;
                
            case 'chips':
                // La lógica de chips es compleja visualmente, mantenemos estructura pero usamos ID
                echo '<div class="afp-chips-wrapper" data-name="'.esc_attr($name).'">';
                echo '<div class="afp-chips-container" id="'.esc_attr($id).'_container">';
                if (!empty($options)) {
                    $opts = explode("\n", $options);
                    echo '<select class="afp-new-chip-input" id="'.esc_attr($id).'" style="display:none;">';
                    echo '<option value="">Seleccionar...</option>';
                    foreach ($opts as $opt) {
                        $opt = trim($opt);
                        if ($opt) echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                } else {
                    echo '<input type="text" class="afp-new-chip-input" id="'.esc_attr($id).'" style="display:none;" placeholder="Escribe...">';
                }
                echo '<button type="button" class="afp-add-chip-trigger">+ Añadir</button>';
                echo '</div></div>';
                break;
            
            case 'file':
                $ext_msg = isset($field['allowed_ext']) ? $field['allowed_ext'] : 'pdf, jpg';
                $max_mb  = isset($field['max_size']) ? $field['max_size'] : 5;
                
                echo '<input type="file" id="'.esc_attr($id).'" name="'.esc_attr($name).'" '.$req_attr.' accept=".'.str_replace(',', ',.', $ext_msg).'">';
                echo '<small style="display:block; color:#777; margin-top:4px; font-size:0.85em;">';
                echo 'Formatos: ' . esc_html($ext_msg) . ' (Max: ' . esc_html($max_mb) . 'MB)';
                echo '</small>';
                break;
                
            default: // Inputs básicos (text, email, number, date, etc)
                $extra = '';
                if ($type === 'number') {
                    if (isset($field['min_value']) && $field['min_value'] !== '') $extra .= ' min="'.esc_attr($field['min_value']).'"';
                    if (isset($field['max_value']) && $field['max_value'] !== '') $extra .= ' max="'.esc_attr($field['max_value']).'"';
                }
                echo '<input type="'.esc_attr($type).'" id="'.esc_attr($id).'" name="'.esc_attr($name).'" '.$req_attr.' '.$extra.'>';
                break;
        }
    }
}