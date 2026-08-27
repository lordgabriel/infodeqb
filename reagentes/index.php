<?php
require $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php';
include ROOT_DIR.'/infodeqb/session.php';
require_once ROOT_DIR.'/infodeqb/inc/admins.php';

$_filesDir = ROOT_DIR . '/infodeqb/files';
$flashMsg  = '';
$flashType = 'success';

// ── Upload (apenas admins) ────────────────────────────────────────
if ($isAdmin && !empty($_FILES['inventario']) && $_FILES['inventario']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['inventario']['name'], PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        $dest = $_filesDir . '/' . basename($_FILES['inventario']['name']);
        if (move_uploaded_file($_FILES['inventario']['tmp_name'], $dest)) {
            $flashMsg  = t('REAGENTES_UPLOAD_OK');
            $flashType = 'success';
        } else {
            $flashMsg  = t('REAGENTES_UPLOAD_ERR');
            $flashType = 'danger';
        }
    } else {
        $flashMsg  = t('REAGENTES_UPLOAD_TYPE');
        $flashType = 'warning';
    }
}

// ── Ficheiro mais recente ─────────────────────────────────────────
$_xlsxFiles = glob($_filesDir . '/*.xlsx') ?: array();
usort($_xlsxFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
$_file    = !empty($_xlsxFiles) ? $_xlsxFiles[0] : null;
$_fileUrl = $_file ? HTTP_DIR . '/infodeqb/files/' . basename($_file) : null;
$_exists  = $_file !== null;
$_size    = $_exists ? round(filesize($_file) / 1024, 1) . ' KB' : '—';
$_mtime   = $_exists ? filemtime($_file) : null;
$_modified = $_mtime ? sprintf(t('DATE_FMT_LONG'),
    ($GLOBALS['_lang']['DAYS_OF_WEEK'] ?? [])[date('w', $_mtime)] ?? '',
    (int)date('j', $_mtime),
    ($GLOBALS['_lang']['MONTHS'] ?? [])[(int)date('n', $_mtime) - 1] ?? '',
    (int)date('Y', $_mtime)
) : '—';

$pageTitle = t('REAGENTES_TITLE');
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <h1><?= t('REAGENTES_TITLE') ?></h1>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" role="alert" style="font-size:.85rem">
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<p class="mb-4"><?= t('REAGENTES_DESC') ?></p>

<div class="card mb-4" style="max-width:640px">
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <tbody>
        <tr><th style="width:11em"><?= t('REAGENTES_FILE') ?></th><td><?= $_exists ? htmlspecialchars(basename($_file)) : '—' ?></td></tr>
        <tr><th><?= t('REAGENTES_DESCRIPTION') ?></th><td><?= t('REAGENTES_FILE_DESC') ?></td></tr>
        <tr><th><?= t('REAGENTES_FREQUENCY') ?></th><td><?= t('REAGENTES_FREQ_VAL') ?></td></tr>
        <tr><th><?= t('REAGENTES_SIZE') ?></th><td><?= $_size ?></td></tr>
        <tr><th><?= t('REAGENTES_MODIFIED') ?></th><td><?= $_modified ?></td></tr>
      </tbody>
      <tfoot>
        <tr><td colspan="2" class="text-center py-3">
          <?php if ($_exists): ?>
          <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($_fileUrl) ?>">
            <i class="fas fa-download me-1"></i><?= t('DOWNLOAD') ?>
          </a>
          <?php else: ?>
          <span class="text-muted"><?= t('REAGENTES_FILE_UNAVAIL') ?></span>
          <?php endif; ?>
        </td></tr>
      </tfoot>
    </table>
  </div>
</div>

<?php if ($isAdmin): ?>
<div class="card border-secondary mb-4" style="max-width:640px">
  <div class="card-header py-2 d-flex align-items-center">
    <i class="fas fa-upload me-2 text-muted"></i>
    <strong><?= t('REAGENTES_UPLOAD_TITLE') ?></strong>
    <span class="badge badge-secondary ms-2" style="font-size:.7rem">Admin</span>
  </div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2" style="gap:.75rem">
      <input type="file" name="inventario" accept=".xlsx" class="form-control form-control-sm" required style="max-width:320px">
      <button type="submit" class="btn btn-secondary btn-sm text-nowrap">
        <i class="fas fa-upload me-1"></i><?= t('UPLOAD') ?>
      </button>
    </form>
    <small class="text-muted mt-1 d-block"><?= t('REAGENTES_UPLOAD_HINT') ?></small>
  </div>
</div>
<?php endif; ?>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
