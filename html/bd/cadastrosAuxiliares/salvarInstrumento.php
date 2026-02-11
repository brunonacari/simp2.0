<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Salvar (criar/editar) instrumento
 * 
 * Cria ou atualiza um instrumento nas tabelas de equipamento.
 * Diferente do salvarDadosMedidor.php, aqui CD_PONTO_MEDICAO é opcional
 * (instrumento pode ser criado independente de um ponto).
 * 
 * Parâmetros POST:
 *   - cd_chave (int, opcional): Se informado, é edição (UPDATE)
 *   - id_tipo_medidor (int): Tipo do medidor (1,2,4,6,8)
 *   - ... campos específicos de cada tipo
 * 
 * @author Bruno - SIMP
 * @version 1.1 - Correção UPDATE + campos NULL permitidos
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

// Debug log (remover em produção após confirmar funcionamento)
$debugLog = '/tmp/salvarInstrumento_debug.log';

try {
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

    include_once '../conexao.php';
    @include_once '../logHelper.php';

    // ============================================
    // Leitura dos parâmetros principais
    // ============================================
    $cdChaveRaw = isset($_POST['cd_chave']) ? $_POST['cd_chave'] : null;
    $cdChave = (!empty($cdChaveRaw) && $cdChaveRaw !== '' && $cdChaveRaw !== '0') ? (int) $cdChaveRaw : null;
    $idTipoMedidor = isset($_POST['id_tipo_medidor']) && $_POST['id_tipo_medidor'] !== '' ? (int) $_POST['id_tipo_medidor'] : null;

    // DEBUG - registra tudo que chega
    file_put_contents($debugLog, "\n" . str_repeat('=', 60) . "\n", FILE_APPEND);
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - INICIO\n", FILE_APPEND);
    file_put_contents($debugLog, "cd_chave_raw: " . var_export($cdChaveRaw, true) . "\n", FILE_APPEND);
    file_put_contents($debugLog, "cd_chave_parsed: " . var_export($cdChave, true) . "\n", FILE_APPEND);
    file_put_contents($debugLog, "id_tipo_medidor: " . var_export($idTipoMedidor, true) . "\n", FILE_APPEND);
    file_put_contents($debugLog, "POST completo: " . print_r($_POST, true) . "\n", FILE_APPEND);

    if (empty($idTipoMedidor)) {
        throw new Exception('Tipo de medidor não informado');
    }

    /**
     * Helper: Obtém valor do POST.
     * Retorna null se não enviado ou se enviado vazio, ou o valor trimado.
     */
    function getVal($key)
    {
        if (!isset($_POST[$key]))
            return null;
        $v = trim($_POST[$key]);
        return $v === '' ? null : $v;
    }

    /** Helper: Formata data para Y-m-d */
    function fmtDate($key)
    {
        $v = getVal($key);
        if (!$v)
            return null;
        return date('Y-m-d', strtotime($v));
    }

    // ============================================
    // Monta campos conforme tipo de medidor
    // ============================================
    $tabela = '';
    $campos = [];

    switch ($idTipoMedidor) {
        case 1: // Macromedidor
            $tabela = 'MACROMEDIDOR';
            $campos = [
                'CD_TIPO_MEDIDOR' => getVal('cd_tipo_medidor_equip'),
                'DS_MARCA' => getVal('ds_marca'),
                'DS_MODELO' => getVal('ds_modelo'),
                'DS_SERIE' => getVal('ds_serie'),
                'DS_TAG' => getVal('ds_tag'),
                'DT_FABRICACAO' => fmtDate('dt_fabricacao'),
                'DS_PATRIMONIO_PRIMARIO' => getVal('ds_patrimonio_primario'),
                'DS_PATRIMONIO_SECUNDARIO' => getVal('ds_patrimonio_secundario'),
                'VL_DIAMETRO' => getVal('vl_diametro'),
                'VL_DIAMETRO_REDE' => getVal('vl_diametro_rede'),
                'DS_REVESTIMENTO' => getVal('ds_revestimento'),
                'VL_PERDA_CARGA_FABRICANTE' => getVal('vl_perda_carga_fabricante'),
                'VL_CAPACIDADE_NOMINAL' => getVal('vl_capacidade_nominal'),
                'VL_K_FABRICANTE' => getVal('vl_k_fabricante'),
                'VL_VAZAO_ESPERADA' => getVal('vl_vazao_esperada'),
                'VL_PRESSAO_MAXIMA' => getVal('vl_pressao_maxima'),
                'DS_TIPO_FLANGE' => getVal('ds_tipo_flange'),
                'DS_ALTURA_SOLEIRA' => getVal('ds_altura_soleira'),
                'DS_NATUREZA_PAREDE' => getVal('ds_natureza_parede'),
                'DS_LARGURA_RELATIVA' => getVal('ds_largura_relativa'),
                'DS_LARGURA_GARGANTA' => getVal('ds_largura_garganta'),
                'VL_COTA' => getVal('vl_cota'),
                'ID_PRODUTO' => getVal('id_produto'),
                'PROT_COMUN' => getVal('prot_comun'),
                'CD_ESTACAO_PITOMETRICA' => getVal('cd_estacao_pitometrica'),
            ];
            break;

        case 2: // Estação Pitométrica
            $tabela = 'ESTACAO_PITOMETRICA';
            $campos = [
                'VL_COTA_GEOGRAFICA' => getVal('vl_cota_geografica'),
                'VL_DIAMETRO' => getVal('vl_diametro'),
                'VL_DIAMETRO_REDE' => getVal('vl_diametro_rede'),
                'DS_LINHA' => getVal('ds_linha'),
                'DS_SISTEMA' => getVal('ds_sistema'),
                'DS_REVESTIMENTO' => getVal('ds_revestimento'),
                'TP_PERIODICIDADE_LEVANTAMENTO' => getVal('tp_periodicidade_levantamento'),
            ];
            break;

        case 4: // Medidor Pressão
            $tabela = 'MEDIDOR_PRESSAO';
            $campos = [
                'DS_MATRICULA_USUARIO' => getVal('ds_matricula_usuario'),
                'DS_NUMERO_SERIE_EQUIPAMENTO' => getVal('ds_numero_serie_equipamento'),
                'VL_DIAMETRO' => getVal('vl_diametro'),
                'VL_DIAMETRO_REDE' => getVal('vl_diametro_rede'),
                'DS_MATERIAL' => getVal('ds_material'),
                'DS_COTA' => getVal('ds_cota'),
                'OP_TELEMETRIA' => getVal('op_telemetria'),
                'DS_ENDERECO' => getVal('ds_endereco'),
                'DT_INSTALACAO' => fmtDate('dt_instalacao'),
                'DS_COORDENADAS' => getVal('ds_coordenadas'),
            ];
            break;

        case 6: // Nível Reservatório
            $tabela = 'NIVEL_RESERVATORIO';
            $campos = [
                'CD_TIPO_MEDIDOR' => getVal('cd_tipo_medidor_equip'),
                'DS_MARCA' => getVal('ds_marca'),
                'DS_MODELO' => getVal('ds_modelo'),
                'DS_SERIE' => getVal('ds_serie'),
                'DS_TAG' => getVal('ds_tag'),
                'DT_FABRICACAO' => fmtDate('dt_fabricacao'),
                'DT_INSTALACAO' => fmtDate('dt_instalacao'),
                'DS_PATRIMONIO_PRIMARIO' => getVal('ds_patrimonio_primario'),
                'DS_PATRIMONIO_SECUNDARIO' => getVal('ds_patrimonio_secundario'),
                'COTA_EXTRAVASAMENTO_M' => getVal('cota_extravasamento_m'),
                'COTA_EXTRAVASAMENTO_P' => getVal('cota_extravasamento_p'),
                'VL_PRESSAO_MAXIMA_SUCCAO' => getVal('vl_pressao_maxima_succao'),
                'VL_PRESSAO_MAXIMA_RECALQUE' => getVal('vl_pressao_maxima_recalque'),
                'VL_VOLUME_TOTAL' => getVal('vl_volume_total'),
                'VL_VOLUME_CAMARA_A' => getVal('vl_volume_camara_a'),
                'VL_VOLUME_CAMARA_B' => getVal('vl_volume_camara_b'),
                'ID_PRODUTO' => getVal('id_produto'),
                'CD_TIPO_RESERVATORIO' => getVal('cd_tipo_reservatorio'),
                'DS_ALTURA_MAXIMA' => getVal('ds_altura_maxima'),
                'VL_NA' => getVal('vl_na'),
                'VL_COTA' => getVal('vl_cota'),
            ];
            break;

        case 8: // Hidrômetro
            $tabela = 'HIDROMETRO';
            $campos = [
                'DS_MATRICULA_USUARIO' => getVal('ds_matricula_usuario'),
                'DS_NUMERO_SERIE_EQUIPAMENTO' => getVal('ds_numero_serie_equipamento'),
                'VL_DIAMETRO' => getVal('vl_diametro'),
                'VL_DIAMETRO_REDE' => getVal('vl_diametro_rede'),
                'DS_MATERIAL' => getVal('ds_material'),
                'DS_COTA' => getVal('ds_cota'),
                'DS_ENDERECO' => getVal('ds_endereco'),
                'DT_INSTALACAO' => fmtDate('dt_instalacao'),
                'DS_COORDENADAS' => getVal('ds_coordenadas'),
                'ID_TEMPO_OPERACAO' => getVal('id_tempo_operacao'),
                'VL_LEITURA_LIMITE' => getVal('vl_leitura_limite'),
                'VL_MULTIPLICADOR' => getVal('vl_multiplicador'),
            ];
            break;

        default:
            throw new Exception('Tipo de medidor não suportado: ' . $idTipoMedidor);
    }

    // ============================================
    // Campos NOT NULL por tabela (com defaults para INSERT)
    // No UPDATE: se valor=null e campo é NOT NULL → ignora (mantém existente)
    // No INSERT: se valor=null e campo é NOT NULL → usa default
    // ============================================
    $camposNotNull = [
        'MACROMEDIDOR' => ['CD_TIPO_MEDIDOR' => 1, 'VL_DIAMETRO' => 0, 'ID_PRODUTO' => 1],
        'ESTACAO_PITOMETRICA' => ['VL_COTA_GEOGRAFICA' => 0, 'VL_DIAMETRO' => 0, 'TP_PERIODICIDADE_LEVANTAMENTO' => 1],
        'HIDROMETRO' => ['ID_TEMPO_OPERACAO' => 1, 'VL_LEITURA_LIMITE' => 0],
        'NIVEL_RESERVATORIO' => ['CD_TIPO_MEDIDOR' => 1, 'DT_INSTALACAO' => null, 'ID_PRODUTO' => 1, 'CD_TIPO_RESERVATORIO' => 1],
        'MEDIDOR_PRESSAO' => []
    ];
    $notNullDefaults = $camposNotNull[$tabela] ?? [];

    file_put_contents($debugLog, "Tabela: {$tabela}\n", FILE_APPEND);
    file_put_contents($debugLog, "Campos montados: " . print_r($campos, true) . "\n", FILE_APPEND);

    // ============================================
    // UPDATE ou INSERT
    // ============================================
    if ($cdChave) {
        // ========================================
        // EDIÇÃO - UPDATE
        // ========================================
        file_put_contents($debugLog, ">>> MODO UPDATE (cd_chave={$cdChave})\n", FILE_APPEND);

        // Verifica se o registro existe antes de atualizar
        $sqlCheck = "SELECT CD_CHAVE FROM SIMP.dbo.{$tabela} WHERE CD_CHAVE = ?";
        $stmtCheck = $pdoSIMP->prepare($sqlCheck);
        $stmtCheck->execute([$cdChave]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existe) {
            file_put_contents($debugLog, "ERRO: CD_CHAVE {$cdChave} não encontrado na tabela {$tabela}!\n", FILE_APPEND);
            throw new Exception("Instrumento CD:{$cdChave} não encontrado na tabela {$tabela}");
        }

        file_put_contents($debugLog, "Registro encontrado, prosseguindo com UPDATE\n", FILE_APPEND);

        $setClauses = [];
        $params = [];

        foreach ($campos as $campo => $valor) {
            if ($valor === null) {
                // Campo veio vazio/null
                if (array_key_exists($campo, $notNullDefaults)) {
                    // Campo NOT NULL - ignora (mantém valor existente no banco)
                    file_put_contents($debugLog, "  Campo {$campo} = NULL (NOT NULL, ignorando)\n", FILE_APPEND);
                    continue;
                }
                // Campo permite NULL - seta NULL no banco
                $setClauses[] = "{$campo} = NULL";
                file_put_contents($debugLog, "  Campo {$campo} = NULL\n", FILE_APPEND);
            } else {
                // Campo com valor - atualiza
                $setClauses[] = "{$campo} = ?";
                $params[] = $valor;
                file_put_contents($debugLog, "  Campo {$campo} = {$valor}\n", FILE_APPEND);
            }
        }

        if (empty($setClauses)) {
            file_put_contents($debugLog, "Nenhum campo para atualizar\n", FILE_APPEND);
            echo json_encode(['success' => true, 'message' => 'Nenhum campo para atualizar', 'cd_chave' => $cdChave]);
            exit;
        }

        $sql = "UPDATE SIMP.dbo.{$tabela} SET " . implode(', ', $setClauses) . " WHERE CD_CHAVE = ?";
        $params[] = $cdChave;

        file_put_contents($debugLog, "SQL: {$sql}\n", FILE_APPEND);
        file_put_contents($debugLog, "Params: " . print_r($params, true) . "\n", FILE_APPEND);

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);
        $rowsAffected = $stmt->rowCount();

        file_put_contents($debugLog, "UPDATE executado! Rows affected: {$rowsAffected}\n", FILE_APPEND);

        // Log de auditoria
        if (function_exists('registrarLogUpdate')) {
            try {
                registrarLogUpdate('Cadastros Auxiliares', 'Instrumento (' . $tabela . ')', $cdChave, 'CD:' . $cdChave, $campos);
            } catch (Exception $e) {
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Instrumento atualizado com sucesso!',
            'cd_chave' => $cdChave,
            'modo' => 'UPDATE'
        ]);

    } else {
        // ========================================
        // NOVO - INSERT
        // ========================================
        file_put_contents($debugLog, ">>> MODO INSERT (cd_chave vazio)\n", FILE_APPEND);

        // Aplica defaults para campos NOT NULL que vieram null
        foreach ($notNullDefaults as $campo => $valorDefault) {
            if ((!isset($campos[$campo]) || $campos[$campo] === null) && $valorDefault !== null) {
                $campos[$campo] = $valorDefault;
                file_put_contents($debugLog, "  Default NOT NULL: {$campo} = {$valorDefault}\n", FILE_APPEND);
            }
        }

        // Remove campos NULL para INSERT (campos opcionais não informados)
        $camposInsert = array_filter($campos, function ($v) {
            return $v !== null; });
        $colunas = implode(', ', array_keys($camposInsert));
        $placeholders = implode(', ', array_fill(0, count($camposInsert), '?'));
        $valores = array_values($camposInsert);

        $sql = "INSERT INTO SIMP.dbo.{$tabela} ({$colunas}) VALUES ({$placeholders})";

        file_put_contents($debugLog, "SQL: {$sql}\n", FILE_APPEND);
        file_put_contents($debugLog, "Valores: " . print_r($valores, true) . "\n", FILE_APPEND);

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($valores);

        // Recupera o ID gerado
        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];

        file_put_contents($debugLog, "INSERT executado! Novo ID: {$novoId}\n", FILE_APPEND);

        // Log de auditoria
        if (function_exists('registrarLogInsert')) {
            try {
                registrarLogInsert('Cadastros Auxiliares', 'Instrumento (' . $tabela . ')', $novoId, 'CD:' . $novoId, $camposInsert);
            } catch (Exception $e) {
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Instrumento cadastrado com sucesso!',
            'cd_chave' => $novoId,
            'modo' => 'INSERT'
        ]);
    }

    file_put_contents($debugLog, "SUCESSO!\n" . str_repeat('=', 60) . "\n", FILE_APPEND);

} catch (PDOException $e) {
    file_put_contents($debugLog, "PDO ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    file_put_contents($debugLog, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}