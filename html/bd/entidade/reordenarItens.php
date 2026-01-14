<?php
/**
 * SIMP - Reordenar Itens de Entidade (Pontos de Medição)
 * Atualiza a ordem dos pontos de medição dentro de uma unidade operacional
 */

header('Content-Type: application/json; charset=utf-8');

// Verificar permissão de edição
require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';

    // Verificar se coluna NR_ORDEM existe
    $temNrOrdem = false;
    try {
        $checkCol = $pdoSIMP->query("SELECT TOP 1 NR_ORDEM FROM SIMP.dbo.ENTIDADE_VALOR_ITEM");
        $temNrOrdem = true;
    } catch (Exception $e) {
        $temNrOrdem = false;
    }

    if (!$temNrOrdem) {
        throw new Exception('A coluna NR_ORDEM não existe. Execute o script SQL para adicionar a coluna.');
    }

    // Receber JSON com a nova ordem
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['itens']) || !is_array($input['itens'])) {
        throw new Exception('Dados inválidos');
    }

    $itens = $input['itens'];
    
    if (empty($itens)) {
        throw new Exception('Nenhum item para reordenar');
    }

    // Atualizar a ordem de cada item
    $sql = "UPDATE SIMP.dbo.ENTIDADE_VALOR_ITEM SET NR_ORDEM = :ordem WHERE CD_CHAVE = :cd";
    $stmt = $pdoSIMP->prepare($sql);
    
    foreach ($itens as $index => $cdItem) {
        $stmt->execute([
            ':ordem' => $index + 1,
            ':cd' => (int)$cdItem
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ordem atualizada com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}