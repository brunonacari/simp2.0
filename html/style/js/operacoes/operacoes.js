/**
 * ============================================
 * SIMP - Operações
 * ============================================
 * Arquivo JavaScript consolidado
 * Caminho: style/js/operacoes.js
 * Versão: 1.1
 */

// ============================================
// VARIÁVEIS GLOBAIS
// ============================================

let grafico = null;
let periodoDataInicio = null;
let periodoDataFim = null;
window.dadosGraficoHover = null;

// Validação
let validacaoPontoAtual = null;
let validacaoDataAtual = null;
let validacaoHorasSelecionadas = [];
let validacaoTipoMedidorAtual = null;
let validacaoUnidadeAtual = 'L/s';
let validacaoDadosOriginais = [];
let validacaoGrafico = null;

// IA
let iaChatHistorico = [];
let dadosCompletosIA = null;
window.valoresIAParaAplicar = null;

// Popup do gráfico
let graficoPopupInstance = null;

// Letras dos tipos de medidor
const letrasTipoMedidor = {
    1: 'E', 2: 'U', 3: 'M', 4: 'P', 5: 'C', 6: 'N'
};

// Permissão de edição
let podeEditar = false;

// ============================================
// INICIALIZAÇÃO
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Operações JS inicializado');
    
    const permissaoEl = document.getElementById('podeEditar');
    if (permissaoEl) {
        podeEditar = permissaoEl.value === '1' || permissaoEl.value === 'true';
    }
    
    inicializarDropdowns();
    
    const urlParams = new URLSearchParams(window.location.search);
    const cdPontoUrl = urlParams.get('cdPonto');
    const dataUrl = urlParams.get('data');
    
    if (cdPontoUrl && dataUrl) {
        setTimeout(() => abrirValidacaoPorParametros(cdPontoUrl, dataUrl), 500);
    }
});

// ============================================
// DROPDOWNS E AUTOCOMPLETE
// ============================================

function inicializarDropdowns() {
    const selectTipoEntidade = document.getElementById('selectTipoEntidade');
    if (selectTipoEntidade) {
        carregarTiposEntidade();
        selectTipoEntidade.addEventListener('change', function() {
            carregarValoresEntidade(this.value);
        });
    }
    
    inicializarAutocompletePonto();
}

function carregarTiposEntidade() {
    const select = document.getElementById('selectTipoEntidade');
    if (!select) return;
    
    fetch('bd/operacoes/getTiposEntidade.php')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const primeiro = select.options[0];
                select.innerHTML = '';
                select.appendChild(primeiro);
                data.data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.CD_TIPO_ENTIDADE;
                    opt.textContent = item.DS_NOME;
                    select.appendChild(opt);
                });
            }
        })
        .catch(e => console.error('Erro ao carregar tipos:', e));
}

function carregarValoresEntidade(cdTipo) {
    const select = document.getElementById('selectValorEntidade');
    if (!select) return;
    
    if (!cdTipo) {
        select.innerHTML = '<option value="">Selecione o tipo primeiro</option>';
        select.disabled = true;
        return;
    }
    
    select.innerHTML = '<option value="">Carregando...</option>';
    select.disabled = true;
    
    fetch(`bd/operacoes/getValoresEntidade.php?cdTipoEntidade=${cdTipo}`)
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">Selecione...</option>';
            if (data.success && data.data) {
                data.data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.CD_VALOR_ENTIDADE;
                    opt.textContent = item.DS_VALOR_ENTIDADE;
                    select.appendChild(opt);
                });
            }
            select.disabled = false;
        })
        .catch(e => {
            console.error('Erro:', e);
            select.innerHTML = '<option value="">Erro ao carregar</option>';
        });
}

function inicializarAutocompletePonto() {
    const input = document.getElementById('filtroPontoMedicaoInput');
    const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
    const btnLimpar = document.getElementById('btnLimparPonto');
    
    if (!input || !dropdown) return;
    
    let timeout = null;
    
    input.addEventListener('input', function() {
        const busca = this.value.trim();
        if (timeout) clearTimeout(timeout);
        if (busca.length < 2) { dropdown.style.display = 'none'; return; }
        timeout = setTimeout(() => buscarPontosMedicao(busca), 300);
    });
    
    input.addEventListener('focus', function() {
        if (this.value.length >= 2) buscarPontosMedicao(this.value);
    });
    
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
    
    if (btnLimpar) {
        btnLimpar.addEventListener('click', function() {
            input.value = '';
            document.getElementById('filtroPontoMedicao').value = '';
            dropdown.style.display = 'none';
        });
    }
}

function buscarPontosMedicao(busca) {
    const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
    if (!dropdown) return;
    
    fetch(`bd/operacoes/getPontosMedicaoSimples.php?busca=${encodeURIComponent(busca)}`)
        .then(r => r.json())
        .then(data => {
            dropdown.innerHTML = '';
            if (data.success && data.data?.length > 0) {
                data.data.forEach(item => {
                    const letra = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                    const codigo = `${item.CD_LOCALIDADE || '000'}-${String(item.CD_PONTO_MEDICAO).padStart(6, '0')}-${letra}-${item.CD_UNIDADE || '00'}`;
                    const nome = item.DS_NOME || '';
                    
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.innerHTML = `<strong>${codigo}</strong> - ${nome}`;
                    div.dataset.cdPonto = item.CD_PONTO_MEDICAO;
                    div.dataset.codigo = codigo;
                    div.dataset.nome = nome;
                    div.dataset.tipoMedidor = item.ID_TIPO_MEDIDOR;
                    div.addEventListener('click', () => selecionarPonto(div.dataset));
                    dropdown.appendChild(div);
                });
                dropdown.style.display = 'block';
            } else {
                dropdown.innerHTML = '<div class="autocomplete-item sem-resultado">Nenhum encontrado</div>';
                dropdown.style.display = 'block';
            }
        })
        .catch(e => console.error('Erro:', e));
}

function selecionarPonto(dados) {
    const input = document.getElementById('filtroPontoMedicaoInput');
    const hidden = document.getElementById('filtroPontoMedicao');
    const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
    
    if (input) input.value = `${dados.codigo} - ${dados.nome}`;
    if (hidden) hidden.value = dados.cdPonto;
    if (dropdown) dropdown.style.display = 'none';
}

