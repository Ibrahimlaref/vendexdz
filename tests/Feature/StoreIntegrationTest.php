<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\WebhookLog;
use App\Models\Order;
use Illuminate\Support\Str;

class StoreIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;
    protected $integration;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = clone User::factory()->create();
        $this->store = Store::create([
            'name' => 'Test Store',
            'created_by' => $this->user->id
        ]);
        
        $this->integration = StoreIntegration::create([
            'store_id' => $this->store->id,
            'platform' => 'shopify',
            'name' => 'Shopify Test',
            'external_url' => 'https://test.myshopify.com',
            'created_by' => $this->user->id,
            'webhook_secret' => 'test_secret'
        ]);
    }

    public function test_webhook_unauthorized_without_valid_signature()
    {
        $payload = ['id' => '12345'];
        $uuid = $this->integration->webhook_uuid;

        // Missing signature
        $response = $this->postJson("/api/webhooks/shopify/{$uuid}", $payload);
        $response->assertStatus(401);
        
        // Invalid signature
        $response = $this->postJson("/api/webhooks/shopify/{$uuid}", $payload, [
            'X-Shopify-Hmac-Sha256' => 'invalid'
        ]);
        $response->assertStatus(401);
    }

    public function test_webhook_authorized_with_valid_signature()
    {
        $payload = json_encode([
            'id' => 123456,
            'email' => 'test@example.com',
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address1' => '123 Test St',
                'city' => 'Algiers',
                'province' => 'Alger',
                'phone' => '0555123456'
            ],
            'line_items' => [
                ['sku' => 'TEST-SKU-1', 'quantity' => 2, 'price' => '100.00']
            ],
            'total_price' => '200.00',
        ]);

        $uuid = $this->integration->webhook_uuid;
        $secret = $this->integration->webhook_secret;
        
        $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        // Note: Disabling jobs execution for this test
        \Illuminate\Support\Facades\Queue::fake();

        $response = $this->call(
            'POST', 
            "/api/webhooks/shopify/{$uuid}", 
            [], 
            [], 
            [], 
            [
                'HTTP_X-Shopify-Hmac-Sha256' => $signature,
                'CONTENT_TYPE' => 'application/json'
            ], 
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Verify WebhookLog was created
        $this->assertDatabaseHas('webhook_logs', [
            'store_integration_id' => $this->integration->id,
            'platform' => 'shopify',
            'signature_valid' => 1
        ]);
        
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessIncomingOrderJob::class);
    }
}
