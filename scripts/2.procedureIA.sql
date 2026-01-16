-- ============================================================================
-- SIMP - SP_PROCESSAR_MEDICAO_DIARIA (VERSÃO BLINDADA)
-- Garantia de execução mesmo com cadastros vazios
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[SP_PROCESSAR_MEDICAO_DIARIA]') AND type in (N'P'))
    DROP PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_DIARIA];
GO

CREATE PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_DIARIA]
    @DT_PROCESSAMENTO DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @DT_PROCESSAMENTO IS NULL
        SET @DT_PROCESSAMENTO = CAST(DATEADD(DAY, -1, GETDATE()) AS DATE);
    
    DECLARE @DT_INICIO DATETIME = CAST(@DT_PROCESSAMENTO AS DATETIME);
    DECLARE @DT_FIM DATETIME = DATEADD(DAY, 1, @DT_INICIO);
    
    PRINT '============================================';
    PRINT 'PROCESSAMENTO DE MEDICAO - VERSAO BLINDADA';
    PRINT '============================================';
    PRINT 'Data: ' + CONVERT(VARCHAR, @DT_PROCESSAMENTO, 103);
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- ====================================================================
        -- ETAPA 0: Limpar dados existentes
        -- ====================================================================
        DELETE FROM [dbo].[MEDICAO_RESUMO_HORARIO] WHERE CAST([DT_HORA] AS DATE) = @DT_PROCESSAMENTO;
        DELETE FROM [dbo].[MEDICAO_RESUMO_DIARIO] WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        PRINT 'Etapa 0: Dados anteriores removidos.';
        
        -- ====================================================================
        -- ETAPA 1: Popular MEDICAO_RESUMO_HORARIO
        -- ====================================================================
        INSERT INTO [dbo].[MEDICAO_RESUMO_HORARIO] (
            [CD_PONTO_MEDICAO], [DT_HORA], [NR_HORA], [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS], [QTD_ZEROS], [QTD_VALORES_DISTINTOS],
            [VL_MEDIA], [VL_MIN], [VL_MAX], [VL_SOMA],
            [FL_TRATADO], [QTD_TRATADOS], [DT_PROCESSAMENTO]
        )
        SELECT 
            RVP.[CD_PONTO_MEDICAO],
            DATEADD(HOUR, DATEPART(HOUR, RVP.[DT_LEITURA]), CAST(CAST(RVP.[DT_LEITURA] AS DATE) AS DATETIME)),
            DATEPART(HOUR, RVP.[DT_LEITURA]),
            ISNULL(RVP.[ID_TIPO_MEDICAO], 0),  -- Nunca NULL
            COUNT(*),
            
            -- Conta zeros (protegido contra NULL)
            SUM(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) 
                     AND ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0) = 0 THEN 1
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 
                     AND ISNULL(RVP.[VL_PRESSAO], 0) = 0 THEN 1
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 
                     AND ISNULL(RVP.[VL_RESERVATORIO], 0) = 0 THEN 1
                ELSE 0
            END),
            
            -- Valores distintos (protegido)
            COUNT(DISTINCT CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) 
                     THEN CAST(ROUND(ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0), 2) AS VARCHAR)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 
                     THEN CAST(ROUND(ISNULL(RVP.[VL_PRESSAO], 0), 2) AS VARCHAR)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 
                     THEN CAST(ROUND(ISNULL(RVP.[VL_RESERVATORIO], 0), 2) AS VARCHAR)
                ELSE '0'
            END),
            
            -- Média (protegido)
            AVG(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                ELSE 0
            END),
            
            -- Min (protegido)
            MIN(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                ELSE 0
            END),
            
            -- Max (protegido)
            MAX(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                ELSE 0
            END),
            
            -- Soma (protegido)
            SUM(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                ELSE 0
            END),
            
            MAX(CASE WHEN ISNULL(RVP.[ID_SITUACAO], 1) = 2 THEN 1 ELSE 0 END),
            SUM(CASE WHEN ISNULL(RVP.[ID_SITUACAO], 1) = 2 THEN 1 ELSE 0 END),
            GETDATE()
            
        FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
        WHERE RVP.[DT_LEITURA] >= @DT_INICIO 
          AND RVP.[DT_LEITURA] < @DT_FIM
          AND ISNULL(RVP.[ID_SITUACAO], 1) IN (1, 2)
        GROUP BY 
            RVP.[CD_PONTO_MEDICAO],
            DATEADD(HOUR, DATEPART(HOUR, RVP.[DT_LEITURA]), CAST(CAST(RVP.[DT_LEITURA] AS DATE) AS DATETIME)),
            DATEPART(HOUR, RVP.[DT_LEITURA]),
            ISNULL(RVP.[ID_TIPO_MEDICAO], 0);
        
        DECLARE @QTD_HORARIO INT = @@ROWCOUNT;
        PRINT 'Etapa 1: MEDICAO_RESUMO_HORARIO - ' + CAST(@QTD_HORARIO AS VARCHAR) + ' registros.';
        
        -- ====================================================================
        -- ETAPA 2: Calcular variações (spikes)
        -- ====================================================================
        ;WITH VariacaoHoraria AS (
            SELECT 
                RVP.CD_PONTO_MEDICAO,
                DATEADD(HOUR, DATEPART(HOUR, RVP.DT_LEITURA), CAST(CAST(RVP.DT_LEITURA AS DATE) AS DATETIME)) AS DT_HORA,
                CASE 
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0)
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) = 4 THEN ISNULL(RVP.VL_PRESSAO, 0)
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) = 6 THEN ISNULL(RVP.VL_RESERVATORIO, 0)
                    ELSE 0
                END AS VALOR,
                LAG(CASE 
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0)
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) = 4 THEN ISNULL(RVP.VL_PRESSAO, 0)
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) = 6 THEN ISNULL(RVP.VL_RESERVATORIO, 0)
                    ELSE 0
                END) OVER (PARTITION BY RVP.CD_PONTO_MEDICAO ORDER BY RVP.DT_LEITURA) AS VALOR_ANT
            FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
            WHERE RVP.DT_LEITURA >= @DT_INICIO 
              AND RVP.DT_LEITURA < @DT_FIM
              AND ISNULL(RVP.ID_SITUACAO, 1) IN (1, 2)
        ),
        VariacaoCalc AS (
            SELECT 
                CD_PONTO_MEDICAO,
                DT_HORA,
                MAX(ABS(ISNULL(VALOR, 0) - ISNULL(VALOR_ANT, 0))) AS VL_VARIACAO_MAX,
                MAX(CASE 
                    WHEN ISNULL(VALOR_ANT, 0) > 0.001  -- Evita divisão por zero
                    THEN ABS((ISNULL(VALOR, 0) - ISNULL(VALOR_ANT, 0)) / VALOR_ANT) * 100 
                    ELSE 0 
                END) AS VL_VARIACAO_PERC_MAX
            FROM VariacaoHoraria
            WHERE VALOR_ANT IS NOT NULL
            GROUP BY CD_PONTO_MEDICAO, DT_HORA
        )
        UPDATE MRH
        SET VL_VARIACAO_MAX = ISNULL(VC.VL_VARIACAO_MAX, 0),
            VL_VARIACAO_PERC_MAX = ISNULL(VC.VL_VARIACAO_PERC_MAX, 0)
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        INNER JOIN VariacaoCalc VC ON MRH.CD_PONTO_MEDICAO = VC.CD_PONTO_MEDICAO 
            AND MRH.DT_HORA = VC.DT_HORA;
        
        PRINT 'Etapa 2: Variacoes calculadas.';
        
        -- ====================================================================
        -- ETAPA 3: Média histórica por hora
        -- ====================================================================
        UPDATE MRH
        SET [VL_MEDIA_HISTORICA] = ISNULL(HIST.VL_MEDIA_HIST, 0)
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        INNER JOIN (
            SELECT 
                RVP.[CD_PONTO_MEDICAO],
                DATEPART(HOUR, RVP.[DT_LEITURA]) AS NR_HORA,
                AVG(CASE 
                    WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                    WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                    WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                    ELSE 0
                END) AS VL_MEDIA_HIST
            FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
            WHERE RVP.[DT_LEITURA] >= DATEADD(WEEK, -4, @DT_INICIO)
              AND RVP.[DT_LEITURA] < @DT_INICIO
              AND DATEPART(WEEKDAY, RVP.[DT_LEITURA]) = DATEPART(WEEKDAY, @DT_PROCESSAMENTO)
              AND ISNULL(RVP.[ID_SITUACAO], 1) IN (1, 2)
            GROUP BY RVP.[CD_PONTO_MEDICAO], DATEPART(HOUR, RVP.[DT_LEITURA])
        ) HIST ON MRH.[CD_PONTO_MEDICAO] = HIST.[CD_PONTO_MEDICAO] 
              AND MRH.[NR_HORA] = HIST.[NR_HORA]
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 3: Media historica calculada.';
        
        -- ====================================================================
        -- ETAPA 4: DETECTAR ANOMALIAS HORÁRIAS (BLINDADO)
        -- ====================================================================
        
        -- 4.1 VALOR CONSTANTE
        UPDATE MRH
        SET [FL_VALOR_CONSTANTE] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'VALOR_CONSTANTE'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[QTD_VALORES_DISTINTOS], 0) = 1
          AND ISNULL(MRH.[QTD_REGISTROS], 0) >= 50
          AND ISNULL(MRH.[VL_MEDIA], 0) <> 0;
        
        -- 4.2 VALOR NEGATIVO
        UPDATE MRH
        SET [FL_VALOR_NEGATIVO] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                'VALOR_NEGATIVO_' + CAST(CAST(ISNULL([VL_MIN], 0) AS DECIMAL(10,2)) AS VARCHAR)
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[VL_MIN], 0) < 0;
        
        -- 4.3 FORA DA FAIXA (usando limites da view - já com fallback)
        -- Só executa se a view existir e tiver dados
        IF EXISTS (SELECT 1 FROM sys.views WHERE name = 'VW_PONTO_MEDICAO_LIMITES')
        BEGIN
            UPDATE MRH
            SET [FL_FORA_FAIXA] = 1,
                [FL_ANOMALIA] = 1,
                [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                    CASE 
                        WHEN ISNULL(MRH.[VL_MAX], 0) > ISNULL(PML.VL_LIMITE_SUPERIOR, 999999)
                        THEN 'ACIMA_LIMITE_' + CAST(CAST(ISNULL(MRH.[VL_MAX], 0) AS DECIMAL(10,2)) AS VARCHAR) +
                             '_MAX_' + CAST(CAST(ISNULL(PML.VL_LIMITE_SUPERIOR, 0) AS DECIMAL(10,2)) AS VARCHAR)
                        WHEN ISNULL(MRH.[VL_MIN], 0) < ISNULL(PML.VL_LIMITE_INFERIOR, -999999) 
                             AND ISNULL(MRH.[VL_MIN], 0) <> 0
                        THEN 'ABAIXO_LIMITE_' + CAST(CAST(ISNULL(MRH.[VL_MIN], 0) AS DECIMAL(10,2)) AS VARCHAR)
                        ELSE 'FORA_FAIXA'
                    END
            FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
            INNER JOIN [dbo].[VW_PONTO_MEDICAO_LIMITES] PML 
                ON MRH.[CD_PONTO_MEDICAO] = PML.[CD_PONTO_MEDICAO]
            WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
              AND ISNULL(MRH.[ID_TIPO_MEDIDOR], 0) IN (1, 2, 8)
              AND (
                  -- Acima do limite (só se limite existir e for > 0)
                  (ISNULL(PML.VL_LIMITE_SUPERIOR, 0) > 0 AND ISNULL(MRH.[VL_MAX], 0) > PML.VL_LIMITE_SUPERIOR)
                  OR
                  -- Abaixo do limite (só se limite existir)
                  (PML.VL_LIMITE_INFERIOR IS NOT NULL AND ISNULL(MRH.[VL_MIN], 0) < PML.VL_LIMITE_INFERIOR AND ISNULL(MRH.[VL_MIN], 0) <> 0)
              );
            
            PRINT 'Etapa 4.3: Fora da faixa detectado (com limites da view).';
        END
        ELSE
        BEGIN
            PRINT 'Etapa 4.3: View VW_PONTO_MEDICAO_LIMITES nao existe - pulando validacao de faixa.';
        END
        
        -- 4.4 SPIKE (variação > 200% - valor fixo como fallback)
        UPDATE MRH
        SET [FL_SPIKE] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                'SPIKE_' + CAST(CAST(ISNULL([VL_VARIACAO_PERC_MAX], 0) AS INT) AS VARCHAR) + '%'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[VL_VARIACAO_PERC_MAX], 0) > 200;
        
        -- 4.5 ZEROS SUSPEITOS
        UPDATE MRH
        SET [FL_ZEROS_SUSPEITOS] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                'ZEROS_SUSPEITOS_' + CAST(ISNULL([QTD_ZEROS], 0) AS VARCHAR) + 'MIN'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[QTD_ZEROS], 0) >= 30
          AND ISNULL(MRH.[VL_MEDIA_HISTORICA], 0) > 0.001
          AND MRH.[NR_HORA] BETWEEN 6 AND 22;
        
        -- 4.6 REGISTROS INCOMPLETOS
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                'INCOMPLETO_' + CAST(ISNULL([QTD_REGISTROS], 0) AS VARCHAR) + '/60'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[QTD_REGISTROS], 0) < 50;
        
        -- 4.7 PRESSÃO específicas
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                CASE 
                    WHEN ISNULL(MRH.[VL_MEDIA], 0) > 0 AND ISNULL(MRH.[VL_MEDIA], 0) < 10 
                        THEN 'PRESSAO_BAIXA_' + CAST(CAST(ISNULL([VL_MEDIA], 0) AS INT) AS VARCHAR) + 'MCA'
                    WHEN ISNULL(MRH.[VL_MAX], 0) > 60 
                        THEN 'PRESSAO_ALTA_' + CAST(CAST(ISNULL([VL_MAX], 0) AS INT) AS VARCHAR) + 'MCA'
                    WHEN (ISNULL(MRH.[VL_MAX], 0) - ISNULL(MRH.[VL_MIN], 0)) > 20 
                        THEN 'VARIACAO_PRESSAO_' + CAST(CAST(ISNULL([VL_MAX], 0) - ISNULL([VL_MIN], 0) AS INT) AS VARCHAR) + 'MCA'
                    ELSE 'PRESSAO_ANOMALA'
                END
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[ID_TIPO_MEDIDOR], 0) = 4
          AND (
              (ISNULL(MRH.[VL_MEDIA], 0) > 0 AND ISNULL(MRH.[VL_MEDIA], 0) < 10)
              OR ISNULL(MRH.[VL_MAX], 0) > 60
              OR (ISNULL(MRH.[VL_MAX], 0) - ISNULL(MRH.[VL_MIN], 0)) > 20
          );
        
        -- 4.8 NÍVEL >= 100%
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'NIVEL_100_EXTRAVASAMENTO'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[ID_TIPO_MEDIDOR], 0) = 6
          AND ISNULL(MRH.[VL_MAX], 0) >= 100;
        
        PRINT 'Etapa 4: Anomalias horarias detectadas.';
        
        -- ====================================================================
        -- ETAPA 5: Popular MEDICAO_RESUMO_DIARIO
        -- ====================================================================
        INSERT INTO [dbo].[MEDICAO_RESUMO_DIARIO] (
            [CD_PONTO_MEDICAO], [DT_MEDICAO], [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS], [QTD_ESPERADA],
            [VL_MEDIA_DIARIA], [VL_MIN_DIARIO], [VL_MAX_DIARIO], 
            [VL_DESVIO_PADRAO], [VL_SOMA_DIARIA],
            [QTD_VALORES_DISTINTOS], [QTD_ZEROS], [QTD_HORAS_SEM_DADO],
            [ID_SITUACAO], [QTD_TRATAMENTOS], [DS_HORAS_TRATADAS],
            [DT_PROCESSAMENTO]
        )
        SELECT 
            MRH.[CD_PONTO_MEDICAO],
            @DT_PROCESSAMENTO,
            ISNULL(MRH.[ID_TIPO_MEDIDOR], 0),
            ISNULL(SUM(ISNULL(MRH.[QTD_REGISTROS], 0)), 0),
            1440,
            CASE 
                WHEN ISNULL(SUM(ISNULL(MRH.[QTD_REGISTROS], 0)), 0) > 0 
                THEN ISNULL(SUM(ISNULL(MRH.[VL_SOMA], 0)), 0) / SUM(ISNULL(MRH.[QTD_REGISTROS], 0))
                ELSE 0 
            END,
            ISNULL(MIN(ISNULL(MRH.[VL_MIN], 0)), 0),
            ISNULL(MAX(ISNULL(MRH.[VL_MAX], 0)), 0),
            ISNULL(STDEV(ISNULL(MRH.[VL_MEDIA], 0)), 0),
            ISNULL(SUM(ISNULL(MRH.[VL_SOMA], 0)), 0),
            ISNULL(SUM(ISNULL(MRH.[QTD_VALORES_DISTINTOS], 0)), 0),
            ISNULL(SUM(ISNULL(MRH.[QTD_ZEROS], 0)), 0),
            24 - COUNT(*),
            CASE WHEN ISNULL(MAX(CAST(ISNULL(MRH.[FL_TRATADO], 0) AS INT)), 0) = 1 THEN 2 ELSE 1 END,
            ISNULL(SUM(ISNULL(MRH.[QTD_TRATADOS], 0)), 0),
            STUFF((
                SELECT ',' + CAST(MRH2.[NR_HORA] AS VARCHAR)
                FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH2
                WHERE MRH2.[CD_PONTO_MEDICAO] = MRH.[CD_PONTO_MEDICAO]
                  AND CAST(MRH2.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
                  AND ISNULL(MRH2.[FL_TRATADO], 0) = 1
                ORDER BY MRH2.[NR_HORA]
                FOR XML PATH('')
            ), 1, 1, ''),
            GETDATE()
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
        GROUP BY MRH.[CD_PONTO_MEDICAO], ISNULL(MRH.[ID_TIPO_MEDIDOR], 0);
        
        DECLARE @QTD_DIARIO INT = @@ROWCOUNT;
        PRINT 'Etapa 5: MEDICAO_RESUMO_DIARIO - ' + CAST(@QTD_DIARIO AS VARCHAR) + ' registros.';
        
        -- ====================================================================
        -- ETAPA 6: Atualizar limites da view (se existir)
        -- ====================================================================
        IF EXISTS (SELECT 1 FROM sys.views WHERE name = 'VW_PONTO_MEDICAO_LIMITES')
        BEGIN
            UPDATE MRD
            SET [VL_LIMITE_INFERIOR] = ISNULL(PML.VL_LIMITE_INFERIOR, 0),
                [VL_LIMITE_SUPERIOR] = ISNULL(PML.VL_LIMITE_SUPERIOR, 999999),
                [VL_CAPACIDADE_NOMINAL] = PML.VL_CAPACIDADE_NOMINAL
            FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
            INNER JOIN [dbo].[VW_PONTO_MEDICAO_LIMITES] PML 
                ON MRD.[CD_PONTO_MEDICAO] = PML.[CD_PONTO_MEDICAO]
            WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO;
            
            PRINT 'Etapa 6: Limites atualizados da view.';
        END
        ELSE
        BEGIN
            PRINT 'Etapa 6: View de limites nao existe - usando valores default.';
        END
        
        -- ====================================================================
        -- ETAPA 7: Média histórica diária
        -- ====================================================================
        UPDATE MRD
        SET [VL_MEDIA_HISTORICA] = ISNULL(HIST.VL_MEDIA_HIST, 0),
            [VL_DESVIO_HISTORICO] = CASE 
                WHEN ISNULL(HIST.VL_MEDIA_HIST, 0) > 0.001
                THEN ((ISNULL(MRD.[VL_MEDIA_DIARIA], 0) - HIST.VL_MEDIA_HIST) / HIST.VL_MEDIA_HIST) * 100
                ELSE 0 
            END
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        INNER JOIN (
            SELECT [CD_PONTO_MEDICAO], AVG(ISNULL([VL_MEDIA_DIARIA], 0)) AS VL_MEDIA_HIST
            FROM [dbo].[MEDICAO_RESUMO_DIARIO]
            WHERE [DT_MEDICAO] >= DATEADD(WEEK, -4, @DT_PROCESSAMENTO)
              AND [DT_MEDICAO] < @DT_PROCESSAMENTO
              AND DATEPART(WEEKDAY, [DT_MEDICAO]) = DATEPART(WEEKDAY, @DT_PROCESSAMENTO)
            GROUP BY [CD_PONTO_MEDICAO]
        ) HIST ON MRD.[CD_PONTO_MEDICAO] = HIST.[CD_PONTO_MEDICAO]
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 7: Media historica diaria calculada.';
        
        -- ====================================================================
        -- ETAPA 8: FLAGS DIÁRIAS
        -- ====================================================================
        
        -- 8.1 SEM COMUNICAÇÃO
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_SEM_COMUNICACAO] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ISNULL([QTD_REGISTROS], 0) < 720;
        
        -- 8.2 VALOR CONSTANTE
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_VALOR_CONSTANTE] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ISNULL([QTD_VALORES_DISTINTOS], 0) <= 5
          AND ISNULL([QTD_REGISTROS], 0) >= 1000;
        
        -- 8.3 ZEROS SUSPEITOS
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_ZEROS_SUSPEITOS] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ISNULL([QTD_ZEROS], 0) > 360
          AND ISNULL([VL_MEDIA_HISTORICA], 0) > 0.001;
        
        -- 8.4 VALOR NEGATIVO
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_VALOR_NEGATIVO] = 1,
            [QTD_NEGATIVOS] = (
                SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO 
                  AND ISNULL(MRH.[FL_VALOR_NEGATIVO], 0) = 1
            )
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ISNULL([VL_MIN_DIARIO], 0) < 0;
        
        -- 8.5 FORA DA FAIXA
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_FORA_FAIXA] = 1,
            [QTD_FORA_FAIXA] = (
                SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO 
                  AND ISNULL(MRH.[FL_FORA_FAIXA], 0) = 1
            )
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND (
              (ISNULL([VL_LIMITE_SUPERIOR], 0) > 0 AND ISNULL([VL_MAX_DIARIO], 0) > [VL_LIMITE_SUPERIOR])
              OR (ISNULL([VL_CAPACIDADE_NOMINAL], 0) > 0 AND ISNULL([VL_MAX_DIARIO], 0) > [VL_CAPACIDADE_NOMINAL])
              OR ([VL_LIMITE_INFERIOR] IS NOT NULL AND ISNULL([VL_MIN_DIARIO], 0) < [VL_LIMITE_INFERIOR] AND ISNULL([VL_MIN_DIARIO], 0) <> 0)
          );
        
        -- 8.6 SPIKES
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_SPIKE] = 1,
            [QTD_SPIKES] = (
                SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO 
                  AND ISNULL(MRH.[FL_SPIKE], 0) = 1
            )
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND EXISTS (
              SELECT 1 FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
              WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO 
                AND ISNULL(MRH.[FL_SPIKE], 0) = 1
          );
        
        -- 8.7 DESVIO HISTÓRICO > 50%
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_DESVIO_HISTORICO] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ABS(ISNULL([VL_DESVIO_HISTORICO], 0)) > 50;
        
        -- 8.8 PERFIL ANÔMALO
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_PERFIL_ANOMALO] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ISNULL([VL_DESVIO_PADRAO], 0) < 0.1
          AND ISNULL([VL_MEDIA_DIARIA], 0) > 0.001
          AND ISNULL([QTD_REGISTROS], 0) >= 1000;
        
        PRINT 'Etapa 8: Flags diarias detectadas.';
        
        -- ====================================================================
        -- ETAPA 9: SCORE DE SAÚDE E CLASSIFICAÇÃO
        -- ====================================================================
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET 
            [VL_SCORE_SAUDE] = 
                10
                - (CASE WHEN ISNULL([FL_SEM_COMUNICACAO], 0) = 1 THEN 3 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_VALOR_CONSTANTE], 0) = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_ZEROS_SUSPEITOS], 0) = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_VALOR_NEGATIVO], 0) = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_FORA_FAIXA], 0) = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_SPIKE], 0) = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_PERFIL_ANOMALO], 0) = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_DESVIO_HISTORICO], 0) = 1 THEN 1 ELSE 0 END),
            
            [FL_ANOMALIA] = CASE 
                WHEN ISNULL([FL_SEM_COMUNICACAO], 0) = 1 
                  OR ISNULL([FL_VALOR_CONSTANTE], 0) = 1 
                  OR ISNULL([FL_ZEROS_SUSPEITOS], 0) = 1
                  OR ISNULL([FL_VALOR_NEGATIVO], 0) = 1 
                  OR ISNULL([FL_FORA_FAIXA], 0) = 1 
                  OR ISNULL([FL_SPIKE], 0) = 1
                  OR ISNULL([FL_PERFIL_ANOMALO], 0) = 1 
                  OR ISNULL([FL_DESVIO_HISTORICO], 0) = 1
                THEN 1 ELSE 0 
            END,
            
            [DS_TIPO_PROBLEMA] = CASE
                WHEN ISNULL([FL_SEM_COMUNICACAO], 0) = 1 THEN 'COMUNICACAO'
                WHEN ISNULL([FL_VALOR_CONSTANTE], 0) = 1 OR ISNULL([FL_PERFIL_ANOMALO], 0) = 1 THEN 'MEDIDOR'
                WHEN ISNULL([FL_VALOR_NEGATIVO], 0) = 1 OR ISNULL([FL_FORA_FAIXA], 0) = 1 OR ISNULL([FL_SPIKE], 0) = 1 THEN 'HIDRAULICO'
                WHEN ISNULL([FL_ZEROS_SUSPEITOS], 0) = 1 OR ISNULL([FL_DESVIO_HISTORICO], 0) = 1 THEN 'VERIFICAR'
                ELSE NULL
            END,
            
            [DS_ANOMALIAS] = STUFF((
                SELECT DISTINCT '; ' + MRH.[DS_TIPO_ANOMALIA]
                FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
                  AND MRH.[DS_TIPO_ANOMALIA] IS NOT NULL
                  AND MRH.[DS_TIPO_ANOMALIA] <> ''
                FOR XML PATH('')
            ), 1, 2, '')
            
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        
        -- Garantir score mínimo 0
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [VL_SCORE_SAUDE] = 0
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO 
          AND ISNULL([VL_SCORE_SAUDE], 0) < 0;
        
        PRINT 'Etapa 9: Score de saude calculado.';
        
        COMMIT TRANSACTION;
        
        -- ====================================================================
        -- RESUMO FINAL
        -- ====================================================================
        PRINT '';
        PRINT '============================================';
        PRINT 'PROCESSAMENTO CONCLUIDO COM SUCESSO';
        PRINT '============================================';
        
        SELECT 
            'RESUMO' AS TIPO,
            COUNT(*) AS TOTAL_PONTOS,
            SUM(ISNULL([QTD_REGISTROS], 0)) AS TOTAL_MEDICOES,
            SUM(CASE WHEN ISNULL([FL_ANOMALIA], 0) = 1 THEN 1 ELSE 0 END) AS PONTOS_COM_ANOMALIA,
            SUM(CASE WHEN ISNULL([FL_SEM_COMUNICACAO], 0) = 1 THEN 1 ELSE 0 END) AS PROBLEMAS_COMUNICACAO,
            SUM(CASE WHEN [DS_TIPO_PROBLEMA] = 'MEDIDOR' THEN 1 ELSE 0 END) AS PROBLEMAS_MEDIDOR,
            SUM(CASE WHEN [DS_TIPO_PROBLEMA] = 'HIDRAULICO' THEN 1 ELSE 0 END) AS PROBLEMAS_HIDRAULICO,
            SUM(CASE WHEN ISNULL([ID_SITUACAO], 1) = 2 THEN 1 ELSE 0 END) AS PONTOS_TRATADOS,
            AVG(ISNULL([VL_SCORE_SAUDE], 10)) AS SCORE_MEDIO,
            MIN(ISNULL([VL_SCORE_SAUDE], 10)) AS SCORE_MINIMO
        FROM [dbo].[MEDICAO_RESUMO_DIARIO]
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        
        PRINT '';
        PRINT '============================================';
        PRINT 'ERRO NO PROCESSAMENTO';
        PRINT '============================================';
        PRINT 'Mensagem: ' + ERROR_MESSAGE();
        PRINT 'Linha: ' + CAST(ERROR_LINE() AS VARCHAR);
        PRINT 'Procedure: ' + ISNULL(ERROR_PROCEDURE(), 'N/A');
        
        -- Não propaga o erro - apenas loga
        -- THROW;
    END CATCH
