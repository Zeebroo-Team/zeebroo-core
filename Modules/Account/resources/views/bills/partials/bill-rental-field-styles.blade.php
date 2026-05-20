@once('account-bills-rental-field-styles')
    <style>
        .rental-fields .bill-location-rental-group{margin-top:18px;}
        .bill-location-rental-group__branch #acct-warehouse-wrap{margin-top:0;}
        .bill-location-rental-group__divider{
            margin:16px 0;padding:0;height:0;border:none;
            border-top:1px solid color-mix(in srgb,var(--border) 78%,transparent);
        }
        .bill-rental-field__lead{
            margin:0 0 14px;font-size:13px;line-height:1.5;color:var(--muted);padding:11px 14px;border-radius:10px;
            border:1px dashed color-mix(in srgb,var(--border) 78%,transparent);
            background:color-mix(in srgb,var(--primary) 4%,transparent);
        }
        .bill-rental-field__lead strong{color:var(--text);font-weight:700;}
        .bill-rental-toggle{
            display:flex;align-items:flex-start;gap:14px;width:100%;margin:0;padding:14px 16px;text-align:left;cursor:pointer;
            border-radius:11px;border:1px solid color-mix(in srgb,var(--border) 88%,transparent);
            background:color-mix(in srgb,var(--card) 98%,transparent);
            box-shadow:inset 0 1px 0 color-mix(in srgb,#fff 4%,transparent);
            transition:border-color .18s ease,background .18s ease,box-shadow .18s ease;
        }
        .bill-rental-toggle:hover:not(:disabled){
            border-color:color-mix(in srgb,var(--primary) 28%,var(--border));
            background:color-mix(in srgb,var(--primary) 5%,transparent);
        }
        .bill-rental-toggle:focus-within{
            outline:2px solid color-mix(in srgb,var(--primary) 42%,transparent);
            outline-offset:2px;
        }
        .bill-rental-toggle--on{
            border-color:color-mix(in srgb,var(--primary) 38%,var(--border));
            background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 9%,transparent),color-mix(in srgb,var(--card) 96%,transparent));
            box-shadow:0 10px 28px -22px color-mix(in srgb,var(--primary) 45%,transparent);
        }
        .bill-rental-toggle[disabled]{opacity:.62;cursor:not-allowed;}
        .bill-rental-toggle__icon{
            flex-shrink:0;width:40px;height:40px;border-radius:10px;display:grid;place-items:center;
            font-size:16px;color:#fff;background:linear-gradient(145deg,var(--primary),color-mix(in srgb,var(--primary) 62%,#0f172a));
            box-shadow:0 10px 22px -14px color-mix(in srgb,var(--primary) 55%,transparent);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-rental-toggle__icon{
            background:linear-gradient(145deg,var(--primary),#292524);color:#fef9c3;
        }
        .bill-rental-toggle__body{flex:1;min-width:0;padding-top:1px;}
        .bill-rental-toggle__title{margin:0 0 4px;font-size:14px;font-weight:800;letter-spacing:-.02em;color:var(--text);line-height:1.25;}
        .bill-rental-toggle__desc{margin:0;font-size:12px;line-height:1.45;color:var(--muted);}
        .bill-rental-toggle__control{flex-shrink:0;padding-top:4px;}
        .bill-rental-toggle__control input[type="checkbox"]{
            width:18px;height:18px;accent-color:var(--primary);cursor:pointer;margin:0;
        }
        .bill-rental-toggle[disabled] .bill-rental-toggle__control input{cursor:not-allowed;}
        .bill-rental-select-wrap{margin-top:14px;padding-top:14px;border-top:1px solid color-mix(in srgb,var(--border) 75%,transparent);}
        .bill-rental-select-wrap label.bill-rental-select-label{
            display:block;margin-bottom:7px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);
        }
        .bill-rental-empty-note{
            margin:10px 0 0;font-size:12px;line-height:1.45;color:var(--muted);display:flex;align-items:flex-start;gap:8px;
            padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border) 85%,transparent);
            background:color-mix(in srgb,var(--card) 88%,transparent);
        }
        .bill-rental-empty-note i{margin-top:2px;color:var(--primary);opacity:.9;}

        .bill-pay-pattern{padding:0;border:none;margin:0;}
        .rental-field.bill-pay-pattern .bill-pay-pattern__option{
            display:block;margin:0;font-size:inherit;font-weight:inherit;text-transform:none;letter-spacing:normal;color:inherit;
        }
        .bill-pay-pattern__legend{
            display:block;margin-bottom:5px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);
        }
        .bill-pay-pattern__hint{
            margin:0 0 12px;font-size:12px;line-height:1.45;color:var(--muted);max-width:52ch;
        }
        .bill-pay-pattern__choices{
            display:grid;gap:10px;grid-template-columns:1fr;
        }
        @media(min-width:560px){
            .bill-pay-pattern__choices{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
        }
        .bill-pay-pattern__option{display:block;margin:0;cursor:pointer;}
        .bill-pay-pattern__option-inner{
            display:flex;align-items:flex-start;gap:12px;width:100%;box-sizing:border-box;
            padding:12px 14px;border-radius:11px;min-height:100%;
            border:1px solid color-mix(in srgb,var(--border) 88%,transparent);
            background:color-mix(in srgb,var(--card) 98%,transparent);
            box-shadow:inset 0 1px 0 color-mix(in srgb,#fff 4%,transparent);
            transition:border-color .18s ease,background .18s ease,box-shadow .18s ease;
        }
        .bill-pay-pattern__option:hover .bill-pay-pattern__option-inner{
            border-color:color-mix(in srgb,var(--primary) 28%,var(--border));
            background:color-mix(in srgb,var(--primary) 5%,transparent);
        }
        .bill-pay-pattern__option:has(input:checked) .bill-pay-pattern__option-inner{
            border-color:color-mix(in srgb,var(--primary) 38%,var(--border));
            background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 9%,transparent),color-mix(in srgb,var(--card) 96%,transparent));
            box-shadow:0 10px 28px -22px color-mix(in srgb,var(--primary) 45%,transparent);
        }
        .bill-pay-pattern__option:focus-within .bill-pay-pattern__option-inner{
            outline:2px solid color-mix(in srgb,var(--primary) 42%,transparent);
            outline-offset:2px;
        }
        .bill-pay-pattern__ico{
            flex-shrink:0;width:36px;height:36px;border-radius:10px;display:grid;place-items:center;
            font-size:14px;color:#fff;background:linear-gradient(145deg,var(--primary),color-mix(in srgb,var(--primary) 62%,#0f172a));
            box-shadow:0 8px 20px -14px color-mix(in srgb,var(--primary) 55%,transparent);
        }
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bill-pay-pattern__ico{
            background:linear-gradient(145deg,var(--primary),#292524);color:#fef9c3;
        }
        .bill-pay-pattern__body{flex:1;min-width:0;padding-top:1px;}
        .bill-pay-pattern__title{display:block;margin:0;font-size:14px;font-weight:800;letter-spacing:-.02em;color:var(--text);line-height:1.25;}
        .bill-pay-pattern__desc{display:block;margin:4px 0 0;font-size:11px;line-height:1.4;color:var(--muted);}
        .bill-pay-pattern__radio{flex-shrink:0;align-self:center;padding-top:2px;}
        .rental-field.bill-pay-pattern .bill-pay-pattern__radio input[type="radio"]{
            width:18px;height:18px;margin:0;padding:0;border:none;border-radius:50%;background:transparent;
            box-shadow:none;accent-color:var(--primary);cursor:pointer;appearance:auto;
        }
    </style>
@endonce
