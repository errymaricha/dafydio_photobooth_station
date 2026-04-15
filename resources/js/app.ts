import '../css/app.css'
import './bootstrap'

import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, createSSRApp, h } from 'vue'
import type { DefineComponent } from 'vue'

const pages = import.meta.glob('./pages/**/*.vue', { eager: true }) as Record<
  string,
  { default: DefineComponent }
>

createInertiaApp({
  resolve: (name) => {
    const page = pages[`./pages/${name}.vue`]

    if (!page) {
      throw new Error(`Page not found: ${name}`)
    }

    return page
  },

  setup({ el, App, props, plugin }) {
    const isServer = typeof window === 'undefined'
    const app = (isServer ? createSSRApp : createApp)({
      render: () => h(App, props),
    })
      .use(plugin)

    if (el) {
      app.mount(el)
    }

    return app
  },

  progress: {
    color: '#2563eb',
  },
})
