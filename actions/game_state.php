<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

requireMethod('GET');
requireUserId();
startAppSession();

if (!isset($_SESSION['pending_game']) || !is_array($_SESSION['pending_game'])) {
    $_SESSION['pending_game'] = createPendingGame();
}

returnJSON(['status' => 'success', 'commitment' => $_SESSION['pending_game']['commitment']]);

/**
 * Generates a server-side outcome and its public SHA-512 commitment.
 *
 * @return array{number:int,salt:string,commitment:string} Secret pending round.
 */
function createPendingGame(): array
{
    $number = random_int(1, 999999);
    $salt = bin2hex(random_bytes(32));
    return [
        'number' => $number,
        'salt' => $salt,
        'commitment' => hash('sha512', $number . ':' . $salt),
    ];
}
