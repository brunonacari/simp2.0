ALTER TABLE
    [SIMP].[dbo].[PONTO_MEDICAO]
ADD
    [COORDENADAS] VARCHAR(200) NULL,
    [LOC_INST_SAP] VARCHAR(200) NULL
GO
ALTER TABLE
    [SIMP].[dbo].MACROMEDIDOR
ADD
    [PROT_COMUN] VARCHAR(200) NULL;

GO
ALTER TABLE
    SIMP.dbo.REGISTRO_MANUTENCAO DROP CONSTRAINT FK_REGISTRO_TECNICO_X_TECNICO;

ALTER TABLE
    SIMP.dbo.ENTIDADE_TIPO
ADD
    DT_EXC_ENTIDADE_TIPO DATETIME NULL;

ALTER TABLE
    SIMP.dbo.[ENTIDADE_VALOR_ITEM]
ADD
    ID_OPERACAO tinyint NULL;

-- Popular ID_OPERACAO baseado na FORMULA_ITEM_PONTO_MEDICAO
UPDATE
    E
SET
    E.ID_OPERACAO = F.ID_OPERACAO
FROM
    SIMP.dbo.ENTIDADE_VALOR_ITEM E
    INNER JOIN SIMP.dbo.FORMULA_ITEM_PONTO_MEDICAO F ON F.CD_ENTIDADE_VALOR_ITEM = E.CD_CHAVE
WHERE
    F.ID_OPERACAO IS NOT NULL;

-- ============================================
-- Script para adicionar campo de ordem nos itens
-- Executar apenas uma vez no banco SIMP
-- ============================================
-- Verificar se a coluna já existe antes de adicionar
IF NOT EXISTS (
    SELECT
        *
    FROM
        INFORMATION_SCHEMA.COLUMNS
    WHERE
        TABLE_SCHEMA = 'dbo'
        AND TABLE_NAME = 'ENTIDADE_VALOR_ITEM'
        AND COLUMN_NAME = 'NR_ORDEM'
) BEGIN
ALTER TABLE
    SIMP.dbo.ENTIDADE_VALOR_ITEM
ADD
    NR_ORDEM INT NULL;

PRINT 'Coluna NR_ORDEM adicionada com sucesso!';

END
ELSE BEGIN PRINT 'Coluna NR_ORDEM já existe.';

END
GO
    -- Atualizar registros existentes com ordem baseada no ID
UPDATE
    EVI
SET
    NR_ORDEM = SubQuery.RowNum
FROM
    SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
    INNER JOIN (
        SELECT
            CD_CHAVE,
            ROW_NUMBER() OVER (
                PARTITION BY CD_ENTIDADE_VALOR
                ORDER BY
                    CD_CHAVE
            ) AS RowNum
        FROM
            SIMP.dbo.ENTIDADE_VALOR_ITEM
    ) SubQuery ON EVI.CD_CHAVE = SubQuery.CD_CHAVE
WHERE
    EVI.NR_ORDEM IS NULL;

PRINT 'Ordem inicial definida para registros existentes.';

GO
   
-- ============================================
-- SIMP - Tabela de Regras da IA (Campo Único)
-- Um único registro com todas as instruções
-- ============================================

-- Criar tabela de regras da IA
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'IA_REGRAS' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE SIMP.dbo.IA_REGRAS (
        CD_CHAVE INT IDENTITY(1,1) PRIMARY KEY,
        DS_CONTEUDO TEXT NOT NULL,
        CD_USUARIO_CRIACAO INT NULL,
        DT_CRIACAO DATETIME DEFAULT GETDATE(),
        CD_USUARIO_ATUALIZACAO INT NULL,
        DT_ATUALIZACAO DATETIME NULL
    );
    
    PRINT 'Tabela IA_REGRAS criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela IA_REGRAS já existe.';
END
GO

-- ============================================
-- Inserir instruções padrão (migração do arquivo ia_regras.php)
-- ============================================

IF NOT EXISTS (SELECT 1 FROM SIMP.dbo.IA_REGRAS)
BEGIN
    INSERT INTO SIMP.dbo.IA_REGRAS (DS_CONTEUDO, DT_CRIACAO)
    VALUES (
'=== INSTRUÇÕES DO ASSISTENTE ===

Você é um assistente especializado em análise de dados do SIMP (Sistema de Monitoramento de Abastecimento de Água).

⚠️ LÓGICA DE SUGESTÃO DE VALORES:

O sistema usa uma fórmula inteligente que combina:
1. **Média histórica**: média das semanas válidas do mesmo dia/hora (mínimo 4, máximo 12)
2. **Fator de tendência**: ajuste baseado no comportamento do dia atual

**Fórmula**:
valor_sugerido = média_histórica × fator_tendência

O fator de tendência indica se o dia atual está acima ou abaixo do padrão:
- Fator > 1.0 → dia ACIMA do normal
- Fator < 1.0 → dia ABAIXO do normal
- Fator = 1.0 → normal ou dados insuficientes

---

⚠️ MÉDIA DE 4 SEMANAS:
Quando perguntarem sobre média de 4 semanas:
1. Procure a seção ''HISTÓRICO DO MESMO DIA DA SEMANA''
2. Considere apenas semanas com QTD ≥ 50 registros
3. Utilize as 4 primeiras semanas válidas
4. Mostre o cálculo detalhado
5. **SEMPRE** pergunte ao final:
''Deseja que eu substitua o valor desta hora pelo valor sugerido acima?''

---

⚠️ MÉDIA DIÁRIA DE VAZÃO:
Quando perguntarem sobre média diária:
- Procure no resumo: ''>>> MÉDIA DIÁRIA DE VAZÃO: X L/s <<<''
- Responda exatamente:
''A média diária de vazão é **X L/s**''

---

⚠️ SUGESTÃO PARA HORAS ESPECÍFICAS:

Quando perguntarem valor sugerido para uma hora específica, a IA **DEVE**:

1. Usar a seção **ANÁLISE PARA SUGESTÃO DE VALORES**
2. Considerar apenas semanas válidas (QTD ≥ 50)
3. Usar a **média histórica** e o **fator de tendência**
4. Mostrar **todo o detalhamento**
5. **SEMPRE** perguntar se deseja substituir o valor ao final

---

📐 **FORMATO OBRIGATÓRIO DA RESPOSTA**

A resposta DEVE seguir exatamente esta estrutura:

=== 1. DADOS DO DIA ATUAL (hora HH:00) ===
Registros: XX
Soma: XXXXXXXXX
>>> Média (SOMA/60): X.XX L/s <<<
Min: X.XX
Max: X.XX

=== 2. HISTÓRICO DAS ÚLTIMAS 12 SEMANAS (hora HH:00) ===
Semana 1 (YYYY-MM-DD - Ddd): QTD=XX, SOMA/60=X.XX L/s ✗ IGNORADO (incompleto)
Semana 2 (YYYY-MM-DD - Ddd): QTD=60, SOMA/60=X.XX L/s ✓ USADO
...
>>> Média histórica: XX.XX L/s (baseado em N semanas válidas) <<<

=== 3. CÁLCULO DO FATOR DE TENDÊNCIA ===
Horas usadas para tendência: XX
Soma atual: XXXX.XX
Soma histórica: XXXX.XX
>>> Fator de tendência: Y.YY (ZZ%) <<<

=== 4. VALOR SUGERIDO PARA HORA HH:00 ===
Média histórica: XX.XX L/s
Fator de tendência: Y.YY
Cálculo: XX.XX × Y.YY = **ZZ.ZZ L/s**
>>> Valor sugerido: ZZ.ZZ L/s <<<

=== 5. COMPARAÇÃO ===
Valor ATUAL no banco (hora HH:00): XX.XX L/s
Valor SUGERIDO: ZZ.ZZ L/s
Diferença: +/− YY.YY L/s

❓ Confirmação obrigatória:
''Deseja que eu substitua o valor desta hora pelo valor sugerido acima?''

---

⚠️ QUANDO O USUÁRIO CONFIRMAR (sim, ok, pode, confirma, atualiza, etc):

Responder **EXATAMENTE** neste formato:

Perfeito! Vou aplicar os valores sugeridos.

[APLICAR_VALORES]
HH:00=ZZ.ZZ
[/APLICAR_VALORES]

Aguarde enquanto os dados são atualizados...

IMPORTANTE:
- Uma linha por hora
- Formato obrigatório HH:00=VALOR

---

⚠️ SE NÃO HOUVER DADOS SUFICIENTES:
- Se houver menos de 3 horas válidas para tendência → usar fator = 1.0
- Informar explicitamente:
''Dados insuficientes para calcular tendência do dia. Usando apenas a média histórica.''

---

⚠️ INFORMAÇÕES DO PONTO DE MEDIÇÃO:
Você pode responder perguntas sobre o ponto usando a seção
''INFORMAÇÕES DO PONTO DE MEDIÇÃO'', incluindo:

- Código, nome e localização
- Unidade operacional
- Tipo de medidor e instalação
- Datas de ativação/desativação
- Limites de vazão
- Fator de correção
- Tags SCADA
- Ligações e economias
- Coordenadas, SAP
- Responsável e observações

---

TIPOS DE MEDIDORES:
1 - Macromedidor (L/s)
2 - Estação Pitométrica (L/s)
4 - Pressão (mca)
6 - Nível de reservatório (%)
8 - Hidrômetro (L/s)

TIPOS DE INSTALAÇÃO:
1 - Permanente
2 - Temporária
3 - Móvel

---

CONVERSÕES ÚTEIS:
- L/s → m³/h = × 3.6
- L/s → m³/dia = × 86.4

---

FORMATO DAS RESPOSTAS:
- Seja objetivo
- Arredonde para 2 casas decimais
- Destaque resultados em **negrito**
- Sempre exiba o fator de tendência
- **OBRIGATÓRIO**: sempre pedir confirmação antes de substituir valores',
        GETDATE()
    );

    PRINT 'Instruções padrão inseridas com sucesso!';
