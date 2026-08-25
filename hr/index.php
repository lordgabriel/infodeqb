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
$responsavel = null;
$outroresponsavel = null;
$respespaco = null;
$validar = true;
$status = 'Novo';

// ── Modo Admin: criar registo em nome de outro colaborador ──────────────
require_once ROOT_DIR . '/infodeqb/inc/admins.php'; // define $isAdmin, $_iqAdminsHr, $_iqCurrentUser
$_isHrAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsHr);
$adminMode  = $_isHrAdmin && isset($_GET['admin']) && $_GET['admin'] == '1';

// SQL statements
$sqlresp = 'SELECT * FROM infodeqb_rds_responsaveis where not Codigo=0 order by respespaco';
$sqlgrupo = 'SELECT * FROM infodeqb_rds_grupo where grupoid BETWEEN 1 AND 7 order by orderid';
$sqlcategoria = 'SELECT * FROM infodeqb_rds_categoria where categoriaid not in (11,12,13,14,15,16,17) order by categoriaid';
$sqlgab = 'SELECT * FROM infodeqb_rds_gabinetes WHERE not (visible = 0) order by edificio, piso, nomegab';
$sqlinuser = "INSERT INTO infodeqb_rds_colaborador (codigo,nome,email,emailalt,telefone,createdate)
              VALUES (?,?,?,?,?,?)
              ON DUPLICATE KEY UPDATE nome=VALUES(nome), email=VALUES(email),
                emailalt=VALUES(emailalt), telefone=VALUES(telefone), deleted=0";
$sqlinregister = "INSERT INTO infodeqb_rds_registo (codigo,datainicio,datafim,responsavel,outroresponsavel,acessodeq,grupo,categoria,acessos,acessosid,curso, createdate,dataregisto, status, unidade, local_trabalho, extensao) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

// DB Connection
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$grupoCatMap = getGrupoCategoriasMap($pdo);


/* Insert new record — token de submissão única (evita double-submit) */
if (!empty($_POST)) {
    $submitToken = trim($_POST['_submit_token'] ?? '');
    $sessionToken = $_SESSION['_hr_submit_token'] ?? '';

    // Se o token foi já usado ou não corresponde → redirect para success
    // (cobre browser Back + resend e submissões duplicadas)
    if ($submitToken === '' || $submitToken !== $sessionToken) {
        header('Location: ' . ($adminMode ? 'admin/index.php' : 'success.php'));
        exit;
    }
    // Invalidar imediatamente para impedir re-uso
    unset($_SESSION['_hr_submit_token']);
}

