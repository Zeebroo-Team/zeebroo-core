<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollCustomTemplate;
use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\HRManagement\Payroll\RegionalTemplates\PayrollRegionalTemplateInstallHelper;
use Modules\Settings\Services\SettingsService;

final class PayrollCustomTemplateService
{
    private const MAX_RULES = 120;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly PayrollRegionalTemplateInstallHelper $installHelper,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title: string, description: ?string, highlights: list<string>, rule_set_name: string, currency: ?string, rules: list<array<string, mixed>>, settings: array<string, mixed>}
     */
    public function validateImportPayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'highlights' => ['nullable', 'array', 'max:24'],
            'highlights.*' => ['required', 'string', 'max:500'],
            'rule_set_name' => ['required', 'string', 'max:140'],
            'currency' => ['nullable', 'string', 'max:16'],
            'settings' => ['nullable', 'array', 'max:80'],
            'rules' => ['required', 'array', 'min:1', 'max:'.self::MAX_RULES],
            'rules.*.code' => ['required', 'string', 'max:64'],
            'rules.*.name' => ['required', 'string', 'max:140'],
            'rules.*.component_type' => ['required', 'string', Rule::in([
                PayrollRule::TYPE_EARNING,
                PayrollRule::TYPE_DEDUCTION,
                PayrollRule::TYPE_STATUTORY,
                PayrollRule::TYPE_OVERTIME,
                PayrollRule::TYPE_INFORMATIONAL,
                PayrollRule::TYPE_EMPLOYER_TRACKING,
            ])],
            'rules.*.calculation_mode' => ['required', 'string', Rule::in([
                PayrollRule::MODE_FIXED,
                PayrollRule::MODE_PERCENTAGE,
                PayrollRule::MODE_SLAB,
                PayrollRule::MODE_FORMULA,
            ])],
            'rules.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'rules.*.is_taxable' => ['nullable', 'boolean'],
            'rules.*.is_statutory' => ['nullable', 'boolean'],
            'rules.*.is_active' => ['nullable', 'boolean'],
            'rules.*.config_json' => ['nullable', 'array'],
        ]);

        $validator->after(function ($v): void {
            /** @var \Illuminate\Validation\Validator $v */
            $rules = $v->getData()['rules'] ?? [];
            if (! is_array($rules)) {
                return;
            }
            foreach ($rules as $i => $row) {
                if (! is_array($row)) {
                    $v->errors()->add('rules.'.$i, __('Each rule must be an object.'));

                    continue;
                }
                $mode = (string) ($row['calculation_mode'] ?? '');
                $cfg = isset($row['config_json']) && is_array($row['config_json']) ? $row['config_json'] : [];
                if ($mode === PayrollRule::MODE_SLAB) {
                    if (! isset($cfg['slabs']) || ! is_array($cfg['slabs']) || $cfg['slabs'] === []) {
                        $v->errors()->add('rules.'.$i, __('Slab rules require config_json.slabs (non-empty array).'));
                    }
                }
                if ($mode === PayrollRule::MODE_FORMULA) {
                    $formula = trim((string) ($cfg['formula'] ?? ''));
                    $flow = $cfg['flow_v1'] ?? null;
                    $hasFlow = is_array($flow) && trim((string) ($flow['root'] ?? '')) !== ''
                        && isset($flow['nodes']) && is_array($flow['nodes']) && $flow['nodes'] !== [];
                    if ($formula === '' && ! $hasFlow) {
                        $v->errors()->add('rules.'.$i, __('Formula rules need config_json.formula or config_json.flow_v1.'));
                    }
                }
                if ($mode === PayrollRule::MODE_FIXED && ! isset($cfg['amount'])) {
                    $v->errors()->add('rules.'.$i, __('Fixed rules need config_json.amount.'));
                }
                if ($mode === PayrollRule::MODE_PERCENTAGE) {
                    if (! isset($cfg['percent'])) {
                        $v->errors()->add('rules.'.$i, __('Percentage rules need config_json.percent.'));
                    }
                    if (! isset($cfg['base_field'])) {
                        $v->errors()->add('rules.'.$i, __('Percentage rules should set config_json.base_field (e.g. basic_salary).'));
                    }
                }
            }

            $settings = $v->getData()['settings'] ?? null;
            if (is_array($settings)) {
                foreach ($settings as $sk => $sv) {
                    if (! is_string($sk) || $sk === '' || strlen($sk) > 120) {
                        $v->errors()->add('settings', __('Setting keys must be non-empty strings (max 120 characters).'));

                        break;
                    }
                    if (is_array($sv) || is_object($sv)) {
                        $v->errors()->add('settings.'.$sk, __('Setting values must be scalar (no nested objects).'));

                        break;
                    }
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array<string, mixed> $data */
        $data = $validator->validated();
        /** @var list<string>|null $h */
        $h = isset($data['highlights']) && is_array($data['highlights']) ? array_values(array_map(static fn ($x) => (string) $x, $data['highlights'])) : [];
        /** @var list<array<string, mixed>> $ruleRows */
        $ruleRows = array_values(array_map(fn (array $r): array => $this->normalizeRuleRow($r), $data['rules']));

        /** @var array<string, mixed> */
        $flatSettings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        return [
            'title' => (string) $data['title'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'highlights' => $h,
            'rule_set_name' => (string) $data['rule_set_name'],
            'currency' => isset($data['currency']) ? (string) $data['currency'] : null,
            'rules' => $ruleRows,
            'settings' => $flatSettings,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(Business $business, array $validated): PayrollCustomTemplate
    {
        return PayrollCustomTemplate::query()->create([
            'business_id' => $business->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'highlights' => $validated['highlights'] ?: null,
            'rule_set_name' => $validated['rule_set_name'],
            'currency' => $validated['currency'],
            'rules' => $validated['rules'],
            'settings' => $validated['settings'] !== [] ? $validated['settings'] : null,
        ]);
    }

    public function apply(Business $business, PayrollCustomTemplate $template): string
    {
        abort_if((int) $template->business_id !== (int) $business->id, 404);

        $currencyFallback = (string) (get_settings('business.currency', 'LKR', $business) ?: 'LKR');
        $currency = $template->currency ? (string) $template->currency : $currencyFallback;

        $ruleSet = PayrollRuleSet::query()
            ->where('business_id', $business->id)
            ->where('name', $template->rule_set_name)
            ->first();

        if (! $ruleSet) {
            $ruleSet = PayrollRuleSet::query()->create([
                'business_id' => $business->id,
                'name' => $template->rule_set_name,
                'currency' => $currency,
                'effective_from' => now()->toDateString(),
                'is_default' => false,
                'is_active' => true,
                'notes' => __('Imported payroll template.'),
            ]);
        }

        $ruleSet->forceFill([
            'currency' => $currency,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
            'notes' => __('Imported payroll template.'),
        ])->save();

        $this->installHelper->makeRuleSetSoleDefault($business, $ruleSet);
        $ruleSet->rules()->delete();

        $rows = [];
        foreach ($template->rules as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = $this->normalizeRuleRow($row);
        }
        $ruleSet->rules()->createMany($rows);

        /** @var array<string, mixed> $extra */
        $extra = is_array($template->settings) ? $template->settings : [];
        $extra['hr.payroll.template'] = $template->templateKey();
        $this->settings->setMany($business, $extra);

        return (string) __('Custom payroll template “:title” installed.', ['title' => $template->title]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRuleRow(array $row): array
    {
        $cfg = isset($row['config_json']) && is_array($row['config_json']) ? $row['config_json'] : [];

        return [
            'code' => strtoupper(trim((string) ($row['code'] ?? ''))),
            'name' => (string) ($row['name'] ?? ''),
            'component_type' => (string) ($row['component_type'] ?? ''),
            'calculation_mode' => (string) ($row['calculation_mode'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_taxable' => (bool) ($row['is_taxable'] ?? false),
            'is_statutory' => (bool) ($row['is_statutory'] ?? false),
            'is_active' => (bool) ($row['is_active'] ?? true),
            'config_json' => $cfg,
        ];
    }
}
