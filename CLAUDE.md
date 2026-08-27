# InfoDEQB — Intranet do Departamento de Engenharia Química e Biológica (FEUP)

## Contexto
Aplicação PHP + MySQL para a gestão interna do DEQB (FEUP). Corre em XAMPP localmente e em produção em `deq.fe.up.pt`. Autenticação via Shibboleth (produção) ou `local_login.php` (desenvolvimento).

## Stack
- **Backend:** PHP 7 — ATENÇÃO: todo o código deve ser compatível com PHP 7
  - Sem `mixed` type hints, sem arrow functions `fn()`, sem `match()`, sem named arguments, sem union types, sem nullsafe operator `?->`
- **BD:** MySQL — servidor `webdb.fe.up.pt` em produção, `localhost` em dev
- **Frontend:** Bootstrap 5 + HTML + CSS + JS vanilla (sem frameworks)
- **Email:** PHPMailer via SMTP FEUP (`up356946@up.pt`)
- **Servidor local:** XAMPP (`C:\xampp\htdocs\infodeqb`)
- **Servidor produção:** `deq.fe.up.pt` (`/home/deqfeuppt/public_html/infodeqb`)
- **Git:** `https://github.com/lordgabriel/infodeqb`

## Base de dados
- **Nome:** `feupptdeqb` (em produção e localhost)
- **Prefixos de tabelas:**
  - `infodeqb_rds_*` — módulo HR (registos, acessos, validações, pedidos)
  - `infodeqb_inv_*` — inventário de colaboradores
  - `infodeqb_exam_*` — módulo de exames
  - `infodeqb_section_admins` — admins de secção por módulo
  - `dsd_*` — módulo DSD (distribuição de serviço docente), vive na mesma BD em produção
  - `infodeqb_dsd_*` — tabelas DSD na BD feupptdeqb (prefixo usado em produção)

## Ficheiro de credenciais — deqbwww.php
**NUNCA commitar.** Vive em `DOCUMENT_ROOT/deqbwww.php` (um nível acima de `infodeqb/`):
- Localhost: `C:\xampp\htdocs\deqbwww.php`
- Produção: `/home/deqfeuppt/public_html/deqbwww.php`

Detecta ambiente automaticamente via `$_SERVER['HTTP_HOST']`. Define:
- `Database::connect()` — PDO com credenciais por ambiente
- `ROOT`, `ROOT_DIR`, `HTTP_DIR` — caminhos base
- `MAIL_TEST_MODE` — `true` intercepta emails; `false` envia reais
- `MAIL_TEST_RECIPIENTS` — destinatários quando em modo teste
- `mailUsername`, `mailUserPassword` — credenciais SMTP

Template de produção: [`_deploy/deqbwww.prod.php`](_deploy/deqbwww.prod.php)

## Arquitectura de ficheiros
```
infodeqb/
├── deqbwww.php          — NÃO EXISTE AQUI; está em DOCUMENT_ROOT/ (acima)
├── index.php            — Dashboard
├── session.php          — Gestão de sessão Shibboleth / local
├── local_login.php      — Login local (dev)
├── local_logout.php     — Logout local
├── admin-sections.php   — Gestão de módulos e admins de secção
├── inc/
│   ├── admins.php       — Controlo de acesso centralizado (roles + section admins)
│   └── header.php       — Navbar comum + função t() de i18n
├── lang/
│   ├── lang.pt.php      — Strings PT
│   └── lang.en.php      — Strings EN
├── css/
│   └── infodeq.css      — Design system (CSS variables, componentes)
├── hr/                  — Módulo Recursos Humanos (acessos a laboratórios)
│   ├── index.php        — Lista de registos do colaborador
│   ├── meu-registo.php  — Formulário de pedido de acesso
│   ├── validar-acesso.php — Validação por gestor de laboratório
│   ├── admin/
│   │   ├── index.php    — Lista admin de todos os registos
│   │   ├── detail.php   — Detalhe do registo com histórico de validações/pedidos
│   │   ├── edit.php     — Edição admin do registo
│   │   └── email.php    — Envio manual de email
│   ├── inc/
│   │   └── functions.php — Helpers: email, acessos, telefone, export
│   └── lang/            — Strings específicas do módulo HR
├── areas/               — Módulo Áreas Disciplinares
│   ├── index.php        — Lista e formulário de submissão
│   └── processa.php     — Processamento do formulário
├── servdoc/             — Módulo Serviço Docente (preferência de ensino)
│   ├── index.php        — Formulário de preferência
│   └── add.php          — Submissão
├── equipments/          — Módulo de Equipamentos de laboratório
├── exams/               — Módulo de Arquivo de Exames
├── water/               — Módulo de Controlo de Qualidade da Água
├── gases/               — Módulo de Controlo de Gases
├── adi/                 — Módulo ADI (Avaliação de Desempenho Integrado)
├── reagentes/           — Módulo de Reagentes
├── mobile/              — Módulo de Mobilidade de estudantes
├── dsd_app/             — Sub-aplicação DSD (Distribuição de Serviço Docente)
│   └── includes/config.php — Detecta ambiente; usa feupptdeqb em produção
├── vendor/              — PHPMailer, FPDF, FPDI (Composer)
├── sql/
│   └── migrations/      — Migrações SQL versionadas (001_initial_schema.sql, 002_data.sql)
└── _deploy/
    └── deqbwww.prod.php — Template de credenciais de produção (preencher e copiar manualmente)
```

