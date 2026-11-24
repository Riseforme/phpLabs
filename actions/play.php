<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

requireMethod('POST');
requireCsrfToken();
$userId = requireUserId();
$betCents = parseMoneyCents(requestString($_POST, 'bet'));
$chanceText = str_replace(',', '.', trim(requestString($_POST, 'chance')));
$direction = requestString($_POST, 'direction');

if ($betCents === null || $betCents < 1) {
    returnError('Ставка должна быть положительным числом с точностью до двух знаков.', 400);
}
if (!preg_match('/^(?:\d{1,2})(?:\.\d{1,2})?$/', $chanceText)
    || (float) $chanceText < 1
    || (float) $chanceText > 95) {
    returnError('Шанс должен быть от 1 до 95%.', 400);
}
if (!in_array($direction, ['less', 'more'], true)) {
    returnError('Выберите сторону числа.', 400);
}

startAppSession();
$pendingGame = $_SESSION['pending_game'] ?? null;
if (!is_array($pendingGame)
    || !isset($pendingGame['number'], $pendingGame['salt'], $pendingGame['commitment'])
    || !hash_equals(
        hash('sha512', (int) $pendingGame['number'] . ':' . (string) $pendingGame['salt']),
        (string) $pendingGame['commitment']
    )) {
    returnError('Не удалось подтвердить раунд. Обновите игру и повторите попытку.', 409);
}

$chanceBasisPoints = (int) round((float) $chanceText * 100);
$winningCount = (int) floor(999999 * $chanceBasisPoints / 10000);
$roll = (int) $pendingGame['number'];
$won = $direction === 'less'
    ? $roll <= $winningCount
    : $roll >= (1000000 - $winningCount);
$coefficientHundredths = (int) floor(1000000 / $chanceBasisPoints);
$payoutCents = $won ? intdiv($betCents * $coefficientHundredths, 100) : 0;
$newPendingGame = [
    'number' => random_int(1, 999999),
    'salt' => bin2hex(random_bytes(32)),
];
$newPendingGame['commitment'] = hash(
    'sha512',
    $newPendingGame['number'] . ':' . $newPendingGame['salt']
);

try {
    $pdo = getPDO();
    $pdo->beginTransaction();
    $userQuery = $pdo->prepare('SELECT balance FROM users WHERE id = :id FOR UPDATE');
    $userQuery->execute(['id' => $userId]);
    $balance = $userQuery->fetchColumn();
    if ($balance === false) {
        $pdo->rollBack();
        returnError('Аккаунт не найден.', 404);
    }

    $balanceCents = parseMoneyCents((string) $balance);
    if ($balanceCents === null || $betCents > $balanceCents) {
        $pdo->rollBack();
        returnError('Недостаточно средств для такой ставки.', 400);
    }

    $updatedBalance = $balanceCents - $betCents + $payoutCents;
    if ($updatedBalance > 999999999999) {
        $pdo->rollBack();
        returnError('Выплата превышает допустимый размер.', 400);
    }
    $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')->execute([
        'balance' => formatMoneyCents($updatedBalance),
        'id' => $userId,
    ]);

    $statement = $pdo->prepare(
        'INSERT INTO game_rounds
            (user_id, bet, chance, direction, roll_value, won, payout, salt, commitment, balance_after)
         VALUES
            (:user_id, :bet, :chance, :direction, :roll, :won, :payout, :salt, :commitment, :balance)'
    );
    $statement->execute([
        'user_id' => $userId,
        'bet' => formatMoneyCents($betCents),
        'chance' => number_format($chanceBasisPoints / 100, 2, '.', ''),
        'direction' => $direction,
        'roll' => $roll,
        'won' => $won ? 'true' : 'false',
        'payout' => formatMoneyCents($payoutCents),
        'salt' => $pendingGame['salt'],
        'commitment' => $pendingGame['commitment'],
        'balance' => formatMoneyCents($updatedBalance),
    ]);
    $pdo->commit();

    $_SESSION['pending_game'] = $newPendingGame;
    returnJSON([
        'status' => 'success',
        'data' => [
            'number' => $roll,
            'salt' => $pendingGame['salt'],
            'commitment' => $pendingGame['commitment'],
            'won' => $won,
            'bet' => formatMoneyCents($betCents),
            'payout' => formatMoneyCents($payoutCents),
            'balance' => formatMoneyCents($updatedBalance),
            'next_commitment' => $newPendingGame['commitment'],
            'winning_count' => $winningCount,
        ],
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('play: ' . $exception->getMessage());
    if ($exception instanceof PDOException && ($exception->errorInfo[0] ?? '') === '23505') {
        unset($_SESSION['pending_game']);
        returnError('Этот раунд уже был обработан. Обновлён новый раунд.', 409);
    }
    returnError('Не удалось сохранить результат игры. Попробуйте позже.', 500);
}
