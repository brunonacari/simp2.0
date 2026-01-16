-- ============================================================================
-- SIMP - Views e Queries para Dashboard e IA
-- Consultas otimizadas sobre as tabelas de resumo
-- ============================================================================

-- ============================================================================
-- VIEW 1: VW_DASHBOARD_RESUMO_GERAL
-- Visão geral para cards do dashboard (últimos 7 dias)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_DASHBOARD_RESUMO_GERAL')
    DROP VIEW [dbo].[VW_DASHBOARD_RESUMO_GERAL];
GO

CREATE VIEW [dbo].[VW_DASHBOARD_RESUMO_GERAL]
AS
SELECT 
    -- Totais
    COUNT(DISTINCT CD_PONTO_MEDICAO) AS TOTAL_PONTOS,
    COUNT(*) AS TOTAL_REGISTROS_DIA,
    SUM(QTD_REGISTROS) AS TOTAL_MEDICOES,
    
    -- Anomalias
    SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS PONTOS_COM_ANOMALIA,
    CAST(SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) AS DECIMAL(5,2)) AS PERC_ANOMALIA,
    
    -- Tratamentos
    SUM(CASE WHEN ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS PONTOS_TRATADOS,
    SUM(QTD_TRATAMENTOS) AS TOTAL_TRATAMENTOS,
    CAST(SUM(CASE WHEN ID_SITUACAO = 2 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) AS DECIMAL(5,2)) AS PERC_TRATADO,
    
    -- Completude
    SUM(CASE WHEN QTD_REGISTROS >= 1400 THEN 1 ELSE 0 END) AS PONTOS_COMPLETOS,
    CAST(SUM(CASE WHEN QTD_REGISTROS >= 1400 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) AS DECIMAL(5,2)) AS PERC_COMPLETO,
    
    -- Período
    MIN(DT_MEDICAO) AS DATA_INICIO,
    MAX(DT_MEDICAO) AS DATA_FIM,
    DATEDIFF(DAY, MIN(DT_MEDICAO), MAX(DT_MEDICAO)) + 1 AS DIAS_ANALISADOS

FROM [dbo].[MEDICAO_RESUMO_DIARIO]
WHERE DT_MEDICAO >= DATEADD(DAY, -7, CAST(GETDATE() AS DATE));
GO

-- ============================================================================
-- VIEW 2: VW_PONTOS_CRITICOS
-- Pontos que precisam de atenção (anomalias ou tratamentos frequentes)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_PONTOS_CRITICOS')
    DROP VIEW [dbo].[VW_PONTOS_CRITICOS];
GO

CREATE VIEW [dbo].[VW_PONTOS_CRITICOS]
AS
SELECT 
    MRD.CD_PONTO_MEDICAO,
    PM.DS_NOME AS NOME_PONTO,
    PM.CD_PONTO_MEDICAO_ID AS CODIGO_PONTO,
    
    -- Tipo de medidor
    MRD.ID_TIPO_MEDIDOR,
    CASE MRD.ID_TIPO_MEDIDOR
        WHEN 1 THEN 'Macromedidor'
        WHEN 2 THEN 'Estação Pitométrica'
        WHEN 4 THEN 'Pressão'
        WHEN 6 THEN 'Nível Reservatório'
        WHEN 8 THEN 'Hidrômetro'
    END AS TIPO_MEDIDOR,
    
    -- Contagens últimos 7 dias
    COUNT(*) AS DIAS_ANALISADOS,
    SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS DIAS_COM_ANOMALIA,
    SUM(CASE WHEN MRD.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS DIAS_TRATADOS,
    SUM(MRD.QTD_TRATAMENTOS) AS TOTAL_TRATAMENTOS,
    
    -- Percentuais
    CAST(SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) AS DECIMAL(5,2)) AS PERC_ANOMALIA,
    CAST(SUM(CASE WHEN MRD.ID_SITUACAO = 2 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) AS DECIMAL(5,2)) AS PERC_TRATADO,
    
    -- Médias
    AVG(MRD.VL_MEDIA_DIARIA) AS MEDIA_PERIODO,
    AVG(MRD.VL_DESVIO_HISTORICO) AS DESVIO_MEDIO_HISTORICO,
    
    -- Última anomalia
    MAX(CASE WHEN MRD.FL_ANOMALIA = 1 THEN MRD.DT_MEDICAO END) AS ULTIMA_ANOMALIA,
    
    -- Score de criticidade (quanto maior, mais crítico)
    (SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) * 2) + 
    (SUM(CASE WHEN MRD.ID_SITUACAO = 2 THEN 1 ELSE 0 END) * 3) +
    (CASE WHEN AVG(ABS(MRD.VL_DESVIO_HISTORICO)) > 30 THEN 5 ELSE 0 END) AS SCORE_CRITICIDADE

FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
WHERE MRD.DT_MEDICAO >= DATEADD(DAY, -7, CAST(GETDATE() AS DATE))
GROUP BY 
    MRD.CD_PONTO_MEDICAO,
    PM.DS_NOME,
    PM.CD_PONTO_MEDICAO_ID,
    MRD.ID_TIPO_MEDIDOR
HAVING SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) > 0
    OR SUM(CASE WHEN MRD.ID_SITUACAO = 2 THEN 1 ELSE 0 END) > 0;
GO

-- ============================================================================
-- VIEW 3: VW_TENDENCIAS_PONTOS
-- Tendências de variação em relação ao histórico
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_TENDENCIAS_PONTOS')
    DROP VIEW [dbo].[VW_TENDENCIAS_PONTOS];
GO

CREATE VIEW [dbo].[VW_TENDENCIAS_PONTOS]
AS
SELECT 
    MRD.CD_PONTO_MEDICAO,
    PM.DS_NOME AS NOME_PONTO,
    PM.CD_PONTO_MEDICAO_ID AS CODIGO_PONTO,
    MRD.ID_TIPO_MEDIDOR,
    
    -- Valores
    AVG(MRD.VL_MEDIA_DIARIA) AS MEDIA_ATUAL,
    AVG(MRD.VL_MEDIA_HISTORICA) AS MEDIA_HISTORICA,
    AVG(MRD.VL_DESVIO_HISTORICO) AS DESVIO_PERCENTUAL,
    
    -- Classificação da tendência
    CASE 
        WHEN AVG(MRD.VL_DESVIO_HISTORICO) > 30 THEN 'ALTA_SIGNIFICATIVA'
        WHEN AVG(MRD.VL_DESVIO_HISTORICO) > 10 THEN 'ALTA_MODERADA'
        WHEN AVG(MRD.VL_DESVIO_HISTORICO) < -30 THEN 'QUEDA_SIGNIFICATIVA'
        WHEN AVG(MRD.VL_DESVIO_HISTORICO) < -10 THEN 'QUEDA_MODERADA'
        ELSE 'ESTAVEL'
    END AS TENDENCIA,
    
    -- Ícone para dashboard
    CASE 
        WHEN AVG(MRD.VL_DESVIO_HISTORICO) > 10 THEN 'trending-up'
        WHEN AVG(MRD.VL_DESVIO_HISTORICO) < -10 THEN 'trending-down'
        ELSE 'remove'
    END AS ICONE_TENDENCIA,
    
    -- Cor para dashboard
    CASE 
        WHEN ABS(AVG(MRD.VL_DESVIO_HISTORICO)) > 30 THEN '#dc2626'  -- Vermelho
        WHEN ABS(AVG(MRD.VL_DESVIO_HISTORICO)) > 10 THEN '#f59e0b'  -- Amarelo
        ELSE '#22c55e'  -- Verde
    END AS COR_TENDENCIA

FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
WHERE MRD.DT_MEDICAO >= DATEADD(DAY, -7, CAST(GETDATE() AS DATE))
  AND MRD.VL_MEDIA_HISTORICA IS NOT NULL
  AND MRD.VL_MEDIA_HISTORICA > 0
GROUP BY 
    MRD.CD_PONTO_MEDICAO,
    PM.DS_NOME,
    PM.CD_PONTO_MEDICAO_ID,
    MRD.ID_TIPO_MEDIDOR;
