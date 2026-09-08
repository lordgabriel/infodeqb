<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';

if (!in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
    http_response_code(403); exit('Acesso não permitido.');
}
if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    header('Location: ' . HTTP_DIR . '/infodeqb/'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    if (!$code || !$name) {
        $error = 'Preencha o código UP e o nome.';
    } else {
        // Normalizar: aceita "up620893", "up620893@up.pt", "620893", etc.
        $codeNum = strtolower(trim($code));
        $codeNum = preg_replace('/@.*$/', '', $codeNum);  // remove domínio se presente
        $codeNum = preg_replace('/^up/i', '', $codeNum);  // remove prefixo 'up'
        $upCode  = 'up' . $codeNum;                       // garante prefixo 'up'
        $_SESSION['user']        = $upCode . '@up.pt';
        $_SESSION['Code']        = $upCode . '@up.pt';
        $_SESSION['DisplayName'] = $name;
        $_SESSION['CommonName']  = $name;
        $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : HTTP_DIR . '/infodeqb/';
        header('Location: ' . $redirect); exit;
    }
}
$base = HTTP_DIR . '/infodeqb';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Acesso — InfoDEQB</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body {
      height: 100%; margin: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      font-size: 14px;
    }
    body {
      background: #f3f4f6;
      display: flex; align-items: center; justify-content: center; min-height: 100vh;
    }
    .login-wrap { width: 100%; max-width: 360px; padding: 1rem; }

    .login-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0,0,0,.07);
      overflow: hidden;
    }
    .login-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid #e5e7eb;
      background: #f9fafb;
      display: flex; align-items: center; justify-content: space-between;
    }
    .login-header img { height: 28px; }
    .dev-tag {
      font-size: .6rem; font-weight: 700; letter-spacing: .08em;
      text-transform: uppercase;
      background: #dbeeee; color: #084d51;
      border: 1px solid #a9d6d6;
      border-radius: 4px; padding: .2rem .5rem;
    }
    .login-body { padding: 1.5rem; }
    .login-title { font-size: 1rem; font-weight: 700; color: #111827; margin: 0 0 .2rem; }
    .login-sub { font-size: .75rem; color: #6b7280; margin: 0 0 1.25rem; }
    .form-group { margin-bottom: .85rem; }
    label { display: block; font-size: .75rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
    .form-control {
      width: 100%; padding: .45rem .7rem; font-size: .85rem; font-family: inherit;
      border: 1px solid #d1d5db; border-radius: 6px; color: #111827; background: #fff;
      outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .form-control:focus {
      border-color: #2475ba;
      box-shadow: 0 0 0 3px rgba(36,117,186,.12);
    }
    .btn-login {
      width: 100%; padding: .55rem; background: #2475ba; color: #fff;
      font-size: .85rem; font-weight: 600; font-family: inherit;
      border: none; border-radius: 6px; cursor: pointer; margin-top: .5rem;
      transition: background .15s, box-shadow .15s;
    }
    .btn-login:hover { background: #1a5a94; box-shadow: 0 4px 10px rgba(36,117,186,.28); }
    .error-msg {
      background: #fef2f2; border: 1px solid #fca5a5; border-left: 3px solid #ef4444;
      color: #991b1b; font-size: .78rem; padding: .5rem .8rem;
      border-radius: 6px; margin-bottom: 1rem;
    }
    .login-footer {
      padding: .65rem 1.5rem; border-top: 1px solid #e5e7eb;
      background: #f9fafb; font-size: .72rem; color: #9ca3af; text-align: center;
    }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-header">
      <img src="<?php echo $base; ?>/img/logo_deq_black.png" alt="DEQB">
      <span class="dev-tag">localhost</span>
    </div>
    <div class="login-body">
      <div class="login-title">InfoDEQB</div>
      <div class="login-sub">Sem autenticação Shibboleth &mdash; desenvolvimento local</div>
      <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <div class="form-group">
          <label for="code">Código UP</label>
          <input type="text" id="code" name="code" class="form-control"
                 placeholder="ex: up356946"
                 value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>"
                 autofocus required>
        </div>
        <div class="form-group">
          <label for="name">Nome completo</label>
          <input type="text" id="name" name="name" class="form-control"
                 placeholder="Nome Apelido"
                 value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                 required>
        </div>
        <button type="submit" class="btn-login">Entrar</button>
      </form>
    </div>
    <div class="login-footer">Departamento de Engenharia Química e Biológica &mdash; FEUP</div>
  </div>
</div>
</body>
</html>
