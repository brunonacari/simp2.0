<?php
/**
 * SIMP - Excluir Valor de Entidade (Unidade Operacional inteira)
 * 
 * Remove uma unidade operacional e todos os seus vínculos com pontos de medição.
 * Também remove registros dependentes em FORMULA_ITEM_PONTO_MEDICAO
 * para evitar conflito com FK_FORMULA_A_ENTIDADE__ENTIDADE.
 */

ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Verificar permissão de edição
require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';
    
    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    $cd = isset($_POST['cd']) && $_POST['cd'] !== '' ? (int)$_POST['cd'] : null;

    if ($cd === null || $cd <= 0) {
        throw new Exception('ID não informado');
    }

    // Buscar dados antes de excluir para log
    $dadosExcluidos = null;
    $identificador = "ID: $cd";
    try {
        $sqlBusca = "SELECT * FROM SIMP.dbo.ENTIDADE_VALOR WHERE CD_CHAVE = :cd";
        $stmtBusca = $pdoSIMP->prepare($sqlBusca);
        $stmtBusca->execute([':cd' => $cd]);
        $dadosExcluidos = $stmtBusca->fetch(PDO::FETCH_ASSOC);
        if ($dadosExcluidos) {
            $identificador = ($dadosExcluidos['CD_ENTIDADE_VALOR_ID'] ?? '') . ' - ' . ($dadosExcluidos['DS_NOME'] ?? "ID: $cd");
        }
    } catch (Exception $e) {}

    // Iniciar transação para garantir integridade
    $pdoSIMP->beginTransaction();

    try {
        // 1) Excluir registros dependentes em FORMULA_ITEM_PONTO_MEDICAO
        //    que referenciam itens deste valor via FK_FORMULA_A_ENTIDADE__ENTIDADE
        $sqlFormula = "DELETE FROM SIMP.dbo.FORMULA_ITEM_PONTO_MEDICAO 
                       WHERE CD_ENTIDADE_VALOR_ITEM IN (
                           SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_VALOR_ITEM WHERE CD_ENTIDADE_VALOR = ?
                       )";
        $stmtFormula = $pdoSIMP->prepare($sqlFormula);
        $stmtFormula->execute([$cd]);
        $formulasExcluidas = $stmtFormula->rowCount();

        // 2) Excluir TODOS os itens vinculados (pontos de medição)
        $sqlItens = "DELETE FROM SIMP.dbo.ENTIDADE_VALOR_ITEM WHERE CD_ENTIDADE_VALOR = ?";
        $stmtItens = $pdoSIMP->prepare($sqlItens);
        $stmtItens->execute([$cd]);
        $itensExcluidos = $stmtItens->rowCount();

        // 3) Excluir o valor (unidade operacional)
        $sqlValor = "DELETE FROM SIMP.dbo.ENTIDADE_VALOR WHERE CD_CHAVE = ?";
        $stmtValor = $pdoSIMP->prepare($sqlValor);
        $stmtValor->execute([$cd]);

        // Confirmar transação
        $pdoSIMP->commit();

        // Adicionar quantidades ao log
        if ($dadosExcluidos) {
            $dadosExcluidos['itens_excluidos'] = $itensExcluidos;
            $dadosExcluidos['formulas_excluidas'] = $formulasExcluidas;
        }

        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogDelete')) {
                registrarLogDelete('Cadastro de Entidade', 'Unidade Operacional', $cd, $identificador, $dadosExcluidos);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => "Valor excluído com sucesso! ($itensExcluidos pontos desvinculados)"
        ]);

    } catch (Exception $e) {
        // Rollback em caso de erro
        $pdoSIMP->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    // Log de erro (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro de Entidade', 'DELETE', $e->getMessage(), ['cd' => $cd ?? null]);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir: ' . $e->getMessage()
    ]);

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