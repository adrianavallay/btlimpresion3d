-- ═══════════════════════════════════════════
-- BOLIVIANS REFORMES — Esquema MySQL
-- Importar en phpMyAdmin o: mysql -u USER -p DB < schema.sql
-- ═══════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  skey  VARCHAR(64) NOT NULL PRIMARY KEY,
  svalue TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NOT NULL,
  image VARCHAR(500) NOT NULL DEFAULT '',
  position INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  meta VARCHAR(160) NOT NULL DEFAULT '',
  image VARCHAR(500) NOT NULL DEFAULT '',
  position INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  author VARCHAR(120) NOT NULL,
  source VARCHAR(120) NOT NULL DEFAULT 'Google',
  body TEXT NOT NULL,
  position INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Contenido inicial (el actual de la web) ──

INSERT INTO settings (skey, svalue) VALUES
('hero_kicker',    'Reformas integrales · Gràcia, Barcelona'),
('hero_line1',     'Reformas'),
('hero_line2',     'que *transforman*'),
('hero_line3',     'tu hogar'),
('hero_sub',       'Cocinas, baños y reformas integrales de vivienda.\nRápidos, limpios y honestos — así nos describen nuestros clientes.'),
('hero_bg',        'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1800&q=80'),
('rating_value',   '5,0'),
('years_value',    '+10'),
('about_statement','Somos una empresa joven con operarios de gran experiencia. Nos dedicamos a la reforma integral de viviendas y a pequeños trabajos, siempre con presupuesto sin compromiso y acabados de calidad profesional.'),
('about_image',    'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=1000&q=80'),
('stat_projects',  '150'),
('stat_years',     '10'),
('stat_clients',   '100'),
('quote_text',     '«Buenos profesionales, limpios, rápidos y honestos.»'),
('quote_author',   '— Imma C., clienta en Barcelona'),
('phone_display',  '631 67 89 23'),
('phone_link',     '+34631678923'),
('whatsapp_number','34631678923'),
('whatsapp_msg',   'Hola, quiero pedir presupuesto para una reforma'),
('address_l1',     'Carrer de Ca l''Alegre de Dalt, 50'),
('address_l2',     'Gràcia, 08024 Barcelona'),
('zone',           'Barcelona ciudad y provincia'),
('hours_weekdays', '7:00 – 20:00'),
('hours_sat',      '7:00 – 15:00'),
('hours_sun',      '11:00 – 15:00'),
('maps_q',         'Carrer de Ca l''Alegre de Dalt 50, 08024 Barcelona')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

INSERT INTO services (name, description, image, position) VALUES
('Reforma integral',   'Tu vivienda de arriba a abajo: demolición, albañilería, instalaciones y acabados.', 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?w=800&q=75', 1),
('Cocinas',            'Diseño, fontanería y montaje de cocinas funcionales que dan gusto usar.', 'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=800&q=75', 2),
('Baños',              'Bañeras, duchas, lavabos y azulejos. Baños completos en tiempo récord.', 'https://images.unsplash.com/photo-1620626011761-996317b8d101?w=800&q=75', 3),
('Suelos y pavimentos','Colocación de parquet, cerámica y todo tipo de pavimentos.', 'https://images.unsplash.com/photo-1600121848594-d8644e57abab?w=800&q=75', 4),
('Pintura interior',   'Pintura de interiores y reparación de paneles de yeso con acabado fino.', 'https://images.unsplash.com/photo-1541123437800-1bb1317badc2?w=800&q=75', 5),
('Fontanería',         'Instalación y reparación de griferías, sanitarios y tuberías.', 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=800&q=75', 6);

INSERT INTO projects (title, meta, image, position, visible) VALUES
('Cocina abierta',   'Gràcia · Reforma completa',       'https://images.unsplash.com/photo-1556909212-d5b604d0c90d?w=1100&q=80', 1, 1),
('Baño principal',   'Eixample · Baño completo',        'https://images.unsplash.com/photo-1600566752355-35792bedcfea?w=1100&q=80', 2, 1),
('Salón y suelos',   'Sant Gervasi · Reforma parcial',  'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=1100&q=80', 3, 1),
('Piso completo',    'Vila de Gràcia · Reforma integral','https://images.unsplash.com/photo-1600573472592-401b489a3cdc?w=1100&q=80', 4, 1),
('Baño de invitados','Horta · Baño completo',           'https://images.unsplash.com/photo-1631679706909-1844bbd07221?w=1100&q=80', 5, 1);

INSERT INTO reviews (author, source, body, position, visible) VALUES
('Daniel Vilaseca',     'Local Guide · Google', 'Trabajo impecable, muy rápidos y profesionales. Nos quedamos muy satisfechos con los resultados. Los recomiendo sin duda.', 1, 1),
('Toñi Castellanos',    'Google',               'Muy profesionales y dispuestos a hacer lo que les pidas. Una experiencia muy satisfactoria.', 2, 1),
('David Mas',           'Google',               'Muy profesionales y educados, rápidos y eficaces. Totalmente recomendable: confianza y resolutivos.', 3, 1),
('Pankarita L.',        'Local Guide · Google', 'Me reformaron la parte de atrás de mi restaurante en tiempo récord.', 4, 1),
('Imma Carner',         'Google',               'Buenos profesionales, limpios, rápidos y honestos. Encantada con ellos.', 5, 1),
('Maria Antonia Arnau', 'Google',               'Hace mucho que los conozco y siempre trabajan muy bien.', 6, 1),
('Javier Ferreras',     'Local Guide · Google', 'Capacidad de respuesta, calidad, profesionalismo y valor. Remodelaciones, suelos, pintura y azulejos.', 7, 1),
('Tina Calle',          'Google',               'Calidad, profesionalismo y valor en la reparación de paneles de yeso.', 8, 1);

-- El primer usuario admin se crea desde /dyp-admin la primera vez que se abre.
