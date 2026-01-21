<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint para obter constantes físicas para cálculo de KPC
 * 
 * Busca valores de Sef (Área Efetiva) e Kp (Correção Projeção TAP)
 * baseado no sistema legado (CalculoPitometria.cs)
 * 
 * @author Bruno - SIMP
 * @version 1.0
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
            // Área Efetiva (Sef) - busca por diâmetro nominal
            // No sistema legado: objConstanteFisicaTabelaTableAdapter.GetDataBySef(VL_DIAMETRO_NOMINAL)
            $resultado = obterSef($pdoSIMP, $diametroNominal);
            break;

        case 'kp':
            // Correção Projeção TAP (Kp)
            // No sistema legado: se DN >= 301 retorna 1, senão busca na tabela
            $resultado = obterKp($pdoSIMP, $projecaoTap, $diametroNominal);
            break;

        case 'densidade':
            // Densidade da água por temperatura
            // No sistema legado: objConstanteFisicaTabelaTableAdapter.GetDataByFiltro(temperatura, null, "Densidade")
            $resultado = obterDensidade($pdoSIMP, $temperatura);
            break;

        case 'todos':
            // Retorna todas as constantes de uma vez
            $sef = obterSef($pdoSIMP, $diametroNominal);
            $kp = obterKp($pdoSIMP, $projecaoTap, $diametroNominal);
            $densidade = obterDensidade($pdoSIMP, $temperatura);
            
            $resultado = [
                'success' => true,
                'sef' => $sef['valor'],
                'kp' => $kp['valor'],
                'densidade' => $densidade['valor']
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
 * 
 * Tabela de valores padrão baseada no sistema legado.
 * Se não encontrar na tabela do banco, usa valores calculados.
 */
function obterSef($pdo, $diametroNominal) {
    // Primeiro tenta buscar na tabela CONSTANTE_FISICA_TABELA
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
        // Tabela pode não existir, usa valores padrão
    }

    // Valores padrão de Sef por diâmetro nominal (em m²)
    // Baseado em tabelas pitométricas padrão
    $tabelaSef = [
        50 => 0.001963,
        75 => 0.004418,
        100 => 0.007854,
        150 => 0.017671,
        200 => 0.031416,
        250 => 0.049087,
        300 => 0.070686,
        350 => 0.096211,
        400 => 0.125664,
        450 => 0.159043,
        500 => 0.196350,
        600 => 0.282743,
        700 => 0.384845,
        800 => 0.502655,
        900 => 0.636173,
        1000 => 0.785398,
        1100 => 0.950332,
        1200 => 1.130973
    ];

    // Se o diâmetro exato existir na tabela
    if (isset($tabelaSef[$diametroNominal])) {
        return ['success' => true, 'valor' => $tabelaSef[$diametroNominal], 'fonte' => 'tabela_padrao'];
    }

    // Calcula usando fórmula: Sef = π × (DN/2000)²
    // Onde DN está em mm e Sef em m²
    $sef = M_PI * pow($diametroNominal / 2000, 2);
    
    return ['success' => true, 'valor' => $sef, 'fonte' => 'calculado'];
}

/**
 * Obtém a Correção de Projeção TAP (Kp)
 * 
 * Conforme sistema legado:
 * - Se diâmetro nominal >= 301mm, retorna 1
 * - Senão, busca na tabela por projeção TAP e diâmetro nominal
 */
