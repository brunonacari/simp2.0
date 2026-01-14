<?php
/**
 * SIMP - Buscar Valores de Entidade por Tipo
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $tipoId = isset($_GET['tipoId']) ? (int)$_GET['tipoId'] : 0;

    if ($tipoId <= 0) {
        throw new Exception('Tipo de entidade não informado');
    }

    $sql = "SELECT 
                CD_CHAVE AS cd,
                DS_NOME AS nome,
                CD_ENTIDADE_VALOR_ID AS id,
                ID_FLUXO AS fluxo
            FROM SIMP.dbo.ENTIDADE_VALOR 
            WHERE CD_ENTIDADE_TIPO = :tipoId
            ORDER BY DS_NOME";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':tipoId' => $tipoId]);
    $valores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'valores' => $valores
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}