<?php
include_once '../conexao.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $cdUnidade = isset($_GET['cd_unidade']) && $_GET['cd_unidade'] !== '' ? (int)$_GET['cd_unidade'] : null;
    $cdLocalidade = isset($_GET['cd_localidade']) && $_GET['cd_localidade'] !== '' ? (int)$_GET['cd_localidade'] : null;
    $tipoEixo = isset($_GET['tipo_eixo']) && $_GET['tipo_eixo'] !== '' ? trim($_GET['tipo_eixo']) : null;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    
    $pagina = isset($_GET['pagina']) && $_GET['pagina'] !== '' ? (int)$_GET['pagina'] : 1;
    $porPagina = 20;
    $offset = ($pagina - 1) * $porPagina;

    $ordenarPor = isset($_GET['ordenar_por']) && $_GET['ordenar_por'] !== '' ? $_GET['ordenar_por'] : 'CMB.DS_NOME';
    $ordenarDirecao = isset($_GET['ordenar_direcao']) && strtoupper($_GET['ordenar_direcao']) === 'DESC' ? 'DESC' : 'ASC';

    $colunasPermitidas = ['UNIDADE', 'LOCALIDADE', 'DS_CODIGO', 'DS_NOME', 'TP_EIXO', 'VL_POTENCIA_MOTOR', 'VL_VAZAO_BOMBA'];
    if (!in_array($ordenarPor, $colunasPermitidas)) {
        $ordenarPor = 'CMB.DS_NOME';
    } else {
        if ($ordenarPor === 'UNIDADE') $ordenarPor = 'U.DS_NOME';
        elseif ($ordenarPor === 'LOCALIDADE') $ordenarPor = 'L.DS_NOME';
        else $ordenarPor = 'CMB.' . $ordenarPor;
    }

    $temFiltro = ($cdUnidade !== null || $cdLocalidade !== null || $tipoEixo !== null || $busca !== '');

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

    $where = [];
    $params = [];

    if ($cdUnidade !== null) {
        $where[] = "L.CD_UNIDADE = :cd_unidade";
        $params[':cd_unidade'] = $cdUnidade;
    }

    if ($cdLocalidade !== null) {
        $where[] = "CMB.CD_LOCALIDADE = :cd_localidade";
        $params[':cd_localidade'] = $cdLocalidade;
    }

    if ($tipoEixo !== null) {
        $where[] = "CMB.TP_EIXO = :tipo_eixo";
        $params[':tipo_eixo'] = $tipoEixo;
    }

    if ($busca !== '') {
        $buscaTermo = '%' . $busca . '%';
        $where[] = "(CMB.DS_CODIGO LIKE :busca OR CMB.DS_NOME LIKE :busca2 OR CMB.DS_LOCALIZACAO LIKE :busca3)";
        $params[':busca'] = $buscaTermo;
        $params[':busca2'] = $buscaTermo;
        $params[':busca3'] = $buscaTermo;
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Contagem total
    $sqlCount = "SELECT COUNT(*) AS TOTAL
                 FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
                 INNER JOIN SIMP.dbo.LOCALIDADE L ON CMB.CD_LOCALIDADE = L.CD_CHAVE
                 INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
                 $whereClause";
    
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['TOTAL'];
    $totalPaginas = ceil($total / $porPagina);

    // Busca paginada
    $sql = "SELECT 
                CMB.CD_CHAVE,
                CMB.CD_LOCALIDADE,
                CMB.DS_CODIGO,
                CMB.DS_NOME,
                CMB.DS_LOCALIZACAO,
                CMB.TP_EIXO,
                CMB.VL_POTENCIA_MOTOR,
                CMB.VL_VAZAO_BOMBA,
                CMB.VL_ALTURA_MANOMETRICA_BOMBA,
                L.DS_NOME AS LOCALIDADE,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                U.DS_NOME AS UNIDADE,
                U.CD_CODIGO AS CD_UNIDADE_CODIGO
            FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
            INNER JOIN SIMP.dbo.LOCALIDADE L ON CMB.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            $whereClause
            ORDER BY $ordenarPor $ordenarDirecao
            OFFSET :offset ROWS FETCH NEXT :porPagina ROWS ONLY";

    $stmt = $pdoSIMP->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => $total,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'totalPaginas' => $totalPaginas,
        'data' => $dados
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
