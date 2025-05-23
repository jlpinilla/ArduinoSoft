<?php
session_start();
require_once 'config.php';

// Verifica si el usuario está autenticado y tiene rol admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    echo "<p>No tiene permiso para acceder a esta sección.</p>";
    exit;
}

// Variable para capturar mensajes de depuración
$debug_messages = [];

// Manejo de acciones (eliminar, editar, crear usuario)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Eliminar usuario
    if (isset($_POST['eliminar_id'])) {
        $id = intval($_POST['eliminar_id']);
        $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Parámetros de redirección después de eliminar
        $redirect = "?pagina=" . (isset($_POST['pagina']) ? $_POST['pagina'] : '1');
        if (!empty($_POST['busqueda'])) {
            $redirect .= "&busqueda=" . urlencode($_POST['busqueda']);
        }
        if (!empty($_POST['filtro_rol'])) {
            $redirect .= "&filtro_rol=" . urlencode($_POST['filtro_rol']);
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . $redirect);
        exit;
    }
    
    // Actualizar usuario
    else if (isset($_POST['editar_id'])) {
        $id = intval($_POST['editar_id']);
        $usuario = trim($_POST['usuario']);
        $rol = $_POST['rol'];
        
        // Si la contraseña está vacía, no la actualizamos
        if (empty($_POST['contrasena'])) {
            $stmt = $mysqli->prepare("UPDATE usuarios SET usuario = ?, rol = ? WHERE id = ?");
            $stmt->bind_param("ssi", $usuario, $rol, $id);
        } else {
            $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET usuario = ?, contrasena = ?, rol = ? WHERE id = ?");
            $stmt->bind_param("sssi", $usuario, $contrasena, $rol, $id);
        }
        
        $stmt->execute();
        $stmt->close();
        
        // Parámetros de redirección después de actualizar
        $redirect = "?pagina=" . (isset($_POST['pagina']) ? $_POST['pagina'] : '1');
        if (!empty($_POST['busqueda'])) {
            $redirect .= "&busqueda=" . urlencode($_POST['busqueda']);
        }
        if (!empty($_POST['filtro_rol'])) {
            $redirect .= "&filtro_rol=" . urlencode($_POST['filtro_rol']);
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . $redirect);
        exit;
    }
    
    // Crear nuevo usuario
    else if (isset($_POST['nuevo_usuario'])) {
        // Verificar que todos los campos necesarios están presentes
        if (!empty($_POST['nuevo_usuario']) && !empty($_POST['nueva_contrasena']) && isset($_POST['nuevo_rol'])) {
            try {
                $usuario = trim($_POST['nuevo_usuario']);
                $contrasena = password_hash($_POST['nueva_contrasena'], PASSWORD_DEFAULT);
                $rol = $_POST['nuevo_rol'];
                
                $debug_messages[] = "Intentando crear usuario: $usuario con rol: $rol";
                
                $stmt = $mysqli->prepare("INSERT INTO usuarios (usuario, contrasena, rol) VALUES (?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $mysqli->error);
                }
                
                $stmt->bind_param("sss", $usuario, $contrasena, $rol);
                
                if ($stmt->execute()) {
                    $nuevo_id = $mysqli->insert_id;
                    $stmt->close();
                    $success_message = "Usuario '$usuario' creado con éxito (ID: $nuevo_id)";
                    $debug_messages[] = $success_message;
                } else {
                    throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
                }
            } catch (Exception $e) {
                $error_message = "Error al crear el usuario: " . $e->getMessage();
                $debug_messages[] = $error_message;
            }
        } else {
            $error_message = "Todos los campos son obligatorios";
            $debug_messages[] = $error_message;
        }
    }
}

// Parámetros de búsqueda
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_rol = isset($_GET['filtro_rol']) ? $_GET['filtro_rol'] : '';
$success_message = isset($_GET['success']) ? $_GET['success'] : '';

// Configuración de paginación
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Preparar la consulta según los filtros
$where_clauses = [];
$params = [];
$types = "";

if (!empty($busqueda)) {
    $where_clauses[] = "usuario LIKE ?";
    $param_busqueda = "%" . $busqueda . "%";
    $params[] = &$param_busqueda;
    $types .= "s";
}

if (!empty($filtro_rol)) {
    $where_clauses[] = "rol = ?";
    $params[] = &$filtro_rol;
    $types .= "s";
}

// Construir la cláusula WHERE
$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Obtener total de usuarios con filtros
$query_count = "SELECT COUNT(*) as total FROM usuarios $where_sql";
$stmt_count = $mysqli->prepare($query_count);

if (!empty($types)) {
    call_user_func_array([$stmt_count, 'bind_param'], array_merge([$types], $params));
}

$stmt_count->execute();
$total_resultado = $stmt_count->get_result();
$total_filas = $total_resultado->fetch_assoc()['total'];
$total_paginas = ceil($total_filas / $por_pagina);
$stmt_count->close();

