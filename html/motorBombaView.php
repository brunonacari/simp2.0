<?php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

exigePermissaoTela('Cadastro de Conjunto Motor-Bomba', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Cadastro de Conjunto Motor-Bomba');

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['msg'] = 'ID inválido.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: motorBomba.php');
    exit;
}

$sql = "SELECT CMB.*, L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO, L.DS_NOME AS DS_LOCALIDADE, L.CD_UNIDADE,
               U.DS_NOME AS DS_UNIDADE, U.CD_CODIGO AS CD_UNIDADE_CODIGO,
               UA.DS_NOME AS DS_USUARIO_ATUALIZACAO, UR.DS_NOME AS DS_USUARIO_RESPONSAVEL_NOME, UR.DS_MATRICULA AS DS_MATRICULA_RESPONSAVEL
        FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
        INNER JOIN SIMP.dbo.LOCALIDADE L ON CMB.CD_LOCALIDADE = L.CD_CHAVE
        INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
        LEFT JOIN SIMP.dbo.USUARIO UA ON CMB.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
        LEFT JOIN SIMP.dbo.USUARIO UR ON CMB.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
        WHERE CMB.CD_CHAVE = :id";
$stmt = $pdoSIMP->prepare($sql);
$stmt->execute([':id' => $id]);
$mb = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mb) {
    $_SESSION['msg'] = 'Registro não encontrado.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: motorBomba.php');
    exit;
}

