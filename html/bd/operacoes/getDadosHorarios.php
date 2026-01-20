<?php
/**
 * SIMP - Buscar dados horários de um ponto em um dia específico
 * Retorna média horária e valores min/max por hora
 * Para tipo 6 (Nível Reservatório): retorna max e soma de NR_EXTRAVASOU
 * 
 * @version 2.4 - Alterado para usar AVG em vez de SUM/60 para média horária
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $cdPonto = isset($_GET['cdPonto']) ? (int)$_GET['cdPonto'] : 0;
    $data = isset($_GET['data']) ? $_GET['data'] : '';
    $tipoMedidor = isset($_GET['tipoMedidor']) ? (int)$_GET['tipoMedidor'] : 1;

    if ($cdPonto <= 0 || empty($data)) {
        throw new Exception('Parâmetros inválidos');
    }

    // Validar formato da data
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Formato de data inválido');
    }

    // Definir coluna baseado no tipo de medidor
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',  // Macromedidor
        2 => 'VL_VAZAO_EFETIVA',  // Estação Pitométrica
        4 => 'VL_PRESSAO',        // Medidor Pressão
        6 => 'VL_RESERVATORIO',   // Nível Reservatório
        8 => 'VL_VAZAO_EFETIVA'   // Hidrômetro
    ];

    $unidadesPorTipo = [
        1 => 'L/s',
        2 => 'L/s',
        4 => 'mca',
        6 => '%',
        8 => 'L/s'
    ];

    $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';
    $unidade = $unidadesPorTipo[$tipoMedidor] ?? 'L/s';

    // Query diferente para tipo 6 (Nível Reservatório)
    if ($tipoMedidor == 6) {
        // Para nível: usa MAX como valor principal, não faz média
        $sql = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) AS HORA,
                    MIN(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MIN,
                    MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MAX,
                    COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS QTD_REGISTROS,
                    COUNT(CASE WHEN ID_SITUACAO = 2 THEN 1 END) AS QTD_INATIVOS,
                    COUNT(CASE WHEN ID_SITUACAO = 1 AND ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 END) AS QTD_TRATADOS,
                    SUM(CASE WHEN ID_SITUACAO = 1 THEN ISNULL(NR_EXTRAVASOU, 0) ELSE 0 END) AS SOMA_EXTRAVASOU
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND CAST(DT_LEITURA AS DATE) = :data
                  AND {$coluna} IS NOT NULL
                GROUP BY DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
    } else {
        // Para outros tipos: média usando AVG (média real dos registros válidos)
        $sql = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) AS HORA,
                    AVG(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} ELSE NULL END) AS MEDIA,
                    MIN(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MIN,
                    MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MAX,
                    COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS QTD_REGISTROS,
                    COUNT(CASE WHEN ID_SITUACAO = 2 THEN 1 END) AS QTD_INATIVOS,
                    COUNT(CASE WHEN ID_SITUACAO = 1 AND ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 END) AS QTD_TRATADOS,
                    AVG(CASE WHEN ID_SITUACAO = 2 THEN {$coluna} ELSE NULL END) AS MEDIA_INATIVOS
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND CAST(DT_LEITURA AS DATE) = :data
                  AND {$coluna} IS NOT NULL
                GROUP BY DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
    }

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':cdPonto' => $cdPonto,
        ':data' => $data
    ]);

    $dadosHorarios = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // IMPORTANTE: Usar snake_case nos nomes dos campos para compatibilidade com frontend
        $dados = [
            'hora' => (int)$row['HORA'],
            'min' => $row['VALOR_MIN'] !== null ? floatval($row['VALOR_MIN']) : null,
            'max' => $row['VALOR_MAX'] !== null ? floatval($row['VALOR_MAX']) : null,
            'qtd_registros' => (int)$row['QTD_REGISTROS'],
            'qtd_inativos' => (int)$row['QTD_INATIVOS'],
            'tratado' => (int)$row['QTD_TRATADOS'] > 0
        ];

        // Adicionar campos específicos por tipo
        if ($tipoMedidor == 6) {
            // Para nível: não tem média, mas tem soma de extravasou
            $dados['media'] = null;
            $dados['soma_extravasou'] = (int)$row['SOMA_EXTRAVASOU'];
            $dados['media_inativos'] = null;
        } else {
            // Para outros: tem média
            $dados['media'] = $row['MEDIA'] !== null ? round(floatval($row['MEDIA']), 2) : null;
            $dados['media_inativos'] = isset($row['MEDIA_INATIVOS']) && $row['MEDIA_INATIVOS'] !== null ? round(floatval($row['MEDIA_INATIVOS']), 2) : null;
        }

        $dadosHorarios[] = $dados;
    }

    // Buscar informações do ponto
    $sqlPonto = "SELECT PM.DS_NOME, PM.ID_TIPO_MEDIDOR, L.CD_LOCALIDADE, L.CD_UNIDADE
                 FROM SIMP.dbo.PONTO_MEDICAO PM
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                 WHERE PM.CD_PONTO_MEDICAO = :cdPonto";
    $stmtPonto = $pdoSIMP->prepare($sqlPonto);
    $stmtPonto->execute([':cdPonto' => $cdPonto]);
    $ponto = $stmtPonto->fetch(PDO::FETCH_ASSOC);

    // Gerar código do ponto
    $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
    $letraTipo = $letrasTipo[$tipoMedidor] ?? 'X';
    $codigoPonto = ($ponto['CD_LOCALIDADE'] ?? '000') . '-' . 
                  str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-' . 
                  $letraTipo . '-' . 
                  ($ponto['CD_UNIDADE'] ?? '00');

    echo json_encode([
        'success' => true,
        'ponto' => [
            'cd' => $cdPonto,
            'codigo' => $codigoPonto,
            'nome' => $ponto['DS_NOME'] ?? '',
            'tipo_medidor' => $tipoMedidor
        ],
        'data' => $data,
        'unidade' => $unidade,
        'coluna' => $coluna,
        'dados' => $dadosHorarios
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}