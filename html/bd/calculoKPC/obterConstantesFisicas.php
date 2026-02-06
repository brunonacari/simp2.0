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
 * CORREÇÃO v2.1 - As queries agora usam JOIN com CONSTANTE_FISICA para
 * resolver DS_NOME, e usam VL_REFERENCIA (não VL_REFERENCIA_A) na tabela
 * CONSTANTE_FISICA_TABELA conforme estrutura real do banco:
 *   - CONSTANTE_FISICA: CD_CHAVE, DS_NOME, DS_UNIDADE_REFERENCIA, DS_UNIDADE_REFERENCIA_B, DS_UNIDADE_VALOR
 *   - CONSTANTE_FISICA_TABELA: CD_CHAVE, CD_CONSTANTE_FISICA, VL_REFERENCIA, VL_REFERENCIA_B, VL_VALOR
 * 
 * @author Bruno - SIMP
 * @version 2.1 - Corrigido mapeamento de colunas do banco
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
 * 
 * CORREÇÃO v2.1: A tabela CONSTANTE_FISICA_TABELA não possui coluna DS_NOME
 * nem VL_REFERENCIA_A. A estrutura real é:
 *   CD_CONSTANTE_FISICA (FK → CONSTANTE_FISICA.CD_CHAVE) e VL_REFERENCIA
 * Necessário JOIN com CONSTANTE_FISICA para filtrar por DS_NOME = 'Sef'
 */
