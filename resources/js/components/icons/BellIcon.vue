<script setup lang="ts">
// Campana (ghanta): mango con su remate, cuerpo acampanado y el borde en dos
// líneas. Trazo simple en la convención de lucide (24x24, stroke
// currentColor, grosor 2), como LotusIcon.
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
        <!-- Mango y remate -->
        <path d="M12 3.2v1.6" />
        <circle cx="12" cy="5.6" r="0.9" fill="currentColor" stroke="none" />
        <path d="M12 6.6v1" />
        <!-- Cuerpo -->
        <path d="M8.2 8.6c0-1.6 1.7-2.8 3.8-2.8s3.8 1.2 3.8 2.8v6.2H8.2V8.6Z" />
        <!-- Borde, en dos líneas como el filo de bronce -->
        <path d="M6.8 14.8h10.4" />
        <path d="M7.6 16.8h8.8" />
    </svg>
</template>
