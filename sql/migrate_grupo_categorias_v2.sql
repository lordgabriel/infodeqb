-- ============================================================
-- Migração v2: grupos 2,3,4,6,7 sem categorias associadas
-- (Estudante dout., Est. Lic/Mest., Est.Avançados, Bolseiro, Externo)
-- Data: 2026-06-08
-- ============================================================
DELETE FROM infodeqb_rds_grupo_categoria WHERE grupo_id IN (2, 3, 4, 6, 7);