GO

-- ============================================================================
-- VIEW 4: VW_ANOMALIAS_RECENTES
-- Lista de anomalias dos últimos 7 dias para análise
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_ANOMALIAS_RECENTES')
    DROP VIEW [dbo].[VW_ANOMALIAS_RECENTES];
GO

CREATE VIEW [dbo].[VW_ANOMALIAS_RECENTES]
AS
SELECT 
    MRD.CD_PONTO_MEDICAO,
    PM.DS_NOME AS NOME_PONTO,
    PM.CD_PONTO_MEDICAO_ID AS CODIGO_PONTO,
    MRD.DT_MEDICAO,
    MRD.ID_TIPO_MEDIDOR,
    CASE MRD.ID_TIPO_MEDIDOR
        WHEN 1 THEN 'Macromedidor'
        WHEN 2 THEN 'Estação Pitométrica'
        WHEN 4 THEN 'Pressão'
        WHEN 6 THEN 'Nível Reservatório'
        WHEN 8 THEN 'Hidrômetro'
    END AS TIPO_MEDIDOR,
    MRD.DS_ANOMALIAS,
    MRD.QTD_REGISTROS,
    MRD.VL_MEDIA_DIARIA,
    MRD.VL_MIN_DIARIO,
    MRD.VL_MAX_DIARIO,
    MRD.VL_DESVIO_HISTORICO,
    MRD.ID_SITUACAO,
    CASE WHEN MRD.ID_SITUACAO = 2 THEN 'Tratado' ELSE 'Pendente' END AS STATUS_TRATAMENTO,
    MRD.DS_HORAS_TRATADAS

FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
WHERE MRD.FL_ANOMALIA = 1
  AND MRD.DT_MEDICAO >= DATEADD(DAY, -7, CAST(GETDATE() AS DATE));
GO

-- ============================================================================
-- VIEW 5: VW_TRATAMENTOS_REALIZADOS
-- Histórico de tratamentos para análise de padrões
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_TRATAMENTOS_REALIZADOS')
    DROP VIEW [dbo].[VW_TRATAMENTOS_REALIZADOS];
GO

CREATE VIEW [dbo].[VW_TRATAMENTOS_REALIZADOS]
AS
SELECT 
    MRD.CD_PONTO_MEDICAO,
    PM.DS_NOME AS NOME_PONTO,
    PM.CD_PONTO_MEDICAO_ID AS CODIGO_PONTO,
    MRD.DT_MEDICAO,
    MRD.ID_TIPO_MEDIDOR,
    MRD.QTD_TRATAMENTOS,
    MRD.DS_HORAS_TRATADAS,
    MRD.FL_ANOMALIA,
    MRD.DS_ANOMALIAS,
    MRD.VL_MEDIA_DIARIA,
    MRD.VL_MEDIA_HISTORICA,
    MRD.VL_DESVIO_HISTORICO

FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
WHERE MRD.ID_SITUACAO = 2
  AND MRD.DT_MEDICAO >= DATEADD(DAY, -30, CAST(GETDATE() AS DATE));
GO

-- ============================================================================
-- VIEW 6: VW_HORAS_PROBLEMATICAS
-- Horas com mais ocorrências de anomalias (padrão temporal)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_HORAS_PROBLEMATICAS')
    DROP VIEW [dbo].[VW_HORAS_PROBLEMATICAS];
GO

