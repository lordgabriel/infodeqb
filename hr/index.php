<?php

/* DB Validation */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

// Retrieve Language
$sites = array(
    'en' => 'en',
    'pt' => 'pt'
);
$language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
// Set default language if a '$lang' version of site is not available
if (! isset($sites[$language])) {
    $language = 'en';
}

include ROOT_DIR . "/infodeqb/lang/lang." . $sites[$language] . ".php";

// Initialize variables
$option_resp = '';
$option_resp_select = '';
$option_grupo = '<option selected disabled value="">' . $lang['OPTION'] . '</option>';
$option_cat = '<option selected disabled value="">' . $lang['OPTION'] . '</option>';
$option_gab = '';
$piso = '';
$validar = true;
$resp = null;
$erroduplicado = null;
$grupo = null;
$categoria = null;
$respespaco = null;
$validar = true;
$status = 'Novo';

// SQL statements
$sqlresp = 'SELECT * FROM infodeqb_rds_responsaveis where not Codigo=0 order by respespaco';
$sqlgrupo = 'SELECT * FROM infodeqb_rds_grupo where grupoid BETWEEN 1 AND 7 order by orderid';
$sqlcategoria = 'SELECT * FROM infodeqb_rds_categoria where categoriaid not in (11,12,13,14,15,16,17)  order by categoriaid';
$sqlgab = 'SELECT * FROM infodeqb_rds_gabinetes WHERE not (visible = 0) order by edificio, piso, nomegab';
$sqlinuser = "INSERT INTO infodeqb_rds_colaborador (codigo,nome,email,emailalt,telefone,createdate)
              VALUES (?,?,?,?,?,?)
              ON DUPLICATE KEY UPDATE nome=VALUES(nome), email=VALUES(email),
                emailalt=VALUES(emailalt), telefone=VALUES(telefone), deleted=0";
$sqlinregister = "INSERT INTO infodeqb_rds_registo (codigo,datainicio,datafim,responsavel,outroresponsavel,acessodeq,grupo,categoria,acessos,acessosid,curso, createdate,dataregisto, status, unidade, local_trabalho, extensao) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

// DB Connection
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Insert new record — token de submissão única (evita double-submit) */
if (!empty($_POST)) {
    $submitToken = trim($_POST['_submit_token'] ?? '');
    $sessionToken = $_SESSION['_hr_submit_token'] ?? '';

    // Se o token foi já usado ou não corresponde → redirect para success
    // (cobre browser Back + resend e submissões duplicadas)
    if ($submitToken === '' || $submitToken !== $sessionToken) {
        header('Location: success.php');
        exit;
    }
    // Invalidar imediatamente para impedir re-uso
    unset($_SESSION['_hr_submit_token']);
}

