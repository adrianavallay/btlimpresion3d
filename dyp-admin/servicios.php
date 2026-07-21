<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/admin.php';
require_once __DIR__ . '/lib/upload.php';
require_login();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $st = db()->prepare('UPDATE services SET name = ?, description = ? WHERE id = ?');
    $st->execute([
        trim((string) ($_POST['name'] ?? '')),
        trim((string) ($_POST['description'] ?? '')),
        $id,
    ]);
    if ($p = handle_image_upload($_FILES['image'] ?? [], 900)) {
        db()->prepare('UPDATE services SET image = ? WHERE id = ?')->execute([$p, $id]);
    }
    flash('Servicio actualizado.');
    redirect('servicios.php');
}

$services = fetch_services();
admin_header('Servicios', 'servicios');
?>
    <p class="lead">La foto es la que aparece flotando al pasar el ratón por cada servicio.</p>

    <?php foreach ($services as $i => $svc): ?>
    <form method="post" enctype="multipart/form-data" class="form form--row">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $svc['id'] ?>">
      <div class="rownum"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></div>
      <img class="thumb" src="<?= e(img_src($svc['image'])) ?>" alt="">
      <div class="grow">
        <label>Nombre <input type="text" name="name" value="<?= e($svc['name']) ?>" required></label>
        <label>Descripción <input type="text" name="description" value="<?= e($svc['description']) ?>" required></label>
        <label class="filelabel">Cambiar foto <input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
      </div>
      <button type="submit" class="btn-primary btn-sm">Guardar</button>
    </form>
    <?php endforeach; ?>
<?php admin_footer();
