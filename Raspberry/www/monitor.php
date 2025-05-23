<?php
session_start();
require_once 'config.php';

// Verifica si el usuario está autenticado
if (!isset($_SESSION['username'])) {
    echo "<p>No tiene permiso para acceder a esta sección.</p>";
    exit;
}

// Obtener el ID del sensor seleccionado
$sensor_id = isset($_GET['sensor_id']) ? trim($_GET['sensor_id']) : '';

if (empty($sensor_id)) {
    echo "<p class='sin-resultados'>No se ha especificado un sensor para monitorizar.</p>";
    echo "<div class='form-buttons'><a href='javascript:history.back()' class='btn-accion btn-cancelar'>Volver</a></div>";
    exit;
}

// Obtener el nombre del sensor a partir del ID
$stmt_sensor = $mysqli->prepare("SELECT nombre FROM dispositivos WHERE nombre = ?");
$stmt_sensor->bind_param("s", $sensor_id); 
$stmt_sensor->execute();
$result_sensor = $stmt_sensor->get_result();

if ($result_sensor->num_rows === 0) {
    echo "<p class='sin-resultados'>El sensor especificado no existe.</p>";
    echo "<div class='form-buttons'><a href='javascript:history.back()' class='btn-accion btn-cancelar'>Volver</a></div>";
    exit;
}

// Nombre del sensor es el mismo que sensor_id en este caso
$nombre_sensor = $sensor_id;
$stmt_sensor->close();

// Verificar si hay registros para este sensor
$check_registros = $mysqli->prepare("SELECT COUNT(*) as count FROM registros WHERE sensor_id = ?");
$check_registros->bind_param("s", $sensor_id);
$check_registros->execute();
$registros_result = $check_registros->get_result();
$registros_count = $registros_result->fetch_assoc()['count'];
$check_registros->close();

if ($registros_count === 0) {
    echo "<p class='sin-resultados'>No hay registros para el sensor seleccionado.</p>";
    echo "<div class='form-buttons'><a href='javascript:history.back()' class='btn-accion btn-cancelar'>Volver</a></div>";
    exit;
}

// Configuración de paginación
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Configuración de filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-7 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// Obtener el total de registros con filtros
$query_count = "SELECT COUNT(*) as total FROM registros 
                WHERE sensor_id = ? 
                AND DATE(fecha_hora) BETWEEN ? AND ?";
$stmt_count = $mysqli->prepare($query_count);
$stmt_count->bind_param("sss", $sensor_id, $fecha_inicio, $fecha_fin);
$stmt_count->execute();
$total_resultado = $stmt_count->get_result();
$total_filas = $total_resultado->fetch_assoc()['total'];
$total_paginas = ceil($total_filas / $por_pagina);
$stmt_count->close();

// Obtener registros paginados y filtrados
$query_select = "SELECT id, sensor_id, temperatura, humedad, ruido, co2, lux, fecha_hora 
                FROM registros 
                WHERE sensor_id = ? 
                AND DATE(fecha_hora) BETWEEN ? AND ?
                ORDER BY fecha_hora DESC 
                LIMIT ?, ?";
$stmt = $mysqli->prepare($query_select);
$stmt->bind_param("sssii", $sensor_id, $fecha_inicio, $fecha_fin, $inicio, $por_pagina);
$stmt->execute();
$resultado = $stmt->get_result();

// Calcular estadísticas
$query_stats = "SELECT 
                    AVG(temperatura) as avg_temp, 
                    MAX(temperatura) as max_temp, 
                    MIN(temperatura) as min_temp,
                    AVG(humedad) as avg_hum, 
                    MAX(humedad) as max_hum, 
                    MIN(humedad) as min_hum,
                    AVG(ruido) as avg_ruido, 
                    MAX(ruido) as max_ruido, 
                    MIN(ruido) as min_ruido,
                    AVG(co2) as avg_co2, 
                    MAX(co2) as max_co2, 
                    MIN(co2) as min_co2,
                    AVG(lux) as avg_lux, 
                    MAX(lux) as max_lux, 
                    MIN(lux) as min_lux
                FROM registros 
                WHERE sensor_id = ? 
                AND DATE(fecha_hora) BETWEEN ? AND ?";
$stmt_stats = $mysqli->prepare($query_stats);
$stmt_stats->bind_param("sss", $sensor_id, $fecha_inicio, $fecha_fin);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

