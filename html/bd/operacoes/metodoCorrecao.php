<?php
/**
 * SIMP 2.0 - Fase A2★: Metodos de Correcao
 *
 * Calcula ate 4 metodos de correcao para anomalias detectadas,
 * retornando valores estimados hora a hora e score de aderencia
 * para que o operador escolha o melhor metodo.
 *
 * Metodos:
 *   1. XGBoost Rede    — predicao via vizinhanca topologica (TensorFlow container)
 *   2. PCHIP           — interpolacao monotonica usando horas validas como ancoras
 *   3. Media Movel Pond — media ponderada dos ultimos 7 dias (mesma hora/dia semana)
 *   4. Prophet          — decomposicao sazonal (TensorFlow container, fallback)
 *
 * Score de aderencia (0-10):
 *   score = (0.40 * R2 + 0.30 * (1 - MAE_norm) + 0.30 * (1 - RMSE_norm)) * 10
 *   Calculado comparando estimativa com horas NAO-anomalas do mesmo dia.
 *
 * Acoes:
 *   - calcular_metodos: Retorna metodos + valores + scores para um ponto/data
 *
 * Localizacao: html/bd/operacoes/metodoCorrecao.php
 *
 * @author  Bruno - CESAN
 * @version 1.0 - Fase A2★
 * @date    2026-02
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

/**
 * Retorna JSON limpo e encerra execucao.
 */
function retornarJSON_MC($data)
{
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Capturar erros fatais
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Erro PHP fatal: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    // ========================================
    // Conexao e configuracao
    // ========================================
    @include_once __DIR__ . '/../conexao.php';

    if (!isset($pdoSIMP)) {
        retornarJSON_MC(['success' => false, 'error' => 'Conexao com banco nao estabelecida']);
    }

    // URL do container TensorFlow
    $tensorflowUrl = getenv('TENSORFLOW_URL') ?: 'http://simp20-tensorflow:5000';

    // Receber dados
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $dados = $_GET;
    }

    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

    if (empty($acao)) {
        retornarJSON_MC([
            'success' => false,
            'error' => 'Parametro "acao" obrigatorio. Valores: calcular_metodos'
        ]);
    }

    // ========================================
    // Roteamento
    // ========================================
    switch ($acao) {

        case 'calcular_metodos':
            $cdPonto = intval($dados['cd_ponto'] ?? 0);
            $dtReferencia = $dados['dt_referencia'] ?? '';
            $tipoMedidor = intval($dados['tipo_medidor'] ?? 1);
            $horasAnomalas = $dados['horas_anomalas'] ?? []; // Array de horas [0-23] que sao anomalas

            if ($cdPonto <= 0 || empty($dtReferencia)) {
                retornarJSON_MC(['success' => false, 'error' => 'cd_ponto e dt_referencia sao obrigatorios']);
            }

            $resultado = calcularMetodosCorrecao(
                $pdoSIMP,
                $tensorflowUrl,
                $cdPonto,
                $dtReferencia,
                $tipoMedidor,
                $horasAnomalas
            );
            retornarJSON_MC($resultado);
            break;

        default:
            retornarJSON_MC(['success' => false, 'error' => "Acao desconhecida: $acao"]);
    }

} catch (Exception $e) {
    retornarJSON_MC(['success' => false, 'error' => $e->getMessage()]);
}


// ============================================================
// FUNCAO PRINCIPAL: CALCULAR METODOS DE CORRECAO
// ============================================================

/**
 * Orquestra o calculo dos 4 metodos de correcao e retorna
 * valores estimados + score de aderencia para cada um.
 *
 * @param PDO    $pdo             Conexao PDO
 * @param string $tfUrl           URL do TensorFlow container
 * @param int    $cdPonto         Codigo do ponto de medicao
 * @param string $dtReferencia    Data no formato YYYY-MM-DD
 * @param int    $tipoMedidor     Tipo do medidor (1,2,4,6,8)
 * @param array  $horasAnomalas   Horas anomalas (ex: [1,2,3])
 * @return array                  Resultado com metodos, scores, valores reais
 */