CREATE VIEW [dbo].[VW_HORAS_PROBLEMATICAS]
AS
SELECT 
    MRH.NR_HORA,
    FORMAT(DATEADD(HOUR, MRH.NR_HORA, 0), 'HH:00') AS HORA_FORMATADA,
    COUNT(*) AS TOTAL_REGISTROS,
    SUM(CASE WHEN MRH.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS TOTAL_ANOMALIAS,
    CAST(SUM(CASE WHEN MRH.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) AS DECIMAL(5,2)) AS PERC_ANOMALIA,
    SUM(CASE WHEN MRH.FL_TRATADO = 1 THEN 1 ELSE 0 END) AS TOTAL_TRATADOS,
    AVG(MRH.VL_MEDIA) AS MEDIA_GERAL,
    
    -- Tipos de anomalia mais comuns nesta hora
    (SELECT TOP 1 DS_TIPO_ANOMALIA 
     FROM [dbo].[MEDICAO_RESUMO_HORARIO] 
     WHERE NR_HORA = MRH.NR_HORA 
       AND FL_ANOMALIA = 1 
       AND DT_HORA >= DATEADD(DAY, -7, CAST(GETDATE() AS DATE))
     GROUP BY DS_TIPO_ANOMALIA 
     ORDER BY COUNT(*) DESC) AS ANOMALIA_MAIS_COMUM

FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
WHERE MRH.DT_HORA >= DATEADD(DAY, -7, CAST(GETDATE() AS DATE))
GROUP BY MRH.NR_HORA;
GO

-- ============================================================================
-- STORED PROCEDURE: SP_GERAR_CONTEXTO_IA
-- Gera um resumo textual otimizado para a IA analisar
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[SP_GERAR_CONTEXTO_IA]') AND type in (N'P'))
    DROP PROCEDURE [dbo].[SP_GERAR_CONTEXTO_IA];
GO

CREATE PROCEDURE [dbo].[SP_GERAR_CONTEXTO_IA]
    @DIAS_ANALISE INT = 7,
    @CD_PONTO_MEDICAO INT = NULL  -- NULL = todos os pontos
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @DT_INICIO DATE = DATEADD(DAY, -@DIAS_ANALISE, CAST(GETDATE() AS DATE));
    
    -- ====================================================================
    -- SEÇÃO 1: RESUMO GERAL
    -- ====================================================================
    SELECT '=== RESUMO GERAL DOS ÚLTIMOS ' + CAST(@DIAS_ANALISE AS VARCHAR) + ' DIAS ===' AS SECAO;
    
    SELECT 
        COUNT(DISTINCT CD_PONTO_MEDICAO) AS TOTAL_PONTOS,
        SUM(QTD_REGISTROS) AS TOTAL_MEDICOES,
        SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS DIAS_COM_ANOMALIA,
        SUM(CASE WHEN ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS DIAS_TRATADOS,
        SUM(QTD_TRATAMENTOS) AS TOTAL_TRATAMENTOS,
        CAST(AVG(CASE WHEN QTD_REGISTROS > 0 THEN QTD_REGISTROS * 100.0 / 1440 END) AS DECIMAL(5,2)) AS COMPLETUDE_MEDIA_PERC
    FROM [dbo].[MEDICAO_RESUMO_DIARIO]
    WHERE DT_MEDICAO >= @DT_INICIO
      AND (@CD_PONTO_MEDICAO IS NULL OR CD_PONTO_MEDICAO = @CD_PONTO_MEDICAO);
    
    -- ====================================================================
    -- SEÇÃO 2: TOP 10 PONTOS MAIS CRÍTICOS
    -- ====================================================================
    SELECT '=== TOP 10 PONTOS MAIS CRÍTICOS ===' AS SECAO;
    
    SELECT TOP 10
        MRD.CD_PONTO_MEDICAO,
        PM.DS_NOME AS NOME_PONTO,
        CASE MRD.ID_TIPO_MEDIDOR
            WHEN 1 THEN 'Macromedidor'
            WHEN 2 THEN 'Estação Pitométrica'
            WHEN 4 THEN 'Pressão'
            WHEN 6 THEN 'Nível Reservatório'
            WHEN 8 THEN 'Hidrômetro'
        END AS TIPO,
        SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS DIAS_ANOMALIA,
        SUM(CASE WHEN MRD.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS DIAS_TRATADOS,
        CAST(AVG(MRD.VL_DESVIO_HISTORICO) AS DECIMAL(10,2)) AS DESVIO_MEDIO_PERC,
        STRING_AGG(CAST(MRD.DS_ANOMALIAS AS VARCHAR(MAX)), '; ') AS ANOMALIAS_PERIODO
    FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
    LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
    WHERE MRD.DT_MEDICAO >= @DT_INICIO
      AND (@CD_PONTO_MEDICAO IS NULL OR MRD.CD_PONTO_MEDICAO = @CD_PONTO_MEDICAO)
      AND (MRD.FL_ANOMALIA = 1 OR MRD.ID_SITUACAO = 2)
    GROUP BY MRD.CD_PONTO_MEDICAO, PM.DS_NOME, MRD.ID_TIPO_MEDIDOR
    ORDER BY 
        SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) DESC,
        SUM(CASE WHEN MRD.ID_SITUACAO = 2 THEN 1 ELSE 0 END) DESC;
    
    -- ====================================================================
    -- SEÇÃO 3: TENDÊNCIAS PREOCUPANTES
    -- ====================================================================
    SELECT '=== PONTOS COM TENDÊNCIA PREOCUPANTE (DESVIO > 30%) ===' AS SECAO;
    
    SELECT 
        MRD.CD_PONTO_MEDICAO,
        PM.DS_NOME AS NOME_PONTO,
        CASE MRD.ID_TIPO_MEDIDOR
            WHEN 1 THEN 'Macromedidor (L/s)'
            WHEN 2 THEN 'Estação Pitométrica (L/s)'
            WHEN 4 THEN 'Pressão (mca)'
            WHEN 6 THEN 'Nível Reservatório (%)'
            WHEN 8 THEN 'Hidrômetro (L/s)'
        END AS TIPO,
        CAST(AVG(MRD.VL_MEDIA_DIARIA) AS DECIMAL(10,2)) AS MEDIA_ATUAL,
        CAST(AVG(MRD.VL_MEDIA_HISTORICA) AS DECIMAL(10,2)) AS MEDIA_HISTORICA,
        CAST(AVG(MRD.VL_DESVIO_HISTORICO) AS DECIMAL(10,2)) AS DESVIO_PERC,
        CASE 
            WHEN AVG(MRD.VL_DESVIO_HISTORICO) > 0 THEN 'ACIMA DO NORMAL'
            ELSE 'ABAIXO DO NORMAL'
        END AS SITUACAO
    FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
    LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
    WHERE MRD.DT_MEDICAO >= @DT_INICIO
      AND (@CD_PONTO_MEDICAO IS NULL OR MRD.CD_PONTO_MEDICAO = @CD_PONTO_MEDICAO)
      AND MRD.VL_MEDIA_HISTORICA > 0
    GROUP BY MRD.CD_PONTO_MEDICAO, PM.DS_NOME, MRD.ID_TIPO_MEDIDOR
    HAVING ABS(AVG(MRD.VL_DESVIO_HISTORICO)) > 30
    ORDER BY ABS(AVG(MRD.VL_DESVIO_HISTORICO)) DESC;
    
    -- ====================================================================
    -- SEÇÃO 4: TIPOS DE ANOMALIA MAIS FREQUENTES
    -- ====================================================================
    SELECT '=== TIPOS DE ANOMALIA MAIS FREQUENTES ===' AS SECAO;
    
    SELECT TOP 10
        TRIM(value) AS TIPO_ANOMALIA,
        COUNT(*) AS OCORRENCIAS
    FROM [dbo].[MEDICAO_RESUMO_DIARIO]
    CROSS APPLY STRING_SPLIT(DS_ANOMALIAS, ';')
    WHERE DT_MEDICAO >= @DT_INICIO
      AND (@CD_PONTO_MEDICAO IS NULL OR CD_PONTO_MEDICAO = @CD_PONTO_MEDICAO)
      AND DS_ANOMALIAS IS NOT NULL
      AND TRIM(value) <> ''
    GROUP BY TRIM(value)
    ORDER BY COUNT(*) DESC;
    
    -- ====================================================================
    -- SEÇÃO 5: HORAS COM MAIS PROBLEMAS
    -- ====================================================================
    SELECT '=== HORAS COM MAIS ANOMALIAS ===' AS SECAO;
    
    SELECT 
        NR_HORA,
        FORMAT(DATEADD(HOUR, NR_HORA, 0), 'HH:00') AS HORA,
        COUNT(*) AS TOTAL_REGISTROS,
        SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS ANOMALIAS,
        CAST(SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) AS DECIMAL(5,2)) AS PERC_ANOMALIA
    FROM [dbo].[MEDICAO_RESUMO_HORARIO]
    WHERE DT_HORA >= @DT_INICIO
      AND (@CD_PONTO_MEDICAO IS NULL OR CD_PONTO_MEDICAO = @CD_PONTO_MEDICAO)
    GROUP BY NR_HORA
    HAVING SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) > 0
    ORDER BY SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) DESC;
    
    -- ====================================================================
    -- SEÇÃO 6: PONTOS QUE MAIS RECEBEM TRATAMENTO
    -- ====================================================================
    SELECT '=== TOP 10 PONTOS COM MAIS TRATAMENTOS ===' AS SECAO;
    
    SELECT TOP 10
        MRD.CD_PONTO_MEDICAO,
        PM.DS_NOME AS NOME_PONTO,
        COUNT(*) AS DIAS_TRATADOS,
        SUM(MRD.QTD_TRATAMENTOS) AS TOTAL_TRATAMENTOS,
        STRING_AGG(MRD.DS_HORAS_TRATADAS, ' | ') AS HORAS_TRATADAS_PERIODO
    FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
    LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
    WHERE MRD.DT_MEDICAO >= @DT_INICIO
      AND MRD.ID_SITUACAO = 2
      AND (@CD_PONTO_MEDICAO IS NULL OR MRD.CD_PONTO_MEDICAO = @CD_PONTO_MEDICAO)
    GROUP BY MRD.CD_PONTO_MEDICAO, PM.DS_NOME
    ORDER BY SUM(MRD.QTD_TRATAMENTOS) DESC;
    
