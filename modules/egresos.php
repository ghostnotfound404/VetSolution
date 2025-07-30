<?php
include('../includes/config.php');

// Obtener todos los egresos
$sql = "SELECT * FROM egresos";
$result = $conn->query($sql);
?>

<div class="container egresos">
    <h2>Registrar Egreso</h2>
    <form action="egresos.php" method="POST">
        <div class="mb-3">
            <label for="motivo" class="form-label">Motivo</label>
            <input type="text" class="form-control" id="motivo" name="motivo" required>
        </div>
        <div class="mb-3">
            <label for="monto" class="form-label">Monto</label>
            <input type="number" class="form-control" id="monto" name="monto" step="0.01" required>
        </div>
        <div class="mb-3">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="date" class="form-control" id="fecha" name="fecha" required>
        </div>
        <button type="submit" class="btn btn-primary">Registrar Egreso</button>
    </form>

    <h3 class="mt-5">Egresos Registrados</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Motivo</th>
                <th>Monto</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['motivo']; ?></td>
                <td><?php echo $row['monto']; ?></td>
                <td><?php echo $row['fecha']; ?></td>
                <td>
                    <a href="editar_egreso.php?id=<?php echo $row['id_egreso']; ?>" class="btn btn-warning">Editar</a>
                    <a href="eliminar_egreso.php?id=<?php echo $row['id_egreso']; ?>" class="btn btn-danger">Eliminar</a>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- JS específico para Egresos -->
<script src="assets/js/egresos.js"></script>
