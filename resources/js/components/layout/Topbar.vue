<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { destroy } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
</script>

<template>
    <header class="border-b border-[#e8e6ef] bg-white px-6 py-4">
        <div class="flex items-center justify-end gap-3">
            <Form :action="destroy.url()" method="post" v-slot="{ processing }">
                <button
                    type="submit"
                    class="rounded-lg border border-[#d8d4e7] px-4 py-2 text-sm font-medium text-[#2f2b3dcc] hover:bg-[#f1f0f5] disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="processing"
                >
                    {{ processing ? 'Logging out...' : 'Logout' }}
                </button>
            </Form>
        </div>
    </header>
</template>
