<?php
/**
 * SIMP - Lista de Usuários com paginação, busca e ordenação
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';

    // Parâmetros
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $cdGrupo = isset($_GET['cd_grupo']) && $_GET['cd_grupo'] !== '' ? (int)$_GET['cd_grupo'] : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $porPagina = isset($_GET['porPagina']) ? (int)$_GET['porPagina'] : 20;
    $offset = ($pagina - 1) * $porPagina;

    // Parâmetros de ordenação
    $ordenarPor = isset($_GET['ordenarPor']) ? trim($_GET['ordenarPor']) : 'DS_NOME';
    $ordem = isset($_GET['ordem']) && strtoupper($_GET['ordem']) === 'DESC' ? 'DESC' : 'ASC';

    // Validar coluna de ordenação (segurança contra SQL injection)
    $colunasPermitidas = [
        'CD_USUARIO' => 'U.CD_USUARIO',
        'DS_MATRICULA' => 'U.DS_MATRICULA',
        'DS_LOGIN' => 'U.DS_LOGIN',
        'DS_NOME' => 'U.DS_NOME',
        'DS_EMAIL' => 'U.DS_EMAIL',
        'DS_GRUPO' => 'G.DS_NOME',
        'OP_BLOQUEADO' => 'U.OP_BLOQUEADO'
    ];

    $colunaOrdem = isset($colunasPermitidas[$ordenarPor]) ? $colunasPermitidas[$ordenarPor] : 'U.DS_NOME';

    // Monta WHERE
    $where = [];
    $params = [];

    if ($busca !== '') {
        $where[] = "(U.DS_NOME LIKE :busca1 OR U.DS_LOGIN LIKE :busca2 OR U.DS_EMAIL LIKE :busca3 OR U.DS_MATRICULA LIKE :busca4)";
        $params[':busca1'] = '%' . $busca . '%';
        $params[':busca2'] = '%' . $busca . '%';
        $params[':busca3'] = '%' . $busca . '%';
        $params[':busca4'] = '%' . $busca . '%';
    }

    if ($cdGrupo !== null) {
        $where[] = "U.CD_GRUPO_USUARIO = :cd_grupo";
        $params[':cd_grupo'] = $cdGrupo;
    }

    if ($status === 'ativo') {
        $where[] = "(U.OP_BLOQUEADO IS NULL OR U.OP_BLOQUEADO = 0 OR U.OP_BLOQUEADO = 2)";
    } elseif ($status === 'bloqueado') {
        $where[] = "U.OP_BLOQUEADO = 1";
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Query para contar total
    $sqlCount = "SELECT COUNT(*) as total
                 FROM SIMP.dbo.USUARIO U
                 LEFT JOIN SIMP.dbo.GRUPO_USUARIO G ON U.CD_GRUPO_USUARIO = G.CD_GRUPO_USUARIO
                 $whereClause";

    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPaginas = $totalRegistros > 0 ? ceil($totalRegistros / $porPagina) : 0;

    // Query principal com ordenação
    $sql = "SELECT 
                U.CD_USUARIO,
                U.CD_GRUPO_USUARIO,
                U.DS_MATRICULA,
                U.DS_LOGIN,
                U.DS_NOME,
                U.DS_EMAIL,
                U.OP_BLOQUEADO,
                G.DS_NOME AS DS_GRUPO
            FROM SIMP.dbo.USUARIO U
            LEFT JOIN SIMP.dbo.GRUPO_USUARIO G ON U.CD_GRUPO_USUARIO = G.CD_GRUPO_USUARIO
            $whereClause
            ORDER BY $colunaOrdem $ordem
            OFFSET $offset ROWS FETCH NEXT $porPagina ROWS ONLY";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $usuarios,
        'total' => (int)$totalRegistros,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'totalPaginas' => $totalPaginas,
        'ordenarPor' => $ordenarPor,
        'ordem' => $ordem
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar usuários: ' . $e->getMessage()
    ]);
}