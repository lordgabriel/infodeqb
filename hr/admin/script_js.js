let selectedFormId = '';
let optionId = '';

$(document).ready(function () {
    $('#new').DataTable({
        autoWidth: false,
        order: [[8, 'desc']],
        columnDefs: [
            {
                targets: [7],
                visible: false,
                searchable: false
            },
            {
                targets: [0, 9],
                visible: true,
                orderable: false
            }
        ]
    });

    $('#pendent').DataTable({
        autoWidth: false,
        language: {
            processing: 'Traitement en cours...',
            search: 'Pesquisar',
            lengthMenu: 'Mostrar _MENU_ registos',
            info: 'A mostrar registos de _START_ a _END_ de _TOTAL_',
            infoEmpty: '',
            infoFiltered: '(filtrado de _MAX_ registos)',
            infoPostFix: '',
            loadingRecords: 'Chargement en cours...',
            zeroRecords: 'Sem registos a mostrar',
            emptyTable: 'Nenhum registo disponível',
            paginate: {
                first: 'Primeiro',
                previous: 'Anterior',
                next: 'Próximo',
                last: 'Último'
            },
            aria: {
                sortAscending: ': activer pour trier la colonne par ordre croissant',
                sortDescending: ': activer pour trier la colonne par ordre décroissant'
            }
        },
        order: [[5, 'desc']],
        columnDefs: [
            {
                targets: [6],
                visible: false,
                searchable: false
            },
            {
                targets: [0],
                visible: true,
                orderable: false
            }
        ]
    });

    $('#expire').DataTable({
        order: [[5, 'asc']],
        autoWidth: false,
        columnDefs: [
            {
                targets: [7],
                visible: false,
                searchable: false
            },
            {
                targets: [0],
                visible: true,
                orderable: false
            }
        ]
    });

    $('#active').DataTable({
        autoWidth: false,
        language: {
            search: 'Pesquisar',
            lengthMenu: 'Mostrar _MENU_ registos',
            info: 'A mostrar registos de _START_ a _END_ de _TOTAL_',
            infoEmpty: '',
            infoFiltered: '(filtrado de _MAX_ registos)',
            infoPostFix: '',
            loadingRecords: 'Chargement en cours...',
            zeroRecords: 'Sem registos a mostrar',
            emptyTable: 'Aucune donnée disponible dans le tableau',
            paginate: {
                first: 'Primeiro',
                previous: 'Anterior',
                next: 'Próximo',
                last: 'Último'
            },
            aria: {
                sortAscending: ': activer pour trier la colonne par ordre croissant',
                sortDescending: ': activer pour trier la colonne par ordre décroissant'
            }
        },
        order: [[2, 'asc']],
        columnDefs: [
            {
                targets: [6],
                visible: true,
                searchable: true
            },
            {
                targets: [0],
                visible: true,
                orderable: false
            }
        ]
    });

    $('#inactive').DataTable({
        autoWidth: false,
        order: [[6, 'desc']],
        columnDefs: [
            {
                targets: [6],
                visible: true,
                searchable: true
            },
            {
                targets: [0],
                visible: true,
                orderable: false
            }
        ]
    });

    // Selecionar/desselecionar todos os registos da tabela ao clicar no checkbox do cabeçalho
    $(document).on('change', 'thead input[type="checkbox"]', function () {
        var $table = $(this).closest('table');
        $table.find('tbody input[type="checkbox"]').prop('checked', this.checked);
    });

    // Bootstrap 5: data-bs-toggle; evento continua igual (shown.bs.tab)
    $('a[data-bs-toggle="pill"]').on('show.bs.tab', function (e) {
        localStorage.setItem('activeTab', $(e.target).attr('href'));
    });

    var activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
        // Bootstrap 5 não tem $.trigger('shown.bs.tab') — usar a API nativa
        var tabEl = document.querySelector('#pills-tab a[href="' + activeTab + '"]');
        if (tabEl) { new bootstrap.Tab(tabEl).show(); }
    }
});

