<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="space-y-6 px-4 py-6">
        <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
            <h1 class="text-xl font-semibold text-[#2f2b3dcc]">Settings</h1>
            <p class="mt-1 text-sm text-[#6d6b77]">
                Manage your profile and account settings.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
            <aside class="rounded-xl border border-[#e8e6ef] bg-white p-3 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <nav class="space-y-1" aria-label="Settings">
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-[#7367f0] text-white'
                                : 'text-[#6d6b77] hover:bg-[#f1f0f5] hover:text-[#2f2b3dcc]'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <div class="min-w-0">
                <section class="space-y-6">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