function calcularMetodosCorrecao(
    PDO $pdo,
    string $tfUrl,
    int $cdPonto,
    string $dtReferencia,
    int $tipoMedidor,
    array $horasAnomalas
): array {

    $inicio = microtime(true);

    // ========================================
    // 1. Buscar valores reais do dia (hora a hora)
    // ========================================
    $valoresReais = buscarValoresHorarios($pdo, $cdPonto, $dtReferencia, $tipoMedidor);

    // Se nao foram informadas horas anomalas, inferir da tabela de pendencias
    if (empty($horasAnomalas)) {
        $horasAnomalas = buscarHorasAnomalas($pdo, $cdPonto, $dtReferencia);
    }

    // Horas validas = horas que NAO sao anomalas (para calcular aderencia)
    $horasValidas = [];
    for ($h = 0; $h < 24; $h++) {
        if (!in_array($h, $horasAnomalas) && isset($valoresReais[$h]) && $valoresReais[$h] !== null) {
            $horasValidas[$h] = $valoresReais[$h];
        }
    }

    // ========================================
    // 2. Calcular cada metodo em paralelo (sequencial no PHP)
    // ========================================
    $metodos = [];

    // --- 2a. XGBoost Rede (via TensorFlow container) ---
    $xgboost = calcularXGBoostRede($tfUrl, $cdPonto, $dtReferencia, $tipoMedidor);
    if ($xgboost !== null) {
        $score = calcularScoreAderencia($xgboost, $horasValidas);
        $metodos[] = [
            'id' => 'xgboost_rede',
            'nome' => 'XGBoost Rede',
            'icone' => 'hardware-chip-outline',
            'cor' => '#3b82f6',
            'valores' => $xgboost,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Predicao multivariada usando vizinhanca topologica do grafo hidraulico'
        ];
    }

    // --- 2b. PCHIP (interpolacao monotonica, PHP puro) ---
    // --- 2b. PCHIP (interpolacao monotonica, PHP puro) ---
    $pchip = calcularPCHIP($valoresReais, $horasAnomalas);
    if ($pchip !== null) {
        // PCHIP passa pelas ancoras (R2=1 artificial) — usar LOO-CV
        if (count($horasValidas) >= 4) {
            $scoreLOO = calcularScorePCHIP_LOO($valoresReais, array_keys($horasValidas));
            $score = [
                'score' => $scoreLOO['score'],
                'metricas' => [
                    'r2' => $scoreLOO['r2'],
                    'mae' => $scoreLOO['mae'],
                    'rmse' => $scoreLOO['rmse'],
                    'mae_norm' => $scoreLOO['mae_norm'],
                    'rmse_norm' => $scoreLOO['rmse_norm'],
                    'amostras' => $scoreLOO['amostras']
                ]
            ];
        } else {
            $score = calcularScoreAderencia($pchip, $horasValidas);
        }
        $metodos[] = [
            'id' => 'pchip',
            'nome' => 'PCHIP',
            'icone' => 'analytics-outline',
            'cor' => '#f59e0b',
            'valores' => $pchip,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Interpolacao monotonica por partes usando horas validas como ancoras'
        ];
    }

    // --- 2c. Media Movel Ponderada (PHP puro) ---
    $mediaMovel = calcularMediaMovelPonderada($pdo, $cdPonto, $dtReferencia, $tipoMedidor);
    if ($mediaMovel !== null) {
        $score = calcularScoreAderencia($mediaMovel, $horasValidas);
        $metodos[] = [
            'id' => 'media_movel',
            'nome' => 'Media Movel Ponderada',
            'icone' => 'trending-up-outline',
            'cor' => '#22c55e',
            'valores' => $mediaMovel,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Media ponderada dos ultimos 7 dias, mesma hora e dia da semana'
        ];
    }

    // --- 2d. Prophet (via TensorFlow container) ---
    $prophet = calcularProphet($tfUrl, $cdPonto, $dtReferencia, $tipoMedidor);
    if ($prophet !== null) {
        $score = calcularScoreAderencia($prophet, $horasValidas);
        $metodos[] = [
            'id' => 'prophet',
            'nome' => 'Prophet',
            'icone' => 'pulse-outline',
            'cor' => '#a855f7',
            'valores' => $prophet,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Decomposicao sazonal (Meta Prophet) para series com padrao temporal forte'
        ];
    }

    // ========================================
    // 3. Ordenar por score (melhor primeiro)
    // ========================================
    usort($metodos, function ($a, $b) {
        return $b['score_aderencia'] <=> $a['score_aderencia'];
    });

    // Metodo recomendado = o de maior score
    $metodoRecomendado = !empty($metodos) ? $metodos[0]['id'] : null;

    return [
        'success' => true,
        'metodos' => $metodos,
        'valores_reais' => $valoresReais,
        'horas_anomalas' => array_values(array_map('intval', $horasAnomalas)),
        'horas_validas' => array_keys($horasValidas),
        'metodo_recomendado' => $metodoRecomendado,
        'total_metodos' => count($metodos),
        'tempo_ms' => round((microtime(true) - $inicio) * 1000)
    ];
}


