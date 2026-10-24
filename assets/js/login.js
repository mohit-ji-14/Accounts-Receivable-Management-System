document.querySelectorAll("[data-password-toggle]").forEach((toggle) => {
    toggle.addEventListener("click", () => {
        const input = toggle.parentElement.querySelector("input");
        if (!input) {
            return;
        }

        const showingPassword = input.type === "password";
        input.type = showingPassword ? "text" : "password";
        toggle.setAttribute("aria-pressed", String(showingPassword));
        toggle.setAttribute("aria-label", showingPassword ? "Hide password" : "Show password");
        toggle.innerHTML = `<i class="bi ${showingPassword ? "bi-eye-slash" : "bi-eye"}" aria-hidden="true"></i>`;
    });
});
