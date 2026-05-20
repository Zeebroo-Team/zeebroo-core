@extends('theme::layouts.app', ['title' => 'Payroll setup', 'heading' => 'Payroll setup'])

@section('content')
    <style>
        .hr-onboard-shell{min-height:calc(100vh - 73px);display:grid;place-items:center;width:100%;margin:0;padding:0;background:var(--bg);}
        .hr-onboard-panel{
            position:relative;overflow:hidden;width:100%;min-height:calc(100vh - 92px);
            display:flex;align-items:center;justify-content:center;padding:28px 20px;background:#ffffff;color:#1f2937;
        }
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-onboard-panel{background:#0f172a;color:#e5e7eb;}
        .hr-onboard-bg{position:absolute;inset:0;pointer-events:none;overflow:hidden;}
        .hr-onboard-bg span{
            position:absolute;display:block;width:18px;height:18px;background:rgba(124,58,237,.14);border-radius:4px;
            animation:hrFloatUp 20s linear infinite;bottom:-100px;
        }
        .hr-onboard-bg span:nth-child(1){left:10%;width:36px;height:36px;}
        .hr-onboard-bg span:nth-child(2){left:30%;animation-delay:2s;}
        .hr-onboard-bg span:nth-child(3){left:55%;animation-delay:4s;}
        .hr-onboard-bg span:nth-child(4){left:78%;animation-delay:1s;}
        @keyframes hrFloatUp{
            0%{transform:translateY(0) rotate(0);opacity:.6;}
            100%{transform:translateY(-100vh) rotate(360deg);opacity:0;}
        }
        .hr-onboard-inner{width:min(720px,100%);margin:0 auto;text-align:center;position:relative;z-index:1;}
        .hr-onboard-badge{font-size:12px;color:#6b7280;border:1px solid #e5e7eb;border-radius:999px;padding:5px 12px;display:inline-block;margin-bottom:12px;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-onboard-badge{color:#94a3b8;border-color:#334155;}
        .hr-onboard-title{margin:0 0 8px;font-size:clamp(1.35rem,2.8vw,1.85rem);font-weight:800;letter-spacing:-.02em;}
        .hr-onboard-lead{margin:0 auto 22px;max-width:52ch;line-height:1.5;color:#6b7280;font-size:14px;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-onboard-lead{color:#94a3b8;}
        .hr-choice-grid{display:grid;gap:12px;text-align:left;}
        @media(min-width:560px){.hr-choice-grid{grid-template-columns:1fr 1fr;}}
        .hr-choice-card{
            border:1px solid #e5e7eb;border-radius:14px;padding:18px;background:#fafafa;display:flex;
            flex-direction:column;gap:10px;min-height:100%;
        }
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-choice-card{border-color:#334155;background:#111827;color:#e5e7eb;}
        .hr-choice-card h3{margin:0;font-size:16px;font-weight:800;color:#111827;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-choice-card h3{color:#f1f5f9;}
        .hr-choice-card p{margin:0;font-size:13px;line-height:1.45;color:#6b7280;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-choice-card p{color:#94a3b8;}
        .hr-choice-card button,.hr-choice-card .hr-linkbtn{display:inline-flex;align-items:center;justify-content:center;gap:8px;margin-top:auto;padding:10px 16px;font-size:13px;font-weight:700;border-radius:10px;text-decoration:none;border:0;cursor:pointer;}
        .hr-linkbtn--ghost{background:#f3f4f6;color:#374151;border:1px solid #d1d5db;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-linkbtn--ghost{background:#1e293b;color:#e2e8f0;border-color:#334155;}
        .hr-linkbtn--primary{background:#7c3aed;color:#fff;}
        .hr-linkbtn--primary:hover{filter:brightness(1.06);}
        #hr-step-2[hidden]{display:none!important;}
        .hr-step2-wrap{margin-top:8px;text-align:left;}
        .hr-step2-wrap .hr-field-lab{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin:14px 0 6px;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-step2-wrap .hr-field-lab{color:#94a3b8;}
        .hr-step2-wrap select{width:100%;box-sizing:border-box;padding:11px 12px;border-radius:10px;border:1px solid #d1d5db;background:#fff;color:#111827;font-size:14px;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-step2-wrap select{border-color:#334155;background:#0f172a;color:#f1f5f9;}
        .hr-band-grid{display:grid;gap:10px;margin-top:4px;}
        @media(min-width:540px){.hr-band-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
        .hr-band-option{display:flex;cursor:pointer;border:1px solid #e5e7eb;border-radius:12px;padding:11px 12px;background:#fafafa;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-band-option{border-color:#334155;background:#111827;color:#f1f5f9;}
        .hr-band-option:has(input:checked){border-color:#7c3aed;background:#faf5ff;box-shadow:0 0 0 2px rgba(124,58,237,.12);}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-band-option:has(input:checked){background:color-mix(in srgb,#7c3aed 14%,transparent);border-color:#7c3aed;}
        .hr-band-option input{margin:2px 10px 0 0;width:16px;height:16px;}
        .hr-band-text strong{display:block;font-size:14px;}
        .hr-band-text span{display:block;font-size:12px;color:#6b7280;margin-top:2px;}
        .hr-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:20px;}
        .hr-errors{margin-bottom:14px;color:#dc2626;font-size:13px;text-align:left;}
        :is(html[data-theme="night"],html[data-theme="night_blue"]) .hr-errors{color:#f87171;}
    </style>
    <div class="hr-onboard-shell">
        <div class="hr-onboard-panel" role="region" aria-label="Payroll setup wizard">
            <div class="hr-onboard-bg" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <div class="hr-onboard-inner">
                <span class="hr-onboard-badge">Human resources · {{ $business->name }}</span>
                <div id="hr-step-1">
                    <h1 class="hr-onboard-title">Do you want payroll in SociBiz?</h1>
                    <p class="hr-onboard-lead">Tell us whether you’ll track salaries and employee-related money here for <strong>{{ $business->name }}</strong>. You can revisit this wizard anytime from <strong>Employee Salary</strong> on the Overview.</p>

                    @if($errors->any())
                        <div class="hr-errors" role="alert">
                            @foreach($errors->all() as $msg)
                                <div>{{ $msg }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="hr-choice-grid">
                        <form method="post" action="{{ route('hr.setup.decline') }}" style="margin:0;">
                            @csrf
                            <div class="hr-choice-card">
                                <h3>Not handled in this system</h3>
                                <p>We keep payroll and employee records elsewhere. Take me back to the overview.</p>
                                <button type="submit" class="hr-linkbtn hr-linkbtn--ghost" style="width:100%;">Back to overview</button>
                            </div>
                        </form>
                        <div class="hr-choice-card">
                            <h3>Yes — I need that feature</h3>
                            <p>Continue to prefer a payout account and your headcount tier for this business.</p>
                            <button type="button" class="hr-linkbtn hr-linkbtn--primary" style="width:100%;" id="hr-show-step2">Continue to step 2</button>
                        </div>
                    </div>
                </div>

                <div id="hr-step-2" class="hr-step2-wrap" hidden>
                    <button type="button" class="hr-linkbtn hr-linkbtn--ghost" style="margin-bottom:12px;background:transparent;padding-left:0;" id="hr-back-step1">← Back</button>
                    <h2 class="hr-onboard-title" style="text-align:center;margin-bottom:6px;font-size:1.35rem;">Salary account & headcount</h2>
                    <p class="hr-onboard-lead" style="margin-bottom:16px;text-align:center;">Pick the debit account used for salaries (you can refine later). Then choose roughly how many people you payroll.</p>

                    @if($accounts->isEmpty())
                        <p style="margin:16px 0;color:#92400e;font-size:13px;line-height:1.45;text-align:center;">Add a bank account for this business first, then return here.<br><a href="{{ route('account.onboarding') }}" class="linkbtn" style="margin-top:10px;display:inline-block;">Account onboarding</a></p>
                    @else
                        <form method="post" action="{{ route('hr.setup.complete') }}" id="hr-complete-form">
                            @csrf
                            <label class="hr-field-lab" for="salary_account_id">Salary handling account</label>
                            <select name="salary_account_id" id="salary_account_id" required>
                                <option value="">Select account…</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}" @selected((string) ($defaults['salary_account_id'] ?? '') === (string) $acc->id)>{{ $acc->deductOptionLabel() }}</option>
                                @endforeach
                            </select>

                            <span class="hr-field-lab">Employee headcount tier</span>
                            <div class="hr-band-grid">
                                @foreach($bands as $band)
                                    <label class="hr-band-option">
                                        <input type="radio" name="employee_count_band" value="{{ $band }}" required @checked((string) ($defaults['employee_count_band'] ?? '') === $band)>
                                        <span class="hr-band-text">
                                            <strong>{{ $bandLabels[$band] ?? $band }}</strong>
                                            <span>Estimated team size covered by payroll</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="hr-actions">
                                <button type="submit" class="linkbtn" style="min-width:200px;"><i class="fa fa-circle-check"></i> Finish setup</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var step1 = document.getElementById('hr-step-1');
        var step2 = document.getElementById('hr-step-2');
        var btn2 = document.getElementById('hr-show-step2');
        var back = document.getElementById('hr-back-step1');
        if(btn2 && step2 && step1){
            btn2.addEventListener('click',function(){
                step1.hidden=true;
                step2.hidden=false;
                document.querySelector('[name=salary_account_id]')?.focus();
            });
        }
        back?.addEventListener('click',function(){
            step1.hidden=false;
            step2.hidden=true;
        });
        @if($errors->has('salary_account_id') || $errors->has('employee_count_band'))
        if(step1&&step2){ step1.hidden=true; step2.hidden=false; }
        @endif
    })();
    </script>
@endsection
