<?php
    //session_start();
require_once $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'].'/infodeqb/session.php';

  //Retrieve Language
  $sites = array('en'=>'en','pt'=>'pt');
  $language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
  // Set default language if a '$lang' version of site is not available
  if (!isset($sites[$language])) {
    $language= 'en';
  }
  include "./lang/lang.".$sites[$language].".php";


if (isset($_SESSION['registo']))
{
  //print_r ($_SESSION["registo"]);
  $result = "";
  $result2 = "";
  $codigo = $_SESSION["registo"]['codigo'];
  $nome = $_SESSION["registo"]['nome'];
  $email = $_SESSION["registo"]['email'];
  $emailalt = $_SESSION["registo"]['emailalt'];
  $telefone = $_SESSION["registo"]['telefone'];
  $datainicio = date("d-m-Y",strtotime($_SESSION["registo"]['datainicio']));
  $datafim =  date("d-m-Y",strtotime($_SESSION["registo"]['datafim']));
  $responsavel = $_SESSION["registo"]['responsavel'];
  $outroresponsavel = $_SESSION["registo"]['outroresponsavel'];
  $acessodeq = $_SESSION["acessodeq"];
  $grupo = $_SESSION["registo"]['grupo'];
  $categoria = $_SESSION["registo"]['categoria'];
  $curso = $_SESSION["registo"]['curso'];
  $result=($acessodeq == "1"?"DEQ;":'')." ".$_SESSION["result"];
  $result2=($acessodeq == "1"?"DEQ;":'')." ".$_SESSION["deqid"];
  $result3=($acessodeq == "1"?"DEQ;":'')." ".$_SESSION["gabid"];
  $optacessos="";
  $acessos =array_map ('trim', explode(";",$result2));

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $ops='';
  $grup='';
  $cat='';
  $gab='';
  $sql = 'SELECT * FROM infodeqb_rds_responsaveis order by respespaco';
  $sqlgrupo = 'SELECT * FROM infodeqb_rds_grupo order by grupoid';
  $sqlcat = 'SELECT * FROM infodeqb_rds_categoria order by categoriaid';
//echo $result3;
  foreach ($pdo->query($sql) as $row) {
  if ($_SESSION["registo"]['responsavel'] == $row["Codigo"]) {
      $ops.= $row["respespaco"];
    } else { }
  }


  foreach ($pdo->query($sqlgrupo) as $rowgrupo) {
    if ( $grupo == $rowgrupo["grupoid"]) {
      $grup.= $rowgrupo["grupo_pro"];
    }
  }

  foreach ($pdo->query($sqlcat) as $rowcat) {
    if ( $categoria == $rowcat["categoriaid"]) {
      $cat.= $rowcat["categoria"];
    }
  }


  $respgab=[];
  foreach ($acessos as $i => $item) {
    $sqlgab= 'SELECT * FROM infodeqb_rds_gabinetes inner join infodeqb_rds_responsaveis on Codigo=responsavel WHERE (deqid ="'.$acessos[$i].'")';
    foreach ($pdo->query($sqlgab) as $rowgab){
    $respgab[].= $rowgab["respespaco"];
    }
  }
  $uniquerespgab = array_unique($respgab);

  sort($uniquerespgab);
  Database::disconnect();
}
else {
//echo "no session";
header("Location: index.php");
}

