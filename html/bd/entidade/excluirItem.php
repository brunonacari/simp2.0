<?php
/**
 * SIMP - Excluir Item de Entidade (Ponto vinculado a uma Unidade Operacional)
 * 
 * Remove o vínculo de um ponto de medição com uma unidade operacional.
 * Também remove registros dependentes em FORMULA_ITEM_PONTO_MEDICAO
 * para evitar conflito com FK_FORMULA_A_ENTIDADE__ENTIDADE.
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

    $cd = isset($_POST['cd']) ? (int)$_POST['cd'] : 0;

    if ($cd <= 0) {
        throw new Exception('ID do item não informado');
    }

    // Buscar dados antes de excluir para log
    $dadosExcluidos = null;
    $identificador = "ID: $cd";
    try {
        $sqlBusca = "SELECT EVI.*, PM.DS_NOME AS PONTO_NOME 
                     FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI 
                     LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO 
                     WHERE EVI.CD_CHAVE = :cd";
        $stmtBusca = $pdoSIMP->prepare($sqlBusca);
        $stmtBusca->execute([':cd' => $cd]);
        $dadosExcluidos = $stmtBusca->fetch(PDO::FETCH_ASSOC);
        if ($dadosExcluidos) {
            $identificador = $dadosExcluidos['PONTO_NOME'] ?? "Ponto " . ($dadosExcluidos['CD_PONTO_MEDICAO'] ?? $cd);
        }
    } catch (Exception $e) {}

    // Iniciar transação para garantir integridade
    $pdoSIMP->beginTransaction();

    try {
        // 1) Excluir registros dependentes em FORMULA_ITEM_PONTO_MEDICAO
        //    que referenciam este item via FK_FORMULA_A_ENTIDADE__ENTIDADE
        $sqlFormula = "DELETE FROM SIMP.dbo.FORMULA_ITEM_PONTO_MEDICAO WHERE CD_ENTIDADE_VALOR_ITEM = ?";
        $stmtFormula = $pdoSIMP->prepare($sqlFormula);
        $stmtFormula->execute([$cd]);
        $formulasExcluidas = $stmtFormula->rowCount();

        // 2) Agora excluir o item propriamente dito
        $sql = "DELETE FROM SIMP.dbo.ENTIDADE_VALOR_ITEM WHERE CD_CHAVE = ?";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([$cd]);

        // Confirmar transação
        $pdoSIMP->commit();

        // Incluir info de fórmulas removidas no log
        if ($dadosExcluidos) {
            $dadosExcluidos['formulas_excluidas'] = $formulasExcluidas;
        }

        // Log (isolado para não impedir a operação principal)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogDelete')) {
                registrarLogDelete('Cadastro de Entidade', 'Vínculo Ponto-Entidade', $cd, $identificador, $dadosExcluidos);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Vínculo removido com sucesso!'
        ]);

    } catch (Exception $e) {
        // Rollback em caso de erro
        $pdoSIMP->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // Log de erro (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro de Entidade', 'DELETE', $e->getMessage(), ['cd' => $cd ?? null]);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}