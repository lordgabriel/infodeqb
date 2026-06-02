<?php
session_start();

// 1. Verificar Autenticação Shibboleth
if (!isset($_SERVER['eppn'])) {
    // Redireciona para login e volta para ESTE ficheiro
    $currentUrl = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $shibLogin = "https://deq.fe.up.pt/Shibboleth.sso/Login?target=" . urlencode($currentUrl);
    header("Location: " . $shibLogin);
    exit;
}

// 2. Preparar Dados
$userEmail = $_SERVER['eppn'];
$displayName = $_SERVER['DisplayName'] ?? $userEmail;
$code = substr($userEmail, 2, strpos($userEmail, '@') - 2);

$userData = [
    'id' => $userEmail,
    'name' => $displayName,
    'email' => $userEmail,
    'code' => $code,
    'timestamp' => time()
];

$payload = base64_encode(json_encode($userData));
$appLink = "gastrack://auth-callback?payload=" . $payload;

// 3. Mostrar Página Intermédia (Resolve o Ecrã Branco)
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login GasTrack</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 40px; background: #f0f9ff; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 400px; margin: 0 auto; }
        h1 { color: #2563eb; font-size: 20px; }
        p { color: #666; margin-bottom: 20px; }
        .btn { display: block; width: 100%; background: #2563eb; color: white; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-bottom: 10px; }
        .code { word-break: break-all; font-size: 10px; color: #999; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Autenticação Concluída</h1>
        <p>Olá, <strong><?php echo htmlspecialchars($displayName); ?></strong>.</p>
        
        <!-- Botão Principal -->
        <a href="<?php echo $appLink; ?>" class="btn">Abrir GasTrack App</a>
        
        <p style="font-size: 12px;">Se a app não abrir automaticamente, clique no botão acima.</p>
        
        <!-- Área de Debug para copiar token se necessário -->
        <div class="code">Token: <?php echo substr($payload, 0, 20) . '...'; ?></div>
    </div>

    <script>
        // Tenta abrir a App automaticamente após 1 segundo
        setTimeout(function() {
            window.location.href = "<?php echo $appLink; ?>";
        }, 1000);
    </script>
</body>
</html>