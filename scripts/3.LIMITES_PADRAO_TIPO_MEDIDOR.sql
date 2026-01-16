-- ============================================================================
-- SIMP - Complemento: Limites Padrão e Estatísticos
-- Para pontos sem cadastro de limites
-- ============================================================================

-- ============================================================================
-- TABELA: LIMITES_PADRAO_TIPO_MEDIDOR
-- Limites default por tipo de medidor (quando não há cadastro específico)
-- ============================================================================

IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[LIMITES_PADRAO_TIPO_MEDIDOR]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR] (
        [ID_TIPO_MEDIDOR]       INT PRIMARY KEY,
        [DS_TIPO_MEDIDOR]       VARCHAR(50) NOT NULL,
        [DS_UNIDADE]            VARCHAR(10) NOT NULL,
        [VL_LIMITE_INFERIOR]    DECIMAL(18,4) NULL,
        [VL_LIMITE_SUPERIOR]    DECIMAL(18,4) NULL,
        [VL_VARIACAO_MAX_PERC]  DECIMAL(18,4) NULL,  -- % máxima de variação entre leituras
        [VL_ZEROS_MAX_PERC]     DECIMAL(18,4) NULL,  -- % máxima de zeros aceitável
        [DS_OBSERVACAO]         VARCHAR(500) NULL
    );

    -- Inserir valores padrão
    INSERT INTO [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR] VALUES
    (1, 'Macromedidor',         'L/s',  0,    500,   200,  25, 'Vazão típica de macromedidores. Ajustar conforme realidade local.'),
    (2, 'Estação Pitométrica',  'L/s',  0,    300,   200,  25, 'Vazão típica de estações pitométricas.'),
    (4, 'Medidor Pressão',      'mca',  0,    80,    50,   10, 'Pressão típica: 10-60 mca. Zero pode indicar falha.'),
    (6, 'Nível Reservatório',   '%',    0,    100,   30,   5,  'Nível 0-100%. Zeros prolongados indicam problema.'),
    (8, 'Hidrômetro',           'L/s',  0,    50,    200,  25, 'Vazão típica de hidrômetros residenciais/comerciais.');
    
    PRINT 'Tabela LIMITES_PADRAO_TIPO_MEDIDOR criada e populada.';
END
GO

-- ============================================================================
-- VIEW: VW_PONTO_MEDICAO_LIMITES_COMPLETO
-- Versão aprimorada que usa: cadastro > limites estatísticos > limites padrão
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_PONTO_MEDICAO_LIMITES')
    DROP VIEW [dbo].[VW_PONTO_MEDICAO_LIMITES];
GO

