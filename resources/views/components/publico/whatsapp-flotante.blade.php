{{--
    Botón flotante de WhatsApp.

    Es el canal real por el que entra el trabajo, así que acompaña el scroll en
    lugar de vivir solo en el bloque de contacto del final. En móvil se queda
    redondo (no tapa contenido); desde `sm` se despliega la etiqueta.
--}}
<x-publico.enlace-whatsapp
    mensaje="Hola XtraPubli, quiero solicitar una cotización."
    class="fixed bottom-5 right-5 z-50 flex items-center gap-2 rounded-full bg-marca-whatsapp px-4 py-3.5 text-sm font-bold text-white shadow-lg transition-transform hover:bg-marca-whatsapp-oscuro active:scale-95 sm:bottom-6 sm:right-6"
    aria-label="Escribir a XtraPubli por WhatsApp"
>
    <x-publico.icono nombre="whatsapp" class="h-6 w-6 shrink-0" />
    <span class="hidden sm:inline">Cotizar por WhatsApp</span>
</x-publico.enlace-whatsapp>
