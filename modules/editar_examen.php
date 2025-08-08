<?php
include('../includes/config.php');

// Procesar la actualización del examen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    header('Content-Type: application/json');
    
    $id_examen_lab = intval($_POST['id_examen_lab']);
    $laboratorio = strtoupper(trim($_POST['laboratorio']));
    $tipo_analisis = trim($_POST['tipo_analisis']);
    $costo = floatval($_POST['costo']);
    $pagado = isset($_POST['pagado']) ? 1 : 0;
    $fecha_envio = $_POST['fecha_envio'];
    $lectura_realizada = isset($_POST['lectura_realizada']) ? 1 : 0;

    // Validaciones
    $errores = [];
    if ($id_examen_lab <= 0) $errores[] = "ID de examen inválido";
    if (empty($laboratorio)) $errores[] = "El laboratorio es obligatorio";
    if (empty($tipo_analisis)) $errores[] = "El tipo de análisis es obligatorio";
    if ($costo <= 0) $errores[] = "El costo debe ser mayor a 0";
    if (empty($fecha_envio)) $errores[] = "La fecha de envío es obligatoria";

    if (empty($errores)) {
        // Verificar que el examen existe
        $sql_check = "SELECT id_examen_lab FROM examenes_laboratorio WHERE id_examen_lab = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $id_examen_lab);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Actualizar el examen
            $sql_update = "UPDATE examenes_laboratorio 
                          SET laboratorio = ?, tipo_analisis = ?, costo = ?, pagado = ?, fecha_envio = ?, lectura_realizada = ?
                          WHERE id_examen_lab = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ssdsisi", $laboratorio, $tipo_analisis, $costo, $pagado, $fecha_envio, $lectura_realizada, $id_examen_lab);
            
            if ($stmt_update->execute()) {
                echo json_encode(['success' => true, 'message' => 'Examen actualizado correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $conn->error]);
            }
            $stmt_update->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'El examen no existe']);
        }
        $stmt_check->close();
    } else {
        echo json_encode(['success' => false, 'message' => implode('\n', $errores)]);
    }
    
    $conn->close();
    exit;
}