$(document).on('click', '.batchaction', function () {
    const btnId = $(this).attr('id');

    // Botões em <tfooter> ficam fora da <form> depois do DataTables — mapear explicitamente
    const formMap = {
        'novoValidacoesBtn':    'formnovoregisto',
        'novoAcessosBtn':       'formnovoregisto',
        'pendentesubmitBtn':    'formpendentes',
        'expiradossubmitBtn':   'formexpirados',
        'expiradosInativarBtn': 'formexpirados',
        'ativossubmitBtn':      'formativos',
        'inativossubmitBtn':    'forminativos',
    };
    const $targetForm = formMap[btnId] ? $('#' + formMap[btnId]) : $(this).closest('form');

    const checkedCount = $targetForm.find('input[type="checkbox"]:checked').length;
    const atLeastOneIsChecked = checkedCount > 0;

    $('.modal-footer').html('<button type="button" id="bdismiss" class="btn btn-secondary" data-bs-dismiss="modal">Não</button><button type="submit" id="bsubmit" class="btn btn-info btn-ok">Sim</button>');

    if (atLeastOneIsChecked) {
        selectedFormId = '#' + $targetForm.attr('id');
        optionId = btnId;
    } else {
        selectedFormId = 'noselect';
        optionId = 'noselect';
    }

    switch (optionId) {
        case 'newsubmitBtn':
            $('.body-text').html('<p>Tem a certeza que pretende solicitar acessos para os registos selecionados?</p>');
            $('.title-text').html('Solicitar acesso');
            break;

        case 'novoValidacoesBtn':
            $('.body-text').html('<p>Enviar pedido de validação de espaços para os registos selecionados? Os responsáveis de cada espaço receberão um email com link para validar.</p>');
            $('.title-text').html('Solicitar validações');
            $('#novo-action').val('solicitar_validacoes_massa');
            break;

        case 'novoAcessosBtn':
            $('.body-text').html('<p>Enviar pedido de acessos ao SIGARRA para os registos selecionados?<br><small class="text-muted">Apenas registos com todas as validações concluídas (ou sem espaços) serão processados. Os restantes são ignorados.</small></p>');
            $('.title-text').html('Pedir acessos ao SIGARRA');
            $('#novo-action').val('pedir_acessos_massa');
            break;

        case 'pendentesubmitBtn':
            $('.body-text').html('<p>Tem a certeza que pretende ativar os registos selecionados?</p>');
            $('.title-text').html('Ativar registo');
            break;

        case 'expiradossubmitBtn':
            $('.body-text').html('<p>Tem a certeza que pretende enviar notificação de acesso a expirar para os registos selecionados?</p>');
            $('.title-text').html('Notificar');
            $('.modal-footer').html('<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button><button type="submit" id="bsubmit" class="btn btn-info btn-ok">Sim</button>');
            selectedFormId = '#formexpirados';
            break;

        case 'expiradosInativarBtn':
            $('.body-text').html('<p>Tem a certeza que pretende tornar inativos os registos selecionados?</p>');
            $('.title-text').html('Inativar Registo');
            $('.modal-footer').html('<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button><button type="submit" id="bsubmit" class="btn btn-info btn-ok">Sim</button>');
            $('#action').attr('value', 'Inativo');
            break;

        case 'ativossubmitBtn':
            $('.body-text').html('<p>Tem a certeza que pretende tornar inativos os registos selecionados?</p>');
            $('.title-text').html('Inativar Registo');
            $('.modal-footer').html('<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button><button type="submit" id="bsubmit" class="btn btn-info btn-ok">Sim</button>');
            break;

        case 'inativossubmitBtn':
            $('.body-text').html('<p>Tem a certeza que pretende ativar os registos selecionados?</p>');
            $('.title-text').html('Ativar registo');
            $('.modal-footer').html('<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button><button type="submit" id="bsubmit" class="btn btn-info btn-ok">Sim</button>');
            break;

        case 'noselect':
            $('.body-text').html('<p>Nenhum registo selecionado.</p>');
            $('.title-text').html('Erro!');
            $('.modal-footer').html('<button type="button" id="bdismiss" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>');
            break;

        default:
            console.log('No id');
            $('.modal-footer').html('<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button><button type="submit" id="bsubmit" class="btn btn-info btn-ok">Sim</button>');
    }
});

$(document).on('click', '#bsubmit', function (e) {
    e.preventDefault();
    if (selectedFormId && selectedFormId !== 'noselect') {
        $(selectedFormId).submit();
    }
});

// ── Filtro por laboratório ────────────────────────────────────────────
// NOTA: todo o bloco está dentro de $(document).ready() porque DataTables
// é carregado em footer.php, DEPOIS deste script. Se o código corresse fora
// do ready, $.fn.dataTable seria undefined e o handler do botão nunca ficava
// registado (o TypeError parava a execução do script nessa linha).
$(document).ready(function () {

var _labFilter   = [];    // IDs confirmados (após clicar "Aplicar")
var _labMode     = 'or';  // 'or' → qualquer; 'and' → todos
var _labIdToName = window._labIdToName || {};

// Custom search registada depois do DataTables estar disponível
$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    if (!_labFilter.length) return true;
    var aoRow = settings.aoData ? settings.aoData[dataIndex] : null;
    var nTr   = aoRow ? aoRow.nTr : null;
    if (!nTr) return true;  // linha sem nó DOM → mostrar (não filtrar)
    var labAttr = nTr.getAttribute('data-labs') || '';
    if (!labAttr) return false; // registo sem labs → excluído quando há filtro
    var rowLabs = labAttr.split(';');
    return _labMode === 'and'
        ? _labFilter.every(function (l) { return rowLabs.indexOf(l) !== -1; })
        : _labFilter.some(function  (l) { return rowLabs.indexOf(l) !== -1; });
});

// Forçar redraw em todas as tabelas (o custom search é reavaliado)
function _labRedraw() {
    ['#new', '#pendent', '#expire', '#active', '#inactive'].forEach(function (id) {
        if ($.fn.dataTable.isDataTable(id)) {
            $(id).DataTable().draw();
        }
    });
}

