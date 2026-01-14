<?php
/**
 * SIMP - Consulta de dados para análise com IA
 * 
 * Fornece dados do banco de dados para análise inteligente
 */

// Iniciar buffer de saída para capturar qualquer output indesejado
ob_start();

// Desabilitar exibição de erros HTML
error_reporting(0);
ini_set('display_errors', 0);

// Função para retornar JSON limpo
function retornarJSON($data) {
    // Limpar qualquer output anterior
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Erro PHP: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    include_once '../conexao.php';
    
    // Aceitar tanto GET quanto POST
    $cdPonto = 0;
    $data = date('Y-m-d');
    
    // Primeiro tentar GET
    if (isset($_GET['cdPonto'])) {
        $cdPonto = (int)$_GET['cdPonto'];
        $data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
    } 
    // Se não tiver GET, tentar POST (JSON)
    else {
        $rawInput = file_get_contents('php://input');
        
        if (!empty($rawInput)) {
            $input = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                retornarJSON([
                    'success' => false, 
                    'error' => 'JSON inválido: ' . json_last_error_msg()
                ]);
            }
            
            $cdPonto = isset($input['cdPonto']) ? (int)$input['cdPonto'] : 0;
            $data = isset($input['data']) ? $input['data'] : date('Y-m-d');
        }
    }
    
    if (!$cdPonto) {
        retornarJSON([
            'success' => false, 
            'error' => 'Ponto não informado'
        ]);
    }
    
    if (!isset($pdoSIMP)) {
        retornarJSON(['success' => false, 'error' => 'Conexão não estabelecida']);
    }
    
    $resultado = [];
    
    // Informações do ponto
    $resultado['ponto'] = obterInfoPonto($pdoSIMP, $cdPonto);
    
    // Dados do dia atual (agrupado por hora)
    $resultado['dia_atual'] = obterDadosDia($pdoSIMP, $cdPonto, $data);
    
    // Dados históricos (últimos 7 dias)
    $resultado['historico_7dias'] = obterHistorico($pdoSIMP, $cdPonto, $data, 7);
    
    // Média do mesmo dia da semana (últimas 4 semanas)
    $resultado['media_mesmo_dia_semana'] = obterMediaMesmoDiaSemana($pdoSIMP, $cdPonto, $data, 4);
    
    // Estatísticas do mês
    $resultado['estatisticas_mes'] = obterEstatisticasMes($pdoSIMP, $cdPonto, $data);
    
    // Alertas e anomalias detectadas
    $resultado['alertas'] = detectarAnomalias($resultado);
    
    // ==========================================
    // CÁLCULOS PRÉ-PROCESSADOS PARA A IA
    // Média diária = soma de tudo ÷ 1440 (SEMPRE)
    // ==========================================
    $resultado['calculos'] = calcularMediasDia($pdoSIMP, $cdPonto, $data);
    
    // Histórico do mesmo dia da semana (últimas 12 semanas para análises flexíveis)
    $resultado['historico_mesmo_dia'] = calcularMediaSemanas($pdoSIMP, $cdPonto, $data, 12);
    
    // Histórico POR HORA das últimas 4 semanas (para sugestões específicas por hora)
    $resultado['historico_por_hora'] = calcularMediaPorHoraSemanas($pdoSIMP, $cdPonto, $data, 12);
    
    retornarJSON([
        'success' => true,
        'dados' => $resultado
    ]);
    
} catch (Exception $e) {
    retornarJSON([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Obtém informações completas do ponto de medição
 */
function obterInfoPonto($pdo, $cdPonto) {
    try {
        $sql = "SELECT 
                    PM.CD_PONTO_MEDICAO,
                    PM.DS_NOME,
                    PM.DS_LOCALIZACAO,
                    PM.ID_TIPO_MEDIDOR,
                    PM.ID_TIPO_LEITURA,
                    PM.OP_PERIODICIDADE_LEITURA,
                    PM.DT_ATIVACAO,
                    PM.DT_DESATIVACAO,
                    PM.CD_USUARIO_RESPONSAVEL,
                    PM.DS_OBSERVACAO,
                    PM.VL_QUANTIDADE_LIGACOES,
                    PM.VL_QUANTIDADE_ECONOMIAS,
                    PM.DS_TAG_VAZAO,
                    PM.DS_TAG_PRESSAO,
                    PM.DS_TAG_TEMP_AGUA,
                    PM.DS_TAG_TEMP_AMBIENTE,
                    PM.VL_FATOR_CORRECAO_VAZAO,
                    PM.DS_TAG_VOLUME,
                    PM.VL_LIMITE_INFERIOR_VAZAO,
                    PM.VL_LIMITE_SUPERIOR_VAZAO,
                    PM.CD_ESTACAO_PITOMETRICA,
                    PM.DS_TAG_RESERVATORIO,
                    PM.TIPO_INSTALACAO,
                    PM.MOTIVO_TIPO_INSTALACAO,
                    L.DS_NOME as UNIDADE_OPERACIONAL,
                    L.CD_LOCALIDADE as CD_LOCALIDADE,
                    U.DS_NOME as RESPONSAVEL_NOME
                FROM SIMP.dbo.PONTO_MEDICAO PM
                LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                LEFT JOIN SIMP.dbo.TIPO_MEDIDOR TM ON PM.ID_TIPO_MEDIDOR = TM.CD_CHAVE
                LEFT JOIN SIMP.dbo.USUARIO U ON PM.CD_USUARIO_RESPONSAVEL = U.CD_USUARIO
                WHERE PM.CD_PONTO_MEDICAO = :cdPonto";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cdPonto' => $cdPonto]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Formatar datas
            if ($row['DT_ATIVACAO']) {
                $row['DT_ATIVACAO_FORMATADA'] = date('d/m/Y', strtotime($row['DT_ATIVACAO']));
            }
            if ($row['DT_DESATIVACAO']) {
                $row['DT_DESATIVACAO_FORMATADA'] = date('d/m/Y', strtotime($row['DT_DESATIVACAO']));
            }
            
            // Descrever tipo de instalação
            $tiposInstalacao = [
                1 => 'Permanente',
                2 => 'Temporária',
                3 => 'Móvel'
            ];
            $row['TIPO_INSTALACAO_DESCRICAO'] = $tiposInstalacao[$row['TIPO_INSTALACAO']] ?? 'Não definido';
        }
        
        return $row ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Obtém dados detalhados de um dia específico (agrupado por hora)
 * MÉDIA HORÁRIA = SUM / 60 (sempre dividir por 60)
 */
function obterDadosDia($pdo, $cdPonto, $data) {
    try {
        $sql = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) as HORA,
                    COUNT(*) as QTD_REGISTROS,
                    SUM(VL_VAZAO_EFETIVA) as SOMA_VAZAO,
                    SUM(VL_VAZAO_EFETIVA) / 60.0 as MEDIA_VAZAO,
                    MIN(VL_VAZAO_EFETIVA) as MIN_VAZAO,
                    MAX(VL_VAZAO_EFETIVA) as MAX_VAZAO,
                    SUM(VL_PRESSAO) as SOMA_PRESSAO,
                    SUM(VL_PRESSAO) / 60.0 as MEDIA_PRESSAO,
                    MIN(VL_PRESSAO) as MIN_PRESSAO,
                    MAX(VL_PRESSAO) as MAX_PRESSAO,
                    MAX(VL_RESERVATORIO) as MAX_NIVEL,
                    MIN(VL_RESERVATORIO) as MIN_NIVEL,
                    SUM(CASE WHEN NR_EXTRAVASOU = 1 THEN 1 ELSE 0 END) as MINUTOS_EXTRAVASOU,
                    SUM(CASE WHEN ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 ELSE 0 END) as QTD_TRATADOS
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND CAST(DT_LEITURA AS DATE) = :data
                  AND ID_SITUACAO = 1
                GROUP BY DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cdPonto' => $cdPonto, ':data' => $data]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Obtém histórico dos últimos N dias
 * MÉDIA DIÁRIA = SUM / 1440 (sempre dividir por 1440)
 */
function obterHistorico($pdo, $cdPonto, $dataBase, $dias) {
    try {
        $dataInicio = date('Y-m-d', strtotime("-{$dias} days", strtotime($dataBase)));
        
        $sql = "SELECT 
                    CAST(DT_LEITURA AS DATE) as DATA,
                    COUNT(*) as QTD_REGISTROS,
                    SUM(VL_VAZAO_EFETIVA) as SOMA_VAZAO,
                    SUM(VL_VAZAO_EFETIVA) / 1440.0 as MEDIA_VAZAO,
                    MIN(VL_VAZAO_EFETIVA) as MIN_VAZAO,
                    MAX(VL_VAZAO_EFETIVA) as MAX_VAZAO,
                    SUM(VL_PRESSAO) as SOMA_PRESSAO,
                    SUM(VL_PRESSAO) / 1440.0 as MEDIA_PRESSAO,
                    MIN(VL_PRESSAO) as MIN_PRESSAO,
                    MAX(VL_PRESSAO) as MAX_PRESSAO,
                    MAX(VL_RESERVATORIO) as MAX_NIVEL,
                    SUM(CASE WHEN NR_EXTRAVASOU = 1 THEN 1 ELSE 0 END) as TOTAL_EXTRAVASOU
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND CAST(DT_LEITURA AS DATE) BETWEEN :dataInicio AND :dataFim
                  AND ID_SITUACAO = 1
                GROUP BY CAST(DT_LEITURA AS DATE)
                ORDER BY DATA DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cdPonto' => $cdPonto, 
            ':dataInicio' => $dataInicio,
            ':dataFim' => $dataBase
        ]);
        
        $dados = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['DATA']) && $row['DATA'] instanceof DateTime) {
                $row['DATA'] = $row['DATA']->format('Y-m-d');
            }
            $dados[] = $row;
        }
        
        return $dados;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Obtém média do mesmo dia da semana nas últimas N semanas
 */
function obterMediaMesmoDiaSemana($pdo, $cdPonto, $dataBase, $semanas) {
    try {
        $dataInicio = date('Y-m-d', strtotime("-{$semanas} weeks", strtotime($dataBase)));
        $dataOntem = date('Y-m-d', strtotime("-1 day", strtotime($dataBase)));
        $diaSemana = date('w', strtotime($dataBase)) + 1;
        
        $sql = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) as HORA,
                    SUM(VL_VAZAO_EFETIVA) / 60.0 as MEDIA_VAZAO,
                    SUM(VL_PRESSAO) / 60.0 as MEDIA_PRESSAO,
                    COUNT(DISTINCT CAST(DT_LEITURA AS DATE)) as QTD_DIAS
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND DATEPART(WEEKDAY, DT_LEITURA) = :diaSemana
                  AND CAST(DT_LEITURA AS DATE) BETWEEN :dataInicio AND :dataOntem
                  AND ID_SITUACAO = 1
                GROUP BY DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cdPonto' => $cdPonto,
            ':diaSemana' => $diaSemana,
            ':dataInicio' => $dataInicio,
            ':dataOntem' => $dataOntem
        ]);
        
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mediaGeralVazao = 0;
        $mediaGeralPressao = 0;
        $count = 0;
        
        foreach ($dados as $d) {
            if (isset($d['MEDIA_VAZAO']) && $d['MEDIA_VAZAO'] !== null) {
                $mediaGeralVazao += $d['MEDIA_VAZAO'];
                $count++;
            }
            if (isset($d['MEDIA_PRESSAO']) && $d['MEDIA_PRESSAO'] !== null) {
                $mediaGeralPressao += $d['MEDIA_PRESSAO'];
            }
        }
        
        return [
            'por_hora' => $dados,
            'media_geral_vazao' => $count > 0 ? $mediaGeralVazao / $count : null,
            'media_geral_pressao' => $count > 0 ? $mediaGeralPressao / $count : null,
            'semanas_analisadas' => $semanas
        ];
    } catch (Exception $e) {
        return [
            'por_hora' => [],
            'media_geral_vazao' => null,
            'media_geral_pressao' => null,
            'semanas_analisadas' => $semanas
        ];
    }
}