// ============================================
// NAVEGAÇÃO DE MÊS
// ============================================

function navegarMes(direcao) {
    const selectMes = document.getElementById('filtroMes');
    const selectAno = document.getElementById('filtroAno');
    
    if (!selectMes || !selectAno) return;
    
    let mes = parseInt(selectMes.value);
    let ano = parseInt(selectAno.value);
    
    if (direcao === -1) {
        mes--;
        if (mes < 1) { mes = 12; ano--; }
    } else if (direcao === 1) {
        mes++;
        if (mes > 12) { mes = 1; ano++; }
    }
    
    // Verificar se ano existe
    let anoExiste = false;
    for (let i = 0; i < selectAno.options.length; i++) {
        if (parseInt(selectAno.options[i].value) === ano) {
            anoExiste = true;
            break;
        }
    }
    
    if (!anoExiste) {
        showToast('Ano não disponível', 'alerta');
        return;
    }
    
    selectMes.value = mes;
    selectAno.value = ano;
    buscarDados();
}

// ============================================
// FUNÇÕES UTILITÁRIAS
// ============================================

function formatarNumero(valor, decimais = 2) {
    if (valor === null || valor === undefined || valor === '' || valor === '-') return '-';
    const num = parseFloat(valor);
    if (isNaN(num)) return '-';
    return num.toLocaleString('pt-BR', { minimumFractionDigits: decimais, maximumFractionDigits: decimais });
}

function formatarDataInput(data) {
    return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}-${String(data.getDate()).padStart(2, '0')}`;
}

function gerarDiasPeriodo(dataInicio, dataFim) {
    const dias = [];
    if (!dataInicio || !dataFim) return dias;
    
    const inicio = new Date(dataInicio + 'T12:00:00');
    const fim = new Date(dataFim + 'T12:00:00');
    if (isNaN(inicio) || isNaN(fim) || inicio > fim) return dias;
    
    const atual = new Date(inicio);
    while (atual <= fim) {
        dias.push(formatarDataInput(atual));
        atual.setDate(atual.getDate() + 1);
    }
    return dias;
}

// ============================================
// BUSCAR DADOS
// ============================================

function buscarDados() {
    const mes = document.getElementById('filtroMes')?.value;
    const ano = document.getElementById('filtroAno')?.value;
    const cdPonto = document.getElementById('filtroPontoMedicao')?.value;
    const tipoEntidade = document.getElementById('selectTipoEntidade')?.value;
    const valorEntidade = document.getElementById('selectValorEntidade')?.value;
    
    if (!mes || !ano) { showToast('Selecione mês e ano', 'alerta'); return; }
    if (!tipoEntidade || !valorEntidade) { showToast('Selecione tipo e valor de entidade', 'alerta'); return; }
    
    periodoDataInicio = formatarDataInput(new Date(ano, mes - 1, 1));
    periodoDataFim = formatarDataInput(new Date(ano, mes, 0));
    
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) loadingOverlay.classList.add('active');
    
    const btnBuscar = document.querySelector('.btn-buscar');
    if (btnBuscar) {
        btnBuscar.disabled = true;
        btnBuscar.innerHTML = '<ion-icon name="sync-outline" class="spin"></ion-icon> Buscando...';
    }
    
    fetch('bd/operacoes/consultarOperacoes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mes, ano, cdPonto, tipoEntidade, valorEntidade })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            exibirResultados(data);
        } else {
            showToast(data.message || 'Erro ao buscar dados', 'erro');
            document.getElementById('emptyState').style.display = 'flex';
            document.getElementById('resultadoCard').style.display = 'none';
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        showToast('Erro ao comunicar com servidor', 'erro');
    })
    .finally(() => {
        if (loadingOverlay) loadingOverlay.classList.remove('active');
        if (btnBuscar) {
            btnBuscar.disabled = false;
            btnBuscar.innerHTML = '<ion-icon name="search-outline"></ion-icon> Buscar';
        }
    });
}

// ============================================
// EXIBIR RESULTADOS
// ============================================

function exibirResultados(data) {
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('resultadoCard').style.display = 'block';
    
    const tipoMedidorEl = document.getElementById('resultadoTipoMedidor');
    if (tipoMedidorEl) tipoMedidorEl.textContent = data.tiposMedidor || '';
    
    const selectValor = document.getElementById('selectValorEntidade');
    const valorNome = selectValor?.options[selectValor.selectedIndex]?.text || 'Resultados';
    const tituloEl = document.getElementById('resultadoTitulo');
    if (tituloEl) tituloEl.textContent = valorNome;
    
    const infoEl = document.getElementById('resultadoInfo');
    if (infoEl) infoEl.innerHTML = `<strong>${data.dados?.length || 0}</strong> registros`;
    
    exibirResumo(data);
    exibirGrafico(data);
    exibirTabela(data);
    
    document.getElementById('resultadoCard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function exibirResumo(data) {
    const resumo = data.resumo || {};
    const unidade = data.unidade || '';
    const el = document.getElementById('resumoCards');
    if (!el) return;
    
    el.innerHTML = `
        <div class="resumo-card"><div class="resumo-card-label">Total</div><div class="resumo-card-value">${formatarNumero(resumo.total || 0)}</div><div class="resumo-card-unit">${unidade}</div></div>
        <div class="resumo-card"><div class="resumo-card-label">Média</div><div class="resumo-card-value">${formatarNumero(resumo.media || 0)}</div><div class="resumo-card-unit">${unidade}</div></div>
        <div class="resumo-card"><div class="resumo-card-label">Mínimo</div><div class="resumo-card-value">${formatarNumero(resumo.minimo || 0)}</div><div class="resumo-card-unit">${unidade}</div></div>
        <div class="resumo-card"><div class="resumo-card-label">Máximo</div><div class="resumo-card-value">${formatarNumero(resumo.maximo || 0)}</div><div class="resumo-card-unit">${unidade}</div></div>
    `;
}

// ============================================
// GRÁFICO
// ============================================

function exibirGrafico(data) {
    const ctx = document.getElementById('graficoOperacoes');
    if (!ctx) return;
    
    if (grafico) grafico.destroy();
    
    const pontosEntrada = {}, pontosSaida = {};
    
    if (data.dados) {
        data.dados.forEach(item => {
            const key = item.ponto_codigo || item.ponto_nome;
            const target = item.fluxo === 'Entrada' ? pontosEntrada : pontosSaida;
            if (!target[key]) target[key] = [];
            target[key].push(item);
        });
    }
    
    const datasets = [];
    const coresEntrada = ['#3b82f6', '#06b6d4', '#8b5cf6'];
    const coresSaida = ['#22c55e', '#10b981', '#14b8a6'];
    
    let idx = 0;
    Object.keys(pontosEntrada).forEach(key => {
        datasets.push({
            label: `↓ ${key}`,
            data: pontosEntrada[key].map(d => ({ x: new Date(d.data), y: parseFloat(d.media_vazao || 0) })),
            borderColor: coresEntrada[idx % coresEntrada.length],
            backgroundColor: coresEntrada[idx % coresEntrada.length] + '20',
            fill: false, tension: 0.3, pointRadius: 3
        });
        idx++;
    });
    
    idx = 0;
    Object.keys(pontosSaida).forEach(key => {
        datasets.push({
            label: `↑ ${key}`,
            data: pontosSaida[key].map(d => ({ x: new Date(d.data), y: parseFloat(d.media_vazao || 0) })),
            borderColor: coresSaida[idx % coresSaida.length],
            backgroundColor: coresSaida[idx % coresSaida.length] + '20',
            fill: false, tension: 0.3, pointRadius: 3
        });
        idx++;
    });
    
    grafico = new Chart(ctx, {
        type: 'line',
        data: { datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true } },
                tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${formatarNumero(ctx.parsed.y)} ${data.unidade || ''}` } }
            },
            scales: {
                x: { type: 'time', time: { unit: 'day', displayFormats: { day: 'dd/MM' } } },
                y: { beginAtZero: true, title: { display: true, text: data.unidade || '' } }
            }
        }
    });
}

