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
            
            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label">Mascota Asignada</label>
                    <div class="form-control bg-light">
                        <strong><?php echo htmlspecialchars($examen['nombre_mascota']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($examen['nombre_cliente'] . ' ' . $examen['apellido_cliente']); ?></small>
                    </div>
                    <small class="text-muted">No se puede cambiar la mascota al editar</small>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="edit_laboratorio" class="form-label">Laboratorio <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit_laboratorio" name="laboratorio" required 
                           value="<?php echo htmlspecialchars($examen['laboratorio']); ?>"
                           style="text-transform: uppercase;">
                </div>
                <div class="col-md-6">
                    <label for="edit_tipo_analisis" class="form-label">Tipo de Análisis <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit_tipo_analisis" name="tipo_analisis" required 
                           value="<?php echo htmlspecialchars($examen['tipo_analisis']); ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="edit_costo" class="form-label">Costo (S/) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="form-control" id="edit_costo" name="costo" required min="0" 
                           value="<?php echo $examen['costo']; ?>">
                </div>
                <div class="col-md-6">
                    <label for="edit_fecha_envio" class="form-label">Fecha de Envío <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="edit_fecha_envio" name="fecha_envio" required
                           value="<?php echo date('Y-m-d', strtotime($examen['fecha_envio'])); ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="edit_pagado" name="pagado" 
                               <?php echo $examen['pagado'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="edit_pagado">
                            <i class="fas fa-money-bill-wave me-1"></i> ¿Está pagado?
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="edit_lectura_realizada" name="lectura_realizada"
                               <?php echo $examen['lectura_realizada'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="edit_lectura_realizada">
                            <i class="fas fa-eye me-1"></i> ¿Se realizó la lectura?
                        </label>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i> Cancelar
            </button>
            <button type="submit" form="formEditarExamen" class="btn btn-warning">
                <i class="fas fa-save me-1"></i> Actualizar Examen
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
                    if (response.success) {
                        $('#editarExamenModal').modal('hide');
                        
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: response.message,
                            confirmButtonColor: '#198754'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    $('#formEditarExamen').data('submitting', false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al actualizar el examen',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
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
