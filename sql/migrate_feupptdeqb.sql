-- ============================================================
-- InfoDEQB — Migração para feupptdeqb
-- Cria nova BD, copia tabelas activas com prefixo infodeqb_
-- e migra tabelas DSD da BD separada
-- ============================================================
-- Executar: mysql -u root < migrate_feupptdeqb.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS feupptdeqb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = '';
SET NAMES utf8mb4;

-- ── HR ────────────────────────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_rds_categoria    LIKE deqfeuppt.rds_categoria;
INSERT INTO  feupptdeqb.infodeqb_rds_categoria    SELECT * FROM deqfeuppt.rds_categoria;

CREATE TABLE feupptdeqb.infodeqb_rds_grupo        LIKE deqfeuppt.rds_grupo;
INSERT INTO  feupptdeqb.infodeqb_rds_grupo        SELECT * FROM deqfeuppt.rds_grupo;

CREATE TABLE feupptdeqb.infodeqb_rds_grupo_categoria LIKE deqfeuppt.rds_grupo_categoria;
INSERT INTO  feupptdeqb.infodeqb_rds_grupo_categoria SELECT * FROM deqfeuppt.rds_grupo_categoria;

CREATE TABLE feupptdeqb.infodeqb_rds_responsaveis LIKE deqfeuppt.rds_responsaveis;
INSERT INTO  feupptdeqb.infodeqb_rds_responsaveis SELECT * FROM deqfeuppt.rds_responsaveis;

CREATE TABLE feupptdeqb.infodeqb_rds_gabinetes    LIKE deqfeuppt.rds_gabinetes;
INSERT INTO  feupptdeqb.infodeqb_rds_gabinetes    SELECT * FROM deqfeuppt.rds_gabinetes;

CREATE TABLE feupptdeqb.infodeqb_rds_colaborador  LIKE deqfeuppt.rds_colaborador;
INSERT INTO  feupptdeqb.infodeqb_rds_colaborador  SELECT * FROM deqfeuppt.rds_colaborador;

CREATE TABLE feupptdeqb.infodeqb_rds_registo      LIKE deqfeuppt.rds_registo;
INSERT INTO  feupptdeqb.infodeqb_rds_registo      SELECT * FROM deqfeuppt.rds_registo;

CREATE TABLE feupptdeqb.infodeqb_rds_pedido       LIKE deqfeuppt.rds_pedido;
INSERT INTO  feupptdeqb.infodeqb_rds_pedido       SELECT * FROM deqfeuppt.rds_pedido;

CREATE TABLE feupptdeqb.infodeqb_rds_validacao    LIKE deqfeuppt.rds_validacao;
INSERT INTO  feupptdeqb.infodeqb_rds_validacao    SELECT * FROM deqfeuppt.rds_validacao;

-- ── Água ──────────────────────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_water_resp              LIKE deqfeuppt.water_resp;
INSERT INTO  feupptdeqb.infodeqb_water_resp              SELECT * FROM deqfeuppt.water_resp;

CREATE TABLE feupptdeqb.infodeqb_water_users             LIKE deqfeuppt.water_users;
INSERT INTO  feupptdeqb.infodeqb_water_users             SELECT * FROM deqfeuppt.water_users;

CREATE TABLE feupptdeqb.infodeqb_water_ultrapure_record  LIKE deqfeuppt.water_ultrapure_record;
INSERT INTO  feupptdeqb.infodeqb_water_ultrapure_record  SELECT * FROM deqfeuppt.water_ultrapure_record;

CREATE TABLE feupptdeqb.infodeqb_waterqc_ph_cond         LIKE deqfeuppt.waterqc_ph_cond;
INSERT INTO  feupptdeqb.infodeqb_waterqc_ph_cond         SELECT * FROM deqfeuppt.waterqc_ph_cond;

CREATE TABLE feupptdeqb.infodeqb_waterqc_toc             LIKE deqfeuppt.waterqc_toc;
INSERT INTO  feupptdeqb.infodeqb_waterqc_toc             SELECT * FROM deqfeuppt.waterqc_toc;

-- ── Equipamentos ──────────────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_labs_ensino          LIKE deqfeuppt.infodeq_labs_ensino;
INSERT INTO  feupptdeqb.infodeqb_labs_ensino          SELECT * FROM deqfeuppt.infodeq_labs_ensino;

CREATE TABLE feupptdeqb.infodeqb_lab_responsibles     LIKE deqfeuppt.infodeqb_lab_responsibles;
INSERT INTO  feupptdeqb.infodeqb_lab_responsibles     SELECT * FROM deqfeuppt.infodeqb_lab_responsibles;

CREATE TABLE feupptdeqb.infodeqb_equipmentdeq         LIKE deqfeuppt.equipmentdeq;
INSERT INTO  feupptdeqb.infodeqb_equipmentdeq         SELECT * FROM deqfeuppt.equipmentdeq;

