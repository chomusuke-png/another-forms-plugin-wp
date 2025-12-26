jQuery(document).ready(function($) {
    
    // Función para inicializar Sortable (llamada al inicio y al añadir nuevos elementos)
    function initSortable() {
        $('.afp-nested-sortable').sortable({
            handle: '.afp-handle',
            connectWith: '.afp-nested-sortable', // Permite arrastrar entre contenedores
            placeholder: 'afp-sortable-placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            cursor: 'grabbing',
            stop: function(e, ui) {
                // Opcional: acciones al soltar
            }
        });
    }

    // 1. Inicializar al cargar
    initSortable();

    // 2. Añadir Campo
    $('.afp-add-field').on('click', function() {
        var type = $(this).data('type');
        
        // Clonar del template oculto
        var $tpl = $('#afp-templates .afp-template-item[data-type="'+type+'"]').children().first().clone();
        
        // Generar ID único visual
        var newId = 'card_' + new Date().getTime();
        $tpl.attr('id', newId);

        // Insertar en el contenedor raíz (o podríamos mejorar para insertar en el activo)
        $('#afp-root-container').append($tpl);
        
        // Reinicializar para que el nuevo elemento sea ordenable y si es contenedor reciba items
        initSortable();

        // Scroll al nuevo elemento
        $('html, body').animate({
            scrollTop: $tpl.offset().top - 100
        }, 500);
    });

    // 3. Eliminar Campo
    $(document).on('click', '.afp-remove-row', function() {
        if(confirm('¿Eliminar este campo y su contenido?')) {
            $(this).closest('.afp-card').fadeOut(200, function(){ $(this).remove(); });
        }
    });

    // 4. Toggle Body
    $(document).on('click', '.afp-toggle-body', function() {
        $(this).closest('.afp-card').find('.afp-card-body').slideToggle();
        $(this).toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    // 5. SERIALIZADOR JSON (Interceptar envío del form)
    $('form#post').on('submit', function(e) {
        // Recorrer el DOM y construir el objeto
        var structure = getStructureRecursive($('#afp-root-container'));
        
        // Meter el JSON en el input hidden
        $('#afp_form_structure_json').val(JSON.stringify(structure));
        
        // Continuar con el envío normal de WP
        return true;
    });

    /**
     * Recorre recursivamente los elementos .afp-card y extrae sus datos
     */
    function getStructureRecursive($container) {
        var items = [];

        $container.children('.afp-card').each(function() {
            var $card = $(this);
            var item = {};

            // Leer tipo
            item.type = $card.data('type');

            // Leer todos los inputs con clase .afp-js-val dentro de ESTA tarjeta (no hijos profundos)
            // Usamos find() pero filtramos para no coger los de las tarjetas anidadas
            $card.find('.afp-js-val').each(function() {
                // Verificar que este input pertenece a esta tarjeta y no a una hija
                if ($(this).closest('.afp-card')[0] === $card[0]) {
                    var key = $(this).data('key');
                    var val;
                    if ($(this).is(':checkbox')) {
                        val = $(this).is(':checked') ? 1 : 0;
                    } else {
                        val = $(this).val();
                    }
                    item[key] = val;
                }
            });

            // Si es contenedor, recursividad
            var $subContainer = $card.find('.afp-nested-sortable').first();
            if ($subContainer.length > 0) {
                var children = getStructureRecursive($subContainer);
                if (children.length > 0) {
                    item.sub_fields = children;
                }
            }

            items.push(item);
        });

        return items;
    }

});