// ============================================
// TABELA
// ============================================

function exibirTabela(data) {
    const thead = document.getElementById('tabelaHead');
    const tbody = document.getElementById('tabelaBody');
    const tfoot = document.getElementById('tabelaFoot');
    if (!thead || !tbody) return;
    
    const dias = gerarDiasPeriodo(periodoDataInicio, periodoDataFim);
    if (dias.length === 0) {
        thead.innerHTML = '<tr><th>Dia</th><th>Valor</th></tr>';
        tbody.innerHTML = '<tr><td colspan="2">Período inválido</td></tr>';
        return;
    }
    
    const pontosEntrada = {}, pontosSaida = {}, operacoesPorPonto = {};
    
    if (data.dados?.length > 0) {
        data.dados.forEach(item => {
            let dataStr = item.data;
            if (!dataStr) return;
            if (dataStr.includes('T')) dataStr = dataStr.split('T')[0];
            if (dataStr.includes(' ')) dataStr = dataStr.split(' ')[0];
            
            const key = item.ponto_codigo || item.ponto_nome;
            const target = item.fluxo === 'Entrada' ? pontosEntrada : pontosSaida;
            
            if (!target[key]) {
                target[key] = { nome: item.ponto_nome, codigo: item.ponto_codigo, cd_ponto: item.cd_ponto, tipo_medidor: item.tipo_medidor, operacao: item.operacao || 1, valores: {} };
            }
            if (!target[key].valores[dataStr]) {
                target[key].valores[dataStr] = { soma: 0, count: 0, valor_min: null, valor_max: null, qtd_registros: 0, tratado: false, tipo_medidor: item.tipo_medidor };
            }
            
            const v = target[key].valores[dataStr];
            const media = parseFloat(item.media_vazao || 0);
            v.soma += media; v.count++;
            v.qtd_registros = parseInt(item.qtd_registros || 0);
            v.tratado = item.tratado || false;
            if (item.valor_min !== null) v.valor_min = v.valor_min === null ? parseFloat(item.valor_min) : Math.min(v.valor_min, parseFloat(item.valor_min));
            if (item.valor_max !== null) v.valor_max = v.valor_max === null ? parseFloat(item.valor_max) : Math.max(v.valor_max, parseFloat(item.valor_max));
            operacoesPorPonto[key] = item.operacao || 1;
        });
    }
    
    const keysEntrada = Object.keys(pontosEntrada).sort();
    const keysSaida = Object.keys(pontosSaida).sort();
    
    // Header
    let headerHtml = '<tr><th class="dia-col">Dia</th>';
    keysEntrada.forEach(key => {
        const p = pontosEntrada[key];
        const icone = (p.operacao || 1) == 1 ? '⊕' : '⊖';
        headerHtml += `<th class="header-ponto"><span class="icone-op">${icone}</span> ${p.codigo || ''}<br><small>${p.nome || ''}</small>
            <button type="button" class="btn-grafico-popup" onclick="mostrarGraficoPopup('${key}','entrada')"><ion-icon name="stats-chart-outline"></ion-icon></button></th>`;
    });
    if (keysEntrada.length > 0) headerHtml += '<th class="header-subtotal entrada">Σ Entrada</th>';
    
    keysSaida.forEach(key => {
        const p = pontosSaida[key];
        const icone = (p.operacao || 1) == 1 ? '⊕' : '⊖';
        headerHtml += `<th class="header-ponto"><span class="icone-op">${icone}</span> ${p.codigo || ''}<br><small>${p.nome || ''}</small>
            <button type="button" class="btn-grafico-popup" onclick="mostrarGraficoPopup('${key}','saida')"><ion-icon name="stats-chart-outline"></ion-icon></button></th>`;
    });
    if (keysSaida.length > 0) headerHtml += '<th class="header-subtotal saida">Σ Saída</th>';
    headerHtml += '</tr>';
    thead.innerHTML = headerHtml;
    
    window.dadosGraficoHover = { pontosEntrada, pontosSaida, dias, unidade: data.unidade || '' };
    
    // Body
    let bodyHtml = '';
    dias.forEach(diaStr => {
        const diaNum = parseInt(diaStr.split('-')[2], 10);
        bodyHtml += `<tr><td class="dia-col">${diaNum}</td>`;
        
        let subtotalEntrada = 0, temEntrada = false;
        keysEntrada.forEach(key => {
            const p = pontosEntrada[key];
            const d = p.valores[diaStr];
            const cdPonto = p.cd_ponto;
            if (d && d.count > 0) {
                const media = d.soma / d.count;
                const incompleto = (d.tipo_medidor != 6 && d.qtd_registros < 1440) ? ' incompleto' : '';
                const tratado = d.tratado ? ' tratado' : '';
                const onclick = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto},'${diaStr}',${p.tipo_medidor||1},'${(p.nome||'').replace(/'/g,"\\'")}','${p.codigo||''}')"` : '';
                bodyHtml += `<td class="valor-entrada${incompleto}${tratado}"${onclick}>${formatarNumero(media)}</td>`;
                subtotalEntrada += (p.operacao||1)==2 ? -media : media;
                temEntrada = true;
            } else {
                const onclick = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto},'${diaStr}',${p.tipo_medidor||1},'${(p.nome||'').replace(/'/g,"\\'")}','${p.codigo||''}')"` : '';
                bodyHtml += `<td class="valor-entrada sem-dados"${onclick}>-</td>`;
            }
        });
        if (keysEntrada.length > 0) {
            bodyHtml += temEntrada ? `<td class="valor-subtotal entrada ${subtotalEntrada>=0?'positivo':'negativo'}">${formatarNumero(subtotalEntrada)}</td>` : '<td class="valor-subtotal entrada sem-dados">-</td>';
        }
        
        let subtotalSaida = 0, temSaida = false;
        keysSaida.forEach(key => {
            const p = pontosSaida[key];
            const d = p.valores[diaStr];
            const cdPonto = p.cd_ponto;
            if (d && d.count > 0) {
                const media = d.soma / d.count;
                const incompleto = (d.tipo_medidor != 6 && d.qtd_registros < 1440) ? ' incompleto' : '';
                const tratado = d.tratado ? ' tratado' : '';
                const onclick = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto},'${diaStr}',${p.tipo_medidor||1},'${(p.nome||'').replace(/'/g,"\\'")}','${p.codigo||''}')"` : '';
                bodyHtml += `<td class="valor-saida${incompleto}${tratado}"${onclick}>${formatarNumero(media)}</td>`;
                subtotalSaida += (p.operacao||1)==2 ? -media : media;
                temSaida = true;
            } else {
                const onclick = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto},'${diaStr}',${p.tipo_medidor||1},'${(p.nome||'').replace(/'/g,"\\'")}','${p.codigo||''}')"` : '';
                bodyHtml += `<td class="valor-saida sem-dados"${onclick}>-</td>`;
            }
        });
        if (keysSaida.length > 0) {
            bodyHtml += temSaida ? `<td class="valor-subtotal saida ${subtotalSaida>=0?'positivo':'negativo'}">${formatarNumero(subtotalSaida)}</td>` : '<td class="valor-subtotal saida sem-dados">-</td>';
        }
        
        bodyHtml += '</tr>';
    });
    tbody.innerHTML = bodyHtml;
    
    // Footer
    renderizarFooterTabela(tfoot, pontosEntrada, pontosSaida, keysEntrada, keysSaida, dias, operacoesPorPonto);
}

