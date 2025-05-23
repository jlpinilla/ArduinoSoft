<?php
require_once 'config.php';

// Obtener la hora actual y la hora hace 1 hora
$fecha_fin = date('Y-m-d H:i:s');
$fecha_inicio = date('Y-m-d H:i:s', strtotime('-1 hour'));

// Verificar la conexión a la base de datos
if ($mysqli->connect_error) {
    die("<p style='color:red;text-align:center;'>Error de conexión a la base de datos: " . $mysqli->connect_error . "</p>");
}

// CORRECCIÓN: Consulta explícitamente la columna sensor_id
// Calcular estadísticas globales de la última hora (medias de todos los sensores)
$query_stats = "SELECT 
                COUNT(DISTINCT sensor_id) as num_sensores,
                AVG(temperatura) as avg_temp, 
                AVG(humedad) as avg_hum, 
                AVG(ruido) as avg_ruido, 
                AVG(co2) as avg_co2, 
                AVG(lux) as avg_lux,
                MIN(temperatura) as min_temp, 
                MAX(temperatura) as max_temp,
                MIN(humedad) as min_hum, 
                MAX(humedad) as max_hum,
                MIN(ruido) as min_ruido, 
                MAX(ruido) as max_ruido,
                MIN(co2) as min_co2, 
                MAX(co2) as max_co2,
                MIN(lux) as min_lux, 
                MAX(lux) as max_lux,
                MAX(fecha_hora) as ultima_lectura
            FROM registros 
            WHERE fecha_hora BETWEEN ? AND ?";

$stmt = $mysqli->prepare($query_stats);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Verificar si hay datos en la última hora
$hay_datos = $stats['num_sensores'] > 0;

// CORRECCIÓN: Hacer debug para verificar la cantidad de sensores y diagnosticar el problema
echo "<!-- Debug: Consulta para periodo " . $fecha_inicio . " a " . $fecha_fin . " -->";

// CORRECCIÓN: Si no hay datos, verificar directamente en la tabla registros
if (!$hay_datos) {
    // Consulta para verificar si hay registros en la tabla
    $debug_query = "SELECT COUNT(*) as total_registros FROM registros";
    $debug_result = $mysqli->query($debug_query);
    $debug_info = $debug_result->fetch_assoc();
    echo "<!-- Debug: Total registros en la tabla: " . $debug_info['total_registros'] . " -->";
    
    // Verificar registros recientes
    $debug_recent = $mysqli->prepare("SELECT COUNT(*) as recientes FROM registros WHERE fecha_hora > ?");
    $one_day_ago = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $debug_recent->bind_param("s", $one_day_ago);
    $debug_recent->execute();
    $debug_recent_result = $debug_recent->get_result();
    $debug_recent_info = $debug_recent_result->fetch_assoc();
    echo "<!-- Debug: Registros en últimas 24h: " . $debug_recent_info['recientes'] . " -->";
}

