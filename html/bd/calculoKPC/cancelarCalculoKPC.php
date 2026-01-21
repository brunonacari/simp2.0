<?php
/**
 * SIMP - Cancelar Cálculos de KPC
 * COM REGISTRO DE LOG
 * 
 * Marca os cálculos selecionados como cancelados (ID_SITUACAO = 2).
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    require_once '../logHelper.php';
    include_once '../conexao.php';

    // Receber dados JSON
    $json = file_get_contents('php://input');
    $dados = json_decode($json, true);

    if (!$dados || !isset($dados['ids']) || !is_array($dados['ids'])) {
        throw new Exception('IDs inválidos');
    }

    $ids = array_filter(array_map('intval', $dados['ids']));

    if (empty($ids)) {
        throw new Exception('Nenhum ID válido informado');
    }

    // Usuário da sessão para auditoria
    $cdUsuarioAtualizacao = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : 1;
    $dtAtualizacao = date('Y-m-d H:i:s');

    // Buscar dados dos registros antes de cancelar (para log)
    $placeholdersSelect = implode(',', array_fill(0, count($ids), '?'));
    $sqlBusca = "SELECT CK.CD_CHAVE, CK.CD_CODIGO, CK.CD_ANO, CK.VL_KPC, CK.CD_PONTO_MEDICAO, PM.DS_NOME AS DS_PONTO, L.CD_UNIDADE
                 FROM SIMP.dbo.CALCULO_KPC CK
                 LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = CK.CD_PONTO_MEDICAO
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
                 WHERE CK.CD_CHAVE IN ($placeholdersSelect) AND CK.ID_SITUACAO = 1";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute($ids);
    $registrosParaCancelar = $stmtBusca->fetchAll(PDO::FETCH_ASSOC);

    if (empty($registrosParaCancelar)) {
        throw new Exception('Nenhum registro válido encontrado para cancelamento');
    }

    // Criar placeholders para a query de update
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Atualizar situação para Cancelado (2)
    $sql = "UPDATE SIMP.dbo.CALCULO_KPC SET
                ID_SITUACAO = 2,
                CD_USUARIO_ULTIMA_ATUALIZACAO = ?,
                DT_ULTIMA_ATUALIZACAO = ?
            WHERE CD_CHAVE IN ($placeholders)
            AND ID_SITUACAO = 1";
    
    $stmt = $pdoSIMP->prepare($sql);
    
    // Montar array de parâmetros
    $params = [$cdUsuarioAtualizacao, $dtAtualizacao];
    $params = array_merge($params, $ids);
    
    $stmt->execute($params);
    
    $registrosAfetados = $stmt->rowCount();

    // Registrar log para cada registro cancelado
    foreach ($registrosParaCancelar as $registro) {
        try {
            $codigoCalculo = ($registro['CD_CODIGO'] ?? '') . '/' . ($registro['CD_ANO'] ?? '');
            $identificador = "KPC $codigoCalculo - " . ($registro['DS_PONTO'] ?? '');
            $cdUnidadeLog = $registro['CD_UNIDADE'] ?? null;
            
            $dadosLog = [
                'CD_CHAVE' => $registro['CD_CHAVE'],
                'CD_CODIGO' => $registro['CD_CODIGO'],
                'CD_ANO' => $registro['CD_ANO'],
                'VL_KPC' => $registro['VL_KPC'],
                'CD_PONTO_MEDICAO' => $registro['CD_PONTO_MEDICAO'],
                'DS_PONTO_MEDICAO' => $registro['DS_PONTO'] ?? null,
                'acao' => 'CANCELAMENTO',
                'ID_SITUACAO_ANTERIOR' => 1,
                'ID_SITUACAO_NOVO' => 2
            ];
            
            registrarLogUpdate('Cálculo do KPC', 'Cálculo KPC', $registro['CD_CHAVE'], "$identificador (Cancelado)", $dadosLog, $cdUnidadeLog);
        } catch (Exception $logEx) {
            error_log('Erro ao registrar log de CANCELAMENTO Cálculo KPC ID ' . $registro['CD_CHAVE'] . ': ' . $logEx->getMessage());
        }
    }

    // Log de ação em massa se múltiplos registros
    if ($registrosAfetados > 1) {
        try {
            registrarLogAlteracaoMassa('Cálculo do KPC', 'Cálculo KPC', $registrosAfetados, 'Cancelamento em lote', [
                'ids_cancelados' => array_column($registrosParaCancelar, 'CD_CHAVE')
            ]);
        } catch (Exception $logEx) {
            error_log('Erro ao registrar log de CANCELAMENTO EM MASSA Cálculo KPC: ' . $logEx->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "$registrosAfetados registro(s) cancelado(s) com sucesso!",
        'registros_afetados' => $registrosAfetados
    ]);

} catch (Exception $e) {
    // Registrar log de erro
    try {
        registrarLogErro('Cálculo do KPC', 'CANCELAR', $e->getMessage(), ['ids' => $ids ?? []]);
    } catch (Exception $logEx) {}

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}