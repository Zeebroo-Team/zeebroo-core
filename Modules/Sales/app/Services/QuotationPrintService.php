<?php

namespace Modules\Sales\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Business\Models\Business;
use Modules\DesignStudio\Models\Design;
use Modules\Sales\Models\Quotation;

/**
 * The quotation print view (sales::quotations.print): the data it needs — business, branch,
 * currency and the accent colour of the Design Studio letterhead — for the web print page,
 * and the same view rendered to a stored PDF for sending to the customer.
 */
class QuotationPrintService
{
    /** @return array<string, mixed> */
    public function viewData(Business $business, Quotation $quotation): array
    {
        $quotation->load(['customer', 'items.product', 'items.serviceItem.products']);

        $currency   = (string) (get_settings('business.currency', '', $business) ?: '');
        $mainBranch = $business->branches()->first();
        $lhLinks    = (array) get_settings('design_studio.lh_links', ['po', 'grn', 'hr_payslip', 'hr_salary_sheet', 'sales_quotation'], $business);
        $letterhead = in_array('sales_quotation', $lhLinks) ? Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'letterhead')
            ->latest('updated_at')
            ->first() : null;

        $accentColor          = '#1d4ed8'; // the desktop print's Classic template colour
        $letterheadCanvasJson = null;
        if ($letterhead) {
            try {
                $raw     = (string) $letterhead->canvas_json;
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    if (isset($decoded['objects'])) {
                        $letterheadCanvasJson = $raw;
                        $objs                 = $decoded['objects'];
                    } elseif (!empty($decoded[0]['json'])) {
                        $letterheadCanvasJson = $decoded[0]['json'];
                        $objs                 = json_decode((string) $decoded[0]['json'], true)['objects'] ?? [];
                    }
                }
                foreach ($objs ?? [] as $obj) {
                    if (($obj['type'] ?? '') === 'rect'
                        && (float) ($obj['top'] ?? 99) < 4
                        && (float) ($obj['height'] ?? 99) <= 8
                        && !empty($obj['fill'])
                        && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $obj['fill'])) {
                        $accentColor = $obj['fill'];
                        break;
                    }
                }
            } catch (\Throwable) {}
        }

        return [
            'business'             => $business,
            'quotation'            => $quotation,
            'currency'             => $currency,
            'mainBranch'           => $mainBranch,
            'accentColor'          => $accentColor,
            'letterheadCanvasJson' => $letterheadCanvasJson,
        ];
    }

    /** The disk quotation PDFs are kept on — private; they are served through authorised routes. */
    public static function disk(): string
    {
        return (string) config('sales.quotation_pdf_disk', 'local');
    }

    /**
     * Render the print view to a PDF (dompdf; the letterhead canvas needs a browser, so the PDF
     * carries the business logo and the letterhead's accent colour instead), store it as
     * quotations/<business>/<number>.pdf and remember the path on the quotation.
     */
    public function generatePdf(Quotation $quotation): string
    {
        $business = $quotation->business()->firstOrFail();
        $data     = $this->viewData($business, $quotation) + [
            'pdf'     => true,
            'logoSrc' => $this->logoDataUri($business),
        ];

        $binary = Pdf::loadView('sales::quotations.print', $data)->setPaper('a4')->output();
        $path   = 'quotations/' . $business->id . '/' . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($quotation->quote_number ?: $quotation->id)) . '.pdf';

        if ($quotation->pdf_path && $quotation->pdf_path !== $path) {
            Storage::disk(self::disk())->delete($quotation->pdf_path);
        }
        Storage::disk(self::disk())->put($path, $binary);
        $quotation->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    /** The stored PDF, made first if missing. */
    public function pdfPath(Quotation $quotation): string
    {
        if ($quotation->pdf_path && Storage::disk(self::disk())->exists($quotation->pdf_path)) {
            return $quotation->pdf_path;
        }

        return $this->generatePdf($quotation);
    }

    private function logoDataUri(Business $business): ?string
    {
        if (! $business->logo_path || ! Storage::disk('public')->exists($business->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($business->logo_path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($business->logo_path));
    }
}