CREATE TABLE feupptdeqb.infodeqb_equipmentdeq_access  LIKE deqfeuppt.equipmentdeq_access;
INSERT INTO  feupptdeqb.infodeqb_equipmentdeq_access  SELECT * FROM deqfeuppt.equipmentdeq_access;

-- ── Exames ────────────────────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_exam_archive         LIKE deqfeuppt.exam_archive;
INSERT INTO  feupptdeqb.infodeqb_exam_archive         SELECT * FROM deqfeuppt.exam_archive;

-- ── Serviço Docente ───────────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_inv_deqb             LIKE deqfeuppt.inv_deqb;
INSERT INTO  feupptdeqb.infodeqb_inv_deqb             SELECT * FROM deqfeuppt.inv_deqb;

CREATE TABLE feupptdeqb.infodeqb_ucs_deqb             LIKE deqfeuppt.ucs_deqb;
INSERT INTO  feupptdeqb.infodeqb_ucs_deqb             SELECT * FROM deqfeuppt.ucs_deqb;

CREATE TABLE feupptdeqb.infodeqb_ucs_deqb_pref        LIKE deqfeuppt.ucs_deqb_pref;
INSERT INTO  feupptdeqb.infodeqb_ucs_deqb_pref        SELECT * FROM deqfeuppt.ucs_deqb_pref;

CREATE TABLE feupptdeqb.infodeqb_servdoc_edit_request LIKE deqfeuppt.servdoc_edit_request;
INSERT INTO  feupptdeqb.infodeqb_servdoc_edit_request SELECT * FROM deqfeuppt.servdoc_edit_request;

-- ── Áreas Disciplinares ───────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_areas_deqb                  LIKE deqfeuppt.areas_deqb;
INSERT INTO  feupptdeqb.infodeqb_areas_deqb                  SELECT * FROM deqfeuppt.areas_deqb;

CREATE TABLE feupptdeqb.infodeqb_subareas                    LIKE deqfeuppt.subareas;
INSERT INTO  feupptdeqb.infodeqb_subareas                    SELECT * FROM deqfeuppt.subareas;

CREATE TABLE feupptdeqb.infodeqb_respostas                   LIKE deqfeuppt.respostas;
INSERT INTO  feupptdeqb.infodeqb_respostas                   SELECT * FROM deqfeuppt.respostas;

CREATE TABLE feupptdeqb.infodeqb_docentes_investigadores_perm LIKE deqfeuppt.docentes_investigadores_perm;
INSERT INTO  feupptdeqb.infodeqb_docentes_investigadores_perm SELECT * FROM deqfeuppt.docentes_investigadores_perm;

-- ── ADI (Espaços de Investigação) ─────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_espacos_elementos_deq LIKE deqfeuppt.espacos_elementos_deq;
INSERT INTO  feupptdeqb.infodeqb_espacos_elementos_deq SELECT * FROM deqfeuppt.espacos_elementos_deq;

CREATE TABLE feupptdeqb.infodeqb_espacos_pontuacoes    LIKE deqfeuppt.espacos_pontuacoes;
INSERT INTO  feupptdeqb.infodeqb_espacos_pontuacoes    SELECT * FROM deqfeuppt.espacos_pontuacoes;

CREATE TABLE feupptdeqb.infodeqb_espacos_formacao      LIKE deqfeuppt.espacos_formacao;
INSERT INTO  feupptdeqb.infodeqb_espacos_formacao      SELECT * FROM deqfeuppt.espacos_formacao;

CREATE TABLE feupptdeqb.infodeqb_espacos_trf           LIKE deqfeuppt.espacos_trf;
INSERT INTO  feupptdeqb.infodeqb_espacos_trf           SELECT * FROM deqfeuppt.espacos_trf;

CREATE TABLE feupptdeqb.infodeqb_espacos_projetos      LIKE deqfeuppt.espacos_projetos;
INSERT INTO  feupptdeqb.infodeqb_espacos_projetos      SELECT * FROM deqfeuppt.espacos_projetos;

CREATE TABLE feupptdeqb.infodeqb_espacos_pub_deq       LIKE deqfeuppt.espacos_pub_deq;
INSERT INTO  feupptdeqb.infodeqb_espacos_pub_deq       SELECT * FROM deqfeuppt.espacos_pub_deq;

CREATE TABLE feupptdeqb.infodeqb_espacos_pub_details   LIKE deqfeuppt.espacos_pub_details;
INSERT INTO  feupptdeqb.infodeqb_espacos_pub_details   SELECT * FROM deqfeuppt.espacos_pub_details;

CREATE TABLE feupptdeqb.infodeqb_espacos_pub_authors   LIKE deqfeuppt.espacos_pub_authors;
INSERT INTO  feupptdeqb.infodeqb_espacos_pub_authors   SELECT * FROM deqfeuppt.espacos_pub_authors;

