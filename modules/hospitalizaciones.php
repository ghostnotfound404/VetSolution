<?php
include('../includes/config.php');

// Obtener las hospitalizaciones
$sql = "SELECT * FROM hospitalizaciones";
$result = $conn->query($sql);
?>

<div class="container hospitalizaciones">
    <h2>Registrar Hospitalización</h2>
    <form action="hospitalizaciones.php" method="POST">
        <div class="mb-3">
            <label for="mascota" class="form-label">Mascota</label>
            <select class="form-control" id="mascota" name="id_mascota" required>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <option value="<?php echo $row['id_mascota']; ?>"><?php echo $row['nombre']; ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="hora_inicio" class="form-label">Hora de Inicio</label>
            <input type="datetime-local" class="form-control" id="hora_inicio" name="hora_inicio" required>
        </div>
        <div class="mb-3">
            <label for="hora_termino" class="form-label">Hora de Término</label>
            <input type="datetime-local" class="form-control" id="hora_termino" name="hora_termino" required>
        </div>
        <button type="submit" class="btn btn-primary">Registrar Hospitalización</button>
    </form>
</div>

<!-- JS específico para Hospitalizaciones -->
<script src="assets/js/hospitalizaciones.js"></script>