// Obtener usuarios paginados con filtros
$query_select = "SELECT id, usuario, rol FROM usuarios $where_sql LIMIT ?, ?";
$stmt = $mysqli->prepare($query_select);

// Agregar los parámetros de paginación
$params[] = &$inicio;
$params[] = &$por_pagina;
$types .= "ii";

if (!empty($params)) {
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
}

$stmt->execute();
$resultado = $stmt->get_result();

// Usuario a editar
$editar_usuario = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $editar_id = intval($_GET['editar']);
    $stmt = $mysqli->prepare("SELECT id, usuario, rol FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $editar_id);
    $stmt->execute();
    $editar_resultado = $stmt->get_result();
    if ($editar_resultado->num_rows > 0) {
        $editar_usuario = $editar_resultado->fetch_assoc();
    }
    $stmt->close();
}

// Conservar parámetros de búsqueda para volver al mismo estado después de editar
$params_url = '';
if (!empty($busqueda)) {
    $params_url .= '&busqueda=' . urlencode($busqueda);
}
if (!empty($filtro_rol)) {
    $params_url .= '&filtro_rol=' . urlencode($filtro_rol);
}
?>

<!-- Añadir estilos específicos para esta página -->
<style>
.users-container {
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    padding-right: 10px;
}

.debug-container {
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    margin-top: 15px;
    font-family: monospace;
    font-size: 0.9em;
    max-height: 150px;
    overflow-y: auto;
    display: none;
}

.debug-container.show {
    display: block;
}

.debug-title {
    font-weight: bold;
    margin-bottom: 5px;
    color: #555;
}

.debug-message {
    margin: 3px 0;
    padding: 3px;
    border-bottom: 1px solid #eee;
}

.refresh-btn {
    background-color: var(--azul-verdoso);
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 10px;
    display: inline-flex;
    align-items: center;
}

.refresh-btn:hover {
    background-color: var(--verde-azulado);
}

.refresh-btn svg {
    width: 16px;
    height: 16px;
    margin-right: 5px;
}

.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.users-section-header {
    display: flex;
    align-items: center;
}
</style>

