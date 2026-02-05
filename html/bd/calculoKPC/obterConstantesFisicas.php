<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint para obter constantes físicas para cálculo de KPC
 * 
 * Busca valores de Sef (Área Efetiva), Kp (Correção Projeção TAP) e Densidade
 * baseado no sistema legado (CalculoPitometria.cs)
 * 
 * ALINHAMENTO COM LEGADO:
 *   - Sef: GetDataBySef(VL_DIAMETRO_NOMINAL)
 *   - Kp:  GetDataByFiltro(projecao_tap, diametro_nominal, "Kp") | DN >= 301 → 1
 *   - Densidade: GetDataByFiltro(temperatura, null, "Densidade")
 * 
 * @author Bruno - SIMP
 * @version 2.0 - Alinhado com CalculoPitometria.cs
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'verificarAuth.php';
    include_once 'conexao.php';

    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
    $diametroNominal = isset($_GET['diametro_nominal']) ? (float)$_GET['diametro_nominal'] : 0;
    $projecaoTap = isset($_GET['projecao_tap']) ? (float)$_GET['projecao_tap'] : 0;
    $temperatura = isset($_GET['temperatura']) ? (float)$_GET['temperatura'] : 25;

    $resultado = ['success' => false, 'valor' => null];

    switch ($tipo) {
        case 'sef':
            $resultado = obterSef($pdoSIMP, $diametroNominal);
            break;

        case 'kp':
            $resultado = obterKp($pdoSIMP, $projecaoTap, $diametroNominal);
            break;

        case 'densidade':
            $resultado = obterDensidade($pdoSIMP, $temperatura);
            break;

        case 'todos':
            $sef = obterSef($pdoSIMP, $diametroNominal);
            $kp = obterKp($pdoSIMP, $projecaoTap, $diametroNominal);
            $densidade = obterDensidade($pdoSIMP, $temperatura);
            
            $resultado = [
                'success' => true,
                'sef' => $sef['valor'],
                'sef_fonte' => $sef['fonte'],
                'kp' => $kp['valor'],
                'kp_fonte' => $kp['fonte'],
                'densidade' => $densidade['valor'],
                'densidade_fonte' => $densidade['fonte'],
                // Flag para o JS saber o formato da densidade
                'densidade_formato' => $densidade['formato'] ?? 'adimensional'
            ];
            break;

        default:
            throw new Exception('Tipo de constante inválido. Use: sef, kp, densidade ou todos');
    }

    echo json_encode($resultado);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Obtém a Área Efetiva (Sef) baseada no diâmetro nominal
 * Legado: objConstanteFisicaTabelaTableAdapter.GetDataBySef(VL_DIAMETRO_NOMINAL)
 */
function obterSef($pdo, $diametroNominal) {
    // 1. Tenta buscar na tabela CONSTANTE_FISICA_TABELA (mesma do legado)
    try {
        $sql = "SELECT VL_VALOR FROM SIMP.dbo.CONSTANTE_FISICA_TABELA 
                WHERE DS_NOME = 'Sef' AND VL_REFERENCIA_A = :dn";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dn' => $diametroNominal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return ['success' => true, 'valor' => (float)$row['VL_VALOR'], 'fonte' => 'banco'];
        }
    } catch (Exception $e) {
        // Tabela pode não existir
    }

    // 2. Tabela padrão pitométrica (em m²)
    $tabelaSef = [
        50 => 0.001963, 75 => 0.004418, 100 => 0.007854, 150 => 0.017671,
        200 => 0.031416, 250 => 0.049087, 300 => 0.070686, 350 => 0.096211,
        400 => 0.125664, 450 => 0.159043, 500 => 0.196350, 600 => 0.282743,
        700 => 0.384845, 800 => 0.502655, 900 => 0.636173, 1000 => 0.785398,
        1100 => 0.950332, 1200 => 1.130973
    ];

    if (isset($tabelaSef[$diametroNominal])) {
        return ['success' => true, 'valor' => $tabelaSef[$diametroNominal], 'fonte' => 'tabela_padrao'];
    }

    // 3. Calcula: Sef = π × (DN/2000)²
    $sef = M_PI * pow($diametroNominal / 2000, 2);
    return ['success' => true, 'valor' => $sef, 'fonte' => 'calculado'];
}

