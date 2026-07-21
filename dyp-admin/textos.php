<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/admin.php';
require_once __DIR__ . '/lib/upload.php';
require_login();
csrf_check();

$keys = [
    'hero_kicker', 'hero_line1', 'hero_line2', 'hero_line3', 'hero_sub',
    'rating_value', 'years_value',
    'about_statement', 'stat_projects', 'stat_years', 'stat_clients',
    'quote_text', 'quote_author',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $st->execute([trim((string) $_POST[$k]), $k]);
        }
    }
    if ($p = handle_image_upload($_FILES['hero_bg'] ?? [], 1800)) {
        $st->execute([$p, 'hero_bg']);
    }
    if ($p = handle_image_upload($_FILES['about_image'] ?? [], 1000)) {
        $st->execute([$p, 'about_image']);
    }
    flash('Cambios guardados. Ya están publicados en la web.');
    redirect('textos.php');
}

$s = settings();
admin_header('Textos y fotos', 'textos');
?>
    <form method="post" enctype="multipart/form-data" class="form">
      <?= csrf_field() ?>

      <fieldset>
        <legend>Portada (hero)</legend>
        <label>Etiqueta superior
          <input type="text" name="hero_kicker" value="<?= e($s['hero_kicker']) ?>">
        </label>
        <div class="row3">
          <label>Título · línea 1 <input type="text" name="hero_line1" value="<?= e($s['hero_line1']) ?>"></label>
          <label>Título · línea 2 <input type="text" name="hero_line2" value="<?= e($s['hero_line2']) ?>"></label>
          <label>Título · línea 3 <input type="text" name="hero_line3" value="<?= e($s['hero_line3']) ?>"></label>
        </div>
        <p class="hint">Para poner una palabra en cursiva naranja escríbela entre asteriscos: <code>que *transforman*</code></p>
        <label>Subtítulo
          <textarea name="hero_sub" rows="2"><?= e($s['hero_sub']) ?></textarea>
        </label>
        <div class="row3">
          <label>Nota de Google <input type="text" name="rating_value" value="<?= e($s['rating_value']) ?>"></label>
          <label>Años (insignia) <input type="text" name="years_value" value="<?= e($s['years_value']) ?>"></label>
        </div>
        <div class="imgfield">
          <img src="<?= e(img_src($s['hero_bg'])) ?>" alt="Foto actual de portada">
          <label>Foto de fondo de la portada
            <input type="file" name="hero_bg" accept="image/jpeg,image/png,image/webp">
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Quiénes somos</legend>
        <label>Párrafo principal
          <textarea name="about_statement" rows="4"><?= e($s['about_statement']) ?></textarea>
        </label>
        <div class="row3">
          <label>Proyectos terminados <input type="number" name="stat_projects" value="<?= e($s['stat_projects']) ?>"></label>
          <label>Años de experiencia <input type="number" name="stat_years" value="<?= e($s['stat_years']) ?>"></label>
          <label>% clientes satisfechos <input type="number" name="stat_clients" value="<?= e($s['stat_clients']) ?>"></label>
        </div>
        <div class="row3">
          <label>Cita destacada <input type="text" name="quote_text" value="<?= e($s['quote_text']) ?>"></label>
          <label>Autor de la cita <input type="text" name="quote_author" value="<?= e($s['quote_author']) ?>"></label>
        </div>
        <div class="imgfield">
          <img src="<?= e(img_src($s['about_image'])) ?>" alt="Foto actual de la sección">
          <label>Foto de la sección
            <input type="file" name="about_image" accept="image/jpeg,image/png,image/webp">
          </label>
        </div>
      </fieldset>

      <button type="submit" class="btn-primary">Guardar cambios</button>
    </form>
<?php admin_footer();