if (! empty($_POST)) {
    if ($adminMode) {
        // Modo admin: código, nome e email são indicados pelo administrador
        $codigo    = preg_replace('/[^0-9]/', '', $_POST['codigo'] ?? '');
        $nome      = trim($_POST['nome'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $nomeEmail = $nome; // sem sessão Shibboleth da pessoa em causa
    } else {
        // codigo, nome e email vêm sempre da sessão Shibboleth — nunca do POST
        $codigo    = preg_replace('/[^0-9]/', '', $_SESSION['Code'] ?? '');
        $nome      = $_SESSION['CommonName'] ?? '';
        $email     = $_SESSION['user'] ?? '';
        $nomeEmail = $_SESSION['DisplayName'] ?? $nome;
    }
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
    $categoria = isset($_POST['categoria']) && $_POST['categoria'] !== '' ? $_POST['categoria'] : 0;
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
    // Validar responsável pelo trabalho
    if ($responsavel != 0) {
        $outroresponsavel = '';
    } elseif (empty($outroresponsavel)) {
        $WorkrespError = $lang['ERR_WORK_RESP'];
        $validar = false;
    }

    // ── 1. Gravar registo na BD (transaction) ──────────────────────
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT grupo_pro FROM infodeqb_rds_grupo WHERE grupoid = ?');
        $stmt->execute([$grupo]);
        $cat = $stmt->fetchColumn() ?: '';

        $stmtCat = $pdo->prepare('SELECT categoria FROM infodeqb_rds_categoria WHERE categoriaid = ?');
        $stmtCat->execute([$categoria]);
        $catNome = $stmtCat->fetchColumn() ?: '';

        $stmt1 = $pdo->prepare('SELECT respespaco FROM infodeqb_rds_responsaveis WHERE Codigo = ?');
        $stmt1->execute([$responsavel]);
        $resp = ($responsavel != 0) ? ($stmt1->fetchColumn() ?: $outroresponsavel) : $outroresponsavel;

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
        $_L = $language; // 'pt' ou 'en'
        $_e = function($pt, $en) use ($_L) { return ($_L === 'en') ? $en : $pt; };

        $info = array(
            'codigo'    => $codigo,
            'nome'      => $nomeEmail,
            'mail'      => $email,
            'altmail'   => $emailalt ?: '-',
            'telefone'  => $telefone ?: '-',
            'grupo'     => $cat ?: '-',
            'categoria' => $catNome ?: '-',
            'inicio'    => $datainicio ?: '-',
            'fim'       => $datafim ?: '-',
            'responsavel' => $resp ?: '-',
            'acessodeq' => $x ?: '-',
            'acessos'   => $result ?: '-',
            'altemail'  => $emailalt ?: '-',
            // i18n — placeholders no mail_register.html
            'subtitle'               => $_e('Registo Efetuado com Sucesso', 'Registration Completed Successfully'),
            'greeting'               => $_e('Caro(a) ' . $nomeEmail . ',', 'Dear ' . $nomeEmail . ','),
            'msg_intro'              => $_e(
                'O seu registo foi submetido com sucesso. Pode consultar abaixo os dados registados.',
                'Your registration was successfully submitted. You can check the registered data below.'
            ),
            'msg_safety'             => $_e(
                'Por favor <a href="https://deq.fe.up.pt/infodeqb/hr/inc/Safety_PT.pdf" style="color:#cce8ff;font-weight:bold;">descarregue aqui</a> e leia cuidadosamente o desdobrável de segurança.',
                'Please <a href="https://deq.fe.up.pt/infodeqb/hr/inc/Safety_EN.pdf" style="color:#cce8ff;font-weight:bold;">download here</a> and read carefully the safety booklet.'
            ),
            'label_section_personal' => $_e('Dados pessoais', 'Personal details'),
            'label_codigo'           => $_e('Código FEUP:', 'FEUP Code:'),
            'label_nome'             => $_e('Nome:', 'Name:'),
            'label_email'            => $_e('E-mail:', 'E-mail:'),
            'label_altemail'         => $_e('E-mail alternativo:', 'Alternate e-mail:'),
            'label_telefone'         => $_e('Contacto telefónico:', 'Phone:'),
            'label_section_registo'  => $_e('Registo', 'Registration'),
            'label_grupo'            => $_e('Grupo profissional:', 'Professional group:'),
            'label_categoria'        => $_e('Categoria:', 'Category:'),
            'label_inicio'           => $_e('Data início:', 'Start date:'),
            'label_fim'              => $_e('Data fim:', 'End date:'),
            'label_responsavel'      => $_e('Responsável do trabalho:', 'Work supervisor:'),
            'label_section_acessos'  => $_e('Acessos', 'Access'),
            'label_porta_norte'      => $_e('Acesso porta norte:', 'North door access:'),
            'label_outros'           => $_e('Outros acessos:', 'Other accesses:'),
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

    if ($adminMode) {
        header("Location: admin/index.php?tab=pills-new&created=1");
    } else {
        header("Location: success.php");
    }
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
        if (isset($_POST['categoria']) && $_POST['categoria'] == $rowcat["categoriaid"]) {
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
$gabCurEdificio = '';
$selectedAcessos = isset($_POST['acessos']) ? (array)$_POST['acessos'] : [];
foreach ($gabRows as $rowgab) {
    if ($rowgab['piso'] !== $gabCurPiso || $rowgab['edificio'] !== $gabCurEdificio) {
        if ($gabCurPiso !== '') $gabChecks .= '</div>';
        $label = '';
        if ($rowgab['edificio'] !== $gabCurEdificio && stripos($rowgab['piso'], 'Edifício') === false) {
            $label .= '<span class="iq-edificio-label">' . htmlspecialchars($rowgab['edificio']) . '</span>';
        }
        $label .= '<span class="iq-checkgroup-label">' . htmlspecialchars($rowgab['piso']) . '</span>';
        $gabChecks .= '<div class="iq-checkgroup">' . $label;
        $gabCurPiso = $rowgab['piso'];
        $gabCurEdificio = $rowgab['edificio'];
    }
    $checked = in_array($rowgab['deqid'], $selectedAcessos) ? ' checked' : '';
    $gabChecks .= '<label><input type="checkbox" name="acessos[]" value="'
        . htmlspecialchars($rowgab['deqid']) . '"' . $checked . '> '
        . htmlspecialchars($rowgab['nomegab']) . '</label>';
}
if ($gabCurPiso !== '') $gabChecks .= '</div>';

// Código numérico do utilizador (para auto-preencher o campo 'codigo')
$_sessionCodeNum = preg_replace('/[^0-9]/', '', $_SESSION['Code'] ?? '');

// Valores a apresentar nos campos Código/Nome/Email
if ($adminMode) {
    $dispCodigo = isset($_POST['codigo']) ? $_POST['codigo'] : trim($_GET['codigo'] ?? '');
    $dispNome   = isset($_POST['nome'])   ? $_POST['nome']   : trim($_GET['nome']   ?? '');
    $dispEmail  = isset($_POST['email'])  ? $_POST['email']  : trim($_GET['email']  ?? '');
} else {
    $dispCodigo = $_sessionCodeNum;
    $dispNome   = !empty($_SESSION['CommonName']) ? $_SESSION['CommonName'] : (!empty($_SESSION['user']) ? $_SESSION['user'] : '');
    $dispEmail  = $_SESSION['user'] ?? '';
}

$pageTitle = $adminMode ? 'Novo Registo (Admin)' : 'Registo de Colaborador';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

<!-- Hidden dom targets for JS -->
<div id="dom-target1" style="display:none"><?php echo htmlspecialchars($option_grupo); ?></div>
<div id="dom-target2" style="display:none"><?php echo htmlspecialchars($option_cat); ?></div>

<div class="iq-page-header">
  <h1>Registo de colaborador</h1>
</div>

<?php if ($adminMode): ?>
<div class="alert alert-info" role="alert">
  <i class="fas fa-user-shield me-1"></i>
  <strong>Modo Administrador</strong> — este registo vai ser criado em nome de outro colaborador.
  Preencha o Código UP, Nome e Email da pessoa em causa.
</div>
<?php endif; ?>

<?php
// Gerar token de submissão único para esta sessão de formulário
$_SESSION['_hr_submit_token'] = bin2hex(random_bytes(16));
?>
<form class="iq-form-2col-wrap" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . ($adminMode ? '?admin=1' : ''); ?>" method="post">
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
      <div class="col-md-4 form-group<?php echo $adminMode ? ' mb-1' : ''; ?>">
        <label><?php echo $lang['FEUP_CODE']; ?></label>
        <input name="codigo" type="text" required maxlength="9" pattern="^(\d{6}|\d{9})$"
               class="form-control" id="code" <?php echo $adminMode ? '' : 'readonly'; ?>
               value="<?php echo htmlspecialchars($dispCodigo); ?>">
      </div>
    </div>
    <?php if ($adminMode): ?>
    <div class="row">
      <div class="col-12">
        <div class="form-text mt-0 mb-2" style="font-size:.78rem;">Código institucional da pessoa (up…). Ao sair do campo, o nome/email são preenchidos automaticamente se já existir.</div>
      </div>
    </div>
    <?php endif; ?>
    <div class="form-group">
      <label><?php echo $lang['NAME']; ?></label>
      <input type="text" name="nome" class="form-control" id="name" required <?php echo $adminMode ? '' : 'readonly'; ?>
             value="<?php echo htmlspecialchars($dispNome); ?>">
    </div>
    <div class="row">
      <div class="col-md-6 form-group">
        <label><?php echo $lang['EMAIL']; ?></label>
        <input type="email" name="email" class="form-control" required id="email" <?php echo $adminMode ? '' : 'readonly'; ?>
               value="<?php echo htmlspecialchars($dispEmail); ?>">
      </div>
      <div class="col-md-6 form-group">
        <label><?php echo $lang['ALT_EMAIL']; ?></label>
        <input type="email" name="emailalt" class="form-control" id="altemail"
               placeholder="<?php echo $lang['ALT_EMAIL']; ?>"
               value="<?php echo htmlspecialchars(isset($_POST['emailalt']) ? $_POST['emailalt'] : ''); ?>">
      </div>
    </div>
    <div class="row">
      <div class="col-md-7 form-group">
        <label><?php echo $lang['PHONE']; ?></label>
        <?php
        // Re-display após erro de validação: combinar indicativo + número de volta
        $tfReStored = combinePhone(
            trim($_POST['telefone_indicativo'] ?? '+351'),
            trim($_POST['telefone_numero'] ?? '')
        );
        renderPhoneInput($tfReStored, '', true);
        ?>
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
        <input type="text" name="extension" class="form-control" id="extension"
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
      <div class="col-md-5 form-group" id="cat-col-wrap">
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
    <div id="outroresp" <?php if (empty($responsavel) || $responsavel != '0'){echo 'style="display:none"';} ?>>
      <div class="row">
        <div class="col-md-6 form-group">
          <label><?php echo $lang['WORK_RESP2']; ?></label>
          <input class="form-control" id="outroresponsavel" type="text" name="outroresponsavel"
                 placeholder="<?php echo $lang['WORK_RESP2']; ?>"
                 value="<?php echo !empty($outroresponsavel) ? htmlspecialchars($outroresponsavel) : ''; ?>"
                 <?php echo ($responsavel == '0') ? 'required' : ''; ?>>
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

    <div class="iq-form-inline" style="gap:1rem;align-items:center;">
      <label style="margin-bottom:0;font-weight:500;"><?php echo $lang['DEQ_ACCESS']; ?></label>
      <div style="display:flex;gap:.75rem;">
        <label style="gap:.35rem;margin-bottom:0;cursor:pointer;font-weight:400;display:flex;align-items:center;">
          <input type="radio" name="acessodeq" value="1" required
                 <?php if (isset($acessodeq) && $acessodeq=="1") echo "checked"; ?>> <?php echo $lang['OPT_YES']; ?>
        </label>
        <label style="gap:.35rem;margin-bottom:0;cursor:pointer;font-weight:400;display:flex;align-items:center;">
          <input type="radio" name="acessodeq" value="0" required
                 <?php if (isset($acessodeq) && $acessodeq=="0") echo "checked"; ?>> <?php echo $lang['OPT_NO']; ?>
        </label>
      </div>
    </div>

    <div class="form-group iq-fill">
      <label><?php echo $lang['LAB_ACCESS']; ?></label>
      <div class="iq-checkboxlist" id="acessos-list">
        <?php echo $gabChecks; ?>
      </div>
      <div class="labs-preview mt-2" id="labs-preview-main"></div>
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
// ── Mapeamento grupo → categorias (carregado da BD) ──────────────
var grupoCategorias    = <?= json_encode($grupoCatMap, JSON_UNESCAPED_UNICODE) ?>;
var gruposSemCategoria = [2, 3, 4, 6, 7];
var allCatOptions      = document.getElementById('category')
    ? document.getElementById('category').innerHTML : '';

function filtrarCategorias(grupoId) {
    var sel  = document.getElementById('category');
    var wrap = document.getElementById('cat-col-wrap');
    if (!sel) return;

    if (gruposSemCategoria.indexOf(grupoId) !== -1) {
        if (wrap) wrap.style.display = 'none';
        sel.required = false;
        return;
    }
    if (wrap) wrap.style.display = '';
    sel.required = true;
    var permitidas = grupoCategorias[grupoId] || null;
    sel.innerHTML  = allCatOptions;
    if (!permitidas) return;
    Array.from(sel.options).forEach(function(opt) {
        if (opt.disabled) return;
        if (permitidas.indexOf(parseInt(opt.value)) === -1) opt.remove();
    });
    if (sel.options.length > 0) sel.selectedIndex = 0;
}

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

document.addEventListener('DOMContentLoaded', function () {

    // ── Filtro grupo → categoria ──────────────────────────────────
    var selGrupo = document.getElementById('grupo');
    if (selGrupo) {
        selGrupo.addEventListener('change', function () {
            filtrarCategorias(parseInt(this.value));
            var mostrarCurso = [2,3,7].indexOf(parseInt(this.value)) !== -1;
            document.getElementById('curso').style.display = mostrarCurso ? '' : 'none';
            var deqRadios = document.querySelectorAll('input[name="acessodeq"]');
            if (parseInt(this.value) === 3) {
                deqRadios.forEach(function(r) {
                    r.disabled = true;
                    if (r.value === '0') r.checked = true;
                });
            } else {
                deqRadios.forEach(function(r) { r.disabled = false; });
            }
        });
        if (selGrupo.value) filtrarCategorias(parseInt(selGrupo.value));
    }

    // ── Preview de labs ───────────────────────────────────────────
    var labList = document.getElementById('acessos-list');
    if (labList) {
        labList.addEventListener('change', function () {
            atualizarPreviewLabs('acessos-list', 'labs-preview-main');
        });
        atualizarPreviewLabs('acessos-list', 'labs-preview-main');
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
            var isOutro = this.value === '0';
            document.getElementById('outroresp').style.display = isOutro ? '' : 'none';
            var inp = document.getElementById('outroresponsavel');
            if (inp) {
                if (isOutro) { inp.setAttribute('required', 'required'); }
                else         { inp.removeAttribute('required'); inp.value = ''; }
            }
        });
    }

    // ── Validação cruzada de datas ────────────────────────────────
    var inpInicio = document.getElementById('datainicio');
    var inpFim    = document.getElementById('datafim');
    if (inpInicio && inpFim) {
        function validarDatasIdx() {
            if (inpInicio.value && inpFim.value && inpFim.value < inpInicio.value) {
                inpFim.setCustomValidity('A data de fim não pode ser anterior à data de início.');
            } else {
                inpFim.setCustomValidity('');
            }
            inpFim.min = inpInicio.value || '';
        }
        inpInicio.addEventListener('change', validarDatasIdx);
        inpFim.addEventListener('change', validarDatasIdx);
    }

    <?php if ($adminMode): ?>
    // ── Modo Admin: autocomplete nome/email a partir do código UP ────
    (function () {
        var inpCodigo = document.getElementById('code');
        var inpNome   = document.getElementById('name');
        var inpEmail  = document.getElementById('email');
        if (!inpCodigo) return;
        var _acTimer = null;
        inpCodigo.addEventListener('input', function () {
            clearTimeout(_acTimer);
            var cod = this.value.trim();
            if (!cod) return;
            _acTimer = setTimeout(function () {
                fetch('admin/ajax-colab.php?codigo=' + encodeURIComponent(cod))
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
    }());
    <?php endif; ?>

});

// Cópia dos grupos para restaurar ao limpar o campo código
var allGrupoOptions = document.getElementById('grupo')
    ? document.getElementById('grupo').innerHTML : '';
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
