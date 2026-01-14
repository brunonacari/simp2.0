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

    if ($cd !== null) {
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

        echo json_encode([
            'success' => true,
            'message' => 'Vínculo atualizado com sucesso!'
        ]);
    } else {
        // INSERT
        if ($cdValor === null) {
            throw new Exception('O valor de entidade é obrigatório');
        }

        // Verifica se o ponto já está vinculado a este valor
        $sqlVerifica = "SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_VALOR_ITEM 
                        WHERE CD_ENTIDADE_VALOR = :cdValor AND CD_PONTO_MEDICAO = :cdPonto";
        $stmtVerifica = $pdoSIMP->prepare($sqlVerifica);
        $stmtVerifica->execute([':cdValor' => $cdValor, ':cdPonto' => $cdPonto]);
        
        if ($stmtVerifica->fetch()) {
            throw new Exception('Este ponto de medição já está vinculado a este valor');
        }

        // Verificar se coluna NR_ORDEM existe
        $temNrOrdem = false;
        try {
            $checkCol = $pdoSIMP->query("SELECT TOP 1 NR_ORDEM FROM SIMP.dbo.ENTIDADE_VALOR_ITEM");
            $temNrOrdem = true;
        } catch (Exception $e) {
            $temNrOrdem = false;
        }

        if ($temNrOrdem) {
            // Obter próxima ordem
            $sqlOrdem = "SELECT ISNULL(MAX(NR_ORDEM), 0) + 1 AS PROX_ORDEM 
                         FROM SIMP.dbo.ENTIDADE_VALOR_ITEM 
                         WHERE CD_ENTIDADE_VALOR = :cdValor";
            $stmtOrdem = $pdoSIMP->prepare($sqlOrdem);
            $stmtOrdem->execute([':cdValor' => $cdValor]);
            $proxOrdem = $stmtOrdem->fetchColumn();

            $sql = "INSERT INTO SIMP.dbo.ENTIDADE_VALOR_ITEM 
                    (CD_ENTIDADE_VALOR, CD_PONTO_MEDICAO, DT_INICIO, DT_FIM, ID_OPERACAO, NR_ORDEM) 
                    VALUES (:cdValor, :cdPonto, :dtInicio, :dtFim, :operacao, :ordem)";
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([
                ':cdValor' => $cdValor,
                ':cdPonto' => $cdPonto,
                ':dtInicio' => $dtInicio,
                ':dtFim' => $dtFim,
                ':operacao' => $operacao,
                ':ordem' => $proxOrdem
            ]);
        } else {
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
        }

        echo json_encode([
            'success' => true,
            'message' => 'Ponto vinculado com sucesso!'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}