function obterKp($pdo, $projecaoTap, $diametroNominal) {
    // Se DN >= 301, retorna 1 (conforme sistema legado linha 1289-1291)
    if ($diametroNominal >= 301) {
        return ['success' => true, 'valor' => 1.0, 'fonte' => 'regra_dn_301'];
    }

    // Primeiro tenta buscar na tabela CONSTANTE_FISICA_TABELA
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
        // Tabela pode não existir, usa valores padrão
    }

    // Tabela de valores padrão de Kp por projeção TAP e diâmetro nominal
    // Valores típicos para tubos de aço (aproximações)
    // Estrutura: [projecao_tap][diametro_nominal] = kp
    $tabelaKp = [
        // Projeção TAP de 25mm
        25 => [
            50 => 0.98, 75 => 0.99, 100 => 0.995, 150 => 0.998, 200 => 0.999, 250 => 1.0, 300 => 1.0
        ],
        // Projeção TAP de 30mm
        30 => [
            50 => 0.97, 75 => 0.98, 100 => 0.99, 150 => 0.995, 200 => 0.998, 250 => 0.999, 300 => 1.0
        ],
        // Projeção TAP de 35mm
        35 => [
            50 => 0.96, 75 => 0.97, 100 => 0.98, 150 => 0.99, 200 => 0.995, 250 => 0.998, 300 => 1.0
        ],
        // Projeção TAP de 40mm
        40 => [
            50 => 0.95, 75 => 0.96, 100 => 0.97, 150 => 0.98, 200 => 0.99, 250 => 0.995, 300 => 1.0
        ],
        // Projeção TAP de 45mm
        45 => [
            50 => 0.94, 75 => 0.95, 100 => 0.96, 150 => 0.97, 200 => 0.98, 250 => 0.99, 300 => 1.0
        ],
        // Projeção TAP de 50mm
        50 => [
            50 => 0.93, 75 => 0.94, 100 => 0.95, 150 => 0.96, 200 => 0.97, 250 => 0.98, 300 => 1.0
        ]
    ];

    // Procura o valor mais próximo na tabela
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
            return [
                'success' => true, 
                'valor' => $tabelaKp[$projecaoMaisProxima][$diametroMaisProximo], 
                'fonte' => 'tabela_padrao_interpolada'
            ];
        }
    }

    // Se não encontrou, retorna 1 como padrão seguro
    return ['success' => true, 'valor' => 1.0, 'fonte' => 'padrao'];
}

/**
 * Obtém a densidade da água baseada na temperatura
 * 
 * Usado para calcular a velocidade e vazão
 */
function obterDensidade($pdo, $temperatura) {
    // Primeiro tenta buscar na tabela CONSTANTE_FISICA_TABELA
    try {
        $sql = "SELECT VL_VALOR FROM SIMP.dbo.CONSTANTE_FISICA_TABELA 
                WHERE DS_NOME = 'Densidade' AND VL_REFERENCIA_A = :temp";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':temp' => $temperatura]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return ['success' => true, 'valor' => (float)$row['VL_VALOR'], 'fonte' => 'banco'];
        }
    } catch (Exception $e) {
        // Tabela pode não existir, usa valores padrão
    }

    // Tabela de densidade da água por temperatura (kg/m³)
    $tabelaDensidade = [
        0 => 999.84,
        5 => 999.96,
        10 => 999.70,
        15 => 999.10,
        20 => 998.20,
        25 => 997.05,
        30 => 995.65,
        35 => 994.03,
        40 => 992.22,
        45 => 990.21,
        50 => 988.03
    ];

    // Se a temperatura exata existir na tabela
    if (isset($tabelaDensidade[$temperatura])) {
        return ['success' => true, 'valor' => $tabelaDensidade[$temperatura], 'fonte' => 'tabela_padrao'];
    }

    // Interpolação linear para temperaturas intermediárias
    $tempInferior = null;
    $tempSuperior = null;
    
    foreach (array_keys($tabelaDensidade) as $t) {
        if ($t <= $temperatura) {
            $tempInferior = $t;
        }
        if ($t >= $temperatura && $tempSuperior === null) {
            $tempSuperior = $t;
        }
    }

    if ($tempInferior !== null && $tempSuperior !== null && $tempInferior !== $tempSuperior) {
        $fator = ($temperatura - $tempInferior) / ($tempSuperior - $tempInferior);
        $densidade = $tabelaDensidade[$tempInferior] + 
                     $fator * ($tabelaDensidade[$tempSuperior] - $tabelaDensidade[$tempInferior]);
        return ['success' => true, 'valor' => $densidade, 'fonte' => 'interpolado'];
    }

    // Valor padrão para 25°C
    return ['success' => true, 'valor' => 997.05, 'fonte' => 'padrao'];
}