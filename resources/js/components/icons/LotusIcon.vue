<script setup lang="ts">
// Loto de trazo simple, dibujado a mano en la convención de lucide
// (24x24, stroke currentColor, grosor 2): hereda tamaño y color como
// cualquier ícono del kit. Lucide no trae loto.
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <!-- Pétalo central -->
        <path
            d="M12 4c-1.7 2-2.6 4-2.6 5.9 0 2.5 1.1 4.4 2.6 5.6 1.5-1.2 2.6-3.1 2.6-5.6C14.6 8 13.7 6 12 4Z"
        />
        <!-- Pétalos laterales -->
        <path d="M7.5 7.5c-1.3 2.4-1.5 4.9-.4 6.9 1 1.8 2.7 2.9 4.9 3.1" />
        <path d="M16.5 7.5c1.3 2.4 1.5 4.9.4 6.9-1 1.8-2.7 2.9-4.9 3.1" />
        <!-- Pétalos externos, abiertos como un cuenco -->
        <path d="M3.5 12c.4 2.4 1.8 4.3 3.9 5.2 1.4.6 3 .8 4.6.3" />
        <path d="M20.5 12c-.4 2.4-1.8 4.3-3.9 5.2-1.4.6-3 .8-4.6.3" />
    </svg>
</template>
