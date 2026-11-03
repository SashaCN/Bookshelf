import { installMockApi, resetDemo } from './mockApi'

installMockApi()

/** A small strip above the app that says this is a demo and lets the reader start over. */
function mountBanner(): void {
  const banner = document.createElement('div')
  banner.style.cssText =
    'position:fixed;left:50%;bottom:calc(env(safe-area-inset-bottom,0px) + 72px);transform:translateX(-50%);z-index:50;' +
    'display:flex;gap:8px;align-items:center;padding:6px 6px 6px 12px;border-radius:999px;font:12px system-ui,sans-serif;' +
    'background:#141b2b;color:#fff;opacity:.88;box-shadow:0 2px 8px rgba(0,0,0,.3)'
  banner.innerHTML = '<span>Демо: дані вигадані</span>'

  const reset = document.createElement('button')
  reset.textContent = 'Скинути'
  reset.style.cssText = 'border:0;border-radius:999px;padding:4px 10px;font:inherit;background:#fff;color:#141b2b;cursor:pointer'
  reset.onclick = () => {
    resetDemo()
    location.hash = '#/'
    location.reload()
  }

  banner.append(reset)
  document.body.append(banner)
}

// The app must be imported after the fake API is in place: its first screen already talks to /api.
void import('../main').then(mountBanner)
