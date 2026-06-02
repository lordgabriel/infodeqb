# DSD – Distribuição de Serviço Docente
## Aplicação Web para XAMPP (PHP + MySQL)

---

## 📦 Estrutura de ficheiros

```
dsd_app/
├── index.php                 ← Painel principal
├── install.sql               ← Script SQL de instalação
├── includes/
│   ├── config.php            ← Configuração BD + helpers
│   ├── header.php            ← Navbar + cabeçalho HTML
│   └── footer.php            ← Rodapé HTML
├── assets/
│   ├── css/app.css           ← Estilos
│   └── js/app.js             ← JavaScript
├── pages/
│   ├── docentes.php          ← Lista de docentes
│   ├── docente-form.php      ← Adicionar/editar docente
│   ├── ucs.php               ← Lista de UCs
│   ├── uc-form.php           ← Adicionar/editar UC
│   ├── distribuicao.php      ← Gestão da distribuição
│   ├── import-csv.php        ← Importação de CSV
│   └── api-distribuicao.php  ← API AJAX (edição inline)
└── reports/
    ├── por-docente.php       ← Relatório por docente
    ├── por-ciclo.php         ← Relatório por ciclo de estudos
    ├── resumo.php            ← Resumo de horas totais
    ├── tabela1.php           ← Tabela 1 DSD (formato oficial)
    └── por-area.php          ← Por área científica
```

---

## 🚀 Instalação no XAMPP

### 1. Copiar ficheiros
Copie a pasta `dsd_app/` para:
```
C:\xampp\htdocs\dsd_app\
```

### 2. Criar a base de dados
1. Abra o **phpMyAdmin**: http://localhost/phpmyadmin
2. Clique em **"Importar"** (ou use a aba SQL)
3. Carregue o ficheiro `install.sql`
4. Execute – cria a BD `dsd_deqb` com todas as tabelas e dados iniciais

### 3. Verificar configuração
Edite `includes/config.php` se necessário:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');        // ← password do MySQL (vazia por defeito no XAMPP)
define('DB_NAME', 'dsd_deqb');
```

### 4. Abrir a aplicação
Aceda a: **http://localhost/dsd_app/**

---

## 📋 Fluxo de utilização recomendado

1. **Importar / criar Docentes**
   - `Docentes → Adicionar Docente` (manualmente)
   - `UCs → Importar CSV` com tipo "Docentes"

2. **Importar / criar UCs** (Ocorrências)
   - `UCs → Importar CSV` com tipo "Unidades Curriculares"
   - Ou `UCs → Adicionar UC` manualmente
   - Definir `F Partilha` para UCs partilhadas (ex: 0.5 para 50/50)

3. **Distribuir serviço**
   - `Distribuição → Adicionar`
   - Para cada UC, atribuir docente(s) com turmas e horas
   - Para UCs partilhadas: marcar `DSD por docente = Não` no docente que não deve ter o peso duplicado

4. **Consultar relatórios**
   - Por Docente, Por Ciclo, Resumo, Tabela 1, Por Área

---

## 📥 Formato CSV para UCs

Separador: `;` (ponto e vírgula)
Primeira linha: cabeçalho

```csv
UC;Plano de estudos;Semestre;Tipo;Estudantes 2024;Estudantes 2025;F Partilha;F Efetivo;T;TP;PL;OT
Álgebra Linear;L.EQ;1S;OB;89;88;1;1;2.5;1.5;0;1
Transferência de Calor;M.EQ;2S;OB;45;40;0.5;0.5;2;1.5;2;0
```

## 📥 Formato CSV para Docentes

```csv
Nome;Carreira;Departamento;DETI;H SLEF;Ref ECDU;Observações
Ana Silva;Docente DEQB;DEQB;1;14;12;
João Costa;Docente DEQB;DEQB;0.5;7;6;50% tempo
```

---

## ⚠️ UCs Partilhadas – Como funciona

O sistema evita duplicação de horas em UCs partilhadas através de dois mecanismos:

1. **F Partilha** na UC: fator que reduz o peso da UC (ex: partilha 50/50 → F=0.5)
2. **DSD por Docente** na distribuição: checkbox que controla se as horas contam
   - ✅ `Sim` → as horas contam no relatório do docente (com F_efetivo aplicado)
   - ❌ `Não` → o docente aparece na UC mas as horas NÃO duplicam no seu total

**Exemplo:**
- UC "Termodinâmica" partilhada entre L.EQ (60%) e M.EQ (40%)
- F Partilha = 0.6 (na ocorrência de L.EQ) e 0.4 (na de M.EQ)
- Ou: F Efetivo definido manualmente por ocorrência

---

## 🖨️ Impressão / Exportação PDF

Todos os relatórios têm botão "Imprimir / PDF".
Use o browser (Ctrl+P) e escolha "Guardar como PDF".
A folha de estilos já oculta o menu e botões na impressão.

---

## 📊 Relatórios disponíveis

| Relatório | Equivalente Excel | Descrição |
|-----------|-------------------|-----------|
| Por Docente | "Por Docente" | DSD completo por docente, agrupado por semestre |
| Por Ciclo | "Por Ciclo de Estudos" | UCs agrupadas por plano de estudos |
| Resumo de Horas | "Resumo H totais por docente" | Tabela resumo com % de ocupação |
| Tabela 1 | "Tabela 1" | Formato oficial DSD com todas as colunas |
| Por Área Científica | "E-Area Cientifica" | Distribuição por AC DEQ |

---

## 🛠️ Requisitos

- XAMPP com PHP ≥ 8.0 e MySQL/MariaDB
- Módulo PDO_MySQL activado (padrão no XAMPP)
- Browser moderno (Chrome, Firefox, Edge)