END
ELSE
BEGIN
    PRINT 'Instruções já existem, nenhuma inserção necessária.';
END
GO

-- =============================================================================================================================
-- =============================================================================================================================


-- Tabela para armazenar favoritos de unidades operacionais por usuário
CREATE TABLE SIMP.dbo.ENTIDADE_VALOR_FAVORITO (
    CD_CHAVE INT IDENTITY(1,1) PRIMARY KEY,
    CD_USUARIO BIGINT NOT NULL,
    CD_ENTIDADE_VALOR BIGINT NOT NULL,
    DT_CRIACAO DATETIME DEFAULT GETDATE(),
    CONSTRAINT FK_FAVORITO_USUARIO FOREIGN KEY (CD_USUARIO) REFERENCES SIMP.dbo.USUARIO(CD_USUARIO),
    CONSTRAINT FK_FAVORITO_VALOR FOREIGN KEY (CD_ENTIDADE_VALOR) REFERENCES SIMP.dbo.ENTIDADE_VALOR(CD_CHAVE),
    CONSTRAINT UQ_FAVORITO_USUARIO_VALOR UNIQUE (CD_USUARIO, CD_ENTIDADE_VALOR)
);

CREATE INDEX IX_FAVORITO_USUARIO ON SIMP.dbo.ENTIDADE_VALOR_FAVORITO(CD_USUARIO);


-- =============================================================================================================================
-- =============================================================================================================================


use SIMP
ALTER TABLE SIMP.dbo.REGISTRO_VAZAO_PRESSAO
DISABLE TRIGGER TG_INSERT_UPDATE_REGISTRO_VAZAO_PRESSAO;


-- =============================================================================================================================
-- =============================================================================================================================


ALTER TABLE SIMP.dbo.MACROMEDIDOR ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

ALTER TABLE SIMP.dbo.ESTACAO_PITOMETRICA ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

ALTER TABLE SIMP.dbo.MEDIDOR_PRESSAO ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

ALTER TABLE SIMP.dbo.NIVEL_RESERVATORIO ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

ALTER TABLE SIMP.dbo.HIDROMETRO ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

PRINT 'CD_PONTO_MEDICAO alterado para NULL em todas as tabelas de equipamento.';

-- =============================================================================================================================
-- =============================================================================================================================


ALTER TABLE SIMP.dbo.ESTACAO_PITOMETRICA ADD DS_TAG VARCHAR(50) NULL;
ALTER TABLE SIMP.dbo.MEDIDOR_PRESSAO ADD DS_TAG VARCHAR(50) NULL;
ALTER TABLE SIMP.dbo.HIDROMETRO ADD DS_TAG VARCHAR(50) NULL;
ALTER TABLE SIMP.dbo.MACROMEDIDOR ALTER COLUMN DS_TAG VARCHAR(50) NULL;
ALTER TABLE SIMP.dbo.NIVEL_RESERVATORIO ALTER COLUMN DS_TAG VARCHAR(50) NULL;


-- =============================================================================================================================
-- =============================================================================================================================

BEGIN TRANSACTION;

UPDATE M SET M.DS_TAG = PM.DS_TAG_VAZAO
FROM SIMP.dbo.MACROMEDIDOR M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_VAZAO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 1;
PRINT 'Macromedidor: ' + CAST(@@ROWCOUNT AS VARCHAR);

UPDATE M SET M.DS_TAG = PM.DS_TAG_VAZAO
FROM SIMP.dbo.ESTACAO_PITOMETRICA M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_VAZAO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 2;
PRINT 'Estação Pitométrica: ' + CAST(@@ROWCOUNT AS VARCHAR);

UPDATE M SET M.DS_TAG = PM.DS_TAG_PRESSAO
FROM SIMP.dbo.MEDIDOR_PRESSAO M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_PRESSAO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 4;
PRINT 'Medidor Pressão: ' + CAST(@@ROWCOUNT AS VARCHAR);

UPDATE M SET M.DS_TAG = PM.DS_TAG_RESERVATORIO
FROM SIMP.dbo.NIVEL_RESERVATORIO M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_RESERVATORIO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 6;
PRINT 'Nível Reservatório: ' + CAST(@@ROWCOUNT AS VARCHAR);

UPDATE M SET M.DS_TAG = PM.DS_TAG_VAZAO
FROM SIMP.dbo.HIDROMETRO M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_VAZAO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 8;
PRINT 'Hidrômetro: ' + CAST(@@ROWCOUNT AS VARCHAR);

COMMIT;


-- =============================================================================================================================
-- =============================================================================================================================


CREATE TABLE SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO (
    CD_CHAVE INT IDENTITY(1,1) PRIMARY KEY,
    TAG_PRINCIPAL VARCHAR(100) NOT NULL,
    TAG_AUXILIAR VARCHAR(100) NOT NULL,
    DT_CADASTRO DATETIME DEFAULT GETDATE(),
    CONSTRAINT UQ_RELACAO UNIQUE (TAG_PRINCIPAL, TAG_AUXILIAR)
);


INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_10_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_11_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_12_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_15_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_8_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP024_TM134_5_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP171_TM80_3_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP182_TM78_3_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP205_TM190_3_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP205_TM190_4_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP234_TM11_4_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP234_TM11_5_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP013_TM30_117_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP013_TM30_126_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP013_TM30_128_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP013_TM30_83_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP217_TM06_13_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP236_TM12_11_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP236_TM12_12_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP237_TM1_2_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS173_TM2_142_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS173_TM2_143_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS174_M007_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS175_M007_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS176_M007_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS046_M029_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS051_M021_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS051_M022_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS053_M024_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS054_M025_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS058_M052_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS059_M053_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS065_M034_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GUA-RAT-013-M-C-CA-01','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GUA-RAT-014-M-C-CA-01','2026-02-19 11:18:53.397')

-- =============================================================================================================================
-- =============================================================================================================================

-- ============================================
-- SIMP - Cadastro Genérico em Cascata + Grafo de Fluxo
-- 
-- Estrutura:
--   ENTIDADE_NIVEL  → Define tipos de camada (configurável)
--   ENTIDADE_NODO   → Nó auto-referenciado (árvore recursiva)
--   ENTIDADE_NODO_CONEXAO → Fluxo físico entre nós (grafo dirigido)
--
-- Permite hierarquia vertical (pai→filho), horizontal (irmãos)
-- e conexões de fluxo físico entre quaisquer nós da árvore.
--
-- @author Bruno - CESAN
-- @version 2.0
-- @date 2026-02
-- ============================================

-- ============================================
-- 1. ENTIDADE_NIVEL
--    Define os "tipos" de cada camada da árvore.
--    O usuário pode criar quantos níveis quiser.
-- ============================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ENTIDADE_NIVEL' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE SIMP.dbo.ENTIDADE_NIVEL (
        CD_CHAVE         INT IDENTITY(1,1) PRIMARY KEY,
        DS_NOME          VARCHAR(100)  NOT NULL,       -- Ex: "Etapa", "Unidade", "Tag"
        DS_ICONE         VARCHAR(50)   NULL,            -- Ionicon (ex: "water-outline")
        DS_COR           VARCHAR(20)   NULL,            -- Hex (ex: "#1565C0")
        NR_ORDEM         INT           NOT NULL DEFAULT 0,
        OP_PERMITE_PONTO TINYINT       NOT NULL DEFAULT 0, -- 1=nós deste nível vinculam pontos
        OP_ATIVO         TINYINT       NOT NULL DEFAULT 1,
        DT_CADASTRO      DATETIME      DEFAULT GETDATE(),
        DT_ATUALIZACAO   DATETIME      NULL
    );

    PRINT 'Tabela ENTIDADE_NIVEL criada com sucesso!';

    -- Níveis padrão para o fluxo da água
    INSERT INTO SIMP.dbo.ENTIDADE_NIVEL (DS_NOME, DS_ICONE, DS_COR, NR_ORDEM, OP_PERMITE_PONTO)
    VALUES 
        ('Manancial/Captação', 'water-outline',           '#0D47A1', 1, 0),
        ('Unidade Operacional','business-outline',         '#E65100', 2, 0),
        ('Fluxo',              'swap-horizontal-outline',  '#6A1B9A', 3, 0),
        ('Reservação',         'cube-outline',             '#00695C', 4, 0),
        ('Distribuição/Setor', 'git-branch-outline',       '#1B5E20', 5, 0),
        ('Ponto de Medição',   'speedometer-outline',      '#2E7D32', 6, 1);

    PRINT 'Níveis padrão inseridos.';
END
ELSE
BEGIN
    PRINT 'Tabela ENTIDADE_NIVEL já existe.';
END
GO

-- ============================================
-- 2. ENTIDADE_NODO
--    Nó genérico da árvore (auto-referência via CD_PAI).
--    CD_PAI = NULL → nó raiz
--    Nós com OP_PERMITE_PONTO=1 podem vincular PONTO_MEDICAO.
-- ============================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ENTIDADE_NODO' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE SIMP.dbo.ENTIDADE_NODO (
        CD_CHAVE          INT IDENTITY(1,1) PRIMARY KEY,
        CD_PAI            INT           NULL,              -- Auto-referência (NULL=raiz)
        CD_ENTIDADE_NIVEL INT           NOT NULL,          -- FK → ENTIDADE_NIVEL
        DS_NOME           VARCHAR(200)  NOT NULL,
        DS_IDENTIFICADOR  VARCHAR(100)  NULL,              -- Código externo (ex: "ETA-001")
        NR_ORDEM          INT           NOT NULL DEFAULT 0,-- Ordem entre irmãos
        -- Campos para vínculo com ponto de medição
        CD_PONTO_MEDICAO  INT           NULL,              -- FK → PONTO_MEDICAO
        ID_OPERACAO       TINYINT       NULL,              -- 1=Soma, 2=Subtração
        ID_FLUXO          TINYINT       NULL,              -- 1=Entrada, 2=Saída, 3=Municipal, 4=N/A
        DT_INICIO         DATETIME      NULL,              -- Vigência
        DT_FIM            DATETIME      NULL,
        -- Metadados
        DS_OBSERVACAO     VARCHAR(500)  NULL,
        OP_ATIVO          TINYINT       NOT NULL DEFAULT 1,
        DT_CADASTRO       DATETIME      DEFAULT GETDATE(),
        DT_ATUALIZACAO    DATETIME      NULL,
        -- Constraints
        CONSTRAINT FK_NODO_PAI   FOREIGN KEY (CD_PAI)            REFERENCES SIMP.dbo.ENTIDADE_NODO(CD_CHAVE),
        CONSTRAINT FK_NODO_NIVEL FOREIGN KEY (CD_ENTIDADE_NIVEL) REFERENCES SIMP.dbo.ENTIDADE_NIVEL(CD_CHAVE),
        CONSTRAINT FK_NODO_PONTO FOREIGN KEY (CD_PONTO_MEDICAO)  REFERENCES SIMP.dbo.PONTO_MEDICAO(CD_PONTO_MEDICAO)
    );

    CREATE INDEX IX_NODO_PAI   ON SIMP.dbo.ENTIDADE_NODO(CD_PAI);
    CREATE INDEX IX_NODO_NIVEL ON SIMP.dbo.ENTIDADE_NODO(CD_ENTIDADE_NIVEL);
    CREATE INDEX IX_NODO_PONTO ON SIMP.dbo.ENTIDADE_NODO(CD_PONTO_MEDICAO) WHERE CD_PONTO_MEDICAO IS NOT NULL;
    CREATE INDEX IX_NODO_ATIVO ON SIMP.dbo.ENTIDADE_NODO(OP_ATIVO);

    PRINT 'Tabela ENTIDADE_NODO criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela ENTIDADE_NODO já existe.';
END
GO

