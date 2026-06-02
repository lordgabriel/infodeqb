<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado

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

$validado = $_SESSION['user'];
$emailuser = $_SESSION['user'] . "@fe.up.pt";

$result = null;

$id = null;
$id1 = null;
$categoria = null;
$os = null;
$id = $_REQUEST['id'];
// $id1 = $_REQUEST['id1'];
// print_r($_POST);
if (! isset($id)) {
    header("Location: ../index.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT * FROM infodeqb_rds_colaborador where infodeqb_rds_colaborador.codigo = ? ";
$q = $pdo->prepare($sql);
$q->execute(array(
        $id
));
$data = $q->fetch(PDO::FETCH_ASSOC);

if (! empty($_POST)) {
    // echo $resptrabalho; // keep track validation errors
    // print_r ($_Post);
    $codigoError = null;
    $nameError = null;
    $emailError = null;
    $cpError = null;
    $telefoneError = null;
    $inicioError = null;
    $localidadeError = null;
    $fimError = null;

    // keep track post values
    $result = "";
    $codigo = $_POST['id'];
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $emailalt = $_POST['emailalt'];
    $telefone = phoneFromPost();

    // validate input
    $validar = true;

    // Insert data
    if ($validar) {

        try {

            $pdo = Database::connect();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->beginTransaction(); /* Inicia a transação */
            $sqlupdate_user = "UPDATE infodeqb_rds_colaborador SET nome = :nome ,email = :email,emailalt = :emailalt,telefone = :telefone where codigo = :codigo";
            $q2 = $pdo->prepare($sqlupdate_user);
            $q2->execute(
                    array(
                            ':nome' => $nome,
                            ':email' => $email,
                            ':emailalt' => $emailalt,
                            ':telefone' => $telefone,
                            ':codigo' => $codigo
                    ));

            if (! $q2) {
                die('Erro a atualizar os Dados Pessoais ');
            }

            // session_close();
            $pdo->commit();
            Database::disconnect();

            $result = '<div class="alert alert-success" role="alert">Registro atualizado com sucesso, aguarde você está sendo redirecionado ...</div>';

            echo '<meta http-equiv=refresh content=\'2;URL=detail.php?id=' . $id .
                    '\'>';

        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                // duplicate entry, do something else
                $codigoError = $lang['ERR_DUPLICATE_CODE'];
                $valid = false;
            } else {
                echo '// an error other than duplicate entry occurred';
                echo "erro.<br>" . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Editar Utilizador';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

				<!-- Breadcrumbs-->
				<ol class="breadcrumb">
					<li class="breadcrumb-item "><h5>Registo de colaboradores</h5></li>
				</ol>

				<div class=""><?php echo $result; ?></div>
				<div class="card mb-3">
					<div class="card-header">
						<i class="fas fa-edit"></i>Editar Registo
					</div>
					<form class="form-horizontal form-label-left input_mask"
						action="edituser.php" method="post">
						<div class="card-body">
							<!-- page content -->
							<div class="row ">
								<div class="col-md-3 col-xs-3"></div>
								<div class="col-md-6 col-xs-6">
									<div class="col-md-12 col-xs-12">
										<div class="x_title">
											<h4>Dados Pessoais</h4>
										</div>
									</div>
									<div class="clearfix"></div>
									<div class="col-xl-12 col-sm-12 mb-12">
										<div class="x_content">
											<div class="form-group">
												<div
													class="col-md-12 col-sm-12 col-xs-12 form-group has-feedback">
													<input class="form-control" type="hidden" name="id1"
														value="<?php echo $id1;?>"> <input class="form-control"
														type="hidden" name="id" value="<?php echo $id;?>"> <input
														name="codigo" disabled required type="text" maxlength="9"
														pattern="^(\d{6}|\d{9})$"
														class="form-control has-feedback-left" id="code"
														placeholder="<?php echo $lang['FEUP_CODE']; ?>"
														value="<?php print_r($data['codigo'])?>"> <span
														class="fa fa-id-card form-control-feedback left"
														aria-hidden="true"></span>
												</div>
												<div class="clearfix"></div>
												<div
													class="col-md-12 col-sm-12 col-xs-12 form-group has-feedback">
													<input type="text" name="nome"
														class="form-control has-feedback-left" id="name" required
														placeholder="<?php echo $lang['NAME']; ?>"
														value="<?php print_r($data['nome'])?>"> <span
														class="fa fa-user form-control-feedback left"
														aria-hidden="true"></span>
												</div>
												<div class="clearfix"></div>
												<div
													class="col-md-12 col-sm-12 col-xs-12 form-group has-feedback">
													<input type="email" name="email"
														pattern="(^.+@.?(fe\.up\.pt)$)|(^.+@.?(edu\.fe\.up\.pt)$)"
														class="form-control has-feedback-left" required id="email"
														placeholder="<?php echo $lang['EMAIL']; ?>"
														value="<?php print_r($data['email'])?>"> <span
														class="fa fa-envelope form-control-feedback left"
														aria-hidden="true"></span>
												</div>
												<div class="clearfix"></div>
												<div
													class="col-md-12 col-sm-12 col-xs-12 form-group has-feedback">
													<input type="email" name="emailalt"
														class="form-control has-feedback-left" id="altemail"
														placeholder="<?php echo $lang['ALT_EMAIL']; ?>"
														value="<?php print_r($data['emailalt'])?>"> <span
														class="fa fa-envelope form-control-feedback left"
														aria-hidden="true"></span>
												</div>
												<div class="clearfix"></div>
												<div
													class="col-md-12 col-sm-12 col-xs-12 form-group has-feedback">
													<?php renderPhoneInput($data['telefone'] ?? ''); ?>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="col-md-3 col-xs-3"></div>
							</div>
						</div>

				</div>
				<div class="col-md-12 col-sm-12 col-xs-12 text-center">
					<a class="text-center text-dark mr-4" href="javascript:Back()"><i
						class="fas fa-undo-alt fa-lg"></i></a>
					<button type="submit" class="btn btn-default"><?php echo $lang['SUBMIT'];  ?></button>

				</div>
				</form>

<script type="text/javascript">
function Back() {
	window.history.go(-1);
}
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
