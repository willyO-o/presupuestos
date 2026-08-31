<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import MainDashboardLayout from '@/Layouts/MainDashboardLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { useServerTable } from '@/Composables/UseServerTable';
import { confirmation } from '@/Utils/AlertUtil';

defineOptions({ layout: MainDashboardLayout });

const props = defineProps({
    ordenes: { type: Object, required: true },
    pedidosSinOc: { type: Array, default: () => [] },
    estados: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const table = useServerTable({
    url: route('ordenes-compra-cliente.index'),
    filters: { search: props.filters.search ?? '', estado: props.filters.estado ?? '' },
    mode: 'manual',
    only: ['ordenes', 'pedidosSinOc', 'filters'],
});

const headers = [
    { label: 'N.º OC', key: 'numero_oc' },
    { label: 'Cliente', key: 'cliente' },
    { label: 'Pedido', key: 'pedido', class: 'text-center', cellClass: 'text-center' },
    { label: 'Fecha', key: 'fecha', class: 'text-center', cellClass: 'text-center' },
    { label: 'Monto', key: 'monto_total', class: 'text-end', cellClass: 'text-end' },
    { label: 'Cond. pago', key: 'condicion_pago', class: 'text-center', cellClass: 'text-center' },
    { label: 'Estado', key: 'estado', class: 'text-center', cellClass: 'text-center' },
];

const estadoBadge = { PENDIENTE: 'badge-soft-warning', VALIDADA: 'badge-soft-success', ANULADA: 'badge-soft-secondary' };

function money(value) {
    return `Bs ${Number(value ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fecha(value) {
    return value ? new Date(value).toLocaleDateString('es-BO') : '—';
}

const showModal = ref(false);
const editing = ref(null);

const form = useForm(() => ({
    _method: 'post',
    pedido_id: props.pedidosSinOc[0]?.id ?? '',
    numero_oc: '',
    fecha: new Date().toISOString().slice(0, 10),
    monto_total: '',
    condicion_pago: '',
    archivo_pdf: null,
}));

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.reset();
    form._method = 'post';
    showModal.value = true;
}

function openEdit(orden) {
    editing.value = orden;
    form.clearErrors();
    form.reset();
    form._method = 'put';
    form.pedido_id = orden.pedido_id;
    form.numero_oc = orden.numero_oc;
    form.fecha = String(orden.fecha).slice(0, 10);
    form.monto_total = orden.monto_total;
    form.condicion_pago = orden.condicion_pago ?? '';
    form.archivo_pdf = null;
    showModal.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => { showModal.value = false; } };
    if (editing.value) {
        form.post(route('ordenes-compra-cliente.update', editing.value.id), options);
    } else {
        form.post(route('ordenes-compra-cliente.store'), options);
    }
}

const accionForm = useForm({});

async function validar(orden) {
    if (!(await confirmation(`¿Validar la OC <strong>${orden.numero_oc}</strong>?`, 'Validar OC', 'Sí, validar'))) return;
    accionForm.post(route('ordenes-compra-cliente.validar', orden.id), { preserveScroll: true });
}

async function anular(orden) {
    if (!(await confirmation(`¿Anular la OC <strong>${orden.numero_oc}</strong>?`, 'Anular OC', 'Sí, anular'))) return;
    accionForm.post(route('ordenes-compra-cliente.anular', orden.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Órdenes de compra de cliente" />

    <div class="card mb-4">
        <div class="card-body">
            <form class="row" @submit.prevent="table.search">
                <div class="col-lg-5">
                    <label class="form-label" for="f-search">Buscar</label>
                    <input id="f-search" v-model="table.filters.search" type="text" class="form-control"
                        placeholder="N.º de OC o cliente..." />
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="f-estado">Estado</label>
                    <select id="f-estado" v-model="table.filters.estado" class="form-control">
                        <option value="">Todos</option>
                        <option v-for="e in estados" :key="e" :value="e">{{ e }}</option>
                    </select>
                </div>
                <div class="col-lg-4 flex items-end gap-2">
                    <button type="submit" class="btn btn-primary" :disabled="table.loading">Buscar</button>
                    <button type="button" class="btn btn-soft-secondary" :disabled="table.loading" @click="table.reset">
                        Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Órdenes de compra de cliente</span>
            <button v-can="'ordenes-compra-cliente.crear'" type="button" class="btn btn-primary btn-sm"
                :disabled="!pedidosSinOc.length" @click="openCreate">
                <i class="fa-solid fa-plus"></i>
                Registrar OC
            </button>
        </div>
        <div class="card-body">
            <DataTable :headers="headers" :items="ordenes.data" :paginator="ordenes" :loading="table.loading"
                empty-text="No hay órdenes de compra registradas." @page-change="table.changePage">
                <template #cell-numero_oc="{ item }">
                    <a v-if="item.archivo_url" :href="item.archivo_url" target="_blank" rel="noopener"
                        class="fw-semibold text-primary">
                        {{ item.numero_oc }} <i class="fa-solid fa-file-pdf"></i>
                    </a>
                    <span v-else class="fw-semibold">{{ item.numero_oc }}</span>
                </template>
                <template #cell-cliente="{ item }">{{ item.cliente?.razon_social ?? '—' }}</template>
                <template #cell-pedido="{ item }">{{ item.pedido?.numero_pedido ?? '—' }}</template>
                <template #cell-fecha="{ item }">{{ fecha(item.fecha) }}</template>
                <template #cell-monto_total="{ item }">{{ money(item.monto_total) }}</template>
                <template #cell-condicion_pago="{ item }">{{ item.condicion_pago ?? '—' }}</template>
                <template #cell-estado="{ item }">
                    <span class="badge" :class="estadoBadge[item.estado] ?? 'badge-soft-secondary'">{{ item.estado }}</span>
                </template>
                <template #actions="{ item }">
                    <div class="d-flex gap-1 justify-content-end">
                        <button v-if="item.estado === 'PENDIENTE'" v-can="'ordenes-compra-cliente.crear'" type="button"
                            class="btn btn-sm btn-icon btn-soft-primary" aria-label="Editar" @click="openEdit(item)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button v-if="item.estado === 'PENDIENTE'" v-can="'ordenes-compra-cliente.validar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-success" aria-label="Validar" @click="validar(item)">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button v-if="item.estado === 'PENDIENTE'" v-can="'ordenes-compra-cliente.validar'" type="button"
                            class="btn btn-sm btn-icon btn-soft-warning" aria-label="Anular" @click="anular(item)">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>

    <Modal :show="showModal" max-width="lg" @close="showModal = false">
        <div class="card-header">
            <span class="card-title">{{ editing ? 'Editar orden de compra' : 'Registrar orden de compra' }}</span>
            <button type="button" class="modal-close" aria-label="Cerrar" @click="showModal = false">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form @submit.prevent="submit">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Pedido</label>
                    <select v-model="form.pedido_id" class="form-control" :disabled="!!editing" required>
                        <option v-for="p in pedidosSinOc" :key="p.id" :value="p.id">
                            {{ p.numero_pedido }} — {{ money(p.total) }}
                        </option>
                        <option v-if="editing" :value="form.pedido_id">{{ editing.pedido?.numero_pedido }}</option>
                    </select>
                    <p v-if="form.errors.pedido_id" class="form-error">{{ form.errors.pedido_id }}</p>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">N.º de OC</label>
                            <input v-model="form.numero_oc" type="text" class="form-control" required />
                            <p v-if="form.errors.numero_oc" class="form-error">{{ form.errors.numero_oc }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">Fecha</label>
                            <input v-model="form.fecha" type="date" class="form-control" required />
                            <p v-if="form.errors.fecha" class="form-error">{{ form.errors.fecha }}</p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">Monto total (Bs)</label>
                            <input v-model="form.monto_total" v-decimal="2" type="text" inputmode="decimal"
                                class="form-control" required />
                            <p v-if="form.errors.monto_total" class="form-error">{{ form.errors.monto_total }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">Condición de pago</label>
                            <input v-model="form.condicion_pago" type="text" class="form-control"
                                placeholder="CONTADO, 30 DIAS..." />
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Archivo PDF (opcional)</label>
                    <input type="file" accept="application/pdf" class="form-control"
                        @input="form.archivo_pdf = $event.target.files[0]" />
                    <p v-if="form.errors.archivo_pdf" class="form-error">{{ form.errors.archivo_pdf }}</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" @click="showModal = false">Cancelar</button>
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                    {{ editing ? 'Guardar' : 'Registrar' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
