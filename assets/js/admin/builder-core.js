jQuery(document).ready(function($) {
    
    // Inicializar DnD
    if (typeof AFP_DnD !== 'undefined') AFP_DnD.init();

    // Añadir Campo
    $('.afp-add-field').on('click', function() {
        var type = $(this).data('type');
        var $tpl = $('#afp-templates .afp-template-item[data-type="'+type+'"]').children().first().clone();
        $tpl.attr('id', 'card_' + new Date().getTime());
        
        $('#afp-root-container').append($tpl);
        AFP_DnD.init(); // Reiniciar sortable para el nuevo item
        
        $('html, body').animate({ scrollTop: $tpl.offset().top - 100 }, 500);
    });

    // Eliminar
    $(document).on('click', '.afp-remove-row', function() {
        if(confirm('¿Eliminar campo?')) {
            $(this).closest('.afp-card').fadeOut(200, function(){ $(this).remove(); });
        }
    });

    // Toggle
    $(document).on('click', '.afp-toggle-body', function() {
        $(this).closest('.afp-card').find('.afp-card-body').slideToggle();
        $(this).toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    // Guardar (Usando Serializer)
    $('form#post').on('submit', function(e) {
        if (typeof AFP_Serializer !== 'undefined') {
            var structure = AFP_Serializer.getStructure($('#afp-root-container'));
            $('#afp_form_structure_json').val(JSON.stringify(structure));
        }
        return true;
    });
});