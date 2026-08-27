<?php
// Detalhes da ligação à base de dados na nuvem Azure
$servidor   = "erasmus.database.windows.net"; // Nome do servidor gerado pela Azure
$base_dados = "erasmus_db";                 // Nome da tua base de dados
$utilizador = "pedroluca";                  // O teu administrador configurado
$password   = "GrVA2Pt8prckJW";   // Substitui pela password real que definiste no portal

try {
    // Na Azure (Linux), usamos o driver DBLIB ou SQLSRV. O formato padrão PDO é este:
    $conexao = new PDO("sqlsrv:server=$servidor;Database=$base_dados", $utilizador, $password);
    
    // Configura o PDO para lançar exceções em caso de erro de SQL
    $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Garante que os caracteres especiais (acentos portugueses) funcionam bem
    $conexao->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8);

} catch (PDOException $e) {
    // Se a ligação falhar, mostra o erro no ecrã (ajuda a diagnosticar na nuvem)
    die("Erro crítico na ligação à base de dados da Azure: " . $e->getMessage());
}
?>