<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Listar instrumentos com paginação e filtros
 * 
 * Lista instrumentos de todas as tabelas de equipamento com suporte a:
 *   - Filtro por tipo de medidor (obrigatório)
 *   - Busca textual
 *   - Paginação
 * 
 * Parâmetros GET:
 *   - id_tipo_medidor (int): Tipo do medidor (1,2,4,6,8)
 *   - busca (string, opcional): Texto para filtrar
 *   - pagina (int, opcional): Página atual (default 1)
 *   - por_pagina (int, opcional): Itens por página (default 20)
 * 
 * @author Bruno - SIMP
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

try {
    include_once '../conexao.php';

    $idTipoMedidor = isset($_GET['id_tipo_medidor']) && $_GET['id_tipo_medidor'] !== '' ? (int) $_GET['id_tipo_medidor'] : null;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
    $ordenarPor = isset($_GET['ordenar_por']) ? trim($_GET['ordenar_por']) : 'CD_CHAVE';
    $ordenarDir = isset($_GET['ordenar_dir']) && strtoupper($_GET['ordenar_dir']) === 'ASC' ? 'ASC' : 'DESC';
    $porPagina = isset($_GET['por_pagina']) ? max(1, min(100, (int) $_GET['por_pagina'])) : 20;
    $offset = ($pagina - 1) * $porPagina;

    // ============================================
    // Se tipo não informado, busca em TODAS as tabelas (UNION ALL)
    // ============================================
    if (empty($idTipoMedidor)) {
        $buscaLike = !empty($busca) ? '%' . $busca . '%' : null;

        $unionParts = [
            "SELECT M.CD_CHAVE, 1 AS ID_TIPO_MEDIDOR, 'Macromedidor' AS DS_TIPO,
                    M.CD_PONTO_MEDICAO,
                    ISNULL(M.DS_MARCA,'') COLLATE Latin1_General_CI_AS AS COL1,
                    ISNULL(M.DS_MODELO,'') COLLATE Latin1_General_CI_AS AS COL2,
                    ISNULL(M.DS_SERIE,'') COLLATE Latin1_General_CI_AS AS COL3,
                    ISNULL(CAST(M.VL_DIAMETRO AS VARCHAR),'') AS COL4,
                    ISNULL(M.DS_TAG,'') COLLATE Latin1_General_CI_AS AS DS_TAG,
                    PM.DS_NOME COLLATE Latin1_General_CI_AS AS DS_PONTO_VINCULADO
             FROM SIMP.dbo.MACROMEDIDOR M
             LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO",

            "SELECT M.CD_CHAVE, 2 AS ID_TIPO_MEDIDOR, 'Estação Pitométrica' AS DS_TIPO,
                    M.CD_PONTO_MEDICAO,
                    ISNULL(M.DS_LINHA,'') COLLATE Latin1_General_CI_AS AS COL1,
                    ISNULL(M.DS_SISTEMA,'') COLLATE Latin1_General_CI_AS AS COL2,
                    ISNULL(M.DS_REVESTIMENTO,'') COLLATE Latin1_General_CI_AS AS COL3,
                    ISNULL(CAST(M.VL_DIAMETRO AS VARCHAR),'') AS COL4,
                    ISNULL(M.DS_TAG,'') COLLATE Latin1_General_CI_AS AS DS_TAG,
                    PM.DS_NOME COLLATE Latin1_General_CI_AS AS DS_PONTO_VINCULADO
             FROM SIMP.dbo.ESTACAO_PITOMETRICA M
             LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO",

            "SELECT M.CD_CHAVE, 4 AS ID_TIPO_MEDIDOR, 'Medidor Pressão' AS DS_TIPO,
                    M.CD_PONTO_MEDICAO,
                    ISNULL(M.DS_MATRICULA_USUARIO,'') COLLATE Latin1_General_CI_AS AS COL1,
                    ISNULL(M.DS_NUMERO_SERIE_EQUIPAMENTO,'') COLLATE Latin1_General_CI_AS AS COL2,
                    ISNULL(M.DS_MATERIAL,'') COLLATE Latin1_General_CI_AS AS COL3,
                    ISNULL(CAST(M.VL_DIAMETRO AS VARCHAR),'') AS COL4,
                    ISNULL(M.DS_TAG,'') COLLATE Latin1_General_CI_AS AS DS_TAG,
                    PM.DS_NOME COLLATE Latin1_General_CI_AS AS DS_PONTO_VINCULADO
             FROM SIMP.dbo.MEDIDOR_PRESSAO M
             LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO",

            "SELECT M.CD_CHAVE, 6 AS ID_TIPO_MEDIDOR, 'Nível Reservatório' AS DS_TIPO,
                    M.CD_PONTO_MEDICAO,
                    ISNULL(M.DS_MARCA,'') COLLATE Latin1_General_CI_AS AS COL1,
                    ISNULL(M.DS_MODELO,'') COLLATE Latin1_General_CI_AS AS COL2,
                    ISNULL(M.DS_SERIE,'') COLLATE Latin1_General_CI_AS AS COL3,
                    ISNULL(CAST(M.VL_VOLUME_TOTAL AS VARCHAR),'') AS COL4,
                    ISNULL(M.DS_TAG,'') COLLATE Latin1_General_CI_AS AS DS_TAG,
                    PM.DS_NOME COLLATE Latin1_General_CI_AS AS DS_PONTO_VINCULADO
             FROM SIMP.dbo.NIVEL_RESERVATORIO M
             LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO",

            "SELECT M.CD_CHAVE, 8 AS ID_TIPO_MEDIDOR, 'Hidrômetro' AS DS_TIPO,
                    M.CD_PONTO_MEDICAO,
                    ISNULL(M.DS_MATRICULA_USUARIO,'') COLLATE Latin1_General_CI_AS AS COL1,
                    ISNULL(M.DS_NUMERO_SERIE_EQUIPAMENTO,'') COLLATE Latin1_General_CI_AS AS COL2,
                    ISNULL(M.DS_MATERIAL,'') COLLATE Latin1_General_CI_AS AS COL3,
                    ISNULL(CAST(M.VL_DIAMETRO AS VARCHAR),'') AS COL4,
                    ISNULL(M.DS_TAG,'') COLLATE Latin1_General_CI_AS AS DS_TAG,
                    PM.DS_NOME COLLATE Latin1_General_CI_AS AS DS_PONTO_VINCULADO
             FROM SIMP.dbo.HIDROMETRO M
             LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO"
        ];

        // Monta WHERE para cada parte do UNION
        $whereUnion = "1=1";
        $paramsUnion = [];

        if ($buscaLike) {
            $whereUnion .= " AND (CAST(T.CD_CHAVE AS VARCHAR) LIKE :b1
                OR CAST(ISNULL(T.CD_PONTO_MEDICAO,0) AS VARCHAR) LIKE :b2
                OR T.COL1 LIKE :b3 OR T.COL2 LIKE :b4 OR T.COL3 LIKE :b5
                OR T.DS_TAG LIKE :b6
                OR ISNULL(T.DS_PONTO_VINCULADO,'') LIKE :b7
                OR T.DS_TIPO LIKE :b8)";
            for ($i = 1; $i <= 8; $i++) {
                $paramsUnion[":b{$i}"] = $buscaLike;
            }
        }

        // Filtro por vínculo
        $vinculo = isset($_GET['vinculo']) ? trim($_GET['vinculo']) : '';
        if ($vinculo === 'vinculado') {
            $whereUnion .= " AND T.CD_PONTO_MEDICAO IS NOT NULL";
        } elseif ($vinculo === 'disponivel') {
            $whereUnion .= " AND T.CD_PONTO_MEDICAO IS NULL";
        }

        $unionSql = implode(" UNION ALL ", $unionParts);

        // Count
        $sqlCount = "SELECT COUNT(*) AS TOTAL FROM ({$unionSql}) T WHERE {$whereUnion}";
        $stmtCount = $pdoSIMP->prepare($sqlCount);
        $stmtCount->execute($paramsUnion);
        $total = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['TOTAL'];
        $totalPaginas = ceil($total / $porPagina);

        // Ordenação
        $colPermUnion = ['CD_CHAVE','ID_TIPO_MEDIDOR','DS_TIPO','COL1','COL2','COL3','COL4','DS_TAG','CD_PONTO_MEDICAO','DS_PONTO_VINCULADO'];
        $ordCol = in_array($ordenarPor, $colPermUnion) ? "T.{$ordenarPor}" : "T.CD_CHAVE";
        $ordDir = (isset($ordenarDir) && $ordenarDir === 'ASC') ? 'ASC' : 'DESC';

        $sql = "SELECT T.* FROM ({$unionSql}) T WHERE {$whereUnion} ORDER BY {$ordCol} {$ordDir} OFFSET {$offset} ROWS FETCH NEXT {$porPagina} ROWS ONLY";

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($paramsUnion);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $dados,
            'total' => $total,
            'pagina' => $pagina,
            'totalPaginas' => $totalPaginas,
            'porPagina' => $porPagina,
            'todos' => true
        ]);
        exit;
    }

    // ============================================
    // Configuração por tipo de medidor
    // ============================================
    $config = [];

    switch ($idTipoMedidor) {
        case 1: // Macromedidor
            $config = [
                'tabela' => 'MACROMEDIDOR',
                'campos' => "M.CD_CHAVE, M.CD_PONTO_MEDICAO, M.CD_TIPO_MEDIDOR, M.DS_MARCA, M.DS_MODELO, 
                             M.DS_SERIE, M.DS_TAG, M.DT_FABRICACAO, M.DS_PATRIMONIO_PRIMARIO,
                             M.DS_PATRIMONIO_SECUNDARIO, M.VL_DIAMETRO, M.VL_DIAMETRO_REDE,
                             M.DS_REVESTIMENTO, M.VL_PERDA_CARGA_FABRICANTE, M.VL_CAPACIDADE_NOMINAL,
                             M.VL_K_FABRICANTE, M.VL_VAZAO_ESPERADA, M.VL_PRESSAO_MAXIMA,
                             M.DS_TIPO_FLANGE, M.DS_ALTURA_SOLEIRA, M.DS_NATUREZA_PAREDE,
                             M.DS_LARGURA_RELATIVA, M.DS_LARGURA_GARGANTA, M.VL_COTA,
                             M.ID_PRODUTO, M.PROT_COMUN, M.CD_ESTACAO_PITOMETRICA",
                'campoBusca' => "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :b1 
                    OR CAST(ISNULL(M.CD_PONTO_MEDICAO,0) AS VARCHAR) LIKE :b2
                    OR ISNULL(M.DS_MARCA,'') LIKE :b3 
                    OR ISNULL(M.DS_MODELO,'') LIKE :b4 
                    OR ISNULL(M.DS_SERIE,'') LIKE :b5 
                    OR ISNULL(M.DS_TAG,'') LIKE :b6
                    OR ISNULL(PM.DS_NOME,'') LIKE :b7)"
            ];
            break;

        case 2: // Estação Pitométrica
            $config = [
                'tabela' => 'ESTACAO_PITOMETRICA',
                'campos' => "M.CD_CHAVE, M.CD_PONTO_MEDICAO, M.VL_COTA_GEOGRAFICA, M.VL_DIAMETRO,
                             M.VL_DIAMETRO_REDE, M.DS_LINHA, M.DS_SISTEMA, M.DS_REVESTIMENTO,
                             M.TP_PERIODICIDADE_LEVANTAMENTO, M.DS_TAG",
                'campoBusca' => "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :b1 
                    OR CAST(ISNULL(M.CD_PONTO_MEDICAO,0) AS VARCHAR) LIKE :b2
                    OR ISNULL(M.DS_LINHA,'') LIKE :b3 
                    OR ISNULL(M.DS_SISTEMA,'') LIKE :b4 
                    OR ISNULL(M.DS_REVESTIMENTO,'') LIKE :b5
                    OR ISNULL(M.DS_TAG,'') LIKE :b6
                    OR ISNULL(PM.DS_NOME,'') LIKE :b7)"
            ];
            break;

        case 4: // Medidor Pressão
            $config = [
                'tabela' => 'MEDIDOR_PRESSAO',
                'campos' => "M.CD_CHAVE, M.CD_PONTO_MEDICAO, M.DS_MATRICULA_USUARIO, 
                             M.DS_NUMERO_SERIE_EQUIPAMENTO, M.VL_DIAMETRO, M.VL_DIAMETRO_REDE,
                             M.DS_MATERIAL, M.DS_COTA, M.OP_TELEMETRIA, M.DS_ENDERECO,
                             M.DT_INSTALACAO, M.DS_COORDENADAS, M.DS_TAG",
                'campoBusca' => "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :b1 
                    OR CAST(ISNULL(M.CD_PONTO_MEDICAO,0) AS VARCHAR) LIKE :b2
                    OR ISNULL(M.DS_NUMERO_SERIE_EQUIPAMENTO,'') LIKE :b3 
                    OR ISNULL(M.DS_MATERIAL,'') LIKE :b4 
                    OR ISNULL(M.DS_ENDERECO,'') LIKE :b5
                    OR ISNULL(M.DS_TAG,'') LIKE :b6
                    OR ISNULL(PM.DS_NOME,'') LIKE :b7)"
            ];
            break;

        case 6: // Nível Reservatório
            $config = [
                'tabela' => 'NIVEL_RESERVATORIO',
                'campos' => "M.CD_CHAVE, M.CD_PONTO_MEDICAO, M.CD_TIPO_MEDIDOR, M.DS_MARCA, M.DS_MODELO,
                             M.DS_SERIE, M.DS_TAG, M.DT_FABRICACAO, M.DT_INSTALACAO,
                             M.DS_PATRIMONIO_PRIMARIO, M.DS_PATRIMONIO_SECUNDARIO,
                             M.COTA_EXTRAVASAMENTO_M, M.COTA_EXTRAVASAMENTO_P,
                             M.VL_PRESSAO_MAXIMA_SUCCAO, M.VL_PRESSAO_MAXIMA_RECALQUE,
                             M.VL_VOLUME_TOTAL, M.VL_VOLUME_CAMARA_A, M.VL_VOLUME_CAMARA_B,
                             M.ID_PRODUTO, M.CD_TIPO_RESERVATORIO, M.DS_ALTURA_MAXIMA,
                             M.VL_NA, M.VL_COTA",
                'campoBusca' => "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :b1 
                    OR CAST(ISNULL(M.CD_PONTO_MEDICAO,0) AS VARCHAR) LIKE :b2
                    OR ISNULL(M.DS_MARCA,'') LIKE :b3 
                    OR ISNULL(M.DS_MODELO,'') LIKE :b4 
                    OR ISNULL(M.DS_SERIE,'') LIKE :b5 
                    OR ISNULL(M.DS_TAG,'') LIKE :b6
                    OR ISNULL(PM.DS_NOME,'') LIKE :b7)"
            ];
            break;

        case 8: // Hidrômetro
            $config = [
                'tabela' => 'HIDROMETRO',
                'campos' => "M.CD_CHAVE, M.CD_PONTO_MEDICAO, M.DS_MATRICULA_USUARIO,
                             M.DS_NUMERO_SERIE_EQUIPAMENTO, M.VL_DIAMETRO, M.VL_DIAMETRO_REDE,
                             M.DS_MATERIAL, M.DS_COTA, M.DS_ENDERECO, M.DT_INSTALACAO,
                             M.DS_COORDENADAS, M.ID_TEMPO_OPERACAO, M.VL_LEITURA_LIMITE,
                             M.VL_MULTIPLICADOR, M.DS_TAG",
                'campoBusca' => "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :b1 
                    OR CAST(ISNULL(M.CD_PONTO_MEDICAO,0) AS VARCHAR) LIKE :b2
                    OR ISNULL(M.DS_NUMERO_SERIE_EQUIPAMENTO,'') LIKE :b3 
                    OR ISNULL(M.DS_MATRICULA_USUARIO,'') LIKE :b4 
                    OR ISNULL(M.DS_MATERIAL,'') LIKE :b5
                    OR ISNULL(M.DS_TAG,'') LIKE :b6
                    OR ISNULL(PM.DS_NOME,'') LIKE :b7)"
            ];
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de medidor não suportado']);
            exit;
    }

    $tabela = $config['tabela'];
    $campos = $config['campos'];

    // ============================================
    // WHERE + Busca
    // ============================================
    $where = "1=1";
    $params = [];

    // Busca direta por CD_CHAVE (usado na edição)
    $cdChaveBusca = isset($_GET['cd_chave']) && $_GET['cd_chave'] !== '' ? (int) $_GET['cd_chave'] : null;
    if ($cdChaveBusca) {
        $where .= " AND M.CD_CHAVE = :cdchave";
        $params[':cdchave'] = $cdChaveBusca;
    }

    if (!empty($busca)) {
        $where .= " AND " . $config['campoBusca'];
        $buscaLike = '%' . $busca . '%';
        // Preenche todos os parâmetros de busca (até 7)
        for ($i = 1; $i <= 7; $i++) {
            if (strpos($config['campoBusca'], ":b{$i}") !== false) {
                $params[":b{$i}"] = $buscaLike;
            }
        }
    }



    // ============================================
    // Filtro por vínculo (vinculado / sem vínculo)
    // ============================================
    $vinculo = isset($_GET['vinculo']) ? trim($_GET['vinculo']) : '';
    if ($vinculo === 'vinculado') {
        $where .= " AND M.CD_PONTO_MEDICAO IS NOT NULL";
    } elseif ($vinculo === 'disponivel') {
        $where .= " AND M.CD_PONTO_MEDICAO IS NULL";
    }

    // ============================================
    // Count total
    // ============================================
    $sqlCount = "SELECT COUNT(*) AS TOTAL 
                 FROM SIMP.dbo.{$tabela} M
                 LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
                 WHERE {$where}";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['TOTAL'];
    $totalPaginas = ceil($total / $porPagina);

    // ============================================
    // Validação da coluna de ordenação (whitelist contra SQL injection)
    // ============================================
    $colunasPermitidas = [
        'CD_CHAVE',
        'DS_MARCA',
        'DS_MODELO',
        'DS_SERIE',
        'DS_TAG',
        'VL_DIAMETRO',
        'DS_LINHA',
        'DS_SISTEMA',
        'DS_REVESTIMENTO',
        'DS_MATRICULA_USUARIO',
        'DS_NUMERO_SERIE_EQUIPAMENTO',
        'DS_MATERIAL',
        'VL_VOLUME_TOTAL',
        'CD_PONTO_MEDICAO',
        'DS_PONTO_VINCULADO'
    ];

    if ($ordenarPor === 'DS_PONTO_VINCULADO') {
        $orderByClause = "PM.DS_NOME {$ordenarDir}";
    } elseif (in_array($ordenarPor, $colunasPermitidas)) {
        $orderByClause = "M.{$ordenarPor} {$ordenarDir}";
    } else {
        $orderByClause = "M.CD_CHAVE DESC";
    }

    $sql = "SELECT {$campos}, PM.DS_NOME AS DS_PONTO_VINCULADO
        FROM SIMP.dbo.{$tabela} M
        LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
        WHERE {$where}
        ORDER BY {$orderByClause}
        OFFSET {$offset} ROWS FETCH NEXT {$porPagina} ROWS ONLY";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total' => $total,
        'pagina' => $pagina,
        'totalPaginas' => $totalPaginas,
        'porPagina' => $porPagina
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao listar instrumentos: ' . $e->getMessage()
    ]);
}