<?php

namespace App\Services\StoreIntegrations\Adapters;

use Illuminate\Http\Request;

class GenericAdapter extends BaseAdapter
{
    public function getPlatformName(): string { return 'Custom Webhook'; }
    public function getPlatformKey(): string { return 'custom'; }

    public function normalizeOrderPayload(array $data): ?array
    {
        // Try to extract common fields in a generic way
        return [
            'platform_order_id' => (string) ($data['order_id'] ?? $data['id'] ?? time()),
            'client_name'       => $data['customer_name'] ?? $data['client_name'] ?? $data['name'] ?? '',
            'phone1'            => $data['phone'] ?? $data['phone1'] ?? '',
            'phone2'            => $data['phone2'] ?? '',
            'email'             => $data['email'] ?? '',
            'wilaya'            => $this->mapWilaya($data['wilaya'] ?? $data['state'] ?? $data['province'] ?? ''),
            'city'              => $data['city'] ?? $data['commune'] ?? '',
            'address'           => $data['address'] ?? $data['full_address'] ?? '',
            'items'             => $data['items'] ?? $data['line_items'] ?? $data['products'] ?? [],
            'delivery_type'     => $data['delivery_type'] ?? 1,
            'comment'           => $data['comment'] ?? $data['notes'] ?? $data['note'] ?? '',
            'discount'          => $data['discount'] ?? 0,
            'subtotal'          => $data['subtotal'] ?? 0,
            'total'             => $data['total'] ?? 0,
            'currency'          => $data['currency'] ?? 'DZD',
            'platform_data'     => $data
        ];
    }

    public function getSetupInstructions(string $webhookUrl): array
    {
        return [
            ['step' => 1, 'title' => 'Configuration', 'description' => 'Configurez votre plateforme pour envoyer des requêtes POST (format JSON) à cette URL :', 'code' => $webhookUrl],
        ];
    }
}
