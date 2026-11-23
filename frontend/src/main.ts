import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import { i18n } from './i18n'
import { router } from './router'
import { installChunkReload } from './router/chunkReload'
import { installSessionExpiry } from './session'
import './style.css'

createApp(App).use(createPinia()).use(router).use(i18n).mount('#app')

installChunkReload(router)
installSessionExpiry(router)
