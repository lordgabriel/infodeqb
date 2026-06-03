<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

date_default_timezone_set('Europe/Lisbon');
require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado

$id  = $_REQUEST['id']  ?? null;
$id1 = $_REQUEST['id1'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

// Retrieve Language
$sites    = ['en' => 'en', 'pt' => 'pt'];
$language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
if (!isset($sites[$language])) $language = 'en';
include "../lang/lang." . $sites[$language] . ".php";

// ── Carregar registo da BD ────────────────────────────────────────
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$q = $pdo->prepare(
    'SELECT * FROM infodeqb_rds_colaborador
     INNER JOIN infodeqb_rds_registo  ON infodeqb_rds_colaborador.codigo  = infodeqb_rds_registo.codigo
     INNER JOIN infodeqb_rds_grupo    ON infodeqb_rds_registo.grupo        = infodeqb_rds_grupo.grupoid
     LEFT  JOIN infodeqb_rds_responsaveis ON infodeqb_rds_registo.responsavel = infodeqb_rds_responsaveis.codigo
     WHERE infodeqb_rds_colaborador.codigo = ? AND infodeqb_rds_registo.autoid = ? AND infodeqb_rds_registo.deleted = 0'
);
$q->execute([$id, $id1]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (!$data) { header("Location: detail.php?id=$id"); exit; }

// Data do estado atual
$dataestado = null;
switch ($data['status']) {
    case 'Novo':     $dataestado = $data['dataregisto']; break;
    case 'Ativo':    $dataestado = $data['dataativo'];   break;
    case 'Inativo':  $dataestado = $data['datainativo']; break;
    case 'Pendente': $dataestado = $data['datacica'];    break;
}

// ── Dados de referência ──────────────────────────────────────────
$rowsResp  = $pdo->query('SELECT * FROM infodeqb_rds_responsaveis WHERE Codigo != 0 ORDER BY respespaco')->fetchAll(PDO::FETCH_ASSOC);
$rowsGrupo = $pdo->query('SELECT * FROM infodeqb_rds_grupo ORDER BY orderid')->fetchAll(PDO::FETCH_ASSOC);
$rowsCat   = $pdo->query('SELECT * FROM infodeqb_rds_categoria ORDER BY categoriaid')->fetchAll(PDO::FETCH_ASSOC);
$gabRows   = $pdo->query('SELECT * FROM infodeqb_rds_gabinetes ORDER BY edificio, piso, nomegab')->fetchAll(PDO::FETCH_ASSOC);

// ── Notificar SIGARRA (ação separada, sem recarregar o form) ──────
if (!empty($_POST['_acao']) && $_POST['_acao'] === 'notificar_sigarra') {
    $nAutoid         = (int)($_POST['autoid']        ?? 0);
    $nDatafimNovo    = trim($_POST['datafim_novo']   ?? '');
    $nDatafimAntigo  = trim($_POST['datafim_antigo'] ?? '');
    if ($nAutoid && $nDatafimNovo) {
        // Criar infodeqb_rds_pedido origem='secretariado' + enviar email ao SIGARRA
        $dadosJson = json_encode(array(
            'datafim_novo'   => $nDatafimNovo,
            'datafim_antigo' => $nDatafimAntigo,
        ), JSON_UNESCAPED_UNICODE);
        $dadosAnt  = json_encode(array('datafim' => $nDatafimAntigo), JSON_UNESCAPED_UNICODE);
        $pdo->prepare(
            "INSERT INTO infodeqb_rds_pedido
             (tipo,origem,codigo,registo_id,dados_json,dados_anteriores,status)
             VALUES ('alteracao_datafim','secretariado',?,?,?,?,'Aguarda_SIGARRA')"
        )->execute([$id, $nAutoid, $dadosJson, $dadosAnt]);

        // Email ao SIGARRA
        $regS = $pdo->prepare(
            'SELECT r.*, c.nome, c.email,
                    COALESCE(rsp.respespaco, r.outroresponsavel) AS resp_nome
             FROM infodeqb_rds_registo r
             JOIN infodeqb_rds_colaborador c ON c.codigo=r.codigo
             LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo=r.responsavel
             WHERE r.autoid=?'
        );
        $regS->execute([$nAutoid]);
        $regD = $regS->fetch(PDO::FETCH_ASSOC);
        if ($regD) {
            $acessos = ($regD['acessodeq'] == 1 ? 'Porta Norte; ' : '') . ($regD['acessos'] ?? '');
            $infoS = array(
                'codigo'      => $regD['codigo'],
                'nome'        => $regD['nome'],
                'fim'         => $nDatafimNovo,
                'acessos'     => $acessos ?: '—',
                'responsavel' => $regD['resp_nome'] ?? '—',
                'detalhe'     => 'Renovação / nova data de fim: ' . $nDatafimAntigo . ' → ' . $nDatafimNovo,
            );
            $bodyS = format_email($infoS, 'mail_alteracao_sigarra.html');
            try {
                send_email(
                    array('sigarra@fe.up.pt'),
                    $bodyS,
                    'Acessos DEQ: Renovação de acessos — ' . $regD['nome'],
                    array('deqdir@fe.up.pt','fmartins@fe.up.pt')
                );
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                error_log('HR edit.php SIGARRA email falhou: ' . $e->getMessage());
            } catch (Exception $e) {
                error_log('HR edit.php SIGARRA email falhou: ' . $e->getMessage());
            }
        }
    }
    header('Location: detail.php?id=' . urlencode($id) . '&msg=sigarra_ok');
    exit;
}

// ── Processar POST ───────────────────────────────────────────────
$WorkrespError    = null;
$erroduplicado    = null;
$status           = $data['status'];  // default: mantém estado atual
$sigarraNotifInfo = null;             // dados para mostrar botão "Notificar SIGARRA"

if (!empty($_POST)) {

    $codigo           = $_POST['codigo'];
    $nome             = $_POST['nome'];
    $email            = $_POST['email'];
    $emailalt         = $_POST['emailalt'];
    $telefone         = phoneFromPost();
    $unidade          = $_POST['unidade'];
    $posto            = $_POST['workplace'];
    $extensao         = $_POST['extension'];
    $datainicio       = date('Y-m-d', strtotime($_POST['datainicio']));
    $datafim          = date('Y-m-d', strtotime($_POST['datafim']));
    $responsavel      = $_POST['responsavel'];
    $outroresponsavel = $_POST['outroresponsavel'];
    $acessodeq_post   = isset($_POST['acessodeq']) ? $_POST['acessodeq'] : 0;
    $grupo            = $_POST['grupo'];
    $categoria        = $_POST['categoria'];
    $curso            = ($grupo == '2' || $grupo == '3') ? ($_POST['curso'] ?? '') : '';

    // Construir strings de acessos
    $result         = '';
    $acessosid      = null;
    $acessosid_unique = '';
    if (isset($_POST['acessos'])) {
        $sth = $pdo->prepare('SELECT nomegab, deqid FROM infodeqb_rds_gabinetes WHERE deqid = ?');
        foreach ($_POST['acessos'] as $acessoption) {
            $sth->execute([$acessoption]);
            $sth1 = $sth->fetch(PDO::FETCH_ASSOC);
            if ($sth1) {
                $result   .= $sth1['nomegab'] . ';';
                $acessosid .= $sth1['deqid'] . ';';
            }
        }
        $acessosid_array  = array_unique(array_filter(explode(';', $acessosid)));
        $acessosid_unique = implode('; ', $acessosid_array);
    }

    // Validar responsável
    $validar = true;
    if ($responsavel != 0) {
        $outroresponsavel = 0;
    } elseif (empty($outroresponsavel)) {
        $WorkrespError = $lang['ERR_WORK_RESP'];
        $validar = false;
    }

    if ($validar) {
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->beginTransaction();

            // Guardar datafim anterior para detetar renovação em registo Ativo
            $datafimAntigo = $data['datafim'];

            $pdo->prepare(
                'UPDATE infodeqb_rds_colaborador SET nome=:nome, email=:email, emailalt=:emailalt, telefone=:telefone WHERE codigo=:codigo'
            )->execute([':nome'=>$nome,':email'=>$email,':emailalt'=>$emailalt,':telefone'=>$telefone,':codigo'=>$codigo]);

            $pdo->prepare(
                'UPDATE infodeqb_rds_registo SET codigo=:codigo, datainicio=:datainicio, datafim=:datafim,
                 responsavel=:responsavel, outroresponsavel=:outroresponsavel, status=:status,
                 acessodeq=:acessodeq, grupo=:grupo, categoria=:categoria,
                 acessos=:acessos, acessosid=:acessosid, curso=:curso,
                 local_trabalho=:posto, extensao=:extensao, unidade=:unidade
                 WHERE autoid=:id1'
            )->execute([
                ':codigo'           => $codigo,
                ':datainicio'       => $datainicio,
                ':datafim'          => $datafim,
                ':responsavel'      => $responsavel,
                ':outroresponsavel' => $outroresponsavel,
                ':status'           => $status,
                ':acessodeq'        => $acessodeq_post,
                ':grupo'            => $grupo,
                ':categoria'        => $categoria,
                ':acessos'          => $result,
                ':acessosid'        => $acessosid_unique,
                ':curso'            => $curso,
                ':id1'              => $id1,
                ':posto'            => $posto,
                ':extensao'         => $extensao,
                ':unidade'          => $unidade,
            ]);

            $pdo->commit();

            // Actualizar tabela relacional de acessos
            if (isset($_POST['acessos'])) {
                $labsPost = array_values(array_unique(array_filter(
                    array_map('trim', (array)$_POST['acessos'])
                )));
                $gabMapAdmin = getGabMap($pdo);
                setRegistoAcessos($pdo, (int)$id1, $labsPost, $gabMapAdmin);
            }

            // Detetar renovação em registo Ativo → preparar botão "Notificar SIGARRA"
            if ($data['status'] === 'Ativo' && $datafim !== $datafimAntigo) {
                $sigarraNotifInfo = array(
                    'autoid'          => (int)$id1,
                    'datafim_novo'    => $datafim,
                    'datafim_antigo'  => $datafimAntigo,
                    'nome'            => $nome,
                    'codigo'          => $codigo,
                );
            }

            echo "<div class='alert alert-success' role='alert'>Registo atualizado com sucesso.</div>";
            if (!$sigarraNotifInfo) {
                echo "<meta http-equiv='refresh' content='2;URL=detail.php?id=" . urlencode($id) . "'>";
            }

        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                $erroduplicado = '<div class="alert alert-danger">Código já existente.</div>';
            } else {
                echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}

// ── Valores para preencher o form (POST > BD) ─────────────────────
$fNome      = !empty($_POST['nome'])            ? $_POST['nome']            : $data['nome'];
$fEmail     = !empty($_POST['email'])           ? $_POST['email']           : $data['email'];
$fEmailalt  = !empty($_POST['emailalt'])        ? $_POST['emailalt']        : ($data['emailalt'] ?? '');
$fTelefone  = !empty($_POST['telefone_numero'])  ? phoneFromPost()           : ($data['telefone'] ?? '');
$fDatainic  = !empty($_POST['datainicio'])      ? $_POST['datainicio']      : $data['datainicio'];
$fDatafim   = !empty($_POST['datafim'])         ? $_POST['datafim']         : $data['datafim'];
$fUnidade   = !empty($_POST['unidade'])         ? $_POST['unidade']         : ($data['unidade'] ?: 'Outro');
$fWorkplace = !empty($_POST['workplace'])       ? $_POST['workplace']       : ($data['local_trabalho'] ?? '');
$fExtensao  = !empty($_POST['extension'])       ? $_POST['extension']       : ($data['extensao'] ?? '');
$fGrupo     = !empty($_POST['grupo'])           ? (int)$_POST['grupo']      : (int)$data['grupoid'];
$fCategoria = !empty($_POST['categoria'])       ? (int)$_POST['categoria']  : (int)$data['categoria'];
$fCurso     = isset($_POST['curso'])            ? $_POST['curso']           : ($data['curso'] ?? '');
$fResp      = isset($_POST['responsavel'])      ? $_POST['responsavel']     : $data['Codigo'];
$fOutroresp = !empty($_POST['outroresponsavel'])? $_POST['outroresponsavel']: ($data['outroresponsavel'] ?? '');
$fAcessodeq = isset($_POST['acessodeq'])        ? $_POST['acessodeq']       : $data['acessodeq'];

// Checkboxlist de labs
$fAcessos = !empty($_POST) && isset($_POST['acessos'])
    ? (array)$_POST['acessos']
    : getRegistoAcessos($pdo, (int)$id1);
$gab = '';
$gabCurPiso = '';
foreach ($gabRows as $rowgab) {
    if ($rowgab['piso'] !== $gabCurPiso) {
        if ($gabCurPiso !== '') $gab .= '</div>';
        $gab .= '<div class="iq-checkgroup"><span class="iq-checkgroup-label">'
              . htmlspecialchars($rowgab['piso']) . '</span>';
        $gabCurPiso = $rowgab['piso'];
    }
    $checked = in_array($rowgab['deqid'], $fAcessos) ? ' checked' : '';
    $gab .= '<label><input type="checkbox" name="acessos[]" value="'
          . htmlspecialchars($rowgab['deqid']) . '"' . $checked . '> '
          . htmlspecialchars($rowgab['nomegab']) . '</label>';
}
if ($gabCurPiso !== '') $gab .= '</div>';

$pageTitle = 'Editar Colaborador';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <h1><i class="fas fa-user-edit fa-sm me-2 text-muted"></i>Editar Colaborador</h1>
</div>

<?php if ($erroduplicado): echo $erroduplicado; endif; ?>

<form action="edit.php?id=<?= htmlspecialchars($id) ?>&id1=<?= htmlspecialchars($id1) ?>"
      method="post" class="iq-form-2col-wrap">
  <input type="hidden" name="id1"   value="<?= htmlspecialchars($id1) ?>">
  <input type="hidden" name="id"    value="<?= htmlspecialchars($id) ?>">

  <div class="iq-form-2col">

  <!-- ══ Coluna esquerda: Dados Pessoais + Período e Afiliação ══ -->
  <div class="iq-form-col">

  <!-- ── Dados Pessoais ── -->
  <div class="iq-form-section">
    <div class="iq-form-section-title">Dados Pessoais</div>
    <div class="row">
      <div class="col-md-4 form-group">
        <label><?= $lang['FEUP_CODE'] ?></label>
        <input name="codigo" type="text" required maxlength="9"
               pattern="^(\d{6}|\d{9})$" class="form-control" id="code"
               value="<?= htmlspecialchars($data['codigo']) ?>">
      </div>
    </div>
    <div class="form-group">
      <label><?= $lang['NAME'] ?></label>
      <input type="text" name="nome" class="form-control" required
             value="<?= htmlspecialchars($fNome) ?>">
    </div>
    <div class="row">
      <div class="col-md-6 form-group">
        <label><?= $lang['EMAIL'] ?></label>
        <input type="email" name="email" class="form-control" required id="email"
               pattern="^[^@]+@((fe\.up\.pt)|(edu\.fe\.up\.pt)|(up\.pt))$"
               value="<?= htmlspecialchars($fEmail) ?>">
      </div>
      <div class="col-md-6 form-group">
        <label><?= $lang['ALT_EMAIL'] ?></label>
        <input type="email" name="emailalt" class="form-control"
               value="<?= htmlspecialchars($fEmailalt) ?>">
      </div>
    </div>
    <div class="row">
      <div class="col-md-5 form-group">
        <label><?= $lang['PHONE'] ?></label>
        <?php renderPhoneInput($fTelefone); ?>
      </div>
    </div>
  </div>

  <!-- ── Período e Afiliação ── -->
  <div class="iq-form-section">
    <div class="iq-form-section-title">Período e Afiliação</div>
    <div class="row">
      <div class="col-md-3 form-group">
        <label><?= $lang['BEGIN_DATE'] ?></label>
        <input type="date" name="datainicio" class="form-control" required id="datainicio"
               value="<?= htmlspecialchars($fDatainic) ?>">
      </div>
      <div class="col-md-3 form-group">
        <label><?= $lang['END_DATE'] ?></label>
        <input type="date" name="datafim" class="form-control" required id="datafim"
               value="<?= htmlspecialchars($fDatafim) ?>">
      </div>
      <div class="col-md-4 form-group">
        <label><?= $lang['UNIT'] ?></label>
        <select name="unidade" class="form-control" id="unidade" required>
          <option disabled value="">Escolha uma opção</option>
          <?php foreach (['CEFT','LEPABE','LSRE-LCM','REQUIMTE','Outro'] as $u): ?>
          <option <?= ($fUnidade === $u) ? 'selected' : '' ?>><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="col-md-7 form-group">
        <label><?= $lang['WORKPLACE'] ?></label>
        <input type="text" name="workplace" class="form-control" id="workplace"
               value="<?= htmlspecialchars($fWorkplace) ?>">
      </div>
      <div class="col-md-4 form-group">
        <label><?= $lang['EXTENSION'] ?></label>
        <input type="text" name="extension" class="form-control" id="extension"
               value="<?= htmlspecialchars($fExtensao) ?>">
      </div>
    </div>
  </div>

  </div><!-- /.iq-form-col esquerda -->

  <!-- ══ Coluna direita: Classificação + Acessos ══ -->
  <div class="iq-form-col">

  <!-- ── Classificação ── -->
  <div class="iq-form-section">
    <div class="iq-form-section-title">Classificação</div>
    <div class="row">
      <div class="col-md-5 form-group">
        <label><?= $lang['PROGROUP'] ?></label>
        <select id="grupo" name="grupo" class="form-control" required>
          <?php foreach ($rowsGrupo as $rg): ?>
          <option value="<?= (int)$rg['grupoid'] ?>" <?= ($fGrupo === (int)$rg['grupoid']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($rg['grupo_pro']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5 form-group">
        <label><?= $lang['CATEGORY'] ?></label>
        <select id="category" name="categoria" class="form-control" required>
          <?php foreach ($rowsCat as $rc): ?>
          <option value="<?= (int)$rc['categoriaid'] ?>" <?= ($fCategoria === (int)$rc['categoriaid']) ? 'selected' : '' ?>>
            <?= htmlspecialchars(trim($rc['categoria'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div id="curso" <?= in_array($fGrupo, [2,3,7]) ? '' : 'style="display:none"' ?>>
      <div class="row">
        <div class="col-md-6 form-group">
          <label><?= $lang['COURSE'] ?></label>
          <input class="form-control" type="text" name="curso"
                 value="<?= htmlspecialchars($fCurso) ?>">
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 form-group">
        <label><?= $lang['WORK_RESP'] ?></label>
        <select id="responsavel" name="responsavel" class="form-control" required>
          <option disabled value=""><?= $lang['OPTION'] ?></option>
          <?php foreach ($rowsResp as $rr): ?>
          <option value="<?= (int)$rr['Codigo'] ?>" <?= ((string)$fResp === (string)$rr['Codigo']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($rr['respespaco']) ?>
          </option>
          <?php endforeach; ?>
          <option value="0" <?= ($fResp == '0') ? 'selected' : '' ?>><?= $lang['OTHER'] ?></option>
        </select>
      </div>
    </div>

    <div id="outroresp" <?= ($fResp != '0') ? 'style="display:none"' : '' ?>>
      <div class="row">
        <div class="col-md-6 form-group">
          <label><?= $lang['WORK_RESP2'] ?></label>
          <input class="form-control" type="text" name="outroresponsavel"
                 value="<?= htmlspecialchars($fOutroresp) ?>">
          <?php if ($WorkrespError): ?>
            <div class="text-danger small mt-1"><?= $WorkrespError ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Estado atual (informativo) -->
    <div class="row align-items-center mt-1">
      <div class="col-md-auto form-group mb-0">
        <label class="d-block"><?= $lang['STATUS'] ?></label>
        <?php
          $sBadge = ['Ativo'=>'badge-success','Pendente'=>'badge-warning','Novo'=>'badge-info','Inativo'=>'badge-secondary'][$data['status']] ?? 'badge-secondary';
        ?>
        <span class="badge <?= $sBadge ?>" style="font-size:.85rem;padding:.35em .65em"><?= htmlspecialchars($data['status']) ?></span>
        <?php if ($dataestado): ?>
          <small class="text-muted ms-2">desde <?= htmlspecialchars($dataestado) ?></small>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Acessos ── -->
  <div class="iq-form-section iq-form-section-fill">
    <div class="iq-form-section-title">Acessos</div>
    <div class="iq-form-inline mb-2">
      <label><?= $lang['DEQ_ACCESS'] ?></label>
      <select id="acessodeq" name="acessodeq" class="form-control" required>
        <option value="1" <?= ($fAcessodeq == '1') ? 'selected' : '' ?>><?= $lang['OPT_YES'] ?></option>
        <option value="0" <?= ($fAcessodeq == '0') ? 'selected' : '' ?>><?= $lang['OPT_NO'] ?></option>
      </select>
    </div>
    <div class="form-group iq-fill">
      <label><?= $lang['LAB_ACCESS'] ?></label>
      <div class="iq-checkboxlist" id="acessos-list">
        <?= $gab ?>
      </div>
    </div>
  </div>

  </div><!-- /.iq-form-col direita -->

  </div><!-- /.iq-form-2col -->

  <!-- ── Ações ── -->
  <div class="iq-form-actions">
    <a href="javascript:history.back()" class="btn btn-secondary me-auto">
      <i class="fas fa-arrow-left me-1"></i> Voltar
    </a>
    <button type="submit" name="submit" value="" class="btn btn-primary">
      <?= $lang['SAVE'] ?>
    </button>
  </div>

</form>

<?php if ($sigarraNotifInfo): ?>
<div class="alert alert-warning mt-3" role="alert">
  <strong><i class="fas fa-exclamation-triangle me-1"></i>Data de fim alterada.</strong>
  O registo está <strong>Ativo</strong> — o SIGARRA deve ser informado da nova data
  (<strong><?= htmlspecialchars($sigarraNotifInfo['datafim_antigo']) ?></strong>
  → <strong><?= htmlspecialchars($sigarraNotifInfo['datafim_novo']) ?></strong>).
</div>
<form method="post"
      action="edit.php?id=<?= htmlspecialchars($id) ?>&id1=<?= htmlspecialchars($id1) ?>"
      onsubmit="return confirm('Enviar email ao SIGARRA com a nova data de fim?')">
  <input type="hidden" name="_acao"          value="notificar_sigarra">
  <input type="hidden" name="autoid"         value="<?= (int)$sigarraNotifInfo['autoid'] ?>">
  <input type="hidden" name="datafim_novo"   value="<?= htmlspecialchars($sigarraNotifInfo['datafim_novo']) ?>">
  <input type="hidden" name="datafim_antigo" value="<?= htmlspecialchars($sigarraNotifInfo['datafim_antigo']) ?>">
  <button class="btn btn-warning">
    <i class="fas fa-paper-plane me-1"></i> Notificar SIGARRA
  </button>
  <a href="detail.php?id=<?= htmlspecialchars($id) ?>" class="btn btn-outline-secondary ms-2">
    Ignorar por agora
  </a>
</form>
<?php endif; ?>

<script>
// ── Mapeamento grupo → categorias ────────────────────────────────
var grupoCategorias = {
    1:[1,2,3,5,6,7], 2:[4,5], 3:[9,80], 4:[3,6,9,10],
    5:[1,2,3,5,6,7,10], 6:[6,9,10,80], 7:[4,9],
    8:[11,12,13,14,15,16,17], 9:[1,2,3,5,6]
};
var allCatOptions  = document.getElementById('category')
    ? document.getElementById('category').innerHTML : '';
var allGrupoOptions = document.getElementById('grupo')
    ? document.getElementById('grupo').innerHTML : '';

function filtrarCategorias(grupoId) {
    var sel = document.getElementById('category');
    if (!sel) return;
    var permitidas = grupoCategorias[grupoId] || null;
    sel.innerHTML = allCatOptions;
    if (!permitidas) return;
    Array.from(sel.options).forEach(function(opt) {
        if (opt.disabled) return;
        if (permitidas.indexOf(parseInt(opt.value)) === -1) opt.remove();
    });
}

document.addEventListener('DOMContentLoaded', function () {

    var selGrupo = document.getElementById('grupo');
    if (selGrupo) {
        selGrupo.addEventListener('change', function () {
            filtrarCategorias(parseInt(this.value));
            var mostrarCurso = [2,3,7].indexOf(parseInt(this.value)) !== -1;
            document.getElementById('curso').style.display = mostrarCurso ? '' : 'none';
            var selDeq = document.getElementById('acessodeq');
            if (parseInt(this.value) === 3) {
                selDeq.value = '0'; selDeq.disabled = true;
            } else {
                selDeq.disabled = false;
            }
        });
        if (selGrupo.value) filtrarCategorias(parseInt(selGrupo.value));
    }

    var selResp = document.querySelector('select[name=responsavel]');
    if (selResp) {
        selResp.addEventListener('change', function () {
            document.getElementById('outroresp').style.display =
                this.value === '0' ? '' : 'none';
        });
    }

    var inpCodigo = document.getElementById('code');
    if (inpCodigo) {
        inpCodigo.addEventListener('change', function () {
            var selG = document.getElementById('grupo');
            if (this.value.length === 9) {
                [1,4,5,6].forEach(function(v) {
                    var opt = selG.querySelector('option[value="' + v + '"]');
                    if (opt) opt.remove();
                });
            } else {
                selG.innerHTML = allGrupoOptions;
                if (selG.value) filtrarCategorias(parseInt(selG.value));
            }
        });
    }

    var inpInicio = document.getElementById('datainicio');
    var inpFim    = document.getElementById('datafim');
    if (inpInicio && inpFim) {
        inpInicio.addEventListener('change', function () { inpFim.min    = this.value; });
        inpFim.addEventListener('change',    function () { inpInicio.max = this.value; });
    }
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
