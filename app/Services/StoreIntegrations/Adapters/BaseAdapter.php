<?php

namespace App\Services\StoreIntegrations\Adapters;

use App\Services\StoreIntegrations\PlatformAdapterInterface;
use Illuminate\Http\Request;

/**
 * Base adapter with shared helpers for wilaya mapping, address formatting, etc.
 * Ported from the monolithic OrderWebhookController.
 */
abstract class BaseAdapter implements PlatformAdapterInterface
{
    /**
     * Algeria wilaya code map (used across multiple platforms).
     */
    protected static array $wilayaMap = [
        'Adrar' => 1, 'Chlef' => 2, 'Laghouat' => 3, 'Oum El Bouaghi' => 4,
        'Batna' => 5, 'Béjaïa' => 6, 'Biskra' => 7, 'Béchar' => 8,
        'Blida' => 9, 'Bouira' => 10, 'Tamanrasset' => 11, 'Tébessa' => 12,
        'Tlemcen' => 13, 'Tiaret' => 14, 'Tizi Ouzou' => 15, 'Alger' => 16,
        'Djelfa' => 17, 'Jijel' => 18, 'Sétif' => 19, 'Saïda' => 20,
        'Skikda' => 21, 'Sidi Bel Abbès' => 22, 'Annaba' => 23, 'Guelma' => 24,
        'Constantine' => 25, 'Médéa' => 26, 'Mostaganem' => 27, 'M\'Sila' => 28,
        'Mascara' => 29, 'Ouargla' => 30, 'Oran' => 31, 'El Bayadh' => 32,
        'Illizi' => 33, 'Bordj Bou Arreridj' => 34, 'Boumerdès' => 35,
        'El Tarf' => 36, 'Tindouf' => 37, 'Tissemsilt' => 38, 'El Oued' => 39,
        'Khenchela' => 40, 'Souk Ahras' => 41, 'Tipaza' => 42, 'Mila' => 43,
        'Aïn Defla' => 44, 'Naâma' => 45, 'Aïn Témouchent' => 46,
        'Ghardaïa' => 47, 'Relizane' => 48, 'Timimoun' => 49,
        'Bordj Badji Mokhtar' => 50, 'Ouled Djellal' => 51, 'Béni Abbès' => 52,
        'In Salah' => 53, 'In Guezzam' => 54, 'Touggourt' => 55, 'Djanet' => 56,
        'El M\'Ghair' => 57, 'El Meniaa' => 58,
    ];

    /**
     * Map a province/state name to an Algeria wilaya code.
     */
    protected function mapWilaya(?string $province): ?int
    {
        if (!$province) return 16; // Default: Alger

        // Try direct numeric value
        if (is_numeric($province)) {
            return (int) $province;
        }

        $province = trim($province);

        // Exact match
        foreach (self::$wilayaMap as $name => $code) {
            if (mb_strtolower($name) === mb_strtolower($province)) {
                return $code;
            }
        }

        // Partial match
        $search = mb_strtolower($province);
        foreach (self::$wilayaMap as $name => $code) {
            if (str_contains($search, mb_strtolower($name))) {
                return $code;
            }
        }

        // Try extracting a number from the string
        preg_match('/\d+/', $province, $matches);
        if (isset($matches[0])) {
            $num = (int) $matches[0];
            if ($num >= 1 && $num <= 58) {
                return $num;
            }
        }

        return 16; // Default: Alger
    }

    /**
     * Exchange rate conversion (DZD base).
     */
    protected function getExchangeRate(string $currency): float
    {
        $rates = [
            'USD' => 135.50, 'EUR' => 147.20, 'GBP' => 170.80, 'DZD' => 1.00,
        ];
        return $rates[$currency] ?? 135.50;
    }

    /**
     * Default: platforms without signature verification pass through.
     */
    public function verifySignature(Request $request, string $secret): bool
    {
        // Override in platform adapters that support HMAC
        return true;
    }

    public function getRequiredFields(): array
    {
        return [
            'name' => ['label' => 'Nom du magasin', 'type' => 'text', 'required' => true],
            'external_url' => ['label' => 'URL du magasin', 'type' => 'url', 'required' => false],
        ];
    }
}
