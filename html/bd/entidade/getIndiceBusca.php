<?php
/**
 * SIMP - Índice de Busca de Entidades (Client-Side Search)
 * Retorna JSON leve com todos os campos pesquisáveis de ENTIDADE_VALOR_ITEM.
 * Carregado uma única vez no frontend para busca instantânea sem queries pesadas.
 *
 * Retorna JSON com array de objetos contendo apenas campos de busca:
 *   { cdValor, cdTipo, pontoNome, pontoCodigo, tagVazao, tagPressao, ... }
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../includes/auth.php';
exigePermissaoTela('Cadastro de Entidade', ACESSO_LEITURA);

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    $sql = "SELECT
                EVI.CD_ENTIDADE_VALOR AS cdValor,
                EV.CD_ENTIDADE_TIPO AS cdTipo,
                EV.DS_NOME AS valorNome,
                EV.CD_ENTIDADE_VALOR_ID AS valorId,
                ET.DS_NOME AS tipoNome,
                ET.CD_ENTIDADE_TIPO_ID AS tipoId,
                EVI.CD_PONTO_MEDICAO AS cdPonto,
                PM.DS_NOME AS pontoNome,
                PM.ID_TIPO_MEDIDOR,
                ISNULL(PM.DS_TAG_VAZAO, '') AS tagVazao,
                ISNULL(PM.DS_TAG_PRESSAO, '') AS tagPressao,
                ISNULL(PM.DS_TAG_VOLUME, '') AS tagVolume,
                ISNULL(PM.DS_TAG_RESERVATORIO, '') AS tagReservatorio,
                ISNULL(PM.DS_TAG_TEMP_AGUA, '') AS tagTempAgua,
                ISNULL(PM.DS_TAG_TEMP_AMBIENTE, '') AS tagTempAmbiente,
                ISNULL(L.CD_LOCALIDADE, '') AS localidade,
                ISNULL(L.CD_UNIDADE, '') AS unidade
            FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
            INNER JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_CHAVE = EVI.CD_ENTIDADE_VALOR
            INNER JOIN SIMP.dbo.ENTIDADE_TIPO ET ON ET.CD_CHAVE = EV.CD_ENTIDADE_TIPO
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute();

    $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
    $indice = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Gerar código formatado do ponto (mesmo formato de getItensValor.php)
        $letraTipo = $letrasTipo[$row['ID_TIPO_MEDIDOR']] ?? 'X';
        $pontoCodigo = $row['localidade'] . '-' .
            str_pad($row['cdPonto'], 6, '0', STR_PAD_LEFT) . '-' .
            $letraTipo . '-' .
            $row['unidade'];

        $indice[] = [
            'v'  => (int)$row['cdValor'],
            't'  => (int)$row['cdTipo'],
            'vn' => $row['valorNome'] ?? '',
            'vi' => $row['valorId'] ?? '',
            'tn' => $row['tipoNome'] ?? '',
            'ti' => $row['tipoId'] ?? '',
            'n'  => $row['pontoNome'] ?? '',
            'c'  => $pontoCodigo,
            'tv' => $row['tagVazao'],
            'tp' => $row['tagPressao'],
            'tl' => $row['tagVolume'],
            'tr' => $row['tagReservatorio'],
            'ta' => $row['tagTempAgua'],
            'te' => $row['tagTempAmbiente'],
            'l'  => $row['localidade']
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $indice,
        'total'   => count($indice)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