-- ============================================
-- 3. ENTIDADE_NODO_CONEXAO
--    Grafo dirigido: fluxo físico entre nós.
--    Representa "a água sai do nó A e vai para o nó B".
--    
--    Exemplos:
--      Captação (PCAP01) ──alimenta──► ETA Carapina
--      ETA Carapina (Saída) ──alimenta──► Reservatório Mestre Álvaro
--      Reservatório (Saída) ──alimenta──► Rede Serra
--
--    Um nó pode ter múltiplas origens e múltiplos destinos
--    (ex: 2 ETAs alimentam o mesmo reservatório).
-- ============================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ENTIDADE_NODO_CONEXAO' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE SIMP.dbo.ENTIDADE_NODO_CONEXAO (
        CD_CHAVE      INT IDENTITY(1,1) PRIMARY KEY,
        CD_NODO_ORIGEM  INT NOT NULL,                    -- FK → ENTIDADE_NODO (de onde sai)
        CD_NODO_DESTINO INT NOT NULL,                    -- FK → ENTIDADE_NODO (para onde vai)
        DS_ROTULO       VARCHAR(100)  NULL,              -- Rótulo da conexão (ex: "Adutora DN600")
        DS_COR          VARCHAR(20)   NULL DEFAULT '#1565C0', -- Cor da linha no diagrama
        NR_ORDEM        INT           NOT NULL DEFAULT 0,-- Ordem das conexões
        OP_ATIVO        TINYINT       NOT NULL DEFAULT 1,
        DT_CADASTRO     DATETIME      DEFAULT GETDATE(),
        DT_ATUALIZACAO  DATETIME      NULL,
        -- Constraints
        CONSTRAINT FK_CONEXAO_ORIGEM  FOREIGN KEY (CD_NODO_ORIGEM)  REFERENCES SIMP.dbo.ENTIDADE_NODO(CD_CHAVE),
        CONSTRAINT FK_CONEXAO_DESTINO FOREIGN KEY (CD_NODO_DESTINO) REFERENCES SIMP.dbo.ENTIDADE_NODO(CD_CHAVE),
        CONSTRAINT CK_CONEXAO_DIFF    CHECK (CD_NODO_ORIGEM <> CD_NODO_DESTINO)
    );

    CREATE INDEX IX_CONEXAO_ORIGEM  ON SIMP.dbo.ENTIDADE_NODO_CONEXAO(CD_NODO_ORIGEM);
    CREATE INDEX IX_CONEXAO_DESTINO ON SIMP.dbo.ENTIDADE_NODO_CONEXAO(CD_NODO_DESTINO);

    PRINT 'Tabela ENTIDADE_NODO_CONEXAO criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela ENTIDADE_NODO_CONEXAO já existe.';
END
GO

-- ============================================
-- CORREÇÃO: Views que falharam por prefixo SIMP.dbo
-- Executar no banco SIMP (já com USE SIMP)
-- ============================================

-- 1. View recursiva da árvore
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_ENTIDADE_ARVORE')
    DROP VIEW dbo.VW_ENTIDADE_ARVORE;
GO

CREATE VIEW dbo.VW_ENTIDADE_ARVORE AS
WITH CTE_ARVORE AS (
    -- Nós raiz
    SELECT 
        N.CD_CHAVE, N.CD_PAI, N.CD_ENTIDADE_NIVEL,
        N.DS_NOME, N.DS_IDENTIFICADOR, N.NR_ORDEM,
        N.CD_PONTO_MEDICAO, N.ID_OPERACAO, N.ID_FLUXO,
        N.DT_INICIO, N.DT_FIM, N.OP_ATIVO, N.DS_OBSERVACAO,
        NV.DS_NOME AS DS_NIVEL, NV.DS_ICONE, NV.DS_COR, NV.OP_PERMITE_PONTO,
        1 AS NR_PROFUNDIDADE,
        CAST(N.DS_NOME AS VARCHAR(MAX)) AS DS_CAMINHO,
        CAST(RIGHT('0000' + CAST(N.NR_ORDEM AS VARCHAR), 4) + '-' + CAST(N.CD_CHAVE AS VARCHAR) AS VARCHAR(MAX)) AS DS_ORDENACAO
    FROM SIMP.dbo.ENTIDADE_NODO N
    INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV ON NV.CD_CHAVE = N.CD_ENTIDADE_NIVEL
    WHERE N.CD_PAI IS NULL

    UNION ALL

    -- Filhos (recursivo)
    SELECT 
        N.CD_CHAVE, N.CD_PAI, N.CD_ENTIDADE_NIVEL,
        N.DS_NOME, N.DS_IDENTIFICADOR, N.NR_ORDEM,
        N.CD_PONTO_MEDICAO, N.ID_OPERACAO, N.ID_FLUXO,
        N.DT_INICIO, N.DT_FIM, N.OP_ATIVO, N.DS_OBSERVACAO,
        NV.DS_NOME, NV.DS_ICONE, NV.DS_COR, NV.OP_PERMITE_PONTO,
        A.NR_PROFUNDIDADE + 1,
        CAST(A.DS_CAMINHO + ' > ' + N.DS_NOME AS VARCHAR(MAX)),
        CAST(A.DS_ORDENACAO + '/' + RIGHT('0000' + CAST(N.NR_ORDEM AS VARCHAR), 4) + '-' + CAST(N.CD_CHAVE AS VARCHAR) AS VARCHAR(MAX))
    FROM SIMP.dbo.ENTIDADE_NODO N
    INNER JOIN CTE_ARVORE A ON A.CD_CHAVE = N.CD_PAI
    INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV ON NV.CD_CHAVE = N.CD_ENTIDADE_NIVEL
)
SELECT * FROM CTE_ARVORE
GO

PRINT 'View VW_ENTIDADE_ARVORE criada com sucesso!';
GO

-- 2. View de conexões
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_ENTIDADE_CONEXOES')
    DROP VIEW dbo.VW_ENTIDADE_CONEXOES;
GO

CREATE VIEW dbo.VW_ENTIDADE_CONEXOES AS
SELECT 
    C.CD_CHAVE,
    C.CD_NODO_ORIGEM,
    C.CD_NODO_DESTINO,
    C.DS_ROTULO,
    C.DS_COR,
    C.NR_ORDEM,
    C.OP_ATIVO,
    NO_ORIG.DS_NOME      AS DS_ORIGEM,
    NO_ORIG.DS_IDENTIFICADOR AS DS_ORIGEM_ID,
    NV_ORIG.DS_NOME      AS DS_NIVEL_ORIGEM,
    NV_ORIG.DS_COR       AS DS_COR_ORIGEM,
    NO_DEST.DS_NOME      AS DS_DESTINO,
    NO_DEST.DS_IDENTIFICADOR AS DS_DESTINO_ID,
    NV_DEST.DS_NOME      AS DS_NIVEL_DESTINO,
    NV_DEST.DS_COR       AS DS_COR_DESTINO
FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO C
INNER JOIN SIMP.dbo.ENTIDADE_NODO NO_ORIG ON NO_ORIG.CD_CHAVE = C.CD_NODO_ORIGEM
INNER JOIN SIMP.dbo.ENTIDADE_NODO NO_DEST ON NO_DEST.CD_CHAVE = C.CD_NODO_DESTINO
INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV_ORIG ON NV_ORIG.CD_CHAVE = NO_ORIG.CD_ENTIDADE_NIVEL
INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV_DEST ON NV_DEST.CD_CHAVE = NO_DEST.CD_ENTIDADE_NIVEL
GO

PRINT 'View VW_ENTIDADE_CONEXOES criada com sucesso!';
GO

-- ============================================
-- SIMP - Adicionar posição X/Y para canvas flowchart
-- Executar no banco SIMP
-- ============================================

IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'ENTIDADE_NODO' AND COLUMN_NAME = 'NR_POS_X'
)
BEGIN
    ALTER TABLE SIMP.dbo.ENTIDADE_NODO ADD NR_POS_X INT NULL;
    ALTER TABLE SIMP.dbo.ENTIDADE_NODO ADD NR_POS_Y INT NULL;
    PRINT 'Colunas NR_POS_X e NR_POS_Y adicionadas com sucesso!';
END
ELSE
BEGIN
    PRINT 'Colunas já existem.';
END
GO

-- ============================================
-- SIMP - Sistema de Abastecimento (agrupador N:N)
-- Executar no banco SIMP
-- ============================================

