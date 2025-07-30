$(document).ready(function() {
    // Validación del formulario de egresos
    $("form").submit(function(e) {
        var motivo = $("#motivo").val();
        var monto = $("#monto").val();
        if (!motivo || !monto) {
            alert("Por favor, complete todos los campos obligatorios.");
            e.preventDefault();
        }
    });
});
