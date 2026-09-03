<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/hr/common.php';
require_once __DIR__ . '/auth.php';

$acao     = $_POST['nr_acao'] ?? '';
$redirect = $_POST['redirect'] ?? 'admin-sections.php?tab=email';
$pdo      = Database::connect();

if ($acao === 'save') {
    $id     = (int)($_POST['nr_id'] ?? 0);
    $nome   = trim($_POST['nr_nome'] ?? '');
    $tipo   = ($_POST['nr_tipo'] ?? 'bcc') === 'cc' ? 'cc' : 'bcc';
    $emails = trim($_POST['nr_emails'] ?? '');
    $ativo  = isset($_POST['nr_ativo']) ? 1 : 0;
    $labs   = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['notif_labs'] ?? [])))));
    $labsJson = json_encode($labs, JSON_UNESCAPED_UNICODE);

    if ($nome === '' || $emails === '' || empty($labs)) {
        $_SESSION['_sec_flash'] = ['Preencha nome, labs e emails.', 'danger'];
        header('Location: ' . $redirect);
        exit;
    }

    if ($id > 0) {
        $pdo->prepare(
            "UPDATE infodeqb_rds_notif_regras SET nome=?, tipo=?, labs_json=?, emails=?, ativo=? WHERE id=?"
        )->execute([$nome, $tipo, $labsJson, $emails, $ativo, $id]);
        $_SESSION['_sec_flash'] = ['Regra actualizada.', 'success'];
    } else {
        $pdo->prepare(
            "INSERT INTO infodeqb_rds_notif_regras (nome, tipo, labs_json, emails, ativo) VALUES (?,?,?,?,?)"
        )->execute([$nome, $tipo, $labsJson, $emails, $ativo]);
        $_SESSION['_sec_flash'] = ['Regra criada.', 'success'];
    }

} elseif ($acao === 'delete') {
    $id = (int)($_POST['nr_id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM infodeqb_rds_notif_regras WHERE id=?")->execute([$id]);
        $_SESSION['_sec_flash'] = ['Regra eliminada.', 'success'];
    }

} elseif ($acao === 'toggle') {
    $id = (int)($_POST['nr_id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE infodeqb_rds_notif_regras SET ativo = 1-ativo WHERE id=?")->execute([$id]);
    }
}

Database::disconnect();
header('Location: ' . $redirect);
exit;