/**
 * Obtém a Correção de Projeção TAP (Kp)
 * Legado: se DN >= 301 → 1; senão GetDataByFiltro(projecao_tap, diametro_nominal, "Kp")
 */
function obterKp($pdo, $projecaoTap, $diametroNominal) {
    // Regra do legado: DN >= 301 retorna 1
    if ($diametroNominal >= 301) {
        return ['success' => true, 'valor' => 1.0, 'fonte' => 'regra_dn_301'];
    }

    // 1. Busca exata no banco (mesmo comportamento do legado)
    try {
        $sql = "SELECT VL_VALOR FROM SIMP.dbo.CONSTANTE_FISICA_TABELA 
                WHERE DS_NOME = 'Kp' 
                AND VL_REFERENCIA_A = :pt 
                AND VL_REFERENCIA_B = :dn";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pt' => $projecaoTap, ':dn' => $diametroNominal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return ['success' => true, 'valor' => (float)$row['VL_VALOR'], 'fonte' => 'banco'];
        }
    } catch (Exception $e) {
        // Tabela pode não existir
    }

    // 2. Tabela padrão com busca por valor mais próximo
    $tabelaKp = [
        25 => [50 => 0.98, 75 => 0.99, 100 => 0.995, 150 => 0.998, 200 => 0.999, 250 => 1.0, 300 => 1.0],
        30 => [50 => 0.97, 75 => 0.98, 100 => 0.99,  150 => 0.995, 200 => 0.998, 250 => 0.999, 300 => 1.0],
        35 => [50 => 0.96, 75 => 0.97, 100 => 0.98,  150 => 0.99,  200 => 0.995, 250 => 0.998, 300 => 1.0],
        40 => [50 => 0.95, 75 => 0.96, 100 => 0.97,  150 => 0.98,  200 => 0.99,  250 => 0.995, 300 => 1.0],
        45 => [50 => 0.94, 75 => 0.95, 100 => 0.96,  150 => 0.97,  200 => 0.98,  250 => 0.99,  300 => 1.0],
        50 => [50 => 0.93, 75 => 0.94, 100 => 0.95,  150 => 0.96,  200 => 0.97,  250 => 0.98,  300 => 1.0]
    ];

    $projecaoMaisProxima = null;
    $diferencaMinima = PHP_INT_MAX;
    foreach (array_keys($tabelaKp) as $pt) {
        $diferenca = abs($pt - $projecaoTap);
        if ($diferenca < $diferencaMinima) {
            $diferencaMinima = $diferenca;
            $projecaoMaisProxima = $pt;
        }
    }

    if ($projecaoMaisProxima !== null && isset($tabelaKp[$projecaoMaisProxima])) {
        $diametroMaisProximo = null;
        $diferencaMinima = PHP_INT_MAX;
        foreach (array_keys($tabelaKp[$projecaoMaisProxima]) as $dn) {
            $diferenca = abs($dn - $diametroNominal);
            if ($diferenca < $diferencaMinima) {
                $diferencaMinima = $diferenca;
                $diametroMaisProximo = $dn;
            }
        }
        if ($diametroMaisProximo !== null) {
            return ['success' => true, 'valor' => $tabelaKp[$projecaoMaisProxima][$diametroMaisProximo], 'fonte' => 'tabela_padrao_interpolada'];
        }
    }

    return ['success' => true, 'valor' => 1.0, 'fonte' => 'padrao'];
}

/**
 * Obtém a densidade da água baseada na temperatura
 * Legado: GetDataByFiltro(temperatura, null, "Densidade")
 * 
 * IMPORTANTE: O legado usa o valor do banco DIRETAMENTE na fórmula:
 *   Velocidade = (deflexão/1000)^0.4931 × 3.8078 × Densidade
 * 
 * O valor no banco é tipicamente adimensional (~0.997) ou kg/L.
 * Esta função NORMALIZA o retorno para ser usado direto na fórmula:
 *   - Se valor do banco > 10 → divide por 1000 (era kg/m³)
 *   - Se valor do banco <= 10 → usa direto (já é adimensional)
 */
