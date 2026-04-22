/* ============================================================
   BTL IMPRESION 3D — INTERACTIONS + THEME TOGGLE
   ============================================================ */

(function () {
    'use strict';

    // --- Theme Toggle ---
    const html = document.documentElement;
    const themeToggle = document.getElementById('themeToggle');
    const STORAGE_KEY = 'btl-theme';

    function setTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem(STORAGE_KEY, theme);
    }

    // Init: dark por defecto, salvo que el usuario haya guardado su preferencia
    const saved = localStorage.getItem(STORAGE_KEY);
    setTheme(saved || 'dark');

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = html.getAttribute('data-theme') || 'dark';
            setTheme(current === 'light' ? 'dark' : 'light');
        });
    }

    // --- i18n Language Toggle ---
    const langSwitch = document.getElementById('langSwitch');
    const langBtns = langSwitch ? langSwitch.querySelectorAll('.lang-switch__btn') : [];
    const LANG_KEY = 'btl-lang';

    const translations = {
        es: {
            // Nav
            'nav-inicio': 'INICIO',
            'nav-servicios': 'SERVICIOS',
            'nav-proceso': 'PROCESO',
            'nav-materiales': 'MATERIALES',
            'nav-contacto': 'CONTACTO',
            'nav-cta': 'SOLICITAR PRESUPUESTO',
            // Hero
            'hero-tag': 'SERVICIO PROFESIONAL DE IMPRESION 3D',
            'hero-t1': 'CONVERTIMOS',
            'hero-t2': 'ARCHIVOS EN',
            'hero-t3': 'OBJETOS REALES',
            'hero-sub': 'Servicio profesional de impresion 3D a medida. Convertimos tus ideas digitales en productos fisicos con la maxima resolucion y cuidado del detalle. Para empresas, diseñadores y emprendedores.',
            'hero-btn1': 'VER SERVICIOS',
            'hero-btn2': 'COMO TRABAJAMOS',
            'hero-c1-label': 'PRECISION',
            'hero-c2-label': 'ENTREGA PROMEDIO',
            'hero-c3-label': 'PROYECTOS ENTREGADOS',
            'hero-c4-label': 'SATISFACCION',
            // Services
            'srv-tag': 'NUESTROS SERVICIOS',
            'srv-title': 'TODO LO QUE NECESITAS<br>PARA MATERIALIZAR TU IDEA',
            'srv1-t': 'IMPRESION 3D PERSONALIZADA',
            'srv1-d': 'Recibimos tus archivos STL o STEP y seleccionamos la tecnologia, resolucion, relleno y parametros termicos optimos. Alto control dimensional, configuracion de malla eficiente y post-procesado basico incluido.',
            'srv2-t': 'DISEÑO Y MODELADO CAD',
            'srv2-d': 'Creamos modelos 3D desde cero a partir de bocetos, planos 2D o referencias fotograficas. Modelado parametrico industrial, optimizacion para manufactura aditiva e ingenieria inversa de piezas rotas o discontinuadas.',
            'srv3-t': 'PROTOTIPADO RAPIDO',
            'srv3-d': 'Iteraciones veloces para validar formas, ensambles y funcionalidad. Reducimos hasta 90% los tiempos respecto a manufactura tradicional. Ideal para startups, ingenieros y diseñadores industriales que necesitan testear antes de producir.',
            'srv4-t': 'PRODUCCION EN SERIE',
            'srv4-d': 'Tiradas de 10 a 500 unidades sin costo de moldes iniciales. Control de calidad y homogeneidad pieza por pieza. Produccion bajo demanda con packaging incluido y logistica coordinada a todo el pais.',
            // Process
            'proc-tag': 'COMO TRABAJAMOS',
            'proc-title': 'UN PROCESO SIMPLE,<br>RESULTADOS PRECISOS',
            'proc-desc': 'De la idea al objeto fisico en cuatro pasos. Sin complicaciones, con seguimiento en cada etapa.',
            'proc1-t': 'CONTACTO INICIAL',
            'proc1-d': 'Nos envias tu archivo, boceto o idea. Evaluamos viabilidad, materiales y tiempos. Te pasamos presupuesto en menos de 24hs.',
            'proc2-t': 'DISEÑO Y VALIDACION',
            'proc2-d': 'Si es necesario, modelamos o ajustamos el diseño. Te mostramos previsualizaciones antes de imprimir para asegurar que todo esta correcto.',
            'proc3-t': 'IMPRESION Y CONTROL',
            'proc3-d': 'Configuramos parametros optimos para cada pieza. Impresion capa por capa con control de calidad durante y despues del proceso.',
            'proc4-t': 'ENTREGA',
            'proc4-d': 'Embalaje protector a medida. Envios a todo el pais con seguimiento o retiro en nuestro taller en Buenos Aires.',
            // Materials
            'mat-tag': 'MATERIALES',
            'mat-title': 'ELEGIMOS EL MATERIAL<br>SEGUN TU PROYECTO',
            'mat1-badge': 'MAS USADO',
            'mat1-sub': 'Acido polilactico. El estandar versatil.',
            'mat1-l1': 'Biodegradable, derivado del maiz',
            'mat1-l2': 'Alta resolucion y nivel de detalle',
            'mat1-l3': 'Excelente acabado superficial',
            'mat1-l4': 'Ideal para piezas decorativas, maquetas y prototipos rapidos',
            'mat2-badge': 'RESISTENTE',
            'mat2-sub': 'Maxima resistencia mecanica y termica.',
            'mat2-l1': 'Resistente a impactos, quimicos y UV',
            'mat2-l2': 'Soporta hasta 80 grados centigrados',
            'mat2-l3': 'Flexibilidad inherente, practicamente irrompible',
            'mat2-l4': 'Ideal para piezas mecanicas, repuestos y uso exterior',
            'mat3-badge': 'FLEXIBLE',
            'mat3-sub': 'Caucho sintetico para adaptabilidad.',
            'mat3-l1': 'Elastomero para piezas tipo goma',
            'mat3-l2': 'Fundas flexibles, sellos hermeticos, ruedas',
            'mat3-l3': 'Alta resistencia a abrasion y tension',
            'mat3-l4': 'Aislacion de impactos y vibraciones',
            // Applications
            'app-tag': 'APLICACIONES',
            'app-title': 'DONDE SE APLICA<br>LA IMPRESION 3D',
            'app1-t': 'PROTOTIPOS',
            'app1-d': 'Valida modelos, testea ensambles y evalua dimensiones y apariencia antes de invertir en moldes o inyeccion plastica.',
            'app2-t': 'PIEZAS FUNCIONALES',
            'app2-d': 'Componentes con tolerancias mecanicas, resistencia termica y estructural. Materiales tecnicos para tension, impacto y friccion.',
            'app3-t': 'REPUESTOS',
            'app3-d': 'Ingenieria inversa para recuperar piezas rotas o discontinuadas. Restaura la utilidad de equipos sin depender del fabricante original.',
            'app4-t': 'NEGOCIOS Y BRANDING',
            'app4-d': 'Merchandising personalizado, logos corporativos 3D, componentes de exhibicion y stands. Elementos unicos para identidad de marca.',
            // Why us
            'why-tag': 'POR QUE ELEGIR BTL',
            'why-title': 'VENTAJAS QUE MARCAN<br>LA DIFERENCIA',
            'why1-t': 'PRODUCCION PERSONALIZADA',
            'why1-d': 'Cada impresion se ajusta exactamente a los requerimientos geometricos y funcionales de tu modelo.',
            'why2-t': 'ENTREGAS RAPIDAS',
            'why2-d': 'Plazos ajustables y velocidad de respuesta para que tu proyecto no se detenga. Entrega promedio en 48hs.',
            'why3-t': 'ASESORAMIENTO TECNICO',
            'why3-d': 'Te acompañamos desde la idea hasta el material ideal y la configuracion de impresion optima.',
            'why4-t': 'MATERIALES DE CALIDAD',
            'why4-d': 'Polimeros seleccionados (PLA, PETG, TPU) garantizando estetica, resistencia y durabilidad.',
            // CTA
            'cta-tag': 'EMPEZA TU PROYECTO',
            'cta-title': '¿TENES UNA IDEA?<br>LA HACEMOS REALIDAD.',
            'cta-desc': 'Contanos que necesitas. Te respondemos en menos de 24 horas con un presupuesto detallado y sin compromiso.',
            'cta-btn': 'PEDIR PRESUPUESTO',
            // Contact
            'ct-tag': 'CONTACTO',
            'ct-title': 'HABLEMOS DE<br>TU PROYECTO',
            'ct-desc': 'Respondemos en menos de 24hs. Tambien podes escribirnos directo por WhatsApp.',
            'ct-label-name': 'NOMBRE',
            'ct-label-email': 'EMAIL',
            'ct-label-service': 'SERVICIO',
            'ct-label-msg': 'MENSAJE',
            'ct-ph-name': 'Tu nombre completo',
            'ct-ph-email': 'tu@email.com',
            'ct-ph-service': 'Selecciona un servicio',
            'ct-ph-msg': 'Contanos sobre tu proyecto...',
            'ct-label-phone': 'WHATSAPP',
            'ct-label-phone2': 'TELEFONO',
            'ct-ph-phone': '+54 11 ...',
            'ct-label-location': 'UBICACION',
            'ct-label-social': 'REDES SOCIALES',
            'ct-btn': 'ENVIAR MENSAJE',
            'ct-copy': '2026 BTL Impresion 3D. Todos los derechos reservados.',
            // Select options
            'ct-opt1': 'Impresion 3D personalizada',
            'ct-opt2': 'Diseño y modelado 3D',
            'ct-opt3': 'Prototipado rapido',
            'ct-opt4': 'Produccion en serie',
            'ct-opt5': 'Acabado y post-procesado',
            'ct-opt6': 'Consulta general',
        },
        en: {
            'nav-inicio': 'HOME',
            'nav-servicios': 'SERVICES',
            'nav-proceso': 'PROCESS',
            'nav-materiales': 'MATERIALS',
            'nav-contacto': 'CONTACT',
            'nav-cta': 'REQUEST QUOTE',
            'hero-tag': 'PROFESSIONAL 3D PRINTING SERVICE',
            'hero-t1': 'WE TURN',
            'hero-t2': 'FILES INTO',
            'hero-t3': 'REAL OBJECTS',
            'hero-sub': 'Professional custom 3D printing service. We turn your digital ideas into physical products with maximum resolution and attention to detail. For companies, designers and entrepreneurs.',
            'hero-btn1': 'VIEW SERVICES',
            'hero-btn2': 'HOW WE WORK',
            'hero-c1-label': 'PRECISION',
            'hero-c2-label': 'AVERAGE DELIVERY',
            'hero-c3-label': 'PROJECTS DELIVERED',
            'hero-c4-label': 'SATISFACTION',
            'srv-tag': 'OUR SERVICES',
            'srv-title': 'EVERYTHING YOU NEED<br>TO MATERIALIZE YOUR IDEA',
            'srv1-t': 'CUSTOM 3D PRINTING',
            'srv1-d': 'We receive your STL or STEP files and select the optimal technology, resolution, infill and thermal parameters. High dimensional control, efficient mesh configuration and basic post-processing included.',
            'srv2-t': 'CAD DESIGN & MODELING',
            'srv2-d': 'We create 3D models from scratch based on sketches, 2D plans or photo references. Parametric industrial modeling, additive manufacturing optimization and reverse engineering of broken or discontinued parts.',
            'srv3-t': 'RAPID PROTOTYPING',
            'srv3-d': 'Fast iterations to validate shapes, assemblies and functionality. We reduce up to 90% of time compared to traditional manufacturing. Ideal for startups, engineers and industrial designers who need to test before production.',
            'srv4-t': 'SERIES PRODUCTION',
            'srv4-d': 'Runs from 10 to 500 units with no initial mold costs. Quality control and homogeneity piece by piece. On-demand production with packaging included and coordinated logistics nationwide.',
            'proc-tag': 'HOW WE WORK',
            'proc-title': 'A SIMPLE PROCESS,<br>PRECISE RESULTS',
            'proc-desc': 'From idea to physical object in four steps. No complications, with tracking at every stage.',
            'proc1-t': 'INITIAL CONTACT',
            'proc1-d': 'Send us your file, sketch or idea. We evaluate feasibility, materials and timelines. You get a quote in less than 24 hours.',
            'proc2-t': 'DESIGN & VALIDATION',
            'proc2-d': 'If needed, we model or adjust the design. We show you previews before printing to make sure everything is correct.',
            'proc3-t': 'PRINTING & QUALITY CONTROL',
            'proc3-d': 'We set optimal parameters for each piece. Layer by layer printing with quality control during and after the process.',
            'proc4-t': 'DELIVERY',
            'proc4-d': 'Custom protective packaging. Nationwide shipping with tracking or pickup at our workshop in Buenos Aires.',
            'mat-tag': 'MATERIALS',
            'mat-title': 'WE CHOOSE THE MATERIAL<br>BASED ON YOUR PROJECT',
            'mat1-badge': 'MOST USED',
            'mat1-sub': 'Polylactic acid. The versatile standard.',
            'mat1-l1': 'Biodegradable, corn-derived',
            'mat1-l2': 'High resolution and detail level',
            'mat1-l3': 'Excellent surface finish',
            'mat1-l4': 'Ideal for decorative pieces, models and quick prototypes',
            'mat2-badge': 'RESISTANT',
            'mat2-sub': 'Maximum mechanical and thermal resistance.',
            'mat2-l1': 'Resistant to impacts, chemicals and UV',
            'mat2-l2': 'Withstands up to 80 degrees Celsius',
            'mat2-l3': 'Inherent flexibility, virtually unbreakable',
            'mat2-l4': 'Ideal for mechanical parts, replacements and outdoor use',
            'mat3-badge': 'FLEXIBLE',
            'mat3-sub': 'Synthetic rubber for adaptability.',
            'mat3-l1': 'Elastomer for rubber-like parts',
            'mat3-l2': 'Flexible cases, hermetic seals, wheels',
            'mat3-l3': 'High abrasion and tension resistance',
            'mat3-l4': 'Impact and vibration isolation',
            'app-tag': 'APPLICATIONS',
            'app-title': 'WHERE 3D PRINTING<br>IS APPLIED',
            'app1-t': 'PROTOTYPES',
            'app1-d': 'Validate models, test assemblies and evaluate dimensions and appearance before investing in molds or plastic injection.',
            'app2-t': 'FUNCTIONAL PARTS',
            'app2-d': 'Components with mechanical tolerances, thermal and structural resistance. Technical materials for tension, impact and friction.',
            'app3-t': 'SPARE PARTS',
            'app3-d': 'Reverse engineering to recover broken or discontinued parts. Restore equipment utility without depending on the original manufacturer.',
            'app4-t': 'BUSINESS & BRANDING',
            'app4-d': 'Custom merchandising, 3D corporate logos, exhibition components and stands. Unique elements for brand identity.',
            'why-tag': 'WHY CHOOSE BTL',
            'why-title': 'ADVANTAGES THAT MAKE<br>THE DIFFERENCE',
            'why1-t': 'CUSTOM PRODUCTION',
            'why1-d': 'Each print adjusts exactly to the geometric and functional requirements of your model.',
            'why2-t': 'FAST DELIVERY',
            'why2-d': 'Adjustable deadlines and fast response so your project never stops. Average delivery in 48 hours.',
            'why3-t': 'TECHNICAL ADVISORY',
            'why3-d': 'We guide you from the idea to the ideal material and optimal printing configuration.',
            'why4-t': 'QUALITY MATERIALS',
            'why4-d': 'Selected polymers (PLA, PETG, TPU) ensuring aesthetics, resistance and durability.',
            'cta-tag': 'START YOUR PROJECT',
            'cta-title': 'HAVE AN IDEA?<br>WE MAKE IT REAL.',
            'cta-desc': 'Tell us what you need. We respond within 24 hours with a detailed quote, no commitment.',
            'cta-btn': 'REQUEST QUOTE',
            'ct-tag': 'CONTACT',
            'ct-title': 'LET\'S TALK ABOUT<br>YOUR PROJECT',
            'ct-desc': 'We respond within 24 hours. You can also message us directly on WhatsApp.',
            'ct-label-name': 'NAME',
            'ct-label-email': 'EMAIL',
            'ct-label-service': 'SERVICE',
            'ct-label-msg': 'MESSAGE',
            'ct-ph-name': 'Your full name',
            'ct-ph-email': 'you@email.com',
            'ct-ph-service': 'Select a service',
            'ct-ph-msg': 'Tell us about your project...',
            'ct-label-phone': 'WHATSAPP',
            'ct-label-phone2': 'PHONE',
            'ct-ph-phone': '+54 11 ...',
            'ct-label-location': 'LOCATION',
            'ct-label-social': 'SOCIAL MEDIA',
            'ct-btn': 'SEND MESSAGE',
            'ct-copy': '2026 BTL Impresion 3D. All rights reserved.',
            'ct-opt1': 'Custom 3D printing',
            'ct-opt2': '3D design & modeling',
            'ct-opt3': 'Rapid prototyping',
            'ct-opt4': 'Series production',
            'ct-opt5': 'Finishing & post-processing',
            'ct-opt6': 'General inquiry',
        }
    };

    function applyLang(lang) {
        const t = translations[lang];
        if (!t) return;
        document.querySelectorAll('[data-i18n]').forEach((el) => {
            const key = el.getAttribute('data-i18n');
            if (t[key]) el.innerHTML = t[key];
        });
        document.querySelectorAll('[data-i18n-ph]').forEach((el) => {
            const key = el.getAttribute('data-i18n-ph');
            if (t[key]) el.placeholder = t[key];
        });
        document.documentElement.lang = lang === 'es' ? 'es' : 'en';
        localStorage.setItem(LANG_KEY, lang);
        // Update switch UI
        if (langSwitch) {
            langSwitch.setAttribute('data-active', lang);
            langBtns.forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.lang === lang);
            });
        }
    }

    // Detect language
    const savedLang = localStorage.getItem(LANG_KEY);
    let currentLang = 'es';
    if (savedLang) {
        currentLang = savedLang;
    } else {
        const browserLang = (navigator.language || navigator.userLanguage || 'es').slice(0, 2);
        currentLang = browserLang === 'en' ? 'en' : 'es';
    }
    applyLang(currentLang);

    langBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            const lang = btn.dataset.lang;
            if (lang !== currentLang) {
                currentLang = lang;
                applyLang(lang);
            }
        });
    });

    // --- Scroll Reveal (Intersection Observer with stagger) ---
    const revealElements = document.querySelectorAll('.reveal');
    let revealIndex = 0;
    const revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    // Stagger delay based on visible order
                    const siblings = entry.target.parentElement.querySelectorAll('.reveal');
                    let sibIndex = 0;
                    siblings.forEach((s, i) => { if (s === entry.target) sibIndex = i; });
                    entry.target.style.transitionDelay = (sibIndex % 6) * 80 + 'ms';
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1, rootMargin: '0px 0px -30px 0px' }
    );
    revealElements.forEach((el) => revealObserver.observe(el));

    // --- Navbar scroll effect ---
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 50);
    }, { passive: true });

    // --- Mobile toggle ---
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    if (navToggle) {
        navToggle.addEventListener('click', () => {
            navToggle.classList.toggle('active');
            navLinks.classList.toggle('active');
        });
        navLinks.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                navToggle.classList.remove('active');
                navLinks.classList.remove('active');
            });
        });
    }

    // --- Counter animation ---
    const counters = document.querySelectorAll('[data-count]');
    const counterObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.5 }
    );
    counters.forEach((el) => counterObserver.observe(el));

    function animateCounter(el) {
        const target = parseInt(el.dataset.count, 10);
        const duration = 1800;
        const start = performance.now();

        function update(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased);
            if (progress < 1) requestAnimationFrame(update);
        }

        requestAnimationFrame(update);
    }

    // --- Smooth anchor scroll ---
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (e) => {
            const id = anchor.getAttribute('href');
            if (id === '#') return;
            const target = document.querySelector(id);
            if (target) {
                e.preventDefault();
                const offset = navbar.offsetHeight + 20;
                const top = target.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top, behavior: 'smooth' });
            }
        });
    });

    // --- Navbar dark section detection ---
    const darkSections = document.querySelectorAll('.dark-section');
    function checkNavbarOverDark() {
        const navRect = navbar.getBoundingClientRect();
        const navCenter = navRect.top + navRect.height / 2;
        let overDark = false;
        darkSections.forEach((section) => {
            const rect = section.getBoundingClientRect();
            if (navCenter >= rect.top && navCenter <= rect.bottom) {
                overDark = true;
            }
        });
        navbar.classList.toggle('over-dark', overDark);
    }
    window.addEventListener('scroll', checkNavbarOverDark, { passive: true });
    checkNavbarOverDark();

    // --- Scroll to top button ---
    const scrollTopBtn = document.getElementById('scrollTop');
    if (scrollTopBtn) {
        window.addEventListener('scroll', () => {
            scrollTopBtn.classList.toggle('visible', window.scrollY > 400);
        }, { passive: true });

        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // --- Active nav link on scroll ---
    const sections = document.querySelectorAll('section[id]');
    const navLinkItems = document.querySelectorAll('.navbar__links a');
    const sectionObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const id = entry.target.getAttribute('id');
                    navLinkItems.forEach((link) => {
                        link.classList.toggle('active', link.getAttribute('href') === '#' + id);
                    });
                }
            });
        },
        { threshold: 0.3, rootMargin: '-80px 0px -50% 0px' }
    );
    sections.forEach((s) => sectionObserver.observe(s));

    // --- Service row hover tilt (subtle) ---
    const serviceRows = document.querySelectorAll('.service-row');
    serviceRows.forEach((row) => {
        row.addEventListener('mouseenter', () => {
            row.style.transform = 'translateX(8px)';
        });
        row.addEventListener('mouseleave', () => {
            row.style.transform = 'translateX(0)';
        });
    });

    // --- Form submit (envia a contact.php) ---
    const form = document.querySelector('.contact__form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const originalHTML = btn.innerHTML;
            const sendingHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M22 12a10 10 0 0 1-10 10"/></svg> ENVIANDO...';
            const okHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> MENSAJE ENVIADO';

            btn.innerHTML = sendingHTML;
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.7';

            try {
                const formData = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json().catch(() => ({}));

                if (res.ok && data.ok) {
                    btn.innerHTML = okHTML;
                    form.reset();
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                        btn.style.pointerEvents = '';
                        btn.style.opacity = '';
                    }, 3500);
                } else {
                    throw new Error(data.error || 'No se pudo enviar');
                }
            } catch (err) {
                btn.innerHTML = originalHTML;
                btn.style.pointerEvents = '';
                btn.style.opacity = '';
                alert('No pudimos enviar el mensaje. Escribinos por WhatsApp o intentalo de nuevo.');
            }
        });
    }

})();
