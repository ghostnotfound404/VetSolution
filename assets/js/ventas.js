$(document).ready(function() {
    // Validación del formulario de ventas
    $("form").submit(function(e) {
        var cantidad = $("#cantidad").val();
        if (!cantidad || cantidad <= 0) {
            alert("Por favor, ingrese una cantidad válida.");
            e.preventDefault();
        }
    });
});
