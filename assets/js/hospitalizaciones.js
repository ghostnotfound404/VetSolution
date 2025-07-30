$(document).ready(function() {
    // Validación del formulario de hospitalizaciones
    $("form").submit(function(e) {
        var hora_inicio = $("#hora_inicio").val();
        var hora_termino = $("#hora_termino").val();
        if (!hora_inicio || !hora_termino) {
            alert("Por favor, complete todos los campos.");
            e.preventDefault();
        }
    });
});
