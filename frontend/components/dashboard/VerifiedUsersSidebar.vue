<script setup lang="ts">
import { fetchVerifiedUsersCountService } from '~/services/auth'

const { t } = useI18n()

const totalItems = ref<number | null>(null)

onMounted(async () => {
  try {
    const res = await fetchVerifiedUsersCountService()
    totalItems.value = res.totalItems
  } catch {
    // Silent fail — le compteur est un complément du dashboard
  }
})
</script>

<template>
  <aside class="w-64 shrink-0 border-r border-ui-border p-4">
    <Heading :level="2" size="sm">{{ t('dashboard.verifiedUsers') }}</Heading>

    <p class="mt-2 text-4xl font-semibold text-brand-500">
      {{ totalItems ?? '—' }}
    </p>

    <Text as="p" size="xs">{{ t('dashboard.verifiedUsersHint') }}</Text>
  </aside>
</template>
