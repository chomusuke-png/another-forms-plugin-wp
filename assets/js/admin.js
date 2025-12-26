jQuery(document).ready(function($) {
    
    // Función para inicializar Sortable y sus restricciones
    function initSortable() {
        $('.afp-nested-sortable').sortable({
            handle: '.afp-handle',
            connectWith: '.afp-nested-sortable', // Permite arrastrar entre contenedores
            placeholder: 'afp-sortable-placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            cursor: 'grabbing',
            
            // EVENTO CLAVE: Se dispara cuando un elemento se suelta en una lista conectada
            receive: function(event, ui) {
                var $item = ui.item;            // El elemento que se está moviendo
                var $targetContainer = $(this); // El contenedor donde cayó
                
                var itemType = $item.data('type');

                // --- RESTRICCIÓN DE ANIDACIÓN ---
                // Si el elemento es un REPEATER, verificamos dónde ha caído.
                if (itemType === 'repeater') {
                    
                    // Buscamos si el contenedor destino está dentro de (es hijo de) una tarjeta tipo 'repeater'.
                    // Usamos .closest() para buscar hacia arriba en el DOM.
                    var isInsideRepeater = $targetContainer.closest('.afp-card[data-type="repeater"]').length > 0;

                    if (isInsideRepeater) {
                        // BLOQUEO: Detectamos intento de anidación profunda de arrays
                        alert('Restricción: No se permite colocar un Repeater dentro de otro Repeater.');
                        
                        // Cancelamos el movimiento visualmente (el item vuelve a su origen)
                        $(ui.sender).sortable('cancel');
                    }
                }
            },
            
            stop: function(e, ui) {
                // Lógica al detener el arrastre (si fuera necesaria)
            }
        });
    }

    // 1. Inicializar al cargar la página
    initSortable();

    // 2. Añadir Campo (Siempre al contenedor raíz primero)
    $('.afp-add-field').on('click', function() {
        var type = $(this).data('type');
        
        // Clonar del template oculto
        var $tpl = $('#afp-templates .afp-template-item[data-type="'+type+'"]').children().first().clone();
        
        // Generar ID único visual
        var newId = 'card_' + new Date().getTime();
        $tpl.attr('id', newId);

        // Insertar siempre en el raíz. 
        // Nota: Esto es seguro porque el raíz nunca está "dentro" de un repeater.
        // El usuario tendrá que arrastrarlo manualmente adentro si quiere anidarlo,
        // y ahí se activará nuestra validación 'receive'.
        $('#afp-root-container').append($tpl);
        
        // Reinicializar sortable para que el nuevo elemento sea interactivo
        initSortable();

        // Scroll suave al nuevo elemento
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

    // 4. Toggle Body (Acordeón)
    $(document).on('click', '.afp-toggle-body', function() {
        $(this).closest('.afp-card').find('.afp-card-body').slideToggle();
        $(this).toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    // 5. SERIALIZADOR JSON (Interceptar envío del form para guardar estructura)
    $('form#post').on('submit', function(e) {
        var structure = getStructureRecursive($('#afp-root-container'));
        $('#afp_form_structure_json').val(JSON.stringify(structure));
        return true;
    });

    /**
     * Recorre recursivamente los elementos .afp-card para generar el JSON.
     */
    function getStructureRecursive($container) {
        var items = [];

        $container.children('.afp-card').each(function() {
            var $card = $(this);
            var item = {};

            item.type = $card.data('type');

            // Leer inputs propios de la tarjeta (ignorando hijos anidados)
            $card.find('.afp-js-val').each(function() {
                if ($(this).closest('.afp-card')[0] === $card[0]) {
                    var key = $(this).data('key');
                    var val = $(this).is(':checkbox') ? ($(this).is(':checked') ? 1 : 0) : $(this).val();
                    item[key] = val;
                }
            });

            // Recursión para contenedores (Secciones o Repeaters)
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