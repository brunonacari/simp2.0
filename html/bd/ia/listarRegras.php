<?php
/**
 * SIMP - API para Listar Regras da IA
 * Retorna o conteúdo único de instruções
 * 
 * @author Bruno
 * @version 2.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }

    // Buscar registro único
    $sql = "SELECT TOP 1 
                CD_CHAVE,
                CAST(DS_CONTEUDO AS VARCHAR(MAX)) AS DS_CONTEUDO,
                CD_USUARIO_CRIACAO,
                DT_CRIACAO,
                CD_USUARIO_ATUALIZACAO,
                DT_ATUALIZACAO
            FROM SIMP.dbo.IA_REGRAS 
            ORDER BY CD_CHAVE DESC";
    
    $stmt = $pdoSIMP->query($sql);
    $regra = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($regra) {
        echo json_encode([
            'success' => true,
            'regra' => [
                'cdChave' => $regra['CD_CHAVE'],
                'conteudo' => $regra['DS_CONTEUDO'],
                'dtCriacao' => $regra['DT_CRIACAO'],
                'dtAtualizacao' => $regra['DT_ATUALIZACAO'],
                'caracteres' => strlen($regra['DS_CONTEUDO']),
                'linhas' => substr_count($regra['DS_CONTEUDO'], "\n") + 1
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Nenhum registro - tentar carregar do arquivo
        $regrasFile = __DIR__ . '/../config/ia_regras.php';
        $conteudo = '';
        
        if (file_exists($regrasFile)) {
            $conteudo = require $regrasFile;
        }

        echo json_encode([
            'success' => true,
            'regra' => [
                'cdChave' => null,
                'conteudo' => $conteudo,
                'dtCriacao' => null,
                'dtAtualizacao' => null,
                'caracteres' => strlen($conteudo),
                'linhas' => substr_count($conteudo, "\n") + 1
            ],
            'fonte' => 'arquivo'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    // Verificar se é erro de tabela inexistente
    if (strpos($e->getMessage(), 'Invalid object name') !== false) {
        // Tentar carregar do arquivo
        $regrasFile = __DIR__ . '/../config/ia_regras.php';
        $conteudo = '';
        
        if (file_exists($regrasFile)) {
            $conteudo = require $regrasFile;
        }

        echo json_encode([
            'success' => true,
            'regra' => [
                'cdChave' => null,
                'conteudo' => $conteudo,
                'dtCriacao' => null,
                'dtAtualizacao' => null,
                'caracteres' => strlen($conteudo),
                'linhas' => substr_count($conteudo, "\n") + 1
            ],
            'fonte' => 'arquivo',
            'aviso' => 'Tabela IA_REGRAS não encontrada. Usando arquivo de configuração.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro de banco de dados: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