/**
 * Obtém estatísticas do mês
 * Média do mês = soma total do mês / (dias do mês * 1440)
 */
function obterEstatisticasMes($pdo, $cdPonto, $dataBase) {
    $primeiroDia = date('Y-m-01', strtotime($dataBase));
    $ultimoDia = date('Y-m-t', strtotime($dataBase));
    $diasNoMes = date('t', strtotime($dataBase));
    $divisorMes = $diasNoMes * 1440;
    
    $resultado = [
        'MES_REFERENCIA' => date('Y-m', strtotime($dataBase)),
        'PRIMEIRO_DIA' => $primeiroDia,
        'ULTIMO_DIA' => $ultimoDia,
        'DIAS_NO_MES' => $diasNoMes,
        'DIAS_COM_DADOS' => 0,
        'TOTAL_REGISTROS' => 0
    ];
    
    try {
        $sql = "SELECT 
                    COUNT(DISTINCT CAST(DT_LEITURA AS DATE)) as DIAS_COM_DADOS,
                    COUNT(*) as TOTAL_REGISTROS,
                    SUM(VL_VAZAO_EFETIVA) as SOMA_VAZAO_MES,
                    MIN(VL_VAZAO_EFETIVA) as MIN_VAZAO_MES,
                    MAX(VL_VAZAO_EFETIVA) as MAX_VAZAO_MES,
                    SUM(VL_PRESSAO) as SOMA_PRESSAO_MES,
                    MIN(VL_PRESSAO) as MIN_PRESSAO_MES,
                    MAX(VL_PRESSAO) as MAX_PRESSAO_MES,
                    SUM(CASE WHEN NR_EXTRAVASOU = 1 THEN 1 ELSE 0 END) as TOTAL_EXTRAVASOU_MES
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND CAST(DT_LEITURA AS DATE) BETWEEN :primeiroDia AND :ultimoDia
                  AND ID_SITUACAO = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cdPonto' => $cdPonto,
            ':primeiroDia' => $primeiroDia,
            ':ultimoDia' => $ultimoDia
        ]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $resultado = array_merge($resultado, $row);
            // Calcular média mensal (soma / dias do mês * 1440)
            $resultado['MEDIA_VAZAO_MES'] = floatval($row['SOMA_VAZAO_MES'] ?? 0) / $divisorMes;
            $resultado['MEDIA_PRESSAO_MES'] = floatval($row['SOMA_PRESSAO_MES'] ?? 0) / $divisorMes;
        }
    } catch (Exception $e) {
        // Retorna valores padrão em caso de erro
    }
    
    return $resultado;
}

