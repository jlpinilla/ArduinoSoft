<?php
session_start();
require_once 'config.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['username'])) {
    echo "<p>No tiene permiso para acceder a esta sección.</p>";
    exit;
}

// Obtener la hora actual y la hora hace 1 hora (para comparación)
$fecha_actual = date('Y-m-d H:i:s');
$una_hora_atras = date('Y-m-d H:i:s', strtotime('-1 hour'));

// CORRECCIÓN: Consulta para obtener todos los sensores activos con registros en la última hora
$sql_sensores = "SELECT DISTINCT sensor_id 
                FROM registros 
                WHERE fecha_hora BETWEEN ? AND ?
                ORDER BY sensor_id";

// Consulta para obtener el último registro de cada sensor_id
$query = "SELECT r.*
          FROM registros r
          INNER JOIN (
              SELECT sensor_id, MAX(fecha_hora) as ultima_fecha
              FROM registros
              GROUP BY sensor_id
          ) as ultimos
          ON r.sensor_id = ultimos.sensor_id AND r.fecha_hora = ultimos.ultima_fecha
          ORDER BY r.fecha_hora DESC";

$resultado = $mysqli->query($query);

// Verificar si hay registros
if (!$resultado || $resultado->num_rows === 0) {
    $mensaje_error = "No se encontraron registros en la base de datos.";
}

// CORRECCIÓN: Verificar si realmente no hay sensores activos con registros
if ($total_sensores === 0) {
    // Consulta adicional para verificar si hay registros en la tabla
    $debug_query = "SELECT COUNT(*) as total, 
                    MIN(fecha_hora) as primera_fecha,
                    MAX(fecha_hora) as ultima_fecha 
                    FROM registros";
    $debug_result = $mysqli->query($debug_query);
    $debug_info = $debug_result->fetch_assoc();
    
    $mensaje_error = "No hay sensores con registros en la última hora.";
    $mensaje_debug = "<!-- Total registros: " . $debug_info['total'] . 
                    ", Primera fecha: " . $debug_info['primera_fecha'] . 
                    ", Última fecha: " . $debug_info['ultima_fecha'] . " -->";
    echo $mensaje_debug;
}
?>

<div class="users-container">
    <div class="dispositivos-container">
        <div class="monitor-header">
            <h1>Estado Actual de los Sensores</h1>
        </div>

        <?php if (isset($mensaje_error)): ?>
            <div class="mensaje-error">
                <?= htmlspecialchars($mensaje_error) ?>
            </div>
        <?php else: ?>
            <div class="public-info">
                <div class="info-header">
                    <div class="last-update">
                        <span class="update-label">Fecha y hora actual:</span>
                        <span class="update-time"><?= date('d/m/Y H:i:s') ?></span>
                    </div>
                    <div class="sensor-count">
                        <span class="count-label">Sensores monitorizados:</span>
                        <span class="count-number"><?= $resultado->num_rows ?></span>
                    </div>
                </div>
            </div>

            <table class="dispositivos-tabla">
                <thead>
                    <tr>
                        <th scope="col">Sensor</th>
                        <th scope="col">Temperatura</th>
                        <th scope="col">Humedad relativa</th>
                        <th scope="col">Ruido (dB)</th>
                        <th scope="col">Calidad del aire (ppm)</th>
                        <th scope="col">Lux</th>
                        <th scope="col">Fecha y Hora</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $resultado->fetch_assoc()): 
                        // Determinar si el registro es reciente (última hora)
                        $es_reciente = $row['fecha_hora'] >= $una_hora_atras;
                        $estado_class = $es_reciente ? 'estado-activo' : 'estado-inactivo';
                        $estado_texto = $es_reciente ? 'Activo' : 'Inactivo';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['sensor_id']) ?></td>
                            <td><?= number_format($row['temperatura'], 1) ?> °C</td>
                            <td><?= number_format($row['humedad'], 1) ?> %</td>
                            <td><?= number_format($row['ruido'], 1) ?> dB</td>
                            <td><?= number_format($row['co2'], 0) ?> ppm</td>
                            <td><?= number_format($row['lux'], 0) ?> lux</td>
                            <td><?= date('d/m/Y H:i:s', strtotime($row['fecha_hora'])) ?></td>
                            <td class="<?= $estado_class ?>"><?= $estado_texto ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="estado-leyenda">
                <div class="leyenda-item">
                    <span class="leyenda-color estado-activo-cuadro"></span>
                    <span>Activo: Última lectura dentro de la última hora</span>
                </div>
                <div class="leyenda-item">
                    <span class="leyenda-color estado-inactivo-cuadro"></span>
                    <span>Inactivo: Sin lecturas recientes</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Script para actualización automática -->
<script>
    // Recargar la página cada 60 segundos
    setTimeout(function() {
        window.location.href = window.location.href;
    }, 60000);
</script>

<?php
$mysqli->close();
?>
