import { ref, computed } from 'vue';

/**
 * Kiosk History Entry
 * Represents a single voucher in the history
 */
export interface KioskHistoryEntry {
  code: string;
  amount: number;
  formatted_amount: string;
  redemption_url: string;
  issued_at: string; // ISO 8601 timestamp
  qr_data_url: string; // Base64 QR code data URL
}

/**
 * Kiosk History Composable
 * 
 * Manages voucher history with localStorage persistence.
 * - Per-skin isolation (separate history per kiosk type)
 * - Auto-purge of entries older than 24 hours
 * - FIFO eviction when max capacity reached
 * - Reactive state for UI updates
 */
export function useKioskHistory(skin: string, maxItems = 5, maxAgeHours = 24) {
  const STORAGE_PREFIX = 'kiosk_history';
  const storageKey = `${STORAGE_PREFIX}:${skin}`;

  // Reactive history state
  const history = ref<KioskHistoryEntry[]>([]);

  /**
   * Load history from localStorage
   * Auto-purges expired entries
   */
  const loadHistory = (): KioskHistoryEntry[] => {
    try {
      const stored = localStorage.getItem(storageKey);
      if (!stored) return [];

      const parsed: KioskHistoryEntry[] = JSON.parse(stored);
      
      // Filter out expired entries
      const now = new Date();
      const maxAge = maxAgeHours * 60 * 60 * 1000; // Convert to milliseconds
      
      const valid = parsed.filter(entry => {
        const issuedTime = new Date(entry.issued_at).getTime();
        const age = now.getTime() - issuedTime;
        return age < maxAge;
      });

      // Sort by issued_at descending (newest first)
      valid.sort((a, b) => 
        new Date(b.issued_at).getTime() - new Date(a.issued_at).getTime()
      );

      return valid;
    } catch (error) {
      console.error('[useKioskHistory] Failed to load history:', error);
      return [];
    }
  };

  /**
   * Save history to localStorage
   */
  const saveHistory = (entries: KioskHistoryEntry[]): void => {
    try {
      localStorage.setItem(storageKey, JSON.stringify(entries));
    } catch (error) {
      console.error('[useKioskHistory] Failed to save history:', error);
    }
  };

  /**
   * Add voucher to history
   * - Prepends to array (newest first)
   * - Enforces max items limit (FIFO eviction)
   * - Prevents duplicates (same voucher code)
   */
  const addToHistory = (
    code: string,
    amount: number,
    formatted_amount: string,
    redemption_url: string,
    qr_data_url: string
  ): void => {
    const current = loadHistory();

    // Check for duplicate (avoid re-adding same voucher)
    const exists = current.find(entry => entry.code === code);
    if (exists) {
      console.log('[useKioskHistory] Voucher already in history:', code);
      return;
    }

    // Create new entry
    const entry: KioskHistoryEntry = {
      code,
      amount,
      formatted_amount,
      redemption_url,
      issued_at: new Date().toISOString(),
      qr_data_url,
    };

    // Prepend new entry
    current.unshift(entry);

    // Enforce max items (FIFO - remove oldest)
    if (current.length > maxItems) {
      current.splice(maxItems);
    }

    // Save to localStorage
    saveHistory(current);

    // Update reactive state
    history.value = current;
  };

  /**
   * Get current history
   * Returns sorted array (newest first)
   */
  const getHistory = (): KioskHistoryEntry[] => {
    return loadHistory();
  };

  /**
   * Clear all history for this skin
   */
  const clearHistory = (): void => {
    try {
      localStorage.removeItem(storageKey);
      history.value = [];
    } catch (error) {
      console.error('[useKioskHistory] Failed to clear history:', error);
    }
  };

  /**
   * Get relative time string (e.g., "5 min ago", "2 hours ago")
   */
  const getRelativeTime = (isoTimestamp: string): string => {
    const now = new Date();
    const issued = new Date(isoTimestamp);
    const diffMs = now.getTime() - issued.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);

    if (diffMins < 1) return 'Just now';
    if (diffMins === 1) return '1 min ago';
    if (diffMins < 60) return `${diffMins} mins ago`;
    if (diffHours === 1) return '1 hour ago';
    if (diffHours < 24) return `${diffHours} hours ago`;
    return 'Over 24 hours ago';
  };

  // Computed: History count
  const historyCount = computed(() => history.value.length);

  // Computed: Has history
  const hasHistory = computed(() => history.value.length > 0);

  // Initialize: Load history on creation
  history.value = loadHistory();

  return {
    history,
    historyCount,
    hasHistory,
    addToHistory,
    getHistory,
    clearHistory,
    getRelativeTime,
  };
}
