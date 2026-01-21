<?php
// bd/registroManutencao/excluirRegistro.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;

    if ($cdChave <= 0) {
        throw new Exception('Registro não informado.');
    }

    // Busca dados antes de excluir (para log)
    $sqlVerifica = "SELECT R.*, P.CD_CODIGO, P.CD_ANO 
                    FROM SIMP.dbo.REGISTRO_MANUTENCAO R
                    LEFT JOIN SIMP.dbo.PROGRAMACAO_MANUTENCAO P ON R.CD_PROGRAMACAO = P.CD_CHAVE
                    WHERE R.CD_CHAVE = :cd_chave";
    $stmtVerifica = $pdoSIMP->prepare($sqlVerifica);
    $stmtVerifica->execute([':cd_chave' => $cdChave]);
    $registro = $stmtVerifica->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        throw new Exception('Registro não encontrado.');
    }

    // Montar identificador para log
    $codigoProgramacao = '';
    if (!empty($registro['CD_CODIGO']) && !empty($registro['CD_ANO'])) {
        $codigoProgramacao = str_pad($registro['CD_CODIGO'], 3, '0', STR_PAD_LEFT) . '/' . $registro['CD_ANO'];
    }
    $ocorrencia = $registro['CD_OCORRENCIA'] ?? '';
    $identificador = $codigoProgramacao ? "$codigoProgramacao - Ocorrência $ocorrencia" : "ID: $cdChave";

    // Exclui o registro
    $sql = "DELETE FROM SIMP.dbo.REGISTRO_MANUTENCAO WHERE CD_CHAVE = :cd_chave";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_chave' => $cdChave]);

    // Log (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogDelete')) {
            registrarLogDelete('Registro de Manutenção', 'Registro', $cdChave, $identificador, $registro);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => 'Registro excluído com sucesso!'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir registro: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}