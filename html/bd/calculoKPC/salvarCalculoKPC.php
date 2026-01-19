<?php
/**
 * SIMP - Salvar Cálculo de KPC
 * 
 * Salva ou atualiza um cálculo de KPC com suas leituras.
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';

    // Receber dados JSON
    $json = file_get_contents('php://input');
    $dados = json_decode($json, true);

    if (!$dados) {
        throw new Exception('Dados inválidos');
    }

    // Extrair dados principais
    $cdChave = isset($dados['cd_chave']) ? (int)$dados['cd_chave'] : 0;
    $cdPontoMedicao = isset($dados['cd_ponto_medicao']) ? (int)$dados['cd_ponto_medicao'] : 0;
    $dtLeitura = isset($dados['dt_leitura']) ? $dados['dt_leitura'] : null;
    $idMetodo = isset($dados['id_metodo']) ? (int)$dados['id_metodo'] : 1;
    
    // Parâmetros do cálculo
    $vlDiametroNominal = isset($dados['vl_diametro_nominal']) ? (float)$dados['vl_diametro_nominal'] : 0;
    $vlDiametroReal = isset($dados['vl_diametro_real']) ? (float)$dados['vl_diametro_real'] : 0;
    $vlProjecaoTap = isset($dados['vl_projecao_tap']) ? (float)$dados['vl_projecao_tap'] : 0;
    $vlRaioTip = isset($dados['vl_raio_tip']) ? (float)$dados['vl_raio_tip'] : 0;
    $vlTemperatura = isset($dados['vl_temperatura']) ? (float)$dados['vl_temperatura'] : 25;
    
    // Resultados do cálculo
    $vlFatorVelocidade = isset($dados['vl_fator_velocidade']) ? (float)$dados['vl_fator_velocidade'] : 0;
    $vlCorrecaoDiametro = isset($dados['vl_correcao_diametro']) ? (float)$dados['vl_correcao_diametro'] : 0;
    $vlCorrecaoProjecaoTap = isset($dados['vl_correcao_projecao_tap']) ? (float)$dados['vl_correcao_projecao_tap'] : 0;
    $vlAreaEfetiva = isset($dados['vl_area_efetiva']) ? (float)$dados['vl_area_efetiva'] : 0;
    $vlKPC = isset($dados['vl_kpc']) ? (float)$dados['vl_kpc'] : 0;
    $vlVazao = isset($dados['vl_vazao']) && $dados['vl_vazao'] !== '' ? (float)$dados['vl_vazao'] : null;
    $vlPressao = isset($dados['vl_pressao']) && $dados['vl_pressao'] !== '' ? (float)$dados['vl_pressao'] : null;
    
    // Responsáveis
    $cdTecnicoResponsavel = isset($dados['cd_tecnico_responsavel']) ? (int)$dados['cd_tecnico_responsavel'] : 0;
    $cdUsuarioResponsavel = isset($dados['cd_usuario_responsavel']) ? (int)$dados['cd_usuario_responsavel'] : 0;
    
    // Observação
    $dsObservacao = isset($dados['ds_observacao']) ? mb_substr(trim($dados['ds_observacao']), 0, 200) : null;
    
    // Leituras
    $leituras = isset($dados['leituras']) ? $dados['leituras'] : [];

    // Validações obrigatórias
    if (empty($cdPontoMedicao)) {
        throw new Exception('Ponto de Medição é obrigatório');
    }
    if (empty($dtLeitura)) {
        throw new Exception('Data da Leitura é obrigatória');
    }
    if ($vlDiametroNominal <= 0) {
        throw new Exception('Diâmetro Nominal é obrigatório');
    }
    if ($vlDiametroReal <= 0) {
        throw new Exception('Diâmetro Real é obrigatório');
    }
    if ($vlRaioTip <= 0) {
        throw new Exception('Raio do TIP é obrigatório');
    }
    if (empty($cdTecnicoResponsavel)) {
        throw new Exception('Técnico Responsável é obrigatório');
    }
    if (empty($cdUsuarioResponsavel)) {
        throw new Exception('Usuário Responsável é obrigatório');
    }
    if (empty($leituras)) {
        throw new Exception('É necessário preencher ao menos uma leitura');
    }

    // Usuário da sessão para auditoria
    $cdUsuarioAtualizacao = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : 1;
    $dtAtualizacao = date('Y-m-d H:i:s');

    // Converter data para formato SQL Server
    $dtLeitura = date('Y-m-d H:i:s', strtotime($dtLeitura));

    // Iniciar transação
    $pdoSIMP->beginTransaction();

    try {
        $isEdicao = $cdChave > 0;

        if ($isEdicao) {
            // UPDATE do cálculo existente
            $sql = "UPDATE SIMP.dbo.CALCULO_KPC SET
                        VL_DIAMETRO_NOMINAL = :vl_diametro_nominal,
                        VL_DIAMETRO_REAL = :vl_diametro_real,
                        VL_PROJECAO_TAP = :vl_projecao_tap,
                        VL_RAIO_TIP = :vl_raio_tip,
                        VL_TEMPERATURA = :vl_temperatura,
                        VL_FATOR_VELOCIDADE = :vl_fator_velocidade,
                        VL_CORRECAO_DIAMETRO = :vl_correcao_diametro,
                        VL_CORRECAO_PROJECAO_TAP = :vl_correcao_projecao_tap,
                        VL_AREA_EFETIVA = :vl_area_efetiva,
                        VL_KPC = :vl_kpc,
                        VL_VAZAO = :vl_vazao,
                        VL_PRESSAO = :vl_pressao,
                        CD_TECNICO_RESPONSAVEL = :cd_tecnico_responsavel,
                        CD_USUARIO_RESPONSAVEL = :cd_usuario_responsavel,
                        DS_OBSERVACAO = :ds_observacao,
                        ID_METODO = :id_metodo,
                        CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario_atualizacao,
                        DT_ULTIMA_ATUALIZACAO = :dt_atualizacao
                    WHERE CD_CHAVE = :cd_chave";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([
                ':vl_diametro_nominal' => $vlDiametroNominal,
                ':vl_diametro_real' => $vlDiametroReal,
                ':vl_projecao_tap' => $vlProjecaoTap,
                ':vl_raio_tip' => $vlRaioTip,
                ':vl_temperatura' => $vlTemperatura,
                ':vl_fator_velocidade' => $vlFatorVelocidade,
                ':vl_correcao_diametro' => $vlCorrecaoDiametro,
                ':vl_correcao_projecao_tap' => $vlCorrecaoProjecaoTap,
                ':vl_area_efetiva' => $vlAreaEfetiva,
                ':vl_kpc' => $vlKPC,
                ':vl_vazao' => $vlVazao,
                ':vl_pressao' => $vlPressao,
                ':cd_tecnico_responsavel' => $cdTecnicoResponsavel,
                ':cd_usuario_responsavel' => $cdUsuarioResponsavel,
                ':ds_observacao' => $dsObservacao,
                ':id_metodo' => $idMetodo,
                ':cd_usuario_atualizacao' => $cdUsuarioAtualizacao,
                ':dt_atualizacao' => $dtAtualizacao,
                ':cd_chave' => $cdChave
            ]);

            // Excluir leituras antigas
            $sqlDeleteLeituras = "DELETE FROM SIMP.dbo.CALCULO_KPC_LEITURA WHERE CD_CALCULO_KPC = :cd_chave";
            $stmtDelete = $pdoSIMP->prepare($sqlDeleteLeituras);
            $stmtDelete->execute([':cd_chave' => $cdChave]);

            $mensagem = 'Cálculo de KPC atualizado com sucesso!';

        } else {
            // Gerar próximo código
            $sqlMaxCodigo = "SELECT ISNULL(MAX(CD_CODIGO), 0) + 1 AS PROXIMO FROM SIMP.dbo.CALCULO_KPC WHERE CD_ANO = :ano";
            $anoAtual = date('y');
            $stmtMax = $pdoSIMP->prepare($sqlMaxCodigo);
            $stmtMax->execute([':ano' => $anoAtual]);
            $proximoCodigo = (int)$stmtMax->fetch(PDO::FETCH_ASSOC)['PROXIMO'];

            // INSERT do novo cálculo
            $sql = "INSERT INTO SIMP.dbo.CALCULO_KPC (
                        CD_PONTO_MEDICAO,
                        CD_CODIGO,
                        CD_ANO,
                        ID_SITUACAO,
                        DT_LEITURA,
                        VL_DIAMETRO_NOMINAL,
                        VL_DIAMETRO_REAL,
                        VL_PROJECAO_TAP,
                        VL_RAIO_TIP,
                        VL_TEMPERATURA,
                        VL_FATOR_VELOCIDADE,
                        VL_CORRECAO_DIAMETRO,
                        VL_CORRECAO_PROJECAO_TAP,
                        VL_AREA_EFETIVA,
                        VL_KPC,
                        VL_VAZAO,
                        VL_PRESSAO,
                        VL_DESVIO_RELATIVO,
                        CD_TECNICO_RESPONSAVEL,
                        CD_USUARIO_RESPONSAVEL,
                        DS_OBSERVACAO,
                        ID_METODO,
                        CD_USUARIO_ULTIMA_ATUALIZACAO,
                        DT_ULTIMA_ATUALIZACAO
                    ) VALUES (
                        :cd_ponto_medicao,
                        :cd_codigo,
                        :cd_ano,
                        1,
                        :dt_leitura,
                        :vl_diametro_nominal,
                        :vl_diametro_real,
                        :vl_projecao_tap,
                        :vl_raio_tip,
                        :vl_temperatura,
                        :vl_fator_velocidade,
                        :vl_correcao_diametro,
                        :vl_correcao_projecao_tap,
                        :vl_area_efetiva,
                        :vl_kpc,
                        :vl_vazao,
                        :vl_pressao,
                        1,
                        :cd_tecnico_responsavel,
                        :cd_usuario_responsavel,
                        :ds_observacao,
                        :id_metodo,
                        :cd_usuario_atualizacao,
                        :dt_atualizacao
                    )";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([
                ':cd_ponto_medicao' => $cdPontoMedicao,
                ':cd_codigo' => $proximoCodigo,
                ':cd_ano' => $anoAtual,
                ':dt_leitura' => $dtLeitura,
                ':vl_diametro_nominal' => $vlDiametroNominal,
                ':vl_diametro_real' => $vlDiametroReal,
                ':vl_projecao_tap' => $vlProjecaoTap,
                ':vl_raio_tip' => $vlRaioTip,
                ':vl_temperatura' => $vlTemperatura,
                ':vl_fator_velocidade' => $vlFatorVelocidade,
                ':vl_correcao_diametro' => $vlCorrecaoDiametro,
                ':vl_correcao_projecao_tap' => $vlCorrecaoProjecaoTap,
                ':vl_area_efetiva' => $vlAreaEfetiva,
                ':vl_kpc' => $vlKPC,
                ':vl_vazao' => $vlVazao,
                ':vl_pressao' => $vlPressao,
                ':cd_tecnico_responsavel' => $cdTecnicoResponsavel,
                ':cd_usuario_responsavel' => $cdUsuarioResponsavel,
                ':ds_observacao' => $dsObservacao,
                ':id_metodo' => $idMetodo,
                ':cd_usuario_atualizacao' => $cdUsuarioAtualizacao,
                ':dt_atualizacao' => $dtAtualizacao
            ]);

            // Obter o ID gerado
            $cdChave = $pdoSIMP->lastInsertId();

            $mensagem = 'Cálculo de KPC cadastrado com sucesso!';
        }

        // Inserir leituras
        $sqlLeitura = "INSERT INTO SIMP.dbo.CALCULO_KPC_LEITURA (
                            CD_CALCULO_KPC,
                            CD_POSICAO_LEITURA,
                            CD_ORDEM_LEITURA,
                            VL_DEFLEXAO_MEDIDA,
                            VL_POSICAO
                        ) VALUES (
                            :cd_calculo_kpc,
                            :cd_posicao_leitura,
                            :cd_ordem_leitura,
                            :vl_deflexao_medida,
                            :vl_posicao
                        )";
        
        $stmtLeitura = $pdoSIMP->prepare($sqlLeitura);
        
        foreach ($leituras as $leitura) {
            $cdOrdemLeitura = (int)$leitura['leitura'];    // Número da leitura (1-20 ou 21 para média)
            $cdPosicaoLeitura = (int)$leitura['ponto'];    // Número do ponto (1-11)
            $deflexao = (float)$leitura['deflexao'];
            $vlPosicao = isset($leitura['posicao']) ? (float)$leitura['posicao'] : null;
            
            $stmtLeitura->execute([
                ':cd_calculo_kpc' => $cdChave,
                ':cd_posicao_leitura' => $cdPosicaoLeitura,
                ':cd_ordem_leitura' => $cdOrdemLeitura,
                ':vl_deflexao_medida' => $deflexao,
                ':vl_posicao' => $vlPosicao
            ]);
        }

        // Commit da transação
        $pdoSIMP->commit();

        echo json_encode([
            'success' => true,
            'message' => $mensagem,
            'id' => $cdChave
        ]);

    } catch (Exception $e) {
        // Rollback em caso de erro
        $pdoSIMP->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
