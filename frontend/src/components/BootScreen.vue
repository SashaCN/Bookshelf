<template>
  <main role="status" aria-busy="true" class="grid min-h-dvh place-items-center">
    <div class="flex flex-col items-center gap-3">
      <img src="/icons/icon-192.png" alt="" class="size-16 animate-pulse rounded-2xl motion-reduce:animate-none">
      <p class="font-display text-2xl font-bold">{{ $t('app.name') }}</p>
      <span class="sr-only">{{ $t('common.loading') }}</span>
    </div>
  </main>
</template>
