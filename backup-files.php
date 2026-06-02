<?php
/**
 * InfoDEQB — Backup de ficheiros
 * Gera um ZIP da pasta infodeqb e envia para download.
 *
 * GET ?mode=pages  → PHP, CSS, JS, imagens, uploads (exclui vendor/)
 * GET ?mode=full   → tudo incluindo vendor/
 */
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
require ROOT_DIR . '/infodeqb/inc/admins.php';

if (!$isAdmin) {
    http_response_code(403);
    exit('Acesso negado.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('A extensão ZipArchive não está disponível neste servidor.');
}

$mode = in_array($_GET['mode'] ?? '', ['pages','full']) ? $_GET['mode'] : 'pages';

// Aumentar limites para ficheiros grandes
set_time_limit(300);
ini_set('memory_limit', '512M');

$srcDir  = realpath($_SERVER['DOCUMENT_ROOT'] . '/infodeqb');
$zipName = 'infodeqb_backup_files_' . $mode . '_' . date('Ymd_His') . '.zip';
$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

// Directórios a excluir no modo 'pages'
$excludeDirs = [];
if ($mode === 'pages') {
    $excludeDirs = array_filter(array_map('realpath', [
        $srcDir . '/vendor',
        $srcDir . '/dsd_app/vendor',
        $srcDir . '/dsd_app/node_modules',
    ]));
}

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Não foi possível criar o arquivo ZIP.');
}

// Percorrer recursivamente
$it    = new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);

$nFiles = 0;
foreach ($files as $file) {
    $realPath = $file->getRealPath();

    // Verificar se está numa directoria excluída
    foreach ($excludeDirs as $excl) {
        if (strpos($realPath . DIRECTORY_SEPARATOR, $excl . DIRECTORY_SEPARATOR) === 0) {
            continue 2;
        }
    }

    // Caminho relativo dentro do ZIP
    $rel = 'infodeqb/' . ltrim(
        str_replace([$srcDir . '/', $srcDir . '\\', $srcDir], '', $realPath),
        '/\\'
    );
    $rel = str_replace('\\', '/', $rel);

    if ($file->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($realPath, $rel);
        $nFiles++;
    }
}

$zip->close();

// ── Enviar para o browser ────────────────────────────────────────
$size = filesize($tmpFile);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');
readfile($tmpFile);
@unlink($tmpFile);
exit;
