<?php
// bd/calibracaoDeprimogenio/salvarCalibracaoDeprimogenio.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $cdRegistroManutencao = isset($_POST['cd_registro_manutencao']) ? (int)$_POST['cd_registro_manutencao'] : 0;
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;

    if ($cdRegistroManutencao <= 0) {
        echo json_encode(['success' => false, 'message' => 'Registro de manutenção não informado.']);
        exit;
    }

    // Campos da calibração
    $vlPercentualAcrescimo = isset($_POST['vl_percentual_acrescimo']) && $_POST['vl_percentual_acrescimo'] !== '' 
        ? str_replace(',', '.', $_POST['vl_percentual_acrescimo']) : null;
    $vlKMedio = isset($_POST['vl_k_medio']) && $_POST['vl_k_medio'] !== '' 
        ? str_replace(',', '.', $_POST['vl_k_medio']) : null;
    $vlVazaoMaxima = isset($_POST['vl_vazao_maxima']) && $_POST['vl_vazao_maxima'] !== '' 
        ? str_replace(',', '.', $_POST['vl_vazao_maxima']) : null;
    $vlPressaoMaxima = isset($_POST['vl_pressao_maxima']) && $_POST['vl_pressao_maxima'] !== '' 
        ? str_replace(',', '.', $_POST['vl_pressao_maxima']) : null;
    $vlKAnterior = isset($_POST['vl_k_anterior']) && $_POST['vl_k_anterior'] !== '' 
        ? str_replace(',', '.', $_POST['vl_k_anterior']) : null;
    $vlDesvio = isset($_POST['vl_desvio']) && $_POST['vl_desvio'] !== '' 
        ? str_replace(',', '.', $_POST['vl_desvio']) : null;
    $opAtualizaK = isset($_POST['op_atualiza_k']) ? (int)$_POST['op_atualiza_k'] : 0;

    if ($cdChave > 0) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.CALIBRACAO_DEPRIMOGENIO SET
                    VL_PERCENTUAL_ACRESCIMO = :vl_percentual_acrescimo,
                    VL_K_MEDIO = :vl_k_medio,
                    VL_VAZAO_MAXIMA = :vl_vazao_maxima,
                    VL_PRESSAO_MAXIMA = :vl_pressao_maxima,
                    VL_K_ANTERIOR = :vl_k_anterior,
                    VL_DESVIO = :vl_desvio,
                    OP_ATUALIZA_K = :op_atualiza_k
                WHERE CD_CHAVE = :cd_chave";

        $params = [
            ':vl_percentual_acrescimo' => $vlPercentualAcrescimo,
            ':vl_k_medio' => $vlKMedio,
            ':vl_vazao_maxima' => $vlVazaoMaxima,
            ':vl_pressao_maxima' => $vlPressaoMaxima,
            ':vl_k_anterior' => $vlKAnterior,
            ':vl_desvio' => $vlDesvio,
            ':op_atualiza_k' => $opAtualizaK,
            ':cd_chave' => $cdChave
        ];

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);

        // Log de atualização
        try {
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Registro de Manutenção', 'Calibração Deprimogênio', $cdChave, "Manutenção $cdRegistroManutencao",
                    ['cd_registro_manutencao' => $cdRegistroManutencao, 'vl_k_medio' => $vlKMedio, 'op_atualiza_k' => $opAtualizaK]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Calibração atualizada com sucesso!',
            'cd_chave' => $cdChave
        ]);

    } else {
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.CALIBRACAO_DEPRIMOGENIO (
                    CD_REGISTRO_MANUTENCAO,
                    VL_PERCENTUAL_ACRESCIMO,
                    VL_K_MEDIO,
                    VL_VAZAO_MAXIMA,
                    VL_PRESSAO_MAXIMA,
                    VL_K_ANTERIOR,
                    VL_DESVIO,
                    OP_ATUALIZA_K
                ) VALUES (
                    :cd_registro_manutencao,
                    :vl_percentual_acrescimo,
                    :vl_k_medio,
                    :vl_vazao_maxima,
                    :vl_pressao_maxima,
                    :vl_k_anterior,
                    :vl_desvio,
                    :op_atualiza_k
                )";

        $params = [
            ':cd_registro_manutencao' => $cdRegistroManutencao,
            ':vl_percentual_acrescimo' => $vlPercentualAcrescimo,
            ':vl_k_medio' => $vlKMedio,
            ':vl_vazao_maxima' => $vlVazaoMaxima,
            ':vl_pressao_maxima' => $vlPressaoMaxima,
            ':vl_k_anterior' => $vlKAnterior,
            ':vl_desvio' => $vlDesvio,
            ':op_atualiza_k' => $opAtualizaK
        ];

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);

        $novoCdChave = $pdoSIMP->lastInsertId();

        // Log de inserção
        try {
            if (function_exists('registrarLogInsert')) {
                registrarLogInsert('Registro de Manutenção', 'Calibração Deprimogênio', $novoCdChave, "Manutenção $cdRegistroManutencao",
                    ['cd_registro_manutencao' => $cdRegistroManutencao, 'vl_k_medio' => $vlKMedio, 'op_atualiza_k' => $opAtualizaK]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Calibração cadastrada com sucesso!',
            'cd_chave' => $novoCdChave
        ]);
    }

} catch (PDOException $e) {
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Registro de Manutenção', 'SALVAR_CALIBRACAO', $e->getMessage(),
                ['cd_registro_manutencao' => $cdRegistroManutencao ?? '', 'cd_chave' => $cdChave ?? '']);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar calibração: ' . $e->getMessage()
    ]);
}