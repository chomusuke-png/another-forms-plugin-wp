<?php
if (!defined('ABSPATH')) exit;

/**
 * Class AFP_Builder_UI
 * Se encarga de renderizar el HTML del constructor en el WP-Admin.
 */
class AFP_Builder_UI {

    /**
     * Renderiza el contenedor principal del builder.
     */
    public static function render_app($fields) {
        ?>
        <div id="afp-builder-app">
            
            <?php self::render_toolbar('top'); ?>

            <div id="afp-fields-container" class="ui-sortable">
                <?php 
                if (!empty($fields)) {
                    foreach ($fields as $index => $field) {
                        self::render_card($index, $field);
                    }
                }
                ?>
            </div>

            <?php self::render_toolbar('bottom'); ?>

            <div id="afp-field-template" style="display:none;">
                <?php self::render_card('__INDEX__', array('type' => 'text', 'label' => '', 'name' => '', 'width' => '100')); ?>
            </div>
            
            <p class="description" style="margin-top:15px;">Arrastra los elementos para reordenarlos.</p>
        </div>
        <?php
    }

    /**
     * Renderiza la barra de botones.
     */
    private static function render_toolbar($position = 'top') {
        ?>
        <div class="afp-toolbar <?php echo esc_attr($position); ?>">
            <button type="button" class="button afp-add-field" data-type="text">Texto</button>
            <button type="button" class="button afp-add-field" data-type="email">Email</button>
            <button type="button" class="button afp-add-field" data-type="textarea">Área Texto</button>
            <button type="button" class="button afp-add-field" data-type="number">Número</button>
            <button type="button" class="button afp-add-field" data-type="select">Dropdown</button>
            <button type="button" class="button afp-add-field" data-type="checkbox">Checkbox</button>
            <button type="button" class="button afp-add-field" data-type="radio">Radio</button>
            <button type="button" class="button afp-add-field" data-type="date">Fecha</button>
            <button type="button" class="button afp-add-field" data-type="chips">Tags/Chips</button>
            <span class="afp-separator">|</span>
            <button type="button" class="button afp-add-field button-primary" data-type="section"><strong>+ Sección</strong></button>
            <button type="button" class="button afp-add-field" data-type="repeater_start" title="Inicio de grupo repetible">[ ...</button>
            <button type="button" class="button afp-add-field" data-type="repeater_end" title="Fin de grupo repetible">... ]</button>
        </div>
        <?php
    }

    /**
     * Renderiza una tarjeta individual de campo.
     */
    public static function render_card($index, $field) {
        $type = isset($field['type']) ? $field['type'] : 'text';
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $req = isset($field['required']) ? $field['required'] : 0;
        $width = isset($field['width']) ? $field['width'] : '100';
        $options = isset($field['options']) ? $field['options'] : '';
        $min_val = isset($field['min_value']) ? $field['min_value'] : '';
        $max_val = isset($field['max_value']) ? $field['max_value'] : '';
        
        $available_types = array(
            'text'           => 'Texto',
            'email'          => 'Email',
            'textarea'       => 'Área Texto',
            'number'         => 'Número',
            'select'         => 'Dropdown',
            'checkbox'       => 'Checkbox',
            'radio'          => 'Radio',
            'date'           => 'Fecha',
            'chips'          => 'Tags/Chips',
            'section'        => 'SECCIÓN (Título)',
            'repeater_start' => 'INICIO REPEATER',
            'repeater_end'   => 'FIN REPEATER'
        );

        $card_class = 'afp-card';
        if ($type === 'section') $card_class .= ' afp-section-card';
        if ($type === 'repeater_start') $card_class .= ' afp-repeater-start-card';
        if ($type === 'repeater_end') $card_class .= ' afp-repeater-end-card';

        $show_options = in_array($type, ['select', 'radio', 'checkbox', 'chips']);
        $show_number  = ($type === 'number');
        $hide_slug    = in_array($type, ['section', 'repeater_end']);
        $hide_req     = in_array($type, ['section', 'repeater_start', 'repeater_end']);
        $hide_width   = in_array($type, ['section', 'repeater_start', 'repeater_end']);

        ?>
        <div class="<?php echo $card_class; ?>" data-index="<?php echo $index; ?>">
            <div class="afp-card-header">
                <span class="afp-handle dashicons dashicons-move"></span>
                <select name="afp_fields[<?php echo $index; ?>][type]" class="afp-type-selector">
                    <?php foreach($available_types as $val => $txt): ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($type, $val); ?>><?php echo esc_html($txt); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" class="afp-input-label-preview" name="afp_fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="Etiqueta">
                <div class="afp-actions">
                    <button type="button" class="afp-toggle-body dashicons dashicons-arrow-down-alt2"></button>
                    <button type="button" class="afp-remove-row dashicons dashicons-trash"></button>
                </div>
            </div>
            <div class="afp-card-body">
                <div class="afp-form-row afp-slug-row" style="<?php echo $hide_slug ? 'display:none;' : ''; ?>">
                    <label>ID/Name (Slug): <input type="text" name="afp_fields[<?php echo $index; ?>][name]" value="<?php echo esc_attr($name); ?>" class="widefat"></label>
                    <?php if ($type === 'repeater_start'): ?>
                        <small style="color:#666;">Este ID agrupará los datos (ej: <code>plantas</code>)</small>
                    <?php endif; ?>
                </div>
                <div class="afp-form-row afp-options-wrapper" style="display:<?php echo $show_options ? 'block' : 'none'; ?>">
                    <label>Opciones:<textarea name="afp_fields[<?php echo $index; ?>][options]" rows="3" class="widefat"><?php echo esc_textarea($options); ?></textarea></label>
                </div>
                <div class="afp-form-row afp-number-wrapper afp-flex" style="display:<?php echo $show_number ? 'flex' : 'none'; ?>; gap: 15px;">
                    <label>Min:<input type="number" name="afp_fields[<?php echo $index; ?>][min_value]" value="<?php echo esc_attr($min_val); ?>" class="widefat" style="width:80px;"></label>
                    <label>Max:<input type="number" name="afp_fields[<?php echo $index; ?>][max_value]" value="<?php echo esc_attr($max_val); ?>" class="widefat" style="width:80px;"></label>
                </div>
                <div class="afp-form-row afp-flex afp-settings-row" style="<?php echo $hide_req ? 'display:none;' : ''; ?>">
                    <label>
                        <input type="checkbox" name="afp_fields[<?php echo $index; ?>][required]" value="1" <?php checked($req, 1); ?>> 
                        Obligatorio
                    </label>

                    <div class="afp-width-control" style="display:<?php echo $hide_width ? 'none' : 'flex'; ?>">
                        <span class="afp-control-label">Ancho:</span>
                        <div class="afp-width-options">
                            <?php 
                            // Definimos las opciones y sus clases visuales
                            $width_opts = [
                                '100' => ['label' => 'Completo', 'class' => 'afp-w-100'],
                                '50'  => ['label' => 'Mitad',    'class' => 'afp-w-50'],
                                '33'  => ['label' => 'Tercio',   'class' => 'afp-w-33'],
                            ];
                            
                            foreach ($width_opts as $val => $opt):
                                $is_checked = ($width == $val) ? 'checked' : '';
                            ?>
                                <label class="afp-width-btn" title="<?php echo esc_attr($opt['label']); ?>">
                                    <input type="radio" 
                                           name="afp_fields[<?php echo $index; ?>][width]" 
                                           value="<?php echo esc_attr($val); ?>" 
                                           <?php echo $is_checked; ?>>
                                    <span class="afp-width-icon <?php echo esc_attr($opt['class']); ?>"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}