// ============================================================
// METODO 1: XGBOOST REDE (via TensorFlow)
// ============================================================

/**
 * Chama /api/predict no container TensorFlow para obter
 * predicoes XGBoost hora a hora (0-23).
 *
 * @param string $tfUrl        URL do container
 * @param int    $cdPonto      Codigo do ponto
 * @param string $data         Data YYYY-MM-DD
 * @param int    $tipoMedidor  Tipo do medidor
 * @return array|null          Mapa hora => valor ou null se falhar
 */
function calcularXGBoostRede(string $tfUrl, int $cdPonto, string $data, int $tipoMedidor): ?array
{
    $resp = chamarTensorFlow($tfUrl . '/api/predict', [
        'cd_ponto' => $cdPonto,
        'data' => $data,
        'tipo_medidor' => $tipoMedidor,
        'horas' => range(0, 23)
    ]);

    if (!($resp['success'] ?? false) || empty($resp['predicoes'])) {
        return null;
    }

    $valores = [];
    foreach ($resp['predicoes'] as $pred) {
        $h = intval($pred['hora'] ?? -1);
        if ($h >= 0 && $h <= 23) {
            $valores[$h] = round(floatval($pred['valor_predito'] ?? 0), 4);
        }
    }

    // Precisa ter pelo menos 12 horas para ser valido
    return count($valores) >= 12 ? $valores : null;
}


// ============================================================
// METODO 2: PCHIP (Interpolacao Monotonica)
// ============================================================

/**
 * Piecewise Cubic Hermite Interpolating Polynomial.
 * Usa as horas validas (nao-anomalas) como pontos de ancoragem
 * e interpola os valores nas horas anomalas.
 *
 * Preserva monotonicidade — nao gera overshoots como spline cubica.
 *
 * @param array $valoresReais   Mapa hora => valor (incluindo anomalas)
 * @param array $horasAnomalas  Lista de horas anomalas
 * @return array|null           Mapa hora => valor interpolado para todas as 24h
 */
function calcularPCHIP(array $valoresReais, array $horasAnomalas): ?array
{
    // Separar ancoras (horas validas com valor)
    $ancorasX = []; // Horas
    $ancorasY = []; // Valores

    for ($h = 0; $h < 24; $h++) {
        if (!in_array($h, $horasAnomalas) && isset($valoresReais[$h]) && $valoresReais[$h] !== null) {
            $ancorasX[] = $h;
            $ancorasY[] = floatval($valoresReais[$h]);
        }
    }

    // Precisa de pelo menos 3 ancoras para interpolacao cubica
    if (count($ancorasX) < 3) {
        return null;
    }

    $n = count($ancorasX);

    // Calcular derivadas monotônicas (metodo Fritsch-Carlson)
    $derivadas = calcularDerivadasMonotonicas($ancorasX, $ancorasY);

    // Interpolar todas as 24 horas
    $valores = [];
    for ($h = 0; $h < 24; $h++) {
        if (!in_array($h, $horasAnomalas) && isset($valoresReais[$h]) && $valoresReais[$h] !== null) {
            // Hora valida: manter valor original
            $valores[$h] = round(floatval($valoresReais[$h]), 4);
        } else {
            // Hora anomala: interpolar via PCHIP
            $valores[$h] = round(interpolarPCHIP($h, $ancorasX, $ancorasY, $derivadas), 4);
        }
    }

    return $valores;
}