function renderizarFooterTabela(tfoot, pontosEntrada, pontosSaida, keysEntrada, keysSaida, dias, operacoesPorPonto) {
    if (!tfoot) return;
    
    function calc(ponto, dias) {
        let medias = [], soma = 0;
        dias.forEach(d => {
            const v = ponto.valores[d];
            if (v && v.count > 0) { const m = v.soma/v.count; medias.push(m); soma += m; }
        });
        if (medias.length === 0) return { media: '-', minimo: '-', maximo: '-', totalM3: '-' };
        return {
            minimo: formatarNumero(Math.min(...medias)),
            media: formatarNumero(medias.reduce((a,b)=>a+b,0)/medias.length),
            maximo: formatarNumero(Math.max(...medias)),
            totalM3: formatarNumero(soma * 86.4)
        };
    }
    
    let footHtml = '';
    
    // Mínimo
    footHtml += '<tr><td class="dia-col"><span class="stat-label">Mínimo</span></td>';
    keysEntrada.forEach(k => footHtml += `<td>${calc(pontosEntrada[k],dias).minimo}</td>`);
    if (keysEntrada.length > 0) footHtml += '<td>-</td>';
    keysSaida.forEach(k => footHtml += `<td>${calc(pontosSaida[k],dias).minimo}</td>`);
    if (keysSaida.length > 0) footHtml += '<td>-</td>';
    footHtml += '</tr>';
    
    // Média
    footHtml += '<tr><td class="dia-col"><span class="stat-label">Média</span></td>';
    keysEntrada.forEach(k => footHtml += `<td>${calc(pontosEntrada[k],dias).media}</td>`);
    if (keysEntrada.length > 0) footHtml += '<td>-</td>';
    keysSaida.forEach(k => footHtml += `<td>${calc(pontosSaida[k],dias).media}</td>`);
    if (keysSaida.length > 0) footHtml += '<td>-</td>';
    footHtml += '</tr>';
    
    // Máximo
    footHtml += '<tr><td class="dia-col"><span class="stat-label">Máximo</span></td>';
    keysEntrada.forEach(k => footHtml += `<td>${calc(pontosEntrada[k],dias).maximo}</td>`);
    if (keysEntrada.length > 0) footHtml += '<td>-</td>';
    keysSaida.forEach(k => footHtml += `<td>${calc(pontosSaida[k],dias).maximo}</td>`);
    if (keysSaida.length > 0) footHtml += '<td>-</td>';
    footHtml += '</tr>';
    
    // Total m³
    footHtml += '<tr><td class="dia-col"><span class="stat-label">Total m³</span></td>';
    let totalE = 0;
    keysEntrada.forEach(k => {
        const s = calc(pontosEntrada[k],dias);
        const v = s.totalM3 !== '-' ? parseFloat(s.totalM3.replace(/\./g,'').replace(',','.')) : 0;
        totalE += (pontosEntrada[k].operacao||1)==2 ? -v : v;
        footHtml += `<td style="color:#3b82f6">${s.totalM3}</td>`;
    });
    if (keysEntrada.length > 0) footHtml += `<td style="font-weight:700;color:${totalE>=0?'#16a34a':'#dc2626'}">${formatarNumero(totalE)}</td>`;
    
    let totalS = 0;
    keysSaida.forEach(k => {
        const s = calc(pontosSaida[k],dias);
        const v = s.totalM3 !== '-' ? parseFloat(s.totalM3.replace(/\./g,'').replace(',','.')) : 0;
        totalS += (pontosSaida[k].operacao||1)==2 ? -v : v;
        footHtml += `<td style="color:#0369a1">${s.totalM3}</td>`;
    });
    if (keysSaida.length > 0) footHtml += `<td style="font-weight:700;color:${totalS>=0?'#16a34a':'#dc2626'}">${formatarNumero(totalS)}</td>`;
    footHtml += '</tr>';
    
    tfoot.innerHTML = footHtml;
}

