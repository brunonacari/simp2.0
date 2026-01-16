<?php
/**
 * SIMP - Consultas para Dashboard de Monitoramento
 * Utiliza as views e SPs criadas para análise de dados
 * 
 * Arquivo: bd/dashboard/consultarResumo.php
 */

header('Content-Type: application/json; charset=utf-8');

include_once '../conexao.php';

// Parâmetros
$acao = $_GET['acao'] ?? 'resumo_geral';
$dias = intval($_GET['dias'] ?? 7);
$cdPonto = isset($_GET['cdPonto']) ? intval($_GET['cdPonto']) : null;

try {
    $resultado = [];
    
    switch ($acao) {
        
        // ============================================
        // CARDS DO DASHBOARD - VISÃO GERAL
        // ============================================
        case 'resumo_geral':
            $sql = "SELECT * FROM VW_DASHBOARD_RESUMO_GERAL";
            $stmt = $pdoSIMP->query($sql);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        
        // ============================================
        // PONTOS CRÍTICOS (com anomalias/tratamentos)
        // ============================================
        case 'pontos_criticos':
            $sql = "SELECT * FROM VW_PONTOS_CRITICOS ORDER BY SCORE_CRITICIDADE DESC";
            $stmt = $pdoSIMP->query($sql);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        // ============================================
        // TENDÊNCIAS DOS PONTOS
        // ============================================
        case 'tendencias':
            $sql = "SELECT * FROM VW_TENDENCIAS_PONTOS ORDER BY ABS(DESVIO_PERCENTUAL) DESC";
            $stmt = $pdoSIMP->query($sql);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        // ============================================
        // ANOMALIAS RECENTES
        // ============================================
        case 'anomalias':
            $sql = "SELECT * FROM VW_ANOMALIAS_RECENTES ORDER BY DT_MEDICAO DESC";
            $stmt = $pdoSIMP->query($sql);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        // ============================================
        // TRATAMENTOS REALIZADOS
        // ============================================
        case 'tratamentos':
            $sql = "SELECT * FROM VW_TRATAMENTOS_REALIZADOS ORDER BY DT_MEDICAO DESC";
            $stmt = $pdoSIMP->query($sql);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        // ============================================
        // HORAS PROBLEMÁTICAS (padrão temporal)
        // ============================================
        case 'horas_problematicas':
            $sql = "SELECT * FROM VW_HORAS_PROBLEMATICAS ORDER BY PERC_ANOMALIA DESC";
            $stmt = $pdoSIMP->query($sql);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        // ============================================
        // DETALHAMENTO HORÁRIO DE UM PONTO/DIA
        // ============================================
        case 'detalhe_horario':
            $data = $_GET['data'] ?? date('Y-m-d', strtotime('-1 day'));
            
            $sql = "SELECT 
                        MRH.*,
                        PM.DS_NOME AS NOME_PONTO
                    FROM MEDICAO_RESUMO_HORARIO MRH
                    LEFT JOIN PONTO_MEDICAO PM ON MRH.CD_PONTO_MEDICAO = PM.CD_CHAVE
                    WHERE CAST(MRH.DT_HORA AS DATE) = :data";
            
            if ($cdPonto) {
                $sql .= " AND MRH.CD_PONTO_MEDICAO = :cdPonto";
            }
            
            $sql .= " ORDER BY MRH.CD_PONTO_MEDICAO, MRH.NR_HORA";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->bindParam(':data', $data);
            if ($cdPonto) {
                $stmt->bindParam(':cdPonto', $cdPonto, PDO::PARAM_INT);
            }
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        // ============================================
        // CONTEXTO PARA IA (texto formatado)
        // ============================================
        case 'contexto_ia':
            $sql = "EXEC SP_CONTEXTO_IA_TEXTO @DIAS_ANALISE = :dias";
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->bindParam(':dias', $dias, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $resultado = ['contexto' => $row['CONTEXTO_IA'] ?? ''];
            break;
        
        // ============================================
        // CONTEXTO DETALHADO PARA IA (múltiplos resultsets)
        // ============================================
        case 'contexto_ia_completo':
            $sql = "EXEC SP_GERAR_CONTEXTO_IA @DIAS_ANALISE = :dias";
            if ($cdPonto) {
                $sql .= ", @CD_PONTO_MEDICAO = :cdPonto";
            }
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->bindParam(':dias', $dias, PDO::PARAM_INT);
            if ($cdPonto) {
                $stmt->bindParam(':cdPonto', $cdPonto, PDO::PARAM_INT);
            }
            $stmt->execute();
            
            // Coletar todos os resultsets
            $resultado = [];
            $secaoAtual = '';
            
            do {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    // Verificar se é uma seção (tem coluna SECAO)
                    if (isset($rows[0]['SECAO'])) {
                        $secaoAtual = $rows[0]['SECAO'];
                    } else {
                        $resultado[$secaoAtual] = $rows;
                    }
                }
            } while ($stmt->nextRowset());
            
            break;
        
        // ============================================
        // GRÁFICO DE EVOLUÇÃO (últimos N dias de um ponto)
        // ============================================
        case 'evolucao_ponto':
            if (!$cdPonto) {
                throw new Exception('Parâmetro cdPonto é obrigatório');
            }
            
            $sql = "SELECT 
                        DT_MEDICAO,
                        VL_MEDIA_DIARIA,
                        VL_MIN_DIARIO,
                        VL_MAX_DIARIO,
                        VL_MEDIA_HISTORICA,
                        VL_DESVIO_HISTORICO,
                        FL_ANOMALIA,
                        ID_SITUACAO,
                        QTD_REGISTROS
                    FROM MEDICAO_RESUMO_DIARIO
                    WHERE CD_PONTO_MEDICAO = :cdPonto
                      AND DT_MEDICAO >= DATEADD(DAY, -:dias, CAST(GETDATE() AS DATE))
                    ORDER BY DT_MEDICAO";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->bindParam(':cdPonto', $cdPonto, PDO::PARAM_INT);
            $stmt->bindParam(':dias', $dias, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        // ============================================
        // RANKING DE PONTOS POR TIPO DE ANOMALIA
        // ============================================
        case 'ranking_anomalias':
            $tipoAnomalia = $_GET['tipo'] ?? null;
            
            $sql = "SELECT 
                        MRD.CD_PONTO_MEDICAO,
                        PM.DS_NOME AS NOME_PONTO,
                        COUNT(*) AS OCORRENCIAS,
                        STRING_AGG(CONVERT(VARCHAR, MRD.DT_MEDICAO, 103), ', ') AS DATAS
                    FROM MEDICAO_RESUMO_DIARIO MRD
                    LEFT JOIN PONTO_MEDICAO PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
                    WHERE MRD.FL_ANOMALIA = 1
                      AND MRD.DT_MEDICAO >= DATEADD(DAY, -:dias, CAST(GETDATE() AS DATE))";
            
            if ($tipoAnomalia) {
                $sql .= " AND MRD.DS_ANOMALIAS LIKE :tipo";
            }
            
            $sql .= " GROUP BY MRD.CD_PONTO_MEDICAO, PM.DS_NOME
                      ORDER BY COUNT(*) DESC";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->bindParam(':dias', $dias, PDO::PARAM_INT);
            if ($tipoAnomalia) {
                $tipoLike = '%' . $tipoAnomalia . '%';
                $stmt->bindParam(':tipo', $tipoLike);
            }
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        default:
            throw new Exception('Ação não reconhecida: ' . $acao);
    }
    
    echo json_encode([
        'sucesso' => true,
        'acao' => $acao,
        'dados' => $resultado
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}