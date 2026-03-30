<?php
// ============================================================
// ADMIN — CUPONES
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$db = pdo();
$flash_ok  = '';
$flash_err = '';

// ── POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($action === 'create') {
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        if ($codigo === '') {
            $codigo = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        }
        $tipo          = $_POST['tipo'] ?? 'porcentaje';
        $valor         = (float)($_POST['valor'] ?? 0);
        $minimo_compra = (float)($_POST['minimo_compra'] ?? 0);
        $usos_maximos  = (int)($_POST['usos_maximos'] ?? 0);
        $fecha_inicio  = trim($_POST['fecha_inicio'] ?? '');
        $fecha_fin     = trim($_POST['fecha_fin'] ?? '');

        if ($valor <= 0) {
            $flash_err = 'El valor del cupón debe ser mayor a 0.';
        } elseif (!in_array($tipo, ['porcentaje', 'monto_fijo'])) {
            $flash_err = 'Tipo de cupón inválido.';
        } else {
            // Check unique code
            $chk = $db->prepare("SELECT id FROM cupones WHERE codigo = ?");
            $chk->execute([$codigo]);
            if ($chk->fetch()) {
                $flash_err = 'Ya existe un cupón con ese código.';
            } else {
                $stmt = $db->prepare("INSERT INTO cupones (codigo, tipo, valor, minimo_compra, usos_maximos, usos_actuales, fecha_inicio, fecha_fin, activo)
                    VALUES (?, ?, ?, ?, ?, 0, ?, ?, 1)");
                $stmt->execute([$codigo, $tipo, $valor, $minimo_compra, $usos_maximos,
                    $fecha_inicio ?: null, $fecha_fin ?: null]);
                $flash_ok = "Cupón <strong>$codigo</strong> creado correctamente.";
            }
        }
    }

    // ── UPDATE ──
    if ($action === 'update') {
        $id            = (int)($_POST['id'] ?? 0);
        $codigo        = strtoupper(trim($_POST['codigo'] ?? ''));
        $tipo          = $_POST['tipo'] ?? 'porcentaje';
        $valor         = (float)($_POST['valor'] ?? 0);
        $minimo_compra = (float)($_POST['minimo_compra'] ?? 0);
        $usos_maximos  = (int)($_POST['usos_maximos'] ?? 0);
        $fecha_inicio  = trim($_POST['fecha_inicio'] ?? '');
        $fecha_fin     = trim($_POST['fecha_fin'] ?? '');

        if ($id < 1 || $codigo === '' || $valor <= 0) {
            $flash_err = 'Datos inválidos.';
        } elseif (!in_array($tipo, ['porcentaje', 'monto_fijo'])) {
            $flash_err = 'Tipo de cupón inválido.';
        } else {
            // Check unique code (excluding self)
            $chk = $db->prepare("SELECT id FROM cupones WHERE codigo = ? AND id != ?");
            $chk->execute([$codigo, $id]);
            if ($chk->fetch()) {
                $flash_err = 'Ya existe otro cupón con ese código.';
            } else {
                $stmt = $db->prepare("UPDATE cupones SET codigo=?, tipo=?, valor=?, minimo_compra=?, usos_maximos=?, fecha_inicio=?, fecha_fin=? WHERE id=?");
                $stmt->execute([$codigo, $tipo, $valor, $minimo_compra, $usos_maximos,
                    $fecha_inicio ?: null, $fecha_fin ?: null, $id]);
                $flash_ok = 'Cupón actualizado.';
            }
        }
    }

    // ── DELETE ──
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM cupones WHERE id = ?")->execute([$id]);
            $flash_ok = 'Cupón eliminado.';
        }
    }

    // ── TOGGLE ACTIVO ──
    if ($action === 'toggle_activo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE cupones SET activo = NOT activo WHERE id = ?")->execute([$id]);
            $flash_ok = 'Estado del cupón actualizado.';
        }
    }

    // PRG
    if ($flash_ok) flash('ok', $flash_ok);
    if ($flash_err) flash('err', $flash_err);
    redirect('admin_cupones.php?' . http_build_query(array_filter(['page' => $_GET['page'] ?? ''])));
}

// Retrieve flash
$flash_ok  = flash('ok');
$flash_err = flash('err');

// ── PAGINATION ──
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$total_rows  = (int)$db->query("SELECT COUNT(*) FROM cupones")->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// ── FETCH CUPONES ──
$stmt = $db->prepare("SELECT * FROM cupones ORDER BY fecha_fin DESC LIMIT :lim OFFSET :off");
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$cupones = $stmt->fetchAll();