// ============================================
// POPUP GRÁFICO
// ============================================

function mostrarGraficoPopup(pontoKey, fluxo) {
    const dados = window.dadosGraficoHover;
    if (!dados) return;
    
    const pontos = fluxo === 'entrada' ? dados.pontosEntrada : dados.pontosSaida;
    const ponto = pontos[pontoKey];
    if (!ponto) return;
    
    document.getElementById('graficoPopupTitulo').textContent = ponto.nome || '-';
    document.getElementById('graficoPopupCodigo').textContent = ponto.codigo || '-';
    
    const popup = document.getElementById('graficoPopup');
    popup.style.left = ((window.innerWidth - 520) / 2) + 'px';
    popup.style.top = ((window.innerHeight - 300) / 2) + 'px';
    popup.classList.add('active');
    
    gerarGraficoPopup(ponto, dados.dias, dados.unidade);
}

function gerarGraficoPopup(ponto, dias, unidade) {
    const ctx = document.getElementById('graficoPopupCanvas');
    if (!ctx) return;
    if (graficoPopupInstance) graficoPopupInstance.destroy();
    
    const labels = [], valores = [], min = [], max = [];
    dias.forEach(d => {
        labels.push(parseInt(d.split('-')[2], 10));
        const v = ponto.valores[d];
        if (v && v.count > 0) {
            const m = v.soma / v.count;
            valores.push(m);
            min.push(v.valor_min !== null ? v.valor_min : m);
            max.push(v.valor_max !== null ? v.valor_max : m);
        } else {
            valores.push(null); min.push(null); max.push(null);
        }
    });
    
    graficoPopupInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Média', data: valores,
                borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)',
                fill: true, tension: 0.3, pointRadius: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const i = ctx.dataIndex;
                            let l = ['Média: ' + formatarNumero(ctx.raw) + ' ' + unidade];
                            if (min[i] !== null) l.push('Mín: ' + formatarNumero(min[i]) + ' ' + unidade);
                            if (max[i] !== null) l.push('Máx: ' + formatarNumero(max[i]) + ' ' + unidade);
                            return l;
                        }
                    }
                }
            },
            scales: {
                x: { title: { display: true, text: 'Dia' } },
                y: { title: { display: true, text: unidade }, beginAtZero: false }
            }
        }
    });
}

function fecharGraficoPopup() {
    document.getElementById('graficoPopup')?.classList.remove('active');
}

// ============================================
// MODAL VALIDAÇÃO
// ============================================

function abrirModalValidacao(cdPonto, data, tipoMedidor, pontoNome, pontoCodigo) {
    validacaoPontoAtual = cdPonto;
    validacaoDataAtual = data;
    validacaoTipoMedidorAtual = tipoMedidor || 1;
    validacaoHorasSelecionadas = [];
    iaChatHistorico = [];
    dadosCompletosIA = null;
    
    document.getElementById('iaChatMensagens').innerHTML = '';
    
    const titulo = document.getElementById('validacaoTitulo');
    if (titulo) titulo.innerHTML = `<span class="ponto-codigo">${pontoCodigo||cdPonto}</span><span class="ponto-nome">${pontoNome||''}</span>`;
    
    const dataEl = document.getElementById('validacaoData');
    if (dataEl) {
        const p = data.split('-');
        dataEl.textContent = p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : data;
    }
    
    document.getElementById('modalValidacao')?.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    carregarDadosValidacao(cdPonto, data);
}

function fecharModalValidacao() {
    document.getElementById('modalValidacao')?.classList.remove('active');
    document.body.style.overflow = '';
    validacaoPontoAtual = null;
    validacaoDataAtual = null;
    validacaoHorasSelecionadas = [];
}

function carregarDadosValidacao(cdPonto, data) {
    const tbody = document.getElementById('validacaoTabelaBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px">Carregando...</td></tr>';
    
    fetch('bd/operacoes/consultarDadosValidacao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cdPonto, data })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            validacaoDadosOriginais = data.dados || [];
            validacaoUnidadeAtual = data.unidade || 'L/s';
            renderizarTabelaHoraria(data.dados, data.unidade);
            renderizarResumoValidacao(data.dados, data.unidade);
            renderizarGraficoValidacao(data.dados, data.unidade);
        } else {
            if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#dc2626">${data.message||'Erro'}</td></tr>`;
        }
    })
    .catch(e => {
        console.error('Erro:', e);
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#dc2626">Erro ao carregar</td></tr>';
    });
}

