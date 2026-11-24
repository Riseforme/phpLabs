<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

requireMethod('GET');
$viewerId = requireUserId();
$phoneDigits = preg_replace('/\D+/', '', requestString($_GET, 'phone'));
$result = requestString($_GET, 'result') ?: 'all';

if (!is_string($phoneDigits) || strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
    returnError('Укажите номер телефона, содержащий от 10 до 15 цифр.', 400);
}
if (!in_array($result, ['all', 'won', 'lost'], true)) {
    returnError('Выберите корректный результат игры.', 400);
}

try {
    $pdo = getPDO();
    $accountQuery = $pdo->prepare(
        'SELECT phone_number, access_level FROM users WHERE id = :id'
    );
    $accountQuery->execute(['id' => $viewerId]);
    $account = $accountQuery->fetch();
    if (!$account) {
        returnError('Учётная запись не найдена.', 404);
    }

    $isAdmin = (int) $account['access_level'] === 2;
    $accountPhone = preg_replace('/\D+/', '', (string) $account['phone_number']);
    if (!$isAdmin && !hash_equals((string) $accountPhone, $phoneDigits)) {
        returnError('Можно просматривать историю только по своему номеру телефона.', 403);
    }

    $ownerFilter = $isAdmin ? '' : ' AND gr.user_id = :viewer_id';
    $resultFilter = $result === 'won' ? ' AND gr.won = TRUE'
        : ($result === 'lost' ? ' AND gr.won = FALSE' : '');
    $statement = $pdo->prepare(
        'SELECT gr.id, gr.created_at, gr.bet, gr.chance, gr.direction,
                gr.roll_value AS winning_number, gr.won, gr.payout,
                gr.commitment AS game_hash, gr.salt
         FROM game_rounds gr
         INNER JOIN users u ON u.id = gr.user_id
         WHERE regexp_replace(u.phone_number, \'[^0-9]\', \'\', \'g\') = :phone'
         . $ownerFilter .
        $resultFilter .
        '
         ORDER BY gr.created_at DESC, gr.id DESC
         LIMIT 100'
    );
    $parameters = ['phone' => $phoneDigits];
    if (!$isAdmin) {
        $parameters['viewer_id'] = $viewerId;
    }
    $statement->execute($parameters);
    returnJSON(['status' => 'success', 'data' => $statement->fetchAll()]);
} catch (PDOException $exception) {
    error_log('game history: ' . $exception->getMessage());
    returnError('Не удалось загрузить историю игр.', 500);
}
