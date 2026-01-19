<?php
/**
 * SIMP - Endpoint de Alertas em Tempo Real
 * 
 * Consulta direta na REGISTRO_VAZAO_PRESSAO
 * Sem cache, sem procedures - sempre dados atuais
 * 
 * PARÂMETROS:
 *   ?data=2025-01-17  - Data específica para análise
 *   ?ponto=1234       - Filtrar por ponto específico (opcional)
 * 
 * Se não informar data, usa o ÚLTIMO DIA COM DADOS
 * 
 * Quando operador trata dados (ID_SITUACAO = 2), 
 * automaticamente some dos alertas
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    // Configurações de alertas
    $horasSemComunicacao = 4;    // Sem dados há mais de X horas
    $minutosZerosSuspeitos = 60; // Mais de X minutos com zero
    $limitePressaoMax = 80;      // mca máximo
    
    // ========================================
    // PARÂMETRO DE DATA
    // ========================================
    $dataFiltro = isset($_GET['data']) ? $_GET['data'] : null;
    
    if ($dataFiltro && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFiltro)) {
        // Usar data informada pelo usuário
        $dataReferencia = $dataFiltro;
    } else {
        // Buscar ÚLTIMO DIA COM DADOS
        $sqlDataMax = "SELECT MAX(CAST(DT_LEITURA AS DATE)) AS DT_MAX FROM REGISTRO_VAZAO_PRESSAO";
        $dataReferencia = $pdoSIMP->query($sqlDataMax)->fetch(PDO::FETCH_ASSOC)['DT_MAX'];
    }
    
    if (!$dataReferencia) {
        echo json_encode(['success' => true, 'data' => [], 'contadores' => [], 'message' => 'Sem dados']);
        exit;
    }
    
    // Buscar última hora de registro do dia de referência
    $sqlHoraMax = "SELECT MAX(DT_LEITURA) AS DT_HORA_MAX FROM REGISTRO_VAZAO_PRESSAO WHERE CAST(DT_LEITURA AS DATE) = :dataRef";
    $stmtHora = $pdoSIMP->prepare($sqlHoraMax);
    $stmtHora->execute([':dataRef' => $dataReferencia]);
    $dtHoraMax = $stmtHora->fetch(PDO::FETCH_ASSOC)['DT_HORA_MAX'];
    
    // Hora limite para considerar "sem comunicação" - baseada no último registro do dia
    $horaLimite = date('Y-m-d H:i:s', strtotime("-{$horasSemComunicacao} hours", strtotime($dtHoraMax)));
    
    $alertas = [];
    $contadores = [
        'COMUNICACAO' => 0,
        'ZEROS' => 0,
        'NEGATIVO' => 0,
        'FAIXA' => 0,
        'CONSTANTE' => 0
    ];
    
    // ========================================
    // 1. PONTOS SEM COMUNICAÇÃO
    // Pontos que não enviaram dados nas últimas X horas do dia de referência
    // ========================================
    $sqlComunicacao = "
        WITH UltimosDados AS (
            SELECT 
                CD_PONTO_MEDICAO,
                MAX(DT_LEITURA) AS ULTIMA_LEITURA
            FROM REGISTRO_VAZAO_PRESSAO
            WHERE CAST(DT_LEITURA AS DATE) >= DATEADD(DAY, -7, :dataRef1)
            GROUP BY CD_PONTO_MEDICAO
        )
        SELECT 
            UD.CD_PONTO_MEDICAO,
            UD.ULTIMA_LEITURA,
            PM.DS_LOCALIDADE,
            PM.NM_PONTO,
            M.NM_MUNICIPIO,
            DATEDIFF(HOUR, UD.ULTIMA_LEITURA, :dtHoraMax) AS HORAS_SEM_COMUNICACAO
        FROM UltimosDados UD
        LEFT JOIN PONTO_MEDICAO PM ON UD.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        LEFT JOIN MUNICIPIO M ON PM.CD_MUNICIPIO = M.CD_MUNICIPIO
        WHERE UD.ULTIMA_LEITURA < :horaLimite
        ORDER BY UD.ULTIMA_LEITURA ASC
    ";
    
    $stmt = $pdoSIMP->prepare($sqlComunicacao);
    $stmt->execute([
        ':dataRef1' => $dataReferencia,
        ':dtHoraMax' => $dtHoraMax,
        ':horaLimite' => $horaLimite
    ]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $horas = $row['HORAS_SEM_COMUNICACAO'];
        $alertas[] = [
            'CD_PONTO_MEDICAO' => $row['CD_PONTO_MEDICAO'],
            'NM_PONTO' => $row['NM_PONTO'],
            'DS_LOCALIDADE' => $row['DS_LOCALIDADE'],
            'NM_MUNICIPIO' => $row['NM_MUNICIPIO'],
            'TIPO_ALERTA' => 'COMUNICACAO',
            'DS_ALERTA' => "Sem comunicação há {$horas}h",
            'DT_REFERENCIA' => date('Y-m-d', strtotime($row['ULTIMA_LEITURA'])),
            'ULTIMA_LEITURA' => $row['ULTIMA_LEITURA'],
            'PRIORIDADE' => 1
        ];
        $contadores['COMUNICACAO']++;
    }
    
    // ========================================
    // 2. ZEROS SUSPEITOS
    // Pontos com muitos registros zerados (não tratados)
    // ========================================
    $sqlZeros = "
        SELECT 
            RVP.CD_PONTO_MEDICAO,
            PM.DS_LOCALIDADE,
            PM.NM_PONTO,
            M.NM_MUNICIPIO,
            COUNT(*) AS QTD_ZEROS,
            :dataRef2 AS DT_REFERENCIA
        FROM REGISTRO_VAZAO_PRESSAO RVP
        LEFT JOIN PONTO_MEDICAO PM ON RVP.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        LEFT JOIN MUNICIPIO M ON PM.CD_MUNICIPIO = M.CD_MUNICIPIO
        WHERE CAST(RVP.DT_LEITURA AS DATE) = :dataRef3
          AND RVP.ID_SITUACAO = 1
          AND ISNULL(RVP.ID_TIPO_MEDICAO, 1) IN (1, 2, 8)
          AND ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0) = 0
        GROUP BY RVP.CD_PONTO_MEDICAO, PM.DS_LOCALIDADE, PM.NM_PONTO, M.NM_MUNICIPIO
        HAVING COUNT(*) >= :minutosZeros
        ORDER BY COUNT(*) DESC
    ";
    
    $stmt = $pdoSIMP->prepare($sqlZeros);
    $stmt->execute([
        ':dataRef2' => $dataReferencia,
        ':dataRef3' => $dataReferencia,
        ':minutosZeros' => $minutosZerosSuspeitos
    ]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alertas[] = [
            'CD_PONTO_MEDICAO' => $row['CD_PONTO_MEDICAO'],
            'NM_PONTO' => $row['NM_PONTO'],
            'DS_LOCALIDADE' => $row['DS_LOCALIDADE'],
            'NM_MUNICIPIO' => $row['NM_MUNICIPIO'],
            'TIPO_ALERTA' => 'ZEROS',
            'DS_ALERTA' => "{$row['QTD_ZEROS']} minutos com vazão zero",
            'DT_REFERENCIA' => $row['DT_REFERENCIA'],
            'PRIORIDADE' => 2
        ];
        $contadores['ZEROS']++;
    }
    
    // ========================================
    // 3. VALORES NEGATIVOS
    // Registros com valores negativos não tratados
    // ========================================
    $sqlNegativos = "
        SELECT 
            RVP.CD_PONTO_MEDICAO,
            PM.DS_LOCALIDADE,
            PM.NM_PONTO,
            M.NM_MUNICIPIO,
            COUNT(*) AS QTD_NEGATIVOS,
            MIN(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO)) AS VALOR_MIN,
            :dataRef4 AS DT_REFERENCIA
        FROM REGISTRO_VAZAO_PRESSAO RVP
        LEFT JOIN PONTO_MEDICAO PM ON RVP.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        LEFT JOIN MUNICIPIO M ON PM.CD_MUNICIPIO = M.CD_MUNICIPIO
        WHERE CAST(RVP.DT_LEITURA AS DATE) = :dataRef5
          AND RVP.ID_SITUACAO = 1
          AND ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0) < 0
        GROUP BY RVP.CD_PONTO_MEDICAO, PM.DS_LOCALIDADE, PM.NM_PONTO, M.NM_MUNICIPIO
        ORDER BY COUNT(*) DESC
    ";
    
    $stmt = $pdoSIMP->prepare($sqlNegativos);
    $stmt->execute([
        ':dataRef4' => $dataReferencia,
        ':dataRef5' => $dataReferencia
    ]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $valorMin = number_format($row['VALOR_MIN'], 2, ',', '.');
        $alertas[] = [
            'CD_PONTO_MEDICAO' => $row['CD_PONTO_MEDICAO'],
            'NM_PONTO' => $row['NM_PONTO'],
            'DS_LOCALIDADE' => $row['DS_LOCALIDADE'],
            'NM_MUNICIPIO' => $row['NM_MUNICIPIO'],
            'TIPO_ALERTA' => 'NEGATIVO',
            'DS_ALERTA' => "{$row['QTD_NEGATIVOS']} registros negativos (mín: {$valorMin} L/s)",
            'DT_REFERENCIA' => $row['DT_REFERENCIA'],
            'PRIORIDADE' => 1
        ];
        $contadores['NEGATIVO']++;
    }
    
    // ========================================
    // 4. VALORES FORA DA FAIXA (Pressão)
    // Pressão muito alta ou muito baixa
    // ========================================
    $sqlFaixa = "
        SELECT 
            RVP.CD_PONTO_MEDICAO,
            PM.DS_LOCALIDADE,
            PM.NM_PONTO,
            M.NM_MUNICIPIO,
            COUNT(*) AS QTD_FORA,
            MAX(RVP.VL_PRESSAO) AS PRESSAO_MAX,
            MIN(RVP.VL_PRESSAO) AS PRESSAO_MIN,
            :dataRef6 AS DT_REFERENCIA
        FROM REGISTRO_VAZAO_PRESSAO RVP
        LEFT JOIN PONTO_MEDICAO PM ON RVP.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        LEFT JOIN MUNICIPIO M ON PM.CD_MUNICIPIO = M.CD_MUNICIPIO
        WHERE CAST(RVP.DT_LEITURA AS DATE) = :dataRef7
          AND RVP.ID_SITUACAO = 1
          AND ISNULL(RVP.ID_TIPO_MEDICAO, 1) = 4
          AND (RVP.VL_PRESSAO > :pressaoMax OR RVP.VL_PRESSAO < 0)
        GROUP BY RVP.CD_PONTO_MEDICAO, PM.DS_LOCALIDADE, PM.NM_PONTO, M.NM_MUNICIPIO
        ORDER BY MAX(RVP.VL_PRESSAO) DESC
    ";
    
    $stmt = $pdoSIMP->prepare($sqlFaixa);
    $stmt->execute([
        ':dataRef6' => $dataReferencia,
        ':dataRef7' => $dataReferencia,
        ':pressaoMax' => $limitePressaoMax
    ]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pressaoMax = number_format($row['PRESSAO_MAX'], 1, ',', '.');
        $alertas[] = [
            'CD_PONTO_MEDICAO' => $row['CD_PONTO_MEDICAO'],
            'NM_PONTO' => $row['NM_PONTO'],
            'DS_LOCALIDADE' => $row['DS_LOCALIDADE'],
            'NM_MUNICIPIO' => $row['NM_MUNICIPIO'],
            'TIPO_ALERTA' => 'FAIXA',
            'DS_ALERTA' => "Pressão fora da faixa (máx: {$pressaoMax} mca)",
            'DT_REFERENCIA' => $row['DT_REFERENCIA'],
            'PRIORIDADE' => 2
        ];
        $contadores['FAIXA']++;
    }
    
    // ========================================
    // 5. VALOR CONSTANTE
    // Mesmo valor por muito tempo (medidor travado)
    // ========================================
    $sqlConstante = "
        SELECT 
            RVP.CD_PONTO_MEDICAO,
            PM.DS_LOCALIDADE,
            PM.NM_PONTO,
            M.NM_MUNICIPIO,
            COUNT(DISTINCT ROUND(ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0), 2)) AS QTD_VALORES_DISTINTOS,
            COUNT(*) AS QTD_REGISTROS,
            :dataRef8 AS DT_REFERENCIA
        FROM REGISTRO_VAZAO_PRESSAO RVP
        LEFT JOIN PONTO_MEDICAO PM ON RVP.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        LEFT JOIN MUNICIPIO M ON PM.CD_MUNICIPIO = M.CD_MUNICIPIO
        WHERE CAST(RVP.DT_LEITURA AS DATE) = :dataRef9
          AND RVP.ID_SITUACAO = 1
          AND ISNULL(RVP.ID_TIPO_MEDICAO, 1) IN (1, 2, 8)
        GROUP BY RVP.CD_PONTO_MEDICAO, PM.DS_LOCALIDADE, PM.NM_PONTO, M.NM_MUNICIPIO
        HAVING COUNT(DISTINCT ROUND(ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0), 2)) <= 3
           AND COUNT(*) >= 500
           AND AVG(ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0)) > 0
        ORDER BY COUNT(*) DESC
    ";
    
    $stmt = $pdoSIMP->prepare($sqlConstante);
    $stmt->execute([
        ':dataRef8' => $dataReferencia,
        ':dataRef9' => $dataReferencia
    ]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alertas[] = [
            'CD_PONTO_MEDICAO' => $row['CD_PONTO_MEDICAO'],
            'NM_PONTO' => $row['NM_PONTO'],
            'DS_LOCALIDADE' => $row['DS_LOCALIDADE'],
            'NM_MUNICIPIO' => $row['NM_MUNICIPIO'],
            'TIPO_ALERTA' => 'CONSTANTE',
            'DS_ALERTA' => "Valor constante ({$row['QTD_VALORES_DISTINTOS']} valores distintos em {$row['QTD_REGISTROS']} leituras)",
            'DT_REFERENCIA' => $row['DT_REFERENCIA'],
            'PRIORIDADE' => 3
        ];
        $contadores['CONSTANTE']++;
    }
    
    // Ordenar por prioridade
    usort($alertas, function($a, $b) {
        return $a['PRIORIDADE'] - $b['PRIORIDADE'];
    });
    
    echo json_encode([
        'success' => true,
        'data' => $alertas,
        'contadores' => $contadores,
        'data_referencia' => $dataReferencia,
        'ultima_leitura' => $dtHoraMax,
        'total' => count($alertas)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => [],
        'contadores' => []
    ], JSON_UNESCAPED_UNICODE);
}