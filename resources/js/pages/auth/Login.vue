<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
</script>

<template>
    <Head title="Login" />

    <div
        class="flex min-h-screen items-center justify-center bg-slate-100 px-4"
    >
        <div
            class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"
        >
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">Login Admin</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Masuk untuk mengelola operasional photobooth.
                </p>
            </div>

            <Form
                v-bind="store.form()"
                v-slot="{ errors, processing }"
                class="space-y-4"
            >
                <div>
                    <label
                        for="email"
                        class="mb-1 block text-sm font-medium text-slate-700"
                        >Email</label
                    >
                    <input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                        autofocus
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-blue-500"
                        placeholder="admin@email.com"
                    />
                    <InputError class="mt-1" :message="errors.email" />
                </div>

                <div>
                    <label
                        for="password"
                        class="mb-1 block text-sm font-medium text-slate-700"
                        >Password</label
                    >
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-blue-500"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-1" :message="errors.password" />
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" />
                    Remember me
                </label>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="processing"
                >
                    {{ processing ? 'Loading...' : 'Login' }}
                </button>
            </Form>

            <div class="mt-6 text-center text-sm text-slate-500">
                Butuh akun?
                <TextLink
                    :href="register()"
                    class="underline underline-offset-4"
                >
                    Register
                </TextLink>
            </div>
        </div>
    </div>
</template>
