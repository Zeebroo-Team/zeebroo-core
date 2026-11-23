<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\DeliveryTerm;
use Modules\CRM\Services\DeliveryTermService;

beforeEach(function () {
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->string('name');
        $t->timestamps();
    });
    (require base_path('Modules/CRM/database/migrations/2026_11_23_100000_create_crm_delivery_terms_table.php'))->up();

    DB::table('businesses')->insert([
        ['id' => 1, 'user_id' => 1, 'name' => 'ABC'],
        ['id' => 2, 'user_id' => 1, 'name' => 'Other'],
    ]);
    $this->business = Business::find(1);
    $this->terms    = app(DeliveryTermService::class);
});

it('seeds the default terms once per business', function () {
    $labels = $this->terms->listForBusiness($this->business)->pluck('label')->all();

    expect($labels)->toBe(DeliveryTerm::DEFAULTS);
    $this->terms->listForBusiness($this->business);
    expect(DeliveryTerm::where('business_id', 1)->count())->toBe(count(DeliveryTerm::DEFAULTS))
        ->and(DeliveryTerm::where('business_id', 2)->count())->toBe(0);
});

it('adds a term at the end and refuses blanks and duplicates', function () {
    $term = $this->terms->create($this->business, ['label' => '  Within 10   days  ']);

    expect($term->label)->toBe('Within 10 days')
        ->and($this->terms->listForBusiness($this->business)->last()->id)->toBe($term->id);

    expect(fn () => $this->terms->create($this->business, ['label' => 'within 10 DAYS']))->toThrow(ValidationException::class);
    expect(fn () => $this->terms->create($this->business, ['label' => '   ']))->toThrow(ValidationException::class);
});

it('hides inactive terms from the editor list but keeps them for managing', function () {
    $first = $this->terms->listForBusiness($this->business)->first();
    $this->terms->update($first, ['is_active' => false]);

    expect($this->terms->listForBusiness($this->business)->pluck('id'))->not->toContain($first->id)
        ->and($this->terms->listForBusiness($this->business, false)->pluck('id'))->toContain($first->id);
});

it('reorders, keeping terms left out after the given ones', function () {
    $ids = $this->terms->listForBusiness($this->business, false)->pluck('id')->all();
    $this->terms->reorder($this->business, [$ids[2], $ids[0], 999]);

    expect($this->terms->listForBusiness($this->business, false)->pluck('id')->all())
        ->toBe([$ids[2], $ids[0], $ids[1], $ids[3]]);
});

it('only finds terms of the same business', function () {
    $other = $this->terms->create(Business::find(2), ['label' => 'Other business term']);

    expect(fn () => $this->terms->termForBusiness($this->business, $other->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
