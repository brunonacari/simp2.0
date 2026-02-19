"""
Módulo de conexão com o banco FINDESLAB para buscar dados das tags auxiliares.
Usado pelo predictor para montar as features de entrada do modelo.

v5.0 - XGBoost:
  Muito mais simples que LSTM. Precisa apenas das últimas 6-7 horas
  de dados das tags auxiliares para montar as features com lags.
  
  Sem normalização (XGBoost trabalha com escala real).
  Sem janela de 168h.
  Sem sequências.

@author Bruno - CESAN
@version 5.0
@date 2026-02
"""

import os
import logging
import pyodbc
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from typing import List, Optional

logger = logging.getLogger('simp-tensorflow.findeslab')


def get_findeslab_connection() -> Optional[pyodbc.Connection]:
    """
    Conecta ao banco FINDESLAB usando variáveis de ambiente.
    Retorna None se as variáveis não estiverem configuradas.
    """
    host = os.environ.get('FINDESLAB_HOST')
    db = os.environ.get('FINDESLAB_DB', 'FINDESLAB')
    user = os.environ.get('simp')
    password = os.environ.get('cesan')

    if not host:
        logger.warning("FINDESLAB_HOST não configurado")
        return None

    try:
        driver = '{ODBC Driver 18 for SQL Server}'
        available = pyodbc.drivers()
        if 'ODBC Driver 18 for SQL Server' not in available:
            if 'ODBC Driver 17 for SQL Server' in available:
                driver = '{ODBC Driver 17 for SQL Server}'
            else:
                logger.error(f"Nenhum driver ODBC encontrado. Disponíveis: {available}")
                return None

        conn_str = (
            f"DRIVER={driver};"
            f"SERVER={host};"
            f"DATABASE={db};"
            f"UID={user};"
            f"PWD={password};"
            f"TrustServerCertificate=yes;"
            f"Connection Timeout=30;"
        )
        conn = pyodbc.connect(conn_str)
        logger.info(f"Conectado ao FINDESLAB ({host})")
        return conn
    except Exception as e:
        logger.error(f"Erro ao conectar FINDESLAB: {e}")
        return None


def buscar_dados_tags_recentes(
    tags: List[str],
    horas_necessarias: int = 12,
    data_alvo: str = None
) -> Optional[pd.DataFrame]:
    """
    Busca dados horários recentes das tags auxiliares no FINDESLAB.
    
    v5.0: Busca apenas as últimas ~12 horas (suficiente para lags de 0,1,3,6).
    Muito mais rápido que buscar semanas inteiras.

    Args:
        tags: Lista de TagNames das auxiliares
        horas_necessarias: Horas para buscar (default 12, cobre lag máximo de 6)

    Returns:
        DataFrame com colunas: data_hora, tag, valor
    """
    conn = get_findeslab_connection()
    if conn is None:
        return None

    try:
        # Buscar 2x as horas necessárias para cobrir gaps
        if data_alvo:
            # Buscar dados do dia solicitado (00:00 até 23:59) + horas extras para lags
            data_ref = datetime.strptime(data_alvo, '%Y-%m-%d')
            data_inicio = data_ref - timedelta(hours=horas_necessarias)
            data_fim = data_ref + timedelta(hours=24)
        else:
            data_inicio = datetime.now() - timedelta(hours=horas_necessarias * 2)
            data_fim = None
        tags_sql = ','.join([f"'{t}'" for t in tags])

        sql = f"""
            SELECT 
                CAST(CAST([DateTime] AS DATE) AS DATETIME) 
                    + CAST(DATEPART(HOUR, [DateTime]) AS FLOAT) / 24.0 AS data_hora,
                [TagName] AS tag,
                AVG(CAST([Value] AS FLOAT)) AS valor
            FROM [FINDESLAB].[cco].[RawDataIntouch]
            WHERE [TagName] IN ({tags_sql})
              AND [Value] IS NOT NULL
              AND [DateTime] >= ?
              AND ([DateTime] < ? OR ? IS NULL)
            GROUP BY 
                CAST(CAST([DateTime] AS DATE) AS DATETIME) 
                    + CAST(DATEPART(HOUR, [DateTime]) AS FLOAT) / 24.0,
                [TagName]
            ORDER BY data_hora DESC, [TagName]
        """

        cursor = conn.cursor()
        cursor.execute(sql, data_inicio, data_fim, data_fim)
        rows = cursor.fetchall()
        conn.close()

        if not rows:
            logger.warning("Nenhum dado recente encontrado no FINDESLAB")
            return None

        df = pd.DataFrame(
            [list(row) for row in rows],
            columns=['data_hora', 'tag', 'valor']
        )
        df['data_hora'] = pd.to_datetime(df['data_hora'])
        df['valor'] = pd.to_numeric(df['valor'], errors='coerce')

        logger.info(f"FINDESLAB: {len(df)} registros recentes de {len(tags)} tags")
        return df

    except Exception as e:
        logger.error(f"Erro ao buscar dados FINDESLAB: {e}")
        try:
            conn.close()
        except:
            pass
        return None