END
GO

PRINT '';
PRINT '============================================';
PRINT 'SP BLINDADA CRIADA COM SUCESSO';
PRINT '============================================';
PRINT 'Todas as comparacoes usam ISNULL()';
PRINT 'View de limites eh opcional';
PRINT 'Divisoes por zero protegidas';
PRINT 'Erros sao logados mas nao propagados';
PRINT '============================================';
GO

-- ============================================================================
-- TABELA 2: MEDICAO_RESUMO_HORARIO (ATUALIZADA)
-- Detalhamento por hora com flags de anomalias
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[MEDICAO_RESUMO_HORARIO]') AND type in (N'U'))
    DROP TABLE [dbo].[MEDICAO_RESUMO_HORARIO];
GO

CREATE TABLE [dbo].[MEDICAO_RESUMO_HORARIO] (
    [CD_CHAVE]                  INT IDENTITY(1,1) NOT NULL,
    [CD_PONTO_MEDICAO]          INT NOT NULL,
    [DT_HORA]                   DATETIME NOT NULL,
    [NR_HORA]                   TINYINT NOT NULL,
    [ID_TIPO_MEDIDOR]           INT NULL,
    
    -- Contagens
    [QTD_REGISTROS]             INT NULL DEFAULT 0,
    [QTD_ESPERADA]              INT NULL DEFAULT 60,
    [QTD_ZEROS]                 INT NULL DEFAULT 0,
    [QTD_VALORES_DISTINTOS]     INT NULL DEFAULT 0,
    
    -- Estatísticas da hora
    [VL_MEDIA]                  DECIMAL(18,4) NULL,
    [VL_MIN]                    DECIMAL(18,4) NULL,
    [VL_MAX]                    DECIMAL(18,4) NULL,
    [VL_SOMA]                   DECIMAL(18,4) NULL,
    [VL_PRIMEIRO]               DECIMAL(18,4) NULL,  -- Primeiro valor da hora
    [VL_ULTIMO]                 DECIMAL(18,4) NULL,  -- Último valor da hora
    
    -- Variação (para detectar spikes)
    [VL_VARIACAO_MAX]           DECIMAL(18,4) NULL,  -- Maior variação entre registros consecutivos
    [VL_VARIACAO_PERC_MAX]      DECIMAL(18,4) NULL,  -- Maior variação percentual
    
    -- Flags de anomalia
    [FL_VALOR_CONSTANTE]        BIT NULL DEFAULT 0,
    [FL_VALOR_NEGATIVO]         BIT NULL DEFAULT 0,
    [FL_FORA_FAIXA]             BIT NULL DEFAULT 0,
    [FL_SPIKE]                  BIT NULL DEFAULT 0,
    [FL_ZEROS_SUSPEITOS]        BIT NULL DEFAULT 0,
    
    -- Tratamento
    [FL_TRATADO]                BIT NULL DEFAULT 0,
    [QTD_TRATADOS]              INT NULL DEFAULT 0,
    
    -- Anomalias
    [FL_ANOMALIA]               BIT NULL DEFAULT 0,
    [DS_TIPO_ANOMALIA]          VARCHAR(500) NULL,
    
    -- Comparação histórica
    [VL_MEDIA_HISTORICA]        DECIMAL(18,4) NULL,
    
    -- Controle
    [DT_PROCESSAMENTO]          DATETIME NULL DEFAULT GETDATE(),
    
    CONSTRAINT [PK_MEDICAO_RESUMO_HORARIO] PRIMARY KEY CLUSTERED ([CD_CHAVE] ASC),
    CONSTRAINT [UK_MEDICAO_RESUMO_HORARIO] UNIQUE ([CD_PONTO_MEDICAO], [DT_HORA])
);

CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_DATA] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([DT_HORA]) INCLUDE ([CD_PONTO_MEDICAO]);
CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_PONTO] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([CD_PONTO_MEDICAO], [DT_HORA]);
CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_ANOMALIA] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([FL_ANOMALIA]) WHERE [FL_ANOMALIA] = 1;

PRINT 'Tabela MEDICAO_RESUMO_HORARIO criada com sucesso.';
GO

-- ============================================================================
-- VIEW AUXILIAR: VW_PONTO_MEDICAO_LIMITES
-- Consolida limites de cada ponto de medição
-- ============================================================================

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_PONTO_MEDICAO_LIMITES')
    DROP VIEW [dbo].[VW_PONTO_MEDICAO_LIMITES];
GO

CREATE VIEW [dbo].[VW_PONTO_MEDICAO_LIMITES]
AS
SELECT 
    PM.CD_PONTO_MEDICAO,
    PM.DS_NOME,
    PM.ID_TIPO_MEDIDOR,
    PM.VL_LIMITE_INFERIOR_VAZAO,
    PM.VL_LIMITE_SUPERIOR_VAZAO,
    
    -- Capacidade nominal (de acordo com o tipo de medidor)
    CASE PM.ID_TIPO_MEDIDOR
        WHEN 1 THEN MAC.VL_CAPACIDADE_NOMINAL  -- Macromedidor
        WHEN 2 THEN NULL                        -- Estação Pitométrica (não tem)
        WHEN 4 THEN NULL                        -- Medidor Pressão
        WHEN 6 THEN NR.VL_VOLUME_TOTAL          -- Nível Reservatório
        WHEN 8 THEN HID.VL_LEITURA_LIMITE       -- Hidrômetro
    END AS VL_CAPACIDADE_NOMINAL,
    
    -- Vazão esperada (para referência)
    CASE PM.ID_TIPO_MEDIDOR
        WHEN 1 THEN MAC.VL_VAZAO_ESPERADA
        ELSE NULL
    END AS VL_VAZAO_ESPERADA,
    
    -- Pressão máxima (para validação cruzada)
    CASE PM.ID_TIPO_MEDIDOR
        WHEN 1 THEN MAC.VL_PRESSAO_MAXIMA
        WHEN 6 THEN NR.VL_PRESSAO_MAXIMA_RECALQUE
        ELSE NULL
    END AS VL_PRESSAO_MAXIMA,
    
    -- Fator de correção
    PM.VL_FATOR_CORRECAO_VAZAO,
    
    -- Periodicidade esperada (para cálculo de registros esperados)
    PM.OP_PERIODICIDADE_LEITURA,
    
    -- Status ativo
    CASE 
        WHEN PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE() 
        THEN 1 ELSE 0 
    END AS FL_ATIVO

