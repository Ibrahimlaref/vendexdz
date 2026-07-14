<?php

namespace App\Services\StoreIntegrations;

use Illuminate\Http\Request;

/**
 * Contract for platform-specific webhook handlers.
 * Each supported platform must implement this interface.
 */
interface PlatformAdapterInterface
{
    /**
     * Verify the webhook signature from the external platform.
     * Returns true if the signature is valid or if the platform doesn't support signing.
     */
    public function verifySignature(Request $request, string $secret): bool;

    /**
     * Normalize the platform-specific order payload into our internal format.
     * Returns a standardized array that the order creation pipeline can consume.
     */
    public function normalizeOrderPayload(array $data): ?array;

    /**
     * Get step-by-step setup instructions for configuring webhooks on this platform.
     * The webhook_url placeholder will be replaced with the actual URL.
     */
    public function getSetupInstructions(string $webhookUrl): array;

    /**
     * Get the fields required when creating a store integration for this platform.
     * e.g., ['external_url' => ['label' => 'Shop domain', 'required' => true]]
     */
    public function getRequiredFields(): array;

    /**
     * Get the human-readable platform name.
     */
    public function getPlatformName(): string;

    /**
     * Get the platform identifier string.
     */
    public function getPlatformKey(): string;
}
