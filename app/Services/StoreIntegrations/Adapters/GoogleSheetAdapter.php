<?php

namespace App\Services\StoreIntegrations\Adapters;

use Illuminate\Http\Request;

class GoogleSheetAdapter extends BaseAdapter
{
    public function getPlatformName(): string { return 'Google Sheets'; }
    public function getPlatformKey(): string { return 'google_sheets'; }

    public function normalizeOrderPayload(array $data): ?array
    {
        // Adapting generic google sheet data row to order payload
        if (empty($data)) return null;
        
        return [
            'platform_order_id' => (string) ($data['id'] ?? $data['order_id'] ?? time()),
            'client_name'       => $data['nom'] ?? $data['name'] ?? $data['client_name'] ?? 'Client Inconnu',
            'phone1'            => $data['telephone'] ?? $data['phone'] ?? $data['phone1'] ?? '',
            'phone2'            => $data['telephone2'] ?? $data['phone2'] ?? '',
            'email'             => $data['email'] ?? '',
            'wilaya'            => $this->mapWilaya($data['wilaya'] ?? $data['state'] ?? $data['province'] ?? ''),
            'city'              => $data['commune'] ?? $data['city'] ?? '',
            'address'           => $data['adresse'] ?? $data['address'] ?? '',
            'items'             => [
                [
                    'sku'      => $data['sku'] ?? $data['produit'] ?? $data['product'] ?? '',
                    'name'     => $data['produit_nom'] ?? $data['product_name'] ?? '',
                    'quantity' => (int) ($data['quantite'] ?? $data['quantity'] ?? $data['qty'] ?? 1),
                    'price'    => (float) ($data['prix'] ?? $data['price'] ?? 0),
                ]
            ],
            'delivery_type'     => (strtolower($data['livraison'] ?? '') === 'stopdesk') ? 1 : 0,
            'comment'           => $data['remarque'] ?? $data['comment'] ?? $data['note'] ?? '',
            'discount'          => (float) ($data['remise'] ?? $data['discount'] ?? 0),
            'subtotal'          => (float) ($data['sous_total'] ?? $data['subtotal'] ?? 0),
            'total'             => (float) ($data['total'] ?? 0),
            'currency'          => $data['devise'] ?? $data['currency'] ?? 'DZD',
            'platform_data'     => $data,
        ];
    }

    public function getSetupInstructions(string $webhookUrl): array
    {
        return [
            ['step' => 1, 'title' => 'Google Apps Script', 'description' => 'Dans votre Google Sheet, allez dans **Extensions → Apps Script**.'],
            ['step' => 2, 'title' => 'Script de Webhook', 'description' => 'Ajoutez un script qui envoie une requête POST avec les données de la ligne à l\'URL suivante lors d\'un ajout (trigger onChange/onEdit) :', 'code' => $webhookUrl],
        ];
    }
}
