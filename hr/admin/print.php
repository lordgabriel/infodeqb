<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

date_default_timezone_set('Europe/Lisbon');

//Retrieve Language
$sites = array('en'=>'en','pt'=>'pt');
$language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
// Set default language if a '$lang' version of site is not available
if (!isset($sites[$language])) {
    $language= 'en';
}

include ROOT_DIR."/infodeqb/lang/lang.".$sites[$language].".php";

$id = null;
$id1 = null;

require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado

if (! empty($_GET['id'])) {
    $id = $_REQUEST['id'];
    if (! empty($_GET['id1'])) {
        $id1 = $_REQUEST['id1'];
    } else {
        header("Location: index.php");
    }
}

if (! empty($_POST)) {
    $id = $_POST['codigo'];
}

if (null == $id) {

    header("Location: index.php");
} else {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "SELECT *  FROM infodeqb_rds_colaborador INNER JOIN infodeqb_rds_registo ON infodeqb_rds_colaborador.codigo=infodeqb_rds_registo.codigo INNER JOIN infodeqb_rds_grupo ON infodeqb_rds_registo.grupo=infodeqb_rds_grupo.grupoid  INNER JOIN infodeqb_rds_categoria ON infodeqb_rds_registo.categoria=infodeqb_rds_categoria.categoriaid left join infodeqb_rds_responsaveis on infodeqb_rds_registo.responsavel=infodeqb_rds_responsaveis.codigo where infodeqb_rds_colaborador.codigo = ? and infodeqb_rds_registo.deleted<>1 and infodeqb_rds_registo.autoid= ?";

    $q = $pdo->prepare($sql);
    $q->execute(array(
            $id,
            $id1
    ));
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if ($data['acessodeq'] == 1) {
        $acessodeq = "DEQ; ";
    } else {
        $acessodeq = "";
    }

    $acessos_name = $data['acessos'];
    // Carregar acessos da tabela relacional
    $gabs = getRegistoAcessos($pdo, (int)$data['autoid']);

    $respgab = [];
    $gabid[] = [];
    $doorid = "";
    foreach ($gabs as $deqid) {
        $sqlgab = 'SELECT * FROM infodeqb_rds_gabinetes inner join infodeqb_rds_responsaveis on Codigo=responsavel WHERE deqid = ?';
        $stGab = $pdo->prepare($sqlgab);
        $stGab->execute([$deqid]);
        foreach ($stGab->fetchAll(PDO::FETCH_ASSOC) as $rowgab) {
            $doorid .= $rowgab["gabid"] . "; ";
            $respgab[] .= trim($rowgab["respespaco"]);
        }
    }

    Database::disconnect();

    $uniquerespgab = array_unique($respgab);
    sort($uniquerespgab);

$pageTitle = 'Imprimir Ficha';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

				<!-- Breadcrumbs-->
				<ol class="breadcrumb hidden-print">
					<li class="breadcrumb-item "><h5>Registo de colaboradores</h5></li>
				</ol>
				<div class="card mb-3">
					<div class="card-header ">
						<i class="fas fa-table"></i>
						<?php echo $lang['RECORD']; ?>
					</div>
					<div class="row ">
						<div class="col-md-12 col-xs-12 mt-4">
							<div class="col-md-12 col-xs-12">
								<table class="table table-borderless table-sm">
									<tr>
										<td><b><?php echo $lang['FEUP_CODE']; ?></b> <?php print_r($data['codigo'])?></td>
									</tr>
									<tr>
										<td><b><?php echo $lang['NAME']; ?></b> <?php print_r($data['nome'])?></td>
									</tr>
									<tr>
										<td><b><?php echo $lang['EMAIL']; ?></b> <?php print_r($data['email'])?></td>
									</tr>
									<tr>
										<td><b><?php echo $lang['ALT_EMAIL']; ?> </b><?php print_r($data['emailalt'])?></td>
									</tr>
									<tr>
										<td><b><?php echo $lang['PHONE_PRINT']; ?> </b><?php print_r($data['telefone'])?> </td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="row ">
						<div class="col-md-12 col-xs-12 mt-4">
							<div class="col-md-12 col-xs-12">
								<table class="table table-bordered table-sm">
									<thead class="thead-light">
										<tr class="text-left ">
											<th class="border-top-0"><?php echo $lang['PROGROUP']; ?></th>
											<th class="border-top-0"><?php echo $lang['CATEGORY']; ?></th>
											<th class="border-top-0"><?php echo $lang['BEGIN_DATE']; ?></th>
											<th class="border-top-0"><?php echo $lang['END_DATE']; ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><?php print_r($data['grupo_pro']);?></td>
											<td><?php print_r($data['categoria']);?></td>
											<td><?php print_r($data['datainicio']);?></td>
											<td><?php print_r($data['datafim']);?></td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div class="row ">
						<div class="col-md-12 col-xs-12 mt-4">
							<div class="col-md-12 col-xs-12">
								<table class="table table-bordered table-sm">
									<thead class="thead-light">
										<tr class="text-left">
											<th class="border-top-0 "><?php echo $lang['WORK_RESP']; ?></th>
											<th class="border-top-0 " style="width: 20%"><?php echo $lang['SIGNATURE']; ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><?php  ($data['Codigo']!='0')? print_r($data['respespaco']):print_r($data['responsavel']);?></td>
											<td></td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div class="row ">
						<div class="col-md-12 col-xs-12 mt-4">
							<div class="col-md-12 col-xs-12">
								<?php
    $rowNum = 0;
    $table = "<table class='table table-bordered table-sm '><thead class='thead-light' ><tr><th class='border-top-0'>" .
            $lang['REQUESTED_ACCESS'] . "</th><th class='border-top-0'>" .
            $lang['WORKSPACE_RESP'] . "</th><th class='border-top-0' >" .
            $lang['SIGNATURE'] . "</th></tr><thead>";
    foreach ($uniquerespgab as $resp) {
        $rowNum ++;
        $table .= "<tr>";
        $table .= ($rowNum == 1) ? ("<td rowspan='%s' >" . $acessodeq .
                $acessos_name . "</td>") : "";
        $table .= "<td>" . $resp . "</td>";
        $table .= "<td></td>";
        $table .= "</tr>";
    }
    $table .= "</table>";
    printf($table, $rowNum);
    ?>

								<?php

    $rowNum = 1;
    $table = "<table class='table table-bordered table-sm'><thead class='thead-light'><tr ><th class='col-md-4 border-top-0'>" .
            $lang['DOOR_ID'] . "</th></tr><thead>";
    $table .= "<tr>";
    $table .= ($rowNum == 1) ? ("<td rowspan='%s' class='col-md-4'>" . $doorid .
            "</td>") : "";
    $table .= "</tr>";
    $table .= "</table>";
    printf($table, $rowNum);
    ?>
								<p>
									<br>
									<strong><?php echo $lang['DATE']; ?></strong><br>
									<br>

							</div>
						</div>
					</div>
				</div>
			<div class="d-print-none">
				<div class="text-center">
					<a style="color: #555555"
						href="print.php?id=<?php print_r($id)?>&id1=<?php print_r($id1)?>">
						<i class="fas fa-print fa-lg"></i>
					</a> <a class="text-center text-dark ml-4" href="javascript:Back()"><i
						class="fas fa-undo-alt fa-lg"></i></a>
				</div>
			</div>

<script>
function myFunction() {
window.print();
}
</script>

<script type="text/javascript">
function Back() {
	window.history.go(-1);
}
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
<?php }?>
