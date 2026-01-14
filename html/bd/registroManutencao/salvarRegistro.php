<?php
// bd/registroManutencao/salvarRegistro.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    // Verifica se é edição ou inserção
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;
    $isEdicao = $cdChave > 0;

    // Validações
    $cdProgramacao = isset($_POST['cd_programacao']) ? (int)$_POST['cd_programacao'] : 0;
    $idSituacao = isset($_POST['id_situacao']) ? (int)$_POST['id_situacao'] : 1;
    $cdTecnico = isset($_POST['cd_tecnico']) ? (int)$_POST['cd_tecnico'] : 0;
    $dsRealizado = isset($_POST['ds_realizado']) ? mb_substr(trim($_POST['ds_realizado']), 0, 5000) : null;
    $dsCondicaoPrimario = isset($_POST['ds_condicao_primario']) ? mb_substr(trim($_POST['ds_condicao_primario']), 0, 5000) : '';
    $dsCondicaoSecundario = isset($_POST['ds_condicao_secundario']) ? mb_substr(trim($_POST['ds_condicao_secundario']), 0, 5000) : '';
    $dsCondicaoTerciario = isset($_POST['ds_condicao_terciario']) ? mb_substr(trim($_POST['ds_condicao_terciario']), 0, 5000) : '';
    $idClassificacaoManutencao = isset($_POST['id_classificacao_manutencao']) && $_POST['id_classificacao_manutencao'] !== '' ? (int)$_POST['id_classificacao_manutencao'] : null;
    $idTipoCalibracao = isset($_POST['id_tipo_calibracao']) && $_POST['id_tipo_calibracao'] !== '' ? (int)$_POST['id_tipo_calibracao'] : null;
    $dsObservacao = isset($_POST['ds_observacao']) ? mb_substr(trim($_POST['ds_observacao']), 0, 5000) : null;

    // Converte data do formato datetime-local para SQL Server
    $dtRealizadoRaw = isset($_POST['dt_realizado']) ? trim($_POST['dt_realizado']) : '';
    $dtRealizado = '';
    if (!empty($dtRealizadoRaw)) {
        $dtRealizado = str_replace('T', ' ', $dtRealizadoRaw);
        if (strlen($dtRealizado) == 16) {
            $dtRealizado .= ':00';
        }
    }

    // Validações obrigatórias
    if ($cdProgramacao <= 0) {
        echo json_encode(['success' => false, 'message' => 'Programação é obrigatória.']);
        exit;
    }

    if ($cdTecnico <= 0) {
        echo json_encode(['success' => false, 'message' => 'Técnico é obrigatório.']);
        exit;
    }

    if (empty($dtRealizado)) {
        echo json_encode(['success' => false, 'message' => 'Data de Realização é obrigatória.']);
        exit;
    }

    if (empty($dsCondicaoPrimario)) {
        echo json_encode(['success' => false, 'message' => 'Condição do Primário é obrigatória.']);
        exit;
    }

    if (empty($dsCondicaoSecundario)) {
        echo json_encode(['success' => false, 'message' => 'Condição do Secundário é obrigatória.']);
        exit;
    }

    if (empty($dsCondicaoTerciario)) {
        echo json_encode(['success' => false, 'message' => 'Condição do Terciário é obrigatória.']);
        exit;
    }

    // Obtém usuário logado
    $cdUsuarioLogado = isset($_SESSION['usuario']['CD_USUARIO']) ? $_SESSION['usuario']['CD_USUARIO'] : 1;

    if ($isEdicao) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.REGISTRO_MANUTENCAO SET
                    CD_PROGRAMACAO = :cd_programacao,
                    ID_SITUACAO = :id_situacao,
                    CD_TECNICO = :cd_tecnico,
                    DT_REALIZADO = :dt_realizado,
                    DS_REALIZADO = :ds_realizado,
                    DS_CONDICAO_PRIMARIO = :ds_condicao_primario,
                    DS_CONDICAO_SECUNDARIO = :ds_condicao_secundario,
                    DS_CONDICAO_TERCIARIO = :ds_condicao_terciario,
                    ID_CLASSIFICACAO_MANUTENCAO = :id_classificacao_manutencao,
                    ID_TIPO_CALIBRACAO = :id_tipo_calibracao,
                    DS_OBSERVACAO = :ds_observacao,
                    CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario_atualizacao,
                    DT_ULTIMA_ATUALIZACAO = GETDATE()
                WHERE CD_CHAVE = :cd_chave";

        $params = [
            ':cd_programacao' => $cdProgramacao,
            ':id_situacao' => $idSituacao,
            ':cd_tecnico' => $cdTecnico,
            ':dt_realizado' => $dtRealizado,
            ':ds_realizado' => $dsRealizado,
            ':ds_condicao_primario' => $dsCondicaoPrimario,
            ':ds_condicao_secundario' => $dsCondicaoSecundario,
            ':ds_condicao_terciario' => $dsCondicaoTerciario,
            ':id_classificacao_manutencao' => $idClassificacaoManutencao,
            ':id_tipo_calibracao' => $idTipoCalibracao,
            ':ds_observacao' => $dsObservacao,
            ':cd_usuario_atualizacao' => $cdUsuarioLogado,
            ':cd_chave' => $cdChave
        ];

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Registro atualizado com sucesso!',
            'cd_chave' => $cdChave
        ]);

    } else {
        // INSERT
        // Gera o próximo número de ocorrência para esta programação
        $sqlMaxOcorrencia = "SELECT ISNULL(MAX(CD_OCORRENCIA), 0) + 1 AS PROXIMA_OCORRENCIA 
                            FROM SIMP.dbo.REGISTRO_MANUTENCAO 
                            WHERE CD_PROGRAMACAO = :cd_programacao";
        $stmtMax = $pdoSIMP->prepare($sqlMaxOcorrencia);
        $stmtMax->execute([':cd_programacao' => $cdProgramacao]);
        $proximaOcorrencia = $stmtMax->fetch(PDO::FETCH_ASSOC)['PROXIMA_OCORRENCIA'];

        $sql = "INSERT INTO SIMP.dbo.REGISTRO_MANUTENCAO (
                    CD_PROGRAMACAO,
                    CD_OCORRENCIA,
                    ID_SITUACAO,
                    CD_TECNICO,
                    DT_REALIZADO,
                    DS_REALIZADO,
                    DS_CONDICAO_PRIMARIO,
                    DS_CONDICAO_SECUNDARIO,
                    DS_CONDICAO_TERCIARIO,
                    ID_CLASSIFICACAO_MANUTENCAO,
                    ID_TIPO_CALIBRACAO,
                    DS_OBSERVACAO,
                    CD_USUARIO_ULTIMA_ATUALIZACAO,
                    DT_ULTIMA_ATUALIZACAO
                ) VALUES (
                    :cd_programacao,
                    :cd_ocorrencia,
                    :id_situacao,
                    :cd_tecnico,
                    :dt_realizado,
                    :ds_realizado,
                    :ds_condicao_primario,
                    :ds_condicao_secundario,
                    :ds_condicao_terciario,
                    :id_classificacao_manutencao,
                    :id_tipo_calibracao,
                    :ds_observacao,
                    :cd_usuario_atualizacao,
                    GETDATE()
                )";

        $params = [
            ':cd_programacao' => $cdProgramacao,
            ':cd_ocorrencia' => $proximaOcorrencia,
            ':id_situacao' => $idSituacao,
            ':cd_tecnico' => $cdTecnico,
            ':dt_realizado' => $dtRealizado,
            ':ds_realizado' => $dsRealizado,
            ':ds_condicao_primario' => $dsCondicaoPrimario,
            ':ds_condicao_secundario' => $dsCondicaoSecundario,
            ':ds_condicao_terciario' => $dsCondicaoTerciario,
            ':id_classificacao_manutencao' => $idClassificacaoManutencao,
            ':id_tipo_calibracao' => $idTipoCalibracao,
            ':ds_observacao' => $dsObservacao,
            ':cd_usuario_atualizacao' => $cdUsuarioLogado
        ];

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);

        $novoCdChave = $pdoSIMP->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => "Registro cadastrado com sucesso! Ocorrência: $proximaOcorrencia",
            'cd_chave' => $novoCdChave,
            'cd_ocorrencia' => $proximaOcorrencia
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar registro: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
