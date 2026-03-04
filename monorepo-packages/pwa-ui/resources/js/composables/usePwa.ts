import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePwa() {
    const page = usePage();
    const isOnline = ref(navigator.onLine);
    const installPrompt = ref<any>(null);

    // Listen for online/offline events
    const updateOnlineStatus = () => {
        isOnline.value = navigator.onLine;
    };

    onMounted(() => {
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        // Listen for install prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            installPrompt.value = e;
        });
    });

    const currentRoute = computed(() => page.url);

    const showInstallPrompt = async () => {
        if (!installPrompt.value) return false;

        installPrompt.value.prompt();
        const { outcome } = await installPrompt.value.userChoice;
        
        if (outcome === 'accepted') {
            installPrompt.value = null;
            return true;
        }
        
        return false;
    };

    const installAvailable = computed(() => installPrompt.value !== null);

    return {
        isOnline,
        installPrompt,
        installAvailable,
        currentRoute,
        showInstallPrompt,
    };
}
