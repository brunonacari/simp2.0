<?php
/**
 * SIMP - Importacao de Planilha de Registro de Vazao e Pressao
 * CORRIGIDO: Converte data para formato YYYY-MM-DD
 */

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

/**
 * Converte data de DD/MM/YYYY para YYYY-MM-DD
 */
function converterDataParaISO($data) {
    if (empty($data)) return $data;
    
    // Se já está no formato YYYY-MM-DD, retornar como está
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return $data;
    }
    
    // Se está no formato DD/MM/YYYY, converter
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    
    return $data;
}

try {
    session_start();
    
    if (!isset($_SESSION['sucesso']) || $_SESSION['sucesso'] != 1) {
        die(json_encode(['success' => false, 'message' => 'Nao autenticado']));
    }
    
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;
    if (!$cdUsuario) {
        die(json_encode(['success' => false, 'message' => 'Usuario nao identificado']));
    }
    
    $json = file_get_contents('php://input');
    
    if (empty($json)) {
        die(json_encode(['success' => false, 'message' => 'JSON vazio']));
    }
    
    $dados = json_decode($json, true);
    
    if (!$dados || !isset($dados['registros']) || empty($dados['registros'])) {
        die(json_encode(['success' => false, 'message' => 'Nenhum registro recebido']));
    }
    
    // Parametros
    $sobrescrever = isset($dados['sobrescrever']) && $dados['sobrescrever'] === true;
    $dataEventoMedicao = isset($dados['dataEventoMedicao']) ? trim($dados['dataEventoMedicao']) : null;
    $tipoVazao = isset($dados['tipoVazao']) ? (int)$dados['tipoVazao'] : 2;
    $numOS = isset($dados['numOS']) && trim($dados['numOS']) !== '' ? substr(trim($dados['numOS']), 0, 10) : null;
    $houveOcorrencia = isset($dados['houveOcorrencia']) && $dados['houveOcorrencia'] == 1 ? 1 : 0;
    $observacao = isset($dados['observacao']) && trim($dados['observacao']) !== '' ? substr(trim($dados['observacao']), 0, 200) : null;
    
    if (!$dataEventoMedicao) {
        die(json_encode(['success' => false, 'message' => 'Data do Evento de Medicao obrigatoria']));
    }
    
    include_once '../conexao.php';
    
    if (!isset($pdoSIMP)) {
        die(json_encode(['success' => false, 'message' => 'Erro conexao']));
    }
    
    $registros = $dados['registros'];
    
    // Agrupar por ponto
    $dadosPorPonto = [];
    foreach ($registros as $reg) {
        $p = $reg['pontoMedicao'];
        $dadosPorPonto[$p][] = $reg;
    }
    
    // Buscar pontos no BD por CD_PONTO_MEDICAO direto
    $pontosMap = [];
    foreach (array_keys($dadosPorPonto) as $cod) {
        $codInt = (int)$cod;
        
        $sql = "SELECT CD_PONTO_MEDICAO, DS_NOME, ID_TIPO_MEDIDOR, OP_PERIODICIDADE_LEITURA 
                FROM PONTO_MEDICAO 
                WHERE CD_PONTO_MEDICAO = ?";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([$codInt]);
        $pt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pt) {
            $pontosMap[$cod] = $pt;
        }
    }
    
    // Verificar pontos nao encontrados
    $pontosNaoEncontrados = [];
    foreach (array_keys($dadosPorPonto) as $cod) {
        if (!isset($pontosMap[$cod])) {
            $pontosNaoEncontrados[] = $cod;
        }
    }
    
    if (!empty($pontosNaoEncontrados)) {
        die(json_encode(['success' => false, 'message' => 'Ponto(s) nao encontrado(s): ' . implode(', ', $pontosNaoEncontrados)]));
    }
    
    $erros = [];
    $avisos = [];
    $resumo = [];
    $totalRegistros = 0;
    $totalSobrescritos = 0;
    
    // Iniciar transacao
    $pdoSIMP->beginTransaction();
    
    $nomesTipoMedidor = [
        1 => 'Macromedidor',
        2 => 'Estacao Pitometrica',
        4 => 'Medidor de Pressao',
        6 => 'Nivel Reservatorio',
        8 => 'Hidrometro'
    ];
    
    foreach ($dadosPorPonto as $cod => $regs) {
        $pt = $pontosMap[$cod];
        $cdPonto = $pt['CD_PONTO_MEDICAO'];
        $nomePonto = $cdPonto . ' - ' . trim($pt['DS_NOME']);
        $tipoMedidor = (int)$pt['ID_TIPO_MEDIDOR'];
        
        // Tipo 6 nao permitido
        if ($tipoMedidor === 6) {
            $erros[] = "Ponto $nomePonto: Nao e possivel importar para Nivel de Reservatorio";
            continue;
        }
        
        $isMacromedidor = in_array($tipoMedidor, [1, 2, 8]);
        $isMedidorPressao = ($tipoMedidor === 4);
        $nomeTipo = $nomesTipoMedidor[$tipoMedidor] ?? "Tipo $tipoMedidor";
        
        // Validar registros
        $registrosValidos = [];
        foreach ($regs as $reg) {
            $linha = isset($reg['linha']) ? $reg['linha'] : '?';
            
            if ($isMacromedidor) {
                $temVolume = isset($reg['volume']) && $reg['volume'] !== null && $reg['volume'] !== '';
                $temPeriodo = isset($reg['periodo']) && $reg['periodo'] !== null && $reg['periodo'] !== '';
                
                if (!$temVolume || !$temPeriodo) {
                    $erros[] = "Linha $linha - Ponto $nomePonto: VOLUME e PERIODO obrigatorios para $nomeTipo";
                    continue;
                }
            } elseif ($isMedidorPressao) {
                $temPressao = isset($reg['pressao']) && $reg['pressao'] !== null && $reg['pressao'] !== '';
                
                if (!$temPressao) {
                    $erros[] = "Linha $linha - Ponto $nomePonto: PRESSAO obrigatoria para $nomeTipo";
                    continue;
                }
            }
            
            $registrosValidos[] = $reg;
        }
        
        if (empty($registrosValidos)) {
            $resumo[] = [
                'ponto' => $nomePonto,
                'registros' => 0,
                'duplicados' => 0,
                'sobrescritos' => 0,
                'rejeitados' => count($regs)
            ];
            continue;
        }
        
        $countPonto = 0;
        $countDuplicados = 0;
        $countSobrescritos = 0;
        
        foreach ($registrosValidos as $reg) {
            // Converter data para ISO
            $dataISO = converterDataParaISO($reg['data']);
            $dtLeitura = $dataISO . ' ' . $reg['hora'];
            
            // Calcular vazao efetiva
            $vazaoEfetiva = null;
            if ($isMacromedidor && isset($reg['volume']) && isset($reg['periodo']) && $reg['periodo'] > 0) {
                $vazaoEfetiva = ($reg['volume'] * 1000) / $reg['periodo'];
            }
            
            // Verificar se existe
            $sqlVerifica = "SELECT CD_CHAVE FROM REGISTRO_VAZAO_PRESSAO 
                            WHERE CD_PONTO_MEDICAO = ? AND DT_LEITURA = ? AND ID_SITUACAO = 1";
            $stmtV = $pdoSIMP->prepare($sqlVerifica);
            $stmtV->execute([$cdPonto, $dtLeitura]);
            $existe = $stmtV->fetch(PDO::FETCH_ASSOC);
            
            if ($existe) {
                if ($sobrescrever) {
                    // Inativar registro existente
                    $sqlInativar = "UPDATE REGISTRO_VAZAO_PRESSAO 
                                    SET ID_SITUACAO = 2,
                                        CD_USUARIO_ULTIMA_ATUALIZACAO = ?,
                                        DT_ULTIMA_ATUALIZACAO = GETDATE()
                                    WHERE CD_CHAVE = ?";
                    $stmtIn = $pdoSIMP->prepare($sqlInativar);
                    $stmtIn->execute([$cdUsuario, $existe['CD_CHAVE']]);
                    $countSobrescritos++;
                } else {
                    $countDuplicados++;
                    continue;
                }
            }
            
            // Inserir novo registro
            $sqlInsert = "INSERT INTO REGISTRO_VAZAO_PRESSAO (
                CD_PONTO_MEDICAO, DT_EVENTO_MEDICAO, ID_TIPO_REGISTRO, ID_TIPO_MEDICAO,
                DT_LEITURA, VL_VAZAO_EFETIVA, VL_PRESSAO, VL_TEMP_AGUA, VL_TEMP_AMBIENTE,
                VL_VOLUME, VL_PERIODO_MEDICAO_VOLUME, ID_SITUACAO, ID_TIPO_VAZAO,
                DS_OBSERVACAO, HOUVE_OCORRENCIA, NUM_OS,
                CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO
            ) VALUES (?, ?, 4, 2, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, GETDATE())";
            
            $stmtIns = $pdoSIMP->prepare($sqlInsert);
            $stmtIns->execute([
                $cdPonto,
                $dataEventoMedicao,
                $dtLeitura,
                $vazaoEfetiva,
                isset($reg['pressao']) ? $reg['pressao'] : null,
                isset($reg['tempAgua']) ? $reg['tempAgua'] : null,
                isset($reg['tempAmb']) ? $reg['tempAmb'] : null,
                isset($reg['volume']) ? $reg['volume'] : null,
                isset($reg['periodo']) ? $reg['periodo'] : null,
                $tipoVazao,
                $observacao,
                $houveOcorrencia,
                $numOS,
                $cdUsuario,
                $cdUsuario
            ]);
            
            $countPonto++;
        }
        
        // Avisos
        if ($countDuplicados > 0 && !$sobrescrever) {
            $avisos[] = "Ponto $nomePonto: $countDuplicados registro(s) ignorados (ja existentes)";
        }
        if ($countSobrescritos > 0) {
            $avisos[] = "Ponto $nomePonto: $countSobrescritos registro(s) sobrescritos";
        }
        
        $resumo[] = [
            'ponto' => $nomePonto,
            'registros' => $countPonto,
            'duplicados' => $countDuplicados,
            'sobrescritos' => $countSobrescritos,
            'rejeitados' => count($regs) - count($registrosValidos)
        ];
        
        $totalRegistros += $countPonto;
        $totalSobrescritos += $countSobrescritos;
    }
    
    // Commit
    $pdoSIMP->commit();
    
    // Retornar
    if ($totalRegistros > 0) {
        echo json_encode([
            'success' => true,
            'resumo' => $resumo,
            'totalRegistros' => $totalRegistros,
            'totalSobrescritos' => $totalSobrescritos,
            'erros' => !empty($erros) ? $erros : null,
            'avisos' => !empty($avisos) ? $avisos : null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum registro importado',
            'erros' => $erros
        ]);
    }
    
} catch (PDOException $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erro BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}