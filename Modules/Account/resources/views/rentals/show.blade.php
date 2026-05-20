@extends('theme::layouts.app', ['title' => $rental->property_type.' — Rental', 'heading' => 'Rental details'])

@section('content')
@php($rentalSettleModalShouldOpen = $errors->has('occurrence_date') || $errors->has('deduct_account_id'))
@php($rentalSettleDueHumanFromOld = old('occurrence_date') ? \Carbon\Carbon::parse(old('occurrence_date'))->format('M j, Y') : null)
@php($rentBillingAmountDefaultDisplay = trim((string) (($detailCurrency ?? '') !== '' ? $detailCurrency.' ' : '').number_format((float) $rental->recurring_cost, 2, '.', ',')))
<div class="rental-show">
    <style>
        .rental-show{max-width:none;width:100%;margin:0;box-sizing:border-box;--rs-radius:10px;--rs-radius-sm:8px;--rs-font:13px;--rs-font-sm:11px;--rs-font-xs:9px;--rs-glow:color-mix(in srgb,var(--primary) 22%,transparent);}
        .rental-show *{box-sizing:border-box;}

        .rental-show__hero{
            position:relative;border-radius:var(--rs-radius);overflow:hidden;margin-bottom:12px;
            border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border));
            background:linear-gradient(145deg,color-mix(in srgb,var(--primary) 14%,var(--card)) 0%,var(--card) 48%,color-mix(in srgb,var(--card) 92%,#0f172a) 100%);
            box-shadow:0 16px 42px -26px color-mix(in srgb,var(--primary) 22%,#000),0 0 0 1px color-mix(in srgb,#fff 4%,transparent) inset;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__hero{
            background:linear-gradient(165deg,#ffffff 0%,color-mix(in srgb,var(--primary) 8%,#fffefd) 40%,#f5f5f4 100%);
            box-shadow:0 16px 40px -24px rgba(0,0,0,.14);
            border-color:color-mix(in srgb,var(--primary) 22%,var(--border));
        }
        @keyframes rental-show-hero-overdue-bar{
            0%,100%{background-position:0% 0%;opacity:1;}
            50%{background-position:0% 100%;opacity:.93;}
        }
        .rental-show__hero--payment-overdue{
            border-color:color-mix(in srgb,#ef4444 48%,var(--border));
            box-shadow:0 16px 42px -26px color-mix(in srgb,#ef4444 22%,#000),0 0 0 1px color-mix(in srgb,#fff 4%,transparent) inset;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__hero--payment-overdue{
            box-shadow:0 16px 40px -24px rgba(220,38,38,.14);
            border-color:color-mix(in srgb,#ef4444 42%,var(--border));
        }
        .rental-show__hero--payment-overdue::before{
            content:"";position:absolute;left:0;top:0;bottom:0;width:5px;z-index:2;pointer-events:none;border-radius:inherit;
            background:linear-gradient(180deg,#ef4444 0%,#991b1b 38%,#f87171 100%);
            background-size:100% 220%;
            animation:rental-show-hero-overdue-bar 1.85s ease-in-out infinite;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__hero--payment-overdue::before{
            background:linear-gradient(180deg,#dc2626 0%,#b91c1c 42%,#fca5a5 100%);
            background-size:100% 220%;
        }
        @media (prefers-reduced-motion:reduce){
            .rental-show__hero--payment-overdue::before{animation:none;}
        }
        .rental-show__hero--payment-overdue .rental-show__hero-inner{padding-left:17px;}
        .rental-show__hero-glow{
            pointer-events:none;position:absolute;right:-20%;top:-40%;width:55%;height:120%;
            background:radial-gradient(ellipse at center,color-mix(in srgb,var(--primary) 18%,transparent) 0%,transparent 68%);
            opacity:.9;
        }
        .rental-show__hero-inner{position:relative;z-index:1;padding:11px 13px 13px;}

        .rental-show__hero-top{display:flex;flex-wrap:wrap;align-items:center;gap:7px 9px;justify-content:space-between;margin-bottom:8px;}
        .rental-show__back{
            display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:8px;font-size:11px;font-weight:600;
            border:1px solid color-mix(in srgb,var(--border) 88%,transparent);background:color-mix(in srgb,var(--card) 55%,transparent);
            color:var(--text);text-decoration:none;backdrop-filter:blur(8px);transition:transform .18s ease,border-color .18s ease,background .18s ease;
        }
        .rental-show__back:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 10%,var(--card));transform:translateX(-2px);}
        .rental-show__biz-chip{
            display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;font-size:9px;font-weight:600;
            letter-spacing:.035em;text-transform:uppercase;color:color-mix(in srgb,var(--primary) 78%,var(--text));
            border:1px solid color-mix(in srgb,var(--primary) 24%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);
        }
        .rental-show__biz-chip i{opacity:.8;font-size:10px;}

        .rental-show__headline{margin:0 0 8px;font-size:clamp(1.02rem,1.55vw,1.2rem);font-weight:600;letter-spacing:-.024em;line-height:1.25;color:var(--text);}
        .rental-show__pills{display:flex;flex-wrap:wrap;gap:5px;}
        .rental-show__pill{
            display:inline-flex;align-items:center;gap:3px;font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;
            padding:2px 7px;border-radius:999px;border:1px solid color-mix(in srgb,var(--border) 78%,transparent);
            background:color-mix(in srgb,var(--card) 40%,transparent);color:var(--muted);
        }
        .rental-show__pill i{color:var(--primary);font-size:9px;opacity:.85;}
        .rental-show__pill--overdue{
            border-color:color-mix(in srgb,#ef4444 58%,var(--border));
            background:color-mix(in srgb,#ef4444 16%,transparent);
            color:#f87171;
            animation:rental-show-pill-overdue 2s ease-in-out infinite;
        }
        .rental-show__pill--overdue i{color:#fecaca!important;opacity:1!important;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__pill--overdue{color:#dc2626;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__pill--overdue i{color:#b91c1c!important;}
        @keyframes rental-show-pill-overdue{
            0%,100%{opacity:1;transform:scale(1);}
            50%{opacity:.92;transform:scale(1.03);}
        }
        @media (prefers-reduced-motion:reduce){
            .rental-show__pill--overdue{animation:none;}
        }

        .rental-show__notify-rental-overdue{
            margin:0 0 11px;font-size:var(--rs-font-sm);padding:10px 12px;border-radius:var(--rs-radius);
            display:flex;align-items:flex-start;gap:10px;line-height:1.45;border:1px solid color-mix(in srgb,#f87171 48%,var(--border));
            background:color-mix(in srgb,#ef4444 12%,transparent);color:var(--text);
        }
        .rental-show__notify-rental-overdue i{margin-top:2px;color:#f87171;flex-shrink:0;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__notify-rental-overdue{background:color-mix(in srgb,#fef2f2 94%,transparent);border-color:color-mix(in srgb,#fca5a5 55%,var(--border));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__notify-rental-overdue i{color:#dc2626;}

        .rental-show__highlight{
            margin-top:10px;display:grid;gap:9px;
            grid-template-columns:1fr;align-items:stretch;
        }
        @media(min-width:640px){
            .rental-show__highlight{grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:10px;align-items:stretch;}
        }
        .rental-show__cost-card{
            border-radius:var(--rs-radius-sm);padding:10px 12px;
            border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));
            background:linear-gradient(155deg,color-mix(in srgb,var(--primary) 16%,transparent),color-mix(in srgb,var(--card) 94%,transparent));
            position:relative;overflow:hidden;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__cost-card{background:linear-gradient(155deg,color-mix(in srgb,var(--primary) 10%,#fff),#fff);}
        .rental-show__cost-card::before{
            content:"";position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:4px 0 0 4px;
            background:linear-gradient(180deg,var(--primary),color-mix(in srgb,var(--primary) 45%,#1e293b));
        }
        .rental-show__cost-lab{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px;}
        .rental-show__cost-val{
            font-size:clamp(1.05rem,2.1vw,1.26rem);font-weight:700;letter-spacing:-.028em;line-height:1.12;
            color:color-mix(in srgb,var(--primary) 34%,var(--text));font-variant-numeric:tabular-nums;
        }
        .rental-show__curr{font-size:9px;opacity:.82;font-weight:600;margin-right:.2em;text-transform:uppercase;vertical-align:super;}

        /* Key money — same tier as recurring cost, visually distinct accent */
        .rental-show__key-card{
            display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:var(--rs-radius-sm);
            border:1px solid color-mix(in srgb,#f59e0b 38%,var(--border));
            background:linear-gradient(145deg,color-mix(in srgb,#f59e0b 11%,transparent),color-mix(in srgb,var(--card) 94%,transparent));
            position:relative;overflow:hidden;
            box-shadow:0 14px 36px -28px color-mix(in srgb,#f59e0b 42%,#000);
        }
        .rental-show__key-card::before{
            content:"";position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:4px 0 0 4px;
            background:linear-gradient(180deg,#fbbf24,color-mix(in srgb,#d97706 55%,#1e293b));
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__key-card{
            background:linear-gradient(158deg,color-mix(in srgb,#fffbeb 94%,#fff),#ffffff);
            border-color:color-mix(in srgb,#fbbf24 45%,var(--border));
            box-shadow:0 12px 32px -24px rgba(245,158,11,.35);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__key-card::before{
            background:linear-gradient(180deg,#fbbf24,#d97706);
        }
        .rental-show__key-ico-wrap{
            width:32px;height:32px;border-radius:8px;flex-shrink:0;display:grid;place-items:center;
            font-size:12px;color:color-mix(in srgb,#fbbf24 85%,var(--text));
            background:color-mix(in srgb,#f59e0b 14%,transparent);
            border:1px solid color-mix(in srgb,#f59e0b 35%,var(--border));
            position:relative;z-index:1;
        }
        .rental-show__key-inner{min-width:0;flex:1;position:relative;z-index:1;}
        .rental-show__key-lab{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px;}
        .rental-show__key-val{
            font-size:clamp(1rem,1.9vw,1.14rem);font-weight:700;letter-spacing:-.03em;line-height:1.14;
            color:color-mix(in srgb,#eab308 14%,var(--text));font-variant-numeric:tabular-nums;
        }
        .rental-show__key-curr{font-size:9px;opacity:.88;font-weight:600;margin-right:.2em;text-transform:uppercase;vertical-align:super;color:color-mix(in srgb,#fbbf24 30%,var(--muted));}
        .rental-show__key-note{margin:4px 0 0;font-size:9px;font-weight:500;line-height:1.4;color:var(--muted);}

        .rental-show__mini-stats{display:flex;flex-direction:column;gap:6px;}
        .rental-show__mini{
            flex:1;display:flex;align-items:flex-start;gap:8px;padding:7px 10px;border-radius:var(--rs-radius-sm);
            border:1px solid color-mix(in srgb,var(--border) 85%,transparent);background:color-mix(in srgb,var(--card) 88%,transparent);
        }
        .rental-show__mini-ico{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;flex-shrink:0;
            background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);font-size:11px;}
        .rental-show__mini-txt{font-size:11px;font-weight:600;line-height:1.32;color:var(--text);}
        .rental-show__mini-sub{display:block;margin-top:1px;font-size:9px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;}

        .rental-show__panels{display:grid;gap:9px;}@media(min-width:900px){.rental-show__panels{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;align-items:start;}}

        .rental-show__panel{
            border-radius:var(--rs-radius);border:1px solid color-mix(in srgb,var(--border) 90%,transparent);
            background:linear-gradient(180deg,color-mix(in srgb,var(--card) 99%,transparent),color-mix(in srgb,var(--card) 94%,#0f172a05));
            box-shadow:0 8px 26px -20px rgba(0,0,0,.28);overflow:hidden;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__panel{background:linear-gradient(180deg,#fff,#fafaf9);box-shadow:0 8px 28px -20px rgba(0,0,0,.08);}
        .rental-show__panel-h{
            margin:0;padding:8px 11px;display:flex;align-items:center;gap:7px;font-size:var(--rs-font-sm);font-weight:600;letter-spacing:.01em;
            border-bottom:1px solid color-mix(in srgb,var(--border) 88%,transparent);
            background:color-mix(in srgb,var(--card) 97%,transparent);
        }
        .rental-show__panel-h i{width:24px;height:24px;display:grid;place-items:center;border-radius:7px;font-size:11px;color:var(--primary);
            background:color-mix(in srgb,var(--primary) 9%,transparent);border:1px solid color-mix(in srgb,var(--primary) 15%,var(--border));}
        .rental-show__panel-body{padding:10px 11px;}

        .rental-show__dl{display:grid;gap:8px;}@media(min-width:440px){.rental-show__dl--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 12px;}}
        .rental-show__kv{padding:6px 0;border-bottom:1px dashed color-mix(in srgb,var(--border) 70%,transparent);}
        .rental-show__kv:last-child{border-bottom:0;padding-bottom:0;}
        .rental-show__dt{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px;}
        .rental-show__dd{margin:0;font-size:var(--rs-font);font-weight:600;line-height:1.42;color:var(--text);word-break:break-word;}
        .rental-show__dd--soft{font-weight:500;color:var(--muted);font-size:var(--rs-font-sm);line-height:1.45;}
        .rental-show__dd--money{font-size:13px;font-weight:700;color:color-mix(in srgb,var(--primary) 40%,var(--text));font-variant-numeric:tabular-nums;}

        .rental-show__prose{margin:0;padding:8px 10px;border-radius:var(--rs-radius-sm);font-size:var(--rs-font-sm);line-height:1.45;color:var(--text);
            background:color-mix(in srgb,var(--primary) 4%,transparent);border-left:3px solid color-mix(in srgb,var(--primary) 45%,transparent);
            white-space:pre-wrap;margin-top:8px;}

        .rental-show__landlord-profile{display:flex;align-items:center;gap:9px;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid color-mix(in srgb,var(--border) 88%,transparent);}
        .rental-show__avatar{
            width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;
            background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 52%,#0f172a));
            box-shadow:0 12px 28px -14px color-mix(in srgb,var(--primary) 50%,transparent);flex-shrink:0;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__avatar{
            background:linear-gradient(135deg,#171717,#404040);
            color:#fde047;
            box-shadow:0 12px 28px -14px rgba(0,0,0,.22);
        }
        .rental-show__avatar-name{margin:0;font-size:var(--rs-font);font-weight:600;letter-spacing:-.015em;color:var(--text);line-height:1.22;}
        .rental-show__avatar-role{margin:2px 0 0;font-size:9px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;}

        .rental-show__contact-rows{display:flex;flex-direction:column;gap:2px;}
        .rental-show__row{
            display:flex;gap:10px;padding:8px 9px;border-radius:8px;align-items:flex-start;
            transition:background .15s ease;
        }
        .rental-show__row:hover{background:color-mix(in srgb,var(--primary) 5%,transparent);}
        .rental-show__row-ico{width:26px;height:26px;border-radius:6px;display:grid;place-items:center;flex-shrink:0;font-size:10px;color:var(--primary);
            background:color-mix(in srgb,var(--primary) 10%,transparent);border:1px solid color-mix(in srgb,var(--primary) 15%,var(--border));}
        .rental-show__row-body{min-width:0;padding-top:1px;}
        .rental-show__row-lab{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:1px;}
        .rental-show__row-val{margin:0;font-size:var(--rs-font-sm);font-weight:500;line-height:1.4;color:var(--text);word-break:break-word;}
        .rental-show__row-val a{color:color-mix(in srgb,var(--primary) 70%,var(--text));text-decoration:none;font-weight:600;border-bottom:1px solid color-mix(in srgb,var(--primary) 32%,transparent);transition:border-color .15s ease,color .15s ease;}
        .rental-show__row-val a:hover{border-bottom-color:var(--primary);color:var(--primary);}

        .rental-show__danger{
            margin-top:12px;padding:10px 12px;border-radius:var(--rs-radius);border:1px solid color-mix(in srgb,#f87171 28%,var(--border));
            background:color-mix(in srgb,#f87171 6%,transparent);display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__danger{background:color-mix(in srgb,#fef2f2 94%,transparent);}
        .rental-show__danger-copy{margin:0;max-width:42ch;font-size:var(--rs-font-sm);line-height:1.4;color:var(--muted);}
        .rental-show__danger-copy strong{display:block;color:var(--text);font-weight:600;margin-bottom:2px;font-size:var(--rs-font-sm);}
        .rental-show__del{
            display:inline-flex;align-items:center;gap:5px;padding:6px 10px;font-size:var(--rs-font-sm);font-weight:600;border-radius:8px;cursor:pointer;flex-shrink:0;
            border:1px solid color-mix(in srgb,#ef4444 50%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);color:#f97373;
            transition:background .18s ease,transform .18s ease,border-color .18s ease;
        }
        .rental-show__del:hover{background:color-mix(in srgb,#ef4444 20%,transparent);border-color:color-mix(in srgb,#ef4444 65%,var(--border));transform:translateY(-1px);}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__del{color:#b91c1c;}

        /* Tabs (CSS-only radios) */
        .rental-show__tab-input{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
        .rental-show__tabs{margin-top:11px;}
        .rental-show__tablist{
            display:flex;flex-wrap:wrap;gap:5px;padding:3px;border-radius:var(--rs-radius);
            border:1px solid color-mix(in srgb,var(--border) 88%,transparent);
            background:color-mix(in srgb,var(--card) 94%,transparent);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__tablist{background:#f5f5f4;}
        .rental-show__tab-btn{
            flex:1 1 auto;min-width:0;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:5px;
            padding:7px 10px;border-radius:var(--rs-radius-sm);font-size:10px;font-weight:600;letter-spacing:.02em;
            color:var(--muted);cursor:pointer;user-select:none;border:1px solid transparent;transition:background .15s ease,color .15s ease,border-color .15s ease,box-shadow .15s ease;
        }
        .rental-show__tab-btn i{font-size:10px;opacity:.85;}
        .rental-show__tab-btn:hover{color:var(--text);background:color-mix(in srgb,var(--primary) 8%,transparent);}
        #rental-show-tab-overview:checked ~ .rental-show__tablist label[for="rental-show-tab-overview"],
        #rental-show-tab-transaction:checked ~ .rental-show__tablist label[for="rental-show-tab-transaction"],
        #rental-show-tab-bills:checked ~ .rental-show__tablist label[for="rental-show-tab-bills"],
        #rental-show-tab-land:checked ~ .rental-show__tablist label[for="rental-show-tab-land"]{
            color:color-mix(in srgb,var(--primary) 72%,var(--text));
            background:color-mix(in srgb,var(--primary) 14%,var(--card));
            border-color:color-mix(in srgb,var(--primary) 28%,var(--border));
            box-shadow:0 1px 0 color-mix(in srgb,#fff 8%,transparent) inset;
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) #rental-show-tab-overview:checked ~ .rental-show__tablist label[for="rental-show-tab-overview"],
        :is(html[data-theme="light"],html[data-theme="light_blue"]) #rental-show-tab-transaction:checked ~ .rental-show__tablist label[for="rental-show-tab-transaction"],
        :is(html[data-theme="light"],html[data-theme="light_blue"]) #rental-show-tab-bills:checked ~ .rental-show__tablist label[for="rental-show-tab-bills"],
        :is(html[data-theme="light"],html[data-theme="light_blue"]) #rental-show-tab-land:checked ~ .rental-show__tablist label[for="rental-show-tab-land"]{
            background:#fff;color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.06);
        }
        .rental-show__tabpanel{display:none;margin-top:10px;}
        #rental-show-tab-overview:checked ~ .rental-show__tabpanel--overview,
        #rental-show-tab-transaction:checked ~ .rental-show__tabpanel--transaction,
        #rental-show-tab-bills:checked ~ .rental-show__tabpanel--bills,
        #rental-show-tab-land:checked ~ .rental-show__tabpanel--land{display:block;}
        .rental-show__highlight{margin-top:0;}
        .rental-show__empty-muted{margin:0;font-size:var(--rs-font-sm);line-height:1.42;color:var(--muted);padding:10px;border-radius:var(--rs-radius-sm);
            border:1px dashed color-mix(in srgb,var(--border) 75%,transparent);background:color-mix(in srgb,var(--card) 92%,transparent);}
        .rental-show__overview-stack{display:flex;flex-direction:column;gap:10px;width:100%;}
        .rental-show__countdown{
            border-radius:var(--rs-radius);border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));
            background:linear-gradient(165deg,color-mix(in srgb,var(--primary) 8%,var(--card)),color-mix(in srgb,var(--card) 96%,transparent));
            overflow:hidden;box-shadow:0 10px 32px -24px color-mix(in srgb,var(--primary) 32%,#000);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__countdown{
            background:linear-gradient(165deg,color-mix(in srgb,var(--primary) 6%,#fff),#fafaf9);
            box-shadow:0 12px 32px -24px rgba(0,0,0,.08);
        }
        .rental-show__countdown-top{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 82%,transparent);}
        .rental-show__countdown-kicker{font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
        .rental-show__count-num{margin:3px 0 0;line-height:1.05;font-size:clamp(1.2rem,2.6vw,1.42rem);font-weight:700;letter-spacing:-.03em;color:color-mix(in srgb,var(--primary) 45%,var(--text));font-variant-numeric:tabular-nums;}
        .rental-show__count-num small{display:block;margin-top:3px;font-size:var(--rs-font-xs);font-weight:600;letter-spacing:0;color:var(--muted);text-transform:none;}
        .rental-show__count-next{margin:0;max-width:24ch;text-align:right;font-size:var(--rs-font-xs);line-height:1.38;color:var(--muted);font-weight:500;}
        .rental-show__count-next strong{display:block;color:var(--text);font-size:var(--rs-font-sm);margin-bottom:2px;font-weight:600;}
        .rental-show__meter-wrap{padding:0 12px 10px;}
        .rental-show__meter-lab{display:flex;justify-content:space-between;align-items:center;font-size:8px;font-weight:600;color:var(--muted);margin-bottom:5px;letter-spacing:.035em;text-transform:uppercase;}
        .rental-show__meter{
            height:7px;border-radius:999px;overflow:hidden;
            background:color-mix(in srgb,var(--border) 70%,transparent);
            border:1px solid color-mix(in srgb,var(--border) 88%,transparent);
        }
        .rental-show__meter-fill{height:100%;border-radius:inherit;width:0;background:linear-gradient(90deg,color-mix(in srgb,var(--primary) 75%,var(--text)),var(--primary));transition:width .35s ease;}
        .rental-show__meter--hot .rental-show__meter-fill{background:linear-gradient(90deg,#f59e0b,#ef4444);}
        .rental-show__meter--overdue .rental-show__meter-fill{background:linear-gradient(90deg,#f87171,#b91c1c);}

        .rental-show__countdown--overdue{
            border-color:color-mix(in srgb,#ef4444 48%,var(--border));
            background:linear-gradient(165deg,color-mix(in srgb,#ef4444 12%,var(--card)),color-mix(in srgb,var(--card) 96%,transparent));
            box-shadow:0 12px 36px -22px color-mix(in srgb,#ef4444 35%,#000);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__countdown--overdue{
            background:linear-gradient(165deg,color-mix(in srgb,#fef2f2 94%,transparent),#fff);
            box-shadow:0 12px 34px -22px rgba(220,38,38,.22);
        }
        .rental-show__countdown--overdue .rental-show__countdown-kicker{color:#f87171;font-weight:700;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__countdown--overdue .rental-show__countdown-kicker{color:#dc2626;}
        .rental-show__count-num--overdue{color:#f87171!important;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__count-num--overdue{color:#b91c1c!important;}
        .rental-show__count-num--overdue small{color:color-mix(in srgb,#fecaca 75%,var(--muted));}
        @keyframes rental-show-count-overdue-flash{
            0%,100%{filter:brightness(1);}
            50%{filter:brightness(1.12);}
        }
        .rental-show__countdown--overdue .rental-show__count-num--overdue{animation:rental-show-count-overdue-flash 2.1s ease-in-out infinite;}
        @media (prefers-reduced-motion:reduce){
            .rental-show__countdown--overdue .rental-show__count-num--overdue{animation:none;}
        }

        .rental-show__tx-h{margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
        .rental-show__tx-lead{margin:0 0 10px;font-size:11px;line-height:1.45;color:var(--muted);max-width:78ch;}
        .rental-show__tx-lead strong{color:var(--text);font-weight:600;}
        .rental-show__tx-scroll{max-height:400px;overflow:auto;border:1px solid var(--border);border-radius:var(--rs-radius);}
        .rental-show__tx-table{width:100%;border-collapse:collapse;font-size:12px;}
        .rental-show__tx-table th{text-align:left;padding:8px 10px;background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:1;}
        .rental-show__tx-table td{padding:8px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 75%,transparent);vertical-align:top;}
        .rental-show__tx-table tr:last-child td{border-bottom:none;}
        .rental-show__tx-amt{font-weight:800;font-variant-numeric:tabular-nums;}
        .rental-show__tx-empty{padding:18px;text-align:center;color:var(--muted);font-size:12px;}
        .rental-show__tx-status{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:3px 7px;border-radius:999px;border:1px solid var(--border);}
        .rental-show__tx-status--paid{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#bbf7d0 70%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__tx-status--paid{color:#166534;}
        .rental-show__tx-status--open{border-color:color-mix(in srgb,var(--border) 90%,transparent);background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--muted);}
        .rental-show__tx-status--late{border-color:color-mix(in srgb,#ef4444 52%,var(--border));background:color-mix(in srgb,#ef4444 14%,transparent);color:color-mix(in srgb,#fecaca 78%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__tx-status--late{color:#991b1b;}
        @keyframes rental-show-tx-row-late{
            0%,100%{background-color:color-mix(in srgb,#ef4444 9%,transparent);}
            50%{background-color:color-mix(in srgb,#dc2626 13%,transparent);}
        }
        .rental-show__tx-table tr.rental-show__tx-row--late > td{background-color:color-mix(in srgb,#ef4444 9%,transparent);animation:rental-show-tx-row-late 2.15s ease-in-out infinite;border-left:none;}
        .rental-show__tx-table tr.rental-show__tx-row--late > td:first-child{box-shadow:inset 3px 0 0 #dc2626;}
        @media (prefers-reduced-motion:reduce){
            .rental-show__tx-table tr.rental-show__tx-row--late > td{animation:none;}
        }
        .rental-show__tx-paid{margin:0;font-size:11px;line-height:1.38;color:var(--text);}
        .rental-show__tx-paid strong{font-weight:700;display:block;margin-bottom:2px;}
        .rental-show__tx-paid-meta{display:block;font-size:10px;color:var(--muted);margin-top:2px;font-variant-numeric:tabular-nums;}
        .rental-show__tx-cell-actions{vertical-align:middle;width:1%;white-space:nowrap;}
        .rental-show__tx-actions{display:flex;flex-wrap:wrap;gap:5px;}
        .rental-show__tx-btn{
            display:inline-flex;align-items:center;justify-content:center;gap:4px;padding:4px 8px;font-size:10px;font-weight:700;
            border-radius:7px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;font-family:inherit;
        }
        .rental-show__tx-btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .rental-show__tx-btn:disabled{opacity:.45;cursor:not-allowed;}
        .rental-show__tx-btn--go{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
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
        .rental-show__saved-banner{
            margin:0 0 12px;padding:10px 12px;border-radius:var(--rs-radius);font-size:12px;line-height:1.45;
            display:flex;align-items:flex-start;gap:8px;border:1px solid color-mix(in srgb,#22c55e 45%,var(--border));
            background:color-mix(in srgb,#22c55e 10%,transparent);color:var(--text);
        }
        .rental-show__saved-banner i{margin-top:2px;color:#4ade80;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .rental-show__saved-banner i{color:#15803d;}
    </style>

    <header @class(['rental-show__hero', 'rental-show__hero--payment-overdue' => ! empty($rentalPaymentOverdue)])>
        <div class="rental-show__hero-glow" aria-hidden="true"></div>
        <div class="rental-show__hero-inner">
            <div class="rental-show__hero-top">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                    <a class="rental-show__back" href="{{ route('account.rentals.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i>All rentals</a>
                    @if(isset($business) && $business)
                        <a class="rental-show__back" href="{{ route('account.rentals.edit', $rental) }}" title="Edit rental details"><i class="fa fa-pen-to-square" aria-hidden="true"></i>Edit</a>
                    @endif
                </div>
                @if($rental->business)
                    <span class="rental-show__biz-chip"><i class="fa fa-briefcase" aria-hidden="true"></i>{{ $rental->business->name }}</span>
                @endif
            </div>
            <h1 class="rental-show__headline">{{ $rental->property_type }}</h1>
            <div class="rental-show__pills">
                @if(!empty($rentalPaymentOverdue))
                    <span class="rental-show__pill rental-show__pill--overdue"><i class="fa fa-circle-exclamation" aria-hidden="true"></i>Overdue</span>
                @endif
                <span class="rental-show__pill"><i class="fa fa-clock" aria-hidden="true"></i>{{ $recurringTypes[$rental->recurring_type] ?? $rental->recurring_type }}</span>
                <span class="rental-show__pill"><i class="fa fa-calendar-days" aria-hidden="true"></i>Agreement until {{ $rental->agreement_valid_until_year }}</span>
                @if($rental->warehouse)
                    <span class="rental-show__pill"><i class="fa fa-code-branch" aria-hidden="true"></i>{{ $rental->warehouse->name }}</span>
                @endif
                @if($rental->due_date)
                    <span class="rental-show__pill"><i class="fa fa-calendar-day" aria-hidden="true"></i>Due {{ $rental->due_date->format('M j, Y') }}</span>
                @endif
                @if($rental->first_installment_due_date)
                    <span class="rental-show__pill"><i class="fa fa-receipt" aria-hidden="true"></i>First installment {{ $rental->first_installment_due_date->format('M j, Y') }}</span>
                @endif
            </div>
        </div>
    </header>

    @if(session('status'))
        <div class="rental-show__saved-banner" role="status"><i class="fa fa-circle-check" aria-hidden="true"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="rental-show__tabs">
        <input type="radio" name="rental-show-tab" id="rental-show-tab-overview" class="rental-show__tab-input" checked>
        <input type="radio" name="rental-show-tab" id="rental-show-tab-transaction" class="rental-show__tab-input">
        <input type="radio" name="rental-show-tab" id="rental-show-tab-bills" class="rental-show__tab-input">
        <input type="radio" name="rental-show-tab" id="rental-show-tab-land" class="rental-show__tab-input">

        <div class="rental-show__tablist" role="tablist" aria-label="Rental detail sections">
            <label id="rental-show-tab-label-overview" for="rental-show-tab-overview" class="rental-show__tab-btn" role="tab"><i class="fa fa-layer-group" aria-hidden="true"></i>Overview</label>
            <label id="rental-show-tab-label-transaction" for="rental-show-tab-transaction" class="rental-show__tab-btn" role="tab"><i class="fa fa-money-bill-wave" aria-hidden="true"></i>Transaction details</label>
            <label id="rental-show-tab-label-bills" for="rental-show-tab-bills" class="rental-show__tab-btn" role="tab"><i class="fa fa-file-invoice-dollar" aria-hidden="true"></i>Linked bills</label>
            <label id="rental-show-tab-label-land" for="rental-show-tab-land" class="rental-show__tab-btn" role="tab"><i class="fa fa-map-location-dot" aria-hidden="true"></i>Land details</label>
        </div>

        <div class="rental-show__tabpanel rental-show__tabpanel--overview" role="tabpanel" id="rental-show-panel-overview" aria-labelledby="rental-show-tab-label-overview">
            <div class="rental-show__overview-stack">
            @if(!empty($rentalPaymentOverdue))
                <div class="rental-show__notify-rental-overdue" role="alert">
                    <i class="fa fa-circle-exclamation" aria-hidden="true"></i>
                    <span><strong>Unpaid billing due</strong> At least one scheduled rent date on or before today has no ledger payment recorded for that date. Log the payment so your schedule stays accurate.</span>
                </div>
            @endif
            @if($nextPaymentInsight)
                <section @class([
                    'rental-show__countdown',
                    'rental-show__countdown--overdue' => ! empty($rentalPaymentOverdue),
                ]) aria-labelledby="rental-countdown-heading">
                    <div class="rental-show__countdown-top">
                        <div>
                            <span class="rental-show__countdown-kicker" id="rental-countdown-heading">Next recurring payment</span>
                            <div @class([
                                'rental-show__count-num',
                                'rental-show__count-num--overdue' => ! empty($rentalPaymentOverdue),
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
                            <p class="rental-show__count-next">
                                <strong>{{ $nextPaymentInsight['next_date']->format('M j, Y') }}</strong>
                                <span style="display:block;margin-top:4px;">{{ $recurringTypes[$rental->recurring_type] ?? '' }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="rental-show__meter-wrap">
                        <div class="rental-show__meter-lab"><span>Approaching due date</span><span>{{ $nextPaymentInsight['progress_percent'] }}%</span></div>
                        <div @class([
                            'rental-show__meter',
                            'rental-show__meter--overdue' => ! empty($rentalPaymentOverdue),
                            'rental-show__meter--hot' => empty($rentalPaymentOverdue) && ($nextPaymentInsight['progress_percent'] ?? 0) >= 85,
                        ]) role="progressbar"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ (int) round($nextPaymentInsight['progress_percent']) }}"
                            aria-label="Progress within the countdown window until the next recurring payment">
                            <div class="rental-show__meter-fill" style="width: {{ $nextPaymentInsight['progress_percent'] }}%;"></div>
                        </div>
                    </div>
                </section>
            @else
                <div class="rental-show__panel" style="box-shadow:none;">
                    <div class="rental-show__panel-body">
                        <p class="rental-show__empty-muted" style="border:none;background:transparent;padding:4px 0;"><strong style="display:block;color:var(--text);margin-bottom:3px;font-weight:600;font-size:var(--rs-font-sm);">No countdown yet</strong>Add a due date or first installment date under <strong>Transaction details</strong> to estimate the next rent payment.</p>
                    </div>
                </div>
            @endif
            <div class="rental-show__highlight">
                <div class="rental-show__cost-card">
                    <div class="rental-show__cost-lab">Recurring cost</div>
                    <div class="rental-show__cost-val">
                        @if($detailCurrency)<span class="rental-show__curr">{{ $detailCurrency }}</span>@endif{{ number_format((float) $rental->recurring_cost, 2, '.', ',') }}
                    </div>
                    <div class="rental-show__mini-sub" style="margin-top:4px;opacity:.88;">Per billing period · {{ $recurringTypes[$rental->recurring_type] ?? $rental->recurring_type }}</div>
                </div>
                <div class="rental-show__mini-stats">
                    @if($rental->key_money !== null && (float) $rental->key_money > 0)
                        <div class="rental-show__key-card" role="group" aria-labelledby="rental-keymoney-label">
                            <span class="rental-show__key-ico-wrap" aria-hidden="true"><i class="fa fa-key"></i></span>
                            <div class="rental-show__key-inner">
                                <div class="rental-show__key-lab" id="rental-keymoney-label">Key money</div>
                                <div class="rental-show__key-val">
                                    @if($detailCurrency)<span class="rental-show__key-curr">{{ $detailCurrency }}</span>@endif{{ number_format((float) $rental->key_money, 2, '.', ',') }}
                                </div>
                                <p class="rental-show__key-note">One-off amount on the lease · not included in recurring cost above.</p>
                            </div>
                        </div>
                    @endif
                    @if($rental->remind_before_days !== null && (int) $rental->remind_before_days > 0)
                        <div class="rental-show__mini">
                            <span class="rental-show__mini-ico" aria-hidden="true"><i class="fa fa-bell"></i></span>
                            <div>
                                <span class="rental-show__mini-txt">{{ (int) $rental->remind_before_days }} day{{ (int) $rental->remind_before_days === 1 ? '' : 's' }} ahead</span>
                                <span class="rental-show__mini-sub">Renewal reminder</span>
                            </div>
                        </div>
                    @endif
                    @if(($rental->key_money === null || (float) $rental->key_money <= 0) && ($rental->remind_before_days === null || (int) $rental->remind_before_days <= 0))
                        <div class="rental-show__mini">
                            <span class="rental-show__mini-ico" aria-hidden="true"><i class="fa fa-circle-info"></i></span>
                            <div>
                                <span class="rental-show__mini-txt">Open <strong>Transaction details</strong> for rent billing, <strong>Linked bills</strong> for property-tied invoices, and <strong>Land details</strong> for premises &amp; landlord.</span>
                                <span class="rental-show__mini-sub">Quick summary</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            </div>
        </div>

        <div class="rental-show__tabpanel rental-show__tabpanel--transaction" role="tabpanel" id="rental-show-panel-transaction" aria-labelledby="rental-show-tab-label-transaction">
            <div class="rental-show__panels" style="grid-template-columns:1fr;">
                <section class="rental-show__panel" aria-labelledby="rental-amounts-heading">
                    <h2 class="rental-show__panel-h" id="rental-amounts-heading"><i class="fa fa-coins" aria-hidden="true"></i>Amounts</h2>
                    <div class="rental-show__panel-body">
                        <dl class="rental-show__dl rental-show__dl--2">
                            <div class="rental-show__kv">
                                <dt class="rental-show__dt">Recurring cost</dt>
                                <dd class="rental-show__dd rental-show__dd--money">
                                    @if($detailCurrency)<span class="rental-show__curr" style="font-size:9px;">{{ $detailCurrency }}</span>@endif{{ number_format((float) $rental->recurring_cost, 2, '.', ',') }}
                                    <span class="rental-show__mini-sub" style="display:block;margin-top:4px;font-weight:600;">{{ $recurringTypes[$rental->recurring_type] ?? $rental->recurring_type }}</span>
                                </dd>
                            </div>
                            @if($rental->key_money !== null && (float) $rental->key_money > 0)
                                <div class="rental-show__kv">
                                    <dt class="rental-show__dt">Key money</dt>
                                    <dd class="rental-show__dd rental-show__dd--money">
                                        @if($detailCurrency)<span class="rental-show__curr" style="font-size:9px;">{{ $detailCurrency }}</span>@endif{{ number_format((float) $rental->key_money, 2, '.', ',') }}
                                        <span class="rental-show__mini-sub" style="display:block;margin-top:4px;font-weight:600;">One-time</span>
                                    </dd>
                                </div>
                            @else
                                <div class="rental-show__kv">
                                    <dt class="rental-show__dt">Key money</dt>
                                    <dd class="rental-show__dd rental-show__dd--soft">—</dd>
                                </div>
                            @endif
                            <div class="rental-show__kv">
                                <dt class="rental-show__dt">Billing cadence</dt>
                                <dd class="rental-show__dd">{{ $recurringTypes[$rental->recurring_type] ?? $rental->recurring_type }}</dd>
                            </div>
                            <div class="rental-show__kv">
                                <dt class="rental-show__dt">Agreement ends (year)</dt>
                                <dd class="rental-show__dd">{{ $rental->agreement_valid_until_year }}</dd>
                            </div>
                            <div class="rental-show__kv">
                                <dt class="rental-show__dt">Renewal reminder</dt>
                                <dd class="rental-show__dd">
                                    @if($rental->remind_before_days !== null && (int) $rental->remind_before_days > 0)
                                        {{ (int) $rental->remind_before_days }} day{{ (int) $rental->remind_before_days === 1 ? '' : 's' }} before end
                                    @else
                                        <span class="rental-show__dd--soft">Not set</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="rental-show__kv">
                                <dt class="rental-show__dt">Due date</dt>
                                <dd class="{{ $rental->due_date ? 'rental-show__dd' : 'rental-show__dd rental-show__dd--soft' }}">{{ $rental->due_date ? $rental->due_date->format('M j, Y') : 'Not set' }}</dd>
                            </div>
                            <div class="rental-show__kv">
                                <dt class="rental-show__dt">First installment due</dt>
                                <dd class="{{ $rental->first_installment_due_date ? 'rental-show__dd' : 'rental-show__dd rental-show__dd--soft' }}">{{ $rental->first_installment_due_date ? $rental->first_installment_due_date->format('M j, Y') : 'Not set' }}</dd>
                            </div>
                            @if($rental->deductAccount)
                                <div class="rental-show__kv" style="grid-column:1/-1;">
                                    <dt class="rental-show__dt">Debit account</dt>
                                    <dd class="rental-show__dd rental-show__dd--soft">{{ $rental->deductAccount->deductOptionLabel() }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </section>

                <section class="rental-show__panel" aria-labelledby="rental-schedule-heading">
                    <h2 class="rental-show__panel-h" id="rental-schedule-heading"><i class="fa fa-calendar-check" aria-hidden="true"></i>Billing schedule &amp; payment status</h2>
                    <div class="rental-show__panel-body">
                        @if($rentalScheduleRows->isEmpty())
                            <p class="rental-show__tx-empty" style="border:1px dashed color-mix(in srgb,var(--border) 80%,transparent);border-radius:var(--rs-radius-sm);padding:16px;">
                                Add a <strong style="color:var(--text);">due date</strong> or <strong style="color:var(--text);">first installment</strong> date to build the schedule through agreement end ({{ $rental->agreement_valid_until_year }}).
                            </p>
                        @else
                            <p class="rental-show__tx-lead"><strong>Make payment</strong> posts a ledger row for that billing date and debits the account you pick (same as loan installments). <strong>Paid</strong> means a payment is already logged. <strong>Outstanding</strong> is a future date unless you pay early.</p>
                            @if(isset($accounts) && $accounts->isEmpty())
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#f97316 70%,var(--muted));"><i class="fa fa-wallet"></i> Add a business account before you can record payments from here.</p>
                            @endif
                            @error('occurrence_date')
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#ef4444 82%,var(--muted));">{{ $message }}</p>
                            @enderror
                            @error('deduct_account_id')
                                <p style="margin:-4px 0 10px;font-size:11px;color:color-mix(in srgb,#ef4444 82%,var(--muted));">{{ $message }}</p>
                            @enderror
                            <div class="rental-show__tx-scroll">
                                <table class="rental-show__tx-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Due date</th>
                                            <th>Billing amount</th>
                                            <th>Status</th>
                                            <th>Paid details</th>
                                            <th class="rental-show__tx-cell-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rentalScheduleRows as $srow)
                                            @php($leg = $srow['ledger'] ?? null)
                                            <tr @class(['rental-show__tx-row--late' => $srow['past_due_unpaid']])>
                                                <td>{{ $srow['period'] }}</td>
                                                <td>{{ $srow['due']->format('M j, Y') }}</td>
                                                <td class="rental-show__tx-amt">@if($detailCurrency)<span style="opacity:.72;font-size:10px;">{{ $detailCurrency }}</span> @endif{{ $srow['amount_formatted'] }}</td>
                                                <td>
                                                    @if($srow['paid'])
                                                        <span class="rental-show__tx-status rental-show__tx-status--paid"><i class="fa fa-circle-check" aria-hidden="true"></i>Paid</span>
                                                    @elseif($srow['past_due_unpaid'])
                                                        <span class="rental-show__tx-status rental-show__tx-status--late"><i class="fa fa-circle-exclamation" aria-hidden="true"></i>{{ $srow['status_label'] }}</span>
                                                    @else
                                                        <span class="rental-show__tx-status rental-show__tx-status--open"><i class="fa fa-clock" aria-hidden="true"></i>{{ $srow['status_label'] }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($srow['paid'] && $leg)
                                                        @php($accLabel = $leg->deductAccount?->deductOptionLabel() ?? '—')
                                                        <p class="rental-show__tx-paid">
                                                            <strong>Posted {{ $leg->occurrence_date?->format('M j, Y') ?? '—' }}</strong>
                                                            <span class="rental-show__tx-paid-meta">
                                                                @if($leg->currency)<span>{{ $leg->currency }}</span> @endif{{ number_format((float) $leg->amount, 2, '.', ',') }}
                                                                · {{ $accLabel }}
                                                            </span>
                                                        </p>
                                                    @elseif($srow['paid'])
                                                        <span class="rental-show__dd--soft">Recorded (open ledger row for detail)</span>
                                                    @else
                                                        <span class="rental-show__dd--soft">—</span>
                                                    @endif
                                                </td>
                                                <td class="rental-show__tx-cell-actions">
                                                    <div class="rental-show__tx-actions">
                                                        @if($srow['paid'] && $leg)
                                                            @php($recAcc = $leg->deductAccount?->deductOptionLabel() ?? '—')
                                                            @php($recCur = ($leg->currency ?? '') !== '' ? $leg->currency : $detailCurrency)
                                                            @php($recAmt = trim($recCur.' '.number_format((float) $leg->amount, 2, '.', ',')))
                                                            <button type="button" class="rental-show__tx-btn js-rental-payment-open-receipt"
                                                                data-payment-due-human="{{ $srow['due']->format('M j, Y') }}"
                                                                data-payment-amount-fmt="{{ $recAmt }}"
                                                                data-payment-account="{{ e($recAcc) }}"><i class="fa fa-receipt"></i>View receipt</button>
                                                        @elseif(! $srow['paid'])
                                                            <button type="button"
                                                                class="rental-show__tx-btn rental-show__tx-btn--go js-rental-payment-open-settle"
                                                                data-occurrence="{{ $srow['due_ymd'] }}"
                                                                data-due-human="{{ $srow['due']->format('M j, Y') }}"
                                                                data-amount-fmt-display="@if($detailCurrency){{ $detailCurrency }} @endif{{ $srow['amount_formatted'] }}"
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

                <section class="rental-show__panel" aria-labelledby="rental-ledger-heading">
                    <h2 class="rental-show__panel-h" id="rental-ledger-heading"><i class="fa fa-book" aria-hidden="true"></i>Ledger payments logged</h2>
                    <div class="rental-show__panel-body">
                        @if($rentalLedgerRows->isEmpty())
                            <p class="rental-show__tx-empty">No rent payments in the ledger yet for this lease. Paid rows above will appear once payments are recorded against this rental.</p>
                        @else
                            <div class="rental-show__tx-scroll" style="max-height:340px;">
                                <table class="rental-show__tx-table">
                                    <thead>
                                        <tr>
                                            <th>Occurred</th>
                                            <th>Amount</th>
                                            <th>Account debited</th>
                                            <th>Cadence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rentalLedgerRows as $row)
                                            <tr>
                                                <td>{{ $row->occurrence_date?->format('M j, Y') ?? '—' }}</td>
                                                <td class="rental-show__tx-amt">
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

                @if($rental->notes)
                    <section class="rental-show__panel" aria-labelledby="rental-tx-notes-heading">
                        <h2 class="rental-show__panel-h" id="rental-tx-notes-heading"><i class="fa fa-note-sticky" aria-hidden="true"></i>Internal notes</h2>
                        <div class="rental-show__panel-body">
                            <p class="rental-show__prose" style="margin-top:0;">{{ $rental->notes }}</p>
                        </div>
                    </section>
                @endif
            </div>
        </div>

        <div class="rental-show__tabpanel rental-show__tabpanel--bills" role="tabpanel" id="rental-show-panel-bills" aria-labelledby="rental-show-tab-label-bills">
            <div class="rental-show__panels" style="grid-template-columns:1fr;">
                <section class="rental-show__panel" aria-labelledby="rental-linked-bills-heading">
                    <h2 class="rental-show__panel-h" id="rental-linked-bills-heading"><i class="fa fa-file-invoice-dollar" aria-hidden="true"></i>Linked bills</h2>
                    <div class="rental-show__panel-body">
                        <p class="rental-show__tx-lead">Bills you attached to this rental (utilities, services, charges). Recording payments stays on each bill&apos;s schedule and ledger.</p>
                        @if($rental->bills->isEmpty())
                            <p class="rental-show__empty-muted" style="margin-top:10px;">
                                No bills are linked yet. When you <a href="{{ route('account.bills.index') }}" style="color:inherit;font-weight:700;">add or edit a bill</a>,
                                enable <strong>Link to a rental record</strong> and choose <strong>{{ $rental->property_type }}</strong>.
                            </p>
                        @else
                            <div class="rental-show__tx-scroll" style="max-height:none;">
                                <table class="rental-show__tx-table">
                                    <thead>
                                        <tr>
                                            <th>Bill</th>
                                            <th>Type</th>
                                            <th>Schedule</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th class="rental-show__tx-cell-actions">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rental->bills as $rBill)
                                            @php($billOv = ($rentalBillPaymentOverdue[$rBill->id] ?? false))
                                            <tr @class(['rental-show__tx-row--late' => $billOv])>
                                                <td><strong>{{ $rBill->name }}</strong>@if($rBill->description)<br><span class="rental-show__dd--soft" style="font-size:11px;line-height:1.35;">{{ \Illuminate\Support\Str::limit((string) $rBill->description, 96) }}</span>@endif</td>
                                                <td><span style="white-space:nowrap;">{{ \Illuminate\Support\Str::limit($rBill->categoryDisplayLabel(), 26) }}</span></td>
                                                <td>
                                                    @if($rBill->isOneTime())
                                                        {{ $billPaymentModes[$rBill->payment_mode] ?? $rBill->payment_mode }}
                                                    @else
                                                        {{ $billRecurringLabels[$rBill->recurring_type] ?? $rBill->recurring_type }}
                                                    @endif
                                                </td>
                                                <td class="rental-show__tx-amt">
                                                    @if($rBill->amount_varies_by_usage)
                                                        Varies
                                                        @if((float) $rBill->recurring_cost > 0)
                                                            <span style="font-weight:600;"> (~ {{ trim(($detailCurrency ? $detailCurrency.' ' : '').number_format((float) $rBill->recurring_cost, 2, '.', ',')) }} typical)</span>
                                                        @endif
                                                    @else
                                                        @if($detailCurrency)
                                                            <span style="opacity:.72;font-size:10px;">{{ $detailCurrency }}</span>
                                                        @endif
                                                        {{ number_format((float) $rBill->recurring_cost, 2, '.', ',') }}
                                                        @if($rBill->isOneTime())
                                                            <span style="display:block;font-size:10px;color:var(--muted);margin-top:2px;font-weight:600;">One-time total</span>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($billOv)
                                                        <span class="rental-show__tx-status rental-show__tx-status--late"><i class="fa fa-circle-exclamation" aria-hidden="true"></i>Overdue</span>
                                                    @else
                                                        <span class="rental-show__tx-status rental-show__tx-status--open"><i class="fa fa-circle-check" aria-hidden="true"></i>No overdue</span>
                                                    @endif
                                                </td>
                                                <td class="rental-show__tx-cell-actions">
                                                    <a href="{{ route('account.bills.show', $rBill) }}" class="rental-show__tx-btn rental-show__tx-btn--go"><i class="fa fa-file-lines" aria-hidden="true"></i>View bill</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <div class="rental-show__tabpanel rental-show__tabpanel--land" role="tabpanel" id="rental-show-panel-land" aria-labelledby="rental-show-tab-label-land">
            <div class="rental-show__panels">
                <section class="rental-show__panel" aria-labelledby="rental-premises-heading">
                    <h2 class="rental-show__panel-h" id="rental-premises-heading"><i class="fa fa-building" aria-hidden="true"></i>Premises &amp; use</h2>
                    <div class="rental-show__panel-body">
                        <dl class="rental-show__dl rental-show__dl--2">
                            <div class="rental-show__kv">
                                <dt class="rental-show__dt">Property type</dt>
                                <dd class="rental-show__dd">{{ $rental->property_type }}</dd>
                            </div>
                            @if($rental->warehouse)
                                <div class="rental-show__kv">
                                    <dt class="rental-show__dt">Branch / site</dt>
                                    <dd class="rental-show__dd">{{ $rental->warehouse->name }}</dd>
                                </div>
                            @endif
                            @if($rental->business)
                                <div class="rental-show__kv" style="grid-column:1/-1;">
                                    <dt class="rental-show__dt">Business</dt>
                                    <dd class="rental-show__dd">{{ $rental->business->name }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if($rental->purpose)
                            <div>
                                <div class="rental-show__dt" style="margin-top:2px;">Purpose / use</div>
                                <p class="rental-show__prose">{{ $rental->purpose }}</p>
                            </div>
                        @else
                            <p class="rental-show__empty-muted" style="margin-top:10px;">No purpose or use description recorded.</p>
                        @endif
                    </div>
                </section>

                @if($rental->landlord)
                    <section class="rental-show__panel" aria-labelledby="rental-landlord-heading">
                        <h2 class="rental-show__panel-h" id="rental-landlord-heading"><i class="fa fa-address-book" aria-hidden="true"></i>Landlord</h2>
                        <div class="rental-show__panel-body">
                            <div class="rental-show__landlord-profile">
                                <div class="rental-show__avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(trim((string) $rental->landlord->name), 0, 1) ?: '?') }}</div>
                                <div>
                                    <p class="rental-show__avatar-name">{{ $rental->landlord->name }}</p>
                                    <p class="rental-show__avatar-role">Address book contact</p>
                                </div>
                            </div>
                            <div class="rental-show__contact-rows">
                                @if($rental->landlord->email)
                                    <div class="rental-show__row">
                                        <span class="rental-show__row-ico" aria-hidden="true"><i class="fa fa-envelope"></i></span>
                                        <div class="rental-show__row-body">
                                            <div class="rental-show__row-lab">Email</div>
                                            <p class="rental-show__row-val"><a href="mailto:{{ $rental->landlord->email }}">{{ $rental->landlord->email }}</a></p>
                                        </div>
                                    </div>
                                @endif
                                @if($rental->landlord->phone)
                                    <div class="rental-show__row">
                                        <span class="rental-show__row-ico" aria-hidden="true"><i class="fa fa-phone"></i></span>
                                        <div class="rental-show__row-body">
                                            <div class="rental-show__row-lab">Phone</div>
                                            <p class="rental-show__row-val"><a href="tel:{{ preg_replace('/\s+/', '', $rental->landlord->phone) }}">{{ $rental->landlord->phone }}</a></p>
                                        </div>
                                    </div>
                                @endif
                                @if($rental->landlord->street_address)
                                    <div class="rental-show__row">
                                        <span class="rental-show__row-ico" aria-hidden="true"><i class="fa fa-location-dot"></i></span>
                                        <div class="rental-show__row-body">
                                            <div class="rental-show__row-lab">Address</div>
                                            <p class="rental-show__row-val">{{ $rental->landlord->street_address }}</p>
                                        </div>
                                    </div>
                                @endif
                                @if($rental->landlord->bank_account_details)
                                    <div class="rental-show__row">
                                        <span class="rental-show__row-ico" aria-hidden="true"><i class="fa fa-building-columns"></i></span>
                                        <div class="rental-show__row-body">
                                            <div class="rental-show__row-lab">Bank / payment</div>
                                            <p class="rental-show__row-val" style="white-space:pre-wrap;">{{ $rental->landlord->bank_account_details }}</p>
                                        </div>
                                    </div>
                                @endif
                                @if($rental->landlord->notes)
                                    <div class="rental-show__row">
                                        <span class="rental-show__row-ico" aria-hidden="true"><i class="fa fa-note-sticky"></i></span>
                                        <div class="rental-show__row-body">
                                            <div class="rental-show__row-lab">Contact notes</div>
                                            <p class="rental-show__row-val" style="white-space:pre-wrap;font-weight:500;">{{ $rental->landlord->notes }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </section>
                @else
                    <section class="rental-show__panel" aria-labelledby="rental-landlord-heading">
                        <h2 class="rental-show__panel-h" id="rental-landlord-heading"><i class="fa fa-address-book" aria-hidden="true"></i>Landlord</h2>
                        <div class="rental-show__panel-body">
                            <p class="rental-show__empty-muted">No landlord linked to this rental. Add one when editing the rental if needed.</p>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>

    <footer class="rental-show__danger">
        <p class="rental-show__danger-copy"><strong>Remove rental</strong> — Deletes this lease from your portfolio; the landlord contact remains in your address book.</p>
        <form method="post" action="{{ route('account.rentals.destroy', $rental) }}" style="margin:0;" onsubmit="return confirm('Remove this rental record?');">
            @csrf
            @method('delete')
            <button type="submit" class="rental-show__del"><i class="fa fa-trash-can" aria-hidden="true"></i>Remove rental</button>
        </form>
    </footer>
</div>

<div id="rental-settle-modal"
    class="loan-show-modal{{ $rentalSettleModalShouldOpen ? ' loan-show-modal--open' : '' }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="rental-settle-modal-title"
    aria-hidden="{{ $rentalSettleModalShouldOpen ? 'false' : 'true' }}">
    <div class="loan-show-modal__backdrop" data-close-rental-settle tabindex="-1"></div>
    <div class="loan-show-modal__panel">
        <div class="loan-show-modal__head">
            <h2 id="rental-settle-modal-title">Record rent payment</h2>
            <button type="button" class="loan-show-modal__close" data-close-rental-settle aria-label="Close">&times;</button>
        </div>
        <div class="loan-show-modal__body">
            <form method="post" action="{{ route('account.rentals.billing.settle', $rental) }}">
                @csrf
                <input type="hidden" name="occurrence_date" id="rental-settle-occurrence" value="{{ old('occurrence_date') }}">
                <div class="loan-show-modal__summ">
                    <span style="color:var(--muted);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Billing date</span>
                    <strong id="rental-settle-due-display" style="font-size:14px;">—</strong>
                </div>
                <span class="loan-show-modal__lbl">Billing amount</span>
                <div class="loan-show-modal__summ" style="margin-top:5px;"><strong id="rental-settle-amount-display">{{ $rentBillingAmountDefaultDisplay }}</strong></div>
                <span class="loan-show-modal__lbl">Debit from account</span>
                @if($accounts->isEmpty())
                    <p style="margin:8px 0 0;color:var(--muted);">Create an account first (Accounts in your business).</p>
                @else
                    <select name="deduct_account_id" id="rental-settle-account" required>
                        <option value="">Select account…</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ (int) old('deduct_account_id', $rental->deduct_account_id) === (int) $acc->id ? 'selected' : '' }}>
                                {{ $acc->deductOptionLabel() }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="loan-show-modal__submit" {{ $accounts->isEmpty() ? 'disabled' : '' }}><i class="fa fa-circle-check"></i> Confirm payment</button>
                <p style="margin:10px 0 0;font-size:10px;color:var(--muted);line-height:1.4;">This creates a ledger row for this billing date and reduces the selected account balance by the rent amount.</p>
            </form>
        </div>
    </div>
</div>

<div id="rental-receipt-modal" class="loan-show-modal" role="dialog" aria-modal="true" aria-labelledby="rental-receipt-title" aria-hidden="true">
    <div class="loan-show-modal__backdrop" data-close-rental-receipt tabindex="-1"></div>
    <div class="loan-show-modal__panel">
        <div class="loan-show-modal__head">
            <h2 id="rental-receipt-title">Recorded payment</h2>
            <button type="button" class="loan-show-modal__close" data-close-rental-receipt aria-label="Close">&times;</button>
        </div>
        <div class="loan-show-modal__body">
            <div class="loan-show-modal__summ">
                <span style="color:var(--muted);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Billing date</span>
                <strong id="rental-receipt-due" style="font-size:14px;">—</strong>
            </div>
            <span class="loan-show-modal__lbl">Amount</span>
            <div class="loan-show-modal__summ" style="margin-top:5px;"><strong id="rental-receipt-amount">—</strong></div>
            <span class="loan-show-modal__lbl">Debited account</span>
            <p id="rental-receipt-account" style="margin:4px 0 0;color:var(--text);font-weight:600;">—</p>
            <div class="loan-show-receipt-toolbar" aria-label="Receipt actions">
                <button type="button" class="rental-show__tx-btn" id="rental-receipt-btn-print" title="Opens print dialog"><i class="fa fa-print" aria-hidden="true"></i>Print</button>
                <button type="button" class="rental-show__tx-btn" id="rental-receipt-btn-copy" title="Copy receipt text"><i class="fa fa-copy" aria-hidden="true"></i>Copy</button>
                <button type="button" class="rental-show__tx-btn rental-show__tx-btn--go" id="rental-receipt-btn-pdf" title="Choose “Save as PDF” in the print dialog"><i class="fa fa-file-pdf" aria-hidden="true"></i>PDF</button>
            </div>
            <div id="rental-receipt-copy-toast" class="loan-show-copy-toast" role="status" aria-live="polite"></div>
            <button type="button" class="loan-show-modal__submit" style="margin-top:8px;background:color-mix(in srgb,var(--card) 70%,transparent);color:var(--text);border-color:var(--border);" data-close-rental-receipt><i class="fa fa-times"></i> Close</button>
        </div>
    </div>
</div>

<script>
var rentalReceiptCtx = {
    propertyLabel: @json($rental->property_type),
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

    var settleModal=document.getElementById('rental-settle-modal');
    var settleOcc=document.getElementById('rental-settle-occurrence');
    var settleDue=document.getElementById('rental-settle-due-display');
    var settleAmt=document.getElementById('rental-settle-amount-display');
    if(settleModal){
        settleModal.querySelectorAll('[data-close-rental-settle]').forEach(function(b){
            b.addEventListener('click',function(){closeModal(settleModal);});
        });
    }

    document.querySelectorAll('.js-rental-payment-open-settle').forEach(function(btn){
        btn.addEventListener('click',function(){
            if(btn.disabled)return;
            var ymd=btn.getAttribute('data-occurrence')||'';
            var human=btn.getAttribute('data-due-human')||'—';
            var amtDisp=btn.getAttribute('data-amount-fmt-display')||'—';
            if(settleOcc)settleOcc.value=ymd;
            if(settleDue)settleDue.textContent=human;
            if(settleAmt)settleAmt.textContent=amtDisp;
            openModal(settleModal);
        });
    });

    var receipt=document.getElementById('rental-receipt-modal');
    if(receipt){
        receipt.querySelectorAll('[data-close-rental-receipt]').forEach(function(b){
            b.addEventListener('click',function(){closeModal(receipt);});
        });
    }

    function escHtml(s){
        return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function getReceiptSnapshot(){
        return {
            due: (document.getElementById('rental-receipt-due')||{}).textContent||'—',
            amount: (document.getElementById('rental-receipt-amount')||{}).textContent||'—',
            account: (document.getElementById('rental-receipt-account')||{}).textContent||'—'
        };
    }

    function buildReceiptPlainText(){
        var r=getReceiptSnapshot();
        var lines=[
            'Rent payment receipt',
            'Property / lease: '+(rentalReceiptCtx.propertyLabel||'—'),
            'Business: '+(rentalReceiptCtx.businessName||'—'),
            'Billing date: '+r.due,
            'Amount: '+r.amount,
            'Debited account: '+r.account,
            'Printed: '+(rentalReceiptCtx.printedAtHint||'')
        ];
        return lines.join('\n');
    }

    function openReceiptPrintWindow(docTitle){
        var r=getReceiptSnapshot();
        var w=window.open('','_blank');
        if(!w){window.alert('Allow pop-ups to print or save as PDF.');return;}
        var title=docTitle||'Rent payment receipt';
        var html='<!DOCTYPE html><html><head><meta charset="utf-8"><title>'+escHtml(title)+'</title>';
        html+='<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:28px 32px;color:#111;line-height:1.45;}h1{font-size:20px;margin:0 0 6px;}h2{font-size:13px;font-weight:600;color:#444;margin:20px 0 8px;text-transform:uppercase;letter-spacing:.04em}.row{margin:6px 0;font-size:14px}.row strong{display:inline-block;min-width:9.5em;color:#333}.foot{margin-top:28px;font-size:11px;color:#666}</style></head><body>';
        html+='<h1>Rent payment receipt</h1>';
        html+='<div class="row"><strong>Property / lease</strong> '+escHtml(rentalReceiptCtx.propertyLabel)+'</div>';
        if(rentalReceiptCtx.businessName){
            html+='<div class="row"><strong>Business</strong> '+escHtml(rentalReceiptCtx.businessName)+'</div>';
        }
        html+='<h2>Payment</h2>';
        html+='<div class="row"><strong>Billing date</strong> '+escHtml(r.due)+'</div>';
        html+='<div class="row"><strong>Amount</strong> '+escHtml(r.amount)+'</div>';
        html+='<div class="row"><strong>Debited account</strong> '+escHtml(r.account)+'</div>';
        html+='<p class="foot">Generated '+escHtml(rentalReceiptCtx.printedAtHint)+' · Use print dialog to print or save as PDF.</p>';
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

    var copyToast=document.getElementById('rental-receipt-copy-toast');
    function showCopyToast(msg){
        if(copyToast)copyToast.textContent=msg||'';
    }

    var btnPrint=document.getElementById('rental-receipt-btn-print');
    var btnPdf=document.getElementById('rental-receipt-btn-pdf');
    var btnCopy=document.getElementById('rental-receipt-btn-copy');
    if(btnPrint)btnPrint.addEventListener('click',function(){openReceiptPrintWindow('Rent payment receipt');});
    if(btnPdf)btnPdf.addEventListener('click',function(){openReceiptPrintWindow('Rent payment receipt — PDF');});
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

    document.querySelectorAll('.js-rental-payment-open-receipt').forEach(function(btn){
        btn.addEventListener('click',function(){
            rentalReceiptCtx.printedAtHint = new Date().toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
            document.getElementById('rental-receipt-due').textContent=btn.getAttribute('data-payment-due-human')||'—';
            document.getElementById('rental-receipt-amount').textContent=btn.getAttribute('data-payment-amount-fmt')||'—';
            document.getElementById('rental-receipt-account').textContent=btn.getAttribute('data-payment-account')||'—';
            showCopyToast('');
            openModal(receipt);
        });
    });

    var open=@json($rentalSettleModalShouldOpen);
    var dueHumanOld=@json($rentalSettleDueHumanFromOld);
    if(open&&dueHumanOld&&settleDue){
        settleDue.textContent=dueHumanOld;
        setHtmlOpen(true);
    }
})();
</script>
@endsection
