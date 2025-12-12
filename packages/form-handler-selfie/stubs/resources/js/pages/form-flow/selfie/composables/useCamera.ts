import { ref, onUnmounted } from 'vue';

export function useCamera() {
    const stream = ref<MediaStream | null>(null);
    const error = ref<string | null>(null);
    const loading = ref(false);

    async function startCamera(constraints: MediaStreamConstraints = { video: { facingMode: 'user' } }) {
        loading.value = true;
        error.value = null;

        try {
            stream.value = await navigator.mediaDevices.getUserMedia(constraints);
            return stream.value;
        } catch (err: any) {
            console.error('Camera error:', err);
            
            if (err.name === 'NotAllowedError') {
                error.value = 'Camera access denied. Please allow camera access in your browser settings.';
            } else if (err.name === 'NotFoundError') {
                error.value = 'No camera found on this device.';
            } else {
                error.value = 'Failed to access camera. Please try again.';
            }
            
            throw err;
        } finally {
            loading.value = false;
        }
    }

    function stopCamera() {
        if (stream.value) {
            stream.value.getTracks().forEach(track => track.stop());
            stream.value = null;
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        stopCamera();
    });

    return {
        stream,
        error,
        loading,
        startCamera,
        stopCamera,
    };
}