END
GO

-- ============================================================================
-- STORED PROCEDURE: SP_CONTEXTO_IA_TEXTO
-- Gera contexto em formato texto para prompt da IA
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[SP_CONTEXTO_IA_TEXTO]') AND type in (N'P'))
    DROP PROCEDURE [dbo].[SP_CONTEXTO_IA_TEXTO];
GO

CREATE PROCEDURE [dbo].[SP_CONTEXTO_IA_TEXTO]
    @DIAS_ANALISE INT = 7
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @DT_INICIO DATE = DATEADD(DAY, -@DIAS_ANALISE, CAST(GETDATE() AS DATE));
    DECLARE @CONTEXTO NVARCHAR(MAX) = '';
    
    -- Variáveis para resumo
    DECLARE @TOTAL_PONTOS INT, @TOTAL_MEDICOES INT, @DIAS_ANOMALIA INT, @DIAS_TRATADOS INT, @TOTAL_TRATAMENTOS INT;
    
    SELECT 
        @TOTAL_PONTOS = COUNT(DISTINCT CD_PONTO_MEDICAO),
        @TOTAL_MEDICOES = SUM(QTD_REGISTROS),
        @DIAS_ANOMALIA = SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END),
        @DIAS_TRATADOS = SUM(CASE WHEN ID_SITUACAO = 2 THEN 1 ELSE 0 END),
        @TOTAL_TRATAMENTOS = SUM(QTD_TRATAMENTOS)
    FROM [dbo].[MEDICAO_RESUMO_DIARIO]
    WHERE DT_MEDICAO >= @DT_INICIO;
    
    -- Montar contexto
    SET @CONTEXTO = '
