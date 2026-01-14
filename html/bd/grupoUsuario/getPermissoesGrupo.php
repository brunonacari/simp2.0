<?php
// bd/grupoUsuario/getPermissoesGrupo.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdGrupo = isset($_GET['cd_grupo']) ? (int)$_GET['cd_grupo'] : 0;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    
    if ($cdGrupo <= 0) {
        echo json_encode(['success' => false, 'message' => 'Grupo não informado']);
        exit;
    }
    
    $where = "WHERE GF.CD_GRUPO_USUARIO = ?";
    $params = [$cdGrupo];
    
    if ($busca !== '') {
        $where .= " AND F.DS_NOME LIKE ?";
        $params[] = '%' . $busca . '%';
    }
    
    $sql = "SELECT 
                GF.CD_CHAVE,
                GF.CD_GRUPO_USUARIO,
                GF.CD_FUNCIONALIDADE,
                GF.ID_TIPO_ACESSO,
                F.DS_NOME AS DS_FUNCIONALIDADE
            FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE GF
            INNER JOIN SIMP.dbo.FUNCIONALIDADE F ON F.CD_FUNCIONALIDADE = GF.CD_FUNCIONALIDADE
            $where
            ORDER BY F.DS_NOME";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total' => count($dados)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar permissões: ' . $e->getMessage()
    ]);
}
