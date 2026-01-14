<?php
/**
 * SIMP - Análise IA de um dia específico
 * Busca dados do dia e gera análise usando IA
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
    $iaConfig = require '../config/ia_config.php';
    
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
                    PM.ID_TIPO_MEDIDOR,
                    L.DS_NOME as LOCALIDADE,
                    L.CD_LOCALIDADE,
                    L.CD_UNIDADE
                FROM SIMP.dbo.PONTO_MEDICAO PM
                LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                WHERE PM.CD_PONTO_MEDICAO = :cdPonto";
    
    $stmtPonto = $pdoSIMP->prepare($sqlPonto);
    $stmtPonto->execute([':cdPonto' => $cdPonto]);
    $infoPonto = $stmtPonto->fetch(PDO::FETCH_ASSOC);
    
    // Gerar código formatado do ponto
    $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
    $letraTipo = $letrasTipo[$infoPonto['ID_TIPO_MEDIDOR']] ?? 'X';
    $codigoPonto = ($infoPonto['CD_LOCALIDADE'] ?? '0') . '-' . 
                  str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-' . 
                  $letraTipo . '-' . 
                  ($infoPonto['CD_UNIDADE'] ?? '0');
    
    // Buscar dados horários do dia
    $sqlDados = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) as HORA,
                    COUNT(*) as QTD_REGISTROS,
                    SUM(VL_VAZAO_EFETIVA) / 60.0 as MEDIA_VAZAO,
                    MIN(VL_VAZAO_EFETIVA) as MIN_VAZAO,
                    MAX(VL_VAZAO_EFETIVA) as MAX_VAZAO,
                    SUM(VL_PRESSAO) / 60.0 as MEDIA_PRESSAO,
                    SUM(CASE WHEN NR_EXTRAVASOU = 1 THEN 1 ELSE 0 END) as MINUTOS_EXTRAVASOU
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND ID_SITUACAO = 1
                  AND CAST(DT_LEITURA AS DATE) = :data
                GROUP BY DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
    
    $stmtDados = $pdoSIMP->prepare($sqlDados);
    $stmtDados->execute([':cdPonto' => $cdPonto, ':data' => $data]);
    
    // Inicializar array com todas as 24 horas (vazias por padrão)
    $dadosPorHora = [];
    for ($h = 0; $h < 24; $h++) {
        $dadosPorHora[$h] = null; // null indica hora vazia (sem dados)
    }
    
    // Preencher com dados do banco
    while ($row = $stmtDados->fetch(PDO::FETCH_ASSOC)) {
        $hora = intval($row['HORA']);
        $dadosPorHora[$hora] = $row;
    }
    
    $dadosHorarios = [];
    $totalRegistros = 0;
    $somaVazao = 0;
    $minVazaoGlobal = null;
    $maxVazaoGlobal = null;
    $horasIncompletas = [];
    $horasZeradas = [];
    $horasVazias = [];
    
    // Processar todas as 24 horas
    for ($hora = 0; $hora < 24; $hora++) {
        $row = $dadosPorHora[$hora];
        $horaFormatada = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00';
        
        if ($row === null) {
            // Hora VAZIA - sem nenhum registro
            $horasVazias[] = $horaFormatada;
            $dadosHorarios[] = [
                'hora' => $hora,
                'horaFormatada' => $horaFormatada,
                'registros' => 0,
                'vazia' => true,
                'mediaVazao' => null,
                'minVazao' => null,
                'maxVazao' => null,
                'mediaPressao' => null,
                'extravasou' => 0
            ];
        } else {
            // Hora com dados
            $qtdRegistros = intval($row['QTD_REGISTROS']);
            $mediaVazao = floatval($row['MEDIA_VAZAO']);
            $minVazao = floatval($row['MIN_VAZAO']);
            $maxVazao = floatval($row['MAX_VAZAO']);
            
            $totalRegistros += $qtdRegistros;
            $somaVazao += $mediaVazao * 60; // Soma total para média diária
            
            if ($minVazaoGlobal === null || $minVazao < $minVazaoGlobal) {
                $minVazaoGlobal = $minVazao;
            }
            if ($maxVazaoGlobal === null || $maxVazao > $maxVazaoGlobal) {
                $maxVazaoGlobal = $maxVazao;
            }
            
            // Detectar horas incompletas (tem dados mas < 50 registros)
            if ($qtdRegistros < 50) {
                $horasIncompletas[] = $horaFormatada;
            }
            
            // Detectar horas zeradas (tem dados suficientes mas média = 0)
            if ($mediaVazao == 0 && $qtdRegistros >= 50) {
                $horasZeradas[] = $horaFormatada;
            }
            
            $dadosHorarios[] = [
                'hora' => $hora,
                'horaFormatada' => $horaFormatada,
                'registros' => $qtdRegistros,
                'vazia' => false,
                'mediaVazao' => round($mediaVazao, 2),
                'minVazao' => round($minVazao, 2),
                'maxVazao' => round($maxVazao, 2),
                'mediaPressao' => round(floatval($row['MEDIA_PRESSAO']), 2),
                'extravasou' => intval($row['MINUTOS_EXTRAVASOU'])
            ];
        }
    }
    
    // Calcular média diária (soma / 1440)
    $mediaDiaria = $totalRegistros > 0 ? round($somaVazao / 1440, 2) : 0;
    $percentualCompleto = round(($totalRegistros / 1440) * 100, 1);
    
    // Buscar histórico para comparação (últimas 4 semanas mesmo dia da semana)
    $diaSemana = date('w', strtotime($data));
    $sqlHistorico = "SELECT 
                        AVG(subq.MEDIA_DIA) as MEDIA_HISTORICA
                    FROM (
                        SELECT 
                            CAST(DT_LEITURA AS DATE) as DIA,
                            SUM(VL_VAZAO_EFETIVA) / 1440.0 as MEDIA_DIA
                        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                        WHERE CD_PONTO_MEDICAO = :cdPonto
                          AND ID_SITUACAO = 1
                          AND DATEPART(WEEKDAY, DT_LEITURA) = :diaSemana
                          AND CAST(DT_LEITURA AS DATE) < :data
                          AND CAST(DT_LEITURA AS DATE) >= DATEADD(WEEK, -4, :data2)
                        GROUP BY CAST(DT_LEITURA AS DATE)
                        HAVING COUNT(*) >= 1000
                    ) subq";
    
    $stmtHist = $pdoSIMP->prepare($sqlHistorico);
    $stmtHist->execute([
        ':cdPonto' => $cdPonto,
        ':diaSemana' => $diaSemana + 1, // SQL Server usa 1-7
        ':data' => $data,
        ':data2' => $data
    ]);
    $rowHist = $stmtHist->fetch(PDO::FETCH_ASSOC);
    $mediaHistorica = $rowHist && $rowHist['MEDIA_HISTORICA'] ? round(floatval($rowHist['MEDIA_HISTORICA']), 2) : null;
    
    // Calcular variação em relação ao histórico
    $variacaoHistorico = null;
    if ($mediaHistorica && $mediaHistorica > 0) {
        $variacaoHistorico = round((($mediaDiaria - $mediaHistorica) / $mediaHistorica) * 100, 1);
    }
    
    // Montar contexto para IA
    $tiposMedidor = [1 => 'Macromedidor', 2 => 'Estação Pitométrica', 4 => 'Medidor de Pressão', 6 => 'Nível Reservatório', 8 => 'Hidrômetro'];
    $tipoMedidorNome = $tiposMedidor[$infoPonto['ID_TIPO_MEDIDOR']] ?? 'Desconhecido';
    
    $dataFormatada = date('d/m/Y', strtotime($data));
    $diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $diaSemanaTexto = $diasSemana[$diaSemana];
    
    $contexto = "Analise os dados do ponto de medição e gere um resumo executivo em 3-5 linhas.\n\n";
    $contexto .= "PONTO: {$infoPonto['DS_NOME']} ({$tipoMedidorNome})\n";
    $contexto .= "LOCAL: {$infoPonto['LOCALIDADE']}\n";
    $contexto .= "DATA: {$dataFormatada} ({$diaSemanaTexto})\n\n";
    $contexto .= "RESUMO DO DIA:\n";
    $contexto .= "- Total de registros: {$totalRegistros} de 1440 ({$percentualCompleto}%)\n";
    $contexto .= "- Média diária de vazão: {$mediaDiaria} L/s\n";
    $contexto .= "- Vazão mínima: " . ($minVazaoGlobal !== null ? $minVazaoGlobal : '-') . " L/s\n";
    $contexto .= "- Vazão máxima: " . ($maxVazaoGlobal !== null ? $maxVazaoGlobal : '-') . " L/s\n";
    
    if ($mediaHistorica) {
        $contexto .= "- Média histórica (mesmo dia semana): {$mediaHistorica} L/s\n";
        $contexto .= "- Variação: {$variacaoHistorico}%\n";
    }
    
    if (!empty($horasIncompletas)) {
        $contexto .= "- Horas incompletas (<50 reg): " . implode(', ', $horasIncompletas) . "\n";
    }
    
    if (!empty($horasZeradas)) {
        $contexto .= "- Horas com vazão zerada: " . implode(', ', $horasZeradas) . "\n";
    }
    
    if (!empty($horasVazias)) {
        $contexto .= "- Horas SEM DADOS (vazias): " . implode(', ', $horasVazias) . "\n";
        $contexto .= "  ATENÇÃO: Horas vazias indicam ausência total de comunicação/leitura. Isso é DIFERENTE de vazão zero!\n";
    }
    
    $contexto .= "\nINSTRUÇÕES:\n";
    $contexto .= "1. Gere um resumo executivo de 3-5 linhas\n";
    $contexto .= "2. Destaque anomalias se houver (horas VAZIAS são críticas, vazão zerada, incompleto, variação alta)\n";
    $contexto .= "3. Compare com histórico se disponível\n";
    $contexto .= "4. Use linguagem técnica mas objetiva\n";
    $contexto .= "5. NÃO use listas ou bullet points, apenas texto corrido\n";
    $contexto .= "6. IMPORTANTE: Diferencie claramente 'hora vazia' (sem dados) de 'vazão zero' (tem dados com valor 0)\n";
    
    // Chamar IA
    $analiseIA = chamarIA($contexto, $iaConfig);
    
    retornarJSON([
        'success' => true,
        'ponto' => [
            'cd' => $cdPonto,
            'nome' => $infoPonto['DS_NOME'],
            'codigo' => $codigoPonto,
            'tipoMedidor' => $infoPonto['ID_TIPO_MEDIDOR'],
            'tipoMedidorNome' => $tipoMedidorNome,
            'localidade' => $infoPonto['LOCALIDADE']
        ],
        'data' => $data,
        'dataFormatada' => $dataFormatada,
        'diaSemana' => $diaSemanaTexto,
        'resumo' => [
            'totalRegistros' => $totalRegistros,
            'percentualCompleto' => $percentualCompleto,
            'mediaDiaria' => $mediaDiaria,
            'minVazao' => $minVazaoGlobal !== null ? round($minVazaoGlobal, 2) : null,
            'maxVazao' => $maxVazaoGlobal !== null ? round($maxVazaoGlobal, 2) : null,
            'mediaHistorica' => $mediaHistorica,
            'variacaoHistorico' => $variacaoHistorico,
            'horasIncompletas' => $horasIncompletas,
            'horasZeradas' => $horasZeradas,
            'horasVazias' => $horasVazias
        ],
        'dadosHorarios' => $dadosHorarios,
        'analiseIA' => $analiseIA
    ]);
    
} catch (Exception $e) {
    retornarJSON([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Chama a API de IA configurada
 */
