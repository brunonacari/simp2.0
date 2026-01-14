<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Buscar Metas Mensais do Ponto de Medição
 */

header('Content-Type: application/json; charset=utf-8');

include_once '../conexao.php';

try {
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) && $_GET['cd_ponto_medicao'] !== '' ? (int)$_GET['cd_ponto_medicao'] : null;

    if (empty($cdPontoMedicao)) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit;
    }

    $sql = "SELECT 
                CD_CHAVE,
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
                DT_ULTIMA_ATUALIZACAO
            FROM SIMP.dbo.META_MENSAL_PONTO_MEDICAO
            WHERE CD_PONTO_MEDICAO = :cd_ponto_medicao
            ORDER BY ANO_META DESC, MES_META ASC";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_ponto_medicao' => $cdPontoMedicao]);
    $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formata os dados
    $dadosFormatados = [];
    foreach ($metas as $meta) {
        $dadosFormatados[] = [
            'CD_CHAVE' => $meta['CD_CHAVE'],
            'CD_PONTO_MEDICAO' => $meta['CD_PONTO_MEDICAO'],
            'MES_META' => $meta['MES_META'],
            'ANO_META' => $meta['ANO_META'],
            'VL_META_L_S' => $meta['VL_META_L_S'],
            'VL_META_PRESSAO_ALTA' => $meta['VL_META_PRESSAO_ALTA'],
            'VL_META_PRESSAO_BAIXA' => $meta['VL_META_PRESSAO_BAIXA'],
            'VL_META_RESERVATORIO_ALTA' => $meta['VL_META_RESERVATORIO_ALTA'],
            'VL_META_RESERVATORIO_BAIXA' => $meta['VL_META_RESERVATORIO_BAIXA'],
            'VL_META_NIVEL_RESERVATORIO' => $meta['VL_META_NIVEL_RESERVATORIO'],
            'ID_TIPO_MEDIDOR' => $meta['ID_TIPO_MEDIDOR'],
            'DT_ULTIMA_ATUALIZACAO' => $meta['DT_ULTIMA_ATUALIZACAO']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $dadosFormatados
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar metas: ' . $e->getMessage(),
        'data' => []
    ]);
}