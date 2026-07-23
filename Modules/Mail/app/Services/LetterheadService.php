<?php

namespace Modules\Mail\Services;

use Modules\Business\Models\Business;

class LetterheadService
{
    /**
     * A simple branded header banner (logo + business name) rendered as plain
     * HTML so it can be injected into any of this app's email templates via
     * the shared $letterheadHtml view variable, regardless of how each
     * template otherwise builds its own layout.
     */
    public function render(Business $business): string
    {
        $name = e($business->name);
        $logo = $business->logoUrl();

        $logoCell = $logo
            ? '<img src="' . e($logo) . '" alt="' . $name . '" style="display:block;max-height:44px;max-width:200px;">'
            : '';

        return <<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
                <td style="padding:0 0 16px;border-bottom:2px solid #e2e8f0;">
                    <table role="presentation" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding-right:{$this->logoSpacing($logo)}vertical-align:middle;">{$logoCell}</td>
                            <td style="vertical-align:middle;font-size:16px;font-weight:700;color:#1e293b;">{$name}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        HTML;
    }

    private function logoSpacing(?string $logo): string
    {
        return $logo ? '12px;' : '0;';
    }
}
