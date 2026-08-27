<?php
require_once 'conexao.php';

$id_cadeira_feup = isset($_GET['id_cadeira_feup']) ? intval($_GET['id_cadeira_feup']) : null;
$id_inst = isset($_GET['id_inst']) ? intval($_GET['id_inst']) : null;

if (!$id_cadeira_feup || !$id_inst) {
    header("Location: index.php");
    exit;
}

// 1. Nome da Cadeira da FEUP
$stmtCad = $conexao->prepare("SELECT nome_cadeira_feup FROM CadeirasFEUP WHERE id_cadeira_feup = :id");
$stmtCad->execute(['id' => $id_cadeira_feup]);
$infoCad = $stmtCad->fetch(PDO::FETCH_ASSOC);

// 2. Coleta a árvore de dados baseada na NOVA relação pura por Equivalência
$stmtCadeiras = $conexao->prepare("
    SELECT 
        E.id_equivalencia,
        CE.id_cadeira_estrangeira,
        CE.nome_cadeira_estrangeira, 
        CE.ects_estrangeira, 
        L.link_url
    FROM Equivalencias E
    JOIN Equivalencias_Cadeiras_Relacao ECR ON E.id_equivalencia = ECR.id_equivalencia
    JOIN CadeirasEstrangeiras CE ON ECR.id_cadeira_estrangeira = CE.id_cadeira_estrangeira
    LEFT JOIN LinksCadeirasEstrangeiras L ON L.id_cadeira_estrangeira = CE.id_cadeira_estrangeira
    WHERE E.id_cadeira_feup = :id_cad AND E.id_instituicao = :id_inst
    ORDER BY E.id_equivalencia DESC, CE.nome_cadeira_estrangeira ASC
");
$stmtCadeiras->execute(['id_cad' => $id_cadeira_feup, 'id_inst' => $id_inst]);
$lista_bruta = $stmtCadeiras->fetchAll(PDO::FETCH_ASSOC);

// 3. Agrupamento por Equivalência e Remoção de Duplicados Estruturais
$equivalencias_temporarias = [];
foreach ($lista_bruta as $linha) {
    $id_eq = $linha['id_equivalencia'];
    $id_ce = $linha['id_cadeira_estrangeira'];
    
    if (!isset($equivalencias_temporarias[$id_eq])) {
        $equivalencias_temporarias[$id_eq] = [];
    }
    if (!isset($equivalencias_temporarias[$id_eq][$id_ce])) {
        $equivalencias_temporarias[$id_eq][$id_ce] = [
            'nome'  => $linha['nome_cadeira_estrangeira'],
            'ects'  => $linha['ects_estrangeira'],
            'links' => []
        ];
    }
    if (!empty($linha['link_url']) && !in_array($linha['link_url'], $equivalencias_temporarias[$id_eq][$id_ce]['links'])) {
        $equivalencias_temporarias[$id_eq][$id_ce]['links'][] = $linha['link_url'];
    }
}

$equivalencias_agrupadas = [];
$assinaturas_registadas = [];
foreach ($equivalencias_temporarias as $id_eq => $cadeiras) {
    $assinatura = "";
    foreach ($cadeiras as $id_ce => $dados) {
        $assinatura .= $id_ce . "_";
    }
    if (!in_array($assinatura, $assinaturas_registadas)) {
        $assinaturas_registadas[] = $assinatura;
        $equivalencias_agrupadas[$id_eq] = $cadeiras; 
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Detalhes da Equivalência - FEUP</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 40px; background-color: #f8f9fa; color: #333; }
        .container { max-width: 800px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 0 auto; }
        .uc-destaque { background-color: #e9ecef; padding: 15px; border-radius: 4px; border-left: 5px solid #800000; margin-bottom: 25px; }
        .bloco-opcao { border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin-bottom: 30px; }
        .opcao-titulo { background: #495057; color: white; margin: -20px -20px 15px -20px; padding: 10px 20px; font-weight: bold; font-size: 15px; display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background-color: #f1f3f5; }
        .divisor-ou { text-align: center; margin: 20px 0; position: relative; }
        .divisor-ou::before { content: ""; display: block; border-top: 1px dashed #ced4da; position: absolute; top: 50%; width: 100%; }
        .divisor-ou span { background: #f8f9fa; padding: 5px 15px; font-weight: bold; color: #800000; position: relative; z-index: 2; border: 1px solid #ced4da; border-radius: 20px; }
        .btn-voltar { display: inline-block; margin-top: 10px; color: #0056b3; font-weight: bold; text-decoration: none; }
    </style>
</head>
<body>

<div class="container">
    <h2>Composição da Equivalência</h2>
    
    <div class="uc-destaque">
        Unidade Curricular da FEUP: <br>
        <strong><?= htmlspecialchars($infoCad['nome_cadeira_feup'] ?? 'Não encontrada') ?></strong>
    </div>

    <p style="color: #495057; margin-bottom: 20px;">
        Foram encontrados <strong><?= count($equivalencias_agrupadas) ?> caminhos alternativos</strong> estruturalmente diferentes. Pode optar por cumprir <u>qualquer um</u> dos conjuntos:
    </p>

    <?php 
    $contador = 1;
    foreach ($equivalencias_agrupadas as $id_eq => $cadeiras): 
    ?>
        <?php if ($contador > 1): ?>
            <div class="divisor-ou"><span>OU</span></div>
        <?php endif; ?>

        <div class="bloco-opcao">
            <div class="opcao-titulo">
                <span>📋 OPÇÃO ALTERNATIVA <?= $contador ?></span>
                <span style="font-weight: normal; font-size: 12px; opacity: 0.8;">Ref: Histórico #<?= $id_eq ?></span>
            </div>
            
            <table style="width:100%">
                <thead>
                    <tr>
                        <th>Cadeira no Estrangeiro</th>
                        <th>Créditos</th>
                        <th>Programa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cadeiras as $id_ce => $dados_ce): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dados_ce['nome']) ?></strong></td>
                            <td><?= htmlspecialchars($dados_ce['ects']) ?> ECTS</td>
                            <td>
                                <?php if (!empty($dados_ce['links'])): ?>
                                    <?php foreach ($dados_ce['links'] as $idx => $url): ?>
                                        <a style="color:#28a745; font-weight:bold; text-decoration:none;" href="<?= htmlspecialchars($url) ?>" target="_blank">
                                            Ver Programa <?= (count($dados_ce['links']) > 1 ? ($idx + 1) : '') ?> ↗
                                        </a><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Não disponível</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php 
        $contador++;
    endforeach; 
    ?>

    <hr style="border: 0; border-top: 1px solid #dee2e6; margin-top: 30px; margin-bottom: 20px;">
    <a class="btn-voltar" href="index.php?instituicao=<?= $id_inst ?>">← Voltar para a Instituição</a>
</div>

</body>
</html>