def montar_features_xgboost(
    dados: pd.DataFrame,
    tags_auxiliares: List[str],
    feature_names: List[str],
    lags: List[int],
    hora_alvo: int = None
) -> Optional[pd.DataFrame]:
    """
    Monta features tabulares para XGBoost a partir dos dados recentes.
    
    v5.0: Cada linha = 1 hora. Colunas = auxiliares com lags + temporais.
    Sem normalização (XGBoost trabalha com escala real).
    
    Args:
        dados: DataFrame com colunas [data_hora, tag, valor]
        tags_auxiliares: Lista de tags auxiliares
        feature_names: Lista de nomes das features (do metricas.json)
        lags: Lista de lags [0, 1, 3, 6]
        hora_alvo: Se definido, retorna features apenas para esta hora

    Returns:
        DataFrame com features prontas para predição ou None
    """
    # Pivotar: cada tag vira coluna
    pivot = dados.pivot_table(
        index='data_hora',
        columns='tag',
        values='valor',
        aggfunc='mean'
    )

    # Ordenar cronologicamente
    pivot = pivot.sort_index()

    # Interpolar gaps curtos
    pivot = pivot.interpolate(method='linear', limit=3)
    pivot = pivot.ffill(limit=2).bfill(limit=2)

    # Montar features na MESMA ORDEM do treino
    features = pd.DataFrame(index=pivot.index)

    for fname in feature_names:
        if fname.startswith('aux_'):
            # Extrair tag e lag do nome: aux_TAGNAME_t0 → TAGNAME, 0
            # O nome tem formato: aux_{tag}_{tN}
            # Precisamos encontrar o lag (último _tN)
            parts = fname.rsplit('_t', 1)
            if len(parts) == 2:
                tag_aux = parts[0][4:]  # Remover 'aux_'
                try:
                    lag = int(parts[1])
                except ValueError:
                    lag = 0
            else:
                tag_aux = fname[4:]
                lag = 0

            if tag_aux in pivot.columns:
                features[fname] = pivot[tag_aux].shift(lag)
            else:
                # Tag sem dados recentes — preencher com NaN (XGBoost lida nativamente)
                features[fname] = np.nan
                logger.warning(f"Tag auxiliar '{tag_aux}' sem dados recentes")

        elif fname == 'hora_sin':
            features[fname] = np.sin(2 * np.pi * features.index.hour / 24)
        elif fname == 'hora_cos':
            features[fname] = np.cos(2 * np.pi * features.index.hour / 24)
        elif fname == 'dia_sem_sin':
            features[fname] = np.sin(2 * np.pi * features.index.dayofweek / 7)
        elif fname == 'dia_sem_cos':
            features[fname] = np.cos(2 * np.pi * features.index.dayofweek / 7)

    if features.empty:
        logger.error("Nenhuma feature montada")
        return None

    # Retornar última linha (mais recente) para predição
    # XGBoost precisa de apenas 1 linha por predição
    features = features.dropna(how='all')

    if features.empty:
        logger.error("Todas as linhas de features são NaN")
        return None

    logger.info(f"Features XGBoost montadas: {features.shape[0]} linhas × {features.shape[1]} colunas")
    return features