function renderizarTabelaHoraria(dados, unidade) {
    const tbody = document.getElementById('validacaoTabelaBody');
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    const desabilitar = !podeEditar || validacaoTipoMedidorAtual === 4;
    if (!tbody) return;
    
    const horasMap = {};
    dados.forEach(d => horasMap[d.hora] = d);
    
    let html = '';
    for (let h = 0; h < 24; h++) {
        const d = horasMap[h] || { hora: h, media: null, min: null, max: null, qtd_registros: 0, tratado: false };
        const horaStr = String(h).padStart(2, '0') + ':00';
        const sel = validacaoHorasSelecionadas.includes(h);
        const tratado = d.tratado || false;
        const temDados = d.qtd_registros > 0;
        
        html += `<tr data-hora="${h}" class="${sel ? 'selecionada' : ''} ${tratado ? 'tratado' : ''}">
            <td class="hora-col">
                <label style="display:flex;align-items:center;gap:6px;cursor:${desabilitar?'default':'pointer'}">
                    ${!desabilitar ? `<input type="checkbox" class="hora-checkbox" value="${h}" ${sel?'checked':''} onchange="toggleHora(${h})">` : ''}
                    ${horaStr}
                    ${tratado ? '<span class="badge-tratado">✓</span>' : ''}
                </label>
            </td>
            <td>${temDados ? formatarNumero(isTipoNivel ? d.max : d.media) + ' ' + unidade : '-'}</td>
            <td>${temDados ? formatarNumero(d.min) + ' ' + unidade : '-'}</td>
            <td>${temDados ? formatarNumero(d.max) + ' ' + unidade : '-'}</td>
            <td>${d.qtd_registros || 0}</td>
        </tr>`;
    }
    tbody.innerHTML = html;
    if (!desabilitar) atualizarCheckboxTodos();
}

function renderizarResumoValidacao(dados, unidade) {
    const el = document.getElementById('validacaoResumo');
    if (!el) return;
    
    let min = null, max = null, soma = 0;
    dados.forEach(d => {
        if (d.min !== null && (min === null || d.min < min)) min = d.min;
        if (d.max !== null && (max === null || d.max > max)) max = d.max;
        if (d.media !== null) soma += d.media;
    });
    
    const media = soma > 0 ? soma / 1440 : null;
    el.innerHTML = `
        <div class="resumo-item"><span class="resumo-label">Mínima</span><span class="resumo-valor">${min!==null?formatarNumero(min)+' '+unidade:'-'}</span></div>
        <div class="resumo-item"><span class="resumo-label">Média</span><span class="resumo-valor">${media!==null?formatarNumero(media)+' '+unidade:'-'}</span></div>
        <div class="resumo-item"><span class="resumo-label">Máxima</span><span class="resumo-valor">${max!==null?formatarNumero(max)+' '+unidade:'-'}</span></div>
    `;
}

function renderizarGraficoValidacao(dados, unidade) {
    const ctx = document.getElementById('validacaoGrafico');
    if (!ctx) return;
    if (validacaoGrafico) validacaoGrafico.destroy();
    
    const labels = [], valores = [], cores = [];
    const horasMap = {};
    dados.forEach(d => horasMap[d.hora] = d);
    
    for (let h = 0; h < 24; h++) {
        labels.push(String(h).padStart(2, '0') + 'h');
        const d = horasMap[h];
        if (d && d.qtd_registros > 0) {
            valores.push(validacaoTipoMedidorAtual === 6 ? d.max : d.media);
            cores.push(d.tratado ? '#22c55e' : '#3b82f6');
        } else {
            valores.push(null);
            cores.push('#94a3b8');
        }
    }
    
    validacaoGrafico = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ data: valores, backgroundColor: cores.map(c => c + '80'), borderColor: cores, borderWidth: 1 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true } }
        }
    });
}

// ============================================
// SELEÇÃO HORAS
// ============================================

function toggleHora(hora) {
    const idx = validacaoHorasSelecionadas.indexOf(hora);
    if (idx > -1) validacaoHorasSelecionadas.splice(idx, 1);
    else validacaoHorasSelecionadas.push(hora);
    
    const tr = document.querySelector(`#validacaoTabelaBody tr[data-hora="${hora}"]`);
    if (tr) tr.classList.toggle('selecionada', validacaoHorasSelecionadas.includes(hora));
    
    atualizarCheckboxTodos();
    atualizarPainelEdicao();
}

function toggleTodasHoras() {
    if (validacaoHorasSelecionadas.length === 24) validacaoHorasSelecionadas = [];
    else validacaoHorasSelecionadas = Array.from({ length: 24 }, (_, i) => i);
    
    document.querySelectorAll('#validacaoTabelaBody tr').forEach(tr => {
        const h = parseInt(tr.dataset.hora);
        tr.classList.toggle('selecionada', validacaoHorasSelecionadas.includes(h));
        const cb = tr.querySelector('.hora-checkbox');
        if (cb) cb.checked = validacaoHorasSelecionadas.includes(h);
    });
    
    atualizarCheckboxTodos();
    atualizarPainelEdicao();
}

function atualizarCheckboxTodos() {
    const cb = document.getElementById('checkboxTodos');
    if (cb) {
        cb.checked = validacaoHorasSelecionadas.length === 24;
        cb.indeterminate = validacaoHorasSelecionadas.length > 0 && validacaoHorasSelecionadas.length < 24;
    }
}

function atualizarPainelEdicao() {
    const painel = document.getElementById('painelEdicaoHoras');
    const horasEl = document.getElementById('horasSelecionadas');
    
    if (validacaoHorasSelecionadas.length > 0) {
        if (painel) painel.style.display = 'block';
        if (horasEl) horasEl.textContent = validacaoHorasSelecionadas.sort((a, b) => a - b).map(h => String(h).padStart(2, '0') + ':00').join(', ');
    } else {
        if (painel) painel.style.display = 'none';
    }
}

function limparSelecaoHoras() {
    validacaoHorasSelecionadas = [];
    document.querySelectorAll('#validacaoTabelaBody tr').forEach(tr => {
        tr.classList.remove('selecionada');
        const cb = tr.querySelector('.hora-checkbox');
        if (cb) cb.checked = false;
    });
    atualizarCheckboxTodos();
    atualizarPainelEdicao();
}

// ============================================
// SALVAR VALIDAÇÃO
// ============================================

