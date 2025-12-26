<?php
if (!defined('ABSPATH')) exit;

class AFP_Builder_UI {

    /**
     * Renderiza el contenedor principal.
     */
    public static function render_app($fields) {
        ?>
        <div id="afp-builder-app">
            <?php self::render_toolbar(); ?>

            <div id="afp-root-container" class="afp-nested-sortable">
                <?php self::render_fields_recursive($fields); ?>
            </div>

            <input type="hidden" name="afp_form_structure_json" id="afp_form_structure_json" value="">

            <div id="afp-templates" style="display:none;">
                <?php 
                // Renderizamos un template vacío por cada tipo para clonar en JS
                $types = ['text', 'email', 'textarea', 'number', 'select', 'checkbox', 'radio', 'date', 'chips', 'file', 'section', 'repeater'];
                foreach($types as $type) {
                    echo '<div class="afp-template-item" data-type="'.$type.'">';
                    self::render_card(array('type' => $type, 'label' => '', 'width' => '100'), 'TPL_IDX');
                    echo '</div>';
                }
                ?>
            </div>
            
            <p class="description" style="margin-top:15px;">Arrastra los campos. Puedes meter campos dentro de Secciones y Repeaters.</p>
        </div>
        <?php
    }

    private static function render_toolbar() {
        ?>
        <div class="afp-toolbar top">
            <button type="button" class="button afp-add-field" data-type="text">Texto</button>
            <button type="button" class="button afp-add-field" data-type="email">Email</button>
            <button type="button" class="button afp-add-field" data-type="textarea">Área</button>
            <button type="button" class="button afp-add-field" data-type="number">Número</button>
            <button type="button" class="button afp-add-field" data-type="select">Select</button>
            <button type="button" class="button afp-add-field" data-type="checkbox">Check</button>
            <button type="button" class="button afp-add-field" data-type="radio">Radio</button>
            <button type="button" class="button afp-add-field" data-type="date">Fecha</button>
            <button type="button" class="button afp-add-field" data-type="chips">Chips</button>
            <button type="button" class="button afp-add-field" data-type="file">Archivo</button>
            <span class="afp-separator">|</span>
            <button type="button" class="button afp-add-field button-primary" data-type="section"><strong>[ ] Sección</strong></button>
            <button type="button" class="button afp-add-field button-primary" data-type="repeater"><strong>🔁 Repeater</strong></button>
        </div>
        <?php
    }

    /**
     * Función recursiva para iterar campos y sus hijos.
     */
    private static function render_fields_recursive($fields) {
        if (empty($fields)) return;
        foreach ($fields as $index => $field) {
            // Usamos un ID único temporal (timestamp + random) para el DOM si no hay índice
            $unique_idx = uniqid(); 
            self::render_card($field, $unique_idx);
        }
    }

    /**
     * Renderiza una tarjeta. Si es contenedor, pinta su área interna.
     */
    public static function render_card($field, $index) {
        $type = isset($field['type']) ? $field['type'] : 'text';
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $req = isset($field['required']) ? $field['required'] : 0;
        $width = isset($field['width']) ? $field['width'] : '100';
        $options = isset($field['options']) ? $field['options'] : '';
        
        $is_container = in_array($type, ['section', 'repeater']);
        
        // Clases y Estilos
        $card_class = 'afp-card';
        
        // Data attributes para que JS pueda leer los valores al serializar
        ?>
        <div class="<?php echo $card_class; ?>" data-type="<?php echo esc_attr($type); ?>" id="card_<?php echo esc_attr($index); ?>">
            
            <div class="afp-card-header">
                <span class="afp-handle dashicons dashicons-move"></span>
                <strong style="font-size:12px; text-transform:uppercase; margin-right:10px;"><?php echo esc_html($type); ?></strong>
                
                <input type="text" class="afp-input-label-preview afp-js-val" data-key="label" value="<?php echo esc_attr($label); ?>" placeholder="Etiqueta del campo">
                
                <div class="afp-actions">
                    <button type="button" class="afp-toggle-body dashicons dashicons-arrow-down-alt2"></button>
                    <button type="button" class="afp-remove-row dashicons dashicons-trash"></button>
                </div>
            </div>

            <div class="afp-card-body">
                <div class="afp-form-row">
                    <label>Slug/ID: <input type="text" class="widefat afp-js-val" data-key="name" value="<?php echo esc_attr($name); ?>"></label>
                </div>
                
                <?php if ($is_container): ?>
                    <p style="font-size:12px; color:#666; margin:0;">Arrastra campos dentro del recuadro punteado:</p>
                <?php else: ?>
                    <?php if (in_array($type, ['select', 'radio', 'checkbox', 'chips'])): ?>
                    <div class="afp-form-row">
                        <label>Opciones:<textarea class="widefat afp-js-val" data-key="options" rows="3"><?php echo esc_textarea($options); ?></textarea></label>
                    </div>
                    <?php endif; ?>

                    <div class="afp-form-row afp-flex" style="gap:15px;">
                        <label><input type="checkbox" class="afp-js-val" data-key="required" value="1" <?php checked($req, 1); ?>> Obligatorio</label>
                        
                        <?php if ($type === 'file'): 
                            $ext = isset($field['allowed_ext']) ? $field['allowed_ext'] : '';
                            $size = isset($field['max_size']) ? $field['max_size'] : 5;
                        ?>
                            <label>Ext: <input type="text" class="afp-js-val" data-key="allowed_ext" value="<?php echo esc_attr($ext); ?>" style="width:80px;"></label>
                            <label>MB: <input type="number" class="afp-js-val" data-key="max_size" value="<?php echo esc_attr($size); ?>" style="width:50px;"></label>
                        <?php endif; ?>
                        
                        <?php if ($type === 'number'): 
                             $min = isset($field['min_value']) ? $field['min_value'] : '';
                             $max = isset($field['max_value']) ? $field['max_value'] : '';
                        ?>
                            <label>Min: <input type="number" class="afp-js-val" data-key="min_value" value="<?php echo esc_attr($min); ?>" style="width:60px;"></label>
                            <label>Max: <input type="number" class="afp-js-val" data-key="max_value" value="<?php echo esc_attr($max); ?>" style="width:60px;"></label>
                        <?php endif; ?>
                    </div>
                    
                    <div class="afp-form-row">
                        <label>Ancho: 
                            <select class="afp-js-val" data-key="width">
                                <option value="100" <?php selected($width, '100'); ?>>100%</option>
                                <option value="50" <?php selected($width, '50'); ?>>50%</option>
                                <option value="33" <?php selected($width, '33'); ?>>33%</option>
                            </select>
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($is_container): ?>
                <div class="afp-dropzone-wrapper">
                    <div class="afp-nested-sortable">
                        <?php 
                        // Renderizar hijos si existen
                        if (!empty($field['sub_fields'])) {
                            self::render_fields_recursive($field['sub_fields']);
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }
}