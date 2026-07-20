/* ═══════════════════════════════════════════
   BOLIVIANS REFORMES — GSAP animations
   gsap core + ScrollTrigger + ScrollTo + SplitText
   ═══════════════════════════════════════════ */

gsap.registerPlugin(ScrollTrigger, ScrollToPlugin, SplitText);

const mm = gsap.matchMedia();
const isTouch = window.matchMedia("(hover: none)").matches;

/* ──────────────────────────────────────────
   PRELOADER → intro del hero
   ────────────────────────────────────────── */
function runPreloader(simple = false) {
  const counter = { val: 0 };
  const words = gsap.utils.toArray(".preloader__word");
  const tl = gsap.timeline({
    onComplete: () => {
      document.getElementById("preloader").remove();
      ScrollTrigger.refresh();
    }
  });

  // Contador 0 → 100
  tl.to(counter, {
    val: 100,
    duration: simple ? 1.2 : 2.2,
    ease: "power2.inOut",
    onUpdate: () => {
      document.getElementById("loadCount").textContent = Math.round(counter.val);
    }
  }, 0);

  if (simple) {
    // Versión con movimiento reducido: solo contador y fundido de salida
    tl.to("#preloader", { autoAlpha: 0, duration: 0.4 }, "+=0.2");
    return tl;
  }

  // Palabras rotando
  words.forEach((w, i) => {
    if (i === 0) return;
    tl.to(".preloader__words .preloader__word", {
      yPercent: -100 * i,
      duration: 0.45,
      ease: "power3.inOut"
    }, 0.55 * i);
  });

  // Cortina ámbar + salida
  tl.to(".preloader__curtain", { scaleY: 1, duration: 0.5, ease: "power3.in" }, "+=0.25")
    .to(".preloader__inner", { opacity: 0, duration: 0.3 }, "<")
    .to("#preloader", { yPercent: -100, duration: 0.7, ease: "power4.inOut" })
    .add(heroIntro(), "-=0.35");

  return tl;
}

/* ──────────────────────────────────────────
   HERO — intro con SplitText
   ────────────────────────────────────────── */
function heroIntro() {
  const tl = gsap.timeline();

  const split = SplitText.create("#heroTitle .hero__line", {
    type: "words, chars",
    mask: "words"
  });

  gsap.set(["#heroKicker", "#heroSub", "#heroActions", "#heroBadges", "#heroScroll"], { autoAlpha: 0 });

  tl.from(".hero__bg", { scale: 1.25, duration: 2.2, ease: "power2.out" }, 0)
    .from(split.chars, {
      yPercent: 110,
      rotate: 4,
      stagger: 0.018,
      duration: 0.9,
      ease: "power4.out"
    }, 0.1)
    .to("#heroKicker", { autoAlpha: 1, y: 0, duration: 0.6, ease: "power2.out", startAt: { y: 16 } }, 0.55)
    .to("#heroSub", { autoAlpha: 1, y: 0, duration: 0.6, ease: "power2.out", startAt: { y: 20 } }, 0.75)
    .to("#heroActions", { autoAlpha: 1, y: 0, duration: 0.6, ease: "power2.out", startAt: { y: 20 } }, 0.9)
    .to("#heroBadges", { autoAlpha: 1, x: 0, duration: 0.7, ease: "power2.out", startAt: { x: 30 } }, 1.0)
    .to("#heroScroll", { autoAlpha: 1, duration: 0.6 }, 1.2);

  return tl;
}

/* ──────────────────────────────────────────
   HERO — parallax al hacer scroll
   ────────────────────────────────────────── */
function heroScrollFX() {
  gsap.to(".hero__bg", {
    yPercent: 18,
    scale: 1.08,
    ease: "none",
    scrollTrigger: {
      trigger: ".hero",
      start: "top top",
      end: "bottom top",
      scrub: true
    }
  });

  gsap.to(".hero__content", {
    yPercent: -14,
    autoAlpha: 0.25,
    ease: "none",
    scrollTrigger: {
      trigger: ".hero",
      start: "top top",
      end: "70% top",
      scrub: true
    }
  });

  // Línea del indicador de scroll en bucle
  gsap.fromTo(".hero__scroll-line i",
    { yPercent: -100 },
    { yPercent: 100, duration: 1.6, ease: "power2.inOut", repeat: -1 }
  );
}

/* ──────────────────────────────────────────
   HEADER — ocultar al bajar + fondo
   ────────────────────────────────────────── */