CREATE VIEW [dbo].[VW_PONTO_MEDICAO_LIMITES]
AS
WITH LimitesEstatisticos AS (
    -- Calcula limites baseados no histórico dos últimos 30 dias
    SELECT 
        CD_PONTO_MEDICAO,
        ID_TIPO_MEDICAO AS ID_TIPO_MEDIDOR,
        AVG(CASE 
            WHEN ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(VL_VAZAO_EFETIVA, VL_VAZAO)
            WHEN ID_TIPO_MEDICAO = 4 THEN VL_PRESSAO
            WHEN ID_TIPO_MEDICAO = 6 THEN VL_RESERVATORIO
        END) AS VL_MEDIA_HIST,
        STDEV(CASE 
            WHEN ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(VL_VAZAO_EFETIVA, VL_VAZAO)
            WHEN ID_TIPO_MEDICAO = 4 THEN VL_PRESSAO
            WHEN ID_TIPO_MEDICAO = 6 THEN VL_RESERVATORIO
        END) AS VL_DESVIO_HIST,
        MIN(CASE 
            WHEN ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(VL_VAZAO_EFETIVA, VL_VAZAO)
            WHEN ID_TIPO_MEDICAO = 4 THEN VL_PRESSAO
            WHEN ID_TIPO_MEDICAO = 6 THEN VL_RESERVATORIO
        END) AS VL_MIN_HIST,
        MAX(CASE 
            WHEN ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(VL_VAZAO_EFETIVA, VL_VAZAO)
            WHEN ID_TIPO_MEDICAO = 4 THEN VL_PRESSAO
            WHEN ID_TIPO_MEDICAO = 6 THEN VL_RESERVATORIO
        END) AS VL_MAX_HIST,
        COUNT(*) AS QTD_REGISTROS_HIST
    FROM [dbo].[REGISTRO_VAZAO_PRESSAO]
    WHERE DT_LEITURA >= DATEADD(DAY, -30, GETDATE())
      AND ID_SITUACAO IN (1, 2)
    GROUP BY CD_PONTO_MEDICAO, ID_TIPO_MEDICAO
    HAVING COUNT(*) >= 1000  -- Mínimo de registros para considerar estatística válida
)
SELECT 
    PM.CD_PONTO_MEDICAO,
    PM.DS_NOME,
    PM.ID_TIPO_MEDIDOR,
    
    -- =============================================
    -- LIMITE INFERIOR: Cadastro > Estatístico > Padrão
    -- =============================================
    COALESCE(
        -- 1º) Cadastro específico do ponto
        PM.VL_LIMITE_INFERIOR_VAZAO,
        -- 2º) Estatístico: média - 3 desvios (mínimo 0)
        CASE 
            WHEN LE.VL_MEDIA_HIST IS NOT NULL AND LE.VL_DESVIO_HIST IS NOT NULL
            THEN CASE 
                WHEN (LE.VL_MEDIA_HIST - 3 * LE.VL_DESVIO_HIST) < 0 THEN 0
                ELSE (LE.VL_MEDIA_HIST - 3 * LE.VL_DESVIO_HIST)
            END
        END,
        -- 3º) Padrão do tipo
        LP.VL_LIMITE_INFERIOR
    ) AS VL_LIMITE_INFERIOR,
    
    -- =============================================
    -- LIMITE SUPERIOR: Cadastro > Capacidade > Estatístico > Padrão
    -- =============================================
    COALESCE(
        -- 1º) Cadastro específico do ponto
        PM.VL_LIMITE_SUPERIOR_VAZAO,
        -- 2º) Capacidade nominal do equipamento
        CASE PM.ID_TIPO_MEDIDOR
            WHEN 1 THEN MAC.VL_CAPACIDADE_NOMINAL
            WHEN 8 THEN HID.VL_LEITURA_LIMITE
        END,
        -- 3º) Estatístico: média + 3 desvios (ou 1.5x o máximo histórico)
        CASE 
            WHEN LE.VL_MEDIA_HIST IS NOT NULL AND LE.VL_DESVIO_HIST IS NOT NULL
            THEN CASE 
                WHEN (LE.VL_MEDIA_HIST + 3 * LE.VL_DESVIO_HIST) > (LE.VL_MAX_HIST * 1.5)
                THEN LE.VL_MAX_HIST * 1.5
                ELSE (LE.VL_MEDIA_HIST + 3 * LE.VL_DESVIO_HIST)
            END
        END,
        -- 4º) Padrão do tipo
        LP.VL_LIMITE_SUPERIOR
    ) AS VL_LIMITE_SUPERIOR,
    
    -- =============================================
    -- CAPACIDADE NOMINAL
    -- =============================================
    COALESCE(
        CASE PM.ID_TIPO_MEDIDOR
            WHEN 1 THEN MAC.VL_CAPACIDADE_NOMINAL
            WHEN 6 THEN NR.VL_VOLUME_TOTAL
            WHEN 8 THEN HID.VL_LEITURA_LIMITE
        END,
        LP.VL_LIMITE_SUPERIOR
    ) AS VL_CAPACIDADE_NOMINAL,
    
    -- =============================================
    -- VARIAÇÃO MÁXIMA PERMITIDA (%)
    -- =============================================
    COALESCE(
        -- Estatístico: 3x o desvio padrão relativo
        CASE 
            WHEN LE.VL_MEDIA_HIST > 0 AND LE.VL_DESVIO_HIST IS NOT NULL
            THEN (LE.VL_DESVIO_HIST / LE.VL_MEDIA_HIST) * 300  -- 3 desvios em %
        END,
        LP.VL_VARIACAO_MAX_PERC,
        200  -- Default 200%
    ) AS VL_VARIACAO_MAX_PERC,
    
    -- =============================================
    -- % MÁXIMA DE ZEROS ACEITÁVEL
    -- =============================================
    COALESCE(
        LP.VL_ZEROS_MAX_PERC,
        25  -- Default 25%
    ) AS VL_ZEROS_MAX_PERC,
    
    -- =============================================
    -- VALORES ESTATÍSTICOS (para referência)
    -- =============================================
    LE.VL_MEDIA_HIST,
    LE.VL_DESVIO_HIST,
    LE.VL_MIN_HIST,
    LE.VL_MAX_HIST,
    LE.QTD_REGISTROS_HIST,
    
    -- =============================================
    -- ORIGEM DOS LIMITES (para debug/auditoria)
    -- =============================================
    CASE 
        WHEN PM.VL_LIMITE_INFERIOR_VAZAO IS NOT NULL THEN 'CADASTRO'
        WHEN LE.VL_MEDIA_HIST IS NOT NULL THEN 'ESTATISTICO'
        ELSE 'PADRAO'
    END AS DS_ORIGEM_LIMITE_INF,
    
    CASE 
        WHEN PM.VL_LIMITE_SUPERIOR_VAZAO IS NOT NULL THEN 'CADASTRO'
        WHEN MAC.VL_CAPACIDADE_NOMINAL IS NOT NULL OR HID.VL_LEITURA_LIMITE IS NOT NULL THEN 'EQUIPAMENTO'
        WHEN LE.VL_MEDIA_HIST IS NOT NULL THEN 'ESTATISTICO'
        ELSE 'PADRAO'
    END AS DS_ORIGEM_LIMITE_SUP,
    
    -- =============================================
    -- OUTRAS INFORMAÇÕES DO PONTO
    -- =============================================
    PM.VL_FATOR_CORRECAO_VAZAO,
    PM.OP_PERIODICIDADE_LEITURA,
    CASE PM.ID_TIPO_MEDIDOR
        WHEN 1 THEN MAC.VL_VAZAO_ESPERADA
        ELSE NULL
    END AS VL_VAZAO_ESPERADA,
    CASE 
        WHEN PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE() 
        THEN 1 ELSE 0 
    END AS FL_ATIVO