function salvarValidacaoManual() {
    if (validacaoHorasSelecionadas.length === 0) { showToast('Selecione horas', 'alerta'); return; }
    
    const novoValor = document.getElementById('novoValorManual')?.value;
    const motivo = document.getElementById('motivoAlteracao')?.value;
    if (!novoValor) { showToast('Informe o valor', 'alerta'); return; }
    
    const registros = validacaoHorasSelecionadas.map(h => ({ hora: h, valor: parseFloat(novoValor.replace(',', '.')) }));
    
    fetch('bd/operacoes/salvarValidacao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cdPonto: validacaoPontoAtual, data: validacaoDataAtual, registros, tipoRegistro: 2, tipoMedicao: 2, motivo: parseInt(motivo) || 1 })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Salvo!', 'sucesso');
            dadosCompletosIA = null;
            carregarDadosValidacao(validacaoPontoAtual, validacaoDataAtual);
            limparSelecaoHoras();
            document.getElementById('novoValorManual').value = '';
        } else {
            showToast(data.message || 'Erro', 'erro');
        }
    })
    .catch(e => showToast('Erro: ' + e.message, 'erro'));
}

// ============================================
// CHAT IA
// ============================================

function enviarPerguntaChat(perguntaFixa = null) {
    const input = document.getElementById('iaChatInput');
    const mensagens = document.getElementById('iaChatMensagens');
    const btn = document.getElementById('btnEnviarChat');
    
    const pergunta = perguntaFixa || (input ? input.value.trim() : '');
    if (!pergunta) {
        if (mensagens) mensagens.innerHTML += `<div class="ia-chat-msg ia"><div class="ia-chat-avatar"><ion-icon name="sparkles"></ion-icon></div><div class="ia-chat-bubble">Digite uma pergunta.</div></div>`;
        return;
    }
    
    if (!perguntaFixa && input) input.value = '';
    
    if (mensagens) {
        mensagens.innerHTML += `<div class="ia-chat-msg user"><div class="ia-chat-avatar"><ion-icon name="person"></ion-icon></div><div class="ia-chat-bubble">${escapeHtmlChat(pergunta)}</div></div>`;
        mensagens.innerHTML += `<div class="ia-chat-msg ia" id="typing"><div class="ia-chat-avatar"><ion-icon name="sparkles"></ion-icon></div><div class="ia-chat-bubble"><div class="ia-chat-typing"><span></span><span></span><span></span></div></div></div>`;
        mensagens.scrollTop = mensagens.scrollHeight;
    }
    
    if (btn) btn.disabled = true;
    
    buscarDadosCompletosIA()
        .then(dados => {
            const contexto = construirContextoCompletoChat(dados);
            iaChatHistorico.push({ role: 'user', content: pergunta });
            return fetch('bd/operacoes/testarIA.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contexto, historico: iaChatHistorico })
            });
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('typing')?.remove();
            if (data.success && data.resposta) {
                iaChatHistorico.push({ role: 'assistant', content: data.resposta });
                if (mensagens) {
                    mensagens.innerHTML += `<div class="ia-chat-msg ia"><div class="ia-chat-avatar"><ion-icon name="sparkles"></ion-icon></div><div class="ia-chat-bubble">${formatarRespostaChat(data.resposta)}</div></div>`;
                    mensagens.scrollTop = mensagens.scrollHeight;
                }
            } else {
                if (mensagens) mensagens.innerHTML += `<div class="ia-chat-msg ia"><div class="ia-chat-avatar"><ion-icon name="sparkles"></ion-icon></div><div class="ia-chat-bubble">Erro: ${data.error||'Desconhecido'}</div></div>`;
            }
        })
        .catch(e => {
            document.getElementById('typing')?.remove();
            if (mensagens) mensagens.innerHTML += `<div class="ia-chat-msg ia"><div class="ia-chat-avatar"><ion-icon name="sparkles"></ion-icon></div><div class="ia-chat-bubble">Erro: ${e.message}</div></div>`;
        })
        .finally(() => { if (btn) btn.disabled = false; });
}

function buscarDadosCompletosIA() {
    if (dadosCompletosIA && dadosCompletosIA.cdPonto === validacaoPontoAtual && dadosCompletosIA.data === validacaoDataAtual) {
        return Promise.resolve(dadosCompletosIA.dados);
    }
    
    return fetch('bd/operacoes/consultarDadosIA.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cdPonto: parseInt(validacaoPontoAtual), data: String(validacaoDataAtual) })
    })
    .then(r => r.text())
    .then(text => {
        if (!text) throw new Error('Resposta vazia');
        return JSON.parse(text);
    })
    .then(data => {
        if (data.success) {
            dadosCompletosIA = { cdPonto: validacaoPontoAtual, data: validacaoDataAtual, dados: data.dados };
            return data.dados;
        }
        throw new Error(data.error || 'Erro');
    });
}

function construirContextoCompletoChat(dados) {
    let ctx = '=== DADOS DO SISTEMA ===\n\n';
    const fmt = (v, d = 2) => v === null || v === undefined ? '-' : parseFloat(v).toFixed(d);
    
    if (dados.ponto) {
        ctx += `PONTO: ${dados.ponto.DS_NOME || 'N/A'} (${dados.ponto.CD_PONTO_MEDICAO || 'N/A'})\n\n`;
    }
    
    const dataFmt = validacaoDataAtual ? validacaoDataAtual.split('-').reverse().join('/') : 'N/A';
    ctx += `DATA: ${dataFmt}\n\n`;
    
    if (dados.dia_atual?.length > 0) {
        ctx += 'DADOS HORÁRIOS:\nHora | Média (L/s) | Registros\n';
        dados.dia_atual.forEach(h => {
            ctx += `${String(h.HORA).padStart(2,'0')}:00 | ${fmt(h.MEDIA_VAZAO)} | ${h.QTD_REGISTROS||0}\n`;
        });
        ctx += '\n';
    }
    
    if (dados.historico_mesmo_dia?.medias_por_dia) {
        const hist = dados.historico_mesmo_dia;
        ctx += `\n========================================\nHISTÓRICO (${hist.dia_semana}):\n`;
        ctx += `Semanas válidas: ${hist.semanas_disponiveis}\n\n`;
        
        let validas = 0, soma = 0;
        hist.medias_por_dia.forEach((dia, i) => {
            const num = dia.semana_numero || (i + 1);
            const data = dia.data_formatada;
            const abrev = dia.dia_semana_abrev || '???';
            if (dia.tem_dados) {
                validas++;
                soma += dia.media_vazao;
                ctx += `Semana ${num} (${data} - ${abrev}): ${fmt(dia.media_vazao)} L/s ✓ USADO (${validas}ª)\n`;
            } else {
                ctx += `Semana ${num} (${data} - ${abrev}): ✗ IGNORADO - ${dia.motivo_sem_dados||'Sem dados'}\n`;
            }
        });
        
        if (validas > 0) {
            ctx += `\n>>> ${validas} semanas válidas, Média: ${fmt(soma/validas)} L/s <<<\n`;
        }
        ctx += '========================================\n';
    }
    
    return ctx;
}

