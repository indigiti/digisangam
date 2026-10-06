<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'product' => 'DigiSangam',
    'status' => 'ok',
    'phase' => 3,
    'api' => '/digisangam/api/v1',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
