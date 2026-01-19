<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Formulário de Cálculo de KPC
 * 
 * Tela para inclusão e edição de cálculos de KPC (Constante Pitométrica).
 * Utiliza metodologia de medição pitométrica com leituras em múltiplas posições.
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para editar Cálculo de KPC
$temPermissao = temPermissaoTela('Cálculo do KPC', ACESSO_ESCRITA);

if (!$temPermissao) {
    $_SESSION['msg'] = 'Você não tem permissão para acessar esta funcionalidade.';
    header('Location: calculoKPC.php');
    exit;
}

// Verifica se é edição
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdicao = $id > 0;
$calculoKPC = null;
$leituras = [];

// Se for edição, busca os dados
if ($isEdicao) {
    // Buscar dados do cálculo
    $sql = "SELECT 
                CK.*,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                L.CD_CHAVE AS CD_CHAVE_LOCALIDADE,
                L.CD_LOCALIDADE,
                L.DS_NOME AS DS_LOCALIDADE,
                U.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                TR.DS_NOME AS DS_TECNICO_RESPONSAVEL,
                UR.DS_NOME AS DS_USUARIO_RESPONSAVEL,
                UA.DS_NOME AS DS_USUARIO_ATUALIZACAO
            FROM SIMP.dbo.CALCULO_KPC CK
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON CK.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            LEFT JOIN SIMP.dbo.USUARIO TR ON CK.CD_TECNICO_RESPONSAVEL = TR.CD_USUARIO
            LEFT JOIN SIMP.dbo.USUARIO UR ON CK.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
            LEFT JOIN SIMP.dbo.USUARIO UA ON CK.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
            WHERE CK.CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $calculoKPC = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$calculoKPC) {
        $_SESSION['msg'] = 'Cálculo de KPC não encontrado.';
        $_SESSION['msg_tipo'] = 'erro';
        header('Location: calculoKPC.php');
        exit;
    }
    
    // Buscar leituras
    $sqlLeituras = "SELECT * FROM SIMP.dbo.CALCULO_KPC_LEITURA WHERE CD_CALCULO_KPC = :id ORDER BY CD_POSICAO_LEITURA, CD_ORDEM_LEITURA";
    $stmtLeituras = $pdoSIMP->prepare($sqlLeituras);
    $stmtLeituras->execute([':id' => $id]);
    $leituras = $stmtLeituras->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar Unidades
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar Técnicos (usuários ativos que podem ser técnicos)
$sqlTecnicos = $pdoSIMP->query("SELECT CD_USUARIO, DS_NOME, DS_MATRICULA FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2 ORDER BY DS_NOME");
$tecnicos = $sqlTecnicos->fetchAll(PDO::FETCH_ASSOC);

// Buscar Usuários para Responsável
$sqlUsuarios = $pdoSIMP->query("SELECT CD_USUARIO, DS_NOME, DS_MATRICULA FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2 ORDER BY DS_NOME");
$usuarios = $sqlUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Métodos de medição
$metodos = [
    1 => 'Digital',
    2 => 'Convencional'
];

// Código formatado
$codigoFormatado = $isEdicao ? $calculoKPC['CD_CODIGO'] . '-' . $calculoKPC['CD_ANO'] : 'Novo';
?>

