-- Migração: adiciona colunas de horas a infodeqb_dsd_uc
-- e popula com valores das ocorrências existentes (média por UC)

-- 1. Adicionar colunas
ALTER TABLE infodeqb_dsd_uc
  ADD COLUMN IF NOT EXISTS h_T   decimal(5,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS h_TP  decimal(5,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS h_L   decimal(5,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS h_Sem decimal(5,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS h_OT  decimal(5,2) DEFAULT 0.00;

-- 2. Popular com valores das ocorrências existentes (usa o máximo por UC)
UPDATE infodeqb_dsd_uc u
JOIN (
    SELECT uc_id,
           MAX(horas_T)   AS h_T,
           MAX(horas_TP)  AS h_TP,
           MAX(horas_L)   AS h_L,
           MAX(horas_Sem) AS h_Sem,
           MAX(horas_OT)  AS h_OT
    FROM infodeqb_dsd_uc_ocorrencia
    GROUP BY uc_id
) o ON o.uc_id = u.id
SET u.h_T   = o.h_T,
    u.h_TP  = o.h_TP,
    u.h_L   = o.h_L,
    u.h_Sem = o.h_Sem,
    u.h_OT  = o.h_OT;

SELECT 'Migração concluída. Horas adicionadas a infodeqb_dsd_uc.' AS resultado;
