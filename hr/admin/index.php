<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado


if (! empty($_POST)) {

    if (isset($_POST["ExportType"])) {

        $pdo = Database::connect();
        $sth = $pdo->prepare(
                'SELECT infodeqb_rds_colaborador.codigo, infodeqb_rds_colaborador.nome, infodeqb_rds_colaborador.email,infodeqb_rds_colaborador.telefone, infodeqb_rds_registo.datafim, infodeqb_rds_registo.datainicio, infodeqb_rds_registo.codigo, infodeqb_rds_grupo.grupo_pro  FROM infodeqb_rds_colaborador INNER JOIN infodeqb_rds_registo ON infodeqb_rds_colaborador.codigo=infodeqb_rds_registo.codigo INNER JOIN infodeqb_rds_grupo ON infodeqb_rds_registo.grupo=infodeqb_rds_grupo.grupoid where infodeqb_rds_colaborador.deleted!=1 AND infodeqb_rds_registo.status= "Ativo" ORDER BY infodeqb_rds_colaborador.nome ASC');

        $sth->execute();

        $data = $sth->fetchAll(PDO::FETCH_ASSOC);
        $filename = 'Registos_Ativos' . ".xls";
        header("Content-Type: application/vnd.ms-excel;");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        ob_end_clean();
        ExportFile($data);
    }

    // ── Ações em massa: solicitar validações / pedir acessos ──────────────
    if (isset($_POST['selector']) && is_array($_POST['selector']) &&
        in_array($_POST['action'] ?? '', array('solicitar_validacoes_massa','pedir_acessos_massa'))) {

        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $autoids   = array_map('intval', array_keys($_POST['selector']));
        $nEnviados = 0; $nIgnorados = 0;

        foreach ($autoids as $autoid) {

            if ($_POST['action'] === 'solicitar_validacoes_massa') {
                $qReg = $pdo->prepare(
                    "SELECT r.datainicio, r.datafim, r.acessosid, c.nome AS colab_nome
                     FROM infodeqb_rds_registo r JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
                     WHERE r.autoid = ? AND r.status = 'Novo'"
                );
                $qReg->execute([$autoid]);
                $reg = $qReg->fetch(PDO::FETCH_ASSOC);
                if (!$reg) { $nIgnorados++; continue; }

                $deqids = getRegistoAcessos($pdo, (int)$autoid);
                if (empty($deqids)) { $nIgnorados++; continue; }

                $nEnviados += _criarValidacoes(
                    $pdo, null, $autoid, $deqids,
                    $reg['colab_nome'], $reg['datainicio'], $reg['datafim']
                );

            } else { // pedir_acessos_massa
                // Só avança se todas as validações estiverem concluídas (ou não houver nenhuma)
                $qChk = $pdo->prepare(
                    "SELECT COUNT(*) AS total, SUM(status='Validado') AS ok
                     FROM infodeqb_rds_validacao WHERE registo_id=?"
                );
                $qChk->execute([$autoid]);
                $chk = $qChk->fetch(PDO::FETCH_ASSOC);
                if ((int)$chk['total'] > 0 && (int)$chk['ok'] < (int)$chk['total']) {
                    $nIgnorados++; continue;
                }

                $qReg = $pdo->prepare(
                    "SELECT r.*, c.nome, c.email,
                            COALESCE(resp.respespaco, r.outroresponsavel) AS responsavel_nome
                     FROM infodeqb_rds_registo r JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
                     LEFT JOIN infodeqb_rds_responsaveis resp ON resp.codigo = r.responsavel
                     WHERE r.autoid = ? AND r.status = 'Novo'"
                );
                $qReg->execute([$autoid]);
                $reg = $qReg->fetch(PDO::FETCH_ASSOC);
                if (!$reg) { $nIgnorados++; continue; }

                $qGabs   = $pdo->query("SELECT gabid, deqid FROM infodeqb_rds_gabinetes");
                $colVals = array_column($qGabs->fetchAll(PDO::FETCH_ASSOC), 'gabid', 'deqid');
                $deqidsMassa = getRegistoAcessos($pdo, (int)$autoid);
                $deqArr    = array_flip($deqidsMassa);
                $gabStr    = str_replace('|', ';', implode(';', array_unique(array_intersect_key($colVals, $deqArr))));

                $info = array(
                    'codigo'      => $reg['codigo'],
                    'nome'        => $reg['nome'],
                    'mail'        => $reg['email'],
                    'fim'         => $reg['datafim'],
                    'responsavel' => $reg['responsavel_nome'] ?? '',
                    'acessos'     => ($reg['acessodeq'] == 1 ? 'Porta Norte; ' : '') . $gabStr,
                    'acessosdeqid'=> ($reg['acessodeq'] == 1 ? 'Porta Norte; ' : '') . ($reg['acessos'] ?? ''),
                );
                $body = format_email($info, 'mail_pedido.html');
                try {
                    send_email(
                        array('sigarra@fe.up.pt'),
                        $body,
                        'Acessos DEQ: Solicitação de novos acessos',
                        array('deqdir@fe.up.pt','fmartins@fe.up.pt','fpereira@fe.up.pt')
                    );
                    $pdo->prepare("UPDATE infodeqb_rds_registo SET status='Pendente', datacica=NOW() WHERE autoid=?")
                        ->execute([$autoid]);
                    $nEnviados++;
                } catch (Exception $e) {
                    error_log('HR pedir_acessos_massa falhou autoid=' . $autoid . ': ' . $e->getMessage());
                    $nIgnorados++;
                }
            }
        }

        if ($_POST['action'] === 'solicitar_validacoes_massa') {
            $_SESSION['val_info'] = 'Pedidos de validação enviados: ' . $nEnviados . ' email(s).'
                . ($nIgnorados ? ' ' . $nIgnorados . ' registo(s) sem espaços/responsáveis ignorados.' : '');
        } else {
            $_SESSION['val_info'] = 'Pedidos enviados ao SIGARRA: ' . $nEnviados . '.'
                . ($nIgnorados ? ' ' . $nIgnorados . ' registo(s) com validações pendentes ignorados.' : '');
        }
        header('Location: index.php'); exit;
    }

    if (isset($_POST['selector']) && is_array($_POST['selector'])) try {

        $timestamp = date('Y-m-d H:i:s');

        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $_SESSION['ids'] = array_intersect_key(
            $_POST['codigo'],
            $_POST['selector']
            );

        foreach ($_POST['selector'] as $id1 => $id1value):

        switch ($_POST['action']) {
            case 'Inativo':
                $sql = 'UPDATE infodeqb_rds_registo SET status = "Inativo", datainativo = ? WHERE autoid = ?';
                break;

            case 'Ativo':
                $sql = 'UPDATE infodeqb_rds_registo SET status = "Ativo", dataativo = ? WHERE autoid = ?';
                break;

            case 'notify':
                $sql = 'UPDATE infodeqb_rds_registo SET datanotificacao = ? WHERE autoid = ?';
                break;

            case 'Pendente':
                $sql = 'UPDATE infodeqb_rds_registo SET status = "Ativo", dataativo = ? WHERE autoid = ?';
                break;
        }

        $q = $pdo->prepare($sql);
        $q->execute([$timestamp, $id1]);

        // Ao activar: se o registo tem substitui_registo → inativar o registo anterior
        if ($_POST['action'] === 'Ativo') {
            $chkSubst = $pdo->prepare('SELECT substitui_registo FROM infodeqb_rds_registo WHERE autoid = ?');
            $chkSubst->execute([$id1]);
            $substId = $chkSubst->fetchColumn();
            if ($substId) {
                $pdo->prepare('UPDATE infodeqb_rds_registo SET status = "Inativo", datainativo = ? WHERE autoid = ?')
                    ->execute([$timestamp, $substId]);
            }
        }

        endforeach;

        // guardar a ação na sessão
        $_SESSION['action'] = $_POST['action'];

        // enviar email apenas nestas ações:
        if (
            $_POST['action'] === 'Ativo' ||
            $_POST['action'] === 'Pendente' ||
            $_POST['action'] === 'notify'
            ) {
                Header('Location: ./email.php');
                exit;
            }

    } catch (PDOException $e) {
        echo $sql . "<br>" . $e->getMessage();
    }

}

$pdo = Database::connect();
$sth = $pdo->prepare(
        'SELECT infodeqb_rds_colaborador.codigo, infodeqb_rds_colaborador.nome, infodeqb_rds_colaborador.email,infodeqb_rds_registo.autoid, infodeqb_rds_registo.datafim, infodeqb_rds_registo.datainicio, infodeqb_rds_registo.codigo,infodeqb_rds_registo.status, infodeqb_rds_grupo.grupo_pro, infodeqb_rds_registo.createdate, infodeqb_rds_registo.datacica, infodeqb_rds_registo.dataativo, infodeqb_rds_registo.datainativo FROM infodeqb_rds_colaborador INNER JOIN infodeqb_rds_registo ON infodeqb_rds_colaborador.codigo=infodeqb_rds_registo.codigo INNER JOIN infodeqb_rds_grupo ON infodeqb_rds_registo.grupo=infodeqb_rds_grupo.grupoid where infodeqb_rds_colaborador.deleted!=1 AND infodeqb_rds_registo.deleted!=1 AND infodeqb_rds_registo.status= ? ORDER BY infodeqb_rds_registo.createdate ASC');

$sth_expire = $pdo->prepare(
        'SELECT infodeqb_rds_colaborador.codigo, infodeqb_rds_colaborador.nome, infodeqb_rds_colaborador.email, infodeqb_rds_registo.autoid, infodeqb_rds_registo.datafim, infodeqb_rds_registo.datanotificacao, infodeqb_rds_registo.codigo,infodeqb_rds_registo.status, infodeqb_rds_grupo.grupo_pro FROM infodeqb_rds_colaborador INNER JOIN infodeqb_rds_registo ON infodeqb_rds_colaborador.codigo=infodeqb_rds_registo.codigo INNER JOIN infodeqb_rds_grupo ON infodeqb_rds_registo.grupo=infodeqb_rds_grupo.grupoid where infodeqb_rds_colaborador.deleted!=1 AND infodeqb_rds_registo.status= ? AND infodeqb_rds_registo.datafim <= ?  ORDER BY infodeqb_rds_grupo.grupoid, infodeqb_rds_colaborador.nome ASC');

