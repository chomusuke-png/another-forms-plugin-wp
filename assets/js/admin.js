jQuery(document).ready(function($) {
    
    var container = $('#afp-fields-container');
    
    // 1. Inicializar Sortable
    container.sortable({
        handle: '.afp-handle',
        placeholder: 'afp-sortable-placeholder',
        forcePlaceholderSize: true
    });

    // 2. Añadir Campo
    $('.afp-add-field').on('click', function() {
        var type = $(this).data('type');
        var index = new Date().getTime(); 
        
        var template = $('#afp-field-template').html();
        var html = template.replace(/__INDEX__/g, index);
        var $el = $(html);
        
        $el.find('.afp-type-selector').val(type);
        $el.find('.afp-type-selector').trigger('change');

        container.append($el);
    });

    // 3. Eliminar Campo
    $(document).on('click', '.afp-remove-row', function() {
        if(confirm('¿Eliminar este campo?')) {
            $(this).closest('.afp-card').remove();
        }
    });

    // 4. Toggle Body
    $(document).on('click', '.afp-toggle-body', function() {
        $(this).closest('.afp-card').find('.afp-card-body').slideToggle();
        $(this).toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    // 5. EVENTO DE CAMBIO DE TIPO
    $(document).on('change', '.afp-type-selector', function() {
        var type = $(this).val();
        var $card = $(this).closest('.afp-card');
        
        // Secciones
        if (type === 'section') {
            $card.addClass('afp-section-card');
            $card.find('.afp-slug-row, .afp-settings-row').hide();
        } else {
            $card.removeClass('afp-section-card');
            $card.find('.afp-slug-row, .afp-settings-row').show();
        }

        // Opciones (Dropdown/Radio/Checkbox)
        if (['select', 'radio', 'checkbox'].includes(type)) {
            $card.find('.afp-options-wrapper').slideDown();
        } else {
            $card.find('.afp-options-wrapper').slideUp();
        }

        // Configuración Numérica (Min/Max) - NUEVO
        if (type === 'number') {
            $card.find('.afp-number-wrapper').css('display', 'flex'); // Usamos flex para que estén juntos
        } else {
            $card.find('.afp-number-wrapper').hide();
        }
    });
});