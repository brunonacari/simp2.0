<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Listar instrumentos disponíveis para vinculação
 * 
 * Retorna instrumentos do mesmo tipo de medidor que estejam:
 *   - Sem vínculo (CD_PONTO_MEDICAO IS NULL)
 *   - Já vinculados ao ponto atual (para exibir no dropdown como selecionado)
 * 
 * Parâmetros GET:
 *   - id_tipo_medidor (int): Tipo do medidor (1=Macro, 2=Pitom, 4=Pressão, 6=Reserv, 8=Hidro)
 *   - cd_ponto_medicao (int): Código do ponto atual (para incluir o já vinculado)
 *   - busca (string, opcional): Texto para filtrar resultados
 * 
 * @author Bruno - SIMP
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $idTipoMedidor = isset($_GET['id_tipo_medidor']) && $_GET['id_tipo_medidor'] !== '' ? (int) $_GET['id_tipo_medidor'] : null;
    $cdPontoMedicao = isset($_GET['cd_ponto_medicao']) && $_GET['cd_ponto_medicao'] !== '' ? (int) $_GET['cd_ponto_medicao'] : null;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    if (empty($idTipoMedidor)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de medidor não informado']);
        exit;
    }

    // ============================================
    // Determina tabela e campos para descrição
    // baseado no tipo de medidor
    // ============================================
    $tabela = '';
    $campoDescricao = ''; // Campos para montar o label do dropdown
    $camposFiltro = '';   // Campos para busca textual

    switch ($idTipoMedidor) {
        case 1: // Macromedidor
            $tabela = 'MACROMEDIDOR';
            $campoDescricao = "CONCAT(
                'CD:', CAST(M.CD_CHAVE AS VARCHAR),
                CASE WHEN M.DS_MARCA IS NOT NULL AND M.DS_MARCA != '' THEN ' | ' + M.DS_MARCA ELSE '' END,
                CASE WHEN M.DS_MODELO IS NOT NULL AND M.DS_MODELO != '' THEN ' - ' + M.DS_MODELO ELSE '' END,
                CASE WHEN M.DS_SERIE IS NOT NULL AND M.DS_SERIE != '' THEN ' (S/N: ' + M.DS_SERIE + ')' ELSE '' END,
                CASE WHEN M.DS_TAG IS NOT NULL AND M.DS_TAG != '' THEN ' [TAG: ' + M.DS_TAG + ']' ELSE '' END
            )";
            $camposFiltro = "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :busca 
                OR M.DS_MARCA LIKE :busca2 
                OR M.DS_MODELO LIKE :busca3 
                OR M.DS_SERIE LIKE :busca4 
                OR M.DS_TAG LIKE :busca5)";
            break;

        case 2: // Estação Pitométrica
            $tabela = 'ESTACAO_PITOMETRICA';
            $campoDescricao = "CONCAT(
                'CD:', CAST(M.CD_CHAVE AS VARCHAR),
                CASE WHEN M.DS_LINHA IS NOT NULL AND M.DS_LINHA != '' THEN ' | Linha: ' + M.DS_LINHA ELSE '' END,
                CASE WHEN M.DS_SISTEMA IS NOT NULL AND M.DS_SISTEMA != '' THEN ' - ' + M.DS_SISTEMA ELSE '' END,
                CASE WHEN M.VL_DIAMETRO IS NOT NULL THEN ' (DN: ' + CAST(M.VL_DIAMETRO AS VARCHAR) + 'mm)' ELSE '' END
            )";
            $camposFiltro = "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :busca 
                OR M.DS_LINHA LIKE :busca2 
                OR M.DS_SISTEMA LIKE :busca3 
                OR M.DS_REVESTIMENTO LIKE :busca4)";
            break;

        case 4: // Medidor Pressão
            $tabela = 'MEDIDOR_PRESSAO';
            $campoDescricao = "CONCAT(
                'CD:', CAST(M.CD_CHAVE AS VARCHAR),
                CASE WHEN M.DS_NUMERO_SERIE_EQUIPAMENTO IS NOT NULL AND M.DS_NUMERO_SERIE_EQUIPAMENTO != '' THEN ' | S/N: ' + M.DS_NUMERO_SERIE_EQUIPAMENTO ELSE '' END,
                CASE WHEN M.VL_DIAMETRO IS NOT NULL THEN ' (DN: ' + CAST(M.VL_DIAMETRO AS VARCHAR) + 'mm)' ELSE '' END,
                CASE WHEN M.DS_MATERIAL IS NOT NULL AND M.DS_MATERIAL != '' THEN ' [' + M.DS_MATERIAL + ']' ELSE '' END
            )";
            $camposFiltro = "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :busca 
                OR M.DS_NUMERO_SERIE_EQUIPAMENTO LIKE :busca2 
                OR M.DS_MATERIAL LIKE :busca3 
                OR M.DS_ENDERECO LIKE :busca4)";
            break;

        case 6: // Nível Reservatório
            $tabela = 'NIVEL_RESERVATORIO';
            $campoDescricao = "CONCAT(
                'CD:', CAST(M.CD_CHAVE AS VARCHAR),
                CASE WHEN M.DS_MARCA IS NOT NULL AND M.DS_MARCA != '' THEN ' | ' + M.DS_MARCA ELSE '' END,
                CASE WHEN M.DS_MODELO IS NOT NULL AND M.DS_MODELO != '' THEN ' - ' + M.DS_MODELO ELSE '' END,
                CASE WHEN M.DS_SERIE IS NOT NULL AND M.DS_SERIE != '' THEN ' (S/N: ' + M.DS_SERIE + ')' ELSE '' END,
                CASE WHEN M.DS_TAG IS NOT NULL AND M.DS_TAG != '' THEN ' [TAG: ' + M.DS_TAG + ']' ELSE '' END
            )";
            $camposFiltro = "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :busca 
                OR M.DS_MARCA LIKE :busca2 
                OR M.DS_MODELO LIKE :busca3 
                OR M.DS_SERIE LIKE :busca4 
                OR M.DS_TAG LIKE :busca5)";
            break;

        case 8: // Hidrômetro
            $tabela = 'HIDROMETRO';
            $campoDescricao = "CONCAT(
                'CD:', CAST(M.CD_CHAVE AS VARCHAR),
                CASE WHEN M.DS_NUMERO_SERIE_EQUIPAMENTO IS NOT NULL AND M.DS_NUMERO_SERIE_EQUIPAMENTO != '' THEN ' | S/N: ' + M.DS_NUMERO_SERIE_EQUIPAMENTO ELSE '' END,
                CASE WHEN M.VL_DIAMETRO IS NOT NULL THEN ' (DN: ' + CAST(M.VL_DIAMETRO AS VARCHAR) + 'mm)' ELSE '' END,
                CASE WHEN M.DS_MATRICULA_USUARIO IS NOT NULL AND M.DS_MATRICULA_USUARIO != '' THEN ' [Mat: ' + M.DS_MATRICULA_USUARIO + ']' ELSE '' END
            )";
            $camposFiltro = "(CAST(M.CD_CHAVE AS VARCHAR) LIKE :busca 
                OR M.DS_NUMERO_SERIE_EQUIPAMENTO LIKE :busca2 
                OR M.DS_MATRICULA_USUARIO LIKE :busca3 
                OR M.DS_ENDERECO LIKE :busca4)";
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de medidor não suportado']);
            exit;
    }

    // ============================================
    // Monta a query: instrumentos livres OU já vinculados ao ponto atual
    // ============================================
    $params = [];
    $whereClause = "(M.CD_PONTO_MEDICAO IS NULL OR M.CD_PONTO_MEDICAO = 0";

    if ($cdPontoMedicao) {
        $whereClause .= " OR M.CD_PONTO_MEDICAO = :cd_ponto_medicao";
        $params[':cd_ponto_medicao'] = $cdPontoMedicao;
    }

    $whereClause .= ")";

    // Adiciona filtro de busca se informado
    if (!empty($busca)) {
        $whereClause .= " AND " . $camposFiltro;
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca2'] = '%' . $busca . '%';
        $params[':busca3'] = '%' . $busca . '%';
        $params[':busca4'] = '%' . $busca . '%';
        // Alguns tipos têm 5 campos de busca
        if (in_array($idTipoMedidor, [1, 6])) {
            $params[':busca5'] = '%' . $busca . '%';
        }
    }

    // Inclui também o nome do ponto vinculado (se houver) para referência
    $sql = "SELECT TOP 100
                M.CD_CHAVE,
                M.CD_PONTO_MEDICAO,
                {$campoDescricao} AS DS_DESCRICAO,
                PM.DS_NOME AS DS_PONTO_VINCULADO
            FROM SIMP.dbo.{$tabela} M
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
            WHERE {$whereClause}
            ORDER BY M.CD_CHAVE DESC";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $instrumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // Formata o resultado para o Select2
    // ============================================
    $resultado = [];
    foreach ($instrumentos as $inst) {
        $label = $inst['DS_DESCRICAO'] ?: ('CD:' . $inst['CD_CHAVE']);

        // Se já está vinculado a outro ponto, indica no label
        if ($inst['CD_PONTO_MEDICAO'] && $inst['CD_PONTO_MEDICAO'] != 0 && $inst['CD_PONTO_MEDICAO'] != $cdPontoMedicao) {
            $label .= ' ⚠️ Vinculado: ' . $inst['DS_PONTO_VINCULADO'];
        }

        $resultado[] = [
            'cd_chave' => (int) $inst['CD_CHAVE'],
            'descricao' => $label,
            'cd_ponto_vinculado' => $inst['CD_PONTO_MEDICAO'] ? (int) $inst['CD_PONTO_MEDICAO'] : null,
            'ds_ponto_vinculado' => $inst['DS_PONTO_VINCULADO']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $resultado,
        'total' => count($resultado)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar instrumentos: ' . $e->getMessage()
    ]);
}