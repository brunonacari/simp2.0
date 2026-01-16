-- ============================================================================
-- SIMP - Scripts para Tabelas de Resumo de Medição
-- Objetivo: Agregar dados para análise por IA e Dashboard
-- ============================================================================

-- ============================================================================
-- TABELA 1: MEDICAO_RESUMO_DIARIO
-- Visão consolidada do dia (1 registro por ponto/dia)
-- ============================================================================

IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[MEDICAO_RESUMO_DIARIO]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[MEDICAO_RESUMO_DIARIO] (
        [CD_CHAVE]              INT IDENTITY(1,1) NOT NULL,
        [CD_PONTO_MEDICAO]      INT NOT NULL,
        [DT_MEDICAO]            DATE NOT NULL,
        [ID_TIPO_MEDIDOR]       INT NULL,
        
        -- Contagens
        [QTD_REGISTROS]         INT NULL DEFAULT 0,
        [QTD_ESPERADA]          INT NULL DEFAULT 1440,
        
        -- Estatísticas do dia
        [VL_MEDIA_DIARIA]       DECIMAL(18,4) NULL,
        [VL_MIN_DIARIO]         DECIMAL(18,4) NULL,
        [VL_MAX_DIARIO]         DECIMAL(18,4) NULL,
        [VL_DESVIO_PADRAO]      DECIMAL(18,4) NULL,
        
        -- Tratamentos
        [ID_SITUACAO]           INT NULL DEFAULT 1,  -- 1=Normal, 2=Tratado
        [QTD_TRATAMENTOS]       INT NULL DEFAULT 0,
        [DS_HORAS_TRATADAS]     VARCHAR(100) NULL,   -- Ex: "08,09,14,15"
        
        -- Anomalias
        [FL_ANOMALIA]           BIT NULL DEFAULT 0,
        [DS_ANOMALIAS]          VARCHAR(500) NULL,   -- Tipos de anomalia detectadas
        
        -- Comparação histórica
        [VL_MEDIA_HISTORICA]    DECIMAL(18,4) NULL,  -- Média últimas 4 semanas
        [VL_DESVIO_HISTORICO]   DECIMAL(18,4) NULL,  -- % desvio do histórico
        
        -- Controle
        [DT_PROCESSAMENTO]      DATETIME NULL DEFAULT GETDATE(),
        
        CONSTRAINT [PK_MEDICAO_RESUMO_DIARIO] PRIMARY KEY CLUSTERED ([CD_CHAVE] ASC),
        CONSTRAINT [UK_MEDICAO_RESUMO_DIARIO] UNIQUE ([CD_PONTO_MEDICAO], [DT_MEDICAO])
    );
    
    -- Índices para performance
    CREATE NONCLUSTERED INDEX [IX_RESUMO_DIARIO_DATA] ON [dbo].[MEDICAO_RESUMO_DIARIO] ([DT_MEDICAO]) INCLUDE ([CD_PONTO_MEDICAO], [FL_ANOMALIA]);
    CREATE NONCLUSTERED INDEX [IX_RESUMO_DIARIO_PONTO] ON [dbo].[MEDICAO_RESUMO_DIARIO] ([CD_PONTO_MEDICAO]) INCLUDE ([DT_MEDICAO], [ID_SITUACAO]);
    CREATE NONCLUSTERED INDEX [IX_RESUMO_DIARIO_ANOMALIA] ON [dbo].[MEDICAO_RESUMO_DIARIO] ([FL_ANOMALIA]) WHERE [FL_ANOMALIA] = 1;
    
    PRINT 'Tabela MEDICAO_RESUMO_DIARIO criada com sucesso.';
END
ELSE
BEGIN
    PRINT 'Tabela MEDICAO_RESUMO_DIARIO já existe.';
END
GO

-- ============================================================================
-- TABELA 2: MEDICAO_RESUMO_HORARIO
-- Detalhamento por hora (24 registros por ponto/dia)
-- ============================================================================

IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[MEDICAO_RESUMO_HORARIO]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[MEDICAO_RESUMO_HORARIO] (
        [CD_CHAVE]              INT IDENTITY(1,1) NOT NULL,
        [CD_PONTO_MEDICAO]      INT NOT NULL,
        [DT_HORA]               DATETIME NOT NULL,  -- Data + Hora truncada (ex: 2025-01-14 08:00:00)
        [NR_HORA]               TINYINT NOT NULL,   -- 0-23 para facilitar consultas
        [ID_TIPO_MEDIDOR]       INT NULL,
        
        -- Contagens
        [QTD_REGISTROS]         INT NULL DEFAULT 0,
        [QTD_ESPERADA]          INT NULL DEFAULT 60,
        [QTD_ZEROS]             INT NULL DEFAULT 0,  -- Registros com valor zero
        
        -- Estatísticas da hora
        [VL_MEDIA]              DECIMAL(18,4) NULL,
        [VL_MIN]                DECIMAL(18,4) NULL,
        [VL_MAX]                DECIMAL(18,4) NULL,
        [VL_SOMA]               DECIMAL(18,4) NULL,  -- Para recálculos
        
        -- Tratamento
        [FL_TRATADO]            BIT NULL DEFAULT 0,
        [QTD_TRATADOS]          INT NULL DEFAULT 0,
        
        -- Anomalias
        [FL_ANOMALIA]           BIT NULL DEFAULT 0,
        [DS_TIPO_ANOMALIA]      VARCHAR(200) NULL,
        
        -- Comparação histórica (mesma hora, mesmo dia da semana)
        [VL_MEDIA_HISTORICA]    DECIMAL(18,4) NULL,
        
        -- Controle
        [DT_PROCESSAMENTO]      DATETIME NULL DEFAULT GETDATE(),
        
        CONSTRAINT [PK_MEDICAO_RESUMO_HORARIO] PRIMARY KEY CLUSTERED ([CD_CHAVE] ASC),
        CONSTRAINT [UK_MEDICAO_RESUMO_HORARIO] UNIQUE ([CD_PONTO_MEDICAO], [DT_HORA])
    );
    
    -- Índices para performance
    CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_DATA] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([DT_HORA]) INCLUDE ([CD_PONTO_MEDICAO]);
    CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_PONTO] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([CD_PONTO_MEDICAO], [DT_HORA]);
    CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_ANOMALIA] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([FL_ANOMALIA]) WHERE [FL_ANOMALIA] = 1;
    
    PRINT 'Tabela MEDICAO_RESUMO_HORARIO criada com sucesso.';
END
ELSE
BEGIN
    PRINT 'Tabela MEDICAO_RESUMO_HORARIO já existe.';
END
GO

-- ============================================================================
-- STORED PROCEDURE: SP_PROCESSAR_MEDICAO_DIARIA
-- Processa dados do dia anterior e popula as tabelas de resumo
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[SP_PROCESSAR_MEDICAO_DIARIA]') AND type in (N'P'))
    DROP PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_DIARIA];
GO

