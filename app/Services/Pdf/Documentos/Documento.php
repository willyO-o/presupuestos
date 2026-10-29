<?php

namespace App\Services\Pdf\Documentos;

use FPDF;
use Illuminate\Support\Carbon;

/**
 * Base de todos los documentos PDF del sistema.
 *
 * Trae el membrete, el pie numerado y los ladrillos de maqueta que comparten
 * los seis documentos (bloques de datos, tabla de detalle, escalera de
 * totales, recuadros, firmas). Un documento concreto solo declara su
 * `identificacion()` y arma su `cuerpo()` con estos ladrillos: si cambia la
 * identidad de la empresa o el pie, cambian acá y en ningún otro sitio.
 *
 * **Por qué FPDF y no un motor HTML**: el sistema se despliega en hosting
 * compartido, donde no hay Node ni Chromium headless y la memoria por proceso
 * es poca. FPDF es PHP puro, pesa unos pocos cientos de KB y dibuja por
 * coordenadas, así que funciona igual en cualquier servidor. El costo es que
 * no hay CSS: la maqueta se programa, y por eso vive concentrada acá.
 *
 * **Encoding**: las fuentes del núcleo de FPDF son de un byte (cp1252), no
 * UTF-8 — FPDF 1.9 solo acepta UTF-8 en las propiedades del documento, no en
 * el texto. Por eso TODO string que se dibuje pasa antes por `t()`. Escribir
 * `Cell(0, 5, 'Cotización')` directamente imprime basura; no hay atajo.
 *
 * **Medidas**: milímetros para posiciones y puntos para tipografía, que es
 * como FPDF trabaja. La hoja es tamaño carta vertical (216 × 279 mm): es el
 * formato de oficina que se usa e imprime en el país.
 */
abstract class Documento extends FPDF
{
    /*
    |--------------------------------------------------------------------------
    | Paleta y métrica
    |--------------------------------------------------------------------------
    |
    | Los colores son los tokens de marca de resources/css/app.css pasados a
    | RGB, que es lo único que entiende FPDF. Si cambia la identidad, se cambia
    | allá y se replica acá.
    */

    protected const AZUL = [28, 127, 196];

    protected const AZUL_OSCURO = [20, 95, 151];

    protected const OSCURO = [20, 22, 26];

    protected const TEXTO = [51, 65, 85];

    protected const SUAVE = [100, 116, 139];

    protected const TENUE = [148, 163, 184];

    protected const BORDE = [226, 232, 240];

    protected const FONDO = [248, 250, 252];

    /** Fondo celeste de las cabeceras y fichas de las páginas de resultados. */
    protected const CELESTE = [236, 244, 251];

    protected const VERDE = [23, 166, 115];

    protected const AMBAR = [247, 184, 75];

    protected const ROJO = [226, 86, 77];

    protected const BLANCO = [255, 255, 255];

    /** Márgenes de la hoja en mm: el inferior es mayor para que quepa el pie. */
    protected const MARGEN_LATERAL = 12.0;

    protected const MARGEN_SUPERIOR = 14.0;

    protected const MARGEN_INFERIOR = 18.0;

    /**
     * Milímetros de alto de línea por punto de tipografía.
     *
     * Equivale al `line-height: 1.45` de la maqueta original: 1 pt son
     * 0.3528 mm, y 0.3528 × 1.37 ≈ 0.484 deja el mismo aire entre renglones
     * sin estirar los bloques cortos.
     */
    protected const INTERLINEA = 0.484;

    /** Aire interno de las celdas de la tabla de detalle, en mm. */
    protected const RESPIRO = 1.6;

    /**
     * Tipografía del documento.
     *
     * Helvetica es una de las fuentes del núcleo de FPDF: no hay que embarcar
     * ni instalar archivos y el PDF pesa mucho menos. La maqueta original
     * pedía Montserrat/Roboto pero caía igual a la pila del sistema, así que
     * no se pierde nada real.
     */
    protected const FUENTE = 'Helvetica';

    /** Fuente monoespaciada para números de documento. */
    protected const FUENTE_MONO = 'Courier';

    /** Ancho del logo de la empresa en el membrete, en mm. */
    protected const ANCHO_LOGO = 46.0;

    /**
     * Proporción ancho/alto del logo, para reservar su espacio si
     * `getimagesize()` no pudiera medir el archivo.
     */
    protected const PROPORCION_LOGO = 4.1;

    /**
     * Parte del documento que nombra el pie ("Resumen de resultados", "Anexo
     * de pedidos"). Con `null` el pie es el de siempre (datos de contacto).
     * Se cambia DESPUÉS del `AddPage()` que abre la parte nueva: FPDF dibuja el
     * pie de una hoja al abrir la siguiente.
     */
    protected ?string $tramoPie = null;

    /**
     * @param  array<string, mixed>  $empresa  Datos de `config('sitio.empresa')`.
     */
    public function __construct(
        protected readonly string $titulo,
        protected readonly array $empresa,
        protected readonly Carbon $generadoEn,
    ) {
        parent::__construct('P', 'mm', 'Letter');

        $this->SetMargins(self::MARGEN_LATERAL, self::MARGEN_SUPERIOR, self::MARGEN_LATERAL);
        $this->SetAutoPageBreak(true, self::MARGEN_INFERIOR);
        // El pie dice "Página 2 de 5": {nb} se reemplaza por el total al cerrar.
        $this->AliasNbPages();
        // Las propiedades del documento SÍ van en UTF-8 (es la novedad de
        // FPDF 1.9): el segundo argumento se lo dice, y FPDF las pasa a
        // UTF-16BE. Convertirlas antes con `t()` produciría bytes cp1252
        // etiquetados como UTF-8 y un aviso de iconv.
        $this->SetTitle($titulo, true);
        $this->SetAuthor($empresa['nombre'], true);
        $this->SetCreator($empresa['nombre'], true);
    }

    /*
    |--------------------------------------------------------------------------
    | Contrato de cada documento
    |--------------------------------------------------------------------------
    */

    /**
     * Bloque derecho del membrete: qué documento es y cuál es su número.
     *
     * Se pide como array y no se dibuja directamente porque `Header()` corre
     * en CADA página, y así el documento concreto no tiene que preocuparse de
     * la paginación.
     *
     * @return array{tipo: string, numero: string, subtitulo?: ?string, etiqueta?: ?array{texto: string, tono: string}}
     */
    abstract protected function identificacion(): array;

    /** Cuerpo del documento, armado con los ladrillos de esta clase. */
    abstract protected function cuerpo(): void;