$data = $sth->fetchAll(PDO::FETCH_ASSOC);
$sth->execute([
        'Novo'
]);
$newrecords = $sth->rowCount();
$sth->execute([
        'Pendente'
]);
$pendentrecords = $sth->rowCount();
$sth->execute([
        'Ativo'
]);
$activerecords = $sth->rowCount();
$atual = date("Y/m/d");
$final = date("Y/m/d", strtotime("+1 month"));
$sth_expire->execute([
        'Ativo',
        $final
]);
$expirerecords = $sth_expire->rowCount();

// Pedidos de utilizadores — pendentes (requerem ação do admin)
$sth_pedidos = $pdo->prepare(
    "SELECT p.*, c.nome AS nome_colab
     FROM infodeqb_rds_pedido p
     LEFT JOIN infodeqb_rds_colaborador c ON c.codigo = p.codigo
     WHERE p.status = 'Pendente'
     ORDER BY p.criado_em ASC"
);
$sth_pedidos->execute();
$pedidos      = $sth_pedidos->fetchAll(PDO::FETCH_ASSOC);
$pedidosCount = count($pedidos);

// Pedidos que aguardam confirmação do SIGARRA
$sth_aguarda = $pdo->prepare(
    "SELECT p.*, c.nome AS nome_colab
     FROM infodeqb_rds_pedido p
     LEFT JOIN infodeqb_rds_colaborador c ON c.codigo = p.codigo
     WHERE p.status = 'Aguarda_SIGARRA'
     ORDER BY p.processado_em ASC"
);
$sth_aguarda->execute();
$pedidosAguarda      = $sth_aguarda->fetchAll(PDO::FETCH_ASSOC);
$pedidosAguardaCount = count($pedidosAguarda);

// Carregar validações existentes para todos os pedidos Pendentes
$valDetails = array();
if (!empty($pedidos)) {
    $pids = array_map('intval', array_column($pedidos, 'id'));
    $phs  = implode(',', array_fill(0, count($pids), '?'));
    $qVals = $pdo->prepare(
        "SELECT id, pedido_id, deq_id, gab_nome, resp_codigo, resp_nome,
                status, nota, respondido_em
         FROM infodeqb_rds_validacao WHERE pedido_id IN ($phs) ORDER BY id"
    );
    $qVals->execute($pids);
    foreach ($qVals->fetchAll(PDO::FETCH_ASSOC) as $vr) {
        $valDetails[$vr['pedido_id']][] = $vr;
    }
}

// ── Dados para o filtro por laboratório ──────────────────────────────
$labsMap = array();  // registo_id → [lab_ids]
foreach ($pdo->query('SELECT registo_id, lab_id FROM infodeqb_rds_registo_acessos')
              ->fetchAll(PDO::FETCH_ASSOC) as $_lr) {
    $labsMap[(int)$_lr['registo_id']][] = $_lr['lab_id'];
}
$gabFilterByPiso = array(); // piso → [ {deqid, nomegab} ]
$labIdToNameMap  = array(); // deqid → nomegab
foreach ($pdo->query('SELECT deqid, nomegab, piso FROM infodeqb_rds_gabinetes WHERE visible != 0 ORDER BY piso, nomegab')
              ->fetchAll(PDO::FETCH_ASSOC) as $_gf) {
    $gabFilterByPiso[$_gf['piso']][] = $_gf;
    $labIdToNameMap[$_gf['deqid']]   = $_gf['nomegab'];
}
// Helper: atributo data-labs="..." para uma linha (string vazia se sem labs)
$getLabsAttr = function($autoid) use ($labsMap) {
    $labs = isset($labsMap[(int)$autoid]) ? $labsMap[(int)$autoid] : array();
    return $labs ? ' data-labs="' . htmlspecialchars(implode(';', $labs)) . '"' : '';
};

