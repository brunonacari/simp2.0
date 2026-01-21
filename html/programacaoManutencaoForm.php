<?php
//programacaoManutencaoForm.php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para editar Programação de Manutenção
exigePermissaoTela('Programação de Manutenção', ACESSO_ESCRITA);

// Verifica se é edição
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdicao = $id > 0;
$programacao = null;

// Tipos de Programação
$tiposProgramacao = [
    1 => 'Calibração',
    2 => 'Manutenção'
];

// Situações
$situacoes = [
    1 => 'Prevista',
    2 => 'Executada',
    4 => 'Cancelada'
];

// Se for edição, busca os dados
if ($isEdicao) {
    $sql = "SELECT 
                P.*,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                PM.CD_LOCALIDADE,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                U.CD_CODIGO AS CD_UNIDADE_CODIGO,
                UR.DS_NOME AS DS_RESPONSAVEL,
                UR.DS_MATRICULA AS DS_MATRICULA_RESPONSAVEL,
                UA.DS_NOME AS DS_USUARIO_ATUALIZACAO
            FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO P
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            LEFT JOIN SIMP.dbo.USUARIO UR ON P.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
            LEFT JOIN SIMP.dbo.USUARIO UA ON P.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
            WHERE P.CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $programacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$programacao) {
        $_SESSION['msg'] = 'Programação não encontrada.';
        $_SESSION['msg_tipo'] = 'erro';
        header('Location: programacaoManutencao.php');
        exit;
    }
}

// Buscar Unidades
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar Usuários para Responsável
$sqlUsuariosBase = "SELECT CD_USUARIO, DS_NOME, DS_MATRICULA FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2";
if ($isEdicao && !empty($programacao['CD_USUARIO_RESPONSAVEL'])) {
    $sqlUsuariosBase .= " OR CD_USUARIO = " . (int)$programacao['CD_USUARIO_RESPONSAVEL'];
}
$sqlUsuariosBase .= " ORDER BY DS_NOME";
$sqlUsuarios = $pdoSIMP->query($sqlUsuariosBase);
$usuarios = $sqlUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Código formatado: CD_CODIGO-CD_ANO/ID_TIPO_PROGRAMACAO
$codigoFormatado = '';
if ($isEdicao) {
    $codigoFormatado = str_pad($programacao['CD_CODIGO'], 3, '0', STR_PAD_LEFT) . '-' . $programacao['CD_ANO'] . '/' . $programacao['ID_TIPO_PROGRAMACAO'];
}

// Mapeamento de letras por tipo de medidor
$letrasTipoMedidor = [
    1 => 'M', // Macromedidor
    2 => 'E', // Estação Pitométrica
    4 => 'P', // Medidor Pressão
    6 => 'R', // Nível Reservatório
    8 => 'H'  // Hidrômetro
];
?>

