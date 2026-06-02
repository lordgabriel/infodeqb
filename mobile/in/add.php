<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsMobile))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
}




$validado = $_SESSION['user'];

$pdo = Database::connect();
$sth = $pdo->prepare('SELECT * from infodeqb_unidades_curriculares where ano LIKE :ano AND curso like :curso AND regime like :regime order by regime, uc');

if (!empty($_POST))
{
	$nome = $_POST['nome'];
	$duracao = $_POST['duracao'];
	$tipocontrato = $_POST['tipocontrato'];
	$universidade = $_POST['universidade'];
	$pais = (isset($_POST['pais'])?$_POST['pais']:0);
	$programa = (isset($_POST['programa'])?$_POST['programa']:0);
	$anoletivo = $_POST['anoletivo'];
	$inicio = date("Y-m-d",strtotime($_POST['inicio']));
	$fim = date("Y-m-d",strtotime($_POST['fim']));
	$obs= $_POST['obs'];
	$outroprograma=$_POST['outroprograma'];
	$resposta=$_POST['resposta'];
	if ($_POST['programa']=='1'){

		$programa=$outroprograma;
	}

	if (empty($_POST['lista_uc'])) {
	    $ucs ="";
	} else {
		$listaucs = array_map(function($element) { return substr($element,0, strpos($element, " ")); }, $_POST['lista_uc']);
		$unique_listaucs= array_unique ($listaucs);
		$ucs= implode(";", $unique_listaucs);
	}

	try {
	// Create database connection using PHP Data Object (PDO)
	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// Create the query - here we grab everything from the table
	$sql = "INSERT INTO infodeqb_registo_mobilidade (nome, universidade, pais, programa, tipocontrato, anoletivo, duracao, inicio, fim, obs, dcoop, ucs) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$q = $pdo->prepare($sql);
	$q->execute(array($nome,$universidade,$pais,$programa,$tipocontrato, $anoletivo,$duracao, $inicio,$fim,$obs, $resposta, $ucs));
	// Close connection to database
	Database::disconnect();
	echo'<div class="alert alert-success  fade show" role="alert" id="success-alert">
  	<strong>Registo efetuado com sucesso</strong>.
	</div>';
	}

	catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "<br/>";
    die();
	}
}

$pageTitle = 'Novo Pedido — Mobilidade';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center" style="gap:8px">
  <h1 class="mr-auto"><i class="fas fa-plus-circle fa-sm me-2 text-muted"></i>Novo registo — Mobilidade IN</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i>Lista
  </a>
