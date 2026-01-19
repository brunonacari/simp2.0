<?php
/**
 * SIMP - Dashboard de Alertas (Versão Simplificada)
 * 
 * Consulta direta em tempo real - SEM procedures, SEM cache
 * Quando dados são tratados na validação, automaticamente somem daqui
 */

session_start();
require_once 'bd/verificarAuth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMP - Alertas de Medição</title>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-info {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Cards de Resumo */
        .resumo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .resumo-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }
        
        .resumo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .resumo-card.active {
            border-left-color: #1e3a5f;
            background: #f8fafc;
        }
        
        .resumo-card.alerta-comunicacao { border-left-color: #ef4444; }
        .resumo-card.alerta-zeros { border-left-color: #f59e0b; }
        .resumo-card.alerta-negativo { border-left-color: #8b5cf6; }
        .resumo-card.alerta-faixa { border-left-color: #ec4899; }
        .resumo-card.alerta-constante { border-left-color: #06b6d4; }
        
        .resumo-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .alerta-comunicacao .resumo-icon { background: #fef2f2; color: #ef4444; }
        .alerta-zeros .resumo-icon { background: #fffbeb; color: #f59e0b; }
        .alerta-negativo .resumo-icon { background: #f5f3ff; color: #8b5cf6; }
        .alerta-faixa .resumo-icon { background: #fdf2f8; color: #ec4899; }
        .alerta-constante .resumo-icon { background: #ecfeff; color: #06b6d4; }
        
        .resumo-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .resumo-content p {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 2px;
        }
        
        /* Lista de Alertas */
        .alertas-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .alertas-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alertas-header h2 {
            font-size: 1.1rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alertas-filtros {
            display: flex;
            gap: 10px;
        }
        
        .filtro-btn {
            padding: 6px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            background: white;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filtro-btn:hover, .filtro-btn.active {
            background: #1e3a5f;
            color: white;
            border-color: #1e3a5f;
        }
        
        .alertas-lista {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .alerta-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        
        .alerta-item:hover {
            background: #f8fafc;
        }
        
        .alerta-tipo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .tipo-comunicacao { background: #fef2f2; color: #ef4444; }
        .tipo-zeros { background: #fffbeb; color: #f59e0b; }
        .tipo-negativo { background: #f5f3ff; color: #8b5cf6; }
        .tipo-faixa { background: #fdf2f8; color: #ec4899; }
        .tipo-constante { background: #ecfeff; color: #06b6d4; }
        
        .alerta-info {
            flex: 1;
        }
        
        .alerta-ponto {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.95rem;
        }
        
        .alerta-descricao {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 3px;
        }
        
        .alerta-detalhe {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .alerta-acoes {
            display: flex;
            gap: 8px;
        }
        
        .btn-validar {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #1e3a5f 0%, #0d9488 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-validar:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state ion-icon {
            font-size: 4rem;
            color: #22c55e;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        
        .loading ion-icon {
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        
        /* Badge de contagem */
        .badge {
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 8px;
        }
        
        /* Responsivo */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .resumo-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .alerta-item {
                flex-wrap: wrap;
            }
            
            .alerta-acoes {
                width: 100%;
                margin-top: 10px;
            }
            
            .btn-validar {
                flex: 1;
                justify-content: center;
            }
            
            .filtros-container {
                flex-direction: column;
            }
            
            .filtro-grupo {
                width: 100%;
            }
        }
        
        /* Filtros */
        .filtros-container {
            display: flex;
            gap: 15px;
            padding: 15px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filtro-grupo {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 200px;
            flex: 1;
        }
        
        .filtro-grupo label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .filtro-grupo label ion-icon {
            font-size: 1rem;
        }
        
        .filtro-grupo input {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .filtro-grupo input:focus {
            outline: none;
            border-color: #1e3a5f;
            box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
        }
        
        .filtro-acoes {
            flex-direction: row !important;
            min-width: auto !important;
            flex: 0 !important;
            gap: 10px !important;
        }
        
        .btn-filtro {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .btn-limpar {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .btn-limpar:hover {
            background: #e2e8f0;
            color: #475569;
        }
        
        .btn-atualizar {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: white;
        }
        
        .btn-atualizar:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
        }
    </style>
</head>
<body>

<div class="header">
    <h1>
        <ion-icon name="alert-circle-outline"></ion-icon>
        Alertas de Medição
    </h1>
    <div class="header-info">
        <span id="dataReferencia">Carregando...</span>
        <button onclick="location.reload()" style="background:none;border:none;color:white;cursor:pointer;margin-left:10px;" title="Atualizar">
            <ion-icon name="refresh-outline"></ion-icon>
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="filtros-container">
    <div class="filtro-grupo">
        <label for="filtroData">
            <ion-icon name="calendar-outline"></ion-icon>
            Data de Análise
        </label>
        <input type="date" id="filtroData" onchange="carregarAlertas()">
    </div>
    <div class="filtro-grupo">
        <label for="filtroPonto">
            <ion-icon name="location-outline"></ion-icon>
            Ponto de Medição
        </label>
        <input type="text" id="filtroPonto" placeholder="Código ou nome do ponto..." oninput="filtrarPorPonto()">
    </div>
    <div class="filtro-grupo filtro-acoes">
        <button class="btn-filtro btn-limpar" onclick="limparFiltros()">
            <ion-icon name="close-circle-outline"></ion-icon>
            Limpar
        </button>
        <button class="btn-filtro btn-atualizar" onclick="carregarAlertas()">
            <ion-icon name="refresh-outline"></ion-icon>
            Atualizar
        </button>
    </div>
</div>

<div class="container">
    <!-- Cards de Resumo -->
    <div class="resumo-grid">
        <div class="resumo-card alerta-comunicacao" onclick="filtrarTipo('COMUNICACAO')">
            <div class="resumo-icon">
                <ion-icon name="wifi-outline"></ion-icon>
            </div>
            <div class="resumo-content">
                <h3 id="qtdComunicacao">-</h3>
                <p>Sem Comunicação</p>
            </div>
        </div>
        
        <div class="resumo-card alerta-zeros" onclick="filtrarTipo('ZEROS')">
            <div class="resumo-icon">
                <ion-icon name="remove-circle-outline"></ion-icon>
            </div>
            <div class="resumo-content">
                <h3 id="qtdZeros">-</h3>
                <p>Zeros Suspeitos</p>
            </div>
        </div>
        
        <div class="resumo-card alerta-negativo" onclick="filtrarTipo('NEGATIVO')">
            <div class="resumo-icon">
                <ion-icon name="trending-down-outline"></ion-icon>
            </div>
            <div class="resumo-content">
                <h3 id="qtdNegativos">-</h3>
                <p>Valores Negativos</p>
            </div>
        </div>
        
        <div class="resumo-card alerta-faixa" onclick="filtrarTipo('FAIXA')">
            <div class="resumo-icon">
                <ion-icon name="speedometer-outline"></ion-icon>
            </div>
            <div class="resumo-content">
                <h3 id="qtdFaixa">-</h3>
                <p>Fora da Faixa</p>
            </div>
        </div>
        
        <div class="resumo-card alerta-constante" onclick="filtrarTipo('CONSTANTE')">
            <div class="resumo-icon">
                <ion-icon name="pulse-outline"></ion-icon>
            </div>
            <div class="resumo-content">
                <h3 id="qtdConstante">-</h3>
                <p>Valor Constante</p>
            </div>
        </div>
    </div>
    
    <!-- Lista de Alertas -->
    <div class="alertas-section">
        <div class="alertas-header">
            <h2>
                <ion-icon name="list-outline"></ion-icon>
                Pontos que Precisam de Atenção
                <span class="badge" id="totalAlertas">0</span>
            </h2>
            <div class="alertas-filtros">
                <button class="filtro-btn active" onclick="filtrarTipo('TODOS')">Todos</button>
                <button class="filtro-btn" onclick="filtrarTipo('COMUNICACAO')">Comunicação</button>
                <button class="filtro-btn" onclick="filtrarTipo('ZEROS')">Zeros</button>
                <button class="filtro-btn" onclick="filtrarTipo('NEGATIVO')">Negativos</button>
            </div>
        </div>
        
        <div class="alertas-lista" id="listaAlertas">
            <div class="loading">
                <ion-icon name="sync-outline"></ion-icon>
                <p>Carregando alertas...</p>
            </div>
        </div>
    </div>
</div>

<script>
let todosAlertas = [];
let alertasFiltradosPorPonto = [];
let tipoFiltro = 'TODOS';
let dataMaxDisponivel = null;

// Carregar alertas ao iniciar
document.addEventListener('DOMContentLoaded', carregarAlertas);

async function carregarAlertas() {
    try {
        // Pegar data do filtro (se informada)
        const dataFiltro = document.getElementById('filtroData').value;
        
        let url = 'bd/dashboard/getAlertas.php';
        if (dataFiltro) {
            url += `?data=${dataFiltro}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            todosAlertas = result.data;
            alertasFiltradosPorPonto = [...todosAlertas];
            dataMaxDisponivel = result.data_referencia;
            
            atualizarContadores(result.contadores);
            
            // Se não tinha data no filtro, preencher com a data retornada
            if (!dataFiltro && result.data_referencia) {
                document.getElementById('filtroData').value = result.data_referencia;
            }
            
            // Aplicar filtro de ponto se houver
            filtrarPorPonto();
            
            // Atualizar data de referência no header
            if (result.data_referencia) {
                const dataRef = formatarData(result.data_referencia);
                document.getElementById('dataReferencia').innerHTML = 
                    `Dados de: <strong>${dataRef}</strong> | Atualizado: ${new Date().toLocaleTimeString('pt-BR')}`;
            }
        } else {
            document.getElementById('listaAlertas').innerHTML = `
                <div class="empty-state">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <h3>Erro ao carregar</h3>
                    <p>${result.message}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erro:', error);
        document.getElementById('listaAlertas').innerHTML = `
            <div class="empty-state">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <h3>Erro de conexão</h3>
                <p>Não foi possível carregar os alertas</p>
            </div>
        `;
    }
}

function filtrarPorPonto() {
    const termoBusca = document.getElementById('filtroPonto').value.toLowerCase().trim();
    
    if (!termoBusca) {
        alertasFiltradosPorPonto = [...todosAlertas];
    } else {
        alertasFiltradosPorPonto = todosAlertas.filter(alerta => {
            const codigo = String(alerta.CD_PONTO_MEDICAO).toLowerCase();
            const nome = (alerta.NM_PONTO || '').toLowerCase();
            const localidade = (alerta.DS_LOCALIDADE || '').toLowerCase();
            const municipio = (alerta.NM_MUNICIPIO || '').toLowerCase();
            
            return codigo.includes(termoBusca) || 
                   nome.includes(termoBusca) || 
                   localidade.includes(termoBusca) ||
                   municipio.includes(termoBusca);
        });
    }
    
    // Reaplicar filtro de tipo
    filtrarTipo(tipoFiltro);
}

function limparFiltros() {
    document.getElementById('filtroData').value = dataMaxDisponivel || '';
    document.getElementById('filtroPonto').value = '';
    tipoFiltro = 'TODOS';
    
    // Atualizar botões
    document.querySelectorAll('.filtro-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.filtro-btn').classList.add('active');
    document.querySelectorAll('.resumo-card').forEach(card => card.classList.remove('active'));
    
    carregarAlertas();
}

function formatarData(dataStr) {
    if (!dataStr) return '-';
    const partes = dataStr.split('-');
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

function atualizarContadores(contadores) {
    document.getElementById('qtdComunicacao').textContent = contadores.COMUNICACAO || 0;
    document.getElementById('qtdZeros').textContent = contadores.ZEROS || 0;
    document.getElementById('qtdNegativos').textContent = contadores.NEGATIVO || 0;
    document.getElementById('qtdFaixa').textContent = contadores.FAIXA || 0;
    document.getElementById('qtdConstante').textContent = contadores.CONSTANTE || 0;
    
    const total = (contadores.COMUNICACAO || 0) + (contadores.ZEROS || 0) + 
                  (contadores.NEGATIVO || 0) + (contadores.FAIXA || 0) + (contadores.CONSTANTE || 0);
    document.getElementById('totalAlertas').textContent = total;
}

function filtrarTipo(tipo) {
    tipoFiltro = tipo;
    
    // Atualizar botões
    document.querySelectorAll('.filtro-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.resumo-card').forEach(card => card.classList.remove('active'));
    
    if (tipo === 'TODOS') {
        document.querySelector('.filtro-btn').classList.add('active');
    } else {
        document.querySelectorAll('.filtro-btn').forEach(btn => {
            if (btn.textContent.toUpperCase().includes(tipo.substring(0, 4))) {
                btn.classList.add('active');
            }
        });
        const cardClass = `.alerta-${tipo.toLowerCase()}`;
        const card = document.querySelector(cardClass);
        if (card) card.classList.add('active');
    }
    
    // Filtrar lista (usa alertasFiltradosPorPonto que já passou pelo filtro de texto)
    const alertasFiltrados = tipo === 'TODOS' 
        ? alertasFiltradosPorPonto 
        : alertasFiltradosPorPonto.filter(a => a.TIPO_ALERTA === tipo);
    
    renderizarLista(alertasFiltrados);
}

function renderizarLista(alertas) {
    const container = document.getElementById('listaAlertas');
    
    if (alertas.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                <h3>Nenhum alerta!</h3>
                <p>Todos os pontos estão operando normalmente</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = alertas.map(alerta => {
        const tipoClass = getTipoClass(alerta.TIPO_ALERTA);
        const tipoIcon = getTipoIcon(alerta.TIPO_ALERTA);
        const urlValidacao = montarUrlValidacao(alerta);
        
        return `
            <div class="alerta-item">
                <div class="alerta-tipo ${tipoClass}">
                    <ion-icon name="${tipoIcon}"></ion-icon>
                </div>
                <div class="alerta-info">
                    <div class="alerta-ponto">${alerta.CD_PONTO_MEDICAO} - ${alerta.NM_PONTO || 'Ponto de Medição'}</div>
                    <div class="alerta-descricao">${alerta.DS_ALERTA}</div>
                    <div class="alerta-detalhe">${alerta.DS_LOCALIDADE || ''} ${alerta.NM_MUNICIPIO ? '• ' + alerta.NM_MUNICIPIO : ''}</div>
                </div>
                <div class="alerta-acoes">
                    <a href="${urlValidacao}" class="btn-validar" target="_blank">
                        <ion-icon name="create-outline"></ion-icon>
                        Validar Dados
                    </a>
                </div>
            </div>
        `;
    }).join('');
}

function getTipoClass(tipo) {
    const classes = {
        'COMUNICACAO': 'tipo-comunicacao',
        'ZEROS': 'tipo-zeros',
        'NEGATIVO': 'tipo-negativo',
        'FAIXA': 'tipo-faixa',
        'CONSTANTE': 'tipo-constante'
    };
    return classes[tipo] || 'tipo-comunicacao';
}

function getTipoIcon(tipo) {
    const icons = {
        'COMUNICACAO': 'wifi-outline',
        'ZEROS': 'remove-circle-outline',
        'NEGATIVO': 'trending-down-outline',
        'FAIXA': 'speedometer-outline',
        'CONSTANTE': 'pulse-outline'
    };
    return icons[tipo] || 'alert-circle-outline';
}

function montarUrlValidacao(alerta) {
    const data = alerta.DT_REFERENCIA;
    if (!data) return '#';
    
    const partes = data.split('-');
    const ano = partes[0];
    const mes = parseInt(partes[1]);
    
    return `operacoes.php?abrirValidacao=1&cdPonto=${alerta.CD_PONTO_MEDICAO}&dataValidacao=${data}&mes=${mes}&ano=${ano}`;
}

// Auto-refresh a cada 5 minutos
setInterval(carregarAlertas, 300000);
</script>

</body>
</html>