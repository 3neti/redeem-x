import { ref, watch } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

export type GenerateMode = 'simple' | 'advanced'

const STORAGE_KEY = 'voucher_generate_mode'

export function useGenerateMode(initialMode: GenerateMode = 'simple') {
  // Try to load from localStorage first (for immediate persistence)
  const savedMode = localStorage.getItem(STORAGE_KEY) as GenerateMode | null
  const startMode = savedMode || initialMode
  
  const mode = ref<GenerateMode>(startMode)
  const loading = ref(false)
  const page = usePage()
  
  // Watch mode changes and persist to localStorage immediately
  watch(mode, (newMode) => {
    localStorage.setItem(STORAGE_KEY, newMode)
  })

  const switchMode = async (newMode: GenerateMode) => {
    // Check if user has permission to use advanced mode
    const hasAdvancedMode = page.props.auth?.feature_flags?.advanced_pricing_mode || false
    
    if (newMode === 'advanced' && !hasAdvancedMode) {
      return // Block mode change if no permission
    }
    
    mode.value = newMode // This will trigger the watch() which saves to localStorage
    loading.value = true
    
    try {
      // Also save to backend for cross-device sync (optional, non-blocking)
      await axios.put('/api/v1/preferences/voucher-mode', { mode: newMode })
    } catch (error) {
      // Don't fail - localStorage persistence is enough
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