/**
 * Detecta anomalias nos dados
 */
function detectarAnomalias($dados) {
    $alertas = [];
    
    if (!empty($dados['dia_atual'])) {
        $horasComDados = count($dados['dia_atual']);
        
        if ($horasComDados < 24) {
            $alertas[] = [
                'tipo' => 'dados_incompletos',
                'mensagem' => "Apenas {$horasComDados} horas com dados no dia (esperado: 24)",
                'severidade' => 'media'
            ];
        }
        
        foreach ($dados['dia_atual'] as $hora) {
            if (isset($hora['QTD_REGISTROS']) && $hora['QTD_REGISTROS'] < 30) {
                $alertas[] = [
                    'tipo' => 'registros_insuficientes',
                    'mensagem' => "Hora {$hora['HORA']}:00 com apenas {$hora['QTD_REGISTROS']} registros",
                    'severidade' => 'baixa'
                ];
            }
            
            if (isset($hora['MINUTOS_EXTRAVASOU']) && $hora['MINUTOS_EXTRAVASOU'] > 0) {
                $alertas[] = [
                    'tipo' => 'extravasamento',
                    'mensagem' => "Hora {$hora['HORA']}:00 com {$hora['MINUTOS_EXTRAVASOU']} minutos de extravasamento",
                    'severidade' => 'alta'
                ];
            }
        }
    }
    
    // Comparar com histórico
    if (!empty($dados['historico_7dias']) && !empty($dados['dia_atual'])) {
        $mediaHistorica = 0;
        $count = 0;
        
        foreach ($dados['historico_7dias'] as $dia) {
            if (isset($dia['MEDIA_VAZAO']) && $dia['MEDIA_VAZAO'] !== null) {
                $mediaHistorica += $dia['MEDIA_VAZAO'];
                $count++;
            }
        }
        
        if ($count > 0) {
            $mediaHistorica /= $count;
            
            $mediaDiaAtual = 0;
            $countAtual = 0;
            foreach ($dados['dia_atual'] as $hora) {
                if (isset($hora['MEDIA_VAZAO']) && $hora['MEDIA_VAZAO'] !== null) {
                    $mediaDiaAtual += $hora['MEDIA_VAZAO'];
                    $countAtual++;
                }
            }
            
            if ($countAtual > 0 && $mediaHistorica > 0) {
                $mediaDiaAtual /= $countAtual;
                $variacao = (($mediaDiaAtual - $mediaHistorica) / $mediaHistorica) * 100;
                
                if (abs($variacao) > 30) {
                    $direcao = $variacao > 0 ? 'acima' : 'abaixo';
                    $alertas[] = [
                        'tipo' => 'variacao_historico',
                        'mensagem' => sprintf("Vazão média %.1f%% %s da média histórica (%.2f vs %.2f)", 
                            abs($variacao), $direcao, $mediaDiaAtual, $mediaHistorica),
                        'severidade' => abs($variacao) > 50 ? 'alta' : 'media'
                    ];
                }
            }
        }
    }
    
    return $alertas;
}

