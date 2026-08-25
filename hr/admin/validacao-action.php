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
        "SELECT p.*, c.nome AS colab_nome, c.codigo AS colab_codigo, r.datainicio, r.datafim,
                IF(r.responsavel=0, r.outroresponsavel, rsp.respespaco) AS resp_trabalho
         FROM infodeqb_rds_pedido p
         LEFT JOIN infodeqb_rds_colaborador  c   ON c.codigo  = p.codigo
         LEFT JOIN infodeqb_rds_registo      r   ON r.autoid  = p.registo_id
         LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo = r.responsavel
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
        $datafim    = isset($d['datafim'])    ? $d['datafim']    : (isset($ped['datafim'])    ? $ped['datafim']    : '');
        $datainicio = isset($d['datainicio']) ? $d['datainicio'] : (isset($ped['datainicio']) ? $ped['datainicio'] : '');
        $nomeColab  = isset($ped['colab_nome']) ? $ped['colab_nome'] : (isset($d['nome']) ? $d['nome'] : '—');
        $codColab    = isset($ped['colab_codigo'])  ? $ped['colab_codigo']  : '';
        $respTrabalho = isset($ped['resp_trabalho']) ? $ped['resp_trabalho'] : '';

        $n = _criarValidacoes($pdo, $pid, null, $deqids, $nomeColab, $datainicio, $datafim, $codColab, $respTrabalho);

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
        "SELECT r.datainicio, r.datafim, r.acessosid, c.nome AS colab_nome, c.codigo AS colab_codigo,
                IF(r.responsavel=0, r.outroresponsavel, rsp.respespaco) AS resp_trabalho
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador  c   ON c.codigo   = r.codigo
         LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo = r.responsavel
         WHERE r.autoid = ?"
    );
    $qReg->execute([$rid]);
    $reg = $qReg->fetch(PDO::FETCH_ASSOC);

    if ($reg) {
        if (_registoTotalmenteValidado($pdo, $rid)) {
            $_SESSION['val_info'] = 'Este registo já tem todas as validações concluídas — não é necessário solicitar novamente.';
        } else {
            $deqids = getRegistoAcessos($pdo, $rid);
            $n = _criarValidacoes($pdo, null, $rid, $deqids, $reg['colab_nome'], $reg['datainicio'], $reg['datafim'], $reg['colab_codigo'], $reg['resp_trabalho'] ?? '');

            if ($n === 0) {
                // Distinguir entre "sem responsável" e "já solicitado"
                $qPend = $pdo->prepare("SELECT COUNT(*) FROM infodeqb_rds_validacao WHERE registo_id=? AND status='Pendente'");
                $qPend->execute([$rid]);
                if ((int)$qPend->fetchColumn() > 0) {
                    $_SESSION['val_info'] = 'Validações já solicitadas anteriormente — aguarda resposta dos responsáveis.';
                } else {
                    $_SESSION['val_info'] = 'Nenhum dos espaços tem responsável definido — não é necessária validação.';
                }
            } else {
                $_SESSION['val_info'] = 'Pedido de validação enviado a ' . $n . ' responsável(is).';
            }
        }
    }
}

// ── Solicitar validação para um responsável específico ───────────────────────
elseif ($acao === 'solicitar_resp' && !empty($_POST['registo_id']) && !empty($_POST['resp_codigo'])) {
    $rid        = (int)$_POST['registo_id'];
    $respFilter = trim($_POST['resp_codigo']);

    $qReg = $pdo->prepare(
        "SELECT r.datainicio, r.datafim, c.nome AS colab_nome, c.codigo AS colab_codigo,
                IF(r.responsavel=0, r.outroresponsavel, rsp.respespaco) AS resp_trabalho
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador  c   ON c.codigo   = r.codigo
         LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo = r.responsavel
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
                 WHERE g.deqid = ?"
            );
            $qG->execute([$deqid]);
            foreach ($qG->fetchAll(PDO::FETCH_COLUMN) as $rc) {
                if ((string)$rc === $respFilter) {
                    $labsResp[] = $deqid;
                    break; // deqid já adicionado, não duplicar
                }
            }
        }
        if (!empty($labsResp)) {
            // Forçar criação mesmo que já exista Pendente anterior (apagar primeiro)
            $pdo->prepare(
                "DELETE FROM infodeqb_rds_validacao
                 WHERE registo_id=? AND resp_codigo=? AND status='Pendente'"
            )->execute([$rid, $respFilter]);
            $n = _criarValidacoes($pdo, null, $rid, $labsResp, $reg['colab_nome'], $reg['datainicio'], $reg['datafim'], $reg['colab_codigo'], $reg['resp_trabalho'] ?? '');
            $_SESSION['val_info'] = $n > 0
                ? 'Pedido de validação enviado ao responsável.'
                : 'Não foi possível enviar — sem espaços com responsável definido.';
        } else {
            $_SESSION['val_info'] = 'Nenhum espaço encontrado para este responsável.';
        }
    }
}

