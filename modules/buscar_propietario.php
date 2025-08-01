<?php
include('../includes/config.php');

if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $busqueda = trim($_GET['q']);
    
    // Usar prepared statement para seguridad
    $sql = "SELECT id_cliente, nombre, apellido, dni, celular 
            FROM clientes 
            WHERE nombre LIKE ? 
               OR apellido LIKE ? 
               OR dni LIKE ? 
               OR CONCAT(nombre, ' ', apellido) LIKE ?
            ORDER BY nombre, apellido 
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $searchTerm = "%$busqueda%";
    $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<div class='list-group'>";
        while ($row = $result->fetch_assoc()) {
            $nombreCompleto = htmlspecialchars($row['nombre'] . ' ' . $row['apellido']);
            $dni = htmlspecialchars($row['dni']);
            $celular = htmlspecialchars($row['celular']);
            
            echo "<a href='#' class='list-group-item list-group-item-action' onclick='seleccionarPropietario({$row['id_cliente']}, \"$nombreCompleto\")'>
                    <div class='d-flex w-100 justify-content-between'>
                        <h6 class='mb-1'>$nombreCompleto</h6>
                        <small>DNI: $dni</small>
                    </div>
                    <p class='mb-1'>Celular: $celular</p>
                  </a>";
        }
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>No se encontraron propietarios con ese criterio de búsqueda.</div>";
    }
    
    $stmt->close();
} else {
    echo "<div class='alert alert-info'>Ingrese al menos 2 caracteres para buscar.</div>";
}
?>