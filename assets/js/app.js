(() => {
    const sidebar = document.getElementById("app-sidebar");
    const toggle = document.querySelector("[data-sidebar-toggle]");
    if (!sidebar || !toggle) {
        return;
    }

    const backdrop = document.createElement("button");
    backdrop.type = "button";
    backdrop.className = "sidebar-backdrop";
    backdrop.setAttribute("aria-label", "Close navigation menu");
    document.body.append(backdrop);

    const closeSidebar = () => {
        sidebar.classList.remove("is-open");
        backdrop.classList.remove("is-visible");
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Open navigation menu");
    };

    const openSidebar = () => {
        sidebar.classList.add("is-open");
        backdrop.classList.add("is-visible");
        toggle.setAttribute("aria-expanded", "true");
        toggle.setAttribute("aria-label", "Close navigation menu");
    };

    toggle.addEventListener("click", () => {
        if (sidebar.classList.contains("is-open")) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    backdrop.addEventListener("click", closeSidebar);
    sidebar.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", closeSidebar);
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeSidebar();
        }
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > 991) {
            closeSidebar();
        }
    });
})();
