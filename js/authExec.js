import { getPOSTData, updateUserInterface } from './utility.js';
import { registrationExec } from './registration_exec.js';

export async function authExec(popUpObj = document.querySelector(".pop-up")) {
    popUpObj.setContent(`
            <form class="modal-card" id="authForm" method="POST">
                <h2 id="modalTitle">Авторизация</h2>
                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" autocomplete="email" maxlength="100" required>
                </label>
                <label class="field">
                    <span>Пароль</span>
                    <input type="password" name="password" autocomplete="current-password" maxlength="72" required>
                </label>
                <p class="form-error" id="formError"></p>
                <button type="submit" class="btn btn-primary btn-wide" id="formSubmit">Авторизоваться</button>
                
            </form>
            <p class="switch">
                <span id="switchText">Нет аккаунта?</span>
                <a href="#" id="switchLink">Зарегистрироваться</a>
            </p>
        `);
    document.getElementById("switchLink").addEventListener('click', (event) => {
        event.preventDefault();
        registrationExec(popUpObj);
    });
    popUpObj.show();

    const form = document.getElementById("authForm");
    const formError = document.getElementById("formError");

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        formError.textContent = "";

        const formData = new FormData(form);
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.get("email"))) {
            formError.textContent = "Запишите почту корректно!";
            return;
        } else if (formData.get("password").length < 8) {
            formError.textContent = "Пароль должен содержать не менее 8 символов.";
            return;
        }

        try {
            const response = await getPOSTData("actions/auth.php", formData);
            formError.textContent = response.message;
            await updateUserInterface(response.data);
            window.dispatchEvent(new Event("dice:authenticated"));
            setTimeout(() => popUpObj.hide(), 900);
        } catch (error) {
            formError.textContent = error.message || "Не удалось выполнить вход.";
        }
    });
}