/**
 * Calcula derivadas monotônicas pelo metodo Fritsch-Carlson.
 * Garante que a interpolacao nao gera overshoots.
 *
 * @param array $x  Pontos X (horas)
 * @param array $y  Pontos Y (valores)
 * @return array    Derivadas em cada ponto
 */
function calcularDerivadasMonotonicas(array $x, array $y): array
{
    $n = count($x);
    $d = array_fill(0, $n, 0.0);

    if ($n < 2)
        return $d;

    // Diferencas divididas
    $delta = [];
    for ($i = 0; $i < $n - 1; $i++) {
        $dx = $x[$i + 1] - $x[$i];
        $delta[$i] = ($dx > 0) ? ($y[$i + 1] - $y[$i]) / $dx : 0.0;
    }

    if ($n === 2) {
        $d[0] = $delta[0];
        $d[1] = $delta[0];
        return $d;
    }

    // Derivadas iniciais (media harmonica)
    $d[0] = $delta[0];
    $d[$n - 1] = $delta[$n - 2];

    for ($i = 1; $i < $n - 1; $i++) {
        if ($delta[$i - 1] * $delta[$i] > 0) {
            // Mesma direcao: media harmonica
            $d[$i] = 2.0 * $delta[$i - 1] * $delta[$i] / ($delta[$i - 1] + $delta[$i]);
        } else {
            // Direcoes opostas ou zero: derivada zero (monotonica)
            $d[$i] = 0.0;
        }
    }

    // Correcao Fritsch-Carlson: limitar derivadas para preservar monotonicidade
    for ($i = 0; $i < $n - 1; $i++) {
        if (abs($delta[$i]) < 1e-10) {
            // Segmento constante: derivadas nos endpoints devem ser zero
            $d[$i] = 0.0;
            $d[$i + 1] = 0.0;
        } else {
            $alpha = $d[$i] / $delta[$i];
            $beta = $d[$i + 1] / $delta[$i];

            // Condicao de monotonicidade: alpha^2 + beta^2 <= 9
            $soma = $alpha * $alpha + $beta * $beta;
            if ($soma > 9.0) {
                $tau = 3.0 / sqrt($soma);
                $d[$i] = $tau * $alpha * $delta[$i];
                $d[$i + 1] = $tau * $beta * $delta[$i];
            }
        }
    }

    return $d;
}

/**
 * Interpola um valor em X usando PCHIP (Hermite cubico).
 *
 * @param float $xp          Ponto X a interpolar (hora)
 * @param array $x           Ancoras X
 * @param array $y           Ancoras Y
 * @param array $derivadas   Derivadas em cada ancora
 * @return float             Valor interpolado
 */
function interpolarPCHIP(float $xp, array $x, array $y, array $derivadas): float
{
    $n = count($x);

    // Clamping: se fora do intervalo, usar valor da borda
    if ($xp <= $x[0])
        return $y[0];
    if ($xp >= $x[$n - 1])
        return $y[$n - 1];

    // Encontrar intervalo [x[i], x[i+1]] que contem xp
    $i = 0;
    for ($k = 0; $k < $n - 1; $k++) {
        if ($xp >= $x[$k] && $xp <= $x[$k + 1]) {
            $i = $k;
            break;
        }
    }

    // Interpolacao Hermite cubica
    $h = $x[$i + 1] - $x[$i];
    if ($h <= 0)
        return $y[$i];

    $t = ($xp - $x[$i]) / $h;
    $t2 = $t * $t;
    $t3 = $t2 * $t;

    // Funcoes base de Hermite
    $h00 = 2 * $t3 - 3 * $t2 + 1;
    $h10 = $t3 - 2 * $t2 + $t;
    $h01 = -2 * $t3 + 3 * $t2;
    $h11 = $t3 - $t2;

    return $h00 * $y[$i] + $h10 * $h * $derivadas[$i]
        + $h01 * $y[$i + 1] + $h11 * $h * $derivadas[$i + 1];
}


// ============================================================
// METODO 3: MEDIA MOVEL PONDERADA
// ============================================================