function chamarIA($contexto, $config) {
    $provider = $config['provider'] ?? 'gemini';
    
    try {
        if ($provider === 'groq') {
            return chamarGroq($contexto, $config['groq']);
        } elseif ($provider === 'deepseek') {
            return chamarDeepSeek($contexto, $config['deepseek']);
        } else {
            return chamarGemini($contexto, $config['gemini']);
        }
    } catch (Exception $e) {
        return "Não foi possível gerar análise automática: " . $e->getMessage();
    }
}

function chamarGemini($contexto, $config) {
    // Montar URL completa: base + modelo + :generateContent
    $baseUrl = rtrim($config['api_url'], '/');
    $model = $config['model'] ?? 'gemini-2.0-flash-lite';
    $url = $baseUrl . '/' . $model . ':generateContent?key=' . $config['api_key'];
    
    $payload = [
        'contents' => [
            ['parts' => [['text' => $contexto]]]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 500
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    // Verificar erro de cURL
    if ($curlErrno !== 0) {
        throw new Exception("Erro cURL $curlErrno: $curlError");
    }
    
    if ($httpCode !== 200) {
        $errorMsg = "Erro HTTP $httpCode";
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['error']['message'])) {
                $errorMsg .= ": " . $errorData['error']['message'];
            }
        }
        throw new Exception($errorMsg);
    }
    
    $data = json_decode($response, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta';
}

function chamarGroq($contexto, $config) {
    $url = $config['api_url'];
    
    $payload = [
        'model' => $config['model'],
        'messages' => [
            ['role' => 'user', 'content' => $contexto]
        ],
        'temperature' => 0.3,
        'max_tokens' => 500
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Erro HTTP $httpCode");
    }
    
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Sem resposta';
}

function chamarDeepSeek($contexto, $config) {
    $url = $config['api_url'];
    
    $payload = [
        'model' => $config['model'],
        'messages' => [
            ['role' => 'user', 'content' => $contexto]
        ],
        'temperature' => 0.3,
        'max_tokens' => 500
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    // Verificar erro de cURL
    if ($curlErrno !== 0) {
        throw new Exception("Erro cURL $curlErrno: $curlError");
    }
    
    if ($httpCode !== 200) {
        $errorMsg = "Erro HTTP $httpCode";
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['error']['message'])) {
                $errorMsg .= ": " . $errorData['error']['message'];
            }
        }
        throw new Exception($errorMsg);
    }
    
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Sem resposta';
}