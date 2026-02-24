<?php
/**
 * SIMP - Cadastro em Cascata v3 — Flowchart Visual
 * Canvas interativo com Drawflow + lista lateral read-only
 * @author Bruno - CESAN
 * @version 3.0
 */
include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';
recarregarPermissoesUsuario();
exigePermissaoTela('flowchart', ACESSO_LEITURA);
$podeEditar = podeEditarTela('flowchart');
include_once 'includes/menu.inc.php';

/* Pontos de medição para select */
$pontosMedicao = [];
try {
    $sql = "SELECT PM.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR, L.CD_LOCALIDADE, L.CD_UNIDADE,
                   COALESCE(PM.DS_TAG_VAZAO, PM.DS_TAG_PRESSAO, PM.DS_TAG_RESERVATORIO, PM.DS_TAG_VOLUME, PM.DS_TAG_TEMP_AGUA, PM.DS_TAG_TEMP_AMBIENTE) AS DS_TAG
            FROM SIMP.dbo.PONTO_MEDICAO PM
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
            WHERE (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())
            ORDER BY PM.DS_NOME";
    $stmt = $pdoSIMP->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $letras = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
        $letra = $letras[$row['ID_TIPO_MEDIDOR']] ?? 'X';
        $codigo = ($row['CD_LOCALIDADE'] ?? '0') . '-' . str_pad($row['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' . $letra . '-' . ($row['CD_UNIDADE'] ?? '0');
        $pontosMedicao[] = ['cd' => $row['CD_PONTO_MEDICAO'], 'codigo' => $codigo, 'nome' => $row['DS_NOME'], 'tag' => $row['DS_TAG'] ?? ''];
    }
} catch (Exception $e) {
}

/* Sistemas de Água para select */
$sistemasAgua = [];
try {
    $sql = "SELECT SA.CD_CHAVE, SA.DS_NOME, SA.DS_DESCRICAO,
                   LOC.DS_NOME AS DS_LOCALIDADE
            FROM SIMP.dbo.SISTEMA_AGUA SA
            LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = SA.CD_LOCALIDADE
            ORDER BY SA.DS_NOME";
    $stmt = $pdoSIMP->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sistemasAgua[] = [
            'cd' => (int) $row['CD_CHAVE'],
            'nome' => $row['DS_NOME'],
            'loc' => $row['DS_LOCALIDADE']
        ];
    }
} catch (Exception $e) {
}
?>

<!-- ============================================
     Drawflow CSS + JS (CDN)
     ============================================ -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<link rel="stylesheet" href="style/css/entidadeCascata.css">

<!-- ============================================
     HTML
     ============================================ -->
<div class="page-container">

    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-icon"><ion-icon name="git-network-outline"></ion-icon></div>
            <div>
                <h1>Flowchart</h1>
                <p class="page-header-subtitle">Fluxo físico da água — arraste para posicionar, conecte para definir o
                    caminho</p>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon nos"><ion-icon name="git-network-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stNos">0</h3>
                <p>Total de Nós</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon raizes"><ion-icon name="git-branch-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stRaizes">0</h3>
                <p>Nós Raiz</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon pontos"><ion-icon name="speedometer-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stPontos">0</h3>
                <p>Com Ponto Medição</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon conexoes"><ion-icon name="swap-horizontal-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stConexoes">0</h3>
                <p>Conexões Fluxo</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon niveis"><ion-icon name="layers-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stNiveis">0</h3>
                <p>Níveis Cadastrados</p>
            </div>
        </div>
    </div>

    <!-- Layout principal -->
    <div class="flow-layout">

        <!-- Sidebar: lista read-only -->
        <div class="flow-sidebar">
            <div class="sidebar-header">
                <ion-icon name="list-outline" style="font-size:16px;color:#64748b;"></ion-icon>
                <h3>Navegação</h3>
                <button class="btn-cv" onclick="abrirModalNiveis()" title="Níveis"
                    style="padding:4px 8px;font-size:11px;"><ion-icon name="settings-outline"></ion-icon></button>
            </div>
            <div class="sidebar-search">
                <input type="text" id="sidebarBusca" placeholder="Buscar nó...">
            </div>
            <div class="sidebar-filters" id="sidebarFilters">
                <select id="filtNivel" onchange="filtrarSidebar()" title="Filtrar por nível">
                    <option value="">Todos os níveis</option>
                </select>
                <select id="filtStatus" onchange="filtrarSidebar()">
                    <option value="">Todos</option>
                    <option value="1">Ativos</option>
                    <option value="0">Inativos</option>
                </select>
                <select id="filtPonto" onchange="filtrarSidebar()">
                    <option value="">Todos</option>
                    <option value="1">Com PM</option>
                    <option value="0">Sem PM</option>
                </select>
            </div>
            <div class="sidebar-list" id="sidebarList">
                <p style="text-align:center;color:#94a3b8;font-size:12px;padding:20px;">Carregando...</p>
            </div>
        </div>

        <!-- Área do canvas -->
        <div class="canvas-wrapper">
            <!-- Toolbar -->
            <div class="canvas-toolbar">
                <div class="toolbar-sistema">
                    <select id="selSistema">
                        <option value="">— Todos os Sistemas —</option>
                    </select>
                </div>
                <?php if ($podeEditar): ?>
                    <button class="btn-cv primary" onclick="abrirModalCriarNo()"><ion-icon name="add-outline"></ion-icon>
                        Novo Nó</button>
                    <button class="btn-cv" onclick="abrirWizard()"><ion-icon name="flash-outline"></ion-icon>
                        Wizard</button>
                    <span class="toolbar-sep"></span>
                    <button class="btn-cv danger" onclick="excluirSelecionados()" id="btnExcluir"
                        style="display:none;"><ion-icon name="trash-outline"></ion-icon> <span
                            id="btnExcluirTxt">Excluir</span></button>
                    <button class="btn-cv" onclick="restaurarSelecionado()" id="btnRestaurar"
                        style="display:none;"><ion-icon name="refresh-outline"></ion-icon> Restaurar</button>
                <?php endif; ?>
                <button class="btn-cv maximizar" onclick="toggleMaximizarFlowchart()" id="btnMaximizar"
                    title="Maximizar/Restaurar">
                    <ion-icon name="expand-outline" id="iconMaximizar"></ion-icon>
                </button>
                <div class="toolbar-zoom">
                    <button onclick="zoomCanvas(1)" title="Zoom +"><ion-icon name="add-outline"></ion-icon></button>
                    <span id="zoomLabel">100%</span>
                    <button onclick="zoomCanvas(-1)" title="Zoom −"><ion-icon name="remove-outline"></ion-icon></button>
                    <button onclick="zoomCanvas(0)" title="Resetar"><ion-icon name="scan-outline"></ion-icon></button>
                </div>
            </div>

            <!-- Canvas Drawflow -->
            <div class="canvas-area">
                <!-- Badge de multi-seleção -->
                <div class="multi-sel-badge" id="multiSelBadge">
                    <ion-icon name="checkmark-done-outline"></ion-icon>
                    <span id="multiSelCount">0</span> nó(s) selecionado(s)
                </div>
                <div id="drawflowCanvas"></div>

                <!-- Painel editor do nó selecionado -->
                <div class="node-editor" id="nodeEditor">
                    <div class="ne-header">
                        <h4><ion-icon name="create-outline"></ion-icon> <span id="neTitle">Editar Nó</span></h4>
                        <button class="ne-close" onclick="fecharEditor()"><ion-icon
                                name="close-outline"></ion-icon></button>
                    </div>
                    <div class="ne-body">
                        <input type="hidden" id="neCd">
                        <div class="fg"><label>Nível *</label><select id="neNivel"></select></div>
                        <div class="fg"><label>Nome *</label><input type="text" id="neNome" maxlength="200"
                                placeholder="Nome do nó"></div>
                        <div class="fr">
                            <div class="fg"><label>Fluxo</label><select id="neFluxo">
                                    <option value="">— Nenhum —</option>
                                    <option value="1">Entrada</option>
                                    <option value="2">Saída</option>
                                    <option value="3">Municipal</option>
                                    <option value="4">N/A</option>
                                </select></div>
                            <div class="fg"><label>Operação</label><select id="neOp">
                                    <option value="">— Nenhuma —</option>
                                    <option value="1">Soma (+)</option>
                                    <option value="2">Subtração (−)</option>
                                </select></div>
                        </div>
                        <div class="ponto-box" id="nePontoBox" style="display:none;">
                            <legend><ion-icon name="speedometer-outline"></ion-icon> Ponto de Medição</legend>
                            <div class="fg"><label>Ponto</label><select id="nePonto" style="width:100%;"></select></div>
                        </div>
                        <div class="ponto-box" id="neSistemaAguaBox" style="display:none;">
                            <legend><ion-icon name="git-network-outline"></ion-icon> Sistema de Água</legend>
                            <div class="fg"><label>Sistema</label><select id="neSistemaAgua"
                                    style="width:100%;"></select></div>
                        </div>
                        <div class="fg"><label>Observação</label><textarea id="neObs" rows="2" maxlength="500"
                                placeholder="Opcional"></textarea></div>
                    </div>
                    <?php if ($podeEditar): ?>
                        <div class="ne-footer">
                            <button class="btn-cancel" onclick="fecharEditor()"><ion-icon name="close-outline"></ion-icon>
                                Fechar</button>
                            <button class="btn-save" onclick="salvarNoEditor()"><ion-icon
                                    name="checkmark-outline"></ion-icon> Salvar</button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Painel editor de conexão (diâmetro/rótulo) -->
                <div class="conn-editor" id="connEditor">
                    <h4><ion-icon name="git-branch-outline"></ion-icon> Editar Conexão</h4>
                    <input type="hidden" id="ceCd">
                    <div class="ce-info" id="ceInfo">Origem → Destino</div>
                    <div class="ce-row">
                        <label>Diâmetro da Rede (mm)</label>
                        <input type="number" id="ceDiametro" placeholder="Ex: 600" step="0.01" min="0">
                    </div>
                    <div class="ce-row">
                        <label>Rótulo</label>
                        <input type="text" id="ceRotulo" placeholder="Ex: Adutora DN600" maxlength="100">
                    </div>
                    <div class="ce-btns">
                        <button class="btn-cv" onclick="fecharConnEditor()"><ion-icon name="close-outline"></ion-icon>
                            Fechar</button>
                        <?php if ($podeEditar): ?>
                            <button class="btn-cv primary" onclick="salvarConnEditor()"><ion-icon
                                    name="checkmark-outline"></ion-icon> Salvar</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CRIAR NÓ (rápido) -->