/**
 * Calcula média diária DIRETO DO BANCO
 * REGRA: SEMPRE dividir por 1440 (total de minutos do dia)
 * 
 * Este é o valor OFICIAL que a IA deve usar
 */
function calcularMediasDia($pdo, $cdPonto, $data) {
    $resultado = [
        'soma_total_vazao' => 0,
        'soma_total_pressao' => 0,
        'total_registros' => 0,
        'media_diaria_vazao' => 0,
        'media_diaria_pressao' => 0,
        'horas_com_dados' => 0,
        'formula_usada' => 'SUM(valores) / 1440',
        'divisor_fixo' => 1440
    ];
    
    try {
        // Query direta para calcular média do dia inteiro
        $sql = "SELECT 
                    COUNT(*) as TOTAL_REGISTROS,
                    COUNT(DISTINCT DATEPART(HOUR, DT_LEITURA)) as HORAS_COM_DADOS,
                    SUM(VL_VAZAO_EFETIVA) as SOMA_VAZAO,
                    SUM(VL_VAZAO_EFETIVA) / 1440.0 as MEDIA_VAZAO,
                    SUM(VL_PRESSAO) as SOMA_PRESSAO,
                    SUM(VL_PRESSAO) / 1440.0 as MEDIA_PRESSAO
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND CAST(DT_LEITURA AS DATE) = :data
                  AND ID_SITUACAO = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cdPonto' => $cdPonto, ':data' => $data]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $resultado['soma_total_vazao'] = round(floatval($row['SOMA_VAZAO'] ?? 0), 2);
            $resultado['soma_total_pressao'] = round(floatval($row['SOMA_PRESSAO'] ?? 0), 2);
            $resultado['total_registros'] = intval($row['TOTAL_REGISTROS'] ?? 0);
            $resultado['media_diaria_vazao'] = round(floatval($row['MEDIA_VAZAO'] ?? 0), 2);
            $resultado['media_diaria_pressao'] = round(floatval($row['MEDIA_PRESSAO'] ?? 0), 2);
            $resultado['horas_com_dados'] = intval($row['HORAS_COM_DADOS'] ?? 0);
        }
    } catch (Exception $e) {
        // Retorna valores padrão
    }
    
    return $resultado;
}

