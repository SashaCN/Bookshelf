import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { useCommentsStore } from '@/stores/comments'
import { usePostsStore } from '@/stores/posts'
import { makeComment, makePost } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import CommentForm from './CommentForm.vue'

vi.mock('@/api/social', () => ({
  socialApi: { comments: vi.fn(), addComment: vi.fn() },
}))

function mountForm() {
  const { plugins } = createTestEnvironment()
  usePostsStore().put(makePost({ id: 5, comments_count: 0 }))
  return mount(CommentForm, { props: { postId: 5 }, global: { plugins } })
}

function submit(wrapper: ReturnType<typeof mount>) {
  return wrapper.get('form').trigger('submit')
}

describe('CommentForm', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('has a labelled field of at most 1000 characters and a counter', async () => {
    const wrapper = mountForm()

    const field = wrapper.get('textarea')
    expect(wrapper.get(`label[for="${field.attributes('id')}"]`).text()).toBe('Ваш коментар')
    expect(field.attributes('maxlength')).toBe('1000')
    expect(wrapper.text()).toContain('0 з 1000')

    await field.setValue('Hello')
    expect(wrapper.text()).toContain('5 з 1000')
  })

  it('cannot be sent while it is empty or only spaces', async () => {
    const wrapper = mountForm()
    const button = wrapper.get('button[type="submit"]')

    expect(button.attributes('disabled')).toBeDefined()

    await wrapper.get('textarea').setValue('    ')
    expect(button.attributes('disabled')).toBeDefined()

    await submit(wrapper)
    expect(socialApi.addComment).not.toHaveBeenCalled()

    await wrapper.get('textarea').setValue('Hi')
    expect(button.attributes('disabled')).toBeUndefined()
  })

  it('sends the trimmed comment, clears the field and counts the comment on the post', async () => {
    vi.mocked(socialApi.addComment).mockResolvedValue(makeComment({ id: 2, post_id: 5 }))
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [], links: { next: null } })
    const wrapper = mountForm()
    await useCommentsStore().load(5)
    await wrapper.get('textarea').setValue('  Nice one!  ')

    await submit(wrapper)
    await flushPromises()

    expect(socialApi.addComment).toHaveBeenCalledWith(5, 'Nice one!')
    expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('')
    expect(useCommentsStore().items.map((comment) => comment.id)).toEqual([2])
    expect(usePostsStore().get(5)?.comments_count).toBe(1)
  })

  it('does not send twice while the first is on its way', async () => {
    vi.mocked(socialApi.addComment).mockReturnValue(new Promise(() => {}))
    const wrapper = mountForm()
    await wrapper.get('textarea').setValue('Hi')

    await submit(wrapper)
    await submit(wrapper)

    expect(socialApi.addComment).toHaveBeenCalledTimes(1)
    expect(wrapper.get('button[type="submit"]').text()).toBe('Надсилаємо…')
  })

  it('keeps what was written and says why when sending fails', async () => {
    vi.mocked(socialApi.addComment).mockRejectedValue(new ApiError(429, null))
    const wrapper = mountForm()
    await wrapper.get('textarea').setValue('Hi')

    await submit(wrapper)
    await flushPromises()

    expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('Hi')
    expect(wrapper.get('[role="alert"]').text()).toContain('Забагато запитів')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
  })

  it('shows a refused content next to the field', async () => {
    vi.mocked(socialApi.addComment).mockRejectedValue(new ApiError(422, { errors: { content: ['The content field must not be greater than 1000 characters.'] } }))
    const wrapper = mountForm()
    await wrapper.get('textarea').setValue('Hi')

    await submit(wrapper)
    await flushPromises()

    expect(wrapper.get('#comment-content-error').text()).toBe('Введіть коментар, не довший за 1000 символів.')
    expect(wrapper.get('textarea').attributes('aria-invalid')).toBe('true')
  })
})
