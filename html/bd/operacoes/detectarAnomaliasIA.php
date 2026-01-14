<?php
/**
 * SIMP - Detectar Anomalias por Hora
 * Analisa dados hora a hora e detecta anomalias baseadas em regras de negócio
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
    $data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
    
    if (!$cdPonto) {
        retornarJSON(['success' => false, 'error' => 'Ponto não informado']);
    }
    
    if (!isset($pdoSIMP)) {
        retornarJSON(['success' => false, 'error' => 'Conexão não estabelecida']);
    }
    
    // Buscar informações do ponto
    $sqlPonto = "SELECT 
                    PM.CD_PONTO_MEDICAO,
                    PM.DS_NOME,
                    PM.ID_TIPO_MEDIDOR
                FROM SIMP.dbo.PONTO_MEDICAO PM
                WHERE PM.CD_PONTO_MEDICAO = :cdPonto";
    
    $stmtPonto = $pdoSIMP->prepare($sqlPonto);
    $stmtPonto->execute([':cdPonto' => $cdPonto]);
    $infoPonto = $stmtPonto->fetch(PDO::FETCH_ASSOC);
    
    if (!$infoPonto) {
        retornarJSON(['success' => false, 'error' => 'Ponto não encontrado']);
    }
    
    $tipoMedidor = intval($infoPonto['ID_TIPO_MEDIDOR']);
    
    // Buscar dados horários do dia
    $sqlDados = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) as HORA,
                    COUNT(*) as QTD_REGISTROS,
                    SUM(VL_VAZAO_EFETIVA) / 60.0 as MEDIA_VAZAO,
                    MIN(VL_VAZAO_EFETIVA) as MIN_VAZAO,
                    MAX(VL_VAZAO_EFETIVA) as MAX_VAZAO,
                    SUM(VL_PRESSAO) / 60.0 as MEDIA_PRESSAO,
                    MIN(VL_PRESSAO) as MIN_PRESSAO,
                    MAX(VL_PRESSAO) as MAX_PRESSAO,
                    AVG(VL_RESERVATORIO) as MEDIA_NIVEL,
                    MIN(VL_RESERVATORIO) as MIN_NIVEL,
                    MAX(VL_RESERVATORIO) as MAX_NIVEL,
                    SUM(CASE WHEN NR_EXTRAVASOU = 1 THEN 1 ELSE 0 END) as MINUTOS_EXTRAVASOU,
                    SUM(CASE WHEN VL_VAZAO_EFETIVA = 0 THEN 1 ELSE 0 END) as MINUTOS_VAZAO_ZERO,
                    SUM(CASE WHEN VL_PRESSAO = 0 THEN 1 ELSE 0 END) as MINUTOS_PRESSAO_ZERO,
                    SUM(CASE WHEN VL_RESERVATORIO >= 100 THEN 1 ELSE 0 END) as MINUTOS_NIVEL_100,
                    SUM(CASE WHEN VL_RESERVATORIO = 0 OR VL_RESERVATORIO IS NULL THEN 1 ELSE 0 END) as MINUTOS_NIVEL_ZERO
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND ID_SITUACAO = 1
                  AND CAST(DT_LEITURA AS DATE) = :data
                GROUP BY DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
    
    $stmtDados = $pdoSIMP->prepare($sqlDados);
    $stmtDados->execute([':cdPonto' => $cdPonto, ':data' => $data]);
    
    // Armazenar dados por hora
    $dadosPorHora = [];
    for ($h = 0; $h < 24; $h++) {
        $dadosPorHora[$h] = null; // null indica hora vazia
    }
    while ($row = $stmtDados->fetch(PDO::FETCH_ASSOC)) {
        $dadosPorHora[intval($row['HORA'])] = $row;
    }
    
    // Buscar média histórica para comparação (últimas 4 semanas mesmo dia da semana)
    $diaSemana = date('w', strtotime($data));
    $sqlMediaHistorica = "SELECT 
                            DATEPART(HOUR, DT_LEITURA) as HORA,
                            AVG(VL_VAZAO_EFETIVA) as MEDIA_VAZAO_HIST,
                            AVG(VL_PRESSAO) as MEDIA_PRESSAO_HIST
                        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                        WHERE CD_PONTO_MEDICAO = :cdPonto
                          AND ID_SITUACAO = 1
                          AND DATEPART(WEEKDAY, DT_LEITURA) = :diaSemana
                          AND CAST(DT_LEITURA AS DATE) < :data
                          AND CAST(DT_LEITURA AS DATE) >= DATEADD(WEEK, -4, :data2)
                        GROUP BY DATEPART(HOUR, DT_LEITURA)";
    
    $stmtHist = $pdoSIMP->prepare($sqlMediaHistorica);
    $stmtHist->execute([
        ':cdPonto' => $cdPonto,
        ':diaSemana' => $diaSemana + 1,
        ':data' => $data,
        ':data2' => $data
    ]);
    
    $mediaHistoricaPorHora = [];
    while ($rowHist = $stmtHist->fetch(PDO::FETCH_ASSOC)) {
        $mediaHistoricaPorHora[intval($rowHist['HORA'])] = [
            'vazao' => floatval($rowHist['MEDIA_VAZAO_HIST']),
            'pressao' => floatval($rowHist['MEDIA_PRESSAO_HIST'])
        ];
    }
    
    // Analisar TODAS as 24 horas
    $anomaliasPorHora = [];
    $totalAnomalias = 0;
    $horasAnalisadas = 24; // Sempre 24 horas
    
    // Variáveis para detectar sequências
    $horasVazaoZeroConsecutivas = 0;
    $horasPressaoZeroConsecutivas = 0;
    $horasNivel100Consecutivas = 0;
    $horasNivelZeroConsecutivas = 0;
    
    for ($hora = 0; $hora < 24; $hora++) {
        $row = $dadosPorHora[$hora];
        $horaFormatada = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00';
        $anomalias = [];
        
        // ===== HORA VAZIA (sem nenhum dado) =====
        if ($row === null) {
            // Hora vazia é CRÍTICO - diferente de valor zero!
            $anomalias[] = [
                'tipo' => 'critico',
                'icone' => 'remove-circle',
                'mensagem' => "Hora SEM DADOS - nenhum registro recebido",
                'sugestao' => 'Verificar comunicação do equipamento'
            ];
            
            // Adicionar ao resultado
            if (!empty($anomalias)) {
                $anomaliasPorHora[] = [
                    'hora' => $hora,
                    'horaFormatada' => $horaFormatada,
                    'anomalias' => $anomalias,
                    'totalAnomalias' => count($anomalias),
                    'vazia' => true,
                    'dados' => [
                        'registros' => 0,
                        'mediaVazao' => null,
                        'mediaPressao' => null,
                        'mediaNivel' => null
                    ]
                ];
                $totalAnomalias += count($anomalias);
            }
            continue; // Pular para próxima hora
        }
        
        // Hora com dados - processar normalmente
        $qtdRegistros = intval($row['QTD_REGISTROS']);
        $mediaVazao = floatval($row['MEDIA_VAZAO']);
        $minVazao = floatval($row['MIN_VAZAO']);
        $maxVazao = floatval($row['MAX_VAZAO']);
        $mediaPressao = floatval($row['MEDIA_PRESSAO']);
        $minPressao = floatval($row['MIN_PRESSAO']);
        $maxPressao = floatval($row['MAX_PRESSAO']);
        $mediaNivel = floatval($row['MEDIA_NIVEL']);
        $minNivel = floatval($row['MIN_NIVEL']);
        $maxNivel = floatval($row['MAX_NIVEL']);
        $minutosExtravasou = intval($row['MINUTOS_EXTRAVASOU']);
        $minutosVazaoZero = intval($row['MINUTOS_VAZAO_ZERO']);
        $minutosPressaoZero = intval($row['MINUTOS_PRESSAO_ZERO']);
        $minutosNivel100 = intval($row['MINUTOS_NIVEL_100']);
        $minutosNivelZero = intval($row['MINUTOS_NIVEL_ZERO']);
        
        // Obter média histórica para esta hora
        $histVazao = $mediaHistoricaPorHora[$hora]['vazao'] ?? null;
        $histPressao = $mediaHistoricaPorHora[$hora]['pressao'] ?? null;
        
        // ===== REGRAS PARA VAZÃO (tipos 1, 2, 8) =====
        if (in_array($tipoMedidor, [1, 2, 8])) {
            
            // Vazão zerada por mais de 30 minutos em horário comercial (6h-22h)
            if ($minutosVazaoZero >= 30 && $hora >= 6 && $hora <= 22) {
                $anomalias[] = [
                    'tipo' => 'critico',
                    'icone' => 'alert-circle',
                    'mensagem' => "Vazão zerada por {$minutosVazaoZero} minutos em horário comercial",
                    'sugestao' => 'Verificar falha no sensor ou falta de água'
                ];
            }
            
            // Vazão negativa
            if ($minVazao < 0) {
                $anomalias[] = [
                    'tipo' => 'erro',
                    'icone' => 'close-circle',
                    'mensagem' => "Vazão negativa detectada: {$minVazao} L/s",
                    'sugestao' => 'Erro de medição - verificar sensor'
                ];
            }
            
            // Variação muito alta (máx > 2x média)
            if ($mediaVazao > 0 && $maxVazao > $mediaVazao * 2) {
                $variacao = round((($maxVazao - $mediaVazao) / $mediaVazao) * 100, 1);
                $anomalias[] = [
                    'tipo' => 'alerta',
                    'icone' => 'warning',
                    'mensagem' => "Pico de vazão {$variacao}% acima da média ({$maxVazao} L/s)",
                    'sugestao' => 'Possível erro de leitura ou vazamento'
                ];
            }
            
            // Comparação com histórico (variação > 50%)
            if ($histVazao && $histVazao > 0) {
                $variacaoHist = (($mediaVazao - $histVazao) / $histVazao) * 100;
                if (abs($variacaoHist) > 50) {
                    $direcao = $variacaoHist > 0 ? 'acima' : 'abaixo';
                    $anomalias[] = [
                        'tipo' => 'info',
                        'icone' => 'trending-' . ($variacaoHist > 0 ? 'up' : 'down'),
                        'mensagem' => sprintf("Vazão %.1f%% %s do histórico (atual: %.2f, histórico: %.2f)", abs($variacaoHist), $direcao, $mediaVazao, $histVazao),
                        'sugestao' => 'Verificar se há motivo para variação'
                    ];
                }
            }
        }
        
        // ===== REGRAS PARA PRESSÃO (tipo 4) =====
        if ($tipoMedidor == 4) {
            
            // Pressão abaixo de 10 mca
            if ($mediaPressao > 0 && $mediaPressao < 10) {
                $anomalias[] = [
                    'tipo' => 'alerta',
                    'icone' => 'arrow-down-circle',
                    'mensagem' => "Pressão baixa: {$mediaPressao} mca",
                    'sugestao' => 'Pode indicar problema na rede'
                ];
            }
            
            // Pressão zerada por mais de 30 minutos
            if ($minutosPressaoZero >= 30) {
                $anomalias[] = [
                    'tipo' => 'critico',
                    'icone' => 'alert-circle',
                    'mensagem' => "Pressão zerada por {$minutosPressaoZero} minutos",
                    'sugestao' => 'Falha no sensor ou falta de água'
                ];
            }
            
            // Pressão acima de 60 mca
            if ($maxPressao > 60) {
                $anomalias[] = [
                    'tipo' => 'alerta',
                    'icone' => 'arrow-up-circle',
                    'mensagem' => "Pressão alta: {$maxPressao} mca",
                    'sugestao' => 'Verificar se há problema ou erro de leitura'
                ];
            }
            
            // Variação brusca de pressão (máx - mín > 20 mca)
            if (($maxPressao - $minPressao) > 20) {
                $anomalias[] = [
                    'tipo' => 'info',
                    'icone' => 'swap-vertical',
                    'mensagem' => sprintf("Variação de pressão: %.1f mca (mín: %.1f, máx: %.1f)", $maxPressao - $minPressao, $minPressao, $maxPressao),
                    'sugestao' => 'Possível manobra na rede ou vazamento'
                ];
            }
        }
        
        // ===== REGRAS PARA NÍVEL RESERVATÓRIO (tipo 6) =====
        if ($tipoMedidor == 6) {
            
            // Nível >= 100% por mais de 30 minutos
            if ($minutosNivel100 >= 30) {
                $anomalias[] = [
                    'tipo' => 'critico',
                    'icone' => 'water',
                    'mensagem' => "Nível ≥100% por {$minutosNivel100} minutos",
                    'sugestao' => 'Registrar extravasamento com motivo "Extravasou"'
                ];
            }
            
            // Extravasamento detectado
            if ($minutosExtravasou > 0) {
                $anomalias[] = [
                    'tipo' => 'erro',
                    'icone' => 'alert-circle',
                    'mensagem' => "Extravasamento: {$minutosExtravasou} minutos",
                    'sugestao' => 'Verificar registro e causa do extravasamento'
                ];
            }
            
            // Nível zerado ou sem leitura por mais de 30 minutos
            if ($minutosNivelZero >= 30) {
                $anomalias[] = [
                    'tipo' => 'critico',
                    'icone' => 'alert-circle',
                    'mensagem' => "Nível zerado/sem leitura por {$minutosNivelZero} minutos",
                    'sugestao' => 'Verificar falha no sensor - motivo "Falha"'
                ];
            }
            
            // Variação brusca de nível (> 30% na hora)
            if ($maxNivel > 0 && ($maxNivel - $minNivel) > 30) {
                $anomalias[] = [
                    'tipo' => 'alerta',
                    'icone' => 'trending-up',
                    'mensagem' => sprintf("Variação de nível: %.1f%% (mín: %.1f%%, máx: %.1f%%)", $maxNivel - $minNivel, $minNivel, $maxNivel),
                    'sugestao' => 'Pode indicar erro de leitura'
                ];
            }
            
            // Nível entre 95% e 99% (risco de extravasamento)
            if ($mediaNivel >= 95 && $mediaNivel < 100) {
                $anomalias[] = [
                    'tipo' => 'alerta',
                    'icone' => 'warning',
                    'mensagem' => sprintf("Nível alto: %.1f%% - risco de extravasamento", $mediaNivel),
                    'sugestao' => 'Monitorar - próximo do limite'
                ];
            }
        }
        
        // ===== REGRAS GERAIS =====
        
        // Hora incompleta (< 50 registros)
        if ($qtdRegistros < 50) {
            $percentual = round(($qtdRegistros / 60) * 100, 1);
            $anomalias[] = [
                'tipo' => 'info',
                'icone' => 'time',
                'mensagem' => "Hora incompleta: {$qtdRegistros}/60 registros ({$percentual}%)",
                'sugestao' => 'Dados parciais - verificar comunicação'
            ];
        }
        
        // Adicionar ao resultado se houver anomalias
        if (!empty($anomalias)) {
            $anomaliasPorHora[] = [
                'hora' => $hora,
                'horaFormatada' => $horaFormatada,
                'anomalias' => $anomalias,
                'totalAnomalias' => count($anomalias),
                'dados' => [
                    'registros' => $qtdRegistros,
                    'mediaVazao' => round($mediaVazao, 2),
                    'mediaPressao' => round($mediaPressao, 2),
                    'mediaNivel' => round($mediaNivel, 2)
                ]
            ];
            $totalAnomalias += count($anomalias);
        }
    }
    
    // Tipos de medidor para descrição
    $tiposMedidor = [
        1 => 'Macromedidor',
        2 => 'Estação Pitométrica',
        4 => 'Medidor de Pressão',
        6 => 'Nível Reservatório',
        8 => 'Hidrômetro'
    ];
    
    retornarJSON([
        'success' => true,
        'ponto' => [
            'cd' => $cdPonto,
            'nome' => $infoPonto['DS_NOME'],
            'tipoMedidor' => $tipoMedidor,
            'tipoMedidorNome' => $tiposMedidor[$tipoMedidor] ?? 'Desconhecido'
        ],
        'data' => $data,
        'dataFormatada' => date('d/m/Y', strtotime($data)),
        'resumo' => [
            'horasAnalisadas' => $horasAnalisadas,
            'horasComAnomalias' => count($anomaliasPorHora),
            'totalAnomalias' => $totalAnomalias
        ],
        'anomaliasPorHora' => $anomaliasPorHora
    ]);
    
} catch (Exception $e) {
    retornarJSON([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}