jQuery(document).ready(function($) {

    // 1. Click en "+ Añadir"
    $(document).on('click', '.afp-add-chip-trigger', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $container = $btn.closest('.afp-chips-container');
        var $input = $container.find('.afp-new-chip-input');

        // Ocultamos botón, mostramos input y ponemos foco
        $btn.hide();
        $input.show().focus();

        // Si es un select, a veces es necesario simular click (depende del navegador)
        // pero con focus suele bastar para interacción teclado/mouse.
    });

    // 2. Manejo de "Blur" (Perder foco) o "Enter" para TEXTO
    $(document).on('blur keypress', 'input.afp-new-chip-input', function(e) {
        // Si es tecla y no es Enter, no hacemos nada
        if (e.type === 'keypress' && e.which !== 13) return;
        
        // Prevenir submit del form si es Enter
        if (e.type === 'keypress') e.preventDefault();

        // Retrasamos un poco el blur para permitir clicks en opciones si hubiera
        var $input = $(this);
        setTimeout(function() {
            addChipFromInput($input);
        }, 100);
    });

    // 3. Manejo de "Change" o "Blur" para SELECT (Opciones predefinidas)
    $(document).on('change blur', 'select.afp-new-chip-input', function(e) {
        // Solo procesar en blur si no se seleccionó nada, para cerrar
        // Pero en change procesamos la adición.
        if (e.type === 'change') {
            addChipFromInput($(this));
        } else if (e.type === 'blur') {
             var $input = $(this);
             setTimeout(function() {
                // Restaurar botón si no hay valor seleccionado
                if ($input.val() === '') {
                    $input.hide();
                    $input.siblings('.afp-add-chip-trigger').show();
                }
             }, 100);
        }
    });

    // FUNCIÓN PRINCIPAL: Añadir Chip
    function addChipFromInput($input) {
        var value = $input.val().trim();
        var $container = $input.closest('.afp-chips-container');
        var $wrapper = $input.closest('.afp-chips-wrapper');
        var fieldName = $wrapper.data('name'); // ej: afp_data[tags]
        var labelText = value;

        // Si es select, obtenemos el texto de la opción, no el value (si fueran distintos)
        if ($input.is('select') && value !== '') {
            labelText = $input.find('option:selected').text();
        }

        if (value !== '') {
            // Crear Chip HTML
            var chipHtml = `
                <div class="afp-chip-item">
                    <span>` + escapeHtml(labelText) + `</span>
                    <span class="afp-chip-remove">&times;</span>
                    <input type="hidden" name="` + fieldName + `[]" value="` + escapeHtml(value) + `">
                </div>
            `;
            
            // Insertar ANTES del input (para mantener el orden visual: chips -> input -> botón)
            $input.before(chipHtml);
        }

        // Resetear estado
        $input.val(''); // Limpiar valor
        $input.hide();  // Ocultar input
        $container.find('.afp-add-chip-trigger').show(); // Volver a mostrar botón +
    }

    // 4. Borrar Chip
    $(document).on('click', '.afp-chip-remove', function() {
        $(this).closest('.afp-chip-item').fadeOut(200, function() {
            $(this).remove();
        });
    });

    // === LÓGICA REPEATER ===
    
    // Añadir Fila
    $(document).on('click', '.afp-add-repeater-row', function(e) {
        e.preventDefault();
        var $group = $(this).closest('.afp-repeater-group');
        var $container = $group.find('.afp-repeater-rows-container');
        var template = $group.find('.afp-repeater-template').html();
        
        // Generar índice único (timestamp)
        var newIdx = new Date().getTime();
        
        // Reemplazar placeholder {{idx}}
        var rowHtml = template.replace(/{{idx}}/g, newIdx);
        
        $container.append(rowHtml);
    });

    // Eliminar Fila
    $(document).on('click', '.afp-remove-repeater-row', function(e) {
        e.preventDefault();
        if (confirm('¿Borrar esta entrada?')) {
            $(this).closest('.afp-repeater-row').slideUp(200, function() {
                $(this).remove();
            });
        }
    });

    function escapeHtml(text) {
        if (!text) return text;
        return text.replace(/[&<>"']/g, function(m) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
        });
    }
});