/**
 * Calcula media movel ponderada dos ultimos 7 dias para cada hora.
 * Peso maior para dias mais recentes e mesmo dia da semana.
 *
 * @param PDO    $pdo          Conexao PDO
 * @param int    $cdPonto      Codigo do ponto
 * @param string $data         Data YYYY-MM-DD
 * @param int    $tipoMedidor  Tipo do medidor
 * @return array|null          Mapa hora => valor ou null se dados insuficientes
 */
function calcularMediaMovelPonderada(PDO $pdo, int $cdPonto, string $data, int $tipoMedidor): ?array
{
    // Buscar dados dos ultimos 7 dias (hora a hora)
    $campo = campoValorPorTipo($tipoMedidor);

    $sql = "
        SELECT
            DATEPART(HOUR, R.DT_LEITURA) AS NR_HORA,
            CAST(R.DT_LEITURA AS DATE) AS DT_DIA,
            DATEPART(WEEKDAY, R.DT_LEITURA) AS NR_DIA_SEMANA,
            AVG(R.$campo) AS VL_MEDIA_HORA
        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO R
        WHERE R.CD_PONTO_MEDICAO = :cd_ponto
          AND CAST(R.DT_LEITURA AS DATE) BETWEEN DATEADD(DAY, -7, :data) AND DATEADD(DAY, -1, :data2)
          AND R.ID_SITUACAO = 1
          AND R.$campo IS NOT NULL
        GROUP BY
            DATEPART(HOUR, R.DT_LEITURA),
            CAST(R.DT_LEITURA AS DATE),
            DATEPART(WEEKDAY, R.DT_LEITURA)
        ORDER BY DT_DIA, NR_HORA
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cd_ponto' => $cdPonto,
            ':data' => $data,
            ':data2' => $data
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }

    if (empty($rows)) {
        return null;
    }

    // Dia da semana da data de referencia
    $diaSemanaRef = intval(date('w', strtotime($data))) + 1; // 1=Dom ... 7=Sab (SQL Server)

    // Organizar por hora
    $dadosPorHora = [];
    foreach ($rows as $row) {
        $hora = intval($row['NR_HORA']);
        $dia = $row['DT_DIA'];
        $diaSemana = intval($row['NR_DIA_SEMANA']);
        $valor = floatval($row['VL_MEDIA_HORA']);

        // Calcular peso:
        // - Dias mais recentes pesam mais (7 = mais recente, 1 = mais antigo)
        $diasAtras = intval((strtotime($data) - strtotime($dia)) / 86400);
        $pesoRecencia = max(1, 8 - $diasAtras);

        // - Mesmo dia da semana pesa 2x mais
        $pesoDiaSemana = ($diaSemana === $diaSemanaRef) ? 2.0 : 1.0;

        $pesoFinal = $pesoRecencia * $pesoDiaSemana;

        if (!isset($dadosPorHora[$hora])) {
            $dadosPorHora[$hora] = ['soma_pv' => 0.0, 'soma_p' => 0.0];
        }
        $dadosPorHora[$hora]['soma_pv'] += $valor * $pesoFinal;
        $dadosPorHora[$hora]['soma_p'] += $pesoFinal;
    }

    // Calcular media ponderada por hora
    $valores = [];
    foreach ($dadosPorHora as $hora => $d) {
        if ($d['soma_p'] > 0) {
            $valores[$hora] = round($d['soma_pv'] / $d['soma_p'], 4);
        }
    }

    // Precisa de pelo menos 12 horas com dados
    return count($valores) >= 12 ? $valores : null;
}


// ============================================================
// METODO 4: PROPHET (via TensorFlow)
// ============================================================

/**
 * Chama /api/prophet no container TensorFlow para obter
 * predicoes sazonais hora a hora.
 *
 * @param string $tfUrl        URL do container
 * @param int    $cdPonto      Codigo do ponto
 * @param string $data         Data YYYY-MM-DD
 * @param int    $tipoMedidor  Tipo do medidor
 * @return array|null          Mapa hora => valor ou null se falhar
 */
