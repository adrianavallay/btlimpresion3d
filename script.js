// Navbar scroll effect
const navbar = document.getElementById("navbar");

window.addEventListener("scroll", () => {
  navbar.classList.toggle("scrolled", window.scrollY > 50);
});

// Mobile menu toggle
const menuToggle = document.getElementById("menuToggle");
const navLinks = document.getElementById("navLinks");

menuToggle.addEventListener("click", () => {
  menuToggle.classList.toggle("active");
  navLinks.classList.toggle("open");
});

navLinks.querySelectorAll("a").forEach((link) => {
  link.addEventListener("click", () => {
    menuToggle.classList.remove("active");
    navLinks.classList.remove("open");
  });
});

// Contact form — envía a contact.php via fetch
const contactForm = document.getElementById("contactForm");
const formSuccess = document.getElementById("formSuccess");
const formError = document.getElementById("formError");

contactForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  formError.classList.add("hidden");

  const submitBtn = contactForm.querySelector("button[type=submit]");
  submitBtn.disabled = true;
  submitBtn.textContent = "Enviando...";

  const data = {
    nombre: contactForm.nombre.value,
    email: contactForm.email.value,
    telefono: contactForm.telefono.value,
    servicio: contactForm.servicio.value,
    mensaje: contactForm.mensaje.value,
  };

  try {
    const res = await fetch("contact.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
    });
    const json = await res.json();

    if (json.ok) {
      contactForm.classList.add("hidden");
      formSuccess.classList.remove("hidden");
    } else {
      formError.textContent = json.mensaje || "Error al enviar. Intentá de nuevo.";
      formError.classList.remove("hidden");
    }
  } catch {
    formError.textContent = "Error de conexión. Intentá de nuevo.";
    formError.classList.remove("hidden");
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = 'Enviar mensaje <span class="btn-arrow">&rarr;</span>';
  }
});

// Scroll fade-in animations with stagger
const observerOptions = { threshold: 0.1, rootMargin: "0px 0px -40px 0px" };

const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add("visible");
      observer.unobserve(entry.target);
    }
  });
}, observerOptions);

document.querySelectorAll(".servicio-card, .section-title, .section-subtitle, .contact-form")
  .forEach((el) => {
    el.classList.add("fade-in");
    observer.observe(el);
  });

// Parallax floating shapes on mouse move
const shapes = document.querySelector(".floating-shapes");
if (shapes && window.matchMedia("(min-width: 769px)").matches) {
  document.addEventListener("mousemove", (e) => {
    const x = (e.clientX / window.innerWidth - 0.5) * 2;
    const y = (e.clientY / window.innerHeight - 0.5) * 2;
    const children = shapes.children;
    for (let i = 0; i < children.length; i++) {
      const speed = (i + 1) * 4;
      children[i].style.transform = `translate(${x * speed}px, ${y * speed}px)`;
    }
  });
}

// Smooth active link highlight on scroll
const sections = document.querySelectorAll("section[id]");
const navLinksAll = document.querySelectorAll(".nav-links a");

window.addEventListener("scroll", () => {
  let current = "";
  sections.forEach((section) => {
    const top = section.offsetTop - 120;
    if (window.scrollY >= top) {
      current = section.getAttribute("id");
    }
  });
  navLinksAll.forEach((link) => {
    link.style.color = "";
    link.style.background = "";
    if (link.getAttribute("href") === `#${current}`) {
      link.style.color = "var(--accent-purple)";
      link.style.background = "rgba(139, 92, 246, 0.08)";
    }
  });
});
