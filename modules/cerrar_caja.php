<?php
include('../includes/config.php');

header('Content-Type: application/json');

try {
    // Transacción para garantizar que no se pierdan datos si algo falla
    $conn->begin_transaction();
    
    // Eliminar todas las ventas
    $stmt = $conn->prepare("DELETE FROM ventas");
    $stmt->execute();
    $deleted_ventas = $stmt->affected_rows;
    
    // Eliminar todos los egresos
    $stmt = $conn->prepare("DELETE FROM egresos");
    $stmt->execute();
    $deleted_egresos = $stmt->affected_rows;
    
    // Confirmar los cambios
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Caja cerrada correctamente',
        'details' => [
            'ventas_eliminadas' => $deleted_ventas,
            'egresos_eliminados' => $deleted_egresos
        ]
    ]);
} catch (Exception $e) {
    // Revertir cambios si algo falla
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
