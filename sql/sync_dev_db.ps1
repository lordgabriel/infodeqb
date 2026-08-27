# sync_dev_db.ps1
# Copia a BD de producao (webdb.fe.up.pt) para a BD local (XAMPP).
# Corre quando precisas de dados frescos para testes em localhost.
#
# Uso:
#   .\sync_dev_db.ps1              -- copia tudo (estrutura + dados)
#   .\sync_dev_db.ps1 -SchemaOnly  -- so estrutura, sem dados (mais rapido)
#
# Pre-requisito: XAMPP a correr (servico MySQL activo)

param(
    [switch]$SchemaOnly
)

$mysqlBin  = "C:\xampp\mysql\bin"
$dumpDir   = $PSScriptRoot
$timestamp = Get-Date -Format 'yyyyMMdd_HHmm'
$suffix    = if ($SchemaOnly) { '_schema' } else { '' }
$dumpFile  = "$dumpDir\dump_feupptdeqb_$timestamp$suffix.sql"

# Credenciais producao (so para dump)
$prodHost  = "webdb.fe.up.pt"
$prodDb    = "feupptdeqb"
$prodUser  = "feupptdeqb"
$prodPass  = "HQYbFNxqcYPf7eFTazfkJzT6bJDxYb"

# Credenciais locais
$localHost = "localhost"
$localDb   = "feupptdeqb"
$localUser = "root"
$localPass = ""

# ---------------------------------------------------------------------

Write-Host ""
Write-Host "=== sync_dev_db ===" -ForegroundColor Cyan
Write-Host "  Origem : $prodUser@$prodHost/$prodDb"
Write-Host "  Destino: $localUser@$localHost/$localDb"
if ($SchemaOnly) {
    Write-Host "  Modo   : so estrutura (sem dados)" -ForegroundColor Yellow
}
Write-Host ""

# 1. Dump de producao
Write-Host "[1/3] A fazer dump da BD de producao..." -ForegroundColor Cyan

$dumpArgs = @(
    "--host=$prodHost",
    "--user=$prodUser",
    "--default-character-set=utf8mb4",
    "--single-transaction",
    "--skip-add-locks",
    "--skip-lock-tables",
    "--routines",
    "--result-file=$dumpFile"
)
if ($SchemaOnly) {
    $dumpArgs += "--no-data"
}
$dumpArgs += $prodDb

$env:MYSQL_PWD = $prodPass
& "$mysqlBin\mysqldump.exe" @dumpArgs
$exitCode = $LASTEXITCODE
Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue

if ($exitCode -ne 0) {
    Write-Host "ERRO no dump (codigo $exitCode). Verifica ligacao a $prodHost." -ForegroundColor Red
    exit 1
}

$sizeMB = [math]::Round((Get-Item $dumpFile).Length / 1MB, 1)
Write-Host "   OK -- $dumpFile ($sizeMB MB)" -ForegroundColor Green

# 2. Criar BD local se nao existir
Write-Host "[2/3] A verificar BD local '$localDb'..." -ForegroundColor Cyan

$createSql = "CREATE DATABASE IF NOT EXISTS " + '`' + $localDb + '`' + " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if ($localPass -ne "") { $env:MYSQL_PWD = $localPass }
& "$mysqlBin\mysql.exe" "--host=$localHost" "--user=$localUser" "--execute=$createSql"
$rc = $LASTEXITCODE
Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue

if ($rc -ne 0) {
    Write-Host "ERRO ao criar BD local. O MySQL do XAMPP esta a correr?" -ForegroundColor Red
    exit 1
}
Write-Host "   OK" -ForegroundColor Green

# 3. Importar dump
Write-Host "[3/3] A importar dump na BD local (pode demorar alguns minutos)..." -ForegroundColor Cyan

if ($localPass -ne "") { $env:MYSQL_PWD = $localPass }
$importCmd = "`"$mysqlBin\mysql.exe`" --host=$localHost --user=$localUser --default-character-set=utf8mb4 $localDb < `"$dumpFile`""
cmd /c $importCmd
$rc = $LASTEXITCODE
Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue

if ($rc -ne 0) {
    Write-Host "ERRO na importacao (codigo $rc)." -ForegroundColor Red
    exit 1
}
Write-Host "   OK" -ForegroundColor Green

Write-Host ""
Write-Host "Concluido. BD local '$localDb' actualizada." -ForegroundColor Green
Write-Host "Dump guardado em: $dumpFile" -ForegroundColor Gray
Write-Host "Acede agora a http://localhost/infodeqb/ com dados frescos." -ForegroundColor Cyan
Write-Host ""
