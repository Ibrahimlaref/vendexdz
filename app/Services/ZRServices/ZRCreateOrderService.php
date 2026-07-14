<?php

namespace App\Services\ZRServices;

use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Models\OrderInconfirmation;
use App\Models\OrderWaiting;

class ZRCreateOrderService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $tenantId;
    protected $installedApp;

    public function __construct($installedApp)
    {
        $this->installedApp = $installedApp;
        $this->baseUrl = $installedApp->supportedApp->base_url;
        $this->apiKey = $installedApp->token;
        $this->tenantId = $installedApp->key;
    }

    public function sendOrders($standardOrders)
    { 
        $parcels = [];
        foreach ($standardOrders as $order) {
            $parcels[] = $this->formatOrder($order);
        }

        $response = Http::withHeaders([
            'Accept'    => 'application/json',
            'X-Api-Key' => $this->apiKey,
            'X-Tenant'  => $this->tenantId,
            ])->post("{$this->baseUrl}/parcels/bulk", ['parcels' => $parcels]);
            
        $data = $response->json();
        return $data;
    }
    
    public function formatOrder($standardOrder)
    {
        $territoryService = new \App\Services\TerritoryServices\ZRTerritoryService($this->installedApp);
        $territoryData = $territoryService->getEverythingCached();

        $wilayaCode = (int)$standardOrder->wilaya;
        $zrWilaya = $territoryData['wilayas'][$wilayaCode] ?? null;

        if (!$zrWilaya) {
            throw new \Exception("ZR Error: Wilaya code {$wilayaCode} not found.");
        }

        $wilayaUuid = $zrWilaya['id'];
        $communesInWilaya = collect($territoryData['communes'][$wilayaUuid] ?? []);
        $zrCommune = $communesInWilaya->firstWhere('name', $standardOrder->city);

        if (!$zrCommune) {
            throw new \Exception("ZR Error: Commune '{$standardOrder->city}' not found in {$zrWilaya['name']}.");
        }

        $order_id = str_replace('VN-', '', $standardOrder->ref);

        $order = Order::with(['items'])->find($order_id);
        $orderedProducts = $order->items->map(function($item){
            return [
                "productSku"  => $item->variant ? $item->variant->sku : null,
                "productName" => $item->product->nickname??$item->product->name,
                "unitPrice"   => $item->product->price,
                "quantity"    => $item->quantity,
                "stockType"   => "none"
            ];
        })->toArray();

        // Determine delivery type: stopdesk (1) = pickup-point, otherwise home
        $isStopDesk = (int)($standardOrder->delivery_type ?? 0) === 1;
        $deliveryType = $isStopDesk ? "pickup-point" : "home";

        // For stop-desk orders, validate that the commune supports pickup points
        if ($isStopDesk) {
            $hasPickupPoint = $zrCommune['delivery']['hasPickupPoint']
                ?? $zrCommune['hasPickupPoint']
                ?? false;

            if (!$hasPickupPoint) {
                throw new \Exception(
                    "ZR Error: Stop desk (pickup-point) is not available for commune '{$standardOrder->city}' in {$zrWilaya['name']}. "
                    . "Please change the delivery type to domicile or select a different commune."
                );
            }
        }

        return [
            "customer" => [
                "customerId"=> "5c809fd6-dfca-4f72-a88a-10dd333339de",
                "name" => $standardOrder->name,
                "phone" => [
                    "number1" => $this->formatPhone($standardOrder->phone),
                    "number2" => $standardOrder->phone2 ? $this->formatPhone($standardOrder->phone2) : null
                ]
            ],
            "deliveryAddress" => [
                "street"             => $standardOrder->address ?: "N/A",
                "city"               => $zrWilaya['name'], 
                "district"           => $zrCommune['name'],
                "country"            => "algeria",
                "cityTerritoryId"    => $zrWilaya['id'], 
                "districtTerritoryId" => $zrCommune['id']
            ],
            "orderedProducts" => $orderedProducts,
            "amount"       => (double) $standardOrder->total_price,
            "description" => !empty($standardOrder->commenter)
                ? $standardOrder->commenter
                : $standardOrder->product_name,
            "deliveryType" => $deliveryType,
            "externalId"   => $standardOrder->ref,
        ];
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            return '+213' . substr($phone, 1);
        }
        return '+' . $phone;
    }
}