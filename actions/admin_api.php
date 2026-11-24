<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    requireAdminId();
    $type = requestString($_GET, 'type') ?: 'games';
    $queries = [
        'users' => 'SELECT id, name, email, phone_number, balance, access_level, reg_date
                    FROM users ORDER BY id DESC LIMIT 100',
        'games' => 'SELECT gr.id, u.phone_number, gr.created_at, gr.bet, gr.chance,
                           gr.direction, gr.roll_value AS winning_number, gr.won,
                           gr.payout, gr.commitment AS game_hash, gr.salt, gr.balance_after
                    FROM game_rounds gr
                    INNER JOIN users u ON u.id = gr.user_id
                    ORDER BY gr.created_at DESC, gr.id DESC LIMIT 100',
        'content' => 'SELECT id, title, body, sort_order
                      FROM public_content ORDER BY sort_order, id LIMIT 100',
    ];
    if (!isset($queries[$type])) {
        returnError('Неизвестный тип данных.', 400);
    }

    try {
        $pdo = getPDO();
        $rows = $pdo->query($queries[$type])->fetchAll();
        returnJSON(['status' => 'success', 'data' => $rows]);
    } catch (PDOException $exception) {
        error_log('admin listing: ' . $exception->getMessage());
        returnError('Не удалось загрузить данные.', 500);
    }
}

requireMethod('POST');
requireCsrfToken();
requireAdminId();

$action = requestString($_POST, 'action');

if ($action === 'save_content') {
    $rawId = trim(requestString($_POST, 'id'));
    $id = $rawId === '' ? null : filter_var($rawId, FILTER_VALIDATE_INT);
    $title = trim(requestString($_POST, 'title'));
    $body = trim(requestString($_POST, 'body'));
    $sortOrder = filter_var(
        requestString($_POST, 'sort_order'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 9999]]
    );
    if (textLength($title) < 3 || textLength($title) > 120
        || textLength($body) < 10 || textLength($body) > 1000
        || $sortOrder === false) {
        returnError('Проверьте заголовок, текст и порядок карточки.', 400);
    }
    if ($rawId !== '' && ($id === false || $id < 1)) {
        returnError('Укажите корректный идентификатор карточки.', 400);
    }

    try {
        $pdo = getPDO();
        if ($rawId === '') {
            $statement = $pdo->prepare(
                'INSERT INTO public_content (title, body, sort_order)
                 VALUES (:title, :body, :sort_order)'
            );
            $statement->execute([
                'title' => $title,
                'body' => $body,
                'sort_order' => $sortOrder,
            ]);
        } else {
            $statement = $pdo->prepare(
                'UPDATE public_content SET title = :title, body = :body, sort_order = :sort_order
                 WHERE id = :id'
            );
            $statement->execute([
                'title' => $title,
                'body' => $body,
                'sort_order' => $sortOrder,
                'id' => $id,
            ]);
            if ($statement->rowCount() === 0) {
                returnError('Карточка не найдена.', 404);
            }
        }
        returnJSON(['status' => 'success', 'message' => 'Карточка сохранена.']);
    } catch (PDOException $exception) {
        error_log('public content administration: ' . $exception->getMessage());
        returnError('Не удалось сохранить карточку.', 500);
    }
}

if ($action === 'delete_content') {
    $id = filter_var(requestString($_POST, 'id'), FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 1) {
        returnError('Укажите корректную карточку.', 400);
    }
    try {
        $pdo = getPDO();
        $statement = $pdo->prepare('DELETE FROM public_content WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) {
            returnError('Карточка не найдена.', 404);
        }
        returnJSON(['status' => 'success', 'message' => 'Карточка удалена.']);
    } catch (PDOException $exception) {
        error_log('public content deletion: ' . $exception->getMessage());
        returnError('Не удалось удалить карточку.', 500);
    }
}

if ($action !== 'create_admin') {
    returnError('Неизвестное действие.', 400);
}

$name = trim(requestString($_POST, 'name'));
$email = strtolower(trim(requestString($_POST, 'email')));
$phone = trim(requestString($_POST, 'phone_number'));
$password = requestString($_POST, 'password');
$validationError = validateRegistration($name, $email, $phone, $password);
if ($validationError !== null) {
    returnError($validationError, 400);
}

try {
    $statement = getPDO()->prepare(
        'INSERT INTO users (name, email, password_hash, balance, phone_number, access_level)
         VALUES (:name, :email, :password, 0, :phone, 2)'
    );
    $statement->execute([
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => preg_replace('/\D+/', '', $phone),
    ]);
    returnJSON(['status' => 'success', 'message' => 'Учётная запись администратора создана.'], 201);
} catch (PDOException $exception) {
    error_log('admin creation: ' . $exception->getMessage());
    if (($exception->errorInfo[0] ?? '') === '23505') {
        returnError('Этот email уже зарегистрирован.', 409);
    }
    returnError('Не удалось создать администратора.', 500);
}
