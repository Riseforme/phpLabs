export class PopUp {
    #root;
    #content;
    #closeBtn;

    constructor(selector) {
        this.#root = document.querySelector(selector);
        if (!this.#root) {
            throw new Error(`PopUp: элемент ${selector} не найден`);
        }
        this.#content = this.#root.querySelector(".pop-up__content");
        this.#closeBtn = this.#root.querySelector(".pop-up__close");

        // Кнопка «Закрыть»
        this.#closeBtn.addEventListener("click", () => this.hide());

        // Клик по чёрному фону 
        this.#root.addEventListener("click", (event) => {
            if (event.target === this.#root) this.hide();
        });

        // Клавиша Esc
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && this.isOpen) this.hide();
        });

        // Очистить содержимое, когда окно полностью исчезло
        this.#root.addEventListener("transitionend", (event) => {
            if (event.target === this.#root &&
                event.propertyName === "opacity" &&
                !this.isOpen) {
                this.#content.innerHTML = "";
            }
        });
    }

    show() {
        this.#root.classList.add("show");
        this.#closeBtn.focus();
        return this;
    }

    hide() {
        this.#root.classList.remove("show");
        return this;
    }

    setContent(html) {
        this.#content.innerHTML = html;
        return this;
    }

    setText(text) {
        this.#content.textContent = text;
        return this;
    }

    isOpen() {
        return this.#root.classList.contains("show");
    }
}

let csrfToken = "";

/**
 * Stores the current server-issued CSRF token for subsequent form submissions.
 * @param {string} token CSRF token returned by the server.
 */
export function setCsrfToken(token) {
    csrfToken = token;
}

export function getRandomInt(max) {
    return Math.floor(Math.random() * max);
}

export async function getPOSTData(url, data = null) {
    const headers = {};
    if (csrfToken) headers["X-CSRF-Token"] = csrfToken;
    const req = await fetch(url, {
        method: "POST",
        body: data,
        headers
    });
    const response = await req.json();
    if (!req.ok) {
        throw new Error(response.message || `Ошибка HTTP: ${req.status}`);
    }
    if (response.csrf_token) setCsrfToken(response.csrf_token);
    return response;
}

export async function sessionData() {
    const response = await fetch("actions/session.php", {credentials: "same-origin"});
    const data = await response.json();
    if (!response.ok || data.status !== "success") {
        throw new Error(data.message || "Не удалось получить данные сессии.");
    }
    setCsrfToken(data.csrf_token);
    return data.data;
}

export function updateUserInterface(userData = null) {
    const guestBlock = document.getElementById("guestBlock");
    const userBlock = document.getElementById("userBlock");
    const userName = document.getElementById("userName");
    const userBalance = document.getElementById("userBalance");
    const game = document.getElementById("game");
    const adminLink = document.getElementById("adminLink");
    const historyPhone = document.getElementById("historyPhone");

    if (userData && Number(userData.access_level) > 0) {
        guestBlock.classList.add("hidden");
        userBlock.classList.remove("hidden");
        game.classList.add("unlocked");
        userName.textContent = userData.name || "Пользователь";
        userBalance.textContent = `${userData.balance ?? "0.00"} MDL`;
        adminLink.classList.toggle("hidden", Number(userData.access_level) < 2);
        if (historyPhone && userData.phone_number) {
            historyPhone.value = userData.phone_number;
        }
        document.querySelectorAll(".actions button").forEach(button => { button.disabled = false; });
    } else {
        guestBlock.classList.remove("hidden");
        userBlock.classList.add("hidden");
        game.classList.remove("unlocked");
        adminLink.classList.add("hidden");
        if (historyPhone) historyPhone.value = "";
        const history = document.getElementById("gameHistory");
        if (history) history.replaceChildren();
        const historyError = document.getElementById("historyError");
        if (historyError) historyError.textContent = "";
        document.querySelectorAll(".actions button").forEach(button => { button.disabled = true; });
    }
}

export async function logoutExec() {
    try {
        await getPOSTData("actions/logout.php");
        updateUserInterface();
        document.getElementById("hashValue").textContent = "Войдите, чтобы получить HASH";
    } catch (error) {
        console.error("Не удалось выполнить выход:", error);
    }
}