<?php

namespace App\Services\StoreIntegrations\Adapters;

use Illuminate\Http\Request;

class YouCanAdapter extends BaseAdapter
{
    public function getPlatformName(): string { return 'YouCan'; }
    public function getPlatformKey(): string { return 'youcan'; }

    public function normalizeOrderPayload(array $data): ?array
    {
        // Adjust according to YouCan's exact JSON structure
        if (empty($data) || !isset($data['id'])) {
            return null;
        }

        $customer = $data['customer'] ?? [];
        $shipping = $data['shipping_address'] ?? [];

        return [
            'platform_order_id' => (string) $data['id'],
            'client_name'       => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
            'phone1'            => $customer['phone'] ?? $shipping['phone'] ?? '',
            'phone2'            => '',
            'email'             => $customer['email'] ?? '',
            'wilaya'            => $this->mapWilaya($shipping['province'] ?? $shipping['city'] ?? ''),
            'city'              => $shipping['city'] ?? '',
            'address'           => trim(($shipping['address1'] ?? '') . ' ' . ($shipping['address2'] ?? '')),
            'items'             => array_map(fn($item) => [
                'sku'      => $item['variant']['sku'] ?? $item['sku'] ?? '',
                'name'     => $item['product_name'] ?? $item['name'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'price'    => $item['price'] ?? 0,
            ], $data['items'] ?? $data['line_items'] ?? []),
            'delivery_type'     => 1,
            'comment'           => $data['notes'] ?? '',
            'discount'          => $data['discount'] ?? 0,
            'subtotal'          => $data['subtotal'] ?? 0,
            'total'             => $data['total'] ?? 0,
            'currency'          => $data['currency'] ?? 'DZD',
            'platform_data'     => $data,
        ];
    }

    public function getSetupInstructions(string $webhookUrl): array
    {
        return [
            ['step' => 1, 'title' => 'Tableau de bord YouCan', 'description' => 'Allez dans **Store Settings → Webhooks**.'],
            ['step' => 2, 'title' => 'Nouveau Webhook', 'description' => 'Créez un webhook pour l\'événement **Order Created** (Création de commande).'],
            ['step' => 3, 'title' => 'URL', 'description' => 'Collez cette URL de webhook :', 'code' => $webhookUrl],
        ];
    }
}
