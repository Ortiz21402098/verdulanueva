<!doctype html>
<html lang="es" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Sistema de ventas y control de stock - Soporte técnico" />
    <link rel="shortcut icon" href="./imagenes/tu-web-mensajes.jpg" type="image/x-icon" />
    <title>Servicio Técnico Web Pos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
      body {
        padding-top: 5rem;
      }
      .marketing {
        text-align: center;
      }
      .marketing .col-lg-3 {
        margin-bottom: 1.5rem;
      }
      .marketing img {
        width: 200px;
        height: 200px;
        object-fit: cover;
        border-radius: 50%;
        margin-bottom: 15px;
        display: block;
        margin-left: auto;
        margin-right: auto;
      }
      .carousel-item img {
        width: 100%;
        object-fit: cover;
      }
      footer {
        padding: 1rem 0;
        margin-top: 2rem;
        background-color: #f8f9fa;
        text-align: center;
      }
      header .logo-header {
        width: 250px;
        height: 250px;
        object-fit: cover;
        border-radius: 50%;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        transition: transform 0.3s ease-in-out;
        margin: 0 auto 1.5rem auto;
      }
      header .logo-header:hover {
        transform: scale(1.1);
      }
      header {
        background-color: #f8f9fa;
        padding: 20px 20px;
        text-align: center;
      }
      h1.display-4 {
        font-size: 3rem;
        font-weight: 700;
        color: #333;
      }
      p.lead {
        font-size: 1.25rem;
        color: #555;
        font-weight: 500;
      }
      @media (max-width: 767.98px) {
        header .logo-header {
          width: 150px;
          height: 150px;
          margin-bottom: 1rem;
          box-shadow: none;
        }
        h1.display-4 {
          font-size: 2rem;
        }
        p.lead {
          font-size: 1rem;
        }
      }

      /* Botón de WhatsApp */
      .whatsapp-float {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
      }

      .whatsapp-float img {
        width: 60px;
        height: 60px;
        transition: transform 0.3s;
      }

      .whatsapp-float img:hover {
        transform: scale(1.1);
      }
    </style>
  </head>
  <body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top" aria-label="Main navigation">
      <div class="container-fluid">
        <a class="navbar-brand" href="#">Servicio Técnico</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav me-auto mb-2 mb-md-0">
            <li class="nav-item"><a class="nav-link active" href="index.php">Inicio</a></li>
            
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Ventas</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="Nuevaventa.php">Nueva venta</a></li>
              </ul>
            </li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Reportes</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="Reporte.php">Reportes</a></li>
              </ul>
            </li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Caja</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="info_ventas.php">Movimientos</a></li>
                <li><a class="dropdown-item" href="caja.php">Apertura y cierre</a></li>
              </ul>
            </li>
            
          </ul>
        </div>
      </div>
    </nav>

    <!-- Header -->
    <header class="bg-light py-5 text-center">
      <div class="container">
        <img src="./imagenes/tu-web-mensajes.jpg" alt="Logo de la empresa" class="logo-header" />
        <h2>Servicio Técnico y Soporte</h2>
        <p>
            Nuestro equipo técnico está disponible para ayudarte ante cualquier inconveniente con el sistema.
            Ya sea para resolver errores, realizar ajustes o recibir capacitación, podés contar con nosotros.
          </p>
      </div>
    </header>

    <!-- Sección de Soporte Técnico -->
    <section class="container my-5">
      <div class="row">
        <div class="col-md-12 text-center">
          <h4>¿Cómo contactarnos?</h4>
          <ul class="list-unstyled">
            <li>📞 WhatsApp: <strong><a href="https://wa.me/543564581110" target="_blank">+54 9 3564 581110</a></strong></li>
            <li>📧 Correo: <strong>tuwebcom09@gmail.com.ar</strong></li>
            <li>⏱️ Horario de atención: Lunes a Viernes de 9:00 a 18:00 hs</li>
          </ul>
          <p>
            También podés usar el botón de WhatsApp en la esquina inferior derecha para enviarnos un mensaje directamente.
          </p>
        </div>
      </div>
    </section>

    <!-- Información sobre la empresa -->
    <section class="bg-light py-4">
      <div class="container text-center">
        <h3>Sobre la Empresa</h3>
        <p>
          <strong>TuWeb_com</strong> es una empresa especializada en soluciones digitales para comercios.
          Desarrollamos sistemas a medida, brindamos soporte técnico personalizado y acompañamos a nuestros clientes en el proceso de digitalización.
        </p>
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
      <div class="container">
        <p>&copy; 2025 Web Pos. Todos los derechos reservados.</p>
        <p>Sitio desarrollado por <strong>TuWeb_com</strong></p>
      </div>
    </footer>

    <!-- Botón flotante de WhatsApp -->
    <a
      href="https://wa.me/543564581110?text=Hola,%20necesito%20ayuda%20con%20el%20sistema"
      class="whatsapp-float"
      target="_blank"
      title="Contactar por WhatsApp"
    >
      <img src="https://img.icons8.com/color/48/000000/whatsapp--v1.png" alt="WhatsApp" />
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
