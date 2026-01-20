<?php
/**
 * SIMP - Buscar datas com dados de um ponto de medição
 * Retorna lista de datas no período com resumo (total registros, média, etc)
 * 
 * @version 2.2 - Alterado para usar AVG em vez de SUM/1440
 */

header('Content-Type: application/json; charset=utf-8');

ob_start();

function retornarJSON($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Erro fatal: ' . $error['message']]);
    }
});

try {
    include_once '../conexao.php';
    
    // Parâmetros
    $cdPonto = isset($_GET['cdPonto']) ? (int)$_GET['cdPonto'] : 0;
    $dataInicio = isset($_GET['dataInicio']) ? $_GET['dataInicio'] : date('Y-m-d');
    $dataFim = isset($_GET['dataFim']) ? $_GET['dataFim'] : date('Y-m-d');
    
    if (!$cdPonto) {
        retornarJSON(['success' => false, 'error' => 'Ponto não informado']);
    }
    
    if (!isset($pdoSIMP)) {
        retornarJSON(['success' => false, 'error' => 'Conexão não estabelecida']);
    }
    
    // Buscar datas com dados agrupados por dia
    // ALTERADO: Usando AVG em vez de SUM/1440 para média diária
    $sql = "SELECT 
                CAST(DT_LEITURA AS DATE) as DATA,
                COUNT(*) as TOTAL_REGISTROS,
                AVG(VL_VAZAO_EFETIVA) as MEDIA_VAZAO,
                MIN(VL_VAZAO_EFETIVA) as MIN_VAZAO,
                MAX(VL_VAZAO_EFETIVA) as MAX_VAZAO,
                AVG(VL_PRESSAO) as MEDIA_PRESSAO,
                SUM(CASE WHEN NR_EXTRAVASOU = 1 THEN 1 ELSE 0 END) as MINUTOS_EXTRAVASOU
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
            WHERE CD_PONTO_MEDICAO = :cdPonto
              AND ID_SITUACAO = 1
              AND CAST(DT_LEITURA AS DATE) >= :dataInicio
              AND CAST(DT_LEITURA AS DATE) <= :dataFim
            GROUP BY CAST(DT_LEITURA AS DATE)
            ORDER BY DATA DESC";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':cdPonto' => $cdPonto,
        ':dataInicio' => $dataInicio,
        ':dataFim' => $dataFim
    ]);
    
    $datas = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $totalRegistros = intval($row['TOTAL_REGISTROS']);
        $percentualCompleto = round(($totalRegistros / 1440) * 100, 1);
        
        // Determinar status
        $status = 'completo';
        if ($percentualCompleto < 50) {
            $status = 'critico';
        } elseif ($percentualCompleto < 90) {
            $status = 'incompleto';
        }
        
        // Verificar se há anomalias
        $temAnomalia = false;
        $anomalias = [];
        
        // Vazão zerada
        if (floatval($row['MEDIA_VAZAO']) == 0 && $totalRegistros > 100) {
            $temAnomalia = true;
            $anomalias[] = 'Vazão zerada';
        }
        
        // Variação alta (máx > 2x média)
        if (floatval($row['MEDIA_VAZAO']) > 0 && floatval($row['MAX_VAZAO']) > floatval($row['MEDIA_VAZAO']) * 2) {
            $temAnomalia = true;
            $anomalias[] = 'Alta variação';
        }
        
        // Extravasamento
        if (intval($row['MINUTOS_EXTRAVASOU']) > 0) {
            $temAnomalia = true;
            $anomalias[] = 'Extravasamento';
        }
        
        $datas[] = [
            'data' => $row['DATA'],
            'dataFormatada' => date('d/m/Y', strtotime($row['DATA'])),
            'diaSemana' => strftime('%A', strtotime($row['DATA'])),
            'totalRegistros' => $totalRegistros,
            'percentualCompleto' => $percentualCompleto,
            'status' => $status,
            'mediaVazao' => round(floatval($row['MEDIA_VAZAO']), 2),
            'minVazao' => round(floatval($row['MIN_VAZAO']), 2),
            'maxVazao' => round(floatval($row['MAX_VAZAO']), 2),
            'mediaPressao' => round(floatval($row['MEDIA_PRESSAO']), 2),
            'minutosExtravasou' => intval($row['MINUTOS_EXTRAVASOU']),
            'temAnomalia' => $temAnomalia,
            'anomalias' => $anomalias
        ];
    }
    
    retornarJSON([
        'success' => true,
        'datas' => $datas,
        'totalDias' => count($datas),
        'cdPonto' => $cdPonto,
        'formula_media' => 'AVG'
    ]);
    
} catch (Exception $e) {
    retornarJSON([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
