<?php
require '../includes/config.php';

// Verifica si se recibió el ID de la mascota
if (!isset($_POST['id_mascota']) || empty($_POST['id_mascota'])) {
    echo json_encode(['success' => false, 'message' => 'ID de mascota no proporcionado']);
    exit;
}

$id_mascota = intval($_POST['id_mascota']);

// Verifica si la mascota existe
$check_sql = "SELECT id_mascota FROM mascotas WHERE id_mascota = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $id_mascota);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Mascota no encontrada']);
    $check_stmt->close();
    exit;
}
$check_stmt->close();

// Eliminar la mascota
$delete_sql = "DELETE FROM mascotas WHERE id_mascota = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $id_mascota);

if ($delete_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Mascota eliminada correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la mascota: ' . $delete_stmt->error]);
}

$delete_stmt->close();
$conn->close();
?>
