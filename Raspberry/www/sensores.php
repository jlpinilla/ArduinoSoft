<?php
// main.php
session_start();

// Solo administradores pueden acceder
if (!isset($_SESSION['role']) || (
  $_SESSION['role'] !== 'admin' &&
  $_SESSION['role'] !== 'administrador'
)) {
  header('Location: index.php');
  exit;
}

// Verificar conexión a base de datos
require_once 'config.php';


// Verificar que $mysqli está definido
if (!isset($mysqli)) {
    die("Error: La conexión a la base de datos no está disponible. Verifique el archivo config.php");
}

// Solo administradores pueden acceder
if (!isset($_SESSION['role']) || (
    $_SESSION['role'] !== 'admin' &&
    $_SESSION['role'] !== 'administrador'
)) {
    header('Location: index.php');
    exit;
}

// Procesar eliminación si se ha enviado el ID
if (isset($_POST['eliminar']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $mysqli->prepare("DELETE FROM dispositivos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    // Redireccionar para evitar reenvío del formulario
    header("Location: sensores.php");
    exit;
}

// Procesar exportación a CSV si se solicita
if (isset($_GET['exportar']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Obtener información del dispositivo
    $stmt = $mysqli->prepare("SELECT nombre FROM dispositivos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dispositivo = $result->fetch_assoc();
    
    // Consultar registros de las últimas 24 horas
    $stmt = $mysqli->prepare("SELECT * FROM lecturas WHERE dispositivo_id = ? AND fecha_registro >= NOW() - INTERVAL 24 HOUR");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Configurar cabeceras para descarga de CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=informe_' . $dispositivo['nombre'] . '_' . date('Y-m-d') . '.csv');
    
    // Crear archivo CSV
    $output = fopen('php://output', 'w');
    
    // Cabecera del CSV
    fputcsv($output, array('ID', 'Dispositivo ID', 'Temperatura', 'Humedad', 'CO2', 'Fecha Registro'));
    
    // Datos
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Contar total de dispositivos para la paginación
$result = $mysqli->query("SELECT COUNT(*) as total FROM dispositivos");
$row = $result->fetch_assoc();
$total_registros = $row['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consultar dispositivos con paginación
$stmt = $mysqli->prepare("SELECT * FROM dispositivos ORDER BY id LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $registros_por_pagina, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<!-- Rest of the HTML code remains the same -->
