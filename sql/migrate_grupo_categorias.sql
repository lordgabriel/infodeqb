-- ============================================================
-- Migração: novas categorias e mapeamento grupo → categoria
-- Data: 2026-06-08
-- ============================================================

-- Novas categorias (IDs 18-24)
INSERT IGNORE INTO infodeqb_rds_categoria (categoriaid, categoria) VALUES
(18, 'Investigador Auxiliar'),
(19, 'Investigador Principal'),
(20, 'Equiparado a Investigador Auxiliar'),
(21, 'Equiparado a Investigador Coordenador'),
(22, 'Equiparado a Investigador Principal'),
(23, 'Técnico'),
(24, 'Outro');

-- Popular tabela de mapeamento grupo → categoria
-- (TRUNCATE seguro: a tabela estava vazia)
TRUNCATE TABLE infodeqb_rds_grupo_categoria;

-- Grupo 1: Pessoal Investigador das Universidades / Doctoral researcher w/ contract
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(1,  4),  -- Doutorado de Nível Inicial
(1,  9),  -- Estagiário de investigação
(1, 10),  -- Assistente de investigação
(1, 18),  -- Investigador Auxiliar
(1, 19),  -- Investigador Principal
(1, 20),  -- Equiparado a Investigador Auxiliar
(1, 21),  -- Equiparado a Investigador Coordenador
(1, 22);  -- Equiparado a Investigador Principal

-- Grupo 2: Estudante de doutoramento
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(2,  4),  -- Doutorado de Nível Inicial
(2,  5);  -- Investigador Doutorado

-- Grupo 3: Estudante de Licenciatura ou Mestrado
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(3,  9),  -- Estagiário de investigação
(3, 80);  -- N.A.

-- Grupo 4: Bolseiro de investigação científica
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(4,  3),  -- Investigador Júnior
(4,  6),  -- Investigador
(4,  9),  -- Estagiário de investigação
(4, 10);  -- Assistente de investigação

-- Grupo 5: Investigador ou colaborador interno
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(5,  6),  -- Investigador
(5, 23),  -- Técnico
(5, 24);  -- Outro

-- Grupo 6: Investigador ou colaborador externo
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(6,  6),  -- Investigador
(6,  9),  -- Estagiário de investigação
(6, 10),  -- Assistente de investigação
(6, 80);  -- N.A.

-- Grupo 7: Estudante em Estudos Avançados
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(7,  4),  -- Doutorado de Nível Inicial
(7,  9);  -- Estagiário de investigação

-- Grupo 8: Docente
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(8, 11),  -- Professor Emérito
(8, 12),  -- Professor Catedrático
(8, 13),  -- Professor Associado com agregação
(8, 14),  -- Professor Associado
(8, 15),  -- Professor Auxiliar com agregação
(8, 16),  -- Professor Auxiliar
(8, 17);  -- Professor aposentado

-- Grupo 9: Investigador permanente
INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
(9,  1),  -- Investigador Principal ou Equiparado
(9,  2),  -- Investigador Auxiliar ou Equiparado
(9,  3),  -- Investigador Júnior
(9,  5),  -- Investigador Doutorado
(9,  6);  -- Investigador
