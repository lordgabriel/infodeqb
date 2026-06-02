<?php
/**
 * DEV ONLY — Simulação de autenticação Shibboleth para ambiente local (XAMPP).
 * NUNCA colocar este ficheiro em produção.
 */

session_name('DEV_SHIB');
session_start();

// Utilizadores pré-definidos
$test_users = [
    'user' => [
        'label'       => '👤 Utilizador normal',
        'description' => 'UP: 999001 — pode fazer reservas mas não tem admin em nenhuma sala.',
        'eppn'        => 'up999001@up.pt',
        'mail'        => 'up999001@fe.up.pt',
        'name'        => 'Utilizador de Teste',
    ],
    'room_admin' => [
        'label'       => '🔑 Admin de sala (teste)',
        'description' => 'UP: 999002 — adiciona "999002" à lista de admins de uma sala para testar.',
        'eppn'        => 'up999002@up.pt',
        'mail'        => 'up999002@fe.up.pt',
        'name'        => 'Admin de Sala Teste',
    ],
    'global_admin' => [
        'label'       => '⭐ Admin global',
        'description' => 'UP: 356946 — acesso total ao sistema (está na lista admin_roles do config).',
        'eppn'        => 'up356946@up.pt',
        'mail'        => 'up356946@fe.up.pt',
        'name'        => 'Admin Global (up356946)',
    ],
];

$error = '';

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        unset($_SESSION['dev_shib_user']);

    } elseif ($action === 'preset') {
        $choice = $_POST['user'] ?? '';
        if (isset($test_users[$choice])) {
            $_SESSION['dev_shib_user'] = $test_users[$choice];
        }

    } elseif ($action === 'custom') {
        $up_raw = trim($_POST['up_number'] ?? '');
        // aceitar "up123456", "123456", "up123456@up.pt", etc.
        if (preg_match('/up(\d+)/i', $up_raw, $m)) {
            $num = $m[1];
        } elseif (preg_match('/^(\d+)$/', $up_raw, $m)) {
            $num = $m[1];
        } else {
            $num = null;
        }

        if ($num) {
            $_SESSION['dev_shib_user'] = [
                'label' => "Personalizado UP$num",
                'eppn'  => "up{$num}@up.pt",
                'mail'  => "up{$num}@fe.up.pt",
                'name'  => "Utilizador up{$num}",
            ];
        } else {
            $error = 'Número UP inválido. Usa só dígitos (ex: 242181) ou o formato up242181.';
        }
    }

    if (!$error) {
        header('Location: dev_login.php');
        exit;
    }
}

