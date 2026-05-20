@extends('theme::layouts.app', ['title' => 'Account Onboarding', 'heading' => 'Account Onboarding'])

@section('content')
<style>
    .account-onboard-shell{
        min-height:calc(100vh - 73px);
        display:grid;
        place-items:center;
        width:100%;
        margin:0;
        padding:0;
    }
    .account-onboard-panel{
        position:relative;
        overflow:hidden;
        width:100%;
        min-height:calc(100vh - 92px);
        display:flex;
        align-items:center;
        justify-content:center;
        background:#ffffff;
        color:#1f2937;
        padding:36px;
    }
    .onboard-bg{
        position:absolute;
        inset:0;
        pointer-events:none;
        overflow:hidden;
    }
    .onboard-bg span{
        position:absolute;
        display:block;
        width:18px;height:18px;
        background:rgba(99,102,241,.12);
        border-radius:4px;
        animation:floatUp 18s linear infinite;
        bottom:-120px;
    }
    .onboard-bg span:nth-child(1){left:8%;width:40px;height:40px;animation-duration:16s;}
    .onboard-bg span:nth-child(2){left:22%;animation-delay:2s;}
    .onboard-bg span:nth-child(3){left:38%;width:30px;height:30px;animation-delay:4s;}
    .onboard-bg span:nth-child(4){left:58%;width:24px;height:24px;animation-duration:14s;}
    .onboard-bg span:nth-child(5){left:74%;animation-delay:3s;}
    .onboard-bg span:nth-child(6){left:88%;width:34px;height:34px;animation-delay:5s;}
    .account-onboard-form{
        width:min(760px, 100%);
        display:grid;
        gap:12px;
        text-align:center;
        position:relative;
        z-index:1;
    }
    .step-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .step-badge{font-size:13px;color:#6b7280;border:1px solid #e5e7eb;border-radius:999px;padding:5px 10px}
    .onboard-step{display:none;animation:fadeIn .25s ease}
    .onboard-step.active{display:grid;gap:12px}
    .type-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .type-card{
        border:1px solid #d1d5db;
        border-radius:12px;
        padding:14px;
        text-align:left;
        background:#f9fafb;
        color:#111827;
        cursor:pointer;
        transition:all .2s ease;
    }
    .type-card h4{margin:0 0 6px;font-size:15px;color:#111827}
    .type-card p{margin:0;color:#6b7280;font-size:13px;line-height:1.35}
    .type-card.active{border-color:#a78bfa;background:#f5f3ff}
    .type-card:hover{background:#f3f4f6;color:#111827;transform:none}
    .account-onboard-form input,
    .account-onboard-form select,
    .account-onboard-form textarea{
        width:100%;
        padding:12px 14px;
        border-radius:12px;
        border:1px solid #d1d5db;
        background:#f9fafb;
        color:#111827;
        outline:none;
        transition:all .2s ease;
    }
    .account-onboard-form input:focus,
    .account-onboard-form select:focus,
    .account-onboard-form textarea:focus{
        border-color:#a78bfa;
        background:#ffffff;
    }
    .account-onboard-form textarea{min-height:110px;resize:vertical;}
    .onboard-actions{display:flex;justify-content:center;gap:10px;margin-top:8px}
    .btn-soft{background:#6b7280}
    @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
    @keyframes floatUp{
        0%{transform:translateY(0) rotate(0deg);opacity:.7;border-radius:4px}
        100%{transform:translateY(-110vh) rotate(520deg);opacity:0;border-radius:50%}
    }
    @media (max-width:700px){.type-grid{grid-template-columns:1fr}}
</style>

<div class="account-onboard-shell">
    <div class="account-onboard-panel">
        <div class="onboard-bg" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span><span></span>
        </div>
        <form method="post" action="{{ route('account.store') }}" class="account-onboard-form">
            @csrf
            <input type="hidden" name="from_onboarding" value="1">
            <input type="hidden" name="bank_type_id" id="bankTypeId" value="{{ old('bank_type_id', $defaultBankTypeId) }}">

            <div class="step-head">
                <h2 style="margin:0;">Account Onboarding Wizard</h2>
                <span class="step-badge" id="stepBadge">Step 1 of 4</span>
            </div>
            <p class="muted" style="margin:0 0 10px;">Please setup account details for your business.</p>

            <div class="onboard-step active" id="step1">
                <h3 style="margin:4px 0;">Select account type</h3>
                <div class="type-grid">
                    @foreach($bankTypes as $type)
                        <button
                            type="button"
                            class="type-card {{ old('bank_type_id', $type->slug === 'current-account' ? $type->id : null) == $type->id ? 'active' : '' }}"
                            data-type-id="{{ $type->id }}"
                        >
                            <h4>{{ $type->name }}</h4>
                            <p>{{ $type->description ?: 'Account type option' }}</p>
                        </button>
                    @endforeach
                </div>
                <div class="onboard-actions">
                    <button type="button" id="toStep2">Next</button>
                </div>
            </div>

            <div class="onboard-step" id="step2">
                <h3 style="margin:4px 0;">Bank and account name</h3>
                <select name="bank_id" required>
                    <option value="">Select bank</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>{{ $bank->name }}</option>
                    @endforeach
                </select>
                <input name="account_name" value="{{ old('account_name') }}" placeholder="Account name" required>
                <div class="onboard-actions">
                    <button type="button" class="btn-soft" id="backToStep1">Back</button>
                    <button type="button" id="toStep3">Next</button>
                </div>
            </div>

            <div class="onboard-step" id="step3">
                <h3 style="margin:4px 0;">Other details</h3>
                <select name="business_id" required>
                    <option value="">Select business</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}" @selected(old('business_id', $defaultBusiness?->id) == $business->id)>{{ $business->name }}</option>
                    @endforeach
                </select>
                @include('account::partials.warehouse-branch-select', ['presetBranchId' => old('branch_id')])
                <input name="bank_account_number" value="{{ old('bank_account_number') }}" placeholder="Bank account number" required>
                <input name="branch" value="{{ old('branch') }}" placeholder="Branch" required>
                <div class="onboard-actions">
                    <button type="button" class="btn-soft" id="backToStep2">Back</button>
                    <button type="button" id="toStep4">Next</button>
                </div>
            </div>

            <div class="onboard-step" id="step4">
                <h3 style="margin:4px 0;">Current balance and other fields</h3>
                <input name="current_balance" type="number" min="0" step="0.01" value="{{ old('current_balance') }}" placeholder="Current Balance of your account" required>
                <input name="bank_officer_contact" value="{{ old('bank_officer_contact') }}" placeholder="Bank officer contact (optional)">
                <textarea name="notes" placeholder="Notes (optional)">{{ old('notes') }}</textarea>
                <div class="onboard-actions">
                    <button type="button" class="btn-soft" id="backToStep3">Back</button>
                    <button type="submit">Create Account</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    const stepBadge = document.getElementById('stepBadge');
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    const step4 = document.getElementById('step4');
    const bankTypeInput = document.getElementById('bankTypeId');
    const typeCards = document.querySelectorAll('.type-card');

    const setStep = (num) => {
        step1.classList.toggle('active', num === 1);
        step2.classList.toggle('active', num === 2);
        step3.classList.toggle('active', num === 3);
        step4.classList.toggle('active', num === 4);
        stepBadge.textContent = `Step ${num} of 4`;
    };

    typeCards.forEach((card) => {
        card.addEventListener('click', () => {
            typeCards.forEach((c) => c.classList.remove('active'));
            card.classList.add('active');
            bankTypeInput.value = card.dataset.typeId;
        });
    });

    document.getElementById('toStep2')?.addEventListener('click', () => {
        if (!bankTypeInput.value) {
            return;
        }
        setStep(2);
    });
    document.getElementById('backToStep1')?.addEventListener('click', () => setStep(1));
    document.getElementById('toStep3')?.addEventListener('click', () => setStep(3));
    document.getElementById('backToStep2')?.addEventListener('click', () => setStep(2));
    document.getElementById('toStep4')?.addEventListener('click', () => setStep(4));
    document.getElementById('backToStep3')?.addEventListener('click', () => setStep(3));

    @if($errors->has('bank_id') || $errors->has('account_name'))
        setStep(2);
    @elseif($errors->has('business_id') || $errors->has('branch_id') || $errors->has('bank_account_number') || $errors->has('branch'))
        setStep(3);
    @elseif($errors->has('current_balance') || $errors->has('bank_officer_contact') || $errors->has('notes'))
        setStep(4);
    @endif
</script>
@endsection
