<?php 
require_once 'conexao.php'; 

// 1. Procura todas as Instituições e Países para preencher o menu Dropdown
$queryInst = $conexao->query("
    SELECT I.id_instituicao, I.nome_instituicao, P.nome_pais
    FROM Instituicoes I
    JOIN Paises P ON I.id_pais = P.id_pais
    ORDER BY P.nome_pais ASC, I.nome_instituicao ASC
");
$instituicoes = $queryInst->fetchAll(PDO::FETCH_ASSOC);

$instituicoes_por_pais = [];
foreach ($instituicoes as $inst) {
    $instituicoes_por_pais[$inst['nome_pais']][] = $inst;
}

$id_selecionado = isset($_GET['instituicao']) ? intval($_GET['instituicao']) : null;

$cadeiras_disponiveis = [];
$planos_estudos = [];

if ($id_selecionado) {
    // SECÇÃO A: Busca as UCs da FEUP disponíveis (Visão Individual)
    $stmt = $conexao->prepare("
        SELECT DISTINCT CF.id_cadeira_feup, CF.nome_cadeira_feup, CF.codigo, CF.ects_feup
        FROM Equivalencias E
        JOIN CadeirasFEUP CF ON E.id_cadeira_feup = CF.id_cadeira_feup
        WHERE E.id_instituicao = :id_inst
        ORDER BY CF.nome_cadeira_feup ASC
    ");
    $stmt->execute(['id_inst' => $id_selecionado]);
    $cadeiras_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // SECÇÃO B: Busca as relações de equivalência para montar os planos
    $stmtPlanos = $conexao->prepare("
        SELECT DISTINCT
            E.numero_estudante_autor,
            E.ano_letivo,
            CF.nome_cadeira_feup,
            CF.codigo AS codigo_feup,
            CE.nome_cadeira_estrangeira,
            CE.ects_estrangeira,
            L.link_url
        FROM Equivalencias E
        JOIN CadeirasFEUP CF ON E.id_cadeira_feup = CF.id_cadeira_feup
        JOIN Equivalencias_Cadeiras_Relacao ECR ON E.id_equivalencia = ECR.id_equivalencia
        JOIN CadeirasEstrangeiras CE ON ECR.id_cadeira_estrangeira = CE.id_cadeira_estrangeira
        LEFT JOIN LinksCadeirasEstrangeiras L ON CE.id_cadeira_estrangeira = L.id_cadeira_estrangeira
        WHERE E.id_instituicao = :id_inst
        ORDER BY E.ano_letivo DESC, E.numero_estudante_autor ASC, CE.nome_cadeira_estrangeira ASC
    ");
    $stmtPlanos->execute(['id_inst' => $id_selecionado]);
    $lista_linhas_planos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);

    // Agrupamento lógico refinado por Nomes das Cadeiras
    foreach ($lista_linhas_planos as $linha) {
        $chave_plano = $linha['numero_estudante_autor'] . '_' . $linha['ano_letivo'];
        
        if (!isset($planos_estudos[$chave_plano])) {
            $planos_estudos[$chave_plano] = [
                'ano_letivo' => $linha['ano_letivo'] ?? 'Não especificado',
                'equivalencias' => [] // Aqui guardamos as linhas finais únicas
            ];
        }
        
        // Criamos uma assinatura textual baseada estritamente nos nomes e códigos
        $nome_est  = trim($linha['nome_cadeira_estrangeira']);
        $nome_feup = trim($linha['nome_cadeira_feup']);
        $assinatura_texto = $nome_est . '|||' . $nome_feup;
        
        // Se esta relação exata de disciplinas já existir neste plano, não duplicamos a linha
        if (!isset($planos_estudos[$chave_plano]['equivalencias'][$assinatura_texto])) {
            
            // Tratamento preventivo de créditos ECTS nulos ou vazios
            $ects_exibir = '-';
            if (!empty($linha['ects_estrangeira']) && floatval($linha['ects_estrangeira']) > 0) {
                $ects_exibir = htmlspecialchars(floatval($linha['ects_estrangeira']));
            }

            $planos_estudos[$chave_plano]['equivalencias'][$assinatura_texto] = [
                'cadeira_est'  => $nome_est,
                'ects_est'     => $ects_exibir,
                'cadeira_feup' => $nome_feup,
                'codigo_feup'  => $linha['codigo_feup'] ?? '-',
                'links'        => [] // Coleção de links para evitar multiplicação de linhas
            ];
        }

        // Se a linha atual trouxer um ECTS válido e a guardada estiver vazia, atualiza
        if ($linha['ects_estrangeira'] && $planos_estudos[$chave_plano]['equivalencias'][$assinatura_texto]['ects_est'] == '-') {
            $planos_estudos[$chave_plano]['equivalencias'][$assinatura_texto]['ects_est'] = htmlspecialchars(floatval($linha['ects_estrangeira']));
        }

        // Adiciona o link à lista de links desta cadeira se não for nulo e não estiver repetido
        if (!empty($linha['link_url']) && !in_array($linha['link_url'], $planos_estudos[$chave_plano]['equivalencias'][$assinatura_texto]['links'])) {
            $planos_estudos[$chave_plano]['equivalencias'][$assinatura_texto]['links'][] = $linha['link_url'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Portal de Equivalências Erasmus - FEUP</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 40px; background-color: #f8f9fa; color: #333; }
        .container { max-width: 1000px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 0 auto; }
        h2 { color: #800000; border-bottom: 2px solid #800000; padding-bottom: 10px; }
        h3 { color: #495057; margin-top: 40px; border-bottom: 1px solid #dee2e6; padding-bottom: 8px; }
        select { width: 100%; padding: 12px; font-size: 16px; border-radius: 4px; border: 1px solid #ccc; margin-top: 10px; background-color: #fff; }
        optgroup { font-weight: bold; color: #800000; background-color: #f1f1f1; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 25px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background-color: #800000; color: white; }
        .btn-ver { background-color: #0056b3; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: bold; }
        .plano-bloco { border: 1px solid #ced4da; border-radius: 6px; padding: 20px; margin-bottom: 25px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .plano-header { font-weight: bold; font-size: 16px; color: #800000; margin-bottom: 15px; display: flex; justify-content: space-between; background: #f1f3f5; padding: 8px 15px; margin: -20px -20px 15px -20px; border-radius: 6px 6px 0 0; }
        .badge-ano { background-color: #6c757d; color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px; }
    </style>
</head>
<body>
<div style="position: absolute; top: 15px; right: 40px; font-size: 14px;">
    <?php
    session_start();
    if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true): ?>
        <span style="color: #28a745; font-weight: bold;">👋 Olá, Admin</span> | 
        <a href="admin.php" style="color: #0056b3; font-weight: bold; text-decoration: none;">Painel Admin</a> | 
        <a href="logout.php" style="color: #dc3545; text-decoration: none;">Sair</a>
    <?php else: ?>
        <a href="login.php" style="background-color: #800000; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">🔐 Acesso Admin</a>
    <?php endif; ?>
</div>
<div class="container">
    <h2>Consulta de Equivalências Erasmus</h2>
    
    <form method="GET" action="index.php">
        <label style="font-weight: bold;" for="instituicao">Selecione a Universidade de Destino:</label>
        <select name="instituicao" id="instituicao" onchange="this.form.submit()">
            <option value="">-- Escolha uma Instituição --</option>
            <?php foreach ($instituicoes_por_pais as $pais => $lista_inst): ?>
                <optgroup label="🌍 <?= htmlspecialchars($pais) ?>">
                    <?php foreach ($lista_inst as $inst): ?>
                        <option value="<?= $inst['id_instituicao'] ?>" <?= $id_selecionado == $inst['id_instituicao'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inst['nome_instituicao']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($id_selecionado): ?>
        
        <h3>Secção A: Pesquisa Individual por Cadeira da FEUP</h3>
        <?php if (count($cadeiras_disponiveis) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cadeira da FEUP</th>
                        <th>ECTS</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cadeiras_disponiveis as $cad): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($cad['codigo'] ?? '-') ?></b></td>
                            <td><?= htmlspecialchars($cad['nome_cadeira_feup']) ?></td>
                            <td><?= htmlspecialchars($cad['ects_feup'] ?? '-') ?></td>
                            <td>
                                <a class="btn-ver" href="detalhes.php?id_cadeira_feup=<?= $cad['id_cadeira_feup'] ?>&id_inst=<?= $id_selecionado ?>">Ver Equivalência Estrangeira</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #6c757d; margin-top: 15px; font-style: italic;">Nenhuma cadeira individual registada.</p>
        <?php endif; ?>

        <h3>Secção B: Planos de Estudo Completos Aplicados nesta Instituição</h3>
        <p style="color: #6c757d; font-size: 14px; margin-bottom: 20px;">
            Abaixo encontram-se os conjuntos completos de disciplinas que antigos estudantes realizaram em simultâneo nesta universidade.
        </p>

        <?php if (count($planos_estudos) > 0): ?>
            <?php 
            $num_plano = 1;
            foreach ($planos_estudos as $chave => $dados_plano): 
            ?>
                <div class="plano-bloco">
                    <div class="plano-header">
                        <span>🎓 PLANO DE ESTUDOS #<?= $num_plano ?></span>
                        <span class="badge-ano">Ano Letivo: <?= htmlspecialchars($dados_plano['ano_letivo']) ?></span>
                    </div>
                    
                    <table style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="background-color: #495057; width: 35%;">Cadeira no Estrangeiro</th>
                                <th style="background-color: #495057; width: 15%;">Créditos</th>
                                <th style="background-color: #495057; width: 40%;">Equivalência Correspondente na FEUP</th>
                                <th style="background-color: #495057; width: 10%;">Programa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dados_plano['equivalencias'] as $eq): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($eq['cadeira_est']) ?></strong></td>
                                    <td><?= $eq['ects_est'] !== '-' ? $eq['ects_est'] . ' ECTS' : '- ECTS' ?></td>
                                    <td><span style="color: #800000; font-weight: 500;"><?= htmlspecialchars($eq['cadeira_feup']) ?></span> <small style="color:#6c757d;">(<?= htmlspecialchars($eq['codigo_feup']) ?>)</small></td>
                                    <td>
                                        <?php if (!empty($eq['links'])): ?>
                                            <?php foreach ($eq['links'] as $index => $link_url): ?>
                                                <a href="<?= htmlspecialchars($link_url) ?>" target="_blank" style="color: #28a745; font-weight: bold; text-decoration: none;">Link <?= count($eq['links']) > 1 ? ($index + 1) : '' ?> ↗</a><br>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #bbb; font-style: italic;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php 
                $num_plano++;
            endforeach; 
            ?>
        <?php else: ?>
            <p style="color: #6c757d; margin-top: 15px; font-style: italic;">Nenhum plano de estudos estruturado encontrado.</p>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>