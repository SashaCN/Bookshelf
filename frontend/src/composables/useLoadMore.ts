import { ref } from 'vue'
import { useApiErrorMessage } from './useApiErrorMessage'

/** The "show more" action of a paged list together with its own error, so a failure does not replace the list. */
export function useLoadMore(next: () => Promise<void>) {
  const errorMessage = useApiErrorMessage()
  const moreError = ref('')

  async function loadMore() {
    moreError.value = ''

    try {
      await next()
    } catch (e) {
      moreError.value = errorMessage(e)
    }
  }

  return { moreError, loadMore }
}
