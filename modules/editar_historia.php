<?php
include('../includes/config.php');

// Procesar la actualización de la historia clínica
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    header('Content-Type: application/json');
    
    $id_historia = intval($_POST['id_historia']);
    $id_mascota = intval($_POST['id_mascota']);
    $motivo_atencion = trim($_POST['motivo_atencion']);
    $anamnesis = trim($_POST['anamnesis']);
    $descripcion_caso = trim($_POST['descripcion_caso']);
    $temperatura = floatval($_POST['temperatura']);
    $peso = floatval($_POST['peso']);
    $examen_clinico = trim($_POST['examen_clinico']);
    
    // Validaciones
    $errores = [];
    if ($id_historia <= 0) $errores[] = "ID de historia no válido";
    if ($id_mascota <= 0) $errores[] = "ID de mascota no válido";
    if (empty($anamnesis)) $errores[] = "La anamnesis es obligatoria";
    if (empty($descripcion_caso)) $errores[] = "El diagnóstico es obligatorio";
    if ($temperatura <= 0) $errores[] = "La temperatura es obligatoria y debe ser mayor a 0";
    if ($peso <= 0) $errores[] = "El peso es obligatorio y debe ser mayor a 0";
    if (empty($examen_clinico)) $errores[] = "Las observaciones son obligatorias";
    
    if (empty($errores)) {
        // Verificar que la historia existe y pertenece a la mascota
        $sql_check = "SELECT id_historia FROM historia_clinica WHERE id_historia = ? AND id_mascota = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $id_historia, $id_mascota);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Actualizar la historia clínica (fecha no se modifica)
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

if (isset($_GET['id']) && isset($_GET['id_mascota'])) {
    $id_historia = intval($_GET['id']);
    $id_mascota = intval($_GET['id_mascota']);
    
    // Obtener datos de la historia clínica
    $sql = "SELECT hc.*, m.nombre as nombre_mascota, c.nombre as nombre_cliente, c.apellido as apellido_cliente
            FROM historia_clinica hc
            JOIN mascotas m ON hc.id_mascota = m.id_mascota
            JOIN clientes c ON m.id_cliente = c.id_cliente
            WHERE hc.id_historia = ? AND hc.id_mascota = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_historia, $id_mascota);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($historia = $result->fetch_assoc()) {
?>
        <form id="formEditarHistoria">
            <input type="hidden" name="id_historia" value="<?php echo $historia['id_historia']; ?>">
            <input type="hidden" name="id_mascota" value="<?php echo $historia['id_mascota']; ?>">
            <input type="hidden" name="accion" value="editar">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Fecha y Hora de Atención</label>
                    <input type="text" class="form-control bg-light" 
                           value="<?php echo date('d/m/Y H:i', strtotime($historia['fecha'])); ?>" readonly>
                    <small class="text-muted">La fecha de consulta no se puede modificar</small>
                </div>
                <div class="col-md-6">
                    <label for="edit_motivo_atencion" class="form-label">Motivo de Atención</label>
                    <input type="text" class="form-control" id="edit_motivo_atencion" name="motivo_atencion" 
                           value="<?php echo htmlspecialchars($historia['motivo']); ?>"
                           placeholder="Ej: Consulta rutinaria, vacunación, enfermedad...">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label for="edit_anamnesis" class="form-label">Anamnesis <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="edit_anamnesis" name="anamnesis" rows="3" required 
                              placeholder="Describir síntomas, comportamiento, historial relevante..."><?php echo htmlspecialchars($historia['anamnesis']); ?></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label for="edit_descripcion_caso" class="form-label">Diagnóstico <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="edit_descripcion_caso" name="descripcion_caso" rows="3" required 
                              placeholder="Diagnóstico y descripción detallada del caso..."><?php echo htmlspecialchars($historia['diagnostico']); ?></textarea>
                </div>
            </div>

            <!-- Constantes Fisiológicas -->
            <div class="card mb-3" style="border-color: #ffc107;">
                <div class="card-header" style="background-color: #fff3cd; border-color: #ffc107;">
                    <h6 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Constantes Fisiológicas</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="edit_temperatura" class="form-label">Temperatura (°C) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" class="form-control" id="edit_temperatura" name="temperatura" required 
                                   value="<?php echo $historia['temperatura']; ?>" placeholder="38.5">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_peso" class="form-label">Peso (Kg) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="edit_peso" name="peso" required 
                                   value="<?php echo $historia['peso']; ?>" placeholder="5.2">
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label for="edit_examen_clinico" class="form-label">Observaciones <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="edit_examen_clinico" name="examen_clinico" rows="4" required 
                              placeholder="Resultados del examen físico completo..."><?php echo htmlspecialchars($historia['observaciones']); ?></textarea>
                </div>
            </div>
        </form>
        
        <div class="modal-footer" style="background-color: #fff3cd; border-color: #ffc107;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i> Cancelar
            </button>
            <button type="submit" form="formEditarHistoria" class="btn btn-warning">
                <i class="fas fa-save me-1"></i> Actualizar Historia
            </button>
        </div>

        <script>
        // Manejar envío del formulario de edición
        $('#formEditarHistoria').on('submit', function(e) {
            e.preventDefault();
            
            if ($(this).data('submitting')) {
                return false;
            }
            $(this).data('submitting', true);
            
            const formData = new FormData(this);
            
            $.ajax({
                url: 'modules/editar_historia.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    $('#formEditarHistoria').data('submitting', false);
                    if (response.success) {
                        $('#editarHistoriaModal').modal('hide');
                        
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
                    $('#formEditarHistoria').data('submitting', false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al actualizar la historia clínica',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        });
        </script>
<?php
    } else {
        echo '<div class="alert alert-danger">Historia clínica no encontrada</div>';
    }
    $stmt->close();
} else {
    echo '<div class="alert alert-danger">Parámetros no válidos</div>';
}

$conn->close();
?>
