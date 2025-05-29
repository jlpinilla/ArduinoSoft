<?php
// main.php
session_start();

// Solo administradores pueden acceder
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: index.php');
  exit;
}

// Usuario conectado - corregir la variable de sesión
$usuario = htmlspecialchars($_SESSION['username']); // Cambiado de 'usuario' a 'username'

// Manejar la carga directa de páginas específicas
$page = isset($_GET['page']) ? $_GET['page'] : '';
$content = '';

// Si se solicita monitor.php directamente
if ($page === 'monitor' && isset($_GET['sensor_id'])) {
  // Capturar la salida de monitor.php
  ob_start();
  include('monitor.php');
  $content = ob_get_clean();
} 
// Si se solicita public.php directamente
else if ($page === 'public') {
  // Capturar la salida de public.php
  ob_start();
  include('public.php');
  $content = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>ArduinoSoft - Panel de Control</title>
  <link rel="stylesheet" href="styles.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <style>
  /* Estilos específicos para la página main que complementan styles.css */
  body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    margin: 0;
    align-items: stretch;
    justify-content: flex-start;
    background-color: var(--beige-claro);
  }
  
  header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: var(--verde-azulado);
    color: var(--blanco-calido);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    box-sizing: border-box;
    z-index: 1000;
  }
  
  header h1 {
    font-size: 1.5rem;
    margin: 0;
    flex-grow: 1;
    text-align: center;
  }
  
  header .user-info {
    font-size: 0.9rem;
  }
  
  .main-container {
    display: flex;
    flex: 1;
    margin-top: 60px; /* Espacio para el header fijo */
  }
  
  aside {
    width: 200px;
    background: var(--azul-verdoso);
    box-sizing: border-box;
    padding-top: 20px;
    align-self: flex-start;
    height: calc(100vh - 60px);
  }
  
  aside .nav-btn {
    display: block;
    width: 180px;
    margin: 10px 10px;
    padding: 10px;
    background: var(--verde-azulado);
    color: var(--blanco-calido);
    text-align: left;
    text-decoration: none;
    border-radius: 4px;
    transition: background 0.3s;
    cursor: pointer;
  }
  
  aside .nav-btn:hover {
    background: var(--verde-apagado);
  }
  
  .logout-btn {
    background: var(--mostaza) !important;
    color: var(--verde-azulado) !important;
    margin-top: 30px;
    font-weight: bold;
  }
  
  .logout-btn:hover {
    background: var(--amarillo-claro) !important;
  }
  
  main {
    flex: 1;
    padding: 20px;
    box-sizing: border-box;
    background-color: var(--blanco-calido);
    margin: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
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
    margin-top: 30px;
    margin-bottom: 30px;
    font-size: 1.2rem;
    color: var(--verde-azulado);
  }

  .welcome-message h2 {
    color: var(--verde-apagado);
    margin-bottom: 10px;
  }
  </style>
  <script>
  $(document).ready(function() {    // Función para cargar contenido
    function loadContent(url) {
      $("#content-area").html('<div style="text-align:center;margin-top:20px;">Cargando...</div>');
      $.ajax({
        url: url,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(data) {
          $("#content-area").html(data);
        },
        error: function(xhr, status, error) {
          console.error("Error AJAX: " + status + " - " + error);
          $("#content-area").html('<div style="color:var(--verde-azulado);text-align:center;margin-top:20px;">' + 
            'El módulo solicitado no está disponible en este momento.<br>' + 
            'Por favor, contacte con el administrador.</div>');
        }
      });
    }
    
    // Manejadores para los botones de navegación
    $(".nav-link").click(function(e) {
      e.preventDefault();
      var url = $(this).data("url");
      
      // Mostrar la página de bienvenida si se hace clic en un botón sin URL válida
      if (!url) {
        showWelcomePage();
        return;
      }
      
      // Verificar si el archivo existe antes de intentar cargarlo
      $.ajax({
        url: url,
        type: 'HEAD',
        error: function() {
          showWelcomePage();
          console.error("El archivo " + url + " no existe");
        },
        success: function() {
          loadContent(url);
          // Actualizar URL sin recargar la página
          history.pushState(null, null, '#' + url);
        }
      });
    });
    
    // Función para mostrar la página de bienvenida
    function showWelcomePage() {
      $("#content-area").html(`
        <div class="welcome-message">
          <h2>Bienvenido al panel de control</h2>
          <p>Seleccione una opción del menú para comenzar</p>
        </div>
        <img src="img/panel.png" 
             alt="Control Medioambiental" 
             class="center-image">
      `);
      history.pushState(null, null, '#');
    }
    
    // Inicialización: comprobar si hay un hash en la URL o si tenemos contenido predefinido
    if (<?= !empty($content) ? 'true' : 'false' ?>) {
      // No hacer nada, se mostrará el contenido precargado
    } else if(window.location.hash && window.location.hash != '#') {
      var url = window.location.hash.substring(1);
      // Comprobar si el archivo existe antes de cargarlo
      $.ajax({
        url: url,
        type: 'HEAD',
        error: function() {
          // En caso de error, mostrar la página de bienvenida
          showWelcomePage();
        },
        success: function() {
          loadContent(url);
        }
      });
    }
    // Si no hay hash o es solo '#', no hacer nada y mantener el contenido estático HTML
  });
  </script>
</head>
<body>

  <header>
    <h1>ArduinoSoft - Panel de Control</h1>
    <div class="user-info">Usuario: <?= $usuario ?></div>
  </header>

  <div class="main-container">
    <aside>      <a id="btn-sensores" class="nav-btn nav-link" data-url="sensores.php">Gestionar dispositivos</a>
      <a class="nav-btn nav-link" data-url="usuarios.php">Gestionar usuarios</a>
      <a class="nav-btn nav-link" data-url="public.php">Estado Sensores</a>
      <a href="logout.php" class="nav-btn logout-btn">Salir</a>
    </aside>

    <main>
      <div id="content-area">
        <?php if (!empty($content)): ?>
          <?= $content ?>
        <?php else: ?>
          <div class="welcome-message">
            <h2>Bienvenido al panel de control</h2>
            <p>Seleccione una opción del menú para comenzar</p>
          </div>
          <img src="img/panel.png" 
               alt="Control Medioambiental" 
               class="center-image">
        <?php endif; ?>
      </div>
    </main>
  </div>

</body>
</html>