<!-- Choices.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<style>
    /* ============================================
       Choices.js Customização
       ============================================ */
    .choices {
        margin-bottom: 0;
        width: 100%;
    }

    .choices__inner {
        min-height: 44px;
        padding: 6px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        width: 100%;
        box-sizing: border-box;
    }

    .choices__inner:focus,
    .is-focused .choices__inner,
    .is-open .choices__inner {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .choices__list--single {
        padding: 4px 16px 4px 4px;
    }

    .choices__list--single .choices__item {
        color: #1e293b;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .choices__list--single .choices__item--selectable {
        color: #1e293b !important;
    }

    .choices__placeholder {
        color: #94a3b8;
        opacity: 1;
    }

    .choices__list--dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        margin-top: 4px;
        z-index: 99999 !important;
        position: absolute !important;
    }

    .choices__list--dropdown .choices__item {
        padding: 12px 16px;
        font-size: 14px;
        color: #334155;
        background-color: #ffffff;
    }

    .choices__list--dropdown .choices__item--selectable:hover,
    .choices__list--dropdown .choices__item--selectable.is-highlighted,
    .choices__list--dropdown .choices__item.is-highlighted {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
    }

    .choices__list--dropdown .choices__item--selectable.is-selected {
        background-color: #eff6ff;
        color: #3b82f6;
    }

    .choices[data-type*="select-one"] .choices__input {
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        margin: 8px;
        width: calc(100% - 16px) !important;
        max-width: calc(100% - 16px) !important;
        box-sizing: border-box;
    }

    .choices[data-type*="select-one"] .choices__input:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .choices[data-type*="select-one"]::after {
        border-color: #64748b transparent transparent transparent;
        right: 14px;
    }

    .choices.is-disabled .choices__inner {
        background-color: #f1f5f9;
        cursor: not-allowed;
    }

    .choices.is-disabled .choices__list--single .choices__item {
        color: #94a3b8;
    }

    .choices {
        position: relative;
        z-index: 1;
    }

    .choices.is-open {
        z-index: 99999;
    }

    /* ============================================
       Page Layout
       ============================================ */
    .page-container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

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
        color: white;
    }

    .page-header-subtitle {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
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
       Form Card
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
        overflow: visible;
    }

    /* ============================================
       Form Grid
       ============================================ */
    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -8px 16px -8px;
        position: relative;
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
    .col-6 { width: 50%; }
    .col-4 { width: 33.333333%; }
    .col-3 { width: 25%; }

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
        padding: 12px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 14px;
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

    textarea.form-control {
        min-height: 80px;
        resize: vertical;
    }

    /* ============================================
       Radio Group
       ============================================ */
    .radio-group {
        display: flex;
        gap: 16px;
        padding: 10px 0;
    }

    .radio-item {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .radio-item input[type="radio"] {
        width: 18px;
        height: 18px;
        accent-color: #3b82f6;
    }

    .radio-label {
        font-size: 14px;
        color: #334155;
    }

    /* ============================================
       Info Badge
       ============================================ */
    .info-badge {
        display: inline-flex;
        align-items: center;
        padding: 10px 14px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        color: #64748b;
    }

    .info-badge.codigo {
        background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
        color: #1e40af;
        font-weight: 600;
        font-family: 'Courier New', monospace;
    }

    /* ============================================
       Form Actions
       ============================================ */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn ion-icon {
        font-size: 16px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
    }

    .btn-danger {
        background: #fee2e2;
        color: #b91c1c;
    }

    .btn-danger:hover {
        background: #fecaca;
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    .form-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, #e2e8f0, transparent);
        margin: 16px 0;
    }

    /* ============================================
       Responsivo
       ============================================ */
    @media (max-width: 992px) {
        .col-3 { width: 50%; }
        .col-4 { width: 50%; }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 12px;
        }

        .page-header {
            padding: 16px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .page-header-info {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }

        .page-header-icon {
            margin: 0 auto;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .btn-voltar {
            width: 100%;
            justify-content: center;
            box-sizing: border-box;
            padding: 12px 14px;
        }

        .form-card-body {
            padding: 16px;
        }

        .col-3, .col-4, .col-6 {
            width: 100%;
        }

        .form-row {
            margin-bottom: 0;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-actions {
            flex-direction: column;
            padding: 16px;
        }

        .form-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="<?= $isEdicao ? 'create-outline' : 'add-outline' ?>"></ion-icon>
                </div>
                <div>
                    <h1><?= $isEdicao ? 'Editar Programação' : 'Nova Programação' ?></h1>
                    <p class="page-header-subtitle">
                        <?= $isEdicao ? 'Atualize as informações da programação' : 'Cadastre uma nova programação de calibração ou manutenção' ?>
                    </p>
                </div>
            </div>
            <a href="programacaoManutencao.php" class="btn-voltar">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Voltar
            </a>
        </div>
    </div>

    <form id="formProgramacao" method="POST" action="bd/programacaoManutencao/salvarProgramacao.php">
        <input type="hidden" name="cd_chave" value="<?= $id ?>">

        <!-- Ponto de Medição -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="location-outline"></ion-icon>
                <h2>Ponto de Medição</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="business-outline"></ion-icon>
                            Unidade <span class="required">*</span>
                        </label>
                        <select id="selectUnidade" name="cd_unidade" required>
                            <option value="">Selecione a Unidade</option>
                            <?php foreach ($unidades as $unidade): ?>
                                <option value="<?= $unidade['CD_UNIDADE'] ?>" 
                                    <?= ($isEdicao && $programacao['CD_UNIDADE'] == $unidade['CD_UNIDADE']) ? 'selected' : '' ?>>
                                    <?= $unidade['CD_CODIGO'] . ' - ' . $unidade['DS_NOME'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="location-outline"></ion-icon>
                            Localidade <span class="required">*</span>
                        </label>
                        <select id="selectLocalidade" name="cd_localidade" required>
                            <option value="">Selecione a Unidade primeiro</option>
                        </select>
                    </div>

                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="speedometer-outline"></ion-icon>
                            Ponto de Medição <span class="required">*</span>
                        </label>
                        <select id="selectPontoMedicao" name="cd_ponto_medicao" required>
                            <option value="">Selecione a Localidade primeiro</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dados da Programação -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="calendar-outline"></ion-icon>
                <h2>Dados da Programação</h2>
                <?php if ($isEdicao): ?>
                    <span class="info-badge codigo" style="margin-left: auto;"><?= $codigoFormatado ?></span>
                <?php endif; ?>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="construct-outline"></ion-icon>
                            Tipo de Programação <span class="required">*</span>
                        </label>
                        <div class="radio-group">
                            <?php foreach ($tiposProgramacao as $id => $nome): ?>
                            <label class="radio-item">
                                <input type="radio" name="id_tipo_programacao" value="<?= $id ?>" 
                                    <?= ($isEdicao && $programacao['ID_TIPO_PROGRAMACAO'] == $id) ? 'checked' : (!$isEdicao && $id == 1 ? 'checked' : '') ?> required>
                                <span class="radio-label"><?= $nome ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data Programada <span class="required">*</span>
                        </label>
                        <input type="date" name="dt_programacao" class="form-control" required
                               value="<?= $isEdicao && $programacao['DT_PROGRAMACAO'] ? date('Y-m-d', strtotime($programacao['DT_PROGRAMACAO'])) : '' ?>">
                    </div>

                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="flag-outline"></ion-icon>
                            Situação <span class="required">*</span>
                        </label>
                        <select name="id_situacao" class="form-control" required>
                            <?php foreach ($situacoes as $id => $nome): ?>
                                <option value="<?= $id ?>" 
                                    <?= ($isEdicao && $programacao['ID_SITUACAO'] == $id) ? 'selected' : (!$isEdicao && $id == 1 ? 'selected' : '') ?>>
                                    <?= $nome ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="person-outline"></ion-icon>
                            Responsável
                        </label>
                        <select id="selectResponsavel" name="cd_usuario_responsavel">
                            <option value="">Selecione o Responsável</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?= $usuario['CD_USUARIO'] ?>"
                                    <?= ($isEdicao && $programacao['CD_USUARIO_RESPONSAVEL'] == $usuario['CD_USUARIO']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usuario['DS_MATRICULA'] . ' - ' . $usuario['DS_NOME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($isEdicao): ?>
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="barcode-outline"></ion-icon>
                            Código
                        </label>
                        <div class="info-badge codigo"><?= $codigoFormatado ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Solicitação -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="document-text-outline"></ion-icon>
                <h2>Dados da Solicitação</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="person-outline"></ion-icon>
                            Solicitante <span class="required">*</span>
                        </label>
                        <input type="text" name="ds_solicitante" class="form-control" 
                               value="<?= $isEdicao ? htmlspecialchars($programacao['DS_SOLICITANTE']) : '' ?>"
                               placeholder="Nome do solicitante" maxlength="50" required>
                    </div>

                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data da Solicitação <span class="required">*</span>
                        </label>
                        <input type="datetime-local" name="dt_solicitacao" class="form-control" required
                               value="<?= $isEdicao && $programacao['DT_SOLICITACAO'] ? date('Y-m-d\TH:i', strtotime($programacao['DT_SOLICITACAO'])) : date('Y-m-d\TH:i') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="chatbox-outline"></ion-icon>
                            Descrição da Solicitação
                        </label>
                        <textarea name="ds_solicitacao" class="form-control" rows="4"
                                  placeholder="Descreva o motivo e detalhes da solicitação" maxlength="200"><?= $isEdicao ? htmlspecialchars($programacao['DS_SOLICITACAO']) : '' ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="programacaoManutencao.php" class="btn btn-secondary">
                    <ion-icon name="close-outline"></ion-icon>
                    Cancelar
                </a>
                <?php if ($isEdicao): ?>
                <button type="button" class="btn btn-danger" onclick="confirmarExclusao()">
                    <ion-icon name="trash-outline"></ion-icon>
                    Excluir
                </button>
                <?php endif; ?>
                <button type="submit" id="btnSalvar" class="btn btn-primary">
                    <ion-icon name="save-outline"></ion-icon>
                    Salvar
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    let choicesUnidade, choicesLocalidade, choicesPontoMedicao, choicesResponsavel;
    const isEdicao = <?= $isEdicao ? 'true' : 'false' ?>;
    const valorSalvo = {
        cdUnidade: <?= $isEdicao ? (int)$programacao['CD_UNIDADE'] : 'null' ?>,
        cdLocalidade: <?= $isEdicao ? (int)$programacao['CD_LOCALIDADE'] : 'null' ?>,
        cdPontoMedicao: <?= $isEdicao ? (int)$programacao['CD_PONTO_MEDICAO'] : 'null' ?>
    };

    // Mapeamento de letras por tipo de medidor
    const letrasTipoMedidor = {
        1: 'M', // Macromedidor
        2: 'E', // Estação Pitométrica
        4: 'P', // Medidor Pressão
        6: 'R', // Nível Reservatório
        8: 'H'  // Hidrômetro
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Configuração base do Choices.js
        const configChoices = {
            searchEnabled: true,
            searchPlaceholderValue: 'Pesquisar...',
            itemSelectText: '',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Sem opções disponíveis',
            placeholder: true,
            placeholderValue: 'Selecione...',
            shouldSort: false
        };

        // Inicializa Choices.js para cada select
        choicesUnidade = new Choices('#selectUnidade', configChoices);
        choicesLocalidade = new Choices('#selectLocalidade', { ...configChoices, searchEnabled: true });
        choicesPontoMedicao = new Choices('#selectPontoMedicao', { ...configChoices, searchEnabled: true });
        choicesResponsavel = new Choices('#selectResponsavel', configChoices);

        // Desabilita Localidade e Ponto inicialmente (se não for edição)
        if (!isEdicao) {
            choicesLocalidade.disable();
            choicesPontoMedicao.disable();
        }

        // Evento de mudança na Unidade
        document.getElementById('selectUnidade').addEventListener('change', function() {
            const cdUnidade = this.value;
            carregarLocalidades(cdUnidade, null);
        });

        // Evento de mudança na Localidade
        document.getElementById('selectLocalidade').addEventListener('change', function() {
            const cdLocalidade = this.value;
            carregarPontosMedicao(cdLocalidade, null);
        });

        // Se for edição, carrega os dados em cascata
        if (isEdicao && valorSalvo.cdUnidade) {
            carregarLocalidades(valorSalvo.cdUnidade, valorSalvo.cdLocalidade);
        }

        // Submit do formulário
        document.getElementById('formProgramacao').addEventListener('submit', function(e) {
            e.preventDefault();
            salvarProgramacao();
        });
    });

    function carregarLocalidades(cdUnidade, cdLocalidadeSelecionada) {
        // Limpa e desabilita os campos dependentes
        choicesLocalidade.clearStore();
        choicesLocalidade.setChoices([{ value: '', label: 'Carregando...', selected: true }], 'value', 'label', true);
        
        choicesPontoMedicao.clearStore();
        choicesPontoMedicao.setChoices([{ value: '', label: 'Selecione a Localidade primeiro', selected: true }], 'value', 'label', true);
        choicesPontoMedicao.disable();

        if (!cdUnidade) {
            choicesLocalidade.setChoices([{ value: '', label: 'Selecione a Unidade primeiro', selected: true }], 'value', 'label', true);
            choicesLocalidade.disable();
            return;
        }

        fetch(`bd/pontoMedicao/getLocalidades.php?cd_unidade=${cdUnidade}`)
            .then(response => response.json())
            .then(data => {
                const choices = [{ value: '', label: 'Selecione a Localidade', selected: !cdLocalidadeSelecionada }];
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(item => {
                        choices.push({
                            value: item.CD_CHAVE,
                            label: `${item.CD_LOCALIDADE} - ${item.DS_NOME}`,
                            selected: cdLocalidadeSelecionada && item.CD_CHAVE == cdLocalidadeSelecionada
                        });
                    });
                }
                
                choicesLocalidade.clearStore();
                choicesLocalidade.setChoices(choices, 'value', 'label', true);
                choicesLocalidade.enable();

                // Se tem localidade selecionada, carrega pontos de medição
                if (cdLocalidadeSelecionada) {
                    carregarPontosMedicao(cdLocalidadeSelecionada, valorSalvo.cdPontoMedicao);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar localidades:', error);
                choicesLocalidade.setChoices([{ value: '', label: 'Erro ao carregar', selected: true }], 'value', 'label', true);
            });
    }

    function carregarPontosMedicao(cdLocalidade, cdPontoSelecionado) {
        choicesPontoMedicao.clearStore();
        choicesPontoMedicao.setChoices([{ value: '', label: 'Carregando...', selected: true }], 'value', 'label', true);

        if (!cdLocalidade) {
            choicesPontoMedicao.setChoices([{ value: '', label: 'Selecione a Localidade primeiro', selected: true }], 'value', 'label', true);
            choicesPontoMedicao.disable();
            return;
        }

        fetch(`bd/pontoMedicao/getPontosMedicao.php?cd_localidade=${cdLocalidade}`)
            .then(response => response.json())
            .then(data => {
                const choices = [{ value: '', label: 'Selecione o Ponto', selected: !cdPontoSelecionado }];
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(item => {
                        const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                        const codigoFormatado = `${item.CD_LOCALIDADE}-${String(item.CD_PONTO_MEDICAO).padStart(6, '0')}-${letraTipo}`;
                        choices.push({
                            value: item.CD_PONTO_MEDICAO,
                            label: `${codigoFormatado} - ${item.DS_NOME}`,
                            selected: cdPontoSelecionado && item.CD_PONTO_MEDICAO == cdPontoSelecionado
                        });
                    });
                }
                
                choicesPontoMedicao.clearStore();
                choicesPontoMedicao.setChoices(choices, 'value', 'label', true);
                choicesPontoMedicao.enable();
            })
            .catch(error => {
                console.error('Erro ao carregar pontos:', error);
                choicesPontoMedicao.setChoices([{ value: '', label: 'Erro ao carregar', selected: true }], 'value', 'label', true);
            });
    }

    function salvarProgramacao() {
        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Salvando...';

        const formData = new FormData(document.getElementById('formProgramacao'));
        
        // Forçar valores dos selects Choices.js (garantir que estão no FormData)
        const selectUnidade = document.getElementById('selectUnidade');
        const selectLocalidade = document.getElementById('selectLocalidade');
        const selectPontoMedicao = document.getElementById('selectPontoMedicao');
        const selectResponsavel = document.getElementById('selectResponsavel');
        
        // Sobrescrever com valores atuais dos selects
        if (selectUnidade) formData.set('cd_unidade', selectUnidade.value);
        if (selectLocalidade) formData.set('cd_localidade', selectLocalidade.value);
        if (selectPontoMedicao) formData.set('cd_ponto_medicao', selectPontoMedicao.value);
        if (selectResponsavel) formData.set('cd_usuario_responsavel', selectResponsavel.value);
        
        // DEBUG: Mostrar dados sendo enviados
        console.log('========================================');
        console.log('=== DEBUG: Dados do formulário ===');
        console.log('========================================');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
        console.log('========================================');

        fetch('bd/programacaoManutencao/salvarProgramacao.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    showToast(data.message, 'sucesso');
                    setTimeout(function() {
                        window.location.href = 'programacaoManutencao.php';
                    }, 1500);
                } else {
                    showToast(data.message || 'Erro ao salvar', 'erro');
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar';
                }
            } catch (e) {
                console.error('Erro ao parsear JSON:', e);
                showToast('Erro ao processar resposta. Verifique o console (F12).', 'erro');
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar';
            }
        })
        .catch(error => {
            console.error('Erro fetch:', error);
            showToast('Erro ao comunicar com o servidor', 'erro');
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar';
        });
    }

    function confirmarExclusao() {
        if (confirm('Tem certeza que deseja excluir esta programação?')) {
            fetch('bd/programacaoManutencao/excluirProgramacao.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'cd_chave=<?= $id ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'sucesso');
                    setTimeout(function() {
                        window.location.href = 'programacaoManutencao.php';
                    }, 1500);
                } else {
                    showToast(data.message || 'Erro ao excluir', 'erro');
                }
            });
        }
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>