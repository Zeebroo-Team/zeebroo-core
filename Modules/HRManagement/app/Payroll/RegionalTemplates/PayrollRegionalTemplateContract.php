<?php

declare(strict_types=1);

namespace Modules\HRManagement\Payroll\RegionalTemplates;

use Modules\Business\Models\Business;

/**
 * Plug-in regional payroll presets: implement this class and register it in
 * {@see PayrollRegionalTemplateRegistry} (HRManagementServiceProvider) to add a new installable template.
 */
interface PayrollRegionalTemplateContract
{
    public function key(): string;

    /**
     * @return array{title: string, description: string, highlights: list<string>}
     */
    public function card(): array;

    /**
     * Apply template to the business (rule set, rules, settings). Idempotent re-install supported.
     *
     * @return string Flash message (translation already applied by implementation)
     */
    public function install(Business $business): string;
}
