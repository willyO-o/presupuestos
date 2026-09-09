{{--
    Pie de todas las páginas de todos los documentos.

    Chromium lo trata como una plantilla aparte del documento: NO hereda el
    <style> de la página, así que todo el estilo va inline y el tamaño de
    fuente tiene que declararse (por defecto es 0 y el pie sale invisible).

    `pageNumber` y `totalPages` son clases que Chromium reemplaza al imprimir;
    no son texto nuestro y no se traducen.
--}}
<div style="width: 100%; padding: 0 12mm; font-family: 'Segoe UI', system-ui, sans-serif; font-size: 7pt; color: #94a3b8; display: flex; justify-content: space-between; align-items: center;">
    <span>{{ $empresa['nombre'] }} · {{ $empresa['telefono_visible'] }} · {{ str_replace('https://', '', $empresa['web']) }}</span>
    <span>{{ $titulo }} · Página <span class="pageNumber"></span> de <span class="totalPages"></span></span>
</div>
