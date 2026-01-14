<?php
/**
 * SIMP - Buscar estrutura hierárquica para análise IA
 * Retorna: Tipo > Valor > Pontos
 */

header('Content-Type: application/json; charset=utf-8');

ob_start();

function retornarJSON($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Erro fatal: ' . $error['message']]);
    }
});

try {
    include_once '../conexao.php';
    
    // Parâmetros - recebe CD_ENTIDADE_VALOR_ID (ex: 'SER-ETA-001')
    $valorEntidadeId = isset($_GET['valorEntidadeId']) ? trim($_GET['valorEntidadeId']) : '';
    $dataInicio = isset($_GET['dataInicio']) ? $_GET['dataInicio'] : date('Y-m-d');
    $dataFim = isset($_GET['dataFim']) ? $_GET['dataFim'] : date('Y-m-d');
    
    if (!$valorEntidadeId || $valorEntidadeId === '' || $valorEntidadeId === 'undefined') {
        retornarJSON([
            'success' => false, 
            'error' => 'ID da unidade operacional não informado'
        ]);
    }
    
    if (!isset($pdoSIMP)) {
        retornarJSON(['success' => false, 'error' => 'Conexão não estabelecida']);
    }
    
    // Buscar estrutura usando CD_ENTIDADE_VALOR_ID
    $sql = "SELECT 
                ET.CD_CHAVE AS TIPO_CD,
                ET.DS_NOME AS TIPO_NOME,
                ET.CD_ENTIDADE_TIPO_ID AS TIPO_ID,
                EV.CD_CHAVE AS VALOR_CD,
                EV.DS_NOME AS VALOR_NOME,
                EV.CD_ENTIDADE_VALOR_ID AS VALOR_ID,
                EVI.CD_CHAVE AS ITEM_CD,
                EVI.CD_PONTO_MEDICAO AS ITEM_PONTO,
                EVI.NR_ORDEM AS ITEM_ORDEM,
                EVI.DT_INICIO AS ITEM_DT_INICIO,
                EVI.DT_FIM AS ITEM_DT_FIM,
                PM.DS_NOME AS PONTO_NOME,
                PM.CD_PONTO_MEDICAO AS PONTO_CODIGO,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE,
                L.CD_UNIDADE
            FROM SIMP.dbo.ENTIDADE_TIPO ET
            INNER JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_ENTIDADE_TIPO = ET.CD_CHAVE
            INNER JOIN SIMP.dbo.ENTIDADE_VALOR_ITEM EVI ON EVI.CD_ENTIDADE_VALOR = EV.CD_CHAVE
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            WHERE EV.CD_ENTIDADE_VALOR_ID = :valorEntidadeId
              AND (EVI.DT_FIM IS NULL OR EVI.DT_FIM >= :dataInicio)
              AND (EVI.DT_INICIO IS NULL OR EVI.DT_INICIO <= :dataFim)
            ORDER BY ET.DS_NOME, EV.DS_NOME, ISNULL(EVI.NR_ORDEM, 999999), PM.DS_NOME";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':valorEntidadeId' => $valorEntidadeId,
        ':dataInicio' => $dataInicio,
        ':dataFim' => $dataFim
    ]);
    
    // Montar estrutura hierárquica
    $estrutura = [];
    $infoTipo = null;
    $infoValor = null;
    $pontos = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Guardar info do tipo e valor (são únicos nessa query)
        if (!$infoTipo) {
            $infoTipo = [
                'cd' => $row['TIPO_CD'],
                'nome' => $row['TIPO_NOME'],
                'id' => $row['TIPO_ID']
            ];
        }
        if (!$infoValor) {
            $infoValor = [
                'cd' => $row['VALOR_CD'],
                'nome' => $row['VALOR_NOME'],
                'id' => $row['VALOR_ID']
            ];
        }
        
        $pontoCd = $row['ITEM_PONTO'];
        
        // Gerar código formatado do ponto
        $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
        $letraTipo = $letrasTipo[$row['ID_TIPO_MEDIDOR']] ?? 'X';
        $codigoPonto = ($row['CD_LOCALIDADE'] ?? '0') . '-' . 
                      str_pad($pontoCd, 6, '0', STR_PAD_LEFT) . '-' . 
                      $letraTipo . '-' . 
                      ($row['CD_UNIDADE'] ?? '0');
        
        $pontos[] = [
            'cd' => $pontoCd,
            'nome' => $row['PONTO_NOME'],
            'codigo' => $codigoPonto,
            'tipoMedidor' => $row['ID_TIPO_MEDIDOR'],
            'ordem' => $row['ITEM_ORDEM']
        ];
    }
    
    // Montar estrutura final
    if ($infoTipo && $infoValor && count($pontos) > 0) {
        $estrutura[] = [
            'cd' => $infoTipo['cd'],
            'nome' => $infoTipo['nome'],
            'id' => $infoTipo['id'],
            'valores' => [
                [
                    'cd' => $infoValor['cd'],
                    'nome' => $infoValor['nome'],
                    'id' => $infoValor['id'],
                    'pontos' => $pontos
                ]
            ]
        ];
    }
    
    retornarJSON([
        'success' => true,
        'estrutura' => $estrutura,
        'totalPontos' => count($pontos),
        'filtros' => [
            'valorEntidadeId' => $valorEntidadeId,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim
        ]
    ]);
    
} catch (Exception $e) {
    retornarJSON([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}