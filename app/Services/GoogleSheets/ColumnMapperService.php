<?php

namespace App\Services\GoogleSheets;

/**
 * Maps Google Sheet column headers to system fields using an alias dictionary.
 * Headers are matched by normalized text (lowercase, accents stripped, trimmed),
 * never by column position — so reordering columns is harmless.
 */
class ColumnMapperService
{
    /**
     * Alias dictionary: system_field => [list of recognized header aliases].
     * When multiple phone columns exist, the first maps to phone_primary,
     * the second to phone_secondary.
     */
    private const FIELD_ALIASES = [
        'customer_name' => [
            'nom', 'nom client', 'nom et prenom', 'client', 'name',
            'الاسم', 'اسم العميل', 'nom complet', 'full name', 'nom & prenom',
        ],
        'phone_primary' => [
            'telephone', 'tel', 'numero', 'n telephone', 'phone',
            'telephone 1', 'tel 1', 'phone 1', 'رقم الهاتف', 'الهاتف',
        ],
        'phone_secondary' => [
            'telephone 2', 'tel 2', 'phone 2', 'الهاتف 2', 'رقم الهاتف 2',
        ],
        'wilaya' => [
            'wilaya', 'etat', 'province', 'الولاية', 'ولاية',
        ],
        'commune' => [
            'commune', 'baladia', 'ville', 'city', 'البلدية', 'المدينة',
        ],
        'address' => [
            'adresse', 'address', 'العنوان',
        ],
        'product' => [
            'produit', 'article', 'product', 'المنتج',
        ],
        'sku' => [
            'sku', 'reference', 'ref', 'المرجع', 'رمز المنتج',
        ],
        'quantity' => [
            'quantite', 'qte', 'qty', 'quantity', 'الكمية',
        ],
        'price' => [
            'prix', 'montant', 'total', 'price', 'السعر', 'المبلغ',
        ],
        'delivery_type' => [
            'type de livraison', 'livraison', 'stop desk', 'domicile',
            'نوع التوصيل', 'type livraison',
        ],
        'order_reference' => [
            'reference', 'id', 'n commande', 'order id', 'ref commande',
            'المرجع', 'رقم الطلب',
        ],
        'note' => [
            'remarque', 'note', 'commentaire', 'comment', 'ملاحظة',
        ],
    ];

    /**
     * Strip accents and normalize a header string for matching.
     */
    public function normalizeHeader(string $header): string
    {
        // Trim whitespace
        $header = trim($header);

        // Strip accents using transliterator or manual mapping
        $header = $this->stripAccents($header);

        // Lowercase
        $header = mb_strtolower($header, 'UTF-8');

        // Remove special characters except spaces and alphanumeric
        $header = preg_replace('/[^\p{L}\p{N}\s]/u', '', $header);

        // Collapse multiple spaces
        $header = preg_replace('/\s+/', ' ', $header);

        return trim($header);
    }

    /**
     * Auto-map sheet headers to system fields.
     * Returns [header_text => ['field' => system_field, 'confidence' => 'high'|'medium'|'none']].
     */
    public function autoMap(array $headers): array
    {
        $mapping = [];
        $usedFields = [];
        $phoneCount = 0;

        foreach ($headers as $header) {
            $normalized = $this->normalizeHeader($header);
            $bestMatch = null;
            $bestConfidence = 'none';

            foreach (self::FIELD_ALIASES as $field => $aliases) {
                // Skip fields already used (except phone which has two slots)
                if (isset($usedFields[$field]) && !in_array($field, ['phone_primary', 'phone_secondary'])) {
                    continue;
                }

                foreach ($aliases as $alias) {
                    $normalizedAlias = $this->normalizeHeader($alias);

                    if ($normalized === $normalizedAlias) {
                        $bestMatch = $field;
                        $bestConfidence = 'high';
                        break 2;
                    }

                    // Partial match: header contains the alias or vice versa
                    if (str_contains($normalized, $normalizedAlias) || str_contains($normalizedAlias, $normalized)) {
                        if ($bestConfidence !== 'high') {
                            $bestMatch = $field;
                            $bestConfidence = 'medium';
                        }
                    }
                }
            }

            // Handle phone columns: first unrecognized "phone-like" header → phone_primary, second → phone_secondary
            if ($bestMatch === 'phone_primary' || $bestMatch === 'phone_secondary') {
                $phoneCount++;
                if ($phoneCount === 1) {
                    $bestMatch = 'phone_primary';
                } elseif ($phoneCount === 2) {
                    $bestMatch = 'phone_secondary';
                } else {
                    $bestMatch = null;
                    $bestConfidence = 'none';
                }
            }

            // Handle ambiguity: "reference" matches both 'sku' and 'order_reference'
            // Prefer 'order_reference' if 'sku' is already mapped, and vice versa
            if ($bestMatch === 'sku' && isset($usedFields['sku'])) {
                $bestMatch = 'order_reference';
            } elseif ($bestMatch === 'order_reference' && isset($usedFields['order_reference'])) {
                $bestMatch = 'sku';
            }

            $mapping[$header] = [
                'field' => $bestMatch,
                'confidence' => $bestConfidence,
            ];

            if ($bestMatch) {
                $usedFields[$bestMatch] = true;
            }
        }

        return $mapping;
    }

    /**
     * Check which headers from the sheet don't match any confirmed mapping.
     * Returns headers that need user confirmation.
     */
    public function getUnmappedHeaders(array $headers, array $confirmedMapping): array
    {
        $unmapped = [];

        foreach ($headers as $header) {
            if (!isset($confirmedMapping[$header]) || $confirmedMapping[$header] === null) {
                $unmapped[] = $header;
            }
        }

        return $unmapped;
    }

    /**
     * Check if any required field is missing from the mapping.
     */
    public function getMissingRequiredFields(array $confirmedMapping): array
    {
        $required = ['customer_name', 'phone_primary', 'wilaya', 'commune'];
        $mappedFields = array_filter(array_values($confirmedMapping));

        return array_diff($required, $mappedFields);
    }

    /**
     * Given confirmed mapping + headers + a row, produce a normalized associative array.
     */
    public function applyMapping(array $confirmedMapping, array $rowData): array
    {
        $result = [];

        foreach ($confirmedMapping as $headerText => $systemField) {
            if ($systemField && isset($rowData[$headerText])) {
                $result[$systemField] = trim((string) $rowData[$headerText]);
            }
        }

        return $result;
    }

    /**
     * Get all available system fields for the mapping dropdown.
     */
    public function getSystemFields(): array
    {
        return [
            'customer_name' => 'Customer Name',
            'phone_primary' => 'Phone (Primary)',
            'phone_secondary' => 'Phone (Secondary)',
            'wilaya' => 'Wilaya',
            'commune' => 'Commune',
            'address' => 'Address',
            'product' => 'Product Name',
            'sku' => 'SKU / Reference',
            'quantity' => 'Quantity',
            'price' => 'Price / Total',
            'delivery_type' => 'Delivery Type',
            'order_reference' => 'Order Reference / ID',
            'note' => 'Note / Comment',
        ];
    }

    /**
     * Strip accents from a string.
     */
    private function stripAccents(string $string): string
    {
        $transliteration = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'Ñ' => 'N', 'ý' => 'y', 'ÿ' => 'y',
            '°' => '', '²' => '2', '³' => '3',
        ];

        return strtr($string, $transliteration);
    }
}