if (isset($_GET['id'])) {
    $id_examen = intval($_GET['id']);
    
    // Obtener datos del examen
    $sql = "SELECT el.*, m.nombre as nombre_mascota, c.nombre as nombre_cliente, c.apellido as apellido_cliente
            FROM examenes_laboratorio el
            JOIN mascotas m ON el.id_mascota = m.id_mascota
            JOIN clientes c ON m.id_cliente = c.id_cliente
            WHERE el.id_examen_lab = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_examen);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($examen = $result->fetch_assoc()) {
?>
        <form id="formEditarExamen">
            <input type="hidden" name="id_examen_lab" value="<?php echo $examen['id_examen_lab']; ?>">
            <input type="hidden" name="accion" value="editar">
            
            <!-- Información de la Mascota (No editable) -->
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-paw text-primary me-1"></i>
                        Mascota Asignada
                    </label>
                    <div class="card bg-light border-0">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-3">
                                    <div class="bg-primary rounded-circle text-white d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                        <i class="fas fa-paw"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($examen['nombre_mascota']); ?></h6>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($examen['nombre_cliente'] . ' ' . $examen['apellido_cliente']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-text text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        No se puede cambiar la mascota al editar un examen
                    </div>
                </div>
            </div>

            <!-- Información del Laboratorio -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6">
                    <label for="edit_laboratorio" class="form-label fw-semibold">
                        <i class="fas fa-flask text-info me-1"></i>
                        Laboratorio <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex">
                            <i class="fas fa-building"></i>
                        </span>
                        <input type="text" class="form-control" id="edit_laboratorio" name="laboratorio" required 
                               value="<?php echo htmlspecialchars($examen['laboratorio']); ?>"
                               placeholder="Nombre del laboratorio"
                               style="text-transform: uppercase;">
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <label for="edit_tipo_analisis" class="form-label fw-semibold">
                        <i class="fas fa-microscope text-warning me-1"></i>
                        Tipo de Análisis <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex">
                            <i class="fas fa-vial"></i>
                        </span>
                        <input type="text" class="form-control" id="edit_tipo_analisis" name="tipo_analisis" required 
                               value="<?php echo htmlspecialchars($examen['tipo_analisis']); ?>"
                               placeholder="Ej: Hemograma completo...">
                    </div>
                </div>
            </div>

            <!-- Costo y Fecha -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6">
                    <label for="edit_costo" class="form-label fw-semibold">
                        <i class="fas fa-dollar-sign text-success me-1"></i>
                        Costo (S/) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">S/.</span>
                        <input type="number" step="0.01" class="form-control" id="edit_costo" name="costo" required min="0" 
                               value="<?php echo $examen['costo']; ?>" placeholder="0.00">
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <label for="edit_fecha_envio" class="form-label fw-semibold">
                        <i class="fas fa-calendar text-primary me-1"></i>
                        Fecha de Envío <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex">
                            <i class="fas fa-calendar-alt"></i>
                        </span>
                        <input type="date" class="form-control" id="edit_fecha_envio" name="fecha_envio" required
                               value="<?php echo date('Y-m-d', strtotime($examen['fecha_envio'])); ?>">
                    </div>
                </div>
            </div>

            <!-- Estados -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6">
                    <div class="card bg-light border-0">
                        <div class="card-body p-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_pagado" name="pagado" 
                                       <?php echo $examen['pagado'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="edit_pagado">
                                    <i class="fas fa-money-bill-wave text-success me-1"></i> 
                                    <span class="d-none d-sm-inline">¿Está pagado?</span>
                                    <span class="d-inline d-sm-none">Pagado</span>
                                </label>
                            </div>
                            <small class="text-muted">Marque si el examen ya fue pagado</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="card bg-light border-0">
                        <div class="card-body p-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_lectura_realizada" name="lectura_realizada"
                                       <?php echo $examen['lectura_realizada'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="edit_lectura_realizada">
                                    <i class="fas fa-eye text-info me-1"></i> 
                                    <span class="d-none d-sm-inline">¿Se realizó la lectura?</span>
                                    <span class="d-inline d-sm-none">Lectura realizada</span>
                                </label>
                            </div>
                            <small class="text-muted">Marque si ya se leyeron los resultados</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Información del historial -->
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-light border d-flex align-items-center mb-0">
                        <i class="fas fa-history text-info me-2"></i>
                        <div class="flex-grow-1">
                            <small class="mb-0 text-muted">
                                <strong>Registro:</strong> Este examen fue creado el <?php echo date('d/m/Y H:i', strtotime($examen['fecha_creacion'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="modal-footer flex-column flex-sm-row p-3">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i> Cancelar
            </button>
            <button type="submit" form="formEditarExamen" class="btn btn-warning">
                <i class="fas fa-save me-1"></i> 
                <span class="d-none d-sm-inline">Actualizar Examen</span>
                <span class="d-inline d-sm-none">Actualizar</span>
            </button>
        </div>

        <script>
        // Convertir laboratorio a mayúsculas automáticamente
        $('#edit_laboratorio').on('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Manejar envío del formulario de edición
        $('#formEditarExamen').on('submit', function(e) {
            e.preventDefault();
            
            if ($(this).data('submitting')) {
                return false;
            }
            $(this).data('submitting', true);
            
            // Mostrar loading en el botón
            const $submitBtn = $('button[type="submit"][form="formEditarExamen"]');
            const originalText = $submitBtn.html();
            $submitBtn.html('<i class="fas fa-spinner fa-spin me-1"></i> Actualizando...');
            $submitBtn.prop('disabled', true);
            
            const formData = new FormData(this);
            
            $.ajax({
                url: 'modules/editar_examen.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    $('#formEditarExamen').data('submitting', false);
                    $submitBtn.html(originalText);
                    $submitBtn.prop('disabled', false);
                    
                    if (response.success) {
                        $('#editarExamenModal').modal('hide');
                        
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <p class="mb-0">${response.message}</p>
                                </div>
                            `,
                            confirmButtonColor: '#198754',
                            confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                            customClass: {
                                popup: 'swal-responsive'
                            }
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al actualizar',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                                    <p class="mb-0">${response.message}</p>
                                </div>
                            `,
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: '<i class="fas fa-times me-1"></i> Cerrar',
                            customClass: {
                                popup: 'swal-responsive'
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    $('#formEditarExamen').data('submitting', false);
                    $submitBtn.html(originalText);
                    $submitBtn.prop('disabled', false);
                    
                    let errorMessage = 'Error al actualizar el examen';
                    
                    if (status === 'timeout') {
                        errorMessage = 'La solicitud tardó demasiado tiempo. Verifica tu conexión.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Error interno del servidor.';
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-wifi fa-3x text-danger mb-3"></i>
                                <p class="mb-0">${errorMessage}</p>
                            </div>
                        `,
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: '<i class="fas fa-refresh me-1"></i> Reintentar',
                        customClass: {
                            popup: 'swal-responsive'
                        }
                    });
                }
            });
        });
        
        // Añadir validación visual a los campos requeridos
        $('#formEditarExamen input[required]').on('blur', function() {
            const $this = $(this);
            if (!$this.val().trim()) {
                $this.addClass('is-invalid');
            } else {
                $this.removeClass('is-invalid').addClass('is-valid');
            }
        });
        
        // Limpiar validación al escribir
        $('#formEditarExamen input').on('input', function() {
            $(this).removeClass('is-invalid is-valid');
        });
        </script>
<?php
    } else {
        echo '<div class="alert alert-danger">Examen no encontrado</div>';
    }
    $stmt->close();
} else {
    echo '<div class="alert alert-danger">ID de examen no válido</div>';
}

$conn->close();
?>