<div class="users-container">
    <div class="dispositivos-container">
        <h1>Gestión de Usuarios</h1>
        
        <?php if (!empty($success_message)): ?>
        <div class="mensaje-exito">
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="mensaje-error">
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Formulario para editar usuario -->
        <?php if ($editar_usuario): ?>
        <div class="form-container">
            <h2>Editar Usuario</h2>
            <form method="POST" class="form-editar">
                <input type="hidden" name="editar_id" value="<?= $editar_usuario['id'] ?>">
                <input type="hidden" name="pagina" value="<?= $pagina_actual ?>">
                <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                <input type="hidden" name="filtro_rol" value="<?= htmlspecialchars($filtro_rol) ?>">
                
                <div class="form-group">
                    <label for="usuario">Usuario:</label>
                    <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($editar_usuario['usuario']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="contrasena">Password (dejar en blanco para mantener):</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="••••••••">
                </div>
                
                <div class="form-group">
                    <label for="rol">Permisos:</label>
                    <select id="rol" name="rol" required>
                        <option value="admin" <?= $editar_usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="operador" <?= $editar_usuario['rol'] === 'operador' ? 'selected' : '' ?>>Operador</option>
                    </select>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn-accion btn-guardar">Guardar Cambios</button>
                    <a href="?pagina=<?= $pagina_actual . $params_url ?>" class="btn-accion btn-cancelar">Cancelar</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        
        <!-- Formulario para nuevo usuario -->
        <div class="form-container">
            <h2>Crear Nuevo Usuario</h2>
            <form method="POST" class="form-crear" id="form-crear-usuario">
                <div class="form-group">
                    <label for="nuevo_usuario">Usuario:</label>
                    <input type="text" id="nuevo_usuario" name="nuevo_usuario" required>
                </div>
                
                <div class="form-group">
                    <label for="nueva_contrasena">Password:</label>
                    <input type="password" id="nueva_contrasena" name="nueva_contrasena" required>
                </div>
                
                <div class="form-group">
                    <label for="nuevo_rol">Permisos:</label>
                    <select id="nuevo_rol" name="nuevo_rol" required>
                        <option value="admin">Administrador</option>
                        <option value="operador" selected>Operador</option>
                    </select>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn-accion btn-crear">Crear Usuario</button>
                    <button type="button" class="btn-accion" id="toggle-debug">Mostrar Depuración</button>
                </div>
            </form>
            
            <!-- Area de depuración -->
            <div class="debug-container" id="debug-area">
                <div class="debug-title">Mensajes de depuración:</div>
                <?php if (!empty($debug_messages)): ?>
                    <?php foreach ($debug_messages as $msg): ?>
                        <div class="debug-message"><?= htmlspecialchars($msg) ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="debug-message">No hay mensajes de depuración.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Formulario de búsqueda -->
        <div class="busqueda-container">
            <h2>Buscar Usuarios</h2>
            <form method="GET" class="form-busqueda">
                <div class="busqueda-flex">
                    <div class="form-group busqueda-input">
                        <label for="busqueda">Buscar por nombre:</label>
                        <input type="text" id="busqueda" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    
                    <div class="form-group busqueda-select">
                        <label for="filtro_rol">Filtrar por permisos:</label>
                        <select id="filtro_rol" name="filtro_rol">
                            <option value="">Todos los roles</option>
                            <option value="admin" <?= $filtro_rol === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            <option value="operador" <?= $filtro_rol === 'operador' ? 'selected' : '' ?>>Operador</option>
                        </select>
                    </div>
                    
                    <div class="form-group busqueda-boton">
                        <label class="invisible">Buscar</label>
                        <button type="submit" class="btn-accion btn-buscar">Buscar</button>
                    </div>
                    
                    <?php if (!empty($busqueda) || !empty($filtro_rol)): ?>
                    <div class="form-group busqueda-boton">
                        <label class="invisible">Limpiar</label>
                        <a href="?pagina=1" class="btn-accion btn-limpiar">Limpiar filtros</a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Lista de usuarios -->
        <div class="section" role="region" aria-labelledby="tabla-usuarios" style="margin-top: 30px;">
            <div class="action-bar">
                <div class="users-section-header">
                    <h2>Usuarios Existentes</h2>
                    <a href="?<?= http_build_query(['pagina' => $pagina_actual, 'busqueda' => $busqueda, 'filtro_rol' => $filtro_rol]) ?>" class="refresh-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                        </svg>
                        Actualizar
                    </a>
                </div>
                <p class="resultados-count">Mostrando <?= $resultado->num_rows ?> de <?= $total_filas ?> usuarios</p>
            </div>
            
            <?php if ($resultado->num_rows > 0): ?>
                <table class="dispositivos-tabla">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Usuario</th>
                            <th scope="col">Permisos</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['usuario']) ?></td>
                                <td>
                                    <?php if ($row['rol'] === 'admin'): ?>
                                        Administrador
                                    <?php elseif ($row['rol'] === 'operador'): ?>
                                        Operador
                                    <?php else: ?>
                                        <?= htmlspecialchars($row['rol']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="acciones-columna">
                                    <a href="?editar=<?= $row['id'] ?>&pagina=<?= $pagina_actual . $params_url ?>" class="btn-accion btn-editar">Editar</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="eliminar_id" value="<?= $row['id'] ?>">
                                        <!-- Agregar parámetros de búsqueda para redirigir correctamente después de eliminar -->
                                        <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                                        <input type="hidden" name="filtro_rol" value="<?= htmlspecialchars($filtro_rol) ?>">
                                        <input type="hidden" name="pagina" value="<?= $pagina_actual ?>">
                                        <button class="btn-accion btn-eliminar" type="submit" 
                                                onclick="return confirm('¿Estás seguro de que deseas eliminar este usuario?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="sin-resultados">No se encontraron usuarios con los criterios de búsqueda.</p>
            <?php endif; ?>
    
            <!-- Paginación con parámetros de búsqueda -->
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación de usuarios">
                    <ul class="paginacion">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li>
                                <a href="?pagina=<?= $i ?>&busqueda=<?= htmlspecialchars($busqueda) ?>&filtro_rol=<?= htmlspecialchars($filtro_rol) ?>" 
                                   class="<?= $i === $pagina_actual ? 'activa' : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle para mostrar/ocultar área de depuración
    const toggleDebugBtn = document.getElementById('toggle-debug');
    const debugArea = document.getElementById('debug-area');
    
    if (toggleDebugBtn && debugArea) {
        toggleDebugBtn.addEventListener('click', function(e) {
            e.preventDefault();
            debugArea.classList.toggle('show');
            toggleDebugBtn.textContent = debugArea.classList.contains('show') ? 
                'Ocultar Depuración' : 'Mostrar Depuración';
        });
    }
    
    // Asegurar que el formulario de creación funcione correctamente
    const form = document.getElementById('form-crear-usuario');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validar que todos los campos estén completos
            const usuario = document.getElementById('nuevo_usuario').value.trim();
            const password = document.getElementById('nueva_contrasena').value;
            const rol = document.getElementById('nuevo_rol').value;
            
            if (!usuario || !password || !rol) {
                e.preventDefault();
                alert('Todos los campos son obligatorios');
                return false;
            }
            
            // Todo está bien, permitir el envío del formulario
            return true;
        });
    }
});
</script>

<?php
$stmt->close();
$mysqli->close();
?>
