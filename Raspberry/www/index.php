<?php
session_start();
require_once 'config.php';

// Habilitar errores (puedes desactivar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error = '';
$dbStatus = '';

// Verificar conexión
if ($mysqli->connect_error) {
    $dbStatus = "<div class='db-status error'>Error de conexión: " . $mysqli->connect_error . "</div>";
} else {
    $dbStatus = "<div class='db-status success'>Conexión a la base de datos establecida correctamente.</div>";
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Por favor, introduce usuario y contraseña.";
    } else {
        // Preparar consulta con manejo de errores
        $stmt = $mysqli->prepare("SELECT id, usuario, contrasena, rol FROM usuarios WHERE usuario = ?");
        if (!$stmt) {
            $error = "Error de preparación de consulta: " . $mysqli->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                // Verificar contraseña (hash o texto plano)
                if (password_verify($password, $user['contrasena']) || $password === $user['contrasena']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['usuario'];
                    $_SESSION['role'] = $user['rol'];

                    if ($user['rol'] === 'admin') {
                        header('Location: main.php');
                        exit();
                    } elseif ($user['rol'] === 'operador') {
                        header('Location: monitor.php');
                        exit();
                    } else {
                        $error = "Rol no válido.";
                    }
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "Usuario no encontrado.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login – ArduinoSoft Panel de Control</title>
  <!-- Enlazamos la hoja de estilos externa -->
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <main class="container" role="main" aria-labelledby="pageTitle">
  <h1 id="pageTitle">ArduinoSoft – Panel de Control</h1>
  <p class="db-message" aria-live="polite"><?php echo $dbStatus; ?></p>
  <?php if (!empty($error)): ?>
    <p class="error-message" aria-live="assertive"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <form method="post" novalidate>
    <div>
    <label for="username">Usuario</label>
    <input 
      type="text" 
      id="username" 
      name="username" 
      placeholder="Introduce tu usuario" 
      required 
      aria-required="true"
      value="<?php echo isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : ''; ?>"
    >
    </div>
    <div>
    <label for="password">Contraseña</label>
    <input 
      type="password" 
      id="password" 
      name="password" 
      placeholder="Introduce tu contraseña" 
      required 
      aria-required="true"
    >
    </div>
    <button type="submit" aria-label="Acceder al panel de control">Acceder</button>
  </form>
  </main>
</body>
</html>
