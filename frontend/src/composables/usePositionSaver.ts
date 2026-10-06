import { getCurrentScope, onScopeDispose } from 'vue'
import type { PositionPayload } from '@/types/reader'

/** How often the position is sent while the reader is reading. */
export const SAVE_INTERVAL_MS = 15_000

interface Options {
  /** Where the reader is now, or null while there is nothing to save (the text is not shown yet). */
  read: () => PositionPayload | null
  /**
   * Sends a position to the server. A failure is not reported: the next try sends it again. `keepalive` is set when the
   * page is on its way out, and the request must survive that.
   */
  save: (position: PositionPayload, options?: { keepalive: true }) => Promise<unknown>
  intervalMs?: number
}

function keyOf(position: PositionPayload): string {
  return `${position.chapter}:${position.chapter_progress.toFixed(4)}`
}

/**
 * Sends the reading position to the server every 15 seconds while the reader reads, whenever the page is hidden
 * (the phone is locked, the app is switched away from) and when the reader leaves. A position that was already sent
 * is not sent again. Positions go out one at a time and in order, so a late answer never overwrites a newer position.
 */
export function usePositionSaver({ read, save, intervalMs = SAVE_INTERVAL_MS }: Options) {
  let timer: ReturnType<typeof setInterval> | undefined
  let lastSent: string | null = null
  let queue: Promise<void> = Promise.resolve()

  /**
   * Sends the position, `position` if given and otherwise where the reader is now. Resolves when it has been tried.
   * `keepalive` when the page is being left.
   */
  function flush(position: PositionPayload | null = read(), { keepalive = false }: { keepalive?: boolean } = {}): Promise<void> {
    if (!position) {
      return queue
    }

    const key = keyOf(position)

    if (key === lastSent) {
      return queue
    }

    const before = lastSent
    lastSent = key

    queue = queue.then(async () => {
      try {
        await (keepalive ? save(position, { keepalive: true }) : save(position))
      } catch {
        // Not sent: it will be tried again, unless a later position has been queued behind it.
        if (lastSent === key) {
          lastSent = before
        }
      }
    })

    return queue
  }

  // The page is hidden or going away: the request has to outlive it.
  function onVisibilityChange(): void {
    if (document.visibilityState === 'hidden') {
      void flush(undefined, { keepalive: true })
    }
  }

  function onPageHide(): void {
    void flush(undefined, { keepalive: true })
  }

  function start(): void {
    stop(false)
    timer = setInterval(() => void flush(), intervalMs)
    document.addEventListener('visibilitychange', onVisibilityChange)
    window.addEventListener('pagehide', onPageHide)
  }

  /** Stops saving; sends the position one last time unless told not to. */
  function stop(final = true): Promise<void> {
    clearInterval(timer)
    timer = undefined
    document.removeEventListener('visibilitychange', onVisibilityChange)
    window.removeEventListener('pagehide', onPageHide)

    // Leaving: the request has to outlive the screen.
    return final ? flush(undefined, { keepalive: true }) : queue
  }

  if (getCurrentScope()) {
    onScopeDispose(() => void stop())
  }

  return { start, stop, flush }
}
