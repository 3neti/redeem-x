import { ref } from 'vue'
import axios from 'axios'

export type GenerateMode = 'simple' | 'advanced'

export function useGenerateMode(initialMode: GenerateMode = 'simple') {
  const mode = ref<GenerateMode>(initialMode)
  const loading = ref(false)

  const switchMode = async (newMode: GenerateMode) => {
    mode.value = newMode
    loading.value = true
    
    try {
      await axios.put('/api/v1/preferences/voucher-mode', { mode: newMode })
    } catch (error) {
      console.error('Failed to save mode preference:', error)
    } finally {
      loading.value = false
    }
  }

  return {
    mode,
    loading,
    switchMode,
  }
}
