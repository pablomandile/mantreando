<script setup lang="ts">
// Vajra (dorje): cetro ritual de dos puntas, simétrico, con la esfera del
// agarre al centro. Trazo simple en la convención de lucide (24x24, stroke
// currentColor, grosor 2), como LotusIcon: lucide no trae símbolos rituales.
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
        <!-- Vara central y esfera del agarre -->
        <path d="M9 12h6" />
        <circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none" />
        <!-- Puntas, una a cada lado -->
        <path d="M9 12 6 9 3 12l3 3Z" />
        <path d="M15 12 18 9l3 3-3 3Z" />
        <!-- Dientes internos de cada punta -->
        <path d="M3 12 6 10.3M3 12 6 13.7" />
        <path d="M21 12 18 10.3M21 12 18 13.7" />
    </svg>
</template>
