<?php
// bd/calibracaoDeprimogenio/salvarLeituras.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdCalibracaoDeprimogenio = isset($_POST['cd_calibracao_deprimogenio']) ? (int)$_POST['cd_calibracao_deprimogenio'] : 0;
    $leituras = isset($_POST['leituras']) ? json_decode($_POST['leituras'], true) : [];

    if ($cdCalibracaoDeprimogenio <= 0) {
        echo json_encode(['success' => false, 'message' => 'Calibração não informada.']);
        exit;
    }

    // Inicia transação
    $pdoSIMP->beginTransaction();

    // Remove todas as leituras existentes desta calibração
    $sqlDelete = "DELETE FROM SIMP.dbo.CALIBRACAO_DEPRIMOGENIO_LEITURA WHERE CD_CALIBRACAO_DEPRIMOGENIO = :cd_calibracao";
    $stmtDelete = $pdoSIMP->prepare($sqlDelete);
    $stmtDelete->execute([':cd_calibracao' => $cdCalibracaoDeprimogenio]);

    // Insere as novas leituras
    if (!empty($leituras)) {
        $sqlInsert = "INSERT INTO SIMP.dbo.CALIBRACAO_DEPRIMOGENIO_LEITURA (
                        CD_CALIBRACAO_DEPRIMOGENIO,
                        CD_PONTO_LEITURA,
                        VL_VAZAO_EP,
                        VL_PERCENTUAL_INCERTEZA_EP,
                        VL_K_DEPRIMOGENIO
                    ) VALUES (
                        :cd_calibracao,
                        :cd_ponto_leitura,
                        :vl_vazao_ep,
                        :vl_percentual_incerteza_ep,
                        :vl_k_deprimogenio
                    )";
        
        $stmtInsert = $pdoSIMP->prepare($sqlInsert);

        foreach ($leituras as $leitura) {
            // Só insere se pelo menos um campo estiver preenchido
            $vlVazaoEp = isset($leitura['vl_vazao_ep']) && $leitura['vl_vazao_ep'] !== '' 
                ? str_replace(',', '.', $leitura['vl_vazao_ep']) : null;
            $vlPercentualIncertezaEp = isset($leitura['vl_percentual_incerteza_ep']) && $leitura['vl_percentual_incerteza_ep'] !== '' 
                ? str_replace(',', '.', $leitura['vl_percentual_incerteza_ep']) : null;
            $vlKDeprimogenio = isset($leitura['vl_k_deprimogenio']) && $leitura['vl_k_deprimogenio'] !== '' 
                ? str_replace(',', '.', $leitura['vl_k_deprimogenio']) : null;

            // Só insere se pelo menos um valor estiver preenchido
            if ($vlVazaoEp !== null || $vlPercentualIncertezaEp !== null || $vlKDeprimogenio !== null) {
                $stmtInsert->execute([
                    ':cd_calibracao' => $cdCalibracaoDeprimogenio,
                    ':cd_ponto_leitura' => (int)$leitura['cd_ponto_leitura'],
                    ':vl_vazao_ep' => $vlVazaoEp,
                    ':vl_percentual_incerteza_ep' => $vlPercentualIncertezaEp,
                    ':vl_k_deprimogenio' => $vlKDeprimogenio
                ]);
            }
        }
    }

    $pdoSIMP->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Leituras salvas com sucesso!'
    ]);

} catch (PDOException $e) {
    if ($pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar leituras: ' . $e->getMessage()
    ]);
}