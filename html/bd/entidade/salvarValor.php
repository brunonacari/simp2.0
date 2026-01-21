<?php
/**
 * SIMP - Salvar Valor de Entidade (INSERT ou UPDATE)
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

    $cd = isset($_POST['cd']) && $_POST['cd'] !== '' ? (int)$_POST['cd'] : null;
    $cdTipo = isset($_POST['cdTipo']) && $_POST['cdTipo'] !== '' ? (int)$_POST['cdTipo'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $idExterno = isset($_POST['idExterno']) ? trim($_POST['idExterno']) : null;
    $fluxo = isset($_POST['fluxo']) && $_POST['fluxo'] !== '' ? (int)$_POST['fluxo'] : null;

    if ($nome === '') {
        throw new Exception('O nome é obrigatório');
    }
    
    if ($idExterno === null || $idExterno === '') {
        throw new Exception('O ID Externo é obrigatório');
    }
    
    if ($fluxo === null) {
        throw new Exception('O fluxo é obrigatório');
    }

    if ($cd !== null) {
        // Buscar dados anteriores para log
        $dadosAnteriores = null;
        try {
            $sqlAnterior = "SELECT DS_NOME, CD_ENTIDADE_VALOR_ID, ID_FLUXO FROM SIMP.dbo.ENTIDADE_VALOR WHERE CD_CHAVE = :cd";
            $stmtAnterior = $pdoSIMP->prepare($sqlAnterior);
            $stmtAnterior->execute([':cd' => $cd]);
            $dadosAnteriores = $stmtAnterior->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // UPDATE
        $sql = "UPDATE SIMP.dbo.ENTIDADE_VALOR 
                SET DS_NOME = :nome, 
                    CD_ENTIDADE_VALOR_ID = :idExterno,
                    ID_FLUXO = :fluxo,
                    OP_ENVIAR_DADOS_SIGAO = 2,
                    OP_EXPORTOU_SIGAO = 2
                WHERE CD_CHAVE = :cd";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':nome' => $nome,
            ':idExterno' => $idExterno,
            ':fluxo' => $fluxo,
            ':cd' => $cd
        ]);

        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastro de Entidade', 'Unidade Operacional', $cd, "$idExterno - $nome",
                    ['anterior' => $dadosAnteriores, 'novo' => ['DS_NOME' => $nome, 'CD_ENTIDADE_VALOR_ID' => $idExterno, 'ID_FLUXO' => $fluxo]]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Valor atualizado com sucesso!'
        ]);
    } else {
        // INSERT
        if ($cdTipo === null) {
            throw new Exception('O tipo de entidade é obrigatório');
        }

        $sql = "INSERT INTO SIMP.dbo.ENTIDADE_VALOR 
                (CD_ENTIDADE_TIPO, DS_NOME, CD_ENTIDADE_VALOR_ID, ID_FLUXO, OP_ENVIAR_DADOS_SIGAO, OP_EXPORTOU_SIGAO) 
                VALUES (:cdTipo, :nome, :idExterno, :fluxo, 2, 2)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdTipo' => $cdTipo,
            ':nome' => $nome,
            ':idExterno' => $idExterno,
            ':fluxo' => $fluxo
        ]);

        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogInsert')) {
                $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
                $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
                registrarLogInsert('Cadastro de Entidade', 'Unidade Operacional', $novoId, "$idExterno - $nome",
                    ['CD_ENTIDADE_TIPO' => $cdTipo, 'DS_NOME' => $nome, 'CD_ENTIDADE_VALOR_ID' => $idExterno, 'ID_FLUXO' => $fluxo]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Valor cadastrado com sucesso!'
        ]);
    }

} catch (Exception $e) {
    // Log de erro (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro de Entidade', $cd !== null ? 'UPDATE' : 'INSERT', $e->getMessage(), $_POST);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}