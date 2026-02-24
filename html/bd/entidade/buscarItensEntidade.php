<?php
/**
 * SIMP - Buscar Itens de Entidade para Filtro
 * Endpoint AJAX usado pelo filtro de busca em entidade.php
 * 
 * Retorna lista de CD_ENTIDADE_VALOR que contêm itens matching o termo de busca,
 * permitindo ao frontend expandir e carregar apenas os valores relevantes.
 * 
 * Parâmetros GET:
 *   - busca (string): Termo de busca (nome do ponto, código, TAG, etc.)
 * 
 * Retorna JSON com array de objetos { cdValor, totalMatch }
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

    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    if (mb_strlen($busca) < 2) {
        // Termo muito curto: retorna vazio para não sobrecarregar
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $buscaLike = '%' . $busca . '%';

    $sql = "SELECT 
                EVI.CD_ENTIDADE_VALOR AS cdValor,
                COUNT(*) AS totalMatch
            FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE (
                PM.DS_NOME LIKE :b1
                OR CAST(EVI.CD_PONTO_MEDICAO AS VARCHAR) LIKE :b2
                OR ISNULL(PM.DS_TAG_VAZAO, '') LIKE :b3
                OR ISNULL(PM.DS_TAG_PRESSAO, '') LIKE :b4
                OR ISNULL(PM.DS_TAG_VOLUME, '') LIKE :b5
                OR ISNULL(PM.DS_TAG_RESERVATORIO, '') LIKE :b6
                OR ISNULL(PM.DS_TAG_TEMP_AGUA, '') LIKE :b7
                OR ISNULL(PM.DS_TAG_TEMP_AMBIENTE, '') LIKE :b8
                OR ISNULL(L.CD_LOCALIDADE, '') LIKE :b9
            )
            GROUP BY EVI.CD_ENTIDADE_VALOR";

    $stmt = $pdoSIMP->prepare($sql);
    $params = [];
    for ($i = 1; $i <= 9; $i++) {
        $params[":b{$i}"] = $buscaLike;
    }
    $stmt->execute($params);

    $resultados = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $resultados[] = [
            'cdValor'    => (int)$row['cdValor'],
            'totalMatch' => (int)$row['totalMatch']
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $resultados
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}