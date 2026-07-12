<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadForm extends Model
{
    protected $table = 'crm_lead_forms';

    const LAYOUT_CARD    = 'card';
    const LAYOUT_SPLIT   = 'split';
    const LAYOUT_MINIMAL = 'minimal';

    protected $fillable = [
        'project_id',
        'name',
        'token',
        'blocks',
        'style',
        'submit_button_text',
        'success_message',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'blocks'       => 'array',
            'style'        => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function layouts(): array
    {
        return [
            self::LAYOUT_CARD    => 'Card',
            self::LAYOUT_SPLIT   => 'Split',
            self::LAYOUT_MINIMAL => 'Minimal',
        ];
    }

    public static function defaultStyle(): array
    {
        return [
            'layout'            => self::LAYOUT_CARD,
            'accent_color'      => '#2563eb',
            'background_color'  => '#f1f5f9',
        ];
    }

    /**
     * Stored style merged over sane defaults, so partial/missing data never breaks rendering.
     */
    public function styleSettings(): array
    {
        return array_merge(self::defaultStyle(), array_filter((array) ($this->style ?? []), fn ($v) => filled($v)));
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(20));
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    public function publicUrl(): string
    {
        return url('/f/' . $this->token);
    }

    /**
     * Walk all blocks — recursing into "row" blocks' "column" children — and return every
     * "field" block keyed by its path (e.g. "3" for a top-level field, "3-0-1" for a field
     * nested in column 0 of the row at top-level index 3). The hyphen separator is deliberate:
     * it's used as a literal HTML input name, and must not collide with Laravel's dot-notation
     * array helpers (old(), Arr::get) which would otherwise try to traverse it.
     *
     * @return array<string, array>
     */
    public function fieldBlocksWithPaths(): array
    {
        $result = [];

        $walk = function (array $blocks, string $prefix) use (&$walk, &$result): void {
            foreach ($blocks as $i => $block) {
                $path = $prefix === '' ? (string) $i : $prefix . '-' . $i;

                if (($block['type'] ?? null) === 'field') {
                    $result[$path] = $block;
                } elseif (($block['type'] ?? null) === 'row') {
                    foreach (($block['blocks'] ?? []) as $colIndex => $column) {
                        $walk($column['blocks'] ?? [], $path . '-' . $colIndex);
                    }
                }
            }
        };

        $walk($this->blocks ?? [], '');

        return $result;
    }

    /**
     * Resolve a flat, path-keyed input array (see fieldBlocksWithPaths()) into the shape
     * LeadService::create()/update() expects: core Lead attributes plus a custom_fields
     * map. Shared by public form submissions and the internal "New lead" form, which is
     * built from these same field blocks — both surfaces stay in sync automatically.
     *
     * @param  array<string, string>  $input  keyed by block path
     * @return array{core: array{name: ?string, company: ?string, email: ?string, phone: ?string}, custom_fields: array<int, string>, first_text: ?string}
     */
    public function mapPathedInputsToLeadData(array $input): array
    {
        $core         = ['name' => null, 'company' => null, 'email' => null, 'phone' => null];
        $customFields = [];
        $firstText    = null;

        foreach ($this->fieldBlocksWithPaths() as $path => $block) {
            $raw   = trim((string) ($input[$path] ?? ''));
            $field = (string) ($block['field'] ?? '');

            if ($raw !== '' && $firstText === null) {
                $firstText = $raw;
            }

            if (array_key_exists($field, $core)) {
                $core[$field] = $raw !== '' ? $raw : $core[$field];
                continue;
            }

            if (str_starts_with($field, 'custom:')) {
                $customFieldId = (int) substr($field, 7);
                if ($raw !== '') {
                    $customFields[$customFieldId] = $raw;
                }
            }
        }

        return ['core' => $core, 'custom_fields' => $customFields, 'first_text' => $firstText];
    }
}
