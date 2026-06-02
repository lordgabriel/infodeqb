-- ============================================================
-- Migração: corrigir colunas turmas de INT para DECIMAL(5,2)
-- Executar no phpMyAdmin sobre a BD dsd_deqb
-- ============================================================
USE dsd_deqb;

ALTER TABLE distribuicao
    MODIFY turmas_T   DECIMAL(5,2) DEFAULT 0,
    MODIFY turmas_TP  DECIMAL(5,2) DEFAULT 0,
    MODIFY turmas_L   DECIMAL(5,2) DEFAULT 0,
    MODIFY turmas_Sem DECIMAL(5,2) DEFAULT 0,
    MODIFY turmas_OT  DECIMAL(5,2) DEFAULT 0;

-- Confirmar estrutura final
DESCRIBE distribuicao;
