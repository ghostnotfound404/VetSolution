$(document).ready(function() {
    // Validación del formulario de servicios
    $("form").submit(function(e) {
        var nombre = $("#nombre_servicio").val();
        var precio = $("#precio_servicio").val();
        if (!nombre || !precio) {
            alert("Por favor, complete todos los campos obligatorios.");
            e.preventDefault();
        }
    });
});