function obterSef($pdo, $diametroNominal) {
    // 1. Tenta buscar na tabela CONSTANTE_FISICA_TABELA via JOIN com CONSTANTE_FISICA
    try {
        $sql = "SELECT cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Sef' AND cft.VL_REFERENCIA = :dn";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dn' => $diametroNominal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return ['success' => true, 'valor' => (float)$row['VL_VALOR'], 'fonte' => 'banco'];
        }
    } catch (Exception $e) {
        error_log('obterSef - Erro ao buscar no banco: ' . $e->getMessage());
    }

    // 2. Tabela de ÁREA CORRIGIDA (m²) - valores com correção pela projeção da haste do Pitot
    //    Fonte: TABELA 2 – CORREÇÃO DA ÁREA PELA PROJEÇÃO DA HASTE DO PITOT
    //    NOTA: Usar sempre ÁREA CORRIGIDA, não ÁREA NOMINAL (teórica)
    $tabelaSef = [
        75   => 0.0044,
        100  => 0.0075,
        125  => 0.0125,
        150  => 0.0172,
        200  => 0.0307,
        250  => 0.0481,
        275  => 0.0588,
        300  => 0.0695,
        350  => 0.0947,
        375  => 0.1089,
        400  => 0.1240,
        450  => 0.1571,
        500  => 0.1942,
        550  => 0.2352,
        600  => 0.2801,
        650  => 0.3290,
        700  => 0.3817,
        750  => 0.4384,
        800  => 0.4991,
        900  => 0.6321,
        1000 => 0.7808,
        1050 => 0.8611,
        1100 => 0.9453,
        1200 => 1.1255,
        1250 => 1.2214,
        1500 => 1.7602,
        1750 => 2.3972,
        1800 => 2.5364,
        2000 => 3.1323
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
 * 
 * CORREÇÃO v2.1: Mesma correção de colunas - usar JOIN e VL_REFERENCIA/VL_REFERENCIA_B
 */
function obterKp($pdo, $projecaoTap, $diametroNominal) {
    // Regra do legado: DN >= 301 retorna 1
    if ($diametroNominal >= 301) {
        return ['success' => true, 'valor' => 1.0, 'fonte' => 'regra_dn_301'];
    }

    // 1. Busca exata no banco (mesmo comportamento do legado)
    try {
        $sql = "SELECT cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Kp' 
                AND cft.VL_REFERENCIA = :pt 
                AND cft.VL_REFERENCIA_B = :dn";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pt' => $projecaoTap, ':dn' => $diametroNominal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return ['success' => true, 'valor' => (float)$row['VL_VALOR'], 'fonte' => 'banco'];
        }
    } catch (Exception $e) {
        error_log('obterKp - Erro ao buscar no banco: ' . $e->getMessage());
    }

    // 2. TABELA 1 - CORREÇÃO PELA PROJEÇÃO DO TAP
    //    Fonte: Tabela oficial de correção Kp
    //    Projeção TAP (mm) × Diâmetro Nominal (mm)
    $tabelaKp = [
        1  => [100 => 0.9965, 150 => 0.9984, 200 => 0.9992, 250 => 0.9995, 300 => 0.9996],
        2  => [100 => 0.9931, 150 => 0.9968, 200 => 0.9985, 250 => 0.9990, 300 => 0.9993],
        3  => [100 => 0.9896, 150 => 0.9953, 200 => 0.9977, 250 => 0.9984, 300 => 0.9989],
        4  => [100 => 0.9862, 150 => 0.9937, 200 => 0.9969, 250 => 0.9979, 300 => 0.9986],
        5  => [100 => 0.9867, 150 => 0.9921, 200 => 0.9961, 250 => 0.9974, 300 => 0.9982],
        6  => [100 => 0.9792, 150 => 0.9905, 200 => 0.9954, 250 => 0.9968, 300 => 0.9979],
        7  => [100 => 0.9758, 150 => 0.9889, 200 => 0.9946, 250 => 0.9963, 300 => 0.9975],
        8  => [100 => 0.9723, 150 => 0.9874, 200 => 0.9938, 250 => 0.9958, 300 => 0.9968],
        9  => [100 => 0.9688, 150 => 0.9858, 200 => 0.9930, 250 => 0.9953, 300 => 0.9968],
        10 => [100 => 0.9654, 150 => 0.9842, 200 => 0.9923, 250 => 0.9947, 300 => 0.9964],
        11 => [100 => 0.9619, 150 => 0.9826, 200 => 0.9915, 250 => 0.9942, 300 => 0.9961],
        12 => [100 => 0.9585, 150 => 0.9810, 200 => 0.9908, 250 => 0.9937, 300 => 0.9957],
        13 => [100 => 0.9550, 150 => 0.9795, 200 => 0.9900, 250 => 0.9931, 300 => 0.9954],
        14 => [100 => 0.9515, 150 => 0.9779, 200 => 0.9892, 250 => 0.9926, 300 => 0.9950],
        15 => [100 => 0.9481, 150 => 0.9763, 200 => 0.9885, 250 => 0.9921, 300 => 0.9946],
        16 => [100 => 0.9446, 150 => 0.9747, 200 => 0.9877, 250 => 0.9915, 300 => 0.9943],
        17 => [100 => 0.9411, 150 => 0.9732, 200 => 0.9869, 250 => 0.9911, 300 => 0.9939],
        18 => [100 => 0.9377, 150 => 0.9716, 200 => 0.9861, 250 => 0.9905, 300 => 0.9935],
        19 => [100 => 0.9342, 150 => 0.9700, 200 => 0.9854, 250 => 0.9900, 300 => 0.9932],
        20 => [100 => 0.9308, 150 => 0.9684, 200 => 0.9846, 250 => 0.9895, 300 => 0.9929]
    ];

    // Encontra a projeção mais próxima
    $ptProxima = 25;
    $menorDif = abs($projecaoTap - 25);
    foreach ($tabelaKp as $p => $valores) {
        $dif = abs($projecaoTap - $p);
        if ($dif < $menorDif) {
            $menorDif = $dif;
            $ptProxima = $p;
        }
    }

    if (isset($tabelaKp[$ptProxima])) {
        // Encontra DN mais próximo
        $dnProximo = 50;
        $menorDifDn = abs($diametroNominal - 50);
        foreach ($tabelaKp[$ptProxima] as $d => $valor) {
            $difDn = abs($diametroNominal - $d);
            if ($difDn < $menorDifDn) {
                $menorDifDn = $difDn;
                $dnProximo = $d;
            }
        }
        return ['success' => true, 'valor' => $tabelaKp[$ptProxima][$dnProximo], 'fonte' => 'tabela_padrao'];
    }

    return ['success' => true, 'valor' => 1.0, 'fonte' => 'padrao'];
}

