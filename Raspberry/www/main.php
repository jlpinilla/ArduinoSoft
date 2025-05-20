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

// Usuario conectado
$usuario = htmlspecialchars($_SESSION['usuario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>ArduinoSoft - Control Medioambiental de Aulas Escolares</title>
  <link rel="stylesheet" href="styles.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <style>
  /* Layout básico: sidebar + contenido */
  body {
    display: flex;
    min-height: 100vh;
    margin: 0;
  }
  header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: #1f2a36;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    box-sizing: border-box;
    z-index: 1000;
  }
  header h1 {
    font-size: 1.2rem;
    margin: 0;
  }
  header .user-info {
    font-size: 0.9rem;
  }
  aside {
    width: 200px;
    background: #0275d8;
    padding-top: 80px; /* dejar espacio para header */
    box-sizing: border-box;
  }
  aside .nav-btn {
    display: block;
    width: 160px;
    margin: 10px auto;
    padding: 10px;
    background: #1f2a36;
    color: #fff;
    text-align: center;
    text-decoration: none;
    border-radius: 4px;
    transition: background 0.3s;
    cursor: pointer;
  }
  aside .nav-btn:hover {
    background: #35414a;
  }
  .logout-btn {
    background: #fff;
    color: #1f2a36;
    margin-top: 30px;
  }
  .logout-btn:hover {
    background: #e1e5e8;
  }
  main {
    flex: 1;
    padding: 80px 20px 20px; /* espacio para header y algo de margen */
    box-sizing: border-box;
  }
  .center-image {
    display: block;
    margin: 0 auto;
    max-width: 100%;
    height: auto;
    max-height: 400px;
  }
  #content-area {
    width: 100%;
    min-height: 400px;
  }
  .welcome-message {
    text-align: center;
    margin-top: 50px;
    font-size: 1.2rem;
    color: #555;
  }
  </style>
  <script>
  $(document).ready(function() {
    // Función para cargar contenido
    function loadContent(url) {
      $("#content-area").html('<div style="text-align:center;margin-top:20px;">Cargando...</div>');
      $.ajax({
        url: url,
        success: function(data) {
          $("#content-area").html(data);
        },
        error: function() {
          $("#content-area").html('<div style="color:red;text-align:center;margin-top:20px;">Error al cargar el contenido</div>');
        }
      });
    }
    
    // Manejadores para los botones de navegación
    $(".nav-link").click(function(e) {
      e.preventDefault();
      var url = $(this).data("url");
      loadContent(url);
      // Actualizar URL sin recargar la página
      history.pushState(null, null, '#' + url);
    });
    
    // Cargar página según hash en URL al iniciar
    if(window.location.hash) {
      loadContent(window.location.hash.substring(1));
    }
    
    // Cargar sensores.php automáticamente al hacer clic en el primer botón
    $("#btn-sensores").click();
  });
  </script>
</head>
<body>

  <header>
    <h1>ArduinoSoft - Control Medioambiental de Aulas Escolares</h1>
    <div class="user-info">Usuario: <?= $usuario ?></div>
  </header>

  <aside>
    <a id="btn-sensores" class="nav-btn nav-link" data-url="sensores.php">Gestionar dispositivos</a>
    <a class="nav-btn nav-link" data-url="users.php">Gestionar usuarios</a>
    <a class="nav-btn nav-link" data-url="public.php">Monitor</a>
    <a href="logout.php" class="nav-btn logout-btn">Salir</a>
  </aside>

  <main>
    <div id="content-area">
      <div class="welcome-message">
        <h2>Bienvenido al panel de control</h2>
        <p>Seleccione una opción del menú para comenzar</p>
      </div>
      <img src="img/arduino-sensor.png" 
           alt="Control Medioambiental" 
           class="center-image">
    </div>
  </main>

</body>
</html>
