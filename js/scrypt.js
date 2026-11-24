"use strict";

import { registrationExec } from "./registration_exec.js";
import { authExec } from "./authExec.js";
import {
    PopUp,
    getPOSTData,
    sessionData,
    updateUserInterface,
    logoutExec
} from "./utility.js";

const numberFormat = new Intl.NumberFormat("ru-RU");

/**
 * Loads and safely renders server-provided public content cards.
 */
async function loadPublicContent() {
    const root = document.getElementById("publicContent");
    try {
        const response = await fetch("actions/public_content.php");
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "Ошибка загрузки.");
        root.replaceChildren();
        result.data.forEach(item => {
            const card = document.createElement("article");
            card.className = "content-card";
            const title = document.createElement("h3");
            title.textContent = item.title;
            const body = document.createElement("p");
            body.textContent = item.body;
            card.append(title, body);
            root.append(card);
        });
    } catch (error) {
        root.textContent = error.message || "Не удалось загрузить информацию.";
    }
}

/**
 * Updates the chance ranges and displayed payout estimate.
 */
function updateBetPreview() {
    const chance = Number(document.getElementById("chanceInput").value);
    const bet = Number(document.getElementById("betInput").value);
    if (!Number.isFinite(chance) || chance < 1 || chance > 95) return;
    const winningCount = Math.floor(999999 * chance / 100);
    const coefficient = Math.floor(1000000 / Math.round(chance * 100)) / 100;
    document.getElementById("lessRange").textContent = `1 – ${numberFormat.format(winningCount)}`;
    document.getElementById("moreRange").textContent =
        `${numberFormat.format(1000000 - winningCount)} – 999 999`;
    document.getElementById("coef").textContent = `x${coefficient.toFixed(2)}`;
    document.getElementById("possibleWin").textContent =
        (Number.isFinite(bet) && bet > 0 ? bet * coefficient : 0).toFixed(2);
}

/**
 * Fetches or displays the next server-generated game commitment.
 */
async function loadGameCommitment() {
    const hashElement = document.getElementById("hashValue");
    try {
        const response = await fetch("actions/game_state.php");
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "Не удалось загрузить игру.");
        hashElement.textContent = result.commitment;
    } catch (error) {
        hashElement.textContent = error.message || "Не удалось загрузить игру.";
    }
}

/**
 * Plays one round and displays the committed result and its verification data.
 * @param {string} direction Chosen side, "less" or "more".
 */
async function playRound(direction) {
    const message = document.getElementById("message");
    const buttons = [...document.querySelectorAll(".actions button")];
    buttons.forEach(button => { button.disabled = true; });
    message.className = "message";
    message.textContent = "Игра выполняется...";
    const data = new FormData();
    data.set("bet", document.getElementById("betInput").value);
    data.set("chance", document.getElementById("chanceInput").value);
    data.set("direction", direction);

    try {
        const result = await getPOSTData("actions/play.php", data);
        const round = result.data;
        document.getElementById("numberValue").textContent =
            String(round.number).padStart(6, "0");
        document.getElementById("numberCaption").textContent =
            round.won ? "Вы выиграли" : "Вы проиграли";
        document.getElementById("numberWindow").className =
            `number-window ${round.won ? "win" : "lose"}`;
        document.getElementById("userBalance").textContent = `${round.balance} MDL`;
        message.className = `message ${round.won ? "win" : "lose"}`;
        message.textContent = round.won
            ? `Выигрыш! Выплата ${round.payout} MDL.`
            : `Не повезло. Списана ставка ${round.bet} MDL.`;
        document.getElementById("vNumber").textContent = String(round.number);
        document.getElementById("vSalt").textContent = round.salt;
        document.getElementById("vHash").textContent = round.commitment;
        document.getElementById("verifyBox").classList.remove("hidden");
        document.getElementById("hashValue").textContent = round.next_commitment;
    } catch (error) {
        message.className = "message error";
        message.textContent = error.message || "Не удалось выполнить игру.";
        if (error.message && error.message.includes("раунд")) await loadGameCommitment();
    } finally {
        buttons.forEach(button => { button.disabled = false; });
    }
}


