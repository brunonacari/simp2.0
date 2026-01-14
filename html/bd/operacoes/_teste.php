<?php
/**
 * DEBUG - Verificar cálculos da IA
 * Acesse: /bd/operacoes/debugIA.php?cdPonto=XXX&data=2025-01-14
 * 
 * http://vdeskadds007.cesan.com.br:9460/bd/operacoes/_teste.php?cdPonto=1022&data=2025-12-02&hora=00
 * 
 * Retorna detalhes dos cálculos usados para sugerir valores pela IA
 */

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family: monospace; font-size: 12px;'>";

include_once '../conexao.php';

$cdPonto = isset($_GET['cdPonto']) ? (int)$_GET['cdPonto'] : 0;
$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$hora = isset($_GET['hora']) ? (int)$_GET['hora'] : 10; // Hora para detalhar

if (!$cdPonto) {
    die("Use: ?cdPonto=XXX&data=YYYY-MM-DD&hora=HH");
}

echo "=== DEBUG CÁLCULOS IA ===\n";
echo "Ponto: $cdPonto\n";
echo "Data: $data\n";
echo "Hora para detalhar: $hora:00\n";
echo "Dia da semana: " . date('l', strtotime($data)) . "\n\n";

// 1. Buscar dados REAIS do banco para a hora específica do dia atual
echo "=== 1. DADOS DO DIA ATUAL (hora $hora:00) ===\n";
$sql = "SELECT 
            COUNT(*) as QTD,
            SUM(VL_VAZAO_EFETIVA) as SOMA,
            AVG(VL_VAZAO_EFETIVA) as MEDIA_AVG,
            MIN(VL_VAZAO_EFETIVA) as MIN,
            MAX(VL_VAZAO_EFETIVA) as MAX
        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
        WHERE CD_PONTO_MEDICAO = :cdPonto
          AND CAST(DT_LEITURA AS DATE) = :data
          AND DATEPART(HOUR, DT_LEITURA) = :hora
          AND ID_SITUACAO = 1";

$stmt = $pdoSIMP->prepare($sql);
$stmt->execute([':cdPonto' => $cdPonto, ':data' => $data, ':hora' => $hora]);
$rowDiaAtual = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Registros: {$rowDiaAtual['QTD']}\n";
echo "Soma: {$rowDiaAtual['SOMA']}\n";
echo ">>> Média (SOMA/60): " . round($rowDiaAtual['SOMA'] / 60, 2) . " L/s <<<\n";
echo "Min: {$rowDiaAtual['MIN']}\n";
echo "Max: {$rowDiaAtual['MAX']}\n\n";

// 2. Buscar histórico das últimas 12 semanas para a mesma hora
echo "=== 2. HISTÓRICO DAS ÚLTIMAS 12 SEMANAS (hora $hora:00) ===\n";

$datasHistorico = [];
for ($i = 1; $i <= 12; $i++) {
    $datasHistorico[] = date('Y-m-d', strtotime("-{$i} weeks", strtotime($data)));
}

$somaMedias = 0;
$countSemanas = 0;

foreach ($datasHistorico as $idx => $dataHist) {
    $sql = "SELECT 
                COUNT(*) as QTD,
                SUM(VL_VAZAO_EFETIVA) as SOMA
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
            WHERE CD_PONTO_MEDICAO = :cdPonto
              AND CAST(DT_LEITURA AS DATE) = :data
              AND DATEPART(HOUR, DT_LEITURA) = :hora
              AND ID_SITUACAO = 1";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cdPonto' => $cdPonto, ':data' => $dataHist, ':hora' => $hora]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $diaSemana = date('D', strtotime($dataHist));
    $qtd = $row['QTD'] ?? 0;
    $soma = $row['SOMA'] ?? 0;
    $mediaSoma60 = $qtd > 0 ? round($soma / 60, 2) : 0;
    
    // Só considera se tiver pelo menos 50 registros (83% da hora)
    $valido = $qtd >= 50 ? "✓ USADO" : "✗ IGNORADO (incompleto)";
    
    echo "Semana " . ($idx + 1) . " ($dataHist - $diaSemana): ";
    echo "QTD=$qtd, SOMA/60=$mediaSoma60 L/s $valido\n";
    
    if ($qtd >= 50) {
        $somaMedias += $mediaSoma60;
        $countSemanas++;
    }
}

