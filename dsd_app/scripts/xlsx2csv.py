#!/usr/bin/env python3
"""
Converter XLSX de ocorrências (formato Fénix/Sigarra) para CSV de importação DSD.
Uso: python3 xlsx2csv.py input.xlsx output.csv
"""
import sys, csv, re
import openpyxl

def clean(v):
    if v is None: return 0
    s = re.sub(r'\s*\([^)]*\)', '', str(v).strip())
    s = s.replace(',', '.')
    try: return float(s)
    except: return 0

def convert(inp, out):
    wb = openpyxl.load_workbook(inp, data_only=True)
    ws = wb.active
    rows = list(ws.iter_rows(values_only=True))
    if len(rows) < 3:
        raise ValueError("Ficheiro sem dados suficientes")

    # Detect header rows: find row with 'Código' or 'Codigo'
    header_row = 0
    for i, row in enumerate(rows[:5]):
        if any(str(c or '').strip() in ('Código','Codigo','UC','Unidade Curricular') for c in row):
            header_row = i
            break

    # Map columns from header rows
    # Row 1: group headers (Nº Turmas, Carga Docente, ...)
    # Row 2: sub-headers (T, TP, P, OT, PL, S, ...)
    h1 = rows[header_row]
    h2 = rows[header_row + 1] if header_row + 1 < len(rows) else [None]*len(h1)

    # Find key column indices
    def find_col(names, row):
        for i, v in enumerate(row):
            if str(v or '').strip() in names:
                return i
        return None

    col_cod  = find_col(['Código','Codigo','Código UC'], h1)
    col_nome = find_col(['Unidade Curricular','UC','Nome'], h1)

    # Find group offsets for Nº Turmas and Carga Docente
    grupos = {}
    last_group = None
    for i, v in enumerate(h1):
        s = str(v or '').strip()
        if s in ('Nº Turmas','Nº de Turmas','Turmas'): grupos['turmas'] = i
        elif s in ('Carga Docente','Horas','Escolaridade') and 'turmas' in grupos: grupos['horas'] = i

    # Sub-column mapping within each group: T=0, TP=1, P=2, OT=3, PL=4, S=5
    tipo_map = {'T':0,'TP':1,'P':2,'OT':3,'PL':4,'S':5}

    def get_tipo_col(group_start, tipo):
        # Look for tipo in h2 starting from group_start
        for i in range(group_start, min(group_start+15, len(h2))):
            if str(h2[i] or '').strip() == tipo:
                return i
        return None

    if col_cod is None: col_cod = 2
    if col_nome is None: col_nome = 3

    t_start = grupos.get('turmas', 14)
    h_start = grupos.get('horas', 23)

    # Write CSV
    fieldnames = ['Codigo','UC','Turmas T','Turmas TP','Turmas L','Turmas S','Turmas OT',
                  'T','TP','PL','S','OT']

    with open(out, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fieldnames, delimiter=';')
        w.writeheader()

        for row in rows[header_row + 2:]:
            codigo = str(row[col_cod] or '').strip()
            nome   = str(row[col_nome] or '').strip()
            if not codigo and not nome: continue

            def get_t(tipo):
                # Try to find column by sub-header
                for i in range(t_start, min(t_start+15, len(row))):
                    if str(h2[i] or '').strip() == tipo:
                        return clean(row[i])
                return 0

            def get_h(tipo):
                for i in range(h_start, min(h_start+15, len(row))):
                    if str(h2[i] or '').strip() == tipo:
                        return clean(row[i])
                return 0

            # Merge P and PL into L
            n_L = get_t('PL') or get_t('P')
            h_L = get_h('PL') or get_h('P')

            w.writerow({
                'Codigo':    codigo,
                'UC':        nome,
                'Turmas T':  get_t('T'),
                'Turmas TP': get_t('TP'),
                'Turmas L':  n_L,
                'Turmas S':  get_t('S'),
                'Turmas OT': get_t('OT'),
                'T':         get_h('T'),
                'TP':        get_h('TP'),
                'PL':        h_L,
                'S':         get_h('S'),
                'OT':        get_h('OT'),
            })

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Uso: xlsx2csv.py input.xlsx output.csv")
        sys.exit(1)
    convert(sys.argv[1], sys.argv[2])
    print(f"OK: {sys.argv[2]}")
