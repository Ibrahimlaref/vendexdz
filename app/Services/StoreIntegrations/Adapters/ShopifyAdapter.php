<?php

namespace App\Services\StoreIntegrations\Adapters;

use Illuminate\Http\Request;

class ShopifyAdapter extends BaseAdapter
{
    public function getPlatformName(): string { return 'Shopify'; }
    public function getPlatformKey(): string { return 'shopify'; }

    /**
     * Verify Shopify HMAC-SHA256 signature.
     */
    public function verifySignature(Request $request, string $secret): bool
    {
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');

        if (!$hmacHeader) {
            return false;
        }

        $calculatedHmac = base64_encode(
            hash_hmac('sha256', $request->getContent(), $secret, true)
        );

        return hash_equals($hmacHeader, $calculatedHmac);
    }

    /**
     * Normalize Shopify order payload.
     * Ported from OrderWebhookController::handleShopifyWebhook
     */
    public function normalizeOrderPayload(array $data): ?array
    {
        if (empty($data) || !isset($data['id'])) {
            return null;
        }

        $province = $data['shipping_address']['province'] ?? null;

        if (!$province) {
            $province = $this->extractProvinceFromNotes($data['note_attributes'] ?? []);
        }

        $wilayaCode = $this->mapWilaya($province);

        $shippingTitle = $data['shipping_lines'][0]['title'] ?? null;
        $deliveryType = $this->mapDeliveryType($shippingTitle);

        return [
            'platform_order_id' => (string) ($data['id'] ?? ''),
            'client_name'       => trim(($data['shipping_address']['first_name'] ?? '') . ' ' . ($data['shipping_address']['last_name'] ?? '')),
            'phone1'            => $data['shipping_address']['phone'] ?? $data['phone'] ?? '',
            'phone2'            => '',
            'email'             => $data['email'] ?? '',
            'wilaya'            => $wilayaCode,
            'city'              => $data['shipping_address']['city'] ?? '',
            'address'           => $data['shipping_address']['address1'] ?? '',
            'items'             => collect($data['line_items'] ?? [])->map(function ($item) {
                return [
                    'sku'      => $item['sku'] ?? '',
                    'name'     => $item['title'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'price'    => $item['price'] ?? 0,
                ];
            })->values()->toArray(),
            'delivery_type'     => $deliveryType,
            'comment'           => $data['note'] ?? '',
            'discount'          => $data['total_discounts'] ?? 0,
            'subtotal'          => $data['subtotal_price'] ?? 0,
            'total'             => $data['total_price'] ?? 0,
            'currency'          => $data['currency'] ?? 'DZD',
            'platform_data'     => $data,
        ];
    }

    public function getSetupInstructions(string $webhookUrl): array
    {
        return [
            [
                'step' => 1,
                'title' => 'Accédez aux paramètres Shopify',
                'description' => 'Connectez-vous à votre panneau d\'administration Shopify, puis allez dans **Paramètres → Notifications**.',
            ],
            [
                'step' => 2,
                'title' => 'Ouvrez la section Webhooks',
                'description' => 'Faites défiler vers le bas jusqu\'à la section **Webhooks** et cliquez sur **Créer un webhook**.',
            ],
            [
                'step' => 3,
                'title' => 'Configurez le webhook',
                'description' => "Sélectionnez l'événement **Création de commande** (Order creation), le format **JSON**, puis collez l'URL suivante :",
                'code' => $webhookUrl,
            ],
            [
                'step' => 4,
                'title' => 'Enregistrez',
                'description' => 'Cliquez sur **Enregistrer** pour activer le webhook. Les nouvelles commandes seront automatiquement importées.',
            ],
        ];
    }

    public function getRequiredFields(): array
    {
        return [
            'name' => ['label' => 'Nom du magasin', 'type' => 'text', 'required' => true],
            'external_url' => ['label' => 'URL du magasin Shopify', 'type' => 'url', 'required' => true, 'placeholder' => 'votre-boutique.myshopify.com'],
        ];
    }

    // --- Private helpers ported from controller ---

    private function extractProvinceFromNotes(array $notes): ?string
    {
        foreach ($notes as $note) {
            if (strtolower($note['name'] ?? '') === 'province') {
                return $note['value'];
            }
        }
        return null;
    }

    private function mapDeliveryType(?string $shippingTitle): int
    {
        if (!$shippingTitle) return 0;
        $title = strtolower(trim($shippingTitle));
        $map = ['door delivery' => 0, 'home delivery' => 1];
        return $map[$title] ?? 0;
    }
}
