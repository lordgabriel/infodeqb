<?php
/**
 * Helpers de permissões para o módulo de equipamentos.
 *
 * Requer:
 *   $isAdmin   (bool)  — admin global (de admins.php)
 *   $userIdNum (int)   — código numérico UP do utilizador actual
 *   $pdo       (PDO)   — ligação activa
 */

/**
 * Retorna os lab_ids que o utilizador pode gerir.
 * Admin global → todos os labs.
 */
function getLabsDoUtilizador(PDO $pdo, int $userIdNum, bool $isAdmin): array {
    if ($isAdmin) {
        return $pdo->query('SELECT lab_id FROM infodeqb_labs_ensino')
                   ->fetchAll(PDO::FETCH_COLUMN);
    }
    $s = $pdo->prepare('SELECT lab_id FROM Infodeqb_lab_responsibles WHERE user_id = ?');
    $s->execute([$userIdNum]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Verifica se o utilizador pode gerir um equipamento específico.
 * Critérios (OR):
 *   1. Admin global
 *   2. Admin do lab desse equipamento
 *   3. Acesso específico concedido ao equipamento
 */
function podeGerirEquipamento(PDO $pdo, int $userIdNum, bool $isAdmin, array $equipment): bool {
    if ($isAdmin) return true;

    // Admin do lab
    $s = $pdo->prepare(
        'SELECT 1 FROM Infodeqb_lab_responsibles WHERE user_id = ? AND lab_id = ?'
    );
    $s->execute([$userIdNum, $equipment['Laboratorio']]);
    if ($s->fetchColumn()) return true;

    // Acesso específico ao equipamento
    $s2 = $pdo->prepare(
        'SELECT 1 FROM infodeqb_equipmentdeq_access WHERE user_id = ? AND equipment_id = ?'
    );
    $s2->execute([$userIdNum, (int)$equipment['equipment_id']]);
    return (bool)$s2->fetchColumn();
}

/**
 * Verifica se o utilizador pode adicionar equipamento a um lab específico.
 */
function podeAdicionarAoLab(PDO $pdo, int $userIdNum, bool $isAdmin, string $labId): bool {
    if ($isAdmin) return true;
    $s = $pdo->prepare(
        'SELECT 1 FROM Infodeqb_lab_responsibles WHERE user_id = ? AND lab_id = ?'
    );
    $s->execute([$userIdNum, $labId]);
    return (bool)$s->fetchColumn();
}
