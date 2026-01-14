<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Salvar/Atualizar Ponto de Medição
 */

header('Content-Type: application/json; charset=utf-8');

// DEBUG: Log para arquivo (remover em produção)
$debugLog = __DIR__ . '/salvar_debug.log';
$debugData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'POST' => $_POST
];
file_put_contents($debugLog, print_r($debugData, true) . "\n" . str_repeat('-', 50) . "\n", FILE_APPEND);

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
    
    // DEBUG: Log da decisão INSERT/UPDATE
    file_put_contents($debugLog, "MODO: " . ($isEdicao ? "UPDATE (ID=$cdPontoMedicao)" : "INSERT (novo registro)") . "\n", FILE_APPEND);

    if ($isEdicao) {
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
        
        // DEBUG: Log dos parâmetros do INSERT
        file_put_contents($debugLog, "INSERT PARAMS:\n" . print_r($insertParams, true) . "\n", FILE_APPEND);
        
        $executeResult = $stmt->execute($insertParams);
        
        // DEBUG: Log do resultado
        file_put_contents($debugLog, "EXECUTE RESULT: " . ($executeResult ? 'TRUE' : 'FALSE') . "\n", FILE_APPEND);
        file_put_contents($debugLog, "ROW COUNT: " . $stmt->rowCount() . "\n", FILE_APPEND);

        // Pegar o ID inserido usando SCOPE_IDENTITY() (SQL Server)
        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $cdPontoMedicao = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
        
        // DEBUG: Log do ID
        file_put_contents($debugLog, "NEW ID: " . ($cdPontoMedicao ?? 'NULL') . "\n" . str_repeat('=', 50) . "\n", FILE_APPEND);
        
        $mensagem = 'Ponto de medição cadastrado com sucesso!';
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
    // DEBUG: Log do erro PDO
    file_put_contents($debugLog, "PDO EXCEPTION: " . $e->getMessage() . "\nCode: " . $e->getCode() . "\nLine: " . $e->getLine() . "\n", FILE_APPEND);
    
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
    // DEBUG: Log do erro genérico
    file_put_contents($debugLog, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    if (!empty($phpErrors)) {
        $response['warnings'] = $phpErrors;
    }
    echo json_encode($response);
}