<div class="modal-overlay" id="modalCriarNo">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h3><ion-icon name="add-circle-outline"></ion-icon> Novo Nó</h3>
            <button class="modal-close" onclick="fecharModalCriarNo()"><ion-icon
                    name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <div class="fg"><label style="font-size:12px;font-weight:600;color:#475569;">Nível *</label><select
                    id="mcNivel"
                    style="width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;"></select>
            </div>
            <div class="fg" style="margin-top:12px;"><label style="font-size:12px;font-weight:600;color:#475569;">Nome
                    *</label><input type="text" id="mcNome" maxlength="200" placeholder="Nome do nó"
                    style="width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                <button class="btn-cv" onclick="fecharModalCriarNo()">Cancelar</button>
                <button class="btn-cv primary" onclick="criarNoRapido()"><ion-icon name="checkmark-outline"></ion-icon>
                    Criar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NÍVEIS -->
<div class="modal-overlay" id="modalNiveis">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header">
            <h3><ion-icon name="layers-outline"></ion-icon> Configurar Níveis</h3><button class="modal-close"
                onclick="fecharModalNiveis()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <div id="listaNiveis"></div>
            <?php if ($podeEditar): ?>
                <div class="nivel-form" id="nivelForm">
                    <h4 id="nvFormTitle"
                        style="margin:0 0 10px;font-size:13px;color:#475569;display:flex;align-items:center;gap:6px;">
                        <ion-icon name="add-circle-outline"></ion-icon> <span>Novo Nível</span>
                    </h4>
                    <input type="hidden" id="nvCd">
                    <div style="display:grid;grid-template-columns:1fr 80px;gap:8px;margin-bottom:8px;">
                        <input type="text" id="nvNome" placeholder="Nome do nível" maxlength="100"
                            style="padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                        <input type="color" id="nvCor" value="#607D8B"
                            style="height:38px;cursor:pointer;border:1px solid #e2e8f0;border-radius:8px;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
                        <select id="nvIcone" style="padding:8px;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;">
                            <option value="ellipse-outline">● Padrão</option>
                            <option value="water-outline">💧 Água</option>
                            <option value="flask-outline">🧪 ETA</option>
                            <option value="cube-outline">📦 Reservatório</option>
                            <option value="speedometer-outline">⏱ Medidor</option>
                            <option value="location-outline">📍 Local</option>
                            <option value="business-outline">🏢 Unidade</option>
                            <option value="git-branch-outline">🔀 Ramificação</option>
                            <option value="pulse-outline">📊 Pressão</option>
                            <option value="thermometer-outline">🌡 Temperatura</option>
                            <option value="git-network-outline">🌐 Sistema</option>
                        </select>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">
                            <input type="checkbox" id="nvPonto" style="accent-color:#2563eb;width:16px;height:16px;">
                            Permite Ponto
                        </label>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label
                            style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;padding:8px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                            <input type="checkbox" id="nvSistema" style="accent-color:#2563eb;width:16px;height:16px;">
                            <div>
                                <strong style="color:#1e40af;">É Sistema de Abastecimento</strong>
                                <div style="font-size:10px;color:#64748b;margin-top:2px;">Nós deste nível aparecem no filtro
                                    de sistemas</div>
                            </div>
                        </label>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn-cv primary" onclick="salvarNivel()"
                            style="flex:1;justify-content:center;"><ion-icon name="checkmark-outline"></ion-icon>
                            Salvar</button>
                        <button class="btn-cv" onclick="limparFormNivel()" id="nvBtnCancelar"
                            style="display:none;"><ion-icon name="close-outline"></ion-icon> Cancelar</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL WIZARD -->
<div class="modal-overlay" id="modalWizard">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h3><ion-icon name="flash-outline"></ion-icon> Wizard — Estrutura Pré-Pronta</h3><button class="modal-close"
                onclick="fecharWizard()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <p style="font-size:12px;color:#64748b;margin:0 0 14px;">Escolha um template para criar automaticamente:</p>
            <div class="wizard-templates" id="wizardTemplates"></div>
        </div>
        <div class="wizard-footer">
            <button class="btn-wiz-cancel" onclick="fecharWizard()">Cancelar</button>
            <button class="btn-wiz-ok" onclick="executarWizard()"><ion-icon name="flash-outline"></ion-icon>
                Criar</button>
        </div>
    </div>
</div>

<div class="toast-box" id="toastBox"></div>

<!-- ============================================
     Scripts
     ============================================ -->
