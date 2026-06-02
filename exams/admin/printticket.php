<?php
// require composer autoload
require_once '../../../../deqwww.php';
require ROOT_DIR . '/infodeqb/vendor/autoload.php';
include ROOT_DIR . '/infodeqb/session.php';

if (! ($_SESSION['user'] &&
        ($_SESSION['user'] == 'up356946@up.pt' ||
        $_SESSION['user'] == 'up448105@up.pt' ||
        $_SESSION['user'] == 'up424064@up.pt'))) {

    header('location:' . HTTP_DIR . '/infodeqb/denied.php');
}
/*
 * if(!isset($_SERVER['HTTP_REFERER'])){
 * // redirect them to your desired location
 * header('location: index.php');
 * exit;
 * }
 */

$mpdf = new mPDF();
$ticket = $_SESSION['ticket'];
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sth = $pdo->prepare('SELECT * from infodeqb_exam_archive where request_id=?');
$sth->execute(array(
        $ticket
));

// Define the Header/Footer before writing anything so they appear on the first
// page
$mpdf->SetHTMLHeader(
        '<h1><div style="text-align: right;"><img src="' . HTTP_DIR .
        '/infodeqb/img/logo_deq_black.png" width="40%" ></div></h1><br><br><br>');

$mpdf->SetHTMLFooter(
        '
<table width="100%">
    <tr>
        <td width="33%">{DATE d/M/Y}</td>
        <td width="33%" align="center"></td>
        <td width="33%" style="text-align: right;">{PAGENO}/{nbpg}</td>
    </tr>
</table>');

echo '<tr>
  <th class="col-sm-2">' . $id1 .
        '</th>
  <th class="col-sm-2">Ano Letivo</th>
  <th class="col-sm-2">Unidade curricular</th>
  <th class="col-sm-2" >Tipologia</th>
  <th class="col-sm-1" >Ações</th>
</tr>
</thead>
<tbody>';

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    $docente = $row['docente'];
    $id = $row['request_id'];
    $data .= '<tr>' . '<td style="padding: 5px">' . $row['curso'] . '</td>' .
            '<td style="padding: 5px">' . $row['ano_letivo'] . '</td>' .
            '<td style="padding: 5px">' . $row['unidade_curricular'] . '</td>' .
            '<td style="padding: 5px">' . $row['tipologia'] . '</td>
		</tr>';
}

// Take PDF contents in a variable
$pdfcontent = '<body style="font-family: verdana; font-size: 9pt;"><h2><br><br>Auto de arquivo de elementos de avaliação</h2>
		<p style="font-size: 11 pt"><strong>Responsável:</strong> ' . $docente .
        '</p>
		<p style="font-size: 11 pt"><strong>Id:</strong> ' . $id .
        '</p>
		<table Autosize="1" border="1" style="border-collapse:collapse">
		<tr style="background-color: DarkGray">
		<th style="padding: 5px"><strong>CURSO</strong></td>
		<th style="padding: 5px"><strong>ANO LETIVO</strong></td>
		<th style="padding: 5px"><strong>UNIDADES CURRICULARES</strong></td>
		<th style="padding: 5px"><strong>Tipologia</strong></td>
		</tr>
		' . $data .
        '
		</table>
	<BR><table border="1" wIDTH="300" style="border-collapse:collapse">
    <tr>
        <td style="BORDER: 1;vertical-align: bottom;HEIGHT:100 PX"><span style="color:gray; font-size: 7pt;">Carimbo e asinatura</span></td>
    </tr>
        <tr>
        <td style="BORDER: 0;"><br><br>RECEBIDO EM _______ / _______ / _______</td>
    </tr>
</table></body>';

$mpdf->WriteHTML($pdfcontent);

$mpdf->SetDisplayMode('fullpage');
$mpdf->list_indent_first_level = 0;

// LOAD a stylesheet
// $stylesheet = file_get_contents('/css/pdf.css');
// $mpdf->WriteHTML($stylesheet,1); // The parameter 1 tells that this is
// css/style only and no body/html/text
// Saves file on the server as 'filename.pdf'
// $mpdf->Output($id.'.pdf', 'I');
// output in browser
$mpdf->Output($id . '.pdf', 'I');
// unset ($_SESSION['ticket']);

?>

