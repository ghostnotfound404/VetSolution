// JavaScript específico para el módulo de mascotas
$(document).ready(function() {
    // Configurar fecha máxima para fecha de nacimiento (hoy)
    const today = new Date().toISOString().split('T')[0];
    $('#fecha_nacimiento').attr('max', today);
    
    // Validación en tiempo real del formulario
    setupFormValidation();
    
    // Limpiar modal al cerrar
    $('#nuevaMascotaModal').on('hidden.bs.modal', function () {
        $('#formNuevaMascota')[0].reset();
        $('#resultados_propietario').html('');
        $('#id_cliente').val('');
        clearValidationStates();
    });
});

// Configurar validación del formulario
function setupFormValidation() {
    // Validación del nombre de mascota
    $('#nombre_mascota').on('input', function() {
        const nombre = $(this).val().trim();
        if (nombre.length < 2) {
            showValidationError($(this), 'El nombre debe tener al menos 2 caracteres');
        } else if (nombre.length > 50) {
            showValidationError($(this), 'El nombre no puede exceder 50 caracteres');
        } else if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(nombre)) {
            showValidationError($(this), 'El nombre solo puede contener letras y espacios');
        } else {
            showValidationSuccess($(this));
        }
    });
    
    // Validación de fecha de nacimiento
    $('#fecha_nacimiento').on('change', function() {
        const fecha = new Date($(this).val());
        const hoy = new Date();
        const hace50Anos = new Date();
        hace50Anos.setFullYear(hoy.getFullYear() - 50);
        
        if (fecha > hoy) {
            showValidationError($(this), 'La fecha no puede ser futura');
        } else if (fecha < hace50Anos) {
            showValidationError($(this), 'La fecha no puede ser anterior a 50 años');
        } else {
            showValidationSuccess($(this));
        }
    });
    
    // Validación de especie
    $('#especie').on('change', function() {
        if ($(this).val() === '') {
            showValidationError($(this), 'Debe seleccionar una especie');
        } else {
            showValidationSuccess($(this));
        }
    });
    
    // Validación de género
    $('#genero').on('change', function() {
        if ($(this).val() === '') {
            showValidationError($(this), 'Debe seleccionar un género');
        } else {
            showValidationSuccess($(this));
        }
    });
    
    // Validación antes de enviar
    $('#formNuevaMascota').on('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            alert('Por favor, corrija los errores en el formulario antes de continuar.');
        }
    });
}

// Mostrar error de validación
function showValidationError(element, message) {
    element.removeClass('is-valid').addClass('is-invalid');
    element.next('.invalid-feedback').remove();
    element.after(`<div class="invalid-feedback">${message}</div>`);
}

// Mostrar éxito de validación
function showValidationSuccess(element) {
    element.removeClass('is-invalid').addClass('is-valid');
    element.next('.invalid-feedback').remove();
}

// Limpiar estados de validación
function clearValidationStates() {
    $('.form-control, .form-select').removeClass('is-valid is-invalid');
    $('.invalid-feedback, .valid-feedback').remove();
}

// Validar todo el formulario
function validateForm() {
    let isValid = true;
    
    // Validar propietario seleccionado
    if ($('#id_cliente').val() === '') {
        alert('Debe seleccionar un propietario');
        isValid = false;
    }
    
    // Validar nombre
    const nombre = $('#nombre_mascota').val().trim();
    if (nombre.length < 2 || nombre.length > 50 || !/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(nombre)) {
        $('#nombre_mascota').focus();
        isValid = false;
    }
    
    // Validar fecha
    const fecha = new Date($('#fecha_nacimiento').val());
    const hoy = new Date();
    if (fecha > hoy || isNaN(fecha.getTime())) {
        $('#fecha_nacimiento').focus();
        isValid = false;
    }
    
    // Validar especie
    if ($('#especie').val() === '') {
        $('#especie').focus();
        isValid = false;
    }
    
    // Validar género
    if ($('#genero').val() === '') {
        $('#genero').focus();
        isValid = false;
    }
    
    return isValid;
}

// Buscar propietario en tiempo real
$('#buscar_propietario').on('input', function() {
    const busqueda = $(this).val().trim();
    if (busqueda.length >= 2) {
        $.ajax({
            url: 'modules/buscar_propietario.php',
            method: 'GET',
            data: { q: busqueda },
            success: function(response) {
                $('#resultados_propietario').html(response);
            },
            error: function() {
                $('#resultados_propietario').html('<div class="alert alert-danger">Error al buscar propietarios</div>');
            }
        });
    } else {
        $('#resultados_propietario').html('');
        $('#id_cliente').val('');
    }
});

// Seleccionar propietario
function seleccionarPropietario(id, nombre) {
    $('#id_cliente').val(id);
    $('#buscar_propietario').val(nombre);
    $('#resultados_propietario').html(`<div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Propietario seleccionado: <strong>${nombre}</strong>
    </div>`);
}

// Cargar razas según especie
$('#especie').on('change', function() {
    const especie = $(this).val();
    cargarRazas(especie);
});

function cargarRazas(especie) {
    const razas = {
        'Canino': [
            'Labrador Retriever', 'Golden Retriever', 'Pastor Alemán', 'Bulldog Francés', 
            'Bulldog Inglés', 'Beagle', 'Poodle', 'Rottweiler', 'Yorkshire Terrier', 
            'Chihuahua', 'Boxer', 'Dálmata', 'Cocker Spaniel', 'Border Collie', 
            'Husky Siberiano', 'Doberman', 'Shih Tzu', 'Mestizo', 'Otro'
        ],
        'Felino': [
            'Persa', 'Siamés', 'Maine Coon', 'Británico de Pelo Corto', 'Ragdoll', 
            'Bengalí', 'Abisinio', 'Birmano', 'Angora Turco', 'Esfinge', 
            'Scottish Fold', 'Mestizo', 'Otro'
        ]
    };
    
    const select = $('#raza');
    select.html('<option value="">Seleccionar raza...</option>');
    
    if (razas[especie]) {
        razas[especie].forEach(function(raza) {
            select.append(`<option value="${raza}">${raza}</option>`);
        });
    }
}

// Funciones para botones de acción
function editarMascota(id) {
    // TODO: Implementar modal de edición
    alert(`Editar mascota ID: ${id}\n(Funcionalidad por implementar)`);
}

function verHistoria(id) {
    // TODO: Redirigir a historia clínica
    alert(`Ver historia clínica de mascota ID: ${id}\n(Funcionalidad por implementar)`);
}

// Función para calcular edad aproximada
function calcularEdad(fechaNacimiento) {
    const hoy = new Date();
    const nacimiento = new Date(fechaNacimiento);
    const diferencia = hoy - nacimiento;
    const años = Math.floor(diferencia / (1000 * 60 * 60 * 24 * 365.25));
    const meses = Math.floor((diferencia % (1000 * 60 * 60 * 24 * 365.25)) / (1000 * 60 * 60 * 24 * 30.44));
    
    if (años > 0) {
        return `${años} año${años > 1 ? 's' : ''}`;
    } else if (meses > 0) {
        return `${meses} mes${meses > 1 ? 'es' : ''}`;
    } else {
        return 'Menos de 1 mes';
    }
}
