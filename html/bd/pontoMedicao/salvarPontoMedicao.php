<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Salvar/Atualizar Ponto de Medição
 * COM REGISTRO DE LOG
 */

header('Content-Type: application/json; charset=utf-8');

// Capturar erros e warnings para incluir na resposta
$phpErrors = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$phpErrors) {
    $phpErrors[] = "[$errno] $errstr em $errfile:$errline";
    return true;
});

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Verificação de autenticação e permissão
require_once '../verificarAuth.php';
require_once '../logHelper.php';

verificarPermissaoAjax('CADASTRO DE PONTO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Captura parâmetros
    $cdPontoMedicao = isset($_POST['cd_ponto_medicao']) && $_POST['cd_ponto_medicao'] !== '' ? (int)$_POST['cd_ponto_medicao'] : null;
    $cdLocalidade = isset($_POST['cd_localidade']) && $_POST['cd_localidade'] !== '' ? (int)$_POST['cd_localidade'] : null;
    $idTipoMedidor = isset($_POST['id_tipo_medidor']) && $_POST['id_tipo_medidor'] !== '' ? (int)$_POST['id_tipo_medidor'] : null;
    $dsNome = isset($_POST['ds_nome']) ? mb_substr(trim($_POST['ds_nome']), 0, 100) : '';
    $dsLocalizacao = isset($_POST['ds_localizacao']) ? mb_substr(trim($_POST['ds_localizacao']), 0, 200) : null;
    $idTipoLeitura = isset($_POST['id_tipo_leitura']) && $_POST['id_tipo_leitura'] !== '' ? (int)$_POST['id_tipo_leitura'] : null;
    $opPeriodicidadeLeitura = isset($_POST['op_periodicidade_leitura']) && $_POST['op_periodicidade_leitura'] !== '' ? (int)$_POST['op_periodicidade_leitura'] : null;
    $cdUsuarioResponsavel = isset($_POST['cd_usuario_responsavel']) && $_POST['cd_usuario_responsavel'] !== '' ? (int)$_POST['cd_usuario_responsavel'] : null;
    $tipoInstalacao = isset($_POST['tipo_instalacao']) && $_POST['tipo_instalacao'] !== '' ? (int)$_POST['tipo_instalacao'] : null;
    $dtAtivacao = isset($_POST['dt_ativacao']) && $_POST['dt_ativacao'] !== '' ? $_POST['dt_ativacao'] : null;
    $dtDesativacao = isset($_POST['dt_desativacao']) && $_POST['dt_desativacao'] !== '' ? $_POST['dt_desativacao'] : null;
    $vlQuantidadeLigacoes = isset($_POST['vl_quantidade_ligacoes']) && $_POST['vl_quantidade_ligacoes'] !== '' ? (float)$_POST['vl_quantidade_ligacoes'] : null;
    $vlQuantidadeEconomias = isset($_POST['vl_quantidade_economias']) && $_POST['vl_quantidade_economias'] !== '' ? (float)$_POST['vl_quantidade_economias'] : null;
    $dsTagVazao = isset($_POST['ds_tag_vazao']) ? mb_substr(trim($_POST['ds_tag_vazao']), 0, 25) : null;
    $dsTagPressao = isset($_POST['ds_tag_pressao']) ? mb_substr(trim($_POST['ds_tag_pressao']), 0, 25) : null;
    $dsTagVolume = isset($_POST['ds_tag_volume']) ? mb_substr(trim($_POST['ds_tag_volume']), 0, 25) : null;
    $dsTagReservatorio = isset($_POST['ds_tag_reservatorio']) ? mb_substr(trim($_POST['ds_tag_reservatorio']), 0, 25) : null;
    $dsTagTempAgua = isset($_POST['ds_tag_temp_agua']) ? mb_substr(trim($_POST['ds_tag_temp_agua']), 0, 25) : null;
    $dsTagTempAmbiente = isset($_POST['ds_tag_temp_ambiente']) ? mb_substr(trim($_POST['ds_tag_temp_ambiente']), 0, 25) : null;
    $coordenadas = isset($_POST['coordenadas']) ? mb_substr(trim($_POST['coordenadas']), 0, 100) : null;
    $locInstSap = isset($_POST['loc_inst_sap']) ? mb_substr(trim($_POST['loc_inst_sap']), 0, 100) : null;
    $vlFatorCorrecaoVazao = isset($_POST['vl_fator_correcao_vazao']) && $_POST['vl_fator_correcao_vazao'] !== '' ? (float)$_POST['vl_fator_correcao_vazao'] : null;
    $vlLimiteInferiorVazao = isset($_POST['vl_limite_inferior_vazao']) && $_POST['vl_limite_inferior_vazao'] !== '' ? (float)$_POST['vl_limite_inferior_vazao'] : null;
    $vlLimiteSuperiorVazao = isset($_POST['vl_limite_superior_vazao']) && $_POST['vl_limite_superior_vazao'] !== '' ? (float)$_POST['vl_limite_superior_vazao'] : null;
    $dsObservacao = isset($_POST['ds_observacao']) ? mb_substr(trim($_POST['ds_observacao']), 0, 200) : null;

    // Validações obrigatórias
    if (empty($cdLocalidade)) {
        throw new Exception('Localidade é obrigatória');
    }

    if (empty($idTipoMedidor)) {
        throw new Exception('Tipo de Medidor é obrigatório');
    }

    if (empty($dsNome)) {
        throw new Exception('Nome é obrigatório');
    }

    if (empty($idTipoLeitura)) {
        throw new Exception('Tipo de Leitura é obrigatório');
    }

    if (empty($cdUsuarioResponsavel)) {
        throw new Exception('Responsável é obrigatório');
    }

    // Usuário da sessão para auditoria
    $cdUsuarioAtualizacao = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;
    $dtAtualizacao = date('Y-m-d H:i:s');

    // Converter datas para formato SQL Server
    if ($dtAtivacao) {
        $dtAtivacao = date('Y-m-d H:i:s', strtotime($dtAtivacao));
    }
    if ($dtDesativacao) {
        $dtDesativacao = date('Y-m-d H:i:s', strtotime($dtDesativacao));
    }

    // Verifica se é INSERT ou UPDATE
    $isEdicao = $cdPontoMedicao > 0;

    // Buscar dados da unidade para log
    $cdUnidadeLog = null;
    try {
        $sqlUnidade = "SELECT L.CD_UNIDADE FROM SIMP.dbo.LOCALIDADE L WHERE L.CD_CHAVE = :cdLocalidade";
        $stmtUnidade = $pdoSIMP->prepare($sqlUnidade);
        $stmtUnidade->execute([':cdLocalidade' => $cdLocalidade]);
        $rowUnidade = $stmtUnidade->fetch(PDO::FETCH_ASSOC);
        if ($rowUnidade) {
            $cdUnidadeLog = (int)$rowUnidade['CD_UNIDADE'];
        }
    } catch (Exception $e) {}

    if ($isEdicao) {
        // Buscar dados anteriores para log de alteração
        $dadosAnteriores = null;
        try {
            $sqlAnt = "SELECT * FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = :id";
            $stmtAnt = $pdoSIMP->prepare($sqlAnt);
            $stmtAnt->execute([':id' => $cdPontoMedicao]);
            $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // UPDATE
        $sql = "UPDATE SIMP.dbo.PONTO_MEDICAO SET
                    CD_LOCALIDADE = :cd_localidade,
                    ID_TIPO_MEDIDOR = :id_tipo_medidor,
                    DS_NOME = :ds_nome,
                    DS_LOCALIZACAO = :ds_localizacao,
                    COORDENADAS = :coordenadas,
                    ID_TIPO_LEITURA = :id_tipo_leitura,
                    OP_PERIODICIDADE_LEITURA = :op_periodicidade_leitura,
                    CD_USUARIO_RESPONSAVEL = :cd_usuario_responsavel,
                    TIPO_INSTALACAO = :tipo_instalacao,
                    DT_ATIVACAO = :dt_ativacao,
                    DT_DESATIVACAO = :dt_desativacao,
                    VL_QUANTIDADE_LIGACOES = :vl_quantidade_ligacoes,
                    VL_QUANTIDADE_ECONOMIAS = :vl_quantidade_economias,
                    DS_TAG_VAZAO = :ds_tag_vazao,
                    DS_TAG_PRESSAO = :ds_tag_pressao,
                    DS_TAG_VOLUME = :ds_tag_volume,
                    DS_TAG_RESERVATORIO = :ds_tag_reservatorio,
                    DS_TAG_TEMP_AGUA = :ds_tag_temp_agua,
                    DS_TAG_TEMP_AMBIENTE = :ds_tag_temp_ambiente,
                    LOC_INST_SAP = :loc_inst_sap,
                    VL_FATOR_CORRECAO_VAZAO = :vl_fator_correcao_vazao,
                    VL_LIMITE_INFERIOR_VAZAO = :vl_limite_inferior_vazao,
                    VL_LIMITE_SUPERIOR_VAZAO = :vl_limite_superior_vazao,
                    DS_OBSERVACAO = :ds_observacao,
                    CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario_atualizacao,
                    DT_ULTIMA_ATUALIZACAO = :dt_atualizacao
                WHERE CD_PONTO_MEDICAO = :cd_ponto_medicao";

        $params = [
            ':cd_localidade' => $cdLocalidade,
            ':id_tipo_medidor' => $idTipoMedidor,
            ':ds_nome' => $dsNome,
            ':ds_localizacao' => $dsLocalizacao,
            ':coordenadas' => $coordenadas,
            ':id_tipo_leitura' => $idTipoLeitura,
            ':op_periodicidade_leitura' => $opPeriodicidadeLeitura,
            ':cd_usuario_responsavel' => $cdUsuarioResponsavel,
            ':tipo_instalacao' => $tipoInstalacao,
            ':dt_ativacao' => $dtAtivacao,
            ':dt_desativacao' => $dtDesativacao,
            ':vl_quantidade_ligacoes' => $vlQuantidadeLigacoes,
            ':vl_quantidade_economias' => $vlQuantidadeEconomias,
            ':ds_tag_vazao' => $dsTagVazao,
            ':ds_tag_pressao' => $dsTagPressao,
            ':ds_tag_volume' => $dsTagVolume,
            ':ds_tag_reservatorio' => $dsTagReservatorio,
            ':ds_tag_temp_agua' => $dsTagTempAgua,
            ':ds_tag_temp_ambiente' => $dsTagTempAmbiente,
            ':loc_inst_sap' => $locInstSap,
            ':vl_fator_correcao_vazao' => $vlFatorCorrecaoVazao,
            ':vl_limite_inferior_vazao' => $vlLimiteInferiorVazao,
            ':vl_limite_superior_vazao' => $vlLimiteSuperiorVazao,
            ':ds_observacao' => $dsObservacao,
            ':cd_usuario_atualizacao' => $cdUsuarioAtualizacao,
            ':dt_atualizacao' => $dtAtualizacao,
            ':cd_ponto_medicao' => $cdPontoMedicao
        ];

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);

        $mensagem = 'Ponto de medição atualizado com sucesso!';

        // Registrar log de UPDATE (isolado para não afetar a operação principal)
        try {
            $alteracoes = [
                'dados_novos' => [
                    'DS_NOME' => $dsNome,
                    'CD_LOCALIDADE' => $cdLocalidade,
                    'ID_TIPO_MEDIDOR' => $idTipoMedidor,
                    'ID_TIPO_LEITURA' => $idTipoLeitura,
                    'DS_LOCALIZACAO' => $dsLocalizacao
                ]
            ];
            if ($dadosAnteriores) {
                $alteracoes['dados_anteriores'] = [
                    'DS_NOME' => $dadosAnteriores['DS_NOME'] ?? null,
                    'CD_LOCALIDADE' => $dadosAnteriores['CD_LOCALIDADE'] ?? null,
                    'ID_TIPO_MEDIDOR' => $dadosAnteriores['ID_TIPO_MEDIDOR'] ?? null,
                    'ID_TIPO_LEITURA' => $dadosAnteriores['ID_TIPO_LEITURA'] ?? null,
                    'DS_LOCALIZACAO' => $dadosAnteriores['DS_LOCALIZACAO'] ?? null
                ];
            }
            registrarLogUpdate('Cadastro de Ponto de Medição', 'Ponto de Medição', $cdPontoMedicao, $dsNome, $alteracoes, $cdUnidadeLog);
        } catch (Exception $logEx) {
            // Não interrompe a operação principal se o log falhar
            error_log('Erro ao registrar log de UPDATE Ponto de Medição: ' . $logEx->getMessage());
        }

    } else {
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.PONTO_MEDICAO (
                    CD_LOCALIDADE,
                    ID_TIPO_MEDIDOR,
                    DS_NOME,
                    DS_LOCALIZACAO,
                    COORDENADAS,
                    ID_TIPO_LEITURA,
                    OP_PERIODICIDADE_LEITURA,
                    CD_USUARIO_RESPONSAVEL,
                    TIPO_INSTALACAO,
                    DT_ATIVACAO,
                    DT_DESATIVACAO,
                    VL_QUANTIDADE_LIGACOES,
                    VL_QUANTIDADE_ECONOMIAS,
                    DS_TAG_VAZAO,
                    DS_TAG_PRESSAO,
                    DS_TAG_VOLUME,
                    DS_TAG_RESERVATORIO,
                    DS_TAG_TEMP_AGUA,
                    DS_TAG_TEMP_AMBIENTE,
                    LOC_INST_SAP,
                    VL_FATOR_CORRECAO_VAZAO,
                    VL_LIMITE_INFERIOR_VAZAO,
                    VL_LIMITE_SUPERIOR_VAZAO,
                    DS_OBSERVACAO,
                    CD_USUARIO_ULTIMA_ATUALIZACAO,
                    DT_ULTIMA_ATUALIZACAO
                ) VALUES (
                    :cd_localidade,
                    :id_tipo_medidor,
                    :ds_nome,
                    :ds_localizacao,
                    :coordenadas,
                    :id_tipo_leitura,
                    :op_periodicidade_leitura,
                    :cd_usuario_responsavel,
                    :tipo_instalacao,
                    :dt_ativacao,
                    :dt_desativacao,
                    :vl_quantidade_ligacoes,
                    :vl_quantidade_economias,
                    :ds_tag_vazao,
                    :ds_tag_pressao,
                    :ds_tag_volume,
                    :ds_tag_reservatorio,
                    :ds_tag_temp_agua,
                    :ds_tag_temp_ambiente,
                    :loc_inst_sap,
                    :vl_fator_correcao_vazao,
                    :vl_limite_inferior_vazao,
                    :vl_limite_superior_vazao,
                    :ds_observacao,
                    :cd_usuario_atualizacao,
                    :dt_atualizacao
                )";

        $stmt = $pdoSIMP->prepare($sql);
        
        $insertParams = [
            ':cd_localidade' => $cdLocalidade,
            ':id_tipo_medidor' => $idTipoMedidor,
            ':ds_nome' => $dsNome,
            ':ds_localizacao' => $dsLocalizacao,
            ':coordenadas' => $coordenadas,
            ':id_tipo_leitura' => $idTipoLeitura,
            ':op_periodicidade_leitura' => $opPeriodicidadeLeitura,
            ':cd_usuario_responsavel' => $cdUsuarioResponsavel,
            ':tipo_instalacao' => $tipoInstalacao,
            ':dt_ativacao' => $dtAtivacao,
            ':dt_desativacao' => $dtDesativacao,
            ':vl_quantidade_ligacoes' => $vlQuantidadeLigacoes,
            ':vl_quantidade_economias' => $vlQuantidadeEconomias,
            ':ds_tag_vazao' => $dsTagVazao,
            ':ds_tag_pressao' => $dsTagPressao,
            ':ds_tag_volume' => $dsTagVolume,
            ':ds_tag_reservatorio' => $dsTagReservatorio,
            ':ds_tag_temp_agua' => $dsTagTempAgua,
            ':ds_tag_temp_ambiente' => $dsTagTempAmbiente,
            ':loc_inst_sap' => $locInstSap,
            ':vl_fator_correcao_vazao' => $vlFatorCorrecaoVazao,
            ':vl_limite_inferior_vazao' => $vlLimiteInferiorVazao,
            ':vl_limite_superior_vazao' => $vlLimiteSuperiorVazao,
            ':ds_observacao' => $dsObservacao,
            ':cd_usuario_atualizacao' => $cdUsuarioAtualizacao,
            ':dt_atualizacao' => $dtAtualizacao
        ];
        
        $stmt->execute($insertParams);

        // Pegar o ID inserido usando SCOPE_IDENTITY() (SQL Server)
        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $cdPontoMedicao = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
        
        $mensagem = 'Ponto de medição cadastrado com sucesso!';

        // Registrar log de INSERT (isolado para não afetar a operação principal)
        try {
            $dadosInseridos = [
                'DS_NOME' => $dsNome,
                'CD_LOCALIDADE' => $cdLocalidade,
                'ID_TIPO_MEDIDOR' => $idTipoMedidor,
                'ID_TIPO_LEITURA' => $idTipoLeitura,
                'DS_LOCALIZACAO' => $dsLocalizacao,
                'COORDENADAS' => $coordenadas,
                'DS_TAG_VAZAO' => $dsTagVazao,
                'DS_TAG_PRESSAO' => $dsTagPressao
            ];
            registrarLogInsert('Cadastro de Ponto de Medição', 'Ponto de Medição', $cdPontoMedicao, $dsNome, $dadosInseridos, $cdUnidadeLog);
        } catch (Exception $logEx) {
            // Não interrompe a operação principal se o log falhar
            error_log('Erro ao registrar log de INSERT Ponto de Medição: ' . $logEx->getMessage());
        }
    }

    $response = [
        'success' => true,
        'message' => $mensagem,
        'cd_ponto_medicao' => $cdPontoMedicao
    ];
    
    // Incluir warnings PHP se houver (para debug)
    if (!empty($phpErrors)) {
        $response['warnings'] = $phpErrors;
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    // Registrar log de erro
    try {
        registrarLogErro('Cadastro de Ponto de Medição', $isEdicao ? 'UPDATE' : 'INSERT', $e->getMessage(), ['cd_ponto_medicao' => $cdPontoMedicao ?? null, 'ds_nome' => $dsNome ?? null]);
    } catch (Exception $logEx) {}
    
    $response = [
        'success' => false,
        'message' => 'Erro ao salvar: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine()
    ];
    if (!empty($phpErrors)) {
        $response['warnings'] = $phpErrors;
    }
    echo json_encode($response);

} catch (Exception $e) {
    // Registrar log de erro
    try {
        registrarLogErro('Cadastro de Ponto de Medição', $isEdicao ? 'UPDATE' : 'INSERT', $e->getMessage(), ['cd_ponto_medicao' => $cdPontoMedicao ?? null]);
    } catch (Exception $logEx) {}
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    if (!empty($phpErrors)) {
        $response['warnings'] = $phpErrors;
    }
    echo json_encode($response);
}