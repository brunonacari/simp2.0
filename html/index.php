<?php
/**
 * SIMP - Sistema Integrado de Macromedicao e Pitometria
 * Dashboard de Metricas IA
 * 
 * Exibe metricas consolidadas da tabela IA_METRICAS_DIARIAS
 * Data base: MAX(DT_REFERENCIA) da tabela (nao GETDATE)
 * 
 * @author Bruno
 * @version 1.0
 * @date 2026-01-22
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// exigePermissaoTela('Análise dos Dados', ACESSO_LEITURA);

// Verifica permissao de acesso

// exigePermissaoTela('DASHBOARD METRICAS IA', ACESSO_LEITURA);

// Buscar unidades para filtro
$sqlUnidades = $pdoSIMP->query("
    SELECT DISTINCT U.CD_UNIDADE, U.DS_NOME 
    FROM SIMP.dbo.UNIDADE U
    INNER JOIN SIMP.dbo.LOCALIDADE L ON U.CD_UNIDADE = L.CD_UNIDADE
    INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON L.CD_CHAVE = PM.CD_LOCALIDADE
    ORDER BY U.DS_NOME
");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar tipos de medidor
$tiposMedidor = [
    1 => 'Macromedidor',
    2 => 'Est. Pitometrica',
    4 => 'Pressao',
    6 => 'Nivel',
    8 => 'Hidrometro'
];

// Buscar ultima data disponivel
$sqlUltimaData = $pdoSIMP->query("SELECT MAX(DT_REFERENCIA) AS ULTIMA_DATA FROM SIMP.dbo.IA_METRICAS_DIARIAS");
$ultimaData = $sqlUltimaData->fetch(PDO::FETCH_ASSOC)['ULTIMA_DATA'] ?? date('Y-m-d');
?>

<style>
    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 24px;
        max-width: 1600px;
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

    .header-data-ref {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: white;
    }

    .header-data-ref ion-icon {
        font-size: 24px;
    }

    .header-data-ref-label {
        font-size: 11px;
        opacity: 0.8;
        display: block;
    }

    .header-data-ref-value {
        font-size: 14px;
        font-weight: 600;
        display: block;
    }

    /* ============================================
       Stats Cards
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .stat-card.primary::before {
        background: linear-gradient(90deg, #3b82f6, #60a5fa);
    }

    .stat-card.success::before {
        background: linear-gradient(90deg, #10b981, #34d399);
    }

    .stat-card.warning::before {
        background: linear-gradient(90deg, #f59e0b, #fbbf24);
    }

    .stat-card.danger::before {
        background: linear-gradient(90deg, #ef4444, #f87171);
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .stat-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .stat-card.primary .stat-card-icon {
        background: #eff6ff;
        color: #3b82f6;
    }

    .stat-card.success .stat-card-icon {
        background: #ecfdf5;
        color: #10b981;
    }

    .stat-card.warning .stat-card-icon {
        background: #fffbeb;
        color: #f59e0b;
    }

    .stat-card.danger .stat-card-icon {
        background: #fef2f2;
        color: #ef4444;
    }

    .stat-card-value {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-card-label {
        font-size: 13px;
        color: #64748b;
    }

    .stat-card-trend {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        margin-top: 8px;
    }

    .stat-card-trend.up {
        color: #10b981;
    }

    .stat-card-trend.down {
        color: #ef4444;
    }

    .stat-card-trend.neutral {
        color: #64748b;
    }

    /* ============================================
       Filtros
       ============================================ */
    .filtros-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 24px;
    }

    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        align-items: end;
    }

    .filtro-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filtro-group label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filtro-group select,
    .filtro-group input {
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        color: #1e293b;
        background: #ffffff;
        transition: border-color 0.2s;
    }

    .filtro-group select:focus,
    .filtro-group input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-filtrar {
        padding: 10px 20px;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-filtrar:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
    }

    /* ============================================
       Content Card
       ============================================ */
    .content-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 24px;
    }

    .content-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .content-card-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }

    .content-card-title ion-icon {
        font-size: 18px;
        color: #3b82f6;
    }

    .content-card-body {
        padding: 0;
    }

    /* ============================================
       Tabela de Dados
       ============================================ */
    .tabela-container {
        overflow-x: auto;
    }

    .tabela-metricas {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .tabela-metricas th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        position: sticky;
        top: 0;
    }

    .tabela-metricas td {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        vertical-align: middle;
    }

    .tabela-metricas tbody tr:hover {
        background: #f8fafc;
    }

    .tabela-metricas tbody tr.critico {
        background: #fef2f2;
    }

    .tabela-metricas tbody tr.atencao {
        background: #fffbeb;
    }

    /* Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 100px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.ok {
        background: #ecfdf5;
        color: #059669;
    }

    .status-badge.atencao {
        background: #fffbeb;
        color: #d97706;
    }

    .status-badge.critico {
        background: #fef2f2;
        color: #dc2626;
    }

    /* Cobertura */
    .cobertura-bar {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cobertura-bar-track {
        flex: 1;
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        min-width: 60px;
    }

    .cobertura-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s;
    }

    .cobertura-bar-fill.alta {
        background: #10b981;
    }

    .cobertura-bar-fill.media {
        background: #f59e0b;
    }

    .cobertura-bar-fill.baixa {
        background: #ef4444;
    }

    .cobertura-value {
        font-size: 12px;
        font-weight: 600;
        min-width: 40px;
        text-align: right;
    }

    /* Ponto Info */
    .ponto-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .ponto-nome {
        font-weight: 600;
        color: #1e293b;
    }

    .ponto-tipo {
        font-size: 11px;
        color: #64748b;
    }

    /* Tendencia */
    .tendencia {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
    }

    .tendencia.subindo {
        color: #10b981;
    }

    .tendencia.descendo {
        color: #ef4444;
    }

    .tendencia.estavel {
        color: #64748b;
    }

    /* Flags */
    .flags-container {
        display: flex;
        gap: 4px;
    }

    .flag-icon {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .flag-icon.ativo {
        background: #fef2f2;
        color: #dc2626;
    }

    .flag-icon.inativo {
        background: #f1f5f9;
        color: #cbd5e1;
    }

    /* Acoes */
    .btn-acao {
        padding: 6px 10px;
        background: #eff6ff;
        color: #3b82f6;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        transition: background 0.2s;
    }

    .btn-acao:hover {
        background: #dbeafe;
    }

    /* ============================================
       Dashboard Grid
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }

    @media (max-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ============================================
       Grafico Container
       ============================================ */
    .grafico-container {
        padding: 20px;
        min-height: 300px;
    }

    /* ============================================
       Lista Ranking
       ============================================ */
    .ranking-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .ranking-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }

    .ranking-item:hover {
        background: #f8fafc;
    }

    .ranking-item:last-child {
        border-bottom: none;
    }

    .ranking-position {
        width: 28px;
        height: 28px;
        background: #f1f5f9;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
    }

    .ranking-item:nth-child(1) .ranking-position {
        background: #fef3c7;
        color: #d97706;
    }

    .ranking-item:nth-child(2) .ranking-position {
        background: #e2e8f0;
        color: #475569;
    }

    .ranking-item:nth-child(3) .ranking-position {
        background: #fed7aa;
        color: #c2410c;
    }

    .ranking-info {
        flex: 1;
        min-width: 0;
    }

    .ranking-nome {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ranking-detalhe {
        font-size: 11px;
        color: #64748b;
    }

    .ranking-valor {
        font-size: 14px;
        font-weight: 700;
    }

    .ranking-valor.critico {
        color: #dc2626;
    }

    .ranking-valor.atencao {
        color: #d97706;
    }

    .ranking-valor.ok {
        color: #059669;
    }

    /* ============================================
       Loading e Empty State
       ============================================ */
    .loading-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .loading-container ion-icon {
        font-size: 48px;
        margin-bottom: 16px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .empty-state ion-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 16px;
        font-weight: 600;
        color: #475569;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 13px;
        margin: 0;
    }

    /* ============================================
       Select2 Customizacao
       ============================================ */
    .select2-container--default .select2-selection--single {
        height: 40px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px;
        padding-left: 12px;
        color: #1e293b;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px;
    }

    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: #3b82f6;
    }

    /* ============================================
       Responsividade
       ============================================ */
    @media (max-width: 768px) {
        .page-container {
            padding: 16px;
        }

        .page-header {
            padding: 20px;
            border-radius: 12px;
        }

        .page-header-content {
            flex-direction: column;
            text-align: center;
        }

        .page-header-info {
            flex-direction: column;
        }

        .filtros-grid {
            grid-template-columns: 1fr;
        }

        .content-card-header {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }

        .tabela-metricas th,
        .tabela-metricas td {
            padding: 10px 12px;
        }
    }
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="analytics-outline"></ion-icon>
                </div>
                <div>
                    <h1>Dashboard Metricas IA</h1>
                    <p class="page-header-subtitle">Monitoramento inteligente dos pontos de medicao</p>
                </div>
            </div>
            <div class="header-data-ref">
                <ion-icon name="calendar-outline"></ion-icon>
                <div>
                    <span class="header-data-ref-label">Ultima atualizacao</span>
                    <span class="header-data-ref-value"
                        id="dataReferencia"><?= date('d/m/Y', strtotime($ultimaData)) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card primary" onclick="filtrarPorStatus('')">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="speedometer-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statTotal">-</div>
            <div class="stat-card-label">Total de Pontos</div>
            <div class="stat-card-trend neutral" id="statTotalTrend">
                <ion-icon name="remove-outline"></ion-icon>
                <span>Carregando...</span>
            </div>
        </div>

        <div class="stat-card success" onclick="filtrarPorStatus('OK')">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statOk">-</div>
            <div class="stat-card-label">Status OK</div>
            <div class="stat-card-trend up" id="statOkTrend">
                <ion-icon name="trending-up-outline"></ion-icon>
                <span>-</span>
            </div>
        </div>

        <div class="stat-card warning" onclick="filtrarPorStatus('ATENCAO')">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statAtencao">-</div>
            <div class="stat-card-label">Atencao</div>
            <div class="stat-card-trend neutral" id="statAtencaoTrend">
                <ion-icon name="remove-outline"></ion-icon>
                <span>-</span>
            </div>
        </div>

        <div class="stat-card danger" onclick="filtrarPorStatus('CRITICO')">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="warning-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statCritico">-</div>
            <div class="stat-card-label">Criticos</div>
            <div class="stat-card-trend down" id="statCriticoTrend">
                <ion-icon name="trending-down-outline"></ion-icon>
                <span>-</span>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-card">
        <div class="filtros-grid">
            <div class="filtro-group">
                <label>Periodo</label>
                <select id="filtroPeriodo">
                    <option value="1">Ultimo dia</option>
                    <option value="7" selected>Ultimos 7 dias</option>
                    <option value="15">Ultimos 15 dias</option>
                    <option value="30">Ultimos 30 dias</option>
                </select>
            </div>

            <div class="filtro-group">
                <label>Unidade</label>
                <select id="filtroUnidade" class="select2-filtro">
                    <option value="">Todas</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['CD_UNIDADE'] ?>"><?= htmlspecialchars($u['DS_NOME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-group">
                <label>Tipo Medidor</label>
                <select id="filtroTipo" class="select2-filtro">
                    <option value="">Todos</option>
                    <?php foreach ($tiposMedidor as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-group">
                <label>Status</label>
                <select id="filtroStatus">
                    <option value="">Todos</option>
                    <option value="OK">OK</option>
                    <option value="ATENCAO">Atencao</option>
                    <option value="CRITICO">Critico</option>
                </select>
            </div>

            <div class="filtro-group">
                <label>Exibir</label>
                <select id="filtroLimite">
                    <option value="50">50 registros</option>
                    <option value="100" selected>100 registros</option>
                    <option value="200">200 registros</option>
                    <option value="500">500 registros</option>
                </select>
            </div>

            <div class="filtro-group">
                <label>&nbsp;</label>
                <button type="button" class="btn-filtrar" onclick="carregarDados()">
                    <ion-icon name="search-outline"></ion-icon>
                    Filtrar
                </button>
            </div>
        </div>
    </div>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Grafico Evolucao -->
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title">
                    <ion-icon name="trending-up-outline"></ion-icon>
                    Evolucao por Status
                </div>
            </div>
            <div class="content-card-body">
                <div class="grafico-container">
                    <canvas id="graficoEvolucao"></canvas>
                </div>
            </div>
        </div>

        <!-- Ranking Criticos -->
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title">
                    <ion-icon name="warning-outline"></ion-icon>
                    Pontos Criticos
                </div>
            </div>
            <div class="content-card-body">
                <ul class="ranking-list" id="rankingCriticos">
                    <li class="loading-container">
                        <ion-icon name="sync-outline"></ion-icon>
                        <span>Carregando...</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Tabela de Dados -->
    <div class="content-card">
        <div class="content-card-header">
            <div class="content-card-title">
                <ion-icon name="list-outline"></ion-icon>
                Detalhamento por Ponto
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn-acao" onclick="exportarCSV()">
                    <ion-icon name="download-outline"></ion-icon>
                    Exportar
                </button>
            </div>
        </div>
        <div class="content-card-body">
            <div class="tabela-container">
                <table class="tabela-metricas">
                    <thead>
                        <tr>
                            <th>Ponto</th>
                            <th>Data</th>
                            <th>Cobertura</th>
                            <th>Media</th>
                            <th>Hist. 4 Sem</th>
                            <th>Desvio %</th>
                            <th>Tendencia</th>
                            <th>Flags</th>
                            <th>Status</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaBody">
                        <tr>
                            <td colspan="10">
                                <div class="loading-container">
                                    <ion-icon name="sync-outline"></ion-icon>
                                    <span>Carregando dados...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    /**
     * Dashboard Metricas IA - JavaScript
     * @version 1.0
     */

    // Variaveis globais
    let graficoEvolucao = null;
    let dadosAtuais = [];

    // Tipos de medidor
    const tiposMedidor = {
        1: 'Macromedidor',
        2: 'Est. Pitometrica',
        4: 'Pressao',
        6: 'Nivel',
        8: 'Hidrometro'
    };

    const unidadesMedidor = {
        1: 'l/s',
        2: 'l/s',
        4: 'mca',
        6: '%',
        8: 'l/s'
    };

    /**
     * Inicializacao
     */
    $(document).ready(function () {
        // Inicializar Select2
        $('.select2-filtro').select2({
            placeholder: 'Selecione...',
            allowClear: true,
            width: '100%'
        });

        // Carregar dados iniciais
        carregarDados();
    });

    /**
     * Carrega dados do servidor
     */
    function carregarDados() {
        const periodo = $('#filtroPeriodo').val();
        const unidade = $('#filtroUnidade').val();
        const tipo = $('#filtroTipo').val();
        const status = $('#filtroStatus').val();
        const limite = $('#filtroLimite').val();

        // Loading
        $('#tabelaBody').html(`
        <tr>
            <td colspan="10">
                <div class="loading-container">
                    <ion-icon name="sync-outline"></ion-icon>
                    <span>Carregando dados...</span>
                </div>
            </td>
        </tr>
    `);

        $.ajax({
            url: 'bd/dashboard/getMetricasIA.php',
            method: 'GET',
            data: {
                acao: 'listar',
                periodo: periodo,
                unidade: unidade,
                tipo: tipo,
                status: status,
                limite: limite
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    dadosAtuais = response.data;

                    // Atualizar data de referencia
                    if (response.dataReferencia) {
                        $('#dataReferencia').text(response.dataReferencia);
                    }

                    // Atualizar cards
                    atualizarCards(response.resumo);

                    // Atualizar tabela
                    atualizarTabela(response.data);

                    // Atualizar grafico
                    atualizarGrafico(response.evolucao);

                    // Atualizar ranking
                    atualizarRanking(response.criticos);
                } else {
                    mostrarErro(response.message || 'Erro ao carregar dados');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro:', error);
                mostrarErro('Erro de conexao com o servidor');
            }
        });
    }

    /**
     * Atualiza os cards de estatisticas
     */
    function atualizarCards(resumo) {
        if (!resumo) return;

        $('#statTotal').text(resumo.total || 0);
        $('#statOk').text(resumo.ok || 0);
        $('#statAtencao').text(resumo.atencao || 0);
        $('#statCritico').text(resumo.critico || 0);

        // Percentuais
        const total = resumo.total || 1;
        $('#statOkTrend span').text(Math.round((resumo.ok / total) * 100) + '% do total');
        $('#statAtencaoTrend span').text(Math.round((resumo.atencao / total) * 100) + '% do total');
        $('#statCriticoTrend span').text(Math.round((resumo.critico / total) * 100) + '% do total');
        $('#statTotalTrend span').text('Cobertura media: ' + (resumo.coberturaMedia || 0).toFixed(1) + '%');
    }

    /**
     * Atualiza a tabela de dados
     */
    /**
  * Atualiza a tabela de dados
  */
    function atualizarTabela(dados) {
        if (!dados || dados.length === 0) {
            $('#tabelaBody').html(`
            <tr>
                <td colspan="10">
                    <div class="empty-state">
                        <ion-icon name="file-tray-outline"></ion-icon>
                        <h3>Nenhum dado encontrado</h3>
                        <p>Ajuste os filtros e tente novamente</p>
                    </div>
                </td>
            </tr>
        `);
            return;
        }

        let html = '';
        dados.forEach(item => {
            const statusClass = item.DS_STATUS ? item.DS_STATUS.toLowerCase() : '';
            const cobertura = parseFloat(item.PERC_COBERTURA) || 0;
            const coberturaClass = cobertura >= 95 ? 'alta' : (cobertura >= 50 ? 'media' : 'baixa');
            const tendenciaClass = item.VL_TENDENCIA_7D ? item.VL_TENDENCIA_7D.toLowerCase() : 'estavel';
            const unidade = unidadesMedidor[item.ID_TIPO_MEDIDOR] || '';

            html += `
            <tr class="${statusClass}">
                <td>
                    <div class="ponto-info">
                        <span class="ponto-nome">${gerarCodigoPonto(item)}</span>
                        <span class="ponto-tipo">${item.DS_NOME || '-'} | ${tiposMedidor[item.ID_TIPO_MEDIDOR] || '-'}</span>
                    </div>
                </td>
                <td>${formatarData(item.DT_REFERENCIA)}</td>
                <td>
                    <div class="cobertura-bar">
                        <div class="cobertura-bar-track">
                            <div class="cobertura-bar-fill ${coberturaClass}" style="width: ${cobertura}%"></div>
                        </div>
                        <span class="cobertura-value">${cobertura.toFixed(0)}%</span>
                    </div>
                </td>
                <td>${formatarNumero(item.VL_MEDIA)} ${unidade}</td>
                <td>${formatarNumero(item.VL_MEDIA_HIST_4SEM)} ${unidade}</td>
                <td>${formatarDesvio(item.VL_DESVIO_HIST_PERC)}</td>
                <td>
                    <span class="tendencia ${tendenciaClass}">
                        <ion-icon name="${getTendenciaIcon(item.VL_TENDENCIA_7D)}"></ion-icon>
                        ${item.VL_TENDENCIA_7D || 'ESTAVEL'}
                    </span>
                </td>
                <td>
                    <div class="flags-container">
                        <span class="flag-icon ${item.FL_COBERTURA_BAIXA == 1 ? 'ativo' : 'inativo'}" title="Cobertura Baixa">
                            <ion-icon name="cloud-offline-outline"></ion-icon>
                        </span>
                        <span class="flag-icon ${item.FL_SENSOR_PROBLEMA == 1 ? 'ativo' : 'inativo'}" title="Problema Sensor">
                            <ion-icon name="hardware-chip-outline"></ion-icon>
                        </span>
                        <span class="flag-icon ${item.FL_VALOR_ANOMALO == 1 ? 'ativo' : 'inativo'}" title="Valor Anomalo">
                            <ion-icon name="alert-outline"></ion-icon>
                        </span>
                        <span class="flag-icon ${item.FL_DESVIO_SIGNIFICATIVO == 1 ? 'ativo' : 'inativo'}" title="Desvio Significativo">
                            <ion-icon name="stats-chart-outline"></ion-icon>
                        </span>
                    </div>
                </td>
                <td>
                    <span class="status-badge ${statusClass}">${item.DS_STATUS || '-'}</span>
                </td>
                <td>
                    <button type="button" class="btn-acao" onclick="verDetalhes(${item.CD_PONTO_MEDICAO}, '${item.DT_REFERENCIA}')" title="Ver detalhes">
                        <ion-icon name="eye-outline"></ion-icon>
                    </button>
                </td>
            </tr>
        `;
        });

        $('#tabelaBody').html(html);
    }
    /**
 * Gera codigo formatado do ponto no padrao SIMP
 */
    function gerarCodigoPonto(item) {
        const letrasTipo = { 1: 'M', 2: 'E', 4: 'P', 6: 'R', 8: 'H' };
        const letra = letrasTipo[item.ID_TIPO_MEDIDOR] || 'X';
        const cdPonto = String(item.CD_PONTO_MEDICAO).padStart(6, '0');
        const localidade = item.CD_LOCALIDADE_CODIGO || '0';
        const unidade = item.CD_UNIDADE || '0';
        return `${localidade}-${cdPonto}-${letra}-${unidade}`;
    }

    /**
     * Atualiza o grafico de evolucao
     */
    function atualizarGrafico(evolucao) {
        const ctx = document.getElementById('graficoEvolucao').getContext('2d');

        if (graficoEvolucao) {
            graficoEvolucao.destroy();
        }

        if (!evolucao || evolucao.length === 0) {
            return;
        }

        const labels = evolucao.map(e => formatarData(e.DT_REFERENCIA));
        const dataOk = evolucao.map(e => e.QTD_OK || 0);
        const dataAtencao = evolucao.map(e => e.QTD_ATENCAO || 0);
        const dataCritico = evolucao.map(e => e.QTD_CRITICO || 0);

        graficoEvolucao = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'OK',
                        data: dataOk,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    },
                    {
                        label: 'Atencao',
                        data: dataAtencao,
                        backgroundColor: '#f59e0b',
                        borderRadius: 4
                    },
                    {
                        label: 'Critico',
                        data: dataCritico,
                        backgroundColor: '#ef4444',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    /**
 * Atualiza o ranking de pontos criticos
 */
    function atualizarRanking(criticos) {
        if (!criticos || criticos.length === 0) {
            $('#rankingCriticos').html(`
            <li class="empty-state">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                <h3>Nenhum ponto critico</h3>
                <p>Todos os pontos estao operando normalmente</p>
            </li>
        `);
            return;
        }

        let html = '';
        criticos.slice(0, 10).forEach((item, index) => {
            const statusClass = item.DS_STATUS ? item.DS_STATUS.toLowerCase() : '';
            const cobertura = parseFloat(item.PERC_COBERTURA) || 0;
            html += `
            <li class="ranking-item" onclick="verDetalhes(${item.CD_PONTO_MEDICAO}, '${item.DT_REFERENCIA}')">
                <span class="ranking-position">${index + 1}</span>
                <div class="ranking-info">
                    <div class="ranking-nome">${item.DS_NOME || 'Ponto ' + item.CD_PONTO_MEDICAO}</div>
                    <div class="ranking-detalhe">${cobertura.toFixed(0)}% cobertura</div>
                </div>
                <span class="ranking-valor ${statusClass}">${item.DS_STATUS}</span>
            </li>
        `;
        });

        $('#rankingCriticos').html(html);
    }

    /**
 * Ver detalhes do ponto - redireciona para operacoes.php com validacao aberta
 */
    function verDetalhes(cdPonto, data) {
        // Extrair mes e ano da data (formato YYYY-MM-DD)
        const partes = data.split('-');
        const ano = partes[0];
        const mes = parseInt(partes[1], 10);

        // Redirecionar para operacoes.php abrindo modal de validacao
        window.location.href = `operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${data}&mes=${mes}&ano=${ano}`;
    }

    /**
     * Filtrar por status (clique nos cards)
     */
    function filtrarPorStatus(status) {
        $('#filtroStatus').val(status);
        carregarDados();
    }



    /**
     * Exportar para CSV
     */
    function exportarCSV() {
        if (!dadosAtuais || dadosAtuais.length === 0) {
            alert('Nenhum dado para exportar');
            return;
        }

        let csv = 'Ponto;Nome;Data;Cobertura;Media;Historico;Desvio%;Tendencia;Status;Resumo\n';

        dadosAtuais.forEach(item => {
            csv += `${item.CD_PONTO_MEDICAO};`;
            csv += `"${item.DS_NOME || ''}";`;
            csv += `${formatarData(item.DT_REFERENCIA)};`;
            csv += `${(item.PERC_COBERTURA || 0).toFixed(1)};`;
            csv += `${(item.VL_MEDIA || 0).toFixed(2)};`;
            csv += `${(item.VL_MEDIA_HIST_4SEM || 0).toFixed(2)};`;
            csv += `${(item.VL_DESVIO_HIST_PERC || 0).toFixed(1)};`;
            csv += `${item.VL_TENDENCIA_7D || ''};`;
            csv += `${item.DS_STATUS || ''};`;
            csv += `"${(item.DS_RESUMO || '').replace(/"/g, '""')}"\n`;
        });

        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `metricas_ia_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
    }

    /**
     * Funcoes auxiliares
     */
    function formatarData(data) {
        if (!data) return '-';
        const d = new Date(data);
        return d.toLocaleDateString('pt-BR');
    }

    function formatarNumero(valor) {
        if (valor === null || valor === undefined) return '-';
        return parseFloat(valor).toFixed(2);
    }

    function formatarDesvio(valor) {
        if (valor === null || valor === undefined) return '-';
        const v = parseFloat(valor);
        const sinal = v > 0 ? '+' : '';
        const cor = Math.abs(v) > 30 ? (v > 0 ? 'color: #ef4444' : 'color: #3b82f6') : '';
        return `<span style="${cor}">${sinal}${v.toFixed(1)}%</span>`;
    }

    function getTendenciaIcon(tendencia) {
        switch (tendencia) {
            case 'SUBINDO': return 'trending-up-outline';
            case 'DESCENDO': return 'trending-down-outline';
            default: return 'remove-outline';
        }
    }

    function mostrarErro(mensagem) {
        $('#tabelaBody').html(`
        <tr>
            <td colspan="10">
                <div class="empty-state">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <h3>Erro</h3>
                    <p>${mensagem}</p>
                </div>
            </td>
        </tr>
    `);
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>