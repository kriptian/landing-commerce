<script setup>
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: 'get',
    },
    as: {
        type: String,
        default: 'a',
    },
});

const handleClick = (event) => {
    if (props.method !== 'get' && props.as === 'button') {
        event.preventDefault();
        if (props.method === 'post') {
            router.post(props.href);
        } else if (props.method === 'put') {
            router.put(props.href);
        } else if (props.method === 'patch') {
            router.patch(props.href);
        } else if (props.method === 'delete') {
            router.delete(props.href);
        }
    }
};
</script>

<template>
    <Link
        v-if="method === 'get' || as !== 'button'"
        :href="href"
        :method="method"
        :as="as"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
    >
        <slot />
    </Link>
    <button
        v-else
        type="button"
        @click="handleClick"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
    >
        <slot />
    </button>
</template>