// ── Helper: enviar email de alteração ao SIGARRA ──────────────────
function _emailSigarra($pdo, $ped, $d) {
    $regRow = $pdo->prepare(
        'SELECT r.*, c.nome, c.email, c.emailalt,
                COALESCE(rsp.respespaco, r.outroresponsavel) AS resp_nome
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
         LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo = r.responsavel
         WHERE r.autoid = ?'
    );
    $regRow->execute([$ped['registo_id']]);
    $reg = $regRow->fetch(PDO::FETCH_ASSOC);
    if (!$reg) return;

    // Determinar datafim a mostrar (nova se mudou, actual caso contrário)
    $datafim = isset($d['datafim_novo']) ? $d['datafim_novo'] : $reg['datafim'];

    // Construir bloco HTML dinâmico de alterações
    $tdStyle  = 'padding:8px;font-family:Arial,sans-serif;font-size:14px;';
    $thStyle  = 'padding:8px;font-family:Arial,sans-serif;font-size:14px;background:#2475ba;color:#fff;text-align:left;';
    $alteracoes = '';

    // ── Alteração de data de fim ──────────────────────────────────
    if (isset($d['datafim_novo'])) {
        $alteracoes .= '<h3 style="font-family:Arial,sans-serif;font-size:15px;margin:0 0 8px 0;color:#153643;">Alteração de Data de Fim</h3>';
        $alteracoes .= '<table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:20px;">';
        $alteracoes .= '<tr><th style="' . $thStyle . 'width:160px;">Data anterior</th><th style="' . $thStyle . '">Nova data</th></tr>';
        $alteracoes .= '<tr>';
        $alteracoes .= '<td style="' . $tdStyle . 'color:#999;text-decoration:line-through;">' . htmlspecialchars($d['datafim_antigo'] ?? '—') . '</td>';
        $alteracoes .= '<td style="' . $tdStyle . 'font-weight:bold;">'                        . htmlspecialchars($d['datafim_novo']   ?? '—') . '</td>';
        $alteracoes .= '</tr></table>';
    }

    // ── Alteração de acessos a labs ───────────────────────────────
    $addNomes = array_filter((array)($d['labs_adicionados_nomes'] ?? $d['labs_adicionados'] ?? array()));
    $remNomes = array_filter((array)($d['labs_removidos_nomes']   ?? $d['labs_removidos']   ?? array()));
    $dadosAnt = json_decode($ped['dados_anteriores'] ?? 'null', true) ?: array();
    $oldDeq   = isset($dadosAnt['acessodeq']) ? (int)$dadosAnt['acessodeq'] : null;
    $newDeq   = isset($d['acessodeq'])         ? (int)$d['acessodeq']        : null;
    $deqMudou = $oldDeq !== null && $newDeq !== null && $oldDeq !== $newDeq;

    if (!empty($addNomes) || !empty($remNomes) || $deqMudou) {
        $alteracoes .= '<h3 style="font-family:Arial,sans-serif;font-size:15px;margin:0 0 8px 0;color:#153643;">Alteração de Acessos</h3>';
        $alteracoes .= '<table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;margin-bottom:20px;">';
        $alteracoes .= '<tr><th style="' . $thStyle . 'width:50%;">Adicionar</th><th style="' . $thStyle . '">Remover</th></tr>';
        $addCell = !empty($addNomes)
            ? implode('', array_map(function($n) use ($tdStyle) {
                return '<span style="display:inline-block;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:3px;padding:2px 8px;margin:2px;font-size:13px;">+ ' . htmlspecialchars($n) . '</span>';
              }, $addNomes))
            : '<span style="color:#999;">—</span>';
        $remCell = !empty($remNomes)
            ? implode('', array_map(function($n) use ($tdStyle) {
                return '<span style="display:inline-block;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:3px;padding:2px 8px;margin:2px;font-size:13px;">- ' . htmlspecialchars($n) . '</span>';
              }, $remNomes))
            : '<span style="color:#999;">—</span>';
        $alteracoes .= '<tr><td style="' . $tdStyle . '">' . $addCell . '</td><td style="' . $tdStyle . '">' . $remCell . '</td></tr>';
        if ($deqMudou) {
            $alteracoes .= '<tr><td colspan="2" style="' . $tdStyle . 'color:#555;">Acesso DEQ: '
                         . ($oldDeq ? 'Sim' : 'Não') . ' → <strong>' . ($newDeq ? 'Sim' : 'Não') . '</strong></td></tr>';
        }
        // Lista completa
        $listaFull = isset($d['acessos_nomes']) ? implode('; ', $d['acessos_nomes']) : ($reg['acessos'] ?? '');
        if ($listaFull) {
            $alteracoes .= '<tr><td colspan="2" style="' . $tdStyle . 'color:#555;font-size:13px;">'
                         . '<strong>Lista completa após atualização:</strong> ' . htmlspecialchars($listaFull) . '</td></tr>';
        }
        $alteracoes .= '</table>';
    }

    $info = array(
        'codigo'       => $reg['codigo'],
        'nome'         => $reg['nome'],
        'fim'          => $datafim,
        'responsavel'  => $reg['resp_nome'] ?? '—',
        'alteracoes'   => $alteracoes ?: '<p style="color:#555;font-family:Arial,sans-serif;">Sem alterações de detalhe disponíveis.</p>',
    );
    $body    = format_email($info, 'mail_alteracao_sigarra.html');
    $subject = 'Acessos DEQ: Atualização de acessos — ' . $reg['nome'];
    try {
        send_email(
            array('sigarra@fe.up.pt'),
            $body, $subject,
            array('deqdir@fe.up.pt','fmartins@fe.up.pt')
        );
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('HR pedido email SIGARRA falhou id=' . $ped['id'] . ': ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('HR pedido email SIGARRA falhou id=' . $ped['id'] . ': ' . $e->getMessage());
    }
}

// ── Helper: enviar email de rejeição ao utilizador ─────────────────
function _emailRejeicao($pdo, $ped, $notas) {
    $colRow = $pdo->prepare('SELECT nome, email FROM infodeqb_rds_colaborador WHERE codigo=?');
    $colRow->execute([$ped['codigo']]);
    $col = $colRow->fetch(PDO::FETCH_ASSOC);
    if (!$col) return;

    $tipos = array(
        'alteracao_labs'    => 'alteração de acessos a laboratórios',
        'alteracao_datafim' => 'alteração de data de fim',
        'alteracao'         => 'alteração de dados',
        'novo'              => 'novo registo',
        'novo_registo'      => 'novo registo',
    );
    $detalhe = $tipos[$ped['tipo']] ?? $ped['tipo'];
    $info = array(
        'nome'    => $col['nome'],
        'detalhe' => $detalhe,
        'nota'    => $notas ?: 'Sem nota adicional.',
    );
    $body = format_email($info, 'mail_alteracao_rejeitada.html');
    try {
        send_email(
            array($col['email']),
            $body,
            'Acessos DEQ: Pedido não aprovado',
            array('deqdir@fe.up.pt','fmartins@fe.up.pt')
        );
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('HR pedido email rejeição falhou id=' . $ped['id'] . ': ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('HR pedido email rejeição falhou id=' . $ped['id'] . ': ' . $e->getMessage());
    }
}

// ── Helper: enviar email de conclusão ao utilizador ────────────────
function _emailConclusao($pdo, $ped) {
    $regRow = $pdo->prepare(
        'SELECT r.datafim, r.acessodeq, r.acessos, c.nome, c.email
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
         WHERE r.autoid = ?'
    );
    $regRow->execute([$ped['registo_id']]);
    $reg = $regRow->fetch(PDO::FETCH_ASSOC);
    if (!$reg) return;

    $tipos = array(
        'alteracao_labs'    => 'alteração de acessos a laboratórios',
        'alteracao_datafim' => 'renovação / alteração de data de fim',
    );
    $detalhe = $tipos[$ped['tipo']] ?? $ped['tipo'];
    $acessos = ($reg['acessodeq'] == 1 ? 'Porta Norte; ' : '') . ($reg['acessos'] ?? '');
    $info = array(
        'nome'    => $reg['nome'],
        'detalhe' => $detalhe,
        'acessos' => $acessos ?: '—',
        'fim'     => $reg['datafim'],
    );
    $body = format_email($info, 'mail_alteracao_concluida.html');
    try {
        send_email(
            array($reg['email']),
            $body,
            'Acessos DEQ: Acessos atualizados',
            array('deqdir@fe.up.pt','fmartins@fe.up.pt')
        );
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('HR pedido email conclusão falhou id=' . $ped['id'] . ': ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('HR pedido email conclusão falhou id=' . $ped['id'] . ': ' . $e->getMessage());
    }
}

// Processar aprovação/rejeição/conclusão de pedidos
if (!empty($_POST['pedido_action']) && !empty($_POST['pedido_id'])) {
    $pid   = (int)$_POST['pedido_id'];
    $acao  = $_POST['pedido_action'];
    $notas = trim($_POST['notas_admin'] ?? '');
    if (in_array($acao, array('Aprovado','Rejeitado','Concluido'))) {

        $pedRow = $pdo->prepare("SELECT * FROM infodeqb_rds_pedido WHERE id=?");
        $pedRow->execute([$pid]);
        $ped = $pedRow->fetch(PDO::FETCH_ASSOC);

        if ($ped) {
            $d = json_decode($ped['dados_json'], true) ?: array();

            // ── Tipos que requerem notificação SIGARRA ────────────
            $requerSigarra = in_array($ped['tipo'], array('alteracao_labs','alteracao_datafim','alteracao_sigarra'));

            if ($acao === 'Aprovado') {

                // Bloquear aprovação se existirem validações por responder
                $tiposComLabs = array('novo','novo_registo','alteracao_sigarra','alteracao_labs');
                if (in_array($ped['tipo'], $tiposComLabs)) {
                    $qBlock = $pdo->prepare(
                        "SELECT COUNT(*) FROM infodeqb_rds_validacao
                         WHERE pedido_id=? AND status IN ('Pendente','Rejeitado')"
                    );
                    $qBlock->execute([$pid]);
                    if ((int)$qBlock->fetchColumn() > 0) {
                        $_SESSION['pedido_err'] = 'Existem validações de espaço por responder (pendentes ou rejeitadas). Aguarde a resposta dos responsáveis ou gira as validações antes de aprovar.';
                        header('Location: index.php'); exit;
                    }
                }

                $mkAcessos = function($d) {
                    return implode('; ', array_filter((array)($d['acessos'] ?? array())));
                };
                $insReg = $pdo->prepare(
                    "INSERT INTO infodeqb_rds_registo
                     (codigo,datainicio,datafim,responsavel,outroresponsavel,acessodeq,grupo,categoria,
                      acessos,acessosid,curso,createdate,dataregisto,status,unidade,local_trabalho,extensao)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Novo',?,?,?)"
                );

                $gabMapAprov = getGabMap($pdo);

                if ($ped['tipo'] === 'novo') {
                    // ── Primeiro registo da pessoa ─────────────────
                    $chkCol = $pdo->prepare("SELECT 1 FROM infodeqb_rds_colaborador WHERE codigo=?");
                    $chkCol->execute([$d['codigo']]);
                    if (!$chkCol->fetchColumn()) {
                        $pdo->prepare(
                            "INSERT INTO infodeqb_rds_colaborador (codigo,nome,email,emailalt,telefone,createdate) VALUES (?,?,?,?,?,?)"
                        )->execute([$d['codigo'],$d['nome'],$d['email'],$d['emailalt']??'',$d['telefone']??'',time()]);
                    }
                    $ac = $mkAcessos($d);
                    $insReg->execute([
                        $d['codigo'],$d['datainicio'],$d['datafim'],
                        $d['responsavel']??0,$d['outroresponsavel']??'',$d['acessodeq']??0,
                        $d['grupo'],$d['categoria'],$ac,$ac,$d['curso']??'',
                        time(),date('Y-m-d H:i:s'),$d['unidade']??'',$d['workplace']??'',$d['extensao']??''
                    ]);
                    $novoRegIdAprov = (int)$pdo->lastInsertId();
                    if ($novoRegIdAprov > 0) {
                        setRegistoAcessos($pdo, $novoRegIdAprov, array_filter((array)($d['acessos'] ?? array())), $gabMapAprov);
                    }
                    $pdo->prepare("UPDATE infodeqb_rds_pedido SET status='Aprovado', processado_em=NOW(), processado_por=?, notas_admin=? WHERE id=?")
                        ->execute([$_SESSION['Code']??'', $notas?:null, $pid]);

                } elseif ($ped['tipo'] === 'alteracao' && $ped['registo_id']) {
                    // ── Actualizar registo existente ───────────────
                    $ac = $mkAcessos($d);
                    $pdo->prepare(
                        "UPDATE infodeqb_rds_registo
                         SET datainicio=?,datafim=?,responsavel=?,outroresponsavel=?,acessodeq=?,
                             grupo=?,categoria=?,acessos=?,acessosid=?,curso=?,
                             unidade=?,local_trabalho=?,extensao=?
                         WHERE autoid=?"
                    )->execute([
                        $d['datainicio'],$d['datafim'],
                        $d['responsavel']??0,$d['outroresponsavel']??'',$d['acessodeq']??0,
                        $d['grupo'],$d['categoria'],$ac,$ac,$d['curso']??'',
                        $d['unidade']??'',$d['workplace']??'',$d['extensao']??'',
                        $ped['registo_id']
                    ]);
                    setRegistoAcessos($pdo, (int)$ped['registo_id'], array_filter((array)($d['acessos'] ?? array())), $gabMapAprov);
                    $pdo->prepare("UPDATE infodeqb_rds_pedido SET status='Aprovado', processado_em=NOW(), processado_por=?, notas_admin=? WHERE id=?")
                        ->execute([$_SESSION['Code']??'', $notas?:null, $pid]);

                } elseif ($ped['tipo'] === 'novo_registo') {
                    // ── Novo registo + inativar o actual ───────────
                    if ($ped['registo_id']) {
                        $pdo->prepare("UPDATE infodeqb_rds_registo SET status='Inativo', datainativo=NOW() WHERE autoid=?")
                            ->execute([$ped['registo_id']]);
                    }
                    $ac = $mkAcessos($d);
                    $insReg->execute([
                        $d['codigo'],$d['datainicio'],$d['datafim'],
                        $d['responsavel']??0,$d['outroresponsavel']??'',$d['acessodeq']??0,
                        $d['grupo'],$d['categoria'],$ac,$ac,$d['curso']??'',
                        time(),date('Y-m-d H:i:s'),$d['unidade']??'',$d['workplace']??'',$d['extensao']??''
                    ]);
                    $novoRegIdAprov = (int)$pdo->lastInsertId();
                    if ($novoRegIdAprov > 0) {
                        setRegistoAcessos($pdo, $novoRegIdAprov, array_filter((array)($d['acessos'] ?? array())), $gabMapAprov);
                    }
                    $pdo->prepare("UPDATE infodeqb_rds_pedido SET status='Aprovado', processado_em=NOW(), processado_por=?, notas_admin=? WHERE id=?")
                        ->execute([$_SESSION['Code']??'', $notas?:null, $pid]);

                } elseif (in_array($ped['tipo'], array('alteracao_sigarra','alteracao_labs','alteracao_datafim')) && $ped['registo_id']) {
                    // ── Aprovar alteração SIGARRA (unificada ou legacy) ──────
                    // Aplicar datafim se presente
                    if (!empty($d['datafim_novo'])) {
                        $pdo->prepare("UPDATE infodeqb_rds_registo SET datafim=? WHERE autoid=?")
                            ->execute([$d['datafim_novo'], $ped['registo_id']]);
                    }
                    // Aplicar labs se presente
                    if (isset($d['acessos'])) {
                        $newDeqids  = array_filter((array)$d['acessos']);
                        $acessosid  = implode('; ', $newDeqids);
                        $newNomes   = array_filter((array)($d['acessos_nomes'] ?? $newDeqids));
                        $acessosStr = implode('; ', $newNomes);
                        $pdo->prepare("UPDATE infodeqb_rds_registo SET acessodeq=?, acessos=?, acessosid=? WHERE autoid=?")
                            ->execute([$d['acessodeq'] ?? 0, $acessosStr, $acessosid, $ped['registo_id']]);
                        setRegistoAcessos($pdo, (int)$ped['registo_id'], $newDeqids, $gabMapAprov);
                    }
                    _emailSigarra($pdo, $ped, $d);
                    $pdo->prepare("UPDATE infodeqb_rds_pedido SET status='Aguarda_SIGARRA', processado_em=NOW(), processado_por=?, notas_admin=? WHERE id=?")
                        ->execute([$_SESSION['Code']??'', $notas?:null, $pid]);
                }

            } elseif ($acao === 'Rejeitado') {
                // ── Rejeitar → email ao utilizador ────────────────
                if ($requerSigarra) {
                    _emailRejeicao($pdo, $ped, $notas);
                }
                $pdo->prepare("UPDATE infodeqb_rds_pedido SET status='Rejeitado', processado_em=NOW(), processado_por=?, notas_admin=? WHERE id=?")
                    ->execute([$_SESSION['Code']??'', $notas?:null, $pid]);

            } elseif ($acao === 'Concluido') {
                // ── SIGARRA confirmou → email ao utilizador ───────
                _emailConclusao($pdo, $ped);
                $pdo->prepare("UPDATE infodeqb_rds_pedido SET status='Concluido', processado_em=NOW(), processado_por=?, notas_admin=? WHERE id=?")
                    ->execute([$_SESSION['Code']??'', $notas?:null, $pid]);
            }
        }
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Administração — Colaboradores';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

				<div class="iq-page-header">
				  <h1><i class="fas fa-id-card fa-sm me-2 text-muted"></i>Investigadores e Colaboradores</h1>
				</div>

				<div class="iq-stat-grid">
				  <div class="iq-stat iq-stat-green">
				    <div class="iq-stat-icon"><i class="fas fa-file-alt"></i></div>
				    <div>
				      <div class="iq-stat-value"><?php echo $newrecords; ?></div>
				      <div class="iq-stat-label"><?= t('DASH_NEW_RECORDS') ?></div>
				    </div>
				  </div>
				  <div class="iq-stat iq-stat-yellow">
				    <div class="iq-stat-icon"><i class="fas fa-clock"></i></div>
				    <div>
				      <div class="iq-stat-value"><?php echo $pendentrecords; ?></div>
				      <div class="iq-stat-label"><?= t('DASH_PENDING') ?></div>
				    </div>
				  </div>
				  <div class="iq-stat iq-stat-red">
				    <div class="iq-stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
				    <div>
				      <div class="iq-stat-value"><?php echo $expirerecords; ?></div>
				      <div class="iq-stat-label"><?= t('DASH_EXPIRING') ?></div>
				    </div>
				  </div>
				  <div class="iq-stat iq-stat-blue">
				    <div class="iq-stat-icon"><i class="fas fa-users"></i></div>
				    <div>
				      <div class="iq-stat-value"><?php echo $activerecords; ?></div>
				      <div class="iq-stat-label"><?= t('DASH_ACTIVE') ?></div>
				    </div>
				  </div>
				</div>

				<div class="card mb-3">
					<div class="card-header">
						<i class="far fa-id-card"></i> Investigadores/Colaboradores DEQ
					</div>
					<div class="card-body">
						<!-- page content -->
						<?php if (!empty($_SESSION['val_info'])): ?>
				<div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
				  <i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($_SESSION['val_info']) ?>
				  <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
				</div>
				<?php unset($_SESSION['val_info']); endif; ?>
				<?php if (!empty($_SESSION['pedido_err'])): ?>
				<div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
				  <i class="fas fa-exclamation-triangle me-1"></i><?= htmlspecialchars($_SESSION['pedido_err']) ?>
				  <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
				</div>
				<?php unset($_SESSION['pedido_err']); endif; ?>
				<!-- ── Filtro por laboratório ──────────────────────────────────────── -->
<div class="mb-3 p-2 rounded border bg-light" id="lab-filter-bar" style="font-size:.84rem">
  <div class="d-flex align-items-center flex-wrap" style="gap:8px">

    <!-- Etiqueta -->
    <span style="white-space:nowrap;font-weight:600">
      <i class="fas fa-filter fa-sm text-muted me-1"></i>Filtro por laboratório:
    </span>

    <!-- Botão que abre o painel (não é Bootstrap dropdown) -->
    <div style="position:relative">
      <button type="button" id="labPanelBtn"
              class="btn btn-sm btn-outline-secondary"
              style="min-width:220px;text-align:left">
        <i class="fas fa-flask fa-xs me-1 text-muted"></i>
        <span id="labBtnLabel">Todos os laboratórios</span>
        <i class="fas fa-chevron-down fa-xs ms-1 text-muted" style="float:right;margin-top:3px"></i>
      </button>

      <!-- Painel custom — fica aberto até o utilizador clicar Aplicar/Fechar/fora -->
      <div id="labPanel" style="display:none;position:absolute;top:calc(100% + 4px);left:0;
           z-index:1060;background:#fff;border:1px solid #ced4da;border-radius:6px;
           width:320px;box-shadow:0 6px 16px rgba(0,0,0,.15)">

        <!-- Pesquisa -->
        <div style="padding:8px 10px;border-bottom:1px solid #dee2e6">
          <input type="text" id="labSearchInput" class="form-control form-control-sm"
                 placeholder="Pesquisar laboratório...">
        </div>

        <!-- Checkboxes agrupados por piso -->
        <div id="labCheckList" style="max-height:250px;overflow-y:auto;padding:8px 10px">
<?php foreach ($gabFilterByPiso as $fpiso => $fgabs): ?>
          <div class="lab-group">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;
                        color:#6c757d;letter-spacing:.04em;
                        border-bottom:1px solid #dee2e6;padding-bottom:2px;margin-bottom:3px;margin-top:6px">
              <?= htmlspecialchars($fpiso) ?>
            </div>
<?php foreach ($fgabs as $fg): ?>
            <div class="lab-item" style="padding:2px 0">
              <label style="margin:0;font-weight:normal;cursor:pointer;
                            display:flex;align-items:center;gap:6px;font-size:.83rem">
                <input type="checkbox" class="lab-chk" value="<?= htmlspecialchars($fg['deqid']) ?>">
                <span style="flex:1"><?= htmlspecialchars($fg['nomegab']) ?></span>
                <small style="color:#adb5bd;font-size:.7rem;flex-shrink:0"><?= htmlspecialchars($fg['deqid']) ?></small>
              </label>
            </div>
<?php endforeach; ?>
          </div>
<?php endforeach; ?>
        </div>

        <!-- Rodapé: links rápidos + botões Cancelar / Aplicar -->
        <div style="padding:7px 10px;border-top:1px solid #dee2e6;
                    display:flex;align-items:center;gap:8px">
          <a href="#" id="labSelectAll"  style="font-size:.78rem">Todos</a>
          <span style="color:#adb5bd;font-size:.78rem">·</span>
          <a href="#" id="labSelectNone" style="font-size:.78rem">Nenhum</a>
          <div style="margin-left:auto;display:flex;gap:6px">
            <button type="button" id="labPanelCancel"
                    class="btn btn-sm btn-outline-secondary"
                    style="padding:2px 12px;font-size:.8rem">Cancelar</button>
            <button type="button" id="labApplyBtn"
                    class="btn btn-sm btn-primary"
                    style="padding:2px 12px;font-size:.8rem">Aplicar</button>
          </div>
        </div>
      </div>
    </div><!-- /posição relativa -->

    <!-- Chips dos labs activos -->
    <div id="labChips" style="display:flex;flex-wrap:wrap;align-items:center;gap:4px"></div>

    <!-- Repor (só visível com filtro activo) -->
    <button type="button" id="labClearBtn" class="btn btn-sm btn-outline-danger"
            style="display:none">
      <i class="fas fa-times fa-xs me-1"></i>Repor
    </button>

    <!-- Modo OU / E (só com 2+ labs) -->
    <span id="labMatchToggle" style="display:none;align-items:center;gap:4px">
      <span style="font-size:.76rem;color:#6c757d">Modo:</span>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary active" id="labModeOr"
                title="Pelo menos 1 laboratório seleccionado">OU</button>
        <button type="button" class="btn btn-outline-secondary" id="labModeAnd"
                title="Todos os laboratórios seleccionados">E</button>
      </div>
    </span>

    <!-- Exportar -->
    <form method="post" action="export-labs.php" id="labExportForm" class="ml-auto">
      <input type="hidden" name="tab"       id="labExportTab"  value="active">
      <input type="hidden" name="labs_json" id="labExportLabs" value="[]">
      <button type="submit" id="labExportBtn" class="btn btn-sm btn-outline-success">
        <i class="fas fa-file-excel fa-sm me-1"></i>Exportar
      </button>
    </form>

  </div>
</div>

<ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
							<li class="nav-item btn-dark"><a
								class="nav-link active text-white" id="pills-new-tab"
								data-bs-toggle="pill" href="#pills-new" role="tab"
								aria-controls="pills-new" aria-selected="true">
								Novos registos
								<?php if ($newrecords > 0): ?>
								<span class="badge badge-light ms-1"><?= $newrecords ?></span>
								<?php endif; ?>
							</a></li>
							<li class="nav-item btn-dark"><a class="nav-link text-white"
								id="pills-pendent-tab" data-bs-toggle="pill" href="#pills-pendent"
								role="tab" aria-controls="pills-pendent" aria-selected="false">
								Pendentes
								<?php if ($pendentrecords > 0): ?>
								<span class="badge badge-warning ms-1"><?= $pendentrecords ?></span>
								<?php endif; ?>
							</a></li>
							<li class="nav-item btn-dark"><a class="nav-link text-white"
								id="pills-expire-tab" data-bs-toggle="pill" href="#pills-expire"
								role="tab" aria-controls="pills-expire" aria-selected="false">A
									expirar</a></li>
							<li class="nav-item btn-dark"><a class="nav-link text-white"
								id="pills-active-tab" data-bs-toggle="pill" href="#pills-active"
								role="tab" aria-controls="pills-active" aria-selected="false">Ativos</a>
							</li>
							<li class="nav-item btn-dark"><a class="nav-link text-white"
								id="pills-inactive-tab" data-bs-toggle="pill"
								href="#pills-inactive" role="tab" aria-controls="pills-inactive"
								aria-selected="false">Inativos</a></li>
							<li class="nav-item btn-dark"><a class="nav-link text-white <?= $pedidosCount > 0 ? 'position-relative' : '' ?>"
								id="pills-pedidos-tab" data-bs-toggle="pill"
								href="#pills-pedidos" role="tab" aria-controls="pills-pedidos"
								aria-selected="false">
								Pedidos
								<?php if ($pedidosCount > 0): ?>
								<span class="badge badge-warning ms-1"><?= $pedidosCount ?></span>
								<?php endif; ?>
							</a></li>
						</ul>
						<div class="tab-content" id="pills-tabContent">
							<div class="tab-pane fade show active" id="pills-new"
								role="tabpanel" aria-labelledby="pills-new-tab">
								<div class="row ">
									<div class="col-md-12 col-xs-12">
										<form action='index.php' id='formnovoregisto' method='post'>
											<div class="d-none">
												<input id="novo-action" name="action" value="Pendente">
											</div>
											<table class="table table-striped table-bordered table-sm "
												id="new">
												<thead>
													<tr>
														<th width="1%" class="text-start"><input name="selector[]"
															type="checkbox" value=""></th>
														<th>Código</th>
														<th>Nome</th>
														<th>Email</th>
														<th>Grupo Profissional</th>
														<th>Início</th>
														<th>Fim</th>
														<th>Estado</th>
														<th>Registo</th>
														<th width="1%">Detalhe</th>
													</tr>
												</thead>
												<tbody>
													<?php
        // Pré-carregar estado de validações para todos os registos Novo
        $novoValStatus = array();
        $qNovoVals = $pdo->query(
            "SELECT v.registo_id,
                    COUNT(*) AS total,
                    SUM(v.status='Validado') AS ok,
                    SUM(v.status='Rejeitado') AS rej
             FROM infodeqb_rds_validacao v
             JOIN infodeqb_rds_registo r ON r.autoid = v.registo_id
             WHERE r.status = 'Novo'
             GROUP BY v.registo_id"
        );
        foreach ($qNovoVals->fetchAll(PDO::FETCH_ASSOC) as $vr) {
            $tot = (int)$vr['total']; $ok = (int)$vr['ok']; $rej = (int)$vr['rej'];
            if ($tot > 0 && $ok === $tot) {
                $novoValStatus[(int)$vr['registo_id']] = 'all_ok';
            } elseif ($rej > 0) {
                $novoValStatus[(int)$vr['registo_id']] = 'rejected';
            } elseif ($tot > 0) {
                $novoValStatus[(int)$vr['registo_id']] = 'pending';
            }
        }

        $row = array();
        if ($sth->execute([
                'Novo'
        ])) {
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                $vs = isset($novoValStatus[(int)$row['autoid']]) ? $novoValStatus[(int)$row['autoid']] : 'none';
                $valBadge = '';
                if ($vs === 'all_ok') {
                    $valBadge = ' <span class="badge badge-success" title="Todas as validações concluídas" style="font-size:.7rem">✓ val.</span>';
                } elseif ($vs === 'rejected') {
                    $valBadge = ' <span class="badge badge-danger" title="Validação rejeitada" style="font-size:.7rem">✗ val.</span>';
                } elseif ($vs === 'pending') {
                    $valBadge = ' <span class="badge badge-warning" title="Validações pendentes" style="font-size:.7rem">⏳ val.</span>';
                }
                echo '<tr data-row-id="' . $row['codigo'] . '"' . $getLabsAttr($row['autoid']) . '>';
                echo '<td class="text-start"><input name="selector[' .
                        $row['autoid'] . ']" type="checkbox"></td>';
                echo '<td>' . $row['codigo'] .
                        '<input type="hidden"  name="codigo[' .
                        $row['autoid'] . ']" value="' . $row['codigo'] .
                        '" ></td>';
                echo '<td>' . htmlspecialchars($row['nome']) . $valBadge . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . $row['grupo_pro'] . '</td>';
                echo '<td>' . $row['datainicio'] . '</td>';
                echo '<td>' . $row['datafim'] . '</td>';
                echo '<td>' . $row['status'] . '</td>';
                echo '<td>' . date("Y-m-d", $row['createdate']) . '</td>';
                echo '<td class="text-center">';
                $link = (strlen($row['codigo']) > 6) ? "https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico=" : "https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=";
                echo '<a  class="mr-1 ms-1" style="color:#17A2B8" title="Ver registo" href="detail.php?id=' .
                        $row['codigo'] .
                        '&amp;status=Novo"><i class="far fa-eye fa-sm" ></i></a><a  class="mr-1 ms-1" style="color:#17A2B8" title="SIGARRA" target="_blank" href="' .
                        $link . $row['codigo'] .
                        '"><i class="fas fa-info fa-sm"></i> </a>';
                echo ' ';
                echo '</td>';
                echo '</tr>';
            }
        }

        ?>
												</tbody>
												<tfooter>
												<tr class="bg-white">
													<td colspan="10" class="text-end py-2">
														<span class="text-dark me-2"><b>Com os selecionados:</b></span>
														<a type="button" href="#" data-bs-toggle="modal" data-bs-target="#modalaction"
														   class="batchaction btn btn-sm btn-warning me-1"
														   id="novoValidacoesBtn" data-form-id="formnovoregisto">
															<i class="fas fa-user-check me-1"></i>Solicitar validações
														</a>
														<a type="button" href="#" data-bs-toggle="modal" data-bs-target="#modalaction"
														   class="batchaction btn btn-sm btn-success"
														   id="novoAcessosBtn" data-form-id="formnovoregisto">
															<i class="fas fa-paper-plane me-1"></i>Pedir acessos
														</a>
													</td>
												</tr>
												</tfooter>
											</table>
										</form>
									</div>
								</div>
							</div>
							<div class="tab-pane fade" id="pills-pendent" role="tabpanel"
								aria-labelledby="pills-pendent-tab">
								<div class="row ">
									<div class="col-md-12 col-xs-12">
										<form action='index.php' id='formpendentes' method='post'>
											<div class="d-none">
												<input name="action" value="Ativo"></input>
											</div>
											<table class="table table-striped table-bordered table-sm "
												id="pendent" style="width: 100%">
												<thead>
													<tr>
														<th width="1%" class="text-start"><input name="selector[]"
															type="checkbox" value=""></th>
														<th>Código</th>
														<th>Nome</th>
														<th>Email</th>
														<th>Grupo Profissional</th>
														<th>Pedido ao CICA</th>
														<th>Estado</Th>
														<th width="1%">Detalhe</th>
													</tr>
												</thead>
												<tbody>
													<?php
        $row = array();
        if ($sth->execute([
                'Pendente'
        ])) {
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr data-row-id="' . $row['codigo'] . '"' . $getLabsAttr($row['autoid']) . '>';
                echo '<td class="text-start"><input name="selector[' .
                        $row['autoid'] . ']" type="checkbox"></td>';
                echo '<td>' . $row['codigo'] .
                        '<input type="hidden"  name="codigo[' .
                        $row['autoid'] . ']" value="' . $row['codigo'] .
                        '" ></td>';
                echo '<td>' . $row['nome'] . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . $row['grupo_pro'] . '</td>';
                echo '<td>' . $row['datacica'] . '</td>';
                echo '<td>' . $row['status'] . '</td>';
                echo '<td class="text-center">';
                $link = (strlen($row['codigo']) > 6) ? "https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico=" : "https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=";
                echo '<a  class="mr-1 ms-1" style="color:#17A2B8" title="Ver registo" href="detail.php?id=' .
                        $row['codigo'] .
                        '"><i class="far fa-eye fa-sm" ></i></a><a  class="mr-1 ms-1" style="color:#17A2B8" title="SIGARRA" target="_blank" href="' .
                        $link . $row['codigo'] .
                        '"><i class="fas fa-info fa-sm"></i> </a></td>';

                echo '</tr>';
            }
        }

        ?>
												</tbody>
												<tfooter>
												<tr class="bg-white">
													<td colspan="8" class="text-end py-2">
														<span class="text-dark me-2"><b>Com os selecionados:</b></span>
														<a type="button" href="#" data-bs-toggle="modal" data-bs-target="#modalaction"
														   class="batchaction btn btn-sm btn-success"
														   id="pendentesubmitBtn">
															<i class="fas fa-check me-1"></i>Ativar registo
														</a>
													</td>
												</tr>
												</tfooter>
											</table>
										</form>
									</div>
								</div>
							</div>
							<div class="tab-pane fade " id="pills-expire" role="tabpanel"
								aria-labelledby="pills-expire-tab">
								<div class="row ">
									<div class="col-md-12 col-xs-12">
									<?php echo '<div class=" "> <p class="text-white bg-dark p-1">Registos ativos com data fim <span class="text-warning">anterior a '.formatDate('d-m-Y',$final).'</span></p></div>';  ?>
									<form action='index.php' id='formexpirados' method='post'>
											<div class="d-none">
												<input id="action" name="action" value="notify"></input>
											</div>
											<table class="table table-striped table-bordered table-sm "
												id="expire" style="width: 100%">
												<thead>
													<tr>
														<th width="1%" class="text-start"><input name="selector[]"
															type="checkbox" value=""></th>
														<th>Código</th>
														<th>Nome</th>
														<th>Email</th>
														<th class="filter-select filter-exact"
															data-placeholder="Escolha uma categoria">Categoria</th>
														<th>Fim</th>
														<th>Notificação</th>
														<th>Estado</th>
														<th width="1%">Detalhe</th>
													</tr>
												</thead>
												<tbody>
												<?php
        $row = array();
        if ($sth_expire->execute([
                'Ativo',
                $final
        ])) {
            while ($row = $sth_expire->fetch(PDO::FETCH_ASSOC)) {
                $date = formatDate('Y-m-d', $row['datanotificacao']);
                echo '<tr data-row-id="' . $row['codigo'] . '"' . $getLabsAttr($row['autoid']) . '>';
                echo '<td class="text-start"><input name="selector[' .
                        $row['autoid'] . ']" type="checkbox"></td>';
                echo '<td>' . $row['codigo'] .
                        '<input type="hidden"  name="codigo[' .
                        $row['autoid'] . ']" value="' . $row['codigo'] .
                        '" ></td>';
                echo '<td>' . $row['nome'] . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . $row['grupo_pro'] . '</td>';
                echo '<td>' . $row['datafim'] . '</td>';
                echo '<td>' . $date . '</td>';
                echo '<td>' . $row['status'] . '</td>';
                echo '<td class="text-center">';
                $link = (strlen($row['codigo']) > 6) ? "https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico=" : "https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=";
                echo '<a  class="mr-1 ms-1" style="color:#17A2B8" title="Ver registo" href="detail.php?id=' .
                        $row['codigo'] .
                        '"><i class="far fa-eye fa-sm" ></i></a>
														<a  class="mr-1 ms-1" style="color:#17A2B8" title="SIGARRA" target="_blank" href="' .
                        $link . $row['codigo'] .
                        '"><i class="fas fa-info fa-sm"></i> </a></td>';
                echo '</tr>';
            }
        }

        ?>
									</form>
												</tbody>
												<tfooter>
												<tr class="bg-white">
													<td colspan="9" class="text-end py-2">
														<span class="text-dark me-2"><b>Com os selecionados:</b></span>
														<a type="button" href="#" data-bs-toggle="modal" data-bs-target="#modalaction"
														   class="batchaction btn btn-sm btn-info me-1"
														   id="expiradossubmitBtn">
															<i class="fas fa-bell me-1"></i>Notificar
														</a>
														<a type="button" href="#" data-bs-toggle="modal" data-bs-target="#modalaction"
														   class="batchaction btn btn-sm btn-secondary"
														   id="expiradosInativarBtn">
															<i class="fas fa-user-slash me-1"></i>Inativar registo
														</a>
													</td>
												</tr>
												</tfooter>
											</table>

									</div>
								</div>
							</div>
							<div class="tab-pane fade" id="pills-active" role="tabpanel"
								aria-labelledby="pills-active-tab">
								<div class="row ">
									<div class="col-md-12 col-xs-12">
										<form action='index.php' id='formativos' method='post'>
											<div class="d-none">
												<input name="action" value="Inativo"></input>
											</div>
											<table class="table table-striped table-bordered table-sm "
												id="active">
												<thead>
													<tr>
														<td class="text-end" colspan="9">
															<form action="index.php" method="post" id="export-form">
																<input type="submit"
																	class="btn btn-info  btn-sm text-white"
																	value='Exportar lista de registos ativos'
																	id='hidden-type' name='ExportType' />
															</form>
														</td>
													</tr>
													<th class="text-start"><input type="checkbox" name="selector[]" value=""></th>
													<th>Código</th>
													<th>Nome</th>
													<th>Email</th>
													<th class="filter-select filter-exact"
														data-placeholder="Escolha uma categoria">Categoria</th>
													<th width=9%>Início</th>
													<th width=9%>Fim</th>
													<th>Ativo desde</th>
													<th width="1%">Detalhe</th>
													</tr>
												</thead>
												<tbody>
												<?php
        if ($sth->execute([
                'Ativo'
        ])) {
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr data-row-id="' . $row['codigo'] . '"' . $getLabsAttr($row['autoid']) . '>';
                echo '<td class="text-start"><input name="selector[' .
                        $row['autoid'] . ']" type="checkbox"></td>';
                echo '<td>' . $row['codigo'] .
                        '<input type="hidden"  name="codigo[' .
                        $row['autoid'] . ']" value="' . $row['codigo'] .
                        '" ></td>';
                echo '<td>' . $row['nome'] . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . $row['grupo_pro'] . '</td>';
                echo '<td>' . $row['datainicio'] . '</td>';
                echo '<td>' . $row['datafim'] . '</td>';
                echo '<td>' . formatDate('Y-m-d', $row['dataativo']) .
                        '</td>';
                echo '<td class="text-center">';
                $link = (strlen($row['codigo']) > 6) ? "https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico=" : "https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=";
                echo '<a  class="mr-1 ms-1" style="color:#17A2B8" title="Ver registo" href="detail.php?id=' .
                        $row['codigo'] .
                        '"><i class="far fa-eye fa-sm" ></i></a><a  class="mr-1 ms-1" style="color:#17A2B8" title="SIGARRA" target="_blank" href="' .
                        $link . $row['codigo'] .
                        '"><i class="fas fa-info fa-sm"></i> </a> ';
                echo ' ';
                echo '</td>';
                echo '</tr>';
            }
        }

        ?>
											</form>
												</tbody>
												<tfooter>
												<tr class="bg-white">
													<td colspan="9" class="text-end py-2">
														<span class="text-dark me-2"><b>Com os selecionados:</b></span>
														<a type="button" href="#" data-bs-toggle="modal" data-bs-target="#modalaction"
														   class="batchaction btn btn-sm btn-secondary"
														   id="ativossubmitBtn">
															<i class="fas fa-user-slash me-1"></i>Inativar registo
														</a>
													</td>
												</tr>
												</tfooter>
											</table>

									</div>
								</div>
							</div>
							<div class="tab-pane fade" id="pills-inactive" role="tabpanel"
								aria-labelledby="pills-inactive-tab">
								<div class="row ">
									<div class="col-md-12 col-xs-12">
										<form action='index.php' id='forminativos' method='post'>
											<div class="d-none">
												<input name="action" value="Ativo"></input>
											</div>
											<table class="table table-striped table-bordered table-sm "
												id="inactive">
												<thead>
													<tr>
														<th width="1%" class="text-start"><input name="selector[]"
															type="checkbox" value=""></th>
														<th>Código</th>
														<th>Nome</th>
														<th>Email</th>
														<th class="filter-select filter-exact"
															data-placeholder="Escolha uma categoria">Categoria</th>
														<th width=9%>Início</th>
														<th width=9%>Fim</th>
														<th>Inativo desde</th>
														<th width="1%">Detalhe</th>
													</tr>
												</thead>
												<tbody>
												<?php
        $row = array();
        if ($sth->execute([
                'Inativo'
        ])) {
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr data-row-id="' . $row['codigo'] . '"' . $getLabsAttr($row['autoid']) . '>';
                echo '<td class="text-start"><input name="selector[' .
                        $row['autoid'] . ']" type="checkbox"></td>';
                echo '<td>' . $row['codigo'] .
                        '<input type="hidden"  name="codigo[' .
                        $row['autoid'] . ']" value="' . $row['codigo'] .
                        '" ></td>';
                echo '<td>' . $row['nome'] . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . $row['grupo_pro'] . '</td>';
                echo '<td>' . $row['datainicio'] . '</td>';
                echo '<td>' . $row['datafim'] . '</td>';
                echo '<td>' . formatDate('Y-m-d', $row['datainativo']) .
                        '</td>';
                echo '<td class="text-center">';
                $link = (strlen($row['codigo']) > 6) ? "https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico=" : "https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=";
                echo '<a  class="mr-1 ms-1" style="color:#17A2B8" title="Ver registo" href="detail.php?id=' .
                        $row['codigo'] .
                        '"><i class="far fa-eye fa-sm" ></i></a><a  class="mr-1 ms-1" style="color:#17A2B8" title="SIGARRA" target="_blank" href="' .
                        $link . $row['codigo'] .
                        '"><i class="fas fa-info fa-sm"></i> </a>';
                echo ' ';
                echo '</td>';
                echo '</tr>';
            }
        }

        ?>
										</form>
										<?php Database::disconnect()?>
											</tbody>
											<tfooter>
											<tr class="bg-white">
												<td colspan="9" class="text-end py-2">
													<span class="text-dark me-2"><b>Com os selecionados:</b></span>
													<a type="button" href="#" data-bs-toggle="modal" data-bs-target="#modalaction"
													   class="batchaction btn btn-sm btn-success"
													   id="inativossubmitBtn">
														<i class="fas fa-check me-1"></i>Ativar registo
													</a>
												</td>
											</tr>
											</tfooter>
										</table>

									</div>
								</div>
							</div>
<div class="tab-pane fade" id="pills-pedidos" role="tabpanel" aria-labelledby="pills-pedidos-tab">
<div class="row"><div class="col-md-12 pt-2">

<!-- flash messages shown above tabs -->

<?php if (empty($pedidos)): ?>
  <p class="text-muted py-3 text-center"><i class="fas fa-check-circle text-success me-1"></i>Sem pedidos pendentes.</p>
<?php else: ?>
  <table class="table table-sm table-hover mt-1">
    <thead class="">
      <tr>
        <th>Código</th><th>Nome</th><th>Tipo</th>
        <th>Submetido</th><th>Observações</th><th>Campos alterados</th><th>Ação</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $tipoLabels = array(
        'novo'               => 'Novo registo',
        'alteracao'          => 'Alteração',
        'novo_registo'       => 'Mudança de grupo',
        'alteracao_labs'     => 'Alteração de acessos',
        'alteracao_datafim'  => 'Alteração de data fim',
        'alteracao_sigarra'  => 'Alteração (SIGARRA)',
    );
    $tipoBadges = array(
        'novo'               => 'badge-primary',
        'alteracao'          => 'badge-info',
        'novo_registo'       => 'badge-warning',
        'alteracao_labs'     => 'badge-danger',
        'alteracao_datafim'  => 'badge-warning',
        'alteracao_sigarra'  => 'badge-danger',
    );
    $confirmMsgs = array(
        'novo'               => 'Criar novo registo?',
        'alteracao'          => 'Aplicar alterações ao registo?',
        'novo_registo'       => 'Criar novo registo e inativar o actual?',
        'alteracao_labs'     => 'Aprovar alteração de acessos e notificar o SIGARRA?',
        'alteracao_datafim'  => 'Aprovar nova data de fim e notificar o SIGARRA?',
        'alteracao_sigarra'  => 'Aprovar alterações e notificar o SIGARRA?',
    );
    $tiposComLabs = array('novo','novo_registo','alteracao_sigarra','alteracao_labs');

    foreach ($pedidos as $ped):
      $dPed  = json_decode($ped['dados_json'],     true) ?: array();
      $dAnt  = json_decode($ped['dados_anteriores'] ?? 'null', true) ?: array();
      $cPed  = $ped['campos_alterados'] ? json_decode($ped['campos_alterados'], true) : array();
      $tipoLabel  = $tipoLabels[$ped['tipo']]  ?? $ped['tipo'];
      $tipoBadge  = $tipoBadges[$ped['tipo']]  ?? 'badge-secondary';
      $confirmMsg = $confirmMsgs[$ped['tipo']] ?? 'Aprovar?';

      // ── Validações ──────────────────────────────────────────────────
      $pedVals     = isset($valDetails[$ped['id']]) ? $valDetails[$ped['id']] : array();
      $temPendente = false; $temRejeitado = false; $todosOk = false;
      $nVal = count($pedVals);
      if ($nVal > 0) {
          $nValid = 0; $nPend = 0; $nRej = 0;
          foreach ($pedVals as $vv) {
              if ($vv['status'] === 'Validado')  $nValid++;
              if ($vv['status'] === 'Pendente')  $nPend++;
              if ($vv['status'] === 'Rejeitado') $nRej++;
          }
          $temPendente  = $nPend  > 0;
          $temRejeitado = $nRej   > 0;
          $todosOk      = $nValid === $nVal;
      }
      $bloqueado = ($temPendente || $temRejeitado);

      // Precisa de validação? (tem labs pedidos)
      $precisaVal = false;
      if (in_array($ped['tipo'], $tiposComLabs)) {
          if (in_array($ped['tipo'], array('novo','novo_registo'))) {
              $precisaVal = !empty($dPed['acessos']);
          } else {
              $precisaVal = !empty($dPed['labs_adicionados']);
          }
      }
    ?>
    <tr>
      <td><?= htmlspecialchars($ped['codigo']) ?></td>
      <td><?= htmlspecialchars($ped['nome_colab'] ?? ($dPed['nome'] ?? '—')) ?></td>
      <td><span class="badge <?= $tipoBadge ?>"><?= $tipoLabel ?></span></td>
      <td><?= htmlspecialchars(substr($ped['criado_em'],0,16)) ?></td>
      <td><?= htmlspecialchars($ped['observacoes'] ?? '—') ?></td>
      <td>
        <?php if ($cPed): ?>
          <small class="text-muted"><?= htmlspecialchars(implode(', ', $cPed)) ?></small>
        <?php elseif (in_array($ped['tipo'], array('novo_registo','novo'))): ?>
          <small class="text-muted"><em>registo completo</em></small>
        <?php elseif ($ped['tipo'] === 'alteracao_datafim'): ?>
          <?php $dj = json_decode($ped['dados_json'],true) ?: array(); ?>
          <small class="text-muted"><?= htmlspecialchars($dj['datafim_antigo']??'') ?> → <strong><?= htmlspecialchars($dj['datafim_novo']??'') ?></strong></small>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td style="white-space:nowrap">
        <button class="btn btn-sm btn-outline-secondary me-1"
          onclick='verPedido(<?= (int)$ped["id"] ?>,
            <?= json_encode($ped["dados_json"]) ?>,
            <?= json_encode($ped["dados_anteriores"] ?? "null") ?>,
            <?= json_encode($ped["campos_alterados"] ?? "null") ?>)'>
          <i class="fas fa-eye fa-xs"></i> Ver
        </button>
        <?php if ($precisaVal && $nVal === 0): ?>
          <!-- Ainda não foram solicitadas validações -->
          <form method="post" action="validacao-action.php" style="display:inline">
            <input type="hidden" name="val_acao"  value="solicitar_pedido">
            <input type="hidden" name="pedido_id" value="<?= (int)$ped['id'] ?>">
            <input type="hidden" name="redirect"  value="index.php">
            <button class="btn btn-sm btn-outline-warning me-1"
                    title="Solicitar validação aos responsáveis dos espaços"
                    onclick="return confirm('Enviar pedido de validação aos responsáveis dos espaços?')">
              <i class="fas fa-user-check fa-xs"></i> Solicitar validações
            </button>
          </form>
        <?php endif; ?>
        <form method="post" style="display:inline"
              onsubmit="return confirm('<?= addslashes($confirmMsg) ?>')">
          <input type="hidden" name="pedido_id"     value="<?= (int)$ped['id'] ?>">
          <input type="hidden" name="pedido_action" value="Aprovado">
          <?php if ($bloqueado): ?>
            <button class="btn btn-sm btn-success me-1" disabled
                    title="Aguarda validação dos responsáveis de espaço">
              <i class="fas fa-lock fa-xs"></i> Aprovar
            </button>
          <?php else: ?>
            <button class="btn btn-sm btn-success me-1">✓ Aprovar</button>
          <?php endif; ?>
        </form>
        <form method="post" style="display:inline"
              onsubmit="return confirm('Rejeitar este pedido?')">
          <input type="hidden" name="pedido_id"     value="<?= (int)$ped['id'] ?>">
          <input type="hidden" name="pedido_action" value="Rejeitado">
          <button class="btn btn-sm btn-danger">✗ Rejeitar</button>
        </form>
      </td>
    </tr>
    <?php if ($precisaVal && $nVal > 0): ?>
    <tr>
      <td colspan="7" style="background:#f8f9fa;padding:6px 16px 10px 16px;border-top:none;">
        <small class="d-block mb-1 text-muted font-weight-bold">
          <i class="fas fa-user-check me-1"></i>Validações de espaço
          <?php if ($todosOk): ?>
            <span class="badge badge-success ms-1">Todas validadas ✓</span>
          <?php elseif ($temRejeitado): ?>
            <span class="badge badge-danger ms-1">Com rejeição</span>
          <?php else: ?>
            <span class="badge badge-warning ms-1">A aguardar resposta</span>
          <?php endif; ?>
        </small>
        <table class="table table-xs table-bordered mb-1" style="font-size:.8rem;background:#fff;">
          <thead class="">
            <tr>
              <th>Espaço</th><th>Responsável</th><th><?= t('STATUS') ?></th><th>Nota</th><th style="width:1%"><?= t('ACTIONS') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pedVals as $vv):
            $vBadge = array('Pendente'=>'badge-warning','Validado'=>'badge-success','Rejeitado'=>'badge-danger');
            $vIcon  = array('Pendente'=>'⏳','Validado'=>'✅','Rejeitado'=>'❌');
          ?>
          <tr>
            <td><?= htmlspecialchars($vv['gab_nome'] ?? $vv['deq_id']) ?></td>
            <td><?= htmlspecialchars($vv['resp_nome'] ?? '') ?>
                <small class="text-muted">(up<?= htmlspecialchars($vv['resp_codigo']) ?>)</small></td>
            <td><span class="badge <?= $vBadge[$vv['status']] ?? 'badge-secondary' ?>">
                <?= ($vIcon[$vv['status']] ?? '') . ' ' . $vv['status'] ?></span>
                <?php if ($vv['respondido_em']): ?>
                  <br><small class="text-muted"><?= htmlspecialchars(substr($vv['respondido_em'],0,16)) ?></small>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($vv['nota'] ?? '—') ?></td>
            <td style="white-space:nowrap">
              <?php if ($vv['status'] !== 'Validado'): ?>
              <form method="post" action="validacao-action.php" style="display:inline">
                <input type="hidden" name="val_acao" value="reabrir">
                <input type="hidden" name="val_id"   value="<?= (int)$vv['id'] ?>">
                <input type="hidden" name="redirect"  value="index.php">
                <button class="btn btn-xs btn-outline-primary" title="Reenviar novo link"
                        onclick="return confirm('Reenviar pedido de validação ao responsável?')">
                  <i class="fas fa-redo fa-xs"></i>
                </button>
              </form>
              <?php endif; ?>
              <form method="post" action="validacao-action.php" style="display:inline">
                <input type="hidden" name="val_acao" value="cancelar">
                <input type="hidden" name="val_id"   value="<?= (int)$vv['id'] ?>">
                <input type="hidden" name="redirect"  value="index.php">
                <button class="btn btn-xs btn-outline-danger" title="Apagar esta validação"
                        onclick="return confirm('Apagar esta validação? O responsável não será notificado.')">
                  <i class="fas fa-trash fa-xs"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($precisaVal && !$todosOk): ?>
        <form method="post" action="validacao-action.php" style="display:inline">
          <input type="hidden" name="val_acao"  value="solicitar_pedido">
          <input type="hidden" name="pedido_id" value="<?= (int)$ped['id'] ?>">
          <input type="hidden" name="redirect"  value="index.php">
          <button class="btn btn-xs btn-outline-warning"
                  title="Enviar pedido de validação para espaços ainda sem resposta"
                  onclick="return confirm('Solicitar validações para espaços ainda sem pedido?')">
            <i class="fas fa-plus fa-xs"></i> Solicitar validações em falta
          </button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (!empty($pedidosAguarda)): ?>
