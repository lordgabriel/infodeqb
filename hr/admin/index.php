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
                    "SELECT r.datainicio, r.datafim, r.acessosid, c.nome AS colab_nome, c.codigo AS colab_codigo,
                            IF(r.responsavel=0, r.outroresponsavel, rsp.respespaco) AS resp_trabalho
                     FROM infodeqb_rds_registo r
                     JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
                     LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo = r.responsavel
                     WHERE r.autoid = ? AND r.status = 'Novo'"
                );
                $qReg->execute([$autoid]);
                $reg = $qReg->fetch(PDO::FETCH_ASSOC);
                if (!$reg) { $nIgnorados++; continue; }

                $deqids = getRegistoAcessos($pdo, (int)$autoid);
                if (empty($deqids)) { $nIgnorados++; continue; }

                $nEnviados += _criarValidacoes(
                    $pdo, null, $autoid, $deqids,
                    $reg['colab_nome'], $reg['datainicio'], $reg['datafim'],
                    $reg['colab_codigo'], $reg['resp_trabalho'] ?? ''
                );

            } else { // pedir_acessos_massa
                // Só avança se todas as validações estiverem concluídas (ou não houver nenhuma)
                // Bloquear se existir validação não-Validada
                $qChk = $pdo->prepare(
                    "SELECT COUNT(*) AS total,
                            SUM(status='Validado') AS ok,
                            SUM(status NOT IN ('Validado','Cancelado')) AS pendentes
                     FROM infodeqb_rds_validacao WHERE registo_id=?"
                );
                $qChk->execute([$autoid]);
                $chk = $qChk->fetch(PDO::FETCH_ASSOC);
                if ((int)$chk['pendentes'] > 0) { $nIgnorados++; continue; }

                // Bloquear se existirem labs com responsável mas sem qualquer pedido de validação
                $qSemVal = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM infodeqb_rds_registo_acessos ra
                     JOIN infodeqb_rds_gabinetes g ON g.deqid = ra.lab_id
                     WHERE ra.registo_id = ?
                       AND g.responsavel IS NOT NULL AND g.responsavel != 0
                       AND NOT EXISTS (
                           SELECT 1 FROM infodeqb_rds_validacao v
                           WHERE v.registo_id = ra.registo_id AND v.resp_codigo = g.responsavel
                       )"
                );
                $qSemVal->execute([$autoid]);
                if ((int)$qSemVal->fetchColumn() > 0) { $nIgnorados++; continue; }

                $qReg = $pdo->prepare(
                    "SELECT r.*, c.nome, c.email,
                            IF(r.responsavel=0, r.outroresponsavel, resp.respespaco) AS responsavel_nome
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

        // Ao activar: inativar o registo anterior (via substitui_registo se existir,
        // e também qualquer outro registo Ativo da mesma pessoa — evitar colisão de estados)
        if ($_POST['action'] === 'Ativo') {
            $chkSubst = $pdo->prepare('SELECT substitui_registo, codigo FROM infodeqb_rds_registo WHERE autoid = ?');
            $chkSubst->execute([$id1]);
            $substRow = $chkSubst->fetch(PDO::FETCH_ASSOC);
            $substId  = $substRow ? $substRow['substitui_registo'] : null;
            $codPess  = $substRow ? $substRow['codigo'] : null;

            if ($substId) {
                $pdo->prepare('UPDATE infodeqb_rds_registo SET status = "Inativo", datainativo = ? WHERE autoid = ?')
                    ->execute([$timestamp, $substId]);
            }
            // Inativar qualquer outro registo Ativo da mesma pessoa (sem email — acção silenciosa)
            if ($codPess) {
                $pdo->prepare(
                    'UPDATE infodeqb_rds_registo SET status = "Inativo", datainativo = ?
                     WHERE codigo = ? AND status = "Ativo" AND autoid != ? AND deleted = 0'
                )->execute([$timestamp, $codPess, (int)$id1]);
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
        'SELECT infodeqb_rds_colaborador.codigo, infodeqb_rds_colaborador.nome, infodeqb_rds_colaborador.email,infodeqb_rds_registo.autoid, infodeqb_rds_registo.datafim, infodeqb_rds_registo.datainicio, infodeqb_rds_registo.codigo,infodeqb_rds_registo.status, infodeqb_rds_grupo.grupo_pro, infodeqb_rds_registo.createdate, infodeqb_rds_registo.datacica, infodeqb_rds_registo.dataativo, infodeqb_rds_registo.datainativo, infodeqb_rds_registo.notif_pendente FROM infodeqb_rds_colaborador INNER JOIN infodeqb_rds_registo ON infodeqb_rds_colaborador.codigo=infodeqb_rds_registo.codigo INNER JOIN infodeqb_rds_grupo ON infodeqb_rds_registo.grupo=infodeqb_rds_grupo.grupoid where infodeqb_rds_colaborador.deleted!=1 AND infodeqb_rds_registo.deleted!=1 AND infodeqb_rds_registo.status= ? ORDER BY infodeqb_rds_registo.createdate ASC');

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

// Registos Ativos com alteração pendente de comunicar ao SIGARRA
$qNotifPend = $pdo->query(
    "SELECT COUNT(*) FROM infodeqb_rds_registo
     WHERE status='Ativo' AND deleted=0 AND notif_pendente=1"
);
$notifPendCount = (int)$qNotifPend->fetchColumn();
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
     WHERE p.status = 'Pendente' AND (p.origem = 'utilizador' OR p.origem IS NULL)
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

// Registos "Novo" onde existe pelo menos um lab cujo responsável
// não tem nenhum pedido de validação real (exclui "Isento auto-validado")
$qSemPedido = $pdo->query(
    "SELECT COUNT(DISTINCT r.autoid)
     FROM infodeqb_rds_registo r
     WHERE r.status = 'Novo' AND r.deleted = 0
       AND EXISTS (
           SELECT 1
           FROM infodeqb_rds_registo_acessos ra
           JOIN infodeqb_rds_gabinetes g ON g.deqid = ra.lab_id
           WHERE ra.registo_id = r.autoid
             AND g.responsavel IS NOT NULL AND g.responsavel != 0
             AND g.responsavel != 246398
             AND NOT EXISTS (
                 SELECT 1 FROM infodeqb_rds_validacao v
                 WHERE v.registo_id = r.autoid
                   AND v.resp_codigo = g.responsavel
             )
       )"
);
$semPedidoCount = (int)$qSemPedido->fetchColumn();

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
                    if ($ped['registo_id']) {
                        $pdo->prepare("UPDATE infodeqb_rds_registo SET notif_pendente=0 WHERE autoid=?")
                            ->execute([$ped['registo_id']]);
                    }
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

    // ── Admin: criar novo registo em nome de outro utilizador ────────────
    if (isset($_POST['_acao']) && $_POST['_acao'] === 'admin_novo_registo') {
        $erroAdminReg = null;
        $adm_codigo   = trim($_POST['adm_codigo'] ?? '');
        $adm_nome     = trim($_POST['adm_nome']   ?? '');
        $adm_email    = trim($_POST['adm_email']  ?? '');
        $adm_inicio   = trim($_POST['adm_datainicio'] ?? '');
        $adm_fim      = trim($_POST['adm_datafim']    ?? '');
        $adm_grupo    = (int)($_POST['adm_grupo']    ?? 0);
        $adm_cat      = (int)($_POST['adm_categoria'] ?? 0);
        $adm_resp     = (int)($_POST['adm_responsavel'] ?? 0);
        $adm_outroresp = trim($_POST['adm_outroresponsavel'] ?? '');
        $adm_acessodeq = (int)($_POST['adm_acessodeq'] ?? 0);
        $adm_labs      = isset($_POST['adm_acessos']) && is_array($_POST['adm_acessos'])
                         ? array_map('trim', $_POST['adm_acessos']) : array();

        if ($adm_codigo === '' || $adm_inicio === '' || $adm_grupo === 0) {
            $erroAdminReg = 'Campos obrigatórios em falta (código UP, data início, grupo).';
        }
        if (!$erroAdminReg) {
            try {
                $pdoAdm = Database::connect();
                $pdoAdm->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Verificar/criar colaborador
                $chkCol = $pdoAdm->prepare('SELECT 1 FROM infodeqb_rds_colaborador WHERE codigo=?');
                $chkCol->execute([$adm_codigo]);
                if (!$chkCol->fetchColumn()) {
                    if ($adm_nome === '') {
                        $erroAdminReg = 'Colaborador não encontrado — preencha o Nome para criar.';
                    } else {
                        $pdoAdm->prepare(
                            'INSERT INTO infodeqb_rds_colaborador (codigo,nome,email,createdate) VALUES (?,?,?,?)'
                        )->execute([$adm_codigo, $adm_nome, $adm_email, time()]);
                    }
                }

                if (!$erroAdminReg) {
                    $gabMapAdm = getGabMap($pdoAdm);
                    $labNomes  = array();
                    foreach ($adm_labs as $_lid) {
                        $labNomes[] = isset($gabMapAdm[$_lid]) ? $gabMapAdm[$_lid] : $_lid;
                    }
                    $acessosStr = implode('; ', $labNomes);
                    $acessosId  = implode('; ', $adm_labs);

                    $insAdm = $pdoAdm->prepare(
                        "INSERT INTO infodeqb_rds_registo
                         (codigo,datainicio,datafim,grupo,categoria,responsavel,outroresponsavel,
                          acessodeq,acessos,acessosid,status,createdate,dataregisto,deleted)
                         VALUES (?,?,?,?,?,?,?,?,?,?,'Novo',?,?,0)"
                    );
                    $insAdm->execute([
                        $adm_codigo,
                        $adm_inicio,
                        $adm_fim ?: null,
                        $adm_grupo,
                        $adm_cat ?: null,
                        $adm_resp,
                        $adm_outroresp,
                        $adm_acessodeq,
                        $acessosStr,
                        $acessosId,
                        time(),
                        date('Y-m-d H:i:s'),
                    ]);
                    $newAdmId = (int)$pdoAdm->lastInsertId();
                    if ($newAdmId > 0 && !empty($adm_labs)) {
                        setRegistoAcessos($pdoAdm, $newAdmId, $adm_labs, $gabMapAdm);
                    }
                    header('Location: index.php?tab=pills-new&created=1'); exit;
                }
            } catch (PDOException $e) {
                $erroAdminReg = 'Erro BD: ' . $e->getMessage();
            }
        }
        // Se chegou aqui houve erro — guardar na sessão e redirecionar
        if ($erroAdminReg) {
            $_SESSION['admin_reg_erro'] = $erroAdminReg;
            header('Location: index.php'); exit;
        }
    }
}

// ── Dados de referência para modal Admin ────────────────────────────────
$rowsGrupo   = $pdo->query('SELECT * FROM infodeqb_rds_grupo ORDER BY orderid')->fetchAll(PDO::FETCH_ASSOC);
$rowsCat     = $pdo->query('SELECT * FROM infodeqb_rds_categoria ORDER BY categoriaid')->fetchAll(PDO::FETCH_ASSOC);
$grupoCatMap = getGrupoCategoriasMap($pdo);
$rowsResp    = $pdo->query('SELECT * FROM infodeqb_rds_responsaveis WHERE Codigo != 0 ORDER BY respespaco')->fetchAll(PDO::FETCH_ASSOC);
$gabRowsAdm  = $pdo->query('SELECT * FROM infodeqb_rds_gabinetes WHERE visible != 0 ORDER BY edificio, piso, nomegab')->fetchAll(PDO::FETCH_ASSOC);

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
				<div class="alert alert-info alert-dismissible fade show mb-3 text-start" role="alert">
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
				<?php if (!empty($_GET['created'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
  <i class="fas fa-check-circle me-1"></i>Registo criado com sucesso.
  <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['admin_reg_erro'])): ?>
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
  <i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($_SESSION['admin_reg_erro']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
</div>
<?php unset($_SESSION['admin_reg_erro']); endif; ?>
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

<style>
/* ── Separadores hr/admin ───────────────────────────────────────────────── */
#pills-tab {
  border-bottom: 2px solid #e5e9f0;
  gap: 0;
  flex-wrap: nowrap;
  overflow-x: auto;
  scrollbar-width: none;
}
#pills-tab::-webkit-scrollbar { display: none; }

#pills-tab .nav-item { flex-shrink: 0; }

#pills-tab .nav-link {
  border: none;
  border-bottom: 3px solid transparent;
  border-radius: 0;
  margin-bottom: -2px;
  padding: 10px 18px;
  font-size: .875rem;
  font-weight: 500;
  color: #6c757d;
  background: transparent;
  white-space: nowrap;
  transition: color .15s, border-color .15s;
}
#pills-tab .nav-link:hover {
  color: #1a3a5c;
  border-bottom-color: #b8cfe8;
  background: transparent;
}
#pills-tab .nav-link.active {
  color: #1a3a5c;
  font-weight: 700;
  border-bottom-color: #2475ba;
  background: transparent;
}

