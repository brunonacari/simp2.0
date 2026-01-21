<?php
// bd/programacaoManutencao/salvarProgramacao.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    // Verifica se é edição ou inserção
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;
    $isEdicao = $cdChave > 0;

    // Validações
    $cdPontoMedicao = isset($_POST['cd_ponto_medicao']) ? (int)$_POST['cd_ponto_medicao'] : 0;
    $idTipoProgramacao = isset($_POST['id_tipo_programacao']) ? (int)$_POST['id_tipo_programacao'] : 0;
    $idSituacao = isset($_POST['id_situacao']) ? (int)$_POST['id_situacao'] : 1;
    $cdUsuarioResponsavel = isset($_POST['cd_usuario_responsavel']) ? (int)$_POST['cd_usuario_responsavel'] : 0;
    $dsSolicitante = isset($_POST['ds_solicitante']) ? mb_substr(trim($_POST['ds_solicitante']), 0, 50) : '';
    $dsSolicitacao = isset($_POST['ds_solicitacao']) ? mb_substr(trim($_POST['ds_solicitacao']), 0, 200) : null;

    // Converte datas do formato datetime-local (YYYY-MM-DDTHH:MM) para SQL Server (YYYY-MM-DD HH:MM:SS)
    $dtProgramacaoRaw = isset($_POST['dt_programacao']) ? trim($_POST['dt_programacao']) : '';
    $dtSolicitacaoRaw = isset($_POST['dt_solicitacao']) ? trim($_POST['dt_solicitacao']) : '';
    
    $dtProgramacao = '';
    $dtSolicitacao = '';
    
    if (!empty($dtProgramacaoRaw)) {
        $dtProgramacao = str_replace('T', ' ', $dtProgramacaoRaw);
        if (strlen($dtProgramacao) == 16) {
            $dtProgramacao .= ':00';
        }
    }
    
    if (!empty($dtSolicitacaoRaw)) {
        $dtSolicitacao = str_replace('T', ' ', $dtSolicitacaoRaw);
        if (strlen($dtSolicitacao) == 16) {
            $dtSolicitacao .= ':00';
        }
    }

    // DEBUG: Valores parseados
    $debugInfo = [
        'cd_chave' => $cdChave,
        'isEdicao' => $isEdicao,
        'cd_ponto_medicao' => $cdPontoMedicao,
        'id_tipo_programacao' => $idTipoProgramacao,
        'id_situacao' => $idSituacao,
        'cd_usuario_responsavel' => $cdUsuarioResponsavel,
        'dt_programacao_raw' => $dtProgramacaoRaw,
        'dt_programacao' => $dtProgramacao,
        'ds_solicitante' => $dsSolicitante,
        'dt_solicitacao_raw' => $dtSolicitacaoRaw,
        'dt_solicitacao' => $dtSolicitacao,
        'ds_solicitacao' => $dsSolicitacao
    ];

    // Validações obrigatórias
    if ($cdPontoMedicao <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ponto de Medição é obrigatório.', 'debug' => $debugInfo]);
        exit;
    }

    if ($idTipoProgramacao <= 0) {
        echo json_encode(['success' => false, 'message' => 'Tipo de Programação é obrigatório.', 'debug' => $debugInfo]);
        exit;
    }

    if ($cdUsuarioResponsavel <= 0) {
        echo json_encode(['success' => false, 'message' => 'Responsável é obrigatório.', 'debug' => $debugInfo]);
        exit;
    }

    if (empty($dtProgramacao)) {
        echo json_encode(['success' => false, 'message' => 'Data da Programação é obrigatória.', 'debug' => $debugInfo]);
        exit;
    }

    if (empty($dsSolicitante)) {
        echo json_encode(['success' => false, 'message' => 'Solicitante é obrigatório.', 'debug' => $debugInfo]);
        exit;
    }

    if (empty($dtSolicitacao)) {
        echo json_encode(['success' => false, 'message' => 'Data da Solicitação é obrigatória.', 'debug' => $debugInfo]);
        exit;
    }

    // Obtém usuário logado
    $cdUsuarioLogado = isset($_SESSION['usuario']['CD_USUARIO']) ? $_SESSION['usuario']['CD_USUARIO'] : 1;

    if ($isEdicao) {
        // Buscar dados anteriores para log
        $dadosAnteriores = null;
        $codigoFormatadoLog = '';
        try {
            $sqlAnt = "SELECT * FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO WHERE CD_CHAVE = :id";
            $stmtAnt = $pdoSIMP->prepare($sqlAnt);
            $stmtAnt->execute([':id' => $cdChave]);
            $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC);
            if ($dadosAnteriores) {
                $codigoFormatadoLog = str_pad($dadosAnteriores['CD_CODIGO'], 3, '0', STR_PAD_LEFT) . '/' . $dadosAnteriores['CD_ANO'];
            }
        } catch (Exception $e) {}

        // UPDATE
        $sql = "UPDATE SIMP.dbo.PROGRAMACAO_MANUTENCAO SET
                    CD_PONTO_MEDICAO = :cd_ponto_medicao,
                    ID_TIPO_PROGRAMACAO = :id_tipo_programacao,
                    ID_SITUACAO = :id_situacao,
                    CD_USUARIO_RESPONSAVEL = :cd_usuario_responsavel,
                    DT_PROGRAMACAO = :dt_programacao,
                    DS_SOLICITANTE = :ds_solicitante,
                    DT_SOLICITACAO = :dt_solicitacao,
                    DS_SOLICITACAO = :ds_solicitacao,
                    CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario_atualizacao,
                    DT_ULTIMA_ATUALIZACAO = GETDATE()
                WHERE CD_CHAVE = :cd_chave";

        $params = [
            ':cd_ponto_medicao' => $cdPontoMedicao,
            ':id_tipo_programacao' => $idTipoProgramacao,
            ':id_situacao' => $idSituacao,
            ':cd_usuario_responsavel' => $cdUsuarioResponsavel,
            ':dt_programacao' => $dtProgramacao,
            ':ds_solicitante' => $dsSolicitante,
            ':dt_solicitacao' => $dtSolicitacao,
            ':ds_solicitacao' => $dsSolicitacao,
            ':cd_usuario_atualizacao' => $cdUsuarioLogado,
            ':cd_chave' => $cdChave
        ];

        $stmt = $pdoSIMP->prepare($sql);
        $result = $stmt->execute($params);

        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogUpdate')) {
                $dadosNovos = [
                    'CD_PONTO_MEDICAO' => $cdPontoMedicao,
                    'ID_TIPO_PROGRAMACAO' => $idTipoProgramacao,
                    'ID_SITUACAO' => $idSituacao,
                    'CD_USUARIO_RESPONSAVEL' => $cdUsuarioResponsavel,
                    'DT_PROGRAMACAO' => $dtProgramacao,
                    'DS_SOLICITANTE' => $dsSolicitante,
                    'DT_SOLICITACAO' => $dtSolicitacao,
                    'DS_SOLICITACAO' => $dsSolicitacao
                ];
                registrarLogUpdate('Programação de Manutenção', 'Programação', $cdChave, $codigoFormatadoLog,
                    ['anterior' => $dadosAnteriores, 'novo' => $dadosNovos]);
            }
        } catch (Exception $logEx) {}

        // Monta SQL legível para debug
        $debugSql = "UPDATE SIMP.dbo.PROGRAMACAO_MANUTENCAO SET 
            CD_PONTO_MEDICAO = $cdPontoMedicao, 
            ID_TIPO_PROGRAMACAO = $idTipoProgramacao, 
            ID_SITUACAO = $idSituacao, 
            CD_USUARIO_RESPONSAVEL = $cdUsuarioResponsavel, 
            DT_PROGRAMACAO = '$dtProgramacao', 
            DS_SOLICITANTE = '$dsSolicitante', 
            DT_SOLICITACAO = '$dtSolicitacao', 
            DS_SOLICITACAO = '$dsSolicitacao', 
            CD_USUARIO_ULTIMA_ATUALIZACAO = $cdUsuarioLogado, 
            DT_ULTIMA_ATUALIZACAO = GETDATE() 
            WHERE CD_CHAVE = $cdChave";

        echo json_encode([
            'success' => true,
            'message' => 'Programação atualizada com sucesso!',
            'cd_chave' => $cdChave,
            'debug_sql' => $debugSql,
            'debug_params' => $params,
            'rows_affected' => $stmt->rowCount()
        ]);

    } else {
        // INSERT
        // Primeiro, gera o próximo código sequencial para o ano atual
        $anoAtual = date('y'); // Formato: 25

        $sqlMaxCodigo = "SELECT ISNULL(MAX(CD_CODIGO), 0) + 1 AS PROXIMO_CODIGO 
                         FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO 
                         WHERE CD_ANO = :cd_ano";
        $stmtMax = $pdoSIMP->prepare($sqlMaxCodigo);
        $stmtMax->execute([':cd_ano' => $anoAtual]);
        $proximoCodigo = $stmtMax->fetch(PDO::FETCH_ASSOC)['PROXIMO_CODIGO'];

        $sql = "INSERT INTO SIMP.dbo.PROGRAMACAO_MANUTENCAO (
                    CD_CODIGO,
                    CD_ANO,
                    CD_PONTO_MEDICAO,
                    ID_TIPO_PROGRAMACAO,
                    ID_SITUACAO,
                    CD_USUARIO_RESPONSAVEL,
                    DT_PROGRAMACAO,
                    DS_SOLICITANTE,
                    DT_SOLICITACAO,
                    DS_SOLICITACAO,
                    CD_USUARIO_ULTIMA_ATUALIZACAO,
                    DT_ULTIMA_ATUALIZACAO
                ) VALUES (
                    :cd_codigo,
                    :cd_ano,
                    :cd_ponto_medicao,
                    :id_tipo_programacao,
                    :id_situacao,
                    :cd_usuario_responsavel,
                    :dt_programacao,
                    :ds_solicitante,
                    :dt_solicitacao,
                    :ds_solicitacao,
                    :cd_usuario_atualizacao,
                    GETDATE()
                )";

        $params = [
            ':cd_codigo' => $proximoCodigo,
            ':cd_ano' => $anoAtual,
            ':cd_ponto_medicao' => $cdPontoMedicao,
            ':id_tipo_programacao' => $idTipoProgramacao,
            ':id_situacao' => $idSituacao,
            ':cd_usuario_responsavel' => $cdUsuarioResponsavel,
            ':dt_programacao' => $dtProgramacao,
            ':ds_solicitante' => $dsSolicitante,
            ':dt_solicitacao' => $dtSolicitacao,
            ':ds_solicitacao' => $dsSolicitacao,
            ':cd_usuario_atualizacao' => $cdUsuarioLogado
        ];

        $stmt = $pdoSIMP->prepare($sql);
        $result = $stmt->execute($params);

        // Obter ID gerado
        $novoCdChave = null;
        try {
            $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
            $novoCdChave = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
        } catch (Exception $e) {
            $novoCdChave = $pdoSIMP->lastInsertId();
        }

        $codigoFormatado = str_pad($proximoCodigo, 3, '0', STR_PAD_LEFT) . '/' . $anoAtual;

        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogInsert')) {
                $dadosInseridos = [
                    'CD_CODIGO' => $proximoCodigo,
                    'CD_ANO' => $anoAtual,
                    'CD_PONTO_MEDICAO' => $cdPontoMedicao,
                    'ID_TIPO_PROGRAMACAO' => $idTipoProgramacao,
                    'ID_SITUACAO' => $idSituacao,
                    'CD_USUARIO_RESPONSAVEL' => $cdUsuarioResponsavel,
                    'DT_PROGRAMACAO' => $dtProgramacao,
                    'DS_SOLICITANTE' => $dsSolicitante,
                    'DT_SOLICITACAO' => $dtSolicitacao,
                    'DS_SOLICITACAO' => $dsSolicitacao
                ];
                registrarLogInsert('Programação de Manutenção', 'Programação', $novoCdChave, $codigoFormatado, $dadosInseridos);
            }
        } catch (Exception $logEx) {}

        // Monta SQL legível para debug
        $debugSql = "INSERT INTO SIMP.dbo.PROGRAMACAO_MANUTENCAO (
            CD_CODIGO, CD_ANO, CD_PONTO_MEDICAO, ID_TIPO_PROGRAMACAO, ID_SITUACAO, 
            CD_USUARIO_RESPONSAVEL, DT_PROGRAMACAO, DS_SOLICITANTE, DT_SOLICITACAO, 
            DS_SOLICITACAO, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO
        ) VALUES (
            $proximoCodigo, '$anoAtual', $cdPontoMedicao, $idTipoProgramacao, $idSituacao, 
            $cdUsuarioResponsavel, '$dtProgramacao', '$dsSolicitante', '$dtSolicitacao', 
            '$dsSolicitacao', $cdUsuarioLogado, GETDATE()
        )";

        echo json_encode([
            'success' => true,
            'message' => "Programação cadastrada com sucesso! Código: $codigoFormatado",
            'cd_chave' => $novoCdChave,
            'codigo' => $codigoFormatado,
            'debug_sql' => $debugSql,
            'debug_params' => $params
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar programação: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}