function calcularProphet(string $tfUrl, int $cdPonto, string $data, int $tipoMedidor): ?array
{
    $resp = chamarTensorFlow($tfUrl . '/api/prophet', [
        'cd_ponto' => $cdPonto,
        'data' => $data,
        'tipo_medidor' => $tipoMedidor,
        'horas' => range(0, 23)
    ]);

    // Se endpoint nao existe ou falhar, retorna null (metodo opcional)
    if (!($resp['success'] ?? false) || empty($resp['predicoes'])) {
        return null;
    }

    $valores = [];
    foreach ($resp['predicoes'] as $pred) {
        $h = intval($pred['hora'] ?? -1);
        if ($h >= 0 && $h <= 23) {
            $valores[$h] = round(floatval($pred['valor_predito'] ?? 0), 4);
        }
    }

    return count($valores) >= 12 ? $valores : null;
}


// ============================================================
// SCORE DE ADERENCIA
// ============================================================

/**
 * Calcula score de aderencia (0-10) comparando valores estimados
 * com os valores reais das horas NAO-anomalas.
 *
 * Formula: score = (0.40 * R2 + 0.30 * (1 - MAE_norm) + 0.30 * (1 - RMSE_norm)) * 10
 *
 * @param array $estimados    Mapa hora => valor estimado
 * @param array $horasValidas Mapa hora => valor real (apenas horas nao-anomalas)
 * @return array              {score, metricas: {r2, mae, rmse, mae_norm, rmse_norm}}
 */
function calcularScoreAderencia(array $estimados, array $horasValidas): array
{
    // Pares (real, estimado) para horas onde ambos existem
    $reais = [];
    $ests = [];

    foreach ($horasValidas as $hora => $valorReal) {
        if (isset($estimados[$hora]) && $estimados[$hora] !== null) {
            $reais[] = floatval($valorReal);
            $ests[] = floatval($estimados[$hora]);
        }
    }

    $n = count($reais);

    // Score default (se nao houver dados suficientes para comparar)
    if ($n < 3) {
        return [
            'score' => 5.0,
            'metricas' => ['r2' => null, 'mae' => null, 'rmse' => null, 'amostras' => $n]
        ];
    }

    // --- MAE ---
    $somaAbs = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $somaAbs += abs($reais[$i] - $ests[$i]);
    }
    $mae = $somaAbs / $n;

    // --- RMSE ---
    $somaQuad = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $diff = $reais[$i] - $ests[$i];
        $somaQuad += $diff * $diff;
    }
    $rmse = sqrt($somaQuad / $n);

    // --- R² ---
    $mediaReal = array_sum($reais) / $n;
    $ssTot = 0.0;
    $ssRes = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $ssTot += ($reais[$i] - $mediaReal) * ($reais[$i] - $mediaReal);
        $ssRes += ($reais[$i] - $ests[$i]) * ($reais[$i] - $ests[$i]);
    }
    $r2 = ($ssTot > 0) ? (1.0 - $ssRes / $ssTot) : 0.0;
    $r2 = max(0.0, $r2); // Clampar para nao ficar negativo

    // --- Normalizar MAE e RMSE ---
    // Normalizar pelo range dos valores reais
    $rangeReal = max($reais) - min($reais);
    if ($rangeReal < 1e-6) {
        // Valores praticamente constantes: normalizar pela media
        $rangeReal = abs($mediaReal) > 0 ? abs($mediaReal) : 1.0;
    }
    $maeNorm = min(1.0, $mae / $rangeReal);
    $rmseNorm = min(1.0, $rmse / $rangeReal);

    // --- Score composto (0-10) ---
    $score = (0.40 * $r2 + 0.30 * (1.0 - $maeNorm) + 0.30 * (1.0 - $rmseNorm)) * 10.0;
    $score = round(max(0.0, min(10.0, $score)), 2);

    return [
        'score' => $score,
        'metricas' => [
            'r2' => round($r2, 4),
            'mae' => round($mae, 4),
            'rmse' => round($rmse, 4),
            'mae_norm' => round($maeNorm, 4),
            'rmse_norm' => round($rmseNorm, 4),
            'amostras' => $n
        ]
    ];
}


/**
 * Leave-One-Out Cross-Validation para PCHIP.
 *
 * Para cada hora valida:
 *   1. Remove ela das ancoras
 *   2. Recalcula PCHIP com as ancoras restantes
 *   3. Compara estimativa vs valor real removido
 *
 * @param array $reais        Array[0..23] valores reais
 * @param array $horasValidas Array de horas validas
 * @return array              Score e metricas
 */