=== CONTEXTO PARA ANÁLISE DE IA ===
Período: Últimos ' + CAST(@DIAS_ANALISE AS VARCHAR) + ' dias (desde ' + CONVERT(VARCHAR, @DT_INICIO, 103) + ')

>>> RESUMO GERAL <<<
- Total de pontos monitorados: ' + CAST(@TOTAL_PONTOS AS VARCHAR) + '
- Total de medições: ' + FORMAT(@TOTAL_MEDICOES, 'N0') + '
- Dias com anomalias detectadas: ' + CAST(@DIAS_ANOMALIA AS VARCHAR) + '
- Dias que necessitaram tratamento: ' + CAST(@DIAS_TRATADOS AS VARCHAR) + '
- Total de tratamentos realizados: ' + CAST(@TOTAL_TRATAMENTOS AS VARCHAR) + '

>>> PONTOS CRÍTICOS (com anomalias ou tratamentos) <<<
';

    -- Adicionar pontos críticos
    SELECT @CONTEXTO = @CONTEXTO + 
        '• ' + ISNULL(PM.DS_NOME, 'Ponto ' + CAST(MRD.CD_PONTO_MEDICAO AS VARCHAR)) + 
        ' (' + CASE MRD.ID_TIPO_MEDIDOR
            WHEN 1 THEN 'Macro'
            WHEN 2 THEN 'EP'
            WHEN 4 THEN 'Pressão'
            WHEN 6 THEN 'Nível'
            WHEN 8 THEN 'Hidro'
        END + ')' +
        ': ' + CAST(SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS VARCHAR) + ' dias c/ anomalia' +
        ', ' + CAST(SUM(MRD.QTD_TRATAMENTOS) AS VARCHAR) + ' tratamentos' +
        ', desvio médio: ' + CAST(CAST(AVG(MRD.VL_DESVIO_HISTORICO) AS DECIMAL(10,1)) AS VARCHAR) + '%' +
        CHAR(13) + CHAR(10)
    FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
    LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_CHAVE
    WHERE MRD.DT_MEDICAO >= @DT_INICIO
      AND (MRD.FL_ANOMALIA = 1 OR MRD.ID_SITUACAO = 2)
    GROUP BY MRD.CD_PONTO_MEDICAO, PM.DS_NOME, MRD.ID_TIPO_MEDIDOR
    ORDER BY SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) DESC;
    
    SET @CONTEXTO = @CONTEXTO + '
