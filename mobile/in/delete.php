<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsMobile))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
}




$validado = $_SESSION['user'];

    $id = null;

    if ( !empty($_GET['id'])) {
        $id = $_REQUEST['id'];
    }

    if ( !empty($_POST)) {
        // keep track post values
        $id = $_POST['id'];

        // delete data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "DELETE FROM infodeqb_registo_mobilidade WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute(array($id));
        Database::disconnect();
        header("Location: edit.php");

    }

$pageTitle = t('MOBILE_DELETE_REQUEST');
$mainClass  = 'iq-hr-page';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

            <!-- Breadcrumbs-->
            <ol class="breadcrumb">
              <li class="breadcrumb-item "><h5> Eng. Química - Mobilidade IN</h5></li>
            </ol>
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-database"></i> Eliminar registo
                </div>
                    <div class="card-body">
                        <!-- Formulario de registo-->
                        <form id="mobilidade" action="delete.php" method="post">
                            <input type="hidden" name="id" value="<?php echo $id;?>"/>
                            <p class="alert alert-error">Tem a certeza que pretensde eliminar o registo?</p>
                            <div class="form-actions">
                            <button type="submit" class="btn btn-danger">Sim</button>
                            <a class="btn btn-info" <?php echo 'href="detail.php?id='.$id.'"';?>>Não</a>'
                            </div>
                       </form>
                    </div>
                </div>
            </div>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
