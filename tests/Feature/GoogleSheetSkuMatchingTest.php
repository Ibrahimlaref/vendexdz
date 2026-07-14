<?php

namespace Tests\Feature;

use App\Models\GoogleSheetConnection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\willaya;
use App\Services\GoogleSheets\SheetOrderImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleSheetSkuMatchingTest extends TestCase
{
    use RefreshDatabase;

    private $store;
    private $product;
    private $variant;
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create default roles to satisfy user creation observer constraints
        foreach ([1 => 'Pending', 2 => 'Admin', 3 => 'Manager', 4 => 'Agent', 5 => 'Store'] as $rid => $name) {
            \App\Models\Role::create([
                'rid' => $rid,
                'name' => $name,
            ]);
        }

        // Create standard User to satisfy foreign key constraints
        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        // Create standard Category to satisfy foreign key constraints
        $category = \App\Models\Category::create([
            'cid' => 1,
            'name' => 'General',
        ]);

        // Create standard Store
        $this->store = Store::create([
            'name' => 'Test Store',
            'created_by' => $user->id,
        ]);

        // Create standard Product and Variant
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Super Widget',
            'nickname' => 'Widget',
            'price' => 100,
            'created_by' => $user->id,
            'category_id' => 1,
            'url' => '',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-WIDGET-123',
            'quantity' => 10,
            'discount' => 0,
        ]);

        // Create Wilaya (required for order client resolution)
        willaya::create([
            'wid' => 16,
            'name' => 'Alger',
        ]);

        // Insert first step status to satisfy order_inconfirmations.fsid foreign key
        \DB::table('first_step_status')->insert([
            'fsid' => 1,
            'name' => 'Pending',
            'icon' => 'pending.svg',
            'color' => 'bg-yellow-500',
            'show' => true,
        ]);

        // Create Connection
        $this->connection = GoogleSheetConnection::create([
            'sid' => $this->store->id,
            'sheet_id' => 'test-sheet-id',
            'column_mapping' => [
                'SKU / Reference' => 'sku',
                'Product Name' => 'product',
                'Customer Name' => 'customer_name',
                'Phone Primary' => 'phone_primary',
                'Wilaya' => 'wilaya',
                'Commune' => 'commune',
            ],
            'mapping_confirmed' => true,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function exact_sku_match_imports_successfully()
    {
        $importer = new SheetOrderImportService($this->connection);

        $rows = [
            [
                'row_number' => 2,
                'data' => [
                    'SKU / Reference' => 'SKU-WIDGET-123',
                    'Product Name' => 'Super Widget',
                    'Customer Name' => 'John Doe',
                    'Phone Primary' => '0550123456',
                    'Wilaya' => 'Alger',
                    'Commune' => 'Alger Center',
                ],
            ]
        ];

        $stats = $importer->importRows($rows);

        $this->assertEquals(1, $stats['created']);
        $this->assertDatabaseHas('orders', [
            'sid' => $this->store->id,
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'vid' => $this->variant->id,
        ]);
    }

    /** @test */
    public function unmatched_sku_goes_to_review_queue()
    {
        $importer = new SheetOrderImportService($this->connection);

        $rows = [
            [
                'row_number' => 3,
                'data' => [
                    'SKU / Reference' => 'WRONG-SKU',
                    'Product Name' => 'Super Widget', // Name matches but SKU doesn't!
                    'Customer Name' => 'Jane Doe',
                    'Phone Primary' => '0770123456',
                    'Wilaya' => 'Alger',
                    'Commune' => 'Alger Center',
                ],
            ]
        ];

        $stats = $importer->importRows($rows);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['needs_review']);

        // Assert order was NOT created
        $this->assertDatabaseMissing('orders', [
            'sid' => $this->store->id,
        ]);

        // Assert log was created in review queue with suggestion hint
        $this->assertDatabaseHas('sheet_sync_logs', [
            'store_id' => $this->store->id,
            'status' => 'needs_review',
            'row_number' => 3,
        ]);
    }
}