<!-- Select2 para busca nos selects -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* ============================================
       Select2 Customização
       ============================================ */
    .select2-container {
        width: 100% !important;
    }
    
    .select2-container--default .select2-selection--single {
        height: 42px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 12px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
        color: #1e293b;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .select2-dropdown {
        border-color: #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .select2-search--dropdown .select2-search__field {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px 12px;
    }
    
    .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
    }

    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 20px;
        max-width: 1400px;
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
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 4px 0;
    }

    .page-header-subtitle {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .page-header-subtitle .codigo {
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 8px;
        border-radius: 4px;
        font-family: monospace;
        font-weight: 600;
    }

    .btn-voltar {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-voltar:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    /* ============================================
       Form Cards
       ============================================ */
    .form-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: visible;
        margin-bottom: 16px;
    }

    .form-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .form-card-header ion-icon {
        font-size: 16px;
        color: #3b82f6;
    }

    .form-card-header h2 {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .form-card-body {
        padding: 20px;
    }

    /* ============================================
       Form Grid
       ============================================ */
    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -8px 16px -8px;
    }

    .form-row:last-child {
        margin-bottom: 0;
    }

    .form-group {
        padding: 0 8px;
        margin-bottom: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
        box-sizing: border-box;
    }

    .col-12 { width: 100%; }
    .col-8 { width: 66.666667%; }
    .col-6 { width: 50%; }
    .col-4 { width: 33.333333%; }
    .col-3 { width: 25%; }
    .col-2 { width: 16.666667%; }

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

    .form-label .required {
        color: #ef4444;
    }

    .form-label ion-icon {
        font-size: 14px;
        color: #94a3b8;
    }

    .form-control {
        width: 100%;
        padding: 10px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
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

    .form-control:disabled,
    .form-control[readonly] {
        background-color: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
    }

    .form-control.resultado {
        background-color: #ecfdf5;
        border-color: #10b981;
        color: #047857;
        font-weight: 600;
    }

    textarea.form-control {
        min-height: 60px;
        resize: vertical;
    }

    /* ============================================
       Tabela de Leituras
       ============================================ */
    .leituras-container {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    .leituras-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        min-width: 900px;
    }

    .leituras-table th,
    .leituras-table td {
        padding: 4px 6px;
        border: 1px solid #d1d5db;
        text-align: center;
        white-space: nowrap;
    }

    .leituras-table thead th {
        background: #dbeafe;
        font-weight: 600;
        color: #1e40af;
        font-size: 11px;
    }

    .leituras-table thead tr.row-posicao th {
        background: #eff6ff;
        color: #3b82f6;
        font-weight: 500;
        font-size: 10px;
    }

    .leituras-table .col-pontos,
    .leituras-table .leitura-label,
    .leituras-table .media-label {
        background: #f1f5f9;
        font-weight: 600;
        color: #475569;
        text-align: right;
        padding-right: 10px;
        min-width: 100px;
    }

    .leituras-table .media-row td {
        background: #fef3c7;
    }

    .leituras-table .media-label {
        background: #fcd34d;
        color: #92400e;
    }

    .leituras-table input.leitura-input {
        width: 60px;
        padding: 4px 6px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        text-align: right;
        font-size: 11px;
        background: #fff;
    }

    .leituras-table input.leitura-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }

    .leituras-table input.media-input {
        width: 60px;
        padding: 4px 6px;
        border: 1px solid #f59e0b;
        border-radius: 4px;
        text-align: right;
        font-size: 11px;
        font-weight: 600;
        background: #fef3c7;
        color: #92400e;
    }

    /* ============================================
       Botões de Ação
       ============================================ */
    .form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        padding: 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        border-radius: 0 0 12px 12px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        text-decoration: none;
    }

    .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .btn-primary:hover {
        background: #2563eb;
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-success:hover {
        background: #059669;
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
    }

    .btn-calcular {
        background: #8b5cf6;
        color: white;
    }

    .btn-calcular:hover {
        background: #7c3aed;
    }

    /* ============================================
       Campo de Método Convencional
       ============================================ */
    .campo-convencional {
        display: none;
    }

    .campo-convencional.visivel {
        display: flex;
    }

    /* ============================================
       Responsivo
       ============================================ */
    @media (max-width: 992px) {
        .col-2, .col-3 { width: 33.333333%; }
        .col-4 { width: 50%; }
    }

    @media (max-width: 768px) {
        .page-container { padding: 12px; }
        .col-2, .col-3, .col-4, .col-6 { width: 100%; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
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
                    <h1><?= $isEdicao ? 'Editar Cálculo de KPC' : 'Novo Cálculo de KPC' ?></h1>
                    <p class="page-header-subtitle">
                        <?php if ($isEdicao): ?>
                        Código: <span class="codigo"><?= $codigoFormatado ?></span>
                        <?php else: ?>
                        Preencha os dados para realizar o cálculo
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <a href="calculoKPC.php" class="btn-voltar">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Voltar
            </a>
        </div>
    </div>

    <form id="formCalculoKPC" method="POST">
        <input type="hidden" name="cd_chave" value="<?= $isEdicao ? $calculoKPC['CD_CHAVE'] : 0 ?>">
        <?php if ($isEdicao): ?>
        <!-- Campos hidden para enviar valores que estão disabled -->
        <input type="hidden" name="cd_ponto_medicao" value="<?= $calculoKPC['CD_PONTO_MEDICAO'] ?>">
        <?php endif; ?>

        <!-- Dados do Ponto de Medição -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="location-outline"></ion-icon>
                <h2>Ponto de Medição</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="business-outline"></ion-icon>
                            Unidade <span class="required">*</span>
                        </label>
                        <select id="selectUnidade" name="cd_unidade" class="form-control" required onchange="carregarLocalidades()" <?= $isEdicao ? 'disabled' : '' ?>>
                            <option value="">Selecione...</option>
                            <?php foreach ($unidades as $u): ?>
                            <option value="<?= $u['CD_UNIDADE'] ?>" <?= ($isEdicao && $calculoKPC['CD_UNIDADE'] == $u['CD_UNIDADE']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['DS_NOME']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="map-outline"></ion-icon>
                            Localidade <span class="required">*</span>
                        </label>
                        <select id="selectLocalidade" name="cd_localidade" class="form-control" required onchange="carregarPontos()" <?= $isEdicao ? 'disabled' : '' ?>>
                            <option value="">Selecione a unidade primeiro</option>
                            <?php if ($isEdicao): ?>
                            <option value="<?= $calculoKPC['CD_CHAVE_LOCALIDADE'] ?>" selected>
                                <?= $calculoKPC['CD_LOCALIDADE'] ?> - <?= htmlspecialchars($calculoKPC['DS_LOCALIDADE']) ?>
                            </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="speedometer-outline"></ion-icon>
                            Ponto de Medição <span class="required">*</span>
                        </label>
                        <select id="selectPonto" name="cd_ponto_medicao" class="form-control" required <?= $isEdicao ? 'disabled' : '' ?>>
                            <option value="">Selecione a localidade primeiro</option>
                            <?php if ($isEdicao): ?>
                            <option value="<?= $calculoKPC['CD_PONTO_MEDICAO'] ?>" selected>
                                <?= htmlspecialchars($calculoKPC['DS_PONTO_MEDICAO']) ?>
                            </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data da Leitura <span class="required">*</span>
                        </label>
                        <input type="date" name="dt_leitura" class="form-control" required
                               value="<?= $isEdicao ? date('Y-m-d', strtotime($calculoKPC['DT_LEITURA'])) : date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Parâmetros do Cálculo -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="options-outline"></ion-icon>
                <h2>Parâmetros do Cálculo</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="git-branch-outline"></ion-icon>
                            Método <span class="required">*</span>
                        </label>
                        <select id="selectMetodo" name="id_metodo" class="form-control" required onchange="toggleCampoConvencional()">
                            <?php foreach ($metodos as $id => $nome): ?>
                            <option value="<?= $id ?>" <?= ($isEdicao && $calculoKPC['ID_METODO'] == $id) ? 'selected' : '' ?>>
                                <?= $nome ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="resize-outline"></ion-icon>
                            Diâmetro Nominal (mm) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_diametro_nominal" id="vlDiametroNominal" class="form-control" required
                               value="<?= $isEdicao ? $calculoKPC['VL_DIAMETRO_NOMINAL'] : '' ?>" onchange="calcular()">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="resize-outline"></ion-icon>
                            Diâmetro Real (mm) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_diametro_real" id="vlDiametroReal" class="form-control" required
                               value="<?= $isEdicao ? $calculoKPC['VL_DIAMETRO_REAL'] : '' ?>" onchange="atualizarPosicoes(); calcular()">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="analytics-outline"></ion-icon>
                            Projeção da TAP (mm) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_projecao_tap" id="vlProjecaoTap" class="form-control" required
                               value="<?= $isEdicao ? $calculoKPC['VL_PROJECAO_TAP'] : '' ?>" onchange="calcular()">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="ellipse-outline"></ion-icon>
                            Raio do TIP (mm) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_raio_tip" id="vlRaioTip" class="form-control" required
                               value="<?= $isEdicao ? $calculoKPC['VL_RAIO_TIP'] : '' ?>" onchange="calcular()">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="thermometer-outline"></ion-icon>
                            Temperatura (°C) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.1" name="vl_temperatura" id="vlTemperatura" class="form-control" required
                               value="<?= $isEdicao ? $calculoKPC['VL_TEMPERATURA'] : '25' ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-2" id="campoUltimaPosicao">
                        <label class="form-label">
                            <ion-icon name="locate-outline"></ion-icon>
                            Última Posição (mm) <span class="required">*</span>
                        </label>
                        <?php
                        // Buscar última posição do banco (maior VL_POSICAO da leitura)
                        $ultimaPosicao = '';
                        if ($isEdicao && !empty($leituras)) {
                            foreach ($leituras as $l) {
                                if ($l['CD_POSICAO_LEITURA'] == 11 && !empty($l['VL_POSICAO'])) {
                                    $ultimaPosicao = $l['VL_POSICAO'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <input type="number" step="0.01" name="vl_ultima_posicao" id="vlUltimaPosicao" class="form-control" required
                               value="<?= $ultimaPosicao ?>" onchange="atualizarPosicoes()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Responsáveis -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="people-outline"></ion-icon>
                <h2>Responsáveis</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="construct-outline"></ion-icon>
                            Técnico Responsável <span class="required">*</span>
                        </label>
                        <select name="cd_tecnico_responsavel" id="selectTecnico" class="form-control select2-tecnico" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($tecnicos as $t): ?>
                            <option value="<?= $t['CD_USUARIO'] ?>" <?= ($isEdicao && $calculoKPC['CD_TECNICO_RESPONSAVEL'] == $t['CD_USUARIO']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['DS_NOME']) ?> <?= $t['DS_MATRICULA'] ? '(' . $t['DS_MATRICULA'] . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="person-outline"></ion-icon>
                            Usuário Responsável <span class="required">*</span>
                        </label>
                        <select name="cd_usuario_responsavel" id="selectUsuario" class="form-control select2-usuario" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['CD_USUARIO'] ?>" <?= ($isEdicao && $calculoKPC['CD_USUARIO_RESPONSAVEL'] == $u['CD_USUARIO']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['DS_NOME']) ?> <?= $u['DS_MATRICULA'] ? '(' . $u['DS_MATRICULA'] . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="chatbox-outline"></ion-icon>
                            Observação
                        </label>
                        <input type="text" name="ds_observacao" class="form-control" maxlength="200"
                               value="<?= $isEdicao ? htmlspecialchars($calculoKPC['DS_OBSERVACAO']) : '' ?>"
                               placeholder="Observações adicionais...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Leituras -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="list-outline"></ion-icon>
                <h2>Leituras da Deflexão (mm)</h2>
            </div>
            <div class="form-card-body">
                <div class="leituras-container">
                    <table class="leituras-table" id="tabelaLeituras">
                        <thead>
                            <tr>
                                <th class="col-pontos">Pontos:</th>
                                <?php for ($p = 1; $p <= 11; $p++): ?>
                                <th class="col-ponto"><?= $p ?></th>
                                <?php endfor; ?>
                            </tr>
                            <tr class="row-posicao">
                                <th>Posição(mm):</th>
                                <?php 
                                // Calcular posições iniciais baseadas no Diâmetro Real
                                $diametroReal = $isEdicao ? $calculoKPC['VL_DIAMETRO_REAL'] : 0;
                                $ultimaPosicaoValor = $ultimaPosicao ?: 0;
                                
                                for ($p = 1; $p <= 11; $p++): 
                                    if ($p == 1) {
                                        $posCalc = 0;
                                    } elseif ($p == 11) {
                                        $posCalc = $ultimaPosicaoValor;
                                    } else {
                                        $posCalc = ($diametroReal / 10) * ($p - 1);
                                    }
                                ?>
                                <th id="posicao_<?= $p ?>"><?= number_format($posCalc, 2, ',', '.') ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Organiza as leituras em array bidimensional [leitura][ponto]
                            $leiturasOrganizadas = [];
                            foreach ($leituras as $l) {
                                // CD_ORDEM_LEITURA = número da leitura (1-20)
                                // CD_POSICAO_LEITURA = número do ponto (1-11)
                                $leiturasOrganizadas[$l['CD_ORDEM_LEITURA']][$l['CD_POSICAO_LEITURA']] = $l['VL_DEFLEXAO_MEDIDA'];
                            }
                            
                            // 20 linhas de leituras
                            for ($leitura = 1; $leitura <= 20; $leitura++):
                            ?>
                            <tr>
                                <td class="leitura-label">Leitura <?= $leitura ?>:</td>
                                <?php for ($ponto = 1; $ponto <= 11; $ponto++): ?>
                                <td>
                                    <input type="number" step="0.01" 
                                           name="leitura[<?= $leitura ?>][<?= $ponto ?>]"
                                           class="leitura-input"
                                           data-leitura="<?= $leitura ?>"
                                           data-ponto="<?= $ponto ?>"
                                           value="<?= isset($leiturasOrganizadas[$leitura][$ponto]) ? number_format($leiturasOrganizadas[$leitura][$ponto], 2, '.', '') : '' ?>"
                                           onchange="calcularMediaPonto(<?= $ponto ?>)">
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                            <!-- Linha de Médias -->
                            <tr class="media-row">
                                <td class="media-label">Média das Deflexões:</td>
                                <?php for ($ponto = 1; $ponto <= 11; $ponto++): ?>
                                <td>
                                    <input type="text" 
                                           name="media[<?= $ponto ?>]"
                                           id="media_<?= $ponto ?>"
                                           class="media-input"
                                           readonly
                                           value="<?= isset($leiturasOrganizadas[21][$ponto]) ? number_format($leiturasOrganizadas[21][$ponto], 2, '.', '') : '' ?>">
                                </td>
                                <?php endfor; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 16px; text-align: right;">
                    <button type="button" class="btn btn-calcular" onclick="calcular()">
                        <ion-icon name="calculator-outline"></ion-icon>
                        Calcular KPC
                    </button>
                </div>
            </div>
        </div>

        <!-- Resultados do Cálculo -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="analytics-outline"></ion-icon>
                <h2>Resultados do Cálculo</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="pulse-outline"></ion-icon>
                            Fator de Velocidade
                        </label>
                        <input type="text" name="vl_fator_velocidade" id="vlFatorVelocidade" class="form-control resultado" readonly
                               value="<?= $isEdicao ? number_format($calculoKPC['VL_FATOR_VELOCIDADE'], 10, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="expand-outline"></ion-icon>
                            Correção Diâmetro
                        </label>
                        <input type="text" name="vl_correcao_diametro" id="vlCorrecaoDiametro" class="form-control resultado" readonly
                               value="<?= $isEdicao ? number_format($calculoKPC['VL_CORRECAO_DIAMETRO'], 6, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="git-merge-outline"></ion-icon>
                            Correção Projeção TAP
                        </label>
                        <input type="text" name="vl_correcao_projecao_tap" id="vlCorrecaoProjecaoTap" class="form-control resultado" readonly
                               value="<?= $isEdicao ? number_format($calculoKPC['VL_CORRECAO_PROJECAO_TAP'], 2, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="scan-outline"></ion-icon>
                            Área Efetiva (m²)
                        </label>
                        <input type="text" name="vl_area_efetiva" id="vlAreaEfetiva" class="form-control resultado" readonly
                               value="<?= $isEdicao ? number_format($calculoKPC['VL_AREA_EFETIVA'], 6, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="star-outline"></ion-icon>
                            KPC
                        </label>
                        <input type="text" name="vl_kpc" id="vlKPC" class="form-control resultado" readonly
                               value="<?= $isEdicao ? number_format($calculoKPC['VL_KPC'], 10, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="water-outline"></ion-icon>
                            Vazão (L/s)
                        </label>
                        <input type="text" name="vl_vazao" id="vlVazao" class="form-control resultado" readonly
                               value="<?= $isEdicao && $calculoKPC['VL_VAZAO'] ? number_format($calculoKPC['VL_VAZAO'], 2, '.', '') : '' ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Ações do Formulário -->
        <div class="form-card">
            <div class="form-actions">
                <a href="calculoKPC.php" class="btn btn-secondary">
                    <ion-icon name="close-outline"></ion-icon>
                    Cancelar
                </a>
                <button type="submit" class="btn btn-success">
                    <ion-icon name="save-outline"></ion-icon>
                    Salvar
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// ============================================
// Constantes para Cálculo
// ============================================
// Constante Pi
const PI = 3.14159265358979;

// ============================================
// Função para calcular posições baseadas no Diâmetro Real
// Posição 1 = 0
// Posições 2-10 = (Diâmetro Real / 10) * (posição - 1)
// Posição 11 = Última Posição (campo)
// ============================================
function calcularPosicoesPontos() {
    const diametroReal = parseFloat(document.getElementById('vlDiametroReal').value) || 0;
    const ultimaPosicao = parseFloat(document.getElementById('vlUltimaPosicao').value) || 0;
    
    const posicoes = {};
    for (let p = 1; p <= 11; p++) {
        if (p === 1) {
            posicoes[p] = 0;
        } else if (p === 11) {
            posicoes[p] = ultimaPosicao;
        } else {
            posicoes[p] = (diametroReal / 10) * (p - 1);
        }
    }
    return posicoes;
}

// ============================================
// Atualizar posições na tabela
// ============================================
function atualizarPosicoes() {
    const posicoes = calcularPosicoesPontos();
    
    for (let p = 1; p <= 11; p++) {
        const th = document.getElementById(`posicao_${p}`);
        if (th) {
            th.textContent = posicoes[p].toFixed(2).replace('.', ',');
        }
    }
}

// ============================================
// Inicialização
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    toggleCampoConvencional();
    atualizarPosicoes();
    
    // Inicializar Select2 para busca
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.select2-tecnico, .select2-usuario').select2({
            placeholder: 'Digite para buscar...',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() { return "Nenhum resultado encontrado"; },
                searching: function() { return "Buscando..."; }
            }
        });
    }
    
    // Calcular todas as médias ao carregar
    for (let ponto = 1; ponto <= 11; ponto++) {
        calcularMediaPonto(ponto);
    }
});

// ============================================
// Carregamento de Dependências
// ============================================
function carregarLocalidades() {
    const unidade = document.getElementById('selectUnidade').value;
    const select = document.getElementById('selectLocalidade');
    select.innerHTML = '<option value="">Selecione...</option>';
    document.getElementById('selectPonto').innerHTML = '<option value="">Selecione a localidade primeiro</option>';
    
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
    const localidade = document.getElementById('selectLocalidade').value;
    const select = document.getElementById('selectPonto');
    select.innerHTML = '<option value="">Selecione...</option>';
    
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
// Campo Convencional
// ============================================
function toggleCampoConvencional() {
    const metodo = document.getElementById('selectMetodo').value;
    const campo = document.getElementById('campoUltimaPosicao');
    
    if (metodo == '2') {
        campo.classList.add('visivel');
    } else {
        campo.classList.remove('visivel');
    }
}

// ============================================
// Cálculo de Médias por Ponto (Coluna)
// ============================================
function calcularMediaPonto(ponto) {
    let soma = 0;
    let count = 0;
    
    // Percorrer todas as 20 leituras para este ponto
    for (let leitura = 1; leitura <= 20; leitura++) {
        const input = document.querySelector(`input[data-leitura="${leitura}"][data-ponto="${ponto}"]`);
        if (input && input.value !== '' && !isNaN(parseFloat(input.value))) {
            soma += parseFloat(input.value);
            count++;
        }
    }
    
    // Calcular média
    const mediaInput = document.getElementById(`media_${ponto}`);
    if (count > 0) {
        mediaInput.value = (soma / count).toFixed(2);
    } else {
        mediaInput.value = '';
    }
}

// ============================================
// Cálculo Principal do KPC
// ============================================
function calcular() {
    // Calcular todas as médias primeiro
    for (let ponto = 1; ponto <= 11; ponto++) {
        calcularMediaPonto(ponto);
    }
    
    // Obter valores dos parâmetros
    const diametroNominal = parseFloat(document.getElementById('vlDiametroNominal').value) || 0;
    const diametroReal = parseFloat(document.getElementById('vlDiametroReal').value) || 0;
    const projecaoTap = parseFloat(document.getElementById('vlProjecaoTap').value) || 0;
    const raioTip = parseFloat(document.getElementById('vlRaioTip').value) || 0;
    
    // Validar dados mínimos
    if (diametroNominal <= 0 || diametroReal <= 0) {
        alert('Preencha os parâmetros do cálculo (Diâmetro Nominal e Real)');
        return;
    }
    
    // Obter posições calculadas
    const POSICOES_PONTOS = calcularPosicoesPontos();
    
    // Obter médias calculadas
    let medias = [];
    let somaMedias = 0;
    let countMedias = 0;
    
    for (let ponto = 1; ponto <= 11; ponto++) {
        const mediaVal = parseFloat(document.getElementById(`media_${ponto}`).value) || 0;
        medias.push({
            ponto: ponto,
            posicao: POSICOES_PONTOS[ponto],
            media: mediaVal
        });
        
        if (mediaVal > 0) {
            somaMedias += mediaVal;
            countMedias++;
        }
    }
    
    // Calcular fator de velocidade (média das médias)
    let fatorVelocidade = countMedias > 0 ? (somaMedias / countMedias) : 0;
    
    // Correção de diâmetro = Diâmetro Real / Diâmetro Nominal
    let correcaoDiametro = diametroNominal > 0 ? (diametroReal / diametroNominal) : 0;
    
    // Correção de projeção TAP
    let correcaoProjecaoTap = projecaoTap;
    
    // Área efetiva = PI * (Diâmetro Real / 2000)² (convertendo mm para m)
    let raioMetros = diametroReal / 2000;
    let areaEfetiva = PI * raioMetros * raioMetros;
    
    // KPC = Fator de velocidade * Área efetiva * correção
    let kpc = fatorVelocidade > 0 ? (fatorVelocidade * areaEfetiva * correcaoDiametro) : 0;
    
    // Vazão em L/s (placeholder)
    let vazao = 0;
    
    // Preencher campos de resultado
    document.getElementById('vlFatorVelocidade').value = fatorVelocidade.toFixed(10);
    document.getElementById('vlCorrecaoDiametro').value = correcaoDiametro.toFixed(6);
    document.getElementById('vlCorrecaoProjecaoTap').value = correcaoProjecaoTap.toFixed(2);
    document.getElementById('vlAreaEfetiva').value = areaEfetiva.toFixed(6);
    document.getElementById('vlKPC').value = kpc.toFixed(10);
    document.getElementById('vlVazao').value = vazao.toFixed(2);
}

// ============================================
// Submissão do Formulário
// ============================================
document.getElementById('formCalculoKPC').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Verificar se há valores calculados
    const kpc = document.getElementById('vlKPC').value;
    if (!kpc || parseFloat(kpc) === 0) {
        if (!confirm('O KPC não foi calculado ou é zero. Deseja continuar assim mesmo?')) {
            return;
        }
    }
    
    // Validar técnico e usuário responsável antes de coletar dados
    const tecnico = document.getElementById('selectTecnico').value;
    const usuario = document.getElementById('selectUsuario').value;
    
    if (!tecnico) {
        showToast('Selecione o Técnico Responsável', 'alerta');
        return;
    }
    if (!usuario) {
        showToast('Selecione o Usuário Responsável', 'alerta');
        return;
    }
    
    // Coletar dados do formulário
    const formData = new FormData(this);
    
    // Converter para objeto
    const data = {};
    formData.forEach((value, key) => {
        if (!key.startsWith('leitura[') && !key.startsWith('media[')) {
            data[key] = value;
        }
    });
    
    // Coletar leituras (estrutura: leitura[linha][ponto])
    const POSICOES_PONTOS = calcularPosicoesPontos();
    data.leituras = [];
    for (let leitura = 1; leitura <= 20; leitura++) {
        for (let ponto = 1; ponto <= 11; ponto++) {
            const input = document.querySelector(`input[data-leitura="${leitura}"][data-ponto="${ponto}"]`);
            if (input && input.value !== '' && !isNaN(parseFloat(input.value))) {
                data.leituras.push({
                    leitura: leitura,    // CD_ORDEM_LEITURA
                    ponto: ponto,        // CD_POSICAO_LEITURA
                    deflexao: parseFloat(input.value),
                    posicao: POSICOES_PONTOS[ponto]
                });
            }
        }
    }
    
    // Coletar médias (posição 21 na lógica do banco)
    for (let ponto = 1; ponto <= 11; ponto++) {
        const mediaInput = document.getElementById(`media_${ponto}`);
        if (mediaInput && mediaInput.value !== '' && !isNaN(parseFloat(mediaInput.value))) {
            data.leituras.push({
                leitura: 21,         // Linha especial para média
                ponto: ponto,
                deflexao: parseFloat(mediaInput.value),
                posicao: POSICOES_PONTOS[ponto]
            });
        }
    }
    
    data.cd_tecnico_responsavel = tecnico;
    data.cd_usuario_responsavel = usuario;
    
    // Enviar para o servidor
    fetch('bd/calculoKPC/salvarCalculoKPC.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(response => {
        if (response.success) {
            showToast('Cálculo de KPC salvo com sucesso!', 'sucesso');
            setTimeout(() => {
                window.location.href = 'calculoKPC.php';
            }, 1500);
        } else {
            showToast('Erro: ' + response.message, 'erro');
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        showToast('Erro ao salvar o cálculo. Verifique o console.', 'erro');
    });
});
</script>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
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
        
        // Animação de entrada
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Auto-remover
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
}
</script>

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

<?php include_once 'includes/footer.inc.php'; ?>
