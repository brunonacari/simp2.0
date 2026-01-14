<?php
// bd/programacaoManutencao/getPontosMedicaoFormatado.php
header('Content-Type: application/json');
include_once '../conexao.php';

try {
    $cdLocalidade = isset($_GET['cd_localidade']) ? (int)$_GET['cd_localidade'] : 0;
    
    if (!$cdLocalidade) {
        echo json_encode(['success' => false, 'message' => 'Localidade não informada', 'data' => []]);
        exit;
    }

    $sql = "SELECT 
                PM.CD_PONTO_MEDICAO,
                PM.DS_NOME,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE,
                L.CD_UNIDADE
            FROM SIMP.dbo.PONTO_MEDICAO PM
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE PM.CD_LOCALIDADE = :cd_localidade
              AND PM.DT_DESATIVACAO IS NULL
            ORDER BY PM.DS_NOME";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_localidade' => $cdLocalidade]);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $pontos
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar pontos de medição: ' . $e->getMessage(),
        'data' => []
    ]);
}