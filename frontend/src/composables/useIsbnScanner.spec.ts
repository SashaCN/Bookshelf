import { effectScope } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { isIsbnScanningSupported, SCAN_INTERVAL_MS, useIsbnScanner } from './useIsbnScanner'

const VALID = '9780306406157'

const track = { stop: vi.fn() }
const detect = vi.fn()
const getUserMedia = vi.fn()

function installCamera() {
  vi.stubGlobal(
    'BarcodeDetector',
    class {
      constructor(public options: { formats: string[] }) {
        detectorOptions.push(options)
      }
      detect = detect
    },
  )
  Object.defineProperty(navigator, 'mediaDevices', { value: { getUserMedia }, configurable: true })
}

const detectorOptions: { formats: string[] }[] = []

function makeVideo() {
  const video = document.createElement('video')
  video.play = vi.fn().mockResolvedValue(undefined)
  return video
}

function domError(name: string) {
  return new DOMException('x', name)
}

function mountScanner(onDetected?: (isbn: string) => void) {
  const scope = effectScope()
  const scanner = scope.run(() => useIsbnScanner(onDetected))!
  return { scope, scanner }
}

describe('useIsbnScanner', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.resetAllMocks()
    detectorOptions.length = 0
    getUserMedia.mockResolvedValue({ getTracks: () => [track] })
    detect.mockResolvedValue([])
    installCamera()
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    Reflect.deleteProperty(navigator, 'mediaDevices')
  })

  it('reports support only when both the detector and a camera API exist', () => {
    expect(isIsbnScanningSupported()).toBe(true)

    vi.unstubAllGlobals()
    expect(isIsbnScanningSupported()).toBe(false)

    installCamera()
    Object.defineProperty(navigator, 'mediaDevices', { value: undefined, configurable: true })
    expect(isIsbnScanningSupported()).toBe(false)
  })

  it('asks for the rear camera and an EAN-13 detector, then scans', async () => {
    const { scanner } = mountScanner()
    const video = makeVideo()

    await scanner.start(video)

    expect(getUserMedia).toHaveBeenCalledWith({ video: { facingMode: { ideal: 'environment' } }, audio: false })
    expect(detectorOptions).toEqual([{ formats: ['ean_13'] }])
    expect(scanner.status.value).toBe('scanning')
  })

  it('scans a frame every 300 ms', async () => {
    const { scanner } = mountScanner()
    await scanner.start(makeVideo())

    await vi.advanceTimersByTimeAsync(SCAN_INTERVAL_MS * 3)

    expect(detect).toHaveBeenCalledTimes(3)
  })

  it('accepts a valid ISBN-13, stops the camera and reports it once', async () => {
    const onDetected = vi.fn()
    const { scanner } = mountScanner(onDetected)
    const video = makeVideo()
    await scanner.start(video)
    detect.mockResolvedValue([{ rawValue: VALID }])

    await vi.advanceTimersByTimeAsync(SCAN_INTERVAL_MS * 5)

    expect(onDetected).toHaveBeenCalledTimes(1)
    expect(onDetected).toHaveBeenCalledWith(VALID)
    expect(scanner.status.value).toBe('found')
    expect(scanner.isbn.value).toBe(VALID)
    expect(track.stop).toHaveBeenCalledTimes(1)
    expect(video.srcObject).toBeNull()
  })

  it('ignores barcodes that are not a valid ISBN-13', async () => {
    const onDetected = vi.fn()
    const { scanner } = mountScanner(onDetected)
    await scanner.start(makeVideo())
    detect.mockResolvedValue([{ rawValue: '9780306406158' }, { rawValue: '5901234123457' }, { rawValue: 'hello' }])

    await vi.advanceTimersByTimeAsync(SCAN_INTERVAL_MS * 3)

    expect(onDetected).not.toHaveBeenCalled()
    expect(scanner.status.value).toBe('scanning')
    expect(track.stop).not.toHaveBeenCalled()
  })

  it('survives a frame that fails to decode', async () => {
    const onDetected = vi.fn()
    const { scanner } = mountScanner(onDetected)
    await scanner.start(makeVideo())
    detect.mockRejectedValueOnce(new Error('not ready')).mockResolvedValue([{ rawValue: VALID }])

    await vi.advanceTimersByTimeAsync(SCAN_INTERVAL_MS * 2)

    expect(onDetected).toHaveBeenCalledWith(VALID)
  })

  it('does not start a second detection while the previous one is still running', async () => {
    const { scanner } = mountScanner()
    await scanner.start(makeVideo())
    detect.mockReturnValue(new Promise(() => {}))

    await vi.advanceTimersByTimeAsync(SCAN_INTERVAL_MS * 4)

    expect(detect).toHaveBeenCalledTimes(1)
  })

  it('releases the camera and stops scanning on stop()', async () => {
    const { scanner } = mountScanner()
    const video = makeVideo()
    await scanner.start(video)

    scanner.stop()
    await vi.advanceTimersByTimeAsync(SCAN_INTERVAL_MS * 3)

    expect(track.stop).toHaveBeenCalledTimes(1)
    expect(video.srcObject).toBeNull()
    expect(detect).not.toHaveBeenCalled()
    expect(scanner.status.value).toBe('idle')
  })

  it('releases the camera when its scope is disposed', async () => {
    const { scanner, scope } = mountScanner()
    await scanner.start(makeVideo())

    scope.stop()
    await vi.advanceTimersByTimeAsync(SCAN_INTERVAL_MS * 3)

    expect(track.stop).toHaveBeenCalledTimes(1)
    expect(detect).not.toHaveBeenCalled()
  })

  it('does not keep a camera that arrives after stop()', async () => {
    let grant: (stream: unknown) => void = () => {}
    getUserMedia.mockReturnValue(new Promise((resolve) => (grant = resolve)))
    const { scanner } = mountScanner()
    const starting = scanner.start(makeVideo())

    scanner.stop()
    grant({ getTracks: () => [track] })
    await starting

    expect(track.stop).toHaveBeenCalledTimes(1)
    expect(scanner.status.value).toBe('idle')
    await vi.advanceTimersByTimeAsync(SCAN_INTERVAL_MS * 2)
    expect(detect).not.toHaveBeenCalled()
  })

  it.each([
    ['NotAllowedError', 'denied'],
    ['SecurityError', 'denied'],
    ['NotFoundError', 'no-camera'],
    ['OverconstrainedError', 'no-camera'],
    ['AbortError', 'failed'],
  ])('maps %s to the %s state', async (name, expected) => {
    getUserMedia.mockRejectedValue(domError(name))
    const { scanner } = mountScanner()

    await scanner.start(makeVideo())

    expect(scanner.status.value).toBe(expected)
  })

  it('releases the camera when the video cannot play', async () => {
    const { scanner } = mountScanner()
    const video = makeVideo()
    video.play = vi.fn().mockRejectedValue(domError('NotAllowedError'))

    await scanner.start(video)

    expect(scanner.status.value).toBe('denied')
    expect(track.stop).toHaveBeenCalledTimes(1)
  })

  it('fails cleanly without a detector', async () => {
    vi.unstubAllGlobals()
    const { scanner } = mountScanner()

    await scanner.start(makeVideo())

    expect(scanner.status.value).toBe('failed')
    expect(getUserMedia).not.toHaveBeenCalled()
  })
})
