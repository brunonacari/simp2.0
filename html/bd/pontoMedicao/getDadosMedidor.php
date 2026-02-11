<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Buscar Dados Específicos do Medidor
 */

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(0);

// Verificação de autenticação
require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO DE PONTO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) && $_GET['cd_ponto_medicao'] !== '' ? (int) $_GET['cd_ponto_medicao'] : null;
    $idTipoMedidor = isset($_GET['id_tipo_medidor']) && $_GET['id_tipo_medidor'] !== '' ? (int) $_GET['id_tipo_medidor'] : null;

    if (empty($cdPontoMedicao) || empty($idTipoMedidor)) {
        echo json_encode([
            'success' => true,
            'data' => null
        ]);
        exit;
    }

    // Determina a tabela e campos baseado no tipo
    $tabela = '';
    $campos = '';

    switch ($idTipoMedidor) {
        case 1: // Macromedidor
            $tabela = 'MACROMEDIDOR';
            $campos = "CD_CHAVE, CD_PONTO_MEDICAO, CD_TIPO_MEDIDOR, DS_MARCA, DS_MODELO, DS_SERIE, DS_TAG,
                       DT_FABRICACAO, DS_PATRIMONIO_PRIMARIO, DS_PATRIMONIO_SECUNDARIO, VL_DIAMETRO,
                       DS_REVESTIMENTO, VL_PERDA_CARGA_FABRICANTE, VL_CAPACIDADE_NOMINAL, VL_K_FABRICANTE,
                       VL_VAZAO_ESPERADA, VL_PRESSAO_MAXIMA, DS_TIPO_FLANGE, DS_ALTURA_SOLEIRA,
                       DS_NATUREZA_PAREDE, DS_LARGURA_RELATIVA, DS_LARGURA_GARGANTA, VL_DIAMETRO_REDE,
                       VL_COTA, ID_PRODUTO, CD_ESTACAO_PITOMETRICA";
            break;

        case 2: // Estação Pitométrica
            $tabela = 'ESTACAO_PITOMETRICA';
            $campos = "CD_CHAVE, CD_PONTO_MEDICAO, VL_COTA_GEOGRAFICA, VL_DIAMETRO, DS_LINHA,
                       DS_SISTEMA, DS_REVESTIMENTO, TP_PERIODICIDADE_LEVANTAMENTO, VL_DIAMETRO_REDE,
                       DS_TAG";
            break;

        case 4: // Medidor Pressão
            $tabela = 'MEDIDOR_PRESSAO';
            $campos = "CD_CHAVE, CD_PONTO_MEDICAO, DS_MATRICULA_USUARIO, DS_NUMERO_SERIE_EQUIPAMENTO,
                       VL_DIAMETRO, VL_DIAMETRO_REDE, DS_MATERIAL, DS_COTA, OP_TELEMETRIA,
                       DS_ENDERECO, DT_INSTALACAO, DS_COORDENADAS, DS_TAG";
            break;

        case 6: // Nível Reservatório
            $tabela = 'NIVEL_RESERVATORIO';
            $campos = "CD_CHAVE, CD_PONTO_MEDICAO, CD_TIPO_MEDIDOR, DS_MARCA, DS_MODELO, DS_SERIE, DS_TAG,
                       DT_FABRICACAO, DT_INSTALACAO, DS_PATRIMONIO_PRIMARIO, DS_PATRIMONIO_SECUNDARIO,
                       COTA_EXTRAVASAMENTO_M, COTA_EXTRAVASAMENTO_P, VL_PRESSAO_MAXIMA_SUCCAO,
                       VL_PRESSAO_MAXIMA_RECALQUE, VL_VOLUME_TOTAL, VL_VOLUME_CAMARA_A, VL_VOLUME_CAMARA_B,
                       ID_PRODUTO, CD_TIPO_RESERVATORIO, DS_ALTURA_MAXIMA, VL_NA, VL_COTA";
            break;

        case 8: // Hidrômetro
            $tabela = 'HIDROMETRO';
            $campos = "CD_CHAVE, CD_PONTO_MEDICAO, DS_MATRICULA_USUARIO, DS_NUMERO_SERIE_EQUIPAMENTO,
                       VL_DIAMETRO, VL_DIAMETRO_REDE, DS_MATERIAL, DS_COTA, DS_ENDERECO,
                       DT_INSTALACAO, DS_COORDENADAS, ID_TEMPO_OPERACAO, VL_LEITURA_LIMITE, VL_MULTIPLICADOR,
                       DS_TAG";
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Tipo de medidor não suportado'
            ]);
            exit;
    }

    $sql = "SELECT {$campos} FROM SIMP.dbo.{$tabela} WHERE CD_PONTO_MEDICAO = :cd_ponto_medicao";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_ponto_medicao' => $cdPontoMedicao]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    // Formata datas se existirem
    if ($dados) {
        foreach ($dados as $key => $value) {
            if (strpos($key, 'DT_') === 0 && $value) {
                $dados[$key] = date('Y-m-d', strtotime($value));
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $dados,
        'tabela' => $tabela
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}