<?php
// bd/calibracaoDeprimogenio/getCalibracaoDeprimogenio.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdRegistroManutencao = isset($_GET['cd_registro_manutencao']) ? (int)$_GET['cd_registro_manutencao'] : 0;

    if ($cdRegistroManutencao <= 0) {
        echo json_encode(['success' => false, 'message' => 'Registro de manutenção não informado.']);
        exit;
    }

    // Busca dados da calibração
    $sql = "SELECT 
                CD_CHAVE,
                CD_REGISTRO_MANUTENCAO,
                VL_PERCENTUAL_ACRESCIMO,
                VL_K_MEDIO,
                VL_VAZAO_MAXIMA,
                VL_PRESSAO_MAXIMA,
                VL_K_ANTERIOR,
                VL_DESVIO,
                OP_ATUALIZA_K
            FROM SIMP.dbo.CALIBRACAO_DEPRIMOGENIO
            WHERE CD_REGISTRO_MANUTENCAO = :cd_registro_manutencao";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_registro_manutencao' => $cdRegistroManutencao]);
    $calibracao = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($calibracao) {
        // Busca leituras da calibração
        $sqlLeituras = "SELECT 
                            CD_CHAVE,
                            CD_CALIBRACAO_DEPRIMOGENIO,
                            CD_PONTO_LEITURA,
                            VL_VAZAO_EP,
                            VL_PERCENTUAL_INCERTEZA_EP,
                            VL_K_DEPRIMOGENIO
                        FROM SIMP.dbo.CALIBRACAO_DEPRIMOGENIO_LEITURA
                        WHERE CD_CALIBRACAO_DEPRIMOGENIO = :cd_calibracao
                        ORDER BY CD_PONTO_LEITURA";

        $stmtLeituras = $pdoSIMP->prepare($sqlLeituras);
        $stmtLeituras->execute([':cd_calibracao' => $calibracao['CD_CHAVE']]);
        $leituras = $stmtLeituras->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'exists' => true,
            'calibracao' => $calibracao,
            'leituras' => $leituras
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'calibracao' => null,
            'leituras' => []
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar calibração: ' . $e->getMessage()
    ]);
}