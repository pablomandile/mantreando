<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Lista IANA del propio runtime: siempre actualizada, sin payload extra.
const timezones =
    typeof Intl.supportedValuesOf === 'function'
        ? Intl.supportedValuesOf('timeZone')
        : ['UTC'];

// ── Avatar ──────────────────────────────────────────────────────────────────
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarUploading = ref(false);

function uploadAvatar(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    avatarUploading.value = true;
    router.post(
        '/settings/avatar',
        { avatar: file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                avatarUploading.value = false;

                if (avatarInput.value) {
                    avatarInput.value.value = '';
                }
            },
        },
    );
}

function removeAvatar(): void {
    router.delete('/settings/avatar', { preserveScroll: true });
}
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Perfil"
            description="Tu nombre, email, avatar y preferencias"
        />

        <!-- Avatar: form propio (upload de archivo, POST directo) -->
        <div class="flex items-center gap-4">
            <img
                v-if="user.avatar_url"
                :src="user.avatar_url as string"
                alt="Avatar"
                class="size-16 rounded-full object-cover"
            />
            <div
                v-else
                class="flex size-16 items-center justify-center rounded-full bg-muted text-lg font-medium text-muted-foreground"
            >
                {{ user.name.charAt(0) }}
            </div>
            <div class="flex flex-col gap-1">
                <input
                    ref="avatarInput"
                    type="file"
                    accept="image/*"
                    class="text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                    :disabled="avatarUploading"
                    @change="uploadAvatar"
                />
                <button
                    v-if="user.avatar_url"
                    type="button"
                    class="w-fit text-xs text-muted-foreground underline underline-offset-4"
                    @click="removeAvatar"
                >
                    Quitar avatar
                </button>
            </div>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="timezone">Zona horaria</Label>
                <select
                    id="timezone"
                    name="timezone"
                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="" :selected="!user.timezone">
                        Detectar automáticamente
                    </option>
                    <option
                        v-for="tz in timezones"
                        :key="tz"
                        :value="tz"
                        :selected="tz === user.timezone"
                    >
                        {{ tz }}
                    </option>
                </select>
                <p class="text-sm text-muted-foreground">
                    Tus rachas y compromisos diarios se calculan según esta
                    zona.
                </p>
                <InputError class="mt-2" :message="errors.timezone" />
            </div>

            <div class="grid gap-2">
                <Label for="locale">Idioma</Label>
                <select
                    id="locale"
                    name="locale"
                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="es" :selected="user.locale === 'es'">
                        Español
                    </option>
                    <option value="en" :selected="user.locale === 'en'">
                        English
                    </option>
                </select>
                <InputError class="mt-2" :message="errors.locale" />
            </div>

            <!-- Bloque de verificación de email eliminado: la feature está
                 deshabilitada en config/fortify.php hasta configurar mail. -->

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
