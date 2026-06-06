<?php

namespace App\Helpers;

use App\Models\Setting;

class WhatsAppHelper
{
    public static function getLink(?string $phone, string $message): string
    {
        if (empty($phone)) {
            return '#';
        }

        // Clean phone number
        $phone = $phone ? preg_replace('/[^0-9]/', '', $phone) : '';
        
        // Ensure international format (assuming ID 62 for now if starts with 0)
        if (!empty($phone) && str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        // WhatsApp Click to Chat format: https://wa.me/<number>?text=<urlencodedtext>
        $encodedMessage = urlencode($message);
        
        if (empty($phone)) {
            return "https://wa.me/?text={$encodedMessage}";
        }
        
        return "https://wa.me/{$phone}?text={$encodedMessage}";
    }

    /**
     * Build the search/replace pairs for rental pickup & return date/time placeholders.
     *
     * Returns [$placeholders, $values] ready to be merged into a str_replace() call.
     * Placeholders: [rental-range], [pickup-date], [return-date], [pickup-time], [return-time].
     */
    public static function rentalDatePlaceholders(\App\Models\Rental $rental): array
    {
        $start = $rental->start_date ? $rental->start_date->copy()->locale('id') : null;
        $end   = $rental->end_date ? $rental->end_date->copy()->locale('id') : null;

        $pickupDate = $start ? $start->translatedFormat('j F Y') : '-';
        $returnDate = $end ? $end->translatedFormat('j F Y') : '-';
        $pickupTime = $start ? $start->translatedFormat('H:i') : '-';
        $returnTime = $end ? $end->translatedFormat('H:i') : '-';
        $range      = ($start && $end) ? "{$pickupDate} - {$returnDate}" : '-';

        return [
            ['[rental-range]', '[pickup-date]', '[return-date]', '[pickup-time]', '[return-time]'],
            [$range, $pickupDate, $returnDate, $pickupTime, $returnTime],
        ];
    }

    public static function parseTemplate(string $templateKey, array $data): string
    {
        // Get template from settings, default to empty string
        $template = Setting::get($templateKey, '');
        
        // Replace placeholders
        foreach ($data as $key => $value) {
            $template = str_replace("[{$key}]", $value, $template);
        }
        
        return $template;
    }
}
