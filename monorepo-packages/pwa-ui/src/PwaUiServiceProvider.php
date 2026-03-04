<?php

namespace LBHurtado\PwaUi;

use Illuminate\Support\ServiceProvider;

class PwaUiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/pwa-ui.php', 'pwa-ui'
        );
        
        // Skin configs are now loaded from YAML by SkinConfigLoader
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/pwa.php');

        // Publish PWA assets (service worker, manifest, icons)
        $this->publishes([
            __DIR__.'/../resources/pwa' => public_path('pwa'),
        ], 'pwa-assets');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/pwa-ui.php' => config_path('pwa-ui.php'),
        ], 'pwa-config');

        // Publish Vue components (layouts, components, pages, composables)
        $this->publishes([
            __DIR__.'/../resources/js/layouts' => resource_path('js/layouts'),
            __DIR__.'/../resources/js/components' => resource_path('js/components/pwa'),
            __DIR__.'/../resources/js/pages/pwa' => resource_path('js/pages/pwa'),
            __DIR__.'/../resources/js/composables' => resource_path('js/composables/pwa'),
        ], 'pwa-components');

        // Publish PhilHealth BST skin bundle (YAML-based)
        $this->publishes([
            __DIR__.'/../resources/skins/philhealth-bst/kiosk.yaml'
                => config_path('pwa-skins/philhealth-bst.yaml'),
            __DIR__.'/../resources/skins/philhealth-bst/driver.yaml'
                => config_path('envelope-drivers/philhealth-bst.yaml'),
            __DIR__.'/../resources/skins/philhealth-bst/assets'
                => public_path('pwa/skins/philhealth-bst'),
            __DIR__.'/../resources/skins/philhealth-bst/campaign.stub.php'
                => database_path('migrations/'.date('Y_m_d_His').'_create_philhealth_bst_campaign.php'),
        ], 'pwa-skin-philhealth-bst');
    }
}
