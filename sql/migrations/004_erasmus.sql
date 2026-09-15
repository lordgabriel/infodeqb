-- Módulo Erasmus — equivalências de cadeiras
-- Fase 1: criação das tabelas normalizadas

CREATE TABLE IF NOT EXISTS `infodeqb_erasmus_paises` (
    `id_pais`   INT AUTO_INCREMENT PRIMARY KEY,
    `nome_pais` VARCHAR(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_erasmus_instituicoes` (
    `id_instituicao`   INT AUTO_INCREMENT PRIMARY KEY,
    `nome_instituicao` VARCHAR(510) NOT NULL,
    `id_pais`          INT DEFAULT NULL,
    CONSTRAINT `fk_inst_pais` FOREIGN KEY (`id_pais`)
        REFERENCES `infodeqb_erasmus_paises` (`id_pais`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_erasmus_cadeiras_feup` (
    `id_cadeira_feup`        INT AUTO_INCREMENT PRIMARY KEY,
    `nome_cadeira_feup`      VARCHAR(510) NOT NULL,
    `codigo`                 VARCHAR(20)  DEFAULT NULL,
    `sigla`                  VARCHAR(20)  DEFAULT NULL,
    `ects_feup`              DECIMAL(5,2) DEFAULT NULL,
    `semestre_de_ocorrencia` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_erasmus_cadeiras_estrangeiras` (
    `id_cadeira_estrangeira`   INT AUTO_INCREMENT PRIMARY KEY,
    `id_instituicao`           INT DEFAULT NULL,
    `nome_cadeira_estrangeira` VARCHAR(510) NOT NULL,
    `ects_estrangeira`         DECIMAL(5,2) DEFAULT NULL,
    CONSTRAINT `fk_cadest_inst` FOREIGN KEY (`id_instituicao`)
        REFERENCES `infodeqb_erasmus_instituicoes` (`id_instituicao`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_erasmus_links_cadeiras` (
    `id_link`               INT AUTO_INCREMENT PRIMARY KEY,
    `id_cadeira_estrangeira` INT NOT NULL,
    `link_url`              TEXT NOT NULL,
    CONSTRAINT `fk_link_cadest` FOREIGN KEY (`id_cadeira_estrangeira`)
        REFERENCES `infodeqb_erasmus_cadeiras_estrangeiras` (`id_cadeira_estrangeira`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_erasmus_equivalencias` (
    `id_equivalencia`       INT AUTO_INCREMENT PRIMARY KEY,
    `id_instituicao`        INT DEFAULT NULL,
    `id_cadeira_feup`       INT DEFAULT NULL,
    `numero_estudante_autor` INT DEFAULT NULL,
    `ano_letivo`            VARCHAR(50) DEFAULT NULL,
    CONSTRAINT `fk_equiv_inst`  FOREIGN KEY (`id_instituicao`)
        REFERENCES `infodeqb_erasmus_instituicoes` (`id_instituicao`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_equiv_cfeup` FOREIGN KEY (`id_cadeira_feup`)
        REFERENCES `infodeqb_erasmus_cadeiras_feup` (`id_cadeira_feup`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_erasmus_equivalencias_relacao` (
    `id_equivalencia`        INT NOT NULL,
    `id_cadeira_estrangeira` INT NOT NULL,
    PRIMARY KEY (`id_equivalencia`, `id_cadeira_estrangeira`),
    CONSTRAINT `fk_er_equiv`  FOREIGN KEY (`id_equivalencia`)
        REFERENCES `infodeqb_erasmus_equivalencias` (`id_equivalencia`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_er_cadest` FOREIGN KEY (`id_cadeira_estrangeira`)
        REFERENCES `infodeqb_erasmus_cadeiras_estrangeiras` (`id_cadeira_estrangeira`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