FROM [dbo].[PONTO_MEDICAO] PM

-- Join com tabela de limites padrão
LEFT JOIN [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR] LP ON PM.ID_TIPO_MEDIDOR = LP.ID_TIPO_MEDIDOR

-- Join com limites estatísticos
LEFT JOIN LimitesEstatisticos LE ON PM.CD_PONTO_MEDICAO = LE.CD_PONTO_MEDICAO

-- Joins com tabelas específicas de equipamento
LEFT JOIN [dbo].[MACROMEDIDOR] MAC ON PM.CD_PONTO_MEDICAO = MAC.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 1
LEFT JOIN [dbo].[ESTACAO_PITOMETRICA] EP ON PM.CD_PONTO_MEDICAO = EP.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 2
LEFT JOIN [dbo].[MEDIDOR_PRESSAO] MP ON PM.CD_PONTO_MEDICAO = MP.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 4
LEFT JOIN [dbo].[NIVEL_RESERVATORIO] NR ON PM.CD_PONTO_MEDICAO = NR.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 6
LEFT JOIN [dbo].[HIDROMETRO] HID ON PM.CD_PONTO_MEDICAO = HID.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 8;
GO

PRINT 'View VW_PONTO_MEDICAO_LIMITES atualizada com limites estatísticos.';
GO

-- ============================================================================
-- VIEW: VW_PONTOS_SEM_CADASTRO
-- Lista pontos que estão usando limites estatísticos ou padrão
-- Útil para identificar cadastros a serem preenchidos
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_PONTOS_SEM_CADASTRO')
    DROP VIEW [dbo].[VW_PONTOS_SEM_CADASTRO];
GO

CREATE VIEW [dbo].[VW_PONTOS_SEM_CADASTRO]
AS
SELECT 
    PML.CD_PONTO_MEDICAO,
    PML.DS_NOME,
    PML.ID_TIPO_MEDIDOR,
    LP.DS_TIPO_MEDIDOR,
    
    -- Status do cadastro
    CASE 
        WHEN PML.DS_ORIGEM_LIMITE_INF = 'CADASTRO' AND PML.DS_ORIGEM_LIMITE_SUP IN ('CADASTRO', 'EQUIPAMENTO')
        THEN 'COMPLETO'
        WHEN PML.DS_ORIGEM_LIMITE_SUP = 'ESTATISTICO' OR PML.DS_ORIGEM_LIMITE_INF = 'ESTATISTICO'
        THEN 'ESTATISTICO'
        ELSE 'PADRAO'
    END AS STATUS_CADASTRO,
    
    -- Detalhes
    PML.DS_ORIGEM_LIMITE_INF,
    PML.DS_ORIGEM_LIMITE_SUP,
    PML.VL_LIMITE_INFERIOR,
    PML.VL_LIMITE_SUPERIOR,
    PML.VL_CAPACIDADE_NOMINAL,
    
    -- Estatísticas disponíveis
    PML.VL_MEDIA_HIST,
    PML.VL_DESVIO_HIST,
    PML.VL_MIN_HIST,
    PML.VL_MAX_HIST,
    PML.QTD_REGISTROS_HIST,
    
    -- Sugestão de limites baseado no histórico
    CASE 
        WHEN PML.VL_MEDIA_HIST IS NOT NULL 
        THEN 'Sugestão: Inferior=' + CAST(CAST(CASE WHEN PML.VL_MEDIA_HIST - 2*PML.VL_DESVIO_HIST < 0 THEN 0 ELSE PML.VL_MEDIA_HIST - 2*PML.VL_DESVIO_HIST END AS DECIMAL(10,2)) AS VARCHAR) +
             ', Superior=' + CAST(CAST(PML.VL_MEDIA_HIST + 2*PML.VL_DESVIO_HIST AS DECIMAL(10,2)) AS VARCHAR)
        ELSE 'Sem histórico suficiente para sugerir'
    END AS DS_SUGESTAO_LIMITES

