<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Buscar Registros de um Ponto de Medição Específico em uma Data
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

// Aumentar limites para operações com muitos registros
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

require_once '../verificarAuth.php';
verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $data = isset($_GET['data']) ? trim($_GET['data']) : '';
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) ? trim($_GET['cd_ponto_medicao']) : '';
    $cdUnidade = isset($_GET['cd_unidade']) ? trim($_GET['cd_unidade']) : '';
    $cdLocalidade = isset($_GET['cd_localidade']) ? trim($_GET['cd_localidade']) : '';
    $tipoMedidor = isset($_GET['tipo_medidor']) ? trim($_GET['tipo_medidor']) : '';
    $tipoLeitura = isset($_GET['tipo_leitura']) ? trim($_GET['tipo_leitura']) : '';
    $descarte = isset($_GET['descarte']) ? trim($_GET['descarte']) : '';
    $apenasChaves = isset($_GET['apenas_chaves']) && $_GET['apenas_chaves'] == '1';

    if (empty($data)) {
        throw new Exception('Data não informada');
    }

    if (empty($cdPontoMedicao)) {
        throw new Exception('Ponto de medição não informado');
    }

    // Validar formato da data (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Formato de data inválido');
    }

    $where = "WHERE CONVERT(DATE, RVP.DT_LEITURA) = :data AND RVP.CD_PONTO_MEDICAO = :cd_ponto_medicao";
    $params = [
        ':data' => $data,
        ':cd_ponto_medicao' => $cdPontoMedicao
    ];

    // Filtro por Unidade
    if (!empty($cdUnidade)) {
        $where .= " AND LOC.CD_UNIDADE = :cd_unidade";
        $params[':cd_unidade'] = $cdUnidade;
    }

    // Filtro por Localidade
    if (!empty($cdLocalidade)) {
        $where .= " AND PM.CD_LOCALIDADE = :cd_localidade";
        $params[':cd_localidade'] = $cdLocalidade;
    }

    // Filtro por Tipo de Medidor
    if (!empty($tipoMedidor)) {
        $where .= " AND PM.ID_TIPO_MEDIDOR = :tipo_medidor";
        $params[':tipo_medidor'] = $tipoMedidor;
    }

    // Filtro por Tipo de Leitura
    if (!empty($tipoLeitura)) {
        $where .= " AND PM.ID_TIPO_LEITURA = :tipo_leitura";
        $params[':tipo_leitura'] = $tipoLeitura;
    }

    // Filtro por Descarte (ID_SITUACAO: 1 = Não, 2 = Sim)
    if (!empty($descarte)) {
        $where .= " AND RVP.ID_SITUACAO = :descarte";
        $params[':descarte'] = $descarte;
    }

    if ($apenasChaves) {
        // Retornar apenas as chaves (para exclusão em massa)
        $sql = "SELECT RVP.CD_CHAVE
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                $where
                ORDER BY RVP.CD_CHAVE";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true, 
            'chaves' => array_map('intval', $dados),
            'total' => count($dados)
        ]);
    } else {
        // Buscar registros completos
        $sql = "SELECT 
                    RVP.CD_CHAVE,
                    RVP.CD_PONTO_MEDICAO,
                    RVP.DT_EVENTO_MEDICAO,
                    RVP.ID_TIPO_REGISTRO,
                    RVP.ID_TIPO_MEDICAO,
                    RVP.DT_LEITURA,
                    RVP.VL_VAZAO,
                    RVP.VL_PRESSAO,
                    RVP.VL_TEMP_AGUA,
                    RVP.VL_TEMP_AMBIENTE,
                    RVP.ID_SITUACAO,
                    RVP.VL_VOLUME,
                    RVP.ID_TIPO_VAZAO,
                    RVP.DS_OBSERVACAO,
                    RVP.VL_VAZAO_EFETIVA,
                    RVP.VL_RESERVATORIO,
                    RVP.NR_EXTRAVASOU,
                    RVP.HOUVE_OCORRENCIA,
                    RVP.NUM_OS,
                    UNI.DS_NOME AS DS_UNIDADE,
                    CONCAT(LOC.CD_LOCALIDADE, ' - ', LOC.DS_NOME) AS DS_LOCALIDADE,
                    CONCAT(PM.CD_PONTO_MEDICAO, ' - ', PM.DS_NOME) AS DS_PONTO_MEDICAO
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN SIMP.dbo.UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                $where
                ORDER BY RVP.DT_LEITURA DESC";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'data' => $dados,
            'total' => count($dados)
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}