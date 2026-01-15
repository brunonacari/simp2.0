<?php
include_once '../conexao.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo nao permitido');
    }

    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;
    $isEdicao = $cdChave > 0;

    // Campos obrigatorios
    $cdLocalidade = isset($_POST['cd_localidade']) ? (int)$_POST['cd_localidade'] : 0;
    $dsCodigo = isset($_POST['ds_codigo']) ? trim($_POST['ds_codigo']) : '';
    $dsNome = isset($_POST['ds_nome']) ? trim($_POST['ds_nome']) : '';
    $dsLocalizacao = isset($_POST['ds_localizacao']) ? trim($_POST['ds_localizacao']) : '';
    $cdUsuarioResponsavel = isset($_POST['cd_usuario_responsavel']) ? (int)$_POST['cd_usuario_responsavel'] : 0;
    $tpEixo = isset($_POST['tp_eixo']) ? trim($_POST['tp_eixo']) : '';
    $vlDiametroRotorBomba = isset($_POST['vl_diametro_rotor_bomba']) ? (float)$_POST['vl_diametro_rotor_bomba'] : 0;
    $vlAlturaManometricaBomba = isset($_POST['vl_altura_manometrica_bomba']) ? (float)$_POST['vl_altura_manometrica_bomba'] : 0;
    $vlTensaoMotor = isset($_POST['vl_tensao_motor']) ? (float)$_POST['vl_tensao_motor'] : 0;
    $vlCorrenteEletricaMotor = isset($_POST['vl_corrente_eletrica_motor']) ? (float)$_POST['vl_corrente_eletrica_motor'] : 0;
    $vlPotenciaMotor = isset($_POST['vl_potencia_motor']) ? (float)$_POST['vl_potencia_motor'] : 0;

    // Campos opcionais
    $dsObservacao = isset($_POST['ds_observacao']) ? trim($_POST['ds_observacao']) : null;
    $dsFabricanteBomba = isset($_POST['ds_fabricante_bomba']) && $_POST['ds_fabricante_bomba'] !== '' ? trim($_POST['ds_fabricante_bomba']) : null;
    $dsTipoBomba = isset($_POST['ds_tipo_bomba']) && $_POST['ds_tipo_bomba'] !== '' ? trim($_POST['ds_tipo_bomba']) : null;
    $dsSerieBomba = isset($_POST['ds_serie_bomba']) && $_POST['ds_serie_bomba'] !== '' ? trim($_POST['ds_serie_bomba']) : null;
    $vlVazaoBomba = isset($_POST['vl_vazao_bomba']) && $_POST['vl_vazao_bomba'] !== '' ? (float)$_POST['vl_vazao_bomba'] : null;
    $vlRotacaoBomba = isset($_POST['vl_rotacao_bomba']) && $_POST['vl_rotacao_bomba'] !== '' ? (float)$_POST['vl_rotacao_bomba'] : null;
    $vlAreaSuccaoBomba = isset($_POST['vl_area_succao_bomba']) && $_POST['vl_area_succao_bomba'] !== '' ? (float)$_POST['vl_area_succao_bomba'] : null;
    $vlAreaRecalqueBomba = isset($_POST['vl_area_recalque_bomba']) && $_POST['vl_area_recalque_bomba'] !== '' ? (float)$_POST['vl_area_recalque_bomba'] : null;
    $dsFabricanteMotor = isset($_POST['ds_fabricante_motor']) && $_POST['ds_fabricante_motor'] !== '' ? trim($_POST['ds_fabricante_motor']) : null;
    $dsTipoMotor = isset($_POST['ds_tipo_motor']) && $_POST['ds_tipo_motor'] !== '' ? trim($_POST['ds_tipo_motor']) : null;
    $dsSerieMotor = isset($_POST['ds_serie_motor']) && $_POST['ds_serie_motor'] !== '' ? trim($_POST['ds_serie_motor']) : null;
    $vlRotacaoMotor = isset($_POST['vl_rotacao_motor']) && $_POST['vl_rotacao_motor'] !== '' ? (float)$_POST['vl_rotacao_motor'] : null;

    // Validacoes
    if ($cdLocalidade <= 0) throw new Exception('Localidade e obrigatoria');
    if (empty($dsCodigo)) throw new Exception('Codigo e obrigatorio');
    if (empty($dsNome)) throw new Exception('Nome e obrigatorio');
    if (empty($dsLocalizacao)) throw new Exception('Localizacao e obrigatoria');
    if ($cdUsuarioResponsavel <= 0) throw new Exception('Responsavel e obrigatorio');
    if (empty($tpEixo)) throw new Exception('Tipo de Eixo e obrigatorio');

    $cdUsuarioAtualizacao = $_SESSION['CD_USUARIO'] ?? 1;

    if ($isEdicao) {
        $sql = "UPDATE SIMP.dbo.CONJUNTO_MOTOR_BOMBA SET
                    CD_LOCALIDADE = :cd_localidade,
                    DS_CODIGO = :ds_codigo,
                    DS_NOME = :ds_nome,
                    DS_LOCALIZACAO = :ds_localizacao,
                    DS_OBSERVACAO = :ds_observacao,
                    CD_USUARIO_RESPONSAVEL = :cd_usuario_responsavel,
                    TP_EIXO = :tp_eixo,
                    DS_FABRICANTE_BOMBA = :ds_fabricante_bomba,
                    DS_TIPO_BOMBA = :ds_tipo_bomba,
                    DS_SERIE_BOMBA = :ds_serie_bomba,
                    VL_DIAMETRO_ROTOR_BOMBA = :vl_diametro_rotor_bomba,
                    VL_VAZAO_BOMBA = :vl_vazao_bomba,
                    VL_ALTURA_MANOMETRICA_BOMBA = :vl_altura_manometrica_bomba,
                    VL_ROTACAO_BOMBA = :vl_rotacao_bomba,
                    VL_AREA_SUCCAO_BOMBA = :vl_area_succao_bomba,
                    VL_AREA_RECALQUE_BOMBA = :vl_area_recalque_bomba,
                    DS_FABRICANTE_MOTOR = :ds_fabricante_motor,
                    DS_TIPO_MOTOR = :ds_tipo_motor,
                    DS_SERIE_MOTOR = :ds_serie_motor,
                    VL_TENSAO_MOTOR = :vl_tensao_motor,
                    VL_CORRENTE_ELETRICA_MOTOR = :vl_corrente_eletrica_motor,
                    VL_POTENCIA_MOTOR = :vl_potencia_motor,
                    VL_ROTACAO_MOTOR = :vl_rotacao_motor,
                    CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario_atualizacao,
                    DT_ULTIMA_ATUALIZACAO = GETDATE()
                WHERE CD_CHAVE = :cd_chave";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cd_localidade' => $cdLocalidade,
            ':ds_codigo' => $dsCodigo,
            ':ds_nome' => $dsNome,
            ':ds_localizacao' => $dsLocalizacao,
            ':ds_observacao' => $dsObservacao,
            ':cd_usuario_responsavel' => $cdUsuarioResponsavel,
            ':tp_eixo' => $tpEixo,
            ':ds_fabricante_bomba' => $dsFabricanteBomba,
            ':ds_tipo_bomba' => $dsTipoBomba,
            ':ds_serie_bomba' => $dsSerieBomba,
            ':vl_diametro_rotor_bomba' => $vlDiametroRotorBomba,
            ':vl_vazao_bomba' => $vlVazaoBomba,
            ':vl_altura_manometrica_bomba' => $vlAlturaManometricaBomba,
            ':vl_rotacao_bomba' => $vlRotacaoBomba,
            ':vl_area_succao_bomba' => $vlAreaSuccaoBomba,
            ':vl_area_recalque_bomba' => $vlAreaRecalqueBomba,
            ':ds_fabricante_motor' => $dsFabricanteMotor,
            ':ds_tipo_motor' => $dsTipoMotor,
            ':ds_serie_motor' => $dsSerieMotor,
            ':vl_tensao_motor' => $vlTensaoMotor,
            ':vl_corrente_eletrica_motor' => $vlCorrenteEletricaMotor,
            ':vl_potencia_motor' => $vlPotenciaMotor,
            ':vl_rotacao_motor' => $vlRotacaoMotor,
            ':cd_usuario_atualizacao' => $cdUsuarioAtualizacao,
            ':cd_chave' => $cdChave
        ]);

        echo json_encode(['success' => true, 'message' => 'Registro atualizado com sucesso']);
    } else {
        $sql = "INSERT INTO SIMP.dbo.CONJUNTO_MOTOR_BOMBA (
                    CD_LOCALIDADE, DS_CODIGO, DS_NOME, DS_LOCALIZACAO, DS_OBSERVACAO,
                    CD_USUARIO_RESPONSAVEL, TP_EIXO,
                    DS_FABRICANTE_BOMBA, DS_TIPO_BOMBA, DS_SERIE_BOMBA,
                    VL_DIAMETRO_ROTOR_BOMBA, VL_VAZAO_BOMBA, VL_ALTURA_MANOMETRICA_BOMBA,
                    VL_ROTACAO_BOMBA, VL_AREA_SUCCAO_BOMBA, VL_AREA_RECALQUE_BOMBA,
                    DS_FABRICANTE_MOTOR, DS_TIPO_MOTOR, DS_SERIE_MOTOR,
                    VL_TENSAO_MOTOR, VL_CORRENTE_ELETRICA_MOTOR, VL_POTENCIA_MOTOR, VL_ROTACAO_MOTOR,
                    CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO
                ) VALUES (
                    :cd_localidade, :ds_codigo, :ds_nome, :ds_localizacao, :ds_observacao,
                    :cd_usuario_responsavel, :tp_eixo,
                    :ds_fabricante_bomba, :ds_tipo_bomba, :ds_serie_bomba,
                    :vl_diametro_rotor_bomba, :vl_vazao_bomba, :vl_altura_manometrica_bomba,
                    :vl_rotacao_bomba, :vl_area_succao_bomba, :vl_area_recalque_bomba,
                    :ds_fabricante_motor, :ds_tipo_motor, :ds_serie_motor,
                    :vl_tensao_motor, :vl_corrente_eletrica_motor, :vl_potencia_motor, :vl_rotacao_motor,
                    :cd_usuario_atualizacao, GETDATE()
                )";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cd_localidade' => $cdLocalidade,
            ':ds_codigo' => $dsCodigo,
            ':ds_nome' => $dsNome,
            ':ds_localizacao' => $dsLocalizacao,
            ':ds_observacao' => $dsObservacao,
            ':cd_usuario_responsavel' => $cdUsuarioResponsavel,
            ':tp_eixo' => $tpEixo,
            ':ds_fabricante_bomba' => $dsFabricanteBomba,
            ':ds_tipo_bomba' => $dsTipoBomba,
            ':ds_serie_bomba' => $dsSerieBomba,
            ':vl_diametro_rotor_bomba' => $vlDiametroRotorBomba,
            ':vl_vazao_bomba' => $vlVazaoBomba,
            ':vl_altura_manometrica_bomba' => $vlAlturaManometricaBomba,
            ':vl_rotacao_bomba' => $vlRotacaoBomba,
            ':vl_area_succao_bomba' => $vlAreaSuccaoBomba,
            ':vl_area_recalque_bomba' => $vlAreaRecalqueBomba,
            ':ds_fabricante_motor' => $dsFabricanteMotor,
            ':ds_tipo_motor' => $dsTipoMotor,
            ':ds_serie_motor' => $dsSerieMotor,
            ':vl_tensao_motor' => $vlTensaoMotor,
            ':vl_corrente_eletrica_motor' => $vlCorrenteEletricaMotor,
            ':vl_potencia_motor' => $vlPotenciaMotor,
            ':vl_rotacao_motor' => $vlRotacaoMotor,
            ':cd_usuario_atualizacao' => $cdUsuarioAtualizacao
        ]);

        echo json_encode(['success' => true, 'message' => 'Registro cadastrado com sucesso']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
