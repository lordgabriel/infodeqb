<?php
session_start();
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'conexao.php';

$msg_sucesso = "";
$msg_erro = "";

// --- PROCESSAMENTO DE INSERÇÕES INDIVIDUAIS (ENTIDADES BASE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    
    // 1. Adicionar País
    if ($_POST['acao'] == 'add_pais') {
        $nome_pais = trim($_POST['nome_pais']);
        if (!empty($nome_pais)) {
            $stmt = $conexao->prepare("INSERT INTO Paises (nome_pais) VALUES (:nome)");
            $stmt->execute(['nome' => $nome_pais]);
            $msg_sucesso = "País '$nome_pais' adicionado com sucesso!";
        }
    }
    
    // 2. Adicionar Instituição
    if ($_POST['acao'] == 'add_instituicao') {
        $id_pais = intval($_POST['id_pais']);
        $nome_inst = trim($_POST['nome_instituicao']);
        if (!empty($nome_inst) && $id_pais > 0) {
            $stmt = $conexao->prepare("INSERT INTO Instituicoes (nome_instituicao, id_pais) VALUES (:nome, :id_pais)");
            $stmt->execute(['nome' => $nome_inst, 'id_pais' => $id_pais]);
            $msg_sucesso = "Instituição '$nome_inst' mapeada com sucesso!";
        }
    }

    // 3. Adicionar Aluno (Opcional se a tabela de Estudantes for restrita)
    if ($_POST['acao'] == 'add_aluno') {
        $num_mec = intval($_POST['numero_mecanografico']);
        if ($num_mec > 0) {
            // Nota: Conforme a estrutura, numero_estudante_autor mapeia o número do aluno diretamente
            $msg_sucesso = "Aluno #$num_mec validado para submissão!";
        }
    }

    // 4. Adicionar Cadeira FEUP
    if ($_POST['acao'] == 'add_cadeira_feup') {
        $nome_cf = trim($_POST['nome_cadeira_feup']);
        $codigo = trim($_POST['codigo']);
        $ects = floatval($_POST['ects_feup']);
        if (!empty($nome_cf) && !empty($codigo)) {
            $stmt = $conexao->prepare("INSERT INTO CadeirasFEUP (nome_cadeira_feup, codigo, ects_feup) VALUES (:nome, :codigo, :ects)");
            $stmt->execute(['nome' => $nome_cf, 'codigo' => $codigo, 'ects' => $ects]);
            $msg_sucesso = "UC da FEUP '$nome_cf' inserida com sucesso!";
        }
    }

    // 5. Adicionar Cadeira Estrangeira
    if ($_POST['acao'] == 'add_cadeira_estrangeira') {
        $id_inst = intval($_POST['id_instituicao_est']);
        $nome_ce = trim($_POST['nome_cadeira_estrangeira']);
        $ects = floatval($_POST['ects_estrangeira']);
        $link = trim($_POST['link_url']);
        
        if (!empty($nome_ce) && $id_inst > 0) {
            // Inserir cadeira estrangeira
            $stmt = $conexao->prepare("INSERT INTO CadeirasEstrangeiras (id_instituicao, nome_cadeira_estrangeira, ects_estrangeira) VALUES (:id_inst, :nome, :ects)");
            $stmt->execute(['id_inst' => $id_inst, 'nome' => $nome_ce, 'ects' => $ects]);
            $id_nova_ce = $conexao->lastInsertId();

            // Se forneceu link, mapear na tabela LinksCadeirasEstrangeiras
            if (!empty($link) && $id_nova_ce) {
                $stmtLink = $conexao->prepare("INSERT INTO LinksCadeirasEstrangeiras (id_cadeira_estrangeira, link_url) VALUES (:id_ce, :url)");
                $stmtLink->execute(['id_ce' => $id_nova_ce, 'url' => $link]);
            }
            $msg_sucesso = "Cadeira Estrangeira '$nome_ce' adicionada!";
        }
    }

    // 6. PROCESSAMENTO MESTRE: ADICIONAR UM PLANO DE ESTUDOS INTEGRADO
    if ($_POST['acao'] == 'salvar_plano_mestre') {
        $id_inst = intval($_POST['plano_id_instituicao']);
        $aluno = intval($_POST['plano_aluno']);
        $ano = trim($_POST['plano_ano_letivo']);
        $id_cf = intval($_POST['plano_id_cadeira_feup']);
        $cadeiras_estrangeiras = isset($_POST['plano_cadeiras_estrangeiras']) ? $_POST['plano_cadeiras_estrangeiras'] : [];

        if ($id_inst > 0 && $aluno > 0 && !empty($ano) && $id_cf > 0 && !empty($cadeiras_estrangeiras)) {
            try {
                $conexao->beginTransaction();

                // Passo 1: Criar o contrato mestre em Equivalencias
                $stmtEq = $conexao->prepare("
                    INSERT INTO Equivalencias (id_instituicao, id_cadeira_feup, numero_estudante_autor, ano_letivo)
                    VALUES (:id_inst, :id_cf, :aluno, :ano)
                ");
                $stmtEq->execute([
                    'id_inst' => $id_inst,
                    'id_cf'   => $id_cf,
                    'aluno'   => $aluno,
                    'ano'     => $ano
                ]);
                $id_nova_equivalencia = $conexao->lastInsertId();

                // Passo 2: Vincular todas as cadeiras estrangeiras selecionadas para este contrato
                $stmtRel = $conexao->prepare("
                    INSERT INTO Equivalencias_Cadeiras_Relacao (id_equivalencia, id_cadeira_estrangeira)
                    VALUES (:id_eq, :id_ce)
                ");
                
                foreach ($cadeiras_estrangeiras as $id_ce) {
                    $stmtRel->execute([
                        'id_eq' => $id_nova_equivalencia,
                        'id_ce' => intval($id_ce)
                    ]);
                }

                $conexao->commit();
                $msg_sucesso = "🎉 O novo Plano de Estudos foi consolidado e guardado com sucesso na Base de Dados!";
            } catch (Exception $e) {
                $conexao->rollBack();
                $msg_erro = "Erro crítico ao persistir o plano: " . $e->getMessage();
            }
        } else {
            $msg_erro = "Por favor, preencha todos os campos do Plano Mestre e selecione pelo menos uma cadeira estrangeira.";
        }
    }
}

// --- RECOLHA DE DADOS ATUALIZADOS PARA OS DROPDOWNS ---
$paises = $conexao->query("SELECT * FROM Paises ORDER BY nome_pais ASC")->fetchAll(PDO::FETCH_ASSOC);
$instituicoes = $conexao->query("SELECT * FROM Instituicoes ORDER BY nome_instituicao ASC")->fetchAll(PDO::FETCH_ASSOC);
$cadeiras_feup = $conexao->query("SELECT * FROM CadeirasFEUP ORDER BY nome_cadeira_feup ASC")->fetchAll(PDO::FETCH_ASSOC);
$cadeiras_est = $conexao->query("SELECT CE.*, I.nome_instituicao FROM CadeirasEstrangeiras CE JOIN Instituicoes I ON CE.id_instituicao = I.id_instituicao ORDER BY I.nome_instituicao ASC, CE.nome_cadeira_estrangeira ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Painel Backoffice - Gestão Segura</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; padding: 30px; color: #333; }
        .wrapper { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .header-area { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #800000; padding-bottom: 15px; margin-bottom: 30px; }
        h2 { color: #800000; margin: 0; }
        h3 { color: #495057; margin-top: 0; border-bottom: 1px solid #dee2e6; padding-bottom: 5px; }
        .grid-entidades { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        .card-add { background: #f8f9fa; border: 1px solid #e3e6f0; padding: 20px; border-radius: 6px; }
        .form-group { margin-bottom: 12px; }
        label { display: block; font-weight: bold; margin-bottom: 4px; font-size: 13px; color: #495057; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .btn-add { background-color: #4e73df; color: white; border: none; padding: 8px 12px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 5px; width: 100%; }
        .btn-add:hover { background-color: #2e59d9; }
        .card-mestre { background: #fff1f1; border: 2px solid #800000; padding: 25px; border-radius: 8px; }
        .btn-mestre { background-color: #800000; color: white; border: none; padding: 14px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; width: 100%; margin-top: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-mestre:hover { background-color: #5a0000; }
        .alert { padding: 12px; border-radius: 4px; font-weight: bold; margin-bottom: 20px; text-align: center; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .checkbox-list { max-height: 150px; overflow-y: auto; border: 1px solid #ced4da; background: white; padding: 8px; border-radius: 4px; }
        .checkbox-item { display: flex; align-items: center; margin-bottom: 4px; font-size: 13px; }
        .checkbox-item input { width: auto; margin-right: 8px; }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="header-area">
        <h2>🛠️ Painel Administrativo de Integridade</h2>
        <div>
            <a href="index.php" style="text-decoration:none; font-weight:bold; color:#4e73df; margin-right:15px;">← Ver Portal</a>
            <a href="logout.php" style="text-decoration:none; font-weight:bold; color:#dc3545;">Terminar Sessão 🚪</a>
        </div>
    </div>

    <?php if(!empty($msg_sucesso)): ?> <div class="alert alert-success"><?= $msg_sucesso ?></div> <?php endif; ?>
    <?php if(!empty($msg_erro)): ?> <div class="alert alert-danger"><?= $msg_erro ?></div> <?php endif; ?>

    <p style="color: #6c757d; font-size: 14px; margin-bottom: 25px;">
        <strong>Instruções:</strong> Use os formulários da <strong>Parte 1</strong> se precisar de introduzir termos ou UCs que ainda não constam no sistema. Depois, vá à <strong>Parte 2</strong> na base da página para consolidar o Plano de Estudos completo de forma limpa e estruturada.
    </p>

    <h3>Parte 1: Adicionar Novas Entidades Base à Base de Dados</h3>
    <div class="grid-entidades">
        
        <div class="card-add">
            <form method="POST" action="admin.php">
                <input type="hidden" name="acao" value="add_pais">
                <label for="nome_pais">Novo País:</label>
                <div class="form-group">
                    <input type="text" name="nome_pais" id="nome_pais" placeholder="Ex: Suécia, Polónia" required>
                </div>
                <button type="submit" class="btn-add">+ Adicionar País</button>
            </form>
        </div>

        <div class="card-add">
            <form method="POST" action="admin.php">
                <input type="hidden" name="acao" value="add_instituicao">
                <div class="form-group">
                    <label for="id_pais">Pertence ao País:</label>
                    <select name="id_pais" id="id_pais" required>
                        <option value="">-- Escolha o País --</option>
                        <?php foreach($paises as $p): ?>
                            <option value="<?= $p['id_pais'] ?>"><?= htmlspecialchars($p['nome_pais']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nome_instituicao">Nome da Universidade Parcerira:</label>
                    <input type="text" name="nome_instituicao" id="nome_instituicao" placeholder="Ex: Universidade de Lund" required>
                </div>
                <button type="submit" class="btn-add">+ Adicionar Instituição</button>
            </form>
        </div>

        <div class="card-add">
            <form method="POST" action="admin.php">
                <input type="hidden" name="acao" value="add_cadeira_feup">
                <div class="form-group">
                    <label for="nome_cadeira_feup">Nome da Unidade Curricular na FEUP:</label>
                    <input type="text" name="nome_cadeira_feup" id="nome_cadeira_feup" placeholder="Ex: Qualidade e Segurança" required>
                </div>
                <div class="form-group">
                    <label for="codigo">Código Único FEUP:</label>
                    <input type="text" name="codigo" id="codigo" placeholder="Ex: M.EQ009" required>
                </div>
                <div class="form-group">
                    <label for="ects_feup">Créditos (ECTS):</label>
                    <input type="number" step="0.5" name="ects_feup" id="ects_feup" placeholder="Ex: 6.00" required>
                </div>
                <button type="submit" class="btn-add">+ Adicionar Cadeira FEUP</button>
            </form>
        </div>

        <div class="card-add">
            <form method="POST" action="admin.php">
                <input type="hidden" name="acao" value="add_cadeira_estrangeira">
                <div class="form-group">
                    <label for="id_instituicao_est">Da Universidade de Destino:</label>
                    <select name="id_instituicao_est" id="id_instituicao_est" required>
                        <option value="">-- Escolha a Universidade --</option>
                        <?php foreach($instituicoes as $i): ?>
                            <option value="<?= $i['id_instituicao'] ?>"><?= htmlspecialchars($i['nome_instituicao']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nome_cadeira_estrangeira">Nome da Disciplina lá fora:</label>
                    <input type="text" name="nome_cadeira_estrangeira" id="nome_cadeira_estrangeira" placeholder="Ex: Quality Management" required>
                </div>
                <div class="form-group">
                    <label for="ects_estrangeira">Créditos Estrangeiros (ECTS):</label>
                    <input type="number" step="0.1" name="ects_estrangeira" id="ects_estrangeira" placeholder="Ex: 7.5" required>
                </div>
                <div class="form-group">
                    <label for="link_url">URL do Programa / Ementa (Opcional):</label>
                    <input type="url" name="link_url" id="link_url" placeholder="https://kurser.lth.se/...">
                </div>
                <button type="submit" class="btn-add">+ Adicionar Cadeira Estrangeira</button>
            </form>
        </div>
    </div>

    <div class="card-mestre">
        <h3>Parte 2: Formatar e Gravar Novo Plano de Estudos</h3>
        <p style="font-size: 13px; color: #5a0000; margin-bottom: 20px;">
            Este bloco consolida e vincula as relações na base de dados, associando as cadeiras selecionadas ao contrato de mobilidade de forma relacional.
        </p>

        <form method="POST" action="admin.php">
            <input type="hidden" name="acao" value="salvar_plano_mestre">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label for="plano_id_instituicao">Universidade de Destino:</label>
                    <select name="plano_id_instituicao" id="plano_id_instituicao" required>
                        <option value="">-- Selecione --</option>
                        <?php foreach($instituicoes as $i): ?>
                            <option value="<?= $i['id_instituicao'] ?>"><?= htmlspecialchars($i['nome_instituicao']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="plano_aluno">Número Mecanográfico do Aluno (Invisível no site):</label>
                    <input type="number" name="plano_aluno" id="plano_aluno" placeholder="Ex: 202306325" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 10px;">
                <div class="form-group">
                    <label for="plano_ano_letivo">Ano Letivo:</label>
                    <select name="plano_ano_letivo" id="plano_ano_letivo" required>
                        <option value="2026/2027">2026/2027</option>
                        <option value="2025/2026">2025/2026</option>
                        <option value="2024/2025">2024/2025</option>
                        <option value="2023/2024">2023/2024</option>
                        <option value="Não especificado">Não especificado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="plano_id_cadeira_feup">Equivalência Concedida na Cadeira FEUP:</label>
                    <select name="plano_id_cadeira_feup" id="plano_id_cadeira_feup" required>
                        <option value="">-- Selecione a UC da FEUP correspondente --</option>
                        <?php foreach($cadeiras_feup as $cf): ?>
                            <option value="<?= $cf['id_cadeira_feup'] ?>"><?= htmlspecialchars($cf['nome_cadeira_feup']) ?> (<?= htmlspecialchars($cf['codigo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label>Selecione as Cadeiras Estrangeiras que compõem esta Equivalência (Pode escolher várias):</label>
                <div class="checkbox-list">
                    <?php foreach($cadeiras_est as $ce): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="plano_cadeiras_estrangeiras[]" value="<?= $ce['id_cadeira_estrangeira'] ?>" id="ce_<?= $ce['id_cadeira_estrangeira'] ?>">
                            <label style="display:inline; font-weight:normal;" for="ce_<?= $ce['id_cadeira_estrangeira'] ?>">
                                [<?= htmlspecialchars($ce['nome_instituicao']) ?>] <strong><?= htmlspecialchars($ce['nome_cadeira_estrangeira']) ?></strong> (<?= htmlspecialchars($ce['ects_estrangeira'] ?? '0') ?> ECTS)
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn-mestre">🔒 Validar e Persistir Plano de Estudos</button>
        </form>
    </div>
</div>

</body>
</html>