$tiposEixo = ['H' => 'Horizontal', 'V' => 'Vertical'];
$eixoClass = $mb['TP_EIXO'] == 'H' ? 'horizontal' : 'vertical';
?>

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
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
        overflow-x: hidden;
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
        overflow: hidden;
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
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .page-header-subtitle .codigo {
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 8px;
        border-radius: 4px;
        font-family: 'SF Mono', Monaco, monospace;
        font-weight: 600;
        font-size: 11px;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-header {
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

    .btn-header:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .btn-header.primary {
        background: rgba(255, 255, 255, 0.95);
        color: #1e3a5f;
        border-color: transparent;
    }

    .btn-header.primary:hover {
        background: white;
    }

    .btn-header ion-icon {
        font-size: 14px;
    }

    /* ============================================
       Status Badge
       ============================================ */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .status-badge.ativo {
        background: #dcfce7;
        color: #15803d;
    }

    .status-badge ion-icon {
        font-size: 12px;
    }

    /* ============================================
       View Card
       ============================================ */
    .view-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 16px;
    }

    .view-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .view-card-header ion-icon {
        font-size: 16px;
        color: #3b82f6;
    }

    .view-card-header h2 {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .view-card-body {
        padding: 20px;
    }

    /* ============================================
       View Grid
       ============================================ */
    .view-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 16px;
    }

    .view-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .view-item.full-width {
        grid-column: 1 / -1;
    }

    .view-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .view-label ion-icon {
        font-size: 12px;
        color: #94a3b8;
    }

    .view-value {
        font-size: 12px;
        color: #1e293b;
        font-weight: 500;
        padding: 8px 12px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        min-height: 36px;
        display: flex;
        align-items: center;
    }

    .view-value.empty {
        color: #94a3b8;
        font-style: italic;
        font-weight: 400;
    }

    .view-value.codigo {
        font-family: 'SF Mono', Monaco, monospace;
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    /* ============================================
       Type Badge
       ============================================ */
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }

    .type-badge.horizontal {
        background: #dcfce7;
        color: #15803d;
    }

    .type-badge.vertical {
        background: #fef3c7;
        color: #b45309;
    }

    /* ============================================
       Responsive
       ============================================ */
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
            gap: 16px;
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

        .page-header-subtitle {
            justify-content: center;
            flex-wrap: wrap;
        }

        .header-actions {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 8px;
        }

        .btn-header {
            width: 100%;
            justify-content: center;
            box-sizing: border-box;
            padding: 12px 14px;
        }

        .view-card-body {
            padding: 16px;
        }

        .view-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="eye-outline"></ion-icon>
                </div>
                <div>
                    <h1><?= htmlspecialchars($mb['DS_NOME']) ?></h1>
                    <p class="page-header-subtitle">
                        <span class="codigo"><?= htmlspecialchars($mb['DS_CODIGO']) ?></span>
                        <span class="status-badge ativo">
                            <ion-icon name="checkmark-circle"></ion-icon>
                            Ativo
                        </span>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="motorBomba.php" class="btn-header">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
                <?php if ($podeEditar): ?>
                <a href="motorBombaForm.php?id=<?= $id ?>" class="btn-header primary">
                    <ion-icon name="create-outline"></ion-icon>
                    Editar
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Identificação -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="information-circle-outline"></ion-icon>
            <h2>Identificação</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label"><ion-icon name="business-outline"></ion-icon> Unidade</span>
                    <div class="view-value"><?= htmlspecialchars($mb['CD_UNIDADE_CODIGO'] . ' - ' . $mb['DS_UNIDADE']) ?></div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="location-outline"></ion-icon> Localidade</span>
                    <div class="view-value"><?= htmlspecialchars($mb['CD_LOCALIDADE_CODIGO'] . ' - ' . $mb['DS_LOCALIDADE']) ?></div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="pricetag-outline"></ion-icon> Código</span>
                    <div class="view-value codigo"><?= htmlspecialchars($mb['DS_CODIGO']) ?></div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="git-compare-outline"></ion-icon> Tipo de Eixo</span>
                    <div class="view-value">
                        <span class="type-badge <?= $eixoClass ?>"><?= $tiposEixo[$mb['TP_EIXO']] ?? '-' ?></span>
                    </div>
                </div>
                <div class="view-item full-width">
                    <span class="view-label"><ion-icon name="text-outline"></ion-icon> Nome</span>
                    <div class="view-value"><?= htmlspecialchars($mb['DS_NOME']) ?></div>
                </div>
                <div class="view-item full-width">
                    <span class="view-label"><ion-icon name="navigate-outline"></ion-icon> Localização</span>
                    <div class="view-value"><?= htmlspecialchars($mb['DS_LOCALIZACAO']) ?></div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="person-outline"></ion-icon> Responsável</span>
                    <div class="view-value"><?= htmlspecialchars($mb['DS_MATRICULA_RESPONSAVEL'] . ' - ' . $mb['DS_USUARIO_RESPONSAVEL_NOME']) ?></div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="chatbox-outline"></ion-icon> Observação</span>
                    <div class="view-value <?= empty($mb['DS_OBSERVACAO']) ? 'empty' : '' ?>">
                        <?= !empty($mb['DS_OBSERVACAO']) ? htmlspecialchars($mb['DS_OBSERVACAO']) : 'Não informado' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dados da Bomba -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="water-outline"></ion-icon>
            <h2>Dados da Bomba</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label"><ion-icon name="construct-outline"></ion-icon> Fabricante</span>
                    <div class="view-value <?= empty($mb['DS_FABRICANTE_BOMBA']) ? 'empty' : '' ?>">
                        <?= !empty($mb['DS_FABRICANTE_BOMBA']) ? htmlspecialchars($mb['DS_FABRICANTE_BOMBA']) : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="options-outline"></ion-icon> Tipo</span>
                    <div class="view-value <?= empty($mb['DS_TIPO_BOMBA']) ? 'empty' : '' ?>">
                        <?= !empty($mb['DS_TIPO_BOMBA']) ? htmlspecialchars($mb['DS_TIPO_BOMBA']) : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="barcode-outline"></ion-icon> Série</span>
                    <div class="view-value <?= empty($mb['DS_SERIE_BOMBA']) ? 'empty' : '' ?>">
                        <?= !empty($mb['DS_SERIE_BOMBA']) ? htmlspecialchars($mb['DS_SERIE_BOMBA']) : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="disc-outline"></ion-icon> Diâmetro Rotor</span>
                    <div class="view-value"><?= number_format($mb['VL_DIAMETRO_ROTOR_BOMBA'], 2, ',', '.') ?> mm</div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="speedometer-outline"></ion-icon> Vazão</span>
                    <div class="view-value <?= empty($mb['VL_VAZAO_BOMBA']) ? 'empty' : '' ?>">
                        <?= !empty($mb['VL_VAZAO_BOMBA']) ? number_format($mb['VL_VAZAO_BOMBA'], 2, ',', '.') . ' L/s' : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="trending-up-outline"></ion-icon> Altura Manométrica</span>
                    <div class="view-value"><?= number_format($mb['VL_ALTURA_MANOMETRICA_BOMBA'], 2, ',', '.') ?> mca</div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="sync-outline"></ion-icon> Rotação</span>
                    <div class="view-value <?= empty($mb['VL_ROTACAO_BOMBA']) ? 'empty' : '' ?>">
                        <?= !empty($mb['VL_ROTACAO_BOMBA']) ? number_format($mb['VL_ROTACAO_BOMBA'], 0, ',', '.') . ' rpm' : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="enter-outline"></ion-icon> Área Sucção</span>
                    <div class="view-value <?= empty($mb['VL_AREA_SUCCAO_BOMBA']) ? 'empty' : '' ?>">
                        <?= !empty($mb['VL_AREA_SUCCAO_BOMBA']) ? number_format($mb['VL_AREA_SUCCAO_BOMBA'], 2, ',', '.') . ' mm²' : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="exit-outline"></ion-icon> Área Recalque</span>
                    <div class="view-value <?= empty($mb['VL_AREA_RECALQUE_BOMBA']) ? 'empty' : '' ?>">
                        <?= !empty($mb['VL_AREA_RECALQUE_BOMBA']) ? number_format($mb['VL_AREA_RECALQUE_BOMBA'], 2, ',', '.') . ' mm²' : 'Não informado' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dados do Motor -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="flash-outline"></ion-icon>
            <h2>Dados do Motor</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label"><ion-icon name="construct-outline"></ion-icon> Fabricante</span>
                    <div class="view-value <?= empty($mb['DS_FABRICANTE_MOTOR']) ? 'empty' : '' ?>">
                        <?= !empty($mb['DS_FABRICANTE_MOTOR']) ? htmlspecialchars($mb['DS_FABRICANTE_MOTOR']) : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="options-outline"></ion-icon> Tipo</span>
                    <div class="view-value <?= empty($mb['DS_TIPO_MOTOR']) ? 'empty' : '' ?>">
                        <?= !empty($mb['DS_TIPO_MOTOR']) ? htmlspecialchars($mb['DS_TIPO_MOTOR']) : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="barcode-outline"></ion-icon> Série</span>
                    <div class="view-value <?= empty($mb['DS_SERIE_MOTOR']) ? 'empty' : '' ?>">
                        <?= !empty($mb['DS_SERIE_MOTOR']) ? htmlspecialchars($mb['DS_SERIE_MOTOR']) : 'Não informado' ?>
                    </div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="pulse-outline"></ion-icon> Tensão</span>
                    <div class="view-value"><?= number_format($mb['VL_TENSAO_MOTOR'], 0, ',', '.') ?> V</div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="flash-outline"></ion-icon> Corrente Elétrica</span>
                    <div class="view-value"><?= number_format($mb['VL_CORRENTE_ELETRICA_MOTOR'], 2, ',', '.') ?> A</div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="fitness-outline"></ion-icon> Potência</span>
                    <div class="view-value"><?= number_format($mb['VL_POTENCIA_MOTOR'], 2, ',', '.') ?> CV</div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="sync-outline"></ion-icon> Rotação</span>
                    <div class="view-value <?= empty($mb['VL_ROTACAO_MOTOR']) ? 'empty' : '' ?>">
                        <?= !empty($mb['VL_ROTACAO_MOTOR']) ? number_format($mb['VL_ROTACAO_MOTOR'], 0, ',', '.') . ' rpm' : 'Não informado' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informações do Sistema -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="time-outline"></ion-icon>
            <h2>Informações do Sistema</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label"><ion-icon name="calendar-outline"></ion-icon> Última Atualização</span>
                    <div class="view-value"><?= date('d/m/Y H:i', strtotime($mb['DT_ULTIMA_ATUALIZACAO'])) ?></div>
                </div>
                <div class="view-item">
                    <span class="view-label"><ion-icon name="person-outline"></ion-icon> Usuário Atualização</span>
                    <div class="view-value <?= empty($mb['DS_USUARIO_ATUALIZACAO']) ? 'empty' : '' ?>">
                        <?= !empty($mb['DS_USUARIO_ATUALIZACAO']) ? htmlspecialchars($mb['DS_USUARIO_ATUALIZACAO']) : 'Não informado' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.inc.php'; ?>