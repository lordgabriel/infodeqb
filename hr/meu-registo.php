<?php
/**
 * InfoDEQB / HR — O meu registo
 *
 * Lógica de processamento de alterações:
 *
 *  Dados pessoais (nome, emailalt, telefone)
 *    → aplicados imediatamente em infodeqb_rds_colaborador
 *
 *  Mudança de grupo profissional  OU  pedido explícito de novo registo
 *    → cria infodeqb_rds_registo status='Novo' com substitui_registo=autoid_actual
 *      (vai para tab "Novos registos" no admin, workflow normal)
 *
 *  Outras alterações de registo (datas, unidade, posto, extensão, responsável, categoria)
 *    → aplicadas imediatamente em infodeqb_rds_registo
 *
 *  Alteração de acessos a labs / acesso DEQ
 *    → cria infodeqb_rds_pedido tipo='alteracao_labs' para aprovação pelo secretariado
 *
 *  Bloqueio: se houver infodeqb_rds_registo Novo/Pendente OU infodeqb_rds_pedido Pendente,
 *    o formulário fica bloqueado (não se pode submeter mais pedidos).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$codigoNum = preg_replace('/[^0-9]/', '', $_SESSION['Code'] ?? '');
$language  = (substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) === 'pt') ? 'pt' : 'en';
include ROOT_DIR . '/infodeqb/hr/lang/lang.' . $language . '.php';

// ── Dados de referência ───────────────────────────────────────────
$grupos      = $pdo->query('SELECT * FROM infodeqb_rds_grupo ORDER BY orderid')->fetchAll(PDO::FETCH_ASSOC);
$cats        = $pdo->query('SELECT * FROM infodeqb_rds_categoria ORDER BY categoriaid')->fetchAll(PDO::FETCH_ASSOC);
$grupoCatMap = getGrupoCategoriasMap($pdo);
$resps   = $pdo->query('SELECT * FROM infodeqb_rds_responsaveis WHERE Codigo != 0 ORDER BY respespaco')->fetchAll(PDO::FETCH_ASSOC);
$gabRows = $pdo->query('SELECT * FROM infodeqb_rds_gabinetes WHERE visible != 0 ORDER BY edificio, piso, nomegab')->fetchAll(PDO::FETCH_ASSOC);

// mapa deqid → gabid para diff (deduplicado por gabid)
$gabMap = array();
foreach ($gabRows as $rg) $gabMap[$rg['deqid']] = $rg['gabid'];

// ── Helpers ───────────────────────────────────────────────────────
function loadRegisto($pdo, $codigo) {
    $s = $pdo->prepare(
        'SELECT r.*, c.nome, c.email, c.emailalt, c.telefone,
                g.grupo_pro, IF(r.responsavel=0, r.outroresponsavel, rsp.respespaco) AS respespaco
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador c     ON c.codigo  = r.codigo
         JOIN infodeqb_rds_grupo g           ON g.grupoid = r.grupo
         LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo = r.responsavel
         WHERE r.codigo = ? AND r.deleted = 0
         ORDER BY CASE r.status
           WHEN "Ativo" THEN 1 WHEN "Pendente" THEN 2
           WHEN "Novo"  THEN 3 WHEN "Inativo"  THEN 4 ELSE 5 END,
           r.datafim DESC LIMIT 1'
    );
    $s->execute([$codigo]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

// ── Carregar dados ────────────────────────────────────────────────
$registoAtivo = $codigoNum ? loadRegisto($pdo, $codigoNum) : null;

$colaborador = null;
if ($codigoNum) {
    $sc = $pdo->prepare('SELECT * FROM infodeqb_rds_colaborador WHERE codigo = ? AND (deleted IS NULL OR deleted != 1)');
    $sc->execute([$codigoNum]);
    $colaborador = $sc->fetch(PDO::FETCH_ASSOC);
}

// ── Pedidos pendentes (inclui Aguarda_SIGARRA) ────────────────────
$sp = null;
$pedidosPendentes = array();
if ($codigoNum) {
    $sp = $pdo->prepare(
        "SELECT * FROM infodeqb_rds_pedido WHERE codigo = ?
         AND status IN ('Pendente','Aguarda_SIGARRA')
         ORDER BY criado_em DESC"
    );
    $sp->execute([$codigoNum]);
    $pedidosPendentes = $sp->fetchAll(PDO::FETCH_ASSOC);
}

// ── Flags de estado ───────────────────────────────────────────────
$isAtivo   = $registoAtivo && $registoAtivo['status'] === 'Ativo';
$isInativo = $registoAtivo && $registoAtivo['status'] === 'Inativo';

// Bloqueado se QUALQUER registo (incluindo paralelos Novo/Pendente) existe
// Não basta verificar $registoAtivo — o registo Ativo pode coexistir com um Novo paralelo
// (ex: pedido de mudança de grupo aprovado, novo registo ainda não activado)
$qBlk = $pdo->prepare(
    "SELECT status FROM infodeqb_rds_registo
     WHERE codigo=? AND deleted=0 AND status IN ('Novo','Pendente')
     ORDER BY createdate DESC LIMIT 1"
);
$qBlk->execute([$codigoNum]);
$blkReg = $qBlk->fetch(PDO::FETCH_ASSOC);
$temPendenteReg = (bool)$blkReg;

$temPendentePed = !empty($pedidosPendentes);
$temPendente    = $temPendenteReg || $temPendentePed;

// Pode usar o formulário de alteração
$podeAlterar = ($isAtivo || $isInativo) && !$temPendente;

// ── Processar POST ────────────────────────────────────────────────
$flashMsg  = '';
$flashType = 'success';
$msgs      = array();

// Dados pessoais: guarda directamente, sem aprovação
if (!empty($_POST) && $codigoNum && ($_POST['_acao'] ?? '') === 'dados_pessoais' && $colaborador) {
    try {
        $tfNum  = preg_replace('/[^0-9]/', '', trim($_POST['telefone_numero'] ?? $_POST['telefone'] ?? ''));
        $tfInd  = trim($_POST['telefone_indicativo'] ?? '+351');
        $tfFull = $tfNum ? $tfInd . ' ' . $tfNum : '';
        $pdo->prepare('UPDATE infodeqb_rds_colaborador SET emailalt=?, telefone=? WHERE codigo=?')
            ->execute(array(
                trim($_POST['emailalt'] ?? ''),
                $tfFull,
                $codigoNum,
            ));
        $flashMsg  = 'Dados pessoais atualizados.';
        $flashType = 'success';
        $sc->execute([$codigoNum]);
        $colaborador  = $sc->fetch(PDO::FETCH_ASSOC);
        $registoAtivo = loadRegisto($pdo, $codigoNum);
        $isAtivo   = $registoAtivo && $registoAtivo['status'] === 'Ativo';
        $isInativo = $registoAtivo && $registoAtivo['status'] === 'Inativo';
    } catch (Exception $e) {
        $flashMsg  = 'Erro ao atualizar dados pessoais.';
        $flashType = 'danger';
    }
}

// Formulário completo de alteração ao registo
if (!empty($_POST) && $codigoNum && $podeAlterar && ($_POST['_acao'] ?? '') !== 'dados_pessoais') {

    $d = array(
        'nome'             => ($colaborador && !empty($colaborador['nome'])) ? (string)$colaborador['nome'] : (isset($_SESSION['CommonName']) ? $_SESSION['CommonName'] : ''),
        'emailalt'         => trim($_POST['emailalt']         ?? ''),
        'telefone'         => (function() {
            $n = preg_replace('/[^0-9]/', '', trim($_POST['telefone_numero'] ?? $_POST['telefone'] ?? ''));
            $i = trim($_POST['telefone_indicativo'] ?? '+351');
            return $n ? $i . ' ' . $n : '';
        })(),
        'datainicio'       => trim($_POST['datainicio']       ?? ''),
        'datafim'          => trim($_POST['datafim']          ?? ''),
        'unidade'          => trim($_POST['unidade']          ?? ''),
        'workplace'        => trim($_POST['workplace']        ?? ''),
        'extensao'         => trim($_POST['extensao']         ?? ''),
        'grupo'            => (int)($_POST['grupo']           ?? 0),
        'categoria'        => (int)($_POST['categoria']       ?? 0),
        'responsavel'      => (int)($_POST['responsavel']     ?? 0),
        'outroresponsavel' => trim($_POST['outroresponsavel'] ?? ''),
        'acessodeq'        => (int)($_POST['acessodeq']       ?? 0),
        'acessos'          => isset($_POST['acessos']) ? array_values(array_filter((array)$_POST['acessos'])) : array(),
        'curso'            => trim($_POST['curso']            ?? ''),
        'observacoes'      => trim($_POST['observacoes']      ?? ''),
    );

    $novoRegisoFlag = ($_POST['_novo_registo'] ?? '0') === '1';
    $grupoMudou     = $registoAtivo && ($d['grupo'] !== (int)$registoAtivo['grupo']);
    // Inativo → sempre cria novo registo (novo período)
    $criarNovoReg   = $isInativo || $novoRegisoFlag || $grupoMudou;

    // Flags para email a enviar APÓS commit
    $_pendEmailNovoReg = null;
    $_pendEmailPedAlt  = null;

    try {
        $pdo->beginTransaction();

        // ── 1. Dados pessoais → aplicar imediatamente ─────────────
        if ($colaborador) {
            // nome e email vêm sempre do Shibboleth — apenas emailalt e telefone são editáveis
            $pessoalMudou =
                $d['emailalt'] !== (string)($colaborador['emailalt'] ?? '') ||
                $d['telefone'] !== (string)($colaborador['telefone'] ?? '');
            if ($pessoalMudou) {
                $pdo->prepare(
                    'UPDATE infodeqb_rds_colaborador SET emailalt=?, telefone=? WHERE codigo=?'
                )->execute(array($d['emailalt'], $d['telefone'], $codigoNum));
                $msgs[] = 'Dados pessoais atualizados.';
            }
        }

        if ($criarNovoReg) {
            // ── 2a. Grupo mudou / novo período / Inativo → novo infodeqb_rds_registo ──
            $ac      = implode('; ', $d['acessos']);
            // Só substitui registo Ativo (Inativo já está inativo)
            $substId = $isAtivo ? (int)$registoAtivo['autoid'] : null;

            $insertStmt = $pdo->prepare(
                "INSERT INTO infodeqb_rds_registo
                 (codigo,datainicio,datafim,responsavel,outroresponsavel,acessodeq,grupo,categoria,
                  acessos,acessosid,curso,createdate,dataregisto,status,unidade,local_trabalho,extensao,
                  substitui_registo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Novo',?,?,?,?)"
            );
            $insertStmt->execute(array(
                $codigoNum, $d['datainicio'], $d['datafim'],
                $d['responsavel'], $d['outroresponsavel'], $d['acessodeq'],
                $d['grupo'], $d['categoria'], $ac, $ac, $d['curso'],
                time(), date('Y-m-d H:i:s'),
                $d['unidade'], $d['workplace'], $d['extensao'],
                $substId,
            ));
            $novoRegistoId = (int)$pdo->lastInsertId();
            setRegistoAcessos($pdo, $novoRegistoId, $d['acessos'], $gabMap);

            if ($grupoMudou) {
                $msgs[] = 'Pedido de novo registo (mudança de grupo profissional) criado e enviado para validação.';
                $_tipoReg = 'Mudança de grupo profissional';
            } elseif ($isInativo) {
                $msgs[] = 'Pedido de renovação criado e enviado para validação.';
                $_tipoReg = 'Renovação / novo período';
            } else {
                $msgs[] = 'Pedido de novo registo criado e enviado para validação.';
                $_tipoReg = 'Novo registo';
            }
            $_pendEmailNovoReg = array(
                'nome'   => $d['nome'] ?: ($colaborador['nome'] ?? ''),
                'email'  => $colaborador['email'] ?? '',
                'codigo' => $codigoNum,
                'inicio' => $d['datainicio'],
                'fim'    => $d['datafim'],
                'tipo'   => $_tipoReg,
            );

        } elseif ($registoAtivo) {
            // ── 2b. Alterações sem mudança de grupo ───────────────

            // datafim de registo Ativo → pedido de aprovação (implica notificação SIGARRA)
            // Para Pendente/Novo → atualização imediata (SIGARRA ainda não agiu)
            $isRegAtivo   = $registoAtivo['status'] === 'Ativo';
            $datafimMudou = $d['datafim'] !== (string)$registoAtivo['datafim'];

            // Campos de registo (sem labs; datafim só imediato se registo não for Ativo)
            $nonLabMudou =
                $d['datainicio']       !== (string)$registoAtivo['datainicio']           ||
                (!$isRegAtivo && $datafimMudou)                                          ||
                $d['unidade']          !== (string)($registoAtivo['unidade']        ?? '')||
                $d['workplace']        !== (string)($registoAtivo['local_trabalho']  ?? '')||
                $d['extensao']         !== (string)($registoAtivo['extensao']        ?? '')||
                $d['responsavel']      !== (int)$registoAtivo['responsavel']             ||
                $d['outroresponsavel'] !== (string)($registoAtivo['outroresponsavel'] ?? '')||
                $d['categoria']        !== (int)$registoAtivo['categoria'];

            if ($nonLabMudou) {
                // Se registo Ativo com datafim mudada: mantém datafim antigo (pedido trata disso)
                $datafimUpdate = ($isRegAtivo && $datafimMudou)
                    ? (string)$registoAtivo['datafim']
                    : $d['datafim'];
                $pdo->prepare(
                    'UPDATE infodeqb_rds_registo
                     SET datainicio=?,datafim=?,unidade=?,local_trabalho=?,extensao=?,
                         responsavel=?,outroresponsavel=?,categoria=?
                     WHERE autoid=?'
                )->execute(array(
                    $d['datainicio'], $datafimUpdate, $d['unidade'],
                    $d['workplace'], $d['extensao'],
                    $d['responsavel'], $d['outroresponsavel'], $d['categoria'],
                    (int)$registoAtivo['autoid'],
                ));
                $msgs[] = 'Dados de registo atualizados.';
            }

            // ── Pedido unificado para SIGARRA (datafim e/ou labs) ────────
            // Requer SIGARRA quando: datafim muda num registo Ativo, ou labs mudam
            $oldDeqids = getRegistoAcessos($pdo, (int)$registoAtivo['autoid']);
            $newDeqids = $d['acessos'];
            $oldSorted = $oldDeqids; sort($oldSorted);
            $newSorted = $newDeqids; sort($newSorted);
            $labsMudaram = $d['acessodeq'] !== (int)$registoAtivo['acessodeq'] || $oldSorted !== $newSorted;

            $dadosSig = array();
            $dadosAnt = array();

            if ($isRegAtivo && $datafimMudou) {
                $dadosSig['datafim_novo']   = $d['datafim'];
                $dadosSig['datafim_antigo'] = (string)$registoAtivo['datafim'];
                $dadosAnt['datafim']        = (string)$registoAtivo['datafim'];
            }
            if ($labsMudaram) {
                $adicionados = array_values(array_diff($newDeqids, $oldDeqids));
                $removidos   = array_values(array_diff($oldDeqids, $newDeqids));
                // Usar gabid (deduplicado) em vez de nomegab
                $_toGabidsU = function($deqids) use ($gabMap) {
                    $gabids = array();
                    foreach ($deqids as $did) {
                        $gid = isset($gabMap[$did]) ? $gabMap[$did] : $did;
                        if (!in_array($gid, $gabids, true)) $gabids[] = $gid;
                    }
                    return $gabids;
                };
                $newNomes = $_toGabidsU($newDeqids);
                $oldNomes = $_toGabidsU($oldDeqids);
                $addNomes = $_toGabidsU($adicionados);
                $remNomes = $_toGabidsU($removidos);
                // Porta Norte: Acesso DEQB como gabid regular
                $oldDeqAc = (int)$registoAtivo['acessodeq'];
                $newDeqAc = (int)$d['acessodeq'];
                if ($oldDeqAc !== $newDeqAc) {
                    if ($newDeqAc === 1 && !in_array('Porta Norte', $addNomes, true)) {
                        array_unshift($addNomes, 'Porta Norte');
                    } elseif ($newDeqAc === 0 && !in_array('Porta Norte', $remNomes, true)) {
                        array_unshift($remNomes, 'Porta Norte');
                    }
                }
                if ($newDeqAc === 1 && !in_array('Porta Norte', $newNomes, true)) {
                    array_unshift($newNomes, 'Porta Norte');
                }
                if ($oldDeqAc === 1 && !in_array('Porta Norte', $oldNomes, true)) {
                    array_unshift($oldNomes, 'Porta Norte');
                }
                $dadosSig['acessodeq']               = $d['acessodeq'];
                $dadosSig['acessos']                 = $newDeqids;
                $dadosSig['acessos_nomes']           = $newNomes;
                $dadosSig['labs_adicionados']             = $adicionados;
                $dadosSig['labs_adicionados_nomes']       = $addNomes;
                $dadosSig['labs_removidos']               = $removidos;
                $dadosSig['labs_removidos_nomes']         = $remNomes;
                $dadosAnt['acessodeq']     = $oldDeqAc;
                $dadosAnt['acessos']       = $oldDeqids;
                $dadosAnt['acessos_nomes'] = $oldNomes;
            }

            if (!empty($dadosSig)) {
                // Verificar se já existe pedido pendente para este registo → merge
                $chkPed = $pdo->prepare(
                    "SELECT id, dados_json, dados_anteriores FROM infodeqb_rds_pedido
                     WHERE registo_id=? AND status='Pendente'
                       AND tipo IN ('alteracao_sigarra','alteracao_labs','alteracao_datafim')"
                );
                $chkPed->execute(array((int)$registoAtivo['autoid']));
                $existPed = $chkPed->fetch(PDO::FETCH_ASSOC);

                if ($existPed) {
                    $existDados = json_decode($existPed['dados_json'],     true) ?: array();
                    $existAnt   = json_decode($existPed['dados_anteriores'] ?? 'null', true) ?: array();
                    $pdo->prepare(
                        "UPDATE infodeqb_rds_pedido
                         SET tipo='alteracao_sigarra', dados_json=?, dados_anteriores=?, observacoes=?
                         WHERE id=?"
                    )->execute(array(
                        json_encode(array_merge($existDados, $dadosSig), JSON_UNESCAPED_UNICODE),
                        json_encode(array_merge($existAnt,   $dadosAnt), JSON_UNESCAPED_UNICODE),
                        $d['observacoes'] ?: null,
                        $existPed['id'],
                    ));
                    $msgs[] = 'Pedido de alteração atualizado (data e/ou acessos).';
                    $_pendEmailPedAlt = array(
                        'nome'    => $d['nome'] ?: ($colaborador['nome'] ?? ''),
                        'email'   => $colaborador['email'] ?? '',
                        'codigo'  => $codigoNum,
                        'detalhe' => 'Atualização de data e/ou acessos a laboratórios',
                    );
                } else {
                    $pdo->prepare(
                        "INSERT INTO infodeqb_rds_pedido
                         (tipo,origem,codigo,registo_id,dados_json,dados_anteriores,observacoes,status)
                         VALUES ('alteracao_sigarra','utilizador',?,?,?,?,?,'Pendente')"
                    )->execute(array(
                        $codigoNum,
                        (int)$registoAtivo['autoid'],
                        json_encode($dadosSig, JSON_UNESCAPED_UNICODE),
                        json_encode($dadosAnt, JSON_UNESCAPED_UNICODE),
                        $d['observacoes'] ?: null,
                    ));
                    $msgs[] = 'Pedido de alteração submetido para aprovação pelo secretariado.';
                    $_pendEmailPedAlt = array(
                        'nome'    => $d['nome'] ?: ($colaborador['nome'] ?? ''),
                        'email'   => $colaborador['email'] ?? '',
                        'codigo'  => $codigoNum,
                        'detalhe' => 'Alteração de data de fim e/ou acessos a laboratórios',
                    );
                }
            }

            if (!$nonLabMudou && empty($dadosSig) && empty($msgs)) {
                $msgs[] = 'Nenhuma alteração detectada.';
                $flashType = 'info';
            }
        }

        $pdo->commit();
        if ($flashType !== 'info') $flashType = 'success';
        $flashMsg = implode(' ', $msgs);

        // ── Emails de notificação (fora da transacção) ────────────
        if ($_pendEmailNovoReg && !empty($_pendEmailNovoReg['email'])) {
            _emailNovoRegisto(
                $_pendEmailNovoReg['nome'],
                $_pendEmailNovoReg['email'],
                $_pendEmailNovoReg['codigo'],
                $_pendEmailNovoReg['inicio'],
                $_pendEmailNovoReg['fim'],
                $_pendEmailNovoReg['tipo']
            );
        }
        if ($_pendEmailPedAlt && !empty($_pendEmailPedAlt['email'])) {
            _emailPedidoAlteracao(
                $_pendEmailPedAlt['nome'],
                $_pendEmailPedAlt['email'],
                $_pendEmailPedAlt['codigo'],
                $_pendEmailPedAlt['detalhe']
            );
        }

        // Recarregar estado
        $registoAtivo = loadRegisto($pdo, $codigoNum);
        $sc->execute([$codigoNum]);
        $colaborador = $sc->fetch(PDO::FETCH_ASSOC);

        $sp->execute([$codigoNum]);
        $pedidosPendentes = $sp->fetchAll(PDO::FETCH_ASSOC);

        $isAtivo   = $registoAtivo && $registoAtivo['status'] === 'Ativo';
        $isInativo = $registoAtivo && $registoAtivo['status'] === 'Inativo';
        $qBlk->execute([$codigoNum]);
        $blkReg         = $qBlk->fetch(PDO::FETCH_ASSOC);
        $temPendenteReg = (bool)$blkReg;
        $temPendentePed = !empty($pedidosPendentes);
        $temPendente    = $temPendenteReg || $temPendentePed;
        $podeAlterar    = ($isAtivo || $isInativo) && !$temPendente;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flashMsg  = 'Erro ao processar o pedido. Tente novamente.';
        $flashType = 'danger';
        error_log('meu-registo error: ' . $e->getMessage());
    }
}

// ── Todos os registos (para a tabela) ────────────────────────────
$todosRegistos = array();
if ($codigoNum) {
    $qAll = $pdo->prepare(
        'SELECT r.autoid, r.status, r.datainicio, r.datafim,
                r.unidade, r.acessos, r.acessodeq,
                g.grupo_pro
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_grupo g ON g.grupoid = r.grupo
         WHERE r.codigo = ? AND r.deleted = 0
         ORDER BY
           CASE r.status
             WHEN "Ativo" THEN 1 WHEN "Pendente" THEN 2
             WHEN "Novo"  THEN 3 WHEN "Inativo"  THEN 4 ELSE 5 END,
           r.datafim DESC'
    );
    $qAll->execute([$codigoNum]);
    $todosRegistos = $qAll->fetchAll(PDO::FETCH_ASSOC);
}

// ── Sem registo → ir para formulário de novo registo ─────────────
// (só redireciona se não há flash de um POST acabado de processar)
if (empty($flashMsg) && (!$colaborador || empty($todosRegistos))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/hr/index.php');
    exit;
}

// ── Botão de topo: etiqueta e JS dependem do estado ──────────────
$topBtnLabel = '';
$topBtnJs    = '';
if ($podeAlterar && $isAtivo) {
    $topBtnLabel = '<i class="fas fa-plus me-1"></i>Pedir novo registo';
    $topBtnJs    = "abrirForm(true)";
} elseif ($podeAlterar && $isInativo) {
    $topBtnLabel = '<i class="fas fa-redo me-1"></i>Renovar acesso';
    $topBtnJs    = "abrirForm(false)";
}

$pageTitle = 'O meu registo';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center">
  <h1 class="mr-auto"><i class="fas fa-id-card fa-sm me-2 text-muted"></i>Os meus registos</h1>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" role="alert" style="font-size:.85rem">
  <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : ($flashType === 'info' ? 'info-circle' : 'exclamation-circle') ?> me-2"></i>
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php if (!$codigoNum): ?>
<div class="alert alert-warning">Não foi possível determinar o seu código FEUP.</div>

<?php else: ?>

<?php /* ── Pedidos em análise ──────────────────────────────────── */ ?>
<?php
$tipoLabelsU = array(
    'novo'               => 'Novo registo',
    'alteracao'          => 'Alteração de dados',
    'novo_registo'       => 'Mudança de grupo',
    'alteracao_labs'     => 'Alteração de acessos a laboratórios',
    'alteracao_datafim'  => 'Alteração de data de fim',
    'alteracao_sigarra'  => 'Alteração (a enviar ao SIGARRA)',
);
?>
<?php if ($pedidosPendentes): ?>
<div class="card mb-3 border-warning">
  <div class="card-header py-2 d-flex align-items-center" style="background:#fffbeb">
    <i class="fas fa-clock text-warning me-2"></i>
    <strong style="color:#92400e" class="mr-auto">Pedidos em análise</strong>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0" style="font-size:.82rem">
      <thead class="">
        <tr><th>Tipo</th><th><?= t('STATUS') ?></th><th>Submetido em</th><th>Observações</th></tr>
      </thead>
      <tbody>
      <?php foreach ($pedidosPendentes as $p):
        $tLabel = isset($tipoLabelsU[$p['tipo']]) ? $tipoLabelsU[$p['tipo']] : $p['tipo'];
        $estadoBadge = $p['status'] === 'Aguarda_SIGARRA'
            ? '<span class="badge badge-secondary">Aguarda SIGARRA</span>'
            : '<span class="badge badge-warning">Em análise</span>';
      ?>
      <tr>
        <td><?= htmlspecialchars($tLabel) ?></td>
        <td><?= $estadoBadge ?></td>
        <td><?= htmlspecialchars(substr($p['criado_em'], 0, 16)) ?></td>
        <td><?= htmlspecialchars($p['observacoes'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php if ($temPendenteReg && !$pedidosPendentes): ?>
<div class="card mb-3 border-info">
  <div class="card-header py-2 d-flex align-items-center" style="background:#e8f4f8">
    <i class="fas fa-hourglass-half text-info me-2"></i>
    <strong style="color:#0c5460" class="mr-auto">Registo em processamento</strong>
  </div>
  <div class="card-body py-2" style="font-size:.85rem">
    Existe um registo no estado <strong><?= htmlspecialchars($blkReg['status']) ?></strong> a aguardar processamento pelo secretariado.
    Não é possível submeter novas alterações até este ser ativado.
  </div>
</div>
<?php endif; ?>

<?php /* ── Barra de acção: botão fora da tabela ─────────────────── */ ?>
<div class="d-flex align-items-center mb-3">
  <h5 class="mb-0 me-auto text-secondary" style="font-size:.95rem;font-weight:600">
    <i class="fas fa-list fa-sm me-1"></i>Registos
  </h5>
  <?php if ($topBtnLabel): ?>
  <button class="btn btn-primary btn-sm" onclick="<?= $topBtnJs ?>">
    <?= $topBtnLabel ?>
  </button>
  <?php elseif ($temPendente): ?>
  <button class="btn btn-secondary btn-sm" disabled
          title="Aguarda processamento de pedido em curso">
    <i class="fas fa-lock me-1"></i>Novo registo indisponível
  </button>
  <?php endif; ?>
</div>

<?php /* ── Tabela de registos ────────────────────────────────────── */ ?>
<div class="card mb-4">
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0">
      <thead class="">
        <tr>
          <th style="width:7em"><?= t('STATUS') ?></th>
          <th>Grupo profissional</th>
          <th style="width:6em">Início</th>
          <th style="width:6em">Fim</th>
          <th>Acessos</th>
          <th style="width:8em" class="text-center"><?= t('ACTIONS') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($todosRegistos as $reg):
        $rAtivo = $reg['status'] === 'Ativo';
        $rBadges = array('Ativo'=>'badge-success','Pendente'=>'badge-warning','Novo'=>'badge-info','Inativo'=>'badge-secondary');
        $rBadge  = isset($rBadges[$reg['status']]) ? $rBadges[$reg['status']] : 'badge-secondary';
        // Acessos resumo
        $acessoRes = array();
        if ($reg['acessodeq']) $acessoRes[] = 'DEQ';
        $nLabs = count(getRegistoAcessos($pdo, (int)$reg['autoid']));
        if ($nLabs) $acessoRes[] = $nLabs . ' lab' . ($nLabs > 1 ? 's' : '');
      ?>
      <tr class="<?= $rAtivo ? 'table-success' : '' ?>">
        <td class="align-middle">
          <span class="badge <?= $rBadge ?>"><?= htmlspecialchars($reg['status']) ?></span>
        </td>
        <td class="align-middle"><?= htmlspecialchars($reg['grupo_pro']) ?></td>
        <td class="align-middle"><?= htmlspecialchars($reg['datainicio']) ?></td>
        <td class="align-middle"><?= htmlspecialchars($reg['datafim']) ?></td>
        <td class="align-middle">
          <?php if ($acessoRes): ?>
            <small class="text-muted"><?= implode(', ', $acessoRes) ?></small>
          <?php else: ?>
            <small class="text-muted">—</small>
          <?php endif; ?>
        </td>
        <td class="align-middle text-center text-nowrap">
          <a href="<?= HTTP_DIR ?>/infodeqb/hr/meu-registo-detalhe.php?id=<?= (int)$reg['autoid'] ?>"
             class="btn btn-xs btn-outline-secondary me-1" title="Ver detalhe">
            <i class="fas fa-eye fa-xs"></i>
          </a>
          <?php if ($reg['status'] === 'Ativo' && $podeAlterar): ?>
            <button class="btn btn-xs btn-outline-primary" onclick="abrirForm(false)"
                    title="Solicitar alteração a este registo">
              <i class="fas fa-edit fa-xs"></i>
            </button>
          <?php elseif (in_array($reg['status'], array('Novo','Pendente'))): ?>
            <span class="text-muted" title="Em validação"><i class="fas fa-clock fa-xs"></i></span>
          <?php elseif ($temPendente && $reg['status'] === 'Ativo'): ?>
            <span class="text-muted" title="Pedido pendente"><i class="fas fa-lock fa-xs"></i></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php /* ── Formulário de alteração (oculto, abre via botões acima) */ ?>
<?php if ($podeAlterar): ?>
<div id="formAlterar" style="display:none">
<div class="card border-primary mb-4">
  <div class="card-header py-2 d-flex align-items-center">
    <strong class="mr-auto" id="formTitle">Solicitar alteração</strong>
    <button type="button" class="btn btn-sm btn-outline-secondary ms-3"
            onclick="document.getElementById('formAlterar').style.display='none'">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <div class="card-body">

    <div id="avisoGrupo" class="alert alert-warning py-2 mb-3" style="display:none;font-size:.83rem">
      <i class="fas fa-exclamation-triangle me-1"></i>
      <strong>Mudança de grupo:</strong> será criado um novo registo e o actual passará a Inativo quando o novo for ativado.
    </div>
    <div id="avisoNovoReg" class="alert alert-warning py-2 mb-3 text-start" style="display:none;font-size:.83rem; justify-content:flex-start;;">
      <i class="fas fa-exclamation-triangle me-1"></i>
      <strong>Novo registo:</strong> o registo actual ficará Inativo quando o novo for ativado. Preencha as novas datas.
    </div>
    <div id="avisoLabs" class="alert alert-secondary py-2 mb-3" style="display:none;font-size:.83rem">
      <i class="fas fa-info-circle me-1"></i>
      A alteração de acessos a laboratórios requer aprovação pelo secretariado. Os restantes campos são aplicados imediatamente.
    </div>

    <form method="post" class="iq-form-2col-wrap" id="mainForm">
      <input type="hidden" name="_novo_registo" id="_novo_registo" value="0">

      <div class="iq-form-2col">

        <!-- Coluna esquerda -->
        <div class="iq-form-col">
          <div class="iq-form-section">
            <div class="iq-form-section-title">
              Dados Pessoais
            
            </div>
            <div class="form-group">
              <label>Nome</label>
              <input type="text" class="form-control" readonly
                     value="<?= htmlspecialchars($registoAtivo['nome'] ?: ($colaborador['nome'] ?: (isset($_SESSION['CommonName']) ? $_SESSION['CommonName'] : ''))) ?>"
                     style="background:#f8f9fa;cursor:not-allowed;"
                     title="O nome não pode ser alterado aqui. Contacte o secretariado.">
              <small class="form-text text-muted">Para alterar nome, email ou código UP, contacte o secretariado.</small>
            </div>
            <div class="row">
              <div class="col-md-5 form-group">
                <label>Email alternativo</label>
                <input type="email" name="emailalt" class="form-control"
                       value="<?= htmlspecialchars($colaborador['emailalt'] ?? '') ?>">
              </div>
              <div class="col-md-7 form-group">
                <label>Telefone</label>
                <?php renderPhoneInput($colaborador['telefone'] ?? '', 'sm', true); ?>
              </div>
            </div>
          </div>

          <div class="iq-form-section">
            <div class="iq-form-section-title">
              Período e Afiliação
            
            </div>
            <div class="row">
              <div class="col-md-4 form-group">
                <label>Data início</label>
                <input type="date" name="datainicio" id="datainicio_ped" class="form-control" required
                       value="<?= $isInativo ? '' : htmlspecialchars($registoAtivo['datainicio'] ?? '') ?>">
              </div>
              <div class="col-md-4 form-group">
                <label>Data fim</label>
                <input type="date" name="datafim" id="datafim_ped" class="form-control" required
                       value="<?= $isInativo ? '' : htmlspecialchars($registoAtivo['datafim'] ?? '') ?>">
              </div>
              <div class="col-md-4 form-group">
                <label>Unidade I&D</label>
                <select name="unidade" class="form-control" required>
                  <option value="" selected disabled>— Selecionar —</option>
                  <?php foreach (array('CEFT','LEPABE','LSRE-LCM','REQUIMTE','Outro') as $u): ?>
                  <option><?= $u ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-7 form-group">
                <label>Posto de trabalho</label>
                <input type="text" name="workplace" class="form-control" required
                       value="<?= htmlspecialchars($registoAtivo['local_trabalho'] ?? '') ?>">
              </div>
              <div class="col-md-4 form-group">
                <label>Extensão FEUP</label>
                <input type="text" name="extensao" class="form-control" maxlength="10"
                       value="<?= htmlspecialchars($registoAtivo['extensao'] ?? '') ?>">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Observações <small class="text-muted">(opcional)</small></label>
            <textarea name="observacoes" class="form-control" rows="2"
                      placeholder="Motivo do pedido…"></textarea>
          </div>
        </div>

        <!-- Coluna direita -->
        <div class="iq-form-col">
          <div class="iq-form-section">
            <div class="iq-form-section-title">
              Classificação

            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label>Grupo Profissional</label>
                <select name="grupo" id="grupo_ped" class="form-control" required>
                  <option value="" selected disabled>— Selecionar —</option>
                  <?php foreach ($grupos as $g): ?>
                  <option value="<?= (int)$g['grupoid'] ?>">
                    <?= htmlspecialchars($g['grupo_pro']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 form-group" id="cat-col-wrap-ped">
                <label>Categoria</label>
                <select name="categoria" id="categoria_ped" class="form-control" required>
                  <option value="" selected disabled>— Selecionar —</option>
                  <?php foreach ($cats as $c): ?>
                  <option value="<?= (int)$c['categoriaid'] ?>">
                    <?= htmlspecialchars(trim($c['categoria'])) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-8 form-group">
                <label>Responsável</label>
                <select name="responsavel" id="responsavel_ped" class="form-control" required>
                  <option value="" selected disabled>— Selecionar —</option>
                  <?php foreach ($resps as $r): ?>
                  <option value="<?= (int)$r['Codigo'] ?>">
                    <?= htmlspecialchars($r['respespaco']) ?>
                  </option>
                  <?php endforeach; ?>
                  <option value="0">Outro…</option>
                </select>
              </div>
            </div>
            <div id="outroresp_ped" style="display:none">
              <div class="form-group">
                <label>Responsável (outro)</label>
                <input type="text" name="outroresponsavel" class="form-control" value="">
              </div>
            </div>
          </div>

          <div class="iq-form-section iq-form-section-fill">
            <div class="iq-form-section-title">
              Acessos
         
            </div>
            <div class="iq-form-inline mb-2" style="gap:1rem;align-items:center;">
              <label style="margin-bottom:0;font-weight:500;">Acesso DEQB (porta norte)</label>
              <div class="d-flex gap-3" style="gap:.75rem;display:flex;">
                <label class="d-flex align-items-center gap-1" style="gap:.35rem;margin-bottom:0;cursor:pointer;font-weight:400;">
                  <input type="radio" name="acessodeq" id="acessodeq_sim" value="1" required onchange="verificarLabs()"> Sim
                </label>
                <label class="d-flex align-items-center gap-1" style="gap:.35rem;margin-bottom:0;cursor:pointer;font-weight:400;">
                  <input type="radio" name="acessodeq" id="acessodeq_nao" value="0" required onchange="verificarLabs()"> Não
                </label>
              </div>
            </div>
            <div class="form-group iq-fill">
              <label>Laboratórios / gabinetes</label>
              <div class="iq-checkboxlist" id="acessos_ped" onchange="atualizarPreviewLabs('acessos_ped','labs-preview-ped')">
              <?php
              $selDeqids = $registoAtivo ? getRegistoAcessos($pdo, (int)$registoAtivo['autoid']) : array();
              $prevPiso  = null; $openGrp = false;
              foreach ($gabRows as $rg):
                if ($rg['piso'] !== $prevPiso):
                  if ($openGrp) echo '</div>';
                  echo '<div class="iq-checkgroup"><span class="iq-checkgroup-label">'
                     . htmlspecialchars($rg['piso']) . '</span>';
                  $prevPiso = $rg['piso']; $openGrp = true;
                endif;
              ?>
              <label>
                <input type="checkbox" name="acessos[]"
                       value="<?= htmlspecialchars($rg['deqid']) ?>"
                       onchange="verificarLabs()"
                       <?= in_array($rg['deqid'], $selDeqids) ? 'checked' : '' ?>>
                <?= htmlspecialchars($rg['nomegab']) ?>
              </label>
              <?php endforeach; if ($openGrp) echo '</div>'; ?>
              </div>
              <div class="labs-preview mt-2" id="labs-preview-ped"></div>
            </div>
          </div>
        </div><!-- /col direita -->
      </div><!-- /iq-form-2col -->

      <div class="iq-form-actions mt-2">
        <button type="button" class="btn btn-secondary me-auto"
                onclick="document.getElementById('formAlterar').style.display='none'">
          <?= t('CANCEL') ?>
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-paper-plane me-1"></i> <?= t('SUBMIT') ?>
        </button>
      </div>
    </form>
  </div>
</div>
</div><!-- /#formAlterar -->
<?php endif; // $podeAlterar ?>

<?php endif; // if(!$codigoNum) / else ?>

<script>
var grupoOriginal     = <?= $registoAtivo ? (int)$registoAtivo['grupo'] : 0 ?>;
var isAtivo           = <?= $isAtivo  ? 'true' : 'false' ?>;
var isInativo         = <?= $isInativo ? 'true' : 'false' ?>;
var acessosOriginais  = <?= json_encode($registoAtivo ? getRegistoAcessos($pdo, (int)$registoAtivo['autoid']) : array()) ?>;
var acessodeqOriginal = <?= $registoAtivo ? (int)$registoAtivo['acessodeq'] : 0 ?>;

// Mapeamento grupo → categorias (carregado da BD)
var grupoCategorias    = <?= json_encode($grupoCatMap, JSON_UNESCAPED_UNICODE) ?>;
var gruposSemCategoria = [2, 3, 4, 6, 7];
// Categoria actualmente guardada (preservar mesmo que não esteja na lista do novo grupo)
var catAtualPed = <?= (int)(($registoAtivo['categoria'] ?? 0)) ?>;
var allCatOpts = document.getElementById('categoria_ped')
    ? document.getElementById('categoria_ped').innerHTML : '';

// ── Preview de labs seleccionados ────────────────────────────────
function atualizarPreviewLabs(listId, previewId) {
    var list = document.getElementById(listId);
    var prev = document.getElementById(previewId);
    if (!list || !prev) return;
    var checks = list.querySelectorAll('input[type=checkbox]:checked');
    if (checks.length === 0) {
        prev.innerHTML = '<span style="font-size:12px;color:#999">Nenhum selecionado</span>';
    } else {
        var html = '';
        checks.forEach(function(c) {
            html += '<span class="lab-tag">' + (c.parentElement.textContent || c.value).trim() + '</span>';
        });
        prev.innerHTML = html;
    }
}

function abrirForm(novoReg) {
    var el = document.getElementById('formAlterar');
    if (!el) return;
    el.style.display = '';
    document.getElementById('_novo_registo').value = novoReg ? '1' : '0';

    // Novo registo: limpar grupo e categoria para forçar escolha explícita
    if (novoReg) {
        var selG = document.getElementById('grupo_ped');
        var selC = document.getElementById('categoria_ped');
        if (selG) selG.selectedIndex = -1;
        if (selC) selC.selectedIndex = -1;
    }

    var avisoG = document.getElementById('avisoGrupo');
    var avisoN = document.getElementById('avisoNovoReg');
    var title  = document.getElementById('formTitle');

    avisoG.style.display = 'none';
    avisoN.style.display = 'none';

    if (novoReg) {
        title.textContent = 'Pedir novo registo';
        avisoN.style.display = '';
        // Limpar datas
        document.getElementById('datainicio_ped').value = '';
        document.getElementById('datafim_ped').value    = '';
        // Limpar Classificação (grupo, categoria)
        var selG = document.getElementById('grupo_ped');
        var selC = document.getElementById('categoria_ped');
        if (selG) { selG.selectedIndex = 0; filtrarCategoriasPed(parseInt(selG.value), false); }
        if (selC) selC.selectedIndex = 0;
        // Limpar Acessos
        document.querySelectorAll('input[name="acessodeq"]').forEach(function(r) { r.checked = false; });
        document.querySelectorAll('#acessos_ped input[type=checkbox]').forEach(function(c) { c.checked = false; });
        atualizarPreviewLabs('acessos_ped', 'labs-preview-ped');
    } else if (isInativo) {
        title.textContent = 'Solicitar renovação';
    } else {
        title.textContent = 'Solicitar alteração';
    }
    verificarLabs();
    el.scrollIntoView({behavior:'smooth', block:'start'});
}

function verificarGrupo() {
    var selG = document.getElementById('grupo_ped');
    if (!selG) return;
    var novoReg = document.getElementById('_novo_registo').value === '1';
    var mudou   = !novoReg && parseInt(selG.value) !== grupoOriginal;
    document.getElementById('avisoGrupo').style.display = mudou ? '' : 'none';
    var lbl = document.getElementById('lblGrupoAviso');
    if (lbl) lbl.style.display = mudou ? '' : 'none';
}

function verificarLabs() {
    var novoReg    = document.getElementById('_novo_registo').value === '1';
    var selG       = document.getElementById('grupo_ped');
    var grupoMudou = selG && parseInt(selG.value) !== grupoOriginal;
    if (novoReg || grupoMudou) {
        document.getElementById('avisoLabs').style.display = 'none'; return;
    }
    var deqChecked = document.querySelector('input[name="acessodeq"]:checked');
    var deqVal     = deqChecked ? parseInt(deqChecked.value) : -1;
    var deqMudou   = deqVal !== acessodeqOriginal;
    var checks   = document.querySelectorAll('#acessos_ped input[type=checkbox]');
    var novos    = [];
    checks.forEach(function(c) { if (c.checked) novos.push(c.value); });
    novos.sort();
    var antigos  = acessosOriginais.slice().sort();
    var labMudou = deqMudou || JSON.stringify(novos) !== JSON.stringify(antigos);
    document.getElementById('avisoLabs').style.display = labMudou ? '' : 'none';
}

function filtrarCategoriasPed(grupoId, preservarAtual) {
    var sel  = document.getElementById('categoria_ped');
    var wrap = document.getElementById('cat-col-wrap-ped');
    if (!sel) return;

    if (gruposSemCategoria.indexOf(grupoId) !== -1) {
        if (!preservarAtual || !catAtualPed) {
            if (wrap) wrap.style.display = 'none';
        }
        return;
    }
    if (wrap) wrap.style.display = '';
    var permitidas = grupoCategorias[grupoId] || null;
    sel.innerHTML  = allCatOpts;
    if (!permitidas) return;
    Array.from(sel.options).forEach(function(o) {
        if (o.disabled) return;
        var id = parseInt(o.value);
        if (preservarAtual && id === catAtualPed) return;
        if (permitidas.indexOf(id) === -1) o.remove();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var selG = document.getElementById('grupo_ped');
    if (selG) {
        if (selG.value) filtrarCategoriasPed(parseInt(selG.value), true);

        selG.addEventListener('change', function () {
            filtrarCategoriasPed(parseInt(this.value), false);
            verificarGrupo();
        });
    }

    // ── Preview de labs (meu-registo) ─────────────────────────────
    atualizarPreviewLabs('acessos_ped', 'labs-preview-ped');
    var selR = document.getElementById('responsavel_ped');
    if (selR) {
        selR.addEventListener('change', function () {
            var isOutro = this.value === '0';
            var div = document.getElementById('outroresp_ped');
            var inp = div ? div.querySelector('input[name="outroresponsavel"]') : null;
            div.style.display = isOutro ? '' : 'none';
            if (inp) {
                if (isOutro) { inp.setAttribute('required', 'required'); }
                else         { inp.removeAttribute('required'); inp.value = ''; }
            }
        });
    }
    <?php if ($isInativo): ?>
    abrirForm(false);
    <?php endif; ?>

    // ── Validação data fim >= data início ─────────────────────────
    (function () {
        var ini = document.getElementById('datainicio_ped');
        var fim = document.getElementById('datafim_ped');
        if (!ini || !fim) return;
        function validarDatas() {
            if (ini.value && fim.value && fim.value < ini.value) {
                fim.setCustomValidity('A data de fim não pode ser anterior à data de início.');
            } else {
                fim.setCustomValidity('');
            }
            fim.min = ini.value || '';
        }
        ini.addEventListener('change', validarDatas);
        fim.addEventListener('change', validarDatas);
        validarDatas();
    })();
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