function escapeHtmlChat(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

function formatarRespostaChat(texto) {
    const regex = /\[APLICAR_VALORES\]([\s\S]*?)\[\/APLICAR_VALORES\]/;
    const match = texto.match(regex);
    
    if (match) {
        const valores = [];
        match[1].trim().split('\n').filter(l => l.trim()).forEach(l => {
            const m = l.match(/(\d{1,2}):00\s*=\s*([\d.]+)/);
            if (m) valores.push({ hora: parseInt(m[1]), valor: parseFloat(m[2]) });
        });
        
        window.valoresIAParaAplicar = valores;
        texto = texto.replace(regex, '');
        
        let html = '<div class="ia-valores-aplicar"><strong>Valores:</strong><br>';
        valores.forEach(v => html += `• ${String(v.hora).padStart(2,'0')}:00 → <strong>${v.valor.toFixed(2)} L/s</strong><br>`);
        html += '<br><button class="btn-aplicar-valores-ia" onclick="aplicarValoresIA()">✓ Aplicar</button>';
        html += ' <button class="btn-cancelar-valores-ia" onclick="cancelarValoresIA()">✗ Cancelar</button></div>';
        texto += html;
    }
    
    return texto.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
}

function aplicarValoresIA() {
    const valores = window.valoresIAParaAplicar;
    if (!valores?.length) { showToast('Nenhum valor', 'erro'); return; }
    
    fetch('bd/operacoes/salvarValidacao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cdPonto: validacaoPontoAtual, data: validacaoDataAtual, registros: valores.map(v => ({ hora: v.hora, valor: v.valor })), tipoRegistro: 2, tipoMedicao: 2, motivo: 5 })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Aplicado!', 'sucesso');
            dadosCompletosIA = null;
            window.valoresIAParaAplicar = null;
            carregarDadosValidacao(validacaoPontoAtual, validacaoDataAtual);
        } else {
            showToast(data.message || 'Erro', 'erro');
        }
    })
    .catch(e => showToast('Erro: ' + e.message, 'erro'));
}

function cancelarValoresIA() { window.valoresIAParaAplicar = null; showToast('Cancelado', 'info'); }

function enviarPerguntaHorasSelecionadas() {
    if (!validacaoHorasSelecionadas.length) { showToast('Selecione horas', 'alerta'); return; }
    enviarPerguntaChat(`Sugira valores para: ${validacaoHorasSelecionadas.sort((a,b)=>a-b).map(h=>String(h).padStart(2,'0')+':00').join(', ')}`);
}

// ============================================
// URL PARAMS
// ============================================

function abrirValidacaoPorParametros(cdPonto, data) {
    buscarEPreencherPontoAutocomplete(cdPonto, info => {
        buscarDados();
        setTimeout(() => abrirModalValidacao(cdPonto, data, info.tipoMedidor || 1, info.pontoNome || '', info.pontoCodigo || cdPonto), 800);
    });
}

function buscarEPreencherPontoAutocomplete(cdPonto, callback) {
    fetch(`bd/operacoes/getPontosMedicaoSimples.php?busca=${cdPonto}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data?.length > 0) {
                const p = data.data.find(i => String(i.CD_PONTO_MEDICAO) === String(cdPonto));
                if (p) {
                    const letra = letrasTipoMedidor[p.ID_TIPO_MEDIDOR] || 'X';
                    const codigo = `${p.CD_LOCALIDADE||'000'}-${String(p.CD_PONTO_MEDICAO).padStart(6,'0')}-${letra}-${p.CD_UNIDADE||'00'}`;
                    document.getElementById('filtroPontoMedicaoInput').value = `${codigo} - ${p.DS_NOME||''}`;
                    document.getElementById('filtroPontoMedicao').value = cdPonto;
                    callback({ tipoMedidor: p.ID_TIPO_MEDIDOR, pontoNome: p.DS_NOME, pontoCodigo: codigo });
                } else callback({});
            } else callback({});
        })
        .catch(() => callback({}));
}

// ============================================
// TOAST
// ============================================

function showToast(msg, tipo = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) { container = document.createElement('div'); container.className = 'toast-container'; document.body.appendChild(container); }
    
    const icones = { sucesso: 'checkmark-circle', erro: 'close-circle', alerta: 'warning', info: 'information-circle' };
    const toast = document.createElement('div');
    toast.className = `toast ${tipo}`;
    toast.innerHTML = `<div class="toast-icon"><ion-icon name="${icones[tipo]||icones.info}"></ion-icon></div><div class="toast-content"><p class="toast-message">${msg}</p></div><button class="toast-close" onclick="this.parentElement.remove()"><ion-icon name="close"></ion-icon></button>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// ============================================
// EVENTS
// ============================================

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-validacao-overlay')) fecharModalValidacao();
    const popup = document.getElementById('graficoPopup');
    if (popup?.classList.contains('active') && !popup.contains(e.target) && !e.target.closest('.btn-grafico-popup')) fecharGraficoPopup();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { fecharModalValidacao(); fecharGraficoPopup(); }
    if (e.key === 'Enter' && e.target.id === 'iaChatInput') enviarPerguntaChat();
});

console.log('Operações JS v1.1 carregado');