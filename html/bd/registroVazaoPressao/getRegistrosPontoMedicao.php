<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Buscar Chaves de Registros por Ponto de Medição
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) ? trim($_GET['cd_ponto_medicao']) : '';
    $dataInicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $dataFim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';
    $descarte = isset($_GET['descarte']) ? trim($_GET['descarte']) : '';
    $apenasChaves = isset($_GET['apenas_chaves']) ? (int)$_GET['apenas_chaves'] : 0;

    if (empty($cdPontoMedicao)) {
        throw new Exception('Ponto de medição não informado');
    }

    // WHERE base
    $where = "WHERE RVP.CD_PONTO_MEDICAO = :cd_ponto_medicao";
    $params = [':cd_ponto_medicao' => $cdPontoMedicao];

    // Filtro por Data Início
    if (!empty($dataInicio)) {
        $where .= " AND RVP.DT_LEITURA >= :data_inicio";
        $params[':data_inicio'] = $dataInicio . ' 00:00:00';
    }

    // Filtro por Data Fim
    if (!empty($dataFim)) {
        $where .= " AND RVP.DT_LEITURA <= :data_fim";
        $params[':data_fim'] = $dataFim . ' 23:59:59';
    }

    // Filtro por Descarte
    if (!empty($descarte)) {
        $where .= " AND RVP.ID_SITUACAO = :descarte";
        $params[':descarte'] = $descarte;
    }

    if ($apenasChaves) {
        // Retornar apenas as chaves (para seleção em massa)
        $sql = "SELECT RVP.CD_CHAVE
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                $where
                ORDER BY RVP.DT_LEITURA DESC";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $chaves = array_map(function($r) { return (int)$r['CD_CHAVE']; }, $registros);
        
        echo json_encode([
            'success' => true,
            'chaves' => $chaves,
            'total' => count($chaves)
        ]);
    } else {
        // Retornar registros completos
        $sql = "SELECT 
                    RVP.CD_CHAVE,
                    RVP.CD_PONTO_MEDICAO,
                    RVP.DT_LEITURA,
                    RVP.VL_VAZAO_EFETIVA,
                    RVP.VL_PRESSAO,
                    RVP.VL_RESERVATORIO,
                    RVP.ID_TIPO_REGISTRO,
                    RVP.ID_TIPO_VAZAO,
                    RVP.ID_SITUACAO,
                    PM.DS_NOME AS DS_PONTO_MEDICAO,
                    LOC.DS_NOME AS DS_LOCALIDADE,
                    UNI.DS_NOME AS DS_UNIDADE
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                $where
                ORDER BY RVP.DT_LEITURA DESC";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $registros,
            'total' => count($registros)
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}