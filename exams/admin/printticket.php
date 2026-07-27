<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
require ROOT_DIR . '/infodeqb/vendor/autoload.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$isExamAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsExam);
if (!$isExamAdmin) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

$ticket = $_SESSION['ticket'] ?? '';
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sth = $pdo->prepare('SELECT * FROM infodeqb_exam_archive WHERE request_id=?');
$sth->execute([$ticket]);

$docente = '';
$id = '';
$rows = '';
while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    $docente = $row['docente'];
    $id = $row['request_id'];
    $rows .= '<tr>'
        . '<td style="padding:5px">' . htmlspecialchars($row['curso']) . '</td>'
        . '<td style="padding:5px">' . htmlspecialchars($row['ano_letivo']) . '</td>'
        . '<td style="padding:5px">' . htmlspecialchars($row['unidade_curricular']) . '</td>'
        . '<td style="padding:5px">' . htmlspecialchars($row['tipologia']) . '</td>'
        . '</tr>';
}

$logoPath = ROOT_DIR . '/infodeqb/img/logo_deq_black.png';

$html = '<html><head><style>
    body  { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #000; padding: 5px; }
    th    { background-color: #ccc; }
    .stamp-box td { border: none; }
</style></head><body>
    <div style="text-align:right"><img src="' . htmlspecialchars($logoPath) . '" width="180"></div>
    <h2>Auto de arquivo de elementos de avaliação</h2>
    <p style="font-size:11pt"><strong>Responsável:</strong> ' . htmlspecialchars($docente) . '</p>
    <p style="font-size:11pt"><strong>Id:</strong> ' . htmlspecialchars($id) . '</p>
    <table>
        <tr><th>CURSO</th><th>ANO LETIVO</th><th>UNIDADES CURRICULARES</th><th>Tipologia</th></tr>
        ' . $rows . '
    </table>
    <br>
    <table class="stamp-box" style="width:300px">
        <tr><td style="height:70px;vertical-align:bottom"><span style="color:gray;font-size:7pt">Carimbo e assinatura</span></td></tr>
        <tr><td>RECEBIDO EM _______ / _______ / _______</td></tr>
    </table>
    <div style="position:fixed;bottom:0;left:0;font-size:7pt;color:#888">' . date('d/m/Y') . '</div>
</body></html>';

$dompdf = new \Dompdf\Dompdf();
$dompdf->getOptions()->setChroot(ROOT_DIR . '/infodeqb');
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream(($id !== '' ? $id : 'ticket') . '.pdf', ['Attachment' => false]);
