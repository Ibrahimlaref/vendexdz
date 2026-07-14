<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StoreIntegration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'platform',
        'name',
        'external_url',
        'webhook_uuid',
        'webhook_secret',
        'platform_meta',
        'status',
        'last_webhook_received_at',
        'created_by',
    ];

    protected $casts = [
        'platform_meta' => 'array',
        'last_webhook_received_at' => 'datetime',
        'webhook_secret' => 'encrypted',
    ];

    protected $hidden = [
        'webhook_secret',
    ];

    /**
     * Available platform definitions
     */
    public const PLATFORMS = [
        'shopify' => [
            'name' => 'Shopify',
            'logo' => 'https://cdn.simpleicons.org/shopify/96BF48',
            'color' => '#96BF48',
            'url_label' => 'URL du magasin Shopify',
            'url_placeholder' => 'votre-boutique.myshopify.com',
            'url_required' => true,
        ],
        'youcan' => [
            'name' => 'YouCan',
            'logo' => 'https://cdn.simpleicons.org/storefront/5C6BC0',
            'color' => '#5C6BC0',
            'url_label' => 'URL du magasin YouCan',
            'url_placeholder' => 'votre-boutique.youcan.shop',
            'url_required' => true,
        ],
        'woocommerce' => [
            'name' => 'WooCommerce',
            'logo' => 'https://cdn.simpleicons.org/woocommerce/7F54B3',
            'color' => '#7F54B3',
            'url_label' => 'URL du site WordPress',
            'url_placeholder' => 'www.votre-site.com',
            'url_required' => true,
        ],
        'ayor' => [
            'name' => 'Ayor',
            'logo' => 'https://cdn.simpleicons.org/awesomelists/FF6B35', // Ayor doesn't have simpleicons, using generic matching color
            'color' => '#FF6B35',
            'url_label' => 'URL du magasin Ayor',
            'url_placeholder' => 'votre-boutique.ayor.ai',
            'url_required' => false,
        ],
        'lightfunnels' => [
            'name' => 'Lightfunnels',
            'logo' => 'https://cdn.simpleicons.org/lightning/3B82F6',
            'color' => '#3B82F6',
            'url_label' => 'URL Lightfunnels',
            'url_placeholder' => 'votre-boutique.lightfunnels.com',
            'url_required' => false,
        ],
        'foorweb' => [
            'name' => 'Foorweb',
            'logo' => 'https://cdn.simpleicons.org/webflow/10B981',
            'color' => '#10B981',
            'url_label' => 'URL du magasin Foorweb',
            'url_placeholder' => 'votre-boutique.foorweb.com',
            'url_required' => false,
        ],
        'feeef' => [
            'name' => 'Feeef',
            'logo' => 'https://cdn.simpleicons.org/foursquare/F59E0B',
            'color' => '#F59E0B',
            'url_label' => 'URL du magasin Feeef',
            'url_placeholder' => 'votre-boutique.feeef.com',
            'url_required' => false,
        ],
        'tassyir' => [
            'name' => 'Tassyir',
            'logo' => 'https://cdn.simpleicons.org/trello/8B5CF6',
            'color' => '#8B5CF6',
            'url_label' => 'URL du magasin Tassyir',
            'url_placeholder' => 'votre-boutique.tassyir.com',
            'url_required' => false,
        ],
        'justsell' => [
            'name' => 'JustSell',
            'logo' => 'https://cdn.simpleicons.org/jcb/EC4899',
            'color' => '#EC4899',
            'url_label' => 'URL du magasin JustSell',
            'url_placeholder' => 'votre-boutique.justsell.com',
            'url_required' => false,
        ],
        'okwin' => [
            'name' => 'Okwin',
            'logo' => 'https://cdn.simpleicons.org/octopusdeploy/14B8A6',
            'color' => '#14B8A6',
            'url_label' => 'URL du magasin Okwin',
            'url_placeholder' => 'votre-boutique.okwin.com',
            'url_required' => false,
        ],
        'google_sheets' => [
            'name' => 'Google Sheets',
            'logo' => 'https://cdn.simpleicons.org/googlesheets/34A853',
            'color' => '#34A853',
            'url_label' => 'ID du Google Sheet',
            'url_placeholder' => 'ID ou URL du spreadsheet',
            'url_required' => false,
        ],
        'ziyendarek' => [
            'name' => 'ZiyenDarek',
            'logo' => 'https://cdn.simpleicons.org/zillow/EF4444',
            'color' => '#EF4444',
            'url_label' => 'URL du magasin ZiyenDarek',
            'url_placeholder' => 'votre-boutique.ziyendarek.com',
            'url_required' => false,
        ],
    ];

    public const STATUS_PENDING = 'pending_webhook_setup';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_ERROR = 'error';
    public const STATUS_DISCONNECTED = 'disconnected';

    /**
     * Boot: auto-generate webhook_uuid on creation
     */
    protected static function booted(): void
    {
        static::creating(function (StoreIntegration $integration) {
            if (empty($integration->webhook_uuid)) {
                $integration->webhook_uuid = (string) Str::uuid();
            }
            if (empty($integration->webhook_secret)) {
                $integration->webhook_secret = Str::random(64);
            }
            if (empty($integration->status)) {
                $integration->status = self::STATUS_PENDING;
            }
        });
    }

    // --- Relationships ---

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function webhookLogs()
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // --- Accessors ---

    public function getWebhookUrlAttribute(): string
    {
        return url("/api/webhooks/{$this->platform}/{$this->webhook_uuid}");
    }

    public function getPlatformConfigAttribute(): array
    {
        return self::PLATFORMS[$this->platform] ?? [
            'name' => ucfirst($this->platform),
            'icon' => 'ri-links-line',
            'color' => '#6B7280',
            'url_label' => 'URL',
            'url_placeholder' => '',
            'url_required' => false,
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En attente de configuration',
            self::STATUS_CONNECTED => 'Connecté',
            self::STATUS_ERROR => 'Erreur',
            self::STATUS_DISCONNECTED => 'Déconnecté',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_CONNECTED => 'green',
            self::STATUS_ERROR => 'red',
            self::STATUS_DISCONNECTED => 'gray',
            default => 'gray',
        };
    }

    // --- Methods ---

    public function markConnected(): void
    {
        $this->update([
            'status' => self::STATUS_CONNECTED,
            'last_webhook_received_at' => now(),
        ]);
    }

    public function markError(): void
    {
        $this->update(['status' => self::STATUS_ERROR]);
    }

    public function disconnect(): void
    {
        $this->update(['status' => self::STATUS_DISCONNECTED]);
        $this->delete(); // Soft delete
    }

    public function regenerateSecret(): string
    {
        $newSecret = Str::random(64);
        $this->update(['webhook_secret' => $newSecret]);
        return $newSecret;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONNECTED]);
    }
}