</div>
          <div class="card mb-3">
            <div class="card-header py-2">
              <i class="fas fa-user-graduate fa-sm me-1 text-muted"></i>
              <strong>Dados do estudante</strong>
            </div>


	      <div class="card mb-3">

	      	<div class="card-body">
	        	<!-- Formulario de registo-->
		        <form id="mobilidade" action="add.php" method="post">
		            <div class="form-group">
		            	<div class="form-row">
			            	<div class="col-md-2">
			            		<label for="anoletivo" class="text-primary" >Ano letivo</label>
					            <select class="form-control " required name="anoletivo">
					              <option selected disabled value="">Ano Letivo</option>
					              <option value="2024/25">2024/25</option>
					              <option value="2023/24">2023/24</option>
					              <option value="2022/23">2022/23</option>
					            </select>
			              </div>
			            </div>
			        	</div>
		            <div class="form-group">
									<div class="form-row">
										<div class="col-md-4">
											<label for="nome" class="text-primary" >Nome</label>
											<input id="nome" name="nome" type="text" placeholder="nome" required class="form-control">
										</div>
										<div class="col-md-4">
											<label for="universidade" class="text-primary" >Universidade</label>
											<input id="universidade" name="universidade" type="text" placeholder="universidade" class="form-control">
										</div>
										<div class="col-md-4">
											<label for="universidade" class="text-primary">País</label>
											<select class="form-control" name="pais" id="pais" >
												<option selected disabled value="0">País de origem</option>
												<option value="África do Sul">África do Sul</option>
												<option value="Albânia">Albânia</option>
												<option value="Alemanha">Alemanha</option>
												<option value="Andorra">Andorra</option>
												<option value="Angola">Angola</option>
												<option value="Anguilla">Anguilla</option>
												<option value="Antigua">Antigua</option>
												<option value="Arábia Saudita">Arábia Saudita</option>
												<option value="Argentina">Argentina</option>
												<option value="Armênia">Armênia</option>
												<option value="Aruba">Aruba</option>
												<option value="Austrália">Austrália</option>
												<option value="Áustria">Áustria</option>
												<option value="Azerbaijão">Azerbaijão</option>
												<option value="Bahamas">Bahamas</option>
												<option value="Bahrein">Bahrein</option>
												<option value="Bangladesh">Bangladesh</option>
												<option value="Barbados">Barbados</option>
												<option value="Bélgica">Bélgica</option>
												<option value="Benin">Benin</option>
												<option value="Bermudas">Bermudas</option>
												<option value="Botsuana">Botsuana</option>
												<option value="Brasil">Brasil</option>
												<option value="Brunei">Brunei</option>
												<option value="Bulgária">Bulgária</option>
												<option value="Burkina Fasso">Burkina Fasso</option>
												<option value="Cabo Verde">Cabo Verde</option>
												<option value="Camarões">Camarões</option>
												<option value="Camboja">Camboja</option>
												<option value="Canadá">Canadá</option>
												<option value="Cazaquistão">Cazaquistão</option>
												<option value="Chade">Chade</option>
												<option value="Chile">Chile</option>
												<option value="China">China</option>
												<option value="Cidade do Vaticano">Cidade do Vaticano</option>
												<option value="Colômbia">Colômbia</option>
												<option value="Congo">Congo</option>
												<option value="Coréia do Sul">Coréia do Sul</option>
												<option value="Costa do Marfim">Costa do Marfim</option>
												<option value="Costa Rica">Costa Rica</option>
												<option value="Croácia">Croácia</option>
												<option value="Dinamarca">Dinamarca</option>
												<option value="Djibuti">Djibuti</option>
												<option value="Dominica">Dominica</option>
												<option value="EUA">EUA</option>
												<option value="Egito">Egito</option>
												<option value="El Salvador">El Salvador</option>
												<option value="Emirados Árabes">Emirados Árabes</option>
												<option value="Equador">Equador</option>
												<option value="Eritréia">Eritréia</option>
												<option value="Escócia">Escócia</option>
												<option value="Eslováquia">Eslováquia</option>
												<option value="Eslovênia">Eslovênia</option>
												<option value="Espanha">Espanha</option>
												<option value="Estônia">Estônia</option>
												<option value="Etiópia">Etiópia</option>
												<option value="Fiji">Fiji</option>
												<option value="Filipinas">Filipinas</option>
												<option value="Finlândia">Finlândia</option>
												<option value="França">França</option>
												<option value="Gabão">Gabão</option>
												<option value="Gâmbia">Gâmbia</option>
												<option value="Gana">Gana</option>
												<option value="Geórgia">Geórgia</option>
												<option value="Gibraltar">Gibraltar</option>
												<option value="Granada">Granada</option>
												<option value="Grécia">Grécia</option>
												<option value="Guadalupe">Guadalupe</option>
												<option value="Guam">Guam</option>
												<option value="Guatemala">Guatemala</option>
												<option value="Guiana">Guiana</option>
												<option value="Guiana Francesa">Guiana Francesa</option>
												<option value="Guiné-bissau">Guiné-bissau</option>
												<option value="Haiti">Haiti</option>
												<option value="Holanda">Holanda</option>
												<option value="Honduras">Honduras</option>
												<option value="Hong Kong">Hong Kong</option>
												<option value="Hungria">Hungria</option>
												<option value="Iêmen">Iêmen</option>
												<option value="Ilhas Cayman">Ilhas Cayman</option>
												<option value="Ilhas Cook">Ilhas Cook</option>
												<option value="Ilhas Curaçao">Ilhas Curaçao</option>
												<option value="Ilhas Marshall">Ilhas Marshall</option>
												<option value="Ilhas Turks & Caicos">Ilhas Turks & Caicos</option>
												<option value="Ilhas Virgens (brit.)">Ilhas Virgens (brit.)</option>
												<option value="Ilhas Virgens(amer.)">Ilhas Virgens(amer.)</option>
												<option value="Ilhas Wallis e Futuna">Ilhas Wallis e Futuna</option>
												<option value="Índia">Índia</option>
												<option value="Indonésia">Indonésia</option>
												<option value="Inglaterra">Inglaterra</option>
												<option value="Irlanda">Irlanda</option>
												<option value="Islândia">Islândia</option>
												<option value="Israel">Israel</option>
												<option value="Itália">Itália</option>
												<option value="Jamaica">Jamaica</option>
												<option value="Japão">Japão</option>
												<option value="Jordânia">Jordânia</option>
												<option value="Kuwait">Kuwait</option>
												<option value="Latvia">Latvia</option>
												<option value="Líbano">Líbano</option>
												<option value="Liechtenstein">Liechtenstein</option>
												<option value="Lituânia">Lituânia</option>
												<option value="Luxemburgo">Luxemburgo</option>
												<option value="Macau">Macau</option>
												<option value="Macedônia">Macedônia</option>
												<option value="Madagascar">Madagascar</option>
												<option value="Malásia">Malásia</option>
												<option value="Malaui">Malaui</option>
												<option value="Mali">Mali</option>
												<option value="Malta">Malta</option>
												<option value="Marrocos">Marrocos</option>
												<option value="Martinica">Martinica</option>
												<option value="Mauritânia">Mauritânia</option>
												<option value="Mauritius">Mauritius</option>
												<option value="México">México</option>
												<option value="Moldova">Moldova</option>
												<option value="Mônaco">Mônaco</option>
												<option value="Montserrat">Montserrat</option>
												<option value="Nepal">Nepal</option>
												<option value="Nicarágua">Nicarágua</option>
												<option value="Niger">Niger</option>
												<option value="Nigéria">Nigéria</option>
												<option value="Noruega">Noruega</option>
												<option value="Nova Caledônia">Nova Caledônia</option>
												<option value="Nova Zelândia">Nova Zelândia</option>
												<option value="Omã">Omã</option>
												<option value="Palau">Palau</option>
												<option value="Panamá">Panamá</option>
												<option value="Papua-nova Guiné">Papua-nova Guiné</option>
												<option value="Paquistão">Paquistão</option>
												<option value="Peru">Peru</option>
												<option value="Polinésia Francesa">Polinésia Francesa</option>
												<option value="Polônia">Polônia</option>
												<option value="Porto Rico">Porto Rico</option>
												<option value="Portugal">Portugal</option>
												<option value="Qatar">Qatar</option>
												<option value="Quênia">Quênia</option>
												<option value="Rep. Dominicana">Rep. Dominicana</option>
												<option value="Rep. Tcheca">Rep. Tcheca</option>
												<option value="Reunion">Reunion</option>
												<option value="Romênia">Romênia</option>
												<option value="Ruanda">Ruanda</option>
												<option value="Rússia">Rússia</option>
												<option value="Saipan">Saipan</option>
												<option value="Samoa Americana">Samoa Americana</option>
												<option value="Senegal">Senegal</option>
												<option value="Serra Leone">Serra Leone</option>
												<option value="Seychelles">Seychelles</option>
												<option value="Singapura">Singapura</option>
												<option value="Síria">Síria</option>
												<option value="Sri Lanka">Sri Lanka</option>
												<option value="St. Kitts & Nevis">St. Kitts & Nevis</option>
												<option value="St. Lúcia">St. Lúcia</option>
												<option value="St. Vincent">St. Vincent</option>
												<option value="Sudão">Sudão</option>
												<option value="Suécia">Suécia</option>
												<option value="Suiça">Suiça</option>
												<option value="Suriname">Suriname</option>
												<option value="Tailândia">Tailândia</option>
												<option value="Taiwan">Taiwan</option>
												<option value="Tanzânia">Tanzânia</option>
												<option value="Togo">Togo</option>
												<option value="Trinidad & Tobago">Trinidad & Tobago</option>
												<option value="Tunísia">Tunísia</option>
												<option value="Turquia">Turquia</option>
												<option value="Ucrânia">Ucrânia</option>
												<option value="Uganda">Uganda</option>
												<option value="Uruguai">Uruguai</option>
												<option value="Venezuela">Venezuela</option>
												<option value="Vietnã">Vietnã</option>
												<option value="Zaire">Zaire</option>
												<option value="Zâmbia">Zâmbia</option>
												<option value="Zimbábue">Zimbábue</option>
											</select>
										</div>
									</div>
		            </div>
		            <div class="form-group">
		              <div class="form-row">
		                <div class="col-md-4">
		                	<label for="programa" class="text-primary" >Programa</label>
			                <select class="form-control" required  name="programa">
			                  <option selected disabled  value="">Programa de mobilidade</option>
			                  <option value="Erasmus Estudos - Europa">Erasmus Estudos - Europa</option>
			                  <option value="Erasmus Estágios (MI) e outros">Erasmus Estágios (MI) e outros</option>
			                  <option value="Estágios - Alunos de Doutoramento">Estágios - Alunos de Doutoramento</option>
			                  <option value="Estágios - Investigadores de pós-doc">Estágios - Investigadores de pós-doc</option>
			                  <option value="Programa Almeida Garrett">Programa Almeida Garrett</option>
			                  <option value="ERASMUS / BE MUNDUS / MUNDUS LINDO">ERASMUS / BE MUNDUS / MUNDUS LINDO - países que não Brasil</option>
			                  <option value="MOBILE + outros">MOBILE + outros - Brasil</option>
			                  <option value="1">Outro</option>
			                </select>
		                </div>
		                <div class="col-md-4 outroprograma" style="Display:none" id="#1">
									<label for="outroprograma" class="text-primary">Nome do programa</label>
									<input id="outroprograma" name="outroprograma" type="text" placeholder="nome do programa" class="form-control" >
		                </div>
			          		<div class="col-md-4">
			                	<label for="tipocontrato"  class="text-primary" >Tipo de Contrato</label>
				                <select class="form-control" required name="tipocontrato">
				                  <option selected disabled value="">Tipo de contrato de estudos</option>
				                  <option value="UCs">Unidades Curriculares</option>
				                  <option value="Estágio">Estágio</option>
				                  <option value="UCs + Estágio">UCs + Estágio</option>
				                </select>
			                </div>
									</div>
		            </div>
		            <div class="form-group">
		              <div class="form-row">
		                <div class="col-md-2">
		                	<label for="duracao" class="text-primary">Duração</label>
			                <select class="form-control" required name="duracao">
			                  <option selected disabled value="">Duração da mobilidade</option>
			                  <option value="1º semestre">1º semestre</option>
			                  <option value="2º semestre">2º semestre</option>
			                  <option value="Anual">Anual</option>
			                  <option value="Outro">Outro</option>
			                </select>
		                </div>
		                <div class="col-md-2 outraduracao" style="Display:none">
		                	<label for="inicio" class="text-primary">Data início</label>
		            			<input type="date" name="inicio" class="form-control ">
									</div>
									<div class="col-md-2 outraduracao" style="Display:none">
										<label for="fim" class="text-primary">Data fim</label>
										<input type="date" name="fim" class="form-control ">
									</div>
		              </div>
		            </div>
		            <div class="form-group">
		            	<div class="form-row">
									<div class="col-md-6">
										<label for="obs" class="text-primary">Comentários</label>
										<textarea class="form-control" id="textarea" name="obs" form="mobilidade" placeholder="comentários"></textarea>
									</div>
									<div class="col-md-6">
										<label for="resposta" class="text-primary">Resposta DCOOP</label>
										<textarea class="form-control" id="textarea" name="resposta" form="mobilidade" placeholder="resposta à dcoop"></textarea>
									</div>
		            	</div>
		            </div>
		            <div class="form-group">
		            	<div class="form-row">
									<div class="col-md-5 ">
					          	<div class="accordion" id="syllabus">
												<div id="cartao" class="col-xs-12 card borda">
													<label class="control-label bg-dark ps-2 text-light" >L.EQ - Unidades curriculares que se inscreve</label>
													<div class="card-header" id="headingOne">
														<h5 class="mb-0">
															<button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">1º Ano</button>
															<button class="btn btn-outline-primary btn-sm " type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">2º Ano</button>
															<button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">3º Ano</button>
														</h5>
													</div>
													<!-- 1º Ano -->
													<div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-bs-parent="#syllabus">
														<div class="card-body">
															<table class="table-sm">
																<tr>
																	<td class="bg-dark text-light small" colspan="6">1º ANO</td>
																</tr>
																<tr>
																	<th class="bg-light small" ><b>1º Semestre</b></th>
																	<th class="bg-light small" ><b>2º Semestre</b></th>
																</tr>
																<tr>
																	<td class="align-top">
																		<table class="table-sm small smaller">
																			<?php
																				$row = array();
																				$sth->execute(array(':ano' => 1,':curso' => 'L.EQ', ':regime' => '1S' ));
																				while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																				{
																				echo '<tr >';
																				echo '<td><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																				echo '<td>'. $row['codigo'] . '</td>';
																				echo '<td>'. $row['uc'] . '</td>';
																				echo '<td></td>';
																				echo '<td></td>';
																				echo '<td></td>';
																				echo '</tr>';
																				}
																			?>
																		</table>
																	</td>
																	<td class="align-top">
																		<table class="table-sm small smaller">
																			<?php
																					$row = array();
																					$sth->execute(array(':ano' => 1,':curso' => 'L.EQ', ':regime' => '2S' ));
																					while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																					{
																					echo '<tr >';
																					echo '<td><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																					echo '<td class="align-top">'. $row['codigo'] . '</td>';
																					echo '<td class="align-top">'. $row['uc'] . '</td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '</tr>';
																					}
																				?>
																		</table>
																	</td>
																</tr>
															</table>
														</div>
													</div>
													<!-- 2º Ano -->
													<div id="collapseTwo" class="collapse" aria-labelledby="headingOne" data-bs-parent="#syllabus">
														<div class="card-body ">
															<table class="table-sm ">
																<tr>
																	<td class="bg-dark text-light" colspan="6">2º ANO</td>
																</tr>
																<tr>
																	<th class="bg-light small" ><b>1º Semestre</b></th>
																	<th class="bg-light small" ><b>2º Semestre</b></th>
																</tr>
																<tr>
																	<td class="align-top">
																		<table class="table-sm small smaller">
																			<?php
																				$row = array();
																				$sth->execute(array(':ano' => 2,':curso' => 'L.EQ', ':regime' => '1S' ));
																				while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																				{
																				echo '<tr >';
																				echo '<td><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																				echo '<td class="align-top">'. $row['codigo'] . '</td>';
																				echo '<td class="align-top">'. $row['uc'] . '</td>';
																				echo '<td class="align-top"></td>';
																				echo '<td class="align-top"></td>';
																				echo '<td class="align-top"></td>';
																				echo '</tr>';
																				}
																			?>
																		</table>
																	</td>
																	<td class="align-top">
																		<table class="table-sm small smaller">
																			<?php
																					$row = array();
																					$sth->execute(array(':ano' => 2,':curso' => 'L.EQ', ':regime' => '2S' ));
																					while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																					{
																					echo '<tr >';
																					echo '<td class="align-top"><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																					echo '<td class="align-top">'. $row['codigo'] . '</td>';
																					echo '<td class="align-top">'. $row['uc'] . '</td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '</tr>';
																					}
																				?>
																		</table>
																	</td>
																</tr>
															</table>
														</div>
													</div>
													<!-- 3º Ano -->
													<div id="collapseThree" class="collapse" aria-labelledby="headingOne" data-bs-parent="#syllabus">
														<div class="card-body">
															<table class="table-sm">
																<tr>
																	<td class="bg-dark text-light" colspan="6">3º ANO</td>
																</tr>
																<tr>
																	<th class="bg-light small" ><b>1º Semestre</b></th>
																	<th class="bg-light small" ><b>2º Semestre</b></th>
																</tr>
																<tr>
																	<td class="align-top">
																		<table class="table-sm small smaller">
																			<?php
																				$row = array();
																				$sth->execute(array(':ano' => 3,':curso' => 'L.EQ', ':regime' => '1S' ));
																				while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																				{
																				echo '<tr >';
																				echo '<td class="align-top"><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																				echo '<td class="align-top">'. $row['codigo'] . '</td>';
																				echo '<td class="align-top">'. $row['uc'] . '</td>';
																				echo '<td class="align-top"></td>';
																				echo '<td class="align-top"></td>';
																				echo '<td class="align-top"></td>';
																				echo '</tr>';
																				}
																			?>
																		</table>
																	</td>
																	<td class="align-top">
																		<table class="table-sm small smaller">
																			<?php
																					$row = array();
																					$sth->execute(array(':ano' => 3,':curso' => 'L.EQ', ':regime' => '2S' ));
																					while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																					{
																					echo '<tr >';
																					echo '<td class="align-top"><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																					echo '<td class="align-top">'. $row['codigo'] . '</td>';
																					echo '<td class="align-top">'. $row['uc'] . '</td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '</tr>';
																					}
																				?>
																		</table>
																	</td>
																</tr>
															</table>
														</div>
													</div>
												</div>
											</div>
										</div>
										<div class="col-md-5">
							      	<div class="accordion" id="syllabus2">
												<div class="col-xs-12 card borda">
													<label class="control-label bg-dark ps-2 text-light" >M.EQ - Unidades curriculares que se inscreve</label>
													<div class="card-header" id="headingTwo">
														<h5 class="mb-0">
															<button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOneMEQ" aria-expanded="false" aria-controls="collapseOne">1º Ano</button>
															<button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwoMEQ" aria-expanded="false" aria-controls="collapseTwo">2º Ano</button>
														</h5>
													</div>
													<div id="collapseOneMEQ" class="collapse show" aria-labelledby="headingTwo" data-bs-parent="#syllabus2">
														<div class="card-body ">
															<table class="table-sm">
																<tr>
																	<td class="bg-dark text-light small" colspan="6">1º ANO</td>
																</tr>
																<tr>
																	<th class="bg-light small" ><b>1º Semestre</b></th>
																	<th class="bg-light small" ><b>2º Semestre</b></th>
																</tr>
																<tr>
																	<td class="align-top">
																		<table class="table-sm small ">
																			<?php
																				$row = array();
																				$sth->execute(array(':ano' => 1,':curso' => 'M.EQ', ':regime' => '1S' ));
																				while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																				{
																				echo '<tr >';
																				echo '<td class="align-top"><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																				echo '<td class="align-top">'. $row['codigo'] . '</td>';
																				echo '<td class="align-top">'. $row['uc'] . '</td>';
																				echo '<td class="align-top"></td>';
																				echo '<td class="align-top"></td>';
																				echo '<td class="align-top"></td>';
																				echo '</tr>';
																				}
																			?>
																		</table>
																	</td>
																	<td class="align-top">
																		<table class="table-sm small ">
																			<?php
																					$row = array();
																					$sth->execute(array(':ano' => 1,':curso' => 'M.EQ', ':regime' => '2S' ));
																					while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																					{
																					echo '<tr >';
																					echo '<td class="align-top"><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																					echo '<td class="align-top">'. $row['codigo'] . '</td>';
																					echo '<td class="align-top">'. $row['uc'] . '</td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '</tr>';
																					}
																				?>
																		</table>
																	</td>
																</tr>
															</table>
														</div>
													</div>
													<div id="collapseTwoMEQ" class="collapse" aria-labelledby="headingTwo" data-bs-parent="#syllabus2">
														<div class="card-body">
															<table class="table-sm">
																<tr>
																	<td class="bg-dark text-light small" colspan="6">2º ANO</td>
																</tr>
																<tr>
																	<th class="bg-light small" ><b>1º Semestre</b></th>
																	<th class="bg-light small" ><b>2º Semestre</b></th>
																</tr>
																<tr>
																	<td class="align-top">
																		<table class="table-sm small ">
																			<?php
																				$row = array();
																				$sth->execute(array(':ano' => 2,':curso' => 'M.EQ', ':regime' => '1S' ));
																				while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																				{
																				echo '<tr >';
																				echo '<td class="align-top"><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																				echo '<td class="align-top">'. $row['codigo'] . '</td>';
																				echo '<td class="align-top">'. $row['uc'] . '</td>';
																				echo '<td class="align-top"></td>';
																				echo '<td class="align-top"></td>';
																				echo '<td class="align-top"></td>';
																				echo '</tr>';
																				}
																			?>
																		</table>
																	</td>
																	<td class="align-top">
																		<table class="table-sm small ">
																			<?php
																					$row = array();
																					$sth->execute(array(':ano' => 2,':curso' => 'M.EQ', ':regime' => '2S' ));
																					while ($row = $sth->fetch(PDO::FETCH_ASSOC))
																					{
																					echo '<tr >';
																					echo '<td class="align-top"><input type="checkbox" name="lista_uc[]" id="checkboxes-0"  value="'.$row['codigo'].' '. $row['uc'].'"></td>';
																					echo '<td class="align-top">'. $row['codigo'] . '</td>';
																					echo '<td class="align-top">'. $row['uc'] . '</td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '<td class="align-top"></td>';
																					echo '</tr>';
																					}
																				?>
																		</table>
																	</td>
																</tr>
															</table>
														</div>
													</div>
												</div>
											</div>
										</div>
										<div class="col-md-2 card borda ">
											<label class="control-label bg-dark ps-2 text-light " >UCs selecionadas</label>
											<p id="demo" class="smaller"></p>
										</div>
									</div>
								</div>
										<div class="col-md-2 mx-auto">
			<button type="submit" class="btn btn-primary btn-block col-md-12 "><?= t('SUBMIT') ?></button>
			</div>
			        </form>
			    </div>
				</div>
			</div>

	</div>

<script type="text/javascript">

		$('input:checkbox').on('change', function(){
		    $('input[value="' + this.value + '"]:checkbox').prop('checked', this.checked);
		});

		$("input[type='checkbox']").change(function() {
		    var classes = $("input[type='checkbox']:checked").map(function() {
		        return this.value;
		    }).get();

		var filteredArray = classes.filter(function(item, pos){
		  return classes.indexOf(item)== pos;
		});
		    document.getElementById("demo").innerHTML = filteredArray.join("<br>");

		});

	   $('select[name=programa]').change(function () {
			if ($(this).val() == '1') {
				$('.outroprograma').show();
				document.getElementById("outroprograma").required=true;

			} else {
				$('.outroprograma').hide();
			}
		});

	   $('select[name=duracao]').change(function () {
			if ($(this).val() == 'Outro') {
				$('.outraduracao').show();

			} else {
				$('.outraduracao').hide();
			}
		});

		$("#success-alert").fadeTo(500, 500).slideUp(500, function(){
		    $("#success-alert").slideUp(500);
		    window.location.replace("add.php");
		});
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
