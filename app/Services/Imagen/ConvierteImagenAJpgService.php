<?php

namespace App\Services\Imagen;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Convierte cualquier imagen subida (PNG, WEBP, GIF, BMP...) a JPG antes de
 * guardarla en el disco `public`: un JPG de calidad 82 pesa una fracción de
 * un PNG/BMP equivalente, y el hosting compartido del proyecto (ver
 * .ai/rules/css.md, migración de PDFs a FPDF por el mismo motivo) no tiene
 * margen para acumular fotos de evidencia o imágenes de catálogo sin
 * comprimir.
 *
 * Único punto del proyecto que toca GD: si mañana se cambia de librería
 * (Imagick, Intervention) alcanza con tocar esta clase.
 */
class ConvierteImagenAJpgService
{
    private const CALIDAD_JPG = 82;

    /**
     * Lado mayor tope, en píxeles. Una imagen de referencia o una foto de
     * evidencia no necesita más resolución que esta para verse bien en
     * pantalla o en el PDF; por encima de esto solo se paga espacio en disco.
     */
    private const LADO_MAXIMO = 1600;

    /**
     * Convierte el archivo subido y lo guarda en el disco `public` bajo
     * `$directorio`. Devuelve la ruta relativa (para guardar en la BD).
     */
    public function guardar(UploadedFile $archivo, string $directorio): string
    {
        return $this->escribir($this->recursoDesdeArchivo($archivo->getRealPath()), $directorio);
    }

    /**
     * Copia una imagen YA guardada en el disco `public` (p. ej. la de un
     * producto del catálogo) a otro directorio, re-codificándola a JPG por si
     * el original no lo era. Devuelve null si el origen no existe: la línea
     * que pidió "usar imagen del producto" queda sin imagen en vez de romper
     * el guardado completo.
     */
    public function copiarDesde(?string $rutaOrigen, string $directorio): ?string
    {
        if (! $rutaOrigen || ! Storage::disk('public')->exists($rutaOrigen)) {
            return null;
        }

        return $this->escribir(
            $this->recursoDesdeArchivo(Storage::disk('public')->path($rutaOrigen)),
            $directorio,
        );
    }

    public function borrar(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk('public')->delete($ruta);
        }
    }

    private function escribir(GdImage $recurso, string $directorio): string
    {
        $recurso = $this->limitarTamano($recurso);

        ob_start();
        imagejpeg($recurso, null, self::CALIDAD_JPG);
        $contenido = ob_get_clean();
        imagedestroy($recurso);

        $ruta = trim($directorio, '/').'/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($ruta, $contenido);

        return $ruta;
    }

    private function recursoDesdeArchivo(string $ruta): GdImage
    {
        $info = @getimagesize($ruta);

        if ($info === false) {
            throw new InvalidArgumentException('El archivo no es una imagen válida.');
        }

        $recurso = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG => imagecreatefrompng($ruta),
            IMAGETYPE_GIF => imagecreatefromgif($ruta),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                ? imagecreatefromwebp($ruta)
                : throw new InvalidArgumentException('Este servidor no puede leer imágenes WEBP.'),
            IMAGETYPE_BMP => function_exists('imagecreatefrombmp')
                ? imagecreatefrombmp($ruta)
                : throw new InvalidArgumentException('Este servidor no puede leer imágenes BMP.'),
            default => throw new InvalidArgumentException('Formato de imagen no soportado.'),
        };

        if ($recurso === false) {
            throw new InvalidArgumentException('No se pudo leer la imagen.');
        }

        // PNG/GIF/WEBP pueden traer transparencia: JPG no la soporta, así que
        // se aplana sobre blanco antes de convertir (si no, el canal alfa se
        // pinta negro).
        if (in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            $recurso = $this->aplanarSobreBlanco($recurso);
        } elseif ($info[2] === IMAGETYPE_JPEG) {
            // Fotos de celular: sin esto salen giradas 90°/180°, porque el
            // sensor guarda la orientación en EXIF y no rota los píxeles.
            $recurso = $this->corregirOrientacion($recurso, $ruta);
        }

        return $recurso;
    }

    private function aplanarSobreBlanco(GdImage $original): GdImage
    {
        $ancho = imagesx($original);
        $alto = imagesy($original);

        $fondo = imagecreatetruecolor($ancho, $alto);
        $blanco = imagecolorallocate($fondo, 255, 255, 255);
        imagefill($fondo, 0, 0, $blanco);

        imagealphablending($fondo, true);
        imagecopy($fondo, $original, 0, 0, 0, 0, $ancho, $alto);
        imagedestroy($original);

        return $fondo;
    }

    private function corregirOrientacion(GdImage $recurso, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $recurso;
        }

        $grados = match (@exif_read_data($ruta)['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($grados === 0) {
            return $recurso;
        }

        $rotado = imagerotate($recurso, $grados, 0);
        imagedestroy($recurso);

        return $rotado;
    }

    private function limitarTamano(GdImage $recurso): GdImage
    {
        $ancho = imagesx($recurso);
        $alto = imagesy($recurso);
        $mayor = max($ancho, $alto);

        if ($mayor <= self::LADO_MAXIMO) {
            return $recurso;
        }

        $escala = self::LADO_MAXIMO / $mayor;
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $redimensionada = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagecopyresampled($redimensionada, $recurso, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($recurso);

        return $redimensionada;
    }
}
