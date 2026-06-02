<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsMobile))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
}


  $pdo = Database::connect();
    $sth = $pdo->prepare('SELECT nome, id, universidade, pais, programa, anoletivo, tipocontrato, duracao, inicio, fim, obs, ucs from infodeqb_registo_mobilidade');
    $sth->execute();
    $dados= $sth->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Editar Pedido — Mobilidade';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

            <!-- Breadcrumbs-->
            <ol class="breadcrumb">
              <li class="breadcrumb-item "><h5> Eng. Química - Mobilidade IN</h5></li>
            </ol>
          <div class="card mb-3">
            <div class="card-header">
              <i class="fas fa-table"></i>
              Estudantes de mobilidade registados
              <a class ="text-white" href="index.php"><button type="button" class="btn btn-secondary float-end col-md-1 "><?= t('NAV_HOME') ?></button></a>
              <a class ="text-white" href="add.php"><button type="button" class="btn btn-secondary float-end col-md-1 me-2"><?= t('MOBILE_NEW_RECORD') ?></button></a>
            </div>




          <!-- DataTables Example -->
          <div class="card mb-3">

            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                  <thead>
                    <tr class="bg-info text-white">
                      <th ></th>
                      <th >Nome </th>
                      <th>Universidade</th>
                      <th>País</th>
                      <th >Programa</th>
                      <th>Tipo</th>
                      <th>Ano Letivo</th>
                      <th>Duração</th>
                       <!-- <th width=5%>Início</th>
                      <th width=5%>Fim</th>-->
                      <!--  <th >UCs</th>-->
                      <th >Obs.</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $row = array();
                      if ($sth->execute()){
                        while ($row = $sth->fetch(PDO::FETCH_ASSOC)){
                          echo '<td class='."small".'><a class="btn btn-info  btn-sm" href="detail.php?id='.$row['id'].'"><i class="far fa-edit"></i></a> </td>';
                          echo '<td class='."small".'>'. $row['nome'] . '</td>';
                          echo '<td class='."small".'>'. $row['universidade'] . '</td>';
                          echo '<td class='."small".'>'. $row['pais'] . '</td>';
                          echo '<td class='."small".'>'. $row['programa'] . '</td>';
                          echo '<td class='."small".'>'. $row['tipocontrato'] . '</td>';
                          echo '<td class='."small".'>'. $row['anoletivo'] . '</td>';
                          echo '<td class='."small".'>'. $row['duracao'] . '</td>';
                         // echo '<td class='."small".'>'. $row['inicio'] . '</td>';
                         //echo '<td class='."small".'>'. $row['fim'] . '</td>';
                          //echo '<td class='."small; ".'>'. $row['ucs'] . '</td>';
                          echo '<td class='."small".'>'. $row['obs'] . '</td>';
                          echo '</tr>';
                        }
                      }
                      Database::disconnect();
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer small text-muted"></div>
          </div>

<script>
  $(document).ready(function() {
    $('#dataTable').DataTable();
} );
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