-- Tabela principal de Sistemas
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ENTIDADE_SISTEMA')
BEGIN
    CREATE TABLE SIMP.dbo.ENTIDADE_SISTEMA (
        CD_CHAVE         INT IDENTITY(1,1) PRIMARY KEY,
        DS_NOME          VARCHAR(200) NOT NULL,
        DS_DESCRICAO     VARCHAR(500) NULL,
        DS_COR           VARCHAR(20)  NULL DEFAULT '#2563eb',
        OP_ATIVO         BIT          NOT NULL DEFAULT 1,
        DT_CADASTRO      DATETIME     NOT NULL DEFAULT GETDATE(),
        DT_ATUALIZACAO   DATETIME     NULL
    );
    PRINT 'Tabela ENTIDADE_SISTEMA criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela ENTIDADE_SISTEMA já existe.';
END
GO


-- ============================================
-- SIMP - Flag "É Sistema" no nível
-- Marca qual nível representa Sistema de Água
-- Executar no banco SIMP
-- ============================================

IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'ENTIDADE_NIVEL' AND COLUMN_NAME = 'OP_EH_SISTEMA'
)
BEGIN
    ALTER TABLE SIMP.dbo.ENTIDADE_NIVEL ADD OP_EH_SISTEMA BIT NOT NULL DEFAULT 0;
    PRINT 'Coluna OP_EH_SISTEMA adicionada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Coluna OP_EH_SISTEMA já existe.';
END
GO


-- ============================================
-- SIMP - Flag "É Sistema" no nível
-- Marca qual nível representa Sistema de Água
-- Executar no banco SIMP
-- ============================================

IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'ENTIDADE_NIVEL' AND COLUMN_NAME = 'OP_EH_SISTEMA'
)
BEGIN
    ALTER TABLE SIMP.dbo.ENTIDADE_NIVEL ADD OP_EH_SISTEMA BIT NOT NULL DEFAULT 0;
    PRINT 'Coluna OP_EH_SISTEMA adicionada em ENTIDADE_NIVEL!';
END
GO

IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'ENTIDADE_NODO' AND COLUMN_NAME = 'CD_SISTEMA_AGUA'
)
BEGIN
    ALTER TABLE SIMP.dbo.ENTIDADE_NODO ADD CD_SISTEMA_AGUA INT NULL;

    ALTER TABLE SIMP.dbo.ENTIDADE_NODO 
    ADD CONSTRAINT FK_NODO_SISTEMA_AGUA 
    FOREIGN KEY (CD_SISTEMA_AGUA) REFERENCES SIMP.dbo.SISTEMA_AGUA(CD_CHAVE);

    PRINT 'Coluna CD_SISTEMA_AGUA adicionada em ENTIDADE_NODO!';
END
GO




-- ============================================
-- SIMP - Flag "É Sistema" no nível
-- Marca qual nível representa Sistema de Água
-- Executar no banco SIMP
-- ============================================

IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'ENTIDADE_NIVEL' AND COLUMN_NAME = 'OP_EH_SISTEMA'
)
BEGIN
    ALTER TABLE SIMP.dbo.ENTIDADE_NIVEL ADD OP_EH_SISTEMA BIT NOT NULL DEFAULT 0;
    PRINT 'Coluna OP_EH_SISTEMA adicionada em ENTIDADE_NIVEL!';
END
GO

-- Marcar nível "Sistema de Abastecimento" como sistema (ajuste o nome se necessário)
UPDATE SIMP.dbo.ENTIDADE_NIVEL 
SET OP_EH_SISTEMA = 1 
WHERE DS_NOME LIKE '%Sistema de Abastecimento%' AND OP_EH_SISTEMA = 0;
GO

IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'ENTIDADE_NODO' AND COLUMN_NAME = 'CD_SISTEMA_AGUA'
)
BEGIN
    ALTER TABLE SIMP.dbo.ENTIDADE_NODO ADD CD_SISTEMA_AGUA INT NULL;

    ALTER TABLE SIMP.dbo.ENTIDADE_NODO 
    ADD CONSTRAINT FK_NODO_SISTEMA_AGUA 
    FOREIGN KEY (CD_SISTEMA_AGUA) REFERENCES SIMP.dbo.SISTEMA_AGUA(CD_CHAVE);

    PRINT 'Coluna CD_SISTEMA_AGUA adicionada em ENTIDADE_NODO!';
END
GO


-- Adicionar coluna de diâmetro na tabela de conexões
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'ENTIDADE_NODO_CONEXAO' AND COLUMN_NAME = 'VL_DIAMETRO_REDE'
)
BEGIN
    ALTER TABLE SIMP.dbo.ENTIDADE_NODO_CONEXAO ADD VL_DIAMETRO_REDE DECIMAL(10,2) NULL;
    PRINT 'Coluna VL_DIAMETRO_REDE adicionada em ENTIDADE_NODO_CONEXAO';
END
GO

-- Recriar view para incluir o diâmetro
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_ENTIDADE_CONEXOES')
    DROP VIEW dbo.VW_ENTIDADE_CONEXOES;
GO

CREATE VIEW dbo.VW_ENTIDADE_CONEXOES AS
SELECT 
    C.CD_CHAVE,
    C.CD_NODO_ORIGEM,
    C.CD_NODO_DESTINO,
    C.DS_ROTULO,
    C.DS_COR,
    C.NR_ORDEM,
    C.OP_ATIVO,
    C.VL_DIAMETRO_REDE,
    NO_ORIG.DS_NOME           AS DS_ORIGEM,
    NO_ORIG.DS_IDENTIFICADOR  AS DS_ORIGEM_ID,
    NV_ORIG.DS_NOME           AS DS_NIVEL_ORIGEM,
    NV_ORIG.DS_COR            AS DS_COR_ORIGEM,
    NO_DEST.DS_NOME           AS DS_DESTINO,
    NO_DEST.DS_IDENTIFICADOR  AS DS_DESTINO_ID,
    NV_DEST.DS_NOME           AS DS_NIVEL_DESTINO,
    NV_DEST.DS_COR            AS DS_COR_DESTINO
FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO C
INNER JOIN SIMP.dbo.ENTIDADE_NODO NO_ORIG ON NO_ORIG.CD_CHAVE = C.CD_NODO_ORIGEM
INNER JOIN SIMP.dbo.ENTIDADE_NODO NO_DEST ON NO_DEST.CD_CHAVE = C.CD_NODO_DESTINO
INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV_ORIG ON NV_ORIG.CD_CHAVE = NO_ORIG.CD_ENTIDADE_NIVEL
INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV_DEST ON NV_DEST.CD_CHAVE = NO_DEST.CD_ENTIDADE_NIVEL
GO
-- =============================================================================================================================
-- =============================================================================================================================


-- ============================================
-- SIMP 2.0 — Fase A1: Governanca e Versionamento de Topologia
-- 
-- Objetivo:
--   Rastrear mudancas na topologia do flowchart (nos + conexoes)
--   e vincular cada modelo ML a versao de topologia vigente no treino.
--   Detecta automaticamente quando a topologia muda e invalida modelos.
--
-- Tabelas:
--   VERSAO_TOPOLOGIA  — snapshot com hash SHA-256 dos nos + conexoes
--   MODELO_REGISTRO   — vinculo modelo <-> versao topologia, flag validade
--
-- Dependencias:
--   - ENTIDADE_NODO (existente)
--   - ENTIDADE_NODO_CONEXAO (existente)
--   - PONTO_MEDICAO (existente)
--
-- @author Bruno - CESAN
-- @version 1.0
-- @date 2026-02
-- ============================================

USE SIMP;
GO

