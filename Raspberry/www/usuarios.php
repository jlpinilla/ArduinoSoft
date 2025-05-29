<?php
// usuarios.php - Gestión completa de usuarios sin JavaScript
session_start();
require_once 'config.php';

// Verificar si el usuario está autenticado y tiene rol admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    echo "<p>No tiene permiso para acceder a esta sección.</p>";
    exit;
}

// Ya no detectamos AJAX, siempre funcionamos con recarga de página completa

// Variables para capturar mensajes de éxito/error
$success_message = '';
$error_message = '';

// Manejo de acciones (eliminar, editar, crear usuario)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Eliminar usuario
    if (isset($_POST['eliminar_id'])) {
        $id = intval($_POST['eliminar_id']);
        
        try {
            // Obtener información del usuario antes de eliminarlo
            $stmt_info = $mysqli->prepare("SELECT usuario FROM usuarios WHERE id = ?");
            $stmt_info->bind_param("i", $id);
            $stmt_info->execute();
            $result_info = $stmt_info->get_result();
            $usuario_info = $result_info->fetch_assoc();
            $stmt_info->close();
            
            if ($usuario_info) {
                $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $affected_rows = $stmt->affected_rows;
                    $stmt->close();
                    
                    if ($affected_rows > 0) {
                        $success_message = "Usuario '{$usuario_info['usuario']}' eliminado exitosamente";
                    } else {
                        $error_message = "No se pudo eliminar el usuario";
                    }
                } else {
                    $error_message = "Error al ejecutar la consulta de eliminación";
                }
            } else {
                $error_message = "Usuario no encontrado";
            }
        } catch (Exception $e) {
            $error_message = "Error al eliminar usuario: " . $e->getMessage();
        }
    }
    
    // Actualizar usuario
    else if (isset($_POST['editar_id'])) {
        $id = intval($_POST['editar_id']);
        $usuario = trim($_POST['usuario']);
        $rol = $_POST['rol'];
        
        try {
            // Validar que el usuario no esté vacío
            if (empty($usuario)) {
                throw new Exception("El nombre de usuario no puede estar vacío");
            }
            
            // Verificar si el usuario ya existe (excluyendo el usuario actual)
            $stmt_check = $mysqli->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $stmt_check->bind_param("si", $usuario, $id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $stmt_check->close();
                throw new Exception("Ya existe un usuario con ese nombre");
            }
            $stmt_check->close();
            
            // Si la contraseña está vacía, no la actualizamos            if (empty($_POST['contrasena'])) {
                $stmt = $mysqli->prepare("UPDATE usuarios SET usuario = ?, rol = ? WHERE id = ?");
                $stmt->bind_param("ssi", $usuario, $rol, $id);
            } else {
                // Guardar la contraseña en texto plano (sin hash)
                $contrasena = $_POST['contrasena'];
                $stmt = $mysqli->prepare("UPDATE usuarios SET usuario = ?, contrasena = ?, rol = ? WHERE id = ?");
                $stmt->bind_param("sssi", $usuario, $contrasena, $rol, $id);
            }
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                if ($affected_rows > 0) {
                    $success_message = "Usuario '$usuario' actualizado exitosamente";
                } else {
                    $success_message = "Usuario '$usuario' actualizado (sin cambios)";
                }
            } else {
                throw new Exception("Error al ejecutar la actualización");
            }
        } catch (Exception $e) {
            $error_message = "Error al actualizar usuario: " . $e->getMessage();
        }
    }
    
    // Crear nuevo usuario
    else if (isset($_POST['nuevo_usuario'])) {
        // Verificar que todos los campos necesarios están presentes
        if (!empty($_POST['nuevo_usuario']) && !empty($_POST['nueva_contrasena']) && isset($_POST['nuevo_rol'])) {
            try {
                $usuario = trim($_POST['nuevo_usuario']);
                $contrasena = $_POST['nueva_contrasena'];
                $rol = $_POST['nuevo_rol'];
                
                // Validaciones adicionales
                if (strlen($usuario) < 3) {
                    throw new Exception("El nombre de usuario debe tener al menos 3 caracteres");
                }
                
                if (strlen($contrasena) < 6) {
                    throw new Exception("La contraseña debe tener al menos 6 caracteres");
                }
                
                // Verificar si el usuario ya existe
                $stmt_check = $mysqli->prepare("SELECT id FROM usuarios WHERE usuario = ?");
                $stmt_check->bind_param("s", $usuario);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->num_rows > 0) {
                    $stmt_check->close();
                    throw new Exception("Ya existe un usuario con ese nombre");
                }
                $stmt_check->close();
                  // Guardar la contraseña en texto plano (sin hash)
                $stmt = $mysqli->prepare("INSERT INTO usuarios (usuario, contrasena, rol) VALUES (?, ?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $mysqli->error);
                }
                
                $stmt->bind_param("sss", $usuario, $contrasena, $rol);
                
                if ($stmt->execute()) {
                    $nuevo_id = $mysqli->insert_id;
                    $stmt->close();
                    
                    $success_message = "Usuario '$usuario' creado exitosamente (ID: $nuevo_id)";
                } else {
                    throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
                }
            } catch (Exception $e) {
                $error_message = "Error al crear el usuario: " . $e->getMessage();
            }
        } else {
            $error_message = "Todos los campos son obligatorios";
        }
    }
}