// ── DETAIL VIEW: orders that used a coupon ──
$detalle_cupon = null;
$detalle_pedidos = [];
if (isset($_GET['detalle'])) {
    $did = (int)$_GET['detalle'];
    $dst = $db->prepare("SELECT * FROM cupones WHERE id = ?");
    $dst->execute([$did]);
    $detalle_cupon = $dst->fetch();
    if ($detalle_cupon) {
        $pst = $db->prepare("SELECT id, fecha, nombre, email, total, estado FROM pedidos WHERE cupon_codigo = ? ORDER BY fecha DESC");
        $pst->execute([$detalle_cupon['codigo']]);
        $detalle_pedidos = $pst->fetchAll();
    }
}

// ── EDIT: load coupon for modal ──
$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $est = $db->prepare("SELECT * FROM cupones WHERE id = ?");
    $est->execute([$eid]);
    $edit = $est->fetch();
}

$now = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cupones — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>
<?php $admin_page = 'cupones'; ?>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

    <!-- Flash -->
    <?php if ($flash_ok): ?>
        <div class="flash ok"><?= $flash_ok ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="flash err"><?= sanitize($flash_err) ?></div>
    <?php endif; ?>

    <!-- ── DETAIL VIEW ── -->
    <?php if ($detalle_cupon): ?>
    <div class="detail-panel">
        <a href="admin_cupones.php" class="btn btn-outline btn-sm" style="margin-bottom:16px">&larr; Volver a cupones</a>
        <h2>Cupón: <?= sanitize($detalle_cupon['codigo']) ?></h2>
        <div class="detail-meta">
            <span>Tipo: <strong><?= $detalle_cupon['tipo'] === 'porcentaje' ? 'Porcentaje' : 'Monto fijo' ?></strong></span>
            <span>Valor: <strong><?= $detalle_cupon['tipo'] === 'porcentaje' ? $detalle_cupon['valor'] . '%' : price((float)$detalle_cupon['valor']) ?></strong></span>
            <span>Mín. compra: <strong><?= price((float)$detalle_cupon['minimo_compra']) ?></strong></span>
            <span>Usos: <strong><?= (int)$detalle_cupon['usos_actuales'] ?>/<?= (int)$detalle_cupon['usos_maximos'] ?: '&infin;' ?></strong></span>
            <span>Vigencia: <strong><?= $detalle_cupon['fecha_inicio'] ? date('d/m/Y', strtotime($detalle_cupon['fecha_inicio'])) : '—' ?> - <?= $detalle_cupon['fecha_fin'] ? date('d/m/Y', strtotime($detalle_cupon['fecha_fin'])) : '—' ?></strong></span>
            <span>Estado: <?php
                if (!$detalle_cupon['activo']) {
                    echo '<span class="badge badge-inactivo">Inactivo</span>';
                } elseif ($detalle_cupon['fecha_fin'] && $detalle_cupon['fecha_fin'] < $now) {
                    echo '<span class="badge badge-expirado">Expirado</span>';
                } else {
                    echo '<span class="badge badge-activo">Activo</span>';
                }
            ?></span>
        </div>
        <h3 style="font-size:1rem;margin-bottom:12px">Pedidos que usaron este cupón (<?= count($detalle_pedidos) ?>)</h3>
        <?php if (empty($detalle_pedidos)): ?>
            <p style="color:#71717a;font-size:.9rem">Ningún pedido ha usado este cupón todavía.</p>
        <?php else: ?>
        <div class="table-wrap table-container">
            <table>
                <thead>
                    <tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Email</th><th>Total</th><th>Estado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($detalle_pedidos as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                        <td><?= sanitize($p['nombre']) ?></td>
                        <td><?= sanitize($p['email']) ?></td>
                        <td><?= price((float)$p['total']) ?></td>
                        <td><?= sanitize($p['estado']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="page-header">
        <h1>Cupones <small style="font-size:.85rem;color:#71717a">(<?= $total_rows ?>)</small></h1>
        <button class="btn btn-primary" onclick="openCuponModal()">+ Nuevo cupón</button>
    </div>

    <!-- Table -->
    <?php if (count($cupones) === 0): ?>
        <div class="table-wrap"><p class="empty">No hay cupones todavía.</p></div>
    <?php else: ?>
    <div class="table-wrap table-container">
        <table>
            <thead>
            <tr>
                <th>Código</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Mín. compra</th>
                <th>Usos</th>
                <th>Vigencia</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($cupones as $c):
                $expired = $c['fecha_fin'] && $c['fecha_fin'] < $now;
            ?>
            <tr class="<?= $expired ? 'row-muted' : '' ?>">
                <td>
                    <a href="admin_cupones.php?detalle=<?= $c['id'] ?>" style="color:var(--black);text-decoration:none;font-weight:600">
                        <?= sanitize($c['codigo']) ?>
                    </a>
                </td>
                <td>
                    <?php if ($c['tipo'] === 'porcentaje'): ?>
                        <span class="badge badge-porcentaje">%</span>
                    <?php else: ?>
                        <span class="badge badge-monto">$</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $c['tipo'] === 'porcentaje'
                        ? number_format((float)$c['valor'], 0) . '%'
                        : price((float)$c['valor']) ?>
                </td>
                <td><?= price((float)$c['minimo_compra']) ?></td>
                <td><?= (int)$c['usos_actuales'] ?>/<?= (int)$c['usos_maximos'] ?: '&infin;' ?></td>
                <td>
                    <?= $c['fecha_inicio'] ? date('d/m/Y', strtotime($c['fecha_inicio'])) : '—' ?>
                    &mdash;
                    <?= $c['fecha_fin'] ? date('d/m/Y', strtotime($c['fecha_fin'])) : '—' ?>
                </td>
                <td>
                    <?php if ($expired): ?>
                        <span class="badge badge-expirado">Expirado</span>
                    <?php else: ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="toggle_activo">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <label class="toggle-switch" title="<?= $c['activo'] ? 'Activo' : 'Inactivo' ?>">
                                <input type="checkbox" <?= $c['activo'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="actions">
                        <a href="admin_cupones.php?edit=<?= $c['id'] ?>" class="btn btn-outline btn-sm" title="Editar">&#9998;</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este cupón?')">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">&#10005;</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>">&laquo;</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="admin-footer">
        <p>&copy; <?= date('Y') ?> DyP Consultora &mdash; Panel de gestión</p>
    </footer>

</div>

<!-- ── CUPON MODAL ── -->
<div class="modal-overlay" id="cuponModal">
    <div class="modal">
        <div class="modal-head">
            <h2 id="modalTitle">Nuevo cupón</h2>
            <button class="modal-close" onclick="closeCuponModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="cuponForm">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <div class="code-row">
                            <input type="text" id="codigo" name="codigo" placeholder="Dejar vacío para auto-generar" maxlength="20" style="text-transform:uppercase">
                            <button type="button" class="btn-gen" onclick="generateCode()">Generar código</button>
                        </div>
                        <span class="hint">Si se deja vacío se genera uno aleatorio de 8 caracteres</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo">Tipo de descuento</label>
                        <select id="tipo" name="tipo">
                            <option value="porcentaje">Porcentaje (%)</option>
                            <option value="monto_fijo">Monto fijo ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="valor">Valor</label>
                        <input type="number" id="valor" name="valor" step="0.01" min="0.01" required placeholder="Ej: 15">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="minimo_compra">Mínimo de compra</label>
                        <input type="number" id="minimo_compra" name="minimo_compra" step="0.01" min="0" value="0" placeholder="0 = sin mínimo">
                    </div>
                    <div class="form-group">
                        <label for="usos_maximos">Usos máximos</label>
                        <input type="number" id="usos_maximos" name="usos_maximos" min="0" value="0" placeholder="0 = ilimitado">
                        <span class="hint">0 = ilimitado</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fecha fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin">
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
                    <button type="button" class="btn btn-outline" onclick="closeCuponModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Crear cupón</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<script>
// ── Generate random code ──
function generateCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = '';
    for (let i = 0; i < 8; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('codigo').value = code;
}

// ── Modal open/close ──
function openCuponModal(data) {
    const modal = document.getElementById('cuponModal');
    const form  = document.getElementById('cuponForm');
    form.reset();

    if (data) {
        // Edit mode
        document.getElementById('modalTitle').textContent = 'Editar cupón';
        document.getElementById('formAction').value = 'update';
        document.getElementById('formId').value = data.id;
        document.getElementById('codigo').value = data.codigo;
        document.getElementById('tipo').value = data.tipo;
        document.getElementById('valor').value = data.valor;
        document.getElementById('minimo_compra').value = data.minimo_compra;
        document.getElementById('usos_maximos').value = data.usos_maximos;
        document.getElementById('fecha_inicio').value = data.fecha_inicio || '';
        document.getElementById('fecha_fin').value = data.fecha_fin || '';
        document.getElementById('submitBtn').textContent = 'Guardar cambios';
    } else {
        // Create mode
        document.getElementById('modalTitle').textContent = 'Nuevo cupón';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formId').value = '';
        document.getElementById('submitBtn').textContent = 'Crear cupón';
    }

    modal.classList.add('open');
}

function closeCuponModal() {
    document.getElementById('cuponModal').classList.remove('open');
}

// Close modal on overlay click
document.getElementById('cuponModal').addEventListener('click', function(e) {
    if (e.target === this) closeCuponModal();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCuponModal();
});

// ── Auto-open modal if editing ──
<?php if ($edit): ?>
openCuponModal(<?= json_encode([
    'id'            => $edit['id'],
    'codigo'        => $edit['codigo'],
    'tipo'          => $edit['tipo'],
    'valor'         => $edit['valor'],
    'minimo_compra' => $edit['minimo_compra'],
    'usos_maximos'  => $edit['usos_maximos'],
    'fecha_inicio'  => $edit['fecha_inicio'],
    'fecha_fin'     => $edit['fecha_fin'],
]) ?>);
<?php endif; ?>
</script>
<script src="js/admin.js"></script>

</body>
</html>