-- ============================================
-- 1. VERSAO_TOPOLOGIA
--    Snapshot da topologia em determinado momento.
--    Hash SHA-256 calculado sobre nos ativos + conexoes ativas.
--    Se hash muda, nova versao e criada automaticamente.
-- ============================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'VERSAO_TOPOLOGIA' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE dbo.VERSAO_TOPOLOGIA (
        CD_CHAVE            INT IDENTITY(1,1) PRIMARY KEY,

        -- Hash SHA-256 dos nos + conexoes (detecta mudanca)
        DS_HASH_TOPOLOGIA   VARCHAR(64)   NOT NULL,

        -- Contexto: sistema de abastecimento (NULL = topologia global)
        CD_SISTEMA          INT           NULL,

        -- Contadores no momento do snapshot
        QTD_NOS_ATIVOS      INT           NOT NULL DEFAULT 0,
        QTD_CONEXOES_ATIVAS INT           NOT NULL DEFAULT 0,
        QTD_NOS_COM_PONTO   INT           NOT NULL DEFAULT 0,

        -- Descricao da mudanca (preenchido automaticamente ou pelo operador)
        DS_DESCRICAO        VARCHAR(500)  NULL,

        -- Detalhes da mudanca (JSON com diff: nos adicionados/removidos/alterados)
        DS_DIFF_JSON        VARCHAR(MAX)  NULL,

        -- Auditoria
        CD_USUARIO          INT           NULL,
        DT_CADASTRO         DATETIME      NOT NULL DEFAULT GETDATE()
    );

    -- Indice para busca rapida por hash (verificar se ja existe)
    CREATE INDEX IX_VERSAO_TOPO_HASH 
        ON dbo.VERSAO_TOPOLOGIA(DS_HASH_TOPOLOGIA);

    -- Indice para busca por sistema
    CREATE INDEX IX_VERSAO_TOPO_SISTEMA 
        ON dbo.VERSAO_TOPOLOGIA(CD_SISTEMA);

    -- Indice para ordenacao cronologica
    CREATE INDEX IX_VERSAO_TOPO_DATA 
        ON dbo.VERSAO_TOPOLOGIA(DT_CADASTRO DESC);

    PRINT 'Tabela VERSAO_TOPOLOGIA criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela VERSAO_TOPOLOGIA ja existe.';
END
GO


-- ============================================
-- 2. MODELO_REGISTRO
--    Vinculo entre modelo ML treinado e a versao
--    de topologia vigente no momento do treino.
--    
--    Permite:
--    - Saber se modelo esta desatualizado (topologia mudou)
--    - Historico de treinos por ponto
--    - SLA de retreino configuravel
--    - Auditoria completa de invalidacao
-- ============================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'MODELO_REGISTRO' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE dbo.MODELO_REGISTRO (
        CD_CHAVE                INT IDENTITY(1,1) PRIMARY KEY,

        -- Ponto de medicao vinculado ao modelo
        CD_PONTO_MEDICAO        INT           NOT NULL,

        -- Tipo do modelo (1=XGBoost, 2=GNN, 3=LSTM, 4=Estatistico)
        ID_TIPO_MODELO          TINYINT       NOT NULL DEFAULT 1,

        -- Versao da topologia usada no treino
        CD_VERSAO_TOPOLOGIA     INT           NULL,

        -- Metricas do treino (snapshot no momento do treino)
        VL_R2                   DECIMAL(8,6)  NULL,
        VL_MAE                  DECIMAL(12,4) NULL,
        VL_RMSE                 DECIMAL(12,4) NULL,
        VL_MAPE                 DECIMAL(8,4)  NULL,

        -- Configuracao do treino
        NR_SEMANAS_HISTORICO    INT           NULL,
        NR_AMOSTRAS_TREINO      INT           NULL,
        NR_FEATURES             INT           NULL,
        DS_VERSAO_PIPELINE      VARCHAR(20)   NULL,  -- Ex: 'v6.0', 'v6.1'

        -- Caminho do modelo salvo (relativo ao diretorio de modelos)
        DS_CAMINHO_MODELO       VARCHAR(500)  NULL,

        -- Flag de validade
        OP_VALIDO               BIT           NOT NULL DEFAULT 1,

        -- Motivo da invalidacao (preenchido quando OP_VALIDO = 0)
        DS_MOTIVO_INVALIDACAO   VARCHAR(500)  NULL,

        -- Data da invalidacao
        DT_INVALIDACAO          DATETIME      NULL,

        -- SLA de retreino: dias maximos sem retreino apos mudanca de topologia
        NR_SLA_RETREINO_DIAS    INT           NOT NULL DEFAULT 7,

        -- Auditoria
        CD_USUARIO_TREINO       INT           NULL,
        DT_TREINO               DATETIME      NOT NULL DEFAULT GETDATE(),
        DT_CADASTRO             DATETIME      NOT NULL DEFAULT GETDATE(),

        -- Constraints
        CONSTRAINT FK_MODELO_REG_PONTO 
            FOREIGN KEY (CD_PONTO_MEDICAO) 
            REFERENCES dbo.PONTO_MEDICAO(CD_PONTO_MEDICAO),

        CONSTRAINT FK_MODELO_REG_VERSAO 
            FOREIGN KEY (CD_VERSAO_TOPOLOGIA) 
            REFERENCES dbo.VERSAO_TOPOLOGIA(CD_CHAVE)
    );

    -- Indice principal: buscar modelo ativo por ponto
    CREATE INDEX IX_MODELO_REG_PONTO_VALIDO 
        ON dbo.MODELO_REGISTRO(CD_PONTO_MEDICAO, OP_VALIDO) 
        INCLUDE (CD_VERSAO_TOPOLOGIA, VL_R2, DT_TREINO);

    -- Indice para busca por versao de topologia
    CREATE INDEX IX_MODELO_REG_VERSAO 
        ON dbo.MODELO_REGISTRO(CD_VERSAO_TOPOLOGIA);

    -- Indice para busca por tipo de modelo
    CREATE INDEX IX_MODELO_REG_TIPO 
        ON dbo.MODELO_REGISTRO(ID_TIPO_MODELO);

    -- Indice para SLA: modelos validos ordenados por data de treino
    CREATE INDEX IX_MODELO_REG_SLA 
        ON dbo.MODELO_REGISTRO(OP_VALIDO, DT_TREINO) 
        WHERE OP_VALIDO = 1;

    PRINT 'Tabela MODELO_REGISTRO criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela MODELO_REGISTRO ja existe.';
END
GO


-- ============================================
-- 3. VIEW: VW_MODELO_STATUS
--    Consolida status de cada modelo com info
--    da topologia e calculo de SLA.
--    Usada pelo banner de alerta e dashboard.
-- ============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_MODELO_STATUS')
    DROP VIEW dbo.VW_MODELO_STATUS;
GO

