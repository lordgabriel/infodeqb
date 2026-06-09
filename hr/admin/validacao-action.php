<?php
/**
 * Handler centralizado para acções de validação de acesso.
 * Chamado via POST de admin/index.php e admin/detail.php.
 * Redireciona sempre de volta para o URL indicado em $_POST['redirect'].
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/hr/common.php';
require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php';
require_once __DIR__ . '/auth.php';
date_default_timezone_set('Europe/Lisbon');

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$acao     = $_POST['val_acao']  ?? '';
$redirect = $_POST['redirect']  ?? 'index.php';

// ── Solicitar validações para um pedido (infodeqb_rds_pedido) ──────────────────
if ($acao === 'solicitar_pedido' && !empty($_POST['pedido_id'])) {
    $pid = (int)$_POST['pedido_id'];

    $qPed = $pdo->prepare(
        "SELECT p.*, c.nome AS colab_nome, r.datainicio, r.datafim
         FROM infodeqb_rds_pedido p
         LEFT JOIN infodeqb_rds_colaborador c ON c.codigo = p.codigo
         LEFT JOIN infodeqb_rds_registo     r ON r.autoid  = p.registo_id
         WHERE p.id = ?"
    );
    $qPed->execute([$pid]);
    $ped = $qPed->fetch(PDO::FETCH_ASSOC);

    if ($ped) {
        $d = json_decode($ped['dados_json'], true) ?: array();

        // Para tipos novo/novo_registo: todos os labs; para alteração: só os adicionados
        if (in_array($ped['tipo'], array('novo', 'novo_registo'))) {
            $deqids = array_filter((array)($d['acessos'] ?? array()));
        } else {
            $deqids = array_filter((array)($d['labs_adicionados'] ?? array()));
        }

        // datafim pode estar em dados_json (novo registo) ou na FK do registo existente
        $datafim    = $d['datafim']    ?? ($ped['datafim']    ?? '');
        $datainicio = $d['datainicio'] ?? ($ped['datainicio'] ?? '');
        $nomeColab  = $ped['colab_nome'] ?? ($d['nome'] ?? '—');

        $n = _criarValidacoes($pdo, $pid, null, $deqids, $nomeColab, $datainicio, $datafim);

        if ($n === 0) {
            // Nenhum espaço com responsável definido → armazenar aviso
            $_SESSION['val_info'] = 'Nenhum dos espaços tem responsável definido — não é necessária validação.';
        } else {
            $_SESSION['val_info'] = 'Pedido de validação enviado a ' . $n . ' responsável(is).';
        }
    }
}

// ── Solicitar validações para um registo directo (infodeqb_rds_registo) ─────────
elseif ($acao === 'solicitar_registo' && !empty($_POST['registo_id'])) {
    $rid = (int)$_POST['registo_id'];

    $qReg = $pdo->prepare(
        "SELECT r.datainicio, r.datafim, r.acessosid, c.nome AS colab_nome, c.codigo AS colab_codigo
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
         WHERE r.autoid = ?"
    );
    $qReg->execute([$rid]);
    $reg = $qReg->fetch(PDO::FETCH_ASSOC);

    if ($reg) {
        $deqids = getRegistoAcessos($pdo, $rid);
        $n = _criarValidacoes($pdo, null, $rid, $deqids, $reg['colab_nome'], $reg['datainicio'], $reg['datafim'], $reg['colab_codigo']);

        if ($n === 0) {
            $_SESSION['val_info'] = 'Nenhum dos espaços tem responsável definido — não é necessária validação.';
        } else {
            $_SESSION['val_info'] = 'Pedido de validação enviado a ' . $n . ' responsável(is).';
        }
    }
}

// ── Solicitar validação para um responsável específico ───────────────────────
elseif ($acao === 'solicitar_resp' && !empty($_POST['registo_id']) && !empty($_POST['resp_codigo'])) {
    $rid        = (int)$_POST['registo_id'];
    $respFilter = trim($_POST['resp_codigo']);

    $qReg = $pdo->prepare(
        "SELECT r.datainicio, r.datafim, c.nome AS colab_nome, c.codigo AS colab_codigo
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
         WHERE r.autoid = ?"
    );
    $qReg->execute([$rid]);
    $reg = $qReg->fetch(PDO::FETCH_ASSOC);

    if ($reg) {
        // Filtrar apenas os labs deste responsável
        $todosDeqids = getRegistoAcessos($pdo, $rid);
        $labsResp    = array();
        foreach ($todosDeqids as $deqid) {
            $qG = $pdo->prepare(
                "SELECT r.Codigo
                 FROM infodeqb_rds_gabinetes g
                 LEFT JOIN infodeqb_rds_responsaveis r ON r.Codigo = g.responsavel
                 WHERE g.deqid = ? LIMIT 1"
            );
            $qG->execute([$deqid]);
            $rc = $qG->fetchColumn();
            if ((string)$rc === $respFilter) {
                $labsResp[] = $deqid;
            }
        }
        if (!empty($labsResp)) {
            // Forçar criação mesmo que já exista Pendente anterior (apagar primeiro)
            $pdo->prepare(
                "DELETE FROM infodeqb_rds_validacao
                 WHERE registo_id=? AND resp_codigo=? AND status='Pendente'"
            )->execute([$rid, $respFilter]);
            $n = _criarValidacoes($pdo, null, $rid, $labsResp, $reg['colab_nome'], $reg['datainicio'], $reg['datafim'], $reg['colab_codigo']);
            $_SESSION['val_info'] = $n > 0
                ? 'Pedido de validação enviado ao responsável.'
                : 'Não foi possível enviar — sem espaços com responsável definido.';
        } else {
            $_SESSION['val_info'] = 'Nenhum espaço encontrado para este responsável.';
        }
    }
}

// ── Cancelar (apagar) uma validação ───────────────────────────────────
elseif ($acao === 'cancelar' && !empty($_POST['val_id'])) {
    $pdo->prepare("DELETE FROM infodeqb_rds_validacao WHERE id = ?")
        ->execute([(int)$_POST['val_id']]);
}

// ── Reabrir validação (novo token, reenviar email) ─────────────────────
elseif ($acao === 'reabrir' && !empty($_POST['val_id'])) {
    _reabrirValidacao($pdo, (int)$_POST['val_id']);
    $_SESSION['val_info'] = 'Novo pedido de validação enviado ao responsável.';
}

// ── Solicitar acessos ao SIGARRA (após validações todas aprovadas) ──────
elseif ($acao === 'solicitar_acessos' && !empty($_POST['registo_id'])) {
    $rid = (int)$_POST['registo_id'];

    // Carregar registo + colaborador
    $qReg = $pdo->prepare(
        "SELECT r.*, c.nome, c.email,
                COALESCE(resp.respespaco, r.outroresponsavel) AS responsavel_nome
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
         LEFT JOIN infodeqb_rds_responsaveis resp ON resp.codigo = r.responsavel
         WHERE r.autoid = ?"
    );
    $qReg->execute([$rid]);
    $reg = $qReg->fetch(PDO::FETCH_ASSOC);

    if ($reg) {
        // Converter deqids em gabids (igual ao email.php)
        $qGabs   = $pdo->query("SELECT gabid, deqid FROM infodeqb_rds_gabinetes");
        $colVals = array_column($qGabs->fetchAll(PDO::FETCH_ASSOC), 'gabid', 'deqid');

        $deqids2   = getRegistoAcessos($pdo, $rid);
        $deqArr    = array_flip($deqids2);
        $gabResult = array_unique(array_intersect_key($colVals, $deqArr));
        $gabStr    = str_replace('|', ';', implode(';', $gabResult));

        $info = array(
            'codigo'      => $reg['codigo'],
            'nome'        => $reg['nome'],
            'mail'        => $reg['email'],
            'fim'         => $reg['datafim'],
            'responsavel' => $reg['responsavel_nome'] ?? '',
            'acessos'     => ($reg['acessodeq'] == 1 ? 'Porta Norte; ' : '') . $gabStr,
            'acessosdeqid'=> ($reg['acessodeq'] == 1 ? 'Porta Norte; ' : '') . ($reg['acessos'] ?? ''),
        );

        $body    = format_email($info, 'mail_pedido.html');
        $subject = 'Acessos DEQ: Solicitação de novos acessos';
        $to      = array('sigarra@fe.up.pt');
        $cc      = array('deqdir@fe.up.pt', 'fmartins@fe.up.pt', 'fpereira@fe.up.pt');

        try {
            send_email($to, $body, $subject, $cc);
            $pdo->prepare(
                "UPDATE infodeqb_rds_registo SET status='Pendente', datacica=NOW() WHERE autoid=?"
            )->execute([$rid]);
            $_SESSION['val_info'] = 'Pedido enviado ao SIGARRA. Estado atualizado para Pendente.';
        } catch (Exception $e) {
            error_log('HR solicitar_acessos falhou registo_id=' . $rid . ': ' . $e->getMessage());
            $_SESSION['val_info'] = 'ERRO ao enviar email ao SIGARRA: ' . $e->getMessage();
        }
    }
}

header('Location: ' . $redirect);
exit;
