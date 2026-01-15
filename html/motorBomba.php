<?php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

exigePermissaoTela('Cadastro de Conjunto Motor-Bomba', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Cadastro de Conjunto Motor-Bomba');

$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

$tiposEixo = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => 'H', 'text' => 'Horizontal'],
    ['value' => 'V', 'text' => 'Vertical'],
];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* ============================================
       Reset e Box Sizing
       ============================================ */
    *, *::before, *::after {
        box-sizing: border-box;
    }

    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 24px;
        max-width: 1800px;
        margin: 0 auto;
        overflow-x: hidden;
    }

    /* ============================================
       Page Header
       ============================================ */
    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px;
        padding: 28px 32px;
        margin-bottom: 24px;
        color: white;
        overflow: hidden;
    }

    .page-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .page-header-info {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .page-header-icon {
        width: 52px;
        height: 52px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .page-header h1 {
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: white;
    }

    .page-header-subtitle {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
    }

    .btn-novo {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: white;
        color: #1e3a5f;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        max-width: 100%;
    }

    .btn-novo:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-novo ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Filters Card
       ============================================ */
    .filters-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .filters-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }

    .filters-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
    }

    .filters-title ion-icon {
        font-size: 18px;
        color: #64748b;
    }

    .btn-clear-filters {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-clear-filters:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        width: 100%;
    }

    /* ============================================
       Form Controls
       ============================================ */
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
        width: 100%;
    }

    .form-group-span-2 {
        grid-column: span 2;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-label ion-icon {
        font-size: 14px;
        color: #94a3b8;
    }

    .form-control {
        width: 100%;
        max-width: 100%;
        padding: 12px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        color: #334155;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        outline: none;
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Select2 Custom Styling */
    .select2-container--default .select2-selection--single {
        height: 44px;
        padding: 8px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        transition: all 0.2s ease;
    }

    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #334155;
        font-size: 13px;
        line-height: 26px;
        padding-left: 0;
    }

    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        right: 10px;
    }

    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
    }

    .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
    }

    .input-search-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-search-wrapper input {
        padding-right: 80px;
    }

    .btn-limpar-busca {
        position: absolute;
        right: 44px;
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px;
        display: none;
    }

    .btn-limpar-busca:hover {
        color: #64748b;
    }

    .btn-buscar-input {
        position: absolute;
        right: 8px;
        background: #3b82f6;
        border: none;
        color: white;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        transition: background 0.2s ease;
    }

    .btn-buscar-input:hover {
        background: #2563eb;
    }

    /* ============================================
       Results Info
       ============================================ */
    .results-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding: 0 4px;
    }

    .results-count {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #64748b;
    }

    .results-count strong {
        color: #1e3a5f;
    }

    /* ============================================
       Table Container
       ============================================ */
    .table-container {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        position: relative;
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: #f8fafc;
        padding: 14px 12px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
        cursor: pointer;
        user-select: none;
        transition: background 0.15s ease;
    }

    .data-table th:hover {
        background: #f1f5f9;
    }

    .data-table th.no-sort {
        cursor: default;
    }

    .data-table th.no-sort:hover {
        background: #f8fafc;
    }

    /* Sort Indicator */
    .sort-indicator {
        display: inline-flex;
        flex-direction: column;
        margin-left: 6px;
        opacity: 0.3;
        transition: opacity 0.2s ease;
    }

    .data-table th:hover .sort-indicator {
        opacity: 0.6;
    }

    .sort-indicator ion-icon {
        font-size: 10px;
        line-height: 1;
    }

    .sort-indicator .sort-asc,
    .sort-indicator .sort-desc {
        height: 8px;
        display: flex;
        align-items: center;
    }

    .data-table th.sorted-asc .sort-indicator,
    .data-table th.sorted-desc .sort-indicator {
        opacity: 1;
    }

    .data-table th.sorted-asc .sort-indicator .sort-asc ion-icon,
    .data-table th.sorted-desc .sort-indicator .sort-desc ion-icon {
        color: #3b82f6;
    }

    .data-table th.sorted-asc .sort-indicator .sort-desc,
    .data-table th.sorted-desc .sort-indicator .sort-asc {
        opacity: 0.2;
    }

    .data-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s ease;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    .data-table tbody tr:last-child {
        border-bottom: none;
    }

    .data-table td {
        padding: 12px;
        font-size: 12px;
        color: #475569;
        vertical-align: middle;
    }

    .data-table td.code {
        font-family: 'SF Mono', Monaco, 'Consolas', monospace;
        font-size: 11px;
        color: #3b82f6;
        font-weight: 600;
    }

    .data-table td.name {
        font-weight: 500;
        color: #1e293b;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .data-table td.truncate {
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-eixo {
        background: #eff6ff;
        color: #3b82f6;
    }

    .badge-eixo.horizontal {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-eixo.vertical {
        background: #fef3c7;
        color: #b45309;
    }

    /* Action Buttons */
    .actions-cell {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 16px;
    }

    .btn-action.view {
        background: #eff6ff;
        color: #3b82f6;
    }

    .btn-action.view:hover {
        background: #dbeafe;
    }

    .btn-action.edit {
        background: #fef3c7;
        color: #d97706;
    }

    .btn-action.edit:hover {
        background: #fde68a;
    }

    .btn-action.delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-action.delete:hover {
        background: #fecaca;
    }

    /* ============================================
       Empty State
       ============================================ */
    .empty-state {
        padding: 60px 20px;
        text-align: center;
    }

    .empty-state-icon {
        width: 64px;
        height: 64px;
        background: #f1f5f9;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 28px;
        color: #94a3b8;
    }

    .empty-state h3 {
        font-size: 16px;
        font-weight: 600;
        color: #475569;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 13px;
        color: #94a3b8;
        margin: 0;
    }

    /* ============================================
       Loading Overlay
       ============================================ */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #e2e8f0;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* ============================================
       Pagination
       ============================================ */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .pagination-buttons {
        display: flex;
        gap: 4px;
    }

    .pagination-buttons button {
        min-width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 8px;
        font-size: 13px;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .pagination-buttons button:hover:not(:disabled) {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .pagination-buttons button.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    .pagination-buttons button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .page-info {
        font-size: 13px;
        color: #64748b;
    }

    /* ============================================
       Responsive
       ============================================ */
    @media (max-width: 1200px) {
        .filters-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 16px;
        }

        .page-header {
            padding: 16px;
            border-radius: 12px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }

        .page-header-info {
            flex-direction: column;
            text-align: center;
            gap: 12px;
        }

        .page-header-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto;
        }

        .page-header h1 {
            font-size: 18px;
        }

        .page-header-subtitle {
            font-size: 12px;
        }

        .btn-novo {
            width: 100%;
            justify-content: center;
            padding: 12px 20px;
            box-sizing: border-box;
        }

        .filters-card {
            padding: 16px;
            border-radius: 12px;
        }

        .filters-header {
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }

        .filters-title {
            justify-content: center;
        }

        .btn-clear-filters {
            width: 100%;
            justify-content: center;
        }

        .filters-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 16px;
        }

        .filters-grid .form-group {
            grid-column: span 1 !important;
            width: 100% !important;
        }

        .form-control {
            width: 100%;
            box-sizing: border-box;
        }

        .table-container {
            border-radius: 12px;
        }

        .results-info {
            flex-direction: column;
            gap: 8px;
            text-align: center;
        }

        .select2-container {
            width: 100% !important;
        }

        .pagination-container {
            flex-direction: column;
            gap: 12px;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="cog-outline"></ion-icon>
                </div>
                <div>
                    <h1>Conjunto Motor Bomba</h1>
                    <p class="page-header-subtitle">Gerencie os conjuntos motor bomba do sistema</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
            <a href="motorBombaForm.php" class="btn-novo">
                <ion-icon name="add-outline"></ion-icon>
                Novo Conjunto
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="filters-card">
        <div class="filters-header">
            <div class="filters-title">
                <ion-icon name="filter-outline"></ion-icon>
                Filtros de Pesquisa
            </div>
            <button type="button" class="btn-clear-filters" onclick="limparFiltros()">
                <ion-icon name="refresh-outline"></ion-icon>
                Limpar Filtros
            </button>
        </div>

        <div class="filters-grid">
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="selectUnidade" class="form-control select2-custom">
                    <option value="">Todas as Unidades</option>
                    <?php foreach ($unidades as $unidade): ?>
                        <option value="<?= $unidade['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($unidade['CD_CODIGO'] . ' - ' . $unidade['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="location-outline"></ion-icon>
                    Localidade
                </label>
                <select id="selectLocalidade" class="form-control select2-custom" disabled>
                    <option value="">Selecione uma Unidade primeiro</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="git-compare-outline"></ion-icon>
                    Tipo de Eixo
                </label>
                <select id="selectTipoEixo" class="form-control select2-custom">
                    <?php foreach ($tiposEixo as $tipo): ?>
                        <option value="<?= $tipo['value'] ?>"><?= $tipo['text'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="search-outline"></ion-icon>
                    Busca Geral
                </label>
                <div class="input-search-wrapper">
                    <input type="text" id="inputBusca" class="form-control" placeholder="Codigo, nome, localizacao...">
                    <button type="button" class="btn-limpar-busca" id="btnLimparBusca" onclick="limparBusca()">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                    <button type="button" class="btn-buscar-input" onclick="buscarMotorBomba()">
                        <ion-icon name="search-outline"></ion-icon>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="results-info">
        <div class="results-count">
            <ion-icon name="list-outline"></ion-icon>
            <span>Total: <strong id="totalRegistros">0</strong> registro(s)</span>
        </div>
    </div>

    <div class="table-container">
        <div class="loading-overlay" id="loadingTabela">
            <div class="loading-spinner"></div>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort="UNIDADE">
                            Unidade
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="LOCALIDADE">
                            Localidade
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="DS_CODIGO">
                            Codigo
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="DS_NOME">
                            Nome
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="TP_EIXO">
                            Eixo
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="VL_POTENCIA_MOTOR">
                            Potencia (CV)
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="VL_VAZAO_BOMBA">
                            Vazao (L/s)
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th class="no-sort">Acoes</th>
                    </tr>
                </thead>
                <tbody id="tabelaResultados">
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <ion-icon name="filter-outline"></ion-icon>
                                </div>
                                <h3>Preencha ao menos um filtro</h3>
                                <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination-container" id="paginacao"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let dadosTabela = [];
let sortColumn = null;
let sortDirection = 'asc';
let paginaAtual = 1;
let totalPaginas = 0;
let totalRegistros = 0;

const permissoes = {
    podeEditar: <?= $podeEditar ? 'true' : 'false' ?>
};

const tiposEixoMap = {
    'H': { text: 'Horizontal', class: 'horizontal' },
    'V': { text: 'Vertical', class: 'vertical' }
};

$(document).ready(function() {
    $('.select2-custom').select2({
        width: '100%',
        placeholder: 'Selecione...',
        allowClear: true
    });

    $('#selectUnidade').on('change', function() {
        const cdUnidade = $(this).val();
        if (cdUnidade) {
            carregarLocalidades(cdUnidade);
        } else {
            $('#selectLocalidade').val('').prop('disabled', true)
                .html('<option value="">Selecione uma Unidade primeiro</option>')
                .trigger('change.select2');
        }
        buscarMotorBomba();
    });

    $('#selectLocalidade, #selectTipoEixo').on('change', function() {
        buscarMotorBomba();
    });

    $('#inputBusca').on('input', function() {
        $('#btnLimparBusca').toggle($(this).val().length > 0);
    });

    $('#inputBusca').on('keypress', function(e) {
        if (e.which === 13) buscarMotorBomba();
    });

    $('.data-table th[data-sort]').on('click', function() {
        const column = $(this).data('sort');
        ordenarTabela(column);
    });
});

function carregarLocalidades(cdUnidade) {
    $('#selectLocalidade').prop('disabled', true).html('<option value="">Carregando...</option>');
    
    $.ajax({
        url: 'bd/pontoMedicao/getLocalidades.php',
        method: 'GET',
        data: { cd_unidade: cdUnidade },
        dataType: 'json',
        success: function(response) {
            let options = '<option value="">Todas as Localidades</option>';
            if (response.success && response.data) {
                response.data.forEach(function(loc) {
                    options += `<option value="${loc.CD_CHAVE}">${loc.CD_LOCALIDADE} - ${loc.DS_NOME}</option>`;
                });
            }
            $('#selectLocalidade').html(options).prop('disabled', false).trigger('change.select2');
        },
        error: function() {
            $('#selectLocalidade').html('<option value="">Erro ao carregar</option>');
            showToast('Erro ao carregar localidades', 'erro');
        }
    });
}

function buscarMotorBomba(pagina = 1) {
    const cdUnidade = $('#selectUnidade').val() || '';
    const cdLocalidade = $('#selectLocalidade').val() || '';
    const tipoEixo = $('#selectTipoEixo').val() || '';
    const busca = $('#inputBusca').val().trim();

    const temFiltro = cdUnidade !== '' || cdLocalidade !== '' || tipoEixo !== '' || busca !== '';

    if (!temFiltro) {
        dadosTabela = [];
        paginaAtual = 1;
        totalPaginas = 0;
        totalRegistros = 0;
        $('#tabelaResultados').html(`
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <div class="empty-state-icon"><ion-icon name="filter-outline"></ion-icon></div>
                        <h3>Preencha ao menos um filtro</h3>
                        <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                    </div>
                </td>
            </tr>
        `);
        $('#totalRegistros').text('0');
        $('#paginacao').html('');
        return;
    }

    $('#loadingTabela').addClass('active');

    $.ajax({
        url: 'bd/motorBomba/getMotorBomba.php',
        method: 'GET',
        data: {
            cd_unidade: cdUnidade,
            cd_localidade: cdLocalidade,
            tipo_eixo: tipoEixo,
            busca: busca,
            pagina: pagina,
            ordenar_por: sortColumn,
            ordenar_direcao: sortDirection
        },
        dataType: 'json',
        success: function(response) {
            $('#loadingTabela').removeClass('active');
            if (response.success) {
                dadosTabela = response.data || [];
                paginaAtual = response.pagina;
                totalPaginas = response.totalPaginas;
                totalRegistros = response.total;
                $('#totalRegistros').text(totalRegistros);
                renderizarTabela(dadosTabela);
                renderizarPaginacao();
            } else {
                showToast(response.message || 'Erro ao buscar dados', 'erro');
            }
        },
        error: function() {
            $('#loadingTabela').removeClass('active');
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

function ordenarTabela(column) {
    document.querySelectorAll('.data-table th').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));

    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'asc';
    }

    const th = document.querySelector(`.data-table th[data-sort="${column}"]`);
    if (th) th.classList.add(sortDirection === 'asc' ? 'sorted-asc' : 'sorted-desc');

    buscarMotorBomba(1);
}

function renderizarPaginacao() {
    const container = $('#paginacao');
    if (totalPaginas <= 1) {
        container.html('');
        return;
    }

    let html = '<div class="pagination-buttons">';
    html += `<button onclick="irParaPagina(${paginaAtual - 1})" ${paginaAtual === 1 ? 'disabled' : ''}>
                <ion-icon name="chevron-back-outline"></ion-icon>
             </button>`;

    let inicio = Math.max(1, paginaAtual - 2);
    let fim = Math.min(totalPaginas, paginaAtual + 2);

    if (inicio > 1) {
        html += `<button onclick="irParaPagina(1)">1</button>`;
        if (inicio > 2) html += `<button disabled>...</button>`;
    }

    for (let i = inicio; i <= fim; i++) {
        html += `<button class="${i === paginaAtual ? 'active' : ''}" onclick="irParaPagina(${i})">${i}</button>`;
    }

    if (fim < totalPaginas) {
        if (fim < totalPaginas - 1) html += `<button disabled>...</button>`;
        html += `<button onclick="irParaPagina(${totalPaginas})">${totalPaginas}</button>`;
    }

    html += `<button onclick="irParaPagina(${paginaAtual + 1})" ${paginaAtual === totalPaginas ? 'disabled' : ''}>
                <ion-icon name="chevron-forward-outline"></ion-icon>
             </button>`;
    html += '</div>';

    const inicio_reg = ((paginaAtual - 1) * 20) + 1;
    const fim_reg = Math.min(paginaAtual * 20, totalRegistros);
    html += `<div class="page-info">Exibindo ${inicio_reg}-${fim_reg} de ${totalRegistros}</div>`;

    container.html(html);
}

function irParaPagina(pagina) {
    if (pagina < 1 || pagina > totalPaginas || pagina === paginaAtual) return;
    buscarMotorBomba(pagina);
    document.querySelector('.table-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderizarTabela(dados) {
    if (!dados || dados.length === 0) {
        $('#tabelaResultados').html(`
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <div class="empty-state-icon"><ion-icon name="file-tray-outline"></ion-icon></div>
                        <h3>Nenhum registro encontrado</h3>
                        <p>Tente ajustar os filtros de pesquisa</p>
                    </div>
                </td>
            </tr>
        `);
        return;
    }

    let html = '';
    dados.forEach(function(item) {
        const tipoEixo = tiposEixoMap[item.TP_EIXO] || { text: '-', class: '' };

        html += `
            <tr>
                <td class="truncate" title="${item.UNIDADE || ''}">${item.UNIDADE || '-'}</td>
                <td class="truncate" title="${item.LOCALIDADE || ''}">${item.LOCALIDADE || '-'}</td>
                <td class="code">${item.DS_CODIGO || '-'}</td>
                <td class="name" title="${item.DS_NOME || ''}">${item.DS_NOME || '-'}</td>
                <td>
                    <span class="badge badge-eixo ${tipoEixo.class}">${tipoEixo.text}</span>
                </td>
                <td>${item.VL_POTENCIA_MOTOR ? parseFloat(item.VL_POTENCIA_MOTOR).toFixed(2) : '-'}</td>
                <td>${item.VL_VAZAO_BOMBA ? parseFloat(item.VL_VAZAO_BOMBA).toFixed(2) : '-'}</td>
                <td>
                    <div class="actions-cell">
                        <button type="button" class="btn-action view" onclick="visualizar(${item.CD_CHAVE})" title="Visualizar">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                        ${permissoes.podeEditar ? `
                        <button type="button" class="btn-action edit" onclick="editar(${item.CD_CHAVE})" title="Editar">
                            <ion-icon name="create-outline"></ion-icon>
                        </button>
                        <button type="button" class="btn-action delete" onclick="excluir(${item.CD_CHAVE})" title="Excluir">
                            <ion-icon name="trash-outline"></ion-icon>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    $('#tabelaResultados').html(html);
}

function limparFiltros() {
    sortColumn = null;
    sortDirection = 'asc';
    document.querySelectorAll('.data-table th').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
    paginaAtual = 1;

    $('#selectUnidade').val('').trigger('change.select2');
    $('#selectLocalidade').val('').prop('disabled', true).html('<option value="">Selecione uma Unidade primeiro</option>').trigger('change.select2');
    $('#selectTipoEixo').val('').trigger('change.select2');
    $('#inputBusca').val('');
    $('#btnLimparBusca').hide();

    $('#tabelaResultados').html(`
        <tr>
            <td colspan="8">
                <div class="empty-state">
                    <div class="empty-state-icon"><ion-icon name="filter-outline"></ion-icon></div>
                    <h3>Preencha ao menos um filtro</h3>
                    <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                </div>
            </td>
        </tr>
    `);
    $('#totalRegistros').text('0');
    $('#paginacao').html('');
}

function limparBusca() {
    $('#inputBusca').val('');
    $('#btnLimparBusca').hide();
    buscarMotorBomba();
}

function visualizar(id) {
    if (!id || id === 'undefined' || id === 'null') {
        showToast('ID do registro não encontrado', 'erro');
        return;
    }
    window.location.href = `motorBombaView.php?id=${id}`;
}

function editar(id) {
    if (!id || id === 'undefined' || id === 'null') {
        showToast('ID do registro não encontrado', 'erro');
        return;
    }
    window.location.href = `motorBombaForm.php?id=${id}`;
}

function excluir(id) {
    if (!id || id === 'undefined' || id === 'null') {
        showToast('ID do registro não encontrado', 'erro');
        return;
    }
    if (confirm('Tem certeza que deseja excluir este conjunto motor bomba?')) {
        $.ajax({
            url: 'bd/motorBomba/excluirMotorBomba.php',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Registro excluido com sucesso', 'sucesso');
                    buscarMotorBomba(paginaAtual);
                } else {
                    showToast(response.message || 'Erro ao excluir', 'erro');
                }
            },
            error: function() {
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }
}
</script>

<?php include_once 'includes/footer.inc.php'; ?>