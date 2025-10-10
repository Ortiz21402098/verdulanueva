<!doctype html>
<html lang="en" data-bs-theme="auto">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="4am Shoes - Your Style, Your Comfort" />
    <link
      rel="shortcut icon"
      href="./imagenes/tu-web-mensajes.jpg"
      type="image/x-icon"
    />
    <title>Sistema online de administracion y venta</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
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
      /* Imagen marketing */
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
      /* Logo encabezado */
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
      header nav {
        padding: 20px 20px;
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

      /* Responsive */
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
    </style>
  </head>
  <body>
    <!-- Navbar -->
    <nav
      class="navbar navbar-expand-md navbar-dark bg-dark fixed-top"
      aria-label="Main navigation"
    >
      <div class="container-fluid">
        <a class="navbar-brand" href="#">Web Pos</a>
        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#navbarNav"
          aria-controls="navbarNav"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav me-auto mb-2 mb-md-0">
            <li class="nav-item">
              <a class="nav-link active" href="index.php">Inicio</a>
            </li>
            <li class="nav-item dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                >Ventas</a
              >
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="Nuevaventa.php">Nueva venta</a></li>
              </ul>
            </li>
            
            <li class="nav-item dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                >Caja</a
              >
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="info_ventas.php">Movimientos</a></li>
                <li><a class="dropdown-item" href="Reporte.php">Reportes</a></li>
                <li><a class="dropdown-item" href="caja.php">Apertura y cierre</a></li>
              </ul>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="servicio.php">Servicio Técnico</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Header -->
    <header class="bg-light py-5 text-center">
      <div class="container">
        <img
          src="./imagenes/tu-web-mensajes.jpg"
          alt="Logo de 4am Shoes"
          class="logo-header"
        />
        <h1>Bienvenido</h1>
        <p class="lead">Aplicación de ventas y control de stock</p>
      </div>
    </header>

    <!-- Footer -->
    <footer class="footer">
      <div class="container">
        <p>&copy; 2024 Web Pos. Todos los derechos reservados.</p>
        <p>
          Sitio desarrollado por <strong>tuweb_com</strong>.
        </p>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>