/**
 * Obtém a Densidade baseada na temperatura
 * Legado: GetDataByFiltro(temperatura, null, "Densidade")
 * 
 * CORREÇÃO v2.1: Mesma correção de colunas - usar JOIN e VL_REFERENCIA
 * 
 * Esta função NORMALIZA o retorno para ser usado direto na fórmula:
 *   - Se valor do banco > 10 → divide por 1000 (era kg/m³)
 *   - Se valor do banco <= 10 → usa direto (já é adimensional)
 */
function obterDensidade($pdo, $temperatura) {
    // 1. Busca exata no banco
    try {
        $sql = "SELECT cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Densidade' 
                AND cft.VL_REFERENCIA = :temp";
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
        error_log('obterDensidade - Erro ao buscar no banco: ' . $e->getMessage());
    }

    // 2. Busca por interpolação no banco (para temperaturas intermediárias)
    try {
        $sql = "SELECT TOP 1 cft.VL_REFERENCIA AS temp, cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Densidade' AND cft.VL_REFERENCIA <= :temp
                ORDER BY cft.VL_REFERENCIA DESC";
        $stmtInf = $pdo->prepare($sql);
        $stmtInf->execute([':temp' => $temperatura]);
        $rowInf = $stmtInf->fetch(PDO::FETCH_ASSOC);

        $sql = "SELECT TOP 1 cft.VL_REFERENCIA AS temp, cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Densidade' AND cft.VL_REFERENCIA >= :temp
                ORDER BY cft.VL_REFERENCIA ASC";
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
            $formato = 'adimensional';
            if ($valor > 10) {
                $valor = $valor / 1000;
                $formato = 'normalizado_de_kgm3';
            }
            return ['success' => true, 'valor' => $valor, 'fonte' => 'banco_aproximado', 'formato' => $formato];
        }
    } catch (Exception $e) {
        error_log('obterDensidade - Erro na interpolação: ' . $e->getMessage());
    }

    // 3. Tabela de fallback local (em kg/m³ - será normalizada pelo JS)
    $tabelaDensidade = [
        0 => 999.84, 5 => 999.96, 10 => 999.70, 15 => 999.10, 20 => 998.20,
        25 => 997.05, 30 => 995.65, 35 => 994.03, 40 => 992.22, 45 => 990.21, 50 => 988.03
    ];

    if (isset($tabelaDensidade[$temperatura])) {
        $valor = $tabelaDensidade[$temperatura] / 1000; // Normaliza para adimensional
        return ['success' => true, 'valor' => $valor, 'fonte' => 'tabela_padrao', 'formato' => 'normalizado_de_kgm3'];
    }

    // Interpolação local
    $temps = array_keys($tabelaDensidade);
    sort($temps);
    $tempInf = $temps[0];
    $tempSup = $temps[count($temps) - 1];

    foreach ($temps as $t) {
        if ($t <= $temperatura) $tempInf = $t;
        if ($t >= $temperatura) { $tempSup = $t; break; }
    }

    if ($tempInf !== $tempSup) {
        $fator = ($temperatura - $tempInf) / ($tempSup - $tempInf);
        $valor = $tabelaDensidade[$tempInf] + $fator * ($tabelaDensidade[$tempSup] - $tabelaDensidade[$tempInf]);
    } else {
        $valor = $tabelaDensidade[$tempInf] ?? 997.05;
    }

    $valor = $valor / 1000; // Normaliza para adimensional
    return ['success' => true, 'valor' => $valor, 'fonte' => 'tabela_padrao_interpolada', 'formato' => 'normalizado_de_kgm3'];
}