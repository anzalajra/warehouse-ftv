<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Shared helpers for the custom-fields feature (Product & Rental).
 *
 * Field definitions are stored as JSON in a Setting key (mirroring
 * `registration_custom_fields` for customers). Each definition is:
 *   { label, name, type, options, required }
 * where `type` ∈ text|number|email|textarea|select|radio|checkbox and
 * `options` is a comma-separated string used by select/radio.
 *
 * Per-record values live in the model's `custom_fields` JSON column (cast array),
 * keyed by the field `name`.
 */
class CustomFields
{
    /** Definition arrays for a given Setting key (product_custom_fields / rental_custom_fields). */
    public static function definitions(string $settingKey): array
    {
        $decoded = json_decode(Setting::get($settingKey, '[]'), true);

        if (! is_array($decoded)) {
            return [];
        }

        // Only keep well-formed rows that have a usable field key.
        return array_values(array_filter($decoded, fn ($f) => is_array($f) && ! empty($f['name'])));
    }

    /**
     * Normalize a field's `options` into an ordered [value => label] map.
     * Accepts either a comma-separated string or an array of scalars / {label,value}.
     */
    public static function parseOptions($options): array
    {
        if (is_string($options)) {
            $options = explode(',', $options);
        }

        if (! is_array($options)) {
            return [];
        }

        $map = [];
        foreach ($options as $opt) {
            if (is_array($opt)) {
                $value = $opt['value'] ?? $opt['label'] ?? null;
                $label = $opt['label'] ?? $opt['value'] ?? null;
            } else {
                $value = $label = trim((string) $opt);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $map[$value] = $label;
        }

        return $map;
    }

    /**
     * Build a definition-key => display-value list for a saved record, skipping
     * empty values. Used for read-only display (ViewRental, PDFs, etc.).
     */
    public static function displayValues(string $settingKey, ?array $values): array
    {
        $values = $values ?: [];
        $rows = [];

        foreach (self::definitions($settingKey) as $field) {
            $name = $field['name'];
            $value = $values[$name] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (($field['type'] ?? null) === 'checkbox') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Ya' : 'Tidak';
            } elseif (in_array($field['type'] ?? null, ['select', 'radio'], true)) {
                $value = self::parseOptions($field['options'] ?? '')[$value] ?? $value;
            }

            $rows[] = ['label' => $field['label'] ?? $name, 'value' => $value];
        }

        return $rows;
    }
}
