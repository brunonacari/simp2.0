<?php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

exigePermissaoTela('Cadastro de Conjunto Motor-Bomba', ACESSO_ESCRITA);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdicao = $id > 0;
$motorBomba = null;

if ($isEdicao) {
    $sql = "SELECT 
                CMB.*,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                U.CD_CODIGO AS CD_UNIDADE_CODIGO,
                UA.DS_NOME AS DS_USUARIO_ATUALIZACAO,
                UR.DS_NOME AS DS_USUARIO_RESPONSAVEL_NOME
            FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
            INNER JOIN SIMP.dbo.LOCALIDADE L ON CMB.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            LEFT JOIN SIMP.dbo.USUARIO UA ON CMB.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
            LEFT JOIN SIMP.dbo.USUARIO UR ON CMB.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
            WHERE CMB.CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $motorBomba = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$motorBomba) {
        $_SESSION['msg'] = 'Registro nao encontrado.';
        $_SESSION['msg_tipo'] = 'erro';
        header('Location: motorBomba.php');
        exit;
    }
}

$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

$sqlUsuarios = $pdoSIMP->query("SELECT CD_USUARIO, DS_NOME, DS_MATRICULA FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2 ORDER BY DS_NOME");
$usuarios = $sqlUsuarios->fetchAll(PDO::FETCH_ASSOC);

$tiposEixo = [
    ['value' => 'H', 'text' => 'Horizontal'],
    ['value' => 'V', 'text' => 'Vertical'],
];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="style/css/motorBomba.css">

<style>
.form-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
}