function headerFX() {
  const header = document.getElementById("header");

  ScrollTrigger.create({
    start: "top -80",
    onUpdate: (self) => {
      header.classList.toggle("is-scrolled", self.scroll() > 80);
      if (self.direction === 1 && self.scroll() > 300) {
        gsap.to(header, { yPercent: -110, duration: 0.4, ease: "power3.out", overwrite: "auto" });
      } else {
        gsap.to(header, { yPercent: 0, duration: 0.4, ease: "power3.out", overwrite: "auto" });
      }
    }
  });

  // Barra de progreso global
  gsap.to("#scrollProgress", {
    scaleX: 1,
    ease: "none",
    scrollTrigger: { start: 0, end: "max", scrub: 0.3 }
  });
}

/* ──────────────────────────────────────────
   MARQUEE — bucle infinito + velocidad de scroll
   ────────────────────────────────────────── */
function marqueeFX() {
  const track = document.getElementById("marqueeTrack");
  const loop = gsap.to(track, {
    xPercent: -50,
    duration: 22,
    ease: "none",
    repeat: -1
  });

  // La dirección y velocidad reaccionan al scroll
  ScrollTrigger.create({
    start: 0,
    end: "max",
    onUpdate: (self) => {
      const v = gsap.utils.clamp(-4, 4, self.getVelocity() / 260);
      gsap.to(loop, {
        timeScale: v < 0 ? -1 + v : 1 + v,
        duration: 0.4,
        overwrite: true,
        onComplete: () => gsap.to(loop, { timeScale: self.direction < 0 ? -1 : 1, duration: 0.8 })
      });
    }
  });
}

/* ──────────────────────────────────────────
   REVEALS GENÉRICOS
   ────────────────────────────────────────── */
function revealFX() {
  // Elementos sueltos .reveal-up
  gsap.utils.toArray(".reveal-up").forEach((el) => {
    gsap.from(el, {
      y: 40,
      autoAlpha: 0,
      duration: 0.9,
      ease: "power3.out",
      scrollTrigger: { trigger: el, start: "top 88%" }
    });
  });

  // Títulos de sección línea a línea (SplitText + máscara)
  document.fonts.ready.then(() => {
    gsap.utils.toArray(".split-lines").forEach((el) => {
      SplitText.create(el, {
        type: "lines",
        mask: "lines",
        autoSplit: true,
        onSplit(self) {
          return gsap.from(self.lines, {
            yPercent: 115,
            duration: 0.9,
            stagger: 0.09,
            ease: "power4.out",
            scrollTrigger: { trigger: el, start: "top 85%" }
          });
        }
      });
    });

    // Párrafo de "Nosotros": palabras que se encienden con el scroll
    const statement = SplitText.create("#aboutStatement", { type: "words" });
    gsap.fromTo(statement.words,
      { opacity: 0.14 },
      {
        opacity: 1,
        stagger: 0.06,
        ease: "none",
        scrollTrigger: {
          trigger: "#aboutStatement",
          start: "top 78%",
          end: "bottom 45%",
          scrub: 0.4
        }
      }
    );

    ScrollTrigger.refresh();
  });

  // Imágenes con reveal de clip + parallax interno
  gsap.utils.toArray(".img-reveal").forEach((wrap) => {
    const img = wrap.querySelector("img, iframe");
    gsap.from(wrap, {
      clipPath: "inset(0 0 100% 0)",
      duration: 1.2,
      ease: "power4.inOut",
      scrollTrigger: { trigger: wrap, start: "top 82%" }
    });
    if (img && img.tagName === "IMG") {
      gsap.fromTo(img, { yPercent: -8, scale: 1.15 }, {
        yPercent: 8,
        scale: 1.15,
        ease: "none",
        scrollTrigger: { trigger: wrap, start: "top bottom", end: "bottom top", scrub: true }
      });
    }
  });
}

/* ──────────────────────────────────────────
   CONTADORES
   ────────────────────────────────────────── */
function counterFX() {
  gsap.utils.toArray(".counter").forEach((el) => {
    const target = +el.dataset.target;
    gsap.fromTo(el, { innerText: 0 }, {
      innerText: target,
      duration: 1.8,
      ease: "power2.out",
      snap: { innerText: 1 },
      scrollTrigger: { trigger: "#aboutStats", start: "top 85%" }
    });
  });
}

/* ──────────────────────────────────────────
   SERVICIOS — stagger + imagen que sigue al cursor
   ────────────────────────────────────────── */
function servicesFX() {
  gsap.from(".service", {
    y: 60,
    autoAlpha: 0,
    stagger: 0.09,
    duration: 0.8,
    ease: "power3.out",
    scrollTrigger: { trigger: "#servicesList", start: "top 82%" }
  });

  if (isTouch) return;

  const preview = document.getElementById("servicePreview");
  const previewImg = document.getElementById("servicePreviewImg");
  const xTo = gsap.quickTo(preview, "x", { duration: 0.45, ease: "power3" });
  const yTo = gsap.quickTo(preview, "y", { duration: 0.45, ease: "power3" });

  document.querySelectorAll(".service").forEach((row) => {
    row.addEventListener("mouseenter", () => {
      previewImg.src = row.dataset.img;
      gsap.to(preview, { opacity: 1, scale: 1, duration: 0.35, ease: "power3.out" });
    });
    row.addEventListener("mouseleave", () => {
      gsap.to(preview, { opacity: 0, scale: 0.85, duration: 0.3, ease: "power3.in" });
    });
  });

  window.addEventListener("mousemove", (e) => {
    xTo(e.clientX + 24);
    yTo(e.clientY - 105);
  });
}