>>> ANOMALIAS MAIS FREQUENTES <<<
';

    -- Adicionar anomalias frequentes
    SELECT @CONTEXTO = @CONTEXTO + 
        '• ' + TRIM(value) + ': ' + CAST(COUNT(*) AS VARCHAR) + ' ocorrências' + CHAR(13) + CHAR(10)
    FROM [dbo].[MEDICAO_RESUMO_DIARIO]
    CROSS APPLY STRING_SPLIT(DS_ANOMALIAS, ';')
    WHERE DT_MEDICAO >= @DT_INICIO
      AND DS_ANOMALIAS IS NOT NULL
      AND TRIM(value) <> ''
    GROUP BY TRIM(value)
    ORDER BY COUNT(*) DESC;
    
    -- Retornar contexto
    SELECT @CONTEXTO AS CONTEXTO_IA;
    
END
GO

-- ============================================================================
-- QUERIES ÚTEIS PARA O DASHBOARD
-- ============================================================================

-- Query 1: Cards do dashboard (visão geral)
-- SELECT * FROM VW_DASHBOARD_RESUMO_GERAL;

-- Query 2: Lista de pontos críticos ordenados
-- SELECT * FROM VW_PONTOS_CRITICOS ORDER BY SCORE_CRITICIDADE DESC;

-- Query 3: Tendências para gráfico
-- SELECT * FROM VW_TENDENCIAS_PONTOS ORDER BY DESVIO_PERCENTUAL DESC;

-- Query 4: Anomalias recentes para lista/tabela
-- SELECT * FROM VW_ANOMALIAS_RECENTES ORDER BY DT_MEDICAO DESC;

-- Query 5: Padrão de horas problemáticas
-- SELECT * FROM VW_HORAS_PROBLEMATICAS ORDER BY PERC_ANOMALIA DESC;

-- Query 6: Gerar contexto para IA
-- EXEC SP_GERAR_CONTEXTO_IA @DIAS_ANALISE = 7;

-- Query 7: Gerar contexto em texto para prompt
-- EXEC SP_CONTEXTO_IA_TEXTO @DIAS_ANALISE = 7;

-- Query 8: Contexto específico de um ponto
-- EXEC SP_GERAR_CONTEXTO_IA @DIAS_ANALISE = 30, @CD_PONTO_MEDICAO = 123;

PRINT 'Views e procedures criadas com sucesso!';
GO