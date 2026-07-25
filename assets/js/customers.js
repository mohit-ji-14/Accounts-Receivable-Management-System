// ================================
// CUSTOMER PAGE
// ================================

// Focus Effect

document.querySelectorAll(".input-box input, .input-box textarea")
.forEach((input) => {

    input.addEventListener("focus", function () {

        this.parentElement.classList.add("active");

    });

    input.addEventListener("blur", function () {

        if (this.value.trim() === "") {

            this.parentElement.classList.remove("active");

        }

    });

});

// ================================
// Form Validation
// ================================

const form = document.querySelector("form");

if (form) {

    form.addEventListener("submit", function (e) {

        const customerName = document.querySelector(
            'input[name="customer_name"]'
        ).value.trim();

        if (customerName === "") {

            e.preventDefault();

            alert("Customer Name is required.");

            return;
        }

    });

}

// ================================
// Button Loading Effect
// ================================

const submitBtn = document.querySelector(".login-btn");

if (submitBtn) {

    submitBtn.addEventListener("click", function () {

        submitBtn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        submitBtn.disabled = true;

    });

}

// ================================
// Card Animation
// ================================

window.addEventListener("load", () => {

    const card = document.querySelector(".login-card");

    if (card) {

        card.style.opacity = "0";
        card.style.transform = "translateY(30px)";

        setTimeout(() => {

            card.style.transition = "all .6s ease";

            card.style.opacity = "1";
            card.style.transform = "translateY(0)";

        }, 150);

    }

});

// ================================
// Input Animation
// ================================

document.querySelectorAll(".input-box").forEach((box, index) => {

    box.style.opacity = "0";
    box.style.transform = "translateX(20px)";

    setTimeout(() => {

        box.style.transition = "0.5s";

        box.style.opacity = "1";

        box.style.transform = "translateX(0)";

    }, 100 * index);

});

// ================================
// Prevent Double Submit
// ================================

if (form) {

    form.addEventListener("submit", function () {

        submitBtn.disabled = true;

    });

}