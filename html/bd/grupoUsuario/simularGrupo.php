<?php
// bd/grupoUsuario/simularGrupo.php
// Endpoint POST para ativar/desativar simulação de grupo de usuário
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Determinar o grupo real do usuário (original, não simulado)
$grupoReal = isset($_SESSION['simulacao_original'])
    ? $_SESSION['simulacao_original']['grupo']
    : ($_SESSION['grupo'] ?? '');

// Apenas Administrador A-DDS pode usar esta funcionalidade
if ($grupoReal !== 'Administrador A-DDS') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

try {
    $reset = isset($_POST['reset']) ? (int)$_POST['reset'] : 0;

    if ($reset === 1) {
        // Desativar simulação - restaurar estado original
        if (isset($_SESSION['simulacao_original'])) {
            $_SESSION['cd_grupo'] = $_SESSION['simulacao_original']['cd_grupo'];
            $_SESSION['grupo'] = $_SESSION['simulacao_original']['grupo'];
            $_SESSION['permissoes'] = $_SESSION['simulacao_original']['permissoes'];
            $_SESSION['permissoes_nome'] = $_SESSION['simulacao_original']['permissoes_nome'];

            unset($_SESSION['simulacao_original']);
            unset($_SESSION['simulando_grupo']);
            unset($_SESSION['simulando_grupo_nome']);
        }

        echo json_encode(['success' => true, 'message' => 'Simulação encerrada']);
        exit;
    }

    // Ativar simulação
    $cdGrupo = isset($_POST['cd_grupo']) ? (int)$_POST['cd_grupo'] : 0;

    if ($cdGrupo <= 0) {
        echo json_encode(['success' => false, 'message' => 'Grupo não informado']);
        exit;
    }

    // Buscar nome do grupo
    $stmtGrupo = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.GRUPO_USUARIO WHERE CD_GRUPO_USUARIO = :cdGrupo");
    $stmtGrupo->execute([':cdGrupo' => $cdGrupo]);
    $grupo = $stmtGrupo->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        echo json_encode(['success' => false, 'message' => 'Grupo não encontrado']);
        exit;
    }

    // Salvar estado original (apenas se não estiver já simulando)
    if (!isset($_SESSION['simulacao_original'])) {
        $_SESSION['simulacao_original'] = [
            'cd_grupo' => $_SESSION['cd_grupo'],
            'grupo' => $_SESSION['grupo'],
            'permissoes' => $_SESSION['permissoes'],
            'permissoes_nome' => $_SESSION['permissoes_nome']
        ];
    }

    // Carregar permissões do grupo selecionado
    $stmtFunc = $pdoSIMP->prepare("
        SELECT
            F.CD_FUNCIONALIDADE,
            F.DS_NOME AS DS_FUNCIONALIDADE,
            GF.ID_TIPO_ACESSO
        FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE GF
        INNER JOIN SIMP.dbo.FUNCIONALIDADE F ON GF.CD_FUNCIONALIDADE = F.CD_FUNCIONALIDADE
        WHERE GF.CD_GRUPO_USUARIO = :cdGrupo
    ");
    $stmtFunc->execute([':cdGrupo' => $cdGrupo]);
    $funcionalidades = $stmtFunc->fetchAll(PDO::FETCH_ASSOC);

    $permissoes = [];
    $permissoesPorNome = [];

    foreach ($funcionalidades as $func) {
        $permissoes[$func['CD_FUNCIONALIDADE']] = $func['ID_TIPO_ACESSO'];
        $permissoesPorNome[$func['DS_FUNCIONALIDADE']] = [
            'cd' => $func['CD_FUNCIONALIDADE'],
            'acesso' => $func['ID_TIPO_ACESSO']
        ];
    }

    // Sobrescrever sessão com dados do grupo simulado
    $_SESSION['cd_grupo'] = $cdGrupo;
    $_SESSION['grupo'] = $grupo['DS_NOME'];
    $_SESSION['permissoes'] = $permissoes;
    $_SESSION['permissoes_nome'] = $permissoesPorNome;
    $_SESSION['simulando_grupo'] = true;
    $_SESSION['simulando_grupo_nome'] = $grupo['DS_NOME'];

    echo json_encode([
        'success' => true,
        'message' => 'Simulando grupo: ' . $grupo['DS_NOME'],
        'grupo' => $grupo['DS_NOME'],
        'permissoes_count' => count($permissoes)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao simular grupo: ' . $e->getMessage()
    ]);
}
