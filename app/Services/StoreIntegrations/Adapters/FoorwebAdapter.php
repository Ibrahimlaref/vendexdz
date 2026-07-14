<?php

namespace App\Services\StoreIntegrations\Adapters;

use Illuminate\Http\Request;

class FoorwebAdapter extends BaseAdapter
{
    public function getPlatformName(): string { return 'Foorweb'; }
    public function getPlatformKey(): string { return 'foorweb'; }

    public function normalizeOrderPayload(array $data): ?array
    {
        if (empty($data) || (!isset($data['id']) && !isset($data['order']['id']))) {
            return null;
        }

        $orderData = $data['order'] ?? $data;
        $customer = $orderData['customer'] ?? [];

        return [
            'platform_order_id' => (string) ($orderData['id'] ?? ''),
            'client_name'       => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
            'phone1'            => $customer['phone'] ?? '',
            'phone2'            => '',
            'email'             => $customer['email'] ?? '',
            'wilaya'            => $this->mapWilaya($customer['state'] ?? $customer['city'] ?? ''),
            'city'              => $customer['city'] ?? '',
            'address'           => $customer['address'] ?? '',
            'items'             => array_map(fn($item) => [
                'sku'      => $item['sku'] ?? '',
                'name'     => $item['name'] ?? $item['title'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'price'    => $item['price'] ?? 0,
            ], $orderData['products'] ?? $orderData['items'] ?? []),
            'delivery_type'     => ($orderData['delivery_type'] ?? '') === 'stopdesk' ? 1 : 0,
            'comment'           => $orderData['note'] ?? '',
            'discount'          => $orderData['discount'] ?? 0,
            'subtotal'          => $orderData['subtotal'] ?? 0,
            'total'             => $orderData['total'] ?? 0,
            'currency'          => $orderData['currency'] ?? 'DZD',
            'platform_data'     => $data,
        ];
    }

    public function getSetupInstructions(string $webhookUrl): array
    {
        return [
            ['step' => 1, 'title' => 'Foorweb Admin', 'description' => 'Connectez-vous à votre boutique Foorweb et allez dans **Webhooks**.'],
            ['step' => 2, 'title' => 'Ajouter Webhook', 'description' => "Ajoutez l'URL suivante pour l'événement **Nouvelle commande** :", 'code' => $webhookUrl],
        ];
    }
}