function calcularScorePCHIP_LOO(array $reais, array $horasValidas): array
{
    $pares = [];

    foreach ($horasValidas as $idx => $horaRemovida) {
        // Montar ancoras sem a hora removida
        $ancorasLOO = [];
        foreach ($horasValidas as $h) {
            if ($h !== $horaRemovida && $reais[$h] !== null) {
                $ancorasLOO[$h] = floatval($reais[$h]);
            }
        }

        // Precisa de pelo menos 3 ancoras para PCHIP fazer sentido
        if (count($ancorasLOO) < 3)
            continue;

        // Recalcular PCHIP sem a hora removida
        $estimativaLOO = calcularPCHIP_Simples($ancorasLOO, $horaRemovida);

        if ($estimativaLOO !== null && $reais[$horaRemovida] !== null) {
            $pares[] = [
                'real' => floatval($reais[$horaRemovida]),
                'est' => $estimativaLOO
            ];
        }
    }

    return calcularMetricasScore($pares);
}


/**
 * Calcula PCHIP para UMA hora especifica dado um conjunto de ancoras.
 * Versao simplificada que interpola monotonicamente.
 *
 * @param array $ancoras  [hora => valor] ancoras (sem a hora alvo)
 * @param int   $horaAlvo Hora a estimar
 * @return float|null     Valor estimado ou null
 */
function calcularPCHIP_Simples(array $ancoras, int $horaAlvo): ?float
{
    ksort($ancoras);
    $horas = array_keys($ancoras);
    $valores = array_values($ancoras);
    $n = count($horas);

    if ($n < 2)
        return null;

    // Encontrar intervalo que contem horaAlvo
    $i = 0;
    for ($k = 0; $k < $n - 1; $k++) {
        if ($horaAlvo >= $horas[$k] && $horaAlvo <= $horas[$k + 1]) {
            $i = $k;
            break;
        }
        // Extrapolacao: antes do primeiro ponto
        if ($horaAlvo < $horas[0]) {
            return $valores[0]; // Flat extrapolation
        }
        // Extrapolacao: apos o ultimo ponto
        if ($horaAlvo > $horas[$n - 1]) {
            return $valores[$n - 1]; // Flat extrapolation
        }
    }

    // Interpolacao linear simples (dentro do intervalo)
    $h0 = $horas[$i];
    $h1 = $horas[$i + 1];
    $v0 = $valores[$i];
    $v1 = $valores[$i + 1];

    if ($h1 == $h0)
        return $v0;

    $t = ($horaAlvo - $h0) / ($h1 - $h0);
    return $v0 + ($v1 - $v0) * $t;
}


/**
 * Calcula metricas de score a partir de pares real/estimado.
 *
 * @param array $pares  Array de ['real' => float, 'est' => float]
 * @return array        Score e metricas
 */
function calcularMetricasScore(array $pares): array
{
    $n = count($pares);

    if ($n < 2) {
        return [
            'score' => 5.0,
            'r2' => 0,
            'mae' => 0,
            'rmse' => 0,
            'mae_norm' => 0,
            'rmse_norm' => 0,
            'amostras' => $n
        ];
    }

    // Calcular MAE e RMSE
    $somaErro = 0;
    $somaErro2 = 0;
    $somaReal = 0;
    $somaReal2 = 0;
    $somaEst = 0;
    $minReal = PHP_FLOAT_MAX;
    $maxReal = PHP_FLOAT_MIN;

    foreach ($pares as $p) {
        $erro = abs($p['real'] - $p['est']);
        $somaErro += $erro;
        $somaErro2 += $erro * $erro;
        $somaReal += $p['real'];
        $somaReal2 += $p['real'] * $p['real'];
        $minReal = min($minReal, $p['real']);
        $maxReal = max($maxReal, $p['real']);
    }

    $mae = $somaErro / $n;
    $rmse = sqrt($somaErro2 / $n);

    // R² (coeficiente de determinacao)
    $mediaReal = $somaReal / $n;
    $ssTot = 0;
    $ssRes = 0;
    foreach ($pares as $p) {
        $ssTot += ($p['real'] - $mediaReal) ** 2;
        $ssRes += ($p['real'] - $p['est']) ** 2;
    }
    $r2 = $ssTot > 0 ? max(0, 1 - ($ssRes / $ssTot)) : 0;

    // Normalizar MAE e RMSE pela amplitude
    $amplitude = $maxReal - $minReal;
    $maeNorm = $amplitude > 0 ? $mae / $amplitude : 0;
    $rmseNorm = $amplitude > 0 ? $rmse / $amplitude : 0;

    // Score composto (0-10)
    $score = (0.40 * $r2 + 0.30 * max(0, 1 - $maeNorm) + 0.30 * max(0, 1 - $rmseNorm)) * 10;
    $score = round(min(10, max(0, $score)), 2);

    return [
        'score' => $score,
        'r2' => round($r2, 4),
        'mae' => round($mae, 4),
        'rmse' => round($rmse, 4),
        'mae_norm' => round($maeNorm, 4),
        'rmse_norm' => round($rmseNorm, 4),
        'amostras' => $n
    ];
}

