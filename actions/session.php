<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

requireMethod('GET');
startAppSession();

try {
    $userData = null;
    $userId = $_SESSION['user']['id'] ?? null;
    if (is_int($userId) && $userId > 0) {
        $statement = getPDO()->prepare(
            'SELECT id, name, email, balance, phone_number, access_level
             FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);
        $userData = $statement->fetch();
        if ($userData) {
            $userData['id'] = (int) $userData['id'];
            $userData['access_level'] = (int) $userData['access_level'];
            $_SESSION['user']['access_level'] = $userData['access_level'];
        } else {
            unset($_SESSION['user']);
        }
    }

    returnJSON([
        'status' => 'success',
        'data' => $userData,
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (PDOException $exception) {
    error_log('session: ' . $exception->getMessage());
    returnError('Не удалось загрузить данные аккаунта.', 500);
}