if (! empty($_POST)) {
    $codigo = $_POST['codigo'];
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $emailalt = $_POST['emailalt'];
    $telefoneNum = preg_replace('/[^0-9]/', '', trim($_POST['telefone_numero'] ?? ''));
    $telefone    = $telefoneNum ? trim($_POST['telefone_indicativo'] ?? '+351') . ' ' . $telefoneNum : '';
    $datainicio = date("Y-m-d", strtotime($_POST['datainicio']));
    $datafim = date("Y-m-d", strtotime($_POST['datafim']));
    // $respespaco = $_POST['respespaco'];
    $responsavel = $_POST['responsavel'];
    $outroresponsavel = $_POST['outroresponsavel'];
    $acessodeq = (isset($_POST['acessodeq']) ? $_POST['acessodeq'] : 0);
    $grupo = $_POST['grupo'];
    $categoria = $_POST['categoria'];
    $posto = $_POST['workplace'];
    $unidade = $_POST['unidade'];
    $curso = $_POST['curso'];

    if ($_POST['grupo'] == 1) {
        $acessosid = "E-172; E107; E111;";
        $result = "Armário de Segurança; E107; E111;";
    } else {
        $acessosid = "E-172;";
        $result = "Armário de Segurança;";
    }

    // echo $acessosid;
    $optacessos = "";
    $acessosgabid = ""; // inicializar antes do foreach
    $createdate = time();
    $extension = $_POST['extension'];

    // echo $categoria;
    if (startsWith($email, 'up') and endsWith($email, '@fe.up.pt')) {
        $email = str_replace('@fe.up.pt', '@edu.fe.up.pt', $email);
    }
    if (isset($_POST['acessos'])) {
        $postacessos = $_POST['acessos'];
        $sth = $pdo->prepare('SELECT nomegab, gabid, deqid FROM infodeqb_rds_gabinetes WHERE deqid = :acessosdeq ');

        // Inicializamos as strings de IDs antes do loop para evitar duplicações estranhas
        foreach ($postacessos as $acessoption) {
            $sth->execute([
                ':acessosdeq' => $acessoption
            ]);
            $sth1 = $sth->fetch(PDO::FETCH_ASSOC);

            $result .= " " . $sth1['nomegab'] . ";";
            $acessosid .= " " . $sth1['deqid'] . ";";
            $acessosgabid .= $sth1['gabid'] . ";";
        }

        // 1. Transformar em array e limpar espaços/vazios
        $acessosid_array = array_filter(array_unique(explode(";", $acessosid)));

        // 2. Verificar se algum elemento começa com "INESC"
        $hasInesc = false;
        foreach ($acessosid_array as $id) {
            if (strpos(trim($id), 'INESC') === 0) {
                $hasInesc = true;
                break;
            }
        }

        // 3. Se encontrou, adiciona a entrada específica
        if ($hasInesc) {
            $acessosid_array[] = "INESC ENTRADA";
            // Opcional: Adicionar também ao $result para aparecer no email
            $result .= " Entrada INESC;";
        }

        // 4. Reconstruir as strings finais únicas
        $acessosid_unique = implode("; ", array_unique($acessosid_array));

        // Repetir a lógica para o gabid se necessário
        $acessosgabid_array = array_filter(array_unique(explode(";", $acessosgabid)));
        if ($hasInesc) {
            $acessosgabid_array[] = "INESC_Entrada";
        }
        $acessosgabid_unique = implode("; ", array_unique($acessosgabid_array));

        // Atualizar as sessões
        $_SESSION['deqid'] = $acessosid_unique;
        $_SESSION['gabid'] = $acessosgabid_unique;
        $_SESSION['result'] = $result;
        $_SESSION['registo'] = $_POST;
        $_SESSION['acessodeq'] = $acessodeq;
    }
    // echo responsável espaço;
    if ($respespaco != 0) {
        $resptrabalho = '';
    } elseif (empty($resptrabalho)) {
        $WorkrespError = $lang['ERR_WORK_RESP'];
        $validar = false;
    }

    // ── 1. Gravar registo na BD (transaction) ──────────────────────
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM infodeqb_rds_grupo WHERE grupoid = ?');
        $stmt->execute([$categoria]);
        $cat = $stmt->fetchcolumn(1);

        $stmt1 = $pdo->prepare('SELECT * FROM infodeqb_rds_responsaveis WHERE codigo = ?');
        $stmt1->execute([$responsavel]);
        $resp = ($responsavel != 0) ? $stmt1->fetchcolumn(1) : $outroresponsavel;

        $acessodeq == 1 ? $x = "Sim" : $x = "Não";
        ($categoria == 4 || $categoria == 5) ? $mailcurso = $curso : $mailcurso = "N.A.";
        $timestamp = date('Y-m-d H:i:s');

        $pdo->prepare($sqlinuser)->execute(array(
            $codigo, $nome, $email, $emailalt, $telefone, $createdate
        ));
        $insReg = $pdo->prepare($sqlinregister);
        $insReg->execute(array(
            $codigo, $datainicio, $datafim, $responsavel, $outroresponsavel,
            $acessodeq, $grupo, $categoria,
            $_SESSION['result'], $_SESSION['deqid'],
            $curso, $createdate, $timestamp, $status,
            $unidade, $posto, $extension
        ));
        $novoRegId = (int)$pdo->lastInsertId();

        $pdo->commit(); // ← commit antes do email: registo fica guardado mesmo que o email falhe

        // Gravar acessos na tabela relacional (try-catch próprio para não bloquear o email)
        if ($novoRegId > 0 && !empty($_SESSION['deqid'])) {
            try {
                $labsParaGravar = array_values(array_unique(array_filter(
                    array_map('trim', explode(';', $_SESSION['deqid']))
                )));
                setRegistoAcessos($pdo, $novoRegId, $labsParaGravar);
            } catch (Exception $eLabs) {
                error_log('HR setRegistoAcessos falhou id=' . $novoRegId . ': ' . $eLabs->getMessage());
            }
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
            $codigoError = $lang['ERR_DUPLICATE_CODE'];
            $erroduplicado = ' <blockquote class="message text-danger"> Código já existente </blockquote>';
        } else {
            echo "<br>Erro ao guardar registo: " . htmlspecialchars($e->getMessage());
        }
        // não continuar para o envio de email
        goto skip_email;
    }

    // ── 2. Enviar email de confirmação (fora da transaction) ───────
    {
        $info = array(
            'codigo'    => $codigo,
            'nome'      => $nome,
            'mail'      => $email,
            'altmail'   => $emailalt,
            'telefone'  => $telefone,
            'grupo'     => $cat,
            'inicio'    => $datainicio,
            'fim'       => $datafim,
            'responsavel' => $resp,
            'acessodeq' => $x,
            'acessos'   => $result,
            'altemail'  => $emailalt
        );
        $body    = format_email($info, 'mail_register.html');
        $subject = $lang['SUBJECT'];
        $to      = array($info['mail']);

        $deq_bcc_ids = array(
            'E301MFP','E301AMTS','E302JMO','E302AMTS','E302JDF',
            'E302MFP','E303MFP','E303JMO','E306JIM','E306MFP',
            'E304BMFP','E402','E403N','E403S','E405JDF','E405MEM','F103A'
        );

        $raw_deqid     = $_SESSION['deqid'] ?? [];
        $selected_deqids = [];
        foreach ((array)$raw_deqid as $v) {
            foreach (explode(';', $v) as $p) {
                $p = strtoupper(trim($p));
                if ($p !== '') $selected_deqids[] = $p;
            }
        }

        $cc_list  = array('deqdir@fe.up.pt', 'fmartins@fe.up.pt');
        $bcc_list = array();
        if (!empty(array_intersect($selected_deqids, $deq_bcc_ids))) {
            $bcc_list[] = 'lucilia@fe.up.pt';
            $bcc_list[] = 'rsribeiro@fe.up.pt';
            $bcc_list[] = 'mjsampaio@fe.up.pt';
        }

        $file1 = ROOT_DIR . '/infodeqb/hr/inc/Safety_PT.pdf';
        $file2 = ROOT_DIR . '/infodeqb/hr/inc/Safety_EN.pdf';

        try {
            send_email($to, $body, $subject, $cc_list, $bcc_list, $file1, $file2);
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // Email falhou mas o registo já foi guardado — avisa mas não bloqueia
            error_log('HR email failed for ' . $codigo . ': ' . $e->getMessage());
        } catch (Exception $e) {
            error_log('HR email failed for ' . $codigo . ': ' . $e->getMessage());
        }
    }

    header("Location: success.php");
    exit();

    skip_email:
} else {}

