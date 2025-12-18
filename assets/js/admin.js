jQuery(document).ready(function($) {
    
    var container = $('#afp-fields-container');
    
    // 1. Inicializar Sortable (Drag & Drop)
    container.sortable({
        handle: '.afp-handle',
        placeholder: 'afp-sortable-placeholder',
        forcePlaceholderSize: true
    });

    // 2. Añadir Campo
    $('.afp-add-field').on('click', function() {
        var type = $(this).data('type');
        var index = new Date().getTime(); // ID único basado en timestamp
        
        // Obtenemos el template HTML
        var template = $('#afp-field-template').html();
        
        // Reemplazamos los placeholders
        var html = template.replace(/__INDEX__/g, index).replace(/__TYPE__/g, type);
        
        // Convertimos a objeto jQuery para modificarlo antes de insertar
        var $el = $(html);
        
        $el.find('.afp-type-badge').text(type.charAt(0).toUpperCase() + type.slice(1));
        $el.find('input[name*="[type]"]').val(type);

        // Si es sección, añadimos clase especial
        if (type === 'section') {
            $el.addClass('afp-section-card');
            $el.find('.afp-form-row').not(':first').remove(); // Quitar opciones innecesarias
        }

        // Mostrar opciones de dropdown/checkbox si corresponde
        if (['select', 'radio', 'checkbox'].includes(type)) {
            $el.find('.afp-options-wrapper').show();
        }

        container.append($el);
    });

    // 3. Eliminar Campo
    $(document).on('click', '.afp-remove-row', function() {
        if(confirm('¿Eliminar este campo?')) {
            $(this).closest('.afp-card').remove();
        }
    });

    // 4. Toggle Body (Acordeón)
    $(document).on('click', '.afp-toggle-body', function() {
        $(this).closest('.afp-card').find('.afp-card-body').slideToggle();
        $(this).toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });
});