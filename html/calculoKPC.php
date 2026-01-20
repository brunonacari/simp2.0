<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Tela de Listagem de Cálculo de KPC
 * 
 * Permite consultar e gerenciar os cálculos de KPC (Constante Pitométrica)
 * realizados nas estações pitométricas.
 */

$paginaAtual = 'calculoKPC';

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para acessar Cálculo de KPC
$temPermissao = temPermissaoTela('Cálculo do KPC', ACESSO_LEITURA);
$podeEditar = temPermissaoTela('Cálculo do KPC', ACESSO_ESCRITA);

if (!$temPermissao) {
    $_SESSION['msg'] = 'Você não tem permissão para acessar esta funcionalidade.';
    header('Location: home.php');
    exit;
}

// Buscar Unidades para filtro
try {
    $sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, CD_CODIGO, DS_NOME FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
    $unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $unidades = [];
}

// Situações
$situacoes = [
    1 => 'Ativo',
    2 => 'Cancelado'
];

// Métodos
$metodos = [
    1 => 'Digital',
    2 => 'Convencional'
];
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* ============================================
       Select2 Customização (igual pontoMedicao.php)
       ============================================ */
    .select2-container {
        width: 100% !important;
    }

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
        margin-top: 4px;
        overflow: hidden;
    }

    .select2-container--default .select2-search--dropdown .select2-search__field {
        padding: 10px 14px;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .select2-container--default .select2-results__option {
        padding: 10px 14px;
        font-size: 13px;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #eff6ff;
        color: #3b82f6;
    }

    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #3b82f6;
        color: white;
    }

    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 24px;
        max-width: 1800px;
        margin: 0 auto;
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
        color: #3b82f6;
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
        gap: 16px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-label ion-icon {
        font-size: 14px;
        color: #94a3b8;
    }

    .form-control {
        width: 100%;
        height: 44px;
        padding: 10px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        color: #334155;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-control::placeholder {
        color: #94a3b8;
    }

    .filters-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .btn-buscar {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-buscar:hover {
        background: #2563eb;
    }

    .btn-buscar ion-icon {
        font-size: 18px;
    }

    .btn-limpar {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: #f1f5f9;
        color: #475569;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-limpar:hover {
        background: #e2e8f0;
    }

    /* ============================================
       Autocomplete Ponto de Medição
       ============================================ */
    .autocomplete-container {
        position: relative;
    }

    .autocomplete-container input.form-control {
        padding-right: 35px;
    }

    .autocomplete-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 10px 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .autocomplete-dropdown.active {
        display: block;
    }

    .autocomplete-item {
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .autocomplete-item:last-child {
        border-bottom: none;
    }

    .autocomplete-item:hover,
    .autocomplete-item.highlighted {
        background-color: #3b82f6;
        color: white;
    }

    .autocomplete-item .item-code {
        font-family: 'SF Mono', Monaco, monospace;
        font-size: 12px;
        color: #64748b;
    }

    .autocomplete-item:hover .item-code,
    .autocomplete-item.highlighted .item-code {
        color: rgba(255, 255, 255, 0.8);
    }

    .autocomplete-item .item-name {
        display: block;
        margin-top: 2px;
    }

    .autocomplete-loading,
    .autocomplete-empty {
        padding: 12px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    .btn-limpar-autocomplete {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .btn-limpar-autocomplete:hover {
        color: #ef4444;
    }

    .btn-limpar-autocomplete ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Tabela de Dados
       ============================================ */
    .table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .table-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .table-info {
        font-size: 13px;
        color: #64748b;
    }

    .table-info strong {
        color: #1e293b;
    }

    .table-actions {
        display: flex;
        gap: 8px;
    }

    .btn-cancelar-selecionados {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-cancelar-selecionados:hover {
        background: #fee2e2;
    }

    .btn-cancelar-selecionados:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .table-container {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        padding: 14px 16px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }

    .data-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
        color: #334155;
        vertical-align: middle;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    .data-table tbody tr.cancelado {
        background: #fef2f2;
        color: #991b1b;
    }

    .data-table tbody tr.cancelado td {
        color: #991b1b;
    }

    .data-table th:first-child,
    .data-table td:first-child {
        width: 40px;
        text-align: center;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge-ativo {
        background: #dcfce7;
        color: #166534;
    }

    .badge-cancelado {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-metodo {
        background: #e0e7ff;
        color: #3730a3;
    }

    .acoes-cell {
        display: flex;
        gap: 6px;
    }

    .btn-acao {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }

    .btn-acao.visualizar {
        background: #eff6ff;
        color: #3b82f6;
    }

    .btn-acao.visualizar:hover {
        background: #dbeafe;
    }

    .btn-acao.editar {
        background: #fef3c7;
        color: #d97706;
    }

    .btn-acao.editar:hover {
        background: #fde68a;
    }

    /* ============================================
       Paginação
       ============================================ */
    .pagination-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;
        border-top: 1px solid #e2e8f0;
    }

    .pagination-info {
        font-size: 13px;
        color: #64748b;
    }

    .pagination {
        display: flex;
        gap: 4px;
    }

    .pagination button {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .pagination button:hover:not(:disabled) {
        background: #f8fafc;
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .pagination button.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }

    .pagination button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ============================================
       Loading e Empty States
       ============================================ */
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        z-index: 9999;
        align-items: center;
        justify-content: center;
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
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .empty-state ion-icon {
        font-size: 48px;
        color: #cbd5e1;
        margin-bottom: 16px;
    }

    .empty-state h3 {
        font-size: 16px;
        color: #475569;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 13px;
        margin: 0;
    }

    /* ============================================
       Responsivo
       ============================================ */
    @media (max-width: 1200px) {
        .filters-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 900px) {
        .filters-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 16px;
        }

        .page-header {
            padding: 20px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .btn-novo {
            width: 100%;
            justify-content: center;
        }

        .filters-card {
            padding: 16px;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .filters-actions {
            flex-direction: column;
        }

        .btn-buscar,
        .btn-limpar {
            width: 100%;
            justify-content: center;
        }

        .table-header {
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }

        .pagination-container {
            flex-direction: column;
            gap: 12px;
            text-align: center;
        }
    }

    /* ============================================
       Toast Styles
       ============================================ */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        min-width: 300px;
        max-width: 400px;
        transform: translateX(120%);
        transition: transform 0.3s ease;
    }

    .toast.show {
        transform: translateX(0);
    }

    .toast-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .toast.sucesso {
        border-left: 4px solid #10b981;
    }

    .toast.sucesso .toast-icon {
        color: #10b981;
    }

    .toast.erro {
        border-left: 4px solid #ef4444;
    }

    .toast.erro .toast-icon {
        color: #ef4444;
    }

    .toast.alerta {
        border-left: 4px solid #f59e0b;
    }

    .toast.alerta .toast-icon {
        color: #f59e0b;
    }

    .toast.info {
        border-left: 4px solid #3b82f6;
    }

    .toast.info .toast-icon {
        color: #3b82f6;
    }

    .toast-content {
        flex: 1;
    }

    .toast-message {
        margin: 0;
        font-size: 14px;
        color: #1e293b;
    }

    .toast-close {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        color: #94a3b8;
        font-size: 18px;
    }

    .toast-close:hover {
        color: #475569;
    }
</style>

<div class="page-container">
    <!-- Header da Página -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="calculator-outline"></ion-icon>
                </div>
                <div>
                    <h1>Cálculo de KPC</h1>
                    <p class="page-header-subtitle">Gerenciamento de cálculos da constante pitométrica</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
                <a href="calculoKPCForm.php" class="btn-novo">
                    <ion-icon name="add-outline"></ion-icon>
                    Novo Cálculo
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
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
            <!-- Unidade -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="selectUnidade" class="form-control select2-unidade">
                    <option value="">Todas as Unidades</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Localidade -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="location-outline"></ion-icon>
                    Localidade
                </label>
                <select id="selectLocalidade" class="form-control select2-localidade" disabled>
                    <option value="">Selecione uma Unidade primeiro</option>
                </select>
            </div>

            <!-- Ponto de Medição (Autocomplete) -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="pin-outline"></ion-icon>
                    Ponto de Medição
                </label>
                <div class="autocomplete-container">
                    <input type="text" class="form-control" id="filtroPontoMedicaoInput"
                        placeholder="Digite para buscar..." autocomplete="off">
                    <input type="hidden" id="filtroPontoMedicao">
                    <button type="button" class="btn-limpar-autocomplete" id="btnLimparPonto"
                        onclick="limparPontoMedicao()">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                    <div class="autocomplete-dropdown" id="filtroPontoMedicaoDropdown"></div>
                </div>
            </div>

            <!-- Situação -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                    Situação
                </label>
                <select id="filtroSituacao" class="form-control select2-default">
                    <option value="">Todas</option>
                    <?php foreach ($situacoes as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Método -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="options-outline"></ion-icon>
                    Método
                </label>
                <select id="filtroMetodo" class="form-control select2-default">
                    <option value="">Todos</option>
                    <?php foreach ($metodos as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Código Inicial -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="code-outline"></ion-icon>
                    Código Inicial
                </label>
                <input type="number" class="form-control" id="filtroCodigoInicial" placeholder="000">
            </div>

            <!-- Ano Inicial -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Ano Inicial
                </label>
                <input type="text" class="form-control" id="filtroAnoInicial" placeholder="AA" maxlength="2">
            </div>

            <!-- Código Final -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="code-outline"></ion-icon>
                    Código Final
                </label>
                <input type="number" class="form-control" id="filtroCodigoFinal" placeholder="999">
            </div>

            <!-- Ano Final -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Ano Final
                </label>
                <input type="text" class="form-control" id="filtroAnoFinal" placeholder="AA" maxlength="2">
            </div>

            <!-- Data Inicial -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-number-outline"></ion-icon>
                    Data Inicial
                </label>
                <input type="date" class="form-control" id="filtroDataInicial">
            </div>

            <!-- Data Final -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-number-outline"></ion-icon>
                    Data Final
                </label>
                <input type="date" class="form-control" id="filtroDataFinal">
            </div>
        </div>

        <div class="filters-actions">
            <button type="button" class="btn-limpar" onclick="limparFiltros()">
                <ion-icon name="refresh-outline"></ion-icon>
                Limpar
            </button>
            <button type="button" class="btn-buscar" onclick="filtrar()">
                <ion-icon name="search-outline"></ion-icon>
                Buscar
            </button>
        </div>
    </div>

    <!-- Tabela de Dados -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-info">
                <strong id="totalRegistros">0</strong> registro(s) encontrado(s)
            </div>
            <div class="table-actions">
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-cancelar-selecionados" id="btnCancelarSelecionados" disabled
                        onclick="cancelarSelecionados()">
                        <ion-icon name="close-circle-outline"></ion-icon>
                        Cancelar Selecionados
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="checkAll" onchange="toggleCheckAll()"></th>
                        <th>Unidade</th>
                        <th>Localidade</th>
                        <th>Ponto Medição</th>
                        <th>Código</th>
                        <th>Situação</th>
                        <th>Data Leitura</th>
                        <th>Método</th>
                        <th>KPC</th>
                        <th>Vazão (L/s)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaBody">
                    <!-- Preenchido via JavaScript -->
                </tbody>
            </table>
        </div>
        <div class="pagination-container">
            <div class="pagination-info">
                Mostrando <span id="paginacaoInicio">0</span> a <span id="paginacaoFim">0</span> de <span
                    id="paginacaoTotal">0</span>
            </div>
            <div class="pagination" id="paginacao"></div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // ============================================
    // Variáveis Globais
    // ============================================
    let paginaAtualKPC = 1;
    const registrosPorPagina = 20;
    let totalRegistros = 0;
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;
    let buscaPontoTimeout;

    // ============================================
    // Inicialização Select2 (igual pontoMedicao.php)
    // ============================================
    $(document).ready(function () {
        // Select2 para Unidade
        $('.select2-unidade').select2({
            placeholder: 'Digite para buscar...',
            allowClear: true,
            language: {
                noResults: function () { return "Nenhuma unidade encontrada"; },
                searching: function () { return "Buscando..."; }
            }
        });

        // Select2 para Localidade
        $('.select2-localidade').select2({
            placeholder: 'Selecione uma Unidade primeiro',
            allowClear: true,
            language: {
                noResults: function () { return "Nenhuma localidade encontrada"; }
            }
        });

        // Select2 para dropdowns simples
        $('.select2-default').select2({
            minimumResultsForSearch: Infinity
        });

        // Events
        $('#selectUnidade').on('change', function () {
            carregarLocalidades($(this).val());
            limparPontoMedicao();
        });

        $('#selectLocalidade').on('change', function () {
            limparPontoMedicao();
        });

        // Inicializa autocomplete de Ponto de Medição
        initAutocompletePontoMedicao();

        // Carrega dados iniciais
        filtrar();
    });

    // ============================================
    // Carregar Localidades via AJAX (igual pontoMedicao.php)
    // ============================================
    function carregarLocalidades(cdUnidade) {
        const select = $('#selectLocalidade');

        if (!cdUnidade) {
            select.prop('disabled', true);
            select.html('<option value="">Selecione uma Unidade primeiro</option>');
            select.trigger('change');
            return;
        }

        select.prop('disabled', true);
        select.html('<option value="">Carregando...</option>');

        $.ajax({
            url: 'bd/pontoMedicao/getLocalidades.php',
            type: 'GET',
            data: { cd_unidade: cdUnidade },
            dataType: 'json',
            success: function (response) {
                let options = '<option value="">Todas as Localidades</option>';

                if (response.success && response.data.length > 0) {
                    response.data.forEach(function (item) {
                        options += `<option value="${item.CD_CHAVE}">${item.CD_LOCALIDADE} - ${item.DS_NOME}</option>`;
                    });
                }

                select.html(options);
                select.prop('disabled', false);
                select.trigger('change.select2');
            },
            error: function () {
                select.html('<option value="">Erro ao carregar</option>');
                showToast('Erro ao carregar localidades', 'erro');
            }
        });
    }

    // ============================================
    // Autocomplete Ponto de Medição
    // ============================================
    function initAutocompletePontoMedicao() {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        let highlightedIndex = -1;

        input.addEventListener('input', function () {
            clearTimeout(buscaPontoTimeout);
            const termo = this.value.trim();

            if (termo.length < 2) {
                dropdown.classList.remove('active');
                return;
            }

            buscaPontoTimeout = setTimeout(() => {
                buscarPontosMedicaoAutocomplete(termo);
            }, 300);
        });

        input.addEventListener('focus', function () {
            if (this.value.trim().length >= 2) {
                dropdown.classList.add('active');
            }
        });

        input.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.autocomplete-item');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
                updateHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = Math.max(highlightedIndex - 1, 0);
                updateHighlight(items);
            } else if (e.key === 'Enter' && highlightedIndex >= 0) {
                e.preventDefault();
                if (items[highlightedIndex]) {
                    selecionarPontoMedicao(
                        items[highlightedIndex].dataset.value,
                        items[highlightedIndex].dataset.label
                    );
                }
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        function updateHighlight(items) {
            items.forEach((item, index) => {
                item.classList.toggle('highlighted', index === highlightedIndex);
            });
        }

        // Fecha dropdown ao clicar fora
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    }

    function buscarPontosMedicaoAutocomplete(termo) {
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const cdLocalidade = $('#selectLocalidade').val() || '';
        const cdUnidade = $('#selectUnidade').val() || '';

        dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        dropdown.classList.add('active');

        // Mapeamento de letras por tipo de medidor
        const letrasTipoMedidor = {
            1: 'M', // Macromedidor
            2: 'E', // Estação Pitométrica
            4: 'P', // Medidor Pressão
            6: 'R', // Nível Reservatório
            8: 'H'  // Hidrômetro
        };

        // Usa o endpoint buscarPontosMedicao.php (mesmo das outras telas)
        const params = new URLSearchParams({ busca: termo });
        if (cdUnidade) params.append('cd_unidade', cdUnidade);
        if (cdLocalidade) params.append('cd_localidade', cdLocalidade);

        fetch('bd/pontoMedicao/buscarPontosMedicao.php?' + params)
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success && data.data && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(function (item) {
                        const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                        const codigoPonto = item.CD_LOCALIDADE + '-' +
                            String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                            letraTipo + '-' +
                            (item.CD_UNIDADE_CODIGO || item.CD_UNIDADE);
                        html += '<div class="autocomplete-item" ' +
                            'data-value="' + item.CD_PONTO_MEDICAO + '" ' +
                            'data-label="' + codigoPonto + ' - ' + item.DS_NOME + '">' +
                            '<span class="item-code">' + codigoPonto + '</span>' +
                            '<span class="item-name">' + item.DS_NOME + '</span>' +
                            '</div>';
                    });
                    dropdown.innerHTML = html;

                    // Adiciona eventos de clique
                    dropdown.querySelectorAll('.autocomplete-item').forEach(function (item) {
                        item.addEventListener('click', function () {
                            selecionarPontoMedicao(this.dataset.value, this.dataset.label);
                        });
                    });
                } else {
                    dropdown.innerHTML = '<div class="autocomplete-empty">Nenhum ponto encontrado</div>';
                }
            })
            .catch(function (error) {
                console.error('Erro ao buscar pontos:', error);
                dropdown.innerHTML = '<div class="autocomplete-empty">Erro ao buscar</div>';
            });
    }

    function selecionarPontoMedicao(value, label) {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        input.value = label;
        hidden.value = value;
        dropdown.classList.remove('active');
        btnLimpar.style.display = 'flex';
    }

    function limparPontoMedicao() {
        document.getElementById('filtroPontoMedicaoInput').value = '';
        document.getElementById('filtroPontoMedicao').value = '';
        document.getElementById('btnLimparPonto').style.display = 'none';
    }

    // ============================================
    // Funções de Filtro
    // ============================================
    function limparFiltros() {
        $('#selectUnidade').val('').trigger('change');
        $('#selectLocalidade').prop('disabled', true).html('<option value="">Selecione uma Unidade primeiro</option>').trigger('change');
        limparPontoMedicao();
        $('#filtroSituacao').val('').trigger('change');
        $('#filtroMetodo').val('').trigger('change');

        document.getElementById('filtroCodigoInicial').value = '';
        document.getElementById('filtroAnoInicial').value = '';
        document.getElementById('filtroCodigoFinal').value = '';
        document.getElementById('filtroAnoFinal').value = '';
        document.getElementById('filtroDataInicial').value = '';
        document.getElementById('filtroDataFinal').value = '';

        paginaAtualKPC = 1;
        filtrar();
    }

    // ============================================
    // Função Principal de Filtrar
    // ============================================
    function filtrar() {
        mostrarLoading(true);

        const params = new URLSearchParams({
            pagina: paginaAtualKPC,
            limite: registrosPorPagina,
            unidade: $('#selectUnidade').val() || '',
            localidade: $('#selectLocalidade').val() || '',
            ponto: document.getElementById('filtroPontoMedicao').value || '',
            situacao: $('#filtroSituacao').val() || '',
            metodo: $('#filtroMetodo').val() || '',
            codigoInicial: document.getElementById('filtroCodigoInicial').value,
            anoInicial: document.getElementById('filtroAnoInicial').value,
            codigoFinal: document.getElementById('filtroCodigoFinal').value,
            anoFinal: document.getElementById('filtroAnoFinal').value,
            dataInicial: document.getElementById('filtroDataInicial').value,
            dataFinal: document.getElementById('filtroDataFinal').value
        });

        $.ajax({
            url: `bd/calculoKPC/listarCalculoKPC.php?${params}`,
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                mostrarLoading(false);
                if (data.success) {
                    totalRegistros = data.total;
                    renderizarTabela(data.data);
                    atualizarPaginacao();
                } else {
                    showToast('Erro ao carregar dados: ' + data.message, 'erro');
                }
            },
            error: function (xhr, status, error) {
                mostrarLoading(false);
                console.error('Erro:', error);
                showToast('Erro ao carregar dados', 'erro');
            }
        });
    }

    // ============================================
    // Renderização da Tabela (ATUALIZADA)
    // ============================================
    function renderizarTabela(dados) {
        const tbody = document.getElementById('tabelaBody');
        document.getElementById('totalRegistros').textContent = totalRegistros;

        if (!dados || dados.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="11">
                    <div class="empty-state">
                        <ion-icon name="document-outline"></ion-icon>
                        <h3>Nenhum registro encontrado</h3>
                        <p>Ajuste os filtros ou cadastre um novo cálculo de KPC.</p>
                    </div>
                </td>
            </tr>
        `;
            return;
        }

        let html = '';
        dados.forEach(function (item) {
            const isCancelado = item.ID_SITUACAO == 2;
            const metodo = item.ID_METODO == 1 ? 'Digital' : (item.ID_METODO == 2 ? 'Convencional' : '-');
            const codigo = item.CD_CODIGO + '-' + item.CD_ANO;
            const dataLeitura = item.DT_LEITURA ? formatarData(item.DT_LEITURA) : '-';
            const kpc = item.VL_KPC ? parseFloat(item.VL_KPC).toFixed(10) : '-';
            const vazao = item.VL_VAZAO ? parseFloat(item.VL_VAZAO).toFixed(2) : '-';

            // MUDANÇA: Construir código composto do ponto (LOCALIDADE-CD_PONTO-LETRA-CD_UNIDADE)
            const letrasTipo = { 1: 'M', 2: 'E', 4: 'P', 6: 'R', 8: 'H' };
            const letraTipo = letrasTipo[item.ID_TIPO_MEDIDOR] || 'X';
            const codigoPonto = item.CD_LOCALIDADE + '-' +
                String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                letraTipo + '-' +
                item.CD_UNIDADE;

            html += `
            <tr class="${isCancelado ? 'cancelado' : ''}">
                <td>
                    <input type="checkbox" class="checkItem" value="${item.CD_CHAVE}" 
                           onchange="atualizarBotaoCancelar()" ${isCancelado ? 'disabled' : ''}>
                </td>
                <td>${item.DS_UNIDADE || '-'}</td>
                <td>${item.DS_LOCALIDADE || '-'}</td>
                <td>${codigoPonto}</td>
                <td><strong>${codigo}</strong></td>
                <td>
                    <span class="badge ${isCancelado ? 'badge-cancelado' : 'badge-ativo'}">
                        ${isCancelado ? 'Cancelado' : 'Ativo'}
                    </span>
                </td>
                <td>${dataLeitura}</td>
                <td>${metodo}</td>
                <td>${kpc}</td>
                <td>${vazao}</td>
                <td>
                    <div class="acoes-cell">
                        <a href="calculoKPCView.php?id=${item.CD_CHAVE}" class="btn-acao visualizar" title="Visualizar">
                            <ion-icon name="eye-outline"></ion-icon>
                        </a>
                        ${podeEditar && !isCancelado ? `
                        <a href="calculoKPCForm.php?id=${item.CD_CHAVE}" class="btn-acao editar" title="Editar">
                            <ion-icon name="create-outline"></ion-icon>
                        </a>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
        });

        tbody.innerHTML = html;
    }

    // ============================================
    // Paginação
    // ============================================
    function atualizarPaginacao() {
        const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
        const inicio = (paginaAtualKPC - 1) * registrosPorPagina + 1;
        const fim = Math.min(paginaAtualKPC * registrosPorPagina, totalRegistros);

        document.getElementById('paginacaoInicio').textContent = totalRegistros > 0 ? inicio : 0;
        document.getElementById('paginacaoFim').textContent = fim;
        document.getElementById('paginacaoTotal').textContent = totalRegistros;

        const paginacao = document.getElementById('paginacao');
        let html = '';

        html += `<button onclick="irParaPagina(${paginaAtualKPC - 1})" ${paginaAtualKPC === 1 ? 'disabled' : ''}>
        <ion-icon name="chevron-back-outline"></ion-icon>
    </button>`;

        const maxPaginas = 5;
        let startPage = Math.max(1, paginaAtualKPC - Math.floor(maxPaginas / 2));
        let endPage = Math.min(totalPaginas, startPage + maxPaginas - 1);

        if (endPage - startPage < maxPaginas - 1) {
            startPage = Math.max(1, endPage - maxPaginas + 1);
        }

        if (startPage > 1) {
            html += `<button onclick="irParaPagina(1)">1</button>`;
            if (startPage > 2) html += `<button disabled>...</button>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button onclick="irParaPagina(${i})" class="${i === paginaAtualKPC ? 'active' : ''}">${i}</button>`;
        }

        if (endPage < totalPaginas) {
            if (endPage < totalPaginas - 1) html += `<button disabled>...</button>`;
            html += `<button onclick="irParaPagina(${totalPaginas})">${totalPaginas}</button>`;
        }

        html += `<button onclick="irParaPagina(${paginaAtualKPC + 1})" ${paginaAtualKPC === totalPaginas || totalPaginas === 0 ? 'disabled' : ''}>
        <ion-icon name="chevron-forward-outline"></ion-icon>
    </button>`;

        paginacao.innerHTML = html;
    }

    function irParaPagina(pagina) {
        const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
        if (pagina >= 1 && pagina <= totalPaginas) {
            paginaAtualKPC = pagina;
            filtrar();
        }
    }

    // ============================================
    // Seleção e Cancelamento
    // ============================================
    function toggleCheckAll() {
        const checkAll = document.getElementById('checkAll');
        document.querySelectorAll('.checkItem:not(:disabled)').forEach(function (cb) {
            cb.checked = checkAll.checked;
        });
        atualizarBotaoCancelar();
    }

    function atualizarBotaoCancelar() {
        const selecionados = document.querySelectorAll('.checkItem:checked').length;
        const btn = document.getElementById('btnCancelarSelecionados');
        if (btn) {
            btn.disabled = selecionados === 0;
        }
    }

    function cancelarSelecionados() {
        const selecionados = [];
        document.querySelectorAll('.checkItem:checked').forEach(function (cb) {
            selecionados.push(cb.value);
        });

        if (selecionados.length === 0) {
            showToast('Selecione ao menos um registro', 'alerta');
            return;
        }

        if (!confirm('Deseja realmente cancelar ' + selecionados.length + ' registro(s)?')) {
            return;
        }

        mostrarLoading(true);

        $.ajax({
            url: 'bd/calculoKPC/cancelarCalculoKPC.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ ids: selecionados }),
            dataType: 'json',
            success: function (data) {
                mostrarLoading(false);
                if (data.success) {
                    showToast('Registros cancelados com sucesso!', 'sucesso');
                    filtrar();
                } else {
                    showToast('Erro: ' + data.message, 'erro');
                }
            },
            error: function () {
                mostrarLoading(false);
                showToast('Erro ao cancelar registros', 'erro');
            }
        });
    }

    // ============================================
    // Utilitários
    // ============================================
    function mostrarLoading(show) {
        document.getElementById('loadingOverlay').classList.toggle('active', show);
    }

    function formatarData(dataStr) {
        if (!dataStr) return '-';
        const data = new Date(dataStr);
        return data.toLocaleDateString('pt-BR');
    }

    // ============================================
    // Toast System
    // ============================================
    function showToast(message, type, duration) {
        type = type || 'info';
        duration = duration || 5000;

        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const icons = {
            sucesso: 'checkmark-circle',
            erro: 'close-circle',
            alerta: 'alert-circle',
            info: 'information-circle'
        };

        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML =
            '<div class="toast-icon">' +
            '<ion-icon name="' + (icons[type] || icons.info) + '"></ion-icon>' +
            '</div>' +
            '<div class="toast-content">' +
            '<p class="toast-message">' + message + '</p>' +
            '</div>' +
            '<button class="toast-close" onclick="this.parentElement.remove()">' +
            '<ion-icon name="close"></ion-icon>' +
            '</button>';

        container.appendChild(toast);
        setTimeout(function () { toast.classList.add('show'); }, 10);
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 300);
        }, duration);
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>