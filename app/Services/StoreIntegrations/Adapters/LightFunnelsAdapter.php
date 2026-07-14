<?php

namespace App\Services\StoreIntegrations\Adapters;

use Illuminate\Http\Request;

class LightFunnelsAdapter extends BaseAdapter
{
    public function getPlatformName(): string { return 'Lightfunnels'; }
    public function getPlatformKey(): string { return 'lightfunnels'; }

    public function normalizeOrderPayload(array $data): ?array
    {
        if (empty($data) || (!isset($data['id']) && !isset($data['order']['id']))) {
            return null;
        }

        $orderData = $data['order'] ?? $data;
        $customer = $orderData['customer'] ?? [];
        $shippingAddress = $orderData['shipping_address'] ?? [];

        return [
            'platform_order_id' => (string) ($orderData['id'] ?? ''),
            'client_name'       => trim(($shippingAddress['first_name'] ?? $customer['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? $customer['last_name'] ?? '')),
            'phone1'            => $shippingAddress['phone'] ?? $customer['phone'] ?? '',
            'phone2'            => '',
            'email'             => $customer['email'] ?? '',
            'wilaya'            => $this->mapWilaya($shippingAddress['province'] ?? $shippingAddress['city'] ?? ''),
            'city'              => $shippingAddress['city'] ?? '',
            'address'           => trim(($shippingAddress['address1'] ?? '') . ' ' . ($shippingAddress['address2'] ?? '')),
            'items'             => array_map(fn($item) => [
                'sku'      => $item['sku'] ?? '',
                'name'     => $item['name'] ?? $item['product']['title'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'price'    => $item['price'] ?? 0,
            ], $orderData['line_items'] ?? []),
            'delivery_type'     => 1, // Defaulting to home delivery for Lightfunnels
            'comment'           => $orderData['note'] ?? '',
            'discount'          => $orderData['total_discounts'] ?? 0,
            'subtotal'          => $orderData['subtotal_price'] ?? 0,
            'total'             => $orderData['total_price'] ?? 0,
            'currency'          => $orderData['currency'] ?? 'DZD',
            'platform_data'     => $data,
        ];
    }

    public function getSetupInstructions(string $webhookUrl): array
    {
        return [
            ['step' => 1, 'title' => 'Paramètres Lightfunnels', 'description' => 'Allez dans **Settings → Webhooks** dans votre admin Lightfunnels.'],
            ['step' => 2, 'title' => 'Créer un webhook', 'description' => 'Cliquez sur **Create Webhook**.'],
            ['step' => 3, 'title' => 'Configuration', 'description' => "Entrez l'URL ci-dessous, sélectionnez l'événement **Order Created**.", 'code' => $webhookUrl],
            ['step' => 4, 'title' => 'Enregistrer', 'description' => 'Sauvegardez pour activer le webhook.'],
        ];
    }
}
