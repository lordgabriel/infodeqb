# DSD — Distribuição de Serviço Docente (FEUP/DEQB)

## Contexto
Aplicação PHP + MySQL para gerir a distribuição de serviço docente do Departamento de Engenharia Química e Biológica (DEQB) da FEUP. Corre em XAMPP (localhost) e servidor de produção em deq.fe.up.pt.

## Stack
- **Backend:** PHP 7 (produção) — ATENÇÃO: todo o código deve ser compatível com PHP 7
  - Sem `mixed` type hints, sem arrow functions `fn()`, sem `match()`, sem named arguments, sem union types, sem nullsafe operator `?->`
- **BD:** MySQL (phpMyAdmin)
- **Frontend:** HTML + CSS + JS vanilla (sem frameworks)
- **Servidor local:** XAMPP (`C:\xampp\htdocs\dsd_app`)
- **Servidor produção:** deq.fe.up.pt (`/home/deqfeuppt/public_html/infodeq/dsd_app`)

## Base de dados
- Nome: `dsd_deqb`
- Ano letivo activo: `dsd_ano_letivo WHERE ativo=1` (actualmente id=1, 2026/2027)
- Tabelas principais:
  - `dsd_uc` — catálogo de UCs (uma entrada por plano, mesmo para UCs partilhadas)
  - `dsd_uc_ocorrencia` — ocorrências anuais das UCs (tem `plano_id` próprio desde migração recente)
  - `dsd_distribuicao` — linhas de serviço por docente/ocorrência
  - `dsd_docente`, `dsd_docente_ano` — docentes e dados anuais
  - `dsd_plano_estudo`, `dsd_carreira`, `dsd_categoria`, `dsd_area_cientifica`
  - `dsd_ano_letivo` — anos letivos

## Arquitectura de UCs partilhadas
- UCs leccionadas em múltiplos planos têm **uma entrada no catálogo por plano** (Abordagem A)
- Cada UC tem a sua ocorrência anual com `plano_id` próprio
- UC principal (ex: M.EQ): linhas de distribuição com `dsd_por_docente=1` (DSD ✅)
- UCs partilhadas (ex: M.EA, M:EF): linhas com `dsd_por_docente=0` (DSD ❌) — contam para report por ciclo mas não duplicam horas do docente

## Fórmulas de horas
- **H SLEf** = (turmas_T×horas_T + turmas_TP×horas_TP + turmas_L×horas_L + turmas_Sem×horas_Sem) × semanas/13 × f_slef
- **H/s** = (T+TP+L+Sem+OT) × semanas/13
- **H Equiv** = H/s + h_tese
- `f_slef` = 0 quando UC tem menos de 5 alunos (não conta para SLEf mas conta para H Equiv)
- Horas OT = turmas_OT × horas_OT × semanas/13

## Estrutura de ficheiros
```
dsd_app/
├── includes/
│   ├── config.php       — DB, funções utilitárias (num(), fmt(), esc(), horasNecessarias(), horasAtribuidas())
│   ├── header.php       — navbar e layout
│   └── footer.php
├── pages/
│   ├── distribuicao.php — página principal de distribuição (modal, treeview sem-SD, flex layout)
│   ├── ocorrencias.php  — gestão de ocorrências (layout flex: treeview esquerda, tabela direita)
│   ├── ocorrencia-form.php — edição/criação de ocorrência (modo ?new=1&uc_id=X ou ?id=X)
│   ├── ajax-dist.php    — AJAX para linhas de distribuição e list_ocors
│   ├── ajax-copy-dist.php — copia serviço entre ocorrências (copia também n_turmas e horas)
│   ├── anos-letivos.php — gestão de anos letivos e cópia entre anos
│   ├── ucs.php, uc-form.php — catálogo de UCs
│   ├── export.php       — exportação SQL e CSV
│   └── ...
├── reports/
│   ├── por-docente.php  — report por docente (H SLEf, H Equiv, H Tese)
│   ├── por-ciclo.php    — report por ciclo de estudos
│   └── graficos.php
├── sql/
│   ├── migrate_ocorrencia_plano.sql — adiciona plano_id a dsd_uc_ocorrencia
│   └── migrate_uc_horas.sql        — adiciona h_T, h_TP, h_L, h_Sem, h_OT a dsd_uc
├── index.php            — dashboard (badges: horas por carreira, top docentes, UCs em falta)
├── install.sql          — estrutura completa da BD
└── CLAUDE.md            — este ficheiro
```

## Decisões de design importantes
- `plano_id` em `dsd_uc_ocorrencia`: UNIQUE KEY é `(uc_id, ano_letivo_id, plano_id)` — permite múltiplas ocorrências da mesma UC em anos/planos diferentes
- `getAnoLetivoAtivo()` usa sessão como override mas a verificação de "é activo?" deve ser feita directamente na BD (`WHERE ativo=1`)
- Criar ocorrência: só insere na BD ao guardar o form (modo `?new=1&uc_id=X`), cancelar não cria nada
- Horas da UC (`h_T`, `h_TP`, etc.) alimentam por defeito a ocorrência na criação mas podem ser ajustadas na ocorrência
- Badge "sem serviço" na distribuição usa query separada à BD (não usa `$rows` filtrado)

## Funcionalidades implementadas
- Dashboard com badges: horas por carreira (H/s, H SLEf, H Equiv), top 10 docentes, UCs em falta
- Distribuição: modal multi-linha, editar UC, copiar serviço entre ocorrências, treeview sem-SD
- Ocorrências: layout flex (treeview esquerda agrupado por plano, tabela direita agrupada por plano/semestre)
- Reports: por docente, por ciclo, gráficos, horas em falta
- Administração: CRUD docentes, UCs, carreiras, categorias, departamentos, anos letivos
- Exportação: SQL backup, CSVs
- Controlo de acesso: `ACCESS_PUBLIC_REPORTS` em config

## Pendente / Conhecido
- Migração `migrate_ocorrencia_plano.sql` e `migrate_uc_horas.sql` já aplicadas na BD
- FK `fk_dist_ocorrencia` com CASCADE activa em `dsd_distribuicao`
