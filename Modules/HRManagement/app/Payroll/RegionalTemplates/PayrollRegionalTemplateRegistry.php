<?php

declare(strict_types=1);

namespace Modules\HRManagement\Payroll\RegionalTemplates;

use Modules\Business\Models\Business;

final class PayrollRegionalTemplateRegistry
{
    /** @var array<string, PayrollRegionalTemplateContract> */
    private array $byKey;

    /**
     * @param  iterable<PayrollRegionalTemplateContract>  $templates
     */
    public function __construct(iterable $templates)
    {
        $map = [];
        foreach ($templates as $template) {
            $key = $template->key();
            if (isset($map[$key])) {
                throw new \InvalidArgumentException('Duplicate payroll regional template key: '.$key);
            }
            $map[$key] = $template;
        }

        $this->byKey = $map;
    }

    /** @return list<string> */
    public function registeredKeys(): array
    {
        return array_keys($this->byKey);
    }

    /**
     * @return list<array{key: string, title: string, description: string, highlights: list<string>}>
     */
    public function cards(): array
    {
        $out = [];
        foreach ($this->byKey as $key => $template) {
            $c = $template->card();
            $out[] = [
                'key' => $key,
                'title' => $c['title'],
                'description' => $c['description'],
                'highlights' => $c['highlights'],
            ];
        }

        return $out;
    }

    public function get(string $key): ?PayrollRegionalTemplateContract
    {
        return $this->byKey[$key] ?? null;
    }

    /**
     * First registered template seeds a business with no rule sets (keep Sri Lanka first in the provider list).
     */
    public function seedEmptyBusinessDefaults(Business $business): void
    {
        $first = reset($this->byKey);
        if ($first instanceof PayrollRegionalTemplateContract) {
            $first->install($business);
        }
    }
}
