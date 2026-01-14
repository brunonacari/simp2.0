<?php
/**
 * Importação de Planilha de Registro de Vazão e Pressão
 * Recebe dados já processados pelo JavaScript (SheetJS)
 * 
 * Colunas esperadas:
 * DATA | HORA | PONTO MEDICAO | TEMP ÁGUA | TEMP AMB | PRESSÃO | VOLUME | PERÍODO
 */

// Suprimir erros/warnings na saída para não quebrar JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

// DEBUG - Log de todas as operações
$debugLog = __DIR__ . '/debug_importacao.log';
file_put_contents($debugLog, "\n\n" . str_repeat('=', 60) . "\n");
file_put_contents($debugLog, "IMPORTAÇÃO: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents($debugLog, str_repeat('=', 60) . "\n", FILE_APPEND);

session_start();
if (!isset($_SESSION['sucesso']) || $_SESSION['sucesso'] != 1) {
    file_put_contents($debugLog, "ERRO: Não autenticado\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

file_put_contents($debugLog, "Usuário: " . ($_SESSION['cd_usuario'] ?? 'N/A') . "\n", FILE_APPEND);

// Receber JSON
$json = file_get_contents('php://input');
file_put_contents($debugLog, "JSON recebido (primeiros 2000 chars):\n" . substr($json, 0, 2000) . "\n\n", FILE_APPEND);

$dados = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $erro = "Erro ao decodificar JSON: " . json_last_error_msg();
    file_put_contents($debugLog, "ERRO: $erro\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $erro]);
    exit;
}

if (!$dados || !isset($dados['registros']) || empty($dados['registros'])) {
    file_put_contents($debugLog, "ERRO: Nenhum dado/registro recebido\n", FILE_APPEND);
    file_put_contents($debugLog, "Dados: " . print_r($dados, true) . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Nenhum dado recebido']);
    exit;
}

file_put_contents($debugLog, "Total de registros recebidos: " . count($dados['registros']) . "\n", FILE_APPEND);

// Parâmetros
$dataEventoMedicao = isset($dados['dataEventoMedicao']) && trim($dados['dataEventoMedicao']) !== '' 
    ? trim($dados['dataEventoMedicao']) : null;

file_put_contents($debugLog, "Parâmetros:\n", FILE_APPEND);
file_put_contents($debugLog, "  - dataEventoMedicao: " . ($dataEventoMedicao ?? 'NULL') . "\n", FILE_APPEND);
file_put_contents($debugLog, "  - tipoVazao: " . ($dados['tipoVazao'] ?? 'N/A') . "\n", FILE_APPEND);
file_put_contents($debugLog, "  - numOS: " . ($dados['numOS'] ?? 'N/A') . "\n", FILE_APPEND);
file_put_contents($debugLog, "  - houveOcorrencia: " . ($dados['houveOcorrencia'] ?? 'N/A') . "\n", FILE_APPEND);
file_put_contents($debugLog, "  - observacao: " . ($dados['observacao'] ?? 'N/A') . "\n", FILE_APPEND);

if (!$dataEventoMedicao) {
    file_put_contents($debugLog, "ERRO: Data do Evento de Medição é obrigatória\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Data do Evento de Medição é obrigatória']);
    exit;
}

$tipoVazao = isset($dados['tipoVazao']) ? (int)$dados['tipoVazao'] : 1;
$numOS = isset($dados['numOS']) && trim($dados['numOS']) !== '' ? substr(trim($dados['numOS']), 0, 10) : null; // Limitar a 10 chars
$houveOcorrencia = isset($dados['houveOcorrencia']) && $dados['houveOcorrencia'] == 1 ? 1 : 0;
$observacao = isset($dados['observacao']) && trim($dados['observacao']) !== '' ? substr(trim($dados['observacao']), 0, 200) : null; // Limitar a 200 chars
$cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;

if (!$cdUsuario) {
    file_put_contents($debugLog, "ERRO: Usuário não identificado na sessão\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Usuário não identificado na sessão']);
    exit;
}

file_put_contents($debugLog, "=== PARÂMETROS DO FORMULÁRIO ===\n", FILE_APPEND);
file_put_contents($debugLog, "  cdUsuario: $cdUsuario (tipo: " . gettype($cdUsuario) . ")\n", FILE_APPEND);
file_put_contents($debugLog, "  dataEventoMedicao: $dataEventoMedicao\n", FILE_APPEND);
file_put_contents($debugLog, "  tipoVazao: $tipoVazao\n", FILE_APPEND);
file_put_contents($debugLog, "  numOS: " . ($numOS ?? 'NULL') . "\n", FILE_APPEND);
file_put_contents($debugLog, "  houveOcorrencia: $houveOcorrencia\n", FILE_APPEND);
file_put_contents($debugLog, "  observacao: " . ($observacao ?? 'NULL') . "\n", FILE_APPEND);
file_put_contents($debugLog, "================================\n", FILE_APPEND);

// Conectar ao banco
file_put_contents($debugLog, "\nConectando ao banco...\n", FILE_APPEND);
include_once '../conexao.php';

if (!isset($pdoSIMP)) {
    file_put_contents($debugLog, "ERRO: Conexão PDO não estabelecida\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Configurar PDO para lançar exceções em caso de erro
$pdoSIMP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
file_put_contents($debugLog, "Conexão OK - ERRMODE_EXCEPTION ativado\n", FILE_APPEND);

// Log de informações do driver
file_put_contents($debugLog, "Driver: " . $pdoSIMP->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n", FILE_APPEND);

try {
    $registros = $dados['registros'];
    $erros = [];
    $avisos = [];
    
    // Agrupar por ponto de medição
    $dadosPorPonto = [];
    
    file_put_contents($debugLog, "\nProcessando " . count($registros) . " registros da planilha...\n", FILE_APPEND);
    
    foreach ($registros as $idx => $reg) {
        $cdPonto = (int)$reg['pontoMedicao'];
        
        file_put_contents($debugLog, "  Registro #$idx: Ponto=$cdPonto, Data={$reg['data']}, Hora={$reg['hora']}\n", FILE_APPEND);
        
        if (!isset($dadosPorPonto[$cdPonto])) {
            $dadosPorPonto[$cdPonto] = [];
            file_put_contents($debugLog, "    -> Novo ponto de medição encontrado: $cdPonto\n", FILE_APPEND);
        }
        
        // Converter data
        $dataStr = trim($reg['data']);
        $dataFormatada = null;
        
        // Formato dd/mm/yyyy (4 dígitos de ano)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dataStr, $m)) {
            $dataFormatada = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        // Formato dd/mm/yy (2 dígitos de ano)
        elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $dataStr, $m)) {
            $ano = (int)$m[3];
            // Assumir 2000-2099 para anos de 2 dígitos
            $anoCompleto = ($ano <= 99) ? 2000 + $ano : $ano;
            $dataFormatada = sprintf('%04d-%02d-%02d', $anoCompleto, $m[2], $m[1]);
        }
        // Formato yyyy-mm-dd (ISO)
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataStr)) {
            $dataFormatada = $dataStr;
        }
        // Tentar outros formatos com DateTime
        else {
            $dt = DateTime::createFromFormat('d/m/Y', $dataStr) 
                ?: DateTime::createFromFormat('d/m/y', $dataStr)
                ?: DateTime::createFromFormat('Y-m-d', $dataStr)
                ?: DateTime::createFromFormat('m/d/Y', $dataStr)
                ?: DateTime::createFromFormat('d-m-Y', $dataStr)
                ?: DateTime::createFromFormat('d-m-y', $dataStr);
            if ($dt) {
                $dataFormatada = $dt->format('Y-m-d');
            }
        }
        
        if (!$dataFormatada) {
            $erros[] = "Linha {$reg['linha']}: Formato de data inválido: $dataStr";
            file_put_contents($debugLog, "  ERRO Linha {$reg['linha']}: Data inválida: $dataStr\n", FILE_APPEND);
            continue;
        }
        
        file_put_contents($debugLog, "  Linha {$reg['linha']}: data='$dataStr' -> '$dataFormatada'\n", FILE_APPEND);
        
        // Converter hora
        $horaStr = $reg['hora'];
        $horaInt = 0;
        $minutoInt = 0;
        
        if (is_numeric($horaStr)) {
            // Hora como decimal (fração do dia)
            $horaDecimal = floatval($horaStr);
            if ($horaDecimal >= 0 && $horaDecimal < 1) {
                // Fração do dia (ex: 0.5 = 12:00)
                $horaDecimal *= 24;
            }
            $horaInt = floor($horaDecimal);
            $minutoInt = round(($horaDecimal - $horaInt) * 60);
        } else {
            // Hora como string (ex: "14:30")
            $horaStr = trim($horaStr);
            if (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $horaStr, $matches)) {
                $horaInt = (int)$matches[1];
                $minutoInt = (int)$matches[2];
            } else {
                $erros[] = "Linha {$reg['linha']}: Formato de hora inválido: $horaStr";
                file_put_contents($debugLog, "  ERRO Linha {$reg['linha']}: Hora inválida: $horaStr\n", FILE_APPEND);
                continue;
            }
        }
        
        $horaFormatada = sprintf('%02d:%02d:00', $horaInt, $minutoInt);
        
        $dadosPorPonto[$cdPonto][] = [
            'data' => $dataFormatada,
            'hora' => $horaFormatada,
            'horaInt' => $horaInt,
            'minutoInt' => $minutoInt,
            'tempAgua' => $reg['tempAgua'],
            'tempAmb' => $reg['tempAmb'],
            'pressao' => $reg['pressao'],
            'volume' => $reg['volume'],
            'periodo' => $reg['periodo'],
            'linha' => $reg['linha']
        ];
    }
    
    file_put_contents($debugLog, "Pontos encontrados: " . implode(', ', array_keys($dadosPorPonto)) . "\n", FILE_APPEND);
    
    if (empty($dadosPorPonto)) {
        file_put_contents($debugLog, "ERRO: Nenhum dado válido para importar\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum dado válido para importar',
            'erros' => $erros
        ]);
        exit;
    }
    
    // Buscar informações dos pontos de medição
    $pontosIds = array_map('intval', array_keys($dadosPorPonto));
    
    // Usar query com IN diretamente (valores inteiros são seguros)
    $idsString = implode(',', $pontosIds);
    
    $sqlPontos = "SELECT PM.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR, PM.OP_PERIODICIDADE_LEITURA,
                         LOC.CD_LOCALIDADE, LOC.CD_UNIDADE
                  FROM SIMP.dbo.PONTO_MEDICAO PM
                  LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                  WHERE PM.CD_PONTO_MEDICAO IN ($idsString)";
    
    file_put_contents($debugLog, "\nBuscando pontos de medição...\n", FILE_APPEND);
    file_put_contents($debugLog, "SQL: $sqlPontos\n", FILE_APPEND);
    file_put_contents($debugLog, "IDs: $idsString\n", FILE_APPEND);
    
    $stmtPontos = $pdoSIMP->query($sqlPontos);
    $pontosInfo = $stmtPontos->fetchAll(PDO::FETCH_ASSOC);
    
    file_put_contents($debugLog, "Pontos encontrados no banco: " . count($pontosInfo) . "\n", FILE_APPEND);
    
    // Mapeamento de tipo de medidor para letra
    $letrasTipoMedidor = [
        1 => 'M', // Macromedidor
        2 => 'E', // Estação Pitométrica
        4 => 'P', // Medidor Pressão
        6 => 'R', // Nível Reservatório
        8 => 'H'  // Hidrômetro
    ];
    
    // Indexar por CD_PONTO_MEDICAO (converter para inteiro para garantir comparação correta)
    $pontosMap = [];
    foreach ($pontosInfo as $ponto) {
        $cdPontoInt = (int)$ponto['CD_PONTO_MEDICAO'];
        
        // Gerar código formatado: LOCALIDADE-ID_PONTO-LETRA-CD_UNIDADE
        $letraTipo = $letrasTipoMedidor[(int)$ponto['ID_TIPO_MEDIDOR']] ?? 'X';
        $codigoFormatado = $ponto['CD_LOCALIDADE'] . '-' . str_pad($cdPontoInt, 6, '0', STR_PAD_LEFT) . '-' . $letraTipo . '-' . $ponto['CD_UNIDADE'];
        $ponto['CODIGO_FORMATADO'] = $codigoFormatado;
        
        $pontosMap[$cdPontoInt] = $ponto;
        file_put_contents($debugLog, "  - Ponto $cdPontoInt ($codigoFormatado): {$ponto['DS_NOME']} | Tipo: {$ponto['ID_TIPO_MEDIDOR']} | Periodicidade: {$ponto['OP_PERIODICIDADE_LEITURA']}\n", FILE_APPEND);
    }
    
    // Verificar pontos não encontrados
    foreach ($pontosIds as $cdPonto) {
        if (!isset($pontosMap[$cdPonto])) {
            $erros[] = "Ponto de medição $cdPonto não encontrado no sistema";
            file_put_contents($debugLog, "  ERRO: Ponto $cdPonto não encontrado\n", FILE_APPEND);
            unset($dadosPorPonto[$cdPonto]);
        }
    }
    
    // Resumo dos pontos a processar
    file_put_contents($debugLog, "\n=== RESUMO DOS PONTOS A PROCESSAR ===\n", FILE_APPEND);
    foreach ($dadosPorPonto as $cdPonto => $registros) {
        $qtdRegistros = count($registros);
        $nomeP = isset($pontosMap[$cdPonto]) ? $pontosMap[$cdPonto]['DS_NOME'] : "Desconhecido";
        $tipoM = isset($pontosMap[$cdPonto]) ? $pontosMap[$cdPonto]['ID_TIPO_MEDIDOR'] : "?";
        file_put_contents($debugLog, "  Ponto $cdPonto ($nomeP) - Tipo: $tipoM - Registros: $qtdRegistros\n", FILE_APPEND);
    }
    file_put_contents($debugLog, "=====================================\n", FILE_APPEND);
    
    if (empty($dadosPorPonto)) {
        file_put_contents($debugLog, "ERRO: Nenhum ponto de medição válido encontrado\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum ponto de medição válido encontrado',
            'erros' => $erros
        ]);
        exit;
    }
    
    // Processar cada ponto
    $resumo = [];
    $totalRegistros = 0;
    
    file_put_contents($debugLog, "\nIniciando transação...\n", FILE_APPEND);
    file_put_contents($debugLog, "PDO inTransaction antes: " . ($pdoSIMP->inTransaction() ? 'SIM' : 'NÃO') . "\n", FILE_APPEND);
    
    $beginResult = $pdoSIMP->beginTransaction();
    file_put_contents($debugLog, "beginTransaction() retornou: " . ($beginResult ? 'true' : 'false') . "\n", FILE_APPEND);
    file_put_contents($debugLog, "PDO inTransaction depois: " . ($pdoSIMP->inTransaction() ? 'SIM' : 'NÃO') . "\n", FILE_APPEND);
    
    foreach ($dadosPorPonto as $cdPonto => $registrosPonto) {
        $pontoInfo = $pontosMap[$cdPonto];
        $tipoMedidor = (int)$pontoInfo['ID_TIPO_MEDIDOR'];
        $periodicidade = (int)$pontoInfo['OP_PERIODICIDADE_LEITURA'];
        $codigoFormatado = $pontoInfo['CODIGO_FORMATADO'] ?? "Ponto $cdPonto";
        
        file_put_contents($debugLog, "\nProcessando ponto $codigoFormatado (ID: $cdPonto)...\n", FILE_APPEND);
        file_put_contents($debugLog, "  Tipo Medidor: $tipoMedidor | Periodicidade: $periodicidade\n", FILE_APPEND);
        file_put_contents($debugLog, "  Registros a processar: " . count($registrosPonto) . "\n", FILE_APPEND);
        
        // Validar tipo de medidor 6 (Nível Reservatório)
        if ($tipoMedidor === 6) {
            $erros[] = "Ponto $codigoFormatado: Não é possível importar planilha para 'Nível de Reservatório'";
            file_put_contents($debugLog, "  ERRO: Tipo 6 (Nível Reservatório) não suportado\n", FILE_APPEND);
            continue;
        }
        
        // Tipo 1, 2, 8 = Macromedidor (vazão)
        // Tipo 4 = Medidor de Pressão
        $isMacromedidor = in_array($tipoMedidor, [1, 2, 8]);
        $isMedidorPressao = ($tipoMedidor === 4);
        
        // Nomes dos tipos de medidor
        $nomesTipoMedidor = [
            1 => 'Macromedidor',
            2 => 'Estação Pitométrica',
            4 => 'Medidor de Pressão',
            6 => 'Nível Reservatório',
            8 => 'Hidrômetro'
        ];
        $nomeTipoMedidor = $nomesTipoMedidor[$tipoMedidor] ?? "Tipo $tipoMedidor";
        
        file_put_contents($debugLog, "  Tipo Medidor: $nomeTipoMedidor | isMacromedidor: " . ($isMacromedidor ? 'SIM' : 'NÃO') . " | isMedidorPressao: " . ($isMedidorPressao ? 'SIM' : 'NÃO') . "\n", FILE_APPEND);
        
        // Validar dados conforme tipo
        $registrosValidos = [];
        foreach ($registrosPonto as $reg) {
            $linhaNum = $reg['linha'];
            
            if ($isMacromedidor) {
                // Macromedidor (Tipo 1, 2 ou 8): Volume e Período obrigatórios, Pressão não permitida
                $temVolume = isset($reg['volume']) && $reg['volume'] !== null && $reg['volume'] !== '' && $reg['volume'] !== 0;
                $temPeriodo = isset($reg['periodo']) && $reg['periodo'] !== null && $reg['periodo'] !== '' && $reg['periodo'] !== 0;
                $temPressao = isset($reg['pressao']) && $reg['pressao'] !== null && $reg['pressao'] !== '' && $reg['pressao'] != 0;
                
                file_put_contents($debugLog, "    Linha $linhaNum: temVolume=" . ($temVolume ? 'SIM' : 'NÃO') . ", temPeriodo=" . ($temPeriodo ? 'SIM' : 'NÃO') . ", temPressao=" . ($temPressao ? 'SIM' : 'NÃO') . "\n", FILE_APPEND);
                
                if ($temPressao && !$temVolume && !$temPeriodo) {
                    // Usuário preencheu PRESSÃO em um macromedidor - orientar corretamente
                    $erros[] = "Linha $linhaNum - Ponto $codigoFormatado: Este ponto é do tipo '$nomeTipoMedidor' e NÃO aceita dados de PRESSÃO. Para este tipo, preencha as colunas VOLUME e PERÍODO.";
                    file_put_contents($debugLog, "    -> ERRO: Dados de PRESSÃO em macromedidor\n", FILE_APPEND);
                    continue;
                }
                if (!$temVolume) {
                    $erros[] = "Linha $linhaNum - Ponto $codigoFormatado: Este ponto é do tipo '$nomeTipoMedidor'. A coluna VOLUME é obrigatória.";
                    file_put_contents($debugLog, "    -> ERRO: VOLUME obrigatório para macromedidor\n", FILE_APPEND);
                    continue;
                }
                if (!$temPeriodo) {
                    $erros[] = "Linha $linhaNum - Ponto $codigoFormatado: Este ponto é do tipo '$nomeTipoMedidor'. A coluna PERÍODO é obrigatória.";
                    file_put_contents($debugLog, "    -> ERRO: PERÍODO obrigatório para macromedidor\n", FILE_APPEND);
                    continue;
                }
                if ($temPressao) {
                    $erros[] = "Linha $linhaNum - Ponto $codigoFormatado: Este ponto é do tipo '$nomeTipoMedidor' e NÃO aceita dados de PRESSÃO. Deixe a coluna PRESSÃO vazia.";
                    file_put_contents($debugLog, "    -> ERRO: PRESSÃO não permitida para macromedidor\n", FILE_APPEND);
                    continue;
                }
            }
            
            if ($isMedidorPressao) {
                // Medidor de Pressão (Tipo 4): Pressão obrigatória, Volume e Período não permitidos
                $temPressao = isset($reg['pressao']) && $reg['pressao'] !== null && $reg['pressao'] !== '';
                $temVolume = isset($reg['volume']) && $reg['volume'] !== null && $reg['volume'] !== '' && $reg['volume'] != 0;
                $temPeriodo = isset($reg['periodo']) && $reg['periodo'] !== null && $reg['periodo'] !== '' && $reg['periodo'] != 0;
                
                file_put_contents($debugLog, "    Linha $linhaNum: temPressao=" . ($temPressao ? 'SIM' : 'NÃO') . ", temVolume=" . ($temVolume ? 'SIM' : 'NÃO') . ", temPeriodo=" . ($temPeriodo ? 'SIM' : 'NÃO') . "\n", FILE_APPEND);
                
                if (($temVolume || $temPeriodo) && !$temPressao) {
                    // Usuário preencheu VOLUME/PERÍODO em um medidor de pressão - orientar corretamente
                    $erros[] = "Linha $linhaNum - Ponto $codigoFormatado: Este ponto é do tipo '$nomeTipoMedidor' e NÃO aceita dados de VOLUME/PERÍODO. Para este tipo, preencha apenas a coluna PRESSÃO.";
                    file_put_contents($debugLog, "    -> ERRO: Dados de VOLUME/PERÍODO em medidor de pressão\n", FILE_APPEND);
                    continue;
                }
                if (!$temPressao) {
                    $erros[] = "Linha $linhaNum - Ponto $codigoFormatado: Este ponto é do tipo '$nomeTipoMedidor'. A coluna PRESSÃO é obrigatória.";
                    file_put_contents($debugLog, "    -> ERRO: PRESSÃO obrigatória para medidor de pressão\n", FILE_APPEND);
                    continue;
                }
                if ($temVolume || $temPeriodo) {
                    $erros[] = "Linha $linhaNum - Ponto $codigoFormatado: Este ponto é do tipo '$nomeTipoMedidor' e NÃO aceita dados de VOLUME/PERÍODO. Deixe essas colunas vazias.";
                    file_put_contents($debugLog, "    -> ERRO: VOLUME/PERÍODO não permitidos para medidor de pressão\n", FILE_APPEND);
                    continue;
                }
            }
            
            $registrosValidos[] = $reg;
            file_put_contents($debugLog, "    Linha $linhaNum: VÁLIDO\n", FILE_APPEND);
        }
        
        file_put_contents($debugLog, "  Registros válidos após validação: " . count($registrosValidos) . " de " . count($registrosPonto) . "\n", FILE_APPEND);
        
        if (empty($registrosValidos)) {
            // Adicionar ao resumo com 0 registros
            $resumo[] = [
                'ponto' => $codigoFormatado,
                'registros' => 0,
                'duplicados' => 0,
                'rejeitados' => count($registrosPonto)
            ];
            continue;
        }
        
        // Importar cada registro individualmente, mantendo a hora original da planilha
        // DT_LEITURA = DATA + HORA da planilha
        $registrosParaInserir = [];
        
        file_put_contents($debugLog, "  Importando registros individualmente (sem agregação)\n", FILE_APPEND);
        
        foreach ($registrosValidos as $reg) {
            // Calcular vazão efetiva se for macromedidor com volume e período
            $vazaoEfetiva = null;
            if ($isMacromedidor && $reg['volume'] && $reg['periodo']) {
                $vazaoEfetiva = ($reg['volume'] * 1000) / $reg['periodo'];
            }
            
            $registrosParaInserir[] = [
                'data' => $reg['data'],
                'hora' => $reg['hora'],  // Manter hora original da planilha
                'tempAgua' => $reg['tempAgua'],
                'tempAmb' => $reg['tempAmb'],
                'pressao' => $reg['pressao'],  // PRESSÃO da planilha -> VL_PRESSAO
                'volume' => $reg['volume'],
                'periodo' => $reg['periodo'],
                'vazaoEfetiva' => $vazaoEfetiva
            ];
            
            file_put_contents($debugLog, "    Linha {$reg['linha']}: {$reg['data']} {$reg['hora']} - Volume: " . ($reg['volume'] ?? 'NULL') . ", Período: " . ($reg['periodo'] ?? 'NULL') . ", Pressão: " . ($reg['pressao'] ?? 'NULL') . "\n", FILE_APPEND);
        }
        
        file_put_contents($debugLog, "  Registros para inserir: " . count($registrosParaInserir) . "\n", FILE_APPEND);
        
        $countPonto = 0;
        $countDuplicados = 0;
        
        foreach ($registrosParaInserir as $regIdx => $reg) {
            // DT_LEITURA = data/hora da planilha
            $dtLeitura = $reg['data'] . ' ' . $reg['hora'];
            
            // Verificar se já existe registro com mesma chave (CD_PONTO_MEDICAO + DT_LEITURA + ID_SITUACAO=1)
            $sqlVerificaDuplicata = "SELECT COUNT(*) as total 
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                WHERE CD_PONTO_MEDICAO = $cdPonto 
                AND DT_LEITURA = '$dtLeitura' 
                AND ID_SITUACAO = 1";
            
            $stmtVerifica = $pdoSIMP->query($sqlVerificaDuplicata);
            $resultVerifica = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
            
            if ($resultVerifica && $resultVerifica['total'] > 0) {
                // Já existe registro - pular
                $countDuplicados++;
                if ($countDuplicados <= 5) { // Log apenas dos primeiros 5
                    file_put_contents($debugLog, "  SKIP #$regIdx - Já existe registro para $dtLeitura\n", FILE_APPEND);
                }
                continue;
            }
            
            // Preparar valores para SQL direto (escapando strings)
            $sqlVazaoEfetiva = ($reg['vazaoEfetiva'] !== null) ? $reg['vazaoEfetiva'] : 'NULL';
            $sqlPressao = ($reg['pressao'] !== null) ? $reg['pressao'] : 'NULL';
            $sqlTempAgua = ($reg['tempAgua'] !== null) ? $reg['tempAgua'] : 'NULL';
            $sqlTempAmb = ($reg['tempAmb'] !== null) ? $reg['tempAmb'] : 'NULL';
            $sqlVolume = ($reg['volume'] !== null) ? $reg['volume'] : 'NULL';
            $sqlPeriodo = ($reg['periodo'] !== null) ? $reg['periodo'] : 'NULL';
            $sqlObservacao = ($observacao !== null) ? "'" . str_replace("'", "''", $observacao) . "'" : 'NULL';
            $sqlNumOS = ($numOS !== null) ? "'" . str_replace("'", "''", $numOS) . "'" : 'NULL';
            
            // Montar SQL direto
            $sqlInsertDireto = "INSERT INTO SIMP.dbo.REGISTRO_VAZAO_PRESSAO (
                CD_PONTO_MEDICAO,
                DT_EVENTO_MEDICAO,
                ID_TIPO_REGISTRO,
                ID_TIPO_MEDICAO,
                DT_LEITURA,
                VL_VAZAO_EFETIVA,
                VL_PRESSAO,
                VL_TEMP_AGUA,
                VL_TEMP_AMBIENTE,
                VL_VOLUME,
                VL_PERIODO_MEDICAO_VOLUME,
                ID_SITUACAO,
                ID_TIPO_VAZAO,
                DS_OBSERVACAO,
                HOUVE_OCORRENCIA,
                NUM_OS,
                CD_USUARIO_RESPONSAVEL,
                CD_USUARIO_ULTIMA_ATUALIZACAO,
                DT_ULTIMA_ATUALIZACAO
            ) VALUES (
                $cdPonto,
                '$dataEventoMedicao',
                4,
                2,
                '$dtLeitura',
                $sqlVazaoEfetiva,
                $sqlPressao,
                $sqlTempAgua,
                $sqlTempAmb,
                $sqlVolume,
                $sqlPeriodo,
                1,
                $tipoVazao,
                $sqlObservacao,
                $houveOcorrencia,
                $sqlNumOS,
                $cdUsuario,
                $cdUsuario,
                GETDATE()
            )";
            
            // Log do SQL
            if ($regIdx < 3) { // Log apenas dos 3 primeiros para não poluir
                file_put_contents($debugLog, "  INSERT #$regIdx - SQL:\n$sqlInsertDireto\n\n", FILE_APPEND);
            }
            
            try {
                $result = $pdoSIMP->exec($sqlInsertDireto);
                
                file_put_contents($debugLog, "  INSERT #$regIdx - exec() retornou: " . ($result !== false ? $result : 'false') . "\n", FILE_APPEND);
                
                if ($result !== false) {
                    $countPonto++;
                } else {
                    $errorInfo = $pdoSIMP->errorInfo();
                    file_put_contents($debugLog, "    -> ERRO: " . json_encode($errorInfo) . "\n", FILE_APPEND);
                }
            } catch (PDOException $e) {
                file_put_contents($debugLog, "  ERRO INSERT #$regIdx PDO: " . $e->getMessage() . "\n", FILE_APPEND);
                throw $e;
            }
        }
        
        file_put_contents($debugLog, "  Inseridos: $countPonto registros | Duplicados ignorados: $countDuplicados\n", FILE_APPEND);
        
        // Calcular rejeitados por validação
        $countRejeitados = count($registrosPonto) - count($registrosValidos);
        
        // Adicionar aviso se houver duplicados
        if ($countDuplicados > 0) {
            $avisos[] = "Ponto $codigoFormatado: $countDuplicados registro(s) já existente(s) foram ignorados";
        }
        
        $resumo[] = [
            'ponto' => $codigoFormatado,
            'registros' => $countPonto,
            'duplicados' => $countDuplicados,
            'rejeitados' => $countRejeitados
        ];
        $totalRegistros += $countPonto;
    }
    
    file_put_contents($debugLog, "\n=== RESUMO ANTES DO COMMIT ===\n", FILE_APPEND);
    file_put_contents($debugLog, "Total de registros contados: $totalRegistros\n", FILE_APPEND);
    file_put_contents($debugLog, "Resumo: " . json_encode($resumo) . "\n", FILE_APPEND);
    file_put_contents($debugLog, "PDO inTransaction: " . ($pdoSIMP->inTransaction() ? 'SIM' : 'NÃO') . "\n", FILE_APPEND);
    
    file_put_contents($debugLog, "\nCommitando transação...\n", FILE_APPEND);
    $commitResult = $pdoSIMP->commit();
    file_put_contents($debugLog, "commit() retornou: " . ($commitResult ? 'true' : 'false') . "\n", FILE_APPEND);
    file_put_contents($debugLog, "PDO inTransaction após commit: " . ($pdoSIMP->inTransaction() ? 'SIM' : 'NÃO') . "\n", FILE_APPEND);
    
    // Verificar se os registros foram realmente inseridos
    $sqlVerifica = "SELECT COUNT(*) as total FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO WHERE DT_EVENTO_MEDICAO = ? AND CD_USUARIO_RESPONSAVEL = ?";
    $stmtVerifica = $pdoSIMP->prepare($sqlVerifica);
    $stmtVerifica->execute([$dataEventoMedicao, $cdUsuario]);
    $verificaResult = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    file_put_contents($debugLog, "Verificação após commit - Registros com DT_EVENTO_MEDICAO=$dataEventoMedicao e usuario=$cdUsuario: " . $verificaResult['total'] . "\n", FILE_APPEND);
    
    file_put_contents($debugLog, "Commit OK!\n", FILE_APPEND);
    
    // Retornar resultado
    if ($totalRegistros > 0) {
        file_put_contents($debugLog, "\nSUCESSO! Total: $totalRegistros registros\n", FILE_APPEND);
        echo json_encode([
            'success' => true,
            'resumo' => $resumo,
            'totalRegistros' => $totalRegistros,
            'erros' => !empty($erros) ? $erros : null,
            'avisos' => !empty($avisos) ? $avisos : null
        ]);
    } else {
        file_put_contents($debugLog, "\nFALHA: Nenhum registro importado\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum registro foi importado',
            'erros' => $erros
        ]);
    }
    
} catch (PDOException $e) {
    if ($pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    file_put_contents($debugLog, "\nERRO PDO: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($debugLog, "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao inserir registros: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    file_put_contents($debugLog, "\nERRO: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($debugLog, "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}