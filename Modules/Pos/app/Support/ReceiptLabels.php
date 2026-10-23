<?php

namespace Modules\Pos\Support;

// Printed-receipt labels per print language (POS setting `receipt_language`).
// Mirrors RECEIPT_STRINGS in electron_app_pos_lite/renderer/js/navbar.js so a
// web-printed bill matches a POS Lite one. English text is the key, so English
// needs no table; business text the user typed (header/footer/address) is never
// translated.
final class ReceiptLabels
{
    public const LANGUAGES = [
        'en' => 'English',
        'si' => 'සිංහල',
        'ta' => 'தமிழ்',
    ];

    public const STRINGS = [
        'si' => [
            'Receipt #' => 'රිසිට් #', 'Date & Time' => 'දිනය සහ වේලාව', 'Payment' => 'ගෙවීම',
            'Customer' => 'පාරිභෝගිකයා', 'Cashier' => 'මුදල් අයකැමි', 'Walk-in Customer' => 'සාමාන්‍ය පාරිභෝගිකයා',
            'Item' => 'අයිතමය', 'Qty' => 'ගණන', 'Price' => 'මිල', 'Disc' => 'වට්.', 'Amount' => 'එකතුව',
            'Subtotal' => 'එකතුව', 'Discount' => 'ඔබට ලැබුණු වාසිය', 'Coupon' => 'කූපනය', 'Total' => 'මුළු එකතුව',
            'Gift card' => 'තෑගි කාඩ්පත', 'Gift card balance' => 'තෑගි කාඩ්පත් ශේෂය',
            'Cash Received' => 'ලැබුණු මුදල', 'Paid' => 'ගෙවූ මුදල', 'Change' => 'ඉතිරි මුදල', 'Notes' => 'සටහන්',
            'Thank you for your purchase!' => 'ඔබේ මිලදී ගැනීමට ස්තුතියි!',
            'Lifetime warranty' => 'ජීවිත කාල වගකීම', 'Warranty until {date}' => '{date} දක්වා වගකීම', 'Warranty' => 'වගකීම',
            'Return {date}' => 'ආපසු {date}', 'Daily {rate}' => 'දෛනික {rate}', 'Late {mult}× rate/day' => 'ප්‍රමාද {mult}× ගාස්තුව/දිනට',
            'Cash' => 'මුදල්', 'Card' => 'කාඩ්පත', 'Credit' => 'ණය', 'Bank Transfer' => 'බැංකු මාරුව',
        ],
        'ta' => [
            'Receipt #' => 'ரசீது #', 'Date & Time' => 'தேதி & நேரம்', 'Payment' => 'கட்டணம்',
            'Customer' => 'வாடிக்கையாளர்', 'Cashier' => 'காசாளர்', 'Walk-in Customer' => 'பொது வாடிக்கையாளர்',
            'Item' => 'பொருள்', 'Qty' => 'எண்', 'Price' => 'விலை', 'Disc' => 'தள்.', 'Amount' => 'தொகை',
            'Subtotal' => 'கூட்டுத்தொகை', 'Discount' => 'தள்ளுபடி', 'Coupon' => 'கூப்பன்', 'Total' => 'மொத்தம்',
            'Gift card' => 'பரிசு அட்டை', 'Gift card balance' => 'பரிசு அட்டை மீதி',
            'Cash Received' => 'பெற்ற தொகை', 'Paid' => 'செலுத்தியது', 'Change' => 'மீதி', 'Notes' => 'குறிப்புகள்',
            'Thank you for your purchase!' => 'உங்கள் கொள்முதலுக்கு நன்றி!',
            'Lifetime warranty' => 'வாழ்நாள் உத்தரவாதம்', 'Warranty until {date}' => '{date} வரை உத்தரவாதம்', 'Warranty' => 'உத்தரவாதம்',
            'Return {date}' => 'திருப்பித் தருதல் {date}', 'Daily {rate}' => 'நாளொன்றுக்கு {rate}', 'Late {mult}× rate/day' => 'தாமதம் {mult}× கட்டணம்/நாள்',
            'Cash' => 'ரொக்கம்', 'Card' => 'அட்டை', 'Credit' => 'கடன்', 'Bank Transfer' => 'வங்கி பரிமாற்றம்',
        ],
    ];

    /** @return array<string,string> label table for a language ('en' → empty, labels print as given) */
    public static function for(?string $lang): array
    {
        return self::STRINGS[$lang] ?? [];
    }

    /** t('si', 'Warranty until {date}', ['date' => '…']) — unknown keys print as given, like Lite's receiptLabels(). */
    public static function t(?string $lang, string $key, array $params = []): string
    {
        $s = self::for($lang)[$key] ?? $key;

        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $params) ? (string) $params[$m[1]] : $m[0], $s);
    }
}
