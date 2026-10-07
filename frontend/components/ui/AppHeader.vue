<script setup lang="ts">
import { useAppToast } from '~/composables/useAppToast'
import { useAuthStore } from '~/stores/auth'

const authStore = useAuthStore()
const { toast } = useAppToast()
const { t } = useI18n()

const loading = ref(false)
const showDeleteConfirm = ref(false)

async function handleLogout() {
  authStore.logout()
  await navigateTo('/auth/login')
}

async function handleDeleteAccount() {
  loading.value = true
  try {
    await authStore.deleteAccount()
    toast({
      title: t('header.accountDeleted'),
      variant: 'success',
    })
    await navigateTo('/auth/login')
  } catch {
    toast({
      title: t('common.error'),
      variant: 'destructive',
    })
  } finally {
    loading.value = false
    showDeleteConfirm.value = false
  }
}
</script>

<template>
  <header
    class="flex items-center justify-between border-b border-ui-border px-4 py-3"
  >
    <AppLogo size="sm" />

    <div class="flex items-center gap-2">
      <Button
        variant="ghost"
        size="icon"
        :title="t('auth.logout')"
        :aria-label="t('auth.logout')"
        @click="handleLogout"
      >
        <UIcon name="i-heroicons-arrow-right-on-rectangle" class="size-5" />
      </Button>

      <Button
        variant="danger"
        size="icon"
        :title="t('header.deleteAccount')"
        :aria-label="t('header.deleteAccount')"
        @click="showDeleteConfirm = true"
      >
        <UIcon name="i-heroicons-trash" class="size-5" />
      </Button>
    </div>

    <ConfirmDialog
      :open="showDeleteConfirm"
      :title="t('header.deleteAccountConfirmTitle')"
      :message="t('header.deleteAccountConfirmMessage')"
      :confirm-label="t('header.deleteAccountConfirm')"
      :cancel-label="t('common.cancel')"
      :loading="loading"
      @confirm="handleDeleteAccount"
      @cancel="showDeleteConfirm = false"
    />
  </header>
</template>
