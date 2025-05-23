<?php
session_start();
require_once 'config.php';

// Verifica si el usuario está autenticado y tiene rol admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    echo "<p>No tiene permiso para acceder a esta sección.</p>";
    exit;
}

// Manejo de eliminación de dispositivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $id = intval($_POST['eliminar_id']);
    $stmt = $mysqli->prepare("DELETE FROM dispositivos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

// Configuración de paginación
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Obtener el total de dispositivos
$total_resultado = $mysqli->query("SELECT COUNT(*) as total FROM dispositivos");
$total_filas = $total_resultado->fetch_assoc()['total'];
$total_paginas = ceil($total_filas / $por_pagina);

// Obtener dispositivos
$sql = "SELECT id, nombre, ubicacion, direccion_ip, direccion_mac FROM dispositivos LIMIT ?, ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $inicio, $por_pagina);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<style>
    .dispositivos-container {
        padding: 1rem;
        background-color: var(--blanco-calido);
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .dispositivos-container h1 {
        color: var(--verde-azulado);
        margin-bottom: 1.5rem;
        font-size: 1.8rem;
        border-bottom: 2px solid var(--azul-celeste);
        padding-bottom: 0.5rem;
    }

    .dispositivos-tabla {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 1.5rem;
        border: 1px solid var(--azul-celeste);
        border-radius: 6px;
        overflow: hidden;
    }

    .dispositivos-tabla thead {
        background-color: var(--verde-apagado);
        color: var(--blanco-calido);
    }

    .dispositivos-tabla th {
        text-align: left;
        padding: 12px 15px;
        font-weight: 600;
    }

    .dispositivos-tabla tbody tr:nth-child(odd) {
        background-color: var(--amarillo-claro);
    }
    
    .dispositivos-tabla tbody tr:nth-child(even) {
        background-color: var(--beige-claro);
    }

    .dispositivos-tabla td {
        padding: 10px 15px;
        border-top: 1px solid var(--azul-celeste);
        vertical-align: middle;
    }
    
    .dispositivos-tabla tr:hover {
        background-color: #e0f2f3;
    }

    .acciones-columna {
        text-align: center;
        white-space: nowrap;
    }

    .btn-accion {
        display: inline-block;
        padding: 5px 10px;
        margin: 0 3px;
        border: none;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-eliminar {
        background-color: #d9534f;
        color: white;
    }

    .btn-eliminar:hover {
        background-color: #c9302c;
    }

    .btn-monitorizar {
        background-color: var(--azul-verdoso);
        color: white;
    }

    .btn-monitorizar:hover {
        background-color: var(--verde-azulado);
    }

    .sin-resultados {
        text-align: center;
        padding: 2rem;
        color: var(--verde-azulado);
        font-style: italic;
    }

    .paginacion {
        display: flex;
        justify-content: center;
        margin-top: 1.5rem;
    }

    .paginacion li {
        margin: 0 3px;
    }

    .paginacion a {
        display: inline-block;
        padding: 5px 12px;
        background-color: var(--blanco-calido);
        border: 1px solid var(--azul-celeste);
        border-radius: 4px;
        color: var(--verde-azulado);
        text-decoration: none;
    }

    .paginacion a.activa,
    .paginacion a:hover {
        background-color: var(--verde-azulado);
        color: var(--blanco-calido);
    }
</style>

<div class="dispositivos-container">
    <h1>Gestión de Dispositivos</h1>

    <div class="section" role="region" aria-labelledby="tabla-dispositivos">
        <h2 id="tabla-dispositivos" class="sr-only">Lista de dispositivos</h2>
        
        <?php if ($resultado->num_rows > 0): ?>
            <table class="dispositivos-tabla">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Ubicación</th>
                        <th scope="col">Dirección IP</th>
                        <th scope="col">Dirección MAC</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nombre']) ?></td>
                            <td><?= htmlspecialchars($row['ubicacion']) ?></td>
                            <td><?= htmlspecialchars($row['direccion_ip']) ?></td>
                            <td><?= htmlspecialchars($row['direccion_mac']) ?></td>
                            <td class="acciones-columna">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="eliminar_id" value="<?= $row['id'] ?>">
                                    <button class="btn-accion btn-eliminar" type="submit" 
                                            onclick="return confirm('¿Estás seguro de que deseas eliminar este dispositivo?')">
                                        Eliminar
                                    </button>
                                </form>
                                <form method="GET" action="monitor.php" style="display: inline;">
                                    <input type="hidden" name="sensor_id" value="<?= htmlspecialchars($row['nombre']) ?>">
                                    <button class="btn-accion btn-monitorizar" type="submit">Monitorizar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="sin-resultados">No se encontraron dispositivos registrados.</p>
        <?php endif; ?>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de dispositivos">
                <ul class="paginacion">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li>
                            <a href="?pagina=<?= $i ?>" class="<?= $i === $pagina_actual ? 'activa' : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
$mysqli->close();
?>