// Preenche select box dos responsaveis do trabalho
if (isset($_POST['respespaco']) and $_POST['respespaco'] == 0) {
    $option_resp_select = 'selected';
}
foreach ($pdo->query($sqlresp, PDO::FETCH_ASSOC) as $row) {
    if (! empty($_POST)) {
        if ($_POST['responsavel'] == $row["Codigo"]) {
            $option_resp .= '<option value="' . $row["Codigo"] . '" selected >' . $row["respespaco"] . '</option>';
        } else {
            $option_resp .= '<option value="' . $row["Codigo"] . '"  >' . $row["respespaco"] . '</option>';
        }
    } else {
        $option_resp .= '<option value="' . $row['Codigo'] . '">' . $row["respespaco"] . '</option>';
    }
}

// Preenche Select box grupo professional
foreach ($pdo->query($sqlgrupo, PDO::FETCH_ASSOC) as $rowgrupo) {
    if (! empty($_POST)) {
        if ($_POST['grupo'] == $rowgrupo["grupoid"]) {
            $option_grupo .= '<option value="' . $rowgrupo["grupoid"] . '" selected >' . $rowgrupo["grupo_pro"] . '</option>';
        } else {
            $option_grupo .= '<option value="' . $rowgrupo["grupoid"] . '"  >' . $rowgrupo["grupo_pro"] . '</option>';
        }
    } else {
        $option_grupo .= '<option value="' . $rowgrupo['grupoid'] . '">' . $rowgrupo["grupo_pro"] . '</option>';
    }
}

