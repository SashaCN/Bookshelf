import { useBlocksStore } from './blocks'
import { useCommentsStore } from './comments'
import { useFeedStore } from './feed'
import { usePostsStore } from './posts'
import { useProfilesStore } from './profiles'

/** Forget everything of the community, e.g. when somebody else signs in on the same device. */
export function resetSocialData(): void {
  usePostsStore().reset()
  useFeedStore().reset()
  useProfilesStore().reset()
  useCommentsStore().reset()
  useBlocksStore().reset()
}
