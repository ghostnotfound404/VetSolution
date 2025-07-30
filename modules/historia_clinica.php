<?php
include('../includes/config.php');

// Obtener las mascotas
$sql = "SELECT * FROM mascotas";
$result = $conn->query($sql);
?>

<div class="container historia-clinica">
    <h2>Historia Clínica</h2>
    <form action="historia_clinica.php" method="POST">
        <div class="mb-3">
            <label for="mascota" class="form-label">Mascota</label>
            <select class="form-control" id="mascota" name="id_mascota" required>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <option value="<?php echo $row['id_mascota']; ?>"><?php echo $row['nombre']; ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="motivo" class="form-label">Motivo de Consulta</label>
            <textarea class="form-control" id="motivo" name="motivo" required></textarea>
        </div>
        <div class="mb-3">
            <label for="anamnesis" class="form-label">Anamnesis</label>
            <textarea class="form-control" id="anamnesis" name="anamnesis"></textarea>
        </div>
        <div class="mb-3">
            <label for="peso" class="form-label">Peso (kg)</label>
            <input type="number" class="form-control" id="peso" name="peso" step="0.01">
        </div>
        <div class="mb-3">
            <label for="temperatura" class="form-label">Temperatura (°C)</label>
            <input type="number" class="form-control" id="temperatura" name="temperatura" step="0.1" required>
        </div>
        <div class="mb-3">
            <label for="diagnostico" class="form-label">Diagnóstico</label>
            <textarea class="form-control" id="diagnostico" name="diagnostico" required></textarea>
        </div>
        <div class="mb-3">
            <label for="observaciones" class="form-label">Observaciones</label>
            <textarea class="form-control" id="observaciones" name="observaciones"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Registrar Historia Clínica</button>
    </form>
</div>

<!-- JS específico para Historia Clínica -->
<script src="assets/js/historia_clinica.js"></script>