CREATE PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_DIARIA]
    @DT_PROCESSAMENTO DATE = NULL  -- Se NULL, processa D-1
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Se não informada, processa o dia anterior
    IF @DT_PROCESSAMENTO IS NULL
        SET @DT_PROCESSAMENTO = CAST(DATEADD(DAY, -1, GETDATE()) AS DATE);
    
    DECLARE @DT_INICIO DATETIME = CAST(@DT_PROCESSAMENTO AS DATETIME);
    DECLARE @DT_FIM DATETIME = DATEADD(DAY, 1, @DT_INICIO);
    
    PRINT 'Iniciando processamento para: ' + CONVERT(VARCHAR, @DT_PROCESSAMENTO, 103);
    PRINT 'Período: ' + CONVERT(VARCHAR, @DT_INICIO, 120) + ' até ' + CONVERT(VARCHAR, @DT_FIM, 120);
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- ====================================================================
        -- ETAPA 1: Limpar dados existentes do dia (reprocessamento)
        -- ====================================================================
        DELETE FROM [dbo].[MEDICAO_RESUMO_HORARIO] 
        WHERE CAST([DT_HORA] AS DATE) = @DT_PROCESSAMENTO;
        
        DELETE FROM [dbo].[MEDICAO_RESUMO_DIARIO] 
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        
        PRINT 'Dados anteriores removidos.';
        
        -- ====================================================================
        -- ETAPA 2: Popular MEDICAO_RESUMO_HORARIO
        -- ====================================================================
        INSERT INTO [dbo].[MEDICAO_RESUMO_HORARIO] (
            [CD_PONTO_MEDICAO],
            [DT_HORA],
            [NR_HORA],
            [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS],
            [QTD_ZEROS],
            [VL_MEDIA],
            [VL_MIN],
            [VL_MAX],
            [VL_SOMA],
            [FL_TRATADO],
            [QTD_TRATADOS],
            [DT_PROCESSAMENTO]
        )
        SELECT 
            RVP.[CD_PONTO_MEDICAO],
            -- Trunca para hora cheia
            DATEADD(HOUR, DATEPART(HOUR, RVP.[DT_LEITURA]), CAST(CAST(RVP.[DT_LEITURA] AS DATE) AS DATETIME)) AS DT_HORA,
            DATEPART(HOUR, RVP.[DT_LEITURA]) AS NR_HORA,
            RVP.[ID_TIPO_MEDICAO] AS ID_TIPO_MEDIDOR,
            COUNT(*) AS QTD_REGISTROS,
            -- Conta zeros baseado no tipo de medidor
            SUM(CASE 
                WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) AND ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]) = 0 THEN 1
                WHEN RVP.[ID_TIPO_MEDICAO] = 4 AND RVP.[VL_PRESSAO] = 0 THEN 1
                WHEN RVP.[ID_TIPO_MEDICAO] = 6 AND RVP.[VL_RESERVATORIO] = 0 THEN 1
                ELSE 0
            END) AS QTD_ZEROS,
            -- Média baseada no tipo de medidor
            AVG(CASE 
                WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) THEN ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO])
                WHEN RVP.[ID_TIPO_MEDICAO] = 4 THEN RVP.[VL_PRESSAO]
                WHEN RVP.[ID_TIPO_MEDICAO] = 6 THEN RVP.[VL_RESERVATORIO]
            END) AS VL_MEDIA,
            -- Mínimo
            MIN(CASE 
                WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) THEN ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO])
                WHEN RVP.[ID_TIPO_MEDICAO] = 4 THEN RVP.[VL_PRESSAO]
                WHEN RVP.[ID_TIPO_MEDICAO] = 6 THEN RVP.[VL_RESERVATORIO]
            END) AS VL_MIN,
            -- Máximo
            MAX(CASE 
                WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) THEN ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO])
                WHEN RVP.[ID_TIPO_MEDICAO] = 4 THEN RVP.[VL_PRESSAO]
                WHEN RVP.[ID_TIPO_MEDICAO] = 6 THEN RVP.[VL_RESERVATORIO]
            END) AS VL_MAX,
            -- Soma para recálculos
            SUM(CASE 
                WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) THEN ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO])
                WHEN RVP.[ID_TIPO_MEDICAO] = 4 THEN RVP.[VL_PRESSAO]
                WHEN RVP.[ID_TIPO_MEDICAO] = 6 THEN RVP.[VL_RESERVATORIO]
                ELSE 0
            END) AS VL_SOMA,
            -- Flag de tratamento
            MAX(CASE WHEN RVP.[ID_SITUACAO] = 2 THEN 1 ELSE 0 END) AS FL_TRATADO,
            -- Quantidade de tratados
            SUM(CASE WHEN RVP.[ID_SITUACAO] = 2 THEN 1 ELSE 0 END) AS QTD_TRATADOS,
            GETDATE()
        FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
        WHERE RVP.[DT_LEITURA] >= @DT_INICIO 
          AND RVP.[DT_LEITURA] < @DT_FIM
          AND RVP.[ID_SITUACAO] IN (1, 2)  -- Apenas ativos e tratados
        GROUP BY 
            RVP.[CD_PONTO_MEDICAO],
            DATEADD(HOUR, DATEPART(HOUR, RVP.[DT_LEITURA]), CAST(CAST(RVP.[DT_LEITURA] AS DATE) AS DATETIME)),
            DATEPART(HOUR, RVP.[DT_LEITURA]),
            RVP.[ID_TIPO_MEDICAO];
        
        PRINT 'MEDICAO_RESUMO_HORARIO populado: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' registros.';
        
        -- ====================================================================
        -- ETAPA 3: Calcular média histórica por hora (últimas 4 semanas, mesmo dia)
        -- ====================================================================
        UPDATE MRH
        SET [VL_MEDIA_HISTORICA] = HIST.VL_MEDIA_HIST
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        INNER JOIN (
            SELECT 
                RVP.[CD_PONTO_MEDICAO],
                DATEPART(HOUR, RVP.[DT_LEITURA]) AS NR_HORA,
                AVG(CASE 
                    WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) THEN ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO])
                    WHEN RVP.[ID_TIPO_MEDICAO] = 4 THEN RVP.[VL_PRESSAO]
                    WHEN RVP.[ID_TIPO_MEDICAO] = 6 THEN RVP.[VL_RESERVATORIO]
                END) AS VL_MEDIA_HIST
            FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
            WHERE RVP.[DT_LEITURA] >= DATEADD(WEEK, -4, @DT_INICIO)
              AND RVP.[DT_LEITURA] < @DT_INICIO
              AND DATEPART(WEEKDAY, RVP.[DT_LEITURA]) = DATEPART(WEEKDAY, @DT_PROCESSAMENTO)
              AND RVP.[ID_SITUACAO] IN (1, 2)
            GROUP BY RVP.[CD_PONTO_MEDICAO], DATEPART(HOUR, RVP.[DT_LEITURA])
        ) HIST ON MRH.[CD_PONTO_MEDICAO] = HIST.[CD_PONTO_MEDICAO] 
              AND MRH.[NR_HORA] = HIST.[NR_HORA]
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO;
        
        PRINT 'Média histórica horária calculada.';
        
        -- ====================================================================
        -- ETAPA 4: Detectar anomalias por hora
        -- ====================================================================
        
        -- 4.1 VAZÃO (tipos 1, 2, 8): Vazão zerada > 30 min em horário comercial
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'VAZAO_ZERADA_' + CAST([QTD_ZEROS] AS VARCHAR) + 'MIN'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] IN (1, 2, 8)
          AND MRH.[QTD_ZEROS] >= 30
          AND MRH.[NR_HORA] BETWEEN 6 AND 22;
        
        -- 4.2 VAZÃO: Vazão negativa
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'VAZAO_NEGATIVA'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] IN (1, 2, 8)
          AND MRH.[VL_MIN] < 0;
        
        -- 4.3 VAZÃO: Pico muito alto (máx > 2x média)
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'PICO_VAZAO'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] IN (1, 2, 8)
          AND MRH.[VL_MEDIA] > 0
          AND MRH.[VL_MAX] > (MRH.[VL_MEDIA] * 2);
        
        -- 4.4 VAZÃO: Desvio histórico > 50%
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'DESVIO_HISTORICO_' + 
                CAST(CAST(ABS((MRH.[VL_MEDIA] - MRH.[VL_MEDIA_HISTORICA]) / MRH.[VL_MEDIA_HISTORICA] * 100) AS INT) AS VARCHAR) + '%'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] IN (1, 2, 8)
          AND MRH.[VL_MEDIA_HISTORICA] > 0
          AND ABS((MRH.[VL_MEDIA] - MRH.[VL_MEDIA_HISTORICA]) / MRH.[VL_MEDIA_HISTORICA]) > 0.5;
        
        -- 4.5 PRESSÃO (tipo 4): Pressão baixa < 10 mca
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'PRESSAO_BAIXA_' + CAST(CAST([VL_MEDIA] AS INT) AS VARCHAR) + 'MCA'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] = 4
          AND MRH.[VL_MEDIA] > 0
          AND MRH.[VL_MEDIA] < 10;
        
        -- 4.6 PRESSÃO: Pressão zerada > 30 min
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'PRESSAO_ZERADA_' + CAST([QTD_ZEROS] AS VARCHAR) + 'MIN'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] = 4
          AND MRH.[QTD_ZEROS] >= 30;
        
        -- 4.7 PRESSÃO: Pressão alta > 60 mca
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'PRESSAO_ALTA_' + CAST(CAST([VL_MAX] AS INT) AS VARCHAR) + 'MCA'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] = 4
          AND MRH.[VL_MAX] > 60;
        
        -- 4.8 PRESSÃO: Variação brusca > 20 mca
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'VARIACAO_PRESSAO_' + CAST(CAST([VL_MAX] - [VL_MIN] AS INT) AS VARCHAR) + 'MCA'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] = 4
          AND ([VL_MAX] - [VL_MIN]) > 20;
        
        -- 4.9 NÍVEL RESERVATÓRIO (tipo 6): Nível >= 100% por > 30 min
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'NIVEL_100_EXTRAVASAMENTO'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] = 6
          AND MRH.[VL_MAX] >= 100
          AND (MRH.[QTD_REGISTROS] - MRH.[QTD_ZEROS]) >= 30;  -- Mais de 30 min com leitura alta
        
        -- 4.10 Registros incompletos (< 50 registros na hora)
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'REGISTROS_INCOMPLETOS_' + CAST([QTD_REGISTROS] AS VARCHAR) + '/60'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[QTD_REGISTROS] < 50;
        
        PRINT 'Anomalias horárias detectadas.';
        
        -- ====================================================================
        -- ETAPA 5: Popular MEDICAO_RESUMO_DIARIO (agregação do dia)
        -- ====================================================================
        INSERT INTO [dbo].[MEDICAO_RESUMO_DIARIO] (
            [CD_PONTO_MEDICAO],
            [DT_MEDICAO],
            [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS],
            [VL_MEDIA_DIARIA],
            [VL_MIN_DIARIO],
            [VL_MAX_DIARIO],
            [VL_DESVIO_PADRAO],
            [ID_SITUACAO],
            [QTD_TRATAMENTOS],
            [DS_HORAS_TRATADAS],
            [FL_ANOMALIA],
            [DS_ANOMALIAS],
            [DT_PROCESSAMENTO]
        )
        SELECT 
            MRH.[CD_PONTO_MEDICAO],
            @DT_PROCESSAMENTO AS DT_MEDICAO,
            MRH.[ID_TIPO_MEDIDOR],
            SUM(MRH.[QTD_REGISTROS]) AS QTD_REGISTROS,
            -- Média diária = soma total / total de registros
            CASE 
                WHEN SUM(MRH.[QTD_REGISTROS]) > 0 
                THEN SUM(MRH.[VL_SOMA]) / SUM(MRH.[QTD_REGISTROS])
                ELSE 0 
            END AS VL_MEDIA_DIARIA,
            MIN(MRH.[VL_MIN]) AS VL_MIN_DIARIO,
            MAX(MRH.[VL_MAX]) AS VL_MAX_DIARIO,
            STDEV(MRH.[VL_MEDIA]) AS VL_DESVIO_PADRAO,
            -- ID_SITUACAO: 2 se houve tratamento, 1 caso contrário
            CASE WHEN MAX(CAST(MRH.[FL_TRATADO] AS INT)) = 1 THEN 2 ELSE 1 END AS ID_SITUACAO,
            SUM(MRH.[QTD_TRATADOS]) AS QTD_TRATAMENTOS,
            -- Horas tratadas concatenadas
            STUFF((
                SELECT ',' + CAST(MRH2.[NR_HORA] AS VARCHAR)
                FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH2
                WHERE MRH2.[CD_PONTO_MEDICAO] = MRH.[CD_PONTO_MEDICAO]
                  AND CAST(MRH2.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
                  AND MRH2.[FL_TRATADO] = 1
                ORDER BY MRH2.[NR_HORA]
                FOR XML PATH('')
            ), 1, 1, '') AS DS_HORAS_TRATADAS,
            -- Flag de anomalia (se qualquer hora teve)
            MAX(CAST(MRH.[FL_ANOMALIA] AS INT)) AS FL_ANOMALIA,
            -- Anomalias concatenadas (únicas)
            STUFF((
                SELECT DISTINCT '; ' + MRH2.[DS_TIPO_ANOMALIA]
                FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH2
                WHERE MRH2.[CD_PONTO_MEDICAO] = MRH.[CD_PONTO_MEDICAO]
                  AND CAST(MRH2.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
                  AND MRH2.[DS_TIPO_ANOMALIA] IS NOT NULL
                FOR XML PATH('')
            ), 1, 2, '') AS DS_ANOMALIAS,
            GETDATE()
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
        GROUP BY MRH.[CD_PONTO_MEDICAO], MRH.[ID_TIPO_MEDIDOR];
        
        PRINT 'MEDICAO_RESUMO_DIARIO populado: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' registros.';
        
        -- ====================================================================
        -- ETAPA 6: Calcular média histórica diária (últimas 4 semanas)
        -- ====================================================================
        UPDATE MRD
        SET [VL_MEDIA_HISTORICA] = HIST.VL_MEDIA_HIST,
            [VL_DESVIO_HISTORICO] = CASE 
                WHEN HIST.VL_MEDIA_HIST > 0 
                THEN ((MRD.[VL_MEDIA_DIARIA] - HIST.VL_MEDIA_HIST) / HIST.VL_MEDIA_HIST) * 100
                ELSE 0 
            END
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        INNER JOIN (
            SELECT 
                [CD_PONTO_MEDICAO],
                AVG([VL_MEDIA_DIARIA]) AS VL_MEDIA_HIST
            FROM [dbo].[MEDICAO_RESUMO_DIARIO]
            WHERE [DT_MEDICAO] >= DATEADD(WEEK, -4, @DT_PROCESSAMENTO)
              AND [DT_MEDICAO] < @DT_PROCESSAMENTO
              AND DATEPART(WEEKDAY, [DT_MEDICAO]) = DATEPART(WEEKDAY, @DT_PROCESSAMENTO)
            GROUP BY [CD_PONTO_MEDICAO]
        ) HIST ON MRD.[CD_PONTO_MEDICAO] = HIST.[CD_PONTO_MEDICAO]
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO;
        
        PRINT 'Média histórica diária calculada.';
        
        COMMIT TRANSACTION;
        
        -- ====================================================================
        -- RESUMO DO PROCESSAMENTO
        -- ====================================================================
        PRINT '============================================';
        PRINT 'PROCESSAMENTO CONCLUÍDO COM SUCESSO';
        PRINT '============================================';
        
        SELECT 
            'RESUMO' AS TIPO,
            COUNT(*) AS TOTAL_PONTOS,
            SUM(CASE WHEN [FL_ANOMALIA] = 1 THEN 1 ELSE 0 END) AS PONTOS_COM_ANOMALIA,
            SUM(CASE WHEN [ID_SITUACAO] = 2 THEN 1 ELSE 0 END) AS PONTOS_TRATADOS,
            SUM([QTD_REGISTROS]) AS TOTAL_REGISTROS
        FROM [dbo].[MEDICAO_RESUMO_DIARIO]
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
        
        PRINT 'ERRO NO PROCESSAMENTO:';
        PRINT ERROR_MESSAGE();
        
        THROW;
    END CATCH
END
GO

-- ============================================================================
-- EXEMPLO DE EXECUÇÃO
-- ============================================================================
-- Processar dia anterior (padrão)
-- EXEC [dbo].[SP_PROCESSAR_MEDICAO_DIARIA];

-- Processar data específica
-- EXEC [dbo].[SP_PROCESSAR_MEDICAO_DIARIA] @DT_PROCESSAMENTO = '2025-01-14';

-- Reprocessar período (loop)
-- DECLARE @DATA DATE = '2025-01-01';
-- WHILE @DATA <= '2025-01-14'
-- BEGIN
--     EXEC [dbo].[SP_PROCESSAR_MEDICAO_DIARIA] @DT_PROCESSAMENTO = @DATA;
--     SET @DATA = DATEADD(DAY, 1, @DATA);
-- END

PRINT 'Scripts criados com sucesso!';
GO