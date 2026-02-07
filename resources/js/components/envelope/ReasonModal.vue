<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { AlertCircle, Loader2 } from 'lucide-vue-next'

interface Props {
    open: boolean
    title: string
    description: string
    action: string
    variant?: 'default' | 'destructive'
    loading?: boolean
    minLength?: number
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
    loading: false,
    minLength: 10
})

const emit = defineEmits<{
    'update:open': [value: boolean]
    confirm: [reason: string]
    cancel: []
}>()

const reason = ref('')
const touched = ref(false)

const isValid = computed(() => reason.value.trim().length >= props.minLength)
const showError = computed(() => touched.value && !isValid.value)

watch(() => props.open, (open) => {
    if (open) {
        reason.value = ''
        touched.value = false
    }
})

const handleConfirm = () => {
    touched.value = true
    if (isValid.value) {
        emit('confirm', reason.value.trim())
    }
}

const handleCancel = () => {
    emit('cancel')
    emit('update:open', false)
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            
            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="reason">Reason</Label>
                    <Textarea
                        id="reason"
                        v-model="reason"
                        placeholder="Enter the reason for this action..."
                        :class="{ 'border-red-500 focus-visible:ring-red-500': showError }"
                        @blur="touched = true"
                    />
                    <p v-if="showError" class="text-sm text-red-500 flex items-center gap-1">
                        <AlertCircle class="h-3 w-3" />
                        Reason must be at least {{ minLength }} characters
                    </p>
                </div>
            </div>
            
            <DialogFooter>
                <Button variant="outline" @click="handleCancel" :disabled="loading">
                    Cancel
                </Button>
                <Button 
                    :variant="variant" 
                    @click="handleConfirm" 
                    :disabled="loading || !isValid"
                >
                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                    {{ action }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
