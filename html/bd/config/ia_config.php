<?php
/** sk-7f2ed60cf610413786b68a319cc1cdf4
 * SIMP - Configuração da API de Inteligência Artificial
 * 
 * Configurações para integração com DeepSeek, Groq e Gemini
 */

return [
    // Provedor de IA: 'deepseek', 'groq' ou 'gemini'
    'provider' => 'deepseek',
    
    // === DEEPSEEK (Recomendado - Barato e Inteligente) ===
    'deepseek' => [
        'api_key' => 'sk-7f2ed60cf610413786b68a319cc1cdf4', // Obtenha em https://platform.deepseek.com/api_keys
        'model' => 'deepseek-chat', // Modelo principal (deepseek-chat ou deepseek-reasoner)
        'api_url' => 'https://api.deepseek.com/v1/chat/completions',
    ],
    
    // === GROQ (Gratuito e Rápido) ===
    'groq' => [
        'api_key' => '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',
        'model' => 'llama-3.1-8b-instant',
        'api_url' => 'https://api.groq.com/openai/v1/chat/completions',
    ],
    
    // === GEMINI (Backup) ===
    'gemini' => [
        'api_key' => 'AIzaSyBm6UYYv4ihRojksNFmYWyb3ePQ2xekhtE',
        'model' => 'gemini-2.0-flash-lite',
        'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/',
    ],
    
    // Regras padrão para análise de dados
    'regras' => [
        'nivel_reservatorio' => [
            'Se o nível do reservatório estiver >= 100% por mais de 30 minutos consecutivos, sugerir registro de extravasamento com motivo "Extravasou".',
            'Se o nível estiver em 0% ou sem leitura por mais de 1 hora, sugerir verificação de falha no sensor com motivo "Falha".',
            'Se houver variação brusca (mais de 50% em 5 minutos), pode indicar erro de leitura.',
            'Níveis entre 95% e 99% por longos períodos podem indicar risco de extravasamento iminente.',
        ],
        'vazao' => [
            'Vazão zerada por mais de 2 horas em horário comercial (6h-22h) pode indicar falha no sensor.',
            'Vazão negativa é impossível e indica erro de medição.',
            'Variação maior que 100% em 5 minutos pode indicar erro de leitura.',
            'Picos muito acima da média histórica podem indicar vazamento ou erro.',
        ],
        'pressao' => [
            'Pressão abaixo de 10 mca pode indicar problema na rede.',
            'Pressão zerada por mais de 30 minutos indica falha no sensor ou falta de água.',
            'Pressão acima de 60 mca pode indicar problema ou erro de leitura.',
            'Variação brusca de pressão pode indicar manobra na rede ou vazamento.',
        ],
    ],
    
    // Configurações de geração
    'temperature' => 0.3,
    'max_tokens' => 2048,
];