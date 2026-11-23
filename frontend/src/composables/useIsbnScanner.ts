import { onScopeDispose, ref } from 'vue'
import { isValidIsbn13 } from '@/utils/isbn'

export const SCAN_INTERVAL_MS = 300

export type ScannerStatus = 'idle' | 'starting' | 'scanning' | 'found' | 'denied' | 'no-camera' | 'failed'

/** The slice of the (not yet in lib.dom) Barcode Detection API that the scanner uses. */
interface BarcodeDetectorLike {
  detect(source: HTMLVideoElement): Promise<{ rawValue: string }[]>
}

type BarcodeDetectorConstructor = new (options: { formats: string[] }) => BarcodeDetectorLike

function detectorConstructor(): BarcodeDetectorConstructor | undefined {
  return (window as unknown as { BarcodeDetector?: BarcodeDetectorConstructor }).BarcodeDetector
}

/** Scanning needs both the detector and a camera; where either is missing the reader types the ISBN instead. */
export function isIsbnScanningSupported(): boolean {
  return typeof window !== 'undefined' && 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia
}

/**
 * Reads an ISBN-13 barcode from the rear camera. The camera is always released: when an ISBN is found, on `stop()`,
 * on any error and when the owning scope is disposed.
 */
export function useIsbnScanner(onDetected?: (isbn: string) => void) {
  const status = ref<ScannerStatus>('idle')
  const isbn = ref('')

  let stream: MediaStream | null = null
  let video: HTMLVideoElement | null = null
  let timer: ReturnType<typeof setInterval> | undefined
  let busy = false
  /** Bumped by every start and stop, so a start that is still waiting for the camera notices it was cancelled. */
  let run = 0

  function release() {
    clearInterval(timer)
    timer = undefined
    stream?.getTracks().forEach((track) => track.stop())
    stream = null
    if (video) video.srcObject = null
    video = null
    busy = false
  }

  function stop() {
    run++
    release()
    if (status.value !== 'found') status.value = 'idle'
  }

  function fail(e: unknown) {
    release()
    const name = e instanceof DOMException || e instanceof Error ? e.name : ''

    if (name === 'NotAllowedError' || name === 'SecurityError') status.value = 'denied'
    else if (name === 'NotFoundError' || name === 'OverconstrainedError' || name === 'DevicesNotFoundError') status.value = 'no-camera'
    else status.value = 'failed'
  }

  async function tick(detector: BarcodeDetectorLike, id: number) {
    if (busy || !video) return
    busy = true

    try {
      const codes = await detector.detect(video)
      if (id !== run) return

      const match = codes.map((code) => code.rawValue).find(isValidIsbn13)
      if (match) {
        isbn.value = match
        release()
        status.value = 'found'
        onDetected?.(match)
      }
    } catch {
      // A frame that cannot be decoded (e.g. the video is not ready yet) is simply skipped.
    } finally {
      busy = false
    }
  }

  async function start(element: HTMLVideoElement) {
    release()
    const id = ++run
    isbn.value = ''
    status.value = 'starting'

    const Detector = detectorConstructor()
    if (!Detector || !navigator.mediaDevices?.getUserMedia) {
      status.value = 'failed'
      return
    }

    let media: MediaStream
    try {
      media = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
    } catch (e) {
      if (id === run) fail(e)
      return
    }

    // Closed while the permission prompt was open: do not keep the camera.
    if (id !== run) {
      media.getTracks().forEach((track) => track.stop())
      return
    }

    stream = media
    video = element
    element.srcObject = media

    try {
      const detector = new Detector({ formats: ['ean_13'] })
      await element.play?.()
      if (id !== run) return
      status.value = 'scanning'
      timer = setInterval(() => void tick(detector, id), SCAN_INTERVAL_MS)
    } catch (e) {
      if (id === run) fail(e)
    }
  }

  onScopeDispose(() => {
    run++
    release()
  })

  return { status, isbn, start, stop }
}
