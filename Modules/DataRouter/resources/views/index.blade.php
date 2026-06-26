@extends('theme::layouts.app', [
    'title' => 'Data Vault',
    'heading' => 'Data Vault',
])

@section('content')
<style>
    .dv-wrap.card{max-width:none;width:100%;box-sizing:border-box;padding:20px 22px 24px;border-radius:16px;}
    .dv-lead{margin:0 0 20px;font-size:14px;line-height:1.55;color:var(--muted);}

    /* Status badge */
    .dv-status{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:650;padding:4px 10px;border-radius:20px;border:1px solid;}
    .dv-status--active{color:#16a34a;border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
    .dv-status--inactive{color:var(--muted);border-color:var(--border);background:color-mix(in srgb,var(--card) 60%,transparent);}
    .dv-status--error{color:#dc2626;border-color:color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 10%,transparent);}
    .dv-status__dot{width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0;}

    /* Section card */
    .dv-section{border:1px solid var(--border);border-radius:14px;background:linear-gradient(165deg,color-mix(in srgb,var(--card) 98%,transparent),color-mix(in srgb,var(--card) 92%,transparent));box-shadow:0 8px 28px -22px rgba(0,0,0,.35);overflow:hidden;margin-bottom:14px;}
    .dv-section__head{padding:16px 20px;display:flex;align-items:center;gap:14px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);}
    .dv-section__icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;color:#fff;}
    .dv-section__titles{flex:1;min-width:0;}
    .dv-section__title{margin:0 0 2px;font-size:15px;font-weight:750;color:var(--text);letter-spacing:-.02em;}
    .dv-section__desc{margin:0;font-size:12.5px;color:var(--muted);line-height:1.4;}
    .dv-section__body{padding:20px;}

    /* Form fields */
    .dv-field{margin-bottom:16px;}
    .dv-field:last-child{margin-bottom:0;}
    .dv-label{display:block;font-size:12.5px;font-weight:650;color:var(--text);margin-bottom:6px;letter-spacing:.01em;}
    .dv-label span{font-weight:400;color:var(--muted);margin-left:4px;}
    .dv-input{width:100%;box-sizing:border-box;padding:9px 12px;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 60%,transparent);color:var(--text);font-size:13.5px;outline:none;transition:border-color .15s;}
    .dv-input:focus{border-color:color-mix(in srgb,var(--primary) 60%,var(--border));}
    .dv-hint{margin:5px 0 0;font-size:11.5px;color:var(--muted);line-height:1.4;}

    /* Toggle */
    .dv-toggle-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 40%,transparent);}
    .dv-toggle-label{font-size:13.5px;font-weight:600;color:var(--text);}
    .dv-toggle-sub{font-size:12px;color:var(--muted);margin-top:2px;}
    .dv-switch{position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0;}
    .dv-switch input{opacity:0;width:0;height:0;position:absolute;}
    .dv-switch__track{position:absolute;inset:0;border-radius:11px;background:var(--border);transition:background .2s;cursor:pointer;}
    .dv-switch input:checked + .dv-switch__track{background:var(--primary);}
    .dv-switch__thumb{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .2s;pointer-events:none;}
    .dv-switch input:checked ~ .dv-switch__thumb{transform:translateX(18px);}

    /* Module checkboxes */
    .dv-modules{display:flex;flex-wrap:wrap;gap:8px;}
    .dv-module-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 40%,transparent);cursor:pointer;font-size:13px;color:var(--text);font-weight:500;user-select:none;transition:border-color .15s,background .15s;}
    .dv-module-chip:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
    .dv-module-chip input{position:absolute;opacity:0;width:0;height:0;}
    .dv-module-chip__check{width:15px;height:15px;border-radius:4px;border:1.5px solid var(--border);display:inline-flex;align-items:center;justify-content:center;font-size:9px;color:transparent;background:transparent;flex-shrink:0;transition:all .15s;}
    .dv-module-chip.is-checked{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
    .dv-module-chip.is-checked .dv-module-chip__check{background:var(--primary);border-color:var(--primary);color:#fff;}

    /* Action buttons */
    .dv-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:20px;}
    .dv-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:650;border:1px solid var(--border);cursor:pointer;text-decoration:none;white-space:nowrap;transition:background .15s,border-color .15s;}
    .dv-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
    .dv-btn--primary:hover{background:color-mix(in srgb,var(--primary) 85%,#000);border-color:color-mix(in srgb,var(--primary) 85%,#000);}
    .dv-btn--outline{background:color-mix(in srgb,var(--card) 80%,transparent);color:var(--text);}
    .dv-btn--outline:hover{background:color-mix(in srgb,var(--primary) 10%,transparent);border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
    .dv-btn--danger{background:transparent;border-color:color-mix(in srgb,#f87171 40%,var(--border));color:color-mix(in srgb,#f87171 85%,var(--text));}
    .dv-btn--danger:hover{background:color-mix(in srgb,#f87171 10%,transparent);}
    .dv-btn[disabled]{opacity:.5;cursor:not-allowed;}

    /* Alerts */
    .dv-alert{margin:0 0 16px;padding:10px 14px;border-radius:10px;font-size:13px;line-height:1.5;border:1px solid;}
    .dv-alert--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:color-mix(in srgb,#16a34a 80%,var(--text));}
    .dv-alert--err{border-color:color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 10%,transparent);color:color-mix(in srgb,#dc2626 80%,var(--text));}

    /* Connection test result */
    .dv-test-result{display:none;margin-top:10px;padding:9px 13px;border-radius:9px;font-size:12.5px;border:1px solid var(--border);}
    .dv-test-result.is-visible{display:block;}
    .dv-test-result--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:color-mix(in srgb,#16a34a 80%,var(--text));}
    .dv-test-result--err{border-color:color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 10%,transparent);color:color-mix(in srgb,#dc2626 80%,var(--text));}

    /* Connected info */
    .dv-connected-info{display:flex;flex-wrap:wrap;gap:8px 20px;padding:12px 14px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 30%,var(--border));background:color-mix(in srgb,#22c55e 6%,transparent);margin-bottom:16px;}
    .dv-connected-info__item{font-size:12.5px;color:var(--muted);}
    .dv-connected-info__item strong{display:block;color:var(--text);font-size:13px;font-weight:600;word-break:break-all;}

    /* Secret field wrapper */
    .dv-secret-wrap{position:relative;}
    .dv-secret-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:13px;padding:2px 4px;}

    /* Grid for 2-col on wide screens */
    @media(min-width:680px){
        .dv-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
        .dv-grid-2 .dv-field--full{grid-column:1/-1;}
    }

    .dv-separator{height:1px;background:color-mix(in srgb,var(--border) 60%,transparent);margin:20px 0;}
</style>

<div class="card dv-wrap">

    @if(session('status'))
        <div class="dv-alert dv-alert--ok" role="status">
            <i class="fa fa-circle-check"></i> {{ session('status') }}
        </div>
    @endif
    @if($errors->any())
        <div class="dv-alert dv-alert--err" role="alert">
            <i class="fa fa-triangle-exclamation"></i> {{ $errors->first() }}
        </div>
    @endif

    @if(! $business)
        <div class="dv-alert dv-alert--err">
            <i class="fa fa-triangle-exclamation"></i> No business selected. Please create or select a business first.
        </div>
    @else

    <p class="dv-lead">
        Store sensitive business data — sales records, employee salaries, and payroll — on your own server instead of SociBiz cloud.
        When enabled, the selected data types are read and written to your self-hosted Data Vault. If disabled, all data uses SociBiz normally.
    </p>

    {{-- ── Connection Status Card ──────────────────────────────────────── --}}
    <div class="dv-section">
        <div class="dv-section__head">
            <div class="dv-section__icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
                <i class="fa fa-server"></i>
            </div>
            <div class="dv-section__titles">
                <h2 class="dv-section__title">Data Vault Connection</h2>
                <p class="dv-section__desc">Your self-hosted server that stores sensitive business data.</p>
            </div>
            <div>
                @if($config && $config->is_enabled)
                    <span class="dv-status dv-status--active">
                        <span class="dv-status__dot"></span> Active
                    </span>
                @elseif($config)
                    <span class="dv-status dv-status--inactive">
                        <span class="dv-status__dot"></span> Configured, disabled
                    </span>
                @else
                    <span class="dv-status dv-status--inactive">
                        <span class="dv-status__dot"></span> Not configured
                    </span>
                @endif
            </div>
        </div>

        <div class="dv-section__body">

            @if($config)
                <div class="dv-connected-info">
                    <div class="dv-connected-info__item">
                        <strong>{{ $config->vault_url }}</strong>
                        Vault URL
                    </div>
                    @if($config->label)
                        <div class="dv-connected-info__item">
                            <strong>{{ $config->label }}</strong>
                            Label
                        </div>
                    @endif
                    <div class="dv-connected-info__item">
                        <strong>{{ implode(', ', array_map('ucfirst', $config->enabled_modules ?? [])) ?: '—' }}</strong>
                        Routed modules
                    </div>
                    <div class="dv-connected-info__item">
                        <strong>{{ $config->updated_at?->diffForHumans() }}</strong>
                        Last updated
                    </div>
                </div>
            @endif

            {{-- Configuration Form --}}
            <form method="post" action="{{ route('data-vault.settings.save') }}" id="dv-form">
                @csrf

                <div class="dv-grid-2">
                    <div class="dv-field dv-field--full">
                        <label class="dv-label" for="dv-vault-url">
                            Vault URL <span>required</span>
                        </label>
                        <input
                            id="dv-vault-url"
                            name="vault_url"
                            type="url"
                            class="dv-input"
                            placeholder="https://vault.yourcompany.com"
                            value="{{ old('vault_url', $config?->vault_url) }}"
                            autocomplete="off"
                        >
                        <p class="dv-hint">The base URL of your self-hosted Data Vault server. No trailing slash.</p>
                    </div>

                    <div class="dv-field dv-field--full">
                        <label class="dv-label" for="dv-secret">
                            Shared Secret
                            @if($config?->shared_secret)
                                <span>leave blank to keep existing</span>
                            @else
                                <span>min 32 characters</span>
                            @endif
                        </label>
                        <div class="dv-secret-wrap">
                            <input
                                id="dv-secret"
                                name="shared_secret"
                                type="password"
                                class="dv-input"
                                placeholder="{{ $config?->shared_secret ? '••••••••••••••••' : 'Enter a strong random secret' }}"
                                autocomplete="new-password"
                                style="padding-right:38px;"
                            >
                            <button type="button" class="dv-secret-toggle" id="dv-secret-toggle" title="Show/hide secret" aria-label="Toggle secret visibility">
                                <i class="fa fa-eye" id="dv-secret-icon"></i>
                            </button>
                        </div>
                        <p class="dv-hint">Must match <code>VAULT_SHARED_SECRET</code> in your Data Vault <code>.env</code>. Used to sign all requests with HMAC-SHA256.</p>
                    </div>

                    <div class="dv-field">
                        <label class="dv-label" for="dv-label">
                            Label <span>optional</span>
                        </label>
                        <input
                            id="dv-label"
                            name="label"
                            type="text"
                            class="dv-input"
                            placeholder="e.g. Company HQ server"
                            value="{{ old('label', $config?->label) }}"
                        >
                    </div>
                </div>

                <div class="dv-separator"></div>

                {{-- Enabled modules --}}
                <div class="dv-field">
                    <label class="dv-label">Data modules to route to vault</label>
                    <div class="dv-modules" id="dv-modules">
                        @php
                            $currentModules = old('enabled_modules', $config?->enabled_modules ?? []);
                            $availableModules = [
                                'sales'     => ['icon' => 'fa fa-receipt',       'label' => 'Sales'],
                                'employees' => ['icon' => 'fa fa-users',         'label' => 'Employees & Salaries'],
                                'payroll'   => ['icon' => 'fa fa-money-bill-wave','label' => 'Payroll'],
                            ];
                        @endphp
                        @foreach($availableModules as $slug => $mod)
                            @php $checked = in_array($slug, (array) $currentModules, true); @endphp
                            <label class="dv-module-chip {{ $checked ? 'is-checked' : '' }}">
                                <input
                                    type="checkbox"
                                    name="enabled_modules[]"
                                    value="{{ $slug }}"
                                    {{ $checked ? 'checked' : '' }}
                                >
                                <span class="dv-module-chip__check"><i class="fa fa-check"></i></span>
                                <i class="{{ $mod['icon'] }}"></i>
                                {{ $mod['label'] }}
                            </label>
                        @endforeach
                    </div>
                    <p class="dv-hint">Only selected modules are routed to your vault. Unselected modules use SociBiz normally.</p>
                </div>

                <div class="dv-separator"></div>

                {{-- Enable toggle --}}
                <div class="dv-toggle-row">
                    <div>
                        <div class="dv-toggle-label">Enable Data Vault routing</div>
                        <div class="dv-toggle-sub">When off, all data uses SociBiz normally regardless of configuration.</div>
                    </div>
                    <label class="dv-switch">
                        <input type="hidden" name="is_enabled" value="0">
                        <input
                            type="checkbox"
                            name="is_enabled"
                            value="1"
                            id="dv-enabled"
                            {{ old('is_enabled', $config?->is_enabled) ? 'checked' : '' }}
                        >
                        <span class="dv-switch__track"></span>
                        <span class="dv-switch__thumb"></span>
                    </label>
                </div>

                <div class="dv-actions">
                    <button type="submit" class="dv-btn dv-btn--primary">
                        <i class="fa fa-floppy-disk"></i> Save Configuration
                    </button>

                    <button type="button" class="dv-btn dv-btn--outline" id="dv-test-btn">
                        <i class="fa fa-plug" id="dv-test-icon"></i>
                        <span id="dv-test-label">Test Connection</span>
                    </button>

                    @if($config)
                        <form method="post" action="{{ route('data-vault.settings.disconnect') }}" style="margin:0;" onsubmit="return confirm('Disconnect the Data Vault? All routing will revert to SociBiz.');">
                            @csrf
                            <button type="submit" class="dv-btn dv-btn--danger">
                                <i class="fa fa-link-slash"></i> Disconnect
                            </button>
                        </form>
                    @endif
                </div>
            </form>

            <div class="dv-test-result" id="dv-test-result" role="status" aria-live="polite"></div>

        </div>
    </div>

    {{-- ── How It Works Card ───────────────────────────────────────────── --}}
    <div class="dv-section">
        <div class="dv-section__head">
            <div class="dv-section__icon" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">
                <i class="fa fa-circle-info"></i>
            </div>
            <div class="dv-section__titles">
                <h2 class="dv-section__title">How it works</h2>
                <p class="dv-section__desc">Data sovereignty in three steps.</p>
            </div>
        </div>
        <div class="dv-section__body">
            <ol style="margin:0;padding:0 0 0 18px;color:var(--muted);font-size:13.5px;line-height:1.9;">
                <li><strong style="color:var(--text);">Install the Data Vault app</strong> on your own server using the standalone <code>socibiz-data-vault</code> Laravel project.</li>
                <li><strong style="color:var(--text);">Set the shared secret</strong> — copy the same value to your vault server's <code>VAULT_SHARED_SECRET</code> in <code>.env</code>. All requests are signed with HMAC-SHA256 using this secret.</li>
                <li><strong style="color:var(--text);">Select modules and enable</strong> — once enabled, SociBiz routes reads and writes for the selected modules directly to your vault server. Your sensitive data never leaves your infrastructure.</li>
            </ol>
            <p style="margin:14px 0 0;font-size:12.5px;color:var(--muted);line-height:1.55;">
                <i class="fa fa-triangle-exclamation" style="color:color-mix(in srgb,#f59e0b 70%,var(--muted));"></i>
                If your vault server is unreachable while enabled, operations will return an error rather than silently falling back to SociBiz — this protects your data sovereignty intent.
            </p>
        </div>
    </div>

    @endif
</div>

<script>
(function () {
    // ── Module chip toggle ───────────────────────────────────────────────
    document.querySelectorAll('.dv-module-chip input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            cb.closest('.dv-module-chip').classList.toggle('is-checked', cb.checked);
        });
    });

    // ── Secret show/hide ────────────────────────────────────────────────
    var secretInput = document.getElementById('dv-secret');
    var secretIcon  = document.getElementById('dv-secret-icon');
    var secretToggle = document.getElementById('dv-secret-toggle');
    if (secretToggle && secretInput) {
        secretToggle.addEventListener('click', function () {
            var isHidden = secretInput.type === 'password';
            secretInput.type = isHidden ? 'text' : 'password';
            secretIcon.className = isHidden ? 'fa fa-eye-slash' : 'fa fa-eye';
        });
    }

    // ── Test Connection ─────────────────────────────────────────────────
    var testBtn    = document.getElementById('dv-test-btn');
    var testResult = document.getElementById('dv-test-result');
    var testIcon   = document.getElementById('dv-test-icon');
    var testLabel  = document.getElementById('dv-test-label');

    if (testBtn && testResult) {
        testBtn.addEventListener('click', function () {
            testBtn.disabled = true;
            testIcon.className = 'fa fa-spinner fa-spin';
            testLabel.textContent = 'Testing…';
            testResult.className  = 'dv-test-result';
            testResult.textContent = '';

            fetch('{{ route('data-vault.settings.test') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'ok') {
                    testResult.className   = 'dv-test-result is-visible dv-test-result--ok';
                    testResult.innerHTML   = '<i class="fa fa-circle-check"></i> Connected successfully — response in ' + data.latency_ms + ' ms.';
                    testIcon.className     = 'fa fa-plug-circle-check';
                } else {
                    testResult.className   = 'dv-test-result is-visible dv-test-result--err';
                    testResult.innerHTML   = '<i class="fa fa-circle-xmark"></i> ' + (data.message || 'Connection failed.');
                    testIcon.className     = 'fa fa-plug-circle-xmark';
                }
                testLabel.textContent = 'Test Connection';
            })
            .catch(function () {
                testResult.className   = 'dv-test-result is-visible dv-test-result--err';
                testResult.innerHTML   = '<i class="fa fa-circle-xmark"></i> Request failed. Check your network.';
                testIcon.className     = 'fa fa-plug-circle-xmark';
                testLabel.textContent  = 'Test Connection';
            })
            .finally(function () {
                testBtn.disabled = false;
            });
        });
    }
})();
</script>
@endsection
