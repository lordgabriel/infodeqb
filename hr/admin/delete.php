<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado

$id = null;

if (! empty($_POST)) {

    $id = $_POST['id'];
    if (! null == $_POST['id1']) {
        $id1 = $_POST['id1'];
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Soft-delete do registo
        $pdo->prepare("UPDATE infodeqb_rds_registo SET deleted = 1, status = 'Inativo' WHERE codigo = ? AND autoid = ?")
            ->execute(array($id, $id1));

        // Cancelar pedidos pendentes associados a este registo
        $pdo->prepare(
            "UPDATE infodeqb_rds_pedido SET status = 'Cancelado', processado_em = NOW()
             WHERE registo_id = ? AND status IN ('Pendente','Aguarda_SIGARRA')"
        )->execute(array($id1));

        Database::disconnect();
        echo "<div class='alert alert-success' role='alert'>Registo apagado com sucesso, aguarde você está sendo redirecionado ...</div> ";
        echo '<meta http-equiv=refresh content=\'1;URL=detail.php?id=' . $id . '\'>';
        header('Location: detail.php?id=' . $id);
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Soft-delete do colaborador e todos os registos
        $pdo->prepare("UPDATE infodeqb_rds_colaborador SET deleted = 1 WHERE codigo = ?")
            ->execute(array($id));
        $pdo->prepare("UPDATE infodeqb_rds_registo SET deleted = 1, status = 'Inativo' WHERE codigo = ?")
            ->execute(array($id));

        // Cancelar todos os pedidos pendentes do colaborador
        $pdo->prepare(
            "UPDATE infodeqb_rds_pedido SET status = 'Cancelado', processado_em = NOW()
             WHERE codigo = ? AND status IN ('Pendente','Aguarda_SIGARRA')"
        )->execute(array($id));

        Database::disconnect();
        echo "<div class='alert alert-success' role='alert'>Registo apagado com sucesso, aguarde você está sendo redirecionado ...</div> ";
        echo '<meta http-equiv=refresh content=\'1;URL=detail.php?id=' . $id . '\'>';
        header('Location: index.php');
    }
} else {
    header('Location:index.php');
}

?>
