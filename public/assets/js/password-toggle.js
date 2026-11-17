// "Show password" checkboxes.
// Usage: <input type="checkbox" data-toggle-password="ID-of-the-password-input">
document.querySelectorAll("[data-toggle-password]").forEach((checkbox) => {
    const input = document.getElementById(checkbox.dataset.togglePassword);
    if (!input) return;

    checkbox.checked = false;
    checkbox.addEventListener("change", () => {
        input.type = checkbox.checked ? "text" : "password";
    });
});
