import { computed, ref } from 'vue'

/** The browser's offer to install the app, held back until the reader asks for it. Not in lib.dom yet. */
interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null)
const installed = ref(false)

/** Must run before the app mounts: the browser fires the event early and only once. */
export function listenForInstallPrompt(): void {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    deferredPrompt.value = event as BeforeInstallPromptEvent
  })

  window.addEventListener('appinstalled', () => {
    deferredPrompt.value = null
    installed.value = true
  })
}

/** Whether the app is running as an installed app (its own window) rather than in a browser tab. */
export function isStandalone(): boolean {
  return window.matchMedia?.('(display-mode: standalone)').matches === true || (navigator as { standalone?: boolean }).standalone === true
}

/** Safari on iOS cannot be asked to install: it has to be done by hand from the share menu. */
export function isIos(): boolean {
  const { userAgent, maxTouchPoints, platform } = navigator
  // iPadOS pretends to be a Mac, but a Mac has no touch screen.
  return /iPhone|iPad|iPod/.test(userAgent) || (platform === 'MacIntel' && maxTouchPoints > 1)
}

export function useInstall() {
  const standalone = isStandalone()
  const canPrompt = computed(() => !standalone && !installed.value && deferredPrompt.value !== null)
  const showIosHint = computed(() => !standalone && !installed.value && deferredPrompt.value === null && isIos())

  async function install(): Promise<void> {
    const prompt = deferredPrompt.value

    if (!prompt) {
      return
    }

    await prompt.prompt()
    await prompt.userChoice
    // The offer can be used once.
    deferredPrompt.value = null
  }

  return { standalone, installed, canPrompt, showIosHint, install }
}