// Añadir encabezado HTML con enlace a styles.css
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor - <?= htmlspecialchars($nombre_sensor) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="users-container">
    <div class="dispositivos-container">
        <div class="monitor-header">
            <h1>Monitorización del Sensor: <?= htmlspecialchars($nombre_sensor) ?></h1>
            <a href="main.php?page=sensores" class="btn-accion btn-volver">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Volver a Dispositivos
            </a>
        </div>

        <!-- Filtros de fecha -->
        <div class="busqueda-container">
            <h2>Filtrar por fecha</h2>
            <form method="GET" action="main.php" class="form-busqueda">
                <input type="hidden" name="page" value="monitor">
                <input type="hidden" name="sensor_id" value="<?= htmlspecialchars($sensor_id) ?>">
                <div class="busqueda-flex">
                    <div class="form-group busqueda-input">
                        <label for="fecha_inicio">Desde:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    
                    <div class="form-group busqueda-input">
                        <label for="fecha_fin">Hasta:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    
                    <div class="form-group busqueda-boton">
                        <label class="invisible">Filtrar</label>
                        <button type="submit" class="btn-accion btn-buscar">Filtrar</button>
                    </div>
                    
                    <div class="form-group busqueda-boton">
                        <label class="invisible">Actualizar</label>
                        <a href="main.php?page=monitor&sensor_id=<?= urlencode($sensor_id) ?>" class="btn-accion btn-limpiar">Reiniciar filtros</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Resumen estadístico -->
        <div class="stats-container">
            <h2>Resumen estadístico</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Temperatura</h3>
                    <div class="stat-value"><?= number_format($stats['avg_temp'], 1) ?> °C</div>
                    <div class="stat-range">Min: <?= number_format($stats['min_temp'], 1) ?> °C · Max: <?= number_format($stats['max_temp'], 1) ?> °C</div>
                </div>
                
                <div class="stat-card">
                    <h3>Humedad</h3>
                    <div class="stat-value"><?= number_format($stats['avg_hum'], 1) ?> %</div>
                    <div class="stat-range">Min: <?= number_format($stats['min_hum'], 1) ?> % · Max: <?= number_format($stats['max_hum'], 1) ?> %</div>
                </div>
                
                <div class="stat-card">
                    <h3>Ruido</h3>
                    <div class="stat-value"><?= number_format($stats['avg_ruido'], 1) ?> dB</div>
                    <div class="stat-range">Min: <?= number_format($stats['min_ruido'], 1) ?> dB · Max: <?= number_format($stats['max_ruido'], 1) ?> dB</div>
                </div>
                
                <div class="stat-card">
                    <h3>CO₂</h3>
                    <div class="stat-value"><?= number_format($stats['avg_co2'], 0) ?> ppm</div>
                    <div class="stat-range">Min: <?= number_format($stats['min_co2'], 0) ?> ppm · Max: <?= number_format($stats['max_co2'], 0) ?> ppm</div>
                </div>
                
                <div class="stat-card">
                    <h3>Iluminación</h3>
                    <div class="stat-value"><?= number_format($stats['avg_lux'], 0) ?> lux</div>
                    <div class="stat-range">Min: <?= number_format($stats['min_lux'], 0) ?> lux · Max: <?= number_format($stats['max_lux'], 0) ?> lux</div>
                </div>
            </div>
        </div>

        <!-- Lista de registros -->
        <div class="section" role="region" aria-labelledby="tabla-registros">
            <div class="action-bar">
                <div class="monitor-section-header">
                    <h2>Registros del sensor</h2>
                </div>
                <p class="resultados-count">Mostrando <?= $resultado->num_rows ?> de <?= $total_filas ?> registros</p>
            </div>
            
            <?php if ($resultado->num_rows > 0): ?>
                <table class="dispositivos-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Sensor</th>
                            <th scope="col">Temperatura</th>
                            <th scope="col">Humedad relativa</th>
                            <th scope="col">Ruido (dB)</th>
                            <th scope="col">Calidad del aire (ppm)</th>
                            <th scope="col">Lux</th>
                            <th scope="col">Fecha y Hora del registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($nombre_sensor) ?></td>
                                <td><?= number_format($row['temperatura'], 1) ?> °C</td>
                                <td><?= number_format($row['humedad'], 1) ?> %</td>
                                <td><?= number_format($row['ruido'], 1) ?> dB</td>
                                <td><?= number_format($row['co2'], 0) ?> ppm</td>
                                <td><?= number_format($row['lux'], 0) ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($row['fecha_hora'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="sin-resultados">No se encontraron registros para este sensor en el período seleccionado.</p>
            <?php endif; ?>
    
            <!-- Paginación con parámetros de búsqueda -->
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación de registros">
                    <ul class="paginacion">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li>
                                <a href="main.php?page=monitor&sensor_id=<?= urlencode($sensor_id) ?>&pagina=<?= $i ?>&fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>" 
                                   class="<?= $i === $pagina_actual ? 'activa' : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>

<?php
$stmt->close();
$mysqli->close();
?>