$current = $_SESSION['dev_shib_user'] ?? null;
$current_key = null;
if ($current) {
    foreach ($test_users as $key => $u) {
        if ($u['eppn'] === $current['eppn']) { $current_key = $key; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>DEV — Simulação Shibboleth</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.12); width: 100%; max-width: 580px; overflow: hidden; }
    .card-header { background: #b71c1c; color: #fff; padding: 1.25rem 1.5rem; display: flex; align-items: center; gap: .75rem; }
    .card-header h1 { font-size: 1.1rem; font-weight: 600; }
    .card-header small { display: block; font-size: .78rem; opacity: .85; margin-top: .15rem; font-weight: 400; }
    .badge-dev { background: #ff5252; border: 2px solid #fff; border-radius: 6px; font-size: .7rem; font-weight: 700; padding: .15rem .45rem; letter-spacing: .05em; flex-shrink: 0; }
    .status { margin: 1.25rem 1.5rem .5rem; padding: .75rem 1rem; border-radius: 6px; font-size: .88rem; border-left: 4px solid; }
    .status.active { background: #e8f5e9; border-color: #43a047; }
    .status.inactive { background: #fafafa; border-color: #bdbdbd; color: #757575; }
    .status strong { display: block; font-size: .95rem; margin-bottom: .3rem; }
    code { font-family: monospace; background: rgba(0,0,0,.07); border-radius: 3px; padding: .1rem .35rem; font-size: .83rem; }
    .section { padding: .75rem 1.5rem; }
    .section-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #9e9e9e; margin-bottom: .6rem; }
    .preset-grid { display: flex; flex-direction: column; gap: .5rem; }
    .preset-btn { display: flex; align-items: flex-start; gap: .7rem; padding: .75rem .9rem; border: 2px solid #e0e0e0; border-radius: 8px; background: #fafafa; cursor: pointer; text-align: left; width: 100%; font-family: inherit; transition: border-color .15s, background .15s; }
    .preset-btn:hover { border-color: #90caf9; background: #e3f2fd; }
    .preset-btn.active { border-color: #1976d2; background: #e3f2fd; }
    .preset-btn .icon { font-size: 1.4rem; line-height: 1; flex-shrink: 0; margin-top: .1rem; }
    .preset-btn strong { display: block; font-size: .9rem; color: #212121; }
    .preset-btn span { font-size: .8rem; color: #616161; margin-top: .15rem; display: block; }
    .custom-form { display: flex; gap: .5rem; margin-top: .3rem; }
    .custom-form input[type=text] { flex: 1; padding: .6rem .8rem; border: 2px solid #e0e0e0; border-radius: 6px; font-size: .9rem; font-family: monospace; }
    .custom-form input[type=text]:focus { outline: none; border-color: #1976d2; }
    .custom-form button { padding: .6rem 1.1rem; background: #1976d2; color: #fff; border: none; border-radius: 6px; font-size: .88rem; font-weight: 500; cursor: pointer; white-space: nowrap; }
    .custom-form button:hover { background: #1565c0; }
    .error { margin: 0 1.5rem .5rem; padding: .65rem 1rem; background: #ffebee; border-left: 4px solid #e53935; border-radius: 6px; font-size: .85rem; color: #b71c1c; }
    .divider { border: none; border-top: 1px solid #f0f0f0; margin: .75rem 0; }
    .logout-row { padding: .5rem 1.5rem 1rem; display: flex; align-items: center; justify-content: space-between; }
    .logout-btn { padding: .5rem 1rem; border: 2px solid #e0e0e0; background: #fff; border-radius: 6px; font-size: .85rem; cursor: pointer; color: #616161; }
    .logout-btn:hover { border-color: #ef9a9a; color: #b71c1c; }
    .goto-app { display: inline-block; padding: .55rem 1.1rem; background: #1976d2; color: #fff; border-radius: 6px; text-decoration: none; font-size: .88rem; font-weight: 500; }
    .goto-app:hover { background: #1565c0; }
    .footer { background: #fff3e0; border-top: 1px solid #ffe0b2; padding: .75rem 1.5rem; font-size: .78rem; color: #e65100; }
  </style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <span class="badge-dev">DEV</span>
    <div>
      <h1>Simulação Shibboleth</h1>
      <small>Apenas para ambiente local — não usar em produção</small>
    </div>
  </div>

  <?php if ($current): ?>
    <div class="status active">
      <strong>Sessão activa: <?= htmlspecialchars($current['name']) ?></strong>
      eppn: <code><?= htmlspecialchars($current['eppn']) ?></code> &nbsp;|&nbsp;
      mail: <code><?= htmlspecialchars($current['mail']) ?></code>
    </div>
  <?php else: ?>
    <div class="status inactive">
      <strong>Sem sessão</strong>
      Nenhum utilizador autenticado — a app trata-te como visitante anónimo.
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Utilizadores predefinidos -->
  <div class="section">
    <div class="section-title">Utilizadores predefinidos</div>
    <form method="post" class="preset-grid">
      <input type="hidden" name="action" value="preset">
      <?php foreach ($test_users as $key => $u): ?>
        <button type="submit" name="user" value="<?= $key ?>"
                class="preset-btn <?= $current_key === $key ? 'active' : '' ?>">
          <span class="icon"><?= mb_substr($u['label'], 0, 2) ?></span>
          <div>
            <strong><?= htmlspecialchars(mb_substr($u['label'], 3)) ?></strong>
            <span><?= htmlspecialchars($u['description']) ?></span>
          </div>
        </button>
      <?php endforeach; ?>
    </form>
  </div>

  <hr class="divider">

  <!-- Número UP personalizado -->
  <div class="section">
    <div class="section-title">Simular qualquer número UP</div>
    <form method="post" class="custom-form">
      <input type="hidden" name="action" value="custom">
      <input type="text" name="up_number"
             placeholder="ex: 242181 ou up242181"
             value="<?= ($current && !$current_key) ? htmlspecialchars(preg_replace('/[^0-9]/', '', $current['eppn'])) : '' ?>">
      <button type="submit">Entrar</button>
    </form>
  </div>

  <hr class="divider">

  <div class="logout-row">
    <form method="post">
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="logout-btn">🚫 Terminar sessão</button>
    </form>
    <a href="index.php" class="goto-app">→ Ir para a aplicação</a>
  </div>

  <div class="footer">
    ⚠️ Este ficheiro só deve existir em desenvolvimento. Apaga-o antes de fazer deploy em produção.
  </div>
</div>
</body>
</html>