<hr class="my-3">
<h6 class="text-muted mb-2">
  <i class="fas fa-hourglass-half me-1"></i>
  A aguardar confirmação do SIGARRA
  <span class="badge badge-secondary ms-1"><?= $pedidosAguardaCount ?></span>
</h6>
<table class="table table-sm table-hover">
  <thead class="">
    <tr>
      <th>Código</th><th>Nome</th><th>Tipo</th>
      <th>Enviado ao SIGARRA</th><th>Detalhe</th><th>Ação</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($pedidosAguarda as $ped):
    $dPed = json_decode($ped['dados_json'], true) ?: array();
    $tipoLabel = $tipoLabels[$ped['tipo']] ?? $ped['tipo'];
    $tipoBadge = $tipoBadges[$ped['tipo']] ?? 'badge-secondary';
  ?>
  <tr>
    <td><?= htmlspecialchars($ped['codigo']) ?></td>
    <td><?= htmlspecialchars($ped['nome_colab'] ?? '—') ?></td>
    <td><span class="badge <?= $tipoBadge ?>"><?= $tipoLabel ?></span></td>
    <td><?= htmlspecialchars(substr($ped['processado_em']??'',0,16)) ?></td>
    <td>
      <?php if ($ped['tipo'] === 'alteracao_datafim'): ?>
        <small><?= htmlspecialchars($dPed['datafim_antigo']??'') ?> → <strong><?= htmlspecialchars($dPed['datafim_novo']??'') ?></strong></small>
      <?php else: ?>
        <button class="btn btn-xs btn-outline-secondary"
          onclick='verPedido(<?= (int)$ped["id"] ?>,
            <?= json_encode($ped["dados_json"]) ?>,
            <?= json_encode($ped["dados_anteriores"] ?? "null") ?>,
            "null")'>
          <i class="fas fa-eye fa-xs"></i>
        </button>
      <?php endif; ?>
    </td>
    <td>
      <form method="post" style="display:inline"
            onsubmit="return confirm('Confirmar que o SIGARRA concluiu e notificar o utilizador?')">
        <input type="hidden" name="pedido_id"     value="<?= (int)$ped['id'] ?>">
        <input type="hidden" name="pedido_action" value="Concluido">
        <button class="btn btn-sm btn-primary">
          <i class="fas fa-check-double me-1"></i> SIGARRA concluiu
        </button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

