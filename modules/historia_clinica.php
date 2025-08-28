<?php
include('../includes/config.php');

// Obtener ID de la mascota
$id_mascota = isset($_GET['id_mascota']) ? intval($_GET['id_mascota']) : 0;

if ($id_mascota <= 0) {
    echo '<div class="alert alert-danger">ID de mascota no válido</div>';
    exit;
}

// Obtener información de la mascota
$sql_mascota = "SELECT m.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente 
                FROM mascotas m 
                JOIN clientes c ON m.id_cliente = c.id_cliente 
                WHERE m.id_mascota = ?";
$stmt_mascota = $conn->prepare($sql_mascota);
$stmt_mascota->bind_param("i", $id_mascota);
$stmt_mascota->execute();
$mascota = $stmt_mascota->get_result()->fetch_assoc();
$stmt_mascota->close();

if (!$mascota) {
    echo '<div class="alert alert-danger">Mascota no encontrada</div>';
    exit;
}

// Procesar formulario de nueva historia clínica
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_historia'])) {
    // Asegurarnos de enviar el contenido como JSON
    header('Content-Type: application/json');
    
    // Log para depuración - comentar o eliminar en producción
    error_log("Procesando nueva historia clínica para mascota ID: $id_mascota");
    
    $motivo_atencion = trim($_POST['motivo_atencion'] ?? '');
    $anamnesis = trim($_POST['anamnesis'] ?? '');
    $descripcion_caso = trim($_POST['descripcion_caso'] ?? '');
    $temperatura = isset($_POST['temperatura']) ? floatval($_POST['temperatura']) : 0;
    $peso = isset($_POST['peso']) ? floatval($_POST['peso']) : 0;
    $frecuencia_cardiaca = !empty($_POST['frecuencia_cardiaca']) ? intval($_POST['frecuencia_cardiaca']) : null;
    $tlc_tiempo_llenado = trim($_POST['tlc_tiempo_llenado'] ?? '');
    $dth_deshidratacion = trim($_POST['dth_deshidratacion'] ?? '');
    $examen_clinico = trim($_POST['examen_clinico'] ?? '');
    
    // Validaciones
    $errores = [];
    if (empty($anamnesis)) $errores[] = "La anamnesis es obligatoria";
    if (empty($descripcion_caso)) $errores[] = "La descripción del caso es obligatoria";
    if ($temperatura <= 0) $errores[] = "La temperatura es obligatoria y debe ser mayor a 0";
    if ($peso <= 0) $errores[] = "El peso es obligatorio y debe ser mayor a 0";
    if (empty($examen_clinico)) $errores[] = "El examen clínico es obligatorio";
    
    if (empty($errores)) {
        $conn->begin_transaction();
        try {
            // Establecer fecha actual en zona horaria de Lima, Perú
            $fecha_actual = date('Y-m-d H:i:s');
            
            // Insertar historia clínica
            $sql_insert = "INSERT INTO historia_clinica (id_mascota, motivo, anamnesis, peso, temperatura, diagnostico, observaciones, fecha) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("issddsss", $id_mascota, $motivo_atencion, $anamnesis, $peso, $temperatura, $descripcion_caso, $examen_clinico, $fecha_actual);
            $stmt_insert->execute();
            $id_historia = $conn->insert_id;
            $stmt_insert->close();
            
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => 'Historia clínica guardada correctamente']);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => implode('\n', $errores)]);
        exit;
    }
}

// Procesar edición de historia clínica
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    header('Content-Type: application/json');
    
    $id_historia = intval($_POST['id_historia']);
    $motivo_atencion = trim($_POST['motivo_atencion']);
    $anamnesis = trim($_POST['anamnesis']);
    $descripcion_caso = trim($_POST['descripcion_caso']);
    $temperatura = floatval($_POST['temperatura']);
    $peso = floatval($_POST['peso']);
    $examen_clinico = trim($_POST['examen_clinico']);
    
    // Validaciones
    $errores = [];
    if ($id_historia <= 0) $errores[] = "ID de historia no válido";
    if (empty($anamnesis)) $errores[] = "La anamnesis es obligatoria";
    if (empty($descripcion_caso)) $errores[] = "La descripción del caso es obligatoria";
    if ($temperatura <= 0) $errores[] = "La temperatura es obligatoria y debe ser mayor a 0";
    if ($peso <= 0) $errores[] = "El peso es obligatorio y debe ser mayor a 0";
    if (empty($examen_clinico)) $errores[] = "El examen clínico es obligatorio";
    
    if (empty($errores)) {
        // Verificar que la historia existe
        $sql_check = "SELECT id_historia FROM historia_clinica WHERE id_historia = ? AND id_mascota = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $id_historia, $id_mascota);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Actualizar la historia clínica
            $sql_update = "UPDATE historia_clinica 
                          SET motivo = ?, anamnesis = ?, diagnostico = ?, peso = ?, temperatura = ?, observaciones = ?
                          WHERE id_historia = ? AND id_mascota = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sssddsii", $motivo_atencion, $anamnesis, $descripcion_caso, $peso, $temperatura, $examen_clinico, $id_historia, $id_mascota);
            
            if ($stmt_update->execute()) {
                echo json_encode(['success' => true, 'message' => 'Historia clínica actualizada correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $conn->error]);
            }
            $stmt_update->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'La historia clínica no existe']);
        }
        $stmt_check->close();
    } else {
        echo json_encode(['success' => false, 'message' => implode('\n', $errores)]);
    }
    
    $conn->close();
    exit;
}