// Preenche Select box categoria
foreach ($pdo->query($sqlcategoria, PDO::FETCH_ASSOC) as $rowcat) {
    if (! empty($_POST)) {
        if ($_POST['categoria'] == $rowcat["categoriaid"]) {
            $option_cat .= '<option value="' . $rowcat["categoriaid"] . '" selected >' . $rowcat["categoria"] . '</option>';
        } else {
            $option_cat .= '<option value="' . $rowcat["categoriaid"] . '"  >' . $rowcat["categoria"] . '</option>';
        }
    } else {
        $option_cat .= '<option value="' . $rowcat['categoriaid'] . '">' . $rowcat["categoria"] . '</option>';
    }
}

// Constrói checkboxlist de laboratórios/gabinetes
$gabRows = $pdo->query($sqlgab)->fetchAll(PDO::FETCH_ASSOC);
$gabChecks  = '';
$gabCurPiso = '';
$selectedAcessos = isset($_POST['acessos']) ? (array)$_POST['acessos'] : [];
foreach ($gabRows as $rowgab) {
    if ($rowgab['piso'] !== $gabCurPiso) {
        if ($gabCurPiso !== '') $gabChecks .= '</div>';
        $gabChecks .= '<div class="iq-checkgroup">'
            . '<span class="iq-checkgroup-label">' . htmlspecialchars($rowgab['piso']) . '</span>';
        $gabCurPiso = $rowgab['piso'];
    }
    $checked = in_array($rowgab['deqid'], $selectedAcessos) ? ' checked' : '';
    $gabChecks .= '<label><input type="checkbox" name="acessos[]" value="'
        . htmlspecialchars($rowgab['deqid']) . '"' . $checked . '> '
        . htmlspecialchars($rowgab['nomegab']) . '</label>';
}
if ($gabCurPiso !== '') $gabChecks .= '</div>';

// Código numérico do utilizador (para auto-preencher o campo 'codigo')
$_sessionCodeNum = preg_replace('/[^0-9]/', '', $_SESSION['Code'] ?? '');

$pageTitle = 'Registo de Colaborador';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

<!-- Hidden dom targets for JS -->
<div id="dom-target1" style="display:none"><?php echo htmlspecialchars($option_grupo); ?></div>
<div id="dom-target2" style="display:none"><?php echo htmlspecialchars($option_cat); ?></div>

<div class="iq-page-header">
  <h1>Registo de colaborador</h1>
</div>

