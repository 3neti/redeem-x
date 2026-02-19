import { ref, computed, onMounted, onUnmounted } from 'vue';

/**
 * Composable for keyboard-driven incremental search
 * 
 * Captures alphanumeric keystrokes and provides search query state.
 * Filter stays active until explicitly cleared with ESC.
 * 
 * @returns Search state and controls
 */
export function useKeyboardSearch() {
    const query = ref('');
    const isSearching = computed(() => query.value.length > 0);
    const showOverlay = ref(false);
    let overlayTimer: ReturnType<typeof setTimeout> | null = null;
    
    /**
     * Clear the search query
     */
    const clearSearch = () => {
        query.value = '';
        showOverlay.value = false;
        if (overlayTimer) {
            clearTimeout(overlayTimer);
            overlayTimer = null;
        }
    };
    
    /**
     * Show overlay and auto-hide after delay
     */
    const showOverlayWithTimeout = () => {
        showOverlay.value = true;
        if (overlayTimer) {
            clearTimeout(overlayTimer);
        }
        overlayTimer = setTimeout(() => {
            showOverlay.value = false;
        }, 1500); // Hide overlay after 1.5s of inactivity
    };
    
    /**
     * Handle keyboard events
     */
    const handleKeydown = (event: KeyboardEvent) => {
        // Ignore if user is typing in an input field
        const target = event.target as HTMLElement;
        if (
            target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable
        ) {
            return;
        }
        
        // Ignore modifier keys
        if (event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }
        
        // Handle ESC key
        if (event.key === 'Escape') {
            if (isSearching.value) {
                event.preventDefault();
                clearSearch();
            }
            return;
        }
        
        // Handle Enter key (handled in parent component)
        if (event.key === 'Enter') {
            if (isSearching.value) {
                event.preventDefault();
            }
            return;
        }
        
        // Handle Backspace
        if (event.key === 'Backspace') {
            if (isSearching.value) {
                event.preventDefault();
                query.value = query.value.slice(0, -1);
                showOverlayWithTimeout();
            }
            return;
        }
        
        // Handle alphanumeric keys (single character)
        if (event.key.length === 1 && /^[a-zA-Z0-9-]$/.test(event.key)) {
            event.preventDefault();
            query.value += event.key.toUpperCase();
            showOverlayWithTimeout();
        }
    };
    
    /**
     * Filter items by code matching the search query
     */
    const filterByCode = <T extends { code: string }>(items: T[]): T[] => {
        if (!isSearching.value) {
            return items;
        }
        
        const searchQuery = query.value.toLowerCase();
        return items.filter(item => 
            item.code.toLowerCase().startsWith(searchQuery)
        );
    };
    
    /**
     * Attach keyboard listener (call in onMounted)
     */
    const attachListener = () => {
        window.addEventListener('keydown', handleKeydown);
    };
    
    /**
     * Detach keyboard listener (call in onUnmounted)
     */
    const detachListener = () => {
        window.removeEventListener('keydown', handleKeydown);
        clearSearch();
    };
    
    return {
        query,
        isSearching,
        showOverlay,
        clearSearch,
        filterByCode,
        attachListener,
        detachListener,
    };
}
