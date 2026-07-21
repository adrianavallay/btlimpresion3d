<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/admin.php';
require_once __DIR__ . '/lib/upload.php';
require_login();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $img = handle_image_upload($_FILES['image'] ?? [], 1400);
        $pos = (int) db()->query('SELECT COALESCE(MAX(position),0)+1 FROM projects')->fetchColumn();
        db()->prepare('INSERT INTO projects (title, meta, image, position, visible) VALUES (?,?,?,?,1)')
            ->execute([
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['meta'] ?? '')),
                $img ?? '',
                $pos,
            ]);
        flash('Proyecto añadido.');
    } elseif ($action === 'update') {
        db()->prepare('UPDATE projects SET title = ?, meta = ? WHERE id = ?')
            ->execute([
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['meta'] ?? '')),
                $id,
            ]);
        if ($p = handle_image_upload($_FILES['image'] ?? [], 1400)) {
            db()->prepare('UPDATE projects SET image = ? WHERE id = ?')->execute([$p, $id]);
        }
        flash('Proyecto actualizado.');
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
        flash('Proyecto eliminado.');
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE projects SET visible = 1 - visible WHERE id = ?')->execute([$id]);
        flash('Visibilidad cambiada.');
    } elseif ($action === 'move') {
        $dir = $_POST['dir'] === 'up' ? -1 : 1;
        $cur = db()->prepare('SELECT id, position FROM projects WHERE id = ?');
        $cur->execute([$id]);
        if ($row = $cur->fetch()) {
            $op = $dir < 0 ? '<' : '>';
            $ord = $dir < 0 ? 'DESC' : 'ASC';
            $sw = db()->prepare("SELECT id, position FROM projects WHERE position $op ? ORDER BY position $ord LIMIT 1");
            $sw->execute([$row['position']]);
            if ($other = $sw->fetch()) {
                $upd = db()->prepare('UPDATE projects SET position = ? WHERE id = ?');
                $upd->execute([$other['position'], $row['id']]);
                $upd->execute([$row['position'], $other['id']]);
            }
        }
    }
    redirect('proyectos.php');
}

$projects = fetch_projects(false);
admin_header('Proyectos', 'proyectos');
?>
    <p class="lead">Estos son los trabajos de la galería horizontal. Puedes ordenarlos, ocultarlos o eliminarlos.</p>

    <?php foreach ($projects as $p): ?>
    <form method="post" enctype="multipart/form-data" class="form form--row <?= $p['visible'] ? '' : 'is-hidden-row' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="movebtns">
        <button type="submit" name="action" value="move" onclick="this.form.dir.value='up'" title="Subir">▲</button>
        <button type="submit" name="action" value="move" onclick="this.form.dir.value='down'" title="Bajar">▼</button>
        <input type="hidden" name="dir" value="up">
      </div>
      <img class="thumb thumb--wide" src="<?= e(img_src($p['image'])) ?>" alt="">
      <div class="grow">
        <label>Título <input type="text" name="title" value="<?= e($p['title']) ?>" required></label>
        <label>Zona · tipo de obra <input type="text" name="meta" value="<?= e($p['meta']) ?>"></label>
        <label class="filelabel">Cambiar foto <input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
      </div>
      <div class="rowactions">
        <button type="submit" name="action" value="update" class="btn-primary btn-sm">Guardar</button>
        <button type="submit" name="action" value="toggle" class="btn-ghost btn-sm"><?= $p['visible'] ? 'Ocultar' : 'Mostrar' ?></button>
        <button type="submit" name="action" value="delete" class="btn-danger btn-sm"
                onclick="return confirm('¿Eliminar este proyecto? Esta acción no se puede deshacer.')">Eliminar</button>
      </div>
    </form>
    <?php endforeach; ?>

    <h2 class="subhead">Añadir proyecto</h2>
    <form method="post" enctype="multipart/form-data" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="row3">
        <label>Título <input type="text" name="title" required placeholder="Cocina en L"></label>
        <label>Zona · tipo de obra <input type="text" name="meta" placeholder="Gràcia · Reforma completa"></label>
        <label>Foto <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
      </div>
      <button type="submit" class="btn-primary">Añadir proyecto</button>
    </form>
<?php admin_footer();