CREATE VIEW dbo.VW_MODELO_STATUS AS
SELECT 
    MR.CD_CHAVE                     AS CD_MODELO_REGISTRO,
    MR.CD_PONTO_MEDICAO,
    PM.DS_NOME                      AS DS_PONTO_NOME,
    PM.ID_TIPO_MEDIDOR,

    -- Tipo do modelo (texto)
    CASE MR.ID_TIPO_MODELO
        WHEN 1 THEN 'XGBoost'
        WHEN 2 THEN 'GNN'
        WHEN 3 THEN 'LSTM'
        WHEN 4 THEN 'Estatistico'
        ELSE 'Desconhecido'
    END                             AS DS_TIPO_MODELO,

    -- Metricas
    MR.VL_R2,
    MR.VL_MAE,
    MR.VL_RMSE,
    MR.VL_MAPE,
    MR.NR_SEMANAS_HISTORICO,
    MR.DS_VERSAO_PIPELINE,

    -- Validade
    MR.OP_VALIDO,
    MR.DS_MOTIVO_INVALIDACAO,
    MR.DT_INVALIDACAO,
    MR.DT_TREINO,

    -- Versao da topologia usada no treino
    MR.CD_VERSAO_TOPOLOGIA,
    VT.DS_HASH_TOPOLOGIA            AS DS_HASH_TREINO,
    VT.DT_CADASTRO                  AS DT_VERSAO_TREINO,
    VT.QTD_NOS_ATIVOS               AS QTD_NOS_NO_TREINO,

    -- Versao atual da topologia (mais recente do mesmo sistema)
    VT_ATUAL.CD_CHAVE               AS CD_VERSAO_ATUAL,
    VT_ATUAL.DS_HASH_TOPOLOGIA      AS DS_HASH_ATUAL,
    VT_ATUAL.DT_CADASTRO            AS DT_VERSAO_ATUAL,

    -- Flag: topologia mudou desde o treino?
    CASE 
        WHEN VT.DS_HASH_TOPOLOGIA IS NULL THEN NULL
        WHEN VT_ATUAL.DS_HASH_TOPOLOGIA IS NULL THEN NULL
        WHEN VT.DS_HASH_TOPOLOGIA <> VT_ATUAL.DS_HASH_TOPOLOGIA THEN 1
        ELSE 0
    END                             AS FL_TOPOLOGIA_ALTERADA,

    -- Dias desde o treino
    DATEDIFF(DAY, MR.DT_TREINO, GETDATE()) AS NR_DIAS_DESDE_TREINO,

    -- SLA: dias restantes para retreino (negativo = vencido)
    CASE 
        WHEN VT.DS_HASH_TOPOLOGIA IS NOT NULL 
         AND VT_ATUAL.DS_HASH_TOPOLOGIA IS NOT NULL
         AND VT.DS_HASH_TOPOLOGIA <> VT_ATUAL.DS_HASH_TOPOLOGIA
        THEN MR.NR_SLA_RETREINO_DIAS - DATEDIFF(DAY, VT_ATUAL.DT_CADASTRO, GETDATE())
        ELSE NULL
    END                             AS NR_DIAS_SLA_RESTANTES,

    -- Status consolidado
    CASE 
        WHEN MR.OP_VALIDO = 0 THEN 'INVALIDADO'
        WHEN VT.DS_HASH_TOPOLOGIA IS NULL THEN 'SEM_VERSAO'
        WHEN VT_ATUAL.DS_HASH_TOPOLOGIA IS NULL THEN 'SEM_VERSAO'
        WHEN VT.DS_HASH_TOPOLOGIA <> VT_ATUAL.DS_HASH_TOPOLOGIA 
         AND DATEDIFF(DAY, VT_ATUAL.DT_CADASTRO, GETDATE()) > MR.NR_SLA_RETREINO_DIAS
        THEN 'SLA_VENCIDO'
        WHEN VT.DS_HASH_TOPOLOGIA <> VT_ATUAL.DS_HASH_TOPOLOGIA
        THEN 'DESATUALIZADO'
        ELSE 'ATUALIZADO'
    END                             AS DS_STATUS_MODELO

FROM dbo.MODELO_REGISTRO MR

-- Ponto de medicao
INNER JOIN dbo.PONTO_MEDICAO PM 
    ON PM.CD_PONTO_MEDICAO = MR.CD_PONTO_MEDICAO

-- Versao usada no treino
LEFT JOIN dbo.VERSAO_TOPOLOGIA VT 
    ON VT.CD_CHAVE = MR.CD_VERSAO_TOPOLOGIA

-- Versao atual: mais recente do mesmo sistema (ou global se NULL)
OUTER APPLY (
    SELECT TOP 1 
        V2.CD_CHAVE,
        V2.DS_HASH_TOPOLOGIA,
        V2.DT_CADASTRO
    FROM dbo.VERSAO_TOPOLOGIA V2
    WHERE ISNULL(V2.CD_SISTEMA, 0) = ISNULL(VT.CD_SISTEMA, 0)
    ORDER BY V2.DT_CADASTRO DESC
) VT_ATUAL

GO

PRINT 'View VW_MODELO_STATUS criada com sucesso!';
GO


-- ============================================
-- 4. VIEW: VW_TOPOLOGIA_RESUMO
--    Resumo da ultima versao de topologia por sistema.
--    Util para tela de modelos e dashboard.
-- ============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_TOPOLOGIA_RESUMO')
    DROP VIEW dbo.VW_TOPOLOGIA_RESUMO;
GO

CREATE VIEW dbo.VW_TOPOLOGIA_RESUMO AS
SELECT 
    VT.CD_CHAVE,
    VT.CD_SISTEMA,
    VT.DS_HASH_TOPOLOGIA,
    VT.QTD_NOS_ATIVOS,
    VT.QTD_CONEXOES_ATIVAS,
    VT.QTD_NOS_COM_PONTO,
    VT.DS_DESCRICAO,
    VT.DT_CADASTRO,

    -- Total de versoes deste sistema
    (SELECT COUNT(*) 
     FROM dbo.VERSAO_TOPOLOGIA V2 
     WHERE ISNULL(V2.CD_SISTEMA, 0) = ISNULL(VT.CD_SISTEMA, 0)
    ) AS QTD_VERSOES_TOTAL,

    -- Modelos ativos vinculados a esta versao
    (SELECT COUNT(*) 
     FROM dbo.MODELO_REGISTRO MR 
     WHERE MR.CD_VERSAO_TOPOLOGIA = VT.CD_CHAVE 
       AND MR.OP_VALIDO = 1
    ) AS QTD_MODELOS_ATIVOS,

    -- Modelos desatualizados (treinados em versao anterior)
    (SELECT COUNT(*) 
     FROM dbo.MODELO_REGISTRO MR2
     INNER JOIN dbo.VERSAO_TOPOLOGIA VT2 ON VT2.CD_CHAVE = MR2.CD_VERSAO_TOPOLOGIA
     WHERE MR2.OP_VALIDO = 1
       AND ISNULL(VT2.CD_SISTEMA, 0) = ISNULL(VT.CD_SISTEMA, 0)
       AND VT2.DS_HASH_TOPOLOGIA <> VT.DS_HASH_TOPOLOGIA
    ) AS QTD_MODELOS_DESATUALIZADOS

FROM dbo.VERSAO_TOPOLOGIA VT
WHERE VT.CD_CHAVE = (
    SELECT TOP 1 V3.CD_CHAVE 
    FROM dbo.VERSAO_TOPOLOGIA V3
    WHERE ISNULL(V3.CD_SISTEMA, 0) = ISNULL(VT.CD_SISTEMA, 0)
    ORDER BY V3.DT_CADASTRO DESC
)
GO

PRINT 'View VW_TOPOLOGIA_RESUMO criada com sucesso!';
GO


-- ============================================
-- 5. STORED PROCEDURE: SP_GERAR_HASH_TOPOLOGIA
--    Calcula hash SHA-256 da topologia atual.
--    Compara com ultimo hash. Se diferente, grava nova versao.
--    Chamada pelo PHP ao salvar nodo/conexao.
--
--    Parametros:
--    @CD_SISTEMA  — NULL para global, ou CD do sistema
--    @CD_USUARIO  — Usuario que disparou a acao
--    @DS_DESCRICAO — Descricao da mudanca (opcional)
--    @NOVA_VERSAO OUTPUT — 1 se criou nova versao, 0 se hash igual
-- ============================================
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_GERAR_HASH_TOPOLOGIA')
    DROP PROCEDURE dbo.SP_GERAR_HASH_TOPOLOGIA;