// Obtener estadísticas
$total_consultas = $conn->query("SELECT COUNT(*) as total FROM historia_clinica WHERE id_mascota = $id_mascota")->fetch_assoc()['total'];
$consultas_mes = $conn->query("SELECT COUNT(*) as total FROM historia_clinica WHERE id_mascota = $id_mascota AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())")->fetch_assoc()['total'];
$ultimo_peso = $conn->query("SELECT peso FROM historia_clinica WHERE id_mascota = $id_mascota ORDER BY fecha DESC LIMIT 1")->fetch_assoc()['peso'] ?? 0;
$ultima_temperatura = $conn->query("SELECT temperatura FROM historia_clinica WHERE id_mascota = $id_mascota ORDER BY fecha DESC LIMIT 1")->fetch_assoc()['temperatura'] ?? 0;

// Configurar zona horaria a Lima, Perú
date_default_timezone_set('America/Lima');

// Obtener historiales existentes
$sql_historiales = "SELECT * FROM historia_clinica WHERE id_mascota = ? ORDER BY fecha DESC";
$stmt_historiales = $conn->prepare($sql_historiales);
$stmt_historiales->bind_param("i", $id_mascota);
$stmt_historiales->execute();
$historiales = $stmt_historiales->get_result();
$stmt_historiales->close();
?>

