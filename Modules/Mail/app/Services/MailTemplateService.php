<?php

namespace Modules\Mail\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Mail\Models\MailTemplate;

class MailTemplateService
{
    public function listForBusiness(Business $business): Collection
    {
        return MailTemplate::where('business_id', $business->id)->orderBy('name')->get();
    }

    public function create(Business $business, array $data): MailTemplate
    {
        return MailTemplate::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'subject'     => $data['subject'],
            'body'        => $data['body'],
        ]);
    }

    public function update(MailTemplate $template, array $data): MailTemplate
    {
        $template->update([
            'name'    => $data['name'],
            'subject' => $data['subject'],
            'body'    => $data['body'],
        ]);

        return $template->fresh();
    }

    public function delete(MailTemplate $template): void
    {
        $template->delete();
    }

    public function templateForBusiness(Business $business, MailTemplate $template): ?MailTemplate
    {
        return $template->business_id === $business->id ? $template : null;
    }
}
