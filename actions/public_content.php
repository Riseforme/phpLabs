<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

requireMethod('GET');
try {
    $pdo = getPDO();
    $content = $pdo->query(
        'SELECT id, title, body FROM public_content ORDER BY sort_order, id LIMIT 6'
    )->fetchAll();
    returnJSON(['status' => 'success', 'data' => $content]);
} catch (Throwable $exception) {
    error_log('public content: ' . $exception->getMessage());
    returnError('Не удалось загрузить информацию.', 500);
}
