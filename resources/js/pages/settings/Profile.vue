<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <AppLayout
        title="Profile Settings"
        subtitle="Update your account identity and email verification status."
    >
        <div class="space-y-6">
            <Form
                v-bind="ProfileController.update.form()"
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-[#2f2b3dcc]">
                        Profile Information
                    </h2>
                    <p class="mt-1 text-sm text-[#6d6b77]">
                        Update your name and email address.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-[#2f2b3dcc]">Name</span>
                        <input
                            id="name"
                            class="block w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="text-xs" :message="errors.name" />
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-[#2f2b3dcc]">Email Address</span>
                        <input
                            id="email"
                            type="email"
                            class="block w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="text-xs" :message="errors.email" />
                    </label>
                </div>

                <div
                    v-if="mustVerifyEmail && !user.email_verified_at"
                    class="mt-4 rounded-lg border border-[#ffd5d9] bg-[#fff5f5] px-3 py-2.5 text-sm text-[#ea5455]"
                >
                    <p>
                        Your email address is unverified.
                        <Link
                            :href="send()"
                            as="button"
                            class="font-medium underline underline-offset-2"
                        >
                            Click here to resend the verification email.
                        </Link>
                    </p>

                    <div
                        v-if="status === 'verification-link-sent'"
                        class="mt-2 rounded-md border border-[#c8f1da] bg-[#f0fcf5] px-2 py-1.5 text-sm font-medium text-[#28c76f]"
                    >
                        A new verification link has been sent to your email address.
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button
                        type="submit"
                        class="rounded-lg bg-[#7367f0] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#685dd8] disabled:opacity-50"
                        :disabled="processing"
                        data-test="update-profile-button"
                    >
                        Save
                    </button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-[#28c76f]"
                        >
                            Saved.
                        </p>
                    </Transition>
                </div>
            </Form>

            <DeleteUser />
        </div>
    </AppLayout>
</template>
