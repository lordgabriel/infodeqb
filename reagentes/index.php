<?php
require $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'].'/infodeqb/session.php';

$pageTitle = 'Reagentes';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

          <!-- Breadcrumbs-->
          <ol class="breadcrumb">
				<li class="breadcrumb-item "><h5> Inventário de Reagentes nos Laboratórios de Ensino e Armazém</h5></li>

          </ol>

     <div class= col-sm-6> <p>A lista de reagentes existentes nos laboratórios de ensino do DEQ pode ser descarregada no final desta página. Os docentes ou investigadores que necessitem podem solicitar, junto dos técnicos dos laboratórios, o empréstimo de reagentes que constem nesta lista. Esse empréstimo será efetuado sempre que for possível.</p></div>




          <!-- DataTables Example -->
          <div class= col-sm-6><div class="card mb-3 ">

            <div class="card-body ">


              <div class="table-responsive">
                <table class="table table-sm table-striped"  width="100%" cellspacing="0">

					<tbody>
								<tr><th>Ficheiro:</th><td><?php echo basename('files/DEQ_Inventory_Sep_10_2024.xlsx')?></td></tr>
							<tr><th>Descrição:</th><td>Lista de reagentes existentes nos laboratórios de ensino do DEQ</td></tr>
							<tr><th>Periocidade de Atualização:</th><td>No mínimo anualmente</td></tr>
							<tr><th>Tamanho:</th><td><?php echo filesize('files/DEQ_Inventory_Sep_10_2024.xlsx') . ' bytes'?></td></tr>
							<tr><th>Última modificação:</th><td><?php echo date("d F Y ", filemtime('files/DEQ_Inventory_Sep_10_2024.xlsx'))?></td></tr>
							<tfooter><tr  class=""><td class='dark text-center ' colspan=2 ><a class="btn btn-info mt-2 btn-sm" href="files/DEQ_Inventory_Sep_10_2024.xlsx"><span class="text-white">Transferir ficheiro</span></a>
							</td></tr></tfooter>
					</tbody>
              </table>

              </div>
            </div>

          </div>

        </div>

<script>
  $(document).ready(function() {
    $('#dataTable').DataTable();
} );
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
