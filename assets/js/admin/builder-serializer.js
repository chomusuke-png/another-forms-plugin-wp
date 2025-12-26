// Variable global o namespace para funciones exportadas
var AFP_Serializer = (function($) {
    
    function getStructureRecursive($container) {
        var items = [];
        $container.children('.afp-card').each(function() {
            var $card = $(this);
            var item = {};
            item.type = $card.data('type');

            $card.find('.afp-js-val').each(function() {
                if ($(this).closest('.afp-card')[0] === $card[0]) {
                    var key = $(this).data('key');
                    var val = $(this).is(':checkbox') ? ($(this).is(':checked') ? 1 : 0) : $(this).val();
                    item[key] = val;
                }
            });

            var $subContainer = $card.find('.afp-nested-sortable').first();
            if ($subContainer.length > 0) {
                var children = getStructureRecursive($subContainer);
                if (children.length > 0) item.sub_fields = children;
            }
            items.push(item);
        });
        return items;
    }

    return {
        getStructure: getStructureRecursive
    };
})(jQuery);