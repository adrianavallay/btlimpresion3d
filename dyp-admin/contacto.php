<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/admin.php';
require_login();
csrf_check();

$keys = [
    'phone_display', 'phone_link', 'whatsapp_number', 'whatsapp_msg',
    'address_l1', 'address_l2', 'zone', 'maps_q',
    'hours_weekdays', 'hours_sat', 'hours_sun',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $st->execute([trim((string) $_POST[$k]), $k]);
        }
    }
    flash('Datos de contacto actualizados.');
    redirect('contacto.php');
}

$s = settings();
admin_header('Contacto', 'contacto');
?>
    <form method="post" class="form">
      <?= csrf_field() ?>

      <fieldset>
        <legend>Teléfono y WhatsApp</legend>
        <div class="row3">
          <label>Teléfono (como se muestra)
            <input type="text" name="phone_display" value="<?= e($s['phone_display']) ?>">
          </label>
          <label>Teléfono para llamar (con +34)
            <input type="text" name="phone_link" value="<?= e($s['phone_link']) ?>">
          </label>
          <label>WhatsApp (con 34, sin +)
            <input type="text" name="whatsapp_number" value="<?= e($s['whatsapp_number']) ?>">
          </label>
        </div>
        <label>Mensaje que llega precargado al WhatsApp
          <input type="text" name="whatsapp_msg" value="<?= e($s['whatsapp_msg']) ?>">
        </label>
      </fieldset>

      <fieldset>
        <legend>Dirección</legend>
        <div class="row3">
          <label>Calle y número <input type="text" name="address_l1" value="<?= e($s['address_l1']) ?>"></label>
          <label>Barrio, CP y ciudad <input type="text" name="address_l2" value="<?= e($s['address_l2']) ?>"></label>
          <label>Zona de trabajo <input type="text" name="zone" value="<?= e($s['zone']) ?>"></label>
        </div>
        <label>Dirección para Google Maps (el mapa de la web)
          <input type="text" name="maps_q" value="<?= e($s['maps_q']) ?>">
        </label>
      </fieldset>

      <fieldset>
        <legend>Horario</legend>
        <div class="row3">
          <label>Lunes – Viernes <input type="text" name="hours_weekdays" value="<?= e($s['hours_weekdays']) ?>"></label>
          <label>Sábado <input type="text" name="hours_sat" value="<?= e($s['hours_sat']) ?>"></label>
          <label>Domingo <input type="text" name="hours_sun" value="<?= e($s['hours_sun']) ?>"></label>
        </div>
      </fieldset>

      <button type="submit" class="btn-primary">Guardar cambios</button>
    </form>
<?php admin_footer();
