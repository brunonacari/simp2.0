<?php
/**
 * SIMP - Buscar Dados de Operações
 * Retorna dados de medição agrupados por ponto de medição
 * Aceita busca por:
 * - CD_ENTIDADE_VALOR_ID (agrupa valores que compartilham os mesmos pontos)
 * - CD_PONTO_MEDICAO (busca direta por ponto específico)
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $valorId = isset($_GET['valorId']) && $_GET['valorId'] !== '' ? (int)$_GET['valorId'] : 0;
    $valorEntidadeId = isset($_GET['valorEntidadeId']) ? trim($_GET['valorEntidadeId']) : '';
    $pontoId = isset($_GET['pontoId']) && $_GET['pontoId'] !== '' ? (int)$_GET['pontoId'] : 0;
    $dataInicio = isset($_GET['dataInicio']) ? $_GET['dataInicio'] : '';
    $dataFim = isset($_GET['dataFim']) ? $_GET['dataFim'] : '';

    if (empty($dataInicio) || empty($dataFim)) {
        throw new Exception('Período não informado');
    }
    
    if ($valorId <= 0 && $pontoId <= 0) {
        throw new Exception('Informe o valor de entidade ou o ponto de medição');
    }

    $fluxoNomes = [
        1 => 'Entrada',
        2 => 'Saída',
        3 => 'Municipal',
        4 => 'Não se Aplica'
    ];

    $letrasTipo = [
        1 => 'M',
        2 => 'E',
        4 => 'P',
        8 => 'H',
        6 => 'R'
    ];

    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',
        2 => 'VL_VAZAO_EFETIVA',
        4 => 'VL_PRESSAO',
        8 => 'VL_VAZAO_EFETIVA',
        6 => 'VL_RESERVATORIO'
    ];

    $unidadesPorTipo = [
        1 => 'L/s',
        2 => 'L/s',
        4 => 'mca',
        8 => 'L/s',
        6 => 'm'
    ];

    $nomesTipo = [
        1 => 'M - Macromedidor',
        2 => 'E - Estação Pitométrica',
        4 => 'P - Medidor Pressão',
        8 => 'H - Hidrômetro',
        6 => 'R - Nível Reservatório'
    ];

    $dados = [];
    $tiposMedidorUsados = [];
    $todosValores = [];
    $unidadePrincipal = '';
    $pontosInfo = [];

    // MODO 1: Busca por ponto específico (sem entidade)
    if ($pontoId > 0 && $valorId <= 0) {
        $sqlPonto = "SELECT 
                        PM.CD_PONTO_MEDICAO,
                        PM.DS_NOME AS PONTO_NOME,
                        PM.ID_TIPO_MEDIDOR,
                        L.CD_LOCALIDADE,
                        L.CD_UNIDADE
                     FROM SIMP.dbo.PONTO_MEDICAO PM
                     LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                     WHERE PM.CD_PONTO_MEDICAO = :pontoId";
        $stmtPonto = $pdoSIMP->prepare($sqlPonto);
        $stmtPonto->execute([':pontoId' => $pontoId]);
        $ponto = $stmtPonto->fetch(PDO::FETCH_ASSOC);
        
        if (!$ponto) {
            throw new Exception('Ponto de medição não encontrado');
        }
        
        $tipoMedidor = $ponto['ID_TIPO_MEDIDOR'];
        $tiposMedidorUsados[$tipoMedidor] = $nomesTipo[$tipoMedidor] ?? 'Desconhecido';
        $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';
        $unidade = $unidadesPorTipo[$tipoMedidor] ?? 'L/s';
        $unidadePrincipal = $unidade;
        
        $letraTipo = $letrasTipo[$tipoMedidor] ?? 'X';
        $codigoPonto = ($ponto['CD_LOCALIDADE'] ?? '000') . '-' . 
                      str_pad($pontoId, 6, '0', STR_PAD_LEFT) . '-' . 
                      $letraTipo . '-' . 
                      ($ponto['CD_UNIDADE'] ?? '00');

        // Se ID_TIPO_MEDIDOR = 6, buscar valor máximo do dia com horário (query otimizada)
        if ($tipoMedidor == 6) {
            // Query otimizada usando CAST AS DATE para melhor uso de índices
            $sqlRegistros = "SELECT 
                                CONVERT(VARCHAR(10), DT_DIA, 120) AS DT_LEITURA,
                                VALOR,
                                HORARIO_MAX,
                                QTD_REGISTROS,
                                QTD_TRATADOS
                            FROM (
                                SELECT 
                                    CAST(DT_LEITURA AS DATE) AS DT_DIA,
                                    MAX({$coluna}) AS VALOR,
                                    COUNT(*) AS QTD_REGISTROS,
                                    SUM(CASE WHEN ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 ELSE 0 END) AS QTD_TRATADOS
                                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                                WHERE CD_PONTO_MEDICAO = :cdPonto
                                  AND DT_LEITURA >= :dataInicio
                                  AND DT_LEITURA <= :dataFim
                                  AND {$coluna} IS NOT NULL
                                  AND ID_SITUACAO = 1
                                GROUP BY CAST(DT_LEITURA AS DATE)
                            ) dias
                            OUTER APPLY (
                                SELECT TOP 1 CONVERT(VARCHAR(5), DT_LEITURA, 108) AS HORARIO_MAX
                                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                                WHERE CD_PONTO_MEDICAO = :cdPonto2
                                  AND DT_LEITURA >= dias.DT_DIA
                                  AND DT_LEITURA < DATEADD(DAY, 1, dias.DT_DIA)
                                  AND {$coluna} = dias.VALOR
                                  AND ID_SITUACAO = 1
                                ORDER BY DT_LEITURA
                            ) horario
                            ORDER BY DT_DIA";
            
            $stmtRegistros = $pdoSIMP->prepare($sqlRegistros);
            $stmtRegistros->execute([
                ':cdPonto' => $pontoId,
                ':cdPonto2' => $pontoId,
                ':dataInicio' => $dataInicio,
                ':dataFim' => $dataFim . ' 23:59:59'
            ]);
            
            $temRegistros = false;
            while ($registro = $stmtRegistros->fetch(PDO::FETCH_ASSOC)) {
                $temRegistros = true;
                $dados[] = [
                    'data' => $registro['DT_LEITURA'],
                    'cd_ponto' => $pontoId,
                    'ponto_codigo' => $codigoPonto,
                    'ponto_nome' => $ponto['PONTO_NOME'],
                    'valor' => floatval($registro['VALOR']),
                    'qtd_registros' => intval($registro['QTD_REGISTROS']),
                    'qtd_tratados' => intval($registro['QTD_TRATADOS']),
                    'horario_max' => $registro['HORARIO_MAX'],
                    'tipo_medidor' => $tipoMedidor,
                    'operacao' => 1,
                    'fluxo' => 'Entrada'
                ];
                $todosValores[] = floatval($registro['VALOR']);
            }
        } else {
            $sqlRegistros = "SELECT 
                                CONVERT(VARCHAR(10), DT_LEITURA, 120) AS DT_LEITURA,
                                SUM(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} ELSE 0 END) / 1440.0 AS VALOR,
                                MIN(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MIN,
                                MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MAX,
                                SUM(CASE WHEN ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS QTD_REGISTROS,
                                SUM(CASE WHEN ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 ELSE 0 END) AS QTD_TRATADOS
                             FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                             WHERE CD_PONTO_MEDICAO = :cdPonto
                               AND DT_LEITURA >= :dataInicio
                               AND DT_LEITURA <= :dataFim
                               AND {$coluna} IS NOT NULL
                             GROUP BY CONVERT(VARCHAR(10), DT_LEITURA, 120)
                             HAVING SUM(CASE WHEN ID_SITUACAO = 1 THEN 1 ELSE 0 END) > 0
                             ORDER BY DT_LEITURA";
            
            $stmtRegistros = $pdoSIMP->prepare($sqlRegistros);
            $stmtRegistros->execute([
                ':cdPonto' => $pontoId,
                ':dataInicio' => $dataInicio,
                ':dataFim' => $dataFim . ' 23:59:59'
            ]);
            
            $temRegistros = false;
            while ($registro = $stmtRegistros->fetch(PDO::FETCH_ASSOC)) {
                $temRegistros = true;
                $valor = floatval($registro['VALOR']);
                $qtdRegistros = intval($registro['QTD_REGISTROS']);
                $qtdTratados = intval($registro['QTD_TRATADOS']);
                
                $dados[] = [
                    'data' => $registro['DT_LEITURA'],
                    'cd_ponto' => $pontoId,
                    'ponto_codigo' => $codigoPonto,
                    'ponto_nome' => $ponto['PONTO_NOME'],
                    'valor' => $valor,
                    'valor_min' => $registro['VALOR_MIN'] !== null ? floatval($registro['VALOR_MIN']) : null,
                    'valor_max' => $registro['VALOR_MAX'] !== null ? floatval($registro['VALOR_MAX']) : null,
                    'qtd_registros' => $qtdRegistros,
                    'qtd_tratados' => $qtdTratados,
                    'horario_max' => null,
                    'tipo_medidor' => $tipoMedidor,
                    'operacao' => 1,
                    'fluxo' => 'Entrada'
                ];
                
                $todosValores[] = $valor;
            }
        }
        
        if (!$temRegistros) {
            $pontosInfo[] = [
                'cd_ponto' => $pontoId,
                'ponto_codigo' => $codigoPonto,
                'ponto_nome' => $ponto['PONTO_NOME'],
                'tipo_medidor' => $tipoMedidor,
                'fluxo' => 'Entrada',
                'operacao' => 1
            ];
        }
    }
    // MODO 2: Busca por entidade (com ou sem filtro de ponto)
    else {
        $sqlValor = "SELECT ID_FLUXO, CD_ENTIDADE_VALOR_ID FROM SIMP.dbo.ENTIDADE_VALOR WHERE CD_CHAVE = :valorId";
        $stmtValor = $pdoSIMP->prepare($sqlValor);
        $stmtValor->execute([':valorId' => $valorId]);
        $valorInfo = $stmtValor->fetch(PDO::FETCH_ASSOC);
        
        if (empty($valorEntidadeId)) {
            $valorEntidadeId = $valorInfo['CD_ENTIDADE_VALOR_ID'] ?? '';
        }

        $sqlTodosValores = "SELECT CD_CHAVE, ID_FLUXO, DS_NOME 
                            FROM SIMP.dbo.ENTIDADE_VALOR 
                            WHERE CD_ENTIDADE_VALOR_ID = :valorEntidadeId";
        $stmtTodosValores = $pdoSIMP->prepare($sqlTodosValores);
        $stmtTodosValores->execute([':valorEntidadeId' => $valorEntidadeId]);
        $todosValoresEntidade = $stmtTodosValores->fetchAll(PDO::FETCH_ASSOC);

        foreach ($todosValoresEntidade as $valorEntidade) {
            $cdValor = $valorEntidade['CD_CHAVE'];
            $fluxoId = $valorEntidade['ID_FLUXO'];
            $fluxoNome = $fluxoNomes[$fluxoId] ?? 'Não definido';

            // Tentar query com NR_ORDEM, se falhar usar sem
            try {
                $sqlPontos = "SELECT 
                                EVI.CD_PONTO_MEDICAO,
                                PM.DS_NOME AS PONTO_NOME,
                                PM.ID_TIPO_MEDIDOR,
                                L.CD_LOCALIDADE,
                                L.CD_UNIDADE,
                                EVI.ID_OPERACAO,
                                EVI.NR_ORDEM
                              FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
                              INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON EVI.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
                              LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                              WHERE EVI.CD_ENTIDADE_VALOR = :cdValor";
                
                if ($pontoId > 0) {
                    $sqlPontos .= " AND EVI.CD_PONTO_MEDICAO = :pontoId";
                }
                
                $sqlPontos .= " ORDER BY ISNULL(EVI.NR_ORDEM, 999999), PM.DS_NOME";
                
                $stmtPontos = $pdoSIMP->prepare($sqlPontos);
                $params = [':cdValor' => $cdValor];
                if ($pontoId > 0) {
                    $params[':pontoId'] = $pontoId;
                }
                $stmtPontos->execute($params);
                $pontos = $stmtPontos->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Coluna NR_ORDEM não existe, usar query sem ela
                $sqlPontos = "SELECT 
                                EVI.CD_PONTO_MEDICAO,
                                PM.DS_NOME AS PONTO_NOME,
                                PM.ID_TIPO_MEDIDOR,
                                L.CD_LOCALIDADE,
                                L.CD_UNIDADE,
                                EVI.ID_OPERACAO
                              FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
                              INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON EVI.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
                              LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                              WHERE EVI.CD_ENTIDADE_VALOR = :cdValor";
                
                if ($pontoId > 0) {
                    $sqlPontos .= " AND EVI.CD_PONTO_MEDICAO = :pontoId";
                }
                
                $sqlPontos .= " ORDER BY PM.DS_NOME";
                
                $stmtPontos = $pdoSIMP->prepare($sqlPontos);
                $params = [':cdValor' => $cdValor];
                if ($pontoId > 0) {
                    $params[':pontoId'] = $pontoId;
                }
                $stmtPontos->execute($params);
                $pontos = $stmtPontos->fetchAll(PDO::FETCH_ASSOC);
            }

            $indiceOrdem = 0;
            foreach ($pontos as $ponto) {
                $indiceOrdem++;
                $cdPonto = $ponto['CD_PONTO_MEDICAO'];
                $tipoMedidor = $ponto['ID_TIPO_MEDIDOR'];
                $tiposMedidorUsados[$tipoMedidor] = $nomesTipo[$tipoMedidor] ?? 'Desconhecido';
                $operacao = $ponto['ID_OPERACAO'] ?? 1;
                $ordemPonto = $ponto['NR_ORDEM'] ?? $indiceOrdem;
                
                $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';
                $unidade = $unidadesPorTipo[$tipoMedidor] ?? 'L/s';
                
                if (empty($unidadePrincipal)) {
                    $unidadePrincipal = $unidade;
                }

                $letraTipo = $letrasTipo[$tipoMedidor] ?? 'X';
                $codigoPonto = ($ponto['CD_LOCALIDADE'] ?? '000') . '-' . 
                              str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-' . 
                              $letraTipo . '-' . 
                              ($ponto['CD_UNIDADE'] ?? '00');

                // Se ID_TIPO_MEDIDOR = 6, buscar valor máximo do dia com horário (query otimizada)
                if ($tipoMedidor == 6) {
                    $sqlRegistros = "SELECT 
                                        CONVERT(VARCHAR(10), DT_DIA, 120) AS DT_LEITURA,
                                        VALOR,
                                        HORARIO_MAX,
                                        QTD_REGISTROS,
                                        QTD_TRATADOS
                                    FROM (
                                        SELECT 
                                            CAST(DT_LEITURA AS DATE) AS DT_DIA,
                                            MAX({$coluna}) AS VALOR,
                                            COUNT(*) AS QTD_REGISTROS,
                                            SUM(CASE WHEN ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 ELSE 0 END) AS QTD_TRATADOS
                                        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                                        WHERE CD_PONTO_MEDICAO = :cdPonto
                                          AND DT_LEITURA >= :dataInicio
                                          AND DT_LEITURA <= :dataFim
                                          AND {$coluna} IS NOT NULL
                                          AND ID_SITUACAO = 1
                                        GROUP BY CAST(DT_LEITURA AS DATE)
                                    ) dias
                                    OUTER APPLY (
                                        SELECT TOP 1 CONVERT(VARCHAR(5), DT_LEITURA, 108) AS HORARIO_MAX
                                        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                                        WHERE CD_PONTO_MEDICAO = :cdPonto2
                                          AND DT_LEITURA >= dias.DT_DIA
                                          AND DT_LEITURA < DATEADD(DAY, 1, dias.DT_DIA)
                                          AND {$coluna} = dias.VALOR
                                          AND ID_SITUACAO = 1
                                        ORDER BY DT_LEITURA
                                    ) horario
                                    ORDER BY DT_DIA";
                    
                    $stmtRegistros = $pdoSIMP->prepare($sqlRegistros);
                    $stmtRegistros->execute([
                        ':cdPonto' => $cdPonto,
                        ':cdPonto2' => $cdPonto,
                        ':dataInicio' => $dataInicio,
                        ':dataFim' => $dataFim . ' 23:59:59'
                    ]);
                    
                    $temRegistros = false;
                    while ($registro = $stmtRegistros->fetch(PDO::FETCH_ASSOC)) {
                        $temRegistros = true;
                        $dados[] = [
                            'data' => $registro['DT_LEITURA'],
                            'cd_ponto' => $cdPonto,
                            'ponto_codigo' => $codigoPonto,
                            'ponto_nome' => $ponto['PONTO_NOME'],
                            'valor' => floatval($registro['VALOR']),
                            'qtd_registros' => intval($registro['QTD_REGISTROS']),
                            'qtd_tratados' => intval($registro['QTD_TRATADOS']),
                            'horario_max' => $registro['HORARIO_MAX'],
                            'tipo_medidor' => $tipoMedidor,
                            'operacao' => $operacao,
                            'fluxo' => $fluxoNome,
                            'ordem' => $ordemPonto
                        ];
                        $todosValores[] = floatval($registro['VALOR']);
                    }
                } else {
                    $sqlRegistros = "SELECT 
                                        CONVERT(VARCHAR(10), DT_LEITURA, 120) AS DT_LEITURA,
                                        SUM(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} ELSE 0 END) / 1440.0 AS VALOR,
                                        MIN(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MIN,
                                        MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MAX,
                                        SUM(CASE WHEN ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS QTD_REGISTROS,
                                        SUM(CASE WHEN ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 ELSE 0 END) AS QTD_TRATADOS
                                     FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                                     WHERE CD_PONTO_MEDICAO = :cdPonto
                                       AND DT_LEITURA >= :dataInicio
                                       AND DT_LEITURA <= :dataFim
                                       AND {$coluna} IS NOT NULL
                                     GROUP BY CONVERT(VARCHAR(10), DT_LEITURA, 120)
                                     HAVING SUM(CASE WHEN ID_SITUACAO = 1 THEN 1 ELSE 0 END) > 0
                                     ORDER BY DT_LEITURA";
                    
                    $stmtRegistros = $pdoSIMP->prepare($sqlRegistros);
                    $stmtRegistros->execute([
                        ':cdPonto' => $cdPonto,
                        ':dataInicio' => $dataInicio,
                        ':dataFim' => $dataFim . ' 23:59:59'
                    ]);

                    $temRegistros = false;
                    while ($registro = $stmtRegistros->fetch(PDO::FETCH_ASSOC)) {
                        $temRegistros = true;
                        $valor = floatval($registro['VALOR']);
                        $qtdRegistros = intval($registro['QTD_REGISTROS']);
                        $qtdTratados = intval($registro['QTD_TRATADOS']);
                        
                        $dados[] = [
                            'data' => $registro['DT_LEITURA'],
                            'cd_ponto' => $cdPonto,
                            'ponto_codigo' => $codigoPonto,
                            'ponto_nome' => $ponto['PONTO_NOME'],
                            'valor' => $valor,
                            'valor_min' => $registro['VALOR_MIN'] !== null ? floatval($registro['VALOR_MIN']) : null,
                            'valor_max' => $registro['VALOR_MAX'] !== null ? floatval($registro['VALOR_MAX']) : null,
                            'qtd_registros' => $qtdRegistros,
                            'qtd_tratados' => $qtdTratados,
                            'horario_max' => null,
                            'tipo_medidor' => $tipoMedidor,
                            'operacao' => $operacao,
                            'fluxo' => $fluxoNome,
                            'ordem' => $ordemPonto
                        ];
                        
                        $todosValores[] = $valor;
                    }
                }
                
                if (!$temRegistros) {
                    $pontosInfo[] = [
                        'cd_ponto' => $cdPonto,
                        'ponto_codigo' => $codigoPonto,
                        'ponto_nome' => $ponto['PONTO_NOME'],
                        'tipo_medidor' => $tipoMedidor,
                        'fluxo' => $fluxoNome,
                        'operacao' => $operacao,
                        'ordem' => $ordemPonto
                    ];
                }
            }
        }
    }

    $resumo = [];
    if (!empty($todosValores)) {
        $resumo = [
            'total' => array_sum($todosValores),
            'media' => array_sum($todosValores) / count($todosValores),
            'minimo' => min($todosValores),
            'maximo' => max($todosValores),
            'quantidade' => count($todosValores)
        ];
    }

    // Montar string dos tipos de medidor
    $tiposMedidorStr = implode(' | ', array_values($tiposMedidorUsados));

    echo json_encode([
        'success' => true,
        'dados' => $dados,
        'pontosInfo' => $pontosInfo,
        'resumo' => $resumo,
        'unidade' => $unidadePrincipal,
        'tiposMedidor' => $tiposMedidorStr,
        'periodo' => [
            'inicio' => $dataInicio,
            'fim' => $dataFim
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'dados' => [],
        'pontosInfo' => [],
        'resumo' => []
    ]);
}