import { readonly, ref } from 'vue'

// "Offline" is reliable (the browser knows there is no connection); "online" only means a network exists.
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine)

if (typeof window !== 'undefined') {
  window.addEventListener('online', () => {
    online.value = true
  })
  window.addEventListener('offline', () => {
    online.value = false
  })
}

export function useOnline() {
  return { online: readonly(online) }
}
