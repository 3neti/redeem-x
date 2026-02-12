import { ref, computed, onMounted } from 'vue';

export function useInstallPrompt() {
    const deferredPrompt = ref<any>(null);
    const isInstallable = computed(() => deferredPrompt.value !== null);
    const isInstalled = ref(false);

    onMounted(() => {
        // Check if already installed
        if (window.matchMedia('(display-mode: standalone)').matches) {
            isInstalled.value = true;
        }

        // Capture the install prompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt.value = e;
        });

        // Detect when app is installed
        window.addEventListener('appinstalled', () => {
            isInstalled.value = true;
            deferredPrompt.value = null;
        });
    });

    const promptInstall = async () => {
        if (!deferredPrompt.value) {
            return { outcome: 'unavailable' };
        }

        deferredPrompt.value.prompt();
        const choiceResult = await deferredPrompt.value.userChoice;

        if (choiceResult.outcome === 'accepted') {
            deferredPrompt.value = null;
        }

        return choiceResult;
    };

    const dismissPrompt = () => {
        deferredPrompt.value = null;
    };

    return {
        isInstallable,
        isInstalled,
        promptInstall,
        dismissPrompt,
    };
}
