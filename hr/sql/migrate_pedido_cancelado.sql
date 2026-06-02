-- ============================================================
-- InfoDEQB / HR — Migração: adicionar status 'Cancelado' a infodeqb_rds_pedido
-- Executar em deq.fe.up.pt (localhost já aplicado)
-- ============================================================

-- A BD local já tem: Pendente, Aprovado, Rejeitado, Aguarda_SIGARRA, Concluido
-- Adicionar Cancelado (usado quando um registo é apagado com pedidos pendentes)
ALTER TABLE `infodeqb_rds_pedido`
  MODIFY COLUMN `status`
    ENUM('Pendente','Aprovado','Rejeitado','Aguarda_SIGARRA','Concluido','Cancelado')
    NOT NULL DEFAULT 'Pendente';
