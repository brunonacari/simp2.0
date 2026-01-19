<?php
/**
 * SIMP - Verificar Conflitos de Importacao
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
    
    $json = file_get_contents('php://input');
    
    if (empty($json)) {
        die(json_encode(['success' => false, 'message' => 'JSON vazio']));
    }
    
    $dados = json_decode($json, true);
    
    if (!$dados || !isset($dados['registros'])) {
        die(json_encode(['success' => false, 'message' => 'Dados invalidos']));
    }
    
    include_once '../conexao.php';
    
    if (!isset($pdoSIMP)) {
        die(json_encode(['success' => false, 'message' => 'Erro conexao']));
    }
    
    $registros = $dados['registros'];
    $conflitos = [];
    $pontosConflito = [];
    
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
        
        $sql = "SELECT CD_PONTO_MEDICAO, DS_NOME, ID_TIPO_MEDIDOR 
                FROM PONTO_MEDICAO 
                WHERE CD_PONTO_MEDICAO = ?";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([$codInt]);
        $pt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pt) {
            $pontosMap[$cod] = $pt;
        }
    }
    
    // Verificar conflitos
    foreach ($dadosPorPonto as $cod => $regs) {
        if (!isset($pontosMap[$cod])) continue;
        
        $cdPonto = $pontosMap[$cod]['CD_PONTO_MEDICAO'];
        $nomePonto = $pontosMap[$cod]['DS_NOME'];
        
        foreach ($regs as $reg) {
            // Converter data para ISO (YYYY-MM-DD)
            $dataISO = converterDataParaISO($reg['data']);
            $dt = $dataISO . ' ' . $reg['hora'];
            
            $sqlV = "SELECT CD_CHAVE, VL_VAZAO_EFETIVA, VL_PRESSAO, VL_VOLUME, VL_PERIODO_MEDICAO_VOLUME 
                     FROM REGISTRO_VAZAO_PRESSAO 
                     WHERE CD_PONTO_MEDICAO = ? AND DT_LEITURA = ? AND ID_SITUACAO = 1";
            
            $stV = $pdoSIMP->prepare($sqlV);
            $stV->execute([$cdPonto, $dt]);
            $ex = $stV->fetch(PDO::FETCH_ASSOC);
            
            if ($ex) {
                // Calcular vazao nova se tiver volume e periodo
                $vazaoNova = null;
                if (isset($reg['volume']) && isset($reg['periodo']) && $reg['periodo'] > 0) {
                    $vazaoNova = ($reg['volume'] * 1000) / $reg['periodo'];
                }
                
                $conflitos[] = [
                    'cdPonto' => $cdPonto,
                    'codigoPonto' => $cdPonto . ' - ' . trim($nomePonto),
                    'dtLeitura' => $dt,
                    'data' => $dataISO, // Data no formato YYYY-MM-DD
                    'hora' => $reg['hora'],
                    'valorExistente' => [
                        'cdChave' => $ex['CD_CHAVE'],
                        'vazao' => $ex['VL_VAZAO_EFETIVA'],
                        'pressao' => $ex['VL_PRESSAO'],
                        'volume' => $ex['VL_VOLUME'],
                        'periodo' => $ex['VL_PERIODO_MEDICAO_VOLUME']
                    ],
                    'valorNovo' => [
                        'vazao' => $vazaoNova,
                        'pressao' => isset($reg['pressao']) ? $reg['pressao'] : null,
                        'volume' => isset($reg['volume']) ? $reg['volume'] : null,
                        'periodo' => isset($reg['periodo']) ? $reg['periodo'] : null
                    ]
                ];
                
                $chaveConflito = $cdPonto . ' - ' . trim($nomePonto);
                if (!isset($pontosConflito[$chaveConflito])) {
                    $pontosConflito[$chaveConflito] = [
                        'cdPonto' => $cdPonto,
                        'codigo' => $chaveConflito,
                        'quantidade' => 0,
                        'primeiraData' => $dataISO // Data no formato YYYY-MM-DD
                    ];
                }
                $pontosConflito[$chaveConflito]['quantidade']++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'temConflitos' => count($conflitos) > 0,
        'totalConflitos' => count($conflitos),
        'conflitos' => $conflitos,
        'pontosConflito' => array_values($pontosConflito)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}