$pageTitle = 'Registo Concluído';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

				<!-- Breadcrumbs-->
				<ol class="breadcrumb hidden-print">
					<li class="breadcrumb-item d-print-none "><a href="#"> Registo de colaboradores</a></li>
				</ol>
				<div class="card  mb-3 d-print-none">
					<div class="card-header  ">
						<i class="fas fa-table"></i>
						<?php echo $lang['INFO']; ?>
					</div>
					<div class="row  ">
						<div class="col-md-12 col-xs-12   ">
							<div class="col-md-12 col-xs-12 alert-success">
								<br><?php echo $lang['SUCCESS']; ?>
								<center><br>
								</center>
							</div>
						</div>
					</div>
				</div>

				<div class="card mb-3">
					<div class="card-header">
						<i class="fas fa-table"></i>
						<?php echo $lang['RECORD']; ?>
					</div>
					<div class="row ">
						<div class="col-md-12 col-xs-12 mt-4">
							<div class="col-md-12 col-xs-12">
								<table class="table table-borderless table-sm">
									<tr><td><b><?php echo $lang['FEUP_CODE']; ?></b> <?PHP echo (!empty($codigo)?$codigo:'')?></td></tr>
									<tr><td><b><?php echo $lang['NAME']; ?></b> <?PHP echo (!empty($nome)?$nome:'')?></td> </tr>
									<tr><td><b><?php echo $lang['EMAIL']; ?></b> <?PHP echo (!empty($email)?$email:'')?></td> </tr>
									<tr><td><b><?php echo $lang['ALT_EMAIL']; ?> </b><?PHP echo (!empty ($emailalt)?$emailalt:'N.A')?></td> </tr>
									<tr><td><b><?php echo $lang['PHONE_PRINT']; ?> </b><?php echo (!empty($telefone)?$telefone:'')?> </td></tr>
								</table>
							</div>
						</div>
					</div>
					<div class="row ">
						<div class="col-md-12 col-xs-12 mt-4">
							<div class="col-md-12 col-xs-12">
								<table class="table table-bordered table-sm">
								<thead class="">
									<tr class="text-start ">
									<th class="border-top-0"><?php echo $lang['CATEGORY']; ?></th>
									<th class="border-top-0"><?php echo $lang['BEGIN_DATE']; ?></th>
									<th class="border-top-0"><?php echo $lang['END_DATE']; ?></th>
									</tr>
								</thead>
								<tbody>
									<tr>
									<td ><?php echo (!empty($cat)?$cat:'').(!empty($grup)?' ('.$grup.')':'')?></td>
									<td ><?php echo (!empty($datainicio)?$datainicio:'')?></td>
									<td ><?php echo (!empty($datafim)?$datafim:'')?></td>
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
									<thead class="" >
										<tr class="text-start">
										<th class="border-top-0 "><?php echo $lang['WORK_RESP']; ?></th>
										<th  class="border-top-0 " style="width: 20%"><?php echo $lang['SIGNATURE']; ?></th>
										</tr>
									</thead>
									<tbody>
										<tr >
										<td ><?PHP echo (($responsavel!='0')?$ops:$outroresponsavel)?></td>
										<td ></td>
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
									$table = "<table class='table table-bordered table-sm'><thead class=''><tr><th class='border-top-0'>".$lang['REQUESTED_ACCESS']."</th><th class='border-top-0'>".$lang['WORKSPACE_RESP']."</th><th class='border-top-0' >".$lang['SIGNATURE']."</th></tr><thead>";
									foreach($uniquerespgab as $resp)
									{
									$rowNum ++;
									$table .= "<tr>";
									$table .= ($rowNum == 1) ? ("<td rowspan='%s' >".$result."</td>"): "";
									$table .= "<td >".$resp."</td>";
									$table .= "<td ></td>";
									$table .= "</tr>";
									}
									$table .= "</table>";
									printf($table, $rowNum);
									?>

									<?php
									$rowNum = 0;
									$table = "<table class='table table-bordered table-sm'><thead class=''><tr ><th class='col-md-4 border-top-0'>".$lang['DOOR_ID']."</th></tr><thead>";
									foreach($uniquerespgab as $resp)
									{
									$rowNum ++;
									$table .= "<tr>";
									$table .= ($rowNum == 1) ? ("<td rowspan='%s' class='col-md-4'>".$result3."</td>"): "";
									$table .= "</tr>";
									}
									$table .= "</table>";
									printf($table, $rowNum);
								?>
								<p><br><strong><?php echo $lang['DATE']; ?></strong><br><br>
							</div>
						</div>
					</div>
				</div>

<script>
$(document).ready(function() {
$('#dataTable').DataTable();
} );
</script>

<script>
function myFunction() {
window.print();
}
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