CREATE TABLE feupptdeqb.infodeqb_espacos_gestao        LIKE deqfeuppt.espacos_gestao;
INSERT INTO  feupptdeqb.infodeqb_espacos_gestao        SELECT * FROM deqfeuppt.espacos_gestao;

-- ── Mobilidade ────────────────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_registo_mobilidade    LIKE deqfeuppt.registo_mobilidade;
INSERT INTO  feupptdeqb.infodeqb_registo_mobilidade    SELECT * FROM deqfeuppt.registo_mobilidade;

CREATE TABLE feupptdeqb.infodeqb_unidades_curriculares LIKE deqfeuppt.unidades_curriculares;
INSERT INTO  feupptdeqb.infodeqb_unidades_curriculares SELECT * FROM deqfeuppt.unidades_curriculares;

-- ── Contactos DIE ─────────────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_company_contacts      LIKE deqfeuppt.company_contacts;
INSERT INTO  feupptdeqb.infodeqb_company_contacts      SELECT * FROM deqfeuppt.company_contacts;

-- ── Admin de secções ──────────────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_section_admins        LIKE deqfeuppt.infodeqb_section_admins;
INSERT INTO  feupptdeqb.infodeqb_section_admins        SELECT * FROM deqfeuppt.infodeqb_section_admins;

-- ── DSD (migrado de dsd_deqb) ─────────────────────────────────
CREATE TABLE feupptdeqb.infodeqb_dsd_ano_letivo        LIKE dsd_deqb.dsd_ano_letivo;
INSERT INTO  feupptdeqb.infodeqb_dsd_ano_letivo        SELECT * FROM dsd_deqb.dsd_ano_letivo;

CREATE TABLE feupptdeqb.infodeqb_dsd_plano_estudo      LIKE dsd_deqb.dsd_plano_estudo;
INSERT INTO  feupptdeqb.infodeqb_dsd_plano_estudo      SELECT * FROM dsd_deqb.dsd_plano_estudo;

CREATE TABLE feupptdeqb.infodeqb_dsd_area_cientifica   LIKE dsd_deqb.dsd_area_cientifica;
INSERT INTO  feupptdeqb.infodeqb_dsd_area_cientifica   SELECT * FROM dsd_deqb.dsd_area_cientifica;

CREATE TABLE feupptdeqb.infodeqb_dsd_carreira          LIKE dsd_deqb.dsd_carreira;
INSERT INTO  feupptdeqb.infodeqb_dsd_carreira          SELECT * FROM dsd_deqb.dsd_carreira;

CREATE TABLE feupptdeqb.infodeqb_dsd_categoria         LIKE dsd_deqb.dsd_categoria;
INSERT INTO  feupptdeqb.infodeqb_dsd_categoria         SELECT * FROM dsd_deqb.dsd_categoria;

CREATE TABLE feupptdeqb.infodeqb_dsd_departamento      LIKE dsd_deqb.dsd_departamento;
INSERT INTO  feupptdeqb.infodeqb_dsd_departamento      SELECT * FROM dsd_deqb.dsd_departamento;

CREATE TABLE feupptdeqb.infodeqb_dsd_docente           LIKE dsd_deqb.dsd_docente;
INSERT INTO  feupptdeqb.infodeqb_dsd_docente           SELECT * FROM dsd_deqb.dsd_docente;

CREATE TABLE feupptdeqb.infodeqb_dsd_docente_ano       LIKE dsd_deqb.dsd_docente_ano;
INSERT INTO  feupptdeqb.infodeqb_dsd_docente_ano       SELECT * FROM dsd_deqb.dsd_docente_ano;

CREATE TABLE feupptdeqb.infodeqb_dsd_uc                LIKE dsd_deqb.dsd_uc;
INSERT INTO  feupptdeqb.infodeqb_dsd_uc                SELECT * FROM dsd_deqb.dsd_uc;

CREATE TABLE feupptdeqb.infodeqb_dsd_uc_ocorrencia     LIKE dsd_deqb.dsd_uc_ocorrencia;
INSERT INTO  feupptdeqb.infodeqb_dsd_uc_ocorrencia     SELECT * FROM dsd_deqb.dsd_uc_ocorrencia;

CREATE TABLE feupptdeqb.infodeqb_dsd_uc_area           LIKE dsd_deqb.dsd_uc_area;
INSERT INTO  feupptdeqb.infodeqb_dsd_uc_area           SELECT * FROM dsd_deqb.dsd_uc_area;

CREATE TABLE feupptdeqb.infodeqb_dsd_distribuicao      LIKE dsd_deqb.dsd_distribuicao;
INSERT INTO  feupptdeqb.infodeqb_dsd_distribuicao      SELECT * FROM dsd_deqb.dsd_distribuicao;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Verificação ───────────────────────────────────────────────
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'feupptdeqb'
ORDER BY TABLE_NAME;