<script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    /**
     * SIMP — Cadastro em Cascata v3 (Flowchart)
     * Canvas Drawflow + Sidebar read-only + CRUD backend
     */

    /* ============================================
       Variáveis globais
       ============================================ */
    var editor = null;                // Instância Drawflow
    var flat = [];                    // Todos os nós (array do backend)
    var niveis = [];                  // Níveis cadastrados
    var conexoes = [];                // Conexões do backend
    var sistemaAtual = '';            // CD_CHAVE do nó raiz selecionado como sistema ('' = todos)
    var podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;
    var pontosJson = <?= json_encode($pontosMedicao, JSON_UNESCAPED_UNICODE) ?>;
    var sistemasAguaJson = <?= json_encode($sistemasAgua, JSON_UNESCAPED_UNICODE) ?>;
    var dfToSimp = {};                // Drawflow ID → SIMP CD_CHAVE
    var simpToDf = {};                // SIMP CD_CHAVE → Drawflow ID
    var noSelecionadoCd = null;       // CD_CHAVE do nó selecionado no editor
    var nosSelecionados = [];          // Array de CD_CHAVEs selecionados (multi-seleção com Ctrl)
    var sincronizando = false;        // Flag para evitar loops de eventos
    var posDebounce = {};             // Debounce para salvar posição

    /* ============================================
       Inicialização
       ============================================ */
    document.addEventListener('DOMContentLoaded', function () {
        inicializarDrawflow();
        inicializarBuscaSidebar();
        inicializarSelectPonto();
        inicializarSelectSistemaAgua();
        carregarNiveis(function () {
            carregarDados();
        });
    });

    /** Cria instância Drawflow e registra eventos */
    function inicializarDrawflow() {
        var container = document.getElementById('drawflowCanvas');
        editor = new Drawflow(container);
        editor.reroute = true;        // Setas curvadas
        editor.reroute_fix_curvature = true;
        editor.force_first_input = false;

        if (!podeEditar) {
            editor.editor_mode = 'view'; // Somente leitura
        }

        editor.start();
        editor.zoom_max = 2;      // Limite máximo 200%
        editor.zoom_min = 0.2;    // Limite mínimo 20%
        editor.zoom_value = 0.04; // Passo menor para zoom suave

        /* --- Interceptar remoção nativa (botão X do Drawflow) ---
           O Drawflow remove o nó só visualmente. Precisamos:
           1. Confirmar com o usuário (informando descendentes)
           2. Chamar soft delete no backend
           3. Só então remover do canvas (ou recarregar tudo)
        */
        var _originalRemoveNode = editor.removeNodeId.bind(editor);
        editor.removeNodeId = function (idStr) {
            if (!podeEditar) return;
            var numId = parseInt(idStr.replace('node-', ''));
            var cd = dfToSimp[numId];
            if (!cd) { _originalRemoveNode(idStr); return; }

            var no = flat.find(function (n) { return n.CD_CHAVE == cd; });
            if (!no) { _originalRemoveNode(idStr); return; }

            /* Contar descendentes via conexões do grafo */
            var descIds = obterDescendentes(cd);
            descIds.splice(descIds.indexOf(parseInt(cd)), 1); /* remover o próprio nó */

            var msg = 'Desativar "' + no.DS_NOME + '"?';
            if (descIds.length > 0) {
                msg += '\n\n⚠ Este nó possui ' + descIds.length + ' nó(s) conectado(s).';
            }

            if (!confirm(msg)) return; /* Cancelou: não remove nada */

            /* Chamar backend (soft delete) */
            var fd = new FormData();
            fd.append('cd', cd);
            fd.append('modo', 'soft');
            fetch('bd/entidadeCascata/excluirNodo.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) {
                        toast(d.message, 'ok');
                        fecharEditor();
                        carregarDados(); /* Recarrega tudo do banco */
                    } else {
                        toast(d.message, 'err');
                    }
                }).catch(function () { toast('Erro ao excluir', 'err'); });
        };

        /* --- Eventos --- */

        /**
         * Nó selecionado — suporte a multi-seleção com Ctrl/Cmd
         * Ctrl+Click: adiciona/remove do conjunto de selecionados
         * Click simples: seleciona apenas este nó (limpa anteriores)
         */
        editor.on('nodeSelected', function (dfId) {
            var cd = dfToSimp[dfId];
            if (!cd) return;

            /* Verificar se Ctrl (ou Cmd no Mac) estava pressionado */
            var ctrlPressed = window._lastClickCtrl || false;

            if (ctrlPressed) {
                /* Toggle: se já está na lista, remove; senão, adiciona */
                var idx = nosSelecionados.indexOf(cd);
                if (idx >= 0) {
                    nosSelecionados.splice(idx, 1);
                    removerClasseMultiSel(cd);
                    /* Se removeu o último do editor, fecha */
                    if (noSelecionadoCd == cd) {
                        noSelecionadoCd = nosSelecionados.length > 0 ? nosSelecionados[nosSelecionados.length - 1] : null;
                        if (noSelecionadoCd) abrirEditor(noSelecionadoCd);
                        else fecharEditor();
                    }
                } else {
                    nosSelecionados.push(cd);
                    adicionarClasseMultiSel(cd);
                    noSelecionadoCd = cd;
                    abrirEditor(cd);
                }
            } else {
                /* Clique simples: limpa seleção anterior */
                limparMultiSelecao();
                nosSelecionados = [cd];
                noSelecionadoCd = cd;
                adicionarClasseMultiSel(cd);
                abrirEditor(cd);
            }

            atualizarBadgeMultiSel();
            atualizarBotoesToolbar(noSelecionadoCd);
        });

        /* Nó deselecionado (clique no canvas vazio) */
        editor.on('nodeUnselected', function () {
            /* Só limpa se NÃO estiver segurando Ctrl */
            if (!window._lastClickCtrl) {
                limparMultiSelecao();
                nosSelecionados = [];
                noSelecionadoCd = null;
                fecharEditor();
                atualizarBadgeMultiSel();
                atualizarBotoesToolbar(null);
            }
        });

        /**
         * Capturar estado do Ctrl/Cmd em cada clique no canvas
         * Necessário porque Drawflow não passa o evento original
         */
        container.addEventListener('mousedown', function (ev) {
            window._lastClickCtrl = ev.ctrlKey || ev.metaKey;
        }, true);
        container.addEventListener('mouseup', function () {
            /* Limpar flag após processamento dos eventos Drawflow */
            setTimeout(function () { window._lastClickCtrl = false; }, 50);
        }, true);

        /* Nó movido: salvar posição com debounce */
        editor.on('nodeMoved', function (dfId) {
            if (sincronizando) return;

            /* Atualizar labels em tempo real durante o arrasto */
            renderizarLabelsConexoes();

            var cd = dfToSimp[dfId];
            if (!cd) return;
            var info = editor.getNodeFromId(dfId);
            /* Debounce 500ms por nó */
            clearTimeout(posDebounce[cd]);
            posDebounce[cd] = setTimeout(function () {
                salvarPosicao(cd, Math.round(info.pos_x), Math.round(info.pos_y));
            }, 500);
        });

        /* Conexão criada: salvar no backend */
        editor.on('connectionCreated', function (info) {
            if (sincronizando) return;
            var cdOrigem = dfToSimp[info.output_id];
            var cdDestino = dfToSimp[info.input_id];
            if (!cdOrigem || !cdDestino) return;
            salvarConexaoBackend(cdOrigem, cdDestino);
        });

        /* Conexão removida: excluir no backend */
        editor.on('connectionRemoved', function (info) {
            if (sincronizando) return;
            var cdOrigem = dfToSimp[info.output_id];
            var cdDestino = dfToSimp[info.input_id];
            if (!cdOrigem || !cdDestino) return;
            excluirConexaoBackend(cdOrigem, cdDestino);
        });

        /* Duplo clique no canvas vazio: criar nó */
        container.addEventListener('dblclick', function (ev) {
            if (!podeEditar) return;
            /* Só se clicou no fundo (não em um nó) */
            if (ev.target.closest('.drawflow-node')) return;
            abrirModalCriarNo();
        });
    }

    /* ============================================
       Multi-seleção — Funções auxiliares
       ============================================ */

    /** Adiciona classe visual de multi-seleção ao nó */
    function adicionarClasseMultiSel(cd) {
        var dfId = simpToDf[cd];
        if (!dfId) return;
        var el = document.getElementById('node-' + dfId);
        if (el) el.classList.add('multi-selected');
    }

    /** Remove classe visual de multi-seleção do nó */
    function removerClasseMultiSel(cd) {
        var dfId = simpToDf[cd];
        if (!dfId) return;
        var el = document.getElementById('node-' + dfId);
        if (el) el.classList.remove('multi-selected');
    }

    /** Limpa toda a seleção visual */
    function limparMultiSelecao() {
        document.querySelectorAll('.drawflow-node.multi-selected').forEach(function (el) {
            el.classList.remove('multi-selected');
        });
    }

    /** Atualiza o badge indicador de quantidade selecionada */
    function atualizarBadgeMultiSel() {
        var badge = document.getElementById('multiSelBadge');
        var count = document.getElementById('multiSelCount');
        if (nosSelecionados.length > 1) {
            count.textContent = nosSelecionados.length;
            badge.classList.add('visible');
        } else {
            badge.classList.remove('visible');
        }
    }

    /* ============================================
       Carregamento de dados
       ============================================ */
    function carregarNiveis(cb) {
        fetch('bd/entidadeCascata/listarNiveis.php').then(function (r) { return r.json() }).then(function (d) {
            if (d.success) {
                niveis = d.niveis;
                popularSelectsNivel();
                document.getElementById('stNiveis').textContent = niveis.length;
                if (cb) cb();
            } else toast(d.message, 'err');
        }).catch(function () { toast('Erro ao carregar níveis', 'err') });
    }

    /** Carregar dropdown de Sistemas com Select2 + contagem dinâmica */
    function popularDropdownSistemas() {
        var sel = document.getElementById('selSistema');
        var valAtual = $(sel).val();

        /* Destruir Select2 anterior se existir */
        if ($(sel).hasClass('select2-hidden-accessible')) {
            $(sel).select2('destroy');
        }

        sel.innerHTML = '<option value="">— Todos os Sistemas —</option>';

        /* Filtrar nós cujo nível é marcado como sistema */
        var nosSistema = flat.filter(function (n) {
            return n.OP_EH_SISTEMA == 1 && n.OP_ATIVO == 1;
        });

        nosSistema.forEach(function (r) {
            var qtdDesc = contarDescendentes(r.CD_CHAVE);
            var o = document.createElement('option');
            o.value = r.CD_CHAVE;
            var label = r.DS_SISTEMA_AGUA ? r.DS_SISTEMA_AGUA : r.DS_NOME;
            o.textContent = label + ' — ' + qtdDesc + ' nó(s)';
            sel.appendChild(o);
        });

        /* Inicializar Select2 com pesquisa e template formatado */
        $(sel).select2({
            placeholder: '— Todos os Sistemas —',
            allowClear: true,
            width: '100%',
            templateResult: formatarOpcaoSistema,
            language: {
                noResults: function () { return 'Nenhum sistema encontrado'; },
                searching: function () { return 'Buscando...'; }
            }
        }).off('select2:select select2:clear select2:open')
            .on('select2:select select2:clear', function () {
                filtrarPorSistema();
            }).on('select2:open', function () {
                /* Autofocus no campo de busca */
                setTimeout(function () { document.querySelector('.select2-search__field').focus(); }, 50);
            });

        /* Restaurar valor anterior se existia */
        if (valAtual) $(sel).val(valAtual).trigger('change.select2');
    }

    /** Atualizar contagem no dropdown sem recriar Select2 */
    function atualizarContagemSistemas() {
        var sel = document.getElementById('selSistema');
        for (var i = 1; i < sel.options.length; i++) {
            var cd = sel.options[i].value;
            var qtd = contarDescendentes(cd);
            var no = flat.find(function (n) { return n.CD_CHAVE == cd; });
            var label = (no && no.DS_SISTEMA_AGUA) ? no.DS_SISTEMA_AGUA : (no ? no.DS_NOME : '');
            sel.options[i].textContent = label + ' — ' + qtd + ' nó(s)';
        }
    }

    /** Formatar opção do Select2 de sistemas (nome + contagem) */
    function formatarOpcaoSistema(opt) {
        if (!opt.id) return opt.text; /* placeholder */
        var parts = opt.text.split(' — ');
        var nome = parts[0] || opt.text;
        var qtd = parts[1] || '';
        var el = document.createElement('div');
        el.innerHTML = '<span class="sis-opt-nome">' + esc(nome) + '</span>' +
            (qtd ? '<span class="sis-opt-qtd">' + esc(qtd) + '</span>' : '');
        return el;
    }

    /** Contar descendentes percorrendo conexões do grafo */
    function contarDescendentes(cdRaiz) {
        return obterDescendentes(cdRaiz).length - 1; // -1 pq inclui ele mesmo
    }

    /** Obter IDs de todos os nós alcançáveis a partir de um nó (via conexões) */
    function obterDescendentes(cdRaiz) {
        var ids = [parseInt(cdRaiz)];
        var fila = [parseInt(cdRaiz)];

        while (fila.length > 0) {
            var atual = fila.shift();

            /* Percorrer conexões (setas no canvas) */
            conexoes.forEach(function (cx) {
                var destino = parseInt(cx.CD_NODO_DESTINO);
                if (parseInt(cx.CD_NODO_ORIGEM) === atual && ids.indexOf(destino) === -1) {
                    ids.push(destino);
                    fila.push(destino);
                }
            });
        }
        return ids;
    }

    /** Filtrar canvas pelo sistema selecionado */
    function filtrarPorSistema() {
        sistemaAtual = $('#selSistema').val() || '';
        renderizarCanvas();
        renderizarSidebar();
        atualizarContagemSistemas();
    }

    function carregarDados() {
        Promise.all([
            fetch('bd/entidadeCascata/listarArvore.php?ativos_only=0').then(function (r) { return r.json() }),
            fetch('bd/entidadeCascata/listarConexoes.php').then(function (r) { return r.json() })
        ]).then(function (results) {
            var dArvore = results[0], dConexoes = results[1];
            if (dArvore.success) {
                flat = dArvore.flat;
                atualizarStats(dArvore.stats);
            }
            if (dConexoes.success) {
                conexoes = dConexoes.conexoes;
                document.getElementById('stConexoes').textContent = dConexoes.total || conexoes.length;
            }
            popularDropdownSistemas();
            renderizarCanvas();
            renderizarSidebar();
        }).catch(function () { toast('Erro ao carregar dados', 'err') });
    }

    function atualizarStats(s) {
        document.getElementById('stNos').textContent = s.totalNos || 0;
        document.getElementById('stRaizes').textContent = s.totalRaizes || 0;
        document.getElementById('stPontos').textContent = s.totalComPonto || 0;
    }

    /* ============================================
       Renderizar Canvas (Drawflow)
       ============================================ */
    function renderizarCanvas() {
        sincronizando = true;
        editor.clear();
        dfToSimp = {};
        simpToDf = {};

        /* Determinar nós visíveis (filtro por nó raiz = sistema) */
        var nosVisiveis = flat;
        if (sistemaAtual) {
            var idsDesc = obterDescendentes(sistemaAtual);
            nosVisiveis = flat.filter(function (no) {
                return idsDesc.indexOf(parseInt(no.CD_CHAVE)) >= 0;
            });
        }

        /* Calcular posição auto para nós sem posição salva */
        var autoX = 80, autoY = 80;

        nosVisiveis.forEach(function (no) {
            var cor = no.DS_COR || '#607D8B';
            var icone = no.DS_ICONE || 'ellipse-outline';
            var posX = no.NR_POS_X !== null ? parseInt(no.NR_POS_X) : autoX;
            var posY = no.NR_POS_Y !== null ? parseInt(no.NR_POS_Y) : autoY;

            if (no.NR_POS_X === null) {
                autoX += 260;
                if (autoX > 900) { autoX = 80; autoY += 180; }
            }

            /* HTML do nó — layout completo com todas as informações */
            var fluxoLabels = { '1': 'Entrada', '2': 'Saída', '3': 'Municipal', '4': 'N/A' };
            var fluxoCores = { '1': '#059669', '2': '#dc2626', '3': '#2563eb', '4': '#64748b' };
            var opLabels = { '1': 'Soma (+)', '2': 'Subtração (−)' };
            var fluxoTxt = no.ID_FLUXO ? fluxoLabels[no.ID_FLUXO] || '' : '';
            var fluxoCor = no.ID_FLUXO ? fluxoCores[no.ID_FLUXO] || '#64748b' : '';
            var opTxt = no.ID_OPERACAO ? opLabels[no.ID_OPERACAO] || '' : '';

            /* Buscar código do ponto de medição no array local */
            var pontoInfo = null;
            if (no.CD_PONTO_MEDICAO) {
                pontoInfo = pontosJson.find(function (p) { return p.cd == no.CD_PONTO_MEDICAO; });
            }

            var html = '<div class="df-node-inner" data-cd="' + no.CD_CHAVE + '">';
            /* Cabeçalho: ícone + nome */
            html += '<div class="df-node-head" style="background:' + esc(cor) + '">';
            html += '<ion-icon name="' + esc(icone) + '"></ion-icon>';
            html += '<span>' + esc(no.DS_NOME) + '</span>';
            html += '</div>';
            /* Corpo: detalhes */
            html += '<div class="df-node-body">';
            html += '<div class="df-node-row"><span class="df-label">Nível</span><span class="df-value">' + esc(no.DS_NIVEL) + '</span></div>';
            if (fluxoTxt) {
                html += '<div class="df-node-row"><span class="df-label">Fluxo</span><span class="df-tag" style="background:' + fluxoCor + '">' + fluxoTxt + '</span></div>';
            }
            if (opTxt) {
                html += '<div class="df-node-row"><span class="df-label">Operação</span><span class="df-value">' + opTxt + '</span></div>';
            }
            if (pontoInfo) {
                html += '<div class="df-node-ponto">';
                html += '<div class="df-ponto-head"><ion-icon name="speedometer-outline"></ion-icon> Ponto de Medição</div>';
                html += '<div class="df-ponto-codigo">' + esc(pontoInfo.codigo) + '</div>';
                html += '<div class="df-ponto-nome">' + esc(pontoInfo.nome) + '</div>';
                html += '</div>';
            }
            /* Sistema de Água vinculado */
            if (no.DS_SISTEMA_AGUA) {
                html += '<div class="df-node-sistema">';
                html += '<div class="df-sistema-head"><ion-icon name="git-network-outline"></ion-icon> Sistema de Água</div>';
                html += '<div class="df-sistema-nome">' + esc(no.DS_SISTEMA_AGUA) + '</div>';
                html += '</div>';
            }
            if (no.DS_IDENTIFICADOR) {
                html += '<div class="df-node-row"><span class="df-label">Tag</span><span class="df-value" style="font-family:monospace;font-size:10px;">' + esc(no.DS_IDENTIFICADOR) + '</span></div>';
            }
            if (no.OP_ATIVO == 0) {
                html += '<div class="df-node-inactive"><ion-icon name="close-circle-outline"></ion-icon> Inativo</div>';
            }
            html += '</div></div>';

            /* Definir classe extra */
            var extraClass = 'simp-node';
            if (no.OP_ATIVO == 0) extraClass += ' inactive';

            /* addNode(name, inputs, outputs, posX, posY, className, data, html) */
            var dfId = editor.addNode(
                'simp_' + no.CD_CHAVE,   // name
                1,                        // num inputs
                1,                        // num outputs
                posX, posY,               // posição
                extraClass,
                { cd: no.CD_CHAVE },      // data
                html                      // HTML
            );

            dfToSimp[dfId] = no.CD_CHAVE;
            simpToDf[no.CD_CHAVE] = dfId;
        });

        /* Criar conexões (só entre nós visíveis) */
        conexoes.forEach(function (cx) {
            var dfOut = simpToDf[cx.CD_NODO_ORIGEM];
            var dfIn = simpToDf[cx.CD_NODO_DESTINO];
            if (dfOut && dfIn) {
                try {
                    editor.addConnection(dfOut, dfIn, 'output_1', 'input_1');
                } catch (e) { /* Conexão duplicada ou inválida, ignorar */ }
            }
        });

        sincronizando = false;

        /* Renderizar labels de diâmetro nas conexões */
        setTimeout(function () { renderizarLabelsConexoes(); }, 100);
    }

    /* ============================================
       Sidebar read-only (lista navegável)
       ============================================ */
    function renderizarSidebar() {
        var c = document.getElementById('sidebarList');

        /* Popular filtro de nível (uma vez) */
        var filtNivel = document.getElementById('filtNivel');
        if (filtNivel.options.length <= 1) {
            niveis.forEach(function (n) {
                var o = document.createElement('option');
                o.value = n.CD_CHAVE;
                o.textContent = n.DS_NOME;
                filtNivel.appendChild(o);
            });
        }

        /* Filtrar nós */
        var nosVisiveis = flat;
        if (sistemaAtual) {
            var idsDesc = obterDescendentes(sistemaAtual);
            nosVisiveis = nosVisiveis.filter(function (no) {
                return idsDesc.indexOf(parseInt(no.CD_CHAVE)) >= 0;
            });
        }

        if (!nosVisiveis.length) {
            c.innerHTML = '<p style="text-align:center;color:#94a3b8;font-size:12px;padding:20px;">Nenhum nó' + (sistemaAtual ? ' neste sistema' : ' cadastrado') + '.<br>Clique "Novo Nó" para começar.</p>';
            return;
        }
        var h = '';
        nosVisiveis.forEach(function (no) {
            var prof = parseInt(no.NR_PROFUNDIDADE) || 0;
            var cor = no.DS_COR || '#607D8B';
            var icone = no.DS_ICONE || 'ellipse-outline';
            h += '<div class="sidebar-node' + (no.OP_ATIVO == 0 ? ' inactive' : '') + '"';
            h += ' data-cd="' + no.CD_CHAVE + '"';
            h += ' data-nivel="' + no.CD_ENTIDADE_NIVEL + '"';
            h += ' data-ativo="' + no.OP_ATIVO + '"';
            h += ' data-ponto="' + (no.CD_PONTO_MEDICAO ? '1' : '0') + '"';
            h += ' onclick="navParaNo(' + no.CD_CHAVE + ')">';
            if (prof > 0) h += '<span class="sn-indent" style="width:' + (prof * 14) + 'px;"></span>';
            h += '<span class="sn-icon" style="background:' + cor + '"><ion-icon name="' + icone + '"></ion-icon></span>';
            h += '<span class="sn-name">' + esc(no.DS_NOME) + '</span>';
            h += '<span class="sn-badge">' + esc(no.DS_NIVEL) + '</span>';
            h += '</div>';
        });
        c.innerHTML = h;
    }

    /** Filtrar sidebar por nível, status, ponto */
    function filtrarSidebar() {
        var fNivel = document.getElementById('filtNivel').value;
        var fStatus = document.getElementById('filtStatus').value;
        var fPonto = document.getElementById('filtPonto').value;

        document.querySelectorAll('.sidebar-node').forEach(function (el) {
            var show = true;
            if (fNivel && el.dataset.nivel !== fNivel) show = false;
            if (fStatus && el.dataset.ativo !== fStatus) show = false;
            if (fPonto === '1' && el.dataset.ponto !== '1') show = false;
            if (fPonto === '0' && el.dataset.ponto !== '0') show = false;
            el.style.display = show ? '' : 'none';
        });
    }

    /** Clicar na sidebar: centralizar nó no canvas e selecioná-lo */
    function navParaNo(cd) {
        var dfId = simpToDf[cd];
        if (!dfId) return;

        /* Destacar na sidebar */
        document.querySelectorAll('.sidebar-node.selected').forEach(function (e) { e.classList.remove('selected') });
        var el = document.querySelector('.sidebar-node[data-cd="' + cd + '"]');
        if (el) { el.classList.add('selected'); el.scrollIntoView({ block: 'nearest' }); }

        /* Selecionar no Drawflow */
        var nodeInfo = editor.getNodeFromId(dfId);
        if (nodeInfo) {
            /* Centralizar canvas no nó */
            var container = document.getElementById('drawflowCanvas');
            var cW = container.offsetWidth, cH = container.offsetHeight;
            var zoom = editor.zoom;
            var newX = -(nodeInfo.pos_x * zoom) + cW / 2 - 80;
            var newY = -(nodeInfo.pos_y * zoom) + cH / 2 - 40;
            editor.canvas_x = newX;
            editor.canvas_y = newY;
            var precanvas = container.querySelector('.drawflow');
            if (precanvas) {
                precanvas.style.transform = 'translate(' + newX + 'px, ' + newY + 'px) scale(' + zoom + ')';
            }
        }

        /* Disparar seleção */
        editor.selectNode('node-' + dfId);
        abrirEditor(cd);
        atualizarBotoesToolbar(cd);
    }

    /** Busca na sidebar */
    function inicializarBuscaSidebar() {
        var t = null;
        document.getElementById('sidebarBusca').addEventListener('input', function () {
            clearTimeout(t);
            var v = this.value.trim().toLowerCase();
            t = setTimeout(function () {
                document.querySelectorAll('.sidebar-node').forEach(function (el) {
                    if (!v) { el.style.display = ''; return; }
                    var nome = (el.querySelector('.sn-name') || {}).textContent || '';
                    el.style.display = nome.toLowerCase().indexOf(v) >= 0 ? '' : 'none';
                });
            }, 200);
        });
    }

    /* ============================================
       Editor lateral do nó
       ============================================ */
    function abrirEditor(cd) {
        var no = flat.find(function (n) { return n.CD_CHAVE == cd });
        if (!no) return;
        noSelecionadoCd = cd;

        document.getElementById('neCd').value = cd;
        document.getElementById('neNome').value = no.DS_NOME;
        document.getElementById('neNivel').value = no.CD_ENTIDADE_NIVEL;
        document.getElementById('neFluxo').value = no.ID_FLUXO || '';
        document.getElementById('neOp').value = no.ID_OPERACAO || '';
        document.getElementById('neObs').value = no.DS_OBSERVACAO || '';

        var permitePonto = no.OP_PERMITE_PONTO == 1;
        document.getElementById('nePontoBox').style.display = permitePonto ? 'block' : 'none';
        $('#nePonto').val(no.CD_PONTO_MEDICAO || '').trigger('change');

        /* Sistema de Água — exibir se nível for marcado como sistema */
        var ehSistema = no.OP_EH_SISTEMA == 1;
        document.getElementById('neSistemaAguaBox').style.display = ehSistema ? 'block' : 'none';
        $('#neSistemaAgua').val(no.CD_SISTEMA_AGUA || '').trigger('change');

        document.getElementById('neTitle').textContent = 'Editar: ' + no.DS_NOME;
        document.getElementById('nodeEditor').classList.add('visible');
    }

    function fecharEditor() {
        document.getElementById('nodeEditor').classList.remove('visible');
        noSelecionadoCd = null;
    }

    /** Atualiza nível → mostra/esconde ponto de medição e sistema de água */
    document.addEventListener('change', function (ev) {
        if (ev.target.id === 'neNivel') {
            var opt = ev.target.options[ev.target.selectedIndex];
            var permite = opt && opt.dataset.permitePonto === '1';
            var ehSistema = opt && opt.dataset.ehSistema === '1';
            document.getElementById('nePontoBox').style.display = permite ? 'block' : 'none';
            document.getElementById('neSistemaAguaBox').style.display = ehSistema ? 'block' : 'none';
        }
    });

    /* ============================================
       CRUD — Nós
       ============================================ */

    /** Salvar nó pelo editor lateral */
    function salvarNoEditor() {
        var cd = document.getElementById('neCd').value;
        var nv = document.getElementById('neNivel').value;
        var nm = document.getElementById('neNome').value.trim();
        if (!nv) { toast('Selecione o nível', 'err'); return; }
        if (!nm) { toast('Informe o nome', 'err'); return; }

        var no = flat.find(function (n) { return n.CD_CHAVE == cd });
        var fd = new FormData();
        fd.append('cd', cd);
        fd.append('cdNivel', nv);
        fd.append('nome', nm);
        fd.append('identificador', no ? (no.DS_IDENTIFICADOR || '') : '');
        fd.append('ordem', no ? (no.NR_ORDEM || 0) : 0);
        fd.append('fluxo', document.getElementById('neFluxo').value);
        fd.append('operacao', document.getElementById('neOp').value);
        fd.append('observacao', document.getElementById('neObs').value.trim());
        /* Manter posição */
        if (no && no.NR_POS_X !== null) { fd.append('posX', no.NR_POS_X); fd.append('posY', no.NR_POS_Y); }
        /* Ponto de medição */
        if (document.getElementById('nePontoBox').style.display !== 'none') {
            fd.append('cdPonto', $('#nePonto').val() || '');
        }
        /* Sistema de água */
        if (document.getElementById('neSistemaAguaBox').style.display !== 'none') {
            fd.append('cdSistemaAgua', $('#neSistemaAgua').val() || '');
        }

        fetch('bd/entidadeCascata/salvarNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast(d.message, 'ok'); fecharEditor(); carregarDados(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro de conexão', 'err') });
    }

    /** Criar nó rápido (modal simples) */
    function criarNoRapido() {
        var nv = document.getElementById('mcNivel').value;
        var nm = document.getElementById('mcNome').value.trim();
        if (!nv) { toast('Selecione o nível', 'err'); return; }
        if (!nm) { toast('Informe o nome', 'err'); return; }

        /* Calcular posição no centro visível do canvas */
        var container = document.getElementById('drawflowCanvas');
        var zoom = editor.zoom || 1;
        var posX = Math.round((-editor.canvas_x + container.offsetWidth / 2) / zoom - 80);
        var posY = Math.round((-editor.canvas_y + container.offsetHeight / 2) / zoom - 40);

        var fd = new FormData();
        fd.append('cdNivel', nv);
        fd.append('nome', nm);
        fd.append('identificador', '');
        fd.append('ordem', 0);
        fd.append('fluxo', '');
        fd.append('operacao', '');
        fd.append('observacao', '');
        fd.append('posX', posX);
        fd.append('posY', posY);

        fetch('bd/entidadeCascata/salvarNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast('Nó criado!', 'ok'); fecharModalCriarNo(); carregarDados(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /**
     * Excluir nós selecionados (suporte a multi-seleção)
     * - 1 nó: comportamento original (soft/cascade)
     * - Vários nós: confirmação em lote, exclusão sequencial
     */
    function excluirSelecionados() {
        if (!nosSelecionados.length) { toast('Selecione ao menos um nó', 'inf'); return; }

        /* === Exclusão de um único nó (comportamento original) === */
        if (nosSelecionados.length === 1) {
            var cd = nosSelecionados[0];
            var no = flat.find(function (n) { return n.CD_CHAVE == cd; });
            if (!no) return;

            var jaInativo = no.OP_ATIVO == 0;
            var msg = jaInativo
                ? 'EXCLUIR PERMANENTEMENTE "' + no.DS_NOME + '" e seus descendentes?\n\nEssa ação não pode ser desfeita!'
                : 'Desativar "' + no.DS_NOME + '" e seus descendentes?';

            if (!confirm(msg)) return;

            var fd = new FormData();
            fd.append('cd', cd);
            fd.append('modo', jaInativo ? 'cascade' : 'soft');
            fetch('bd/entidadeCascata/excluirNodo.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) {
                        toast(d.message, 'ok');
                        limparSelecaoCompleta();
                        carregarDados();
                    } else toast(d.message, 'err');
                }).catch(function () { toast('Erro ao excluir', 'err'); });
            return;
        }

        /* === Exclusão em lote (múltiplos nós) === */
        var nomes = [];
        var ativos = [];
        var inativos = [];

        nosSelecionados.forEach(function (cd) {
            var no = flat.find(function (n) { return n.CD_CHAVE == cd; });
            if (!no) return;
            nomes.push('• ' + no.DS_NOME);
            if (no.OP_ATIVO == 0) inativos.push(cd);
            else ativos.push(cd);
        });

        /* Montar mensagem de confirmação */
        var msg = 'Excluir ' + nosSelecionados.length + ' nó(s) selecionado(s)?\n\n' + nomes.join('\n');
        if (ativos.length > 0 && inativos.length > 0) {
            msg += '\n\n⚠ ' + ativos.length + ' ativo(s) serão desativados e ' + inativos.length + ' inativo(s) serão excluídos permanentemente.';
        } else if (inativos.length > 0) {
            msg += '\n\n⚠ EXCLUSÃO PERMANENTE — esta ação não pode ser desfeita!';
        }

        if (!confirm(msg)) return;

        /* Processar exclusões sequencialmente */
        var fila = nosSelecionados.slice(); // Cópia do array
        var sucesso = 0;
        var erros = 0;

        function processarProximo() {
            if (fila.length === 0) {
                /* Todas processadas — exibir resultado */
                if (sucesso > 0) toast(sucesso + ' nó(s) excluído(s) com sucesso', 'ok');
                if (erros > 0) toast(erros + ' nó(s) com erro na exclusão', 'err');
                limparSelecaoCompleta();
                carregarDados();
                return;
            }

            var cd = fila.shift();
            var no = flat.find(function (n) { return n.CD_CHAVE == cd; });
            var modo = (no && no.OP_ATIVO == 0) ? 'cascade' : 'soft';

            var fd = new FormData();
            fd.append('cd', cd);
            fd.append('modo', modo);

            fetch('bd/entidadeCascata/excluirNodo.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) sucesso++;
                    else erros++;
                    processarProximo();
                }).catch(function () {
                    erros++;
                    processarProximo();
                });
        }

        processarProximo();
    }

    /** Limpa toda a seleção e fecha o editor */
    function limparSelecaoCompleta() {
        limparMultiSelecao();
        nosSelecionados = [];
        noSelecionadoCd = null;
        fecharEditor();
        atualizarBadgeMultiSel();
        atualizarBotoesToolbar(null);
    }

    /** Restaurar nó desativado */
    function restaurarSelecionado() {
        if (!noSelecionadoCd) { toast('Selecione um nó primeiro', 'inf'); return; }
        var no = flat.find(function (n) { return n.CD_CHAVE == noSelecionadoCd });
        if (!no || !confirm('Restaurar "' + no.DS_NOME + '" e seus descendentes?')) return;

        var fd = new FormData();
        fd.append('cd', noSelecionadoCd);
        fd.append('incluirDescendentes', 1);
        fetch('bd/entidadeCascata/restaurarNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast(d.message, 'ok'); fecharEditor(); carregarDados(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /** Botões Excluir/Restaurar na toolbar (suporte multi-seleção) */
    function atualizarBotoesToolbar(cd) {
        var btnExcl = document.getElementById('btnExcluir');
        var btnRest = document.getElementById('btnRestaurar');
        var btnTxt = document.getElementById('btnExcluirTxt');
        if (!btnExcl) return;

        /* Nenhum selecionado */
        if (!nosSelecionados.length) {
            btnExcl.style.display = 'none';
            btnRest.style.display = 'none';
            return;
        }

        /* Multi-seleção (2+) */
        if (nosSelecionados.length > 1) {
            btnExcl.style.display = 'flex';
            btnTxt.textContent = 'Excluir (' + nosSelecionados.length + ')';
            btnExcl.title = 'Excluir ' + nosSelecionados.length + ' nós selecionados';
            btnRest.style.display = 'none'; /* Restaurar em lote não suportado por enquanto */
            return;
        }

        /* Seleção única — comportamento original */
        var no = flat.find(function (n) { return n.CD_CHAVE == cd; });
        if (!no) return;
        if (no.OP_ATIVO == 0) {
            btnExcl.style.display = 'flex';
            btnTxt.textContent = 'Excluir Definitivo';
            btnExcl.title = 'Exclusão física permanente';
            btnRest.style.display = 'flex';
        } else {
            btnExcl.style.display = 'flex';
            btnTxt.textContent = 'Excluir';
            btnExcl.title = 'Desativar nó';
            btnRest.style.display = 'none';
        }
    }

    /* ============================================
       CRUD — Posição
       ============================================ */
    function salvarPosicao(cd, x, y) {
        /* Atualizar flat local */
        var no = flat.find(function (n) { return n.CD_CHAVE == cd });
        if (no) { no.NR_POS_X = x; no.NR_POS_Y = y; }

        var fd = new FormData();
        fd.append('cd', cd);
        fd.append('posX', x);
        fd.append('posY', y);
        fetch('bd/entidadeCascata/salvarPosicao.php', { method: 'POST', body: fd }).catch(function () { });
    }

    /* ============================================
       CRUD — Conexões
       ============================================ */
    function salvarConexaoBackend(cdOrigem, cdDestino) {
        /* Verificar duplicata local */
        var existe = conexoes.find(function (c) { return c.CD_NODO_ORIGEM == cdOrigem && c.CD_NODO_DESTINO == cdDestino });
        if (existe) return;

        var fd = new FormData();
        fd.append('cdOrigem', cdOrigem);
        fd.append('cdDestino', cdDestino);
        fd.append('rotulo', '');
        fd.append('diametroRede', '');
        fetch('bd/entidadeCascata/salvarConexao.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast('Conexão criada', 'ok'); recarregarConexoes(); }
                else { toast(d.message, 'err'); carregarDados(); }
            }).catch(function () { toast('Erro ao criar conexão', 'err') });
    }

    function excluirConexaoBackend(cdOrigem, cdDestino) {
        /* Encontrar CD_CHAVE da conexão */
        var cx = conexoes.find(function (c) { return c.CD_NODO_ORIGEM == cdOrigem && c.CD_NODO_DESTINO == cdDestino });
        if (!cx) return;

        var fd = new FormData();
        fd.append('cd', cx.CD_CHAVE);
        fetch('bd/entidadeCascata/excluirConexao.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast('Conexão removida', 'ok'); recarregarConexoes(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    function recarregarConexoes() {
        fetch('bd/entidadeCascata/listarConexoes.php').then(function (r) { return r.json() }).then(function (d) {
            if (d.success) {
                conexoes = d.conexoes;
                document.getElementById('stConexoes').textContent = d.total || conexoes.length;
                atualizarContagemSistemas();
                renderizarLabelsConexoes();
            }
        }).catch(function () { });
    }

    /* ============================================
       Zoom
       ============================================ */
    function zoomCanvas(dir) {
        if (dir === 0) {
            editor.zoom_reset();
        } else if (dir > 0) {
            editor.zoom_in();
        } else {
            editor.zoom_out();
        }
        document.getElementById('zoomLabel').textContent = Math.round(editor.zoom * 100) + '%';
    }

    /* ============================================
   Maximizar/Restaurar Flowchart
   Oculta cabeçalho, cards e menu para foco total no canvas.
   ============================================ */
    var flowchartMaximizado = false;

    function toggleMaximizarFlowchart() {
        flowchartMaximizado = !flowchartMaximizado;
        var body = document.body;
        var icone = document.getElementById('iconMaximizar');
        var btn = document.getElementById('btnMaximizar');

        if (flowchartMaximizado) {
            // Guardar estado do sidebar para restaurar depois
            var sidebar = document.getElementById('modernSidebar');
            window._sidebarEstavaColapsado = sidebar ? sidebar.classList.contains('collapsed') : false;

            body.classList.add('flowchart-maximizado');
            if (icone) icone.setAttribute('name', 'contract-outline');
            if (btn) btn.title = 'Restaurar visualização';
        } else {
            body.classList.remove('flowchart-maximizado');
            if (icone) icone.setAttribute('name', 'expand-outline');
            if (btn) btn.title = 'Maximizar';

            // Restaurar estado anterior do sidebar
            var sidebar = document.getElementById('modernSidebar');
            if (sidebar && window._sidebarEstavaColapsado) {
                sidebar.classList.add('collapsed');
                body.classList.add('sidebar-collapsed');
            } else if (sidebar && !window._sidebarEstavaColapsado) {
                sidebar.classList.remove('collapsed');
                body.classList.remove('sidebar-collapsed');
            }
        }

        // Forçar Drawflow a recalcular dimensões após transição
        setTimeout(function () {
            if (editor) {
                editor.zoom_refresh();
            }
        }, 100);
    }

    // ESC para sair do modo maximizado
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && flowchartMaximizado) {
            toggleMaximizarFlowchart();
        }
    });

    /* ============================================
       Editor de Conexão (diâmetro/rótulo)
       ============================================ */
    var connSelecionada = null; // Objeto da conexão selecionada

    /** Abrir editor de conexão ao clicar no label ou na linha */
    function abrirConnEditor(cdConexao, posX, posY) {
        var cx = conexoes.find(function (c) { return c.CD_CHAVE == cdConexao; });
        if (!cx) return;
        connSelecionada = cx;

        document.getElementById('ceCd').value = cx.CD_CHAVE;
        document.getElementById('ceDiametro').value = cx.VL_DIAMETRO_REDE || '';
        document.getElementById('ceRotulo').value = cx.DS_ROTULO || '';

        /* Info: nomes origem → destino */
        var nomeOrig = cx.DS_ORIGEM || 'Nó ' + cx.CD_NODO_ORIGEM;
        var nomeDest = cx.DS_DESTINO || 'Nó ' + cx.CD_NODO_DESTINO;
        document.getElementById('ceInfo').textContent = nomeOrig + ' → ' + nomeDest;

        /* Posicionar perto do clique, dentro do canvas */
        var editorEl = document.getElementById('connEditor');
        var canvasArea = document.querySelector('.canvas-area');
        var rect = canvasArea.getBoundingClientRect();

        var x = posX - rect.left;
        var y = posY - rect.top;

        /* Garantir que não saia da área visível */
        if (x + 290 > canvasArea.offsetWidth) x = canvasArea.offsetWidth - 290;
        if (y + 200 > canvasArea.offsetHeight) y = canvasArea.offsetHeight - 210;
        if (x < 10) x = 10;
        if (y < 10) y = 10;

        editorEl.style.left = x + 'px';
        editorEl.style.top = y + 'px';
        editorEl.classList.add('visible');

        setTimeout(function () { document.getElementById('ceDiametro').focus(); }, 100);
    }

    /** Fechar editor de conexão */
    function fecharConnEditor() {
        document.getElementById('connEditor').classList.remove('visible');
        connSelecionada = null;
    }

    /** Salvar diâmetro/rótulo da conexão */
    function salvarConnEditor() {
        if (!connSelecionada) return;
        var cd = document.getElementById('ceCd').value;
        var diametro = document.getElementById('ceDiametro').value;
        var rotulo = document.getElementById('ceRotulo').value.trim();

        var fd = new FormData();
        fd.append('cd', cd);
        fd.append('cdOrigem', connSelecionada.CD_NODO_ORIGEM);
        fd.append('cdDestino', connSelecionada.CD_NODO_DESTINO);
        fd.append('rotulo', rotulo);
        fd.append('cor', connSelecionada.DS_COR || '#1565C0');
        fd.append('diametroRede', diametro);

        fetch('bd/entidadeCascata/salvarConexao.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    toast('Conexão atualizada!', 'ok');
                    fecharConnEditor();
                    recarregarConexoes();
                    /* Redesenhar labels */
                    setTimeout(function () { renderizarLabelsConexoes(); }, 300);
                } else toast(d.message, 'err');
            }).catch(function () { toast('Erro ao salvar conexão', 'err'); });
    }

   /**
     * Renderiza labels de diâmetro/rótulo sobre as conexões.
     * Recalcula posição com base no ponto médio ATUAL dos nós,
     * mantendo o label sempre acompanhando a linha de conexão.
     */
    function renderizarLabelsConexoes() {
        var container = document.getElementById('drawflowCanvas');
        var precanvas = container.querySelector('.drawflow');
        if (!precanvas) return;

        /* Remover labels anteriores */
        precanvas.querySelectorAll('.connection-label').forEach(function (el) { el.remove(); });

        conexoes.forEach(function (cx) {
            var diametro = cx.VL_DIAMETRO_REDE;
            var rotulo = cx.DS_ROTULO;
            if (!diametro && !rotulo) return;

            var dfOut = simpToDf[cx.CD_NODO_ORIGEM];
            var dfIn = simpToDf[cx.CD_NODO_DESTINO];
            if (!dfOut || !dfIn) return;

            /* Posição REAL do nó no DOM (inclui arrasto em andamento) */
            var elOut = document.getElementById('node-' + dfOut);
            var elIn = document.getElementById('node-' + dfIn);
            if (!elOut || !elIn) return;

            /* Extrair translate do style (Drawflow usa transform ou left/top) */
            var posOut = obterPosicaoNo(elOut);
            var posIn = obterPosicaoNo(elIn);

            /* Ponto médio + offset para centralizar sobre a linha */
            var midX = (posOut.x + posIn.x) / 2 + 100;
            var midY = (posOut.y + posIn.y) / 2 + 20;

            /* Montar texto */
            var texto = '';
            if (rotulo && diametro) texto = rotulo + ' — DN' + parseFloat(diametro);
            else if (diametro) texto = 'DN' + parseFloat(diametro);
            else if (rotulo) texto = rotulo;
            if (!texto) return;

            var label = document.createElement('div');
            label.className = 'connection-label';
            label.innerHTML = '<ion-icon name="resize-outline"></ion-icon> ' + esc(texto);
            label.style.left = midX + 'px';
            label.style.top = midY + 'px';
            label.dataset.cdConexao = cx.CD_CHAVE;

            label.addEventListener('click', function (ev) {
                ev.stopPropagation();
                abrirConnEditor(cx.CD_CHAVE, ev.clientX, ev.clientY);
            });

            precanvas.appendChild(label);
        });
    }

    /**
     * Obtém posição real de um nó Drawflow no canvas (left/top do style).
     * Drawflow define a posição via style.left e style.top no elemento do nó.
     */
    function obterPosicaoNo(el) {
        return {
            x: parseFloat(el.style.left) || 0,
            y: parseFloat(el.style.top) || 0
        };
    }

    /**
     * Detectar clique nas linhas SVG de conexão para abrir editor.
     * As linhas do Drawflow são <svg><path class="main-path">.
     */
    function inicializarCliqueConexoes() {
        var container = document.getElementById('drawflowCanvas');
        container.addEventListener('click', function (ev) {
            var path = ev.target.closest('.main-path');
            if (!path) return;

            /* Identificar a conexão pelo elemento pai .connection */
            var connEl = path.closest('.connection');
            if (!connEl) return;

            /* Extrair IDs de origem/destino do atributo class */
            var classes = connEl.classList;
            var dfOut = null, dfIn = null;
            classes.forEach(function (cls) {
                var mOut = cls.match(/^node_out_node-(\d+)$/);
                var mIn = cls.match(/^node_in_node-(\d+)$/);
                if (mOut) dfOut = parseInt(mOut[1]);
                if (mIn) dfIn = parseInt(mIn[1]);
            });

            if (!dfOut || !dfIn) return;

            var cdOrigem = dfToSimp[dfOut];
            var cdDestino = dfToSimp[dfIn];
            if (!cdOrigem || !cdDestino) return;

            /* Encontrar conexão no array */
            var cx = conexoes.find(function (c) {
                return c.CD_NODO_ORIGEM == cdOrigem && c.CD_NODO_DESTINO == cdDestino;
            });
            if (!cx) return;

            abrirConnEditor(cx.CD_CHAVE, ev.clientX, ev.clientY);
        });
    }

    /* Zoom com scroll do mouse (sem precisar de Ctrl) */
    document.getElementById('drawflowCanvas').addEventListener('wheel', function (e) {
        e.preventDefault();
        if (e.deltaY < 0) {
            editor.zoom_in();
        } else {
            editor.zoom_out();
        }
        document.getElementById('zoomLabel').textContent = Math.round(editor.zoom * 100) + '%';
    }, { passive: false });

    /* Inicializar clique nas conexões para editar diâmetro */
    inicializarCliqueConexoes();

    /* Re-renderizar labels ao fazer zoom */
        editor.on('zoom', function () {
            renderizarLabelsConexoes();
        });

    /* ============================================
       Selects de Nível
       ============================================ */
    function popularSelectsNivel() {
        ['neNivel', 'mcNivel'].forEach(function (id) {
            var s = document.getElementById(id);
            if (!s) return;
            s.innerHTML = '<option value="">— Selecione —</option>';
            niveis.forEach(function (n) {
                var o = document.createElement('option');
                o.value = n.CD_CHAVE;
                o.textContent = n.DS_NOME;
                o.dataset.permitePonto = n.OP_PERMITE_PONTO;
                o.dataset.ehSistema = n.OP_EH_SISTEMA;
                s.appendChild(o);
            });
        });
    }

    /* ============================================
       Select Ponto de Medição (Select2)
       ============================================ */
    function inicializarSelectPonto() {
        var s = document.getElementById('nePonto');
        s.innerHTML = '<option value="">— Selecione —</option>';
        pontosJson.forEach(function (p) {
            var o = document.createElement('option');
            o.value = p.cd;
            o.textContent = p.codigo + ' - ' + p.nome;
            o.dataset.tag = p.tag || '';
            s.appendChild(o);
        });

        /** Matcher customizado: pesquisa por código, nome e TAG */
        function matcherPonto(params, data) {
            if (!params.term || params.term.trim() === '') return data;
            if (!data.element) return null;
            var termo = params.term.toLowerCase();
            var tag = (data.element.dataset.tag || '').toLowerCase();
            var texto = (data.text || '').toLowerCase();
            if (texto.indexOf(termo) > -1 || tag.indexOf(termo) > -1) {
                return data;
            }
            return null;
        }

        $('#nePonto').select2({
            placeholder: '— Buscar por código, nome ou TAG —',
            allowClear: true,
            width: '100%',
            matcher: matcherPonto,
            language: {
                noResults: function () { return 'Nenhum ponto encontrado'; }
            }
        });


        /* Autofocus no campo de busca ao abrir qualquer Select2 da página */
        $(document).on('select2:open', function () {
            setTimeout(function () {
                var campo = document.querySelector('.select2-container--open .select2-search__field');
                if (campo) campo.focus();
            }, 0);
        });
    }

    /** Select2 para Sistema de Água */
    function inicializarSelectSistemaAgua() {
        var s = document.getElementById('neSistemaAgua');
        s.innerHTML = '<option value="">— Selecione —</option>';
        sistemasAguaJson.forEach(function (sa) {
            var o = document.createElement('option');
            o.value = sa.cd;
            o.textContent = sa.nome + (sa.loc ? ' (' + sa.loc + ')' : '');
            s.appendChild(o);
        });
        $('#neSistemaAgua').select2({ placeholder: '— Selecione um sistema —', allowClear: true, width: '100%' });
    }

    /* ============================================
       Modais
       ============================================ */
    function abrirModalCriarNo() {
        document.getElementById('mcNome').value = '';
        document.getElementById('mcNivel').value = '';
        document.getElementById('modalCriarNo').classList.add('active');
        setTimeout(function () { document.getElementById('mcNome').focus() }, 100);
    }
    function fecharModalCriarNo() { document.getElementById('modalCriarNo').classList.remove('active'); }

    function abrirModalNiveis() { document.getElementById('modalNiveis').classList.add('active'); renderListaNiveis(); limparFormNivel(); }
    function fecharModalNiveis() { document.getElementById('modalNiveis').classList.remove('active'); limparFormNivel(); }

    /** Renderiza lista de níveis com botões editar/excluir */
    function renderListaNiveis() {
        var c = document.getElementById('listaNiveis');
        if (!niveis.length) { c.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:12px;">Nenhum nível cadastrado.</p>'; return; }
        c.innerHTML = niveis.map(function (n) {
            /* Contar nós usando este nível */
            var qtdNos = flat.filter(function (f) { return f.CD_ENTIDADE_NIVEL == n.CD_CHAVE }).length;
            return '<div class="nivel-item">' +
                '<span class="ni-icon" style="background:' + (n.DS_COR || '#607D8B') + '"><ion-icon name="' + (n.DS_ICONE || 'ellipse-outline') + '"></ion-icon></span>' +
                '<div class="ni-info"><strong>' + esc(n.DS_NOME) + '</strong>' +
                '<small>Ordem: ' + n.NR_ORDEM + ' | Ponto: ' + (n.OP_PERMITE_PONTO == 1 ? 'Sim' : 'Não') + ' | ' + qtdNos + ' nó(s)' + (n.OP_EH_SISTEMA == 1 ? ' | <strong style="color:#1e40af;">🌐 Sistema</strong>' : '') + '</small></div>' +
                (podeEditar ?
                    '<div style="display:flex;gap:4px;">' +
                    '<button onclick="editarNivel(' + n.CD_CHAVE + ')" title="Editar" style="width:28px;height:28px;border:none;background:transparent;border-radius:6px;cursor:pointer;font-size:15px;color:#64748b;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background=\'#eff6ff\';this.style.color=\'#2563eb\'" onmouseout="this.style.background=\'transparent\';this.style.color=\'#64748b\'"><ion-icon name="create-outline"></ion-icon></button>' +
                    '<button onclick="excluirNivel(' + n.CD_CHAVE + ')" title="Excluir" style="width:28px;height:28px;border:none;background:transparent;border-radius:6px;cursor:pointer;font-size:15px;color:#64748b;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background=\'#fef2f2\';this.style.color=\'#dc2626\'" onmouseout="this.style.background=\'transparent\';this.style.color=\'#64748b\'"><ion-icon name="trash-outline"></ion-icon></button>' +
                    '</div>' : '') +
                '</div>';
        }).join('');
    }

    /** Preencher formulário para edição */
    function editarNivel(cd) {
        var nv = niveis.find(function (n) { return n.CD_CHAVE == cd });
        if (!nv) return;
        document.getElementById('nvCd').value = nv.CD_CHAVE;
        document.getElementById('nvNome').value = nv.DS_NOME;
        document.getElementById('nvCor').value = nv.DS_COR || '#607D8B';
        document.getElementById('nvIcone').value = nv.DS_ICONE || 'ellipse-outline';
        document.getElementById('nvPonto').checked = nv.OP_PERMITE_PONTO == 1;
        document.getElementById('nvSistema').checked = nv.OP_EH_SISTEMA == 1;
        document.getElementById('nvFormTitle').innerHTML = '<ion-icon name="create-outline"></ion-icon> <span>Editar: ' + esc(nv.DS_NOME) + '</span>';
        document.getElementById('nvBtnCancelar').style.display = 'flex';
        document.getElementById('nvNome').focus();
    }

    /** Limpar formulário (volta ao modo criação) */
    function limparFormNivel() {
        document.getElementById('nvCd').value = '';
        document.getElementById('nvNome').value = '';
        document.getElementById('nvCor').value = '#607D8B';
        document.getElementById('nvIcone').value = 'ellipse-outline';
        document.getElementById('nvPonto').checked = false;
        document.getElementById('nvSistema').checked = false;
        document.getElementById('nvFormTitle').innerHTML = '<ion-icon name="add-circle-outline"></ion-icon> <span>Novo Nível</span>';
        document.getElementById('nvBtnCancelar').style.display = 'none';
    }

    /** Salvar nível (criar ou atualizar) */
    function salvarNivel() {
        var nm = document.getElementById('nvNome').value.trim();
        if (!nm) { toast('Informe o nome', 'err'); return; }
        var fd = new FormData();
        var cd = document.getElementById('nvCd').value;
        if (cd) fd.append('cd', cd);
        fd.append('nome', nm);
        fd.append('cor', document.getElementById('nvCor').value);
        fd.append('icone', document.getElementById('nvIcone').value);
        fd.append('permitePonto', document.getElementById('nvPonto').checked ? 1 : 0);
        fd.append('ehSistema', document.getElementById('nvSistema').checked ? 1 : 0);
        fetch('bd/entidadeCascata/salvarNivel.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) {
                    toast(d.message, 'ok');
                    limparFormNivel();
                    carregarNiveis(function () { renderListaNiveis(); carregarDados(); });
                } else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /** Excluir nível (com confirmação) */
    function excluirNivel(cd) {
        var nv = niveis.find(function (n) { return n.CD_CHAVE == cd });
        if (!nv) return;
        var qtdNos = flat.filter(function (f) { return f.CD_ENTIDADE_NIVEL == cd }).length;
        if (qtdNos > 0) {
            toast('Este nível está sendo usado por ' + qtdNos + ' nó(s). Remova os nós primeiro.', 'err');
            return;
        }
        if (!confirm('Excluir o nível "' + nv.DS_NOME + '"?')) return;
        var fd = new FormData();
        fd.append('cd', cd);
        fetch('bd/entidadeCascata/excluirNivel.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) {
                    toast(d.message, 'ok');
                    limparFormNivel();
                    carregarNiveis(function () { renderListaNiveis(); carregarDados(); });
                } else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /* ============================================
       Wizard
       ============================================ */
    var wizardSel = null;
    var wizardTpls = [
        {
            id: 'capt_eta', nome: 'Captação → ETA', icone: 'water-outline', cor: '#2563eb',
            desc: 'Manancial com macro alimentando ETA',
            preview: 'Manancial\n └→ Macro Capt.\n   └→ ETA',
            niveis: ['Manancial/Captação', 'Ponto de Medição', 'Unidade Operacional'],
            nos: [{ nome: 'Manancial', nivel: 0 }, { nome: 'Macro Captação', nivel: 1, pai: 0 }, { nome: 'ETA', nivel: 2, pai: 1 }],
            conexoes: [[0, 1], [1, 2]]
        },
        {
            id: 'eta_res', nome: 'ETA → Reservatório', icone: 'layers-outline', cor: '#059669',
            desc: 'ETA com saídas alimentando reservatório',
            preview: 'ETA\n ├→ Saída 1\n ├→ Saída 2\n └→ Reservatório\n     └→ Nível',
            niveis: ['Unidade Operacional', 'Ponto de Medição', 'Reservatório'],
            nos: [{ nome: 'ETA', nivel: 0 }, { nome: 'Saída ETA 1', nivel: 1, pai: 0 }, { nome: 'Saída ETA 2', nivel: 1, pai: 0 }, { nome: 'Reservatório', nivel: 2, pai: 0 }, { nome: 'Nível Reservatório', nivel: 1, pai: 3 }],
            conexoes: [[0, 1], [0, 2], [0, 3], [3, 4]]
        },
        {
            id: 'completo', nome: 'Captação → ETA → Reservatório', icone: 'git-network-outline', cor: '#7c3aed',
            desc: 'Fluxo completo: captação, tratamento e reservação',
            preview: 'Manancial\n └→ Macro Capt.\n   └→ ETA\n     ├→ Macro Saída\n     └→ Reservatório\n         └→ Nível',
            niveis: ['Manancial/Captação', 'Ponto de Medição', 'Unidade Operacional', 'Reservatório'],
            nos: [{ nome: 'Manancial', nivel: 0 }, { nome: 'Macro Captação', nivel: 1, pai: 0 }, { nome: 'ETA', nivel: 2, pai: 1 }, { nome: 'Macro Saída ETA', nivel: 1, pai: 2 }, { nome: 'Reservatório', nivel: 3, pai: 3 }, { nome: 'Nível Reservatório', nivel: 1, pai: 4 }],
            conexoes: [[0, 1], [1, 2], [2, 3], [3, 4], [4, 5]]
        }
    ];

    function abrirWizard() {
        wizardSel = null;
        var c = document.getElementById('wizardTemplates');
        c.innerHTML = wizardTpls.map(function (t) {
            return '<div class="wizard-card" data-id="' + t.id + '" onclick="selWizard(\'' + t.id + '\')">' +
                '<div class="wizard-card-icon" style="background:' + t.cor + '"><ion-icon name="' + t.icone + '"></ion-icon></div>' +
                '<h4>' + esc(t.nome) + '</h4><p>' + esc(t.desc) + '</p>' +
                '<div class="wizard-preview">' + esc(t.preview) + '</div></div>';
        }).join('');
        document.getElementById('modalWizard').classList.add('active');
    }
    function fecharWizard() { document.getElementById('modalWizard').classList.remove('active'); }
    function selWizard(id) {
        wizardSel = id;
        document.querySelectorAll('.wizard-card').forEach(function (c) { c.classList.remove('selected') });
        var el = document.querySelector('.wizard-card[data-id="' + id + '"]');
        if (el) el.classList.add('selected');
    }
    function executarWizard() {
        if (!wizardSel) { toast('Selecione um template', 'err'); return; }
        var tpl = wizardTpls.find(function (t) { return t.id === wizardSel });
        if (!tpl) return;
        fecharWizard();
        toast('Criando estrutura...', 'inf');
        var cdsCriados = {};
        criarNoWiz(tpl, 0, cdsCriados);
    }
    function criarNoWiz(tpl, idx, cds) {
        if (idx >= tpl.nos.length) { criarCxWiz(tpl.conexoes, 0, cds); return; }
        var item = tpl.nos[idx];
        var nivelEnc = niveis.find(function (n) { return n.DS_NOME === tpl.niveis[item.nivel] });
        if (!nivelEnc) { toast('Nível "' + tpl.niveis[item.nivel] + '" não encontrado. Crie-o primeiro.', 'err'); return; }

        /* Posição automática em grid */
        var baseX = 120, baseY = 100;
        var posX = baseX + (idx % 3) * 250;
        var posY = baseY + Math.floor(idx / 3) * 160;

        var fd = new FormData();
        fd.append('cdNivel', nivelEnc.CD_CHAVE);
        fd.append('nome', item.nome);
        fd.append('identificador', '');
        fd.append('ordem', idx + 1);
        fd.append('fluxo', '');
        fd.append('operacao', '');
        fd.append('observacao', 'Criado por Wizard');
        fd.append('posX', posX);
        fd.append('posY', posY);

        fetch('bd/entidadeCascata/salvarNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { cds[idx] = d.cd; criarNoWiz(tpl, idx + 1, cds); }
                else toast('Erro: ' + d.message, 'err');
            }).catch(function () { toast('Erro de conexão', 'err') });
    }
    function criarCxWiz(cxList, idx, cds) {
        if (idx >= cxList.length) { toast('Estrutura criada!', 'ok'); carregarDados(); return; }
        var cx = cxList[idx];
        var fd = new FormData();
        fd.append('cdOrigem', cds[cx[0]]);
        fd.append('cdDestino', cds[cx[1]]);
        fd.append('rotulo', '');
        fetch('bd/entidadeCascata/salvarConexao.php', { method: 'POST', body: fd })
            .then(function () { criarCxWiz(cxList, idx + 1, cds) })
            .catch(function () { criarCxWiz(cxList, idx + 1, cds) });
    }

    /* ============================================
       Utilitários
       ============================================ */
    function toast(msg, tipo) {
        var c = document.getElementById('toastBox');
        var ic = tipo === 'ok' ? 'checkmark-circle-outline' : tipo === 'err' ? 'alert-circle-outline' : 'information-circle-outline';
        var d = document.createElement('div');
        d.className = 'toast-msg ' + tipo;
        d.innerHTML = '<ion-icon name="' + ic + '"></ion-icon> ' + esc(msg);
        c.appendChild(d);
        setTimeout(function () { d.remove() }, 4000);
    }
    function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.appendChild(document.createTextNode(t)); return d.innerHTML; }

    /* Fechar modais com ESC */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(function (m) { m.classList.remove('active') });
            fecharEditor();
        }
        /* Fechar editor de conexão */
        if (e.key === 'Escape') fecharConnEditor();
        /* Delete/Backspace: excluir nó selecionado */
        if ((e.key === 'Delete' || e.key === 'Backspace') && noSelecionadoCd && podeEditar) {
            /* Não excluir se foco em input */
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA' || document.activeElement.tagName === 'SELECT') return;
            excluirSelecionados();
        }
    });

    /* Fechar modais ao clicar fora */
    document.querySelectorAll('.modal-overlay').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === this) this.classList.remove('active'); });
    });
</script>

<?php include_once 'includes/footer.inc.php'; ?>