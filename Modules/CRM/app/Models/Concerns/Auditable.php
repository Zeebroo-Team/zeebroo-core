<?php

namespace Modules\CRM\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Models\CrmAuditLog;
use Modules\CRM\Services\CrmAuditService;

/**
 * Writes a crm_audit_logs row for every create, update and delete of the model, with the raw
 * column values before and after. The model needs a business_id column. Changes made through
 * query-builder mass updates (Model::query()->update()) fire no model events and are not
 * recorded — use the model when the change has to be audited.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(CrmAuditService::class)->record($model, CrmAuditLog::EVENT_CREATED, [], $model->auditValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $new = $model->auditValues($model->getChanges());
            if ($new === []) {
                return;
            }

            $old = array_intersect_key($model->getRawOriginal(), $new);
            app(CrmAuditService::class)->record($model, CrmAuditLog::EVENT_UPDATED, $old, $new);
        });

        static::deleted(function (Model $model) {
            app(CrmAuditService::class)->record($model, CrmAuditLog::EVENT_DELETED, $model->auditValues($model->getRawOriginal()), []);
        });
    }

    /** Columns never written to the audit log. */
    protected function auditExcluded(): array
    {
        return ['created_at', 'updated_at'];
    }

    /** @return array<string, mixed> */
    public function auditValues(array $attributes): array
    {
        return array_diff_key($attributes, array_flip($this->auditExcluded()));
    }
}
