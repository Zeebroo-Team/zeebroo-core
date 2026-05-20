@extends('theme::layouts.app', ['title' => __('Regional template'), 'heading' => __('Regional template')])

@section('content')
    <style>
        .rt-wrap{max-width:1080px;display:grid;gap:14px}
        .rt-intro{border:1px solid color-mix(in srgb,var(--border)90%,transparent);border-radius:12px;background:var(--card);padding:14px 16px;box-shadow:0 1px 0 color-mix(in srgb,var(--border)55%,transparent) inset,0 6px 18px rgba(0,0,0,.04)}
        .rt-head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px}
        .rt-title{margin:0;font-size:1rem;font-weight:800;letter-spacing:-.01em}
        .rt-sub{margin:8px 0 0;font-size:12px;line-height:1.45;color:var(--muted);max-width:640px}
        .rt-actions{display:flex;gap:8px;flex-wrap:wrap}
        .rt-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:7px 11px;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);font-size:12px;font-weight:700;text-decoration:none;cursor:pointer;font-family:inherit}
        .rt-btn:hover{background:color-mix(in srgb,var(--primary)18%,transparent);color:var(--text)}
        button.rt-btn:disabled{opacity:.55;cursor:not-allowed}
        button.rt-btn:disabled:hover{background:color-mix(in srgb,var(--primary)12%,transparent)}
        .rt-btn--primary{
            border-color:color-mix(in srgb,var(--primary)58%,var(--border));
            background:linear-gradient(180deg,color-mix(in srgb,var(--primary)26%,transparent),color-mix(in srgb,var(--primary)14%,transparent));
        }
        .rt-btn--primary:hover{background:linear-gradient(180deg,color-mix(in srgb,var(--primary)32%,transparent),color-mix(in srgb,var(--primary)18%,transparent))}
        .rt-btn--ghost{border-color:color-mix(in srgb,var(--border)88%,transparent);background:color-mix(in srgb,var(--card)96%,transparent)}
        .rt-meta{font-size:11px;color:var(--muted);margin-top:12px;line-height:1.4}

        .rt-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));align-items:stretch}
        .rt-card{
            border:1px solid color-mix(in srgb,var(--border)88%,transparent);
            border-radius:14px;background:var(--card);
            padding:16px;
            box-shadow:0 1px 0 color-mix(in srgb,var(--border)50%,transparent) inset;
            display:flex;flex-direction:column;gap:12px;
        }
        .rt-card--active{
            border-color:color-mix(in srgb,var(--primary)42%,var(--border));
            box-shadow:0 0 0 1px color-mix(in srgb,var(--primary)22%,transparent),0 1px 0 color-mix(in srgb,var(--border)45%,transparent) inset;
            background:linear-gradient(165deg,color-mix(in srgb,var(--card)98%,transparent),color-mix(in srgb,var(--primary)8%,transparent));
        }
        .rt-card-top{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:10px}
        .rt-card-title{margin:0;font-size:.98rem;font-weight:800;line-height:1.25;color:var(--text);letter-spacing:-.01em}
        .rt-card-desc{margin:0;font-size:12px;line-height:1.45;color:var(--muted)}
        .rt-chip{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:800;border:1px solid color-mix(in srgb,#22c55e 38%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:#15803d}
        .rt-highlights{margin:0;padding-left:18px;font-size:11px;line-height:1.45;color:var(--text);opacity:.92}
        .rt-highlights li{margin-bottom:6px}
        .rt-highlights li:last-child{margin-bottom:0}
        .rt-card-foot{margin-top:auto;padding-top:4px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
        .rt-hint{margin:0;font-size:10.5px;line-height:1.4;color:var(--muted)}

        .rt-import{border:1px dashed color-mix(in srgb,var(--border)78%,transparent);border-radius:12px;background:color-mix(in srgb,var(--card)99%,transparent);padding:12px 14px}
        .rt-import summary{cursor:pointer;font-size:11px;font-weight:800;color:var(--muted);letter-spacing:.04em;text-transform:uppercase;list-style:none}
        .rt-import summary::-webkit-details-marker{display:none}
        .rt-import textarea{width:100%;box-sizing:border-box;min-height:220px;font-family:ui-monospace,Consolas,Menlo,monospace;font-size:11px;line-height:1.35;padding:10px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border)82%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);color:var(--text)}
        .rt-import code{font-size:10px;color:var(--muted)}
    </style>

    @if(session('status'))
        <p class="emp-show__flash" role="status" style="max-width:1080px;">{{ session('status') }}</p>
    @endif
    @if($errors->any())
        <div class="emp-show__err" role="alert" style="max-width:1080px;">
            <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="rt-wrap">
        <section class="rt-intro" aria-labelledby="rt-page-title">
            <div class="rt-head">
                <div>
                    <h2 id="rt-page-title" class="rt-title">{{ __('Regional template') }}</h2>
                    <p class="rt-sub">{{ __('Each card is a bundled regional preset. Installing applies statutory rules and saved settings for this business (same as the former “apply template” action).') }}</p>
                </div>
                <div class="rt-actions">
                    <a href="{{ route('hr.payroll.rule-sets.index') }}" class="rt-btn rt-btn--ghost"><i class="fa fa-sliders" aria-hidden="true"></i>{{ __('Rule sets') }}</a>
                    <a href="{{ route('hr.payroll.index') }}" class="rt-btn rt-btn--ghost"><i class="fa fa-arrow-left" aria-hidden="true"></i>{{ __('Back to payroll') }}</a>
                </div>
            </div>
            <p class="rt-meta">{{ __('Business') }}: <strong style="color:var(--text);font-weight:700;">{{ $business->name }}</strong></p>
        </section>

        <div class="rt-grid">
            @foreach($payrollTemplateCards as $card)
                @php
                    $isActive = $selectedPayrollTemplate === $card['key'];
                    $cardDomId = 'rt-card-title-'.str_replace([':', ' ', '.'], '-', $card['key']);
                @endphp
                <article class="rt-card{{ $isActive ? ' rt-card--active' : '' }}" aria-labelledby="{{ $cardDomId }}">
                    <div class="rt-card-top">
                        <h3 id="{{ $cardDomId }}" class="rt-card-title">{{ $card['title'] }}@if(!empty($card['is_custom'])) <span class="rt-hint" style="display:inline;font-weight:700;">{{ __('(imported)') }}</span>@endif</h3>
                        @if($isActive)
                            <span class="rt-chip">{{ __('Installed') }}</span>
                        @endif
                    </div>
                    <p class="rt-card-desc">{{ $card['description'] }}</p>
                    @if(! empty($card['highlights']))
                        <ul class="rt-highlights">
                            @foreach($card['highlights'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="rt-card-foot">
                        <form method="post" action="{{ route('hr.payroll.templates.apply') }}" class="rt-install-form">
                            @csrf
                            <input type="hidden" name="template" value="{{ $card['key'] }}">
                            <button type="submit" class="rt-btn rt-btn--primary"><i class="fa fa-download" aria-hidden="true"></i>{{ $isActive ? __('Re-install & apply') : __('Install & apply') }}</button>
                        </form>
                        <p class="rt-hint">{{ __('Install configures the starter rule set and business payroll settings.') }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <details class="rt-import">
            <summary>{{ __('Import custom template (JSON)') }}</summary>
            <p class="rt-meta" style="margin:10px 0 8px;">{{ __('Paste a JSON object. It is saved for this business and applied immediately to the named rule set (rules are replaced). Keys: title, optional description & highlights, rule_set_name, optional currency, optional settings (flat scalars only), and rules (see payroll rules in Rule sets).') }}</p>
            <p class="rt-hint" style="margin:0 0 8px;"><code>{"title":"…","rule_set_name":"…","currency":"INR","rules":[{"code":"STAT_1","name":"…","component_type":"statutory","calculation_mode":"percentage","sort_order":10,"is_taxable":false,"is_statutory":true,"is_active":true,"config_json":{"base_field":"basic_salary","percent":5}}],"settings":{"hr.payroll.cycle.default_working_days":26}}</code></p>
            <form method="post" action="{{ route('hr.payroll.templates.import') }}" style="display:grid;gap:10px;margin-top:10px;">
                @csrf
                <label for="rt-import-json" class="rt-hint">{{ __('Template JSON') }}</label>
                <textarea id="rt-import-json" name="definition" required placeholder="{{ __('{ ... }') }}" spellcheck="false">{{ old('definition') }}</textarea>
                <div>
                    <button type="submit" class="rt-btn rt-btn--ghost"><i class="fa fa-file-import" aria-hidden="true"></i>{{ __('Import & apply') }}</button>
                </div>
            </form>
        </details>
    </div>
@endsection