FROM [dbo].[VW_PONTO_MEDICAO_LIMITES] PML
LEFT JOIN [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR] LP ON PML.ID_TIPO_MEDIDOR = LP.ID_TIPO_MEDIDOR
WHERE PML.FL_ATIVO = 1
  AND (PML.DS_ORIGEM_LIMITE_INF <> 'CADASTRO' OR PML.DS_ORIGEM_LIMITE_SUP NOT IN ('CADASTRO', 'EQUIPAMENTO'));
GO

PRINT 'View VW_PONTOS_SEM_CADASTRO criada.';
GO

-- ============================================================================
-- ATUALIZAÇÃO DA SP: Usar variação máxima específica por ponto
-- ============================================================================

-- Atualizar a regra de SPIKE para usar VL_VARIACAO_MAX_PERC da view
-- (Este é um patch para a SP principal)

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[SP_DETECTAR_SPIKES_DINAMICO]') AND type in (N'P'))
    DROP PROCEDURE [dbo].[SP_DETECTAR_SPIKES_DINAMICO];
GO

CREATE PROCEDURE [dbo].[SP_DETECTAR_SPIKES_DINAMICO]
    @DT_PROCESSAMENTO DATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Detectar spikes usando limite dinâmico por ponto
    UPDATE MRH
    SET [FL_SPIKE] = 1,
        [FL_ANOMALIA] = 1,
        [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
            'SPIKE_' + CAST(CAST(MRH.[VL_VARIACAO_PERC_MAX] AS INT) AS VARCHAR) + 
            '%_LIMITE_' + CAST(CAST(PML.VL_VARIACAO_MAX_PERC AS INT) AS VARCHAR) + '%'
    FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
    INNER JOIN [dbo].[VW_PONTO_MEDICAO_LIMITES] PML ON MRH.[CD_PONTO_MEDICAO] = PML.[CD_PONTO_MEDICAO]
    WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
      AND MRH.[VL_VARIACAO_PERC_MAX] > PML.VL_VARIACAO_MAX_PERC
      AND MRH.[FL_SPIKE] = 0;  -- Não sobrescrever se já marcado
    
    PRINT 'Spikes detectados com limites dinâmicos: ' + CAST(@@ROWCOUNT AS VARCHAR);
END
GO

-- ============================================================================
-- QUERY PARA VERIFICAR STATUS DOS CADASTROS
-- ============================================================================

/*
-- Resumo de cadastros por status
SELECT 
    STATUS_CADASTRO,
    COUNT(*) AS QTD_PONTOS
FROM VW_PONTOS_SEM_CADASTRO
GROUP BY STATUS_CADASTRO;

-- Pontos usando limites padrão (prioridade para cadastrar)
SELECT * FROM VW_PONTOS_SEM_CADASTRO 
WHERE STATUS_CADASTRO = 'PADRAO'
ORDER BY QTD_REGISTROS_HIST DESC;

-- Pontos com sugestão de limites baseada no histórico
SELECT 
    CD_PONTO_MEDICAO,
    DS_NOME,
    DS_TIPO_MEDIDOR,
    VL_MEDIA_HIST,
    VL_DESVIO_HIST,
    DS_SUGESTAO_LIMITES
FROM VW_PONTOS_SEM_CADASTRO 
WHERE STATUS_CADASTRO = 'ESTATISTICO'
ORDER BY QTD_REGISTROS_HIST DESC;
*/

PRINT '';
PRINT '============================================';
PRINT 'RESUMO DA LÓGICA DE LIMITES';
PRINT '============================================';
PRINT '1. CADASTRO: Usa VL_LIMITE_INFERIOR_VAZAO / VL_LIMITE_SUPERIOR_VAZAO do PONTO_MEDICAO';
PRINT '2. EQUIPAMENTO: Usa VL_CAPACIDADE_NOMINAL do MACROMEDIDOR/HIDROMETRO';
PRINT '3. ESTATISTICO: Calcula média ± 3 desvios do histórico (30 dias)';
PRINT '4. PADRAO: Usa valores default da tabela LIMITES_PADRAO_TIPO_MEDIDOR';
PRINT '';
PRINT 'A view VW_PONTOS_SEM_CADASTRO lista pontos que precisam de cadastro.';
PRINT '============================================';
GO