FROM [dbo].[PONTO_MEDICAO] PM

-- Join com Macromedidor
LEFT JOIN [dbo].[MACROMEDIDOR] MAC ON PM.CD_PONTO_MEDICAO = MAC.CD_PONTO_MEDICAO
    AND PM.ID_TIPO_MEDIDOR = 1

-- Join com Estação Pitométrica
LEFT JOIN [dbo].[ESTACAO_PITOMETRICA] EP ON PM.CD_PONTO_MEDICAO = EP.CD_PONTO_MEDICAO
    AND PM.ID_TIPO_MEDIDOR = 2

-- Join com Medidor Pressão
LEFT JOIN [dbo].[MEDIDOR_PRESSAO] MP ON PM.CD_PONTO_MEDICAO = MP.CD_PONTO_MEDICAO
    AND PM.ID_TIPO_MEDIDOR = 4

-- Join com Nível Reservatório
LEFT JOIN [dbo].[NIVEL_RESERVATORIO] NR ON PM.CD_PONTO_MEDICAO = NR.CD_PONTO_MEDICAO
    AND PM.ID_TIPO_MEDIDOR = 6

-- Join com Hidrômetro
LEFT JOIN [dbo].[HIDROMETRO] HID ON PM.CD_PONTO_MEDICAO = HID.CD_PONTO_MEDICAO
    AND PM.ID_TIPO_MEDIDOR = 8;
