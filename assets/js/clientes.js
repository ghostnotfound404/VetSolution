$(document).ready(function() {
    // Funcionalidad de búsqueda de clientes en tiempo real
    $("#buscar_nombre").on('input', function() {
        var nombre = $(this).val();
        if (nombre.length >= 2) {
            // Buscar automáticamente cuando hay al menos 2 caracteres
            realizarBusqueda(nombre);
        }
    });

    function realizarBusqueda(nombre) {
        $.ajax({
            url: 'modules/clientes.php',
            method: 'GET',
            data: { buscar_nombre: nombre },
            success: function(response) {
                $("#contenido").html(response);
                inicializarEventosClientes(); // Reinicializar eventos después de cargar contenido
            },
            error: function() {
                console.error("Error al realizar la búsqueda.");
            }
        });
    }

    // Funcionalidad para el formulario de crear cliente en modal
    $(document).off('submit', '#formNuevoCliente');
    $(document).on('submit', '#formNuevoCliente', function(e) {
        e.preventDefault();

        // Obtener los valores de los campos
        var nombre = $("#nombre").val().trim();
        var apellido = $("#apellido").val().trim();
        var celular = $("#celular").val().trim();
        var dni = $("#dni").val().trim();
        var direccion = $("#direccion").val().trim();

        // Validación de campos obligatorios
        var errores = [];
        
        if (!nombre) {
            errores.push("El nombre es obligatorio");
        }
        
        if (!apellido) {
            errores.push("El apellido es obligatorio");
        }
        
        if (!celular) {
            errores.push("El celular es obligatorio");
        } else if (!/^[0-9]{9}$/.test(celular)) {
            errores.push("El celular debe tener exactamente 9 números");
        }
        
        // DNI es opcional, pero si se proporciona debe ser válido
        if (dni && !/^[0-9]{8}$/.test(dni)) {
            errores.push("El DNI debe tener exactamente 8 números");
        }

        if (errores.length > 0) {
            // Errores de validación - no hacer nada
            return;
        }

        $.ajax({
            url: 'modules/clientes.php',
            method: 'POST',
            data: {
                nombre: nombre,
                apellido: apellido,
                celular: celular,
                dni: dni,
                direccion: direccion
            },
            success: function(response) {
                // Cerrar modal
                $('#nuevoClienteModal').modal('hide');
                
                // Limpiar formulario
                $('#formNuevoCliente')[0].reset();
                
                // Recargar contenido
                cargarContenidoClientes();
            },
            error: function() {
                // Error silencioso - solo cerrar modal
                $('#nuevoClienteModal').modal('hide');
                $('#formNuevoCliente')[0].reset();
            }
        });
    });

    // Función para cargar contenido de clientes
    function cargarContenidoClientes() {
        $.ajax({
            url: 'modules/clientes.php',
            method: 'GET',
            success: function(response) {
                $("#contenido").html(response);
                inicializarEventosClientes();
            }
        });
    }

    // Función para inicializar eventos después de cargar contenido dinámico
    function inicializarEventosClientes() {
        // Event listener para el botón de crear cliente
        $(document).off('click', '[data-bs-target="#nuevoClienteModal"]');
        $(document).on('click', '[data-bs-target="#nuevoClienteModal"]', function() {
            $('#formNuevoCliente')[0].reset();
            // Limpiar mensajes de error
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();
        });

        // Validación en tiempo real para celular
        $(document).off('input', '#celular');
        $(document).on('input', '#celular', function() {
            var valor = $(this).val().replace(/\D/g, ''); // Solo números
            $(this).val(valor.substring(0, 9)); // Máximo 9 dígitos
            
            if (valor.length > 0 && valor.length !== 9) {
                $(this).addClass('is-invalid');
                if (!$(this).next('.invalid-feedback').length) {
                    $(this).after('<div class="invalid-feedback">Debe tener exactamente 9 números</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();
            }
        });

        // Validación en tiempo real para DNI
        $(document).off('input', '#dni');
        $(document).on('input', '#dni', function() {
            var valor = $(this).val().replace(/\D/g, ''); // Solo números
            $(this).val(valor.substring(0, 8)); // Máximo 8 dígitos
            
            // Solo validar si hay contenido (ya que es opcional)
            if (valor.length > 0 && valor.length !== 8) {
                $(this).addClass('is-invalid');
                if (!$(this).next('.invalid-feedback').length) {
                    $(this).after('<div class="invalid-feedback">Debe tener exactamente 8 números</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();
            }
        });

        // Deshabilitamos los event listeners para los botones de Excel y PDF
        // ya que ahora usamos onclick directo en el HTML
        $(document).off('click', '.btn-outline-success, .btn-outline-danger');
    }

    // Inicializar eventos al cargar la página
    inicializarEventosClientes();
});
