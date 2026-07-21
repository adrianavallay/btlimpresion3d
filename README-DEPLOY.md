# Bolivians Reformes — Despliegue en hosting PHP + MySQL

Web pública (`index.php`) + panel de administración en `/dyp-admin` para que el
cliente edite textos, fotos y datos de contacto sin tocar el diseño.

**Requisitos:** PHP 8.1+ con extensiones `pdo_mysql` y `gd` (estándar en
cualquier cPanel), y una base de datos MySQL/MariaDB.

## Instalación fácil (con el instalador web)

1. **Archivos**: subir todo el proyecto a `public_html/` (git clone, FTP o ZIP).
2. **Base de datos**: cPanel → *MySQL® Databases* → crear una base y un usuario
   con todos los permisos (si no existen ya).
3. Abrir en el navegador **`https://TU-DOMINIO/install.php`**, poner las
   credenciales de la base y pulsar *Instalar*. El instalador crea las tablas
   con el contenido inicial, genera `config.php` y se borra solo.
4. **Primer acceso**: en `https://TU-DOMINIO/dyp-admin/` crear el usuario y
   contraseña del administrador.

## Instalación manual (alternativa)

1. cPanel → *phpMyAdmin* → seleccionar la base → *Importar* → subir `schema.sql`
2. Copiar `config.sample.php` como `config.php` y rellenar host (normalmente
   `localhost`), nombre de la base, usuario y contraseña.
3. Borrar `install.php` del servidor.

**Permisos**: si la subida de fotos falla, dar permisos de escritura a la
carpeta `uploads/` (755 suele bastar; 775 si el hosting lo requiere).

## Notas

- `config.php` y `uploads/` están fuera de git: los cambios del cliente viven
  en el servidor y un `git pull` nunca los pisa.
- `index.html` es el demo estático de GitHub Pages; el `.htaccess` hace que el
  hosting sirva `index.php`. Cuando el hosting sea el definitivo, se puede
  borrar `index.html` sin más.
- Cambio de dominio: no requiere tocar nada del código (rutas relativas).
  Solo apuntar el dominio nuevo a la misma carpeta y activar SSL.
