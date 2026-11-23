import { defineStore } from 'pinia'
import { ref } from 'vue'
import { readerApi } from '@/api/reader'
import type { ReaderBookmark, ReaderChapter, ReaderDocument, ReaderPosition } from '@/types/api'

export const useReaderStore = defineStore('reader', () => {
  /** The uploaded books, newest first (the server's order). */
  const items = ref<ReaderDocument[]>([])
  const loaded = ref(false)

  function replace(doc: ReaderDocument): void {
    items.value = items.value.map((item) => (item.id === doc.id ? doc : item))
  }

  async function load(): Promise<void> {
    items.value = await readerApi.list()
    loaded.value = true
  }

  async function get(id: number): Promise<ReaderDocument> {
    return items.value.find((item) => item.id === id) ?? (await readerApi.get(id))
  }

  async function upload(file: File): Promise<ReaderDocument> {
    const doc = await readerApi.upload(file)
    items.value = [doc, ...items.value.filter((item) => item.id !== doc.id)]
    return doc
  }

  async function remove(id: number): Promise<void> {
    await readerApi.remove(id)
    items.value = items.value.filter((item) => item.id !== id)
  }

  async function savePosition(id: number, position: ReaderPosition): Promise<ReaderDocument> {
    const doc = await readerApi.savePosition(id, position)
    replace(doc)
    return doc
  }

  async function link(id: number, userBookId: number | null): Promise<ReaderDocument> {
    const doc = await readerApi.link(id, userBookId)
    replace(doc)
    return doc
  }

  function chapter(id: number, index: number): Promise<ReaderChapter> {
    return readerApi.chapter(id, index)
  }

  function bookmarks(id: number): Promise<ReaderBookmark[]> {
    return readerApi.bookmarks(id)
  }

  function saveBookmark(id: number, chapterIndex: number, note?: string | null): Promise<ReaderBookmark> {
    return readerApi.saveBookmark(id, chapterIndex, note)
  }

  function removeBookmark(id: number, bookmarkId: number): Promise<null> {
    return readerApi.removeBookmark(id, bookmarkId)
  }

  function reset(): void {
    items.value = []
    loaded.value = false
  }

  return { items, loaded, load, get, upload, remove, savePosition, link, chapter, bookmarks, saveBookmark, removeBookmark, reset }
})
