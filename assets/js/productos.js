$(document).ready(function() {
    // Validación del formulario de productos
    $("form").submit(function(e) {
        var nombre = $("#nombre_producto").val();
        var precio = $("#precio").val();
        if (!nombre || !precio) {
            alert("Por favor, complete todos los campos obligatorios.");
            e.preventDefault();
        }
    });
});