<div class="container-fluid px-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Historia Clínica</h2>
    </div>

    <!-- Información de la Mascota (Formato Vertical) -->
    <div class="row mb-4">
        <div class="col-md-4">
            <!-- Tarjeta de información de la mascota -->
            <div class="card mb-4">
                <div class="card-body p-3">
                    
                    
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="bg-light rounded-circle p-2 text-center" style="width: 50px; height: 50px;">
                            <i class="fas fa-paw text-primary fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="mb-3 text-center"><?php echo htmlspecialchars($mascota['nombre']); ?></h3>
                    
                    <div class="mb-3">
                        <p class="mb-1"><strong>N° Historia Clínica:</strong> <?php echo $mascota['id_mascota']; ?></p>
                        <p class="mb-1"><strong>Especie:</strong> <?php echo strtoupper($mascota['especie']); ?></p>
                        <p class="mb-1"><strong>Raza:</strong> <?php echo htmlspecialchars($mascota['raza']); ?></p>
                        <p class="mb-1"><strong>Sexo:</strong> <?php echo isset($mascota['genero']) ? strtoupper($mascota['genero']) : 'NO ESPECIFICADO'; ?></p>
                        <p class="mb-1"><strong>¿Esterilizado?:</strong> <?php echo $mascota['esterilizado'] ? 'SÍ' : 'NO'; ?></p>
                        <p class="mb-1"><strong>Fecha de nacimiento:</strong> <?php echo date('d-m-Y', strtotime($mascota['fecha_nacimiento'])); ?></p>
                        <p class="mb-1"><strong>Edad:</strong> <?php 
                            $fecha_nac = new DateTime($mascota['fecha_nacimiento']);
                            $hoy = new DateTime();
                            $edad = $hoy->diff($fecha_nac);
                            echo $edad->y . " años y " . $edad->m . " meses"; 
                        ?></p>
                    </div>

                    <div class="mb-3">
                        <p class="mb-1">
                            <i class="fab fa-whatsapp text-success me-1"></i> <strong>Propietario:</strong><br>
                            <?php echo htmlspecialchars($mascota['nombre_cliente'] . ' ' . $mascota['apellido_cliente']); ?>
                        </p>
                    </div>
                    
                    <div class="text-center">
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#nuevaConsultaModal">
                            <i class="fas fa-plus-circle me-1"></i> Nueva Consulta
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Contenedor para las tarjetas de resumen -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card bg-success text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Último Peso</h6>
                                    <h4 class="mb-0"><?php echo $ultimo_peso ? number_format($ultimo_peso, 2) . ' Kg' : 'N/A'; ?></h4>
                                </div>
                                <i class="fas fa-weight fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Historiales -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historial de Consultas</h5>
        </div>
        <div class="card-body">
            <?php if ($historiales->num_rows > 0): ?>
                <?php while ($historia = $historiales->fetch_assoc()): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php 
                                    $fecha = new DateTime($historia['fecha'], new DateTimeZone('America/Lima'));
                                    echo $fecha->format('d/m/Y H:i'); 
                                    ?> - consulta
                                    <?php if ($historia['motivo']): ?>
                                        <span class="text-muted">- <?php echo htmlspecialchars($historia['motivo']); ?></span>
                                    <?php endif; ?>
                                </h6>
                                <button class="btn btn-sm btn-outline-warning" onclick="editarHistoria(<?php echo $historia['id_historia']; ?>)">
                                    <i class="fas fa-edit me-1"></i> Editar
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-notes-medical me-2"></i>Anamnesis:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($historia['anamnesis'])); ?></p>
                                    
                                    <h6><i class="fas fa-file-medical me-2"></i>Diagnóstico:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($historia['diagnostico'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-heartbeat me-2"></i>Constantes Fisiológicas:</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Temperatura:</strong> <?php echo $historia['temperatura']; ?>°C</li>
                                        <li><strong>Peso:</strong> <?php echo $historia['peso']; ?> Kg</li>
                                    </ul>
                                    
                                    <h6><i class="fas fa-stethoscope me-2"></i>Observaciones:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($historia['observaciones'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-notes-medical fa-4x text-muted mb-3"></i>
                    <h4>No hay consultas registradas</h4>
                    <p class="text-muted">Comienza creando una nueva consulta médica</p>
                    <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#nuevaConsultaModal">
                        <i class="fas fa-plus-circle me-1"></i> Registrar Consulta
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para Nueva Consulta -->
<div class="modal fade" id="nuevaConsultaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-notes-medical me-2"></i>Nueva Consulta Médica</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formHistoriaClinica" action="modules/historia_clinica.php?id_mascota=<?php echo $id_mascota; ?>" method="post">
                    <input type="hidden" name="crear_historia" value="1">
                    <input type="hidden" name="id_mascota" value="<?php echo $id_mascota; ?>">
                    <input type="hidden" name="url_format" value="#/mascotas/historia/<?php echo $id_mascota; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha y Hora de Atención</label>
                            <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i'); ?>" readonly>
                            <small class="text-muted">Se registra automáticamente con la zona horaria de Lima, Perú</small>
                        </div>
                        <div class="col-md-6">
                            <label for="motivo_atencion" class="form-label">Motivo de Atención</label>
                            <input type="text" class="form-control" id="motivo_atencion" name="motivo_atencion" 
                                   placeholder="Ej: Consulta rutinaria, vacunación, enfermedad...">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="anamnesis" class="form-label">Anamnesis <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="anamnesis" name="anamnesis" rows="3" required 
                                      placeholder="Describir síntomas, comportamiento, historial relevante..."></textarea>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="descripcion_caso" class="form-label">Diagnóstico <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="descripcion_caso" name="descripcion_caso" rows="3" required 
                                      placeholder="Diagnóstico y descripción detallada del caso..."></textarea>
                        </div>
                    </div>

                    <!-- Constantes Fisiológicas -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Constantes Fisiológicas</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="temperatura" class="form-label">Temperatura (°C) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.1" class="form-control" id="temperatura" name="temperatura" required 
                                           placeholder="38.5">
                                </div>
                                <div class="col-md-6">
                                    <label for="peso" class="form-label">Peso (Kg) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="peso" name="peso" required 
                                           placeholder="5.2">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="examen_clinico" class="form-label">Observaciones <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="examen_clinico" name="examen_clinico" rows="4" required 
                                      placeholder="Resultados del examen físico completo..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="submit" form="formHistoriaClinica" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Guardar Historia
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Editar Consulta -->
<div class="modal fade" id="editarHistoriaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Consulta Médica</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoModalEditarHistoria">
                <!-- El contenido se carga dinámicamente -->
            </div>
        </div>
    </div>
</div>

<script>
// Función para extraer el ID de la mascota de la URL con formato #/mascotas/historia/ID
function getMascotaIdFromUrl() {
    const hash = window.location.hash;
    if (hash && hash.includes('/mascotas/historia/')) {
        const parts = hash.split('/');
        return parts[parts.length - 1]; // Obtener el último segmento de la URL
    }
    return null;
}

$(document).ready(function() {
    // Manejar envío del formulario de nueva historia
    $('#formHistoriaClinica').on('submit', function(e) {
        e.preventDefault();
        
        if ($(this).data('submitting')) {
            return false;
        }
        $(this).data('submitting', true);
        
        const formData = new FormData(this);
        
        // Agregar la URL en el formato correcto para poder redireccionar de vuelta
        const id_mascota = <?php echo $id_mascota; ?>;
        const mascotaUrl = '#/mascotas/historia/' + id_mascota;
        formData.append('current_url', mascotaUrl);
        
        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                $('#formHistoriaClinica').data('submitting', false);
                console.log('Respuesta del servidor:', response);
                
                if (response.success) {
                    // Ocultar el modal antes de mostrar el mensaje
                    $('#nuevaConsultaModal').modal('hide');
                    
                    // Limpiar el formulario para futuras entradas
                    $('#formHistoriaClinica')[0].reset();
                    
                    // Mostrar mensaje de éxito y luego recargar la página actual
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Historia clínica guardada correctamente',
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        // Recargar solo el contenido de la historia clínica sin recargar toda la página
                        const id_mascota = <?php echo $id_mascota; ?>;
                        
                        // Recargar el contenido de forma directa
                        $.ajax({
                            url: 'modules/historia_clinica.php?id_mascota=' + id_mascota,
                            method: 'GET',
                            success: function(response) {
                                $("#contenido").html(response);
                                
                                // Asegurar que la URL es la correcta
                                const newUrl = '#/mascotas/historia/' + id_mascota;
                                if (window.location.hash !== newUrl) {
                                    window.history.pushState({mascotaId: id_mascota}, '', newUrl);
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Error al recargar la historia clínica',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        });
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al guardar la historia clínica',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#formHistoriaClinica').data('submitting', false);
                console.error('Error en la petición:', status, error);
                console.log('Respuesta del servidor:', xhr.responseText);
                
                let errorMsg = 'Error al guardar la historia clínica';
                
                // Intentar obtener un mensaje de error más detallado si está disponible
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg,
                    confirmButtonColor: '#dc3545',
                    footer: 'Importante: A pesar del error, es posible que la historia se haya guardado. Intente refrescar la página.'
                }).then(() => {
                    // En caso de error, intentamos recargar la página de todos modos
                    // ya que es posible que la historia se haya guardado correctamente
                    const id_mascota = <?php echo $id_mascota; ?>;
                    
                    // Recargar el contenido de forma directa
                    $.ajax({
                        url: 'modules/historia_clinica.php?id_mascota=' + id_mascota,
                        method: 'GET',
                        success: function(response) {
                            $("#contenido").html(response);
                            
                            // Asegurar que la URL es la correcta
                            const newUrl = '#/mascotas/historia/' + id_mascota;
                            if (window.location.hash !== newUrl) {
                                window.history.pushState({mascotaId: id_mascota}, '', newUrl);
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error al recargar la historia clínica',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    });
                });
            }
        });
    });
});

function editarHistoria(id) {
    // Asegurarnos que la URL está en el formato correcto
    const id_mascota = <?php echo $id_mascota; ?>;
    const newUrl = '#/mascotas/historia/' + id_mascota;
    if (window.location.hash !== newUrl) {
        window.history.pushState(null, '', newUrl);
    }
    
    $.ajax({
        url: 'modules/editar_historia.php?id=' + id + '&id_mascota=<?php echo $id_mascota; ?>',
        method: 'GET',
        success: function(response) {
            $('#contenidoModalEditarHistoria').html(response);
            $('#editarHistoriaModal').modal('show');
        },
        error: function() {
            Swal.fire('Error', 'No se pudo cargar la información de la consulta', 'error');
        }
    });
}

// Actualizar la URL al cargar la historia clínica
$(document).ready(function() {
    // Actualiza la URL para reflejar el formato esperado
    const id_mascota = <?php echo $id_mascota; ?>;
    const newUrl = '#/mascotas/historia/' + id_mascota;
    
    // Cambiar la URL sin recargar la página completamente
    // Solo si estamos en una URL diferente y no estamos en un iframe o carga parcial
    if (window.location.hash !== newUrl && window.top === window.self) {
        window.history.pushState({mascotaId: id_mascota}, '', newUrl);
    }
});
</script>
