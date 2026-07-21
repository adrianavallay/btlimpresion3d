<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/admin.php';
require_login();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $pos = (int) db()->query('SELECT COALESCE(MAX(position),0)+1 FROM reviews')->fetchColumn();
        db()->prepare('INSERT INTO reviews (author, source, body, position, visible) VALUES (?,?,?,?,1)')
            ->execute([
                trim((string) ($_POST['author'] ?? '')),
                trim((string) ($_POST['source'] ?? 'Google')),
                trim((string) ($_POST['body'] ?? '')),
                $pos,
            ]);
        flash('Opinión añadida.');
    } elseif ($action === 'update') {
        db()->prepare('UPDATE reviews SET author = ?, source = ?, body = ? WHERE id = ?')
            ->execute([
                trim((string) ($_POST['author'] ?? '')),
                trim((string) ($_POST['source'] ?? '')),
                trim((string) ($_POST['body'] ?? '')),
                $id,
            ]);
        flash('Opinión actualizada.');
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        flash('Opinión eliminada.');
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE reviews SET visible = 1 - visible WHERE id = ?')->execute([$id]);
        flash('Visibilidad cambiada.');
    }
    redirect('opiniones.php');
}

$reviews = fetch_reviews(false);
admin_header('Opiniones', 'opiniones');
?>
    <p class="lead">Reseñas que se muestran en la sección "Lo dicen nuestros clientes".</p>

    <?php foreach ($reviews as $r): ?>
    <form method="post" class="form form--row <?= $r['visible'] ? '' : 'is-hidden-row' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <div class="grow">
        <div class="row3">
          <label>Autor <input type="text" name="author" value="<?= e($r['author']) ?>" required></label>
          <label>Fuente <input type="text" name="source" value="<?= e($r['source']) ?>"></label>
        </div>
        <label>Texto <textarea name="body" rows="2" required><?= e($r['body']) ?></textarea></label>
      </div>
      <div class="rowactions">
        <button type="submit" name="action" value="update" class="btn-primary btn-sm">Guardar</button>
        <button type="submit" name="action" value="toggle" class="btn-ghost btn-sm"><?= $r['visible'] ? 'Ocultar' : 'Mostrar' ?></button>
        <button type="submit" name="action" value="delete" class="btn-danger btn-sm"
                onclick="return confirm('¿Eliminar esta opinión?')">Eliminar</button>
      </div>
    </form>
    <?php endforeach; ?>

    <h2 class="subhead">Añadir opinión</h2>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="row3">
        <label>Autor <input type="text" name="author" required></label>
        <label>Fuente <input type="text" name="source" value="Google"></label>
      </div>
      <label>Texto <textarea name="body" rows="2" required></textarea></label>
      <button type="submit" class="btn-primary">Añadir opinión</button>
    </form>
<?php admin_footer();
