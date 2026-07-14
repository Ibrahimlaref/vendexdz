<?php

namespace App\Services\StoreIntegrations\Adapters;

use Illuminate\Http\Request;

class WooCommerceAdapter extends BaseAdapter
{
    public function getPlatformName(): string { return 'WooCommerce'; }
    public function getPlatformKey(): string { return 'woocommerce'; }

    public function verifySignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-WC-Webhook-Signature');
        if (!$signature) return false;

        $calculated = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
        return hash_equals($signature, $calculated);
    }

    public function normalizeOrderPayload(array $data): ?array
    {
        if (empty($data) || !isset($data['id']) || !isset($data['billing'])) {
            return null;
        }

        $shipping = $data['shipping'] ?? $data['billing'];

        return [
            'platform_order_id' => (string) $data['id'],
            'client_name'       => trim(($data['billing']['first_name'] ?? '') . ' ' . ($data['billing']['last_name'] ?? '')),
            'phone1'            => $data['billing']['phone'] ?? '',
            'phone2'            => $shipping['phone'] ?? '',
            'email'             => $data['billing']['email'] ?? '',
            'wilaya'            => $this->mapWilaya($shipping['state'] ?? $shipping['city'] ?? ''),
            'city'              => $shipping['city'] ?? $data['billing']['city'] ?? '',
            'address'           => trim(($shipping['address_1'] ?? '') . ' ' . ($shipping['address_2'] ?? '')),
            'items'             => array_map(fn($item) => [
                'sku'      => $item['sku'] ?? '',
                'name'     => $item['name'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'price'    => $item['price'] ?? 0,
            ], $data['line_items'] ?? []),
            'delivery_type'     => ($data['shipping_lines'][0]['method_id'] ?? '') === 'local_pickup' ? 1 : 0,
            'comment'           => $data['customer_note'] ?? '',
            'discount'          => $data['discount_total'] ?? 0,
            'subtotal'          => ($data['total'] ?? 0) - ($data['shipping_total'] ?? 0) - ($data['discount_total'] ?? 0),
            'total'             => $data['total'] ?? 0,
            'currency'          => $data['currency'] ?? 'DZD',
            'platform_data'     => [
                'order_key' => $data['order_key'] ?? '',
                'status' => $data['status'] ?? '',
                'payment_method' => $data['payment_method'] ?? '',
            ],
        ];
    }

    public function getSetupInstructions(string $webhookUrl): array
    {
        return [
            ['step' => 1, 'title' => 'Accédez aux paramètres WooCommerce', 'description' => 'Dans votre tableau de bord WordPress, allez dans **WooCommerce → Réglages → Avancé → Webhooks**.'],
            ['step' => 2, 'title' => 'Ajoutez un webhook', 'description' => 'Cliquez sur **Ajouter un webhook** et configurez les champs suivants.'],
            ['step' => 3, 'title' => 'Configurez le webhook', 'description' => "Nom: **Vendex Orders**, Statut: **Actif**, Sujet: **Commande créée**, URL de livraison :", 'code' => $webhookUrl],
            ['step' => 4, 'title' => 'Enregistrez', 'description' => 'Cliquez sur **Enregistrer le webhook**. Notez le **Secret** affiché par WooCommerce et collez-le dans le champ secret ci-dessous.'],
        ];
    }

    public function getRequiredFields(): array
    {
        return [
            'name' => ['label' => 'Nom du magasin', 'type' => 'text', 'required' => true],
            'external_url' => ['label' => 'URL du site WordPress', 'type' => 'url', 'required' => true, 'placeholder' => 'www.votre-site.com'],
        ];
    }
}