## Controlo de acesso — inc/admins.php
Incluído em cada página após `deqbwww.php` e `session.php`. Expõe:

| Variável | Conteúdo |
|---|---|
| `$isAdmin` | `true` se admin global (hardcoded: `up356946@up.pt`) |
| `$_iqCurrentUser` | código do utilizador autenticado (`upXXXXXX@up.pt`) |
| `$_iqAdminsHr` | admins do módulo HR (da BD, cache 5 min em sessão) |
| `$_iqAdminsHrList` | admins com acesso à lista HR |
| `$_iqAdminsWater` | admins Água |
| `$_iqAdminsExam` | admins Exames |
| `$_iqAdminsMobile` | admins Mobilidade |
| `$_iqAdminsDsd` | admins DSD |
| `$_iqAdminsGases` | admins Gases |

Admins de secção são geridos via `admin-sections.php` e guardados em `infodeqb_section_admins`.

## i18n — sistema t()
Strings em `lang/lang.pt.php` e `lang/lang.en.php`. Função `t($key, ...$params)` disponível após `inc/header.php`. Detecta língua via `Accept-Language` ou sessão. Adicionar nova string: editar ambos os ficheiros de língua com a mesma chave.

## Email — PHPMailer
Configurado em `hr/inc/functions.php`. Usa SMTP FEUP (`smtp.fe.up.pt`, porta 587, STARTTLS). Credenciais em `deqbwww.php`. Em `MAIL_TEST_MODE=true`, todos os emails vão para `MAIL_TEST_RECIPIENTS` em vez dos destinatários reais. Templates HTML em `hr/inc/*.html`.

Função principal: `send_email(array $to, string $subject, string $body, array $attachments = [])` em `hr/inc/functions.php`.

## Módulo HR — tabelas principais
| Tabela | Conteúdo |
|---|---|
| `infodeqb_rds_colaborador` | Registo do colaborador (dados pessoais, deleted) |
| `infodeqb_rds_registo` | Registo de acesso (status: Novo/Pendente/Ativo/Expirado) |
| `infodeqb_rds_registo_acessos` | Relação registo ↔ laboratórios (fonte primária) |
| `infodeqb_rds_gabinetes` | Laboratórios/gabinetes disponíveis (deqid, nomegab) |
| `infodeqb_rds_validacao` | Histórico de validações (quem, quando, que laboratório) |
| `infodeqb_rds_pedido` | Histórico de pedidos de alteração |
| `infodeqb_inv_deqb` | Inventário de colaboradores DEQB (para menu Departamento) |
| `infodeqb_section_admins` | Admins de secção por módulo |

## Helpers úteis — hr/inc/functions.php
- `getRegistoAcessos(PDO, int $registoId): array` — lab_ids do registo
- `setRegistoAcessos(PDO, int $registoId, array $labIds, array $gabMap)` — substitui acessos (DELETE+INSERT + cache de texto)
- `getGabMap(PDO): array` — mapa deqid→nomegab de gabinetes visíveis
- `renderPhoneInput(string $stored, string $size, bool $required, bool $disabled)` — campo de telefone com indicativo
- `parsePhone(string $stored): array` — separa "+351 912345678" em [indicativo, número]
- `combinePhone(string $ind, string $num): string` — junta indicativo + número
- `format_email(array $info, string $format): string` — renderiza template HTML de email
- `send_email(array $to, string $subject, string $body): bool` — envia via PHPMailer
- `formatDate(string $format, string $dateStr): string` — formata data ou retorna ''
- `ExportFile(array $records)` — exporta para TSV (UTF-16 para Excel)

## Deploy
- **Código:** `git push origin main` + FTP para `/home/deqfeuppt/public_html/infodeqb/`
- **BD:** migrações em `sql/migrations/` aplicadas via phpMyAdmin em produção
- **Credenciais:** `deqbwww.php` copiado manualmente para `public_html/` (nunca em git)
- **Encoding dumps:** usar `--result-file` no mysqldump para evitar BOM do PowerShell

```powershell
# Gerar dump sem BOM (encoding correcto para phpMyAdmin)
& "C:\xampp\mysql\bin\mysqldump.exe" -u root --default-character-set=utf8mb4 --result-file="sql\migrations\002_data.sql" feupptdeqb
```

## Decisões de design importantes
- `deqbwww.php` com detecção de ambiente: sem ficheiros de config separados por ambiente
- Admins globais hardcoded em `inc/admins.php`; admins de secção na BD com cache de sessão
- `MAIL_TEST_MODE` mantido `true` enquanto o deploy não estiver validado
- Dashboard agrupa módulos por secção do menu (Departamento, Ensino, etc.)
- CSS variables em `infodeq.css` (`--iq-primary`, `--iq-r`, etc.) para consistência visual
- Histórico de validações/pedidos em `hr/admin/detail.php`: `<details>` com links de texto discretos (toggle fechando o activo)

## Pendente / Conhecido
- `MAIL_TEST_MODE` em `deqbwww.php` em produção: mudar 2º `true` para `false` quando validado
- `dsd_app` em produção aponta para `feupptdeqb` / `webdb.fe.up.pt` (config detecta ambiente)
- Redirect `infodeq/` → `infodeqb/` via `.htaccess` activo em produção
