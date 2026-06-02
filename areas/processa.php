<?php
require $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php';
include ROOT_DIR.'/infodeqb/session.php';

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Location: '.HTTP_DIR.'/infodeqb/error.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    try {
        // Criar conexão PDO
        $pdo = Database::connect();
        
        // Receber dados do formulário
        $codigo_inquirido = filter_input(INPUT_POST, 'codigo_inquirido', FILTER_SANITIZE_STRING);
        $valores = $_POST['valores'];
        
        // Excluir valores anteriores
        $deleteStmt = $pdo->prepare("DELETE FROM infodeqb_respostas WHERE codigo_inquirido = :codigo_inquirido");
        $deleteStmt->execute([':codigo_inquirido' => $codigo_inquirido]);
        
        // Preparar a declaração SQL para inserção
        $stmt = $pdo->prepare("INSERT INTO infodeqb_respostas (codigo_inquirido, area_id, subarea_id, valor) VALUES (:codigo_inquirido, :area_id, :subarea_id, :valor)");
        
        foreach ($valores as $subarea_id => $areas) {
            foreach ($areas as $area_id => $valor) {
                $valor = filter_var($valor, FILTER_SANITIZE_STRING);
                if (!empty($valor)) {
                    // Executar a declaração SQL
                    $stmt->execute([
                        ':codigo_inquirido' => $codigo_inquirido,
                        ':area_id' => $area_id,
                        ':subarea_id' => $subarea_id,
                        ':valor' => $valor
                    ]);
                }
            }
        }
        
        echo "Dados inseridos com sucesso!";
    } catch (PDOException $e) {
        error_log("Erro: " . $e->getMessage(), 3, '/var/log/php_errors.log');
        echo "Ocorreu um erro ao inserir os dados.";
    } finally {
        // Fechar conexão
        Database::disconnect();
    }
} else {
    // Exibir formulário de confirmação
    $codigo_inquirido = filter_input(INPUT_POST, 'codigo_inquirido', FILTER_SANITIZE_STRING);
    $valores = $_POST['valores'];
    ?>
    <form method="post" action="">
        <input type="hidden" name="codigo_inquirido" value="<?php echo htmlspecialchars($codigo_inquirido); ?>">
        <?php foreach ($valores as $subarea_id => $areas): ?>
            <?php foreach ($areas as $area_id => $valor): ?>
                <input type="hidden" name="valores[<?php echo $subarea_id; ?>][<?php echo $area_id; ?>]" value="<?php echo htmlspecialchars($valor); ?>">
            <?php endforeach; ?>
        <?php endforeach; ?>
        <p>Os valores anteriores serão apagados e novos valores serão inseridos. Deseja continuar?</p>
        <button type="submit" name="confirm" value="yes">Sim</button>
        <button type="submit" name="confirm" value="no">Não</button>
    </form>
    <?php
}
?>