<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Importar CSV';
$activePage = 'admin';
$db = getDB();
$al = getAnoLetivoAtivo();
$resultado = null;

// ── Helpers ───────────────────────────────────────────────────
function pegar(array $data, string ...$keys): string {
    foreach ($keys as $k) {
        if (isset($data[$k]) && $data[$k] !== '') return trim((string)$data[$k]);
    }
    return '';
}

// ── Import: Catálogo de UCs (sem ocorrências) ─────────────────
function importCatalogo(PDO $db, array $data): void {
    $get  = function(...$k) { return pegar($data; }, ...$k);
    $fnum = function($v) { return num($v; });

    $designacao = $get('UC', 'Unidade curricular', 'designacao', 'nome');
    if (!$designacao) throw new Exception("Sem designação da UC");

    $planoSigla = $get('Plano de estudos', 'Plano', 'plano');
    $planoId = null;
    if ($planoSigla) {
        $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_plano_estudo WHERE sigla=? LIMIT 1");
        $stmt->execute([$planoSigla]);
        $r = $stmt->fetch();
        if (!$r) {
            $db->prepare("INSERT IGNORE INTO infodeqb_dsd_plano_estudo (sigla) VALUES (?)")->execute([$planoSigla]);
            $stmt->execute([$planoSigla]);
            $r = $stmt->fetch();
        }
        $planoId = $r ? (int)$r['id'] : null;
    }

    $ucFields = [
        'codigo'         => $get('Codigo','Código','codigo'),
        'designacao'     => $designacao,
        'ano'            => $get('Ano','ano'),
        'semestre'       => $get('Semestre','semestre'),
        'especializacao' => $get('Especialização','Especializacao','especializacao'),
        'tipo'           => $get('Tipo','tipo') ?: 'OB',
        'tipo_curso'     => $get('Tipo Curso','tipo_curso'),
    ];
    if ($planoId) $ucFields['plano_id'] = $planoId;

    $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_uc WHERE designacao=? AND (plano_id<=>?) LIMIT 1");
    $stmt->execute([$designacao, $planoId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $ucId = (int)$existing['id'];
        $sets = implode(',', array_map(function($k) { return "$k=?"; }, array_keys($ucFields)));
        $db->prepare("UPDATE infodeqb_dsd_uc SET $sets WHERE id=?")->execute([...array_values($ucFields), $ucId]);
    } else {
        $cols = implode(',', array_keys($ucFields));
        $phs  = implode(',', array_fill(0, count($ucFields), '?'));
        $db->prepare("INSERT INTO infodeqb_dsd_uc ($cols) VALUES ($phs)")->execute(array_values($ucFields));
        $ucId = (int)$db->lastInsertId();
    }

    // Áreas científicas
    $areasRaw = $get('Áreas','Areas','AC DEQ','ac_deq','Área Científica');
    if ($areasRaw !== '') {
        $siglas = array_filter(array_map('trim', preg_split('/[;,]+/', $areasRaw)));
        if ($siglas) {
            $db->prepare("DELETE FROM infodeqb_dsd_uc_area WHERE uc_id=?")->execute([$ucId]);
            $first = true;
            foreach ($siglas as $s) {
                $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_area_cientifica WHERE sigla=? LIMIT 1");
                $stmt->execute([$s]);
                $a = $stmt->fetch();
                if (!$a) {
                    $db->prepare("INSERT INTO infodeqb_dsd_area_cientifica (sigla) VALUES (?)")->execute([$s]);
                    $aid = (int)$db->lastInsertId();
                } else { $aid = (int)$a['id']; }
                $db->prepare("INSERT IGNORE INTO infodeqb_dsd_uc_area (uc_id,area_id,principal) VALUES (?,?,?)")
                   ->execute([$ucId, $aid, $first ? 1 : 0]);
                $first = false;
            }
        }
    }
}

// ── Import: Ocorrências (por ano letivo) ──────────────────────
function importOcorrencia(PDO $db, array $data, int $anoLetivoId): void {
    $get  = function(...$k) { return pegar($data; }, ...$k);
    $fnum = function($v) { return num($v; });

    // Encontrar a UC pelo código ou designação
    $codigo     = $get('Codigo','Código','codigo');
    $designacao = $get('UC','Unidade curricular','designacao','nome');
    $planoSigla = $get('Plano de estudos','Plano','plano');

    $ucId = null;
    if ($codigo) {
        $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_uc WHERE TRIM(codigo)=TRIM(?) LIMIT 1");
        $stmt->execute([$codigo]);
        $r = $stmt->fetch();
        if ($r) $ucId = (int)$r['id'];
    }
    if (!$ucId && $designacao) {
        $planoId = null;
        if ($planoSigla) {
            $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_plano_estudo WHERE sigla=? LIMIT 1");
            $stmt->execute([$planoSigla]);
            $r = $stmt->fetch();
            if ($r) $planoId = (int)$r['id'];
        }
        $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_uc WHERE designacao=? AND (plano_id<=>?) LIMIT 1");
        $stmt->execute([$designacao, $planoId]);
        $r = $stmt->fetch();
        if ($r) $ucId = (int)$r['id'];
    }
    if (!$ucId) throw new Exception("UC não encontrada: " . ($codigo ?: $designacao));

    $ocFields = [
        'uc_id'         => $ucId,
        'ano_letivo_id' => $anoLetivoId,
        'estudantes'    => (int)$fnum($get('Estudantes','Alunos','estudantes')),
        'f_slef'        => $fnum($get('F SLEf','F Efetivo','f_slef')) ?: 1,
        'outros_planos' => $get('Outros Planos','outros_planos'),
        'semanas'       => $fnum($get('Semanas','semanas')) ?: 13,
        'n_turmas_T'    => $fnum($get('Turmas T','n_T','n_turmas_T')),
        'n_turmas_TP'   => $fnum($get('Turmas TP','n_TP','n_turmas_TP')),
        'n_turmas_L'    => $fnum($get('Turmas L','Turmas PL','n_L','n_turmas_L')),
        'n_turmas_Sem'  => $fnum($get('Turmas S','Turmas Sem','n_Sem','n_turmas_Sem')),
        'n_turmas_OT'   => $fnum($get('Turmas OT','n_OT','n_turmas_OT')),
        'horas_T'       => $fnum($get('T','horas_T','h_T')),
        'horas_TP'      => $fnum($get('TP','horas_TP','h_TP')),
        'horas_L'       => $fnum($get('PL','L','horas_L','h_L')),
        'horas_Sem'     => $fnum($get('S','horas_Sem','h_S')),
        'horas_OT'      => $fnum($get('OT','horas_OT','h_OT')),
    ];

    $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_uc_ocorrencia WHERE uc_id=? AND ano_letivo_id=?");
    $stmt->execute([$ucId, $anoLetivoId]);
    $oc = $stmt->fetch();

    if ($oc) {
        $sets = implode(',', array_map(function($k) { return "$k=?"; }, array_keys($ocFields)));
        $db->prepare("UPDATE infodeqb_dsd_uc_ocorrencia SET $sets WHERE id=?")
           ->execute([...array_values($ocFields), (int)$oc['id']]);
    } else {
        $cols = implode(',', array_keys($ocFields));
        $phs  = implode(',', array_fill(0, count($ocFields), '?'));
        $db->prepare("INSERT INTO infodeqb_dsd_uc_ocorrencia ($cols) VALUES ($phs)")->execute(array_values($ocFields));
    }
}

// ── Import: Docentes ──────────────────────────────────────────
function importDocente(PDO $db, array $data, int $anoLetivoId): void {
    $get = function(...$k) { return pegar($data; }, ...$k);

    $nome = $get('Nome','nome');
    if (!$nome) throw new Exception("Sem nome do docente");

    $carrId = null;
    $carrNome = $get('Carreira','carreira');
    if ($carrNome) {
        $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_carreira WHERE designacao=? LIMIT 1");
        $stmt->execute([$carrNome]);
        $r = $stmt->fetch();
        if (!$r) {
            $db->prepare("INSERT INTO infodeqb_dsd_carreira (designacao) VALUES (?)")->execute([$carrNome]);
            $carrId = (int)$db->lastInsertId();
        } else { $carrId = (int)$r['id']; }
    }

    $depId = null;
    $depSigla = $get('Departamento','Depto','depto');
    if ($depSigla) {
        $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_departamento WHERE sigla=? LIMIT 1");
        $stmt->execute([$depSigla]);
        $r = $stmt->fetch();
        if (!$r) {
            $db->prepare("INSERT INTO infodeqb_dsd_departamento (sigla) VALUES (?)")->execute([$depSigla]);
            $depId = (int)$db->lastInsertId();
        } else { $depId = (int)$r['id']; }
    }

    $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_docente WHERE nome=? LIMIT 1");
    $stmt->execute([$nome]);
    $existing = $stmt->fetch();

    $docFields = ['nome' => $nome, 'departamento_id' => $depId];
    if ($existing) {
        $docId = (int)$existing['id'];
        $db->prepare("UPDATE infodeqb_dsd_docente SET departamento_id=? WHERE id=?")->execute([$depId, $docId]);
    } else {
        $db->prepare("INSERT INTO infodeqb_dsd_docente (nome,departamento_id) VALUES (?,?)")->execute([$nome,$depId]);
        $docId = (int)$db->lastInsertId();
    }

    // Snapshot do ano
    $anoFields = [
        'docente_id'    => $docId,
        'ano_letivo_id' => $anoLetivoId,
        'carreira_id'   => $carrId,
        'deti'          => num($get('DETI','deti') ?: '1'),
        'h_slef'        => num($get('H SLEF','H SLEf','h_slef')),
        'ref_ecdu'      => num($get('Ref ECDU','ref_ecdu')),
        'observacoes'   => $get('Observações','Observacoes','observacoes'),
        'ativo'         => 1,
    ];
    $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_docente_ano WHERE docente_id=? AND ano_letivo_id=?");
    $stmt->execute([$docId, $anoLetivoId]);
    if ($stmt->fetch()) {
        $sets = implode(',', array_map(function($k) { return "$k=?"; }, array_keys($anoFields)));
        $db->prepare("UPDATE infodeqb_dsd_docente_ano SET $sets WHERE docente_id=? AND ano_letivo_id=?")
           ->execute([...array_values($anoFields), $docId, $anoLetivoId]);
    } else {
        $cols = implode(',', array_keys($anoFields));
        $phs  = implode(',', array_fill(0, count($anoFields), '?'));
        $db->prepare("INSERT INTO infodeqb_dsd_docente_ano ($cols) VALUES ($phs)")->execute(array_values($anoFields));
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile'])) {
    $tipo = $_POST['tipo'] ?? 'catalogo';
    $ano  = (int)($_POST['ano_letivo_id'] ?? $al['id']);
    $file = $_FILES['csvfile']['tmp_name'];
    $sep  = $_POST['separador'] ?? ';';
    $enc  = $_POST['encoding'] ?? 'UTF-8';
    $skip = (int)($_POST['skip_linhas'] ?? 1);

    if (!$file || !is_uploaded_file($file)) {
        flash('Erro ao receber o ficheiro.', 'error');
    } else {
        $handle = fopen($file, 'r');
        // Auto-detectar codificação se não for UTF-8
        $sample = fread($handle, 4096);
        fseek($handle, 0);
        if ($enc === 'UTF-8' && !mb_check_encoding($sample, 'UTF-8')) {
            $enc = 'Windows-1252';
        }
        $header = null;
        $rows = 0; $erros = 0; $errosDetalhe = [];
        for ($i = 0; $i < $skip; $i++) {
            $line = fgets($handle);
            if ($i === $skip-1) $header = str_getcsv(rtrim($line, "\r\n"), $sep);
        }
        $linhaNum = $skip;
        while (($line = fgets($handle)) !== false) {
            $linhaNum++;
            // Use str_getcsv to avoid locale-dependent fgetcsv behaviour with commas
            $row = str_getcsv(rtrim($line, "\r\n"), $sep);
            if (!array_filter($row, function($v) { return $v !== '' && $v !== null; })) continue;
            if ($enc !== 'UTF-8')
                $row = array_map(function($v) { return mb_convert_encoding($v ?? ''; }, 'UTF-8', $enc), $row);
            $data = $header
                ? array_combine(array_map('trim', $header), array_map(function($v) { return trim((string; })$v), $row))
                : $row;
            try {
                if ($tipo === 'catalogo') importCatalogo($db, $data);
                elseif ($tipo === 'ocorrencias') importOcorrencia($db, $data, $ano);
                elseif ($tipo === 'docentes') importDocente($db, $data, $ano);
                $rows++;
            } catch (Exception $e) {
                $erros++;
                if (count($errosDetalhe) < 10)
                    $errosDetalhe[] = "Linha $linhaNum: " . $e->getMessage();
            }
        }
        fclose($handle);
        $resultado = compact('rows','erros','errosDetalhe');
        flash("Importação: $rows registos OK, $erros erros.", $erros ? 'warning' : 'success');
    }
    // fall through to show page with resultado
}

$anos = $db->query("SELECT * FROM infodeqb_dsd_ano_letivo ORDER BY id DESC")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-file-upload me-1"></i>Importar CSV</div>
    <div class="page-sub">Ano letivo activo: <strong><?= esc($al['designacao']) ?></strong></div>
  </div>
  <a href="admin.php" class="btn btn-secondary">← Administração</a>
</div>

<?php if ($resultado): ?>
<div class="card" style="border-left:4px solid <?= $resultado['erros'] ? 'var(--orange)' : 'var(--green)' ?>">
  <div class="card-title" style="color:<?= $resultado['erros'] ? 'var(--orange)' : 'var(--green)' ?>">
    <?= $resultado['erros'] ? '<i class="fas fa-exclamation-triangle"></i>' : '<i class="fas fa-check-circle"></i>' ?> Resultado da Importação
  </div>
  <div style="font-size:14px;margin-bottom:10px">
    <strong><?= $resultado['rows'] ?></strong> registos importados com sucesso
    <?php if ($resultado['erros']): ?>
    &nbsp;·&nbsp; <strong style="color:var(--red)"><?= $resultado['erros'] ?> erros</strong>
    <?php endif; ?>
  </div>
  <?php if ($resultado['errosDetalhe']): ?>
  <ul style="font-size:12px;color:var(--gray-600);margin-left:18px">
    <?php foreach ($resultado['errosDetalhe'] as $e): ?>
    <li><?= esc($e) ?></li>
    <?php endforeach; ?>
    <?php if ($resultado['erros'] > 10): ?>
    <li style="color:var(--gray-400)">... e mais <?= $resultado['erros']-10 ?> erros</li>
    <?php endif; ?>
  </ul>
  <?php endif; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<div class="card">
  <div class="card-title"><i class="fas fa-file-export me-1"></i>Carregar Ficheiro CSV</div>
  <form method="post" enctype="multipart/form-data" id="import-form">

    <div class="form-group">
      <label>Tipo de importação</label>
      <select name="tipo" id="sel-tipo" onchange="tipoChange()">
        <option value="catalogo"><i class="fas fa-book me-1"></i>Catálogo de UCs</option>
        <option value="ocorrencias"><i class="fas fa-calendar-alt me-1"></i>Ocorrências (por ano letivo)</option>
        <option value="docentes"><i class="fas fa-chalkboard-teacher me-1"></i>Docentes / Colaboradores</option>
      </select>
    </div>

    <div id="sel-ano-group" class="form-group" style="display:none">
      <label>Ano letivo de destino</label>
      <select name="ano_letivo_id">
        <?php foreach($anos as $a): ?>
        <option value="<?= $a['id'] ?>" <?= $a['id']==$al['id']?'selected':'' ?>>
          <?= esc($a['designacao']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <span class="form-hint">Ano ao qual pertencem as ocorrências/docentes.</span>
    </div>

    <div class="form-group">
      <label>Ficheiro CSV</label>
      <input type="file" name="csvfile" accept=".csv,.txt" required>
    </div>

    <div class="form-group">
      <label>Separador</label>
      <select name="separador">
        <option value=";">Ponto e vírgula ( ; )</option>
        <option value=",">Vírgula ( , )</option>
        <option value="&#9;">Tabulação (TAB)</option>
      </select>
    </div>

    <div class="form-group">
      <label>Codificação</label>
      <select name="encoding">
        <option value="UTF-8">UTF-8</option>
        <option value="Windows-1252">Windows-1252 (ANSI)</option>
        <option value="ISO-8859-1">ISO-8859-1 (Latin-1)</option>
      </select>
    </div>

    <div class="form-group">
      <label>Linhas de cabeçalho</label>
      <input type="number" name="skip_linhas" value="1" min="0" max="10">
      <span class="form-hint">Normalmente 1.</span>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-file-upload me-1"></i>Importar</button>
  </form>
</div>

<div class="card">
  <div class="card-title"><i class="fas fa-clipboard-list me-1"></i>Formato esperado</div>

  <div id="info-catalogo">
    <p style="font-size:13px;color:var(--gray-600);margin-bottom:10px">
      Actualiza o <strong>catálogo permanente</strong> de UCs — não está associado a nenhum ano letivo.
    </p>
    <table class="data-table" style="font-size:11.5px">
      <thead><tr><th>Coluna</th><th>Exemplo</th></tr></thead>
      <tbody>
        <tr><td><strong>UC *</strong></td><td>Álgebra</td></tr>
        <tr><td>Plano de estudos</td><td>L.EQ</td></tr>
        <tr><td>Codigo</td><td>L.EQ001</td></tr>
        <tr><td>Ano</td><td>1º Ano</td></tr>
        <tr><td>Semestre</td><td>1S</td></tr>
        <tr><td>Tipo</td><td>OB / OPT</td></tr>
        <tr><td>Áreas</td><td>TQM,FTR</td></tr>
      </tbody>
    </table>
  </div>

  <div id="info-ocorrencias" style="display:none">
    <p style="font-size:13px;color:var(--gray-600);margin-bottom:10px">
      Cria/actualiza ocorrências de UCs já existentes no catálogo para o <strong>ano letivo seleccionado</strong>.
      A UC é identificada pelo código ou pela designação+plano.
    </p>
    <table class="data-table" style="font-size:11.5px">
      <thead><tr><th>Coluna</th><th>Exemplo</th></tr></thead>
      <tbody>
        <tr><td>Codigo <em>ou</em> UC *</td><td>L.EQ001 <em>ou</em> Álgebra</td></tr>
        <tr><td>Plano de estudos</td><td>L.EQ</td></tr>
        <tr><td>Estudantes</td><td>89</td></tr>
        <tr><td>F SLEf</td><td>1</td></tr>
        <tr><td>Semanas</td><td>13</td></tr>
        <tr><td>Turmas T / TP / L / S / OT</td><td>1 / 3 / 0 / 0 / 0</td></tr>
        <tr><td>T / TP / PL / S / OT</td><td>2.5 / 1.5 / 0 / 0 / 0</td></tr>
        <tr><td>Outros Planos</td><td>(M.EQ)</td></tr>
      </tbody>
    </table>
  </div>

  <div id="info-docentes" style="display:none">
    <p style="font-size:13px;color:var(--gray-600);margin-bottom:10px">
      Cria/actualiza docentes e o seu snapshot para o <strong>ano letivo seleccionado</strong>.
    </p>
    <table class="data-table" style="font-size:11.5px">
      <thead><tr><th>Coluna</th><th>Exemplo</th></tr></thead>
      <tbody>
        <tr><td><strong>Nome *</strong></td><td>Ana Silva</td></tr>
        <tr><td>Carreira</td><td>Professor Auxiliar</td></tr>
        <tr><td>Departamento</td><td>DEQB</td></tr>
        <tr><td>DETI</td><td>1</td></tr>
        <tr><td>H SLEF</td><td>8</td></tr>
        <tr><td>Ref ECDU</td><td>8</td></tr>
        <tr><td>Observações</td><td>Directora curso</td></tr>
      </tbody>
    </table>
  </div>

  <p style="margin-top:12px;font-size:12px;color:var(--gray-500)">
    <i class="fas fa-check-circle me-1"></i>Registos existentes são <strong>actualizados</strong>, não duplicados.<br>
    <i class="fas fa-lightbulb me-1"></i>Planos, áreas, carreiras e departamentos são criados automaticamente.
  </p>
</div>

</div>

<script>
function tipoChange() {
  const tipo = document.getElementById('sel-tipo').value;
  document.getElementById('sel-ano-group').style.display =
    (tipo === 'ocorrencias' || tipo === 'docentes') ? '' : 'none';
  document.getElementById('info-catalogo').style.display    = tipo==='catalogo'    ? '' : 'none';
  document.getElementById('info-ocorrencias').style.display = tipo==='ocorrencias' ? '' : 'none';
  document.getElementById('info-docentes').style.display    = tipo==='docentes'    ? '' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
