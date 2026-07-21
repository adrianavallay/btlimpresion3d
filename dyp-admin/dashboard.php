<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/admin.php';
require_login();

$nProjects = (int) db()->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$nReviews  = (int) db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
$nServices = (int) db()->query('SELECT COUNT(*) FROM services')->fetchColumn();

admin_header('Hola, ' . ($_SESSION['uname'] ?? 'admin'), 'dashboard');
?>
    <p class="lead">Desde aquí puedes cambiar los textos, las fotos y los datos de contacto de la web. Los cambios se publican al instante.</p>

    <div class="cards">
      <a class="card" href="textos.php">
        <h2>Textos y fotos</h2>
        <p>Título principal, quiénes somos, cifras y fotos grandes.</p>
      </a>
      <a class="card" href="servicios.php">
        <h2>Servicios <span class="badge"><?= $nServices ?></span></h2>
        <p>Nombre, descripción y foto de cada servicio.</p>
      </a>
      <a class="card" href="proyectos.php">
        <h2>Proyectos <span class="badge"><?= $nProjects ?></span></h2>
        <p>Añade, ordena u oculta trabajos realizados.</p>
      </a>
      <a class="card" href="opiniones.php">
        <h2>Opiniones <span class="badge"><?= $nReviews ?></span></h2>
        <p>Reseñas de clientes que se muestran en la web.</p>
      </a>
      <a class="card" href="contacto.php">
        <h2>Contacto</h2>
        <p>Teléfono, WhatsApp, dirección y horarios.</p>
      </a>
      <a class="card" href="cuenta.php">
        <h2>Mi cuenta</h2>
        <p>Cambiar usuario o contraseña del panel.</p>
      </a>
    </div>
<?php admin_footer();
