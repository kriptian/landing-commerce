<script setup>
import { computed } from 'vue';

const props = defineProps({
    catalog: { type: Object, required: true },
    departmentCode: { type: String, default: '' },
    municipalityCode: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    inputClass: { type: [String, Array, Object], default: '' },
    inputStyle: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:departmentCode', 'update:municipalityCode']);

const departments = computed(() => props.catalog?.departments || []);
const municipalities = computed(() => departments.value.find((item) => item.code === props.departmentCode)?.municipalities || []);

const changeDepartment = (event) => {
    emit('update:departmentCode', event.target.value);
    emit('update:municipalityCode', '');
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="department_code" class="mb-1 block text-sm font-medium text-gray-700">Departamento</label>
            <select id="department_code" :value="departmentCode" :class="inputClass" :style="inputStyle" required @change="changeDepartment">
                <option value="">Selecciona un departamento</option>
                <option v-for="department in departments" :key="department.code" :value="department.code">{{ department.name }}</option>
            </select>
            <p v-if="errors.department_code" class="mt-1 text-sm text-red-600">{{ errors.department_code }}</p>
        </div>
        <div>
            <label for="municipality_code" class="mb-1 block text-sm font-medium text-gray-700">Municipio</label>
            <select id="municipality_code" :value="municipalityCode" :disabled="!departmentCode" :class="inputClass" :style="inputStyle" required @change="emit('update:municipalityCode', $event.target.value)">
                <option value="">{{ departmentCode ? 'Selecciona un municipio' : 'Primero selecciona departamento' }}</option>
                <option v-for="municipality in municipalities" :key="municipality.code" :value="municipality.code">{{ municipality.name }}</option>
            </select>
            <p v-if="errors.municipality_code" class="mt-1 text-sm text-red-600">{{ errors.municipality_code }}</p>
            <p v-else-if="departmentCode && !municipalities.length" class="mt-1 text-sm text-amber-700">No hay municipios disponibles para este departamento.</p>
        </div>
    </div>
</template>