// ── Forçar validação de acessos ainda sem pedido enviado ──────────────
// Admin aprova directamente (sem notificar o responsável do espaço)
elseif ($acao === 'forcar_sem_pedido' && !empty($_POST['registo_id']) && !empty($_POST['resp_codigo'])) {
    $rid        = (int)$_POST['registo_id'];
    $respFilter = trim($_POST['resp_codigo']);

    // Encontrar os labs deste registo cujo responsável é $respFilter
    $todosDeqids = getRegistoAcessos($pdo, $rid);
    $labs = array();
    foreach ($todosDeqids as $deqid) {
        $qG = $pdo->prepare(
            "SELECT g.nomegab, r.Codigo AS resp_codigo
             FROM infodeqb_rds_gabinetes g
             LEFT JOIN infodeqb_rds_responsaveis r ON r.Codigo = g.responsavel
             WHERE g.deqid = ?"
        );
        $qG->execute([$deqid]);
        foreach ($qG->fetchAll(PDO::FETCH_ASSOC) as $g) {
            if ((string)$g['resp_codigo'] === $respFilter) {
                $labs[] = array('deq_id' => $deqid, 'gab_nome' => $g['nomegab']);
            }
        }
    }

    if (!empty($labs)) {
        $qResp = $pdo->prepare("SELECT respespaco FROM infodeqb_rds_responsaveis WHERE Codigo=?");
        $qResp->execute([$respFilter]);
        $respNome = $qResp->fetchColumn() ?: '';

        $firstId  = $labs[0]['deq_id'];
        $labNames = substr(implode(', ', array_column($labs, 'gab_nome')), 0, 490);
        $labsJson = json_encode($labs, JSON_UNESCAPED_UNICODE);

        $pdo->prepare(
            "INSERT INTO infodeqb_rds_validacao
             (registo_id, deq_id, gab_nome, labs_json, resp_codigo, resp_nome, token, status, nota, respondido_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Validado', 'Aprovação forçada pelo administrador', NOW())"
        )->execute([$rid, $firstId, $labNames, $labsJson, $respFilter, $respNome, bin2hex(random_bytes(16))]);

        $_SESSION['val_info'] = 'Validação aprovada manualmente pelo administrador (sem pedido enviado ao responsável).';
    } else {
        $_SESSION['val_info'] = 'Nenhum espaço encontrado para este responsável.';
    }
}

// ── Notificar SIGARRA de alteração (pedido secretariado pendente) ─────
elseif ($acao === 'notif_sigarra' && !empty($_POST['registo_id'])) {
    $rid = (int)$_POST['registo_id'];

    // Encontrar pedido secretariado Pendente mais recente para este registo
    $qPed = $pdo->prepare(
        "SELECT * FROM infodeqb_rds_pedido
         WHERE registo_id=? AND origem='secretariado' AND tipo='alteracao_sigarra' AND status='Pendente'
         ORDER BY criado_em DESC LIMIT 1"
    );
    $qPed->execute([$rid]);
    $ped = $qPed->fetch(PDO::FETCH_ASSOC);

    // Se não existe pedido (edição anterior ao novo código), criar um sintético a partir do registo actual
    if (!$ped) {
        $qReg2 = $pdo->prepare(
            "SELECT r.*, c.nome, c.email, c.codigo AS colab_codigo
             FROM infodeqb_rds_registo r
             JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
             WHERE r.autoid = ?"
        );
        $qReg2->execute([$rid]);
        $reg2 = $qReg2->fetch(PDO::FETCH_ASSOC);

        if ($reg2) {
            // Converter acessos actuais em gabids deduplicados
            $deqids2 = getRegistoAcessos($pdo, $rid);
            $qGabMap = $pdo->query("SELECT deqid, gabid FROM infodeqb_rds_gabinetes");
            $deqToGabid2 = array_column($qGabMap->fetchAll(PDO::FETCH_ASSOC), 'gabid', 'deqid');
            $gabidsCurrent = array();
            foreach ($deqids2 as $_dq) {
                $gid = isset($deqToGabid2[$_dq]) ? $deqToGabid2[$_dq] : $_dq;
                if (!in_array($gid, $gabidsCurrent, true)) {
                    $gabidsCurrent[] = $gid;
                }
            }

            $dadosJson2 = json_encode(array(
                'acessodeq'     => (int)$reg2['acessodeq'],
                'acessos'       => $deqids2,
                'acessos_nomes' => $gabidsCurrent,
            ), JSON_UNESCAPED_UNICODE);

            $pdo->prepare(
                "INSERT INTO infodeqb_rds_pedido
                 (tipo,origem,codigo,registo_id,dados_json,dados_anteriores,status)
                 VALUES ('alteracao_sigarra','secretariado',?,?,?,'{}','Pendente')"
            )->execute([$reg2['colab_codigo'], $rid, $dadosJson2]);
            $pedId2 = $pdo->lastInsertId();

            $qPed2 = $pdo->prepare("SELECT * FROM infodeqb_rds_pedido WHERE id=?");
            $qPed2->execute([$pedId2]);
            $ped = $qPed2->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($ped) {
        $d = json_decode($ped['dados_json'], true);
        if (!is_array($d)) $d = array();

        _emailSigarra($pdo, $ped, $d);

        $pdo->prepare(
            "UPDATE infodeqb_rds_pedido SET status='Aguarda_SIGARRA' WHERE id=?"
        )->execute([$ped['id']]);
        $pdo->prepare(
            "UPDATE infodeqb_rds_registo SET notif_pendente=0 WHERE autoid=?"
        )->execute([$rid]);

        $_SESSION['val_info'] = 'SIGARRA notificado com sucesso.';
    } else {
        $_SESSION['val_info'] = 'Erro: não foi possível criar pedido de notificação.';
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

// ── Forçar validação (admin aprova sem resposta do responsável) ────────
elseif ($acao === 'forcar_validacao' && !empty($_POST['val_id'])) {
    $pdo->prepare(
        "UPDATE infodeqb_rds_validacao
         SET status='Validado', respondido_em=NOW(),
             nota='Aprovação forçada pelo administrador'
         WHERE id=?"
    )->execute([(int)$_POST['val_id']]);
    $_SESSION['val_info'] = 'Validação aprovada manualmente pelo administrador.';
}

// ── Solicitar acessos ao SIGARRA (após validações todas aprovadas) ──────
elseif ($acao === 'solicitar_acessos' && !empty($_POST['registo_id'])) {
    $rid = (int)$_POST['registo_id'];

    // 1. Bloquear se houver validações com status não-final (Pendente, Rejeitado)
    $qValPend = $pdo->prepare(
        "SELECT COUNT(*) FROM infodeqb_rds_validacao
         WHERE registo_id=? AND status NOT IN ('Validado','Cancelado')"
    );
    $qValPend->execute([$rid]);
    if ((int)$qValPend->fetchColumn() > 0) {
        $_SESSION['val_info'] = 'Existem validações pendentes ou rejeitadas. Resolva-as antes de pedir acessos.';
        header('Location: ' . $redirect);
        exit;
    }

    // 2. Bloquear se existirem labs com responsável mas sem qualquer pedido de validação
    $qSemVal = $pdo->prepare(
        "SELECT COUNT(*)
         FROM infodeqb_rds_registo_acessos ra
         JOIN infodeqb_rds_gabinetes g ON g.deqid = ra.lab_id
         WHERE ra.registo_id = ?
           AND g.responsavel IS NOT NULL AND g.responsavel != 0
           AND g.responsavel != 246398
           AND NOT EXISTS (
               SELECT 1 FROM infodeqb_rds_validacao v
               WHERE v.registo_id = ra.registo_id AND v.resp_codigo = g.responsavel
           )"
    );
    $qSemVal->execute([$rid]);
    if ((int)$qSemVal->fetchColumn() > 0) {
        $_SESSION['val_info'] = 'Existem laboratórios sem validação solicitada. Solicite validações antes de pedir acessos.';
        header('Location: ' . $redirect);
        exit;
    }

    // Carregar registo + colaborador
    $qReg = $pdo->prepare(
        "SELECT r.*, c.nome, c.email,
                IF(r.responsavel=0, r.outroresponsavel, resp.respespaco) AS responsavel_nome
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
        $subject = 'Acessos DEQB: Solicitação de novos acessos';
        $to      = array('sigarra@fe.up.pt');
        $cc      = array('deqdir@fe.up.pt', 'fmartins@fe.up.pt', 'fpereira@fe.up.pt');

        try {
            send_email($to, $body, $subject, $cc);
            $pdo->prepare(
                "UPDATE infodeqb_rds_registo SET status='Pendente', datacica=NOW(), notif_pendente=0 WHERE autoid=?"
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
