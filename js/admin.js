import { getPOSTData, sessionData } from "./utility.js";

let activeType = "games";

/**
 * Renders administrator records with safe DOM text.
 * @param {Array<Object>} rows Records returned by the administrator API.
 */
function renderRows(rows) {
    const root = document.getElementById("adminRows");
    root.replaceChildren();
    if (!rows.length) {
        root.textContent = "Записей нет.";
        return;
    }

    const table = document.createElement("table");
    const head = document.createElement("tr");
    const columns = Object.keys(rows[0]);
    columns.forEach(column => {
        const cell = document.createElement("th");
        cell.textContent = column;
        head.append(cell);
    });
    table.append(head);
    if (activeType === "content") {
        const actionHeading = document.createElement("th");
        actionHeading.textContent = "Действия";
        head.append(actionHeading);
    }
    rows.forEach(row => {
        const tr = document.createElement("tr");
        columns.forEach(column => {
            const cell = document.createElement("td");
            cell.textContent = String(row[column] ?? "");
            tr.append(cell);
        });
        if (activeType === "content") {
            const actions = document.createElement("td");
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "btn btn-ghost";
            edit.textContent = "Изменить";
            edit.addEventListener("click", () => fillContentForm(row));
            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "btn btn-danger";
            remove.textContent = "Удалить";
            remove.addEventListener("click", () => deleteContent(row.id));
            actions.append(edit, remove);
            tr.append(actions);
        }
        table.append(tr);
    });
    
    root.append(table);
}

/**
 * Loads an administrator data set from the server.
 * @param {string} type Data set to display.
 */
async function loadRows(type = activeType) {
    activeType = type;
    document.getElementById("adminError").textContent = "";
    document.getElementById("contentEditor").classList.toggle("hidden", type !== "content");
    try {
        const response = await fetch(`actions/admin_api.php?type=${encodeURIComponent(type)}`);
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "Не удалось загрузить записи.");
        renderRows(result.data);
    } catch (error) {
        document.getElementById("adminError").textContent = error.message;
    }
}

/**
 * Populates the public-card editor with a selected record.
 * @param {Object} content Public content record.
 */
function fillContentForm(content) {
    const form = document.getElementById("contentForm");
    ["id", "title", "body", "sort_order"].forEach(name => {
        form.elements.namedItem(name).value = content[name] ?? "";
    });
    form.scrollIntoView({ behavior: "smooth", block: "center" });
}

/**
 * Deletes a public card after explicit confirmation.
 * @param {number|string} id Public card identifier.
 */
async function deleteContent(id) {
    if (!window.confirm("Удалить эту публичную карточку?")) return;
    const data = new FormData();
    data.set("action", "delete_content");
    data.set("id", String(id));
    try {
        const result = await getPOSTData("actions/admin_api.php", data);
        document.getElementById("adminError").textContent = result.message;
        await loadRows();
    } catch (error) {
        document.getElementById("adminError").textContent = error.message;
    }
}

window.addEventListener("DOMContentLoaded", async () => {
    try {
        const user = await sessionData();
        if (!user || Number(user.access_level) < 2) {
            window.location.replace("index.php");
            return;
        }
        await loadRows();
    } catch (error) {
        document.getElementById("adminError").textContent = error.message;
    }

    document.querySelectorAll(".admin-tabs button").forEach(button => {
        button.addEventListener("click", () => {
            document.querySelectorAll(".admin-tabs button").forEach(tab => {
                tab.className = tab === button ? "btn btn-primary" : "btn btn-ghost";
            });
            loadRows(button.dataset.type);
        });
    });

    document.getElementById("contentForm").addEventListener("submit", async event => {
        event.preventDefault();
        try {
            const result = await getPOSTData("actions/admin_api.php", new FormData(event.currentTarget));
            document.getElementById("adminError").textContent = result.message;
            event.target.reset();
            await loadRows("content");
        } catch (error) {
            document.getElementById("adminError").textContent = error.message;
        }
    });

    document.getElementById("adminCreateForm").addEventListener("submit", async event => {
        event.preventDefault();
        try {
            const result = await getPOSTData("actions/admin_api.php", new FormData(event.currentTarget));
            document.getElementById("adminError").textContent = result.message;
            event.currentTarget.reset();
            await loadRows("users");
        } catch (error) {
            document.getElementById("adminError").textContent = error.message;
        }
    });
});
