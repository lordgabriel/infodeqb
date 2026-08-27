<?php
session_start();
$erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Credenciais administrativas fixas por segurança
    if ($username === 'admin' && $password === 'feup_erasmus_2026') {
        $_SESSION['admin_logado'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $erro = "Utilizador ou palavra-passe incorretos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Login Administrativo - Erasmus FEUP</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 360px; }
        h2 { color: #800000; margin-top: 0; text-align: center; border-bottom: 2px solid #800000; padding-bottom: 10px; }
        label { font-weight: bold; display: block; margin-top: 15px; color: #495057; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .btn-submit { background-color: #800000; color: white; border: none; width: 100%; padding: 12px; margin-top: 25px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; }
        .btn-submit:hover { background-color: #5a0000; }
        .erro { color: #dc3545; font-weight: bold; text-align: center; margin-top: 15px; }
        .voltar { display: block; text-align: center; margin-top: 15px; color: #6c757d; text-decoration: none; }
    </style>
</head>
<body>
<div class="login-card">
    <h2>🔐 Painel de Gestão</h2>
    <?php if(!empty($erro)): ?> <div class="erro"><?= $erro ?></div> <?php endif; ?>
    <form method="POST" action="login.php">
        <label for="username">Utilizador:</label>
        <input type="text" name="username" id="username" required>
        
        <label for="password">Palavra-passe:</label>
        <input type="password" name="password" id="password" required>
        
        <button type="submit" class="btn-submit">Entrar</button>
    </form>
    <a class="voltar" href="index.php">← Voltar ao Portal</a>
</div>
</body>
</html>