# InfoDEQB — Intranet do Departamento de Engenharia Química e Biológica (FEUP)

## Contexto do projecto
Aplicação PHP + MySQL para gerir múltiplos módulos do DEQB. Corre em XAMPP (localhost) e em produção em `deq.fe.up.pt`. Autenticação por Shibboleth em produção; `local_login.php` em localhost.

## Stack
- **Backend:** PHP 8.2 (XAMPP localhost), PHP 7 em produção — evitar `fn()`, `match()`, `?->`, named arguments, union types
- **BD:** MySQL/MariaDB — base `deqfeuppt` (localhost e produção)
- **Frontend:** Bootstrap 5.3.3 (CDN jsdelivr, ver `inc/header.php`), FontAwesome 5.11.2 + v4-shims (CDN), jQuery 3.7.1 (CDN), DataTables 1.13.8 tema bootstrap5 (CDN), Alpine.js 3.14.3 (CDN), Google Fonts Inter — grande parte da marcação ainda usa convenções BS4/BS3 mantidas via shims em `css/infodeq.css` (ver aviso #6)
- **Servidor local:** XAMPP `C:\xampp\htdocs\infodeqb`
- **Produção:** `deq.fe.up.pt` — pasta a definir (ver migração)

## Ficheiro de configuração principal
`C:\xampp\htdocs\deqbwww.php` (foi renomeado de `deqwww_teste.php` — existe stub de compatibilidade)
- Define classe `Database` (PDO para `feupptdeqb`)
- `HTTP_DIR = 'http://localhost'`
- `ROOT_DIR = $_SERVER['DOCUMENT_ROOT']`
- `MAIL_TEST_MODE = false` — colocar `true` em produção para testes
- `MAIL_TEST_RECIPIENTS = ['up356946@up.pt']`

## Sessão PHP e língua
`session.php` — incluído por todas as páginas:
- Carrega automaticamente o ficheiro de língua (`lang/lang.pt.php` ou `lang/lang.en.php`)
- Define função global `t('KEY', ...params)` para tradução
- Detecção: `?lang=en/pt` > `$_SESSION['lang']` > `Accept-Language` > `pt`
- Localhost: redireciona para `local_login.php` se sem sessão
- Produção: usa Shibboleth (`$_SERVER['eppn']`)

## Variáveis de sessão importantes
- `$_SESSION['user']` — eppn Shibboleth (produção: `up356946@fe.up.pt`; localhost: `up356946@up.pt`)
- `$_SESSION['Code']` — normalizado `up356946@up.pt` (USAR SEMPRE PARA CONTROLO DE ACESSO)
- `$_SESSION['DisplayName']` / `$_SESSION['CommonName']` — nome do utilizador

## Controlo de acesso centralizado
`inc/admins.php` — incluído por todas as páginas via `header.php`:
- `$isAdmin` — admin global (hardcoded no ficheiro)
- `$_iqCurrentUser` — código normalizado `up356946@up.pt`
- `$_iqAdminsHr`, `$_iqAdminsWater`, `$_iqAdminsExam`, `$_iqAdminsMobile` — carregados da tabela `infodeqb_section_admins` em BD (cache sessão 5 min)
- **Admins globais**: `up356946@up.pt` (Luís Martins), `up444525@up.pt`
- Painel de gestão: `/admin-sections.php` (só admin global)

### Padrão para módulos locais
```php
$isModuleAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsWater);
```

## Estrutura de ficheiros
```
infodeqb/
├── CLAUDE.md               ← este ficheiro
├── index.php               ← dashboard (3 vistas: admin global, section admin, utilizador)
├── session.php             ← autenticação + língua
├── admin-sections.php      ← gestão de admins de secção
├── backup.php              ← dump SQL das BDs
├── backup-files.php        ← ZIP dos ficheiros do projecto
├── denied.php / error.php / timeout.php
├── local_login.php / local_logout.php
├── inc/
│   ├── admins.php          ← controlo de acesso centralizado
│   ├── header.php          ← navbar + carrega Bootstrap/FA/DataTables/jQuery
│   └── footer.php          ← Bootstrap JS + DataTables JS
├── lang/
│   ├── lang.pt.php         ← ~350 chaves PT
│   └── lang.en.php         ← ~350 chaves EN
├── css/infodeq.css         ← design system (Bootstrap override)
├── vendor/                 ← Dompdf (geração de PDF, exams/), PHPMailer, etc. (Bootstrap/jQuery/DataTables/FontAwesome locais aqui já não são usados pelo core — tudo carregado via CDN, ver inc/header.php)
├── hr/                     ← Gestão de Colaboradores
├── water/                  ← Qualidade da Água + Consumos
├── equipments/             ← Catálogo de Equipamentos
├── exams/                  ← Arquivo de Exames
├── servdoc/                ← Preferência Serviço Docente
├── areas/                  ← Áreas Disciplinares
├── adi/                    ← Espaços de Investigação
├── mobile/                 ← Mobilidade EQ + Contactos DIE
│   ├── index.php           ← dashboard mobilidade
│   ├── in/                 ← Mobilidade IN
│   └── die/                ← Contactos DIE
├── reagentes/              ← Reagentes (externo)
├── booking/                ← Reserva de recursos (MRBS)
├── cell/                   ← Sistema de reservas extra
├── dsd_app/                ← Distribuição de Serviço Docente (BD separada dsd_deqb)
└── sql/
    ├── migrate_producao.md ← guia de migração para produção
    └── *.sql               ← migrações aplicadas
```

## Base de dados
**`feupptdeqb`** — BD única (tudo, incluindo DSD migrado)

### Tabelas por módulo
| Prefixo | Módulo |
|---|---|
| `rds_` | HR (colaboradores, registos, pedidos, grupos, etc.) |
| `water_`, `waterqc_` | Água (consumos, qualidade, responsáveis, utilizadores) |
| `equipmentdeq`, `infodeq_labs_ensino`, `Infodeqb_lab_responsibles`, `equipmentdeq_access` | Equipamentos |
| `exam_archive` | Exames |
| `ucs_deqb`, `ucs_deqb_pref`, `inv_deqb`, `servdoc_edit_request` | Serviço Docente |
| `areas_deqb`, `subareas`, `respostas`, `docentes_investigadores_perm` | Áreas Disciplinares |
| `espacos_*` | ADI (Espaços de Investigação) |
| `registo_mobilidade`, `unidades_curriculares` | Mobilidade |
| `company_contacts` | Contactos DIE |
| `infodeqb_section_admins` | Admins de secção (via UI) |
| `water_users.ativo` | Soft-delete utilizadores água |

**`dsd_deqb`** — BD separada para DSD (em produção mover para `deqfeuppt`)
- Ligação em `dsd_app/includes/config.php`

## Módulo HR
- **Registo novo:** `hr/index.php` (formulário inicial)
- **Meu registo:** `hr/meu-registo.php` (utilizador vê/edita o seu registo)
- **Detalhe:** `hr/meu-registo-detalhe.php`
- **Admin:** `hr/admin/index.php` (tabs: Novos, Pendentes, A expirar, Ativos, Inativos, Pedidos)
- **Deep-links tabs:** `hr/admin/index.php?tab=pills-new|pills-pendent|pills-expire|pills-active`
- **Email:** `hr/inc/functions.php` → `send_email()` com suporte a `MAIL_TEST_MODE`
- **Lang HR:** `hr/lang/lang.pt.php` e `hr/lang/lang.en.php` (têm `?>` no fim — normal, são incluídos depois do header)
- **Telefone:** campo com indicativo + número (select país + input), armazenado como `+351 912345678`
- **Helpers telefone:** `hr/inc/functions.php` → `parsePhone()`, `combinePhone()`, `phoneFromPost()`, `renderPhoneInput()`

## Módulo Água
- **Qualidade:** `water/waterqc.php` — gráficos Chart.js v4, filtro período estilo HA (pills + setas navegação + datas personalizadas)
- **Endpoint AJAX:** `water/waterqcdata.php` — parâmetros `de`/`ate` (YYYY-MM-DD)
- **Exportar Excel:** `water/waterqc_export.php?de=...&ate=...` — CSV com BOM + separador `;`
- **Consumos:** `water/index.php` — registos por ano, modais editar/apagar/responsáveis/utilizadores
- **Gestão:** `water/edit.php` — responsáveis (DataTable) + utilizadores (agrupados por resp, colapsáveis, 3 colunas, pesquisa por resp+utilizador)
- **Soft-delete utilizadores:** coluna `water_users.ativo` — desativar preserva histórico

## Módulo Equipamentos
- **Permissões:** `equipments/inc/permissions.php` — `podeGerirEquipamento()`, `getLabsDoUtilizador()`
- **Acesso específico:** tabela `equipmentdeq_access` — admin global pode conceder acesso a equipamento específico fora do lab do utilizador
- **Labs admin:** tabela `Infodeqb_lab_responsibles` (user_id numérico)

## Módulo Exames
- **Página unificada:** `exams/index.php` — admin vê dashboard + form; utilizador vê só form
- **Grupos por ticket:** cards colapsáveis, filtros (status badges + docente + datas)
- **"Arquivado":** ticket onde TODAS as linhas têm `num_caixa` (não só uma)
- **PDF do auto de incorporação:** `exams/success.php` (após submissão) e `exams/admin/printticket.php` (reimprimir) usam **Dompdf** (`vendor/dompdf/`, vendorizado manualmente sem Composer — ver `vendor/composer/autoload_static.php`). Precisa da extensão `gd` do PHP ativa (para embutir o logótipo no PDF) — confirmar em produção com `php -m | grep gd`.

## Módulo Serviço Docente
- **`servdoc/index.php`** — utilizadores em `inv_deqb` podem submeter preferências; admin vê dashboard + pode editar preferências de qualquer docente
- **Pedido de edição:** tabela `servdoc_edit_request` (pedido → aprovação admin → edição → fechado)
- **Form com filtros:** pills de área, toggle "só selecionadas", contador, paginação DataTables

## Módulo Áreas Disciplinares
- **`areas/index.php`** — restrito a `docentes_investigadores_perm`; admin vê dashboard com ETI por área/subárea + heatmap + gráficos
- **ETI:** cada pessoa = 1 ETI distribuído pelo %; heatmap mostra soma por subárea × área

## Módulo ADI
- **`adi/index.php`** — tabela com cores por nível (dark/azul/cinzento); admins locais: códigos numéricos `['356946','246398']`
- **`adi/ficha.php`** — tabs verticais com publicações, formação, transferência, gestão, projetos; scores coloridos; marcação em convenção Bootstrap 4 (funciona porque o framework carregado é BS5 + shims em `infodeq.css`, ver aviso #6)
- **Acesso especial:** `$special_access` em `adi/ficha.php` para pares de utilizadores

## Módulo Mobilidade
- **Dashboard:** `mobile/index.php` — stats (estudantes, países, DIE), gráficos Chart.js
- **Mobilidade IN:** `mobile/in/` — só para `$_iqAdminsMobile` + admin global
- **Contactos DIE:** `mobile/die/` — a partir de `die/` (que tem redirect)

## Dashboard (index.php)
3 vistas baseadas no tipo de utilizador:
1. **Admin global** — stats HR, DSD, exames; módulos; ferramentas de backup; painel admins de secção
2. **Admin de secção** — só stats do(s) módulo(s) que gere
3. **Utilizador normal** — registo HR, formulários pessoais (condicionais: servdoc se em `inv_deqb`, áreas se em `docentes_investigadores_perm`, ADI se em `espacos_elementos_deq`)

Módulos condicionais no menu/dashboard:
- **Mobilidade:** só `$_iqAdminsMobile` + admin global
- **Áreas Disciplinares:** só `docentes_investigadores_perm` + admin global

## Backup
- **BD:** `backup.php?db=hr|dsd|all` — dump SQL
- **Ficheiros:** `backup-files.php?mode=pages|full` — ZIP (pages exclui vendor/)
- **Sistema completo:** botão no dashboard que inicia os dois downloads em sequência

## Internacionalização (i18n)
- Função `t('KEY', ...params)` disponível globalmente (definida em `session.php`)
- `lang/lang.pt.php` e `lang/lang.en.php` — ~350 chaves cobrindo toda a app
- Língua detectada automaticamente; override via `?lang=en`
- Ficheiros de lang não devem ter `?>` no fim (causa whitespace antes do HTML)

## Convenções CSS (infodeq.css)
- `iq-topnav` — navbar horizontal sticky (altura: `--iq-tn-h: 64px`)
- `iq-main` — conteúdo principal
- `iq-page-header` — cabeçalho de página com título e acções
- `iq-stat`, `iq-stat-green/yellow/red/blue` — cards de estatísticas do dashboard
- `iq-modules`, `iq-module` — grelha de módulos
- `iq-tool-card` — cards de ferramentas (backup, etc.)
- `.alert` — tem `display:flex` no design system; usar `style="display:block"` para texto alinhado à esquerda

## Avisos / Armadilhas
1. **BOM em ficheiros PHP** — causa barra branca no topo; usar PowerShell para remover: `[System.IO.File]::ReadAllBytes()` + verificar `bytes[0..2] == EF BB BF`
2. **`?>` no fim de includes** — qualquer newline depois do tag é emitido como output; NUNCA usar `?>` em ficheiros de configuração/includes
3. **`$_SESSION['user']` vs `$_SESSION['Code']`** — em produção `$_SESSION['user']` é eppn (`@fe.up.pt`); SEMPRE usar `$_SESSION['Code']` para controlo de acesso
4. **`text-align:left` em alerts** — não funciona porque `infodeq.css` tem `.alert { display:flex }` → usar `style="justify-content:flex-start"` ou `style="display:block"`
5. **DataTables com tfoot fora do form** — usar `formMap` no `script_js.js` (HR admin)
6. **Bootstrap 5 carregado, mas marcação maioritariamente em convenção BS4/BS3** — o site carrega Bootstrap 5.3.3 via CDN (`inc/header.php`), mas a maior parte do HTML ainda usa nomes de classe/atributos ao estilo BS4 (`data-toggle`, `text-left`, `font-weight-bold`, `.form-group`, `.close`) em vez dos equivalentes BS5 (`data-bs-toggle`, `text-start`, `fw-bold`, `.btn-close`). Isto funciona porque `css/infodeq.css` tem uma secção de shims dedicada ("BOOTSTRAP 5 — compatibilidade") que mantém as classes antigas a funcionar. Componentes mais recentes (navbar, dropdown do user) já usam os atributos `data-bs-*` nativos — em HTML novo preferir a convenção BS5, mas ao editar páginas existentes seguir o estilo já presente no ficheiro.
7. **DSD usa BD separada** — `dsd_deqb` via `dsd_app/includes/config.php`; em produção migrar para `deqfeuppt`
8. **Códigos numéricos vs formato @up.pt** — módulos legacy (ADI, equipamentos labs) usam código numérico; controlo de acesso principal usa `up356946@up.pt`

## Migração para produção
Ver `sql/migrate_producao.md` para guia completo. Pontos principais:
- Mudar `deqbwww.php`: `HTTP_DIR`, credenciais BD, `MAIL_TEST_MODE=true` inicialmente
- Mudar `dsd_app/includes/config.php`: apontar para BD de produção
- Importar `dsd_deqb` na BD de produção (remover `USE dsd_deqb;` do dump)
- Testar workflow HR com `MAIL_TEST_MODE=true` antes de activar emails reais