// Actualizar campos escondidos do form de export
function _labSyncExport() {
    var tab = (localStorage.getItem('activeTab') || '#pills-active').replace('#pills-', '');
    $('#labExportTab').val(tab);
    $('#labExportLabs').val(JSON.stringify(_labFilter));
    $('#labExportBtn').prop('disabled', tab === 'pedidos');
}

// Actualizar a UI (chips, botão Repor, toggle OU/E, label do botão)
function _labUpdateUI() {
    var n = _labFilter.length;

    // Botão "Repor"
    document.getElementById('labClearBtn').style.display = n ? 'inline-block' : 'none';

    // Toggle OU / E (só com 2+)
    document.getElementById('labMatchToggle').style.display = n >= 2 ? 'inline-flex' : 'none';

    // Label do botão abre-painel
    $('#labBtnLabel').text(
        n === 0 ? 'Todos os laboratórios' :
        n <= 2  ? _labFilter.map(function (id) { return _labIdToName[id] || id; }).join(', ') :
                  n + ' laboratórios'
    );

    // Chips
    if (n === 0) {
        $('#labChips').empty();
    } else {
        $('#labChips').html(
            _labFilter.map(function (id) {
                return '<span class="badge badge-primary" style="font-size:.75rem;padding:4px 8px;margin:2px">'
                    + (_labIdToName[id] || id)
                    + ' <a href="#" class="text-white lab-chip-rm" data-lid="' + id
                    + '" style="text-decoration:none;margin-left:4px">&times;</a></span>';
            }).join('')
        );
    }

    _labSyncExport();
}

// ── Painel custom (não usa Bootstrap dropdown) ──────────────────────

// Abrir / fechar painel
$('#labPanelBtn').on('click', function (e) {
    e.stopPropagation();
    var $p = $('#labPanel');
    if ($p.is(':visible')) {
        $p.hide();
        return;
    }
    // Sincronizar checkboxes com o filtro activo antes de abrir
    $('.lab-chk').each(function () {
        this.checked = _labFilter.indexOf(this.value) !== -1;
    });
    // Repor pesquisa
    $('#labSearchInput').val('');
    $('#labCheckList .lab-item, #labCheckList .lab-group').show();
    $p.show();
    $('#labSearchInput').focus();
});

// Fechar ao clicar fora do painel e do botão
$(document).on('click', function (e) {
    if (!$(e.target).closest('#labPanelBtn, #labPanel').length) {
        $('#labPanel').hide();
    }
});

// Botão "Aplicar" — confirma selecção e fecha painel
$('#labApplyBtn').on('click', function () {
    _labFilter = [];
    $('.lab-chk:checked').each(function () { _labFilter.push(this.value); });
    _labUpdateUI();
    _labRedraw();
    $('#labPanel').hide();
});

// Botão "Cancelar" — fecha sem aplicar
$('#labPanelCancel').on('click', function () {
    $('#labPanel').hide();
});

// Botão "Repor" (fora do painel)
$('#labClearBtn').on('click', function () {
    _labFilter = [];
    $('.lab-chk').prop('checked', false);
    _labUpdateUI();
    _labRedraw();
});

// Links "Todos" / "Nenhum" dentro do painel
$('#labSelectAll').on('click', function (e) {
    e.preventDefault();
    $('#labCheckList .lab-item:visible .lab-chk').prop('checked', true);
});
$('#labSelectNone').on('click', function (e) {
    e.preventDefault();
    $('.lab-chk').prop('checked', false);
});

// Pesquisa dentro do painel
$('#labSearchInput').on('input', function () {
    var q = this.value.toLowerCase().trim();
    $('#labCheckList .lab-item').each(function () {
        var nm = $(this).find('span').first().text().toLowerCase();
        var id = $(this).find('.lab-chk').val().toLowerCase();
        $(this).toggle(!q || nm.indexOf(q) !== -1 || id.indexOf(q) !== -1);
    });
    $('#labCheckList .lab-group').each(function () {
        $(this).toggle(!q || $(this).find('.lab-item:visible').length > 0);
    });
});

// Remover chip individual (actua imediatamente)
$(document).on('click', '.lab-chip-rm', function (e) {
    e.preventDefault();
    var lid = String($(this).data('lid'));
    _labFilter = _labFilter.filter(function (l) { return l !== lid; });
    _labUpdateUI();
    _labRedraw();
});

// Modo OU / E
$('#labModeOr').on('click', function () {
    _labMode = 'or';
    $(this).addClass('active');
    $('#labModeAnd').removeClass('active');
    _labRedraw();
});
$('#labModeAnd').on('click', function () {
    _labMode = 'and';
    $(this).addClass('active');
    $('#labModeOr').removeClass('active');
    _labRedraw();
});

// Sincronizar tab de export ao mudar de separador
$('a[data-bs-toggle="pill"]').on('shown.bs.tab', function () { _labSyncExport(); });
$('#labExportForm').on('submit', function () { _labSyncExport(); });

// Init
_labSyncExport();

}); // fim do segundo $(document).ready() do filtro de labs