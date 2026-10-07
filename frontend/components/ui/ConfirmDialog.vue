<script setup lang="ts">
const props = defineProps<{
  open: boolean
  title: string
  message: string
  confirmLabel: string
  cancelLabel?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

function onCancel() {
  if (props.loading) return
  emit('cancel')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-navy-900/60 px-4"
      role="dialog"
      aria-modal="true"
      @click.self="onCancel"
    >
      <Card variant="shadow" class="w-full max-w-sm bg-card">
        <Heading :level="2" size="lg">{{ title }}</Heading>
        <Text as="p">{{ message }}</Text>
        <div class="flex justify-end gap-3">
          <Button variant="outline" :disabled="loading" @click="onCancel">
            {{ cancelLabel ?? 'Annuler' }}
          </Button>
          <Button variant="primary" :loading="loading" @click="emit('confirm')">
            {{ confirmLabel }}
          </Button>
        </div>
      </Card>
    </div>
  </Teleport>
</template>
