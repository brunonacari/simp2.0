<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Tela de Listagem de Cálculo de KPC
 * 
 * Permite consultar e gerenciar os cálculos de KPC (Constante Pitométrica)
 * realizados nas estações pitométricas.
 */

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
    $sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
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

<style>
    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 20px;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* ============================================
       Page Header
       ============================================ */
    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 12px;
        padding: 20px 24px;
        margin-bottom: 20px;
        color: white;
    }

    .page-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .page-header-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-header-icon {
        width: 42px;
        height: 42px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .page-header h1 {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 4px 0;
    }

    .page-header-subtitle {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-header {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-header:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-1px);
    }

    .btn-header.primary {
        background: #10b981;
        border-color: #10b981;
    }

    .btn-header.primary:hover {
        background: #059669;
    }

    /* ============================================
       Filtros
       ============================================ */
    .filtros-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 20px;
        overflow: hidden;
    }

    .filtros-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
    }

    .filtros-header-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
    }

    .filtros-header-title ion-icon {
        color: #3b82f6;
    }

    .filtros-body {
        padding: 20px;
    }

    .filtros-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 16px;
    }

    .filtros-row:last-child {
        margin-bottom: 0;
    }

    .filtro-group {
        flex: 1;
        min-width: 180px;
        max-width: 220px;
    }

    .filtro-group.codigo {
        max-width: 140px;
        min-width: 100px;
    }

    .filtro-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .filtro-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .filtro-control:focus {
        outline: none;
        border-color: #3b82f6;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filtros-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding-top: 16px;
        border-top: 1px solid #e2e8f0;
        margin-top: 16px;
    }

    .btn-filtrar {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-filtrar:hover {
        background: #2563eb;
    }

    .btn-limpar {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-limpar:hover {
        background: #e2e8f0;
    }

    /* ============================================
       Tabela de Dados
       ============================================ */
    .table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .table-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
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
        border-radius: 6px;
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
        padding: 12px 16px;
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
        padding: 12px 16px;
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

    /* Checkbox */
    .data-table th:first-child,
    .data-table td:first-child {
        width: 40px;
        text-align: center;
    }

    /* Badge de Situação */
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

    /* Ações */
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
        border-radius: 6px;
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
        padding: 16px 20px;
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
        border-radius: 6px;
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
        to { transform: rotate(360deg); }
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
    @media (max-width: 1024px) {
        .filtro-group {
            max-width: none;
            flex: 1 1 calc(33.333% - 16px);
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 12px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .header-actions {
            justify-content: stretch;
        }

        .btn-header {
            flex: 1;
            justify-content: center;
        }

        .filtro-group {
            flex: 1 1 100%;
            max-width: none;
        }

        .filtros-actions {
            flex-direction: column;
        }

        .filtros-actions button {
            width: 100%;
            justify-content: center;
        }

        .table-header {
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }
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
            <div class="header-actions">
                <?php if ($podeEditar): ?>
                <a href="calculoKPCForm.php" class="btn-header primary">
                    <ion-icon name="add-circle-outline"></ion-icon>
                    Novo Cálculo
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-card">
        <div class="filtros-header" onclick="toggleFiltros()">
            <div class="filtros-header-title">
                <ion-icon name="filter-outline"></ion-icon>
                Filtros de Consulta
            </div>
            <ion-icon name="chevron-down-outline" id="filtrosIcon"></ion-icon>
        </div>
        <div class="filtros-body" id="filtrosBody">
            <div class="filtros-row">
                <div class="filtro-group">
                    <label class="filtro-label">Unidade</label>
                    <select class="filtro-control" id="filtroUnidade" onchange="carregarLocalidades()">
                        <option value="">Todas</option>
                        <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['CD_UNIDADE'] ?>"><?= htmlspecialchars($u['DS_NOME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-group">
                    <label class="filtro-label">Localidade</label>
                    <select class="filtro-control" id="filtroLocalidade" onchange="carregarPontos()">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="filtro-group">
                    <label class="filtro-label">Ponto de Medição</label>
                    <select class="filtro-control" id="filtroPonto">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="filtro-group">
                    <label class="filtro-label">Situação</label>
                    <select class="filtro-control" id="filtroSituacao">
                        <option value="">Todas</option>
                        <?php foreach ($situacoes as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= $nome ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-group">
                    <label class="filtro-label">Método</label>
                    <select class="filtro-control" id="filtroMetodo">
                        <option value="">Todos</option>
                        <?php foreach ($metodos as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= $nome ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filtros-row">
                <div class="filtro-group codigo">
                    <label class="filtro-label">Código Inicial</label>
                    <input type="number" class="filtro-control" id="filtroCodigoInicial" placeholder="000">
                </div>
                <div class="filtro-group codigo">
                    <label class="filtro-label">Ano Inicial</label>
                    <input type="text" class="filtro-control" id="filtroAnoInicial" placeholder="AA" maxlength="2">
                </div>
                <div class="filtro-group codigo">
                    <label class="filtro-label">Código Final</label>
                    <input type="number" class="filtro-control" id="filtroCodigoFinal" placeholder="999">
                </div>
                <div class="filtro-group codigo">
                    <label class="filtro-label">Ano Final</label>
                    <input type="text" class="filtro-control" id="filtroAnoFinal" placeholder="AA" maxlength="2">
                </div>
                <div class="filtro-group">
                    <label class="filtro-label">Data Inicial</label>
                    <input type="date" class="filtro-control" id="filtroDataInicial">
                </div>
                <div class="filtro-group">
                    <label class="filtro-label">Data Final</label>
                    <input type="date" class="filtro-control" id="filtroDataFinal">
                </div>
            </div>
            <div class="filtros-actions">
                <button type="button" class="btn-limpar" onclick="limparFiltros()">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Limpar
                </button>
                <button type="button" class="btn-filtrar" onclick="filtrar()">
                    <ion-icon name="search-outline"></ion-icon>
                    Filtrar
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Dados -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-info">
                <span id="totalRegistros">0</span> registro(s) encontrado(s)
            </div>
            <div class="table-actions">
                <?php if ($podeEditar): ?>
                <button type="button" class="btn-cancelar-selecionados" id="btnCancelarSelecionados" disabled onclick="cancelarSelecionados()">
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
                Mostrando <span id="paginacaoInicio">0</span> a <span id="paginacaoFim">0</span> de <span id="paginacaoTotal">0</span>
            </div>
            <div class="pagination" id="paginacao">
                <!-- Preenchido via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<script>
// ============================================
// Variáveis Globais
// ============================================
let paginaAtual = 1;
const registrosPorPagina = 20;
let totalRegistros = 0;
const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

// ============================================
// Inicialização
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    filtrar();
});

// ============================================
// Funções de Filtro
// ============================================
function toggleFiltros() {
    const body = document.getElementById('filtrosBody');
    const icon = document.getElementById('filtrosIcon');
    body.style.display = body.style.display === 'none' ? 'block' : 'none';
    icon.name = body.style.display === 'none' ? 'chevron-forward-outline' : 'chevron-down-outline';
}

function limparFiltros() {
    document.getElementById('filtroUnidade').value = '';
    document.getElementById('filtroLocalidade').innerHTML = '<option value="">Todas</option>';
    document.getElementById('filtroPonto').innerHTML = '<option value="">Todos</option>';
    document.getElementById('filtroSituacao').value = '';
    document.getElementById('filtroMetodo').value = '';
    document.getElementById('filtroCodigoInicial').value = '';
    document.getElementById('filtroAnoInicial').value = '';
    document.getElementById('filtroCodigoFinal').value = '';
    document.getElementById('filtroAnoFinal').value = '';
    document.getElementById('filtroDataInicial').value = '';
    document.getElementById('filtroDataFinal').value = '';
    paginaAtual = 1;
    filtrar();
}

// ============================================
// Carregamento de Dependências
// ============================================
function carregarLocalidades() {
    const unidade = document.getElementById('filtroUnidade').value;
    const select = document.getElementById('filtroLocalidade');
    select.innerHTML = '<option value="">Todas</option>';
    document.getElementById('filtroPonto').innerHTML = '<option value="">Todos</option>';
    
    if (!unidade) return;
    
    fetch(`bd/operacoes/getLocalidades.php?unidade=${unidade}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                data.data.forEach(loc => {
                    select.innerHTML += `<option value="${loc.CD_CHAVE}">${loc.CD_LOCALIDADE} - ${loc.DS_NOME}</option>`;
                });
            }
        });
}

function carregarPontos() {
    const localidade = document.getElementById('filtroLocalidade').value;
    const select = document.getElementById('filtroPonto');
    select.innerHTML = '<option value="">Todos</option>';
    
    if (!localidade) return;
    
    // Buscar apenas Estações Pitométricas (ID_TIPO_MEDIDOR = 2)
    fetch(`bd/operacoes/getPontosMedicao.php?localidade=${localidade}&tipo=2`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                data.data.forEach(p => {
                    select.innerHTML += `<option value="${p.CD_PONTO_MEDICAO}">${p.DS_NOME}</option>`;
                });
            }
        });
}

// ============================================
// Função Principal de Filtrar
// ============================================
function filtrar() {
    mostrarLoading(true);
    
    const params = new URLSearchParams({
        pagina: paginaAtual,
        limite: registrosPorPagina,
        unidade: document.getElementById('filtroUnidade').value,
        localidade: document.getElementById('filtroLocalidade').value,
        ponto: document.getElementById('filtroPonto').value,
        situacao: document.getElementById('filtroSituacao').value,
        metodo: document.getElementById('filtroMetodo').value,
        codigoInicial: document.getElementById('filtroCodigoInicial').value,
        anoInicial: document.getElementById('filtroAnoInicial').value,
        codigoFinal: document.getElementById('filtroCodigoFinal').value,
        anoFinal: document.getElementById('filtroAnoFinal').value,
        dataInicial: document.getElementById('filtroDataInicial').value,
        dataFinal: document.getElementById('filtroDataFinal').value
    });
    
    fetch(`bd/calculoKPC/listarCalculoKPC.php?${params}`)
        .then(r => r.json())
        .then(data => {
            mostrarLoading(false);
            if (data.success) {
                renderizarTabela(data.data);
                totalRegistros = data.total;
                atualizarPaginacao();
            } else {
                showToast('Erro ao carregar dados: ' + data.message, 'erro');
            }
        })
        .catch(err => {
            mostrarLoading(false);
            console.error('Erro:', err);
            showToast('Erro ao carregar dados', 'erro');
        });
}

// ============================================
// Renderização da Tabela
// ============================================
function renderizarTabela(dados) {
    const tbody = document.getElementById('tabelaBody');
    document.getElementById('totalRegistros').textContent = totalRegistros;
    
    if (dados.length === 0) {
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
    
    tbody.innerHTML = dados.map(item => {
        const isCancelado = item.ID_SITUACAO == 2;
        const metodo = item.ID_METODO == 1 ? 'Digital' : (item.ID_METODO == 2 ? 'Convencional' : '-');
        const codigo = item.CD_CODIGO + '-' + item.CD_ANO;
        const dataLeitura = item.DT_LEITURA ? formatarData(item.DT_LEITURA) : '-';
        const kpc = item.VL_KPC ? parseFloat(item.VL_KPC).toFixed(10) : '-';
        const vazao = item.VL_VAZAO ? parseFloat(item.VL_VAZAO).toFixed(2) : '-';
        
        return `
            <tr class="${isCancelado ? 'cancelado' : ''}">
                <td>
                    <input type="checkbox" class="checkItem" value="${item.CD_CHAVE}" 
                           onchange="atualizarBotaoCancelar()" ${isCancelado ? 'disabled' : ''}>
                </td>
                <td>${item.DS_UNIDADE || '-'}</td>
                <td>${item.DS_LOCALIDADE || '-'}</td>
                <td>${item.DS_PONTO_MEDICAO || '-'}</td>
                <td><strong>${codigo}</strong></td>
                <td>
                    <span class="badge ${isCancelado ? 'badge-cancelado' : 'badge-ativo'}">
                        ${isCancelado ? 'Cancelado' : 'Ativo'}
                    </span>
                </td>
                <td>${dataLeitura}</td>
                <td><span class="badge badge-metodo">${metodo}</span></td>
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
    }).join('');
}

// ============================================
// Paginação
// ============================================
function atualizarPaginacao() {
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
    const inicio = (paginaAtual - 1) * registrosPorPagina + 1;
    const fim = Math.min(paginaAtual * registrosPorPagina, totalRegistros);
    
    document.getElementById('paginacaoInicio').textContent = totalRegistros > 0 ? inicio : 0;
    document.getElementById('paginacaoFim').textContent = fim;
    document.getElementById('paginacaoTotal').textContent = totalRegistros;
    
    const paginacao = document.getElementById('paginacao');
    let html = '';
    
    html += `<button onclick="irParaPagina(${paginaAtual - 1})" ${paginaAtual === 1 ? 'disabled' : ''}>
        <ion-icon name="chevron-back-outline"></ion-icon>
    </button>`;
    
    // Mostrar páginas
    const maxPaginas = 5;
    let startPage = Math.max(1, paginaAtual - Math.floor(maxPaginas / 2));
    let endPage = Math.min(totalPaginas, startPage + maxPaginas - 1);
    
    if (endPage - startPage < maxPaginas - 1) {
        startPage = Math.max(1, endPage - maxPaginas + 1);
    }
    
    if (startPage > 1) {
        html += `<button onclick="irParaPagina(1)">1</button>`;
        if (startPage > 2) html += `<button disabled>...</button>`;
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button onclick="irParaPagina(${i})" class="${i === paginaAtual ? 'active' : ''}">${i}</button>`;
    }
    
    if (endPage < totalPaginas) {
        if (endPage < totalPaginas - 1) html += `<button disabled>...</button>`;
        html += `<button onclick="irParaPagina(${totalPaginas})">${totalPaginas}</button>`;
    }
    
    html += `<button onclick="irParaPagina(${paginaAtual + 1})" ${paginaAtual === totalPaginas || totalPaginas === 0 ? 'disabled' : ''}>
        <ion-icon name="chevron-forward-outline"></ion-icon>
    </button>`;
    
    paginacao.innerHTML = html;
}

function irParaPagina(pagina) {
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
    if (pagina >= 1 && pagina <= totalPaginas) {
        paginaAtual = pagina;
        filtrar();
    }
}

// ============================================
// Seleção e Cancelamento
// ============================================
function toggleCheckAll() {
    const checkAll = document.getElementById('checkAll');
    document.querySelectorAll('.checkItem:not(:disabled)').forEach(cb => {
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
    const selecionados = Array.from(document.querySelectorAll('.checkItem:checked')).map(cb => cb.value);
    
    if (selecionados.length === 0) {
        showToast('Selecione ao menos um registro', 'alerta');
        return;
    }
    
    if (!confirm(`Deseja realmente cancelar ${selecionados.length} registro(s)?`)) {
        return;
    }
    
    mostrarLoading(true);
    
    fetch('bd/calculoKPC/cancelarCalculoKPC.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: selecionados })
    })
    .then(r => r.json())
    .then(data => {
        mostrarLoading(false);
        if (data.success) {
            showToast('Registros cancelados com sucesso!', 'sucesso');
            filtrar();
        } else {
            showToast('Erro: ' + data.message, 'erro');
        }
    })
    .catch(err => {
        mostrarLoading(false);
        console.error('Erro:', err);
        showToast('Erro ao cancelar registros', 'erro');
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
// Toast System (fallback se não existir)
// ============================================
if (typeof showToast !== 'function') {
    function showToast(message, type = 'info', duration = 5000) {
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
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <ion-icon name="${icons[type] || icons.info}"></ion-icon>
            </div>
            <div class="toast-content">
                <p class="toast-message">${message}</p>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <ion-icon name="close"></ion-icon>
            </button>
        `;
        
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
}
</script>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<style>
/* Toast Styles */
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
    border-radius: 8px;
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

.toast.sucesso { border-left: 4px solid #10b981; }
.toast.sucesso .toast-icon { color: #10b981; }

.toast.erro { border-left: 4px solid #ef4444; }
.toast.erro .toast-icon { color: #ef4444; }

.toast.alerta { border-left: 4px solid #f59e0b; }
.toast.alerta .toast-icon { color: #f59e0b; }

.toast.info { border-left: 4px solid #3b82f6; }
.toast.info .toast-icon { color: #3b82f6; }

.toast-content { flex: 1; }
.toast-message { margin: 0; font-size: 14px; color: #1e293b; }
.toast-close {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: #94a3b8;
    font-size: 18px;
}
.toast-close:hover { color: #475569; }
</style>

<?php include_once 'includes/footer.inc.php'; ?>
