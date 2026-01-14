<?php
// bd/funcionalidades/getFuncionalidades.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $porPagina = isset($_GET['porPagina']) ? (int)$_GET['porPagina'] : 20;
    
    if ($pagina < 1) $pagina = 1;
    if ($porPagina < 1) $porPagina = 20;
    
    $offset = ($pagina - 1) * $porPagina;
    
    // Condições WHERE
    $where = "WHERE 1=1";
    $params = [];
    
    if ($busca !== '') {
        $where .= " AND (F.DS_NOME LIKE :busca OR CAST(F.CD_FUNCIONALIDADE AS VARCHAR(20)) LIKE :busca2)";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca2'] = '%' . $busca . '%';
    }
    
    // Query de contagem
    $sqlCount = "SELECT COUNT(*) as total FROM SIMP.dbo.FUNCIONALIDADE F $where";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query principal
    $sql = "SELECT 
                F.CD_FUNCIONALIDADE,
                F.DS_NOME
            FROM SIMP.dbo.FUNCIONALIDADE F
            $where
            ORDER BY F.DS_NOME
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
        'data' => $dados,
        'total' => (int)$total,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'totalPaginas' => ceil($total / $porPagina)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar funcionalidades: ' . $e->getMessage()
    ]);
}