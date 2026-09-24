<?php

namespace Modules\Sales\Services;

use Modules\Business\Models\Business;
use Modules\DesignStudio\Models\Design;

class InvoiceAppearanceService
{
    public const KEY_TEMPLATE = 'sales_invoice.template';

    public const KEY_ACCENT_COLOR = 'sales_invoice.accent_color';

    public const KEY_PAPER_SIZE = 'sales_invoice.paper_size';

    public const KEY_ORIENTATION = 'sales_invoice.orientation';

    public const KEY_MARGIN_TOP = 'sales_invoice.margin_top';

    public const KEY_MARGIN_BOTTOM = 'sales_invoice.margin_bottom';

    public const KEY_MARGIN_LEFT = 'sales_invoice.margin_left';

    public const KEY_MARGIN_RIGHT = 'sales_invoice.margin_right';

    public const KEY_HEADER_LAYOUT = 'sales_invoice.header_layout';

    /** @var array<string, array{name: string, desc: string, accent: string}> */
    public const TEMPLATES = [
        'classic'   => ['name' => 'Classic',     'desc' => 'Traditional bordered table with letterhead', 'accent' => '#1d4ed8'],
        'bold'      => ['name' => 'Bold Banner', 'desc' => 'Full-width colour header, large number',      'accent' => '#e11d48'],
        'minimal'   => ['name' => 'Minimal',     'desc' => 'Pure typography, no fills or colour blocks',  'accent' => '#374151'],
        'compact'   => ['name' => 'Compact',     'desc' => 'Card-style info grid with teal accent',       'accent' => '#0891b2'],
        'executive' => ['name' => 'Executive',   'desc' => 'Dark luxury header with gold accent trim',    'accent' => '#c7a84f'],
    ];

    /** @var list<string> */
    public const COLOR_PRESETS = [
        '#1d4ed8', '#e11d48', '#0891b2', '#059669', '#7c3aed',
        '#c7a84f', '#ea580c', '#0f172a', '#374151', '#db2777',
    ];

    /** @var array<string, array{0: int, 1: int}> paper size in mm: [width, height] */
    private const PAPER_MM = [
        'a4'     => [210, 297],
        'a5'     => [148, 210],
        'letter' => [216, 279],
        'legal'  => [216, 356],
    ];

    private const MM_PX = 96 / 25.4;

    public function forBusiness(Business $business): array
    {
        $template = (string) ($business->getSetting(self::KEY_TEMPLATE, 'classic') ?: 'classic');
        if (! array_key_exists($template, self::TEMPLATES)) {
            $template = 'classic';
        }

        $paper = (string) ($business->getSetting(self::KEY_PAPER_SIZE, 'a4') ?: 'a4');
        if (! array_key_exists($paper, self::PAPER_MM)) {
            $paper = 'a4';
        }

        $orientation = (string) ($business->getSetting(self::KEY_ORIENTATION, 'portrait') ?: 'portrait');
        if (! in_array($orientation, ['portrait', 'landscape'], true)) {
            $orientation = 'portrait';
        }

        $headerLayout = (string) ($business->getSetting(self::KEY_HEADER_LAYOUT, 'num-left') ?: 'num-left');
        if (! in_array($headerLayout, ['num-left', 'num-right', 'num-center'], true)) {
            $headerLayout = 'num-left';
        }

        return [
            'template'      => $template,
            'accent_color'  => (string) ($business->getSetting(self::KEY_ACCENT_COLOR, '') ?: ''),
            'paper_size'    => $paper,
            'orientation'   => $orientation,
            'margin_top'    => (int) ($business->getSetting(self::KEY_MARGIN_TOP, 20) ?? 20),
            'margin_bottom' => (int) ($business->getSetting(self::KEY_MARGIN_BOTTOM, 20) ?? 20),
            'margin_left'   => (int) ($business->getSetting(self::KEY_MARGIN_LEFT, 15) ?? 15),
            'margin_right'  => (int) ($business->getSetting(self::KEY_MARGIN_RIGHT, 25) ?? 25),
            'header_layout' => $headerLayout,
        ];
    }

