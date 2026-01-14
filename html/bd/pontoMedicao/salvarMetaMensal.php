<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Salvar Meta Mensal do Ponto de Medição
 */

header('Content-Type: application/json; charset=utf-8');

session_start();
include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Captura parâmetros
    $cdChave = isset($_POST['cd_chave']) && $_POST['cd_chave'] !== '' ? (int)$_POST['cd_chave'] : null;
    $cdPontoMedicao = isset($_POST['cd_ponto_medicao']) && $_POST['cd_ponto_medicao'] !== '' ? (int)$_POST['cd_ponto_medicao'] : null;
    $idTipoMedidor = isset($_POST['id_tipo_medidor']) && $_POST['id_tipo_medidor'] !== '' ? (int)$_POST['id_tipo_medidor'] : null;
    $mesMeta = isset($_POST['mes_meta']) && $_POST['mes_meta'] !== '' ? (int)$_POST['mes_meta'] : null;
    $anoMeta = isset($_POST['ano_meta']) && $_POST['ano_meta'] !== '' ? (int)$_POST['ano_meta'] : null;
    
    // Campos específicos por tipo de medidor
    $vlMetaLS = isset($_POST['vl_meta_l_s']) && $_POST['vl_meta_l_s'] !== '' ? (float)$_POST['vl_meta_l_s'] : null;
    $vlMetaPressaoAlta = isset($_POST['vl_meta_pressao_alta']) && $_POST['vl_meta_pressao_alta'] !== '' ? (float)$_POST['vl_meta_pressao_alta'] : null;
    $vlMetaPressaoBaixa = isset($_POST['vl_meta_pressao_baixa']) && $_POST['vl_meta_pressao_baixa'] !== '' ? (float)$_POST['vl_meta_pressao_baixa'] : null;
    $vlMetaReservatorioAlta = isset($_POST['vl_meta_reservatorio_alta']) && $_POST['vl_meta_reservatorio_alta'] !== '' ? (float)$_POST['vl_meta_reservatorio_alta'] : null;
    $vlMetaReservatorioBaixa = isset($_POST['vl_meta_reservatorio_baixa']) && $_POST['vl_meta_reservatorio_baixa'] !== '' ? (float)$_POST['vl_meta_reservatorio_baixa'] : null;
    $vlMetaNivelReservatorio = isset($_POST['vl_meta_nivel_reservatorio']) && $_POST['vl_meta_nivel_reservatorio'] !== '' ? (float)$_POST['vl_meta_nivel_reservatorio'] : null;

    // Validações
    if (empty($cdPontoMedicao)) {
        throw new Exception('Ponto de medição não informado');
    }

    if (empty($idTipoMedidor)) {
        throw new Exception('Tipo de medidor não informado');
    }

    if (empty($anoMeta)) {
        throw new Exception('Ano é obrigatório');
    }

    // Valida campos obrigatórios por tipo
    if ($idTipoMedidor == 1 && empty($vlMetaLS)) {
        throw new Exception('Meta L/S é obrigatória para Macromedidor');
    }

    if ($idTipoMedidor == 4 && (empty($vlMetaPressaoAlta) || empty($vlMetaPressaoBaixa))) {
        throw new Exception('Pressão Alta e Pressão Baixa são obrigatórias para Medidor de Pressão');
    }

    if ($idTipoMedidor == 6 && (empty($vlMetaNivelReservatorio) || empty($vlMetaReservatorioAlta) || empty($vlMetaReservatorioBaixa))) {
        throw new Exception('Nível de Extravasamento, Nível Alto e Nível Baixo são obrigatórios para Nível Reservatório');
    }

    // Usuário e data
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;
    $dtAtualizacao = date('Y-m-d H:i:s');

    // Se não informou mês, salva para o ano inteiro (12 meses)
    $mesesParaSalvar = [];
    $anoInteiro = false;
    
    if (empty($mesMeta)) {
        $anoInteiro = true;
        $mesesParaSalvar = range(1, 12);
    } else {
        $mesesParaSalvar = [$mesMeta];
    }

    $registrosSalvos = 0;
    $registrosAtualizados = 0;

    // Se for edição de um registro específico
    if ($cdChave) {
        $sql = "UPDATE SIMP.dbo.META_MENSAL_PONTO_MEDICAO SET
                    MES_META = :mes_meta,
                    ANO_META = :ano_meta,
                    VL_META_L_S = :vl_meta_l_s,
                    VL_META_PRESSAO_ALTA = :vl_meta_pressao_alta,
                    VL_META_PRESSAO_BAIXA = :vl_meta_pressao_baixa,
                    VL_META_RESERVATORIO_ALTA = :vl_meta_reservatorio_alta,
                    VL_META_RESERVATORIO_BAIXA = :vl_meta_reservatorio_baixa,
                    VL_META_NIVEL_RESERVATORIO = :vl_meta_nivel_reservatorio,
                    ID_TIPO_MEDIDOR = :id_tipo_medidor,
                    DT_ULTIMA_ATUALIZACAO = :dt_atualizacao,
                    CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario
                WHERE CD_CHAVE = :cd_chave";

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':mes_meta' => $mesMeta,
            ':ano_meta' => $anoMeta,
            ':vl_meta_l_s' => $vlMetaLS,
            ':vl_meta_pressao_alta' => $vlMetaPressaoAlta,
            ':vl_meta_pressao_baixa' => $vlMetaPressaoBaixa,
            ':vl_meta_reservatorio_alta' => $vlMetaReservatorioAlta,
            ':vl_meta_reservatorio_baixa' => $vlMetaReservatorioBaixa,
            ':vl_meta_nivel_reservatorio' => $vlMetaNivelReservatorio,
            ':id_tipo_medidor' => $idTipoMedidor,
            ':dt_atualizacao' => $dtAtualizacao,
            ':cd_usuario' => $cdUsuario,
            ':cd_chave' => $cdChave
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Meta atualizada com sucesso!'
        ]);
        exit;
    }

    // Insert para cada mês
    foreach ($mesesParaSalvar as $mes) {
        // Verifica se já existe registro para este mês/ano
        $sqlCheck = "SELECT CD_CHAVE FROM SIMP.dbo.META_MENSAL_PONTO_MEDICAO 
                     WHERE CD_PONTO_MEDICAO = :cd_ponto_medicao 
                     AND ANO_META = :ano_meta 
                     AND MES_META = :mes_meta";
        $stmtCheck = $pdoSIMP->prepare($sqlCheck);
        $stmtCheck->execute([
            ':cd_ponto_medicao' => $cdPontoMedicao,
            ':ano_meta' => $anoMeta,
            ':mes_meta' => $mes
        ]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            // Update
            $sqlUpdate = "UPDATE SIMP.dbo.META_MENSAL_PONTO_MEDICAO SET
                            VL_META_L_S = :vl_meta_l_s,
                            VL_META_PRESSAO_ALTA = :vl_meta_pressao_alta,
                            VL_META_PRESSAO_BAIXA = :vl_meta_pressao_baixa,
                            VL_META_RESERVATORIO_ALTA = :vl_meta_reservatorio_alta,
                            VL_META_RESERVATORIO_BAIXA = :vl_meta_reservatorio_baixa,
                            VL_META_NIVEL_RESERVATORIO = :vl_meta_nivel_reservatorio,
                            ID_TIPO_MEDIDOR = :id_tipo_medidor,
                            DT_ULTIMA_ATUALIZACAO = :dt_atualizacao,
                            CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario
                        WHERE CD_CHAVE = :cd_chave";

            $stmtUpdate = $pdoSIMP->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':vl_meta_l_s' => $vlMetaLS,
                ':vl_meta_pressao_alta' => $vlMetaPressaoAlta,
                ':vl_meta_pressao_baixa' => $vlMetaPressaoBaixa,
                ':vl_meta_reservatorio_alta' => $vlMetaReservatorioAlta,
                ':vl_meta_reservatorio_baixa' => $vlMetaReservatorioBaixa,
                ':vl_meta_nivel_reservatorio' => $vlMetaNivelReservatorio,
                ':id_tipo_medidor' => $idTipoMedidor,
                ':dt_atualizacao' => $dtAtualizacao,
                ':cd_usuario' => $cdUsuario,
                ':cd_chave' => $existe['CD_CHAVE']
            ]);
            $registrosAtualizados++;
        } else {
            // Insert
            $sqlInsert = "INSERT INTO SIMP.dbo.META_MENSAL_PONTO_MEDICAO (
                            CD_PONTO_MEDICAO,
                            MES_META,
                            ANO_META,
                            VL_META_L_S,
                            VL_META_PRESSAO_ALTA,
                            VL_META_PRESSAO_BAIXA,
                            VL_META_RESERVATORIO_ALTA,
                            VL_META_RESERVATORIO_BAIXA,
                            VL_META_NIVEL_RESERVATORIO,
                            ID_TIPO_MEDIDOR,
                            DT_ULTIMA_ATUALIZACAO,
                            CD_USUARIO_ULTIMA_ATUALIZACAO
                        ) VALUES (
                            :cd_ponto_medicao,
                            :mes_meta,
                            :ano_meta,
                            :vl_meta_l_s,
                            :vl_meta_pressao_alta,
                            :vl_meta_pressao_baixa,
                            :vl_meta_reservatorio_alta,
                            :vl_meta_reservatorio_baixa,
                            :vl_meta_nivel_reservatorio,
                            :id_tipo_medidor,
                            :dt_atualizacao,
                            :cd_usuario
                        )";

            $stmtInsert = $pdoSIMP->prepare($sqlInsert);
            $stmtInsert->execute([
                ':cd_ponto_medicao' => $cdPontoMedicao,
                ':mes_meta' => $mes,
                ':ano_meta' => $anoMeta,
                ':vl_meta_l_s' => $vlMetaLS,
                ':vl_meta_pressao_alta' => $vlMetaPressaoAlta,
                ':vl_meta_pressao_baixa' => $vlMetaPressaoBaixa,
                ':vl_meta_reservatorio_alta' => $vlMetaReservatorioAlta,
                ':vl_meta_reservatorio_baixa' => $vlMetaReservatorioBaixa,
                ':vl_meta_nivel_reservatorio' => $vlMetaNivelReservatorio,
                ':id_tipo_medidor' => $idTipoMedidor,
                ':dt_atualizacao' => $dtAtualizacao,
                ':cd_usuario' => $cdUsuario
            ]);
            $registrosSalvos++;
        }
    }

    // Mensagem de retorno
    if ($anoInteiro) {
        $msg = "Meta salva para todos os 12 meses de {$anoMeta}";
        if ($registrosAtualizados > 0) {
            $msg .= " ({$registrosSalvos} novos, {$registrosAtualizados} atualizados)";
        }
    } else {
        $msg = $registrosAtualizados > 0 ? 'Meta atualizada com sucesso!' : 'Meta cadastrada com sucesso!';
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'ano_inteiro' => $anoInteiro,
        'registros_salvos' => $registrosSalvos,
        'registros_atualizados' => $registrosAtualizados
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}