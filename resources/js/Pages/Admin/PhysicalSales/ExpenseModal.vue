<script setup>
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
    amount: '',
    description: '',
});

const amountInput = ref(null);
const formattedAmount = ref('');

const formatCurrency = (value) => {
    if (!value) return '';
    // Eliminar todo excepto números
    const number = value.replace(/\D/g, '');
    if (!number) return '';
    // Formatear como moneda colombiana sin decimales
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(number);
};

const handleAmountInput = (e) => {
    // Obtener valor limpio
    let value = e.target.value.replace(/\D/g, '');
    
    // Actualizar valor en el formulario (limpio)
    form.amount = value;
    
    // Actualizar valor visual (formateado)
    formattedAmount.value = formatCurrency(value);
};

const handleSubmit = () => {
    form.post(route('admin.expenses.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            formattedAmount.value = '';
            emit('success');
            emit('close');
        },
        onError: () => {
            if (form.errors.amount) {
                // Focus amount if error
                if (amountInput.value) amountInput.value.focus();
            }
        },
    });
};

const closeModal = () => {
    form.reset();
    formattedAmount.value = '';
    form.clearErrors();
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                Registrar Gasto (Salida de Caja)
            </h2>

            <form @submit.prevent="handleSubmit">
                <div class="mb-4">
                    <InputLabel for="amount" value="Monto" />
                    <TextInput
                        id="amount"
                        ref="amountInput"
                        v-model="formattedAmount"
                        @input="handleAmountInput"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="$ 0"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>

                <div class="mb-6">
                    <InputLabel for="description" value="Descripción" />
                    <textarea
                        id="description"
                        v-model="form.description"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        rows="3"
                        placeholder="Ej: Pago de taxi, compra de insumos..."
                        required
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>

                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Registrar Gasto
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
