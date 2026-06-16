<?php

namespace Modules\Service\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Service\Models\ServiceRequest;

class ServiceRequestService
{
    public function listForBusiness(Business $business, ?string $search = null, ?string $status = null): Collection
    {
        $query = ServiceRequest::query()
            ->where('business_id', $business->id)
            ->with(['serviceItem', 'customer']);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('request_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function businessHasRequests(Business $business): bool
    {
        return ServiceRequest::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data): ServiceRequest
    {
        return ServiceRequest::create([
            'business_id'     => $business->id,
            'service_item_id' => $this->nullableInt($data['service_item_id'] ?? null),
            'customer_id'     => $this->nullableInt($data['customer_id'] ?? null),
            'request_number'  => $this->nextRequestNumber($business),
            'title'           => $data['title'],
            'reference'       => filled($data['reference'] ?? '') ? $data['reference'] : null,
            'notes'           => filled($data['notes'] ?? '') ? $data['notes'] : null,
            'scheduled_at'    => filled($data['scheduled_at'] ?? '') ? $data['scheduled_at'] : null,
            'status'          => ServiceRequest::STATUS_PENDING,
            'total_price'     => isset($data['total_price']) && $data['total_price'] !== '' ? (float) $data['total_price'] : null,
        ]);
    }

    public function update(ServiceRequest $request, array $data): ServiceRequest
    {
        $request->update([
            'service_item_id' => $this->nullableInt($data['service_item_id'] ?? null),
            'customer_id'     => $this->nullableInt($data['customer_id'] ?? null),
            'title'           => $data['title'],
            'reference'       => filled($data['reference'] ?? '') ? $data['reference'] : null,
            'notes'           => filled($data['notes'] ?? '') ? $data['notes'] : null,
            'scheduled_at'    => filled($data['scheduled_at'] ?? '') ? $data['scheduled_at'] : null,
            'total_price'     => isset($data['total_price']) && $data['total_price'] !== '' ? (float) $data['total_price'] : null,
        ]);

        return $request->fresh();
    }

    public function markInProgress(ServiceRequest $request): ServiceRequest
    {
        if ($request->status === ServiceRequest::STATUS_PENDING) {
            $request->update(['status' => ServiceRequest::STATUS_IN_PROGRESS]);
        }

        return $request;
    }

    public function markCompleted(ServiceRequest $request): ServiceRequest
    {
        if (in_array($request->status, [ServiceRequest::STATUS_PENDING, ServiceRequest::STATUS_IN_PROGRESS])) {
            $request->update(['status' => ServiceRequest::STATUS_COMPLETED]);
        }

        return $request;
    }

    public function cancel(ServiceRequest $request): ServiceRequest
    {
        if ($request->status !== ServiceRequest::STATUS_COMPLETED) {
            $request->update(['status' => ServiceRequest::STATUS_CANCELLED]);
        }

        return $request;
    }

    public function delete(ServiceRequest $request): void
    {
        $request->delete();
    }

    public function requestForBusiness(Business $business, ServiceRequest $request): ?ServiceRequest
    {
        return $request->business_id === $business->id ? $request : null;
    }

    private function nextRequestNumber(Business $business): string
    {
        $last = ServiceRequest::query()
            ->where('business_id', $business->id)
            ->whereNotNull('request_number')
            ->orderByDesc('id')
            ->value('request_number');

        $seq = 1;
        if ($last && preg_match('/SR-(\d+)$/i', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'SR-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