/**
 * Renders game history with the data needed to check each round's hash.
 * @param {Array<Object>} rounds Game history records.
 */
function renderGameHistory(rounds) {
    const root = document.getElementById("gameHistory");
    root.replaceChildren();
    if (!rounds.length) {
        root.textContent = "Игры по заданным условиям не найдены.";
        return;
    }

    rounds.forEach(round => {
        const card = document.createElement("article");
        card.className = "history-card";
        const won = [true, "1", "t", "true"].includes(round.won);
        const heading = document.createElement("h3");
        heading.textContent = `${won ? "Выигрыш" : "Проигрыш"} · ${new Date(round.created_at).toLocaleString("ru-RU")}`;
        const details = document.createElement("dl");
        const values = [
            ["Ставка", `${round.bet} MDL`],
            ["Выплата", `${round.payout} MDL`],
            ["Победное число", String(round.winning_number)],
            ["Сторона / шанс", `${round.direction === "less" ? "Меньше" : "Больше"} · ${round.chance}%`],
            ["Хеш игры (SHA-512)", round.game_hash.trim()],
            ["Соль", round.salt]
        ];
        values.forEach(([label, value]) => {
            const term = document.createElement("dt");
            term.textContent = label;
            const description = document.createElement("dd");
            if (label === "Хеш игры (SHA-512)" || label === "Соль") {
                const code = document.createElement("code");
                code.textContent = value;
                description.append(code);
            } else {
                description.textContent = value;
            }
            details.append(term, description);
        });
        card.append(heading, details);
        root.append(card);
    });
}

/**
 * Requests filtered history from the server.
 * @param {HTMLFormElement} form Game history search form.
 */
async function searchGameHistory(form) {
    const errorElement = document.getElementById("historyError");
    errorElement.textContent = "";
    const phone = new FormData(form).get("phone");
    const phoneDigits = String(phone).replace(/\D/g, "");
    if (phoneDigits.length < 10 || phoneDigits.length > 15) {
        errorElement.textContent = "Номер телефона должен содержать от 10 до 15 цифр.";
        return;
    }
    const query = new URLSearchParams(new FormData(form));
    try {
        const response = await fetch(`actions/game_history.php?${query.toString()}`);
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "Не удалось выполнить поиск.");
        renderGameHistory(result.data);
    } catch (error) {
        errorElement.textContent = error.message || "Не удалось выполнить поиск.";
        document.getElementById("gameHistory").replaceChildren();
    }
}

window.addEventListener("DOMContentLoaded", async () => {
    const popUp = new PopUp(".pop-up");
    document.querySelector("[data-open='register']").addEventListener("click", () => registrationExec(popUp));
    document.querySelectorAll("[data-open='login']").forEach(button =>
        button.addEventListener("click", () => authExec(popUp)));
    document.getElementById("logoutBtn").addEventListener("click", logoutExec);
    document.getElementById("betInput").addEventListener("input", updateBetPreview);
    document.getElementById("chanceInput").addEventListener("input", updateBetPreview);
    document.querySelectorAll(".actions button").forEach(button =>
        button.addEventListener("click", () => playRound(button.dataset.dir)));


    const historyForm = document.getElementById("historyForm");
    historyForm.addEventListener("submit", event => {
        event.preventDefault();
        searchGameHistory(historyForm);
    });
    window.addEventListener("dice:authenticated", loadGameCommitment);

    try {
        const user = await sessionData();
        await updateUserInterface(user);
        if (user) {
            document.getElementById("historyPhone").value = user.phone_number;
            await loadGameCommitment();
        }
    } catch (error) {
        document.getElementById("message").textContent =
            error.message || "Не удалось проверить сессию.";
        await updateUserInterface(null);
    }
    updateBetPreview();
    await loadPublicContent();
});
