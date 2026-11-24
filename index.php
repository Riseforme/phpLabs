<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Narodny DICE — игра в кости с проверяемой историей раундов.">
    <link rel="stylesheet" href="css/style.css">
    <title>Narodny DICE</title>
</head>
<body>
    <header class="header">
        <a class="logo" href="index.php">Narodny DICE</a>
        <div class="auth-guest" id="guestBlock">
            <button class="btn btn-ghost" data-open="register">Регистрация</button>
            <button class="btn btn-primary" data-open="login">Авторизация</button>
        </div>
        <div class="auth-user hidden" id="userBlock">
            <span class="user-name" id="userName"></span>
            <span class="balance">Баланс: <b id="userBalance">0.00 MDL</b></span>
            <a class="btn btn-ghost hidden" id="adminLink" href="admin.php">Админка</a>
            <button class="btn btn-ghost" id="logoutBtn">Выйти</button>
        </div>
    </header>

    <main>
        <div class="container">
            <section class="public-section">
                <h1>Let's Play</h1>
                <div class="content-cards" id="publicContent" aria-live="polite">
                    <p>Загрузка информации...</p>
                </div>
            </section>
            <section class="game" id="game" aria-labelledby="gameTitle">
                <h2 id="gameTitle" class="sr-only">Игра DICE</h2>
                <div class="lock" id="lock">
                    <p>Войдите или зарегистрируйтесь, чтобы играть</p>
                    <button class="btn btn-primary" data-open="login">Авторизация</button>
                </div>
                <div class="number-window" id="numberWindow">
                    <span id="numberValue">000000</span>
                    <small id="numberCaption">Число от 1 до 999 999</small>
                </div>
                <div class="hash-box">
                    <div class="hash-head"><span>Хеш следующей игры (SHA-512)</span></div>
                    <code id="hashValue">Войдите, чтобы получить обязательство</code>
                </div>
                <div class="fields">
                    <label class="field"><span>Сумма ставки, MDL</span>
                        <input type="number" id="betInput" value="10" min="0.01" step="0.01" max="10000000">
                    </label>
                    <label class="field"><span>Шанс, % (1–95)</span>
                        <input type="number" id="chanceInput" value="50" min="1" max="95" step="1">
                    </label>
                </div>
                <div class="ranges">
                    <div class="range range-less"><span>Меньше</span><b id="lessRange">1 – 499 999</b></div>
                    <div class="range range-info"><span>Коэффициент</span><b id="coef">x2</b>
                        <small>Выплата: <span id="possibleWin">20</span> MDL</small>
                    </div>
                    <div class="range range-more"><span>Больше</span><b id="moreRange">500 000 – 999 999</b></div>
                </div>
                <div class="actions">
                    <button class="btn btn-big btn-blue" data-dir="less" disabled>Меньше</button>
                    <button class="btn btn-big btn-green" data-dir="more" disabled>Больше</button>
                </div>
                <p class="message" id="message" role="status" aria-live="polite"></p>
                <details class="verify hidden" id="verifyBox">
                    <summary>Проверить прошлую игру</summary>
                    <dl>
                        <dt>Число</dt><dd id="vNumber"></dd>
                        <dt>Соль</dt><dd><code id="vSalt"></code></dd>
                        <dt>Хеш</dt><dd><code id="vHash"></code></dd>
                    </dl>
                    <p class="hint">SHA-512 от строки <code>число:соль</code></p>
                </details>
            </section>
            <section class="history-section" aria-labelledby="historyTitle">
                <h2 id="historyTitle">История игр</h2>
                <p class="hint">История доступна после входа. Игрок видит только свои раунды; администратор может найти раунды по номеру телефона.</p>
                <form id="historyForm" class="history-form">
                    <label class="field"><span>Номер телефона</span>
                        <input type="tel" name="phone" id="historyPhone" maxlength="20"
                            placeholder="+373 69 123 456" autocomplete="tel"
                            pattern="\+?[\d\s]{10,20}" title="Укажите номер телефона: цифры, пробелы и необязательный +"
                            required>
                    </label>
                    <label class="field"><span>Результат ставок</span>
                        <select name="result">
                            <option value="all">Все игры</option>
                            <option value="won">Только выигрышные</option>
                            <option value="lost">Только проигрышные</option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit">Найти игры</button>
                </form>
                <p class="form-error" id="historyError" role="alert"></p>
                <div id="gameHistory" class="game-history" aria-live="polite"></div>
            </section>
        </div>
    </main>

    <div class="pop-up">
        <div class="pop-up__container">
            <button class="pop-up__close" type="button">Закрыть</button>
            <div class="pop-up__content"></div>
        </div>
    </div>
    <script type="module" src="js/scrypt.js"></script>
</body>
</html>
