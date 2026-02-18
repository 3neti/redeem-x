import { ref, computed, onMounted, onUnmounted } from 'vue';

export interface KeyboardSearchOptions {
    /**
     * Auto-clear timeout in milliseconds (default: 1500)
     */
    timeout?: number;
}

/**
 * Composable for keyboard-driven incremental search
 * 
 * Captures alphanumeric keystrokes and provides search query state.
 * Auto-clears after timeout period of inactivity.
 * 
 * @param options - Configuration options
 * @returns Search state and controls
 */
export function useKeyboardSearch(options: KeyboardSearchOptions = {}) {
    const { timeout = 1500 } = options;
    
    const query = ref('');
    const isSearching = computed(() => query.value.length > 0);
    
    let clearTimer: ReturnType<typeof setTimeout> | null = null;
    
    /**
     * Clear the search query
     */
    const clearSearch = () => {
        query.value = '';
        if (clearTimer) {
            clearTimeout(clearTimer);
            clearTimer = null;
        }
    };
    
    /**
     * Reset the auto-clear timer
     */
    const resetTimer = () => {
        if (clearTimer) {
            clearTimeout(clearTimer);
        }
        
        clearTimer = setTimeout(() => {
            clearSearch();
        }, timeout);
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
                if (query.value.length > 0) {
                    resetTimer();
                } else {
                    clearSearch();
                }
            }
            return;
        }
        
        // Handle alphanumeric keys (single character)
        if (event.key.length === 1 && /^[a-zA-Z0-9-]$/.test(event.key)) {
            event.preventDefault();
            query.value += event.key.toUpperCase();
            resetTimer();
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
        clearSearch,
        filterByCode,
        attachListener,
        detachListener,
    };
}
