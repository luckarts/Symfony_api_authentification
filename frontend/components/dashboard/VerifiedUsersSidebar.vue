<script setup lang="ts">
import { fetchVerifiedUsersService } from '~/services/auth'
import { useAuthStore } from '~/stores/auth'

const ITEMS_PER_PAGE = 50

const authStore = useAuthStore()
const { t } = useI18n()

const firstNames = ref<string[]>([])
const totalItems = ref(0)
const page = ref(1)
const loading = ref(false)

const hasMore = computed(() => firstNames.value.length < totalItems.value)

async function load(nextPage: number) {
  loading.value = true
  try {
    const res = await fetchVerifiedUsersService(nextPage, ITEMS_PER_PAGE)
    firstNames.value =
      nextPage === 1 ? res.member : [...firstNames.value, ...res.member]
    totalItems.value = res.totalItems
    page.value = res.page
  } catch {
    // Silent fail — la liste est un complément du dashboard
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load(1)
})
</script>

<template>
  <aside class="w-64 shrink-0 border-r border-ui-border p-4">
    <Heading :level="2" size="sm">{{ t('dashboard.verifiedUsers') }}</Heading>
    <Text as="p" size="xs">
      {{ t('dashboard.verifiedUsersCount', { count: totalItems }) }}
    </Text>

    <ul class="mt-4 space-y-1">
      <li
        v-for="(firstName, index) in firstNames"
        :key="`${firstName}-${index}`"
        class="truncate rounded-lg px-3 py-1.5 text-sm"
        :class="
          firstName === authStore.user?.firstName
            ? 'font-semibold text-brand-500'
            : ''
        "
      >
        {{ firstName }}
      </li>
    </ul>

    <Text v-if="!loading && firstNames.length === 0" as="p" size="xs">
      {{ t('dashboard.noVerifiedUsers') }}
    </Text>

    <Button
      v-if="hasMore"
      variant="ghost"
      size="sm"
      class="mt-3"
      :loading="loading"
      @click="load(page + 1)"
    >
      {{ t('dashboard.loadMore') }}
    </Button>
  </aside>
</template>
