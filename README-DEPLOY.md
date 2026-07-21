# Bolivians Reformes — Despliegue en hosting PHP + MySQL

Web pública (`index.php`) + panel de administración en `/dyp-admin` para que el
cliente edite textos, fotos y datos de contacto sin tocar el diseño.

**Requisitos:** PHP 8.1+ con extensiones `pdo_mysql` y `gd` (estándar en
cualquier cPanel), y una base de datos MySQL/MariaDB.

## Pasos de instalación

1. **Base de datos** (no hay que "instalar" nada: en hosting compartido MySQL ya
   está corriendo):
   - cPanel → *MySQL® Databases* → crear una base de datos, crear un usuario y
     asignarlo a la base con todos los permisos
   - cPanel → *phpMyAdmin* → seleccionar la base → *Importar* → subir `schema.sql`

2. **Archivos**: subir todo el proyecto a `public_html/` (por FTP, gestor de
   archivos o `git clone` + `git pull` si el hosting tiene Git/SSH).

3. **Configuración**: copiar `config.sample.php` como `config.php` y rellenar
   host (normalmente `localhost`), nombre de la base, usuario y contraseña.

4. **Primer acceso**: abrir `https://TU-DOMINIO/dyp-admin/` — la primera vez
   pide crear el usuario y contraseña del administrador.

5. **Permisos**: si la subida de fotos falla, dar permisos de escritura a la
   carpeta `uploads/` (755 suele bastar; 775 si el hosting lo requiere).

## Notas

- `config.php` y `uploads/` están fuera de git: los cambios del cliente viven
  en el servidor y un `git pull` nunca los pisa.
- `index.html` es el demo estático de GitHub Pages; el `.htaccess` hace que el
  hosting sirva `index.php`. Cuando el hosting sea el definitivo, se puede
  borrar `index.html` sin más.
- Cambio de dominio: no requiere tocar nada del código (rutas relativas).
  Solo apuntar el dominio nuevo a la misma carpeta y activar SSL.
