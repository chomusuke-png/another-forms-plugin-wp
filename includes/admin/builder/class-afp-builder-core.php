<?php
if (!defined('ABSPATH')) exit;

class AFP_Builder_Core {

    public static function render($fields) {
        ?>
        <div id="afp-builder-app">
            <?php self::render_toolbar(); ?>

            <div id="afp-root-container" class="afp-nested-sortable">
                <?php self::render_children($fields); ?>
            </div>

            <input type="hidden" name="afp_form_structure_json" id="afp_form_structure_json" value="">

            <div id="afp-templates" style="display:none;">
                <?php 
                $types = ['text', 'email', 'textarea', 'number', 'select', 'checkbox', 'radio', 'date', 'chips', 'file', 'section', 'repeater'];
                foreach($types as $type) {
                    echo '<div class="afp-template-item" data-type="'.$type.'">';
                    AFP_Card_Renderer::render(array('type' => $type, 'label' => '', 'width' => '100'), 'TPL_IDX');
                    echo '</div>';
                }
                ?>
            </div>
            <p class="description">Arrastra los campos. Puedes anidar en Secciones y Repeaters.</p>
        </div>
        <?php
    }

    public static function render_children($fields) {
        if (empty($fields)) return;
        foreach ($fields as $field) {
            AFP_Card_Renderer::render($field, uniqid());
        }
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
            <button type="button" class="button afp-add-field button-primary" data-type="section"><strong>Sección</strong></button>
            <button type="button" class="button afp-add-field button-primary" data-type="repeater"><strong>Repetidor</strong></button>
        </div>
        <?php
    }
}