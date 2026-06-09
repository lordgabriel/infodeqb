-- =============================================================================
-- Validações retroativas — infodeqb HR
-- =============================================================================
-- Para cada par (registo_id, responsável) de registos com status Ativo/Inativo/N.A.
-- que ainda não têm validação real, insere um registo com status='Validado'
-- marcado como "pré-existente".
--
-- Registos abrangidos: Ativo, Inativo, N.A. (exclui Novo — esses passam pelo fluxo normal)
-- Registos ignorados:  já têm pelo menos uma validação real para esse responsável
-- Número esperado:     ~4410 linhas
--
-- ATENÇÃO: operação irreversível. Fazer backup antes se necessário.
--   BACKUP:  SELECT * FROM infodeqb_rds_validacao INTO OUTFILE '/tmp/validacao_backup.csv'...
--            ou exportar via phpMyAdmin antes de correr este script.
-- =============================================================================

INSERT INTO infodeqb_rds_validacao
  (registo_id, pedido_id, deq_id, gab_nome, labs_json,
   resp_codigo, resp_nome, token, status, nota,
   criado_em, respondido_em, expira_em)

SELECT
  t.registo_id,
  NULL                                                              AS pedido_id,
  t.first_deqid                                                     AS deq_id,
  t.first_gab                                                       AS gab_nome,
  t.labs_json                                                       AS labs_json,
  t.responsavel                                                     AS resp_codigo,
  COALESCE(resp.respespaco, t.responsavel)                          AS resp_nome,
  SHA1(CONCAT('retro|', t.registo_id, '|', t.responsavel, '|', UUID())) AS token,
  'Validado'                                                        AS status,
  'Validado retroativamente (acesso pré-existente)'                 AS nota,
  NOW()                                                             AS criado_em,
  NOW()                                                             AS respondido_em,
  NULL                                                              AS expira_em

FROM (
  -- Agrupar labs por (registo, responsável)
  SELECT
    ra.registo_id,
    g.responsavel,
    MIN(g.deqid)                                                    AS first_deqid,
    MIN(g.nomegab)                                                  AS first_gab,
    CONCAT('[',
      GROUP_CONCAT(
        DISTINCT CONCAT('"', REPLACE(g.deqid, '"', '\\"'), '"')
        ORDER BY g.deqid
        SEPARATOR ','
      ),
    ']')                                                            AS labs_json
  FROM infodeqb_rds_registo_acessos ra
  JOIN infodeqb_rds_gabinetes        g   ON g.deqid    = ra.lab_id
  JOIN infodeqb_rds_registo          reg ON reg.autoid = ra.registo_id
  WHERE reg.deleted  = 0
    AND reg.status   IN ('Ativo', 'Inativo', 'N.A.')
    AND g.responsavel IS NOT NULL
    AND g.responsavel != 0
    AND NOT EXISTS (
      -- Ignorar se já existe validação real para este responsável neste registo
      SELECT 1
      FROM infodeqb_rds_validacao v
      WHERE v.registo_id  = ra.registo_id
        AND v.resp_codigo = g.responsavel
        AND v.resp_nome  != 'Isento (auto-validado)'
    )
  GROUP BY ra.registo_id, g.responsavel
) t
LEFT JOIN infodeqb_rds_responsaveis resp ON resp.Codigo = t.responsavel;

-- =============================================================================
-- Verificação pós-execução (correr separadamente depois do INSERT)
-- =============================================================================
-- SELECT COUNT(*) AS total_validacoes FROM infodeqb_rds_validacao WHERE status = 'Validado';
-- SELECT COUNT(*) AS retro FROM infodeqb_rds_validacao
--   WHERE nota = 'Validado retroativamente (acesso pré-existente)';
