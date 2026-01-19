<?php
/**
 * SIMP - Dashboard de Saúde
 * Endpoint: Evolução Diária do Score
 * 
 * PRIORIDADE:
 * 1. TB_EVOLUCAO_DIARIA_CACHE (mais rápido ~10ms)
 * 2. VW_EVOLUCAO_DIARIA (view/fallback)
 * 3. MEDICAO_RESUMO_DIARIO (cálculo direto - LENTO)
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    // Parâmetros
    $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 7;
    $dias = max(1, min(90, $dias));
    
    // Buscar última data disponível no cache
    $sqlMaxData = "SELECT MAX(DT_MEDICAO) AS DATA_MAX FROM TB_EVOLUCAO_DIARIA_CACHE";
    $stmtMax = $pdoSIMP->query($sqlMaxData);
    $resultMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
    
    if ($resultMax && $resultMax['DATA_MAX']) {
        // Usar última data com dados como referência
        $dataFim = $resultMax['DATA_MAX'];
        $dataInicio = date('Y-m-d', strtotime("-{$dias} days", strtotime($dataFim)));
    } else {
        // Fallback para data atual
        $dataInicio = date('Y-m-d', strtotime("-{$dias} days"));
        $dataFim = date('Y-m-d');
    }
    
    $dados = [];
    $fonte = '';
    
    // PRIORIDADE 1: Tabela de Cache (mais rápido)
    $checkCache = $pdoSIMP->query("SELECT OBJECT_ID('TB_EVOLUCAO_DIARIA_CACHE', 'U') AS CacheExists");
    $cacheExists = $checkCache->fetch(PDO::FETCH_ASSOC)['CacheExists'];
    
    if ($cacheExists) {
        $sql = "
            SELECT 
                DT_MEDICAO,
                TOTAL_PONTOS,
                SCORE_MEDIO,
                QTD_SAUDAVEIS,
                QTD_ALERTA,
                QTD_CRITICOS,
                TOTAL_ANOMALIAS,
                TOTAL_TRATAMENTOS
            FROM TB_EVOLUCAO_DIARIA_CACHE 
            WHERE DT_MEDICAO >= :dataInicio 
              AND DT_MEDICAO <= :dataFim 
            ORDER BY DT_MEDICAO
        ";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':dataInicio' => $dataInicio, ':dataFim' => $dataFim]);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $fonte = 'TB_EVOLUCAO_DIARIA_CACHE';
    }
    
    // PRIORIDADE 2: View (se cache vazio ou não existe)
    if (empty($dados)) {
        $checkView = $pdoSIMP->query("SELECT OBJECT_ID('VW_EVOLUCAO_DIARIA', 'V') AS ViewExists");
        $viewExists = $checkView->fetch(PDO::FETCH_ASSOC)['ViewExists'];
        
        if ($viewExists) {
            $sql = "
                SELECT * FROM VW_EVOLUCAO_DIARIA 
                WHERE DT_MEDICAO >= :dataInicio 
                  AND DT_MEDICAO <= :dataFim 
                ORDER BY DT_MEDICAO
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([':dataInicio' => $dataInicio, ':dataFim' => $dataFim]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fonte = 'VW_EVOLUCAO_DIARIA';
        }
    }
    
    // PRIORIDADE 3: Cálculo direto (mais lento - evitar!)
    if (empty($dados)) {
        $checkTable = $pdoSIMP->query("SELECT OBJECT_ID('MEDICAO_RESUMO_DIARIO', 'U') AS TableExists");
        $tableExists = $checkTable->fetch(PDO::FETCH_ASSOC)['TableExists'];
        
        if ($tableExists) {
            $sql = "
                SELECT 
                    DT_MEDICAO,
                    COUNT(DISTINCT CD_PONTO_MEDICAO) AS TOTAL_PONTOS,
                    ROUND(AVG(CAST(VL_SCORE_SAUDE AS DECIMAL(5,2))), 2) AS SCORE_MEDIO,
                    SUM(CASE WHEN VL_SCORE_SAUDE >= 8 THEN 1 ELSE 0 END) AS QTD_SAUDAVEIS,
                    SUM(CASE WHEN VL_SCORE_SAUDE >= 5 AND VL_SCORE_SAUDE < 8 THEN 1 ELSE 0 END) AS QTD_ALERTA,
                    SUM(CASE WHEN VL_SCORE_SAUDE < 5 THEN 1 ELSE 0 END) AS QTD_CRITICOS,
                    SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS TOTAL_ANOMALIAS,
                    SUM(CASE WHEN ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS TOTAL_TRATAMENTOS
                FROM MEDICAO_RESUMO_DIARIO
                WHERE DT_MEDICAO >= :dataInicio
                  AND DT_MEDICAO <= :dataFim
                GROUP BY DT_MEDICAO
                ORDER BY DT_MEDICAO
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([':dataInicio' => $dataInicio, ':dataFim' => $dataFim]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fonte = 'MEDICAO_RESUMO_DIARIO (direto)';
        }
    }
    
    // Se não houver dados, gerar array vazio com estrutura
    if (empty($dados)) {
        $dataAtual = new DateTime($dataInicio);
        $dataFinal = new DateTime($dataFim);
        
        while ($dataAtual <= $dataFinal) {
            $dados[] = [
                'DT_MEDICAO' => $dataAtual->format('Y-m-d'),
                'TOTAL_PONTOS' => 0,
                'SCORE_MEDIO' => 0,
                'QTD_SAUDAVEIS' => 0,
                'QTD_ALERTA' => 0,
                'QTD_CRITICOS' => 0,
                'TOTAL_ANOMALIAS' => 0,
                'TOTAL_TRATAMENTOS' => 0
            ];
            $dataAtual->modify('+1 day');
        }
        $fonte = 'dados_vazios';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $dados,
        'periodo' => [
            'inicio' => $dataInicio,
            'fim' => $dataFim,
            'dias' => $dias
        ],
        'fonte' => $fonte
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}