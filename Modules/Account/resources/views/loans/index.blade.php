@extends('theme::layouts.app', ['title' => 'Loan Management', 'heading' => 'Loan Management'])

@section('content')
<div class="loan-page">
    <style>
        .loan-page{max-width:none;width:100%;margin:0;box-sizing:border-box;}
        .loan-hero{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            justify-content:space-between;
            align-items:flex-start;
            padding:0 0 12px;margin-bottom:2px;border-bottom:1px solid var(--border);
        }
        .loan-hero__badge{
            display:inline-flex;
            align-items:center;
            gap:5px;
            font-size:10px;
            font-weight:700;
            letter-spacing:.06em;
            text-transform:uppercase;
            color:var(--primary);
            padding:3px 8px;
            border-radius:999px;
            border:1px solid color-mix(in srgb,var(--primary) 42%,var(--border));
            background:color-mix(in srgb,var(--primary) 10%,transparent);
        }
        .loan-hero__actions{display:flex;flex-wrap:wrap;gap:7px;align-items:center;}
        .loan-btn--ghost{
            display:inline-flex;align-items:center;gap:5px;
            padding:6px 12px;border-radius:9px;font-size:12px;font-weight:600;
            border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);
            color:var(--text);text-decoration:none;transition:border-color .18s ease,background .18s ease;
        }
        .loan-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .loan-btn--primary,.loan-btn--primary:visited{
            display:inline-flex;align-items:center;gap:6px;
            padding:7px 13px;border-radius:9px;font-size:12px;font-weight:700;
            border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));
            background:var(--btn-bg);color:#fff;cursor:pointer;text-decoration:none;
            transition:background .18s ease,color .18s ease,transform .18s ease;
        }
        .loan-btn--primary:hover{background:var(--btn-hover);color:#111827;}
        .loan-modal{
            position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;
            padding:max(12px,2.5vh) max(14px,env(safe-area-inset-right)) calc(14px + env(safe-area-inset-bottom)) max(14px,env(safe-area-inset-left));
            overflow:auto;box-sizing:border-box;
            opacity:0;visibility:hidden;pointer-events:none;
            transition:opacity .22s ease,visibility .22s ease;
        }
        .loan-modal.loan-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
        .loan-modal__backdrop{
            position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-modal__backdrop{background:rgba(17,24,39,.38);}
        .loan-modal__panel{
            position:relative;z-index:1;
            box-sizing:border-box;
            width:100%;
            max-width:820px;
            flex:0 1 auto;
            max-height:min(94vh,calc(100dvh - 48px));
            display:flex;flex-direction:column;
            border-radius:14px;border:1px solid var(--border);background:var(--card);
            box-shadow:0 20px 48px rgba(0,0,0,.32);
            margin-inline:auto;margin-bottom:auto;margin-top:auto;
        }
        .loan-modal__head{
            display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid var(--border);flex-shrink:0;
            background:color-mix(in srgb,var(--card) 95%,transparent);
        }
        .loan-modal__head h2{margin:0;font-size:15px;font-weight:800;letter-spacing:-.02em;}
        .loan-modal__close{
            width:32px;height:32px;display:grid;place-items:center;padding:0;border:1px solid var(--border);
            border-radius:9px;background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--text);cursor:pointer;font-size:17px;line-height:1;
        }
        .loan-modal__close:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .loan-modal__body{padding:12px 14px 16px;overflow:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;}
        .loan-modal__body .loan-preview{margin-bottom:10px;padding:10px 12px;}
        .loan-modal__body .loan-form-section{padding:10px 12px;margin-bottom:10px;}
        .loan-modal__banner{margin:0 0 10px;}
        .loan-inline-create{
            box-sizing:border-box;width:100%;max-width:none;
            border-radius:14px;border:1px solid var(--border);background:var(--card);
            box-shadow:0 12px 40px -28px rgba(0,0,0,.35);
            padding:14px 16px 18px;margin-top:4px;
        }
        .loan-inline-create__head{margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--border);}
        .loan-inline-create__head h2{margin:0;font-size:16px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
        .loan-inline-create__lead{margin:6px 0 0;font-size:12px;line-height:1.45;color:var(--muted);max-width:52ch;}
        .loan-inline-create .loan-preview{margin-bottom:10px;padding:10px 12px;}
        html.loan-modal-open-html,html.loan-modal-open-html body{overflow:hidden;}
        .loan-body{padding:12px 0 0;}
        .loan-alert{padding:8px 11px;border-radius:10px;font-size:12px;margin-bottom:12px;display:flex;align-items:flex-start;gap:8px;line-height:1.4;}
        .loan-alert--ok{border:1px solid color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:color-mix(in srgb,#bbf7d0 75%,var(--text));}
        .loan-alert--err{border:1px solid color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 10%,transparent);color:color-mix(in srgb,#fecaca 70%,var(--text));}
        .loan-alert i{margin-top:2px;opacity:.9;}
        .loan-empty{text-align:center;padding:22px 16px;color:var(--muted);border:1px dashed color-mix(in srgb,var(--primary) 26%,var(--border));border-radius:11px;background:color-mix(in srgb,var(--primary) 5%,transparent);}
        .loan-empty__ico{width:44px;height:44px;margin:0 auto 10px;display:grid;place-items:center;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 22%,transparent),color-mix(in srgb,var(--primary) 7%,transparent));color:var(--primary);font-size:18px;}
        .loan-empty h2{margin:0;font-size:15px;font-weight:700;color:var(--text);}
        .loan-empty p{margin:7px auto 0;max-width:36ch;color:var(--muted);font-size:13px;line-height:1.45;}
        .loan-cards{display:flex;flex-direction:column;gap:12px;margin-bottom:6px;}
        .loan-portfolio-snapshot{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(118px,1fr));
            gap:8px 12px;
            padding:11px 14px;
            border-radius:12px;
            border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border));
            background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 12%,transparent) 0%,color-mix(in srgb,var(--card) 94%,transparent) 48%,color-mix(in srgb,var(--card) 92%,#000));
            box-shadow:0 10px 32px -24px rgba(0,0,0,.42),inset 0 1px 0 color-mix(in srgb,#fff .06,transparent);
            margin-bottom:4px;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-portfolio-snapshot{background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 11%,transparent),#fff);}
        .loan-portfolio-stat{display:flex;flex-direction:column;gap:3px;}
        .loan-portfolio-stat__lbl{font-size:9px;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);font-weight:700;}
        .loan-portfolio-stat__val{font-size:15px;font-weight:800;letter-spacing:-.03em;line-height:1.15;color:var(--text);}
        .loan-portfolio-stat__hint{font-size:10px;line-height:1.32;color:var(--muted);max-width:34ch;margin-top:-1px;}

        .loan-li{
            position:relative;
            border-radius:12px;
            border:1px solid var(--border);
            background:linear-gradient(165deg,color-mix(in srgb,var(--card) 98%,transparent) 0%,color-mix(in srgb,var(--card) 91%,#000));
            box-shadow:0 12px 36px -30px rgba(0,0,0,.42);
            overflow:hidden;
            transition:border-color .22s ease,box-shadow .22s ease,transform .22s ease;
        }
        .loan-li:hover{
            border-color:color-mix(in srgb,var(--primary) 35%,var(--border));
            box-shadow:0 24px 56px -32px color-mix(in srgb,var(--primary) 22%,#000008);
            transform:translateY(-1px);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-li{background:#fff linear-gradient(180deg,#fafbff 0%,#fff);}

        @keyframes loan-li-overdue-pulse{
            0%,100%{box-shadow:0 0 0 1px color-mix(in srgb,#fb923c 28%,transparent),0 14px 42px -26px color-mix(in srgb,#ea580c 38%,#000008);}
            50%{box-shadow:0 0 0 1px color-mix(in srgb,#fb923c 55%,transparent),0 18px 52px -22px color-mix(in srgb,#f97316 42%,#000008);}
        }
        @keyframes loan-li-ribbon-wave{
            0%,100%{background-position:0% 0%;opacity:1;}
            50%{background-position:0% 100%;opacity:.92;}
        }

        .loan-li--overdue{
            border-color:color-mix(in srgb,#fb923c 58%,var(--border));
            animation:loan-li-overdue-pulse 2.4s ease-in-out infinite;
        }
        .loan-li--overdue .loan-li__ribbon{
            width:5px;
            background:linear-gradient(180deg,#f97316 0%,#ef4444 45%,#fdba74 100%);
            background-size:100% 220%;
            animation:loan-li-ribbon-wave 1.85s ease-in-out infinite;
        }
        @media (prefers-reduced-motion:reduce){
            .loan-li--overdue{animation:none;}
            .loan-li--overdue .loan-li__ribbon{animation:none;}
        }

        .loan-li__ribbon{
            position:absolute;left:0;top:0;bottom:0;width:3px;
            background:linear-gradient(180deg,var(--primary),color-mix(in srgb,var(--primary) 35%,#1e293b));
        }

        .loan-li__layout{
            display:grid;
            grid-template-columns:1fr auto;
            gap:8px 12px;
            align-items:start;
            padding:12px 12px 12px 16px;
        }
        @media (max-width:720px){
            .loan-li__layout{grid-template-columns:1fr;padding:11px 11px 11px 15px;}
        }

        .loan-li__primary{min-width:0;}
        a.loan-li__main-link{display:block;min-width:0;color:inherit;text-decoration:none;border-radius:0 11px 11px 0;cursor:pointer;outline-offset:2px;}
        a.loan-li__main-link:focus-visible{outline:2px solid color-mix(in srgb,var(--primary) 55%,transparent);}
        .loan-li__aside{display:flex;flex-direction:column;align-items:flex-end;gap:6px;margin-top:0;}

        .loan-li__header{display:flex;flex-wrap:wrap;align-items:flex-start;gap:8px;margin-bottom:2px;}

        .loan-li__icon{
            flex-shrink:0;width:34px;height:34px;display:grid;place-items:center;
            border-radius:10px;
            background:linear-gradient(142deg,var(--primary),color-mix(in srgb,var(--primary) 55%,#0f172a));
            color:#fff;font-size:14px;
            box-shadow:0 6px 16px color-mix(in srgb,var(--primary) 28%,transparent);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-li__icon{color:#fff;}

        .loan-li__titles{flex:1;min-width:0;}

        .loan-li__title{margin:0;font-size:14px;font-weight:800;letter-spacing:-.025em;line-height:1.2;color:var(--text);}

        .loan-li__bank{
            display:inline-flex;
            align-items:center;
            gap:5px;margin:0;
            font-size:11px;
            color:var(--muted);
        }
        .loan-li__bank i{opacity:.8;font-size:11px;}

        .loan-li__pill{
            display:inline-flex;
            align-items:center;
            gap:4px;padding:3px 8px;border-radius:999px;
            font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
            border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));
            background:color-mix(in srgb,var(--primary) 11%,transparent);
            color:color-mix(in srgb,var(--primary) 70%,var(--text));
            white-space:nowrap;
        }
        .loan-li__pill--overdue{
            border-color:color-mix(in srgb,#f97316 55%,var(--border));
            background:color-mix(in srgb,#f97316 16%,transparent);
            color:color-mix(in srgb,#fed7aa 75%,var(--text));
            animation:loan-li-pill-flash 2s ease-in-out infinite;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-li__pill--overdue{color:#9a3412;}
        @keyframes loan-li-pill-flash{
            0%,100%{opacity:1;transform:scale(1);}
            50%{opacity:.88;transform:scale(1.02);}
        }
        @media (prefers-reduced-motion:reduce){
            .loan-li__pill--overdue{animation:none;}
        }

        .loan-li__desc{
            margin:6px 0 0;color:var(--muted);
            font-size:11px;line-height:1.45;
            max-width:70ch;border-left:2px solid color-mix(in srgb,var(--border) 80%,transparent);
            padding-left:8px;
        }

        .loan-li__metrics{
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:6px;margin-top:9px;
        }
        @media (max-width:620px){.loan-li__metrics{grid-template-columns:1fr;}}

        .loan-li__tile{
            border-radius:9px;
            padding:8px 9px;
            border:1px solid color-mix(in srgb,var(--border) 90%,transparent);
            background:color-mix(in srgb,var(--card) 94%,transparent);
        }
        .loan-li__tile--hero{border-color:color-mix(in srgb,var(--primary) 42%,var(--border));background:linear-gradient(160deg,color-mix(in srgb,var(--primary) 14%,transparent),color-mix(in srgb,var(--card) 92%,transparent));}
        .loan-li__tile--accent{border-color:color-mix(in srgb,var(--primary) 28%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}

        .loan-li__tile-lab{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px;display:block;line-height:1.3;}
        .loan-li__tile-val{font-size:13px;font-weight:800;line-height:1.2;letter-spacing:-.025em;color:var(--text);word-break:break-word;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-li__tile-val{color:#0f172a;}
        .loan-li__tile--hero .loan-li__tile-val{color:color-mix(in srgb,var(--primary) 78%,var(--text));font-size:14px;}
        .loan-li__tile-cur{font-size:10px;font-weight:600;opacity:.75;margin-right:.12em;text-transform:uppercase;}

        .loan-li__detail-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
            gap:6px 10px;margin-top:9px;padding-top:9px;border-top:1px solid color-mix(in srgb,var(--border) 75%,transparent);
        }
        .loan-li__row{display:flex;gap:8px;align-items:flex-start;font-size:11px;line-height:1.4;color:var(--muted);}
        .loan-li__row i{width:12px;text-align:center;flex-shrink:0;margin-top:1px;font-size:11px;color:color-mix(in srgb,var(--primary) 80%,var(--muted));}
        .loan-li__row strong{color:var(--text);font-weight:600;display:block;margin-bottom:0;font-size:11px;}

        .loan-li__del-form{margin:0;}
        .loan-btn--danger{
            display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:8px;font-size:11px;font-weight:600;
            border:1px solid color-mix(in srgb,#ef4444 50%,var(--border));
            background:transparent;
            color:#f97373;
            cursor:pointer;
            transition:background .18s ease,border-color .18s ease,color .18s ease;
        }
        .loan-btn--danger:hover{
            background:color-mix(in srgb,#ef4444 16%,transparent);
            border-color:color-mix(in srgb,#ef4444 68%,var(--border));
            color:#fecaca;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-btn--danger{color:#dc2626;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-btn--danger:hover{color:#b91c1c;}
        .loan-form-section{margin-bottom:11px;padding:11px 12px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border) 80%,transparent);background:color-mix(in srgb,var(--card) 88%,transparent);box-shadow:0 6px 18px -18px rgba(0,0,0,.22);}
        .loan-form-section__head{display:flex;align-items:center;gap:7px;margin-bottom:9px;font-size:12px;font-weight:700;letter-spacing:.02em;color:var(--text);}
        .loan-form-section__head i{color:var(--primary);opacity:.95;}
        .loan-fields{display:grid;gap:10px;}
        @media (min-width:640px){.loan-fields--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 12px;}}
        .loan-field label{display:flex;justify-content:space-between;gap:8px;margin-bottom:4px;font-size:10px;font-weight:600;letter-spacing:.035em;text-transform:uppercase;color:var(--muted);}
        .loan-field input,.loan-field select,.loan-field textarea{
            width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;
            border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);outline:none;transition:border-color .15s ease,box-shadow .15s ease;
        }
        .loan-field textarea{min-height:72px;line-height:1.42;resize:vertical;font-family:inherit;}
        .loan-field input:focus,.loan-field select:focus,.loan-field textarea:focus{
            border-color:color-mix(in srgb,var(--primary) 55%,var(--border));
            box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 18%,transparent);
        }
        .loan-submit-wrap{margin-top:3px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
        .loan-submit-wrap .linkbtn{border-radius:9px;font-weight:700;padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:7px;}
        .loan-submit-note{font-size:11px;color:var(--muted);max-width:36ch;line-height:1.35;}
        .loan-muted-hint{font-weight:500;text-transform:none;letter-spacing:0;color:var(--muted);font-size:10px;}
        .loan-preview{
            margin:0 0 11px;padding:11px 12px;
            border-radius:11px;border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));
            background:linear-gradient(145deg,color-mix(in srgb,var(--primary) 10%,transparent),color-mix(in srgb,var(--card) 94%,transparent));
            box-shadow:0 10px 32px -24px color-mix(in srgb,var(--primary) 40%,transparent);
        }
        .loan-preview__head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:7px;margin-bottom:9px;}
        .loan-preview__title{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text);}
        .loan-preview__title i{color:var(--primary);}
        .loan-preview__badge{font-size:10px;color:var(--muted);font-weight:600;text-transform:none;letter-spacing:0;border:1px solid var(--border);padding:3px 7px;border-radius:999px;background:color-mix(in srgb,var(--card) 80%,transparent);}
        .loan-preview__grid{display:grid;gap:7px;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));}
        .loan-prev-dial{border-radius:9px;padding:8px 10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);}
        .loan-prev-dial__lab{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;margin-bottom:3px;}
        .loan-prev-dial__val{font-size:13px;font-weight:800;line-height:1.22;letter-spacing:-.02em;color:var(--text);word-break:break-word;}
        .loan-prev-dial--hero{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);}
        .loan-prev-dial--hero .loan-prev-dial__val{color:color-mix(in srgb,var(--primary) 88%,var(--text));font-size:14px;}
        .loan-preview__foot{margin-top:8px;font-size:10px;line-height:1.4;color:var(--muted);}
        .loan-preview--idle .loan-prev-dial__val{color:var(--muted);font-weight:600;font-size:12px;}
    </style>

    <header class="loan-hero">
        <div>
            <span class="loan-hero__badge"><i class="fa fa-hand-holding-dollar"></i> Portfolio</span>
        </div>
        <div class="loan-hero__actions">
            @if($business && $loans->isNotEmpty())
                <button type="button" id="loan-modal-open" class="loan-btn--primary"><i class="fa fa-plus"></i>Add another loan</button>
            @endif
            <a class="loan-btn--ghost" href="{{ route('dashboard') }}"><i class="fa fa-arrow-left"></i> Overview</a>
        </div>
    </header>

    <div class="loan-body">
            @if(!$business)
                <div class="loan-empty">
                    <div class="loan-empty__ico"><i class="fa fa-briefcase"></i></div>
                    <h2>No business selected</h2>
                    <p>Create or select a business from the navbar to manage loans for that entity.</p>
                </div>
            @else
                @if(session('status'))
                    <div class="loan-alert loan-alert--ok" role="status">
                        <i class="fa fa-circle-check"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if($loans->isNotEmpty())
                    <div class="loan-portfolio-snapshot" aria-label="Loan portfolio summary">
                        <div class="loan-portfolio-stat">
                            <span class="loan-portfolio-stat__lbl">Active facilities</span>
                            <span class="loan-portfolio-stat__val">{{ $loans->count() }}</span>
                            <span class="loan-portfolio-stat__hint">Shown for {{ $business?->name ?? 'business' }}</span>
                        </div>
                        <div class="loan-portfolio-stat">
                            <span class="loan-portfolio-stat__lbl">Total principal</span>
                            <span class="loan-portfolio-stat__val">
                                @if($loanCurrency)<span style="opacity:.75;font-weight:700;font-size:11px;text-transform:uppercase;">{{ $loanCurrency }}</span> @endif
                                {{ number_format($loanPortfolioTotals['principal'] ?? 0, 2, '.', ',') }}
                            </span>
                            <span class="loan-portfolio-stat__hint">Outstanding borrowed balances</span>
                        </div>
                        <div class="loan-portfolio-stat">
                            <span class="loan-portfolio-stat__lbl">Approx. monthly outflow</span>
                            <span class="loan-portfolio-stat__val">
                                @if($loanCurrency)<span style="opacity:.75;font-weight:700;font-size:11px;text-transform:uppercase;">{{ $loanCurrency }}</span> @endif
                                {{ number_format($loanPortfolioTotals['approx_monthly'] ?? 0, 2, '.', ',') }}
                            </span>
                            <span class="loan-portfolio-stat__hint">Equivalent monthly budgeting (daily ×30, yearly ÷12)</span>
                        </div>
                    </div>
                    <div class="loan-cards">
                        @foreach($loans as $loan)
                            @php
                                $s = $loanSummaries[$loan->id] ?? null;
                            @endphp
                            <article @class(['loan-li', 'loan-li--overdue' => ($loanInstallmentOverdue[$loan->id] ?? false)])>
                                <div class="loan-li__ribbon" aria-hidden="true"></div>
                                <div class="loan-li__layout">
                                    <a class="loan-li__primary loan-li__main-link" href="{{ route('account.loans.show', $loan) }}" aria-label="Open loan: {{ $loan->name }}">
                                        <header class="loan-li__header">
                                            <span class="loan-li__icon"><i class="fa fa-hand-holding-dollar"></i></span>
                                            <div class="loan-li__titles">
                                                <h2 class="loan-li__title">{{ $loan->name }}</h2>
                                                <p class="loan-li__bank"><i class="fa fa-building-columns"></i> {{ $loan->bank?->name ?? '—' }}</p>
                                            </div>
                                            @if(!empty($loanInstallmentOverdue[$loan->id]))
                                                <span class="loan-li__pill loan-li__pill--overdue" title="A due date on the schedule is in the past without a matching installment in the ledger yet."><i class="fa fa-circle-exclamation" style="font-size:.95em;"></i> Overdue</span>
                                            @endif
                                            @if(!empty($s['cadence_label']))
                                                <span class="loan-li__pill"><i class="fa fa-clock" style="font-size:.9em;"></i>{{ $s['cadence_label'] }}</span>
                                            @endif
                                        </header>

                                        @if($loan->description)
                                            <p class="loan-li__desc">{{ \Illuminate\Support\Str::limit($loan->description, 240) }}</p>
                                        @endif

                                        @if($s !== null)
                                            <div class="loan-li__metrics">
                                                <div class="loan-li__tile loan-li__tile--hero">
                                                    <span class="loan-li__tile-lab">Principal</span>
                                                    <span class="loan-li__tile-val">
                                                        @if($loanCurrency)<span class="loan-li__tile-cur">{{ $loanCurrency }}</span>@endif{{ number_format((float) $loan->borrowed_amount, 2, '.', ',') }}
                                                    </span>
                                                </div>
                                                <div class="loan-li__tile loan-li__tile--accent">
                                                    <span class="loan-li__tile-lab">Payment · per period</span>
                                                    <span class="loan-li__tile-val">
                                                        @if($loanCurrency)<span class="loan-li__tile-cur">{{ $loanCurrency }}</span>@endif{{ $s['payment_formatted'] }}
                                                    </span>
                                                </div>
                                                <div class="loan-li__tile">
                                                    <span class="loan-li__tile-lab">Budget · monthly equiv.</span>
                                                    <span class="loan-li__tile-val">
                                                        @if($loanCurrency)<span class="loan-li__tile-cur">{{ $loanCurrency }}</span>@endif{{ $s['approx_monthly_formatted'] }}
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <div class="loan-li__metrics">
                                                <div class="loan-li__tile loan-li__tile--hero">
                                                    <span class="loan-li__tile-lab">Principal</span>
                                                    <span class="loan-li__tile-val">
                                                        @if($loanCurrency)<span class="loan-li__tile-cur">{{ $loanCurrency }}</span>@endif{{ number_format((float) $loan->borrowed_amount, 2, '.', ',') }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="loan-li__detail-grid">
                                            <div class="loan-li__row">
                                                <i class="fa fa-percent"></i>
                                                <div>
                                                    <strong>Interest structure</strong>
                                                    {{ $interestRateTypes[$loan->interest_rate_type] ?? $loan->interest_rate_type }} ·
                                                    {{ rtrim(rtrim(number_format((float) $loan->interest_rate, 4, '.', ''), '0'), '.') }}{{ $loan->interest_rate_type === \Modules\Account\Models\Loan::INTEREST_RATE_PERCENTAGE ? '% APR' : ' flat fee' }}
                                                </div>
                                            </div>
                                            @if($s !== null)
                                                <div class="loan-li__row">
                                                    <i class="fa fa-list-check"></i>
                                                    <div>
                                                        <strong>Installments</strong>
                                                        {{ $s['period_count'] }} periods
                                                        <span style="opacity:.85;"> · {{ \Illuminate\Support\Str::limit($s['period_source'], 72) }}</span>
                                                    </div>
                                                </div>
                                            @endif
                                            @if($loan->first_installment_due_date || $loan->loan_ending_date)
                                                <div class="loan-li__row">
                                                    <i class="fa fa-calendar-days"></i>
                                                    <div>
                                                        <strong>Schedule</strong>
                                                        @if($loan->first_installment_due_date)
                                                            First {{ $loan->first_installment_due_date->format('M j, Y') }}
                                                        @endif
                                                        @if($loan->first_installment_due_date && $loan->loan_ending_date) · @endif
                                                        @if($loan->loan_ending_date)
                                                            Last {{ $loan->loan_ending_date->format('M j, Y') }}
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                            @if($loan->deductAccount)
                                                <div class="loan-li__row">
                                                    <i class="fa fa-wallet"></i>
                                                    <div>
                                                        <strong>Debit account</strong>
                                                        <span title="{{ $loan->deductAccount->deductOptionLabel() }}">{{ \Illuminate\Support\Str::limit($loan->deductAccount->deductOptionLabel(), 96) }}</span>
                                                    </div>
                                                </div>
                                            @endif
                                            @if($loan->remind_before_days !== null)
                                                <div class="loan-li__row">
                                                    <i class="fa fa-bell"></i>
                                                    <div>
                                                        <strong>Reminder</strong>
                                                        {{ (int) $loan->remind_before_days }} day{{ (int) $loan->remind_before_days === 1 ? '' : 's' }} before each due date
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </a>
                                    <aside class="loan-li__aside" aria-label="Actions">
                                        <form class="loan-li__del-form" method="post" action="{{ route('account.loans.destroy', $loan->id) }}" onsubmit="return confirm('Remove this loan record?');">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="loan-btn--danger"><i class="fa fa-trash-can"></i> Remove</button>
                                        </form>
                                    </aside>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <section class="loan-inline-create" aria-labelledby="loan-inline-title">
                        <header class="loan-inline-create__head">
                            <div>
                                <h2 id="loan-inline-title">Add your first loan</h2>
                                <p class="loan-inline-create__lead">Record this liability so installments and budgeting stay visible on your overview.</p>
                            </div>
                        </header>
                        @include('account::loans.partials.create-form')
                    </section>
                @endif

                @if($loans->isNotEmpty())
                <div id="loan-modal"
                    class="loan-modal {{ $errors->any() ? 'loan-modal--open' : '' }}"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="loan-modal-title"
                    aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
                    <div class="loan-modal__backdrop" data-loan-modal-close tabindex="-1"></div>
                    <div class="loan-modal__panel">
                        <div class="loan-modal__head">
                            <h2 id="loan-modal-title">Add another loan</h2>
                            <button type="button" class="loan-modal__close" data-loan-modal-close aria-label="Close dialog">&times;</button>
                        </div>
                        <div class="loan-modal__body">
                            @include('account::loans.partials.create-form', ['loanFormErrorBannerClass' => 'loan-modal__banner'])
                        </div>
                    </div>
                </div>
                @endif
            @endif
    </div>
</div>
@if($business)
<script>
(function () {
    const form = document.getElementById('loan-form');
    if (!form) return;

    const pct = '{{ \Modules\Account\Models\Loan::INTEREST_RATE_PERCENTAGE }}';
    const flatKey = '{{ \Modules\Account\Models\Loan::INTEREST_RATE_FLAT }}';

    const el = {
        principal: document.getElementById('loan-principal'),
        rateType: document.getElementById('loan-rate-type'),
        rate: document.getElementById('loan-rate'),
        recurring: document.getElementById('loan-recurring'),
        firstDue: document.getElementById('loan-first-due'),
        end: document.getElementById('loan-end'),
        periodsManual: document.getElementById('loan-preview-periods'),
        panel: document.getElementById('loan-preview'),
        source: document.getElementById('loan-preview-source'),
        foot: document.getElementById('loan-preview-foot'),
        pvPayment: document.getElementById('loan-pv-payment'),
        pvPrincipal: document.getElementById('loan-pv-principal'),
        pvInterestTotal: document.getElementById('loan-pv-interest-total'),
        pvTotalRepay: document.getElementById('loan-pv-total-repay'),
        pvN: document.getElementById('loan-pv-n'),
        pvPeriodicRate: document.getElementById('loan-pv-periodic-rate'),
    };

    const moneyFmt = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function fmtMoney(x) {
        if (!Number.isFinite(x)) return '—';
        return moneyFmt.format(x);
    }
    function trimRateLabel(v) {
        if (!Number.isFinite(v)) return '0';
        return String(Number(v.toFixed(6)));
    }
    function parseNum(inp) {
        const v = parseFloat(String(inp?.value ?? '').replace(',', '.'));
        return Number.isFinite(v) ? v : NaN;
    }
    function parseYMD(str) {
        if (!str || !/^\d{4}-\d{2}-\d{2}$/.test(str)) return null;
        const [y, m, d] = str.split('-').map(Number);
        const dt = new Date(y, m - 1, d);
        return Number.isFinite(dt.getTime()) && dt.getFullYear() === y && dt.getMonth() === m - 1 ? dt : null;
    }

    /** Inclusive payments: first installment on start, thereafter by cadence, while date <= end. */
    function formatYMD(dt) {
        const y = dt.getFullYear();
        const m = String(dt.getMonth() + 1).padStart(2, '0');
        const day = String(dt.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    /** Date of the last inclusive installment (payment 1 = firstYmd, step by cadence). */
    function lastInstallmentIso(firstYmd, recurType, periodCount) {
        const parsed = parseYMD(firstYmd);
        if (!parsed || !(periodCount >= 1)) return null;
        const d = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
        for (let step = 1; step < periodCount; step++) {
            if (recurType === 'per_day') d.setDate(d.getDate() + 1);
            else if (recurType === 'per_month') d.setMonth(d.getMonth() + 1);
            else if (recurType === 'per_year') d.setFullYear(d.getFullYear() + 1);
            else return null;
        }
        return formatYMD(d);
    }

    function assumedPeriodCountIfBlank(recurType) {
        if (recurType === 'per_month') return 12;
        if (recurType === 'per_day') return 30;
        if (recurType === 'per_year') return 5;
        return 12;
    }

    function parsedManualPeriodCount() {
        const n = parseInt(String(el.periodsManual?.value ?? '').trim(), 10);
        return Number.isFinite(n) && n > 0 ? n : null;
    }

    function syncLoanEndingFromSchedule(target) {
        const tid = target?.id ?? '';
        if (tid !== 'loan-first-due' && tid !== 'loan-recurring' && tid !== 'loan-preview-periods') {
            return;
        }
        clearAutoLoanEndingUserLock();
        maybeAutoLoanEnding();
    }

    let programmaticLoanEndUpdate = false;

    function maybeAutoLoanEnding() {
        if (!el.end || !el.firstDue) return;
        const firstYmd = el.firstDue.value || '';
        const recur = el.recurring?.value || '';

        if (!firstYmd || !recur) {
            return;
        }

        const manualN = parsedManualPeriodCount();
        const n = manualN ?? assumedPeriodCountIfBlank(recur);
        const endingIso = lastInstallmentIso(firstYmd, recur, n);
        if (!endingIso || el.end.dataset.userEdited === '1') return;

        programmaticLoanEndUpdate = true;
        el.end.value = endingIso;
        programmaticLoanEndUpdate = false;
    }

    function clearAutoLoanEndingUserLock() {
        delete el.end?.dataset?.userEdited;
    }

    /** Inclusive payments: first installment on start, thereafter by cadence, while date <= end. */
    function countPeriodsInclusive(startDate, endDate, recurType) {
        const start = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
        const end = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());
        if (end < start) return null;
        let n = 0;
        const d = new Date(start.getTime());
        while (d <= end) {
            n++;
            if (recurType === 'per_day') d.setDate(d.getDate() + 1);
            else if (recurType === 'per_month') d.setMonth(d.getMonth() + 1);
            else if (recurType === 'per_year') d.setFullYear(d.getFullYear() + 1);
            else return null;
        }
        return n > 0 ? n : null;
    }

    function periodsPerYear(recurType) {
        if (recurType === 'per_month') return 12;
        if (recurType === 'per_day') return 365;
        if (recurType === 'per_year') return 1;
        return NaN;
    }

    /** Annual nominal APR % → periodic rate decimal for this cadence */
    function periodicRateFromApr(aprPct, recurType) {
        const k = periodsPerYear(recurType);
        if (!Number.isFinite(k) || k <= 0) return NaN;
        return (aprPct / 100) / k;
    }

    /** Equal installment (compound per period); i = periodic decimal */
    function paymentAmort(principal, i, n) {
        if (n <= 0 || principal < 0) return NaN;
        if (!(i > 0)) return principal / n;
        const pow = Math.pow(1 + i, n);
        return (principal * i * pow) / (pow - 1);
    }

    function humanDuration(n, recurType) {
        if (!(n >= 1)) return '';
        if (recurType === 'per_year') return n === 1 ? '~1 yr' : '~' + n + ' yrs';
        if (recurType === 'per_month') {
            const yrs = Math.floor(n / 12);
            const mo = n % 12;
            const bits = [];
            if (yrs) bits.push(yrs === 1 ? '1 yr' : yrs + ' yrs');
            if (mo) bits.push(mo + ' mo');
            return bits.join(', ') || n + ' mo';
        }
        if (recurType === 'per_day') {
            const yApprox = Math.round((n / 365) * 10) / 10;
            return n + ' days (~' + yApprox + ' yr)';
        }
        return '';
    }

    function setIdle(msg) {
        el.panel.classList.add('loan-preview--idle');
        el.source.textContent = '';
        el.pvPrincipal.textContent = '—';
        el.pvPayment.textContent = '—';
        el.pvInterestTotal.textContent = '—';
        el.pvTotalRepay.textContent = '—';
        el.pvN.textContent = '—';
        el.pvPeriodicRate.textContent = '—';
        el.foot.innerHTML = msg || 'Enter borrowed amount and interest fields. The last installment date is derived from cadence plus estimated periods (or defaults). Adjust <strong>Recurring Settings</strong> anytime.';
    }

    function compute() {
        const principal = parseNum(el.principal);
        const rateNum = parseNum(el.rate);
        const recur = el.recurring.value;
        const type = el.rateType.value;

        if (!(principal >= 0) || !(rateNum >= 0)) {
            setIdle();
            return;
        }

        const first = parseYMD(el.firstDue?.value ?? '');
        const end = parseYMD(el.end?.value ?? '');
        let n = null;
        let sourceBadge = '';

        if (first && end) {
            n = countPeriodsInclusive(first, end, recur);
            if (!n || n <= 0) {
                el.panel.classList.add('loan-preview--idle');
                el.source.textContent = 'Dates invalid';
                setIdle('Ending date must be on or after the first installment.');
                return;
            }
            sourceBadge = 'n from calendar';
        }

        const manualRaw = parseInt(String(el.periodsManual?.value ?? '').trim(), 10);
        if ((!n || n <= 0) && Number.isFinite(manualRaw) && manualRaw > 0) {
            n = manualRaw;
            sourceBadge = 'n from estimate';
        }

        if (!(n >= 1)) {
            el.panel.classList.remove('loan-preview--idle');
            el.source.textContent = 'Awaiting term';
            el.pvPrincipal.textContent = fmtMoney(principal);
            el.pvPayment.textContent = '—';
            el.pvInterestTotal.textContent = '—';
            el.pvTotalRepay.textContent = '—';
            el.pvN.textContent = '—';
            let prLabel = '';
            if (type === pct) prLabel = 'APR ÷ cadence';
            else prLabel = 'flat model';
            el.pvPeriodicRate.textContent = '— (' + prLabel + ')';
            el.foot.innerHTML =
                '<strong>Set a term:</strong> pick first installment (we auto-fill loan ending based on cadence + periods estimate or defaults).';
            return;
        }

        el.panel.classList.remove('loan-preview--idle');
        el.source.textContent = sourceBadge;

        let payment = NaN;
        let totalInterest = NaN;
        let periodicRatePctDisplay = '';

        if (type === pct) {
            const i = periodicRateFromApr(rateNum, recur);
            if (!Number.isFinite(i)) {
                setIdle('Cannot resolve cadence for APR.');
                return;
            }
            payment = paymentAmort(principal, i, n);
            const totalPay = payment * n;
            totalInterest = totalPay - principal;
            periodicRatePctDisplay =
                rateNum !== 0 ? trimRateLabel(i * 100) + '% / period' : '0% / period';
            el.pvPeriodicRate.textContent = periodicRatePctDisplay;
            el.foot.innerHTML =
                '<strong>Percentage model:</strong> equal installments; APR is interpreted as nominal per year divided into ' +
                periodsPerYear(recur) +
                ' periodic portion(s). Total interest sums all payments minus principal.';
        } else if (type === flatKey) {
            const flatTotalInterest = principal * (rateNum / 100);
            const totalPay = principal + flatTotalInterest;
            payment = totalPay / n;
            totalInterest = flatTotalInterest;
            el.pvPeriodicRate.textContent = 'Flat fee ' + trimRateLabel(rateNum) + '% of principal (once)';
            el.foot.innerHTML =
                '<strong>Flat model:</strong> total interest = principal × (rate÷100); each period pays an equal slice of principal+interest.';
        } else {
            setIdle('Unknown rate type.');
            return;
        }

        el.pvPrincipal.textContent = fmtMoney(principal);
        el.pvPayment.textContent = fmtMoney(payment);
        el.pvInterestTotal.textContent = fmtMoney(totalInterest);
        el.pvTotalRepay.textContent = fmtMoney(principal + totalInterest);
        el.pvN.textContent =
            String(n) + (humanDuration(n, recur) ? ' (~' + humanDuration(n, recur) + ')' : '');
    }

    function markEndingDateUserChosen() {
        if (programmaticLoanEndUpdate) return;
        el.end.dataset.userEdited = '1';
    }

    el.end?.addEventListener('input', markEndingDateUserChosen);
    el.end?.addEventListener('change', markEndingDateUserChosen);

    ['input', 'change'].forEach((ev) => {
        form.addEventListener(ev, (e) => {
            syncLoanEndingFromSchedule(e.target);
            compute();
        });
    });

    const loanModalEl = document.getElementById('loan-modal');
    const loanModalOpenBtn = document.getElementById('loan-modal-open');

    function lockScrollForLoanModal(lock) {
        document.documentElement.classList.toggle('loan-modal-open-html', Boolean(lock));
    }

    function openLoanModal() {
        if (!loanModalEl) return;
        loanModalEl.classList.add('loan-modal--open');
        loanModalEl.setAttribute('aria-hidden', 'false');
        lockScrollForLoanModal(true);
        compute();
        const firstField = document.getElementById('loan-name');
        window.requestAnimationFrame(() => firstField?.focus());
    }

    function closeLoanModal() {
        if (!loanModalEl) return;
        loanModalEl.classList.remove('loan-modal--open');
        loanModalEl.setAttribute('aria-hidden', 'true');
        lockScrollForLoanModal(false);
        loanModalOpenBtn?.focus();
    }

    loanModalOpenBtn?.addEventListener('click', openLoanModal);
    loanModalEl?.querySelectorAll('[data-loan-modal-close]').forEach((node) =>
        node.addEventListener('click', () => closeLoanModal()),
    );

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (!loanModalEl?.classList.contains('loan-modal--open')) return;
        closeLoanModal();
    });

    if (loanModalEl?.classList.contains('loan-modal--open')) {
        lockScrollForLoanModal(true);
    }

    compute();
})();
</script>
@endif
@endsection
