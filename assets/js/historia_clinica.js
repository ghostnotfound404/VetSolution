$(document).ready(function() {
    // Validación del formulario de historia clínica
    $("form").submit(function(e) {
        var motivo = $("#motivo").val();
        var diagnostico = $("#diagnostico").val();
        if (!motivo || !diagnostico) {
            alert("Por favor, complete todos los campos obligatorios.");
            e.preventDefault();
        }
    });
});
