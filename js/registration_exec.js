import  {getPOSTData, updateUserInterface} from './utility.js';
import {authExec} from "./authExec.js";

export async function registrationExec(popUpObj = document.querySelector(".pop-up")) {
    popUpObj.setContent(`
            <form class="modal-card" id="regForm" method="POST">
                <h2 id="modalTitle">Регистрация</h2>
                <label class="field reg-only">
                    <span>Имя</span>
                    <input type="text" name="name" autocomplete="name" maxlength="50" required>
                </label>
                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" autocomplete="email" maxlength="100" required>
                </label>
                <label class="field reg-only">
                    <span>Телефон</span>
                    <input type="tel" name="phone_number" autocomplete="tel" placeholder="+373 69 123 456" required>
                </label>
                <label class="field">
                    <span>Пароль</span>
                    <input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>
                </label>
                <p class="form-error" id="formError"></p>
                <button type="submit" class="btn btn-primary btn-wide" id="formSubmit">Зарегистрироваться</button>
                <p class="switch">
                    <span id="switchText">Есть аккаунт?</span>
                    <a href="#" id="switchLink">Авторизоваться</a>
                </p>
            </form>
        `);
        document.getElementById("switchLink").addEventListener('click', (event) => {
            event.preventDefault();
            authExec(popUpObj);
        });
        popUpObj.show();

        const form = document.getElementById("regForm");

        const formError = document.getElementById("formError");
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            formError.textContent = "";

            const formData = new FormData(form);
            if (formData.get("password").length < 8) {
                formError.textContent = "Пароль должен содержать не менее 8 символов.";
                return;
            } else if (!/^\+?[\d\s()-]{10,20}$/.test(formData.get("phone_number"))) {
                formError.textContent = "Укажите номер телефона, содержащий от 10 до 15 цифр.";
                return;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.get("email"))) {
                formError.textContent = "Неверный формат email.";
                return;
            } else if (formData.get("name").trim().length < 2) {
                formError.textContent = "Имя должно быть не менее 2 символов.";
                return;
            }

            try {
                const response = await getPOSTData("actions/reg.php", formData);
                formError.textContent = response.message;
                await updateUserInterface(response.data);
                window.dispatchEvent(new Event("dice:authenticated"));
                setTimeout(() => popUpObj.hide(), 900);
            } catch (error) {
                formError.textContent = error.message || "Не удалось создать аккаунт.";
            }
        });
}

