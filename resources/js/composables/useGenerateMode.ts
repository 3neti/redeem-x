import { ref } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

export type GenerateMode = 'simple' | 'advanced'

export function useGenerateMode(initialMode: GenerateMode = 'simple') {
  const mode = ref<GenerateMode>(initialMode)
  const loading = ref(false)
  const page = usePage()

  const switchMode = async (newMode: GenerateMode) => {
    console.log('[useGenerateMode] switchMode called:', { newMode, currentMode: mode.value })
    
    // Check if user has permission to use advanced mode
    const hasAdvancedMode = page.props.auth?.feature_flags?.advanced_pricing_mode || false
    console.log('[useGenerateMode] Permission check:', { hasAdvancedMode, newMode })
    
    if (newMode === 'advanced' && !hasAdvancedMode) {
      console.warn('[useGenerateMode] ❌ BLOCKED: User does not have advanced mode feature flag')
      return // CRITICAL: Block mode change if no permission
    }
    
    console.log('[useGenerateMode] ✅ Permission check passed, switching mode to:', newMode)
    mode.value = newMode
    loading.value = true
    
    try {
      await axios.put('/api/v1/preferences/voucher-mode', { mode: newMode })
      console.log('[useGenerateMode] Mode preference saved successfully')
    } catch (error) {
      console.error('[useGenerateMode] Failed to save mode preference:', error)
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
