@extends('theme::layouts.app', ['title' => $bill->name.' — Bill', 'heading' => 'Bill details'])

@section('content')
@php
    $billSettleModalShouldOpen = $errors->has('occurrence_date')
        || $errors->has('deduct_account_id')
        || $errors->has('payment_option')
        || $errors->has('partial_amount')
        || $errors->has('period_charge_total')
        || $errors->has('split_rows')
        || collect($errors->keys())->contains(function ($key) {
            return \Illuminate\Support\Str::startsWith((string) $key, 'split_rows.');
        });

    $billSettleDueHumanFromOld = old('occurrence_date')
        ? \Carbon\Carbon::parse(old('occurrence_date'))->format('M j, Y')
        : null;

    $billBillingAmountDefaultDisplay = trim((string) (($detailCurrency ?? '') !== '' ? $detailCurrency.' ' : '').number_format((float) $bill->recurring_cost, 2, '.', ','));
@endphp
<div class="bill-show">
    <style>
        .bill-show{max-width:none;width:100%;margin:0;box-sizing:border-box;--rs-radius:10px;--rs-radius-sm:8px;--rs-font:13px;--rs-font-sm:11px;--rs-font-xs:9px;--rs-glow:color-mix(in srgb,var(--primary) 22%,transparent);}
        .bill-show *{box-sizing:border-box;}

        .bill-show__hero{
            position:relative;border-radius:var(--rs-radius);overflow:hidden;margin-bottom:12px;
            border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border));
            background:linear-gradient(145deg,color-mix(in srgb,var(--primary) 14%,var(--card)) 0%,var(--card) 48%,color-mix(in srgb,var(--card) 92%,#0f172a) 100%);
            box-shadow:0 16px 42px -26px color-mix(in srgb,var(--primary) 22%,#000),0 0 0 1px color-mix(in srgb,#fff 4%,transparent) inset;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__hero{
            background:linear-gradient(165deg,#ffffff 0%,color-mix(in srgb,var(--primary) 8%,#fffefd) 40%,#f5f5f4 100%);
            box-shadow:0 16px 40px -24px rgba(0,0,0,.14);
            border-color:color-mix(in srgb,var(--primary) 22%,var(--border));
        }
        @keyframes bill-show-hero-overdue-bar{
            0%,100%{background-position:0% 0%;opacity:1;}
            50%{background-position:0% 100%;opacity:.93;}
        }
        .bill-show__hero--payment-overdue{
            border-color:color-mix(in srgb,#ef4444 48%,var(--border));
            box-shadow:0 16px 42px -26px color-mix(in srgb,#ef4444 22%,#000),0 0 0 1px color-mix(in srgb,#fff 4%,transparent) inset;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__hero--payment-overdue{
            box-shadow:0 16px 40px -24px rgba(220,38,38,.14);
            border-color:color-mix(in srgb,#ef4444 42%,var(--border));
        }
        .bill-show__hero--payment-overdue::before{
            content:"";position:absolute;left:0;top:0;bottom:0;width:5px;z-index:2;pointer-events:none;border-radius:inherit;
            background:linear-gradient(180deg,#ef4444 0%,#991b1b 38%,#f87171 100%);
            background-size:100% 220%;
            animation:bill-show-hero-overdue-bar 1.85s ease-in-out infinite;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__hero--payment-overdue::before{
            background:linear-gradient(180deg,#dc2626 0%,#b91c1c 42%,#fca5a5 100%);
            background-size:100% 220%;
        }
        @media (prefers-reduced-motion:reduce){
            .bill-show__hero--payment-overdue::before{animation:none;}
        }
        .bill-show__hero--payment-overdue .bill-show__hero-inner{padding-left:17px;}
        .bill-show__hero-glow{
            pointer-events:none;position:absolute;right:-20%;top:-40%;width:55%;height:120%;
            background:radial-gradient(ellipse at center,color-mix(in srgb,var(--primary) 18%,transparent) 0%,transparent 68%);
            opacity:.9;
        }
        .bill-show__hero-inner{position:relative;z-index:1;padding:11px 13px 13px;}

        .bill-show__hero-top{display:flex;flex-wrap:wrap;align-items:center;gap:7px 9px;justify-content:space-between;margin-bottom:8px;}
        .bill-show__back{
            display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:8px;font-size:11px;font-weight:600;
            border:1px solid color-mix(in srgb,var(--border) 88%,transparent);background:color-mix(in srgb,var(--card) 55%,transparent);
            color:var(--text);text-decoration:none;backdrop-filter:blur(8px);transition:transform .18s ease,border-color .18s ease,background .18s ease;
        }
        .bill-show__back:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 10%,var(--card));transform:translateX(-2px);}
        .bill-show__biz-chip{
            display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;font-size:9px;font-weight:600;
            letter-spacing:.035em;text-transform:uppercase;color:color-mix(in srgb,var(--primary) 78%,var(--text));
            border:1px solid color-mix(in srgb,var(--primary) 24%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);
        }
        .bill-show__biz-chip i{opacity:.8;font-size:10px;}

        .bill-show__headline{margin:0 0 8px;font-size:clamp(1.02rem,1.55vw,1.2rem);font-weight:600;letter-spacing:-.024em;line-height:1.25;color:var(--text);}
        .bill-show__pills{display:flex;flex-wrap:wrap;gap:5px;}
        .bill-show__pill{
            display:inline-flex;align-items:center;gap:3px;font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;
            padding:2px 7px;border-radius:999px;border:1px solid color-mix(in srgb,var(--border) 78%,transparent);
            background:color-mix(in srgb,var(--card) 40%,transparent);color:var(--muted);
        }
        .bill-show__pill i{color:var(--primary);font-size:9px;opacity:.85;}
        .bill-show__pill--overdue{
            border-color:color-mix(in srgb,#ef4444 58%,var(--border));
            background:color-mix(in srgb,#ef4444 16%,transparent);
            color:#f87171;
            animation:bill-show-pill-overdue 2s ease-in-out infinite;
        }
        .bill-show__pill--overdue i{color:#fecaca!important;opacity:1!important;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__pill--overdue{color:#dc2626;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__pill--overdue i{color:#b91c1c!important;}
        @keyframes bill-show-pill-overdue{
            0%,100%{opacity:1;transform:scale(1);}
            50%{opacity:.92;transform:scale(1.03);}
        }
        @media (prefers-reduced-motion:reduce){
            .bill-show__pill--overdue{animation:none;}
        }

        .bill-show__notify-bill-overdue{
            margin:0 0 11px;font-size:var(--rs-font-sm);padding:10px 12px;border-radius:var(--rs-radius);
            display:flex;align-items:flex-start;gap:10px;line-height:1.45;border:1px solid color-mix(in srgb,#f87171 48%,var(--border));
            background:color-mix(in srgb,#ef4444 12%,transparent);color:var(--text);
        }
        .bill-show__notify-bill-overdue i{margin-top:2px;color:#f87171;flex-shrink:0;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__notify-bill-overdue{background:color-mix(in srgb,#fef2f2 94%,transparent);border-color:color-mix(in srgb,#fca5a5 55%,var(--border));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__notify-bill-overdue i{color:#dc2626;}

        .bill-show__highlight{
            margin-top:10px;display:grid;gap:9px;
            grid-template-columns:1fr;align-items:stretch;
        }
        @media(min-width:640px){
            .bill-show__highlight{grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:10px;align-items:stretch;}
        }
        .bill-show__cost-card{
            border-radius:var(--rs-radius-sm);padding:10px 12px;
            border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));
            background:linear-gradient(155deg,color-mix(in srgb,var(--primary) 16%,transparent),color-mix(in srgb,var(--card) 94%,transparent));
            position:relative;overflow:hidden;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__cost-card{background:linear-gradient(155deg,color-mix(in srgb,var(--primary) 10%,#fff),#fff);}
        .bill-show__cost-card::before{
            content:"";position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:4px 0 0 4px;
            background:linear-gradient(180deg,var(--primary),color-mix(in srgb,var(--primary) 45%,#1e293b));
        }
        .bill-show__cost-lab{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px;}
        .bill-show__cost-val{
            font-size:clamp(1.05rem,2.1vw,1.26rem);font-weight:700;letter-spacing:-.028em;line-height:1.12;
            color:color-mix(in srgb,var(--primary) 34%,var(--text));font-variant-numeric:tabular-nums;
        }
        .bill-show__curr{font-size:9px;opacity:.82;font-weight:600;margin-right:.2em;text-transform:uppercase;vertical-align:super;}

        /* Key money — same tier as recurring cost, visually distinct accent */
        .bill-show__key-card{
            display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:var(--rs-radius-sm);
            border:1px solid color-mix(in srgb,#f59e0b 38%,var(--border));
            background:linear-gradient(145deg,color-mix(in srgb,#f59e0b 11%,transparent),color-mix(in srgb,var(--card) 94%,transparent));
            position:relative;overflow:hidden;
            box-shadow:0 14px 36px -28px color-mix(in srgb,#f59e0b 42%,#000);
        }
        .bill-show__key-card::before{
            content:"";position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:4px 0 0 4px;
            background:linear-gradient(180deg,#fbbf24,color-mix(in srgb,#d97706 55%,#1e293b));
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__key-card{
            background:linear-gradient(158deg,color-mix(in srgb,#fffbeb 94%,#fff),#ffffff);
            border-color:color-mix(in srgb,#fbbf24 45%,var(--border));
            box-shadow:0 12px 32px -24px rgba(245,158,11,.35);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__key-card::before{
            background:linear-gradient(180deg,#fbbf24,#d97706);
        }
        .bill-show__key-ico-wrap{
            width:32px;height:32px;border-radius:8px;flex-shrink:0;display:grid;place-items:center;
            font-size:12px;color:color-mix(in srgb,#fbbf24 85%,var(--text));
            background:color-mix(in srgb,#f59e0b 14%,transparent);
            border:1px solid color-mix(in srgb,#f59e0b 35%,var(--border));
            position:relative;z-index:1;
        }
        .bill-show__key-inner{min-width:0;flex:1;position:relative;z-index:1;}
        .bill-show__key-lab{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px;}
        .bill-show__key-val{
            font-size:clamp(1rem,1.9vw,1.14rem);font-weight:700;letter-spacing:-.03em;line-height:1.14;
            color:color-mix(in srgb,#eab308 14%,var(--text));font-variant-numeric:tabular-nums;
        }
        .bill-show__key-curr{font-size:9px;opacity:.88;font-weight:600;margin-right:.2em;text-transform:uppercase;vertical-align:super;color:color-mix(in srgb,#fbbf24 30%,var(--muted));}
        .bill-show__key-note{margin:4px 0 0;font-size:9px;font-weight:500;line-height:1.4;color:var(--muted);}

        .bill-show__mini-stats{display:flex;flex-direction:column;gap:6px;}
        .bill-show__mini{
            flex:1;display:flex;align-items:flex-start;gap:8px;padding:7px 10px;border-radius:var(--rs-radius-sm);
            border:1px solid color-mix(in srgb,var(--border) 85%,transparent);background:color-mix(in srgb,var(--card) 88%,transparent);
        }
        .bill-show__mini-ico{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;flex-shrink:0;
            background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);font-size:11px;}
        .bill-show__mini-txt{font-size:11px;font-weight:600;line-height:1.32;color:var(--text);}
        .bill-show__mini-sub{display:block;margin-top:1px;font-size:9px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;}

        .bill-show__panels{display:grid;gap:9px;}@media(min-width:900px){.bill-show__panels{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;align-items:start;}}

        .bill-show__panel{
            border-radius:var(--rs-radius);border:1px solid color-mix(in srgb,var(--border) 90%,transparent);
            background:linear-gradient(180deg,color-mix(in srgb,var(--card) 99%,transparent),color-mix(in srgb,var(--card) 94%,#0f172a05));
            box-shadow:0 8px 26px -20px rgba(0,0,0,.28);overflow:hidden;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__panel{background:linear-gradient(180deg,#fff,#fafaf9);box-shadow:0 8px 28px -20px rgba(0,0,0,.08);}
        .bill-show__panel-h{
            margin:0;padding:8px 11px;display:flex;align-items:center;gap:7px;font-size:var(--rs-font-sm);font-weight:600;letter-spacing:.01em;
            border-bottom:1px solid color-mix(in srgb,var(--border) 88%,transparent);
            background:color-mix(in srgb,var(--card) 97%,transparent);
        }
        .bill-show__panel-h i{width:24px;height:24px;display:grid;place-items:center;border-radius:7px;font-size:11px;color:var(--primary);
            background:color-mix(in srgb,var(--primary) 9%,transparent);border:1px solid color-mix(in srgb,var(--primary) 15%,var(--border));}
        .bill-show__panel-body{padding:10px 11px;}

        .bill-show__dl{display:grid;gap:8px;}@media(min-width:440px){.bill-show__dl--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 12px;}}
        .bill-show__kv{padding:6px 0;border-bottom:1px dashed color-mix(in srgb,var(--border) 70%,transparent);}
        .bill-show__kv:last-child{border-bottom:0;padding-bottom:0;}
        .bill-show__dt{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px;}
        .bill-show__dd{margin:0;font-size:var(--rs-font);font-weight:600;line-height:1.42;color:var(--text);word-break:break-word;}
        .bill-show__dd--soft{font-weight:500;color:var(--muted);font-size:var(--rs-font-sm);line-height:1.45;}
        .bill-show__dd--money{font-size:13px;font-weight:700;color:color-mix(in srgb,var(--primary) 40%,var(--text));font-variant-numeric:tabular-nums;}

        .bill-show__prose{margin:0;padding:8px 10px;border-radius:var(--rs-radius-sm);font-size:var(--rs-font-sm);line-height:1.45;color:var(--text);
            background:color-mix(in srgb,var(--primary) 4%,transparent);border-left:3px solid color-mix(in srgb,var(--primary) 45%,transparent);
            white-space:pre-wrap;margin-top:8px;}

        .bill-show__landlord-profile{display:flex;align-items:center;gap:9px;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid color-mix(in srgb,var(--border) 88%,transparent);}
        .bill-show__avatar{
            width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;
            background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 52%,#0f172a));
            box-shadow:0 12px 28px -14px color-mix(in srgb,var(--primary) 50%,transparent);flex-shrink:0;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__avatar{
            background:linear-gradient(135deg,#171717,#404040);
            color:#fde047;
            box-shadow:0 12px 28px -14px rgba(0,0,0,.22);
        }
        .bill-show__avatar-name{margin:0;font-size:var(--rs-font);font-weight:600;letter-spacing:-.015em;color:var(--text);line-height:1.22;}
        .bill-show__avatar-role{margin:2px 0 0;font-size:9px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;}

        .bill-show__contact-rows{display:flex;flex-direction:column;gap:2px;}
        .bill-show__row{
            display:flex;gap:10px;padding:8px 9px;border-radius:8px;align-items:flex-start;
            transition:background .15s ease;
        }
        .bill-show__row:hover{background:color-mix(in srgb,var(--primary) 5%,transparent);}
        .bill-show__row-ico{width:26px;height:26px;border-radius:6px;display:grid;place-items:center;flex-shrink:0;font-size:10px;color:var(--primary);
            background:color-mix(in srgb,var(--primary) 10%,transparent);border:1px solid color-mix(in srgb,var(--primary) 15%,var(--border));}
        .bill-show__row-body{min-width:0;padding-top:1px;}
        .bill-show__row-lab{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:1px;}
        .bill-show__row-val{margin:0;font-size:var(--rs-font-sm);font-weight:500;line-height:1.4;color:var(--text);word-break:break-word;}
        .bill-show__row-val a{color:color-mix(in srgb,var(--primary) 70%,var(--text));text-decoration:none;font-weight:600;border-bottom:1px solid color-mix(in srgb,var(--primary) 32%,transparent);transition:border-color .15s ease,color .15s ease;}
        .bill-show__row-val a:hover{border-bottom-color:var(--primary);color:var(--primary);}

        .bill-show__danger{
            margin-top:12px;padding:10px 12px;border-radius:var(--rs-radius);border:1px solid color-mix(in srgb,#f87171 28%,var(--border));
            background:color-mix(in srgb,#f87171 6%,transparent);display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__danger{background:color-mix(in srgb,#fef2f2 94%,transparent);}
        .bill-show__danger-copy{margin:0;max-width:42ch;font-size:var(--rs-font-sm);line-height:1.4;color:var(--muted);}
        .bill-show__danger-copy strong{display:block;color:var(--text);font-weight:600;margin-bottom:2px;font-size:var(--rs-font-sm);}
        .bill-show__del{
            display:inline-flex;align-items:center;gap:5px;padding:6px 10px;font-size:var(--rs-font-sm);font-weight:600;border-radius:8px;cursor:pointer;flex-shrink:0;
            border:1px solid color-mix(in srgb,#ef4444 50%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);color:#f97373;
            transition:background .18s ease,transform .18s ease,border-color .18s ease;
        }
        .bill-show__del:hover{background:color-mix(in srgb,#ef4444 20%,transparent);border-color:color-mix(in srgb,#ef4444 65%,var(--border));transform:translateY(-1px);}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__del{color:#b91c1c;}

        /* Tabs (CSS-only radios) */
        .bill-show__tab-input{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
        .bill-show__tabs{margin-top:11px;}
        .bill-show__tablist{
            display:flex;flex-wrap:wrap;gap:5px;padding:3px;border-radius:var(--rs-radius);
            border:1px solid color-mix(in srgb,var(--border) 88%,transparent);
            background:color-mix(in srgb,var(--card) 94%,transparent);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__tablist{background:#f5f5f4;}
        .bill-show__tab-btn{
            flex:1 1 auto;min-width:0;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:5px;
            padding:7px 10px;border-radius:var(--rs-radius-sm);font-size:10px;font-weight:600;letter-spacing:.02em;
            color:var(--muted);cursor:pointer;user-select:none;border:1px solid transparent;transition:background .15s ease,color .15s ease,border-color .15s ease,box-shadow .15s ease;
        }
        .bill-show__tab-btn i{font-size:10px;opacity:.85;}
        .bill-show__tab-btn:hover{color:var(--text);background:color-mix(in srgb,var(--primary) 8%,transparent);}
        #bill-show-tab-overview:checked ~ .bill-show__tablist label[for="bill-show-tab-overview"],
        #bill-show-tab-transaction:checked ~ .bill-show__tablist label[for="bill-show-tab-transaction"]{
            color:color-mix(in srgb,var(--primary) 72%,var(--text));
            background:color-mix(in srgb,var(--primary) 14%,var(--card));
            border-color:color-mix(in srgb,var(--primary) 28%,var(--border));
            box-shadow:0 1px 0 color-mix(in srgb,#fff 8%,transparent) inset;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) #bill-show-tab-overview:checked ~ .bill-show__tablist label[for="bill-show-tab-overview"],
        :is(html[data-theme="light"],html[data-theme="light_blue"]) #bill-show-tab-transaction:checked ~ .bill-show__tablist label[for="bill-show-tab-transaction"]{
            background:#fff;color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.06);
        }
        .bill-show__tabpanel{display:none;margin-top:10px;}
        #bill-show-tab-overview:checked ~ .bill-show__tabpanel--overview,
        #bill-show-tab-transaction:checked ~ .bill-show__tabpanel--transaction{display:block;}
        .bill-show__highlight{margin-top:0;}
        .bill-show__empty-muted{margin:0;font-size:var(--rs-font-sm);line-height:1.42;color:var(--muted);padding:10px;border-radius:var(--rs-radius-sm);
            border:1px dashed color-mix(in srgb,var(--border) 75%,transparent);background:color-mix(in srgb,var(--card) 92%,transparent);}
        .bill-show__overview-stack{display:flex;flex-direction:column;gap:10px;width:100%;}
        .bill-show__countdown{
            border-radius:var(--rs-radius);border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));
            background:linear-gradient(165deg,color-mix(in srgb,var(--primary) 8%,var(--card)),color-mix(in srgb,var(--card) 96%,transparent));
            overflow:hidden;box-shadow:0 10px 32px -24px color-mix(in srgb,var(--primary) 32%,#000);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__countdown{
            background:linear-gradient(165deg,color-mix(in srgb,var(--primary) 6%,#fff),#fafaf9);
            box-shadow:0 12px 32px -24px rgba(0,0,0,.08);
        }
        .bill-show__countdown-top{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 82%,transparent);}
        .bill-show__countdown-kicker{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
        .bill-show__count-num{margin:3px 0 0;line-height:1.05;font-size:clamp(1.2rem,2.6vw,1.42rem);font-weight:700;letter-spacing:-.03em;color:color-mix(in srgb,var(--primary) 45%,var(--text));font-variant-numeric:tabular-nums;}
        .bill-show__count-num small{display:block;margin-top:3px;font-size:var(--rs-font-xs);font-weight:600;letter-spacing:0;color:var(--muted);text-transform:none;}
        .bill-show__count-next{margin:0;max-width:24ch;text-align:right;font-size:var(--rs-font-xs);line-height:1.38;color:var(--muted);font-weight:500;}
        .bill-show__count-next strong{display:block;color:var(--text);font-size:var(--rs-font-sm);margin-bottom:2px;font-weight:600;}
        .bill-show__meter-wrap{padding:0 12px 10px;}
        .bill-show__meter-lab{display:flex;justify-content:space-between;align-items:center;font-size:8px;font-weight:600;color:var(--muted);margin-bottom:5px;letter-spacing:.035em;text-transform:uppercase;}
        .bill-show__meter{
            height:7px;border-radius:999px;overflow:hidden;
            background:color-mix(in srgb,var(--border) 70%,transparent);
            border:1px solid color-mix(in srgb,var(--border) 88%,transparent);
        }
        .bill-show__meter-fill{height:100%;border-radius:inherit;width:0;background:linear-gradient(90deg,color-mix(in srgb,var(--primary) 75%,var(--text)),var(--primary));transition:width .35s ease;}
        .bill-show__meter--hot .bill-show__meter-fill{background:linear-gradient(90deg,#f59e0b,#ef4444);}
        .bill-show__meter--overdue .bill-show__meter-fill{background:linear-gradient(90deg,#f87171,#b91c1c);}

        .bill-show__countdown--overdue{
            border-color:color-mix(in srgb,#ef4444 48%,var(--border));
            background:linear-gradient(165deg,color-mix(in srgb,#ef4444 12%,var(--card)),color-mix(in srgb,var(--card) 96%,transparent));
            box-shadow:0 12px 36px -22px color-mix(in srgb,#ef4444 35%,#000);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__countdown--overdue{
            background:linear-gradient(165deg,color-mix(in srgb,#fef2f2 94%,transparent),#fff);
            box-shadow:0 12px 34px -22px rgba(220,38,38,.22);
        }
        .bill-show__countdown--overdue .bill-show__countdown-kicker{color:#f87171;font-weight:700;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__countdown--overdue .bill-show__countdown-kicker{color:#dc2626;}
        .bill-show__count-num--overdue{color:#f87171!important;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__count-num--overdue{color:#b91c1c!important;}
        .bill-show__count-num--overdue small{color:color-mix(in srgb,#fecaca 75%,var(--muted));}
        @keyframes bill-show-count-overdue-flash{
            0%,100%{filter:brightness(1);}
            50%{filter:brightness(1.12);}
        }
        .bill-show__countdown--overdue .bill-show__count-num--overdue{animation:bill-show-count-overdue-flash 2.1s ease-in-out infinite;}
        @media (prefers-reduced-motion:reduce){
            .bill-show__countdown--overdue .bill-show__count-num--overdue{animation:none;}
        }

        .bill-show__tx-h{margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
        .bill-show__tx-lead{margin:0 0 10px;font-size:11px;line-height:1.45;color:var(--muted);max-width:78ch;}
        .bill-show__tx-lead strong{color:var(--text);font-weight:600;}
        .bill-show__tx-scroll{max-height:400px;overflow:auto;border:1px solid var(--border);border-radius:var(--rs-radius);}
        .bill-show__tx-table{width:100%;border-collapse:collapse;font-size:12px;}
        .bill-show__tx-table th{text-align:left;padding:8px 10px;background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:1;}
        .bill-show__tx-table td{padding:8px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 75%,transparent);vertical-align:top;}
        .bill-show__tx-table tr:last-child td{border-bottom:none;}
        .bill-show__tx-amt{font-weight:800;font-variant-numeric:tabular-nums;}
        .bill-show__tx-empty{padding:18px;text-align:center;color:var(--muted);font-size:12px;}
        .bill-show__tx-status{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:3px 7px;border-radius:999px;border:1px solid var(--border);}
        .bill-show__tx-status--paid{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#bbf7d0 70%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__tx-status--paid{color:#166534;}
        .bill-show__tx-status--open{border-color:color-mix(in srgb,var(--border) 90%,transparent);background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--muted);}
        .bill-show__tx-status--late{border-color:color-mix(in srgb,#ef4444 52%,var(--border));background:color-mix(in srgb,#ef4444 14%,transparent);color:color-mix(in srgb,#fecaca 78%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__tx-status--late{color:#991b1b;}
        @keyframes bill-show-tx-row-late{
            0%,100%{background-color:color-mix(in srgb,#ef4444 9%,transparent);}
            50%{background-color:color-mix(in srgb,#dc2626 13%,transparent);}
        }
        .bill-show__tx-table tr.bill-show__tx-row--late > td{background-color:color-mix(in srgb,#ef4444 9%,transparent);animation:bill-show-tx-row-late 2.15s ease-in-out infinite;border-left:none;}
        .bill-show__tx-table tr.bill-show__tx-row--late > td:first-child{box-shadow:inset 3px 0 0 #dc2626;}
        @media (prefers-reduced-motion:reduce){
            .bill-show__tx-table tr.bill-show__tx-row--late > td{animation:none;}
        }
        .bill-show__tx-paid{margin:0;font-size:11px;line-height:1.38;color:var(--text);}
        .bill-show__tx-paid strong{font-weight:700;display:block;margin-bottom:2px;}
        .bill-show__tx-paid-meta{display:block;font-size:10px;color:var(--muted);margin-top:2px;font-variant-numeric:tabular-nums;}
        .bill-show__tx-cell-actions{vertical-align:middle;width:1%;white-space:nowrap;}
        .bill-show__tx-actions{display:flex;flex-wrap:wrap;gap:5px;}
        .bill-show__tx-actions--stack{flex-direction:column;align-items:stretch;gap:7px;width:100%;}
        .bill-settle-payopt{display:flex;flex-direction:column;gap:10px;margin:14px 0;}
        .bill-settle-payopt label.bill-settle-payopt-card{display:flex;align-items:flex-start;gap:10px;margin:0;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border) 86%,transparent);cursor:pointer;line-height:1.35;background:color-mix(in srgb,var(--card) 70%,transparent);}
        .bill-settle-payopt input{margin-top:3px;width:17px;height:17px;accent-color:var(--primary);cursor:pointer;}
        .bill-settle-payopt-title{display:block;font-size:13px;font-weight:800;color:var(--text);}
        .bill-settle-payopt-hint{display:block;margin:3px 0 0;font-size:11px;font-weight:500;color:var(--muted);}
        .bill-settle-split-row{display:grid;grid-template-columns:1fr minmax(5rem,7rem);gap:10px;align-items:end;margin-bottom:10px;}@media(max-width:460px){.bill-settle-split-row{grid-template-columns:1fr;}}
        .bill-receipt-breakdown{list-style:none;margin:10px 0 0;padding:0;display:none;}
        .bill-receipt-breakdown li{font-size:12px;line-height:1.45;color:var(--muted);padding:8px 0;border-bottom:1px solid color-mix(in srgb,var(--border) 75%,transparent);}
        .bill-receipt-breakdown li:last-child{border-bottom:none;}
        .bill-show__tx-btn{
            display:inline-flex;align-items:center;justify-content:center;gap:4px;padding:4px 8px;font-size:10px;font-weight:700;
            border-radius:7px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;font-family:inherit;
        }
        .bill-show__tx-btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .bill-show__tx-btn:disabled{opacity:.45;cursor:not-allowed;}
        .bill-show__tx-btn--go{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
        .loan-show-modal{
            position:fixed;inset:0;z-index:140;display:flex;justify-content:center;align-items:flex-start;
            padding:max(14px,2.8vh) 14px calc(14px + env(safe-area-inset-bottom));overflow:auto;box-sizing:border-box;
            opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;
        }
        .loan-show-modal.loan-show-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
        .loan-show-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.54);backdrop-filter:blur(3px);}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-show-modal__backdrop{background:rgba(17,24,39,.32);}
        .loan-show-modal__panel{
            position:relative;z-index:1;width:100%;max-width:440px;background:var(--card);border:1px solid var(--border);
            border-radius:14px;box-shadow:0 22px 50px rgba(0,0,0,.32);overflow:hidden;display:flex;flex-direction:column;max-height:min(92vh,720px);
        }
        #bill-settle-modal .loan-show-modal__panel{
            width:min(70vw,calc(100vw - 24px));max-width:min(70vw,calc(100vw - 24px));max-height:min(93vh,900px);border-radius:16px;
        }
        #bill-settle-modal .loan-show-modal__head{padding:10px 14px;}
        #bill-settle-modal .loan-show-modal__head h2{font-size:14px;font-weight:800;}
        #bill-settle-modal .loan-show-modal__body{padding:12px 14px 14px;font-size:12px;line-height:1.42;}
        #bill-settle-modal .loan-show-modal__lbl{margin:8px 0 4px;font-size:10px;}
        #bill-settle-modal .loan-show-modal__summ{padding:9px 11px;margin-bottom:2px;}
        #bill-settle-modal .loan-show-modal__summ strong{font-size:16px;margin-top:2px;}
        #bill-settle-modal .loan-show-modal__submit{margin-top:12px;padding:9px;font-size:13px;}
        #bill-settle-modal select,
        #bill-settle-modal input[type=text],
        #bill-settle-modal input[type=number]{
            width:100%;box-sizing:border-box;min-height:38px;padding:9px 11px;font-size:13px;line-height:1.25;border-radius:9px;
            border:1px solid color-mix(in srgb,var(--border) 88%,transparent);background:var(--card);color:var(--text);
            box-shadow:0 1px 0 color-mix(in srgb,#fff 6%,transparent) inset;
            transition:border-color .15s ease,box-shadow .15s ease;
        }
        #bill-settle-modal select:focus,
        #bill-settle-modal input[type=text]:focus,
        #bill-settle-modal input[type=number]:focus{
            outline:none;border-color:color-mix(in srgb,var(--primary) 42%,var(--border));
            box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 28%,transparent);
        }
        #bill-settle-modal .bill-settle-payopt{margin:12px 0;gap:8px;}
        #bill-settle-modal .bill-settle-payopt label.bill-settle-payopt-card{padding:9px 11px;border-radius:10px;}
        #bill-settle-modal .bill-settle-payopt-title{font-size:12px;font-weight:800;}
        #bill-settle-modal .bill-settle-payopt-hint{font-size:11px;line-height:1.38;margin-top:2px;}
        #bill-settle-modal .bill-settle-payopt input{margin-top:2px;width:16px;height:16px;}
        #bill-settle-modal .bill-settle-split-row{grid-template-columns:1fr minmax(6rem,8.5rem);gap:10px;}
        #bill-settle-modal #bill-settle-due-display{font-size:13px;font-weight:800;}
        #bill-settle-modal .bill-settle-declaration .loan-show-modal__lbl{margin-top:8px;}
        #bill-settle-modal .bill-settle-declaration input[type=number]{margin-top:4px;}
        #bill-settle-modal .bill-settle-help{display:block;margin-top:5px;font-size:10px;color:var(--muted);line-height:1.4;}
        #bill-settle-modal .bill-settle-help--split{margin:4px 0 8px;}
        #bill-settle-modal .bill-settle-partial-block{margin-top:10px;}
        #bill-settle-modal .bill-settle-split-block{margin-top:6px;}
        #bill-settle-modal .bill-settle-payopt-legend{margin-bottom:6px;}
        #bill-settle-modal .bill-settle-footnote{margin:8px 0 0;font-size:9px;color:var(--muted);line-height:1.45;}
        #bill-settle-modal .bill-settle-submit-btn{margin-top:12px;}
        .loan-show-modal__head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:11px 14px;border-bottom:1px solid var(--border);}
        .loan-show-modal__head h2{margin:0;font-size:14px;font-weight:800;color:var(--text);}
        .loan-show-modal__close{width:31px;height:31px;display:grid;place-items:center;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--text);cursor:pointer;font-size:18px;line-height:1;}
        .loan-show-modal__body{padding:12px 14px 14px;font-size:12px;overflow:auto;line-height:1.45;}
        .loan-show-modal__lbl{display:block;margin:8px 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.055em;color:var(--muted);}
        .loan-show-modal__summ{padding:10px 11px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border) 80%,transparent);background:color-mix(in srgb,var(--primary) 6%,transparent);margin-bottom:4px;font-variant-numeric:tabular-nums;}
        .loan-show-modal__summ strong{font-size:17px;display:block;color:var(--text);margin-top:3px;font-weight:800;}
        .loan-show-modal select,.loan-show-modal input[type=text]{
            width:100%;box-sizing:border-box;padding:8px 9px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);
        }
        .loan-show-modal__submit{width:100%;margin-top:12px;padding:9px;border-radius:9px;font-size:13px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;}
        .loan-show-modal__submit:hover{background:var(--btn-hover);color:#111827;}
        html.loan-show-modal-html-open, html.loan-show-modal-html-open body{overflow:hidden;}
        .loan-show-receipt-toolbar{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px;margin-bottom:4px;}
        .loan-show-copy-toast{font-size:11px;margin-top:8px;color:color-mix(in srgb,#22c55e 70%,var(--muted));min-height:16px;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .loan-show-copy-toast{color:#166534;}
        .bill-show__saved-banner{
            margin:0 0 12px;padding:10px 12px;border-radius:var(--rs-radius);font-size:12px;line-height:1.45;
            display:flex;align-items:flex-start;gap:8px;border:1px solid color-mix(in srgb,#22c55e 45%,var(--border));
            background:color-mix(in srgb,#22c55e 10%,transparent);color:var(--text);
        }
        .bill-show__saved-banner i{margin-top:2px;color:#4ade80;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-show__saved-banner i{color:#15803d;}
    </style>

    <header @class(['bill-show__hero', 'bill-show__hero--payment-overdue' => ! empty($billPaymentOverdue)])>
        <div class="bill-show__hero-glow" aria-hidden="true"></div>
        <div class="bill-show__hero-inner">
            <div class="bill-show__hero-top">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                    <a class="bill-show__back" href="{{ route('account.bills.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i>All bills</a>
                    @if(isset($business) && $business)
                        <a class="bill-show__back" href="{{ route('account.bills.edit', $bill) }}" title="Edit bill"><i class="fa fa-pen-to-square" aria-hidden="true"></i>Edit</a>
                    @endif
                </div>
                @if($bill->business)
                    <span class="bill-show__biz-chip"><i class="fa fa-briefcase" aria-hidden="true"></i>{{ $bill->business->name }}</span>
                @endif
            </div>
            <h1 class="bill-show__headline">{{ $bill->name }}</h1>
            <div class="bill-show__pills">
                @if(!empty($billPaymentOverdue))
                    <span class="bill-show__pill bill-show__pill--overdue"><i class="fa fa-circle-exclamation" aria-hidden="true"></i>Overdue</span>
                @endif
                <span class="bill-show__pill"><i class="fa fa-tag" aria-hidden="true"></i>{{ $bill->categoryDisplayLabel() }}</span>
                <span class="bill-show__pill"><i class="fa fa-rotate" aria-hidden="true"></i>{{ $paymentModes[$bill->payment_mode] ?? $bill->payment_mode }}</span>
                @if($bill->isOneTime())
                    <span class="bill-show__pill"><i class="fa fa-money-bill-wave" aria-hidden="true"></i>One-time</span>
                @else
                    <span class="bill-show__pill"><i class="fa fa-clock" aria-hidden="true"></i>{{ $recurringTypes[$bill->recurring_type] ?? $bill->recurring_type }}</span>
                    <span class="bill-show__pill"><i class="fa fa-calendar-days" aria-hidden="true"></i>Through {{ $bill->agreement_valid_until_year }}</span>
                @endif
                @if($bill->warehouse)
                    <span class="bill-show__pill"><i class="fa fa-code-branch" aria-hidden="true"></i>{{ $bill->warehouse->name }}</span>
                @endif
                @if($bill->property)
                    <span class="bill-show__pill"><i class="fa fa-building" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit($bill->property->property_name, 42) }}</span>
                @endif
                @if($bill->employee)
                    <span class="bill-show__pill"><i class="fa fa-user" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit($bill->employee->full_name, 42) }}</span>
                @endif
                @if($bill->modification)
                    <span class="bill-show__pill"><i class="fa fa-screwdriver-wrench" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit($bill->modification->name, 42) }}</span>
                @endif
                @if($bill->department)
                    <span class="bill-show__pill"><i class="fa fa-users" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit($bill->department->name, 42) }}</span>
                @endif
                @if($bill->rental_property_related && $bill->rental)
                    <a href="{{ route('account.rentals.show', $bill->rental) }}" class="bill-show__pill" style="text-decoration:none;color:inherit;"><i class="fa fa-building" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit($bill->rental->property_type, 42) }}</a>
                @elseif($bill->rental_property_related)
                    <span class="bill-show__pill"><i class="fa fa-building" aria-hidden="true"></i>Rental (removed)</span>
                @endif
                @if($bill->due_date)
                    <span class="bill-show__pill"><i class="fa fa-calendar-day" aria-hidden="true"></i>Due {{ $bill->due_date->format('M j, Y') }}</span>
                @endif
                @if($bill->first_installment_due_date)
                    <span class="bill-show__pill"><i class="fa fa-receipt" aria-hidden="true"></i>First installment {{ $bill->first_installment_due_date->format('M j, Y') }}</span>
                @endif
            </div>
        </div>
    </header>

    @if(session('status'))
        <div class="bill-show__saved-banner" role="status"><i class="fa fa-circle-check" aria-hidden="true"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="bill-show__tabs">
        <input type="radio" name="bill-show-tab" id="bill-show-tab-overview" class="bill-show__tab-input" checked>
        <input type="radio" name="bill-show-tab" id="bill-show-tab-transaction" class="bill-show__tab-input">

        <div class="bill-show__tablist" role="tablist" aria-label="Bill detail sections">
            <label id="bill-show-tab-label-overview" for="bill-show-tab-overview" class="bill-show__tab-btn" role="tab"><i class="fa fa-layer-group" aria-hidden="true"></i>Overview</label>
            <label id="bill-show-tab-label-transaction" for="bill-show-tab-transaction" class="bill-show__tab-btn" role="tab"><i class="fa fa-money-bill-wave" aria-hidden="true"></i>Transaction details</label>
        </div>

        <div class="bill-show__tabpanel bill-show__tabpanel--overview" role="tabpanel" id="bill-show-panel-overview" aria-labelledby="bill-show-tab-label-overview">
            <div class="bill-show__overview-stack">
            @if(!empty($billPaymentOverdue))
                <div class="bill-show__notify-bill-overdue" role="alert">
                    <i class="fa fa-circle-exclamation" aria-hidden="true"></i>
                    <span><strong>Unpaid billing due</strong> At least one scheduled bill date on or before today has no ledger payment recorded for that date. Log the payment so your schedule stays accurate.</span>
                </div>
            @endif
            @if($nextPaymentInsight)
                <section @class([
                    'bill-show__countdown',
                    'bill-show__countdown--overdue' => ! empty($billPaymentOverdue),
                ]) aria-labelledby="bill-countdown-heading">
                    <div class="bill-show__countdown-top">
                        <div>
                            <span class="bill-show__countdown-kicker" id="bill-countdown-heading">Next bill payment</span>
                            <div @class([
                                'bill-show__count-num',
                                'bill-show__count-num--overdue' => ! empty($billPaymentOverdue),
                            ]) aria-live="polite">
                                @if($nextPaymentInsight['days_until'] === 0)
                                    Today <small>payment due</small>
                                @elseif($nextPaymentInsight['days_until'] === 1)
                                    1 <small>day left</small>
                                @else
                                    {{ $nextPaymentInsight['days_until'] }} <small>days left</small>
                                @endif
                            </div>
                        </div>
                        <div>
                            <p class="bill-show__count-next">
                                <strong>{{ $nextPaymentInsight['next_date']->format('M j, Y') }}</strong>
                                <span style="display:block;margin-top:4px;">{{ $recurringTypes[$bill->recurring_type] ?? '' }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="bill-show__meter-wrap">
                        <div class="bill-show__meter-lab"><span>Approaching due date</span><span>{{ $nextPaymentInsight['progress_percent'] }}%</span></div>
                        <div @class([
                            'bill-show__meter',
                            'bill-show__meter--overdue' => ! empty($billPaymentOverdue),
                            'bill-show__meter--hot' => empty($billPaymentOverdue) && ($nextPaymentInsight['progress_percent'] ?? 0) >= 85,
                        ]) role="progressbar"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ (int) round($nextPaymentInsight['progress_percent']) }}"
                            aria-label="Progress within the countdown window until the next bill payment">
                            <div class="bill-show__meter-fill" style="width: {{ $nextPaymentInsight['progress_percent'] }}%;"></div>
                        </div>
                    </div>
                </section>
            @else
                <div class="bill-show__panel" style="box-shadow:none;">
                    <div class="bill-show__panel-body">
                        <p class="bill-show__empty-muted" style="border:none;background:transparent;padding:4px 0;"><strong style="display:block;color:var(--text);margin-bottom:3px;font-weight:600;font-size:var(--rs-font-sm);">No countdown yet</strong>Add a due date or first installment date under <strong>Transaction details</strong> to estimate the next bill payment.</p>
                    </div>
                </div>
            @endif
            <div class="bill-show__highlight">
                <div class="bill-show__cost-card">
                    <div class="bill-show__cost-lab">{{ $bill->isOneTime() ? 'Payment amount' : 'Recurring cost' }}</div>
                    <div class="bill-show__cost-val">
                        @if($detailCurrency)<span class="bill-show__curr">{{ $detailCurrency }}</span>@endif{{ number_format((float) $bill->recurring_cost, 2, '.', ',') }}
                    </div>
                    <div class="bill-show__mini-sub" style="margin-top:4px;opacity:.88;">
                        @if($bill->isOneTime())
                            One-time bill
                        @else
                            Per billing period · {{ $recurringTypes[$bill->recurring_type] ?? $bill->recurring_type }}
                        @endif
                    </div>
                </div>
                <div class="bill-show__mini-stats">
                    @if($bill->remind_before_days !== null && (int) $bill->remind_before_days > 0)
                        <div class="bill-show__mini">
                            <span class="bill-show__mini-ico" aria-hidden="true"><i class="fa fa-bell"></i></span>
                            <div>
                                <span class="bill-show__mini-txt">{{ (int) $bill->remind_before_days }} day{{ (int) $bill->remind_before_days === 1 ? '' : 's' }} ahead</span>
                                <span class="bill-show__mini-sub">Reminder before period end</span>
                            </div>
                        </div>
                    @endif
                    @if($bill->remind_before_days === null || (int) $bill->remind_before_days <= 0)
                        <div class="bill-show__mini">
                            <span class="bill-show__mini-ico" aria-hidden="true"><i class="fa fa-circle-info"></i></span>
                            <div>
                                <span class="bill-show__mini-txt">Open <strong>Transaction details</strong> for billing schedule, ledger, and recording payments.</span>
                                <span class="bill-show__mini-sub">Quick summary</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            </div>
        </div>

        <div class="bill-show__tabpanel bill-show__tabpanel--transaction" role="tabpanel" id="bill-show-panel-transaction" aria-labelledby="bill-show-tab-label-transaction">
            <div class="bill-show__panels" style="grid-template-columns:1fr;">
                <section class="bill-show__panel" aria-labelledby="bill-amounts-heading">
                    <h2 class="bill-show__panel-h" id="bill-amounts-heading"><i class="fa fa-coins" aria-hidden="true"></i>Bill &amp; amounts</h2>
                    <div class="bill-show__panel-body">
                        <dl class="bill-show__dl bill-show__dl--2">
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Name</dt>
                                <dd class="bill-show__dd">{{ $bill->name }}</dd>
                            </div>
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Type</dt>
                                <dd class="bill-show__dd">{{ $bill->categoryDisplayLabel() }}</dd>
                            </div>
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Payment</dt>
                                <dd class="bill-show__dd">{{ $paymentModes[$bill->payment_mode] ?? $bill->payment_mode }}</dd>
                            </div>
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Assigned property</dt>
                                <dd class="{{ $bill->property ? 'bill-show__dd' : 'bill-show__dd bill-show__dd--soft' }}">
                                    {{ $bill->property?->property_name ?: 'Not assigned' }}
                                </dd>
                            </div>
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Assigned employee</dt>
                                <dd class="{{ $bill->employee ? 'bill-show__dd' : 'bill-show__dd bill-show__dd--soft' }}">
                                    {{ $bill->employee?->full_name ?: 'Not assigned' }}
                                </dd>
                            </div>
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Assigned modification</dt>
                                <dd class="{{ $bill->modification ? 'bill-show__dd' : 'bill-show__dd bill-show__dd--soft' }}">
                                    {{ $bill->modification?->name ?: 'Not assigned' }}
                                </dd>
                            </div>
                            @if($bill->description)
                                <div class="bill-show__kv" style="grid-column:1/-1;">
                                    <dt class="bill-show__dt">Description</dt>
                                    <dd class="bill-show__dd bill-show__dd--soft" style="font-weight:600;color:var(--text);">{{ $bill->description }}</dd>
                                </div>
                            @endif
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">{{ $bill->isOneTime() ? 'Payment amount' : 'Amount per cycle' }}</dt>
                                <dd class="bill-show__dd bill-show__dd--money">
                                    @if($detailCurrency)<span class="bill-show__curr" style="font-size:9px;">{{ $detailCurrency }}</span>@endif{{ number_format((float) $bill->recurring_cost, 2, '.', ',') }}
                                    @unless($bill->isOneTime())
                                        <span class="bill-show__mini-sub" style="display:block;margin-top:4px;font-weight:600;">{{ $recurringTypes[$bill->recurring_type] ?? $bill->recurring_type }}</span>
                                    @endunless
                                </dd>
                            </div>
                            @unless($bill->isOneTime())
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Billing cadence</dt>
                                <dd class="bill-show__dd">{{ $recurringTypes[$bill->recurring_type] ?? $bill->recurring_type }}</dd>
                            </div>
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Schedule through (year)</dt>
                                <dd class="bill-show__dd">{{ $bill->agreement_valid_until_year }}</dd>
                            </div>
                            @endunless
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Renewal reminder</dt>
                                <dd class="bill-show__dd">
                                    @if($bill->remind_before_days !== null && (int) $bill->remind_before_days > 0)
                                        {{ (int) $bill->remind_before_days }} day{{ (int) $bill->remind_before_days === 1 ? '' : 's' }} before end
                                    @else
                                        <span class="bill-show__dd--soft">Not set</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">Due date</dt>
                                <dd class="{{ $bill->due_date ? 'bill-show__dd' : 'bill-show__dd bill-show__dd--soft' }}">{{ $bill->due_date ? $bill->due_date->format('M j, Y') : 'Not set' }}</dd>
                            </div>
                            <div class="bill-show__kv">
                                <dt class="bill-show__dt">First installment due</dt>
                                <dd class="{{ $bill->first_installment_due_date ? 'bill-show__dd' : 'bill-show__dd bill-show__dd--soft' }}">{{ $bill->first_installment_due_date ? $bill->first_installment_due_date->format('M j, Y') : 'Not set' }}</dd>
                            </div>
                            @if($bill->deductAccount)
                                <div class="bill-show__kv" style="grid-column:1/-1;">
                                    <dt class="bill-show__dt">Debit account</dt>
                                    <dd class="bill-show__dd bill-show__dd--soft">{{ $bill->deductAccount->deductOptionLabel() }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </section>

                <section class="bill-show__panel" aria-labelledby="bill-schedule-heading">
                    <h2 class="bill-show__panel-h" id="bill-schedule-heading"><i class="fa fa-calendar-check" aria-hidden="true"></i>Billing schedule &amp; payment status</h2>
                    <div class="bill-show__panel-body">
                        @if($billScheduleRows->isEmpty())
                            <p class="bill-show__tx-empty" style="border:1px dashed color-mix(in srgb,var(--border) 80%,transparent);border-radius:var(--rs-radius-sm);padding:16px;">
                                @if($bill->isOneTime())
                                    Add a <strong style="color:var(--text);">due date</strong> (or first installment date) for this one-time bill so you can record the payment.
                                @else
                                    Add a <strong style="color:var(--text);">due date</strong> or <strong style="color:var(--text);">first installment</strong> date to build the schedule through agreement end ({{ $bill->agreement_valid_until_year }}).
                                @endif
                            </p>
                        @else
                            <p class="bill-show__tx-lead"><strong>Make payment</strong> can settle the remaining balance at once (<strong>full</strong>), pay a <strong>partial</strong>, or <strong>split</strong> the charge across multiple debit accounts. Each posting debits your books for that billing date.</p>
                            @if(isset($accounts) && $accounts->isEmpty())
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#f97316 70%,var(--muted));"><i class="fa fa-wallet"></i> Add a business account before you can record payments from here.</p>
                            @endif
                            @error('occurrence_date')
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#ef4444 82%,var(--muted));">{{ $message }}</p>
                            @enderror
                            @error('deduct_account_id')
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#ef4444 82%,var(--muted));">{{ $message }}</p>
                            @enderror
                            @error('payment_option')
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#ef4444 82%,var(--muted));">{{ $message }}</p>
                            @enderror
                            @error('partial_amount')
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#ef4444 82%,var(--muted));">{{ $message }}</p>
                            @enderror
                            @error('split_rows')
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#ef4444 82%,var(--muted));">{{ $message }}</p>
                            @enderror
                            <div class="bill-show__tx-scroll">
                                <table class="bill-show__tx-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Due date</th>
                                            <th>Billing amount</th>
                                            <th>Status</th>
                                            <th>Paid details</th>
                                            <th class="bill-show__tx-cell-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($billScheduleRows as $srow)
                                            @php
                                                $scheduleLegs = $srow['ledger_rows'] ?? collect();
                                                $__curPref = (($detailCurrency ?? '') !== '') ? ($detailCurrency.' ') : '';
                                                $__recAcctLabel = $scheduleLegs->count() > 1 ? 'Several postings — see breakdown' : ($scheduleLegs->first()?->deductAccount?->deductOptionLabel() ?? '—');
                                                $receiptPayload = [];
                                                foreach ($scheduleLegs as $__txr) {
                                                    $receiptPayload[] = [
                                                        'amount' => trim((($detailCurrency ?? '') !== '' ? $detailCurrency.' ' : '').number_format((float) $__txr->amount, 2, '.', ',')),
                                                        'account' => $__txr->deductAccount?->deductOptionLabel() ?? '—',
                                                        'posted' => $__txr->occurrence_date?->format('M j, Y') ?? '—',
                                                    ];
                                                }
                                                $__receiptJson = htmlspecialchars(json_encode($receiptPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                                                $__receiptTotalFmt = trim((($detailCurrency ?? '') !== '' ? $detailCurrency.' ' : '').number_format((float) ($srow['paid_total'] ?? 0), 2, '.', ','));
                                            @endphp
                                            <tr @class(['bill-show__tx-row--late' => $srow['past_due_unpaid']])>
                                                <td>{{ $srow['period'] }}</td>
                                                <td>{{ $srow['due']->format('M j, Y') }}</td>
                                                <td class="bill-show__tx-amt">@if($detailCurrency)<span style="opacity:.72;font-size:10px;">{{ $detailCurrency }}</span> @endif{{ $srow['amount_formatted'] }}</td>
                                                <td>
                                                    @if($srow['paid'])
                                                        <span class="bill-show__tx-status bill-show__tx-status--paid"><i class="fa fa-circle-check" aria-hidden="true"></i>Paid</span>
                                                    @elseif($srow['past_due_unpaid'])
                                                        <span class="bill-show__tx-status bill-show__tx-status--late"><i class="fa fa-circle-exclamation" aria-hidden="true"></i>{{ $srow['status_label'] }}</span>
                                                    @else
                                                        <span class="bill-show__tx-status bill-show__tx-status--open"><i class="fa fa-clock" aria-hidden="true"></i>{{ $srow['status_label'] }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($scheduleLegs->isNotEmpty())
                                                        <p class="bill-show__tx-paid">
                                                            <strong>Posted {{ optional($scheduleLegs->first()->occurrence_date)->format('M j, Y') ?? '—' }}{{ $scheduleLegs->count() > 1 ? ' · '.$scheduleLegs->count().' postings' : '' }}</strong>
                                                            <span class="bill-show__tx-paid-meta"><strong>{{ $__curPref }}{{ $srow['paid_total_formatted'] }}</strong> paid of <strong>{{ $__curPref }}{{ $srow['amount_formatted'] }}</strong> scheduled @if($srow['partially_paid']) · outstanding <strong>{{ $__curPref }}{{ $srow['outstanding_formatted'] }}</strong>@endif</span>
                                                        </p>
                                                    @else
                                                        <span class="bill-show__dd--soft">—</span>
                                                    @endif
                                                </td>
                                                <td class="bill-show__tx-cell-actions">
                                                    <div class="bill-show__tx-actions bill-show__tx-actions--stack">
                                                        @if($scheduleLegs->isNotEmpty())
                                                            <button type="button" class="bill-show__tx-btn js-bill-payment-open-receipt"
                                                                data-payment-due-human="{{ $srow['due']->format('M j, Y') }}"
                                                                data-payment-amount-fmt="{{ $__receiptTotalFmt }}"
                                                                data-payment-account="{{ e($__recAcctLabel) }}"
                                                                data-payment-lines-json="{{ $__receiptJson }}"><i class="fa fa-receipt"></i>View receipt</button>
                                                        @endif
                                                        @if(! $srow['paid'])
                                                            <button type="button"
                                                                class="bill-show__tx-btn bill-show__tx-btn--go js-bill-payment-open-settle"
                                                                data-occurrence="{{ $srow['due_ymd'] }}"
                                                                data-due-human="{{ $srow['due']->format('M j, Y') }}"
                                                                data-scheduled-num="{{ number_format((float) $srow['amount'], 2, '.', '') }}"
                                                                data-scheduled-fmt-display="{{ (($detailCurrency ?? '') !== '') ? trim((string) $detailCurrency.' '.$srow['amount_formatted']) : $srow['amount_formatted'] }}"
                                                                data-outstanding-num="{{ number_format((float) $srow['outstanding'], 2, '.', '') }}"
                                                                data-outstanding-fmt-display="{{ (($detailCurrency ?? '') !== '') ? trim((string) $detailCurrency.' '.$srow['outstanding_formatted']) : $srow['outstanding_formatted'] }}"
                                                                data-outstanding-unknown="{{ (($srow['outstanding_raw'] ?? null) === null) ? '1' : '0' }}"
                                                                data-paid-num="{{ number_format((float) ($srow['paid_total'] ?? 0), 2, '.', '') }}"
                                                                data-needs-declaration="{{ ! empty($srow['needs_period_charge_declaration']) ? '1' : '0' }}"
                                                                data-cur-prefix="{{ e((($detailCurrency ?? '') !== '') ? trim((string) $detailCurrency).' ' : '') }}"
                                                                @if(! isset($accounts) || $accounts->isEmpty()) disabled title="Add an account first" @endif><i class="fa fa-money-bill-wave"></i>Make payment</button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="bill-show__panel" aria-labelledby="bill-ledger-heading">
                    <h2 class="bill-show__panel-h" id="bill-ledger-heading"><i class="fa fa-book" aria-hidden="true"></i>Ledger payments logged</h2>
                    <div class="bill-show__panel-body">
                        @if($billLedgerRows->isEmpty())
                            <p class="bill-show__tx-empty">No bill payments in the ledger yet. Record payments from the schedule above to track what was debited from your accounts.</p>
                        @else
                            <div class="bill-show__tx-scroll" style="max-height:340px;">
                                <table class="bill-show__tx-table">
                                    <thead>
                                        <tr>
                                            <th>Occurred</th>
                                            <th>Amount</th>
                                            <th>Account debited</th>
                                            <th>Cadence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($billLedgerRows as $row)
                                            <tr>
                                                <td>{{ $row->occurrence_date?->format('M j, Y') ?? '—' }}</td>
                                                <td class="bill-show__tx-amt">
                                                    @if($row->currency)<span style="opacity:.72;font-size:10px;">{{ $row->currency }}</span>@elseif($detailCurrency)<span style="opacity:.72;font-size:10px;">{{ $detailCurrency }}</span>@endif
                                                    {{ number_format((float) $row->amount, 2, '.', ',') }}
                                                </td>
                                                <td>{{ $row->deductAccount?->deductOptionLabel() ?? '—' }}</td>
                                                <td>{{ $row->cadence_snapshot ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>

                @if($bill->notes)
                    <section class="bill-show__panel" aria-labelledby="bill-tx-notes-heading">
                        <h2 class="bill-show__panel-h" id="bill-tx-notes-heading"><i class="fa fa-note-sticky" aria-hidden="true"></i>Internal notes</h2>
                        <div class="bill-show__panel-body">
                            <p class="bill-show__prose" style="margin-top:0;">{{ $bill->notes }}</p>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>

    <footer class="bill-show__danger">
        <p class="bill-show__danger-copy"><strong>Remove bill</strong> — Deletes this bill and its payment schedule from your business; existing ledger rows in Transactions remain for history.</p>
        <form method="post" action="{{ route('account.bills.destroy', $bill) }}" style="margin:0;" onsubmit="return confirm('Remove this bill record?');">
            @csrf
            @method('delete')
            <button type="submit" class="bill-show__del"><i class="fa fa-trash-can" aria-hidden="true"></i>Remove bill</button>
        </form>
    </footer>
</div>

<div id="bill-settle-modal"
    class="loan-show-modal{{ $billSettleModalShouldOpen ? ' loan-show-modal--open' : '' }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="bill-settle-modal-title"
    aria-hidden="{{ $billSettleModalShouldOpen ? 'false' : 'true' }}">
    <div class="loan-show-modal__backdrop" data-close-bill-settle tabindex="-1"></div>
    <div class="loan-show-modal__panel">
        <div class="loan-show-modal__head">
            <h2 id="bill-settle-modal-title">Record bill payment</h2>
            <button type="button" class="loan-show-modal__close" data-close-bill-settle aria-label="Close">&times;</button>
        </div>
        <div class="loan-show-modal__body">
            @php
                $__billSplitPairs = array_values((array) old('split_rows', []));
                while (count($__billSplitPairs) < 2) {
                    $__billSplitPairs[] = ['deduct_account_id' => '', 'amount' => ''];
                }
            @endphp
            <form id="bill-settle-form" method="post" action="{{ route('account.bills.billing.settle', $bill) }}" novalidate>
                @csrf
                <input type="hidden" name="occurrence_date" id="bill-settle-occurrence" value="{{ old('occurrence_date') }}">
                <div class="loan-show-modal__summ">
                    <span style="color:var(--muted);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Billing date</span>
                    <strong id="bill-settle-due-display">{{ $billSettleDueHumanFromOld ?? '—' }}</strong>
                </div>
                <span class="loan-show-modal__lbl">Scheduled for this period</span>
                <div class="loan-show-modal__summ" style="margin-top:5px;"><strong id="bill-settle-scheduled-display">{{ $billBillingAmountDefaultDisplay }}</strong></div>
                <span class="loan-show-modal__lbl">Still outstanding</span>
                <div class="loan-show-modal__summ" style="margin-top:5px;"><strong id="bill-settle-outstanding-display">—</strong></div>
                @if($bill->amount_varies_by_usage)
                    <div id="bill-settle-declaration-wrap" class="bill-settle-declaration" @if(! $errors->has('period_charge_total')) hidden @endif>
                        <label for="bill-settle-period-total" class="loan-show-modal__lbl">Invoice or meter total for this period</label>
                        <input type="number" name="period_charge_total" id="bill-settle-period-total" step="0.01" min="0.01" placeholder="Enter this period&apos;s billed amount first"
                            value="{{ old('period_charge_total') }}">
                        <small class="bill-settle-help">Locks in the cap for this billing date once you confirm payment — each new period needs its total when unpaid.</small>
                        @error('period_charge_total')
                            <small style="display:block;color:#f87171;margin-top:8px;font-size:12px;">{{ $message }}</small>
                        @enderror
                    </div>
                @endif
                @if($accounts->isEmpty())
                    <p style="margin:12px 0 0;color:var(--muted);">Create an account first (Accounts in your business).</p>
                    <button type="button" disabled class="loan-show-modal__submit"><i class="fa fa-circle-check"></i> Confirm payment</button>
                @else
                    <fieldset class="bill-settle-payopt" aria-label="How to apply this payment">
                        <legend class="loan-show-modal__lbl bill-settle-payopt-legend">Payment application</legend>
                        <label class="bill-settle-payopt-card">
                            <input type="radio" name="payment_option" id="bill-settle-opt-full" value="full" @checked((string) old('payment_option', 'full') === 'full')>
                            <span>
                                <span class="bill-settle-payopt-title">Pay full remainder</span>
                                <span class="bill-settle-payopt-hint">Debit the outstanding amount for this billing date from one account.</span>
                            </span>
                        </label>
                        <label class="bill-settle-payopt-card">
                            <input type="radio" name="payment_option" id="bill-settle-opt-partial" value="partial" @checked((string) old('payment_option') === 'partial')>
                            <span>
                                <span class="bill-settle-payopt-title">Partial payment</span>
                                <span class="bill-settle-payopt-hint">Record less than the outstanding amount — you can add another payment later.</span>
                            </span>
                        </label>
                        <label class="bill-settle-payopt-card" id="bill-settle-split-payopt">
                            <input type="radio" name="payment_option" id="bill-settle-opt-split" value="split" @checked((string) old('payment_option') === 'split')>
                            <span>
                                <span class="bill-settle-payopt-title">Split across accounts</span>
                                <span class="bill-settle-payopt-hint">Debit two portions from different accounts in one step (combined total cannot exceed the outstanding amount).</span>
                            </span>
                        </label>
                    </fieldset>
                    <div id="bill-settle-single-wrap">
                        <span class="loan-show-modal__lbl">Debit account</span>
                        <select name="deduct_account_id" id="bill-settle-account">
                            <option value="">Select account…</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected((int) old('deduct_account_id', $bill->deduct_account_id) === (int) $acc->id)>
                                    {{ $acc->deductOptionLabel() }}
                                </option>
                            @endforeach
                        </select>
                        <div id="bill-settle-partial-extra" class="bill-settle-partial-block" hidden>
                            <label for="bill-settle-partial-amt" class="loan-show-modal__lbl">Amount for this posting</label>
                            <input type="number" name="partial_amount" id="bill-settle-partial-amt" step="0.01" min="0.01" placeholder="e.g. 50.00" value="{{ old('partial_amount') }}">
                            <small id="bill-settle-partial-cap" class="bill-settle-help"></small>
                            @error('partial_amount')
                                <small style="display:block;color:#f87171;margin-top:6px;font-size:12px;">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                    <div id="bill-settle-split-wrap" class="bill-settle-split-block" hidden>
                        <span class="loan-show-modal__lbl">Debit each portion</span>
                        <p class="bill-settle-help bill-settle-help--split">Use two different debit accounts and amounts that add up to at most what is still owed on this billing date.</p>
                        @foreach($__billSplitPairs as $__si => $__sr)
                            <div class="bill-settle-split-row">
                                <div>
                                    <label class="loan-show-modal__lbl" style="margin-bottom:4px;display:block;font-size:10px;">Account {{ $__si + 1 }}</label>
                                    <select name="split_rows[{{ $__si }}][deduct_account_id]" class="bill-settle-split-acct">
                                        <option value="">Select…</option>
                                        @foreach($accounts as $__accSplit)
                                            <option value="{{ $__accSplit->id }}" @selected((string) ($__sr['deduct_account_id'] ?? '') === (string) $__accSplit->id)>{{ $__accSplit->deductOptionLabel() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="loan-show-modal__lbl" style="margin-bottom:4px;display:block;font-size:10px;">Amount</label>
                                    <input type="number" name="split_rows[{{ $__si }}][amount]" step="0.01" min="0.01" class="bill-settle-split-amt" placeholder="0.00" value="{{ $__sr['amount'] ?? '' }}">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" id="bill-settle-submit" class="loan-show-modal__submit bill-settle-submit-btn"><i class="fa fa-circle-check"></i> Confirm payment</button>
                    <p class="bill-settle-footnote">Posting updates your account balances immediately. Outstanding on this billing date shrinks until the scheduled amount is fully covered.</p>
                @endif
            </form>
        </div>
    </div>
</div>

<div id="bill-receipt-modal" class="loan-show-modal" role="dialog" aria-modal="true" aria-labelledby="bill-receipt-title" aria-hidden="true">
    <div class="loan-show-modal__backdrop" data-close-bill-receipt tabindex="-1"></div>
    <div class="loan-show-modal__panel">
        <div class="loan-show-modal__head">
            <h2 id="bill-receipt-title">Recorded payment</h2>
            <button type="button" class="loan-show-modal__close" data-close-bill-receipt aria-label="Close">&times;</button>
        </div>
        <div class="loan-show-modal__body">
            <div class="loan-show-modal__summ">
                <span style="color:var(--muted);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Billing date</span>
                <strong id="bill-receipt-due" style="font-size:14px;">—</strong>
            </div>
            <span class="loan-show-modal__lbl">Amount</span>
            <div class="loan-show-modal__summ" style="margin-top:5px;"><strong id="bill-receipt-amount">—</strong></div>
            <span class="loan-show-modal__lbl">Account summary</span>
            <p id="bill-receipt-account" style="margin:4px 0 0;color:var(--text);font-weight:600;">—</p>
            <span class="loan-show-modal__lbl" style="margin-top:10px;">Posting lines</span>
            <ul id="bill-receipt-breakdown" class="bill-receipt-breakdown" aria-live="polite"></ul>
            <div class="loan-show-receipt-toolbar" aria-label="Receipt actions">
                <button type="button" class="bill-show__tx-btn" id="bill-receipt-btn-print" title="Opens print dialog"><i class="fa fa-print" aria-hidden="true"></i>Print</button>
                <button type="button" class="bill-show__tx-btn" id="bill-receipt-btn-copy" title="Copy receipt text"><i class="fa fa-copy" aria-hidden="true"></i>Copy</button>
                <button type="button" class="bill-show__tx-btn bill-show__tx-btn--go" id="bill-receipt-btn-pdf" title="Choose “Save as PDF” in the print dialog"><i class="fa fa-file-pdf" aria-hidden="true"></i>PDF</button>
            </div>
            <div id="bill-receipt-copy-toast" class="loan-show-copy-toast" role="status" aria-live="polite"></div>
            <button type="button" class="loan-show-modal__submit" style="margin-top:8px;background:color-mix(in srgb,var(--card) 70%,transparent);color:var(--text);border-color:var(--border);" data-close-bill-receipt><i class="fa fa-times"></i> Close</button>
        </div>
    </div>
</div>

<script>
var billReceiptCtx = {
    billLabel: @json($bill->name),
    businessName: @json($business->name ?? ''),
    printedAtHint: ''
};
(function(){
    function setHtmlOpen(on){
        document.documentElement.classList.toggle('loan-show-modal-html-open', on);
    }
    function openModal(el){
        if(!el)return;
        el.classList.add('loan-show-modal--open');
        el.setAttribute('aria-hidden','false');
        setHtmlOpen(true);
    }
    function closeModal(el){
        if(!el)return;
        el.classList.remove('loan-show-modal--open');
        el.setAttribute('aria-hidden','true');
        if(!document.querySelector('.loan-show-modal.loan-show-modal--open'))setHtmlOpen(false);
    }

    var settleModal=document.getElementById('bill-settle-modal');
    var settleForm=document.getElementById('bill-settle-form');
    var settleOcc=document.getElementById('bill-settle-occurrence');
    var settleDue=document.getElementById('bill-settle-due-display');
    var settleSched=document.getElementById('bill-settle-scheduled-display');
    var settleOut=document.getElementById('bill-settle-outstanding-display');
    var settleAc=document.getElementById('bill-settle-account');
    var settlePartialExtra=document.getElementById('bill-settle-partial-extra');
    var settlePartialAmt=document.getElementById('bill-settle-partial-amt');
    var settlePartialCap=document.getElementById('bill-settle-partial-cap');
    var settleSingleWrap=document.getElementById('bill-settle-single-wrap');
    var settleSplitWrap=document.getElementById('bill-settle-split-wrap');
    var settleOutstandingNum='0.00';
    var settleDeclWrap=document.getElementById('bill-settle-declaration-wrap');
    var settlePeriodTotal=document.getElementById('bill-settle-period-total');
    var settleCurPrefix='';
    var settlePaidSoFarNum=0;
    var disallowSplitGlob=@json(! $bill->allow_split_payment);
    var billUsageVaries=@json((bool) $bill->amount_varies_by_usage);

    function settleFormatMoney(pfx, raw){
        var n = parseFloat(String(raw).replace(',', ''));
        if(!isFinite(n)) n = 0;
        return String(pfx)+n.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
    }

    function settleRefreshOutstanding(){
        if(!billUsageVaries || !settleDeclWrap || settleDeclWrap.hidden || !settlePeriodTotal)return;
        var capStr = String(settlePeriodTotal.value||'').trim();
        var cap = parseFloat(capStr);
        if(!capStr||!isFinite(cap)||cap<0.009){
            settleOutstandingNum='0.00';
            if(settleOut)settleOut.textContent=settleCurPrefix?'('+settleCurPrefix.trim()+') Enter period total above':'Enter period total above';
            syncBillSettlePaymentOption();
            return;
        }
        var unpaid = Math.max(0, Math.round((cap-settlePaidSoFarNum)*100)/100);
        settleOutstandingNum=unpaid.toFixed(2);
        if(settleOut)settleOut.textContent=settleFormatMoney(settleCurPrefix||'', unpaid);
        syncBillSettlePaymentOption();
    }

    function syncBillSettlePaymentOption(){
        if(!settleForm)return;
        var opt=(settleForm.querySelector('input[name="payment_option"]:checked')||{}).value||'full';
        var useSingle=opt==='full'||opt==='partial';
        var useSplit=opt==='split';
        if(settleSingleWrap)settleSingleWrap.hidden=useSplit;
        if(settleSplitWrap)settleSplitWrap.hidden=!useSplit;
        if(settlePartialExtra)settlePartialExtra.hidden=opt!=='partial';
        if(settleAc){
            settleAc.required=useSingle;
            settleAc.disabled=useSplit;
        }
        settleForm.querySelectorAll('.bill-settle-split-acct, .bill-settle-split-amt').forEach(function(el){
            el.disabled=!useSplit;
        });
        if(settlePartialAmt){
            settlePartialAmt.required=(opt==='partial');
            settlePartialAmt.disabled=opt!=='partial';
            settlePartialAmt.setAttribute('max', settleOutstandingNum);
        }
        if(settlePartialCap){
            settlePartialCap.textContent=opt==='partial'?'You can pay any amount up to '+settleFormatMoney(settleCurPrefix||'', settleOutstandingNum)+' for this billing date.':'';
        }
    }

    settleForm&&settleForm.querySelectorAll('input[name="payment_option"]').forEach(function(r){r.addEventListener('change',syncBillSettlePaymentOption);});
    syncBillSettlePaymentOption();

    if(disallowSplitGlob){
        var spLab=document.getElementById('bill-settle-split-payopt');
        if(spLab) spLab.hidden=true;
        var srad=document.getElementById('bill-settle-opt-split');
        var frad=document.getElementById('bill-settle-opt-full');
        if(srad&&srad.checked&&frad) frad.checked=true;
    }
    if(settlePeriodTotal){
        settlePeriodTotal.addEventListener('input', settleRefreshOutstanding);
        settlePeriodTotal.addEventListener('change', settleRefreshOutstanding);
    }

    if(settleModal){
        settleModal.querySelectorAll('[data-close-bill-settle]').forEach(function(b){
            b.addEventListener('click',function(){closeModal(settleModal);});
        });
    }

    document.querySelectorAll('.js-bill-payment-open-settle').forEach(function(btn){
        btn.addEventListener('click',function(){
            if(btn.disabled)return;
            var ymd=btn.getAttribute('data-occurrence')||'';
            var human=btn.getAttribute('data-due-human')||'—';
            var schedDisp=btn.getAttribute('data-scheduled-fmt-display')||'—';
            var outDisp=btn.getAttribute('data-outstanding-fmt-display')||'—';
            var outNum=btn.getAttribute('data-outstanding-num')||'0';
            var outUnknown=(btn.getAttribute('data-outstanding-unknown')||'0')==='1';
            var needsDeclRaw=(btn.getAttribute('data-needs-declaration')||'0')==='1';
            settleCurPrefix=btn.getAttribute('data-cur-prefix')||'';
            settlePaidSoFarNum=parseFloat(btn.getAttribute('data-paid-num')||'0')||0;
            settleOutstandingNum=outNum;
            if(settleOcc)settleOcc.value=ymd;
            if(settleDue)settleDue.textContent=human;
            if(settleSched)settleSched.textContent=schedDisp;
            if(settleOut)settleOut.textContent=outDisp;
            if(settleDeclWrap){
                settleDeclWrap.hidden=!billUsageVaries|| !(outUnknown||needsDeclRaw);
                if(settlePeriodTotal){
                    settlePeriodTotal.required=! settleDeclWrap.hidden;
                    if(! settleDeclWrap.hidden){
                        settlePeriodTotal.value='';
                    }
                }
            }
            var spLab2=document.getElementById('bill-settle-split-payopt');
            if(spLab2)spLab2.hidden=!!disallowSplitGlob;
            var sRad2=document.getElementById('bill-settle-opt-split');
            var fRad2=document.getElementById('bill-settle-opt-full');
            if(disallowSplitGlob && sRad2 && sRad2.checked && fRad2) fRad2.checked=true;
            if(settleDeclWrap&&!settleDeclWrap.hidden){
                settleOutstandingNum='0.00';
                settleRefreshOutstanding();
            }else{
                settleOutstandingNum=outNum;
                syncBillSettlePaymentOption();
            }
            openModal(settleModal);
        });
    });

    settleForm&&settleForm.addEventListener('submit',function(ev){
        var opt=(settleForm.querySelector('input[name="payment_option"]:checked')||{}).value||'full';
        if(opt==='split'){
            settleForm.querySelectorAll('#bill-settle-account, #bill-settle-partial-amt').forEach(function(el){el.disabled=true;});
            settleForm.querySelectorAll('.bill-settle-split-acct, .bill-settle-split-amt').forEach(function(el){el.disabled=false;});
        }else{
            settleForm.querySelectorAll('.bill-settle-split-acct, .bill-settle-split-amt').forEach(function(el){el.disabled=true;});
            if(opt==='full'&&settlePartialAmt)settlePartialAmt.disabled=true;
        }
    });

    var receipt=document.getElementById('bill-receipt-modal');
    if(receipt){
        receipt.querySelectorAll('[data-close-bill-receipt]').forEach(function(b){
            b.addEventListener('click',function(){closeModal(receipt);});
        });
    }

    function escHtml(s){
        return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function getReceiptLinesFromDom(){
        var ul=document.getElementById('bill-receipt-breakdown');
        if(!ul)return [];
        var out=[];
        ul.querySelectorAll('li').forEach(function(li){
            out.push(li.textContent||'');
        });
        return out;
    }

    function getReceiptSnapshot(){
        return {
            due: (document.getElementById('bill-receipt-due')||{}).textContent||'—',
            amount: (document.getElementById('bill-receipt-amount')||{}).textContent||'—',
            account: (document.getElementById('bill-receipt-account')||{}).textContent||'—',
            lines:getReceiptLinesFromDom()
        };
    }

    function buildReceiptPlainText(){
        var r=getReceiptSnapshot();
        var lines=[
            'Bill payment receipt',
            'Bill: '+(billReceiptCtx.billLabel||'—'),
            'Business: '+(billReceiptCtx.businessName||'—'),
            'Billing date: '+r.due,
            'Amount: '+r.amount,
            'Account summary: '+r.account,
            'Printed: '+(billReceiptCtx.printedAtHint||'')
        ];
        if(r.lines&&r.lines.length){
            lines.splice(-1,0,'Posting lines:',r.lines.join('\n'));
        }
        return lines.join('\n');
    }

    function openReceiptPrintWindow(docTitle){
        var r=getReceiptSnapshot();
        var w=window.open('','_blank');
        if(!w){window.alert('Allow pop-ups to print or save as PDF.');return;}
        var title=docTitle||'Bill payment receipt';
        var html='<!DOCTYPE html><html><head><meta charset="utf-8"><title>'+escHtml(title)+'</title>';
        html+='<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:28px 32px;color:#111;line-height:1.45;}h1{font-size:20px;margin:0 0 6px;}h2{font-size:13px;font-weight:600;color:#444;margin:20px 0 8px;text-transform:uppercase;letter-spacing:.04em}.row{margin:6px 0;font-size:14px}.row strong{display:inline-block;min-width:9.5em;color:#333}.foot{margin-top:28px;font-size:11px;color:#666}</style></head><body>';
        html+='<h1>Bill payment receipt</h1>';
        html+='<div class="row"><strong>Bill</strong> '+escHtml(billReceiptCtx.billLabel)+'</div>';
        if(billReceiptCtx.businessName){
            html+='<div class="row"><strong>Business</strong> '+escHtml(billReceiptCtx.businessName)+'</div>';
        }
        html+='<h2>Payment</h2>';
        html+='<div class="row"><strong>Billing date</strong> '+escHtml(r.due)+'</div>';
        html+='<div class="row"><strong>Amount</strong> '+escHtml(r.amount)+'</div>';
        html+='<div class="row"><strong>Account summary</strong> '+escHtml(r.account)+'</div>';
        if(r.lines&&r.lines.length){
            html+='<h2>Posting lines</h2>';
            r.lines.forEach(function(ln){html+='<div class="row" style="padding-left:1em">'+escHtml(ln)+'</div>';});
        }
        html+='<p class="foot">Generated '+escHtml(billReceiptCtx.printedAtHint)+' · Use print dialog to print or save as PDF.</p>';
        html+='</body></html>';
        w.document.open();
        w.document.write(html);
        w.document.close();
        w.focus();
        var closeAfter=function(){try{w.close();}catch(e){}};
        if('onafterprint' in w){
            w.addEventListener('afterprint',closeAfter);
        }else{
            setTimeout(closeAfter,800);
        }
        setTimeout(function(){w.print();},150);
    }

    var copyToast=document.getElementById('bill-receipt-copy-toast');
    function showCopyToast(msg){
        if(copyToast)copyToast.textContent=msg||'';
    }

    var btnPrint=document.getElementById('bill-receipt-btn-print');
    var btnPdf=document.getElementById('bill-receipt-btn-pdf');
    var btnCopy=document.getElementById('bill-receipt-btn-copy');
    if(btnPrint)btnPrint.addEventListener('click',function(){openReceiptPrintWindow('Bill payment receipt');});
    if(btnPdf)btnPdf.addEventListener('click',function(){openReceiptPrintWindow('Bill payment receipt — PDF');});
    if(btnCopy)btnCopy.addEventListener('click',function(){
        var t=buildReceiptPlainText();
        showCopyToast('');
        if(navigator.clipboard&&navigator.clipboard.writeText){
            navigator.clipboard.writeText(t).then(function(){
                showCopyToast('Copied to clipboard.');
            }).catch(function(){
                window.prompt('Copy this receipt:',t);
            });
        }else{
            window.prompt('Copy this receipt:',t);
        }
    });

    document.querySelectorAll('.js-bill-payment-open-receipt').forEach(function(btn){
        btn.addEventListener('click',function(){
            billReceiptCtx.printedAtHint = new Date().toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
            document.getElementById('bill-receipt-due').textContent=btn.getAttribute('data-payment-due-human')||'—';
            document.getElementById('bill-receipt-amount').textContent=btn.getAttribute('data-payment-amount-fmt')||'—';
            document.getElementById('bill-receipt-account').textContent=btn.getAttribute('data-payment-account')||'—';
            var breakdown=document.getElementById('bill-receipt-breakdown');
            if(breakdown){
                breakdown.innerHTML='';
                var raw=btn.getAttribute('data-payment-lines-json')||'';
                var rows=[];
                try{rows=JSON.parse(raw);}catch(__){rows=[];}
                if(Array.isArray(rows)&&rows.length){
                    rows.forEach(function(row){
                        var li=document.createElement('li');
                        li.textContent=(row.amount||'—')+' · '+(row.account||'—')+' · Posted '+(row.posted||'—');
                        breakdown.appendChild(li);
                    });
                    breakdown.style.display='block';
                }else{
                    breakdown.style.display='none';
                }
            }
            showCopyToast('');
            openModal(receipt);
        });
    });

    var open=@json($billSettleModalShouldOpen);
    var dueHumanOld=@json($billSettleDueHumanFromOld);
    if(open&&dueHumanOld&&settleDue){
        settleDue.textContent=dueHumanOld;
        setHtmlOpen(true);
    }
})();
</script>
@endsection
