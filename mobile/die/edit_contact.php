<?php
require $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'].'/infodeqb/session.php';
$pdo = Database::connect();

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acesso restrito');
}
$username = $_SESSION['user'];
$isAdmin = true;

$id = $_GET['id'] ?? '';
$editing = !empty($id);

// Pré-preenchimento com parâmetros de URL se estiver dentro de país/empresa
$prePais = $_GET['pais'] ?? '';
$preEmpresa = $_GET['empresa'] ?? '';

$nome = $empresa = $email = $pais = $contacto_feup = $logo_empresa = '';
$errors = [];

// Se for edição, buscar dados existentes
if($editing) {
    $stmt = $pdo->prepare("SELECT * FROM infodeqb_company_contacts WHERE id=?");
    $stmt->execute([$id]);
    $contact = $stmt->fetch();
    if(!$contact) exit("Contacto não encontrado.");
    $nome = $contact['nome'];
    $empresa = $contact['empresa'];
    $email = $contact['email'];
    $pais = $contact['pais'];
    $contacto_feup = $contact['contacto_feup'];

} else {
    // Pré-preenche país/empresa
    $pais = $prePais;
    $empresa = $preEmpresa;
}

// Processar submissão
if($_SERVER['REQUEST_METHOD']=='POST') {
    $nome = trim($_POST['nome']);
    $empresa = trim($_POST['empresa']);
    $email = trim($_POST['email']);
    $pais = trim($_POST['pais']);
    $contacto_feup = trim($_POST['contacto_feup']);


    if(!$nome) $errors[] = "O nome é obrigatório.";
    if(!$empresa) $errors[] = "A empresa é obrigatória.";
    if(!$email) $errors[] = "O email é obrigatório.";
    if(!$pais) $errors[] = "O país é obrigatório.";

    if(empty($errors)) {
        if($editing) {
            $stmt = $pdo->prepare("UPDATE infodeqb_company_contacts SET nome=?, empresa=?, email=?, pais=?, contacto_feup=? WHERE id=?");
            $stmt->execute([$nome,$empresa,$email,$pais,$contacto_feup,$id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO infodeqb_company_contacts (nome,empresa,email,pais,contacto_feup,criado_por) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$nome,$empresa,$email,$pais,$contacto_feup,$username]);
        }
        header("Location: index.php?pais=".urlencode($pais)."&empresa=".urlencode($empresa));
        exit;
    }
}

$pageTitle = t('MOBILE_EDIT_CONTACT');
$mainClass  = 'iq-hr-page';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

            	<!-- Breadcrumbs-->
            	<ol class="breadcrumb">
            	<li class="breadcrumb-item "><h5>Eng. Química - Mobilidade OUT</h5></li>
            	</ol>
            		<div class="card mb-3">
            			<div class="card-header">
            				<i class="fas fa-table"></i> <?= $editing ? "Editar" : "Adicionar" ?> Contacto
            			</div>
            			<div class="card-body">
                		<a href="index.php?pais=<?=urlencode($pais)?>&empresa=<?=urlencode($empresa)?>" class="btn btn-secondary text-white mb-3">← Voltar</a>

                            <?php if($errors): ?>
                            <div class="alert alert-danger">
                            <ul><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul>
                            </div>
                            <?php endif; ?>

                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Nome *</label>
                                    <input type="text" name="nome" class="form-control" value="<?=htmlspecialchars($nome)?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Empresa *</label>
                                    <input type="text" name="empresa" class="form-control" value="<?=htmlspecialchars($empresa)?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" value="<?=htmlspecialchars($email)?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">País *</label>
                                    <input type="text" name="pais" class="form-control" value="<?=htmlspecialchars($pais)?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contacto FEUP</label>
                                    <input type="text" name="contacto_feup" class="form-control" value="<?=htmlspecialchars($contacto_feup)?>">
                                </div>

                                <button class="btn btn-primary"><?= $editing ? "Guardar Alterações" : "Adicionar Contacto" ?></button>
                            </form>
            			</div>
            		</div>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
