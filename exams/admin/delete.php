<?php
require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';

if (!($_SESSION['user'] && (
    $_SESSION['user'] == 'up356946@up.pt' ||
    $_SESSION['user'] == 'up448105@up.pt' ||
    $_SESSION['user'] == 'up424064@up.pt'
))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

$id = null;

if (!empty($_GET['id'])) {
    $id = $_REQUEST['id'];
}

if (!empty($_POST)) {
    $id = $_POST['id'];
    $pdo = Database::connect();
    $sql = 'DELETE FROM infodeqb_exam_archive WHERE id = ?';
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    Database::disconnect();
    header('Location: index.php');
    exit;
}

$pageTitle = t('EXAM_ADMIN_DELETE_TITLE');
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<ol class="breadcrumb">
  <li class="breadcrumb-item"><h5>Administração — Arquivo de Exames</h5></li>
</ol>

<div class="card mb-3">
  <div class="card-header">
    <i class="fas fa-trash"></i> Eliminar registo
  </div>
  <div class="card-body">
    <form action="delete.php" method="post">
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
      <p class="alert alert-warning">Tem a certeza que pretende eliminar este exame?</p>
      <div class="form-group">
        <button type="submit" class="btn btn-danger">Sim, eliminar</button>
        <a class="btn btn-secondary" href="index.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
