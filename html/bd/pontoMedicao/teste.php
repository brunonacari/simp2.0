<?php
/**
 * Debug - Descobrir valores válidos para campos NOT NULL
 */

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Debug Valores Default</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
.ok { color: #4ec9b0; }
.erro { color: #f14c4c; }
.info { color: #569cd6; }
pre { background: #252526; padding: 10px; border-radius: 4px; overflow-x: auto; }
h2 { border-bottom: 1px solid #444; padding-bottom: 5px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #444; padding: 5px 10px; }
th { background: #333; }
</style></head><body>";

echo "<h1>🔍 Descobrir Valores Default</h1>";

session_start();
if (!isset($_SESSION['sucesso']) || $_SESSION['sucesso'] != 1) {
    echo "<p class='erro'>❌ Não autenticado</p></body></html>";
    exit;
}

include_once '../conexao.php';

// 1. Verificar campos NOT NULL de MACROMEDIDOR
echo "<h2>1. Campos NOT NULL de MACROMEDIDOR (sem default)</h2>";

try {
    $sql = "SELECT COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'MACROMEDIDOR' 
            AND IS_NULLABLE = 'NO'
            AND COLUMN_DEFAULT IS NULL
            AND COLUMNPROPERTY(OBJECT_ID('SIMP.dbo.MACROMEDIDOR'), COLUMN_NAME, 'IsIdentity') = 0
            ORDER BY ORDINAL_POSITION";
    
    $stmt = $pdoSIMP->query($sql);
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    foreach ($cols as $col) {
        echo "<li><strong>{$col['COLUMN_NAME']}</strong> ({$col['DATA_TYPE']})</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p class='erro'>" . $e->getMessage() . "</p>";
}

// 2. Descobrir valores existentes em registros de MACROMEDIDOR
echo "<h2>2. Valores usados em registros existentes</h2>";

try {
    $sql = "SELECT TOP 5 CD_CHAVE, CD_PONTO_MEDICAO, CD_TIPO_MEDIDOR, VL_DIAMETRO, ID_PRODUTO 
            FROM SIMP.dbo.MACROMEDIDOR 
            ORDER BY CD_CHAVE DESC";
    $stmt = $pdoSIMP->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        echo "<table>";
        echo "<tr><th>CD_CHAVE</th><th>CD_PONTO_MEDICAO</th><th>CD_TIPO_MEDIDOR</th><th>VL_DIAMETRO</th><th>ID_PRODUTO</th></tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . ($val ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        
        // Pegar valores mais comuns
        $sql = "SELECT TOP 1 CD_TIPO_MEDIDOR, ID_PRODUTO FROM SIMP.dbo.MACROMEDIDOR WHERE CD_TIPO_MEDIDOR IS NOT NULL";
        $stmt = $pdoSIMP->query($sql);
        $comum = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($comum) {
            echo "<p class='ok'>Valores sugeridos para default:</p>";
            echo "<ul>";
            echo "<li>CD_TIPO_MEDIDOR: <strong>{$comum['CD_TIPO_MEDIDOR']}</strong></li>";
            echo "<li>ID_PRODUTO: <strong>{$comum['ID_PRODUTO']}</strong></li>";
            echo "</ul>";
        }
    } else {
        echo "<p class='info'>Nenhum registro encontrado</p>";
    }
} catch (Exception $e) {
    echo "<p class='erro'>" . $e->getMessage() . "</p>";
}

// 3. Verificar tabela PRODUTO
echo "<h2>3. Tabela de Produtos (para ID_PRODUTO)</h2>";

$tabelasProduto = ['PRODUTO', 'PRODUTOS', 'TB_PRODUTO', 'TIPO_PRODUTO'];
foreach ($tabelasProduto as $t) {
    try {
        $sql = "SELECT TOP 10 * FROM SIMP.dbo.$t ORDER BY 1";
        $stmt = $pdoSIMP->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "<p class='ok'>Tabela encontrada: $t</p>";
            echo "<pre>" . print_r($rows, true) . "</pre>";
            break;
        }
    } catch (Exception $e) {
        // Tabela não existe, continuar
    }
}

// 4. Verificar FKs
echo "<h2>4. Foreign Keys de MACROMEDIDOR</h2>";

try {
    $sql = "SELECT 
                COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS COLUNA,
                OBJECT_NAME(fkc.referenced_object_id) AS TABELA_REF,
                COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS COLUNA_REF
            FROM sys.foreign_keys fk
            JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
            WHERE OBJECT_NAME(fk.parent_object_id) = 'MACROMEDIDOR'";
    
    $stmt = $pdoSIMP->query($sql);
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Coluna</th><th>Tabela Referenciada</th><th>Coluna Ref</th></tr>";
    foreach ($fks as $fk) {
        echo "<tr><td>{$fk['COLUNA']}</td><td>{$fk['TABELA_REF']}</td><td>{$fk['COLUNA_REF']}</td></tr>";
        
        // Buscar valores válidos
        try {
            $sqlRef = "SELECT TOP 5 {$fk['COLUNA_REF']} FROM SIMP.dbo.{$fk['TABELA_REF']} ORDER BY 1";
            $stmtRef = $pdoSIMP->query($sqlRef);
            $vals = $stmtRef->fetchAll(PDO::FETCH_COLUMN);
            echo "<tr><td colspan='3' class='info'>Valores válidos: " . implode(', ', $vals) . "</td></tr>";
        } catch (Exception $e) {
            // ignorar
        }
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p class='erro'>" . $e->getMessage() . "</p>";
}

// 5. Testar INSERT com valores válidos
echo "<h2>5. Testar INSERT com Valores Default</h2>";

if (isset($_GET['testar_insert'])) {
    try {
        // Buscar valores válidos
        $sql = "SELECT TOP 1 CD_TIPO_MEDIDOR, ID_PRODUTO FROM SIMP.dbo.MACROMEDIDOR WHERE CD_TIPO_MEDIDOR IS NOT NULL AND ID_PRODUTO IS NOT NULL";
        $stmt = $pdoSIMP->query($sql);
        $defaults = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$defaults) {
            echo "<p class='erro'>Não foi possível encontrar valores default</p>";
        } else {
            // Buscar ponto sem registro
            $sql = "SELECT TOP 1 PM.CD_PONTO_MEDICAO 
                    FROM SIMP.dbo.PONTO_MEDICAO PM
                    WHERE PM.ID_TIPO_MEDIDOR = 1
                    AND NOT EXISTS (SELECT 1 FROM SIMP.dbo.MACROMEDIDOR M WHERE M.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO)";
            $stmt = $pdoSIMP->query($sql);
            $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ponto) {
                echo "<p class='info'>Nenhum ponto disponível</p>";
            } else {
                $pdoSIMP->beginTransaction();
                
                $sql = "INSERT INTO SIMP.dbo.MACROMEDIDOR (CD_PONTO_MEDICAO, CD_TIPO_MEDIDOR, VL_DIAMETRO, ID_PRODUTO) VALUES (?, ?, ?, ?)";
                $params = [$ponto['CD_PONTO_MEDICAO'], $defaults['CD_TIPO_MEDIDOR'], 0, $defaults['ID_PRODUTO']];
                
                echo "<p class='info'>SQL: $sql</p>";
                echo "<p class='info'>Params: " . implode(', ', $params) . "</p>";
                
                $stmt = $pdoSIMP->prepare($sql);
                $result = $stmt->execute($params);
                
                if ($result) {
                    echo "<p class='ok'>✅ INSERT FUNCIONOU!</p>";
                } else {
                    echo "<p class='erro'>❌ Falhou</p>";
                    echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
                }
                
                $pdoSIMP->rollBack();
            }
        }
    } catch (Exception $e) {
        if ($pdoSIMP->inTransaction()) $pdoSIMP->rollBack();
        echo "<p class='erro'>" . $e->getMessage() . "</p>";
    }
} else {
    echo "<p><a href='?testar_insert=1' style='background:#4ec9b0; color:#000; padding:10px 20px; text-decoration:none; border-radius:5px;'>🚀 Testar INSERT</a></p>";
}

echo "</body></html>";