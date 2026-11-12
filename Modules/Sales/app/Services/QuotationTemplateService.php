<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Business\Models\Business;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationItem;

/**
 * The business's Quotation Setup (set in the desktop app) and its "Custom HTML" template.
 *
 * The desktop prints with the setup locally; the server keeps the same setup so the PDFs it
 * makes (CRM download, sending to the customer, approval mails) use the same layout. Custom
 * HTML uses the desktop's mustache-style placeholders:
 *   {{field}}  escaped (newlines → <br>)   {{{field}}}  raw
 *   {{#items}}…{{/items}} per line item    {{#field}}…{{/field}} / {{^field}}…{{/field}} if / unless
 */
class QuotationTemplateService
{
    public const KEY_SETUP = 'sales.quotation_setup';

    public const KEY_CUSTOM_HTML = 'sales.quotation_custom_html';

    /** Settings values are a TEXT column — keep the HTML well inside it. */
    public const MAX_HTML = 60000;

    public const TEMPLATES = ['classic', 'bold', 'minimal', 'compact', 'executive', 'custom'];

    public const PAPERS = ['a4' => [210, 297], 'a5' => [148, 210], 'letter' => [216, 279], 'legal' => [216, 356]];

    /** @return array<string, mixed> the setup in the desktop's quotation_* shape (without the prefix) */
    public function get(Business $business): array
    {
        $s = (array) ($business->getSetting(self::KEY_SETUP, []) ?: []);

        return [
            'template'    => in_array($s['template'] ?? null, self::TEMPLATES, true) ? $s['template'] : 'classic',
            'color'       => (string) ($s['color'] ?? ''),
            'paper'       => array_key_exists($s['paper'] ?? '', self::PAPERS) ? $s['paper'] : 'a4',
            'orientation' => ($s['orientation'] ?? '') === 'landscape' ? 'landscape' : 'portrait',
            'mg_top'      => (int) ($s['mg_top'] ?? 20),
            'mg_bot'      => (int) ($s['mg_bot'] ?? 20),
            'mg_left'     => (int) ($s['mg_left'] ?? 15),
            'mg_right'    => (int) ($s['mg_right'] ?? 15),
            'custom_html' => (string) ($business->getSetting(self::KEY_CUSTOM_HTML, '') ?: ''),
            'updated_at'  => $s['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data validated input */
    public function save(Business $business, array $data): array
    {
        $current = $this->get($business);
        $setup   = [];
        foreach (['template', 'paper', 'orientation', 'mg_top', 'mg_bot', 'mg_left', 'mg_right'] as $key) {
            $setup[$key] = isset($data[$key]) ? $data[$key] : $current[$key];
        }
        // An empty colour means "the template's own accent"
        $setup['color']      = array_key_exists('color', $data) ? (string) ($data['color'] ?? '') : $current['color'];
        $setup['updated_at'] = now()->toIso8601String();

        $business->setSetting(self::KEY_SETUP, $setup);
        if (array_key_exists('custom_html', $data)) {
            $business->setSetting(self::KEY_CUSTOM_HTML, (string) ($data['custom_html'] ?? ''));
        }

        return $this->get($business);
    }

    /** True when quotations print from the business's own HTML. */
    public function usesCustomHtml(Business $business): bool
    {
        $setup = $this->get($business);

        return $setup['template'] === 'custom' && trim($setup['custom_html']) !== '';
    }

    /** Paper for dompdf: [width mm, height mm, orientation]. */
    public function paper(Business $business): array
    {
        $setup = $this->get($business);
        [$w, $h] = self::PAPERS[$setup['paper']];

        return $setup['orientation'] === 'landscape' ? [$h, $w] : [$w, $h];
    }

    /** The quotation rendered through the custom HTML template — a full HTML document. */
    public function render(Business $business, Quotation $quotation): string
    {
        $setup = $this->get($business);
        $quotation->loadMissing(['customer', 'items.product']);

        $html = $this->inlineImages($business, $this->sanitize($setup['custom_html']));
        $out  = $this->renderNodes($this->parse($html), [$this->data($business, $quotation, $setup)]);

        // Paper size first. dompdf merges every @page rule (even inside @media), so the
        // zero margin is only a default for templates that set no page margins themselves.
        [$w, $h] = $this->paper($business);
        $pageCss = '<style>@page{size:' . $w . 'mm ' . $h . 'mm' . (str_contains($html, '@page') ? '' : ';margin:0') . '}</style>';
        if (preg_match('/<head\b[^>]*>/i', $out)) {
            return preg_replace('/<head\b[^>]*>/i', '$0' . $pageCss, $out, 1);
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $pageCss . '</head><body>' . $out . '</body></html>';
    }

    // ── Placeholder data — mirrors the desktop's _qtplData ──────────────────────

    /** @return array<string, mixed> */
    private function data(Business $business, Quotation $q, array $setup): array
    {
        $rates = [];
        $items = $q->items->values()->map(function (QuotationItem $it, int $i) use (&$rates) {
            $qty   = (float) $it->quantity;
            $unit  = (float) $it->unit_price;
            $gross = $qty * $unit;
            $disc  = (float) $it->discount_amount;
            $net   = max(0, $gross - $disc);
            $rate  = (float) $it->tax_rate;
            $tax   = $it->tax_amount !== null ? (float) $it->tax_amount : $net * $rate / 100;
            if ($rate > 0) {
                $rates[] = $this->trimNum($rate);
            }
            $lines  = preg_split('/\r?\n/', (string) ($it->description ?: $it->product?->name ?: ''));
            $qtyStr = fmod($qty, 1.0) == 0.0 ? (string) (int) $qty : number_format($qty, 2, '.', '');

            return [
                'no'           => $i + 1,
                'no_padded'    => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'name'         => $lines[0] ?? '',
                'details'      => trim(implode("\n", array_slice($lines, 1))),
                'description'  => (string) $it->description,
                'qty'          => $qtyStr,
                'qty_padded'   => fmod($qty, 1.0) == 0.0 ? str_pad($qtyStr, 2, '0', STR_PAD_LEFT) : $qtyStr,
                'unit_price'   => $this->num($unit),
                'gross'        => $this->num($gross),
                'discount'     => $this->num($disc),
                'has_discount' => $disc > 0.001,
                'net'          => $this->num($net),
                'unit_net'     => $this->num($qty ? $net / $qty : 0),
                'tax'          => $this->num($tax),
                'tax_rate'     => $rate > 0 ? $this->trimNum($rate) : '',
                'total'        => $this->num($net + $tax),
            ];
        })->all();

        // Most common line tax rate, for headings like "VAT (18%)"
        $taxRate = '';
        if ($rates) {
            $counts = array_count_values($rates);
            arsort($counts);
            $taxRate = (string) array_key_first($counts);
        }

        $disc = (float) $q->discount_amount;
        $tax  = (float) $q->tax_amount;
        [$wMm, $hMm] = $this->paper($business);

        return [
            'doc_title'        => 'Quotation',
            'doc_title_upper'  => 'QUOTATION',
            'is_quotation'     => true,
            'is_invoice'       => false,
            'number'           => (string) $q->quote_number,
            'date'             => $q->quote_date?->format('d/m/Y') ?? '',
            'valid_until'      => $q->expiry_date?->format('d/m/Y') ?? '',
            'due_date'         => $q->expiry_date?->format('d/m/Y') ?? '',
            'reference'        => (string) ($q->reference ?? ''),
            'status'           => method_exists($q, 'statusLabel') ? (string) $q->statusLabel() : (string) $q->status,
            'notes'            => (string) ($q->notes ?? ''),
            'customer_name'    => (string) ($q->customer?->name ?: 'Walk-in Customer'),
            'customer_address' => (string) ($q->customer?->address ?? ''),
            'customer_phone'   => (string) ($q->customer?->phone ?? ''),
            'customer_email'   => (string) ($q->customer?->email ?? ''),
            'business_name'    => (string) $business->name,
            'business_address' => (string) ($business->getSetting('pos.receipt_address', '') ?: ''),
            'logo_url'         => $this->logoSrc($business),
            'accent'           => $setup['color'] ?: '#1e3a8a',
            'currency'         => (string) (get_settings('business.currency', '', $business) ?: ''),
            'items'            => $items,
            'subtotal'         => $this->num($q->subtotal),
            'discount'         => $this->num($disc),
            'has_discount'     => $disc > 0.001,
            'tax_amount'       => $this->num($tax),
            'has_tax'          => $tax > 0.001,
            'total'            => $this->num($q->total),
            'tax_rate'         => $taxRate,
            'page_width_mm'    => $wMm,
            'page_height_mm'   => $hMm,
            'mg_top'           => $setup['mg_top'],
            'mg_bottom'        => $setup['mg_bot'],
            'mg_left'          => $setup['mg_left'],
            'mg_right'         => $setup['mg_right'],
        ];
    }

    private function num(mixed $n): string
    {
        return number_format((float) $n, 2, '.', ',');
    }

    private function trimNum(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    /** The business logo as a data URI — dompdf does not fetch remote files. */
    private function logoSrc(Business $business): string
    {
        $path = $business->logo_path ?: $this->publicPathFromUrl($business, (string) ($business->getSetting('business.logo_url', '') ?: ''));

        return $path ? ($this->dataUri($path) ?? '') : '';
    }

    // ── Template engine — same syntax and output as the desktop's _qtpl* ────────

    /** @return array<int, mixed> nodes: strings, or ['t' => var|raw|#|^, 'k' => key, 'c' => children] */
    private function parse(string $src): array
    {
        $root  = ['c' => []];
        $stack = [&$root];
        $last  = 0;
        preg_match_all('/\{\{\{\s*([\w.]+)\s*\}\}\}|\{\{\s*([#^\/]?)\s*([\w.]+)\s*\}\}/', $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $m) {
            $top = &$stack[count($stack) - 1];
            [$tag, $pos] = $m[0];
            if ($pos > $last) {
                $top['c'][] = substr($src, $last, $pos - $last);
            }
            $last = $pos + strlen($tag);

            if (isset($m[1]) && $m[1][1] !== -1 && $m[1][0] !== '') {
                $top['c'][] = ['t' => 'raw', 'k' => $m[1][0]];
            } elseif ($m[2][0] === '#' || $m[2][0] === '^') {
                $top['c'][] = ['t' => $m[2][0], 'k' => $m[3][0], 'c' => []];
                $stack[]    = &$top['c'][count($top['c']) - 1];
            } elseif ($m[2][0] === '/') {
                if (count($stack) > 1 && ($top['k'] ?? null) === $m[3][0]) {
                    array_pop($stack);
                }
            } else {
                $top['c'][] = ['t' => 'var', 'k' => $m[3][0]];
            }
            unset($top);
        }
        if ($last < strlen($src)) {
            $stack[count($stack) - 1]['c'][] = substr($src, $last);
        }

        return $root['c'];
    }

    private function lookup(array $ctxs, string $key): mixed
    {
        $parts = explode('.', $key);
        $head  = array_shift($parts);
        for ($i = count($ctxs) - 1; $i >= 0; $i--) {
            if (is_array($ctxs[$i]) && array_key_exists($head, $ctxs[$i])) {
                $v = $ctxs[$i][$head];
                foreach ($parts as $p) {
                    $v = is_array($v) ? ($v[$p] ?? null) : null;
                }

                return $v;
            }
        }

        return null;
    }

    private function renderNodes(array $nodes, array $ctxs): string
    {
        $out = '';
        foreach ($nodes as $n) {
            if (is_string($n)) {
                $out .= $n;
                continue;
            }
            $v = $this->lookup($ctxs, $n['k']);
            if ($n['t'] === 'var') {
                $out .= $v === null || $v === false ? '' : preg_replace('/\r?\n/', '<br>', htmlspecialchars(is_bool($v) ? '1' : (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                continue;
            }
            if ($n['t'] === 'raw') {
                $out .= is_scalar($v) ? (string) $v : '';
                continue;
            }
            $isList = is_array($v) && array_is_list($v);
            $truthy = $isList ? count($v) > 0 : (bool) $v;
            if ($n['t'] === '^') {
                $out .= $truthy ? '' : $this->renderNodes($n['c'], $ctxs);
            } elseif ($truthy) {
                $out .= $isList
                    ? implode('', array_map(fn ($item) => $this->renderNodes($n['c'], [...$ctxs, $item]), $v))
                    : $this->renderNodes($n['c'], is_array($v) ? [...$ctxs, $v] : $ctxs);
            }
        }

        return $out;
    }

    /** Uploaded HTML never runs code: strip scripts, embeds, inline handlers and javascript: links. */
    private function sanitize(string $html): string
    {
        return preg_replace([
            '/<script\b[\s\S]*?<\/script\s*>/i',
            '/<script\b[^>]*>/i',
            '/<(iframe|object|embed)\b[\s\S]*?<\/\1\s*>/i',
            '/<(iframe|object|embed)\b[^>]*>/i',
            '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            '/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i',
        ], ['', '', '', '', '', '$1=$2#$2'], $html) ?? '';
    }

    /**
     * dompdf runs without remote access, so <img src> links to this business's own files
     * (File Manager "Copy link", /storage/… or the media proxy) are embedded as data URIs.
     * Links to anything else are left as they are.
     */
    private function inlineImages(Business $business, string $html): string
    {
        return preg_replace_callback('/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])([^"\']+)\2/i', function ($m) use ($business) {
            $path = $this->publicPathFromUrl($business, html_entity_decode($m[3]));
            $uri  = $path ? $this->dataUri($path) : null;

            return $uri ? $m[1] . $m[2] . $uri . $m[2] : $m[0];
        }, $html) ?? $html;
    }

    /** The `public` disk path behind one of this business's media URLs, or null. */
    private function publicPathFromUrl(Business $business, string $url): ?string
    {
        if ($url === '' || str_starts_with($url, 'data:')) {
            return null;
        }
        $urlPath = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $id      = (int) $business->id;

        if (preg_match('#/media/business-logos/' . $id . '/([A-Za-z0-9._-]+)$#', $urlPath, $m)) {
            $path = 'business-logos/' . $id . '/' . $m[1];
        } elseif (preg_match('#/media/business-file/' . $id . '/(.+)$#', $urlPath, $m)) {
            $path = $m[1];
        } elseif (preg_match('#/storage/(.+)$#', $urlPath, $m)) {
            $path = $m[1];
        } else {
            return null;
        }

        // Only this business's own files: <folder>/<business id>/…
        if (str_contains($path, '..') || ! preg_match('#^[A-Za-z0-9_-]+/' . $id . '/#', $path)) {
            return null;
        }

        return $path;
    }

    private function dataUri(string $path): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path) || $disk->size($path) > 5 * 1024 * 1024) {
            return null;
        }
        $mime = $disk->mimeType($path) ?: 'image/png';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($disk->get($path));
    }
}
