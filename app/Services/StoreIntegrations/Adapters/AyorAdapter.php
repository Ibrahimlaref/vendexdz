<?php

namespace App\Services\StoreIntegrations\Adapters;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AyorAdapter extends BaseAdapter
{
    public function getPlatformName(): string { return 'Ayor'; }
    public function getPlatformKey(): string { return 'ayor'; }

    public function normalizeOrderPayload(array $data): ?array
    {
        // Ayor sends data in a nested message.data (base64-encoded JSON)
        $orderData = $this->extractAyorData($data);
        if (!$orderData || !isset($orderData['client_info'])) return null;

        $cities = $this->getAyorCitiesByState($orderData['client_info']['state'] ?? '');

        return [
            'platform_order_id' => (string) ($orderData['order_id'] ?? $orderData['display_id'] ?? ''),
            'client_name'       => $orderData['client_info']['full_name'] ?? 'Unknown',
            'phone1'            => $orderData['client_info']['phone_number'] ?? '',
            'phone2'            => '',
            'wilaya'            => $this->parseAyorWilaya($orderData['client_info']['state'] ?? ''),
            'city'              => $cities->firstWhere('id', $orderData['client_info']['city'])['name'] ?? '',
            'address'           => $this->extractAyorAddress($orderData),
            'items'             => array_map(fn($item) => [
                'sku'      => $item['sku'] ?? $item['product_name'] ?? 'N/A',
                'quantity' => $item['quantity'] ?? 1,
                'price'    => $item['unit_price'] ?? 0,
            ], $orderData['order_lines'] ?? []),
            'delivery_type'     => ($orderData['is_stop_desk'] ?? false) ? 1 : 0,
            'comment'           => $orderData['client_note'] ?? '',
            'discount'          => $this->calculateAyorDiscount($orderData['order_lines'] ?? []),
            'subtotal'          => $orderData['total_price'] ?? 0,
            'total'             => $orderData['total_price'] ?? 0,
            'currency'          => $orderData['currency'] ?? 'DZD',
            'platform_data'     => $data,
        ];
    }

    public function getSetupInstructions(string $webhookUrl): array
    {
        return [
            ['step' => 1, 'title' => 'Accédez aux paramètres Ayor', 'description' => 'Connectez-vous à votre tableau de bord Ayor et accédez aux **Paramètres → Webhooks**.'],
            ['step' => 2, 'title' => 'Ajoutez un webhook', 'description' => "Créez un nouveau webhook avec l'URL suivante :", 'code' => $webhookUrl],
            ['step' => 3, 'title' => 'Sélectionnez les événements', 'description' => "Sélectionnez l'événement **Nouvelle commande** et enregistrez."],
        ];
    }

    // --- Private helpers ported from controller ---

    private function extractAyorData(array $payload): ?array
    {
        if (isset($payload['message']['data'])) {
            $raw = $payload['message']['data'];
            if (is_string($raw)) {
                $decoded = json_decode(base64_decode($raw), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded['data'] ?? $decoded;
                }
            }
            return is_array($raw) ? $raw : null;
        }
        $data = $payload['data'] ?? $payload;
        if (!isset($data['client_info'])) {
            $data = $this->findKeyRecursively($payload, 'client_info');
        }
        return $data;
    }

    private function findKeyRecursively($array, $key)
    {
        if (!is_array($array)) return null;
        if (array_key_exists($key, $array)) return $array;
        foreach ($array as $value) {
            if (is_array($value)) {
                $result = $this->findKeyRecursively($value, $key);
                if ($result) return $result;
            }
        }
        return null;
    }

    private function parseAyorWilaya($stateString): int
    {
        return config("ayor_states.$stateString") ?? 16;
    }

    private function getAyorCitiesByState($stateId)
    {
        return Cache::remember("ayor_cities_state_$stateId", now()->addMonths(6), function () use ($stateId) {
            $response = Http::get("https://backend.ayor.ai/api/states/{$stateId}/cities/");
            return $response->successful() ? collect($response->json()) : collect([]);
        });
    }

    private function extractAyorAddress($orderData): string
    {
        if (isset($orderData['custom_fields']) && is_array($orderData['custom_fields'])) {
            foreach ($orderData['custom_fields'] as $field) {
                if (isset($field['custom_title']) && str_contains(strtolower($field['custom_title']), 'address')) {
                    return $field['value'] ?? '';
                }
            }
        }
        return $orderData['client_info']['city'] ?? '';
    }

    private function calculateAyorDiscount(array $orderLines): float
    {
        $discount = 0;
        foreach ($orderLines as $item) {
            if (isset($item['reduced_price']) && $item['reduced_price'] < $item['unit_price']) {
                $discount += ($item['unit_price'] - $item['reduced_price']) * $item['quantity'];
            }
        }
        return $discount;
    }
}