.form-card-title {
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

.form-card-title ion-icon {
    font-size: 20px;
    color: #3b82f6;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.form-group.span-2 { grid-column: span 2; }
.form-group.span-3 { grid-column: span 3; }
.form-group.span-4 { grid-column: span 4; }

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.btn-salvar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-salvar:hover { background: #2563eb; }

.btn-cancelar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: #f1f5f9;
    color: #64748b;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-cancelar:hover { background: #e2e8f0; color: #475569; }

.required-field::after {
    content: ' *';
    color: #dc2626;
}

@media (max-width: 1200px) {
    .form-grid { grid-template-columns: repeat(2, 1fr); }
    .form-group.span-3, .form-group.span-4 { grid-column: span 2; }
}

@media (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-group.span-2, .form-group.span-3, .form-group.span-4 { grid-column: span 1; }
    .form-actions { flex-direction: column; }
    .btn-salvar, .btn-cancelar { width: 100%; justify-content: center; }
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
                    <h1><?= $isEdicao ? 'Editar' : 'Novo' ?> Conjunto Motor Bomba</h1>
                    <p class="page-header-subtitle"><?= $isEdicao ? 'Altere os dados do conjunto' : 'Preencha os dados do novo conjunto' ?></p>
                </div>
            </div>
        </div>
    </div>

    <form id="formMotorBomba" method="post">
        <input type="hidden" name="cd_chave" value="<?= $id ?>">

        <!-- Dados Gerais -->
        <div class="form-card">
            <div class="form-card-title">
                <ion-icon name="information-circle-outline"></ion-icon>
                Dados Gerais
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required-field">Unidade</label>
                    <select id="selectUnidade" name="cd_unidade" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= $u['CD_UNIDADE'] ?>" <?= ($isEdicao && $motorBomba['CD_UNIDADE'] == $u['CD_UNIDADE']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Localidade</label>
                    <select id="selectLocalidade" name="cd_localidade" class="form-control" required <?= !$isEdicao ? 'disabled' : '' ?>>
                        <?php if ($isEdicao): ?>
                            <option value="<?= $motorBomba['CD_LOCALIDADE'] ?>" selected>
                                <?= htmlspecialchars($motorBomba['CD_LOCALIDADE_CODIGO'] . ' - ' . $motorBomba['DS_LOCALIDADE']) ?>
                            </option>
                        <?php else: ?>
                            <option value="">Selecione uma Unidade primeiro</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Codigo</label>
                    <input type="text" name="ds_codigo" class="form-control" maxlength="20" required
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_CODIGO']) : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Nome</label>
                    <input type="text" name="ds_nome" class="form-control" maxlength="50" required
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_NOME']) : '' ?>">
                </div>

                <div class="form-group span-2">
                    <label class="form-label required-field">Localizacao</label>
                    <input type="text" name="ds_localizacao" class="form-control" maxlength="200" required
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_LOCALIZACAO']) : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Responsavel</label>
                    <select name="cd_usuario_responsavel" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['CD_USUARIO'] ?>" <?= ($isEdicao && $motorBomba['CD_USUARIO_RESPONSAVEL'] == $u['CD_USUARIO']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['DS_MATRICULA'] . ' - ' . $u['DS_NOME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Tipo de Eixo</label>
                    <select name="tp_eixo" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($tiposEixo as $t): ?>
                            <option value="<?= $t['value'] ?>" <?= ($isEdicao && $motorBomba['TP_EIXO'] == $t['value']) ? 'selected' : '' ?>>
                                <?= $t['text'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group span-4">
                    <label class="form-label">Observacao</label>
                    <textarea name="ds_observacao" class="form-control" maxlength="200" rows="2"><?= $isEdicao ? htmlspecialchars($motorBomba['DS_OBSERVACAO'] ?? '') : '' ?></textarea>
                </div>
            </div>
        </div>

        <!-- Dados da Bomba -->
        <div class="form-card">
            <div class="form-card-title">
                <ion-icon name="water-outline"></ion-icon>
                Dados da Bomba
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Fabricante</label>
                    <input type="text" name="ds_fabricante_bomba" class="form-control" maxlength="20"
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_FABRICANTE_BOMBA'] ?? '') : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <input type="text" name="ds_tipo_bomba" class="form-control" maxlength="20"
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_TIPO_BOMBA'] ?? '') : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Serie</label>
                    <input type="text" name="ds_serie_bomba" class="form-control" maxlength="20"
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_SERIE_BOMBA'] ?? '') : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Diametro Rotor (mm)</label>
                    <input type="number" name="vl_diametro_rotor_bomba" class="form-control" step="0.01" required
                           value="<?= $isEdicao ? $motorBomba['VL_DIAMETRO_ROTOR_BOMBA'] : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Vazao (m3/h)</label>
                    <input type="number" name="vl_vazao_bomba" class="form-control" step="0.01"
                           value="<?= $isEdicao ? $motorBomba['VL_VAZAO_BOMBA'] : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Altura Manometrica (mca)</label>
                    <input type="number" name="vl_altura_manometrica_bomba" class="form-control" step="0.01" required
                           value="<?= $isEdicao ? $motorBomba['VL_ALTURA_MANOMETRICA_BOMBA'] : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Rotacao (rpm)</label>
                    <input type="number" name="vl_rotacao_bomba" class="form-control" step="0.01"
                           value="<?= $isEdicao ? $motorBomba['VL_ROTACAO_BOMBA'] : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Area Succao (mm2)</label>
                    <input type="number" name="vl_area_succao_bomba" class="form-control" step="0.01"
                           value="<?= $isEdicao ? $motorBomba['VL_AREA_SUCCAO_BOMBA'] : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Area Recalque (mm2)</label>
                    <input type="number" name="vl_area_recalque_bomba" class="form-control" step="0.01"
                           value="<?= $isEdicao ? $motorBomba['VL_AREA_RECALQUE_BOMBA'] : '' ?>">
                </div>
            </div>
        </div>

        <!-- Dados do Motor -->
        <div class="form-card">
            <div class="form-card-title">
                <ion-icon name="flash-outline"></ion-icon>
                Dados do Motor
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Fabricante</label>
                    <input type="text" name="ds_fabricante_motor" class="form-control" maxlength="20"
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_FABRICANTE_MOTOR'] ?? '') : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <input type="text" name="ds_tipo_motor" class="form-control" maxlength="20"
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_TIPO_MOTOR'] ?? '') : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Serie</label>
                    <input type="text" name="ds_serie_motor" class="form-control" maxlength="20"
                           value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_SERIE_MOTOR'] ?? '') : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Tensao (V)</label>
                    <input type="number" name="vl_tensao_motor" class="form-control" step="0.01" required
                           value="<?= $isEdicao ? $motorBomba['VL_TENSAO_MOTOR'] : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Corrente Eletrica (A)</label>
                    <input type="number" name="vl_corrente_eletrica_motor" class="form-control" step="0.01" required
                           value="<?= $isEdicao ? $motorBomba['VL_CORRENTE_ELETRICA_MOTOR'] : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label required-field">Potencia (CV)</label>
                    <input type="number" name="vl_potencia_motor" class="form-control" step="0.01" required
                           value="<?= $isEdicao ? $motorBomba['VL_POTENCIA_MOTOR'] : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Rotacao (rpm)</label>
                    <input type="number" name="vl_rotacao_motor" class="form-control" step="0.01"
                           value="<?= $isEdicao ? $motorBomba['VL_ROTACAO_MOTOR'] : '' ?>">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="motorBomba.php" class="btn-cancelar">
                <ion-icon name="close-outline"></ion-icon>
                Cancelar
            </a>
            <button type="submit" class="btn-salvar">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('select').select2({ width: '100%', placeholder: 'Selecione...', allowClear: true });

    $('#selectUnidade').on('change', function() {
        const cdUnidade = $(this).val();
        if (cdUnidade) {
            carregarLocalidades(cdUnidade);
        } else {
            $('#selectLocalidade').val('').prop('disabled', true)
                .html('<option value="">Selecione uma Unidade primeiro</option>')
                .trigger('change.select2');
        }
    });

    $('#formMotorBomba').on('submit', function(e) {
        e.preventDefault();
        salvar();
    });
});

function carregarLocalidades(cdUnidade) {
    $('#selectLocalidade').prop('disabled', true).html('<option value="">Carregando...</option>');
    
    $.ajax({
        url: 'bd/getLocalidadesPorUnidade.php',
        method: 'GET',
        data: { cd_unidade: cdUnidade },
        dataType: 'json',
        success: function(response) {
            let options = '<option value="">Selecione...</option>';
            if (response.success && response.data) {
                response.data.forEach(function(loc) {
                    options += `<option value="${loc.CD_CHAVE}">${loc.CD_LOCALIDADE} - ${loc.DS_NOME}</option>`;
                });
            }
            $('#selectLocalidade').html(options).prop('disabled', false).trigger('change.select2');
        },
        error: function() {
            $('#selectLocalidade').html('<option value="">Erro ao carregar</option>');
            showToast('Erro ao carregar localidades', 'erro');
        }
    });
}

function salvar() {
    const formData = new FormData(document.getElementById('formMotorBomba'));
    
    $.ajax({
        url: 'bd/motorBomba/salvarMotorBomba.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                setTimeout(function() {
                    window.location.href = 'motorBomba.php';
                }, 1500);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        },
        error: function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}
</script>

<?php include_once 'includes/footer.inc.php'; ?>