/**
 * Calcula a média das últimas N semanas do MESMO DIA DA SEMANA
 * NÃO inclui o dia atual, apenas semanas anteriores
 */
function calcularMediaSemanas($pdo, $cdPonto, $dataBase, $numSemanas = 12) {
    $resultado = [
        'dia_semana' => '',
        'dia_semana_numero' => 0,
        'semanas_disponiveis' => 0,
        'datas_analisadas' => [],
        'medias_por_dia' => []
    ];
    
    try {
        // Descobrir o dia da semana
        $diaSemanaNum = date('w', strtotime($dataBase)); // 0=Dom, 1=Seg, ..., 6=Sab
        $diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $resultado['dia_semana'] = $diasSemana[$diaSemanaNum];
        $resultado['dia_semana_numero'] = $diaSemanaNum;
        
        // Calcular as datas (APENAS semanas anteriores, começando de i=1)
        $datas = [];
        for ($i = 1; $i <= $numSemanas; $i++) {
            $datas[] = date('Y-m-d', strtotime("-{$i} weeks", strtotime($dataBase)));
        }
        $resultado['datas_analisadas'] = $datas;
        
        // Buscar média de cada dia
        foreach ($datas as $data) {
            $sql = "SELECT 
                        CAST(DT_LEITURA AS DATE) as DATA,
                        COUNT(*) as TOTAL_REGISTROS,
                        SUM(VL_VAZAO_EFETIVA) as SOMA_VAZAO,
                        SUM(VL_VAZAO_EFETIVA) / 1440.0 as MEDIA_VAZAO,
                        SUM(VL_PRESSAO) as SOMA_PRESSAO,
                        SUM(VL_PRESSAO) / 1440.0 as MEDIA_PRESSAO
                    FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                    WHERE CD_PONTO_MEDICAO = :cdPonto
                      AND CAST(DT_LEITURA AS DATE) = :data
                      AND ID_SITUACAO = 1
                    GROUP BY CAST(DT_LEITURA AS DATE)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':cdPonto' => $cdPonto, ':data' => $data]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $mediaDia = [
                'data' => $data,
                'data_formatada' => date('d/m/Y', strtotime($data)),
                'total_registros' => 0,
                'soma_vazao' => 0,
                'media_vazao' => 0,
                'soma_pressao' => 0,
                'media_pressao' => 0,
                'tem_dados' => false
            ];
            
            if ($row && intval($row['TOTAL_REGISTROS'] ?? 0) > 0) {
                $mediaDia['total_registros'] = intval($row['TOTAL_REGISTROS']);
                $mediaDia['soma_vazao'] = round(floatval($row['SOMA_VAZAO'] ?? 0), 2);
                $mediaDia['media_vazao'] = round(floatval($row['MEDIA_VAZAO'] ?? 0), 2);
                $mediaDia['soma_pressao'] = round(floatval($row['SOMA_PRESSAO'] ?? 0), 2);
                $mediaDia['media_pressao'] = round(floatval($row['MEDIA_PRESSAO'] ?? 0), 2);
                $mediaDia['tem_dados'] = true;
                $resultado['semanas_disponiveis']++;
            }
            
            $resultado['medias_por_dia'][] = $mediaDia;
        }
        
    } catch (Exception $e) {
        // Retorna valores padrão
    }
    
    return $resultado;
}

