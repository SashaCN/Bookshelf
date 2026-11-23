import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import { onUnauthorized } from './api/http'
import { i18n } from './i18n'
import { listenForInstallPrompt } from './pwa/install'
import { registerServiceWorker } from './pwa/updates'
import { router } from './router'
import { installChunkReload } from './router/chunkReload'
import { useAuthStore } from './stores/auth'
import './style.css'

const app = createApp(App).use(createPinia()).use(router).use(i18n)

// A session that ends while the app is open sends the reader to the sign-in form, with an explanation.
onUnauthorized(() => {
  const auth = useAuthStore()

  if (auth.isAuthenticated) {
    auth.expireSession()
    void router.replace({ name: 'login', query: { expired: '1' } })
  }
})

installChunkReload(router)
listenForInstallPrompt()
registerServiceWorker()

app.mount('#app')
