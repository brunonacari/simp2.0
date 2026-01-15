<?php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

exigePermissaoTela('Cadastro de Conjunto Motor-Bomba', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Cadastro de Conjunto Motor-Bomba');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['msg'] = 'ID invalido.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: motorBomba.php');
    exit;
}

$sql = "SELECT 
            CMB.*,
            L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
            L.DS_NOME AS DS_LOCALIDADE,
            L.CD_UNIDADE,
            U.DS_NOME AS DS_UNIDADE,
            U.CD_CODIGO AS CD_UNIDADE_CODIGO,
            UA.DS_NOME AS DS_USUARIO_ATUALIZACAO,
            UR.DS_NOME AS DS_USUARIO_RESPONSAVEL_NOME,
            UR.DS_MATRICULA AS DS_MATRICULA_RESPONSAVEL
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
    $_SESSION['msg'] = 'Registro nao encontrado.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: motorBomba.php');
    exit;
}

$tiposEixo = ['H' => 'Horizontal', 'V' => 'Vertical'];
?>

<link rel="stylesheet" href="style/css/motorBomba.css">

<style>
.view-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
}

.view-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
    color: #1e3a5f;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
}

.view-card-title ion-icon {
    font-size: 20px;
    color: #3b82f6;
}

.view-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.view-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.view-item.span-2 { grid-column: span 2; }
.view-item.span-4 { grid-column: span 4; }

.view-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.view-value {
    font-size: 14px;
    color: #1e3a5f;
    font-weight: 500;
}

.view-value.empty {
    color: #94a3b8;
    font-style: italic;
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
}

.btn-header.primary {
    background: rgba(59, 130, 246, 0.8);
    border-color: rgba(59, 130, 246, 0.5);
}

.btn-header.primary:hover {
    background: rgba(59, 130, 246, 1);
}

@media (max-width: 1200px) {
    .view-grid { grid-template-columns: repeat(2, 1fr); }
    .view-item.span-4 { grid-column: span 2; }
}

@media (max-width: 768px) {
    .view-grid { grid-template-columns: 1fr; }
    .view-item.span-2, .view-item.span-4 { grid-column: span 1; }
    .header-actions { flex-direction: column; width: 100%; }
    .btn-header { justify-content: center; }
}
</style>

