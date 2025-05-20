<?php
// Start session
session_start();

// Include database configuration
require_once 'config.php';

$error = '';
$dbStatus = '';

// Check database connection
try {
    $testConn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $testConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbStatus = "<div class='db-status success'>Conexión a la base de datos establecida correctamente.</div>";
    $testConn = null;
} catch(PDOException $e) {
    $dbStatus = "<div class='db-status error'>Error de conexión a la base de datos: " . $e->getMessage() . "</div>";
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Por favor, introduce usuario y contraseña.";
    } else {
        try {
            // Connect to database using config.php credentials
            $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Prepare statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            // Check if user exists
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if (password_verify($password, $user['contrasena']) || $password === $user['contrasena']) { // Supporting both hashed and plain passwords
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['usuario'];
                    $_SESSION['role'] = $user['rol'];
                    
                    // Redirect based on role
                    if ($user['rol'] === 'admin') {
                        header('Location: main.php');
                        exit();
                    } else if ($user['rol'] === 'operador') {
                        header('Location: monitor.php');
                        exit();
                    } else {
                        // Default redirection if role is neither admin nor operador
                        header('Location: main.php');
                        exit();
                    }
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "Usuario no encontrado.";
            }
        } catch(PDOException $e) {
            $error = "Error de conexión: " . $e->getMessage();
        }
        
        $conn = null;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArduinoSoft - Login</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .db-status {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .db-status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .db-status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title1">ArduinoSoft - Control Medioambiental de Aulas Escolares</h1>
        
        <?php echo $dbStatus; ?>
        
        <div class="login-form">
            <h2>Iniciar Sesión</h2>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label for="username">Usuario:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Entrar</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>