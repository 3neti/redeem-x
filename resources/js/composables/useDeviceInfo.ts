/**
 * Device Information Composable
 * 
 * Provides device detection and persistent device ID management for kiosk mode.
 * Device IDs are stored in localStorage and persist across sessions.
 */

export interface DeviceInfo {
    device_id: string;
    model?: string;
    platform?: string;
    screen_size?: string;
    os?: string;
    browser?: string;
}

export function useDeviceInfo() {
    const STORAGE_KEY = 'kiosk_device_id';

    /**
     * Check if localStorage is available
     * Handles incognito mode and browser restrictions
     */
    function isLocalStorageAvailable(): boolean {
        try {
            const test = '__storage_test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return true;
        } catch {
            return false;
        }
    }

    /**
     * Detect operating system from user agent
     */
    function detectOS(): string {
        const ua = navigator.userAgent;
        if (/Android/i.test(ua)) return 'Android';
        if (/iPhone|iPad|iPod/i.test(ua)) return 'iOS';
        if (/Windows/i.test(ua)) return 'Windows';
        if (/Mac OS X/i.test(ua)) return 'macOS';
        if (/Linux/i.test(ua)) return 'Linux';
        return 'Unknown';
    }

    /**
     * Detect browser from user agent
     */
    function detectBrowser(): string {
        const ua = navigator.userAgent;
        if (/Chrome/i.test(ua) && !/Edg/i.test(ua)) return 'Chrome';
        if (/Safari/i.test(ua) && !/Chrome/i.test(ua)) return 'Safari';
        if (/Firefox/i.test(ua)) return 'Firefox';
        if (/Edg/i.test(ua)) return 'Edge';
        return 'Unknown';
    }

    /**
     * Get or create persistent device ID
     * Returns UUID stored in localStorage, or generates new one if not found
     * Falls back to session-only ID if localStorage unavailable
     */
    function getDeviceId(): string {
        if (!isLocalStorageAvailable()) {
            // Incognito mode or localStorage blocked - generate session-only ID
            return `session-${crypto.randomUUID()}`;
        }

        let deviceId = localStorage.getItem(STORAGE_KEY);
        if (!deviceId) {
            deviceId = crypto.randomUUID();
            localStorage.setItem(STORAGE_KEY, deviceId);
        }
        return deviceId;
    }

    /**
     * Get device metadata (lightweight browser/device info)
     */
    function getDeviceMetadata(): Partial<DeviceInfo> {
        return {
            model: navigator.userAgent.match(/\(([^)]+)\)/)?.[1] || 'Unknown',
            platform: navigator.platform,
            screen_size: `${screen.width}x${screen.height}`,
            os: detectOS(),
            browser: detectBrowser(),
        };
    }

    /**
     * Get full device information (ID + metadata)
     */
    function getDeviceInfo(): DeviceInfo {
        return {
            device_id: getDeviceId(),
            ...getDeviceMetadata(),
        };
    }

    /**
     * Reset device ID (for admin/debug purposes)
     */
    function resetDeviceId(): void {
        if (isLocalStorageAvailable()) {
            localStorage.removeItem(STORAGE_KEY);
        }
    }

    /**
     * Check if device ID is session-only (not persisted)
     */
    function isSessionOnly(): boolean {
        return !isLocalStorageAvailable();
    }

    return {
        getDeviceId,
        getDeviceMetadata,
        getDeviceInfo,
        resetDeviceId,
        isSessionOnly,
        STORAGE_KEY,
    };
}
