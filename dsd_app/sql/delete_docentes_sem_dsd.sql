-- ============================================================
-- Docentes sem qualquer linha de distribuição em 2026/2027
-- ============================================================
-- Ano letivo activo (id=1, 2026/2027):
-- SELECT id FROM infodeqb_dsd_ano_letivo WHERE ativo=1

-- 1. PRÉVIA – ver quem vai ser eliminado antes de apagar
SELECT d.id, d.nome, d.nome_curto
FROM infodeqb_dsd_docente d
WHERE d.id NOT IN (
    SELECT DISTINCT docente_id
    FROM infodeqb_dsd_distribuicao
    WHERE ano_letivo_id = (SELECT id FROM infodeqb_dsd_ano_letivo WHERE ativo=1 ORDER BY id DESC LIMIT 1)
)
ORDER BY d.nome;

-- 2. DELETE – só correr depois de confirmar a prévia acima
--    (CASCADE elimina também as linhas em infodeqb_dsd_docente_ano)
/*
DELETE FROM infodeqb_dsd_docente
WHERE id NOT IN (
    SELECT DISTINCT docente_id
    FROM infodeqb_dsd_distribuicao
    WHERE ano_letivo_id = (SELECT id FROM infodeqb_dsd_ano_letivo WHERE ativo=1 ORDER BY id DESC LIMIT 1)
);
*/
