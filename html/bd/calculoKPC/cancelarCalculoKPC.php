<?php
/**
 * SIMP - Cancelar Cálculos de KPC
 * 
 * Marca os cálculos selecionados como cancelados (ID_SITUACAO = 2).
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
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

    // Criar placeholders para a query
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

    echo json_encode([
        'success' => true,
        'message' => "$registrosAfetados registro(s) cancelado(s) com sucesso!",
        'registros_afetados' => $registrosAfetados
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
