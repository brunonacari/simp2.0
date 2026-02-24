<?php
/**
 * SIMP - Buscar Itens (Pontos de Medição) de uma Unidade Operacional
 * Endpoint AJAX para lazy load dos itens em entidade.php
 * 
 * Parâmetros GET:
 *   - cdValor (int): CD_CHAVE do ENTIDADE_VALOR
 * 
 * Retorna JSON com array de itens contendo dados do ponto, TAGs, período, etc.
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

    $cdValor = isset($_GET['cdValor']) ? (int)$_GET['cdValor'] : 0;

    if ($cdValor <= 0) {
        throw new Exception('Código do valor não informado');
    }

    // Verificar se coluna NR_ORDEM existe
    $temNrOrdem = false;
    try {
        $pdoSIMP->query("SELECT TOP 1 NR_ORDEM FROM SIMP.dbo.ENTIDADE_VALOR_ITEM");
        $temNrOrdem = true;
    } catch (Exception $e) {
        $temNrOrdem = false;
    }

    // Montar ORDER BY conforme existência de NR_ORDEM
    $orderBy = $temNrOrdem
        ? "ORDER BY ISNULL(EVI.NR_ORDEM, 999999), PM.DS_NOME"
        : "ORDER BY PM.DS_NOME";

    $sql = "SELECT 
                EVI.CD_CHAVE AS ITEM_CD,
                EVI.CD_PONTO_MEDICAO AS ITEM_PONTO,
                EVI.DT_INICIO AS ITEM_DT_INICIO,
                EVI.DT_FIM AS ITEM_DT_FIM,
                EVI.ID_OPERACAO AS ITEM_OPERACAO,
                " . ($temNrOrdem ? "EVI.NR_ORDEM AS ITEM_ORDEM," : "NULL AS ITEM_ORDEM,") . "
                PM.DS_NOME AS PONTO_NOME,
                PM.ID_TIPO_MEDIDOR,
                PM.DS_TAG_VAZAO,
                PM.DS_TAG_PRESSAO,
                PM.DS_TAG_VOLUME,
                PM.DS_TAG_RESERVATORIO,
                PM.DS_TAG_TEMP_AGUA,
                PM.DS_TAG_TEMP_AMBIENTE,
                L.CD_LOCALIDADE,
                L.CD_UNIDADE
            FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE EVI.CD_ENTIDADE_VALOR = :cdValor
            $orderBy";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cdValor' => $cdValor]);

    $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
    $itens = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Gerar código formatado do ponto
        $letraTipo = $letrasTipo[$row['ID_TIPO_MEDIDOR']] ?? 'X';
        $codigoPonto = ($row['CD_LOCALIDADE'] ?? '') . '-' .
            str_pad($row['ITEM_PONTO'], 6, '0', STR_PAD_LEFT) . '-' .
            $letraTipo . '-' .
            ($row['CD_UNIDADE'] ?? '');

        // Verificar se expirado
        $expirado = false;
        if (!empty($row['ITEM_DT_FIM'])) {
            try {
                $dtFimObj = new DateTime($row['ITEM_DT_FIM']);
                $hoje = new DateTime();
                $expirado = $dtFimObj < $hoje;
            } catch (Exception $e) {
                $expirado = false;
            }
        }

        // Formatar datas para exibição
        $dtInicioFmt = '';
        $dtFimFmt = '';
        $dtInicioVal = '';
        $dtFimVal = '';
        if (!empty($row['ITEM_DT_INICIO'])) {
            $dtInicioFmt = date('d/m/Y', strtotime($row['ITEM_DT_INICIO']));
            $dtInicioVal = date('Y-m-d', strtotime($row['ITEM_DT_INICIO']));
        }
        if (!empty($row['ITEM_DT_FIM'])) {
            $dtFimFmt = date('d/m/Y', strtotime($row['ITEM_DT_FIM']));
            $dtFimVal = date('Y-m-d', strtotime($row['ITEM_DT_FIM']));
        }

        // Montar período legível
        $periodo = [];
        if ($dtInicioFmt) $periodo[] = 'Início: ' . $dtInicioFmt;
        if ($dtFimFmt)    $periodo[] = 'Fim: ' . $dtFimFmt;
        $periodoTexto = implode(' | ', $periodo) ?: 'Período não definido';

        // Coletar TAGs não vazias
        $tags = [];
        if (!empty($row['DS_TAG_VAZAO']))        $tags[] = 'V: ' . $row['DS_TAG_VAZAO'];
        if (!empty($row['DS_TAG_PRESSAO']))       $tags[] = 'P: ' . $row['DS_TAG_PRESSAO'];
        if (!empty($row['DS_TAG_VOLUME']))        $tags[] = 'Vol: ' . $row['DS_TAG_VOLUME'];
        if (!empty($row['DS_TAG_RESERVATORIO']))  $tags[] = 'R: ' . $row['DS_TAG_RESERVATORIO'];
        if (!empty($row['DS_TAG_TEMP_AGUA']))     $tags[] = 'T.Água: ' . $row['DS_TAG_TEMP_AGUA'];
        if (!empty($row['DS_TAG_TEMP_AMBIENTE'])) $tags[] = 'T.Amb: ' . $row['DS_TAG_TEMP_AMBIENTE'];

        $itens[] = [
            'cd'              => (int)$row['ITEM_CD'],
            'cdPonto'         => (int)$row['ITEM_PONTO'],
            'pontoNome'       => $row['PONTO_NOME'] ?? '',
            'pontoCodigo'     => $codigoPonto,
            'dtInicio'        => $row['ITEM_DT_INICIO'],
            'dtFim'           => $row['ITEM_DT_FIM'],
            'dtInicioVal'     => $dtInicioVal,
            'dtFimVal'        => $dtFimVal,
            'periodoTexto'    => $periodoTexto,
            'operacao'        => $row['ITEM_OPERACAO'],
            'ordem'           => $row['ITEM_ORDEM'],
            'expirado'        => $expirado,
            'tags'            => $tags,
            'tagVazao'        => $row['DS_TAG_VAZAO'] ?? '',
            'tagPressao'      => $row['DS_TAG_PRESSAO'] ?? '',
            'tagVolume'       => $row['DS_TAG_VOLUME'] ?? '',
            'tagReservatorio' => $row['DS_TAG_RESERVATORIO'] ?? '',
            'tagTempAgua'     => $row['DS_TAG_TEMP_AGUA'] ?? '',
            'tagTempAmbiente' => $row['DS_TAG_TEMP_AMBIENTE'] ?? ''
        ];
    }

    echo json_encode([
        'success'    => true,
        'data'       => $itens,
        'total'      => count($itens),
        'temNrOrdem' => $temNrOrdem
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}