<?php
// Gerar token de submissão único para esta sessão de formulário
$_SESSION['_hr_submit_token'] = bin2hex(random_bytes(16));
?>
<form class="iq-form-2col-wrap" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
  <input type="hidden" name="_submit_token" value="<?= htmlspecialchars($_SESSION['_hr_submit_token']) ?>">

  <?php if (isset($erroduplicado)): ?>
    <div class="alert alert-danger"><?php echo $erroduplicado; ?></div>
  <?php endif; ?>

  <div class="iq-form-2col">

  <!-- ══ Coluna esquerda: Dados Pessoais + Período e Afiliação ══ -->
  <div class="iq-form-col">

  <!-- ── Dados Pessoais ── -->
  <div class="iq-form-section">
    <div class="iq-form-section-title">Dados Pessoais</div>
    <div class="row">
      <div class="col-md-4 form-group">
        <label><?php echo $lang['FEUP_CODE']; ?></label>
        <input name="codigo" type="text" required maxlength="9" pattern="^(\d{6}|\d{9})$"
               class="form-control" id="code"
               placeholder="ex: 356946"
               value="<?php echo htmlspecialchars(isset($_POST['codigo']) ? $_POST['codigo'] : $_sessionCodeNum); ?>">
      </div>
    </div>
    <div class="form-group">
      <label><?php echo $lang['NAME']; ?></label>
      <input type="text" name="nome" class="form-control" id="name" required
             placeholder="<?php echo $lang['NAME']; ?>"
             value="<?php echo htmlspecialchars(isset($_POST['nome']) ? $_POST['nome'] : $_SESSION['CommonName']); ?>">
    </div>
    <div class="row">
      <div class="col-md-6 form-group">
        <label><?php echo $lang['EMAIL']; ?></label>
        <input type="email" name="email" class="form-control" required id="email"
               pattern="^[^@]+@((fe\.up\.pt)|(edu\.fe\.up\.pt)|(up\.pt))$"
               placeholder="utilizador@fe.up.pt"
               value="<?php echo htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : $_SESSION['user']); ?>">
      </div>
      <div class="col-md-6 form-group">
        <label><?php echo $lang['ALT_EMAIL']; ?></label>
        <input type="email" name="emailalt" class="form-control" id="altemail"
               placeholder="<?php echo $lang['ALT_EMAIL']; ?>"
               value="<?php echo htmlspecialchars(isset($_POST['emailalt']) ? $_POST['emailalt'] : ''); ?>">
      </div>
    </div>
    <div class="row">
      <div class="col-md-5 form-group">
        <label><?php echo $lang['PHONE']; ?></label>
        <?php
        // Parse valor existente "indicativo numero" → separar
        $tfVal = isset($_POST['telefone']) ? $_POST['telefone'] : '';
        $tfInd = '+351'; $tfNum = '';
        if (preg_match('/^(\+\d{1,4})\s+(.+)$/', trim($tfVal), $tfM)) {
            $tfInd = $tfM[1]; $tfNum = $tfM[2];
        } elseif ($tfVal) { $tfNum = preg_replace('/\D/', '', $tfVal); }
        ?>
        <div class="input-group">
          <div class="input-group-prepend">
            <select name="telefone_indicativo" class="custom-select"
                    style="border-radius:.25rem 0 0 .25rem;min-width:105px">
              <?php
              $inds = [
                '+351'=>'🇵🇹 +351','+34'=>'🇪🇸 +34','+33'=>'🇫🇷 +33',
                '+49'=>'🇩🇪 +49','+39'=>'🇮🇹 +39','+44'=>'🇬🇧 +44',
                '+31'=>'🇳🇱 +31','+32'=>'🇧🇪 +32','+41'=>'🇨🇭 +41',
                '+43'=>'🇦🇹 +43','+46'=>'🇸🇪 +46','+47'=>'🇳🇴 +47',
                '+45'=>'🇩🇰 +45','+48'=>'🇵🇱 +48','+420'=>'🇨🇿 +420',
                '+55'=>'🇧🇷 +55','+244'=>'🇦🇴 +244','+258'=>'🇲🇿 +258',
                '+238'=>'🇨🇻 +238','+1'=>'🇺🇸 +1',
              ];
              foreach ($inds as $code => $label):
                $sel = ($tfInd === $code) ? 'selected' : '';
              ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= $sel ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <input type="tel" name="telefone_numero" class="form-control" required
                 placeholder="912 345 678"
                 value="<?= htmlspecialchars($tfNum) ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ── Período e Afiliação ── -->
  <div class="iq-form-section">
    <div class="iq-form-section-title">Período e Afiliação</div>
    <div class="row">
      <div class="col-md-3 form-group">
        <label><?php echo $lang['BEGIN_DATE']; ?></label>
        <input type="date" name="datainicio" class="form-control" required id="datainicio"
               value="<?php echo htmlspecialchars(isset($_POST['datainicio']) ? $_POST['datainicio'] : ''); ?>">
      </div>
      <div class="col-md-3 form-group">
        <label><?php echo $lang['END_DATE']; ?></label>
        <input type="date" name="datafim" class="form-control" required id="datafim"
               value="<?php echo htmlspecialchars(isset($_POST['datafim']) ? $_POST['datafim'] : ''); ?>">
      </div>
      <div class="col-md-4 form-group">
        <label><?php echo $lang['UNIT']; ?></label>
        <select name="unidade" class="form-control" id="unidade" required>
          <option selected disabled value="">Escolha uma opção</option>
          <option <?php if (isset($unidade) && $unidade=="CEFT")      echo "selected"; ?>>CEFT</option>
          <option <?php if (isset($unidade) && $unidade=="LEPABE")    echo "selected"; ?>>LEPABE</option>
          <option <?php if (isset($unidade) && $unidade=="LSRE-LCM") echo "selected"; ?>>LSRE-LCM</option>
          <option <?php if (isset($unidade) && $unidade=="REQUIMTE") echo "selected"; ?>>REQUIMTE</option>
          <option <?php if (isset($unidade) && $unidade=="Outro")    echo "selected"; ?>>Outro</option>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="col-md-7 form-group">
        <label><?php echo $lang['WORKPLACE']; ?></label>
        <input type="text" name="workplace" class="form-control" required id="workplace"
               placeholder="<?php echo $lang['WORKPLACE']; ?>"
               value="<?php echo htmlspecialchars(isset($_POST['workplace']) ? $_POST['workplace'] : ''); ?>">
      </div>
      <div class="col-md-4 form-group">
        <label><?php echo $lang['EXTENSION']; ?></label>
        <input type="text" name="extension" class="form-control" required id="extension"
               placeholder="<?php echo $lang['EXTENSION']; ?>"
               value="<?php echo htmlspecialchars(isset($_POST['extension']) ? $_POST['extension'] : ''); ?>">
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
        <label><?php echo $lang['PROGROUP']; ?></label>
        <select id="grupo" name="grupo" class="form-control" required>
          <?php echo $option_grupo; ?>
        </select>
      </div>
      <div class="col-md-5 form-group">
        <label><?php echo $lang['CATEGORY']; ?></label>
        <select id="category" name="categoria" class="form-control" required>
          <?php echo $option_cat; ?>
        </select>
      </div>
    </div>
    <div id="curso" <?php if ($grupo!='2' && $grupo!='3'){echo 'style="display:none"';} ?>>
      <div class="row">
        <div class="col-md-6 form-group">
          <label><?php echo $lang['COURSE']; ?></label>
          <input class="form-control" id="cursoInput" type="text" name="curso"
                 placeholder="<?php echo $lang['COURSE']; ?>"
                 value="<?php echo !empty($curso) ? $curso : ''; ?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-6 form-group">
        <label><?php echo $lang['WORK_RESP']; ?></label>
        <select id="responsavel" name="responsavel" class="form-control" required>
          <option selected disabled value=""><?php echo $lang['OPTION']; ?></option>
          <?php echo $option_resp; ?>
          <option value="0" <?php echo $option_resp_select; ?>><?php echo $lang['OTHER']; ?></option>
        </select>
      </div>
    </div>
    <div id="outroresp" <?php if ($respespaco!='0'){echo 'style="display:none"';} ?>>
      <div class="row">
        <div class="col-md-6 form-group">
          <label><?php echo $lang['WORK_RESP2']; ?></label>
          <input class="form-control" id="outroresponsavel" type="text" name="outroresponsavel"
                 placeholder="<?php echo $lang['WORK_RESP2']; ?>"
                 value="<?php echo !empty($resptrabalho) ? $resptrabalho : ''; ?>">
          <?php if (!empty($WorkrespError)): ?>
            <div class="text-danger small mt-1"><?php echo $WorkrespError; ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Acessos (cresce para preencher o espaço restante) ── -->
  <div class="iq-form-section iq-form-section-fill">
    <div class="iq-form-section-title">Acessos</div>

    <div class="iq-form-inline">
      <label><?php echo $lang['DEQ_ACCESS']; ?></label>
      <select id="acessodeq" name="acessodeq" class="form-control" required>
        <option value=""><?php echo $lang['OPTION']; ?></option>
        <option <?php if (isset($acessodeq) && $acessodeq=="1") echo "selected"; ?> value="1"><?php echo $lang['OPT_YES']; ?></option>
        <option <?php if (isset($acessodeq) && $acessodeq=="0") echo "selected"; ?> value="0"><?php echo $lang['OPT_NO']; ?></option>
      </select>
    </div>

    <div class="form-group iq-fill">
      <label><?php echo $lang['LAB_ACCESS']; ?></label>
      <div class="iq-checkboxlist" id="acessos-list">
        <?php echo $gabChecks; ?>
      </div>
    </div>
  </div>

  </div><!-- /.iq-form-col direita -->

  </div><!-- /.iq-form-2col -->

  <!-- ── Acções ── -->
  <div class="iq-form-actions mt-1">
    <button type="submit" class="btn btn-primary"><?php echo $lang['SUBMIT']; ?></button>
  </div>

