<?php
/**
 * SIMP - Salvar Item de Entidade (INSERT ou UPDATE)
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
    $cdValor = isset($_POST['cdValor']) && $_POST['cdValor'] !== '' ? (int)$_POST['cdValor'] : null;
    $cdPonto = isset($_POST['cdPonto']) && $_POST['cdPonto'] !== '' ? (int)$_POST['cdPonto'] : null;
    $dtInicio = isset($_POST['dtInicio']) && $_POST['dtInicio'] !== '' ? $_POST['dtInicio'] : null;
    $dtFim = isset($_POST['dtFim']) && $_POST['dtFim'] !== '' ? $_POST['dtFim'] : null;
    $operacao = isset($_POST['operacao']) && $_POST['operacao'] !== '' ? (int)$_POST['operacao'] : null;

    if ($cdPonto === null) {
        throw new Exception('O ponto de medição é obrigatório');
    }
    
    if ($dtInicio === null) {
        throw new Exception('A data de início é obrigatória');
    }
    
    if ($dtFim === null) {
        throw new Exception('A data de fim é obrigatória');
    }
    
    if ($operacao === null) {
        throw new Exception('A operação é obrigatória');
    }

    // Buscar nome do ponto de medição para log
    $nomePonto = "Ponto $cdPonto";
    try {
        $sqlPonto = "SELECT DS_NOME FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = :cdPonto";
        $stmtPonto = $pdoSIMP->prepare($sqlPonto);
        $stmtPonto->execute([':cdPonto' => $cdPonto]);
        $rowPonto = $stmtPonto->fetch(PDO::FETCH_ASSOC);
        if ($rowPonto) {
            $nomePonto = $rowPonto['DS_NOME'];
        }
    } catch (Exception $e) {}

    if ($cd !== null) {
        // Buscar dados anteriores para log
        $dadosAnteriores = null;
        try {
            $sqlAnterior = "SELECT CD_PONTO_MEDICAO, DT_INICIO, DT_FIM, ID_OPERACAO FROM SIMP.dbo.ENTIDADE_VALOR_ITEM WHERE CD_CHAVE = :cd";
            $stmtAnterior = $pdoSIMP->prepare($sqlAnterior);
            $stmtAnterior->execute([':cd' => $cd]);
            $dadosAnteriores = $stmtAnterior->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // UPDATE
        $sql = "UPDATE SIMP.dbo.ENTIDADE_VALOR_ITEM 
                SET CD_PONTO_MEDICAO = :cdPonto, 
                    DT_INICIO = :dtInicio,
                    DT_FIM = :dtFim,
                    ID_OPERACAO = :operacao
                WHERE CD_CHAVE = :cd";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdPonto' => $cdPonto,
            ':dtInicio' => $dtInicio,
            ':dtFim' => $dtFim,
            ':operacao' => $operacao,
            ':cd' => $cd
        ]);

        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastro de Entidade', 'Vínculo Ponto-Entidade', $cd, $nomePonto,
                    ['anterior' => $dadosAnteriores, 'novo' => ['CD_PONTO_MEDICAO' => $cdPonto, 'DT_INICIO' => $dtInicio, 'DT_FIM' => $dtFim, 'ID_OPERACAO' => $operacao]]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Vínculo atualizado com sucesso!'
        ]);
    } else {
        // INSERT
        if ($cdValor === null) {
            throw new Exception('O valor de entidade é obrigatório');
        }

        $sql = "INSERT INTO SIMP.dbo.ENTIDADE_VALOR_ITEM 
                (CD_ENTIDADE_VALOR, CD_PONTO_MEDICAO, DT_INICIO, DT_FIM, ID_OPERACAO) 
                VALUES (:cdValor, :cdPonto, :dtInicio, :dtFim, :operacao)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdValor' => $cdValor,
            ':cdPonto' => $cdPonto,
            ':dtInicio' => $dtInicio,
            ':dtFim' => $dtFim,
            ':operacao' => $operacao
        ]);

        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogInsert')) {
                $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
                $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
                registrarLogInsert('Cadastro de Entidade', 'Vínculo Ponto-Entidade', $novoId, $nomePonto,
                    ['CD_ENTIDADE_VALOR' => $cdValor, 'CD_PONTO_MEDICAO' => $cdPonto, 'DT_INICIO' => $dtInicio, 'DT_FIM' => $dtFim, 'ID_OPERACAO' => $operacao]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Vínculo cadastrado com sucesso!'
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