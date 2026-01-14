<?php
/**
 * Teste do consultarDadosIA.php
 * Acesse: /bd/operacoes/testeConsultarIA.php?cdPonto=1051&data=2025-12-01
 */

header('Content-Type: text/html; charset=utf-8');

$cdPonto = isset($_GET['cdPonto']) ? $_GET['cdPonto'] : 'NAO_INFORMADO';
$data = isset($_GET['data']) ? $_GET['data'] : 'NAO_INFORMADO';

echo "<h3>Teste do consultarDadosIA.php</h3>";
echo "<p>cdPonto recebido: <strong>$cdPonto</strong></p>";
echo "<p>data recebida: <strong>$data</strong></p>";
echo "<p>GET: " . json_encode($_GET) . "</p>";

echo "<h4>Fazendo requisição GET ao consultarDadosIA.php:</h4>";

// Construir URL
$url = "consultarDadosIA.php?cdPonto=$cdPonto&data=$data";
echo "<p>URL: <code>$url</code></p>";

// Fazer requisição
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost" . dirname($_SERVER['REQUEST_URI']) . "/" . $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p>HTTP Code: <strong>$httpCode</strong></p>";

if ($error) {
    echo "<p style='color:red'>Erro cURL: $error</p>";
}

echo "<h4>Resposta (primeiros 2000 chars):</h4>";
echo "<pre style='background:#f0f0f0;padding:10px;overflow:auto;max-height:400px;'>";
echo htmlspecialchars(substr($response, 0, 2000));
echo "</pre>";

// Tentar parsear JSON
$json = json_decode($response, true);
if ($json) {
    echo "<h4>JSON Parseado:</h4>";
    echo "<p>success: <strong>" . ($json['success'] ? 'true' : 'false') . "</strong></p>";
    if (isset($json['error'])) {
        echo "<p style='color:red'>error: <strong>" . htmlspecialchars($json['error']) . "</strong></p>";
    }
    if (isset($json['dados'])) {
        echo "<p style='color:green'>dados presentes: " . count($json['dados']) . " chaves</p>";
        echo "<p>Chaves: " . implode(', ', array_keys($json['dados'])) . "</p>";
        
        if (isset($json['dados']['historico_por_hora'])) {
            echo "<p style='color:blue'>historico_por_hora presente!</p>";
            $hist = $json['dados']['historico_por_hora'];
            echo "<p>Fator tendência: " . ($hist['fator_tendencia'] ?? 'N/A') . "</p>";
            echo "<p>Horas com dados: " . count($hist['horas'] ?? []) . "</p>";
        }
    }
} else {
    echo "<p style='color:red'>Erro ao parsear JSON: " . json_last_error_msg() . "</p>";
}