GO

CREATE PROCEDURE dbo.SP_GERAR_HASH_TOPOLOGIA
    @CD_SISTEMA   INT           = NULL,
    @CD_USUARIO   INT           = NULL,
    @DS_DESCRICAO VARCHAR(500)  = NULL,
    @NOVA_VERSAO  BIT           OUTPUT
AS
BEGIN
    SET NOCOUNT ON;

    -- ========================================
    -- 1. Montar string canonica da topologia
    --    Nos: CD_CHAVE|CD_ENTIDADE_NIVEL|CD_PONTO_MEDICAO|OP_ATIVO
    --    Conexoes: CD_NODO_ORIGEM|CD_NODO_DESTINO|OP_ATIVO
    --    Ordenados por CD_CHAVE para determinismo
    -- ========================================
    DECLARE @TOPO_STRING VARCHAR(MAX) = '';

    -- Nos ativos (filtrar por sistema se informado)
    SELECT @TOPO_STRING = @TOPO_STRING + 
        'N:' + CAST(N.CD_CHAVE AS VARCHAR) + '|' +
        CAST(N.CD_ENTIDADE_NIVEL AS VARCHAR) + '|' +
        ISNULL(CAST(N.CD_PONTO_MEDICAO AS VARCHAR), 'NULL') + '|' +
        CAST(N.OP_ATIVO AS VARCHAR) + ';'
    FROM dbo.ENTIDADE_NODO N
    WHERE N.OP_ATIVO = 1
      AND (@CD_SISTEMA IS NULL OR N.CD_CHAVE IN (
          -- Nos do sistema: descendentes via conexoes
          SELECT CD_NODO_DESTINO FROM dbo.ENTIDADE_NODO_CONEXAO WHERE OP_ATIVO = 1
          UNION
          SELECT CD_NODO_ORIGEM FROM dbo.ENTIDADE_NODO_CONEXAO WHERE OP_ATIVO = 1
      ))
    ORDER BY N.CD_CHAVE;

    -- Conexoes ativas
    SELECT @TOPO_STRING = @TOPO_STRING + 
        'C:' + CAST(C.CD_NODO_ORIGEM AS VARCHAR) + '|' +
        CAST(C.CD_NODO_DESTINO AS VARCHAR) + '|' +
        CAST(C.OP_ATIVO AS VARCHAR) + ';'
    FROM dbo.ENTIDADE_NODO_CONEXAO C
    WHERE C.OP_ATIVO = 1
    ORDER BY C.CD_CHAVE;

    -- ========================================
    -- 2. Calcular SHA-256
    -- ========================================
    DECLARE @HASH VARCHAR(64);
    SET @HASH = CONVERT(VARCHAR(64), HASHBYTES('SHA2_256', @TOPO_STRING), 2);

    -- ========================================
    -- 3. Comparar com ultimo hash do mesmo sistema
    -- ========================================
    DECLARE @ULTIMO_HASH VARCHAR(64);
    SELECT TOP 1 @ULTIMO_HASH = DS_HASH_TOPOLOGIA
    FROM dbo.VERSAO_TOPOLOGIA
    WHERE ISNULL(CD_SISTEMA, 0) = ISNULL(@CD_SISTEMA, 0)
    ORDER BY DT_CADASTRO DESC;

    -- ========================================
    -- 4. Se hash diferente, gravar nova versao
    -- ========================================
    IF @ULTIMO_HASH IS NULL OR @HASH <> @ULTIMO_HASH
    BEGIN
        -- Contadores
        DECLARE @QTD_NOS INT, @QTD_CONEXOES INT, @QTD_COM_PONTO INT;

        SELECT @QTD_NOS = COUNT(*) FROM dbo.ENTIDADE_NODO WHERE OP_ATIVO = 1;
        SELECT @QTD_CONEXOES = COUNT(*) FROM dbo.ENTIDADE_NODO_CONEXAO WHERE OP_ATIVO = 1;
        SELECT @QTD_COM_PONTO = COUNT(*) FROM dbo.ENTIDADE_NODO WHERE OP_ATIVO = 1 AND CD_PONTO_MEDICAO IS NOT NULL;

        INSERT INTO dbo.VERSAO_TOPOLOGIA (
            DS_HASH_TOPOLOGIA,
            CD_SISTEMA,
            QTD_NOS_ATIVOS,
            QTD_CONEXOES_ATIVAS,
            QTD_NOS_COM_PONTO,
            DS_DESCRICAO,
            CD_USUARIO,
            DT_CADASTRO
        )
        VALUES (
            @HASH,
            @CD_SISTEMA,
            @QTD_NOS,
            @QTD_CONEXOES,
            @QTD_COM_PONTO,
            ISNULL(@DS_DESCRICAO, 'Snapshot automatico - topologia alterada'),
            @CD_USUARIO,
            GETDATE()
        );

        SET @NOVA_VERSAO = 1;
        PRINT 'Nova versao de topologia registrada. Hash: ' + @HASH;
    END
    ELSE
    BEGIN
        SET @NOVA_VERSAO = 0;
        PRINT 'Topologia inalterada. Hash: ' + @HASH;
    END
END
GO

PRINT 'SP SP_GERAR_HASH_TOPOLOGIA criada com sucesso!';
GO


-- ============================================
-- 6. STORED PROCEDURE: SP_VERIFICAR_MODELOS_DESATUALIZADOS
--    Retorna modelos que precisam de retreino.
--    Usada pelo banner de alerta na tela de modelos.
-- ============================================
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_VERIFICAR_MODELOS_DESATUALIZADOS')
    DROP PROCEDURE dbo.SP_VERIFICAR_MODELOS_DESATUALIZADOS;
GO

CREATE PROCEDURE dbo.SP_VERIFICAR_MODELOS_DESATUALIZADOS
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        CD_MODELO_REGISTRO,
        CD_PONTO_MEDICAO,
        DS_PONTO_NOME,
        DS_TIPO_MODELO,
        VL_R2,
        DT_TREINO,
        DS_STATUS_MODELO,
        FL_TOPOLOGIA_ALTERADA,
        NR_DIAS_DESDE_TREINO,
        NR_DIAS_SLA_RESTANTES
    FROM dbo.VW_MODELO_STATUS
    WHERE OP_VALIDO = 1
      AND DS_STATUS_MODELO IN ('DESATUALIZADO', 'SLA_VENCIDO')
    ORDER BY 
        -- SLA vencido primeiro, depois por dias restantes
        CASE DS_STATUS_MODELO WHEN 'SLA_VENCIDO' THEN 0 ELSE 1 END,
        NR_DIAS_SLA_RESTANTES ASC;
END
GO

PRINT 'SP SP_VERIFICAR_MODELOS_DESATUALIZADOS criada com sucesso!';
GO


-- ============================================
-- 7. Gerar snapshot inicial da topologia atual
--    (primeira versao de referencia)
-- ============================================
DECLARE @NOVA BIT;
EXEC dbo.SP_GERAR_HASH_TOPOLOGIA 
    @CD_SISTEMA = NULL, 
    @CD_USUARIO = NULL, 
    @DS_DESCRICAO = 'Snapshot inicial - implantacao Fase A1',
    @NOVA_VERSAO = @NOVA OUTPUT;

IF @NOVA = 1
    PRINT 'Snapshot inicial da topologia gravado com sucesso!';
ELSE
    PRINT 'Topologia ja possuia snapshot.';
GO


PRINT '';
PRINT '================================================';
PRINT 'Fase A1 - Governanca e Versionamento: SQL OK';
PRINT '================================================';
GO