    public function saveForBusiness(Business $business, array $data): void
    {
        if (array_key_exists('template', $data)) {
            $template = (string) ($data['template'] ?? 'classic');
            $business->setSetting(self::KEY_TEMPLATE, array_key_exists($template, self::TEMPLATES) ? $template : 'classic');
        }

        if (array_key_exists('accent_color', $data)) {
            $color = trim((string) ($data['accent_color'] ?? ''));
            $business->setSetting(self::KEY_ACCENT_COLOR, preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : null);
        }

        if (array_key_exists('paper_size', $data)) {
            $paper = strtolower(trim((string) ($data['paper_size'] ?? 'a4')));
            $business->setSetting(self::KEY_PAPER_SIZE, array_key_exists($paper, self::PAPER_MM) ? $paper : 'a4');
        }

        if (array_key_exists('orientation', $data)) {
            $orientation = strtolower(trim((string) ($data['orientation'] ?? 'portrait')));
            $business->setSetting(self::KEY_ORIENTATION, in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait');
        }

        $marginFields = [
            'margin_top'    => self::KEY_MARGIN_TOP,
            'margin_bottom' => self::KEY_MARGIN_BOTTOM,
            'margin_left'   => self::KEY_MARGIN_LEFT,
            'margin_right'  => self::KEY_MARGIN_RIGHT,
        ];
        foreach ($marginFields as $field => $key) {
            if (array_key_exists($field, $data)) {
                $business->setSetting($key, max(0, min(80, (int) ($data[$field] ?? 0))));
            }
        }

        if (array_key_exists('header_layout', $data)) {
            $layout = strtolower(trim((string) ($data['header_layout'] ?? 'num-left')));
            $business->setSetting(self::KEY_HEADER_LAYOUT, in_array($layout, ['num-left', 'num-right', 'num-center'], true) ? $layout : 'num-left');
        }
    }

    /**
     * Resolve the effective accent color: an explicit override wins, then a
     * color derived from the business's letterhead design, then the
     * template's own default.
     */
    public function resolveAccent(array $settings, ?string $letterheadAccent = null): string
    {
        if (filled($settings['accent_color'] ?? null)) {
            return $settings['accent_color'];
        }
        if (filled($letterheadAccent)) {
            return $letterheadAccent;
        }

        return self::TEMPLATES[$settings['template']]['accent'] ?? '#1d4ed8';
    }

    /**
     * @return array{w_mm: int, h_mm: int, w_px: int, h_px: int, min_height_css: string}
     */
    public function geometry(array $settings): array
    {
        [$wMm, $hMm] = self::PAPER_MM[$settings['paper_size']] ?? self::PAPER_MM['a4'];
        if (($settings['orientation'] ?? 'portrait') === 'landscape') {
            [$wMm, $hMm] = [$hMm, $wMm];
        }
        $wPx = (int) round($wMm * self::MM_PX);
        $hPx = (int) round($hMm * self::MM_PX);

        return [
            'w_mm'           => $wMm,
            'h_mm'           => $hMm,
            'w_px'           => $wPx,
            'h_px'           => $hPx,
            'min_height_css' => "min-height:{$hPx}px;",
        ];
    }

    /**
     * Shared header-layout override, applied uniformly to every template's
     * two-column header container (business block + invoice-number block).
     */
    public function headerLayoutCss(string $headerLayout): string
    {
        $sel = '.top,.banner,.hdr-top,.topbar';

        return match ($headerLayout) {
            'num-left'   => "{$sel}{flex-direction:row-reverse}",
            'num-center' => "{$sel}{justify-content:center;gap:48px}",
            default      => '',
        };
    }

    /** @page rule so the actual print output matches the configured paper size. */
    public function pageAtCss(array $geometry): string
    {
        return '@page{size:'.$geometry['w_mm'].'mm '.$geometry['h_mm'].'mm;margin:0}';
    }

    /** Whether the `sales_invoice` doc type is currently enabled in `design_studio.lh_links`. */
    public function letterheadEnabledForInvoices(Business $business): bool
    {
        $lhLinks = (array) get_settings('design_studio.lh_links', ['po', 'grn', 'hr_payslip', 'hr_salary_sheet', 'sales_quotation', 'sales_invoice'], $business);

        return in_array('sales_invoice', $lhLinks, true);
    }

    /** Adds or removes the `sales_invoice` entry from `design_studio.lh_links`, leaving other doc types untouched. */
    public function setLetterheadEnabledForInvoices(Business $business, bool $enabled): void
    {
        $lhLinks = (array) get_settings('design_studio.lh_links', ['po', 'grn', 'hr_payslip', 'hr_salary_sheet', 'sales_quotation', 'sales_invoice'], $business);
        $lhLinks = array_values(array_filter($lhLinks, static fn ($link) => $link !== 'sales_invoice'));
        if ($enabled) {
            $lhLinks[] = 'sales_invoice';
        }
        $business->setSetting('design_studio.lh_links', $lhLinks);
    }

    /**
     * Looks up the business's letterhead design (if the `sales_invoice` doc
     * type is enabled) and returns its raw canvas JSON (for client-side
     * Fabric.js rendering — see `sales::partials.print-letterhead-script`)
     * plus an accent color derived from a thin colored rect near its top,
     * if any. Pass `$enabledOverride` to check an unsaved on/off state (the
     * Invoice Setup live preview) instead of the saved `design_studio.lh_links`
     * setting.
     *
     * @return array{accent: ?string, canvasJson: ?string}
     */
    public function resolveLetterheadForInvoices(Business $business, ?bool $enabledOverride = null): array
    {
        $enabled = $enabledOverride ?? $this->letterheadEnabledForInvoices($business);
        if (! $enabled) {
            return ['accent' => null, 'canvasJson' => null];
        }

        $letterhead = Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'letterhead')
            ->latest('updated_at')
            ->first();

        if (! $letterhead || blank($letterhead->canvas_json)) {
            return ['accent' => null, 'canvasJson' => null];
        }

        $accent = null;

        try {
            $decoded = json_decode((string) $letterhead->canvas_json, true);
            $objs = [];
            if (is_array($decoded)) {
                if (isset($decoded['objects'])) {
                    $objs = $decoded['objects'];
                } elseif (! empty($decoded[0]['json'])) {
                    $objs = json_decode((string) $decoded[0]['json'], true)['objects'] ?? [];
                }
            }
            foreach ($objs as $obj) {
                if (($obj['type'] ?? '') === 'rect'
                    && (float) ($obj['top'] ?? 99) < 4
                    && (float) ($obj['height'] ?? 99) <= 8
                    && ! empty($obj['fill'])
                    && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $obj['fill'])) {
                    $accent = $obj['fill'];
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return ['accent' => $accent, 'canvasJson' => (string) $letterhead->canvas_json];
    }
}