/**
 * Calcula a média POR HORA das últimas N semanas do mesmo dia da semana
 * INCLUI fator de tendência do dia atual para ajuste
 */
function calcularMediaPorHoraSemanas($pdo, $cdPonto, $dataBase, $numSemanas = 12) {
    $resultado = [
        'dia_semana' => '',
        'semanas_analisadas' => $numSemanas,
        'fator_tendencia' => 1.0,
        'tendencia_percentual' => 0,
        'horas_usadas_tendencia' => 0,
        'horas' => []
    ];
    
    try {
        $diaSemanaNum = date('w', strtotime($dataBase));
        $diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $resultado['dia_semana'] = $diasSemana[$diaSemanaNum];
        
        // Calcular as datas das últimas N semanas (excluindo o dia atual)
        $datasHistorico = [];
        for ($i = 1; $i <= $numSemanas; $i++) {
            $datasHistorico[] = date('Y-m-d', strtotime("-{$i} weeks", strtotime($dataBase)));
        }
        
        // =====================================================
        // PASSO 1: Buscar dados do dia atual (para calcular tendência)
        // Média = SOMA / 60 (regra do SIMP)
        // =====================================================
        $dadosDiaAtual = [];
        $sqlDiaAtual = "SELECT 
                            DATEPART(HOUR, DT_LEITURA) as HORA,
                            COUNT(*) as QTD_REGISTROS,
                            SUM(VL_VAZAO_EFETIVA) / 60.0 as MEDIA_VAZAO
                        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                        WHERE CD_PONTO_MEDICAO = :cdPonto
                          AND CAST(DT_LEITURA AS DATE) = :data
                          AND ID_SITUACAO = 1
                        GROUP BY DATEPART(HOUR, DT_LEITURA)";
        
        $stmtAtual = $pdo->prepare($sqlDiaAtual);
        $stmtAtual->execute([':cdPonto' => $cdPonto, ':data' => $dataBase]);
        while ($row = $stmtAtual->fetch(PDO::FETCH_ASSOC)) {
            $hora = intval($row['HORA']);
            // Considerar apenas horas com >= 50 registros (hora quase completa)
            if (intval($row['QTD_REGISTROS']) >= 50) {
                $dadosDiaAtual[$hora] = floatval($row['MEDIA_VAZAO']);
            }
        }
        
        // =====================================================
        // PASSO 2: Buscar histórico por hora
        // Média = SOMA / 60 (regra do SIMP)
        // FILTRO: Só usa semanas com >= 50 registros (hora quase completa)
        // =====================================================
        $historicosPorHora = [];
        
        for ($hora = 0; $hora < 24; $hora++) {
            $dadosHora = [
                'hora' => $hora,
                'hora_formatada' => str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00',
                'valores_por_semana' => [],
                'media_historica' => 0,
                'valor_dia_atual' => isset($dadosDiaAtual[$hora]) ? $dadosDiaAtual[$hora] : null,
                'valor_sugerido' => 0,
                'semanas_com_dados' => 0
            ];
            
            $somaMedias = 0;
            $semanasComDados = 0;
            
            foreach ($datasHistorico as $idx => $data) {
                // Usar SOMA/60 conforme regra do SIMP
                $sql = "SELECT 
                            COUNT(*) as QTD_REGISTROS,
                            SUM(VL_VAZAO_EFETIVA) / 60.0 as MEDIA_VAZAO
                        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                        WHERE CD_PONTO_MEDICAO = :cdPonto
                          AND CAST(DT_LEITURA AS DATE) = :data
                          AND DATEPART(HOUR, DT_LEITURA) = :hora
                          AND ID_SITUACAO = 1";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':cdPonto' => $cdPonto, ':data' => $data, ':hora' => $hora]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $valorSemana = [
                    'semana' => $idx + 1,
                    'data' => $data,
                    'data_formatada' => date('d/m/Y', strtotime($data)),
                    'media_vazao' => 0,
                    'registros' => 0,
                    'tem_dados' => false
                ];
                
                // FILTRO: Só considera semanas com >= 50 registros (83% da hora)
                // Isso evita que horas incompletas distorçam a média
                if ($row && intval($row['QTD_REGISTROS'] ?? 0) >= 50) {
                    $valorSemana['media_vazao'] = round(floatval($row['MEDIA_VAZAO'] ?? 0), 2);
                    $valorSemana['registros'] = intval($row['QTD_REGISTROS']);
                    $valorSemana['tem_dados'] = true;
                    
                    $somaMedias += $valorSemana['media_vazao'];
                    $semanasComDados++;
                }
                
                $dadosHora['valores_por_semana'][] = $valorSemana;
            }
            
            $dadosHora['semanas_com_dados'] = $semanasComDados;
            if ($semanasComDados > 0) {
                $dadosHora['media_historica'] = round($somaMedias / $semanasComDados, 2);
            }
            
            $historicosPorHora[$hora] = $dadosHora;
            $resultado['horas'][$hora] = $dadosHora;
        }
        
        // =====================================================
        // PASSO 3: Calcular fator de tendência do dia atual
        // Compara média do dia atual com média histórica
        // Só usa horas com >= 50 registros (completas)
        // =====================================================
        $somaAtual = 0;
        $somaHistorica = 0;
        $horasUsadas = 0;
        
        foreach ($dadosDiaAtual as $hora => $valorAtual) {
            $mediaHistorica = $historicosPorHora[$hora]['media_historica'] ?? 0;
            
            // Só usar horas que têm dados válidos tanto no dia atual quanto no histórico
            if ($valorAtual > 0 && $mediaHistorica > 0) {
                $somaAtual += $valorAtual;
                $somaHistorica += $mediaHistorica;
                $horasUsadas++;
            }
        }
        
        // Calcular fator (evitar divisão por zero)
        $fatorTendencia = 1.0;
        if ($somaHistorica > 0 && $horasUsadas >= 3) {
            $fatorTendencia = $somaAtual / $somaHistorica;
            // Limitar fator entre 0.5 e 2.0 para evitar valores extremos
            $fatorTendencia = max(0.5, min(2.0, $fatorTendencia));
        }
        
        $resultado['fator_tendencia'] = round($fatorTendencia, 4);
        $resultado['tendencia_percentual'] = round(($fatorTendencia - 1) * 100, 2);
        $resultado['horas_usadas_tendencia'] = $horasUsadas;
        
        // =====================================================
        // PASSO 4: Calcular valor sugerido para cada hora
        // Valor sugerido = média_histórica × fator_tendência
        // =====================================================
        for ($hora = 0; $hora < 24; $hora++) {
            $mediaHistorica = $resultado['horas'][$hora]['media_historica'];
            if ($mediaHistorica > 0) {
                $resultado['horas'][$hora]['valor_sugerido'] = round($mediaHistorica * $fatorTendencia, 2);
            }
        }
        
    } catch (Exception $e) {
        // Retorna valores padrão
    }
    
    return $resultado;
}