/* ──────────────────────────────────────────
   PROYECTOS — scroll horizontal con pin
   ────────────────────────────────────────── */
function projectsFX() {
  const track = document.getElementById("projectsTrack");

  mm.add("(min-width: 901px)", () => {
    const getDistance = () => track.scrollWidth - window.innerWidth;

    const scrollTween = gsap.to(track, {
      x: () => -getDistance(),
      ease: "none",
      scrollTrigger: {
        trigger: "#proyectos",
        pin: "#projectsPin",
        start: "top top",
        end: () => "+=" + getDistance(),
        scrub: 1,
        invalidateOnRefresh: true,
        onUpdate: (self) => {
          gsap.set("#projectsProgress", { scaleX: self.progress });
        }
      }
    });

    // Ligero parallax de cada imagen mientras cruza la pantalla
    gsap.utils.toArray(".project__img img").forEach((img) => {
      gsap.fromTo(img, { xPercent: -6 }, {
        xPercent: 6,
        ease: "none",
        scrollTrigger: {
          containerAnimation: scrollTween,
          trigger: img.closest(".project"),
          start: "left right",
          end: "right left",
          scrub: true
        }
      });
    });
  });

  mm.add("(max-width: 900px)", () => {
    // En móvil: carrusel nativo con reveals
    track.style.transform = "none";
    track.parentElement.style.overflowX = "auto";
    gsap.from(".project", {
      x: 60,
      autoAlpha: 0,
      stagger: 0.1,
      duration: 0.7,
      ease: "power3.out",
      scrollTrigger: { trigger: "#proyectos", start: "top 75%" }
    });
  });
}

/* ──────────────────────────────────────────
   PROCESO — pasos en cascada
   ────────────────────────────────────────── */
function processFX() {
  gsap.from(".step", {
    y: 70,
    autoAlpha: 0,
    stagger: 0.12,
    duration: 0.85,
    ease: "back.out(1.4)",
    scrollTrigger: { trigger: "#processSteps", start: "top 80%" }
  });
}

/* ──────────────────────────────────────────
   OPINIONES — batch reveal
   ────────────────────────────────────────── */
function reviewsFX() {
  gsap.set(".review", { y: 50, autoAlpha: 0 });
  ScrollTrigger.batch(".review", {
    start: "top 88%",
    once: true,
    onEnter: (batch) => gsap.to(batch, {
      y: 0,
      autoAlpha: 1,
      stagger: 0.1,
      duration: 0.8,
      ease: "power3.out",
      overwrite: true
    })
  });
}

/* ──────────────────────────────────────────
   CTA — palabras que se separan con el scroll
   ────────────────────────────────────────── */
function ctaFX() {
  const words = gsap.utils.toArray(".cta__word");
  gsap.from(words[0], {
    xPercent: -35,
    ease: "none",
    scrollTrigger: { trigger: ".cta", start: "top bottom", end: "center center", scrub: 1 }
  });
  gsap.from(words[1], {
    xPercent: 35,
    ease: "none",
    scrollTrigger: { trigger: ".cta", start: "top bottom", end: "center center", scrub: 1 }
  });
}

/* ──────────────────────────────────────────
   FOOTER — texto gigante con parallax
   ────────────────────────────────────────── */
function footerFX() {
  gsap.from("#footerGiant", {
    yPercent: 55,
    ease: "none",
    scrollTrigger: {
      trigger: ".footer",
      start: "top bottom",
      end: "bottom bottom",
      scrub: 1
    }
  });
}

/* ──────────────────────────────────────────
   CURSOR personalizado + botones magnéticos
   ────────────────────────────────────────── */
function cursorFX() {
  if (isTouch) return;

  document.body.classList.add("has-cursor");

  const dot = document.getElementById("cursor");
  const ring = document.getElementById("cursorRing");
  const dotX = gsap.quickTo(dot, "x", { duration: 0.12, ease: "power2" });
  const dotY = gsap.quickTo(dot, "y", { duration: 0.12, ease: "power2" });
  const ringX = gsap.quickTo(ring, "x", { duration: 0.45, ease: "power3" });
  const ringY = gsap.quickTo(ring, "y", { duration: 0.45, ease: "power3" });

  gsap.set([dot, ring], { xPercent: -50, yPercent: -50 });

  window.addEventListener("mousemove", (e) => {
    dotX(e.clientX); dotY(e.clientY);
    ringX(e.clientX); ringY(e.clientY);
  });

  document.querySelectorAll("a, button, .service").forEach((el) => {
    el.addEventListener("mouseenter", () => gsap.to(ring, { scale: 1.7, duration: 0.3 }));
    el.addEventListener("mouseleave", () => gsap.to(ring, { scale: 1, duration: 0.3 }));
  });

  document.querySelectorAll(".project__img").forEach((el) => {
    el.addEventListener("mouseenter", () => ring.classList.add("is-view"));
    el.addEventListener("mouseleave", () => ring.classList.remove("is-view"));
  });
}