$mediaHistorica = $countSemanas > 0 ? round($somaMedias / $countSemanas, 2) : 0;
echo "\n>>> Média histórica: $mediaHistorica L/s (baseado em $countSemanas semanas válidas) <<<\n\n";

// 3. Calcular fator de tendência
echo "=== 3. CÁLCULO DO FATOR DE TENDÊNCIA ===\n";
echo "(Compara dia atual com média histórica - apenas horas com >= 50 registros)\n\n";

$somaAtual = 0;
$somaHistorica = 0;
$horasUsadas = 0;

for ($h = 0; $h < 24; $h++) {
    // Dados do dia atual
    $sqlAtual = "SELECT COUNT(*) as QTD, SUM(VL_VAZAO_EFETIVA) / 60.0 as MEDIA
                 FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                 WHERE CD_PONTO_MEDICAO = :cdPonto
                   AND CAST(DT_LEITURA AS DATE) = :data
                   AND DATEPART(HOUR, DT_LEITURA) = :hora
                   AND ID_SITUACAO = 1";
    $stmt = $pdoSIMP->prepare($sqlAtual);
    $stmt->execute([':cdPonto' => $cdPonto, ':data' => $data, ':hora' => $h]);
    $rowAtual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Média histórica para esta hora (apenas semanas com >= 50 registros)
    $mediaHist = 0;
    $countHist = 0;
    foreach ($datasHistorico as $dataHist) {
        $stmt = $pdoSIMP->prepare($sqlAtual);
        $stmt->execute([':cdPonto' => $cdPonto, ':data' => $dataHist, ':hora' => $h]);
        $rowHist = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rowHist['QTD'] >= 50) {
            $mediaHist += $rowHist['MEDIA'];
            $countHist++;
        }
    }
    $mediaHist = $countHist > 0 ? $mediaHist / $countHist : 0;
    
    $valorAtual = floatval($rowAtual['MEDIA'] ?? 0);
    $qtdAtual = $rowAtual['QTD'] ?? 0;
    
    // Só usa horas completas no dia atual (>= 50) e com histórico válido
    if ($qtdAtual >= 50 && $mediaHist > 0 && $valorAtual > 0) {
        $somaAtual += $valorAtual;
        $somaHistorica += $mediaHist;
        $horasUsadas++;
        
        if ($h <= 12) { // Mostrar só algumas horas
            echo "Hora $h:00 - Atual: " . round($valorAtual, 2) . " | Histórico: " . round($mediaHist, 2) . "\n";
        }
    }
}

echo "...\n";
echo "Horas usadas para tendência: $horasUsadas\n";
echo "Soma atual: " . round($somaAtual, 2) . "\n";
echo "Soma histórica: " . round($somaHistorica, 2) . "\n";

$fator = $somaHistorica > 0 ? $somaAtual / $somaHistorica : 1;
$fator = max(0.5, min(2.0, $fator));
echo "Fator de tendência: " . round($fator, 4) . " (" . round(($fator - 1) * 100, 2) . "%)\n\n";

// 4. Valor sugerido final
echo "=== 4. VALOR SUGERIDO PARA HORA $hora:00 ===\n";
echo "Média histórica: $mediaHistorica L/s\n";
echo "Fator de tendência: " . round($fator, 4) . "\n";
echo "Valor sugerido: " . round($mediaHistorica * $fator, 2) . " L/s\n\n";

// 5. Comparação
echo "=== 5. COMPARAÇÃO ===\n";
$valorAtualHora = $rowDiaAtual['SOMA'] > 0 ? round($rowDiaAtual['SOMA'] / 60, 2) : 0;
echo "Valor ATUAL no banco (hora $hora): $valorAtualHora L/s\n";
echo "Valor SUGERIDO: " . round($mediaHistorica * $fator, 2) . " L/s\n";
$diferenca = round($mediaHistorica * $fator - $valorAtualHora, 2);
$direcao = $diferenca >= 0 ? "+" : "";
echo "Diferença: {$direcao}{$diferenca} L/s\n";

echo "</pre>";