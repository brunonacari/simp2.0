# Diagnóstico de Saúde de Medidores em Sistema de Macromedição

Este documento descreve regras práticas para identificar se os dados de
macromedição indicam funcionamento normal do equipamento, falhas de
comunicação ou possíveis defeitos no medidor, utilizando apenas a
análise dos dados históricos do sistema historiador.

------------------------------------------------------------------------

## 1. Integridade do Dado (Comunicação / Telemetria)

Essas regras indicam problemas de rádio, bateria, datalogger ou
integração.

### 1.1. Dado não atualiza (freeze)

**Regra:**\
Diferença entre data atual e última leitura maior que 2x o intervalo
esperado.

**Indicativo de:** - Falha de comunicação - Medidor desligado - Bateria
fraca

------------------------------------------------------------------------

### 1.2. Valor constante por longo período

**Regra:**\
Em uma janela de tempo, todos os valores são iguais.

**Indicativo de:** - Sensor travado - Conversor analógico com problema -
Medidor mecânico parado

------------------------------------------------------------------------

### 1.3. Leituras nulas ou zero suspeitas

**Regra:**\
Valor passa a ser zero em ponto que historicamente nunca zera.

**Indicativo de:** - Medidor travado - Obstrução - Falha interna do
equipamento

------------------------------------------------------------------------

## 2. Validação Física / Hidráulica

Verifica se os dados fazem sentido fisicamente.

### 2.1. Fora da faixa operacional

**Regra:** - Vazão \< 0 - Vazão \> capacidade nominal do medidor

**Indicativo de:** - Erro de escala - Configuração incorreta - Falha de
sensor

------------------------------------------------------------------------

### 2.2. Saltos abruptos (spikes)

**Regra:**\
Variação percentual acima de limite configurado entre leituras
consecutivas.

**Indicativo de:** - Ruído elétrico - Falha de transmissão - Reset do
equipamento

------------------------------------------------------------------------

### 2.3. Incompatibilidade entre grandezas

**Exemplos:** - Vazão alta com pressão zero - Nível subindo com vazão de
saída maior que entrada

**Indicativo de:** - Sensor com defeito - Erro de instrumentação

------------------------------------------------------------------------

## 3. Consistência Temporal e Operacional

### 3.1. Perfil diário esperado

Cada ponto possui comportamento típico (picos e vales).

**Alerta quando:** - Perfil some - Linha reta contínua - Patamar fixo
prolongado

**Técnicas:** - Média móvel - Comparação com semana anterior - Desvio
padrão por horário

------------------------------------------------------------------------

### 3.2. Balanço de zona

**Regra:**\
Medidor de entrada ≈ soma dos medidores de saída + perdas.

**Diagnóstico:** - Entrada ok, saídas zeradas → falha comunicação
secundária - Entrada zerada, saídas ok → falha medidor principal

------------------------------------------------------------------------

## 4. Diagnóstico Avançado

### 4.1. Score de Saúde do Medidor

  Regra                      Pontos
  -------------------------- --------
  Atualização dentro prazo   +2
  Sem congelamento           +2
  Dentro da faixa            +2
  Sem spikes                 +2
  Balanço compatível         +2

**Classificação:** - Score ≥ 8 → Normal - Score 5 a 7 → Suspeito - Score
\< 5 → Falha provável

------------------------------------------------------------------------

### 4.2. Detecção Automática de Anomalias

Possíveis abordagens: - Z-score - IQR (Intervalo Interquartil) - Modelos
simples de séries temporais

Ferramentas: - Backend (PHP, Python) - Power BI - Jobs SQL

------------------------------------------------------------------------

## 5. Diferença entre Falha de Comunicação e Falha do Medidor

### Falha de Comunicação:

-   Dados ausentes
-   Timestamp não atualiza

### Falha do Medidor:

-   Dados presentes porém constantes
-   Valores incoerentes fisicamente
-   Balanço hidráulico não fecha

------------------------------------------------------------------------

## 6. Implementação no Sistema

### No Banco de Dados (SQL)

-   Jobs para gerar flags:
    -   flag_sem_comunicacao
    -   flag_valor_constante
    -   flag_fora_faixa
    -   flag_spike

### No Backend

-   Validação ao importar dados
-   Tabela de status por tag

### Em Dashboards (Power BI)

-   Medidas de qualidade do dado
-   Indicadores visuais por medidor e por zona

------------------------------------------------------------------------

## Conclusão

Mesmo sem alarmes nativos do equipamento, é possível diagnosticar com
boa precisão: - Problemas de comunicação - Falhas de sensores -
Problemas hidráulicos - Desvios de medição

A combinação de regras simples já cobre a maioria dos casos em sistemas
de macromedição.