    /**
     * Dibuja el documento entero. Idempotente: llamarlo dos veces no duplica
     * páginas.
     */
    public function armar(): static
    {
        if ($this->PageNo() === 0) {
            $this->AddPage();
            $this->cuerpo();
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Membrete y pie (FPDF los llama solo, en cada página)
    |--------------------------------------------------------------------------
    */

    public function Header(): void
    {
        $y = self::MARGEN_SUPERIOR;
        $ancho = $this->anchoUtil();
        $anchoMarca = $ancho * 0.55;

        // --- Izquierda: la marca. El logo va primero; el nombre queda debajo
        // como texto (dato buscable del documento y respaldo si el PNG falta).
        $altoLogo = $this->logo(self::MARGEN_LATERAL, $y, self::ANCHO_LOGO);

        $this->SetXY(self::MARGEN_LATERAL, $y + $altoLogo + 2);
        $this->fuente(8, '', self::SUAVE);
        $this->Cell($anchoMarca, $this->altoLinea(8), $this->t(sprintf(
            '%s · %s',
            $this->empresa['nombre'],
            $this->empresa['lema'],
        )), 0, 2);

        $this->fuente(7.5, '', self::TENUE);
        $this->Ln(1);
        $this->Cell($anchoMarca, $this->altoLinea(7.5), $this->t(sprintf(
            '%s · %s, %s',
            $this->empresa['direccion'],
            $this->empresa['ciudad'],
            $this->empresa['departamento'],
        )), 0, 2);
        // El NIT va en el membrete (el cliente lo pide para su contabilidad);
        // el nombre del propietario NO: solo aparece en la firma de los
        // documentos que él firma, ver `firmasEmpresa()`.
        $this->Cell($anchoMarca, $this->altoLinea(7.5), $this->t(implode(' · ', array_filter([
            $this->empresa['telefono_visible'],
            $this->empresa['email'],
            ! empty($this->empresa['nit']) ? 'NIT '.$this->empresa['nit'] : null,
        ]))), 0, 2);

        $finMarca = $this->GetY();

        // --- Derecha: qué documento es. Alineado a la derecha del área útil.
        $identificacion = $this->identificacion();
        $anchoId = $ancho - $anchoMarca - 4;
        $x = self::MARGEN_LATERAL + $anchoMarca + 4;

        $this->SetXY($x, $y);
        $this->fuente(12, 'B', self::AZUL_OSCURO);
        $this->Cell($anchoId, $this->altoLinea(12), $this->t(mb_strtoupper($identificacion['tipo'], 'UTF-8')), 0, 2, 'R');

        $this->SetFont(self::FUENTE_MONO, 'B', 11);
        $this->setTexto(self::OSCURO);
        $this->Cell($anchoId, $this->altoLinea(11), $this->t($identificacion['numero']), 0, 2, 'R');

        if ($subtitulo = ($identificacion['subtitulo'] ?? null)) {
            $this->fuente(8, '', self::SUAVE);
            $this->Cell($anchoId, $this->altoLinea(8), $this->t($subtitulo), 0, 2, 'R');
        }

        if ($etiqueta = ($identificacion['etiqueta'] ?? null)) {
            $this->etiqueta($etiqueta['texto'], $etiqueta['tono'], $x, $anchoId);
        }

        // --- Regla azul bajo el membrete.
        $base = max($finMarca, $this->GetY()) + 3;
        $this->setTrazo(self::AZUL);
        $this->SetLineWidth(0.6);
        $this->Line(self::MARGEN_LATERAL, $base, $this->w - self::MARGEN_LATERAL, $base);
        $this->SetLineWidth(0.2);

        $this->SetXY(self::MARGEN_LATERAL, $base + 5);
    }

    /**
     * Página nueva sin perder el color del texto que se estaba escribiendo.
     *
     * FPDF restaura su color interno después del membrete, pero no la copia
     * `colorActual` que lee `flujo()`: un párrafo que saltaba de página
     * heredaba el blanco de la etiqueta del membrete y salía invisible.
     *
     * @param  string  $orientation
     * @param  string|array<int, float>  $size
     * @param  int  $rotation
     */
    public function AddPage($orientation = '', $size = '', $rotation = 0): void
    {
        $color = $this->colorActual;

        parent::AddPage($orientation, $size, $rotation);

        $this->colorActual = $color;
    }

    public function Footer(): void
    {
        $this->SetY(-13);
        $this->fuente(7, '', self::TENUE);

        $izquierda = sprintf(
            '%s · %s · %s',
            $this->empresa['nombre'],
            $this->empresa['telefono_visible'],
            str_replace('https://', '', $this->empresa['web']),
        );

        // Ojo con concatenar fuera de `t()`: un '·' escrito en el fuente es
        // UTF-8 de dos bytes y saldría como "Â·". Se arma la frase entera y se
        // convierte una sola vez. {nb} lo reemplaza FPDF por el total de
        // páginas al cerrar el documento, y sobrevive a la conversión.
        $derecha = sprintf('%s · Página %d de {nb}', $this->titulo, $this->PageNo());

        // Con tramo vacío ('') el pie dice solo "Empresa | Título".
        if ($this->tramoPie !== null) {
            $izquierda = implode(' | ', array_filter([$this->empresa['nombre'], $this->titulo, $this->tramoPie], fn (string $parte): bool => $parte !== ''));
            $derecha = sprintf('Página %d de {nb}', $this->PageNo());
        }

        $mitad = $this->anchoUtil() / 2;
        $this->Cell($mitad, 4, $this->t($izquierda), 0, 0, 'L');
        $this->Cell($mitad, 4, $this->t($derecha), 0, 0, 'R');
    }

    /**
     * Etiqueta de estado: rectángulo de color con el texto en blanco.
     *
     * Va pegada a la derecha del bloque de identificación, así que se calcula
     * su ancho a partir del texto en vez de ocupar toda la columna.
     */
    protected function etiqueta(string $texto, string $tono, float $xColumna, float $anchoColumna): void
    {
        $fondo = match ($tono) {
            'exito' => self::VERDE,
            'aviso' => self::AMBAR,
            'peligro' => self::ROJO,
            'info' => self::AZUL,
            default => self::SUAVE,
        };

        $this->fuente(7.5, 'B', $tono === 'aviso' ? self::OSCURO : self::BLANCO);

        $etiqueta = $this->t(mb_strtoupper($texto, 'UTF-8'));
        $ancho = $this->GetStringWidth($etiqueta) + 5;
        $alto = 4.4;

        $x = $xColumna + $anchoColumna - $ancho;
        $y = $this->GetY() + 1;

        $this->setRelleno($fondo);
        $this->Rect($x, $y, $ancho, $alto, 'F');

        $this->SetXY($x, $y);
        $this->Cell($ancho, $alto, $etiqueta, 0, 2, 'C');
        $this->SetY($y + $alto);
    }

    /**
     * Dibuja el logo de la empresa arriba a la izquierda del membrete y
     * devuelve el alto que ocupó, en mm.
     *
     * La imagen se lee del disco POR RUTA (`public_path()`), nunca por URL:
     * FPDF no resuelve HTTP. Si el archivo falta o no es una imagen que FPDF
     * sepa leer, cae al nombre de la empresa en texto grande y el documento se
     * emite igual — el membrete no puede depender de que exista un PNG.
     */
    protected function logo(float $x, float $y, float $ancho): float
    {
        $relativa = $this->empresa['logo'] ?? null;
        $ruta = $relativa ? public_path($relativa) : null;

        if ($ruta !== null && is_file($ruta)) {
            try {
                $medida = @getimagesize($ruta);
                $alto = ($medida && $medida[0] > 0)
                    ? $ancho * $medida[1] / $medida[0]
                    : $ancho / self::PROPORCION_LOGO;

                $this->Image($ruta, $x, $y, $ancho);

                return $alto;
            } catch (\Throwable) {
                // Cae al nombre en texto, abajo.
            }
        }

        $this->SetXY($x, $y);
        $this->fuente(15, 'B', self::OSCURO);
        $this->Cell($ancho, $this->altoLinea(15), $this->t($this->empresa['nombre']), 0, 2);

        return $this->altoLinea(15);
    }

    /*
    |--------------------------------------------------------------------------
    | Ladrillos de maqueta
    |--------------------------------------------------------------------------
    */

    /**
     * Rótulo de sección: mayúsculas pequeñas y grises, como el `.doc-rotulo`
     * de la maqueta original.
     */
    protected function rotulo(string $texto): void
    {
        $this->fuente(7, 'B', self::TENUE);
        $this->SetX(self::MARGEN_LATERAL);
        $this->Cell($this->anchoUtil(), $this->altoLinea(7), $this->t(mb_strtoupper($texto, 'UTF-8')), 0, 2);
    }

    /**
     * Las dos columnas de datos bajo el membrete (cliente / detalles).
     *
     * Se dibujan por separado y la Y final es la del bloque más alto: con
     * `Cell` en cadena la segunda columna arrancaría donde terminó la primera.
     *
     * @param  list<array{rotulo: string, titulo?: ?string, lineas?: list<array{0: ?string, 1: ?string}|string>}>  $columnas
     */
    protected function bloqueDatos(array $columnas): void
    {
        $separacion = 8.0;
        $cantidad = max(count($columnas), 1);
        $ancho = ($this->anchoUtil() - $separacion * ($cantidad - 1)) / $cantidad;

        $inicio = $this->GetY();
        $final = $inicio;

        foreach (array_values($columnas) as $indice => $columna) {
            $x = self::MARGEN_LATERAL + $indice * ($ancho + $separacion);
            $this->SetXY($x, $inicio);

            $this->fuente(7, 'B', self::TENUE);
            $this->Cell($ancho, $this->altoLinea(7), $this->t(mb_strtoupper($columna['rotulo'], 'UTF-8')), 0, 2);

            if (! empty($columna['titulo'])) {
                $this->fuente(10, 'B', self::OSCURO);
                $this->SetX($x);
                $this->flujo($ancho, [['texto' => $columna['titulo']]], $this->altoLinea(10));
            }

            foreach ($columna['lineas'] ?? [] as $linea) {
                $this->SetX($x);
                $this->fuente(8.5);

                $trozos = is_array($linea)
                    ? [
                        ['texto' => $linea[0].' ', 'color' => self::TENUE],
                        ['texto' => (string) ($linea[1] ?? '—'), 'negrita' => (bool) ($linea[2] ?? false)],
                    ]
                    : [['texto' => (string) $linea]];

                $this->flujo($ancho, $trozos, $this->altoLinea(8.5));
            }

            $final = max($final, $this->GetY());
        }

        $this->SetXY(self::MARGEN_LATERAL, $final);
    }

    /**
     * Tabla de detalle con cabecera que se repite en cada página.
     *
     * Es el ladrillo que justifica esta clase. FPDF no sabe nada de tablas:
     * hay que medir cada fila ANTES de dibujarla (una descripción larga ocupa
     * tres renglones), decidir si entra en lo que queda de página y, si no,
     * saltar y volver a pintar la cabecera. Sin esto, una cotización de
     * veinte líneas parte una fila por la mitad entre dos páginas.
     *
     * Con `$resumen` la cabecera va en texto normal sobre un fondo celeste:
     * es la tabla corta de las páginas de resultados de un informe, que se
     * lee como parte del texto y no como un listado.
     *
     * @param  list<array{titulo: string, ancho: float, alineacion?: string}>  $columnas  `ancho` en % del área útil.
     * @param  list<list<string|array{texto?: string, nota?: ?string, negrita?: bool, imagen?: ?string, color?: array<int, int>}>>  $filas
     */
    protected function tabla(array $columnas, array $filas, bool $resumen = false): void
    {
        $anchos = array_map(fn (array $c): float => $this->anchoUtil() * $c['ancho'] / 100, $columnas);

        $this->cabeceraTabla($columnas, $anchos, $resumen);

        foreach (array_values($filas) as $indice => $fila) {
            $celdas = array_map($this->normalizarCelda(...), array_values($fila));
            $alto = $this->altoFila($celdas, $anchos);

            if ($this->GetY() + $alto > $this->PageBreakTrigger) {
                $this->AddPage();
                $this->cabeceraTabla($columnas, $anchos, $resumen);
            }

            $this->dibujarFila($celdas, $anchos, $columnas, $alto, $indice % 2 === 1);
        }

        $this->SetXY(self::MARGEN_LATERAL, $this->GetY() + ($resumen ? 3 : 1));
    }

    /**
     * Escalera de totales, alineada a la derecha como en la maqueta original.
     *
     * @param  list<array{etiqueta: string, valor: string, principal?: bool}>  $filas
     */
    protected function totales(array $filas, float $porcentaje = 46): void
    {
        $ancho = $this->anchoUtil() * $porcentaje / 100;
        $x = $this->w - self::MARGEN_LATERAL - $ancho;

        // Se mide el bloque entero con las mismas alturas con que se dibuja
        // abajo: si no entra, salta de página completo. Una estimación corta
        // dejaba el "Total" solo en una hoja nueva.
        $altoBloque = 3 + array_sum(array_map(
            fn (array $fila) => ($fila['principal'] ?? false) ? 2 + 6.5 + 3.2 : 5.2 + 2.4,
            $filas,
        ));

        $this->asegurarEspacio($altoBloque);
        $this->SetY($this->GetY() + 3);

        foreach (array_values($filas) as $indice => $fila) {
            $principal = (bool) ($fila['principal'] ?? false);
            $y = $this->GetY() + ($principal ? 2 : 0);

            if ($indice > 0 || $principal) {
                $this->setTrazo($principal ? self::OSCURO : self::BORDE);
                $this->SetLineWidth($principal ? 0.5 : 0.2);
                $this->Line($x, $y, $x + $ancho, $y);
                $this->SetLineWidth(0.2);
            }

            $alto = $principal ? 6.5 : 5.2;
            $this->SetXY($x, $y + ($principal ? 1.6 : 1.2));

            $this->fuente($principal ? 11 : 9, $principal ? 'B' : '', $principal ? self::OSCURO : self::TEXTO);
            $this->Cell($ancho / 2, $alto, $this->t($fila['etiqueta']), 0, 0, 'L');
            $this->Cell($ancho / 2, $alto, $this->t($fila['valor']), 0, 0, 'R');

            $this->SetXY(self::MARGEN_LATERAL, $y + $alto + ($principal ? 1.6 : 1.2));
        }
    }

    /**
     * Recuadro de condiciones o avisos.
     *
     * El contenido se declara en bloques (`titulo`, `nota`, `lista`, `rotulo`,
     * `rango`, `formula`) porque hay que medir su altura ANTES de pintar el fondo: en
     * FPDF el rectángulo se dibuja primero y el texto encima, así que no se
     * puede "dejar que crezca" como una caja de CSS.
     *
     * @param  list<array{tipo: string, texto?: string, trozos?: list<array<string, mixed>>, items?: list<string>}>  $bloques
     */
    protected function recuadro(array $bloques, string $tono = 'neutro', bool $centrado = false): void
    {
        $relleno = match ($tono) {
            'destacado' => [237, 245, 251],
            'aviso' => [254, 246, 230],
            'exito' => [234, 246, 241],
            // Lo que hay que atender primero (prioridad alta).
            'alerta' => [253, 237, 237],
            default => self::FONDO,
        };

        $borde = match ($tono) {
            'destacado' => [186, 216, 238],
            'aviso' => [246, 221, 172],
            'exito' => [190, 228, 211],
            'alerta' => [245, 200, 198],
            default => self::BORDE,
        };

        $anchoInterno = $this->anchoUtil() - 8;
        $alto = $this->altoBloques($bloques, $anchoInterno) + 6;

        $this->asegurarEspacio($alto + 4);
        $this->SetY($this->GetY() + 4);

        $y = $this->GetY();
        $this->setRelleno($relleno);
        $this->setTrazo($borde);
        $this->Rect(self::MARGEN_LATERAL, $y, $this->anchoUtil(), $alto, 'FD');

        $this->SetXY(self::MARGEN_LATERAL + 4, $y + 3);
        $this->dibujarBloques($bloques, $anchoInterno, self::MARGEN_LATERAL + 4, $centrado);

        $this->SetXY(self::MARGEN_LATERAL, $y + $alto);
    }

    /**
     * Sección suelta con rótulo y texto libre (observaciones, indicaciones).
     */
    protected function seccion(string $rotulo, string $texto): void
    {
        $this->asegurarEspacio(16);
        $this->SetY($this->GetY() + 4);
        $this->rotulo($rotulo);

        $this->fuente(8, '', self::SUAVE);
        $this->SetX(self::MARGEN_LATERAL);
        $this->flujo($this->anchoUtil(), [['texto' => $texto]], $this->altoLinea(8));
    }

    /**
     * Líneas de firma.
     *
     * Van al final y nunca se parten: son la razón de ser de la nota de
     * entrega, y media firma en la página siguiente no vale nada.
     *
     * @param  list<array{titulo: string, pie: string}>  $firmas
     */
    protected function firmas(array $firmas): void
    {
        $separacion = 14.0;
        $cantidad = max(count($firmas), 1);
        $ancho = ($this->anchoUtil() - $separacion * ($cantidad - 1)) / $cantidad;

        $this->asegurarEspacio(28);
        $this->SetY($this->GetY() + 18);

        $y = $this->GetY();

        foreach (array_values($firmas) as $indice => $firma) {
            $x = self::MARGEN_LATERAL + $indice * ($ancho + $separacion);

            $this->setTrazo(self::OSCURO);
            $this->Line($x, $y, $x + $ancho, $y);

            $this->SetXY($x, $y + 1.5);
            $this->fuente(8, 'B', self::OSCURO);
            $this->Cell($ancho, $this->altoLinea(8), $this->t($firma['titulo']), 0, 2, 'C');

            $this->fuente(8, '', self::SUAVE);
            $this->SetX($x);
            $this->Cell($ancho, $this->altoLinea(8), $this->t($firma['pie']), 0, 2, 'C');
        }

        $this->SetXY(self::MARGEN_LATERAL, $y + 12);
    }

    /**
     * Firmas de la empresa: el nombre y cargo salen de
     * Administración → Firmas de documentos (`$empresa['firmas']`, por defecto
     * el propietario). Es el único lugar donde se imprime el nombre de quien
     * firma por la empresa; el membrete lleva solo el NIT.
     *
     * Si varias firmas recaen en la misma persona (hoy el propietario firma
     * producción y control de calidad) se imprime UNA sola firma con sus
     * funciones juntas: dos líneas con el mismo nombre no aportan nada.
     *
     * @param  list<string>  $claves  Claves de FirmaDocumento::FIRMAS.
     * @param  list<array{titulo: string, pie: string}>  $otras  Firmas ajenas a la empresa (el cliente, el proveedor), van a la derecha.
     */
    protected function firmasEmpresa(array $claves, array $otras = []): void
    {
        $porPersona = [];

        foreach ($claves as $clave) {
            $firma = $this->firmaConfigurada($clave);

            if ($firma === null) {
                continue;
            }

            $llave = mb_strtolower($firma['nombre'].'|'.$firma['cargo']);
            $porPersona[$llave] ??= ['titulo' => $firma['nombre'], 'cargo' => $firma['cargo'], 'funciones' => []];
            $porPersona[$llave]['funciones'][] = $firma['funcion'];
        }

        $firmas = array_map(fn (array $persona): array => [
            'titulo' => $persona['titulo'],
            'pie' => $persona['cargo'].' · '.$this->unirFunciones($persona['funciones']),
        ], array_values($porPersona));

        $firmas = [...$firmas, ...$otras];

        if ($firmas === []) {
            return;
        }

        if (count($firmas) === 1) {
            $this->firmaCentrada($firmas[0]);

            return;
        }

        $this->firmas($firmas);
    }

    /**
     * @return array{nombre: string, cargo: string, funcion: string}|null
     */
    private function firmaConfigurada(string $clave): ?array
    {
        $firma = $this->empresa['firmas'][$clave] ?? null;

        if ($firma === null) {
            $propietario = $this->empresa['propietario'] ?? null;

            return $propietario ? ['nombre' => $propietario, 'cargo' => 'Propietario', 'funcion' => $this->empresa['nombre']] : null;
        }

        return ['nombre' => $firma['nombre'], 'cargo' => $firma['cargo'], 'funcion' => $firma['funcion'] ?? $this->empresa['nombre']];
    }

    /**
     * "Recibe la orden" + "Aprueba antes de entregar" →
     * "Recibe la orden y aprueba antes de entregar".
     *
     * @param  list<string>  $funciones
     */
    private function unirFunciones(array $funciones): string
    {
        $funciones = array_values(array_unique($funciones));
        $ultima = array_pop($funciones);

        if ($funciones === []) {
            return $ultima;
        }

        return implode(', ', $funciones).' y '.mb_strtolower(mb_substr($ultima, 0, 1)).mb_substr($ultima, 1);
    }

    /**
     * Una sola firma, centrada y con una línea de largo razonable (una firma
     * a todo el ancho de la hoja se ve rara).
     *
     * @param  array{titulo: string, pie: string}  $firma
     */
    private function firmaCentrada(array $firma): void
    {
        $ancho = 80.0;
        $x = self::MARGEN_LATERAL + ($this->anchoUtil() - $ancho) / 2;

        $this->asegurarEspacio(34);
        $this->SetY($this->GetY() + 22);
        $y = $this->GetY();

        $this->setTrazo(self::OSCURO);
        $this->Line($x, $y, $x + $ancho, $y);

        $this->SetXY($x, $y + 1.5);
        $this->fuente(8.5, 'B', self::OSCURO);
        $this->Cell($ancho, $this->altoLinea(8.5), $this->t($firma['titulo']), 0, 2, 'C');

        $this->fuente(8, '', self::SUAVE);
        $this->SetX($x);
        $this->Cell($ancho, $this->altoLinea(8), $this->t($firma['pie']), 0, 2, 'C');

        $this->SetXY(self::MARGEN_LATERAL, $y + 12);
    }

    /**
     * Nota legal del pie del documento, separada por una regla fina.
     *
     * @param  list<array{texto: string, negrita?: bool}>  $trozos
     */
    protected function notaLegal(array $trozos): void
    {
        $ancho = $this->anchoUtil();
        $alto = $this->flujo($ancho, $trozos, $this->altoLinea(7.5), dibujar: false);

        $this->asegurarEspacio($alto + 8);
        $this->SetY($this->GetY() + 6);

        $this->setTrazo(self::BORDE);
        $this->Line(self::MARGEN_LATERAL, $this->GetY(), $this->w - self::MARGEN_LATERAL, $this->GetY());

        $this->SetXY(self::MARGEN_LATERAL, $this->GetY() + 2.5);
        $this->fuente(7.5, '', self::SUAVE);
        $this->flujo($ancho, $trozos, $this->altoLinea(7.5));
    }

    /*
    |--------------------------------------------------------------------------
    | Ladrillos de los informes (reportes y BI)
    |--------------------------------------------------------------------------
    */

    /** Título de una sección de informe, con una regla fina debajo. */
    protected function tituloSeccion(string $texto): void
    {
        $this->asegurarEspacio(24);
        $this->fuente(13, 'B', self::AZUL_OSCURO);
        $this->SetX(self::MARGEN_LATERAL);
        $this->Cell($this->anchoUtil(), $this->altoLinea(13), $this->t($texto), 0, 2);

        $y = $this->GetY() + 0.5;
        $this->setTrazo(self::BORDE);
        $this->Line(self::MARGEN_LATERAL, $y, $this->w - self::MARGEN_LATERAL, $y);
        $this->SetXY(self::MARGEN_LATERAL, $y + 2);
    }

    /**
     * Apertura de un capítulo de informe: "01 / RESUMEN PARA LA GERENCIA",
     * la pregunta que responde en grande y una bajada que dice qué entra en
     * las cifras. Pensado para empezar hoja: no deja espacio antes.
     */
    protected function encabezadoCapitulo(string $numero, string $rotulo, string $pregunta, ?string $bajada = null): void
    {
        $this->asegurarEspacio(40);
        $ancho = $this->anchoUtil();

        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente(8, '', self::OSCURO);
        $this->Cell($ancho, $this->altoLinea(8), $this->t($numero.' / '.mb_strtoupper($rotulo, 'UTF-8')), 0, 2);

        $this->espacio(2);
        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente(20, '', self::AZUL_OSCURO);
        $this->flujo($ancho, [['texto' => $pregunta]], $this->altoLinea(20));

        if ($bajada !== null) {
            $this->espacio(0.5);
            $this->SetX(self::MARGEN_LATERAL);
            $this->fuente(9, '', self::OSCURO);
            $this->flujo($ancho, [['texto' => $bajada]], $this->altoLinea(9));
        }

        $this->espacio(3);
    }

    /**
     * Título de un apartado dentro de un capítulo ("Lo que requiere
     * atención", "A. Margen de los pedidos…"): azul, sin regla.
     */
    protected function apartado(string $texto): void
    {
        $this->asegurarEspacio(22);
        $this->espacio(3);
        $this->SetX(self::MARGEN_LATERAL);
        $this->fuente(12, '', self::AZUL_OSCURO);
        $this->flujo($this->anchoUtil(), [['texto' => $texto]], $this->altoLinea(12));
        $this->espacio(1.5);
    }

    /**
     * Párrafo que arranca con un rótulo en negrita ("Lectura del resultado:
     * …"). `$tamano` 9 para lo que se lee; 8 para las aclaraciones.
     */
    protected function parrafoRotulado(?string $rotulo, string $texto, float $tamano = 9): void
    {
        $trozos = array_values(array_filter([
            $rotulo !== null ? ['texto' => $rotulo.' ', 'negrita' => true, 'color' => self::OSCURO] : null,
            ['texto' => $texto],
        ]));

        $this->fuente($tamano, '', self::OSCURO);
        $alto = $this->flujo($this->anchoUtil(), $trozos, $this->altoLinea($tamano), dibujar: false);

        $this->asegurarEspacio($alto + 2);
        $this->SetX(self::MARGEN_LATERAL);
        $this->flujo($this->anchoUtil(), $trozos, $this->altoLinea($tamano));
        $this->espacio(2);
    }

    /**
     * Fichas de cifras principales: cada una es una franja celeste con el
     * rótulo y la cifra, y debajo la explicación de qué cuenta esa cifra.
     * Reemplaza a las tarjetas de `indicadores()` cuando el informe tiene que
     * leerse sin conocer el sistema.
     *
     * @param  list<array{rotulo: string, valor: string, explicacion: string, tono?: string}>  $fichas
     */
    protected function fichas(array $fichas): void
    {
        $ancho = $this->anchoUtil();
        $columnaValor = $ancho * 0.34;
        $anchoTexto = $ancho - $columnaValor - 2 * self::RESPIRO;

        foreach ($fichas as $ficha) {
            $this->fuente(8.5);
            $altoExplicacion = $this->flujo($anchoTexto, [['texto' => $ficha['explicacion']]], $this->altoLinea(8.5), dibujar: false);
            $this->asegurarEspacio(7 + $altoExplicacion + 6);

            $y = $this->GetY();
            $this->setRelleno(self::CELESTE);
            $this->Rect(self::MARGEN_LATERAL, $y, $ancho, 7, 'F');
            $this->setTrazo(self::AZUL);
            $this->SetLineWidth(0.3);
            $this->Line(self::MARGEN_LATERAL, $y + 7, $this->w - self::MARGEN_LATERAL, $y + 7);
            $this->SetLineWidth(0.2);

            $this->SetXY(self::MARGEN_LATERAL + self::RESPIRO, $y);
            $this->fuente(8.5, '', self::OSCURO);
            $this->Cell($anchoTexto, 7, $this->t(mb_strtoupper($ficha['rotulo'], 'UTF-8')));

            $this->SetXY($this->w - self::MARGEN_LATERAL - $columnaValor + self::RESPIRO, $y);
            $this->fuente(8.5, '', ($ficha['tono'] ?? null) === 'peligro' ? self::ROJO : self::OSCURO);
            $this->Cell($columnaValor - 2 * self::RESPIRO, 7, $this->t($ficha['valor']));

            $this->SetXY(self::MARGEN_LATERAL + self::RESPIRO, $y + 7 + 2);
            $this->fuente(8.5, '', self::TEXTO);
            $this->flujo($anchoTexto, [['texto' => $ficha['explicacion']]], $this->altoLinea(8.5));

            $this->SetXY(self::MARGEN_LATERAL, $this->GetY() + 4);
        }
    }

    /** Párrafo de texto corrido que no se parte entre páginas. */
    protected function parrafo(string $texto): void
    {
        $this->fuente(8.5, '', self::TEXTO);
        $alto = $this->flujo($this->anchoUtil(), [['texto' => $texto]], $this->altoLinea(8.5), dibujar: false);

        $this->asegurarEspacio($alto + 2);
        $this->SetX(self::MARGEN_LATERAL);
        $this->flujo($this->anchoUtil(), [['texto' => $texto]], $this->altoLinea(8.5), 'L');
        $this->espacio(1.5);
    }

    /**
     * Subtítulo de un bloque dentro de una sección (gráfico o tabla), con una
     * línea de explicación debajo: de dónde sale el número.
     */
    protected function subtitulo(string $titulo, ?string $explicacion = null): void
    {
        $this->asegurarEspacio(30);
        $this->espacio(3);

        $this->fuente(10, 'B', self::OSCURO);
        $this->SetX(self::MARGEN_LATERAL);
        $this->Cell($this->anchoUtil(), $this->altoLinea(10), $this->t($titulo), 0, 2);

        if ($explicacion !== null) {
            $this->fuente(7.5, '', self::SUAVE);
            $this->SetX(self::MARGEN_LATERAL);
            $this->flujo($this->anchoUtil(), [['texto' => $explicacion]], $this->altoLinea(7.5));
        }

        $this->espacio(1);
    }

    /**
     * Fila de indicadores: las cifras que se leen primero, cada una en su
     * tarjeta con un filete de color arriba. El tono `peligro` también pinta
     * la cifra (una pérdida tiene que verse sin leer el rótulo).
     *
     * @param  list<array{rotulo: string, valor: string, nota?: ?string, tono?: string}>  $indicadores
     */
    protected function indicadores(array $indicadores): void
    {
        $separacion = 3.0;
        $cantidad = max(count($indicadores), 1);
        $ancho = ($this->anchoUtil() - $separacion * ($cantidad - 1)) / $cantidad;
        $interno = $ancho - 6;

        // La nota explica la cifra ("Atraso medio de los 237 pedidos
        // entregados tarde"): puede ocupar hasta dos renglones. Todas las
        // tarjetas toman el alto de la que más necesite, para que la fila
        // quede pareja.
        $this->fuente(7);
        $renglonesNota = 1;

        foreach ($indicadores as $indicador) {
            if (! empty($indicador['nota'])) {
                $alto = $this->flujo($interno, [['texto' => $indicador['nota']]], $this->altoLinea(7), dibujar: false);
                $renglonesNota = max($renglonesNota, min(2, (int) round($alto / $this->altoLinea(7))));
            }
        }

        $alto = 21.0 + ($renglonesNota - 1) * $this->altoLinea(7);

        $this->asegurarEspacio($alto + 4);
        $y = $this->GetY() + 1;

        foreach (array_values($indicadores) as $indice => $indicador) {
            $x = self::MARGEN_LATERAL + $indice * ($ancho + $separacion);
            $tono = $indicador['tono'] ?? 'info';
            $acento = match ($tono) {
                'exito' => self::VERDE,
                'aviso' => self::AMBAR,
                'peligro' => self::ROJO,
                default => self::AZUL,
            };

            $this->setRelleno(self::FONDO);
            $this->setTrazo(self::BORDE);
            $this->Rect($x, $y, $ancho, $alto, 'FD');
            $this->setRelleno($acento);
            $this->Rect($x, $y, $ancho, 0.9, 'F');

            $this->SetXY($x + 3, $y + 3.2);
            $this->fuente(6.5, 'B', self::TENUE);
            $this->Cell($interno, $this->altoLinea(6.5), $this->t($this->recortar(mb_strtoupper($indicador['rotulo'], 'UTF-8'), $interno)), 0, 2);

            $this->SetX($x + 3);
            $this->fuente(13.5, 'B', $tono === 'peligro' ? self::ROJO : self::OSCURO);
            $this->Cell($interno, $this->altoLinea(13.5) + 0.6, $this->t($this->recortar($indicador['valor'], $interno)), 0, 2);

            if (! empty($indicador['nota'])) {
                $this->SetX($x + 3);
                $this->fuente(7, '', self::SUAVE);
                $this->flujo($interno, [['texto' => $indicador['nota']]], $this->altoLinea(7));
            }
        }

        $this->SetXY(self::MARGEN_LATERAL, $y + $alto + 2);
    }

    /**
     * Acorta un texto con "..." hasta que entre en `$ancho` mm con la fuente
     * ACTIVA. Se mide ya convertido a cp1252, que es lo que se dibuja.
     */
    protected function recortar(string $texto, float $ancho): string
    {
        if ($this->GetStringWidth($this->t($texto)) <= $ancho) {
            return $texto;
        }

        $corto = $texto;

        while (mb_strlen($corto) > 1 && $this->GetStringWidth($this->t($corto.'...')) > $ancho) {
            $corto = mb_substr($corto, 0, -1);
        }

        return rtrim($corto).'...';
    }

    /*
    |--------------------------------------------------------------------------
    | Motor de texto
    |--------------------------------------------------------------------------
    */

    /**
     * Escribe (o solo mide) un párrafo hecho de trozos con distinto estilo.
     *
     * Es el primitivo del que cuelga todo lo demás: `MultiCell` de FPDF no
     * sabe cambiar de fuente ni de color a media línea, y los documentos están
     * llenos de "Cliente: <negrita>Delizia S.A.</negrita>". Acá el texto se
     * parte en palabras, se mide cada una con SU estilo y se van llenando
     * renglones.
     *
     * Con `dibujar: false` solo devuelve la altura, que es como los recuadros
     * saben qué tan grande hacer el fondo antes de escribir dentro.
     *
     * @param  list<array{texto: string, negrita?: bool, color?: array<int, int>, tamano?: float}>  $trozos
     * @return float Altura consumida, en mm.
     */
    protected function flujo(float $ancho, array $trozos, ?float $altoLinea = null, string $alineacion = 'L', bool $dibujar = true): float
    {
        $altoLinea ??= $this->altoLinea();

        $fuenteBase = $this->FontFamily;
        $estiloBase = $this->FontStyle;
        $tamanoBase = $this->FontSizePt;
        $colorBase = $this->colorActual;

        $palabras = $this->palabrasConEstilo($trozos, $tamanoBase, $estiloBase, $colorBase);
        $lineas = $this->repartirEnLineas($palabras, $ancho, $fuenteBase);

        $x = $this->GetX();

        if ($dibujar) {
            foreach ($lineas as $linea) {
                $this->SetX($x + $this->sangriaDe($linea, $ancho, $alineacion));

                foreach ($linea['palabras'] as $palabra) {
                    $this->SetFont($fuenteBase, $palabra['estilo'], $palabra['tamano']);
                    $this->setTexto($palabra['color']);
                    $this->Cell($palabra['ancho'], $altoLinea, $palabra['texto'], 0, 0);
                }

                $this->SetXY($x, $this->GetY() + $altoLinea);
            }
        }

        // Se restaura siempre, también al solo medir: medir obliga a cambiar
        // la fuente activa para poder pedir `GetStringWidth`, y dejarla
        // cambiada haría que el siguiente bloque se dibujara con la de otro.
        $this->SetFont($fuenteBase, $estiloBase, $tamanoBase);
        $this->setTexto($colorBase);

        return count($lineas) * $altoLinea;
    }

    /**
     * Parte los trozos en palabras sueltas, cada una con su estilo ya resuelto
     * y su ancho medido. El ancho se mide acá, una sola vez, porque medir
     * obliga a cambiar la fuente activa y hacerlo dentro del bucle de
     * repartido sería cambiarla dos veces por palabra.
     *
     * @param  list<array<string, mixed>>  $trozos
     * @return list<array{texto: string, estilo: string, tamano: float, color: array<int, int>, ancho: float, salto: bool, pegar: bool}>
     */
    private function palabrasConEstilo(array $trozos, float $tamanoBase, string $estiloBase, array $colorBase): array
    {
        $fuente = $this->FontFamily;
        $palabras = [];
        $pegarPrimera = false;

        foreach ($trozos as $trozo) {
            $estilo = ($trozo['negrita'] ?? false) ? 'B' : $estiloBase;
            $tamano = (float) ($trozo['tamano'] ?? $tamanoBase);
            $color = $trozo['color'] ?? $colorBase;
            $crudo = (string) $trozo['texto'];

            $this->SetFont($fuente, $estilo, $tamano);

            $pegar = $pegarPrimera;
            // Los trozos se comportan como `<span>` pegados: si el anterior no
            // terminaba en espacio y este no empieza con uno, van sin separar.
            // Así "…el código " + <b>COT-1</b> + "." imprime "COT-1." y no
            // "COT-1 .", que es lo que salía al meter un espacio entre cada par
            // de palabras sin mirar cómo venían escritas.
            $pegarPrimera = $crudo !== '' && ! preg_match('/\s$/u', $crudo);

            if ($crudo !== '' && preg_match('/^\s/u', $crudo)) {
                $pegar = false;
            }

            // Los saltos de línea del usuario (observaciones) se respetan:
            // eran `white-space: pre-line` en la maqueta original.
            foreach (explode("\n", str_replace("\r\n", "\n", $crudo)) as $indice => $parrafo) {
                if ($indice > 0) {
                    $palabras[] = ['texto' => '', 'estilo' => $estilo, 'tamano' => $tamano, 'color' => $color, 'ancho' => 0.0, 'salto' => true, 'pegar' => false];
                    $pegar = false;
                }

                $previa = null;

                foreach (preg_split('/\s+/u', trim($parrafo), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
                    $texto = $this->t($palabra);

                    // "5,1 %" no se parte: el signo ("%", "%." o "%),") va
                    // pegado a su número, con el espacio, y baja de renglón
                    // con él en vez de quedar solo al principio del siguiente.
                    if ($previa !== null && str_starts_with($palabra, '%')) {
                        $palabras[$previa]['texto'] .= ' '.$texto;
                        $palabras[$previa]['ancho'] = $this->GetStringWidth($palabras[$previa]['texto']);

                        continue;
                    }

                    $previa = count($palabras);
                    $palabras[] = [
                        'texto' => $texto,
                        'estilo' => $estilo,
                        'tamano' => $tamano,
                        'color' => $color,
                        'ancho' => $this->GetStringWidth($texto),
                        'salto' => false,
                        'pegar' => $pegar,
                    ];

                    $pegar = false;
                }
            }
        }

        return $palabras;
    }

    /**
     * Reparte las palabras en renglones que quepan en `$ancho`.
     *
     * Una palabra sola más ancha que la columna (un correo largo, una URL) se
     * parte por caracteres: sin esto se desbordaría fuera de la celda, que es
     * el desborde silencioso clásico de FPDF.
     *
     * @param  list<array<string, mixed>>  $palabras
     * @return list<array{palabras: list<array<string, mixed>>, ancho: float}>
     */
    private function repartirEnLineas(array $palabras, float $ancho, string $fuente): array
    {
        $lineas = [];
        $actual = [];
        $usado = 0.0;

        $cerrar = function () use (&$lineas, &$actual, &$usado): void {
            $lineas[] = ['palabras' => $actual, 'ancho' => $usado];
            $actual = [];
            $usado = 0.0;
        };

        foreach ($palabras as $palabra) {
            if ($palabra['salto']) {
                $cerrar();

                continue;
            }

            $espacio = ($actual === [] || $palabra['pegar']) ? 0.0 : $this->anchoEspacio($fuente, $palabra);

            if ($actual !== [] && $usado + $espacio + $palabra['ancho'] > $ancho) {
                $cerrar();
                $espacio = 0.0;
            }

            if ($palabra['ancho'] > $ancho) {
                foreach ($this->partirPalabra($palabra, $ancho, $fuente) as $pedazo) {
                    $lineas[] = ['palabras' => [$pedazo], 'ancho' => $pedazo['ancho']];
                }

                continue;
            }

            if ($espacio > 0) {
                $palabra['texto'] = ' '.$palabra['texto'];
                $palabra['ancho'] += $espacio;
            }

            $actual[] = $palabra;
            $usado += $palabra['ancho'];
        }

        if ($actual !== [] || $lineas === []) {
            $cerrar();
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $palabra
     * @return list<array<string, mixed>>
     */
    private function partirPalabra(array $palabra, float $ancho, string $fuente): array
    {
        $this->SetFont($fuente, $palabra['estilo'], $palabra['tamano']);

        $pedazos = [];
        $resto = $palabra['texto'];

        while ($resto !== '') {
            $corte = strlen($resto);

            while ($corte > 1 && $this->GetStringWidth(substr($resto, 0, $corte)) > $ancho) {
                $corte--;
            }

            $texto = substr($resto, 0, $corte);
            $pedazos[] = [...$palabra, 'texto' => $texto, 'ancho' => $this->GetStringWidth($texto)];
            $resto = substr($resto, $corte);
        }

        return $pedazos;
    }

    /**
     * @param  array<string, mixed>  $palabra
     */
    private function anchoEspacio(string $fuente, array $palabra): float
    {
        $this->SetFont($fuente, $palabra['estilo'], $palabra['tamano']);

        return $this->GetStringWidth(' ');
    }

    /**
     * @param  array{palabras: list<array<string, mixed>>, ancho: float}  $linea
     */
    private function sangriaDe(array $linea, float $ancho, string $alineacion): float
    {
        return match ($alineacion) {
            'C' => max(0.0, ($ancho - $linea['ancho']) / 2),
            'R' => max(0.0, $ancho - $linea['ancho']),
            default => 0.0,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Tabla: medición y dibujo
    |--------------------------------------------------------------------------
    */

    /**
     * @param  list<array{titulo: string, ancho: float, alineacion?: string}>  $columnas
     * @param  list<float>  $anchos
     */
    private function cabeceraTabla(array $columnas, array $anchos, bool $resumen = false): void
    {
        $this->SetY($this->GetY() + 2);
        $y = $this->GetY();
        $alto = $resumen ? 7.0 : 5.0;

        if ($resumen) {
            $this->cabeceraResumen(array_values($columnas), $anchos, $y);

            return;
        }

        $this->fuente(7, 'B', self::SUAVE);
        $this->SetX(self::MARGEN_LATERAL);

        foreach (array_values($columnas) as $indice => $columna) {
            $this->Cell($anchos[$indice], $alto, $this->t(mb_strtoupper($columna['titulo'], 'UTF-8')), 0, 0, $columna['alineacion'] ?? 'L');
        }

        $this->setTrazo(self::AZUL);
        $this->SetLineWidth(0.45);
        $this->Line(self::MARGEN_LATERAL, $y + $alto, $this->w - self::MARGEN_LATERAL, $y + $alto);
        $this->SetLineWidth(0.2);

        $this->SetXY(self::MARGEN_LATERAL, $y + $alto);
    }

    /**
     * Cabecera de la tabla corta de un informe: franja celeste con los títulos
     * en texto normal. Un título que no entra en su columna ("Ajuste de
     * precios estimado") baja de renglón y la franja crece, en vez de pisarse
     * con el de al lado. Con títulos de un renglón queda igual que siempre.
     *
     * @param  list<array{titulo: string, alineacion?: string}>  $columnas
     * @param  list<float>  $anchos
     */
    private function cabeceraResumen(array $columnas, array $anchos, float $y): void
    {
        $this->fuente(8.5, '', self::TEXTO);
        $altoLinea = $this->altoLinea(8.5);
        $altos = array_map(
            fn (array $columna, int $indice): float => $this->flujo($anchos[$indice] - 2 * self::RESPIRO, [['texto' => $columna['titulo']]], $altoLinea, dibujar: false),
            $columnas,
            array_keys($columnas),
        );
        $alto = max(7.0, max($altos) + 3.0);

        $this->setRelleno(self::CELESTE);
        $this->Rect(self::MARGEN_LATERAL, $y, $this->anchoUtil(), $alto, 'F');

        $x = self::MARGEN_LATERAL;

        foreach ($columnas as $indice => $columna) {
            // Centrado en la franja, con el mismo respiro que las celdas para
            // que quede alineado con el texto de su columna.
            $this->SetXY($x + self::RESPIRO, $y + ($alto - $altos[$indice]) / 2);
            $this->fuente(8.5, '', self::TEXTO);
            $this->flujo($anchos[$indice] - 2 * self::RESPIRO, [['texto' => $columna['titulo']]], $altoLinea, $columna['alineacion'] ?? 'L');
            $x += $anchos[$indice];
        }

        $this->setTrazo(self::AZUL);
        $this->SetLineWidth(0.45);
        $this->Line(self::MARGEN_LATERAL, $y + $alto, $this->w - self::MARGEN_LATERAL, $y + $alto);
        $this->SetLineWidth(0.2);

        $this->SetXY(self::MARGEN_LATERAL, $y + $alto);
    }

    /**
     * @param  string|array<string, mixed>  $celda
     * @return array{texto: string, nota: ?string, negrita: bool, imagen: ?string, alto_imagen: float, color: ?array<int, int>}
     */
    private function normalizarCelda(string|array $celda): array
    {
        if (is_string($celda)) {
            return ['texto' => $celda, 'nota' => null, 'negrita' => false, 'imagen' => null, 'alto_imagen' => self::ALTO_EVIDENCIA, 'color' => null];
        }

        return [
            'texto' => (string) ($celda['texto'] ?? ''),
            'nota' => $celda['nota'] ?? null,
            'negrita' => (bool) ($celda['negrita'] ?? false),
            'imagen' => $celda['imagen'] ?? null,
            // Alto de la fila que reserva la imagen, en mm (la imagen de
            // referencia de la cotización va más grande que una evidencia).
            'alto_imagen' => (float) ($celda['alto_imagen'] ?? self::ALTO_EVIDENCIA),
            // Solo para cifras con signo de estado (una pérdida en rojo).
            'color' => $celda['color'] ?? null,
        ];
    }

    /**
     * Alto que necesita una fila: el de la celda que más renglones ocupe.
     *
     * @param  list<array<string, mixed>>  $celdas
     * @param  list<float>  $anchos
     */
    private function altoFila(array $celdas, array $anchos): float
    {
        $alto = 0.0;

        foreach ($celdas as $indice => $celda) {
            if ($celda['imagen']) {
                $alto = max($alto, $celda['alto_imagen']);

                continue;
            }

            $ancho = $anchos[$indice] - 2 * self::RESPIRO;

            $this->fuente(8.5, $celda['negrita'] ? 'B' : '');
            $parcial = $this->flujo($ancho, [['texto' => $celda['texto']]], $this->altoLinea(8.5), dibujar: false);

            if ($celda['nota']) {
                $this->fuente(7.5);
                $parcial += $this->flujo($ancho, [['texto' => $celda['nota']]], $this->altoLinea(7.5), dibujar: false);
            }

            $alto = max($alto, $parcial);
        }

        return $alto + 2 * self::RESPIRO;
    }

    /** Alto por defecto de una fila con imagen (evidencia de la nota de entrega), en mm. */
    private const ALTO_EVIDENCIA = 15.0;

    /**
     * @param  list<array<string, mixed>>  $celdas
     * @param  list<float>  $anchos
     * @param  list<array{titulo: string, ancho: float, alineacion?: string}>  $columnas
     */
    private function dibujarFila(array $celdas, array $anchos, array $columnas, float $alto, bool $alterna): void
    {
        $y = $this->GetY();

        if ($alterna) {
            $this->setRelleno(self::FONDO);
            $this->Rect(self::MARGEN_LATERAL, $y, $this->anchoUtil(), $alto, 'F');
        }

        $x = self::MARGEN_LATERAL;

        foreach ($celdas as $indice => $celda) {
            $alineacion = $columnas[$indice]['alineacion'] ?? 'L';
            $ancho = $anchos[$indice];

            if ($celda['imagen']) {
                $this->evidencia($celda['imagen'], $x, $y, $ancho, $alto, $celda['alto_imagen']);
                $x += $ancho;

                continue;
            }

            $this->SetXY($x + self::RESPIRO, $y + self::RESPIRO);
            $this->fuente(8.5, $celda['negrita'] ? 'B' : '', $celda['color'] ?? ($celda['negrita'] ? self::OSCURO : self::TEXTO));
            $this->flujo($ancho - 2 * self::RESPIRO, [['texto' => $celda['texto']]], $this->altoLinea(8.5), $alineacion);

            if ($celda['nota']) {
                $this->SetX($x + self::RESPIRO);
                $this->fuente(7.5, '', self::TENUE);
                $this->flujo($ancho - 2 * self::RESPIRO, [['texto' => $celda['nota']]], $this->altoLinea(7.5), $alineacion);
            }

            $x += $ancho;
        }

        $this->setTrazo(self::BORDE);
        $this->Line(self::MARGEN_LATERAL, $y + $alto, $this->w - self::MARGEN_LATERAL, $y + $alto);

        $this->SetXY(self::MARGEN_LATERAL, $y + $alto);
    }

    /**
     * Miniatura de una imagen (evidencia de entrega, referencia de una
     * cotización), centrada en su celda y SIN deformarla: se ajusta dentro
     * del recuadro respetando su proporción.
     *
     * FPDF lee la imagen del disco: no hay data URI que valga (eso era para
     * Chromium). Si el archivo desapareció o no es una imagen que FPDF sepa
     * leer, se dibuja un guion en vez de reventar el documento entero — una
     * foto perdida no puede impedir que se imprima el documento.
     */
    private function evidencia(string $ruta, float $x, float $y, float $ancho, float $altoFila, float $altoImagen): void
    {
        $altoMaximo = $altoImagen - 2 * self::RESPIRO;
        $anchoMaximo = $ancho - 2 * self::RESPIRO;

        try {
            $medidas = @getimagesize($ruta);

            if ($medidas === false || $medidas[0] <= 0 || $medidas[1] <= 0) {
                throw new \RuntimeException('No es una imagen legible.');
            }

            $escala = min($anchoMaximo / $medidas[0], $altoMaximo / $medidas[1]);
            $anchoDibujo = $medidas[0] * $escala;
            $altoDibujo = $medidas[1] * $escala;

            $this->Image(
                $ruta,
                $x + ($ancho - $anchoDibujo) / 2,
                $y + ($altoFila - $altoDibujo) / 2,
                $anchoDibujo,
                $altoDibujo,
            );
        } catch (\Throwable) {
            $this->SetXY($x, $y + $altoFila / 2 - 2);
            $this->fuente(8.5, '', self::TENUE);
            $this->Cell($ancho, 4, $this->t('—'), 0, 0, 'C');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Recuadros: medición y dibujo
    |--------------------------------------------------------------------------
    */

    /**
     * @param  list<array<string, mixed>>  $bloques
     */
    private function altoBloques(array $bloques, float $ancho): float
    {
        $alto = 0.0;

        foreach ($bloques as $bloque) {
            $alto += $this->altoBloque($bloque, $ancho, dibujar: false, x: 0, centrado: false);
        }

        return $alto;
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     */
    private function dibujarBloques(array $bloques, float $ancho, float $x, bool $centrado): void
    {
        foreach ($bloques as $bloque) {
            $this->SetX($x);
            $this->altoBloque($bloque, $ancho, dibujar: true, x: $x, centrado: $centrado);
        }
    }

    /**
     * Mide y, si se le pide, dibuja un bloque de recuadro.
     *
     * Medir y dibujar comparten código a propósito: si fueran dos métodos
     * distintos, el día que alguien cambie el tamaño de fuente de las notas
     * el fondo del recuadro dejaría de coincidir con su contenido.
     *
     * @param  array<string, mixed>  $bloque
     */
    private function altoBloque(array $bloque, float $ancho, bool $dibujar, float $x, bool $centrado): float
    {
        $alineacion = $centrado ? 'C' : 'L';

        return match ($bloque['tipo']) {
            'titulo' => $this->bloqueSimple($bloque['texto'], $ancho, 9, 'B', self::OSCURO, $alineacion, $dibujar),
            // Título y texto de los recuadros que se leen como parte del
            // cuerpo ("Respuesta breve", "Acción sugerida"): más grandes que
            // una nota. El encabezado deja un respiro antes del texto.
            'encabezado' => $this->bloqueSimple($bloque['texto'], $ancho, 10.5, 'B', self::AZUL_OSCURO, $alineacion, $dibujar) + $this->respiro(1.5, $dibujar),
            'texto' => $this->bloqueTexto($bloque, $ancho, $alineacion, $dibujar),
            'rotulo' => $this->bloqueSimple(mb_strtoupper($bloque['texto'], 'UTF-8'), $ancho, 7, 'B', self::TENUE, $alineacion, $dibujar),
            'rango' => $this->bloqueSimple($bloque['texto'], $ancho, 13, 'B', self::AZUL_OSCURO, $alineacion, $dibujar),
            'formula' => $this->bloqueSimple($bloque['texto'], $ancho, 10, 'B', self::AZUL_OSCURO, $alineacion, $dibujar),
            'lista' => $this->bloqueLista($bloque['items'], $ancho, $x, $dibujar),
            default => $this->bloqueNota($bloque, $ancho, $alineacion, $dibujar),
        };
    }

    /**
     * @param  array<int, int>  $color
     */
    private function bloqueSimple(string $texto, float $ancho, float $tamano, string $estilo, array $color, string $alineacion, bool $dibujar): float
    {
        $this->fuente($tamano, $estilo, $color);

        return $this->flujo($ancho, [['texto' => $texto]], $this->altoLinea($tamano), $alineacion, $dibujar);
    }

    /**
     * Texto de cuerpo dentro de un recuadro: admite `trozos` con negrita.
     *
     * @param  array<string, mixed>  $bloque
     */
    private function bloqueTexto(array $bloque, float $ancho, string $alineacion, bool $dibujar): float
    {
        $this->fuente(9.5, '', self::TEXTO);

        $trozos = $bloque['trozos'] ?? [['texto' => (string) ($bloque['texto'] ?? '')]];

        return $this->flujo($ancho, $trozos, $this->altoLinea(9.5), $alineacion, $dibujar);
    }

    /** Espacio vertical dentro de un recuadro; devuelve cuánto ocupa. */
    private function respiro(float $milimetros, bool $dibujar): float
    {
        if ($dibujar) {
            $this->SetY($this->GetY() + $milimetros);
        }

        return $milimetros;
    }

    /**
     * @param  array<string, mixed>  $bloque
     */
    private function bloqueNota(array $bloque, float $ancho, string $alineacion, bool $dibujar): float
    {
        $this->fuente(8, '', self::SUAVE);

        $trozos = $bloque['trozos'] ?? [['texto' => (string) ($bloque['texto'] ?? '')]];

        return $this->flujo($ancho, $trozos, $this->altoLinea(8), $alineacion, $dibujar);
    }

    /**
     * @param  list<string>  $items
     */
    private function bloqueLista(array $items, float $ancho, float $x, bool $dibujar): float
    {
        $sangria = 3.5;
        $alto = 0.0;

        foreach ($items as $item) {
            $this->fuente(8, '', self::SUAVE);

            if ($dibujar) {
                $this->SetX($x);
                $this->Cell($sangria, $this->altoLinea(8), $this->t('·'), 0, 0);
                $this->SetX($x + $sangria);
            }

            $alto += $this->flujo($ancho - $sangria, [['texto' => $item]], $this->altoLinea(8), 'L', $dibujar);
        }

        return $alto;
    }

    /*
    |--------------------------------------------------------------------------
    | Utilidades
    |--------------------------------------------------------------------------
    */

    /**
     * Pasa un texto de UTF-8 al cp1252 que entienden las fuentes del núcleo.
     *
     * Es la conversión que hay que hacer en CADA string que se dibuje: FPDF
     * 1.9 acepta UTF-8 solo en las propiedades del documento (título, autor),
     * nunca en el contenido. Todo lo que usan estos documentos —tildes, ñ, ×,
     * m², ±, guion largo— existe en cp1252, así que no se pierde nada. Los
     * pocos signos que NO existen y sí aparecen en los textos del sistema se
     * cambian por su equivalente (EQUIVALENTES_CP1252): sin eso salían como
     * "?" ("Pedidos ? Ajustar medidas reales", "Descuento ? 100,00").
     */
    protected function t(?string $texto): string
    {
        if ($texto === null || $texto === '') {
            return '';
        }

        return mb_convert_encoding(strtr($texto, self::EQUIVALENTES_CP1252), 'Windows-1252', 'UTF-8');
    }

    /**
     * Signos que no existen en cp1252 → el más parecido que sí existe.
     *
     * @var array<string, string>
     */
    private const EQUIVALENTES_CP1252 = [
        '→' => '›',
        '←' => '‹',
        '−' => '-',
        '≤' => '<=',
        '≥' => '>=',
        '≈' => '~',
        '✓' => '',
        '✔' => '',
    ];

    /** Importe en bolivianos con formato local (miles con punto, decimales con coma). */
    protected function monto(float|int|string|null $valor, bool $moneda = true): string
    {
        return ($moneda ? 'Bs ' : '').number_format((float) $valor, 2, ',', '.');
    }

    protected function numero(float|int|string|null $valor, int $decimales = 2): string
    {
        return number_format((float) $valor, $decimales, ',', '.');
    }

    /** Fecha larga en español, o un guion si no hay fecha. */
    protected function fechaLarga(?Carbon $fecha): string
    {
        return $fecha?->translatedFormat('d \d\e F \d\e Y') ?? '—';
    }

    protected function selloDeGeneracion(): string
    {
        return $this->generadoEn->translatedFormat('d/m/Y \a \l\a\s H:i');
    }

    protected function anchoUtil(): float
    {
        return $this->w - 2 * self::MARGEN_LATERAL;
    }

    /** Alto de renglón en mm para un tamaño de fuente en puntos. */
    protected function altoLinea(?float $puntos = null): float
    {
        return ($puntos ?? $this->FontSizePt) * self::INTERLINEA;
    }

    /** Salta de página si el bloque que viene no entra en lo que queda. */
    protected function asegurarEspacio(float $alto): void
    {
        if ($this->GetY() + $alto > $this->PageBreakTrigger) {
            $this->AddPage();
        }
    }

    protected function espacio(float $milimetros): void
    {
        $this->SetXY(self::MARGEN_LATERAL, $this->GetY() + $milimetros);
    }

    /**
     * @param  array<int, int>  $color
     */
    protected function fuente(float $puntos, string $estilo = '', ?array $color = null): void
    {
        $this->SetFont(self::FUENTE, $estilo, $puntos);

        if ($color !== null) {
            $this->setTexto($color);
        }
    }

    /**
     * Color del texto activo.
     *
     * FPDF guarda el color como cadena PDF interna, no como RGB, así que se
     * lleva copia aparte: `flujo()` necesita restaurarlo tal cual después de
     * pintar palabras con colores distintos.
     *
     * @var array<int, int>
     */
    protected array $colorActual = self::TEXTO;

    /**
     * @param  array<int, int>  $rgb
     */
    protected function setTexto(array $rgb): void
    {
        $this->colorActual = $rgb;
        $this->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function setRelleno(array $rgb): void
    {
        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function setTrazo(array $rgb): void
    {
        $this->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
    }
}
