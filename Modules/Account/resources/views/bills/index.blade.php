@extends('theme::layouts.app', ['title' => 'Bills', 'heading' => 'Bills'])

@section('content')
@php
    $billCurrency = $business ? (string) (get_settings('business.currency', '', $business) ?: '') : '';
@endphp
<div class="rental-page">
    <style>
        .rental-page{max-width:none;width:100%;margin:0;box-sizing:border-box;--rf-radius:12px;--rf-radius-sm:9px;}
        .rental-hero{display:flex;flex-wrap:wrap;gap:12px 20px;justify-content:space-between;align-items:center;padding:0 2px 16px;margin-bottom:4px;border-bottom:1px solid var(--border);}
        .rental-hero__badge{display:inline-flex;align-items:center;gap:6px;width:fit-content;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary);padding:4px 10px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);}
        .rental-hero__actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center;margin-left:auto;}
        .rental-btn--ghost{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);text-decoration:none;transition:background .18s ease,border-color .18s ease,transform .18s ease;}
        .rental-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);transform:translateY(-1px);}
        .rental-btn--primary{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;box-shadow:0 8px 20px -12px color-mix(in srgb,var(--btn-bg) 55%,transparent);transition:background .18s ease,transform .18s ease;}
        .rental-btn--primary:hover{background:var(--btn-hover);color:#111827;transform:translateY(-1px);}
        .rental-body{padding:4px 0 0;}

        .rental-alert{padding:11px 14px;border-radius:12px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;line-height:1.45;border:1px solid;}
        .rental-alert i{margin-top:2px;opacity:.9;}
        .rental-alert--ok{border-color:color-mix(in srgb,#22c55e 38%,var(--border));background:linear-gradient(135deg,color-mix(in srgb,#22c55e 8%,transparent),color-mix(in srgb,var(--card) 96%,transparent));}
        .rental-alert--err{border-color:color-mix(in srgb,#f87171 42%,var(--border));background:color-mix(in srgb,#f87171 7%,transparent);}
        .rental-modal__banner{margin:0 0 12px;}

        .rental-empty{text-align:center;padding:36px 22px;color:var(--muted);border:1px dashed color-mix(in srgb,var(--primary) 24%,var(--border));border-radius:var(--rf-radius);background:linear-gradient(165deg,color-mix(in srgb,var(--primary) 7%,transparent),color-mix(in srgb,var(--card) 98%,transparent));}
        .rental-empty__ico{width:52px;height:52px;margin:0 auto 14px;display:grid;place-items:center;border-radius:16px;background:linear-gradient(145deg,color-mix(in srgb,var(--primary) 22%,transparent),color-mix(in srgb,var(--primary) 6%,transparent));color:var(--primary);font-size:20px;box-shadow:0 12px 32px -20px color-mix(in srgb,var(--primary) 40%,transparent);}
        .rental-empty h2{margin:0;font-size:16px;font-weight:800;color:var(--text);}
        .rental-empty p{margin:8px auto 0;max-width:36ch;line-height:1.5;font-size:13px;}

        .rental-cards{display:flex;flex-direction:column;gap:6px;}
        .rental-card{position:relative;display:flex;align-items:stretch;border-radius:10px;border:1px solid var(--border);overflow:hidden;background:color-mix(in srgb,var(--card) 98%,transparent);box-shadow:0 4px 18px -16px rgba(0,0,0,.35);transition:border-color .18s ease,box-shadow .18s ease;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-card{background:var(--card);}
        .rental-card:hover{border-color:color-mix(in srgb,var(--primary) 28%,var(--border));box-shadow:0 8px 24px -18px color-mix(in srgb,var(--primary) 12%,#000);}
        @keyframes rental-card-overdue-pulse{
            0%,100%{box-shadow:0 0 0 1px color-mix(in srgb,#f87171 32%,transparent),0 4px 18px -16px color-mix(in srgb,#ef4444 28%,rgba(0,0,0,.35));}
            50%{box-shadow:0 0 0 1px color-mix(in srgb,#ef4444 58%,transparent),0 8px 26px -14px color-mix(in srgb,#dc2626 34%,rgba(0,0,0,.35));}
        }
        @keyframes rental-card-ribbon-overdue-wave{
            0%,100%{background-position:0% 0%;opacity:1;}
            50%{background-position:0% 100%;opacity:.92;}
        }
        .rental-card--overdue{border-color:color-mix(in srgb,#ef4444 58%,var(--border));animation:rental-card-overdue-pulse 2.4s ease-in-out infinite;}
        /* Left stripe widens + animated gradient sweep (loan-style overdue cue, rental red palette) */
        .rental-card--overdue .rental-card__ribbon{
            width:5px;
            background:linear-gradient(180deg,#ef4444 0%,#991b1b 38%,#f87171 100%);
            background-size:100% 220%;
            animation:rental-card-ribbon-overdue-wave 1.85s ease-in-out infinite;
            box-shadow:inset -1px 0 0 color-mix(in srgb,#fecaca 35%,transparent);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-card--overdue .rental-card__ribbon{
            background:linear-gradient(180deg,#dc2626 0%,#b91c1c 42%,#fca5a5 100%);
            background-size:100% 220%;
        }
        @media (prefers-reduced-motion:reduce){
            .rental-card--overdue{animation:none;}
            .rental-card--overdue .rental-card__ribbon{animation:none;}
        }
        .rental-card__ribbon{position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(180deg,var(--primary),color-mix(in srgb,var(--primary) 42%,#1e293b));pointer-events:none;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-card__ribbon{background:linear-gradient(180deg,var(--primary),color-mix(in srgb,var(--primary) 25%,var(--text)));}
        .rental-card__hit{flex:1;min-width:0;margin-left:3px;text-decoration:none;color:inherit;display:flex;align-items:center;}
        .rental-card--overdue .rental-card__hit{margin-left:5px;}
        .rental-card__hit:focus-visible{outline:2px solid color-mix(in srgb,var(--primary) 55%,transparent);outline-offset:2px;border-radius:8px;}
        .rental-card__inner{padding:10px 8px 10px 14px;display:flex;align-items:center;gap:12px 16px;flex-wrap:wrap;width:100%;box-sizing:border-box;}
        .rental-card__tail{display:flex;align-items:center;padding:10px 12px 10px 4px;flex-shrink:0;gap:8px;border-left:1px solid color-mix(in srgb,var(--border) 70%,transparent);}
        .rental-card__main{flex:1;min-width:min(160px,100%);}
        .rental-card__titles-row{display:flex;flex-wrap:wrap;align-items:center;gap:6px;}
        .rental-card__title{margin:0;font-size:14px;font-weight:800;letter-spacing:-.02em;line-height:1.2;display:inline-flex;align-items:center;gap:6px;color:var(--text);}
        .rental-card__title .fa-building{color:var(--primary);opacity:.9;font-size:13px;}
        .rental-card__pill{display:inline-flex;align-items:center;gap:3px;margin:0;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:color-mix(in srgb,var(--primary) 72%,var(--text));padding:3px 7px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);line-height:1.2;}
        .rental-card__pill--overdue{
            border-color:color-mix(in srgb,#ef4444 58%,var(--border));
            background:color-mix(in srgb,#ef4444 18%,transparent);
            color:color-mix(in srgb,#fecaca 78%,var(--text));
            animation:rental-card-pill-overdue 2s ease-in-out infinite;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-card__pill--overdue{color:#991b1b;}
        @keyframes rental-card-pill-overdue{
            0%,100%{opacity:1;transform:scale(1);}
            50%{opacity:.9;transform:scale(1.02);}
        }
        @media (prefers-reduced-motion:reduce){
            .rental-card__pill--overdue{animation:none;}
        }
        .rental-card__meta{margin:4px 0 0;font-size:11px;line-height:1.35;color:var(--muted);}
        .rental-card__aside{display:flex;align-items:center;gap:14px;margin-left:auto;flex-wrap:wrap;text-align:right;}
        .rental-card__cost{text-align:right;min-width:7.5rem;}
        .rental-card__cost-lab{display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px;line-height:1.2;}
        .rental-card__cost-val{font-weight:800;font-size:15px;color:color-mix(in srgb,var(--primary) 45%,var(--text));letter-spacing:-.03em;line-height:1.15;font-variant-numeric:tabular-nums;}
        .rental-card__cost-val .rf-curr{font-size:9px;opacity:.75;font-weight:700;margin-right:.12em;text-transform:uppercase;vertical-align:baseline;}
        .rental-card__dates{display:flex;flex-direction:column;gap:6px;text-align:right;min-width:6.5rem;}
        .rental-card__dates-block{display:flex;flex-direction:column;}
        .rental-card__dates-lab{display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:1px;line-height:1.2;}
        .rental-card__dates-val{margin:0;font-size:11px;font-weight:700;line-height:1.25;color:var(--text);font-variant-numeric:tabular-nums;}
        .rental-card__dates-val--empty{font-weight:600;color:var(--muted);}

        .rental-btn-del{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;font-size:11px;font-weight:600;border-radius:8px;border:1px solid color-mix(in srgb,#ef4444 45%,var(--border));background:transparent;color:#f97373;cursor:pointer;transition:background .18s ease,border-color .18s ease;flex-shrink:0;}
        .rental-btn-del:hover{background:color-mix(in srgb,#ef4444 12%,transparent);border-color:color-mix(in srgb,#ef4444 55%,var(--border));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-btn-del{color:#dc2626;}

        .rental-inline-create{box-sizing:border-box;width:100%;max-width:none;margin-top:6px;padding:22px;border-radius:var(--rf-radius);border:1px solid color-mix(in srgb,var(--primary) 16%,var(--border));background:linear-gradient(160deg,color-mix(in srgb,var(--primary) 5%,transparent),var(--card));box-shadow:0 14px 44px -30px rgba(0,0,0,.38);}
        .rental-inline-create__head{margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border);display:flex;gap:14px;align-items:flex-start;}
        .rental-inline-create__head-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(145deg,var(--primary),color-mix(in srgb,var(--primary) 62%,#0f172a));color:#fff;font-size:17px;flex-shrink:0;box-shadow:0 12px 28px -14px color-mix(in srgb,var(--primary) 45%,transparent);}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-inline-create__head-icon{background:linear-gradient(145deg,var(--primary),#292524);color:#fef9c3;font-size:17px;box-shadow:0 12px 28px -14px rgba(0,0,0,.18);}
        .rental-inline-create__head h2{margin:0;font-size:18px;font-weight:800;letter-spacing:-.03em;line-height:1.2;color:var(--text);}
        .rental-inline-create__lead{margin:8px 0 0;font-size:13px;color:var(--muted);max-width:52ch;line-height:1.5;}

        .rental-form-section{margin-bottom:16px;padding:14px 16px;border-radius:var(--rf-radius-sm);border:1px solid color-mix(in srgb,var(--border) 88%,transparent);background:linear-gradient(180deg,color-mix(in srgb,var(--card) 97%,transparent),color-mix(in srgb,var(--card) 92%,transparent));box-shadow:0 8px 24px -22px rgba(0,0,0,.2);}
        .rental-form-section__head{display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:13px;font-weight:800;color:var(--text);letter-spacing:-.01em;}
        .rental-form-section__head i{color:var(--primary);width:22px;text-align:center;}
        .rental-fields-grid{display:grid;gap:12px;}@media(min-width:640px){.rental-fields-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px;}}
        .rental-field--full{grid-column:1/-1;}
        .rental-field label{display:block;margin-bottom:5px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
        .rental-hint{font-weight:500;text-transform:none;letter-spacing:0;color:var(--muted);font-size:10px;font-weight:500;}
        .rental-owner-lead{margin:0 0 12px;font-size:13px;line-height:1.5;color:var(--muted);padding:11px 13px;border-radius:10px;border:1px dashed color-mix(in srgb,var(--border) 80%,transparent);background:color-mix(in srgb,var(--bg) 25%,transparent);}

        .rental-field input,.rental-field select,.rental-field textarea{width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text);transition:border-color .15s ease,box-shadow .15s ease;}
        .rental-field textarea{min-height:72px;resize:vertical;font-family:inherit;line-height:1.45;}
        .rental-field input:focus,.rental-field select:focus,.rental-field textarea:focus{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));outline:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 16%,transparent);}
        .rental-select,.rental-page .acct-warehouse-branch-el{width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text);transition:border-color .15s ease,box-shadow .15s ease;}
        .rental-page #acct-warehouse-wrap label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
        .rental-page .acct-warehouse-branch-el:focus{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));outline:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 16%,transparent);}

        .rental-field-err{display:block;color:#f87171;font-size:12px;margin-top:5px;line-height:1.35;}
        .rental-submit-wrap{margin-top:14px;display:flex;flex-wrap:wrap;gap:14px;align-items:center;padding-top:14px;border-top:1px solid var(--border);}
        .rental-submit-wrap .linkbtn{border-radius:10px;font-weight:800;padding:11px 20px;font-size:14px;display:inline-flex;align-items:center;gap:9px;}
        .rental-submit-note{font-size:12px;color:var(--muted);max-width:40ch;line-height:1.45;}

        .rental-modal,.rental-modal *{box-sizing:border-box;}
        .rental-modal{position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;padding:max(16px,3vh) 16px calc(16px + env(safe-area-inset-bottom));overflow:auto;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .24s ease,visibility .24s ease;}
        .rental-modal.rental-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
        .rental-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.52);backdrop-filter:blur(5px);}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-modal__backdrop{background:rgba(17,24,39,.36);}
        .rental-modal__panel{position:relative;z-index:1;width:100%;max-width:820px;max-height:min(93vh,calc(100dvh - 40px));display:flex;flex-direction:column;border-radius:var(--rf-radius);border:1px solid var(--border);background:var(--card);box-shadow:0 28px 64px rgba(0,0,0,.4);margin:auto;}
        .rental-modal__head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0;background:linear-gradient(180deg,color-mix(in srgb,var(--card) 98%,transparent),color-mix(in srgb,var(--card) 92%,transparent));}
        .rental-modal__head h2{margin:0;font-size:16px;font-weight:900;letter-spacing:-.02em;}
        .rental-modal__close{width:36px;height:36px;display:grid;place-items:center;padding:0;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 90%,transparent);cursor:pointer;color:var(--text);font-size:18px;line-height:1;transition:background .18s ease,border-color .18s ease;}
        .rental-modal__close:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
        .rental-modal__body{padding:16px 18px 22px;overflow:auto;overscroll-behavior:contain;}
        html.rental-modal-open-html,html.rental-modal-open-html body{overflow:hidden;}
    </style>

    <header class="rental-hero">
        @if($business)
            <span class="rental-hero__badge"><i class="fa fa-file-invoice-dollar"></i> Bills hub</span>
        @endif
        <div class="rental-hero__actions">
            @if($business && $bills->isNotEmpty())
                <button type="button" id="bill-modal-open" class="rental-btn--primary"><i class="fa fa-plus"></i>Add bill</button>
            @endif
            <a class="rental-btn--ghost" href="{{ route('dashboard') }}"><i class="fa fa-arrow-left"></i>Overview</a>
        </div>
    </header>

    <div class="rental-body">
        @if(!$business)
            <div class="rental-empty">
                <div class="rental-empty__ico" aria-hidden="true"><i class="fa fa-briefcase"></i></div>
                <h2>No business selected</h2>
                <p>Select a business from the navbar — then log utility and recurring service bills with payment cadence.</p>
            </div>
        @else
            @if(session('status'))
                <div class="rental-alert rental-alert--ok" role="status">
                    <i class="fa fa-circle-check"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if($bills->isNotEmpty())
                <div class="rental-cards">
                    @foreach($bills as $bill)
                        @php
                            $listMetaParts = [];
                            $listMetaParts[] = 'Until '.$bill->agreement_valid_until_year;
                            if ($bill->warehouse) {
                                $listMetaParts[] = \Illuminate\Support\Str::limit((string) $bill->warehouse->name, 28);
                            }
                            if ($bill->property) {
                                $listMetaParts[] = 'Property: '.\Illuminate\Support\Str::limit((string) $bill->property->property_name, 22);
                            }
                            if ($bill->employee) {
                                $listMetaParts[] = 'Emp: '.\Illuminate\Support\Str::limit((string) $bill->employee->full_name, 22);
                            }
                            if ($bill->modification) {
                                $listMetaParts[] = 'Mod: '.\Illuminate\Support\Str::limit((string) $bill->modification->name, 22);
                            }
                            if ($bill->rental_property_related && $bill->rental) {
                                $listMetaParts[] = 'Rental: '.\Illuminate\Support\Str::limit((string) $bill->rental->property_type, 22);
                            }
                            if ($bill->department) {
                                $listMetaParts[] = 'Dept: '.\Illuminate\Support\Str::limit((string) $bill->department->name, 26);
                            }
                            if ($bill->description) {
                                $listMetaParts[] = \Illuminate\Support\Str::limit((string) $bill->description, 40);
                            }
                        @endphp
                        <article @class(['rental-card', 'rental-card--overdue' => ($billPaymentOverdue[$bill->id] ?? false)])>
                            <div class="rental-card__ribbon" aria-hidden="true"></div>
                            <a class="rental-card__hit" href="{{ route('account.bills.show', $bill) }}">
                                <div class="rental-card__inner">
                                    <div class="rental-card__main">
                                        <div class="rental-card__titles-row">
                                            <h3 class="rental-card__title"><i class="fa fa-file-invoice-dollar" aria-hidden="true"></i> {{ $bill->name }}</h3>
                                            <span class="rental-card__pill"><i class="fa fa-tag" style="opacity:.85;" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit($bill->categoryDisplayLabel(), 24) }}</span>
                                            @if($bill->isOneTime())
                                                <span class="rental-card__pill"><i class="fa fa-money-bill-wave" style="opacity:.85;" aria-hidden="true"></i>One-time</span>
                                            @else
                                                <span class="rental-card__pill"><i class="fa fa-clock" style="opacity:.85;" aria-hidden="true"></i>{{ $recurringTypes[$bill->recurring_type] ?? $bill->recurring_type }}</span>
                                            @endif
                                            @if($billPaymentOverdue[$bill->id] ?? false)
                                                <span class="rental-card__pill rental-card__pill--overdue" title="A billing date on or before today has no ledger payment logged for that date."><i class="fa fa-circle-exclamation" style="font-size:.95em;" aria-hidden="true"></i> Overdue</span>
                                            @endif
                                        </div>
                                        <p class="rental-card__meta">{{ implode(' · ', array_filter($listMetaParts)) }}</p>
                                    </div>
                                    <div class="rental-card__aside">
                                        <div class="rental-card__dates">
                                            <div class="rental-card__dates-block">
                                                <span class="rental-card__dates-lab">Due date</span>
                                                @if($bill->due_date)
                                                    <span class="rental-card__dates-val">{{ $bill->due_date->format('M j, Y') }}</span>
                                                @else
                                                    <span class="rental-card__dates-val rental-card__dates-val--empty">—</span>
                                                @endif
                                            </div>
                                            <div class="rental-card__dates-block">
                                                <span class="rental-card__dates-lab">1st installment</span>
                                                @if($bill->first_installment_due_date)
                                                    <span class="rental-card__dates-val">{{ $bill->first_installment_due_date->format('M j, Y') }}</span>
                                                @else
                                                    <span class="rental-card__dates-val rental-card__dates-val--empty">—</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="rental-card__cost" title="Amount per billing cycle">
                                            <span class="rental-card__cost-lab">Per cycle</span>
                                            <span class="rental-card__cost-val">@if($billCurrency)<span class="rf-curr">{{ $billCurrency }}</span>@endif{{ number_format((float) $bill->recurring_cost, 2, '.', ',') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <div class="rental-card__tail">
                                <form method="post" action="{{ route('account.bills.destroy', $bill) }}" style="margin:0;" onsubmit="return confirm('Remove this bill record?');">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="rental-btn-del"><i class="fa fa-trash-can" aria-hidden="true"></i>Remove</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <section class="rental-inline-create" aria-labelledby="bill-inline-title">
                    <header class="rental-inline-create__head">
                        <div class="rental-inline-create__head-icon" aria-hidden="true"><i class="fa fa-file-invoice-dollar"></i></div>
                        <div>
                            <h2 id="bill-inline-title">Add your first bill</h2>
                            <p class="rental-inline-create__lead">Utilities and services with a fixed cycle and amount — same payment schedule and ledger flow as loans and rentals.</p>
                        </div>
                    </header>
                    @include('account::bills.partials.create-form')
                </section>
            @endif

            @if($bills->isNotEmpty())
                <div id="bill-modal"
                    class="rental-modal {{ $errors->any() ? 'rental-modal--open' : '' }}"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="bill-modal-title"
                    aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
                    <div class="rental-modal__backdrop" data-bill-modal-close tabindex="-1"></div>
                    <div class="rental-modal__panel">
                        <div class="rental-modal__head">
                            <h2 id="bill-modal-title">New bill</h2>
                            <button type="button" class="rental-modal__close" data-bill-modal-close aria-label="Close">&times;</button>
                        </div>
                        <div class="rental-modal__body">
                            @include('account::bills.partials.create-form', ['billFormErrorBannerClass' => 'rental-modal__banner'])
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

@if($business && $bills->isNotEmpty())
<script>
(function () {
    const modal = document.getElementById('bill-modal');
    const openBtn = document.getElementById('bill-modal-open');
    function lockScroll(on) {
        document.documentElement.classList.toggle('rental-modal-open-html', Boolean(on));
    }
    function openModal() {
        if (!modal) return;
        modal.classList.add('rental-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lockScroll(true);
        document.getElementById('bill-name')?.focus();
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('rental-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lockScroll(false);
        openBtn?.focus();
    }
    openBtn?.addEventListener('click', openModal);
    modal?.querySelectorAll('[data-bill-modal-close]').forEach((el) =>
        el.addEventListener('click', () => closeModal()),
    );
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (!modal?.classList.contains('rental-modal--open')) return;
        closeModal();
    });
    if (modal?.classList.contains('rental-modal--open')) lockScroll(true);
})();
</script>
@endif
@endsection
