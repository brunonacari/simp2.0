<?php
/**
 * SIMP - Buscar Pontos de Medição vinculados a Entidades
 * Filtra por:
 * - CD_ENTIDADE_VALOR_ID (valor específico)
 * - CD_ENTIDADE_TIPO (tipo de entidade)
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $valorId = isset($_GET['valor_id']) ? trim($_GET['valor_id']) : '';
    $tipoId = isset($_GET['tipo_id']) && $_GET['tipo_id'] !== '' ? (int)$_GET['tipo_id'] : 0;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    $params = [];
    $where = [];
    
    // Se tem valor_id, busca pontos vinculados a esse valor de entidade
    if (!empty($valorId)) {
        $sql = "SELECT DISTINCT TOP 50
                    PM.CD_PONTO_MEDICAO,
                    PM.DS_NOME,
                    PM.ID_TIPO_MEDIDOR,
                    L.CD_LOCALIDADE,
                    L.CD_UNIDADE
                FROM SIMP.dbo.PONTO_MEDICAO PM
                LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                INNER JOIN SIMP.dbo.ENTIDADE_VALOR_ITEM EVI ON EVI.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
                INNER JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_CHAVE = EVI.CD_ENTIDADE_VALOR";
        
        $where[] = "EV.CD_ENTIDADE_VALOR_ID = :valorId";
        $params[':valorId'] = $valorId;
        
        if (!empty($busca)) {
            $where[] = "(PM.DS_NOME LIKE :busca OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR) LIKE :busca2 OR EV.DS_NOME LIKE :busca3)";
            $params[':busca'] = '%' . $busca . '%';
            $params[':busca2'] = '%' . $busca . '%';
            $params[':busca3'] = '%' . $busca . '%';
        }
    }
    // Se tem tipo_id, busca pontos vinculados a esse tipo de entidade
    elseif ($tipoId > 0) {
        $sql = "SELECT DISTINCT TOP 50
                    PM.CD_PONTO_MEDICAO,
                    PM.DS_NOME,
                    PM.ID_TIPO_MEDIDOR,
                    L.CD_LOCALIDADE,
                    L.CD_UNIDADE
                FROM SIMP.dbo.PONTO_MEDICAO PM
                LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                INNER JOIN SIMP.dbo.ENTIDADE_VALOR_ITEM EVI ON EVI.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
                INNER JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_CHAVE = EVI.CD_ENTIDADE_VALOR
                INNER JOIN SIMP.dbo.ENTIDADE_TIPO ET ON ET.CD_CHAVE = EV.CD_ENTIDADE_TIPO";
        
        $where[] = "ET.CD_CHAVE = :tipoId";
        $params[':tipoId'] = $tipoId;
        
        if (!empty($busca)) {
            $where[] = "(PM.DS_NOME LIKE :busca OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR) LIKE :busca2 OR EV.DS_NOME LIKE :busca3 OR ET.DS_NOME LIKE :busca4)";
            $params[':busca'] = '%' . $busca . '%';
            $params[':busca2'] = '%' . $busca . '%';
            $params[':busca3'] = '%' . $busca . '%';
            $params[':busca4'] = '%' . $busca . '%';
        }
    }
    // Sem filtro de entidade
    else {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit;
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $sql .= " ORDER BY PM.DS_NOME";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $pontos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
}