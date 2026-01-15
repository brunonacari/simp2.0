<?php
/**
 * SIMP - Sistema Integrado de Macromedicao e Pitometria
 * Endpoint: Buscar Pontos de Medicao com Filtros e Paginacao
 */

header('Content-Type: application/json; charset=utf-8');

// Capturar erros
$errors = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errors) {
    $errors[] = "[$errno] $errstr em $errfile:$errline";
    return true;
});

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Verificacao de autenticacao
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('CADASTRO DE PONTO', ACESSO_LEITURA);

    include_once '../conexao.php';
    
    if (!isset($pdoSIMP)) {
        throw new Exception('Conexao com banco de dados nao estabelecida');
    }

    // Captura parametros
    $cdUnidade = isset($_GET['cd_unidade']) && $_GET['cd_unidade'] !== '' ? (int)$_GET['cd_unidade'] : null;
    $cdLocalidade = isset($_GET['cd_localidade']) && $_GET['cd_localidade'] !== '' ? (int)$_GET['cd_localidade'] : null;
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) && $_GET['cd_ponto_medicao'] !== '' ? (int)$_GET['cd_ponto_medicao'] : null;
    $tipoMedidor = isset($_GET['tipo_medidor']) && $_GET['tipo_medidor'] !== '' ? (int)$_GET['tipo_medidor'] : null;
    $tipoLeitura = isset($_GET['tipo_leitura']) && $_GET['tipo_leitura'] !== '' ? (int)$_GET['tipo_leitura'] : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    
    // Paginacao
    $pagina = isset($_GET['pagina']) && $_GET['pagina'] !== '' ? (int)$_GET['pagina'] : 1;
    $porPagina = 20;
    $offset = ($pagina - 1) * $porPagina;

    // Verifica se pelo menos um filtro foi preenchido
    $temFiltro = ($cdUnidade !== null || $cdLocalidade !== null || $cdPontoMedicao !== null ||
                  $tipoMedidor !== null || $tipoLeitura !== null || $status !== '' || $busca !== '');

    if (!$temFiltro) {
        echo json_encode([
            'success' => true,
            'total' => 0,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'totalPaginas' => 0,
            'data' => [],
            'message' => 'Preencha ao menos um filtro para realizar a busca'
        ]);
        exit;
    }

    // Monta WHERE conditions
    $where = [];
    $params = [];

    // Filtro por Unidade (atraves da LOCALIDADE)
    if ($cdUnidade !== null) {
        $where[] = "L.CD_UNIDADE = :cd_unidade";
        $params[':cd_unidade'] = $cdUnidade;
    }

    if ($cdLocalidade !== null) {
        $where[] = "PM.CD_LOCALIDADE = :cd_localidade";
        $params[':cd_localidade'] = $cdLocalidade;
    }

    // Filtro por Ponto de Medicao especifico
    if ($cdPontoMedicao !== null) {
        $where[] = "PM.CD_PONTO_MEDICAO = :cd_ponto_medicao";
        $params[':cd_ponto_medicao'] = $cdPontoMedicao;
    }

    if ($tipoMedidor !== null) {
        $where[] = "PM.ID_TIPO_MEDIDOR = :tipo_medidor";
        $params[':tipo_medidor'] = $tipoMedidor;
    }

    if ($tipoLeitura !== null) {
        $where[] = "PM.ID_TIPO_LEITURA = :tipo_leitura";
        $params[':tipo_leitura'] = $tipoLeitura;
    }

    if ($status === '1') {
        $where[] = "PM.DT_DESATIVACAO IS NULL";
    } elseif ($status === '0') {
        $where[] = "PM.DT_DESATIVACAO IS NOT NULL";
    }

    if ($busca !== '') {
        $buscaTermo = '%' . $busca . '%';
        $where[] = "(
            PM.DS_TAG_VAZAO LIKE :busca1
            OR PM.DS_TAG_PRESSAO LIKE :busca2
            OR PM.DS_TAG_TEMP_AGUA LIKE :busca3
            OR PM.DS_TAG_TEMP_AMBIENTE LIKE :busca4
            OR PM.DS_TAG_VOLUME LIKE :busca5
            OR PM.DS_TAG_RESERVATORIO LIKE :busca6
            OR PM.DS_OBSERVACAO LIKE :busca7
            OR PM.DS_LOCALIZACAO LIKE :busca8
            OR PM.DS_NOME LIKE :busca9
            OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR(20)) LIKE :busca10
            OR L.DS_NOME LIKE :busca11
        )";
        $params[':busca1'] = $buscaTermo;
        $params[':busca2'] = $buscaTermo;
        $params[':busca3'] = $buscaTermo;
        $params[':busca4'] = $buscaTermo;
        $params[':busca5'] = $buscaTermo;
        $params[':busca6'] = $buscaTermo;
        $params[':busca7'] = $buscaTermo;
        $params[':busca8'] = $buscaTermo;
        $params[':busca9'] = $buscaTermo;
        $params[':busca10'] = $buscaTermo;
        $params[':busca11'] = $buscaTermo;
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Query para contar total
    $sqlCount = "SELECT COUNT(*) as total
                 FROM SIMP.dbo.PONTO_MEDICAO PM
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                 $whereClause";

    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $resultCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $totalRegistros = $resultCount['total'] ?? 0;
    $totalPaginas = $totalRegistros > 0 ? ceil($totalRegistros / $porPagina) : 0;
    
    if ($totalRegistros == 0) {
        echo json_encode([
            'success' => true,
            'total' => 0,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'totalPaginas' => 0,
            'data' => [],
            'message' => 'Nenhum registro encontrado'
        ]);
        exit;
    }

    // Query principal com paginacao
    $sql = "SELECT 
                PM.CD_PONTO_MEDICAO,
                PM.CD_LOCALIDADE,
                PM.ID_TIPO_MEDIDOR,
                PM.DS_NOME,
                PM.DS_LOCALIZACAO,
                PM.ID_TIPO_LEITURA,
                PM.OP_PERIODICIDADE_LEITURA,
                PM.DT_ATIVACAO,
                PM.DT_DESATIVACAO,
                PM.CD_USUARIO_RESPONSAVEL,
                PM.DS_OBSERVACAO,
                PM.VL_QUANTIDADE_LIGACOES,
                PM.VL_QUANTIDADE_ECONOMIAS,
                PM.DS_TAG_VAZAO,
                PM.DS_TAG_PRESSAO,
                PM.DS_TAG_TEMP_AGUA,
                PM.DS_TAG_TEMP_AMBIENTE,
                PM.DS_TAG_RESERVATORIO,
                PM.DS_TAG_VOLUME,
                PM.VL_FATOR_CORRECAO_VAZAO,
                PM.VL_LIMITE_INFERIOR_VAZAO,
                PM.VL_LIMITE_SUPERIOR_VAZAO,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                U.CD_CODIGO AS CD_UNIDADE_CODIGO
            FROM SIMP.dbo.PONTO_MEDICAO PM
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            LEFT JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            $whereClause
            ORDER BY PM.DS_NOME
            OFFSET $offset ROWS FETCH NEXT $porPagina ROWS ONLY";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $pontosMedicao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Processa os dados
    $dadosProcessados = [];
    foreach ($pontosMedicao as $pm) {
        $codigoTag = '';
        $tagsFields = ['DS_TAG_VAZAO', 'DS_TAG_PRESSAO', 'DS_TAG_TEMP_AGUA', 
                       'DS_TAG_TEMP_AMBIENTE', 'DS_TAG_RESERVATORIO', 'DS_TAG_VOLUME'];
        
        foreach ($tagsFields as $field) {
            if (!empty($pm[$field])) {
                $codigoTag = $pm[$field];
                break;
            }
        }

        // Mapeamento de tipo de medidor para letra
        $letrasTipoMedidor = [
            1 => 'M', // Macromedidor
            2 => 'E', // Estacao Pitometrica
            4 => 'P', // Medidor Pressao
            6 => 'R', // Nivel Reservatorio
            8 => 'H'  // Hidrometro
        ];
        $letraTipo = $letrasTipoMedidor[$pm['ID_TIPO_MEDIDOR']] ?? 'X';

        // Codigo formatado: LOCALIDADE-CD_PONTO-LETRA-CD_UNIDADE
        $codigoFormatado = $pm['CD_LOCALIDADE_CODIGO'] . '-' . 
                           str_pad($pm['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' . 
                           $letraTipo . '-' . 
                           $pm['CD_UNIDADE'];

        // Tipo de Medidor por extenso
        $tiposMedidor = [
            1 => 'Macromedidor',
            2 => 'Estacao Pitometrica',
            4 => 'Medidor Pressao',
            6 => 'Nivel Reservatorio',
            8 => 'Hidrometro'
        ];
        $dsTipoMedidor = $tiposMedidor[$pm['ID_TIPO_MEDIDOR']] ?? 'Desconhecido';

        // Tipo de Leitura por extenso
        $tiposLeitura = [
            2 => 'Manual',
            4 => 'Planilha',
            6 => 'Integracao CesanLims',
            8 => 'Integracao CCO'
        ];
        $dsTipoLeitura = $tiposLeitura[$pm['ID_TIPO_LEITURA']] ?? 'Desconhecido';

        // Status
        $opSituacao = empty($pm['DT_DESATIVACAO']) ? 1 : 0;

        $dadosProcessados[] = [
            'CD_PONTO_MEDICAO' => $pm['CD_PONTO_MEDICAO'],
            'CD_CODIGO' => $codigoFormatado,
            'DS_NOME' => $pm['DS_NOME'],
            'DS_LOCALIZACAO' => $pm['DS_LOCALIZACAO'],
            'CD_LOCALIDADE' => $pm['CD_LOCALIDADE'],
            'CD_LOCALIDADE_CODIGO' => $pm['CD_LOCALIDADE_CODIGO'],
            'DS_LOCALIDADE' => $pm['DS_LOCALIDADE'],
            'CD_UNIDADE' => $pm['CD_UNIDADE'],
            'CD_UNIDADE_CODIGO' => $pm['CD_UNIDADE_CODIGO'],
            'DS_UNIDADE' => $pm['DS_UNIDADE'],
            'ID_TIPO_MEDIDOR' => $pm['ID_TIPO_MEDIDOR'],
            'DS_TIPO_MEDIDOR' => $dsTipoMedidor,
            'ID_TIPO_LEITURA' => $pm['ID_TIPO_LEITURA'],
            'DS_TIPO_LEITURA' => $dsTipoLeitura,
            'DT_ATIVACAO' => $pm['DT_ATIVACAO'],
            'DT_DESATIVACAO' => $pm['DT_DESATIVACAO'],
            'OP_SITUACAO' => $opSituacao,
            'CODIGO_TAG' => $codigoTag
        ];
    }

    echo json_encode([
        'success' => true,
        'total' => $totalRegistros,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'totalPaginas' => $totalPaginas,
        'data' => $dadosProcessados
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage(),
        'errors' => $errors
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage(),
        'errors' => $errors
    ]);
}