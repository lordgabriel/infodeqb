# Migração para Produção — InfoDEQB

## Pré-requisitos
- Servidor: deq.fe.up.pt
- PHP com Shibboleth
- Uma única BD MySQL (ex: `deqfeuppt` ou nome definido pelo serviço)
- Acesso SSH ou phpMyAdmin

---

## 1. Preparação (local)

```bash
# Dump completo das duas BDs locais
mysqldump -u root deqfeuppt > backup_deqfeuppt_YYYYMMDD.sql
mysqldump -u root dsd_deqb   > backup_dsd_deqb_YYYYMMDD.sql
```

Ou usar o botão **"Backup BD → Ambas"** no dashboard admin.

---

## 2. Adaptar configuração para produção

### `deqwww_teste.php` (servidor)
```php
// Alterar para credenciais de produção:
private static $dbName     = 'NOME_BD_PRODUCAO';
private static $dbHost     = 'localhost';
private static $dbUsername = 'UTILIZADOR_BD';
private static $dbUserPassword = 'PASSWORD_BD';

define('HTTP_DIR', 'https://deq.fe.up.pt');

// Activar modo de teste de email ANTES de colocar em produção:
define('MAIL_TEST_MODE', true);
define('MAIL_TEST_RECIPIENTS', [
    'up356946@up.pt',   // admin — recebe cópias de tudo
    // 'outro@up.pt',   // outros endereços de teste
]);
```

### `dsd_app/includes/config.php` (servidor)
```php
// Mudar para a mesma BD de produção:
define('DB_NAME', 'NOME_BD_PRODUCAO');
define('DB_USER', 'UTILIZADOR_BD');
define('DB_PASS', 'PASSWORD_BD');
```

---

## 3. Importar BD para produção

```sql
-- Na BD de produção (phpMyAdmin ou mysql CLI):

-- 3a. Importar todas as tabelas de deqfeuppt
SOURCE backup_deqfeuppt_YYYYMMDD.sql;

-- 3b. Importar tabelas DSD (prefixo dsd_) sem o USE dsd_deqb
-- Editar o .sql: remover "USE `dsd_deqb`;" e importar na BD de produção
SOURCE backup_dsd_deqb_adaptado_YYYYMMDD.sql;
```

**Ou** usar o script `sql/import_dsd_para_bd_unica.sql` (ver secção 4).

---

## 4. Script de adaptação do dump DSD

```bash
# Remover "USE `dsd_deqb`" do dump para importar na BD principal
sed 's/USE `dsd_deqb`;//g' backup_dsd_deqb.sql > backup_dsd_para_producao.sql
```

---

## 5. Testar em produção (MAIL_TEST_MODE=true)

1. Fazer login com Shibboleth (conta real FEUP)
2. Criar registo HR de teste → verificar que email chega a `up356946@up.pt`
3. Aprovar pedido → verificar email de confirmação
4. Testar workflow completo (criar, aprovar, alterar, validar espaços)
5. Verificar `hr/email_dev.log` e pasta `hr/email_dev/` para preview dos emails

---

## 6. Cutover final

1. Tirar snapshot final da BD de produção existente
2. Parar sistema antigo (ou redirecionar URL)
3. Confirmar que MAIL_TEST_MODE=**false** (emails reais activos)
4. Monitorizar erros nos primeiros dias

---

## 7. Notas sobre Shibboleth

Em produção, `session.php` usa automaticamente `$_SERVER['eppn']` do Shibboleth:
- `$_SESSION['user']` = eppn (ex: `up356946@fe.up.pt`)
- `$_SESSION['Code']` = normalizado para `up356946@up.pt`

O controlo de acesso usa `$_SESSION['Code']` — consistente com produção.

---

## Checklist de cutover

- [ ] Backup BD local feito
- [ ] `deqwww_teste.php` adaptado para produção
- [ ] `dsd_app/includes/config.php` adaptado
- [ ] BD importada em produção
- [ ] MAIL_TEST_MODE=true activado
- [ ] Testes de email confirmados
- [ ] Workflow HR testado com Shibboleth real
- [ ] MAIL_TEST_MODE=false (emails reais)
- [ ] Sistema antigo arquivado/desactivado
