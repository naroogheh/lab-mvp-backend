<?php

return [
    'avalai' => [
        'key' => env('AVALAI_API_KEY'),
        'endpoint' => env('AVALAI_ENDPOINT', 'https://api.avalai.ir/v1'),
        'vision_model' => env('AVALAI_VISION_MODEL', 'gpt-5.5'),
        'input_cost_per_1m_tokens' => env('AVALAI_INPUT_COST_PER_1M_TOKENS', 5.00),
        'output_cost_per_1m_tokens' => env('AVALAI_OUTPUT_COST_PER_1M_TOKENS', 30.00),
    ],
];