// Parámetros de búsqueda
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_rol = isset($_GET['filtro_rol']) ? $_GET['filtro_rol'] : '';

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

if (!$stmt) {
    $error_message = "Error en la consulta de base de datos: " . $mysqli->error;
    $resultado = null;
} else {
    // Agregar los parámetros de paginación
    $params[] = &$inicio;
    $params[] = &$por_pagina;
    $types .= "ii";

    if (!empty($params)) {
        call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
    }

    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
    } else {
        $error_message = "Error al ejecutar la consulta: " . $stmt->error;
        $resultado = null;
    }
}

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

<!-- Estilos específicos para la integración con main.php -->
<style>
    .usuarios-container {
        width: 100%;
        padding: 20px;
    }
    
    .form-container {
        background-color: var(--beige-claro);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .busqueda-container {
        background-color: var(--beige-claro);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .busqueda-flex {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    
    .busqueda-input {
        flex: 2;
    }
    
    .busqueda-select {
        flex: 1;
    }
    
    .busqueda-boton {
        flex: 0 0 auto;
    }
    
    .invisible {
        visibility: hidden;
    }
    
    .form-buttons {
        margin-top: 20px;
        display: flex;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: var(--verde-apagado);
    }
    
    .form-group input, .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 16px;
    }
    
    .mensaje {
        padding: 10px 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .mensaje.success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
    
    .mensaje.error {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }
    
    .acciones-columna {
        display: flex;
        gap: 10px;
    }
    
    .sin-resultados {
        text-align: center;
        padding: 20px;
        color: var(--verde-apagado);
    }
    
    .resultados-count {
        color: var(--verde-apagado);
        margin: 0;
    }
</style>

<div class="usuarios-container">
        <h1>Gestión de Usuarios</h1>
        
        <?php if (!empty($success_message)): ?>
        <div class="mensaje success">
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="mensaje error">
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>
          <?php if ($editar_usuario): ?>
        <!-- Formulario para editar usuario -->
        <div class="form-container">            <h2>Editar Usuario</h2>
            <form method="POST" action="usuarios.php">
                <input type="hidden" name="editar_id" value="<?= $editar_usuario['id'] ?>">
                <input type="hidden" name="pagina" value="<?= $pagina_actual ?>">
                <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                <input type="hidden" name="filtro_rol" value="<?= htmlspecialchars($filtro_rol) ?>">
                
                <div class="form-group">
                    <label for="usuario">Usuario:</label>
                    <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($editar_usuario['usuario']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="contrasena">Contraseña (dejar en blanco para mantener):</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="••••••••">
                </div>
                
                <div class="form-group">
                    <label for="rol">Permisos:</label>
                    <select id="rol" name="rol" required>
                        <option value="admin" <?= $editar_usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="operador" <?= $editar_usuario['rol'] === 'operador' ? 'selected' : '' ?>>Operador</option>
                    </select>
                </div>
                  <div class="form-buttons">                    <button type="submit" class="btn-accion btn-guardar">Guardar Cambios</button>
                    <a href="usuarios.php?pagina=<?= $pagina_actual . $params_url ?>" class="btn-accion btn-cancelar">Cancelar</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        
        <!-- Formulario para nuevo usuario -->
        <div class="form-container">            <h2>Crear Nuevo Usuario</h2>
            <form method="POST" action="usuarios.php">
                <div class="form-group">
                    <label for="nuevo_usuario">Usuario:</label>
                    <input type="text" id="nuevo_usuario" name="nuevo_usuario" required>
                </div>
                
                <div class="form-group">
                    <label for="nueva_contrasena">Contraseña:</label>
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
                </div>
            </form>
        </div>
          <!-- Formulario de búsqueda -->
        <div class="busqueda-container">            <h2>Buscar Usuarios</h2>
            <form method="GET" action="usuarios.php">
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
                        <a href="usuarios.php" class="btn-accion btn-limpiar">Limpiar filtros</a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Lista de usuarios -->
        <div class="tabla-container">
            <h2>Usuarios Existentes <small>(<?= $total_filas ?> en total)</small></h2>
            
            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Permisos</th>
                            <th>Acciones</th>
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
                                </td>                                <td class="acciones-columna">                                    <a href="usuarios.php?editar=<?= $row['id'] ?>&pagina=<?= $pagina_actual . $params_url ?>" class="btn-accion btn-editar">Editar</a>
                                    <form method="POST" action="usuarios.php" style="display: inline;">
                                        <input type="hidden" name="eliminar_id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="pagina" value="<?= $pagina_actual ?>">
                                        <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                                        <input type="hidden" name="filtro_rol" value="<?= htmlspecialchars($filtro_rol) ?>">
                                        <button type="submit" class="btn-accion btn-eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este usuario?')">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                    <nav>
                        <ul class="paginacion">
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>                                <li>                                    <a href="usuarios.php?pagina=<?= $i ?>&busqueda=<?= htmlspecialchars($busqueda) ?>&filtro_rol=<?= htmlspecialchars($filtro_rol) ?>" 
                                       class="<?= $i === $pagina_actual ? 'activa' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            <?php elseif ($resultado): ?>
                <p class="sin-resultados">No se encontraron usuarios con los criterios de búsqueda.</p>
            <?php else: ?>
                <p class="sin-resultados">Error al cargar los usuarios. Por favor, inténtelo de nuevo.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>    </div>

<?php
// Cerrar los recursos de la base de datos
if (isset($stmt) && $stmt) {
    $stmt->close();
}
$mysqli->close();
?>

<!-- El código AJAX ha sido eliminado -->
            e.preventDefault();
            
            const formData = new FormData(this);
            const method = this.method.toUpperCase();
            const url = this.action;
            
            // Opciones para la solicitud fetch
            const options = {
                method: method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            // Para peticiones POST, adjuntar el formData
            if (method === 'POST') {
                options.body = formData;
            }
            
            // Para peticiones GET, añadir los parámetros a la URL
            let fetchUrl = url;
            if (method === 'GET') {
                const params = new URLSearchParams();
                for (const pair of formData.entries()) {
                    params.append(pair[0], pair[1]);
                }
                fetchUrl = `${url}?${params.toString()}`;
            }
              // Realizar la petición
            fetch(fetchUrl, options)
                .then(response => response.text())
                .then(html => {
                    // Obtener el contenedor principal donde se debe cargar el contenido
                    const contentArea = document.getElementById('content-area');
                    if (contentArea) {
                        contentArea.innerHTML = html;
                        // Volver a adjuntar los event listeners a los nuevos elementos
                        attachAjaxEventHandlers();
                    } else {
                        console.error("No se encontró el elemento con ID 'content-area'");
                        // Intentar buscar en el contexto de parent window por si estamos en un iframe
                        if (window.parent && window.parent.document) {
                            const parentContentArea = window.parent.document.getElementById('content-area');
                            if (parentContentArea) {
                                parentContentArea.innerHTML = html;
                                // Volver a adjuntar los event listeners a los nuevos elementos en la ventana padre
                                setTimeout(() => attachAjaxEventHandlers(), 100);
                            } else {
                                console.error("No se encontró el elemento con ID 'content-area' en la ventana padre");
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });
    });
    
    // Interceptar clics en enlaces
    document.querySelectorAll('a[data-ajax-link]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const url = this.href;
              fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.text())
                .then(html => {
                    // Obtener el contenedor principal donde se debe cargar el contenido
                    const contentArea = document.getElementById('content-area');
                    if (contentArea) {
                        contentArea.innerHTML = html;
                        // Volver a adjuntar los event listeners a los nuevos elementos
                        attachAjaxEventHandlers();
                    } else {
                        console.error("No se encontró el elemento con ID 'content-area'");
                        // Intentar buscar en el contexto de parent window por si estamos en un iframe
                        if (window.parent && window.parent.document) {
                            const parentContentArea = window.parent.document.getElementById('content-area');
                            if (parentContentArea) {
                                parentContentArea.innerHTML = html;
                                // Volver a adjuntar los event listeners a los nuevos elementos en la ventana padre
                                setTimeout(() => attachAjaxEventHandlers(), 100);
                            } else {
                                console.error("No se encontró el elemento con ID 'content-area' en la ventana padre");
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });
    });
}

// Ejecutar inmediatamente para asegurar que los handlers se adjuntan
// y también en DOMContentLoaded para asegurar compatibilidad
attachAjaxEventHandlers();

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar los event handlers
    attachAjaxEventHandlers();
});
</script>
<?php endif; ?>