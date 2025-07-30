<?php
include('../includes/config.php');

// Obtener clientes y productos
$clientes_sql = "SELECT * FROM clientes";
$productos_sql = "SELECT * FROM productos";
$clientes_result = $conn->query($clientes_sql);
$productos_result = $conn->query($productos_sql);
?>

<div class="container ventas">
    <h2>Registrar Venta</h2>
    <form action="ventas.php" method="POST">
        <div class="mb-3">
            <label for="cliente" class="form-label">Cliente</label>
            <select class="form-control" id="cliente" name="id_cliente" required>
                <?php while ($row = $clientes_result->fetch_assoc()) { ?>
                    <option value="<?php echo $row['id_cliente']; ?>"><?php echo $row['nombre'] . ' ' . $row['apellido']; ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="producto" class="form-label">Producto</label>
            <select class="form-control" id="producto" name="id_producto" required>
                <?php while ($row = $productos_result->fetch_assoc()) { ?>
                    <option value="<?php echo $row['id_producto']; ?>"><?php echo $row['nombre']; ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="cantidad" class="form-label">Cantidad</label>
            <input type="number" class="form-control" id="cantidad" name="cantidad" required>
        </div>
        <button type="submit" class="btn btn-primary">Registrar Venta</button>
    </form>
</div>

<!-- JS específico para Ventas -->
<script src="assets/js/ventas.js"></script>
