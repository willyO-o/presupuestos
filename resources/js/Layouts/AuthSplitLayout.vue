<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="relative flex min-h-screen w-full overflow-hidden bg-white">
        <!-- Blob decorativo de marca -->
        <div
            class="login-brand-blob bg-primary pointer-events-none absolute -top-[10%] -left-[14%] hidden h-[120%] w-[62%] md:block"
        ></div>

        <!-- Panel izquierdo: marca -->
        <div
            class="relative z-10 hidden w-1/2 flex-col items-center justify-center gap-6 px-10 md:flex"
        >
            <img
                src="/img/logo/logo-blanco.png"
                alt="Logo XtraPubli"
                class="login-logo-3d w-96 max-w-full"
            />
        </div>

        <!-- Panel derecho: contenido de la pagina (formulario) -->
        <div
            class="relative z-10 flex w-full items-center justify-center px-6 py-12 md:w-1/2"
        >
            <div class="w-full max-w-sm">
                <div class="mb-8 flex flex-col items-center text-center">
                    <span
                        class="avatar avatar-xl mb-4 overflow-hidden rounded-full shadow-lg ring-4 ring-white"
                    >
                        <img
                            src="/img/logo/logo-mini.png"
                            alt="XtraPubli"
                            class="h-full w-full object-cover"
                        />
                    </span>
                    <h1
                        v-if="title"
                        class="text-2xl font-bold tracking-wide text-heading"
                    >
                        {{ title }}
                    </h1>
                </div>

                <slot name="status" />

                <slot />
            </div>
        </div>
    </div>
</template>
