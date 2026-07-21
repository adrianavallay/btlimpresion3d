<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/content.php';

$s        = settings();
$services = fetch_services();
$projects = fetch_projects();
$reviews  = fetch_reviews();

$phoneLink = e(setting('phone_link'));
$waLink    = 'https://wa.me/' . rawurlencode(setting('whatsapp_number'))
           . '?text=' . rawurlencode(setting('whatsapp_msg'));
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bolivians Reformes — Reformas integrales en Barcelona · Cocinas y Baños</title>
  <meta name="description" content="Bolivians Reformes: reformas integrales de viviendas, cocinas y baños en Barcelona (Gràcia). Presupuesto sin compromiso. Tel. <?= e(setting('phone_display')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=Fraunces:ital,opsz,wght@1,9..144,300;1,9..144,400;1,9..144,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='18' fill='%2316130F'/><text x='50' y='68' font-size='52' font-family='Arial' font-weight='900' fill='%23D97706' text-anchor='middle'>B</text></svg>">
</head>
<body>

  <!-- ══════════ PRELOADER ══════════ -->
  <div class="preloader" id="preloader">
    <div class="preloader__inner">
      <div class="preloader__words">
        <span class="preloader__word">Cocinas</span>
        <span class="preloader__word">Baños</span>
        <span class="preloader__word">Reformas</span>
        <span class="preloader__word">Bolivians<em>Reformes</em></span>
      </div>
      <div class="preloader__counter"><span id="loadCount">0</span>%</div>
    </div>
    <div class="preloader__curtain"></div>
  </div>

  <!-- ══════════ CURSOR ══════════ -->
  <div class="cursor" id="cursor"></div>
  <div class="cursor-ring" id="cursorRing"><span>Ver</span></div>

  <!-- ══════════ PROGRESS BAR ══════════ -->
  <div class="scroll-progress" id="scrollProgress"></div>

  <!-- ══════════ HEADER ══════════ -->
  <header class="header" id="header">
    <a href="#inicio" class="header__logo" data-scrollto>
      <span class="header__logo-mark">B</span>
      <span class="header__logo-text">Bolivians<em>Reformes</em></span>
    </a>
    <nav class="header__nav" id="mainNav" aria-label="Navegación principal">
      <a href="#nosotros" data-scrollto>Nosotros</a>
      <a href="#servicios" data-scrollto>Servicios</a>
      <a href="#proyectos" data-scrollto>Proyectos</a>
      <a href="#opiniones" data-scrollto>Opiniones</a>
      <a href="#contacto" data-scrollto>Contacto</a>
    </nav>
    <a href="tel:<?= $phoneLink ?>" class="header__cta magnetic">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      <span><?= e(setting('phone_display')) ?></span>
    </a>
    <button class="header__burger" id="burger" aria-label="Abrir menú" aria-expanded="false">
      <span></span><span></span>
    </button>
  </header>

  <!-- ══════════ MENÚ MÓVIL ══════════ -->
  <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <nav class="mobile-menu__nav">
      <a href="#nosotros" data-scrollto>Nosotros</a>
      <a href="#servicios" data-scrollto>Servicios</a>
      <a href="#proyectos" data-scrollto>Proyectos</a>
      <a href="#opiniones" data-scrollto>Opiniones</a>
      <a href="#contacto" data-scrollto>Contacto</a>
    </nav>
    <a href="tel:<?= $phoneLink ?>" class="mobile-menu__phone"><?= e(setting('phone_display')) ?></a>
  </div>

  <main id="smooth-content">

    <!-- ══════════ HERO ══════════ -->
    <section class="hero" id="inicio">
      <div class="hero__bg-wrap">
        <div class="hero__bg" style="background-image:url('<?= e(setting('hero_bg')) ?>')"></div>
        <div class="hero__overlay"></div>
      </div>
      <div class="hero__content">
        <p class="hero__kicker" id="heroKicker">
          <span class="hero__kicker-dot"></span>
          <?= e(setting('hero_kicker')) ?>
        </p>
        <h1 class="hero__title" id="heroTitle">
          <span class="hero__line"><?= fmt_em(setting('hero_line1')) ?></span>
          <span class="hero__line"><?= fmt_em(setting('hero_line2')) ?></span>
          <span class="hero__line"><?= fmt_em(setting('hero_line3')) ?></span>
        </h1>
        <p class="hero__sub" id="heroSub"><?= fmt_nl(setting('hero_sub')) ?></p>
        <div class="hero__actions" id="heroActions">
          <a href="#contacto" class="btn btn--solid magnetic" data-scrollto>Pide tu presupuesto<span class="btn__arrow">→</span></a>
          <a href="#proyectos" class="btn btn--ghost magnetic" data-scrollto>Ver proyectos</a>
        </div>
      </div>
      <div class="hero__badges" id="heroBadges">
        <div class="hero__badge">
          <span class="hero__badge-big"><?= e(setting('rating_value')) ?></span>
          <span class="hero__badge-small">★★★★★<br>Reseñas de Google</span>
        </div>
        <div class="hero__badge">
          <span class="hero__badge-big"><?= e(setting('years_value')) ?></span>
          <span class="hero__badge-small">años de oficio<br>en Barcelona</span>
        </div>
      </div>
      <div class="hero__scroll" id="heroScroll">
        <span>Desliza</span>
        <div class="hero__scroll-line"><i></i></div>
      </div>
    </section>

    <!-- ══════════ MARQUEE ══════════ -->
    <div class="marquee" aria-hidden="true">
      <div class="marquee__track" id="marqueeTrack">
        <?php for ($chunk = 0; $chunk < 2; $chunk++): ?>
        <div class="marquee__chunk">
          <?php foreach ($services as $svc): ?><span><?= e($svc['name']) ?></span><i>✦</i><?php endforeach; ?>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- ══════════ NOSOTROS ══════════ -->
    <section class="about" id="nosotros">
      <div class="about__grid">
        <div class="about__text">
          <p class="section-tag reveal-up">— Quiénes somos</p>
          <p class="about__statement" id="aboutStatement"><?= e(setting('about_statement')) ?></p>
          <div class="about__stats" id="aboutStats">
            <div class="stat">
              <span class="stat__num"><span class="counter" data-target="<?= e(setting('stat_projects')) ?>">0</span>+</span>
              <span class="stat__label">Proyectos terminados</span>
            </div>
            <div class="stat">
              <span class="stat__num"><span class="counter" data-target="<?= e(setting('stat_years')) ?>">0</span>+</span>
              <span class="stat__label">Años de experiencia</span>
            </div>
            <div class="stat">
              <span class="stat__num"><span class="counter" data-target="<?= e(setting('stat_clients')) ?>">0</span>%</span>
              <span class="stat__label">Clientes satisfechos</span>
            </div>
          </div>
        </div>
        <div class="about__media">
          <div class="img-reveal">
            <img src="<?= e(setting('about_image')) ?>" alt="Planos y herramientas de obra" loading="lazy" width="1000" height="1250">
          </div>
          <div class="about__card reveal-up">
            <p><?= e(setting('quote_text')) ?></p>
            <span><?= e(setting('quote_author')) ?></span>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ SERVICIOS ══════════ -->
    <section class="services" id="servicios">
      <div class="services__head">
        <p class="section-tag reveal-up">— Servicios</p>
        <h2 class="section-title split-lines">Todo lo que tu casa necesita</h2>
      </div>
      <ul class="services__list" id="servicesList">
        <?php foreach ($services as $i => $svc): ?>
        <li class="service" data-img="<?= e($svc['image']) ?>">
          <span class="service__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h3 class="service__name"><?= e($svc['name']) ?></h3>
          <p class="service__desc"><?= e($svc['description']) ?></p>
          <span class="service__arrow">→</span>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="service-preview" id="servicePreview"><img src="" alt="" id="servicePreviewImg"></div>
    </section>

    <!-- ══════════ PROYECTOS (scroll horizontal) ══════════ -->
    <section class="projects" id="proyectos">
      <div class="projects__pin" id="projectsPin">
        <div class="projects__head">
          <p class="section-tag">— Proyectos</p>
          <h2 class="section-title">Trabajo reciente</h2>
          <div class="projects__progress"><i id="projectsProgress"></i></div>
        </div>
        <div class="projects__track" id="projectsTrack">
          <?php foreach ($projects as $p): ?>
          <article class="project">
            <div class="project__img"><img src="<?= e($p['image']) ?>" alt="<?= e($p['title']) ?>" loading="lazy"></div>
            <div class="project__meta"><h3><?= e($p['title']) ?></h3><span><?= e($p['meta']) ?></span></div>
          </article>
          <?php endforeach; ?>
          <article class="project project--cta">
            <a href="#contacto" data-scrollto class="magnetic">
              <span>Tu proyecto<br><em>aquí</em></span>
              <i>→</i>
            </a>
          </article>
        </div>
      </div>
    </section>

    <!-- ══════════ PROCESO ══════════ -->
    <section class="process" id="proceso">
      <div class="process__head">
        <p class="section-tag reveal-up">— Cómo trabajamos</p>
        <h2 class="section-title split-lines">De la idea a las llaves</h2>
      </div>
      <ol class="process__steps" id="processSteps">
        <li class="step">
          <span class="step__num">1</span>
          <h3>Visita y presupuesto</h3>
          <p>Visitamos tu vivienda, escuchamos lo que necesitas y te damos presupuesto sin compromiso.</p>
        </li>
        <li class="step">
          <span class="step__num">2</span>
          <h3>Planificación</h3>
          <p>Cerramos materiales, plazos y calendario. Sabrás qué pasa cada semana.</p>
        </li>
        <li class="step">
          <span class="step__num">3</span>
          <h3>Ejecución</h3>
          <p>Operarios propios con experiencia. Obra rápida, limpia y bien rematada.</p>
        </li>
        <li class="step">
          <span class="step__num">4</span>
          <h3>Entrega</h3>
          <p>Repaso final contigo y entrega. Solo terminamos cuando tú estás satisfecho.</p>
        </li>
      </ol>
    </section>

    <!-- ══════════ OPINIONES ══════════ -->
    <section class="reviews" id="opiniones">
      <div class="reviews__head">
        <p class="section-tag reveal-up">— Opiniones reales</p>
        <h2 class="section-title split-lines">Lo dicen nuestros clientes</h2>
        <p class="reviews__score reveal-up">★★★★★ <b><?= e(setting('rating_value')) ?></b> en Google</p>
      </div>
      <div class="reviews__grid" id="reviewsGrid">
        <?php foreach ($reviews as $r): ?>
        <figure class="review">
          <div class="review__stars">★★★★★</div>
          <blockquote><?= e($r['body']) ?></blockquote>
          <figcaption><b><?= e($r['author']) ?></b><span><?= e($r['source']) ?></span></figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ══════════ CTA ══════════ -->
    <section class="cta">
      <p class="cta__kicker reveal-up">¿Tienes una reforma en mente?</p>
      <a href="tel:<?= $phoneLink ?>" class="cta__title magnetic" id="ctaTitle">
        <span class="cta__word">¿Habla</span><span class="cta__word">mos?</span>
      </a>
      <p class="cta__sub reveal-up">Llámanos o escríbenos — presupuesto sin compromiso en 48&nbsp;h.</p>
    </section>

    <!-- ══════════ CONTACTO ══════════ -->
    <section class="contact" id="contacto">
      <div class="contact__grid">
        <div class="contact__info">
          <p class="section-tag reveal-up">— Contacto</p>
          <h2 class="section-title split-lines">Estamos en Gràcia</h2>
          <ul class="contact__data">
            <li class="reveal-up">
              <span>Dirección</span>
              <a href="https://maps.google.com/?q=<?= rawurlencode(setting('maps_q')) ?>" target="_blank" rel="noopener"><?= e(setting('address_l1')) ?><br><?= e(setting('address_l2')) ?></a>
            </li>
            <li class="reveal-up">
              <span>Teléfono</span>
              <a href="tel:<?= $phoneLink ?>">+34 <?= e(setting('phone_display')) ?></a>
            </li>
            <li class="reveal-up">
              <span>Zona de trabajo</span>
              <p><?= e(setting('zone')) ?></p>
            </li>
            <li class="reveal-up">
              <span>Horario</span>
              <table class="contact__hours">
                <tr><td>Lunes – Viernes</td><td><?= e(setting('hours_weekdays')) ?></td></tr>
                <tr><td>Sábado</td><td><?= e(setting('hours_sat')) ?></td></tr>
                <tr><td>Domingo</td><td><?= e(setting('hours_sun')) ?></td></tr>
              </table>
            </li>
          </ul>
        </div>
        <div class="contact__map img-reveal">
          <iframe
            src="https://maps.google.com/maps?q=<?= rawurlencode(setting('maps_q')) ?>&t=&z=15&ie=UTF8&iwloc=&output=embed"
            title="Mapa: Bolivians Reformes, <?= e(setting('address_l1')) ?>"
            loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
      </div>
    </section>

    <!-- ══════════ FOOTER ══════════ -->
    <footer class="footer">
      <div class="footer__top">
        <a href="#inicio" data-scrollto class="footer__back magnetic">↑ Volver arriba</a>
        <nav class="footer__nav" aria-label="Navegación del pie">
          <a href="#nosotros" data-scrollto>Nosotros</a>
          <a href="#servicios" data-scrollto>Servicios</a>
          <a href="#proyectos" data-scrollto>Proyectos</a>
          <a href="#contacto" data-scrollto>Contacto</a>
        </nav>
      </div>
      <div class="footer__giant" id="footerGiant" aria-hidden="true">BOLIVIANS</div>
      <div class="footer__bottom">
        <p>© <?= date('Y') ?> Bolivians Reformes · Barcelona</p>
        <p>Reformas integrales · Cocinas · Baños</p>
      </div>
    </footer>

  </main>

  <!-- ══════════ WHATSAPP FLOTANTE ══════════ -->
  <a href="<?= e($waLink) ?>"
     class="whatsapp-fab" id="whatsappFab" target="_blank" rel="noopener"
     aria-label="Escríbenos por WhatsApp">
    <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
    </svg>
    <span class="whatsapp-fab__ring"></span>
  </a>

  <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollToPlugin.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/SplitText.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
