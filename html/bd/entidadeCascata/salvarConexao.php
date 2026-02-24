<?php
/**
 * SIMP - Salvar/Atualizar Conexão de Fluxo
 * Cria ligação dirigida: nó origem → nó destino (fluxo físico da água).
 * 
 * POST params:
 *   - cd (int|null)           : CD_CHAVE (null = nova conexão)
 *   - cdOrigem (int)          : CD_NODO_ORIGEM
 *   - cdDestino (int)         : CD_NODO_DESTINO
 *   - rotulo (string|null)    : DS_ROTULO (ex: "Adutora DN600")
 *   - cor (string|null)       : DS_COR (hex, default #1565C0)
 *   - diametroRede (float|null) : VL_DIAMETRO_REDE (mm)
 * 
 * @author Bruno - CESAN
 * @version 1.1 — Adicionado diâmetro da rede
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
include_once __DIR__ . '/topologiaHelper.php';

if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    $cd = isset($_POST['cd']) && $_POST['cd'] !== '' ? (int) $_POST['cd'] : null;
    $cdOrigem = isset($_POST['cdOrigem']) ? (int) $_POST['cdOrigem'] : 0;
    $cdDestino = isset($_POST['cdDestino']) ? (int) $_POST['cdDestino'] : 0;
    $rotulo = isset($_POST['rotulo']) ? trim($_POST['rotulo']) : null;
    $cor = isset($_POST['cor']) && $_POST['cor'] !== '' ? trim($_POST['cor']) : '#1565C0';
    $diametroRede = isset($_POST['diametroRede']) && $_POST['diametroRede'] !== '' ? (float) $_POST['diametroRede'] : null;

    // --------------------------------------------------
    // Validações
    // --------------------------------------------------
    if ($cdOrigem <= 0) {
        throw new Exception('Nó de origem é obrigatório');
    }
    if ($cdDestino <= 0) {
        throw new Exception('Nó de destino é obrigatório');
    }
    if ($cdOrigem === $cdDestino) {
        throw new Exception('Origem e destino não podem ser o mesmo nó');
    }

    // Verificar se os nós existem
    $sqlCheck = "SELECT CD_CHAVE, DS_NOME FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_CHAVE IN (:o, :d)";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':o' => $cdOrigem, ':d' => $cdDestino]);
    $nosEncontrados = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
    if (count($nosEncontrados) < 2) {
        throw new Exception('Um ou ambos os nós não foram encontrados');
    }

    // Verificar duplicidade (mesma origem→destino)
    $sqlDup = "SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO 
               WHERE CD_NODO_ORIGEM = :o AND CD_NODO_DESTINO = :d";
    if ($cd !== null) {
        $sqlDup .= " AND CD_CHAVE <> :cd";
    }
    $stmtDup = $pdoSIMP->prepare($sqlDup);
    $paramsDup = [':o' => $cdOrigem, ':d' => $cdDestino];
    if ($cd !== null)
        $paramsDup[':cd'] = $cd;
    $stmtDup->execute($paramsDup);
    if ($stmtDup->fetch()) {
        throw new Exception('Já existe uma conexão entre estes dois nós nesta direção');
    }

    // --------------------------------------------------
    // INSERT ou UPDATE
    // --------------------------------------------------
    if ($cd !== null) {
        $sql = "UPDATE SIMP.dbo.ENTIDADE_NODO_CONEXAO SET
                    CD_NODO_ORIGEM    = :cdOrigem,
                    CD_NODO_DESTINO   = :cdDestino,
                    DS_ROTULO         = :rotulo,
                    DS_COR            = :cor,
                    VL_DIAMETRO_REDE  = :diametroRede,
                    DT_ATUALIZACAO    = GETDATE()
                WHERE CD_CHAVE = :cd";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdOrigem' => $cdOrigem,
            ':cdDestino' => $cdDestino,
            ':rotulo' => $rotulo,
            ':cor' => $cor,
            ':diametroRede' => $diametroRede,
            ':cd' => $cd
        ]);

        // Log isolado
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastro Cascata', 'Conexão Fluxo', $cd, $rotulo ?: "Conexão $cdOrigem→$cdDestino", $_POST);
            }
        } catch (Exception $logEx) {
        }

        echo json_encode([
            'success' => true,
            'message' => 'Conexão atualizada com sucesso!',
            'cd' => $cd
        ], JSON_UNESCAPED_UNICODE);

    } else {
        $sql = "INSERT INTO SIMP.dbo.ENTIDADE_NODO_CONEXAO 
                    (CD_NODO_ORIGEM, CD_NODO_DESTINO, DS_ROTULO, DS_COR, VL_DIAMETRO_REDE, NR_ORDEM, OP_ATIVO, DT_CADASTRO)
                VALUES 
                    (:cdOrigem, :cdDestino, :rotulo, :cor, :diametroRede, 0, 1, GETDATE())";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdOrigem' => $cdOrigem,
            ':cdDestino' => $cdDestino,
            ':rotulo' => $rotulo,
            ':cor' => $cor,
            ':diametroRede' => $diametroRede
        ]);
        $novoCd = $pdoSIMP->lastInsertId();

        // Log isolado
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogInsert')) {
                registrarLogInsert('Cadastro Cascata', 'Conexão Fluxo', $novoCd, $rotulo ?: "Conexão $cdOrigem→$cdDestino", $_POST);
            }
        } catch (Exception $logEx) {
        }

        echo json_encode([
            'success' => true,
            'message' => 'Conexão criada com sucesso!',
            'cd' => $novoCd
        ], JSON_UNESCAPED_UNICODE);
    }
    // Disparar snapshot de topologia (Fase A1 - Governança)
    if (function_exists('dispararSnapshotTopologia')) {
        dispararSnapshotTopologia($pdoSIMP, ($cd !== null ? 'Conexão atualizada' : 'Conexão criada') . ': ' . ($rotulo ?: "$cdOrigem→$cdDestino"));
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
