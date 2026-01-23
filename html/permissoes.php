<?php
/**
 * DEBUG - Verificar carregamento de permissões
 * Colocar temporariamente no início do dashboard.php
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Recarregar permissões do banco (garante que estão atualizadas)
recarregarPermissoesUsuario();

echo '<div style="background:#0f172a;color:#e2e8f0;padding:20px;margin:20px;border-radius:12px;font-family:monospace;font-size:13px;">';
echo '<h2 style="color:#60a5fa;margin-top:0;">🔍 Debug de Permissões</h2>';

// 1. Verificar dados da sessão
echo '<h3 style="color:#fbbf24;">1. Dados da Sessão</h3>';
echo '<table style="width:100%;border-collapse:collapse;">';
echo '<tr><td style="padding:5px;border-bottom:1px solid #334155;">CD_USUARIO:</td><td style="padding:5px;border-bottom:1px solid #334155;color:#4ade80;">' . ($_SESSION['cd_usuario'] ?? 'NÃO DEFINIDO') . '</td></tr>';
echo '<tr><td style="padding:5px;border-bottom:1px solid #334155;">CD_GRUPO:</td><td style="padding:5px;border-bottom:1px solid #334155;color:#4ade80;">' . ($_SESSION['cd_grupo'] ?? 'NÃO DEFINIDO') . '</td></tr>';
echo '<tr><td style="padding:5px;border-bottom:1px solid #334155;">GRUPO:</td><td style="padding:5px;border-bottom:1px solid #334155;color:#4ade80;">' . ($_SESSION['grupo'] ?? 'NÃO DEFINIDO') . '</td></tr>';
echo '<tr><td style="padding:5px;border-bottom:1px solid #334155;">LOGIN:</td><td style="padding:5px;border-bottom:1px solid #334155;color:#4ade80;">' . ($_SESSION['login'] ?? 'NÃO DEFINIDO') . '</td></tr>';
echo '</table>';

// 2. Verificar permissões na sessão
echo '<h3 style="color:#fbbf24;">2. Permissões na Sessão ($_SESSION[\'permissoes_nome\'])</h3>';
if (isset($_SESSION['permissoes_nome']) && !empty($_SESSION['permissoes_nome'])) {
    echo '<div style="max-height:200px;overflow-y:auto;background:#1e293b;padding:10px;border-radius:8px;">';
    echo '<table style="width:100%;border-collapse:collapse;">';
    echo '<tr style="background:#334155;"><th style="padding:8px;text-align:left;">Funcionalidade</th><th style="padding:8px;text-align:center;">Acesso</th></tr>';
    foreach ($_SESSION['permissoes_nome'] as $nome => $dados) {
        $acesso = $dados['acesso'] == 2 ? '✏️ Escrita' : '👁️ Leitura';
        $cor = $dados['acesso'] == 2 ? '#4ade80' : '#60a5fa';
        echo "<tr><td style='padding:5px;border-bottom:1px solid #334155;'>{$nome}</td><td style='padding:5px;border-bottom:1px solid #334155;text-align:center;color:{$cor};'>{$acesso}</td></tr>";
    }
    echo '</table></div>';
    echo '<p style="color:#94a3b8;">Total: <strong style="color:#4ade80;">' . count($_SESSION['permissoes_nome']) . '</strong> permissões</p>';
} else {
    echo '<p style="color:#f87171;">⚠️ VAZIO ou NÃO DEFINIDO!</p>';
}

// 3. Buscar direto do banco
echo '<h3 style="color:#fbbf24;">3. Consulta Direta no Banco</h3>';
$cdGrupo = $_SESSION['cd_grupo'] ?? 0;

if ($cdGrupo > 0) {
    try {
        $sqlDireto = "
            SELECT 
                F.CD_FUNCIONALIDADE,
                F.DS_NOME AS DS_FUNCIONALIDADE,
                GF.ID_TIPO_ACESSO
            FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE GF
            INNER JOIN SIMP.dbo.FUNCIONALIDADE F ON GF.CD_FUNCIONALIDADE = F.CD_FUNCIONALIDADE
            WHERE GF.CD_GRUPO_USUARIO = :cdGrupo
            ORDER BY F.DS_NOME
        ";
        $stmtDireto = $pdoSIMP->prepare($sqlDireto);
        $stmtDireto->execute([':cdGrupo' => $cdGrupo]);
        $permissoesBanco = $stmtDireto->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($permissoesBanco)) {
            echo '<div style="max-height:200px;overflow-y:auto;background:#1e293b;padding:10px;border-radius:8px;">';
            echo '<table style="width:100%;border-collapse:collapse;">';
            echo '<tr style="background:#334155;"><th style="padding:8px;text-align:left;">CD</th><th style="padding:8px;text-align:left;">Funcionalidade</th><th style="padding:8px;text-align:center;">Acesso</th></tr>';
            foreach ($permissoesBanco as $p) {
                $acesso = $p['ID_TIPO_ACESSO'] == 2 ? '✏️ Escrita' : '👁️ Leitura';
                $cor = $p['ID_TIPO_ACESSO'] == 2 ? '#4ade80' : '#60a5fa';
                echo "<tr><td style='padding:5px;border-bottom:1px solid #334155;'>{$p['CD_FUNCIONALIDADE']}</td><td style='padding:5px;border-bottom:1px solid #334155;'>{$p['DS_FUNCIONALIDADE']}</td><td style='padding:5px;border-bottom:1px solid #334155;text-align:center;color:{$cor};'>{$acesso}</td></tr>";
            }
            echo '</table></div>';
            echo '<p style="color:#94a3b8;">Total no banco: <strong style="color:#4ade80;">' . count($permissoesBanco) . '</strong> permissões</p>';
        } else {
            echo '<p style="color:#f87171;">⚠️ Nenhuma permissão encontrada no banco para o grupo ' . $cdGrupo . '</p>';
        }
        
    } catch (Exception $e) {
        echo '<p style="color:#f87171;">❌ Erro na consulta: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color:#f87171;">⚠️ CD_GRUPO não definido na sessão!</p>';
}

// 4. Verificar funcionalidade específica
echo '<h3 style="color:#fbbf24;">4. Buscar "Analise Dados" no Banco</h3>';
try {
    $sqlFunc = "SELECT CD_FUNCIONALIDADE, DS_NOME FROM SIMP.dbo.FUNCIONALIDADE 
                WHERE DS_NOME LIKE '%Analis%' OR DS_NOME LIKE '%Dashboard%' OR DS_NOME LIKE '%Dados%'
                ORDER BY DS_NOME";
    $stmtFunc = $pdoSIMP->query($sqlFunc);
    $funcs = $stmtFunc->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($funcs)) {
        echo '<ul style="margin:0;padding-left:20px;">';
        foreach ($funcs as $f) {
            echo "<li style='margin:5px 0;'>[{$f['CD_FUNCIONALIDADE']}] <strong style='color:#4ade80;'>{$f['DS_NOME']}</strong></li>";
        }
        echo '</ul>';
    } else {
        echo '<p style="color:#f87171;">⚠️ Nenhuma funcionalidade encontrada com esses termos!</p>';
        echo '<p style="color:#94a3b8;">Você precisa cadastrar a funcionalidade "Análise de Dados" em Cadastros Administrativos.</p>';
    }
} catch (Exception $e) {
    echo '<p style="color:#f87171;">❌ Erro: ' . $e->getMessage() . '</p>';
}

// 5. Comparar sessão vs banco
echo '<h3 style="color:#fbbf24;">5. Diagnóstico</h3>';
$qtdSessao = count($_SESSION['permissoes_nome'] ?? []);
$qtdBanco = count($permissoesBanco ?? []);

if ($qtdSessao == 0 && $qtdBanco == 0) {
    echo '<p style="color:#f87171;font-size:16px;">❌ <strong>PROBLEMA:</strong> Nenhuma permissão no banco NEM na sessão!</p>';
    echo '<p style="color:#94a3b8;">→ Vincule funcionalidades ao grupo em Cadastros Administrativos → Grupos → Permissões</p>';
} elseif ($qtdSessao == 0 && $qtdBanco > 0) {
    echo '<p style="color:#f87171;font-size:16px;">❌ <strong>PROBLEMA:</strong> Banco tem ' . $qtdBanco . ' permissões, mas sessão está VAZIA!</p>';
    echo '<p style="color:#94a3b8;">→ A função de login não está carregando as permissões corretamente.</p>';
    echo '<p style="color:#94a3b8;">→ Verifique o arquivo <code>bd/ldap.php</code> ou <code>bd/loginLocal.php</code></p>';
} elseif ($qtdSessao != $qtdBanco) {
    echo '<p style="color:#fbbf24;font-size:16px;">⚠️ <strong>AVISO:</strong> Sessão tem ' . $qtdSessao . ' permissões, banco tem ' . $qtdBanco . '</p>';
    echo '<p style="color:#94a3b8;">→ Faça logout e login para sincronizar.</p>';
} else {
    echo '<p style="color:#4ade80;font-size:16px;">✅ <strong>OK:</strong> Sessão e banco sincronizados (' . $qtdSessao . ' permissões)</p>';
}

echo '</div>';

// Mostrar botão para continuar
echo '<div style="text-align:center;margin:20px;">';
echo '<a href="dashboard.php?skip_debug=1" style="background:#3b82f6;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">Continuar para o Dashboard →</a>';
echo '</div>';

exit;
?>