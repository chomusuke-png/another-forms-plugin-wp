var AFP_DnD = (function($) {

    function initSortable() {
        $('.afp-nested-sortable').sortable({
            handle: '.afp-handle',
            connectWith: '.afp-nested-sortable',
            placeholder: 'afp-sortable-placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            cursor: 'grabbing',
            
            receive: function(event, ui) {
                var itemType = ui.item.data('type');
                // Restricción: No repeater dentro de repeater
                if (itemType === 'repeater') {
                    var isInsideRepeater = $(this).closest('.afp-card[data-type="repeater"]').length > 0;
                    if (isInsideRepeater) {
                        alert('No puedes anidar Repeaters dentro de Repeaters.');
                        $(ui.sender).sortable('cancel');
                    }
                }
            }
        });
    }

    return {
        init: initSortable
    };
})(jQuery);