</form>

<script>
// ── Mapeamento grupo → categorias permitidas ──────────────────────
var grupoCategorias = {
    1: [1,2,3,5,6,7],
    2: [4,5],
    3: [9,80],
    4: [3,6,9,10],
    5: [1,2,3,5,6,7,10],
    6: [6,9,10,80],
    7: [4,9],
    8: [11,12,13,14,15,16,17],
    9: [1,2,3,5,6]
};

// Cópia de todas as <option> da categoria para restaurar
var allCatOptions = document.getElementById('category')
    ? document.getElementById('category').innerHTML : '';

function filtrarCategorias(grupoId) {
    var sel = document.getElementById('category');
    if (!sel) return;
    var permitidas = grupoCategorias[grupoId] || null;
    sel.innerHTML = allCatOptions; // repõe tudo
    if (!permitidas) return;
    Array.from(sel.options).forEach(function(opt) {
        if (opt.disabled) return; // placeholder
        if (permitidas.indexOf(parseInt(opt.value)) === -1) {
            opt.remove();
        }
    });
    if (sel.options.length > 0) sel.selectedIndex = 0;
}

document.addEventListener('DOMContentLoaded', function () {

    // ── Filtro grupo → categoria ──────────────────────────────────
    var selGrupo = document.getElementById('grupo');
    if (selGrupo) {
        selGrupo.addEventListener('change', function () {
            filtrarCategorias(parseInt(this.value));
            // mostrar/ocultar campo curso
            var mostrarCurso = [2,3,7].indexOf(parseInt(this.value)) !== -1;
            document.getElementById('curso').style.display = mostrarCurso ? '' : 'none';
            // desabilitar acesso DEQ para externos (grupo 3)
            var selDeq = document.querySelector('select[name=acessodeq]');
            if (parseInt(this.value) === 3) {
                selDeq.value = '0'; selDeq.disabled = true;
            } else {
                selDeq.disabled = false;
            }
        });
        // Aplicar na carga se já houver valor selecionado
        if (selGrupo.value) filtrarCategorias(parseInt(selGrupo.value));
    }

    // ── Código UP: filtra grupos para estudantes (9 dígitos) ──────
    var inpCode = document.getElementById('code');
    if (inpCode) {
        inpCode.addEventListener('change', function () {
            var selG = document.getElementById('grupo');
            if (this.value.length === 9) {
                [1,4,5,6].forEach(function(v) {
                    var opt = selG.querySelector('option[value="' + v + '"]');
                    if (opt) opt.remove();
                });
            } else {
                selG.innerHTML = allGrupoOptions;
            }
        });
    }

    // ── Responsável: mostrar campo "outro" ────────────────────────
    var selResp = document.querySelector('select[name=responsavel]');
    if (selResp) {
        selResp.addEventListener('change', function () {
            document.getElementById('outroresp').style.display =
                this.value === '0' ? '' : 'none';
        });
    }

    // ── Validação cruzada de datas ────────────────────────────────
    var inpInicio = document.getElementById('datainicio');
    var inpFim    = document.getElementById('datafim');
    if (inpInicio && inpFim) {
        inpInicio.addEventListener('change', function () {
            inpFim.min = this.value;
        });
        inpFim.addEventListener('change', function () {
            inpInicio.max = this.value;
        });
    }

});

// Cópia dos grupos para restaurar ao limpar o campo código
var allGrupoOptions = document.getElementById('grupo')
    ? document.getElementById('grupo').innerHTML : '';
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
