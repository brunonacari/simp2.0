<?php
/**
 * SIMP - Treinamento da IA
 * Interface simplificada com campo único para instruções
 * 
 * @author Bruno
 * @version 2.0
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão de acesso à tela (mínimo leitura)
// Recarregar permissões do banco (garante que estão atualizadas)
recarregarPermissoesUsuario();

// Agora verificar permissão
exigePermissaoTela('Treinamento IA', ACESSO_LEITURA);

// Verifica se pode editar (para ocultar/desabilitar botões)
$podeEditar = podeEditarTela('Treinamento IA');

// Buscar configuração atual da IA
$configIA = [];
try {
    $configFile = __DIR__ . '/bd/config/ia_config.php';
    if (file_exists($configFile)) {
        $configIA = require $configFile;
    }
} catch (Exception $e) {
    // Ignorar
}
$providerAtual = $configIA['provider'] ?? 'deepseek';
?>

<style>
    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 24px;
        max-width: 1200px;
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
    }

    .page-header-subtitle {
        font-size: 14px;
        opacity: 0.9;
        margin: 0;
    }

    .page-header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .provider-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.2);
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
    }

    .provider-badge ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Card Principal
       ============================================ */
    .regras-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .regras-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 16px;
    }

    .regras-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
    }

    .regras-title ion-icon {
        font-size: 22px;
        color: #3b82f6;
    }

    .regras-meta {
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 13px;
        color: #64748b;
    }

    .regras-meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .regras-meta ion-icon {
        font-size: 16px;
    }

    .regras-body {
        padding: 24px;
    }

    .regras-help {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .regras-help-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 10px;
    }

    .regras-help-title ion-icon {
        font-size: 18px;
    }

    .regras-help ul {
        margin: 0;
        padding-left: 20px;
        font-size: 13px;
        color: #1e40af;
        line-height: 1.8;
    }

    .regras-textarea {
        width: 100%;
        min-height: 500px;
        padding: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: 'Monaco', 'Consolas', 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.6;
        color: #334155;
        background: #f8fafc;
        resize: vertical;
        transition: all 0.2s;
    }

    .regras-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        background: white;
    }

    .regras-textarea:disabled {
        background: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
    }

    .regras-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 16px;
    }

    .regras-stats {
        display: flex;
        align-items: center;
        gap: 20px;
        font-size: 13px;
        color: #64748b;
    }

    .regras-stats span {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .regras-stats ion-icon {
        font-size: 16px;
    }

    .regras-actions {
        display: flex;
        gap: 12px;
    }

    /* ============================================
       Botões
       ============================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn ion-icon {
        font-size: 18px;
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #475569;
    }

    .btn-secondary:hover {
        background: #cbd5e1;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* ============================================
       Toast
       ============================================ */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10001;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-radius: 10px;
        background: white;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        animation: slideIn 0.3s ease;
        min-width: 300px;
    }

    .toast.sucesso {
        border-left: 4px solid #16a34a;
    }

    .toast.erro {
        border-left: 4px solid #dc2626;
    }

    .toast.aviso {
        border-left: 4px solid #f59e0b;
    }

    .toast ion-icon {
        font-size: 22px;
    }

    .toast.sucesso ion-icon { color: #16a34a; }
    .toast.erro ion-icon { color: #dc2626; }
    .toast.aviso ion-icon { color: #f59e0b; }

    .toast-message {
        flex: 1;
        font-size: 14px;
        color: #1e293b;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* ============================================
       Loading
       ============================================ */
    .btn-primary.loading {
        pointer-events: none;
    }

    .btn-primary.loading ion-icon {
        animation: spin 1s linear infinite;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }

    .loading-spinner {
        width: 48px;
        height: 48px;
        border: 4px solid #e2e8f0;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* ============================================
       Responsivo
       ============================================ */
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

        .regras-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .regras-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .regras-actions {
            justify-content: flex-end;
        }

        .regras-textarea {
            min-height: 400px;
        }
    }
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="sparkles"></ion-icon>
                </div>
                <div>
                    <h1>Treinamento da IA</h1>
                    <p class="page-header-subtitle">Configure as instruções e regras de comportamento da IA</p>
                </div>
            </div>
            <div class="page-header-actions">
                <div class="provider-badge">
                    <ion-icon name="hardware-chip-outline"></ion-icon>
                    Provider: <?= ucfirst($providerAtual) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Card de Regras -->
    <div class="regras-card">
        <div class="regras-header">
            <div class="regras-title">
                <ion-icon name="document-text-outline"></ion-icon>
                Instruções do Assistente
            </div>
            <div class="regras-meta" id="regrasMeta">
                <!-- Preenchido via JS -->
            </div>
        </div>

        <div class="regras-body">
            <div class="regras-help">
                <div class="regras-help-title">
                    <ion-icon name="bulb-outline"></ion-icon>
                    Dicas para escrever boas instruções
                </div>
                <ul>
                    <li>Seja claro e específico sobre o comportamento esperado</li>
                    <li>Use **texto** para destacar termos importantes</li>
                    <li>Inclua exemplos de formato de resposta quando necessário</li>
                    <li>Defina regras para cálculos (média, soma, fator de tendência)</li>
                    <li>Especifique quando a IA deve pedir confirmação ao usuário</li>
                </ul>
            </div>

            <textarea 
                id="regrasTexto" 
                class="regras-textarea" 
                placeholder="Digite aqui as instruções para a IA..."
                <?= !$podeEditar ? 'disabled' : '' ?>
            ></textarea>
        </div>

        <div class="regras-footer">
            <div class="regras-stats">
                <span>
                    <ion-icon name="text-outline"></ion-icon>
                    <span id="contadorCaracteres">0</span> caracteres
                </span>
                <span>
                    <ion-icon name="reader-outline"></ion-icon>
                    <span id="contadorLinhas">0</span> linhas
                </span>
            </div>
            
            <?php if ($podeEditar): ?>
            <div class="regras-actions">
                <button type="button" class="btn btn-secondary" onclick="restaurarPadrao()">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Restaurar Padrão
                </button>
                <button type="button" class="btn btn-primary" id="btnSalvar" onclick="salvarRegras()">
                    <ion-icon name="save-outline"></ion-icon>
                    Salvar Alterações
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay" style="display: none;">
    <div class="loading-spinner"></div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
    // ============================================
    // Variáveis globais
    // ============================================
    const textarea = document.getElementById('regrasTexto');
    const contadorCaracteres = document.getElementById('contadorCaracteres');
    const contadorLinhas = document.getElementById('contadorLinhas');
    const regrasMeta = document.getElementById('regrasMeta');
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // ============================================
    // Inicialização
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        carregarRegras();
        
        if (textarea) {
            textarea.addEventListener('input', atualizarContadores);
        }
    });

    // ============================================
    // Carregar regras do servidor
    // ============================================
    function carregarRegras() {
        mostrarLoading(true);

        fetch('bd/ia/listarRegras.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.regra) {
                    textarea.value = data.regra.conteudo || '';
                    atualizarContadores();
                    
                    // Atualizar meta informações
                    if (data.regra.dtAtualizacao) {
                        const dt = new Date(data.regra.dtAtualizacao);
                        regrasMeta.innerHTML = `
                            <span>
                                <ion-icon name="time-outline"></ion-icon>
                                Atualizado: ${dt.toLocaleDateString('pt-BR')} ${dt.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}
                            </span>
                        `;
                    }

                    if (data.aviso) {
                        showToast(data.aviso, 'aviso');
                    }
                } else {
                    showToast(data.message || 'Erro ao carregar regras', 'erro');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de comunicação com o servidor', 'erro');
            })
            .finally(() => {
                mostrarLoading(false);
            });
    }

    // ============================================
    // Atualizar contadores
    // ============================================
    function atualizarContadores() {
        const texto = textarea.value;
        contadorCaracteres.textContent = texto.length.toLocaleString('pt-BR');
        contadorLinhas.textContent = (texto.split('\n').length).toLocaleString('pt-BR');
    }

    // ============================================
    // Salvar regras
    // ============================================
    function salvarRegras() {
        const btn = document.getElementById('btnSalvar');
        const conteudo = textarea.value.trim();

        if (!conteudo) {
            showToast('Digite as instruções para a IA', 'aviso');
            textarea.focus();
            return;
        }

        // Estado de loading
        btn.classList.add('loading');
        btn.disabled = true;
        btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Salvando...';

        fetch('bd/ia/salvarRegra.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conteudo: conteudo })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Instruções salvas com sucesso!', 'sucesso');
                
                // Atualizar meta
                const agora = new Date();
                regrasMeta.innerHTML = `
                    <span>
                        <ion-icon name="time-outline"></ion-icon>
                        Atualizado: ${agora.toLocaleDateString('pt-BR')} ${agora.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}
                    </span>
                `;
            } else {
                showToast(data.message || 'Erro ao salvar', 'erro');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro de comunicação com o servidor', 'erro');
        })
        .finally(() => {
            btn.classList.remove('loading');
            btn.disabled = false;
            btn.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar Alterações';
        });
    }

    // ============================================
    // Restaurar padrão
    // ============================================
    function restaurarPadrao() {
        if (!confirm('Deseja restaurar as instruções para o padrão original?\n\nIsso substituirá todo o conteúdo atual.')) {
            return;
        }

        mostrarLoading(true);

        fetch('bd/ia/getRegrasPadrao.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.conteudo) {
                    textarea.value = data.conteudo;
                    atualizarContadores();
                    showToast('Instruções padrão restauradas. Clique em Salvar para confirmar.', 'aviso');
                } else {
                    showToast('Não foi possível carregar as instruções padrão', 'erro');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro ao carregar instruções padrão', 'erro');
            })
            .finally(() => {
                mostrarLoading(false);
            });
    }

    // ============================================
    // Loading overlay
    // ============================================
    function mostrarLoading(show) {
        document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
    }

    // ============================================
    // Toast
    // ============================================
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const icons = {
            sucesso: 'checkmark-circle',
            erro: 'alert-circle',
            aviso: 'warning'
        };

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <ion-icon name="${icons[type] || 'information-circle'}-outline"></ion-icon>
            <span class="toast-message">${message}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
