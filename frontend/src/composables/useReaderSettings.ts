import { computed, reactive, watch } from 'vue'
import type { ReaderFont, ReaderSettings, ReaderTheme } from '@/types/reader'

export const SETTINGS_STORAGE_KEY = 'bookshelf.reader.settings'

export const FONT_SIZE = { min: 14, max: 30, step: 1 } as const
export const LINE_HEIGHT = { min: 1.3, max: 2.1, step: 0.1 } as const

export const DEFAULT_SETTINGS: Readonly<ReaderSettings> = { fontSize: 18, lineHeight: 1.6, font: 'serif', theme: 'auto' }

export const READER_THEMES: readonly ReaderTheme[] = ['auto', 'light', 'sepia', 'dark']
export const READER_FONTS: readonly ReaderFont[] = ['serif', 'sans']

const FONT_STACKS: Record<ReaderFont, string> = {
  serif: "'Iowan Old Style', 'Palatino Linotype', Georgia, serif",
  sans: "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
}

/**
 * The colors of the reader. "auto" takes the ones of the app, which follow the setting of the phone; the others are the
 * same wherever the phone is set to. Every reader element takes its colors from these variables.
 */
const THEME_VARIABLES: Record<ReaderTheme, Record<string, string>> = {
  auto: {
    '--reader-bg': 'var(--paper)',
    '--reader-surface': 'var(--surface)',
    '--reader-ink': 'var(--ink)',
    '--reader-muted': 'var(--ink-muted)',
    '--reader-line': 'var(--line)',
    '--reader-accent': 'var(--accent)',
    '--reader-on-accent': 'var(--on-accent)',
    '--reader-mark': 'color-mix(in srgb, var(--accent) 30%, transparent)',
    '--reader-danger': 'var(--danger)',
    colorScheme: 'light dark',
  },
  light: {
    '--reader-bg': '#ffffff',
    '--reader-surface': '#f4f4f2',
    '--reader-ink': '#1c1c1c',
    '--reader-muted': '#5f5f5f',
    '--reader-line': '#dcdcd8',
    '--reader-accent': '#2d47c8',
    '--reader-on-accent': '#ffffff',
    '--reader-mark': 'rgba(255, 213, 79, 0.55)',
    '--reader-danger': '#b42318',
    colorScheme: 'light',
  },
  sepia: {
    '--reader-bg': '#f4ecd8',
    '--reader-surface': '#fbf5e6',
    '--reader-ink': '#433422',
    '--reader-muted': '#7a6a53',
    '--reader-line': '#dccfae',
    '--reader-accent': '#8a5a1c',
    '--reader-on-accent': '#fffaf0',
    '--reader-mark': 'rgba(214, 160, 60, 0.4)',
    '--reader-danger': '#a32d1f',
    colorScheme: 'light',
  },
  dark: {
    '--reader-bg': '#0e1116',
    '--reader-surface': '#171b22',
    '--reader-ink': '#d7dae0',
    '--reader-muted': '#98a0ad',
    '--reader-line': '#272c35',
    '--reader-accent': '#93a5ff',
    '--reader-on-accent': '#0b1020',
    '--reader-mark': 'rgba(147, 165, 255, 0.3)',
    '--reader-danger': '#f2837a',
    colorScheme: 'dark',
  },
}

export function themeVariables(theme: ReaderTheme): Record<string, string> {
  return THEME_VARIABLES[theme]
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value))
}

/** Settings from whatever was stored: a value that is missing or makes no sense is replaced by the default. */
export function normalizeSettings(raw: unknown): ReaderSettings {
  const stored = (typeof raw === 'object' && raw !== null ? raw : {}) as Record<string, unknown>
  const { fontSize, lineHeight, font, theme } = stored

  return {
    fontSize: typeof fontSize === 'number' && Number.isFinite(fontSize)
      ? clamp(Math.round(fontSize), FONT_SIZE.min, FONT_SIZE.max)
      : DEFAULT_SETTINGS.fontSize,
    lineHeight: typeof lineHeight === 'number' && Number.isFinite(lineHeight)
      ? clamp(Math.round(lineHeight * 10) / 10, LINE_HEIGHT.min, LINE_HEIGHT.max)
      : DEFAULT_SETTINGS.lineHeight,
    font: READER_FONTS.includes(font as ReaderFont) ? (font as ReaderFont) : DEFAULT_SETTINGS.font,
    theme: READER_THEMES.includes(theme as ReaderTheme) ? (theme as ReaderTheme) : DEFAULT_SETTINGS.theme,
  }
}

/** The stored settings. Storage may be missing, blocked or full (a private window, cleared site data): that is no reason to fail. */
export function loadSettings(): ReaderSettings {
  try {
    const stored = localStorage.getItem(SETTINGS_STORAGE_KEY)
    return normalizeSettings(stored === null ? null : JSON.parse(stored))
  } catch {
    return { ...DEFAULT_SETTINGS }
  }
}

export function saveSettings(settings: ReaderSettings): void {
  try {
    localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify(settings))
  } catch {
    // The settings still apply to this visit.
  }
}

/** The look of the reader: size, line height, typeface and theme, kept on the device. */
export function useReaderSettings() {
  const settings = reactive<ReaderSettings>(loadSettings())

  watch(settings, () => saveSettings(settings), { deep: true })

  /** CSS variables for the element that holds the whole reader. */
  const style = computed(() => ({
    ...themeVariables(settings.theme),
    '--reader-size': `${settings.fontSize}px`,
    '--reader-leading': String(settings.lineHeight),
    '--reader-font': FONT_STACKS[settings.font],
  }))

  function stepFontSize(direction: 1 | -1): void {
    settings.fontSize = clamp(settings.fontSize + direction * FONT_SIZE.step, FONT_SIZE.min, FONT_SIZE.max)
  }

  function stepLineHeight(direction: 1 | -1): void {
    settings.lineHeight = clamp(Math.round((settings.lineHeight + direction * LINE_HEIGHT.step) * 10) / 10, LINE_HEIGHT.min, LINE_HEIGHT.max)
  }

  function reset(): void {
    Object.assign(settings, DEFAULT_SETTINGS)
  }

  return { settings, style, stepFontSize, stepLineHeight, reset }
}
