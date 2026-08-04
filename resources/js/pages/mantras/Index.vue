<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Plus, Search, Star } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface MantraItem {
    id: number;
    name: string;
    text: string;
    transliteration: string | null;
    image_url: string | null;
    is_system: boolean;
    category: { name: string; slug: string };
    is_favorite: boolean;
}

const props = defineProps<{
    mantras: MantraItem[];
    categories: { id: number; name: string; slug: string }[];
    filters: { q: string; category: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mantras', href: '/mantras' }],
    },
});

const search = ref(props.filters.q ?? '');
const activeCategory = ref<string | null>(props.filters.category ?? null);

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

function applyFilters(): void {
    router.get(
        '/mantras',
        {
            q: search.value || undefined,
            category: activeCategory.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

function toggleCategory(slug: string): void {
    activeCategory.value = activeCategory.value === slug ? null : slug;
    applyFilters();
}

function toggleFavorite(mantra: MantraItem): void {
    router.post(
        `/mantras/${mantra.id}/favorite`,
        {},
        { preserveState: true, preserveScroll: true },
    );
}

// ── Orden personal (flechas subir/bajar) ────────────────────────────────────
// Solo tiene sentido sin búsqueda ni filtro activos: ahí se ve la lista real.
const canReorder = computed(
    () => search.value === '' && activeCategory.value === null,
);

const localOrder = ref<MantraItem[]>([...props.mantras]);

watch(
    () => props.mantras,
    (value) => (localOrder.value = [...value]),
);

function move(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= localOrder.value.length) {
        return;
    }

    const list = [...localOrder.value];
    [list[index], list[target]] = [list[target], list[index]];
    localOrder.value = list; // optimista: la tarjeta se mueve al instante

    router.post(
        '/mantras/reorder',
        { ids: list.map((m) => m.id) },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('Mantras')" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 p-4">
        <header class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ t('Mantras') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('Tu biblioteca de práctica.') }}
                </p>
            </div>
            <Button as-child size="sm">
                <Link href="/mantras/create">
                    <Plus class="size-4" />
                    {{ t('Nuevo mantra') }}
                </Link>
            </Button>
        </header>

        <div class="relative">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                :placeholder="t('Buscar por nombre, texto o transliteración…')"
                class="pl-9"
            />
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="category in categories"
                :key="category.slug"
                type="button"
                class="rounded-full border px-3 py-1 text-xs transition-colors"
                :class="
                    activeCategory === category.slug
                        ? 'border-foreground bg-foreground text-background'
                        : 'text-muted-foreground hover:bg-accent'
                "
                @click="toggleCategory(category.slug)"
            >
                {{ category.name }}
            </button>
        </div>

        <p
            v-if="mantras.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ t('No hay mantras que coincidan con la búsqueda.') }}
        </p>

        <div class="flex flex-col gap-3">
            <div
                v-for="(mantra, index) in localOrder"
                :key="mantra.id"
                class="group relative rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:bg-accent/50 dark:border-sidebar-border"
            >
                <Link
                    :href="`/mantras/${mantra.id}`"
                    class="absolute inset-0"
                    :aria-label="mantra.name"
                />
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium">{{ mantra.name }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ mantra.text }}
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <span
                                class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >
                                {{ mantra.category.name }}
                            </span>
                            <span
                                v-if="!mantra.is_system"
                                class="rounded-full border px-2 py-0.5 text-xs text-muted-foreground"
                            >
                                {{ t('propio') }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-0.5">
                        <div v-if="canReorder" class="flex flex-col">
                            <button
                                type="button"
                                class="relative z-10 rounded-md p-1 text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-30"
                                :disabled="index === 0"
                                :aria-label="t('Subir')"
                                @click.stop="move(index, -1)"
                            >
                                <ChevronUp class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="relative z-10 rounded-md p-1 text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-30"
                                :disabled="index === localOrder.length - 1"
                                :aria-label="t('Bajar')"
                                @click.stop="move(index, 1)"
                            >
                                <ChevronDown class="size-4" />
                            </button>
                        </div>
                        <button
                            type="button"
                            class="relative z-10 rounded-md p-2 hover:bg-accent"
                            :aria-label="
                                mantra.is_favorite
                                    ? t('Quitar de favoritos')
                                    : t('Agregar a favoritos')
                            "
                            @click.stop="toggleFavorite(mantra)"
                        >
                            <Star
                                class="size-5"
                                :class="
                                    mantra.is_favorite
                                        ? 'fill-amber-400 text-amber-400'
                                        : 'text-muted-foreground'
                                "
                            />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