/* badges dentro dos tabs */
#pills-tab .nav-link .tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 5px;
  border-radius: 10px;
  font-size: .7rem;
  font-weight: 700;
  line-height: 1;
  margin-left: 6px;
  vertical-align: middle;
}
#pills-tab .nav-link .tab-badge-blue   { background: #2475ba; color: #fff; }
#pills-tab .nav-link .tab-badge-yellow { background: #ffc107; color: #1a1a2e; }
#pills-tab .nav-link .tab-badge-purple { background: #6f42c1; color: #fff; }
</style>

<ul class="nav mb-3" id="pills-tab" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" id="pills-new-tab"
       data-bs-toggle="pill" href="#pills-new" role="tab"
       aria-controls="pills-new" aria-selected="true">
      Novos registos
      <?php if ($newrecords > 0): ?>
        <span class="tab-badge tab-badge-blue"><?= $newrecords ?></span>
      <?php endif; ?>
      <?php if ($semPedidoCount > 0): ?>
        <span class="tab-badge tab-badge-purple"
              title="Registos com acessos sem pedido de validação enviado">
          ⚠ <?= $semPedidoCount ?>
        </span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="pills-pendent-tab"
       data-bs-toggle="pill" href="#pills-pendent" role="tab"
       aria-controls="pills-pendent" aria-selected="false">
      Pendentes
      <?php if ($pendentrecords > 0): ?>
        <span class="tab-badge tab-badge-yellow"><?= $pendentrecords ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="pills-expire-tab"
       data-bs-toggle="pill" href="#pills-expire" role="tab"
       aria-controls="pills-expire" aria-selected="false">
      A expirar
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="pills-active-tab"
       data-bs-toggle="pill" href="#pills-active" role="tab"
       aria-controls="pills-active" aria-selected="false">
      Ativos
      <?php if ($notifPendCount > 0): ?>
        <span class="tab-badge tab-badge-yellow" id="badge-notif-pend"
              title="Clique para ver só os registos com alteração pendente"
              style="cursor:pointer;">
          ⏳ <?= $notifPendCount ?>
        </span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="pills-inactive-tab"
       data-bs-toggle="pill" href="#pills-inactive" role="tab"
       aria-controls="pills-inactive" aria-selected="false">
      Inativos
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="pills-pedidos-tab"
       data-bs-toggle="pill" href="#pills-pedidos" role="tab"
       aria-controls="pills-pedidos" aria-selected="false">
      Pedidos
      <?php if ($pedidosCount > 0): ?>
        <span class="tab-badge tab-badge-yellow"><?= $pedidosCount ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item ms-auto d-flex align-items-center pe-1">
    <button type="button" class="btn btn-sm btn-success"
            data-bs-toggle="modal" data-bs-target="#modalAdminNovoRegisto">
      <i class="fas fa-plus fa-xs me-1"></i>Novo Registo (Admin)
    </button>
  </li>
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
														<th>Ações</th>
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
        // Registos onde existe pelo menos um lab cujo responsável
        // não tem pedido de validação real (mesma lógica do $qSemPedido acima)
        $qMissing = $pdo->query(
            "SELECT DISTINCT r.autoid
             FROM infodeqb_rds_registo r
             WHERE r.status = 'Novo' AND r.deleted = 0
               AND EXISTS (
                   SELECT 1
                   FROM infodeqb_rds_registo_acessos ra
                   JOIN infodeqb_rds_gabinetes g ON g.deqid = ra.lab_id
                   WHERE ra.registo_id = r.autoid
                     AND g.responsavel IS NOT NULL AND g.responsavel != 0
                     AND g.responsavel != 246398
                     AND NOT EXISTS (
                         SELECT 1 FROM infodeqb_rds_validacao v
                         WHERE v.registo_id = r.autoid
                           AND v.resp_codigo = g.responsavel
                     )
               )"
        );
        $semPedidoIds = array();
        foreach ($qMissing->fetchAll(PDO::FETCH_COLUMN) as $_mid) {
            $semPedidoIds[(int)$_mid] = true;
        }

        $row = array();
        if ($sth->execute([
                'Novo'
        ])) {
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                $vs = isset($novoValStatus[(int)$row['autoid']]) ? $novoValStatus[(int)$row['autoid']] : 'none';
                // Badge de estado das validações existentes
                $valBadge = '';
                if ($vs === 'all_ok') {
                    $valBadge = ' <span class="badge badge-success" title="Todas as validações concluídas" style="font-size:.7rem">✓ val.</span>';
                } elseif ($vs === 'rejected') {
                    $valBadge = ' <span class="badge badge-danger" title="Validação rejeitada" style="font-size:.7rem">✗ val.</span>';
                } elseif ($vs === 'pending') {
                    $valBadge = ' <span class="badge badge-warning" title="Validações pendentes" style="font-size:.7rem">⏳ val.</span>';
                }
                // Badge laranja separado: acessos sem pedido de validação enviado
                $semPedidoBadge = '';
                if (isset($semPedidoIds[(int)$row['autoid']])) {
                    $semPedidoBadge = ' <span class="badge" style="background:#6f42c1;color:#fff;font-size:.7rem" title="Tem acessos sem pedido de validação enviado">⚠ sem pedido</span>';
                }
                echo '<tr data-row-id="' . $row['codigo'] . '"' . $getLabsAttr($row['autoid']) . '>';
                echo '<td class="text-start"><input name="selector[' .
                        $row['autoid'] . ']" type="checkbox"></td>';
                echo '<td>' . $row['codigo'] .
                        '<input type="hidden"  name="codigo[' .
                        $row['autoid'] . ']" value="' . $row['codigo'] .
                        '" ></td>';
                $nomeShow = !empty($row['nome'])
                    ? htmlspecialchars($row['nome'])
                    : '<em class="text-muted">' . htmlspecialchars($row['email']) . '</em>';
                echo '<td>' . $nomeShow . $valBadge . $semPedidoBadge . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . $row['grupo_pro'] . '</td>';
                echo '<td>' . $row['datainicio'] . '</td>';
                echo '<td>' . $row['datafim'] . '</td>';
                echo '<td>' . $row['status'] . '</td>';
                echo '<td>' . date("Y-m-d", $row['createdate']) . '</td>';
                $autoidRow = (int)$row['autoid'];
                $link = (strlen($row['codigo']) > 6) ? "https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico=" : "https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=";
                // Condições para os botões de acção
                $temPedidosVal   = isset($novoValStatus[$autoidRow]); // já tem validações solicitadas
                $validacoesOk    = ($vs === 'all_ok');                // todas aprovadas
                $semPedido       = isset($semPedidoIds[$autoidRow]);  // labs sem pedido
                $podeAcessos     = $validacoesOk && !$semPedido;

                echo '<td class="text-nowrap text-center" style="white-space:nowrap">';
                // Ver detalhe
                echo '<a class="btn btn-xs btn-outline-info me-1" title="Ver registo" href="detail.php?id=' . htmlspecialchars($row['codigo']) . '&amp;status=Novo"><i class="far fa-eye fa-xs"></i></a>';
                // SIGARRA
                echo '<a class="btn btn-xs btn-outline-secondary me-1" title="SIGARRA" target="_blank" href="' . $link . htmlspecialchars($row['codigo']) . '"><i class="fas fa-info fa-xs"></i></a>';
                // Solicitar validações
                echo '<button type="button" class="btn btn-xs btn-warning me-1" title="Solicitar validações a responsáveis de laboratório"'
                   . ' onclick="adminAcao(\'solicitar_registo\',' . $autoidRow . ')">'
                   . '<i class="fas fa-user-check fa-xs"></i></button>';
                // Pedir acessos (só activo se validações todas feitas)
                if ($podeAcessos) {
                    echo '<button type="button" class="btn btn-xs btn-success" title="Pedir acessos ao SIGARRA"'
                       . ' onclick="adminAcao(\'solicitar_acessos\',' . $autoidRow . ')">'
                       . '<i class="fas fa-paper-plane fa-xs"></i></button>';
                } else {
                    $titleAcessos = $semPedido ? 'Labs com responsável sem validação solicitada' : ($temPedidosVal ? 'Validações ainda pendentes ou rejeitadas' : 'Solicite validações primeiro');
                    echo '<button type="button" class="btn btn-xs btn-outline-success" disabled title="' . htmlspecialchars($titleAcessos) . '">'
                       . '<i class="fas fa-paper-plane fa-xs"></i></button>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }

        ?>
												</tbody>
												<tfooter>
												<tr>
													<td colspan="10" class="text-end py-2" style="background:#f0f7fa !important;border-top:1px solid #cde0ea !important;">
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
														<td class="text-end" colspan="9" style="vertical-align:middle;">
															<?php if ($notifPendCount > 0): ?>
                  <button type="button" id="btn-filter-notif"
                          class="btn btn-sm btn-outline-warning me-2"
                          title="Ver só registos com alteração pendente">
                    ⏳ Ver <span id="notif-count"><?= $notifPendCount ?></span> pendente<?php echo $notifPendCount > 1 ? 's' : ''; ?>
                  </button>
                  <?php endif; ?>
															<form action="index.php" method="post" id="export-form" style="display:inline-block;">
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
                $isPend = !empty($row['notif_pendente']);
                $trStyle = $isPend ? ' style="background:#fffdf0;"' : '';
                echo '<tr data-row-id="' . $row['codigo'] . '"'
                    . ($isPend ? ' data-notif-pend="1"' : '')
                    . $getLabsAttr($row['autoid'])
                    . $trStyle . '>';
                echo '<td class="text-start"><input name="selector[' .
                        $row['autoid'] . ']" type="checkbox"></td>';
                echo '<td>' . $row['codigo'] .
                        '<input type="hidden"  name="codigo[' .
                        $row['autoid'] . ']" value="' . $row['codigo'] .
                        '" ></td>';
                echo '<td>' . $row['nome'] . ($isPend ? ' <span title="Alteração não comunicada ao SIGARRA" style="color:#d08000;font-size:.75rem;">⏳</span>' : '') . '</td>';
                echo '<td>' . $row['email'] . '</td>';
                echo '<td>' . $row['grupo_pro'] . '</td>';
                echo '<td>' . $row['datainicio'] . '</td>';
                echo '<td>' . $row['datafim'] . '</td>';
                echo '<td>' . formatDate('Y-m-d', $row['dataativo']) . '</td>';
                echo '<td class="text-center">';
                $link = (strlen($row['codigo']) > 6) ? "https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico=" : "https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=";
                echo '<a class="mr-1 ms-1" style="color:#17A2B8" title="Ver registo" href="detail.php?id=' .
                        $row['codigo'] .
                        '"><i class="far fa-eye fa-sm"></i></a>'
                   . '<a class="mr-1 ms-1" style="color:#17A2B8" title="SIGARRA" target="_blank" href="' .
                        $link . $row['codigo'] .
                        '"><i class="fas fa-info fa-sm"></i></a>';
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

// Submete acção individual por registo (evita forms aninhados)
function adminAcao(acao, registoId) {
  var f = document.createElement('form');
  f.method = 'POST';
  f.action = 'validacao-action.php';
  f.style.display = 'none';
  f.innerHTML = '<input name="val_acao" value="' + acao + '">'
              + '<input name="registo_id" value="' + registoId + '">'
              + '<input name="redirect" value="index.php?tab=pills-new">';
  document.body.appendChild(f);
  f.submit();
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
<script>
(function () {
  'use strict';

  // ── Filtro "Ver pendentes" no tab Ativos ─────────────────────────────────
  var btnFilter  = document.getElementById('btn-filter-notif');
  var badgePend  = document.getElementById('badge-notif-pend');
  var activeTab  = document.getElementById('pills-active-tab');
  var activePane = document.getElementById('pills-active');
  var _filtered  = false;

  function applyFilter(on) {
    _filtered = on;
    var rows = activePane ? activePane.querySelectorAll('tbody tr[data-notif-pend]') : [];
    var allRows = activePane ? activePane.querySelectorAll('tbody tr') : [];

    if (on) {
      // Esconder linhas sem a flag
      allRows.forEach(function (r) {
        r.style.display = r.getAttribute('data-notif-pend') === '1' ? '' : 'none';
      });
      if (btnFilter) {
        btnFilter.classList.replace('btn-outline-warning', 'btn-warning');
        btnFilter.querySelector('span') && (btnFilter.querySelector('span').textContent = '✕ Limpar filtro');
      }
    } else {
      allRows.forEach(function (r) { r.style.display = ''; });
      if (btnFilter) {
        btnFilter.classList.replace('btn-warning', 'btn-outline-warning');
        var sp = btnFilter.querySelector('span');
        if (sp) sp.textContent = '⏳ Ver ' + rows.length + ' pendente' + (rows.length !== 1 ? 's' : '');
      }
    }
  }

  function activateAndFilter() {
    // 1. Activar o tab Ativos via Bootstrap
    if (activeTab && typeof bootstrap !== 'undefined') {
      bootstrap.Tab.getOrCreateInstance(activeTab).show();
    } else if (activeTab) {
      activeTab.click();
    }
    // 2. Aplicar filtro (ligeiro delay para o tab terminar de mostrar)
    setTimeout(function () { applyFilter(true); }, 80);
  }

  if (btnFilter) {
    btnFilter.addEventListener('click', function (e) {
      e.stopPropagation();
      applyFilter(!_filtered);
    });
  }

  // Badge no cabeçalho do tab também activa filtro
  if (badgePend) {
    badgePend.addEventListener('click', function (e) {
      e.stopPropagation();
      e.preventDefault();
      activateAndFilter();
    });
  }

  // Se URL tiver ?filter=notif, activar automaticamente
  if (window.location.search.indexOf('filter=notif') !== -1) {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(activateAndFilter, 200);
    });
  }
}());
</script>

<!-- ── Modal: Admin — Novo Registo em nome de utilizador ─────────────── -->
<div class="modal fade" id="modalAdminNovoRegisto" tabindex="-1"
     aria-labelledby="modalAdminNovoRegistoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="index.php" id="formAdminNovoRegisto">
        <input type="hidden" name="_acao" value="admin_novo_registo">
        <div class="modal-header">
          <h5 class="modal-title" id="modalAdminNovoRegistoLabel">
            <i class="fas fa-plus-circle me-2 text-success"></i>Novo Registo (Admin)
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">

          <div class="row g-3">

            <!-- Código UP -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Código UP <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="adm_codigo" id="adm_codigo"
                     placeholder="ex: up202300001" required>
              <div class="form-text">Código institucional (up…)</div>
            </div>

            <!-- Nome -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Nome</label>
              <input type="text" class="form-control" name="adm_nome" id="adm_nome"
                     placeholder="Só se não existir na BD">
              <div class="form-text">Preencher se for colaborador novo</div>
            </div>

            <!-- Email -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Email</label>
              <input type="email" class="form-control" name="adm_email" id="adm_email"
                     placeholder="colaborador@fe.up.pt">
            </div>

            <!-- Data início -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">Data de início <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="adm_datainicio" required>
            </div>

            <!-- Data fim -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">Data de fim</label>
              <input type="date" class="form-control" name="adm_datafim">
            </div>

            <!-- Grupo profissional -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">Grupo profissional <span class="text-danger">*</span></label>
              <select class="form-select" name="adm_grupo" id="adm_grupo" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($rowsGrupo as $rg): ?>
                <option value="<?= (int)$rg['grupoid'] ?>"><?= htmlspecialchars($rg['grupo_pro']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Categoria -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">Categoria</label>
              <select class="form-select" name="adm_categoria" id="adm_categoria">
                <option value="">-- Seleccione --</option>
                <?php foreach ($rowsCat as $rc): ?>
                <option value="<?= (int)$rc['categoriaid'] ?>"><?= htmlspecialchars($rc['categoria_pro']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Responsável -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Responsável</label>
              <select class="form-select" name="adm_responsavel" id="adm_responsavel">
                <option value="0">-- Outro (preencher abaixo) --</option>
                <?php foreach ($rowsResp as $rr): ?>
                <option value="<?= (int)$rr['Codigo'] ?>"><?= htmlspecialchars($rr['respespaco']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Outro responsável (visível só se select = 0) -->
            <div class="col-md-6" id="adm_outroresp_wrap">
              <label class="form-label fw-semibold">Outro responsável</label>
              <input type="text" class="form-control" name="adm_outroresponsavel"
                     placeholder="Nome do responsável">
            </div>

            <!-- Acesso DEQB -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">Acesso DEQB (Porta Norte)</label>
              <select class="form-select" name="adm_acessodeq">
                <option value="0">Não</option>
                <option value="1">Sim</option>
              </select>
            </div>

            <!-- Labs -->
            <div class="col-12">
              <label class="form-label fw-semibold">Laboratórios / Espaços</label>
              <div style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:6px;padding:10px">
                <?php
                $admGabCurPiso = '';
                foreach ($gabRowsAdm as $admGab):
                    if ($admGab['piso'] !== $admGabCurPiso):
                        if ($admGabCurPiso !== '') echo '</div>';
                        $admGabCurPiso = $admGab['piso'];
                        echo '<div class="iq-checkgroup"><span class="iq-checkgroup-label">'
                           . htmlspecialchars($admGabCurPiso) . '</span>';
                    endif;
                ?>
                <label style="display:flex;align-items:center;gap:6px;font-weight:normal;margin-bottom:2px">
                  <input type="checkbox" name="adm_acessos[]" value="<?= htmlspecialchars($admGab['deqid']) ?>">
                  <span><?= htmlspecialchars($admGab['nomegab']) ?></span>
                  <small class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($admGab['deqid']) ?></small>
                </label>
                <?php endforeach; ?>
                <?php if ($admGabCurPiso !== '') echo '</div>'; ?>
              </div>
            </div>

          </div><!-- /row -->
        </div><!-- /modal-body -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i>Criar Registo
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  // Filtro dinâmico de categorias no modal admin
  var grupoCatMap = <?= json_encode($grupoCatMap, JSON_UNESCAPED_UNICODE) ?>;
  var selGrupo = document.getElementById('adm_grupo');
  var selCat   = document.getElementById('adm_categoria');
  var allCatOpts = selCat ? Array.prototype.slice.call(selCat.options) : [];

  function filterCats() {
    if (!selGrupo || !selCat) return;
    var gid = parseInt(selGrupo.value, 10);
    var allowed = grupoCatMap[gid] || null;
    var prevVal = selCat.value;
    selCat.innerHTML = '';
    var defOpt = document.createElement('option');
    defOpt.value = ''; defOpt.textContent = '-- Seleccione --';
    selCat.appendChild(defOpt);
    allCatOpts.forEach(function (opt) {
      if (!opt.value) return;
      if (!allowed || allowed.indexOf(parseInt(opt.value, 10)) !== -1) {
        selCat.appendChild(opt.cloneNode(true));
      }
    });
    selCat.value = prevVal;
  }

  if (selGrupo) selGrupo.addEventListener('change', filterCats);

  // Mostrar/ocultar campo "outro responsável"
  var selResp      = document.getElementById('adm_responsavel');
  var wrapOutroresp = document.getElementById('adm_outroresp_wrap');
  function toggleOutroresp() {
    if (!selResp || !wrapOutroresp) return;
    wrapOutroresp.style.display = (selResp.value === '0') ? '' : 'none';
  }
  if (selResp) {
    selResp.addEventListener('change', toggleOutroresp);
    toggleOutroresp();
  }

  // Autocomplete colaborador por código UP
  var inpCodigo = document.getElementById('adm_codigo');
  var inpNome   = document.getElementById('adm_nome');
  var inpEmail  = document.getElementById('adm_email');
  if (inpCodigo) {
    var _acTimer = null;
    inpCodigo.addEventListener('input', function () {
      clearTimeout(_acTimer);
      var cod = this.value.trim();
      if (!cod) return;
      _acTimer = setTimeout(function () {
        fetch('ajax-colab.php?codigo=' + encodeURIComponent(cod))
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
            if (data && data.nome) {
              if (inpNome  && !inpNome.value)  inpNome.value  = data.nome;
              if (inpEmail && !inpEmail.value) inpEmail.value = data.email || '';
            }
          })
          .catch(function () {});
      }, 400);
    });
  }
}());
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
