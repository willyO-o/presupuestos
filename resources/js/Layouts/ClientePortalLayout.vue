<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';

defineProps({
    pageTitle: { type: String, default: 'Portal' },
});

const page = usePage();

function salir() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="portal-shell">
        <header class="portal-topbar">
            <div class="portal-topbar-inner">
                <div class="d-flex align-items-center gap-3">
                    <img src="/img/logo/logo.webp" alt="XtraPubli" class="portal-logo" />
                    <span class="fw-semibold">Portal del cliente</span>
                </div>
                <nav class="d-flex align-items-center gap-3">
                    <Link :href="route('portal.cotizaciones')" class="portal-nav-link">Cotizaciones</Link>
                    <Link :href="route('portal.pedidos')" class="portal-nav-link">Pedidos</Link>
                    <Link :href="route('portal.solicitar')" class="portal-nav-link">Solicitar</Link>
                    <span class="text-muted fs-sm hidden md:inline">{{ page.props.auth.user?.name }}</span>
                    <button type="button" class="btn btn-soft-secondary btn-sm" @click="salir">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Salir
                    </button>
                </nav>
            </div>
        </header>

        <main class="portal-content">
            <h1 class="page-title mb-4">{{ pageTitle }}</h1>
            <slot />
        </main>
    </div>
</template>
