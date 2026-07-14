<?php

namespace App\Services\StoreIntegrations;

use App\Services\StoreIntegrations\Adapters\ShopifyAdapter;
use App\Services\StoreIntegrations\Adapters\WooCommerceAdapter;
use App\Services\StoreIntegrations\Adapters\AyorAdapter;
use App\Services\StoreIntegrations\Adapters\LightFunnelsAdapter;
use App\Services\StoreIntegrations\Adapters\FoorwebAdapter;
use App\Services\StoreIntegrations\Adapters\YouCanAdapter;
use App\Services\StoreIntegrations\Adapters\GoogleSheetAdapter;
use App\Services\StoreIntegrations\Adapters\GenericAdapter;

/**
 * Factory to resolve platform string → adapter instance.
 * Adding a new platform = adding one adapter class + one line here.
 */
class PlatformAdapterFactory
{
    protected static array $adapters = [
        'shopify'       => ShopifyAdapter::class,
        'woocommerce'   => WooCommerceAdapter::class,
        'ayor'          => AyorAdapter::class,
        'lightfunnels'  => LightFunnelsAdapter::class,
        'foorweb'       => FoorwebAdapter::class,
        'youcan'        => YouCanAdapter::class,
        'google_sheets' => GoogleSheetAdapter::class,
        'googlesheet'   => GoogleSheetAdapter::class,
    ];

    /**
     * Get an adapter instance for the given platform.
     */
    public static function make(string $platform): PlatformAdapterInterface
    {
        $adapterClass = self::$adapters[$platform] ?? GenericAdapter::class;
        return new $adapterClass();
    }

    /**
     * Check if a platform has a dedicated adapter.
     */
    public static function isSupported(string $platform): bool
    {
        return isset(self::$adapters[$platform]);
    }

    /**
     * Get all registered platform keys.
     */
    public static function supportedPlatforms(): array
    {
        return array_keys(self::$adapters);
    }
}
