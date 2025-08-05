<?php
include('../includes/config.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id_examen = intval($_POST['id']);
    
    if ($id_examen > 0) {
        // Verificar que el examen existe
        $sql_check = "SELECT id_examen_lab FROM examenes_laboratorio WHERE id_examen_lab = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $id_examen);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Eliminar el examen
            $sql_delete = "DELETE FROM examenes_laboratorio WHERE id_examen_lab = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $id_examen);
            
            if ($stmt_delete->execute()) {
                echo json_encode(['success' => true, 'message' => 'Examen eliminado correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar el examen: ' . $conn->error]);
            }
            $stmt_delete->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'El examen no existe']);
        }
        $stmt_check->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'ID de examen inválido']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
}

$conn->close();
?>
