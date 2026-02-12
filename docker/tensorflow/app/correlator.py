"""
SIMP TensorFlow - Correlação entre Pontos de Medição
Identifica relações entre pontos e sugere fórmulas de substituição.

Ex: Se ponto A (entrada) e ponto B (saída) de um mesmo setor 
tem correlação alta, podemos estimar B a partir de A quando B falhar.

@author Bruno - CESAN
@version 1.0
"""

import logging
from typing import Dict, List, Optional, Any

import numpy as np
import pandas as pd
from sklearn.linear_model import LinearRegression
from sklearn.metrics import r2_score

logger = logging.getLogger('simp-tensorflow.correlator')


class PointCorrelator:
    """
    Analisa correlação entre pontos de medição e sugere
    fórmulas de substituição (regressão linear, proporcional, etc.).
    """

    # R² mínimo para considerar uma correlação válida
    MIN_R2 = 0.6
    # Mínimo de dados simultâneos para análise
    MIN_DADOS = 100

    def analyze(
        self,
        cd_ponto_origem: int,
        dados_pontos: Dict[int, Optional[pd.DataFrame]]
    ) -> Dict[str, Any]:
        """
        Analisa correlação do ponto origem com cada ponto candidato.

        Para cada par, ajusta uma regressão linear:
            valor_origem ≈ a × valor_candidato + b

        Args:
            cd_ponto_origem: Ponto que precisa de substituição
            dados_pontos: Dict {cd_ponto: DataFrame} com dados de todos os pontos

        Returns:
            Dict com correlacoes, melhor_formula, melhor_r2
        """
        dados_origem = dados_pontos.get(cd_ponto_origem)

        if dados_origem is None or dados_origem.empty:
            return {
                'correlacoes': [],
                'melhor_formula': None,
                'melhor_r2': 0.0
            }

        correlacoes = []

        for cd_candidato, dados_cand in dados_pontos.items():
            if cd_candidato == cd_ponto_origem:
                continue

            if dados_cand is None or dados_cand.empty:
                continue

            resultado = self._calcular_correlacao(
                cd_ponto_origem, cd_candidato,
                dados_origem, dados_cand
            )

            if resultado is not None:
                correlacoes.append(resultado)

        # Ordenar por R² decrescente
        correlacoes.sort(key=lambda x: x['r2'], reverse=True)

        # Identificar melhor fórmula
        melhor_formula = None
        melhor_r2 = 0.0

        if correlacoes and correlacoes[0]['r2'] >= self.MIN_R2:
            melhor = correlacoes[0]
            melhor_formula = melhor['formula']
            melhor_r2 = melhor['r2']

        return {
            'correlacoes': correlacoes,
            'melhor_formula': melhor_formula,
            'melhor_r2': round(float(melhor_r2), 4)
        }

    def _calcular_correlacao(
        self,
        cd_origem: int,
        cd_candidato: int,
        dados_origem: pd.DataFrame,
        dados_candidato: pd.DataFrame
    ) -> Optional[Dict]:
        """
        Calcula correlação entre dois pontos via regressão linear.

        Faz merge dos dados por (data, hora) para comparar apenas
        os horários em que ambos têm dados válidos.

        Args:
            cd_origem: Código do ponto origem
            cd_candidato: Código do ponto candidato
            dados_origem: DataFrame do ponto origem
            dados_candidato: DataFrame do ponto candidato

        Returns:
            Dict com cd_ponto, r2, coeficiente, intercepto, formula ou None
        """
        try:
            # Merge por data e hora
            df_o = dados_origem[['data', 'hora', 'valor']].copy()
            df_c = dados_candidato[['data', 'hora', 'valor']].copy()

            df_o.columns = ['data', 'hora', 'valor_origem']
            df_c.columns = ['data', 'hora', 'valor_candidato']

            merged = pd.merge(df_o, df_c, on=['data', 'hora'], how='inner')

            # Remover NaN e zeros
            merged = merged.dropna(subset=['valor_origem', 'valor_candidato'])
            merged = merged[
                (merged['valor_origem'] != 0) &
                (merged['valor_candidato'] != 0)
            ]

            if len(merged) < self.MIN_DADOS:
                logger.debug(
                    f"Dados insuficientes entre {cd_origem} e {cd_candidato}: "
                    f"{len(merged)} (mínimo: {self.MIN_DADOS})"
                )
                return None

            X = merged['valor_candidato'].values.reshape(-1, 1)
            y = merged['valor_origem'].values

            # Regressão linear
            reg = LinearRegression()
            reg.fit(X, y)

            y_pred = reg.predict(X)
            r2 = r2_score(y, y_pred)

            coef = float(reg.coef_[0])
            intercepto = float(reg.intercept_)

            # Montar fórmula legível
            if abs(intercepto) < 0.01:
                formula = f"P{cd_origem} = {coef:.4f} × P{cd_candidato}"
            elif intercepto > 0:
                formula = f"P{cd_origem} = {coef:.4f} × P{cd_candidato} + {intercepto:.2f}"
            else:
                formula = f"P{cd_origem} = {coef:.4f} × P{cd_candidato} - {abs(intercepto):.2f}"

            # Calcular erro médio
            erro_medio = float(np.mean(np.abs(y - y_pred)))

            # Correlação de Pearson
            pearson = float(np.corrcoef(merged['valor_origem'], merged['valor_candidato'])[0, 1])

            return {
                'cd_ponto_candidato': cd_candidato,
                'r2': round(float(r2), 4),
                'coeficiente': round(coef, 4),
                'intercepto': round(intercepto, 2),
                'formula': formula,
                'pearson': round(pearson, 4),
                'erro_medio': round(erro_medio, 2),
                'dados_comuns': len(merged),
                'qualidade': self._classificar_qualidade(r2)
            }

        except Exception as e:
            logger.warning(
                f"Erro na correlação {cd_origem} vs {cd_candidato}: {e}"
            )
            return None

    @staticmethod
    def _classificar_qualidade(r2: float) -> str:
        """Classifica a qualidade da correlação."""
        if r2 >= 0.95:
            return 'excelente'
        elif r2 >= 0.85:
            return 'boa'
        elif r2 >= 0.70:
            return 'moderada'
        elif r2 >= 0.50:
            return 'fraca'
        else:
            return 'insuficiente'