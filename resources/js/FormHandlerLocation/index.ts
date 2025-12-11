export { default as LocationCapture } from './components/LocationCapture.vue';
export { default as LocationCapturePage } from './pages/LocationCapturePage.vue';
export { default as GeoPermissionAlert } from './components/GeoPermissionAlert.vue';

export { useBrowserLocation } from './composables/useBrowserLocation';

export type { LocationData, GeocodedAddressData } from './composables/useBrowserLocation';
export type { LocationCaptureConfig } from './components/LocationCapture.vue';