GO

PRINT 'View VW_PONTO_MEDICAO_LIMITES criada com sucesso.';
GO

-- ============================================================================
-- STORED PROCEDURE: SP_PROCESSAR_MEDICAO_DIARIA (VERSÃO 2.0)
-- Processa dados com todas as regras de validação
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[SP_PROCESSAR_MEDICAO_DIARIA]') AND type in (N'P'))
    DROP PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_DIARIA];
GO

CREATE PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_DIARIA]
    @DT_PROCESSAMENTO DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @DT_PROCESSAMENTO IS NULL
        SET @DT_PROCESSAMENTO = CAST(DATEADD(DAY, -1, GETDATE()) AS DATE);
    
    DECLARE @DT_INICIO DATETIME = CAST(@DT_PROCESSAMENTO AS DATETIME);
    DECLARE @DT_FIM DATETIME = DATEADD(DAY, 1, @DT_INICIO);
    
    PRINT '============================================';
    PRINT 'PROCESSAMENTO DE MEDIÇÃO - VERSÃO 2.0';
    PRINT '============================================';
    PRINT 'Data: ' + CONVERT(VARCHAR, @DT_PROCESSAMENTO, 103);
    PRINT 'Período: ' + CONVERT(VARCHAR, @DT_INICIO, 120) + ' até ' + CONVERT(VARCHAR, @DT_FIM, 120);
    PRINT '';
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- ====================================================================
        -- ETAPA 0: Limpar dados existentes (reprocessamento)
        -- ====================================================================
        DELETE FROM [dbo].[MEDICAO_RESUMO_HORARIO] WHERE CAST([DT_HORA] AS DATE) = @DT_PROCESSAMENTO;
        DELETE FROM [dbo].[MEDICAO_RESUMO_DIARIO] WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        PRINT 'Etapa 0: Dados anteriores removidos.';
        
        -- ====================================================================
        -- ETAPA 1: Popular MEDICAO_RESUMO_HORARIO com estatísticas básicas
        -- ====================================================================
        INSERT INTO [dbo].[MEDICAO_RESUMO_HORARIO] (
            [CD_PONTO_MEDICAO], [DT_HORA], [NR_HORA], [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS], [QTD_ZEROS], [QTD_VALORES_DISTINTOS],
            [VL_MEDIA], [VL_MIN], [VL_MAX], [VL_SOMA],
            [FL_TRATADO], [QTD_TRATADOS], [DT_PROCESSAMENTO]
        )
        SELECT 
            RVP.[CD_PONTO_MEDICAO],
            DATEADD(HOUR, DATEPART(HOUR, RVP.[DT_LEITURA]), CAST(CAST(RVP.[DT_LEITURA] AS DATE) AS DATETIME)) AS DT_HORA,
            DATEPART(HOUR, RVP.[DT_LEITURA]) AS NR_HORA,
            RVP.[ID_TIPO_MEDICAO],
            COUNT(*) AS QTD_REGISTROS,
            
            -- Conta zeros baseado no tipo
            SUM(CASE 
                WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) AND ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]) = 0 THEN 1
                WHEN RVP.[ID_TIPO_MEDICAO] = 4 AND RVP.[VL_PRESSAO] = 0 THEN 1
                WHEN RVP.[ID_TIPO_MEDICAO] = 6 AND RVP.[VL_RESERVATORIO] = 0 THEN 1
                ELSE 0
            END) AS QTD_ZEROS,
            
            -- Conta valores distintos (para detectar valor constante)
            COUNT(DISTINCT CASE 
                WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) THEN CAST(ROUND(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 2) AS VARCHAR)
                WHEN RVP.[ID_TIPO_MEDICAO] = 4 THEN CAST(ROUND(RVP.[VL_PRESSAO], 2) AS VARCHAR)
                WHEN RVP.[ID_TIPO_MEDICAO] = 6 THEN CAST(ROUND(RVP.[VL_RESERVATORIO], 2) AS VARCHAR)
            END) AS QTD_VALORES_DISTINTOS,
            
            -- Média
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
            
            -- Soma
            SUM(CASE 
                WHEN RVP.[ID_TIPO_MEDICAO] IN (1, 2, 8) THEN ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO])
                WHEN RVP.[ID_TIPO_MEDICAO] = 4 THEN RVP.[VL_PRESSAO]
                WHEN RVP.[ID_TIPO_MEDICAO] = 6 THEN RVP.[VL_RESERVATORIO]
                ELSE 0
            END) AS VL_SOMA,
            
            MAX(CASE WHEN RVP.[ID_SITUACAO] = 2 THEN 1 ELSE 0 END) AS FL_TRATADO,
            SUM(CASE WHEN RVP.[ID_SITUACAO] = 2 THEN 1 ELSE 0 END) AS QTD_TRATADOS,
            GETDATE()
            
        FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
        WHERE RVP.[DT_LEITURA] >= @DT_INICIO 
          AND RVP.[DT_LEITURA] < @DT_FIM
          AND RVP.[ID_SITUACAO] IN (1, 2)
        GROUP BY 
            RVP.[CD_PONTO_MEDICAO],
            DATEADD(HOUR, DATEPART(HOUR, RVP.[DT_LEITURA]), CAST(CAST(RVP.[DT_LEITURA] AS DATE) AS DATETIME)),
            DATEPART(HOUR, RVP.[DT_LEITURA]),
            RVP.[ID_TIPO_MEDICAO];
        
        PRINT 'Etapa 1: MEDICAO_RESUMO_HORARIO populado - ' + CAST(@@ROWCOUNT AS VARCHAR) + ' registros.';
        
        -- ====================================================================
        -- ETAPA 2: Calcular variações (spikes) por hora
        -- ====================================================================
        ;WITH VariacaoHoraria AS (
            SELECT 
                RVP.CD_PONTO_MEDICAO,
                DATEADD(HOUR, DATEPART(HOUR, RVP.DT_LEITURA), CAST(CAST(RVP.DT_LEITURA AS DATE) AS DATETIME)) AS DT_HORA,
                CASE 
                    WHEN RVP.ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO)
                    WHEN RVP.ID_TIPO_MEDICAO = 4 THEN RVP.VL_PRESSAO
                    WHEN RVP.ID_TIPO_MEDICAO = 6 THEN RVP.VL_RESERVATORIO
                END AS VALOR,
                LAG(CASE 
                    WHEN RVP.ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO)
                    WHEN RVP.ID_TIPO_MEDICAO = 4 THEN RVP.VL_PRESSAO
                    WHEN RVP.ID_TIPO_MEDICAO = 6 THEN RVP.VL_RESERVATORIO
                END) OVER (PARTITION BY RVP.CD_PONTO_MEDICAO ORDER BY RVP.DT_LEITURA) AS VALOR_ANT
            FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
            WHERE RVP.DT_LEITURA >= @DT_INICIO 
              AND RVP.DT_LEITURA < @DT_FIM
              AND RVP.ID_SITUACAO IN (1, 2)
        ),
        VariacaoCalc AS (
            SELECT 
                CD_PONTO_MEDICAO,
                DT_HORA,
                MAX(ABS(VALOR - VALOR_ANT)) AS VL_VARIACAO_MAX,
                MAX(CASE 
                    WHEN VALOR_ANT > 0 THEN ABS((VALOR - VALOR_ANT) / VALOR_ANT) * 100 
                    ELSE 0 
                END) AS VL_VARIACAO_PERC_MAX
            FROM VariacaoHoraria
            WHERE VALOR_ANT IS NOT NULL
            GROUP BY CD_PONTO_MEDICAO, DT_HORA
        )
        UPDATE MRH
        SET VL_VARIACAO_MAX = VC.VL_VARIACAO_MAX,
            VL_VARIACAO_PERC_MAX = VC.VL_VARIACAO_PERC_MAX
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        INNER JOIN VariacaoCalc VC ON MRH.CD_PONTO_MEDICAO = VC.CD_PONTO_MEDICAO 
            AND MRH.DT_HORA = VC.DT_HORA;
        
        PRINT 'Etapa 2: Variações (spikes) calculadas.';
        
        -- ====================================================================
        -- ETAPA 3: Calcular média histórica por hora (últimas 4 semanas)
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
        
        PRINT 'Etapa 3: Média histórica horária calculada.';
        
        -- ====================================================================
        -- ETAPA 4: DETECTAR ANOMALIAS HORÁRIAS
        -- ====================================================================
        
        -- 4.1 VALOR CONSTANTE (sensor travado)
        -- Regra: apenas 1 valor distinto na hora com >= 50 registros
        UPDATE MRH
        SET [FL_VALOR_CONSTANTE] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'VALOR_CONSTANTE'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[QTD_VALORES_DISTINTOS] = 1
          AND MRH.[QTD_REGISTROS] >= 50
          AND MRH.[VL_MEDIA] <> 0;  -- Zero constante é tratado separadamente
        
        -- 4.2 VALOR NEGATIVO
        UPDATE MRH
        SET [FL_VALOR_NEGATIVO] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'VALOR_NEGATIVO_' + CAST(CAST([VL_MIN] AS DECIMAL(10,2)) AS VARCHAR)
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[VL_MIN] < 0;
        
        -- 4.3 FORA DA FAIXA (usando limites do ponto)
        UPDATE MRH
        SET [FL_FORA_FAIXA] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                CASE 
                    WHEN MRH.[VL_MAX] > COALESCE(PML.VL_LIMITE_SUPERIOR_VAZAO, PML.VL_CAPACIDADE_NOMINAL) 
                    THEN 'ACIMA_LIMITE_' + CAST(CAST(MRH.[VL_MAX] AS DECIMAL(10,2)) AS VARCHAR)
                    WHEN MRH.[VL_MIN] < PML.VL_LIMITE_INFERIOR_VAZAO 
                    THEN 'ABAIXO_LIMITE_' + CAST(CAST(MRH.[VL_MIN] AS DECIMAL(10,2)) AS VARCHAR)
                END
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        INNER JOIN [dbo].[VW_PONTO_MEDICAO_LIMITES] PML ON MRH.[CD_PONTO_MEDICAO] = PML.[CD_PONTO_MEDICAO]
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] IN (1, 2, 8)  -- Apenas vazão
          AND (
              -- Acima do limite superior OU capacidade nominal
              (PML.VL_LIMITE_SUPERIOR_VAZAO IS NOT NULL AND MRH.[VL_MAX] > PML.VL_LIMITE_SUPERIOR_VAZAO)
              OR (PML.VL_CAPACIDADE_NOMINAL IS NOT NULL AND MRH.[VL_MAX] > PML.VL_CAPACIDADE_NOMINAL)
              -- Abaixo do limite inferior
              OR (PML.VL_LIMITE_INFERIOR_VAZAO IS NOT NULL AND MRH.[VL_MIN] < PML.VL_LIMITE_INFERIOR_VAZAO AND MRH.[VL_MIN] <> 0)
          );
        
        -- 4.4 SPIKE (salto abrupto > 200% de variação)
        UPDATE MRH
        SET [FL_SPIKE] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'SPIKE_' + CAST(CAST([VL_VARIACAO_PERC_MAX] AS INT) AS VARCHAR) + '%'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[VL_VARIACAO_PERC_MAX] > 200;
        
        -- 4.5 ZEROS SUSPEITOS (histórico não zera e agora zerou > 30 min)
        UPDATE MRH
        SET [FL_ZEROS_SUSPEITOS] = 1,
            [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'ZEROS_SUSPEITOS_' + CAST([QTD_ZEROS] AS VARCHAR) + 'MIN'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[QTD_ZEROS] >= 30
          AND MRH.[VL_MEDIA_HISTORICA] > 0  -- Histórico mostra que não deveria zerar
          AND MRH.[NR_HORA] BETWEEN 6 AND 22;  -- Horário comercial
        
        -- 4.6 REGISTROS INCOMPLETOS
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'INCOMPLETO_' + CAST([QTD_REGISTROS] AS VARCHAR) + '/60'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[QTD_REGISTROS] < 50;
        
        -- 4.7 PRESSÃO específicas
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                CASE 
                    WHEN MRH.[VL_MEDIA] < 10 AND MRH.[VL_MEDIA] > 0 THEN 'PRESSAO_BAIXA_' + CAST(CAST([VL_MEDIA] AS INT) AS VARCHAR) + 'MCA'
                    WHEN MRH.[VL_MAX] > 60 THEN 'PRESSAO_ALTA_' + CAST(CAST([VL_MAX] AS INT) AS VARCHAR) + 'MCA'
                    WHEN (MRH.[VL_MAX] - MRH.[VL_MIN]) > 20 THEN 'VARIACAO_PRESSAO_' + CAST(CAST([VL_MAX] - [VL_MIN] AS INT) AS VARCHAR) + 'MCA'
                END
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] = 4
          AND (
              (MRH.[VL_MEDIA] < 10 AND MRH.[VL_MEDIA] > 0)
              OR MRH.[VL_MAX] > 60
              OR (MRH.[VL_MAX] - MRH.[VL_MIN]) > 20
          );
        
        -- 4.8 NÍVEL RESERVATÓRIO - Extravasamento
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'NIVEL_100_EXTRAVASAMENTO'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND MRH.[ID_TIPO_MEDIDOR] = 6
          AND MRH.[VL_MAX] >= 100;
        
        PRINT 'Etapa 4: Anomalias horárias detectadas.';
        
        -- ====================================================================
        -- ETAPA 5: Popular MEDICAO_RESUMO_DIARIO
        -- ====================================================================
        INSERT INTO [dbo].[MEDICAO_RESUMO_DIARIO] (
            [CD_PONTO_MEDICAO], [DT_MEDICAO], [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS], [VL_MEDIA_DIARIA], [VL_MIN_DIARIO], [VL_MAX_DIARIO], 
            [VL_DESVIO_PADRAO], [VL_SOMA_DIARIA],
            [VL_LIMITE_INFERIOR], [VL_LIMITE_SUPERIOR], [VL_CAPACIDADE_NOMINAL],
            [QTD_VALORES_DISTINTOS], [QTD_ZEROS], [QTD_HORAS_SEM_DADO],
            [ID_SITUACAO], [QTD_TRATAMENTOS], [DS_HORAS_TRATADAS],
            [DT_PROCESSAMENTO]
        )
        SELECT 
            MRH.[CD_PONTO_MEDICAO],
            @DT_PROCESSAMENTO,
            MRH.[ID_TIPO_MEDIDOR],
            SUM(MRH.[QTD_REGISTROS]),
            CASE WHEN SUM(MRH.[QTD_REGISTROS]) > 0 THEN SUM(MRH.[VL_SOMA]) / SUM(MRH.[QTD_REGISTROS]) ELSE 0 END,
            MIN(MRH.[VL_MIN]),
            MAX(MRH.[VL_MAX]),
            STDEV(MRH.[VL_MEDIA]),
            SUM(MRH.[VL_SOMA]),
            PML.VL_LIMITE_INFERIOR_VAZAO,
            PML.VL_LIMITE_SUPERIOR_VAZAO,
            PML.VL_CAPACIDADE_NOMINAL,
            SUM(MRH.[QTD_VALORES_DISTINTOS]),
            SUM(MRH.[QTD_ZEROS]),
            24 - COUNT(*),  -- Horas sem dado
            CASE WHEN MAX(CAST(MRH.[FL_TRATADO] AS INT)) = 1 THEN 2 ELSE 1 END,
            SUM(MRH.[QTD_TRATADOS]),
            STUFF((
                SELECT ',' + CAST(MRH2.[NR_HORA] AS VARCHAR)
                FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH2
                WHERE MRH2.[CD_PONTO_MEDICAO] = MRH.[CD_PONTO_MEDICAO]
                  AND CAST(MRH2.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
                  AND MRH2.[FL_TRATADO] = 1
                ORDER BY MRH2.[NR_HORA]
                FOR XML PATH('')
            ), 1, 1, ''),
            GETDATE()
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        LEFT JOIN [dbo].[VW_PONTO_MEDICAO_LIMITES] PML ON MRH.[CD_PONTO_MEDICAO] = PML.[CD_PONTO_MEDICAO]
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
        GROUP BY MRH.[CD_PONTO_MEDICAO], MRH.[ID_TIPO_MEDIDOR],
                 PML.VL_LIMITE_INFERIOR_VAZAO, PML.VL_LIMITE_SUPERIOR_VAZAO, PML.VL_CAPACIDADE_NOMINAL;
        
        PRINT 'Etapa 5: MEDICAO_RESUMO_DIARIO populado - ' + CAST(@@ROWCOUNT AS VARCHAR) + ' registros.';
        
        -- ====================================================================
        -- ETAPA 6: Calcular média histórica diária
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
            SELECT [CD_PONTO_MEDICAO], AVG([VL_MEDIA_DIARIA]) AS VL_MEDIA_HIST
            FROM [dbo].[MEDICAO_RESUMO_DIARIO]
            WHERE [DT_MEDICAO] >= DATEADD(WEEK, -4, @DT_PROCESSAMENTO)
              AND [DT_MEDICAO] < @DT_PROCESSAMENTO
              AND DATEPART(WEEKDAY, [DT_MEDICAO]) = DATEPART(WEEKDAY, @DT_PROCESSAMENTO)
            GROUP BY [CD_PONTO_MEDICAO]
        ) HIST ON MRD.[CD_PONTO_MEDICAO] = HIST.[CD_PONTO_MEDICAO]
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 6: Média histórica diária calculada.';
        
        -- ====================================================================
        -- ETAPA 7: DETECTAR FLAGS DIÁRIAS
        -- ====================================================================
        
        -- 7.1 SEM COMUNICAÇÃO (< 50% dos registros esperados)
        UPDATE MRD
        SET [FL_SEM_COMUNICACAO] = 1
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO
          AND MRD.[QTD_REGISTROS] < 720;  -- < 50% de 1440
        
        -- 7.2 VALOR CONSTANTE no dia (< 5 valores distintos com muitos registros)
        UPDATE MRD
        SET [FL_VALOR_CONSTANTE] = 1
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO
          AND MRD.[QTD_VALORES_DISTINTOS] <= 5
          AND MRD.[QTD_REGISTROS] >= 1000;
        
        -- 7.3 ZEROS SUSPEITOS
        UPDATE MRD
        SET [FL_ZEROS_SUSPEITOS] = 1
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO
          AND MRD.[QTD_ZEROS] > 360  -- > 25% do dia
          AND MRD.[VL_MEDIA_HISTORICA] > 0;  -- Histórico indica que não deveria
        
        -- 7.4 VALOR NEGATIVO
        UPDATE MRD
        SET [FL_VALOR_NEGATIVO] = 1,
            [QTD_NEGATIVOS] = (
                SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = MRD.[CD_PONTO_MEDICAO] 
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO 
                  AND MRH.[FL_VALOR_NEGATIVO] = 1
            )
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO
          AND MRD.[VL_MIN_DIARIO] < 0;
        
        -- 7.5 FORA DA FAIXA
        UPDATE MRD
        SET [FL_FORA_FAIXA] = 1,
            [QTD_FORA_FAIXA] = (
                SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = MRD.[CD_PONTO_MEDICAO] 
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO 
                  AND MRH.[FL_FORA_FAIXA] = 1
            )
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO
          AND (
              (MRD.[VL_LIMITE_SUPERIOR] IS NOT NULL AND MRD.[VL_MAX_DIARIO] > MRD.[VL_LIMITE_SUPERIOR])
              OR (MRD.[VL_CAPACIDADE_NOMINAL] IS NOT NULL AND MRD.[VL_MAX_DIARIO] > MRD.[VL_CAPACIDADE_NOMINAL])
              OR (MRD.[VL_LIMITE_INFERIOR] IS NOT NULL AND MRD.[VL_MIN_DIARIO] < MRD.[VL_LIMITE_INFERIOR] AND MRD.[VL_MIN_DIARIO] <> 0)
          );
        
        -- 7.6 SPIKES
        UPDATE MRD
        SET [FL_SPIKE] = 1,
            [QTD_SPIKES] = (
                SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = MRD.[CD_PONTO_MEDICAO] 
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO 
                  AND MRH.[FL_SPIKE] = 1
            )
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO
          AND EXISTS (
              SELECT 1 FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
              WHERE MRH.[CD_PONTO_MEDICAO] = MRD.[CD_PONTO_MEDICAO] 
                AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO 
                AND MRH.[FL_SPIKE] = 1
          );
        
        -- 7.7 DESVIO HISTÓRICO > 50%
        UPDATE MRD
        SET [FL_DESVIO_HISTORICO] = 1
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ABS(MRD.[VL_DESVIO_HISTORICO]) > 50;
        
        -- 7.8 PERFIL ANÔMALO (desvio padrão muito baixo = linha reta)
        UPDATE MRD
        SET [FL_PERFIL_ANOMALO] = 1
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO
          AND MRD.[VL_DESVIO_PADRAO] < 0.1
          AND MRD.[VL_MEDIA_DIARIA] > 0
          AND MRD.[QTD_REGISTROS] >= 1000;
        
        PRINT 'Etapa 7: Flags diárias detectadas.';
        
        -- ====================================================================
        -- ETAPA 8: CALCULAR SCORE DE SAÚDE E CLASSIFICAR PROBLEMA
        -- ====================================================================
        UPDATE MRD
        SET 
            -- Score: começa em 10, subtrai por cada problema
            [VL_SCORE_SAUDE] = 10
                - (CASE WHEN [FL_SEM_COMUNICACAO] = 1 THEN 3 ELSE 0 END)
                - (CASE WHEN [FL_VALOR_CONSTANTE] = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN [FL_ZEROS_SUSPEITOS] = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN [FL_VALOR_NEGATIVO] = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN [FL_FORA_FAIXA] = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN [FL_SPIKE] = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN [FL_PERFIL_ANOMALO] = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN [FL_DESVIO_HISTORICO] = 1 THEN 1 ELSE 0 END),
            
            -- Flag geral de anomalia
            [FL_ANOMALIA] = CASE 
                WHEN [FL_SEM_COMUNICACAO] = 1 OR [FL_VALOR_CONSTANTE] = 1 OR [FL_ZEROS_SUSPEITOS] = 1
                  OR [FL_VALOR_NEGATIVO] = 1 OR [FL_FORA_FAIXA] = 1 OR [FL_SPIKE] = 1
                  OR [FL_PERFIL_ANOMALO] = 1 OR [FL_DESVIO_HISTORICO] = 1
                THEN 1 ELSE 0 
            END,
            
            -- Classificação do tipo de problema
            [DS_TIPO_PROBLEMA] = CASE
                -- Problemas de COMUNICAÇÃO (dado ausente/não atualiza)
                WHEN [FL_SEM_COMUNICACAO] = 1 THEN 'COMUNICACAO'
                -- Problemas de MEDIDOR (dado presente mas fisicamente estranho)
                WHEN [FL_VALOR_CONSTANTE] = 1 OR [FL_PERFIL_ANOMALO] = 1 THEN 'MEDIDOR'
                -- Problemas HIDRÁULICOS (valores fora do esperado)
                WHEN [FL_VALOR_NEGATIVO] = 1 OR [FL_FORA_FAIXA] = 1 OR [FL_SPIKE] = 1 THEN 'HIDRAULICO'
                -- Outros
                WHEN [FL_ZEROS_SUSPEITOS] = 1 OR [FL_DESVIO_HISTORICO] = 1 THEN 'VERIFICAR'
                ELSE NULL
            END,
            
            -- Concatenar anomalias
            [DS_ANOMALIAS] = STUFF((
                SELECT DISTINCT '; ' + MRH.[DS_TIPO_ANOMALIA]
                FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
                WHERE MRH.[CD_PONTO_MEDICAO] = MRD.[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
                  AND MRH.[DS_TIPO_ANOMALIA] IS NOT NULL
                FOR XML PATH('')
            ), 1, 2, '')
            
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO;
        
        -- Garantir score mínimo de 0
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [VL_SCORE_SAUDE] = 0
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO AND [VL_SCORE_SAUDE] < 0;
        
        PRINT 'Etapa 8: Score de saúde calculado.';
        
        COMMIT TRANSACTION;
        
        -- ====================================================================
        -- RESUMO FINAL
        -- ====================================================================
        PRINT '';
        PRINT '============================================';
        PRINT 'PROCESSAMENTO CONCLUÍDO COM SUCESSO';
        PRINT '============================================';
        
        SELECT 
            'RESUMO' AS TIPO,
            COUNT(*) AS TOTAL_PONTOS,
            SUM([QTD_REGISTROS]) AS TOTAL_MEDICOES,
            SUM(CASE WHEN [FL_ANOMALIA] = 1 THEN 1 ELSE 0 END) AS PONTOS_COM_ANOMALIA,
            SUM(CASE WHEN [FL_SEM_COMUNICACAO] = 1 THEN 1 ELSE 0 END) AS PROBLEMAS_COMUNICACAO,
            SUM(CASE WHEN [DS_TIPO_PROBLEMA] = 'MEDIDOR' THEN 1 ELSE 0 END) AS PROBLEMAS_MEDIDOR,
            SUM(CASE WHEN [DS_TIPO_PROBLEMA] = 'HIDRAULICO' THEN 1 ELSE 0 END) AS PROBLEMAS_HIDRAULICO,
            SUM(CASE WHEN [ID_SITUACAO] = 2 THEN 1 ELSE 0 END) AS PONTOS_TRATADOS,
            AVG([VL_SCORE_SAUDE]) AS SCORE_MEDIO,
            MIN([VL_SCORE_SAUDE]) AS SCORE_MINIMO
        FROM [dbo].[MEDICAO_RESUMO_DIARIO]
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        PRINT 'ERRO: ' + ERROR_MESSAGE();
        THROW;
    END CATCH
END
GO

PRINT 'Stored Procedure SP_PROCESSAR_MEDICAO_DIARIA criada com sucesso.';
GO

-- ============================================================================
-- EXEMPLO DE EXECUÇÃO
-- ============================================================================
-- EXEC [dbo].[SP_PROCESSAR_MEDICAO_DIARIA] @DT_PROCESSAMENTO = '2025-01-14';
GO