// ============================================================
// FUNCOES AUXILIARES
// ============================================================

/**
 * Busca valores medios horarios de um ponto em uma data especifica.
 *
 * @param PDO    $pdo          Conexao PDO
 * @param int    $cdPonto      Codigo do ponto
 * @param string $data         Data YYYY-MM-DD
 * @param int    $tipoMedidor  Tipo do medidor
 * @return array               Mapa hora => valor medio (null se sem dados)
 */
function buscarValoresHorarios(PDO $pdo, int $cdPonto, string $data, int $tipoMedidor): array
{
    // Coluna de valor conforme tipo de medidor
    $campo = campoValorPorTipo($tipoMedidor);

    $sql = "
        SELECT
            DATEPART(HOUR, DT_LEITURA) AS NR_HORA,
            AVG($campo) AS VL_MEDIA,
            COUNT(*) AS QTD_REGISTROS
        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
        WHERE CD_PONTO_MEDICAO = :cd_ponto
          AND CAST(DT_LEITURA AS DATE) = :data
          AND ID_SITUACAO = 1
          AND $campo IS NOT NULL
        GROUP BY DATEPART(HOUR, DT_LEITURA)
        ORDER BY NR_HORA
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cd_ponto' => $cdPonto, ':data' => $data]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $valores = [];
    for ($h = 0; $h < 24; $h++) {
        $valores[$h] = null;
    }

    foreach ($rows as $row) {
        $h = intval($row['NR_HORA']);
        $valores[$h] = round(floatval($row['VL_MEDIA']), 4);
    }

    return $valores;
}

/**
 * Busca horas anomalas de um ponto/data na tabela de pendencias.
 *
 * @param PDO    $pdo      Conexao PDO
 * @param int    $cdPonto  Codigo do ponto
 * @param string $data     Data YYYY-MM-DD
 * @return array           Lista de horas anomalas [0-23]
 */
function buscarHorasAnomalas(PDO $pdo, int $cdPonto, string $data): array
{
    $sql = "
        SELECT DISTINCT NR_HORA
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE CD_PONTO_MEDICAO = :cd_ponto
          AND DT_REFERENCIA = :data
          AND ID_STATUS = 0
        ORDER BY NR_HORA
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cd_ponto' => $cdPonto, ':data' => $data]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Retorna o campo de valor apropriado para o tipo de medidor.
 *
 * @param int $tipoMedidor  Tipo do medidor
 * @return string            Nome do campo SQL
 */
function campoValorPorTipo(int $tipoMedidor): string
{
    switch ($tipoMedidor) {
        case 4:
            return 'VL_PRESSAO';
        case 6:
            return 'VL_RESERVATORIO';
        default:
            return 'VL_VAZAO_EFETIVA';
    }
}

/**
 * Faz requisicao POST ao container TensorFlow.
 *
 * @param string $url   URL completa do endpoint
 * @param array  $dados Dados para enviar (JSON)
 * @param int    $timeout Timeout em segundos
 * @return array         Resposta decodificada ou [success => false]
 */
function chamarTensorFlow(string $url, array $dados, int $timeout = 30): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        return ['success' => false, 'error' => $curlError ?: "HTTP $httpCode"];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'JSON invalido'];
}