// Obtener la última hora registrada
$ultima_actualizacion = $stats['ultima_lectura'] ? date('d/m/Y H:i:s', strtotime($stats['ultima_lectura'])) : 'Sin lecturas recientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60"> <!-- Actualizar la página cada minuto -->
    <meta name="description" content="Monitor ambiental en tiempo real. Muestra datos de temperatura, humedad, ruido, calidad del aire e iluminación.">
    <title>Panel Ambiental - Datos en Tiempo Real</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Estilos para visualización en televisor horizontal */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: var(--beige-claro);
            font-size: 16px;
        }
        
        .tv-panel-container {
            width: 100vw;
            height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            padding: 1.5vh 2vw;
            box-sizing: border-box;
            background-color: var(--blanco-calido);
        }
        
        .tv-header {
            text-align: center;
            border-bottom: 4px solid var(--azul-celeste);
            padding-bottom: 0.5vh;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tv-title-container {
            flex-grow: 1;
        }
        
        .tv-header h1 {
            color: var(--verde-azulado);
            font-size: 3.2vw;
            margin: 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        .tv-header .date-time {
            color: var(--verde-apagado);
            font-size: 2vw;
            margin-top: 0.5vh;
        }
        
        .tv-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5vh 1vw;
            background-color: var(--azul-celeste);
            border-radius: 8px;
            margin-left: 2vw;
        }
        
        .tv-info-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 1vw;
        }
        
        .tv-info-label {
            font-size: 1.2vw;
            color: var(--verde-azulado);
            font-weight: bold;
        }
        
        .tv-info-value {
            font-size: 1.5vw;
            background-color: var(--blanco-calido);
            padding: 0.5vh 1vw;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 0.3vh;
        }
        
        .tv-stats-container {
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            height: 100%;
            gap: 2vw;
            padding: 2vh 0;
        }
        
        .tv-stat-card {
            flex: 1;
            background-color: var(--beige-claro);
            border: 0.5vh solid var(--azul-verdoso);
            border-radius: 2vh;
            padding: 2vh 1.5vw;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-shadow: 0 0.5vh 1vh rgba(0,0,0,0.15);
        }
        
        .tv-stat-title {
            color: var(--verde-azulado);
            font-size: 2.5vw;
            margin-bottom: 2vh;
            font-weight: bold;
        }
        
        .tv-stat-value {
            font-size: 5vw;
            font-weight: bold;
            line-height: 1;
            margin: 1vh 0 3vh;
        }
        
        .tv-stat-range {
            width: 100%;
            display: flex;
            justify-content: space-between;
            font-size: 1.5vw;
            color: var (--verde-azulado);
            margin-top: auto;
        }
        
        .tv-stat-min, .tv-stat-max {
            background-color: rgba(255,255,255,0.7);
            padding: 0.8vh 1vw;
            border-radius: 1vh;
        }
        
        .tv-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1vh;
            border-top: 2px solid var(--azul-celeste);
        }
        
        .tv-footer-info {
            font-size: 1.5vw;
            color: var(--verde-azulado);
        }
        
        .tv-footer-info strong {
            color: var(--verde-apagado);
            font-weight: bold;
        }
        
        .tv-countdown {
            color: var(--verde-azulado);
            font-size: 1.3vw;
            background-color: var(--azul-celeste);
            padding: 0.5vh 1vw;
            border-radius: 1vh;
        }
        
        /* Color codificación para los valores */
        .valor-normal { color: #155724 !important; }
        .valor-atencion { color: #856404 !important; }
        .valor-alerta { color: #721c24 !important; }
        
        /* Mensaje de no datos */
        .tv-no-data {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-size: 5vw;
            color: #721c24;
            background-color: #f8d7da;
            border-radius: 2vh;
            padding: 4vh 2vw;
        }
        
        .tv-no-data p {
            margin: 2vh 0;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="tv-panel-container" role="main">
    <header class="tv-header">
        <div class="tv-title-container">
            <h1>MONITOR AMBIENTAL EN TIEMPO REAL</h1>
            <div class="date-time" id="current-date-time"></div>
        </div>
        <div class="tv-info">
            <div class="tv-info-item">
                <div class="tv-info-label">Sensores activos</div>
                <div class="tv-info-value"><?= $stats['num_sensores'] ?: '0' ?></div>
            </div>
            <div class="tv-info-item">
                <div class="tv-info-label">Última lectura</div>
                <div class="tv-info-value"><?= $stats['ultima_lectura'] ? date('H:i:s', strtotime($stats['ultima_lectura'])) : 'N/A' ?></div>
            </div>
        </div>
    </header>
    
    <?php if ($hay_datos): ?>
        <div class="tv-stats-container">
            <!-- Temperatura -->
            <div class="tv-stat-card" role="region" aria-label="Temperatura">
                <div class="tv-stat-title">Temperatura</div>
                <?php
                $temp_class = 'valor-normal';
                $temp_value = $stats['avg_temp'];
                if ($temp_value > 28) { $temp_class = 'valor-alerta'; }
                elseif ($temp_value > 26) { $temp_class = 'valor-atencion'; }
                elseif ($temp_value < 17) { $temp_class = 'valor-alerta'; }
                elseif ($temp_value < 19) { $temp_class = 'valor-atencion'; }
                ?>
                <div class="tv-stat-value <?= $temp_class ?>">
                    <?= number_format($temp_value, 1) ?> °C
                </div>
                <div class="tv-stat-range">
                    <span class="tv-stat-min">Min: <?= number_format($stats['min_temp'], 1) ?> °C</span>
                    <span class="tv-stat-max">Max: <?= number_format($stats['max_temp'], 1) ?> °C</span>
                </div>
            </div>
            
            <!-- Humedad -->
            <div class="tv-stat-card" role="region" aria-label="Humedad">
                <div class="tv-stat-title">Humedad</div>
                <?php
                $hum_class = 'valor-normal';
                $hum_value = $stats['avg_hum'];
                if ($hum_value > 70) { $hum_class = 'valor-alerta'; }
                elseif ($hum_value > 60) { $hum_class = 'valor-atencion'; }
                elseif ($hum_value < 30) { $hum_class = 'valor-alerta'; }
                elseif ($hum_value < 40) { $hum_class = 'valor-atencion'; }
                ?>
                <div class="tv-stat-value <?= $hum_class ?>">
                    <?= number_format($hum_value, 1) ?> %
                </div>
                <div class="tv-stat-range">
                    <span class="tv-stat-min">Min: <?= number_format($stats['min_hum'], 1) ?> %</span>
                    <span class="tv-stat-max">Max: <?= number_format($stats['max_hum'], 1) ?> %</span>
                </div>
            </div>
            
            <!-- Ruido -->
            <div class="tv-stat-card" role="region" aria-label="Ruido">
                <div class="tv-stat-title">Ruido</div>
                <?php
                $ruido_class = 'valor-normal';
                $ruido_value = $stats['avg_ruido'];
                if ($ruido_value > 70) { $ruido_class = 'valor-alerta'; }
                elseif ($ruido_value > 60) { $ruido_class = 'valor-atencion'; }
                ?>
                <div class="tv-stat-value <?= $ruido_class ?>">
                    <?= number_format($ruido_value, 0) ?> dB
                </div>
                <div class="tv-stat-range">
                    <span class="tv-stat-min">Min: <?= number_format($stats['min_ruido'], 0) ?> dB</span>
                    <span class="tv-stat-max">Max: <?= number_format($stats['max_ruido'], 0) ?> dB</span>
                </div>
            </div>
            
            <!-- CO2 -->
            <div class="tv-stat-card" role="region" aria-label="Calidad del aire">
                <div class="tv-stat-title">CO₂</div>
                <?php
                $co2_class = 'valor-normal';
                $co2_value = $stats['avg_co2'];
                if ($co2_value > 1000) { $co2_class = 'valor-alerta'; }
                elseif ($co2_value > 800) { $co2_class = 'valor-atencion'; }
                ?>
                <div class="tv-stat-value <?= $co2_class ?>">
                    <?= number_format($co2_value, 0) ?> ppm
                </div>
                <div class="tv-stat-range">
                    <span class="tv-stat-min">Min: <?= number_format($stats['min_co2'], 0) ?> ppm</span>
                    <span class="tv-stat-max">Max: <?= number_format($stats['max_co2'], 0) ?> ppm</span>
                </div>
            </div>
            
            <!-- Lux -->
            <div class="tv-stat-card" role="region" aria-label="Iluminación">
                <div class="tv-stat-title">Iluminación</div>
                <?php
                $lux_class = 'valor-normal';
                $lux_value = $stats['avg_lux'];
                if ($lux_value > 1000) { $lux_class = 'valor-atencion'; }
                elseif ($lux_value < 200) { $lux_class = 'valor-atencion'; }
                ?>
                <div class="tv-stat-value <?= $lux_class ?>">
                    <?= number_format($lux_value, 0) ?> lux
                </div>
                <div class="tv-stat-range">
                    <span class="tv-stat-min">Min: <?= number_format($stats['min_lux'], 0) ?> lux</span>
                    <span class="tv-stat-max">Max: <?= number_format($stats['max_lux'], 0) ?> lux</span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="tv-no-data" role="alert">
            <p>NO HAY DATOS DISPONIBLES</p>
            <p>Verifique la conexión de los sensores</p>
        </div>
    <?php endif; ?>
    
    <footer class="tv-footer">
        <div class="tv-footer-info">
            Valores promedio de la última hora
        </div>
        <div class="tv-countdown" aria-live="polite">
            Actualización en <span id="countdown">60</span> segundos
        </div>
    </footer>
</div>

<script>
// Actualización de fecha y hora en tiempo real
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    };
    document.getElementById('current-date-time').textContent = 
        now.toLocaleDateString('es-ES', options).replace(/^\w/, c => c.toUpperCase());
}

// Contador para actualización
function startCountdown() {
    let countdown = 60;
    const countdownElement = document.getElementById('countdown');
    
    updateDateTime(); // Actualizar fecha/hora inicial
    
    // Actualizar cada segundo
    setInterval(function() {
        // Actualizar contador
        countdown--;
        if (countdown < 0) {
            countdown = 60;
        }
        countdownElement.textContent = countdown;
        
        // Actualizar fecha y hora cada segundo
        updateDateTime();
    }, 1000);
}

// Iniciar cuando se cargue la página
document.addEventListener('DOMContentLoaded', startCountdown);
</script>

</body>
</html>

<?php
$mysqli->close();
?>