function magneticFX() {
  if (isTouch) return;
  document.querySelectorAll(".magnetic").forEach((el) => {
    const strength = 0.35;
    const xTo = gsap.quickTo(el, "x", { duration: 0.4, ease: "power3" });
    const yTo = gsap.quickTo(el, "y", { duration: 0.4, ease: "power3" });
    el.addEventListener("mousemove", (e) => {
      const r = el.getBoundingClientRect();
      xTo((e.clientX - r.left - r.width / 2) * strength);
      yTo((e.clientY - r.top - r.height / 2) * strength);
    });
    el.addEventListener("mouseleave", () => { xTo(0); yTo(0); });
  });
}

/* ──────────────────────────────────────────
   WHATSAPP — entrada con rebote tras el preloader
   ────────────────────────────────────────── */
function whatsappFX() {
  gsap.from("#whatsappFab", {
    scale: 0,
    autoAlpha: 0,
    duration: 0.7,
    delay: 3.6,
    ease: "back.out(2.2)"
  });
}

/* ──────────────────────────────────────────
   NAVEGACIÓN — anclas suaves + menú móvil
   ────────────────────────────────────────── */
function navFX() {
  document.querySelectorAll("[data-scrollto]").forEach((link) => {
    link.addEventListener("click", (e) => {
      const target = link.getAttribute("href");
      if (!target.startsWith("#")) return;
      e.preventDefault();
      closeMenu();
      gsap.to(window, {
        scrollTo: { y: target, offsetY: 0 },
        duration: 1.1,
        ease: "power3.inOut"
      });
    });
  });

  const burger = document.getElementById("burger");
  const menu = document.getElementById("mobileMenu");
  let open = false;

  const openTl = gsap.timeline({ paused: true })
    .set(menu, { visibility: "visible" })
    .to(menu, { clipPath: "circle(150% at calc(100% - 3rem) 2rem)", duration: 0.7, ease: "power3.inOut" })
    .from(".mobile-menu__nav a", { y: 40, autoAlpha: 0, stagger: 0.07, duration: 0.4, ease: "power3.out" }, "-=0.25")
    .from(".mobile-menu__phone", { autoAlpha: 0, duration: 0.3 }, "-=0.15");

  function closeMenu() {
    if (!open) return;
    open = false;
    burger.setAttribute("aria-expanded", "false");
    menu.setAttribute("aria-hidden", "true");
    document.body.classList.remove("menu-open");
    openTl.reverse();
  }

  burger.addEventListener("click", () => {
    open = !open;
    burger.setAttribute("aria-expanded", String(open));
    menu.setAttribute("aria-hidden", String(!open));
    document.body.classList.toggle("menu-open", open);
    open ? openTl.play() : openTl.reverse();
  });
}

/* ──────────────────────────────────────────
   INIT
   ────────────────────────────────────────── */
const noAnim = window.matchMedia("(prefers-reduced-motion: reduce)").matches
  || new URLSearchParams(location.search).has("noanim");

// Ejecuta cada efecto de forma aislada: si uno falla, el resto sigue
const safe = (fn) => { try { fn(); } catch (e) { console.error("[FX]", fn.name, e); } };

// El cursor personalizado es feedback directo del ratón, no animación
// decorativa: se activa siempre que haya puntero fino
safe(cursorFX);

if (!noAnim) {
  safe(runPreloader);
  safe(heroScrollFX);
  safe(marqueeFX);
  safe(revealFX);
  safe(counterFX);
  safe(servicesFX);
  safe(projectsFX);
  safe(processFX);
  safe(reviewsFX);
  safe(ctaFX);
  safe(footerFX);
  safe(magneticFX);
  safe(whatsappFX);
} else {
  // Movimiento reducido: preloader simple (solo contador + fundido),
  // contenido visible sin efectos y proyectos con scroll nativo
  safe(() => runPreloader(true));
  document.querySelectorAll(".counter").forEach((el) => { el.textContent = el.dataset.target; });
  const pin = document.getElementById("projectsPin");
  pin.style.overflowX = "auto";
  pin.style.minHeight = "auto";
}

headerFX();
navFX();

window.addEventListener("load", () => ScrollTrigger.refresh());