</div></div>
</div><!-- /pills-pedidos -->
						</div>
					</div>
				</div>

<!-- Modal detalhe pedido -->
<div class="modal fade" id="modalPedido" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="modalPedidoTitle">Detalhe do pedido</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body" id="modalPedidoBody" style="font-size:.84rem"></div>
  </div></div>
</div>
<script>
function verPedido(id, jsonNovo, jsonAnt, jsonCampos) {
  var d, ant;
  try { d   = JSON.parse(jsonNovo); } catch(e) { d   = {}; }
  try { ant = JSON.parse(jsonAnt);  } catch(e) { ant = null; }

  var html = '';
  var hasSpecific = false;

  // ── Pedido de alteração de data de fim ──────────────────────────
  if (d.datafim_novo !== undefined) {
    hasSpecific = true;
    html += '<h6 class="mb-2">Alteração de Data de Fim</h6>';
    html += '<table class="table table-sm table-bordered mb-3">';
    html += '<thead class=""><tr><th>Data anterior</th><th>Nova data</th></tr></thead>';
    html += '<tbody><tr>';
    html += '<td class="text-muted">' + esc(d.datafim_antigo || '—') + '</td>';
    html += '<td><strong>' + esc(d.datafim_novo || '—') + '</strong></td>';
    html += '</tr></tbody></table>';
  }

  // ── Pedido de alteração de labs ─────────────────────────────────
  if (d.labs_adicionados !== undefined || d.labs_removidos !== undefined) {
    hasSpecific = true;
    html += '<h6 class="mb-2">Acesso DEQ</h6>';
    html += '<table class="table table-sm table-bordered mb-3">';
    html += '<thead class=""><tr><th>Antes</th><th>Depois</th></tr></thead><tbody><tr>';
    html += '<td>' + (ant ? (ant.acessodeq ? 'Sim' : 'Não') : '—') + '</td>';
    html += '<td>' + (d.acessodeq ? 'Sim' : 'Não') + '</td>';
    html += '</tr></tbody></table>';

    html += '<h6 class="mb-2">Laboratórios / Gabinetes</h6>';
    html += '<table class="table table-sm table-bordered mb-3">';
    html += '<thead class=""><tr><th style="width:50%">Adicionados</th><th>Removidos</th></tr></thead><tbody><tr>';

    var add = (d.labs_adicionados_nomes || d.labs_adicionados || []);
    var rem = (d.labs_removidos_nomes   || d.labs_removidos   || []);

    var addHtml = add.length
      ? add.map(function(n){ return '<span class="badge badge-success me-1">+ '+esc(n)+'</span>'; }).join(' ')
      : '<span class="text-muted">—</span>';
    var remHtml = rem.length
      ? rem.map(function(n){ return '<span class="badge badge-danger me-1">- '+esc(n)+'</span>'; }).join(' ')
      : '<span class="text-muted">—</span>';

    html += '<td>'+addHtml+'</td><td>'+remHtml+'</td></tr>';

    // Lista completa nova
    var full = (d.acessos_nomes || d.acessos || []);
    if (full.length) {
      html += '<tr><td colspan="2" class="text-muted" style="font-size:.8rem">'
            + '<strong>Lista completa após aprovação:</strong> ' + esc(full.join('; '))
            + '</td></tr>';
    }
    html += '</tbody></table>';
  }

  if (!hasSpecific) {
    // ── Outros tipos: tabela genérica ──────────────────────────────
    var L = {
      nome:'Nome', emailalt:'Email alt.', telefone:'Telefone',
      datainicio:'Início', datafim:'Fim', grupo:'Grupo prof.', categoria:'Categoria',
      unidade:'Unidade I&D', workplace:'Posto de trabalho', extensao:'Extensão',
      acessodeq:'Acesso DEQ', curso:'Curso'
    };
    var rows = '';
    for (var k in L) {
      var vN = d[k]; if (vN == null || vN === '') continue;
      vN = Array.isArray(vN) ? vN.join('; ') : String(vN);
      var vA = ant && ant[k] != null ? String(ant[k]) : null;
      var changed = vA !== null && vA !== vN;
      rows += '<tr' + (changed ? ' style="background:#fef3c7"' : '') + '>'
            + '<th style="width:35%;padding:.3rem .6rem">' + L[k] + '</th>'
            + (vA !== null ? '<td style="padding:.3rem .6rem;color:#999">' + esc(vA) + '</td>' : '')
            + '<td style="padding:.3rem .6rem' + (changed ? ';font-weight:600' : '') + '">' + esc(vN) + '</td>'
            + '</tr>';
    }
    var cols = ant ? 3 : 2;
    html += '<table class="table table-sm table-bordered mb-0">'
          + '<thead class=""><tr>'
          + '<th>Campo</th>' + (ant ? '<th>Antes</th>' : '') + '<th>Valor</th>'
          + '</tr></thead><tbody>' + rows + '</tbody></table>';
  }

  document.getElementById('modalPedidoBody').innerHTML = html;
  $('#modalPedido').modal('show');
}
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<!-- Logout Modal-->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog"
	aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="exampleModalLabel">Terminar a sessão?</h5>
				<button class="btn-close" type="button" data-bs-dismiss="modal"
					aria-label="Close">
					<span aria-hidden="true">×</span>
				</button>
			</div>
			<div class="modal-body">Escolha "Sair" se pretender terminar a atual
				sessão.</div>
			<div class="modal-footer">
				<button class="btn btn-secondary" type="button"
					data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
				<a class="btn btn-info" href="../../logout.php">Sair</a>
			</div>
		</div>
	</div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="modalaction" tabindex="-1" role="dialog"
	aria-labelledby="ativarlabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="ativarlabel">
					<span class="title-text"></span>
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"
					aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="body-text"></p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary"
					data-bs-dismiss="modal">Não</button>
				<button type="submit" id="bsubmit" class="btn btn-info btn-ok">Sim</button>
			</div>
		</div>
	</div>
</div>

<?php
// Deep-link de dashboard: ?tab=pills-new → activa o separador correcto
$_tabParam = trim($_GET['tab'] ?? '');
// Aceita com ou sem # inicial; valores válidos: pills-new, pills-pendent, pills-expire, pills-active, pills-inactive, pills-pedidos
$_validTabs = ['pills-new','pills-pendent','pills-expire','pills-active','pills-inactive','pills-pedidos'];
$_tabId = ltrim($_tabParam, '#');
if (in_array($_tabId, $_validTabs)):
?>
<script>
// Escreve no localStorage ANTES do script_js.js para que o tab correcto seja activado
localStorage.setItem('activeTab', '#<?= htmlspecialchars($_tabId) ?>');
</script>
<?php endif; ?>
<script>
// Mapa deqid → nomegab para o filtro de laboratórios
window._labIdToName = <?= json_encode($labIdToNameMap, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="script_js.js"></script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
