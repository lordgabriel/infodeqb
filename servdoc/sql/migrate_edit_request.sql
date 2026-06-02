-- InfoDEQB / servdoc — Migração: tabela de pedidos de edição de preferências
-- Executar em deq.fe.up.pt (localhost já aplicado)

CREATE TABLE IF NOT EXISTS infodeqb_servdoc_edit_request (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    id_docente  INT NOT NULL,
    pedido_em   DATETIME NOT NULL DEFAULT NOW(),
    motivo      VARCHAR(500),
    aprovado    TINYINT NOT NULL DEFAULT 0,
    aprovado_em DATETIME NULL,
    editado_em  DATETIME NULL,
    INDEX idx_docente (id_docente)
);
