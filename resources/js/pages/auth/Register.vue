<script setup lang="ts">
import { Chrome, Facebook, Github, Twitter } from 'lucide-vue-next';
import { ref } from 'vue';
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import * as register from '@/routes/register';

const password = ref('');
</script>

<template>
    <Head title="Register" />

    <div
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#f4f3fa] px-4 py-10"
    >
        <div
            class="absolute top-10 left-8 hidden h-56 w-56 rounded-2xl border border-[#d8d4ff] bg-[#eceafc] md:block"
        />
        <div
            class="absolute top-20 left-16 hidden h-56 w-56 rounded-2xl bg-[#e6e4f8] md:block"
        />
        <div
            class="absolute right-10 bottom-12 hidden h-40 w-40 rounded-2xl border border-dashed border-[#d8d4ff] md:block"
        />
        <div
            class="absolute right-16 bottom-20 hidden h-40 w-40 rounded-2xl bg-[#e6e4f8] md:block"
        />

        <div
            class="relative z-10 w-full max-w-[28rem] rounded-xl border border-[#ebe8f6] bg-white px-8 py-10 shadow-[0_6px_20px_rgba(47,43,61,0.08)]"
        >
            <div class="mb-8 flex items-center justify-center gap-3">
                <span class="text-[#7367f0]">
                    <svg
                        width="30"
                        height="22"
                        viewBox="0 0 32 22"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z"
                            fill="currentColor"
                        />
                        <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z"
                            fill="currentColor"
                        />
                    </svg>
                </span>
                <h2 class="text-[2rem] leading-none font-semibold text-[#2f2b3dcf]">Dafydio Station</h2>
            </div>

            <div class="mb-7">
                <h3 class="text-[2rem] leading-tight font-medium text-[#2f2b3dcc]">
                    Adventure starts here 🚀
                </h3>
                <p class="mt-1 text-[1.0625rem] text-[#2f2b3d99]">
                    Make your app management easy and fun!
                </p>
            </div>

            <Form
                v-bind="register.store.form()"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="space-y-5"
            >
                <div class="grid gap-2">
                    <Label
                        for="name"
                        class="text-[0.9375rem] font-normal text-[#2f2b3dcf]"
                    >
                        Username
                    </Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Enter your username"
                        class="h-11 border-[#d8d4e7] text-[1rem] placeholder:text-[#2f2b3d66]"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="email"
                        class="text-[0.9375rem] font-normal text-[#2f2b3dcf]"
                    >
                        Email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="Enter your email"
                        class="h-11 border-[#d8d4e7] text-[1rem] placeholder:text-[#2f2b3d66]"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="password"
                        class="text-[0.9375rem] font-normal text-[#2f2b3dcf]"
                    >
                        Password
                    </Label>
                    <PasswordInput
                        id="password"
                        v-model="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="············"
                        class="h-11 border-[#d8d4e7] text-[1rem] placeholder:text-[#2f2b3d66]"
                    />
                    <input type="hidden" name="password_confirmation" :value="password">
                    <InputError :message="errors.password" />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <label
                    for="terms-conditions"
                    class="flex items-center gap-2 text-[1.0625rem] text-[#2f2b3dcc]"
                >
                    <input
                        id="terms-conditions"
                        type="checkbox"
                        class="h-[1.125rem] w-[1.125rem] rounded-sm border border-[#d8d4e7] text-[#7367f0] focus:ring-[#7367f0]"
                    >
                    <span>I agree to</span>
                    <a href="#" class="text-[#7367f0] hover:underline">
                        privacy policy & terms
                    </a>
                </label>

                <Button
                    type="submit"
                    class="h-11 w-full bg-[#7367f0] text-base font-medium text-white hover:bg-[#685dd8]"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Loading...' : 'Sign up' }}
                </Button>
            </Form>

            <p class="mt-6 text-center text-[1.125rem] text-[#2f2b3d99]">
                Already have an account?
                <TextLink
                    :href="login()"
                    :tabindex="6"
                    class="text-[#7367f0] no-underline hover:underline"
                >
                    Sign in instead
                </TextLink>
            </p>

            <div class="my-6 flex items-center gap-4">
                <div class="h-px flex-1 bg-[#e8e6ef]" />
                <span class="text-[1.125rem] text-[#2f2b3d99]">or</span>
                <div class="h-px flex-1 bg-[#e8e6ef]" />
            </div>

            <div class="flex items-center justify-center gap-5">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-[#3b5998] hover:bg-[#f1f0f5]"
                    aria-label="Sign up with Facebook"
                >
                    <Facebook class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-[#1da1f2] hover:bg-[#f1f0f5]"
                    aria-label="Sign up with Twitter"
                >
                    <Twitter class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-[#2f2b3dcc] hover:bg-[#f1f0f5]"
                    aria-label="Sign up with Github"
                >
                    <Github class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-[#db4437] hover:bg-[#f1f0f5]"
                    aria-label="Sign up with Google"
                >
                    <Chrome class="h-5 w-5" />
                </button>
            </div>
        </div>
    </div>
</template>

