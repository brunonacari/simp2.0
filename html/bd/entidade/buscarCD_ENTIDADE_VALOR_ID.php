<?php
/**
 * SIMP - Buscar Valores Existentes de um Tipo de Entidade
 * Para autocomplete do campo ID Externo
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $cdTipo = isset($_GET['cdTipo']) && $_GET['cdTipo'] !== '' ? (int)$_GET['cdTipo'] : 0;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    if ($cdTipo <= 0) {
        throw new Exception('Tipo de entidade não informado');
    }

    // Mapeamento de fluxo
    $fluxosTexto = [
        1 => 'Entrada',
        2 => 'Saída',
        3 => 'Municipal',
        4 => 'Não se Aplica'
    ];

    $where = "WHERE CD_ENTIDADE_TIPO = :cdTipo";
    $params = [':cdTipo' => $cdTipo];

    if (!empty($busca)) {
        $where .= " AND (CD_ENTIDADE_VALOR_ID LIKE :busca OR DS_NOME LIKE :busca2)";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca2'] = '%' . $busca . '%';
    }

    $sql = "SELECT DISTINCT CD_ENTIDADE_VALOR_ID, DS_NOME, ID_FLUXO
            FROM SIMP.dbo.ENTIDADE_VALOR
            $where
            ORDER BY CD_ENTIDADE_VALOR_ID";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $valores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar resultado com nome do fluxo
    $resultado = [];
    foreach ($valores as $v) {
        if (empty($v['CD_ENTIDADE_VALOR_ID'])) continue;
        
        $fluxoNome = $fluxosTexto[$v['ID_FLUXO']] ?? 'Não definido';
        $resultado[] = [
            'id' => $v['CD_ENTIDADE_VALOR_ID'],
            'nome' => $v['DS_NOME'],
            'fluxo' => $v['ID_FLUXO'],
            'fluxoNome' => $fluxoNome,
            'label' => $v['CD_ENTIDADE_VALOR_ID'] . ' - ' . $v['DS_NOME'] . ' (' . $fluxoNome . ')'
        ];
    }

    // Remover duplicados por ID
    $idsUnicos = [];
    $resultadoFinal = [];
    foreach ($resultado as $item) {
        if (!in_array($item['id'], $idsUnicos)) {
            $idsUnicos[] = $item['id'];
            $resultadoFinal[] = $item;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $resultadoFinal
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
}