<div class="page-container">
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="cog-outline"></ion-icon>
                </div>
                <div>
                    <h1><?= htmlspecialchars($mb['DS_NOME']) ?></h1>
                    <p class="page-header-subtitle">
                        <span style="background:rgba(255,255,255,0.2);padding:3px 8px;border-radius:4px;font-family:monospace;">
                            <?= htmlspecialchars($mb['DS_CODIGO']) ?>
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

    <!-- Dados Gerais -->
    <div class="view-card">
        <div class="view-card-title">
            <ion-icon name="information-circle-outline"></ion-icon>
            Dados Gerais
        </div>
        <div class="view-grid">
            <div class="view-item">
                <span class="view-label">Unidade</span>
                <span class="view-value"><?= htmlspecialchars($mb['CD_UNIDADE_CODIGO'] . ' - ' . $mb['DS_UNIDADE']) ?></span>
            </div>
            <div class="view-item">
                <span class="view-label">Localidade</span>
                <span class="view-value"><?= htmlspecialchars($mb['CD_LOCALIDADE_CODIGO'] . ' - ' . $mb['DS_LOCALIDADE']) ?></span>
            </div>
            <div class="view-item">
                <span class="view-label">Codigo</span>
                <span class="view-value"><?= htmlspecialchars($mb['DS_CODIGO']) ?></span>
            </div>
            <div class="view-item">
                <span class="view-label">Nome</span>
                <span class="view-value"><?= htmlspecialchars($mb['DS_NOME']) ?></span>
            </div>
            <div class="view-item span-2">
                <span class="view-label">Localizacao</span>
                <span class="view-value"><?= htmlspecialchars($mb['DS_LOCALIZACAO']) ?></span>
            </div>
            <div class="view-item">
                <span class="view-label">Responsavel</span>
                <span class="view-value"><?= htmlspecialchars($mb['DS_MATRICULA_RESPONSAVEL'] . ' - ' . $mb['DS_USUARIO_RESPONSAVEL_NOME']) ?></span>
            </div>
            <div class="view-item">
                <span class="view-label">Tipo de Eixo</span>
                <span class="view-value">
                    <span class="badge badge-eixo <?= strtolower($tiposEixo[$mb['TP_EIXO']] ?? '') ?>">
                        <?= $tiposEixo[$mb['TP_EIXO']] ?? '-' ?>
                    </span>
                </span>
            </div>
            <div class="view-item span-4">
                <span class="view-label">Observacao</span>
                <span class="view-value <?= empty($mb['DS_OBSERVACAO']) ? 'empty' : '' ?>">
                    <?= !empty($mb['DS_OBSERVACAO']) ? htmlspecialchars($mb['DS_OBSERVACAO']) : 'Nenhuma observacao' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Dados da Bomba -->
    <div class="view-card">
        <div class="view-card-title">
            <ion-icon name="water-outline"></ion-icon>
            Dados da Bomba
        </div>
        <div class="view-grid">
            <div class="view-item">
                <span class="view-label">Fabricante</span>
                <span class="view-value <?= empty($mb['DS_FABRICANTE_BOMBA']) ? 'empty' : '' ?>">
                    <?= !empty($mb['DS_FABRICANTE_BOMBA']) ? htmlspecialchars($mb['DS_FABRICANTE_BOMBA']) : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Tipo</span>
                <span class="view-value <?= empty($mb['DS_TIPO_BOMBA']) ? 'empty' : '' ?>">
                    <?= !empty($mb['DS_TIPO_BOMBA']) ? htmlspecialchars($mb['DS_TIPO_BOMBA']) : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Serie</span>
                <span class="view-value <?= empty($mb['DS_SERIE_BOMBA']) ? 'empty' : '' ?>">
                    <?= !empty($mb['DS_SERIE_BOMBA']) ? htmlspecialchars($mb['DS_SERIE_BOMBA']) : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Diametro Rotor</span>
                <span class="view-value"><?= number_format($mb['VL_DIAMETRO_ROTOR_BOMBA'], 2, ',', '.') ?> mm</span>
            </div>
            <div class="view-item">
                <span class="view-label">Vazao</span>
                <span class="view-value <?= empty($mb['VL_VAZAO_BOMBA']) ? 'empty' : '' ?>">
                    <?= !empty($mb['VL_VAZAO_BOMBA']) ? number_format($mb['VL_VAZAO_BOMBA'], 2, ',', '.') . ' m3/h' : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Altura Manometrica</span>
                <span class="view-value"><?= number_format($mb['VL_ALTURA_MANOMETRICA_BOMBA'], 2, ',', '.') ?> mca</span>
            </div>
            <div class="view-item">
                <span class="view-label">Rotacao</span>
                <span class="view-value <?= empty($mb['VL_ROTACAO_BOMBA']) ? 'empty' : '' ?>">
                    <?= !empty($mb['VL_ROTACAO_BOMBA']) ? number_format($mb['VL_ROTACAO_BOMBA'], 0, ',', '.') . ' rpm' : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Area Succao</span>
                <span class="view-value <?= empty($mb['VL_AREA_SUCCAO_BOMBA']) ? 'empty' : '' ?>">
                    <?= !empty($mb['VL_AREA_SUCCAO_BOMBA']) ? number_format($mb['VL_AREA_SUCCAO_BOMBA'], 2, ',', '.') . ' mm2' : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Area Recalque</span>
                <span class="view-value <?= empty($mb['VL_AREA_RECALQUE_BOMBA']) ? 'empty' : '' ?>">
                    <?= !empty($mb['VL_AREA_RECALQUE_BOMBA']) ? number_format($mb['VL_AREA_RECALQUE_BOMBA'], 2, ',', '.') . ' mm2' : '-' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Dados do Motor -->
    <div class="view-card">
        <div class="view-card-title">
            <ion-icon name="flash-outline"></ion-icon>
            Dados do Motor
        </div>
        <div class="view-grid">
            <div class="view-item">
                <span class="view-label">Fabricante</span>
                <span class="view-value <?= empty($mb['DS_FABRICANTE_MOTOR']) ? 'empty' : '' ?>">
                    <?= !empty($mb['DS_FABRICANTE_MOTOR']) ? htmlspecialchars($mb['DS_FABRICANTE_MOTOR']) : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Tipo</span>
                <span class="view-value <?= empty($mb['DS_TIPO_MOTOR']) ? 'empty' : '' ?>">
                    <?= !empty($mb['DS_TIPO_MOTOR']) ? htmlspecialchars($mb['DS_TIPO_MOTOR']) : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Serie</span>
                <span class="view-value <?= empty($mb['DS_SERIE_MOTOR']) ? 'empty' : '' ?>">
                    <?= !empty($mb['DS_SERIE_MOTOR']) ? htmlspecialchars($mb['DS_SERIE_MOTOR']) : '-' ?>
                </span>
            </div>
            <div class="view-item">
                <span class="view-label">Tensao</span>
                <span class="view-value"><?= number_format($mb['VL_TENSAO_MOTOR'], 0, ',', '.') ?> V</span>
            </div>
            <div class="view-item">
                <span class="view-label">Corrente Eletrica</span>
                <span class="view-value"><?= number_format($mb['VL_CORRENTE_ELETRICA_MOTOR'], 2, ',', '.') ?> A</span>
            </div>
            <div class="view-item">
                <span class="view-label">Potencia</span>
                <span class="view-value"><?= number_format($mb['VL_POTENCIA_MOTOR'], 2, ',', '.') ?> CV</span>
            </div>
            <div class="view-item">
                <span class="view-label">Rotacao</span>
                <span class="view-value <?= empty($mb['VL_ROTACAO_MOTOR']) ? 'empty' : '' ?>">
                    <?= !empty($mb['VL_ROTACAO_MOTOR']) ? number_format($mb['VL_ROTACAO_MOTOR'], 0, ',', '.') . ' rpm' : '-' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Informacoes do Sistema -->
    <div class="view-card">
        <div class="view-card-title">
            <ion-icon name="time-outline"></ion-icon>
            Informacoes do Sistema
        </div>
        <div class="view-grid">
            <div class="view-item">
                <span class="view-label">Ultima Atualizacao</span>
                <span class="view-value"><?= date('d/m/Y H:i', strtotime($mb['DT_ULTIMA_ATUALIZACAO'])) ?></span>
            </div>
            <div class="view-item">
                <span class="view-label">Usuario Atualizacao</span>
                <span class="view-value"><?= htmlspecialchars($mb['DS_USUARIO_ATUALIZACAO'] ?? '-') ?></span>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.inc.php'; ?>