function obterDensidade($pdo, $temperatura) {
    // 1. Busca no banco (mesmo que o legado)
    try {
        $sql = "SELECT VL_VALOR FROM SIMP.dbo.CONSTANTE_FISICA_TABELA 
                WHERE DS_NOME = 'Densidade' 
                AND VL_REFERENCIA_A = :temp";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':temp' => $temperatura]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $valor = (float)$row['VL_VALOR'];
            // Normaliza: se está em kg/m³ (>10), converte para adimensional
            $formato = 'adimensional';
            if ($valor > 10) {
                $valor = $valor / 1000;
                $formato = 'normalizado_de_kgm3';
            }
            return ['success' => true, 'valor' => $valor, 'fonte' => 'banco', 'formato' => $formato];
        }
    } catch (Exception $e) {
        // Tabela pode não existir
    }

    // 2. Busca por interpolação no banco (para temperaturas intermediárias)
    try {
        $sql = "SELECT TOP 1 VL_REFERENCIA_A AS temp, VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA 
                WHERE DS_NOME = 'Densidade' AND VL_REFERENCIA_A <= :temp
                ORDER BY VL_REFERENCIA_A DESC";
        $stmtInf = $pdo->prepare($sql);
        $stmtInf->execute([':temp' => $temperatura]);
        $rowInf = $stmtInf->fetch(PDO::FETCH_ASSOC);

        $sql = "SELECT TOP 1 VL_REFERENCIA_A AS temp, VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA 
                WHERE DS_NOME = 'Densidade' AND VL_REFERENCIA_A >= :temp
                ORDER BY VL_REFERENCIA_A ASC";
        $stmtSup = $pdo->prepare($sql);
        $stmtSup->execute([':temp' => $temperatura]);
        $rowSup = $stmtSup->fetch(PDO::FETCH_ASSOC);

        if ($rowInf && $rowSup && $rowInf['temp'] != $rowSup['temp']) {
            $fator = ($temperatura - $rowInf['temp']) / ($rowSup['temp'] - $rowInf['temp']);
            $valor = $rowInf['VL_VALOR'] + $fator * ($rowSup['VL_VALOR'] - $rowInf['VL_VALOR']);
            
            $formato = 'adimensional';
            if ($valor > 10) {
                $valor = $valor / 1000;
                $formato = 'normalizado_de_kgm3';
            }
            return ['success' => true, 'valor' => $valor, 'fonte' => 'banco_interpolado', 'formato' => $formato];
        }
        
        if ($rowInf) {
            $valor = (float)$rowInf['VL_VALOR'];
            if ($valor > 10) $valor = $valor / 1000;
            return ['success' => true, 'valor' => $valor, 'fonte' => 'banco_aproximado', 'formato' => 'normalizado'];
        }
    } catch (Exception $e) {
        // Continua para tabela de fallback
    }

    // 3. Tabela de fallback - valores em kg/m³, já normalizados para adimensional
    $tabelaDensidade = [
        0  => 0.99984, 5  => 0.99996, 10 => 0.99970,
        15 => 0.99910, 20 => 0.99820, 25 => 0.99705,
        30 => 0.99565, 35 => 0.99403, 40 => 0.99222,
        45 => 0.99021, 50 => 0.98803
    ];

    if (isset($tabelaDensidade[$temperatura])) {
        return ['success' => true, 'valor' => $tabelaDensidade[$temperatura], 'fonte' => 'tabela_padrao', 'formato' => 'adimensional'];
    }

    // 4. Interpolação linear local
    $tempInferior = null;
    $tempSuperior = null;
    foreach (array_keys($tabelaDensidade) as $t) {
        if ($t <= $temperatura) $tempInferior = $t;
        if ($t >= $temperatura && $tempSuperior === null) $tempSuperior = $t;
    }

    if ($tempInferior !== null && $tempSuperior !== null && $tempInferior !== $tempSuperior) {
        $fator = ($temperatura - $tempInferior) / ($tempSuperior - $tempInferior);
        $densidade = $tabelaDensidade[$tempInferior] + $fator * ($tabelaDensidade[$tempSuperior] - $tabelaDensidade[$tempInferior]);
        return ['success' => true, 'valor' => $densidade, 'fonte' => 'interpolado_local', 'formato' => 'adimensional'];
    }

    return ['success' => true, 'valor' => 0.99705, 'fonte' => 'padrao', 'formato' => 'adimensional'];
}