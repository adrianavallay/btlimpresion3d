<?php
/**
 * Subida de imágenes: valida tipo, redimensiona con GD y
 * guarda en /uploads. Devuelve la ruta relativa o null.
 */

declare(strict_types=1);

/**
 * @param array $file     Entrada de $_FILES
 * @param int   $maxWidth Ancho máximo del resultado
 * @return string|null    p.ej. "uploads/foto-6543ab.jpg"
 */
function handle_image_upload(array $file, int $maxWidth = 1600): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('Error al subir la imagen (código ' . $file['error'] . ').');
        return null;
    }
    if ($file['size'] > 12 * 1024 * 1024) {
        flash('La imagen supera los 12 MB.');
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        flash('El archivo no es una imagen válida.');
        return null;
    }

    [$w, $h, $type] = $info;
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        default        => null,
    };
    if (!$src) {
        flash('Formato no soportado. Usa JPG, PNG o WebP.');
        return null;
    }

    // Redimensionar manteniendo proporción
    if ($w > $maxWidth) {
        $nw = $maxWidth;
        $nh = (int) round($h * $maxWidth / $w);
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    $dir = __DIR__ . '/../../uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'img-' . bin2hex(random_bytes(6)) . '.jpg';
    $ok = imagejpeg($src, $dir . '/' . $name, 84);
    imagedestroy($src);

    if (!$ok) {
        flash('No se pudo guardar la imagen en el servidor.');
        return null;
    }
    return 'uploads/' . $name;
}
