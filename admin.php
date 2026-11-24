<?php
declare(strict_types=1);

require_once __DIR__ . '/actions/utility.php';
startAppSession();
requireAdminId();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Админка — Narodny DICE</title>
</head>
<body>
    <style>
        * {
            user-select: text;
        }
    </style>
    <header class="header">
        <a class="logo" href="index.php">Narodny DICE</a>
        <a class="btn btn-ghost" href="index.php">Вернуться</a>
    </header>
    <main>
        <div class="container admin-container">
            <h1>Панель администратора</h1>
            <p>Просмотр игровых раундов и пользователей, управление публичными карточками и создание администраторов.</p>
            <nav class="admin-tabs" aria-label="Разделы панели">
                <button class="btn btn-primary" data-type="games">История игр</button>
                <button class="btn btn-ghost" data-type="users">Пользователи</button>
                <button class="btn btn-ghost" data-type="content">Публичные карточки</button>
            </nav>
            <p class="form-error" id="adminError" role="alert"></p>
             <section class="admin-forms">
                <details id="contentEditor">
                    <summary>Добавить или изменить публичную карточку</summary>
                    <form id="contentForm" class="admin-form">
                        <input type="hidden" name="id">
                        <input type="hidden" name="action" value="save_content">
                        <label class="field"><span>Заголовок</span><input name="title" minlength="3" maxlength="120" required></label>
                        <label class="field"><span>Текст карточки</span><textarea name="body" minlength="10" maxlength="1000" required></textarea></label>
                        <label class="field"><span>Порядок отображения</span><input type="number" name="sort_order" min="0" max="9999" step="1" value="0" required></label>
                        <div class="form-actions"><button class="btn btn-primary" type="submit">Сохранить</button>
                            <button class="btn btn-ghost" type="reset">Очистить</button></div>
                    </form>
                </details>
                <details>
                    <summary>Создать администратора</summary>
                    <form id="adminCreateForm" class="admin-form">
                        <input type="hidden" name="action" value="create_admin">
                        <label class="field"><span>Имя</span><input name="name" minlength="2" maxlength="50" required></label>
                        <label class="field"><span>Email</span><input type="email" name="email" maxlength="254" required></label>
                        <label class="field"><span>Телефон</span><input type="tel" name="phone_number" required></label>
                        <label class="field"><span>Пароль</span><input type="password" name="password" minlength="8" maxlength="72" required></label>
                        <button class="btn btn-primary" type="submit">Создать администратора</button>
                    </form>
                </details>
            </section>
            <div class="admin-table-wrap" id="adminRows" aria-live="polite"></div>
           
        </div>
    </main>
    <script type="module" src="js/admin.js"></script>
</body>
</html>
