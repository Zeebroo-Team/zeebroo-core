<style>
    .rental-form-page{max-width:920px;width:100%;margin:0 auto;box-sizing:border-box;--rf-radius:12px;--rf-radius-sm:9px;padding:0 2px 28px;}
    .rental-edit-hero{display:flex;flex-wrap:wrap;gap:12px 16px;align-items:center;justify-content:space-between;padding:0 2px 16px;margin-bottom:8px;border-bottom:1px solid var(--border);}
    .rental-edit-hero h1{margin:0;font-size:17px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
    .rental-btn--ghost{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);text-decoration:none;transition:background .18s ease,border-color .18s ease,transform .18s ease;}
    .rental-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);transform:translateY(-1px);}
    .rental-alert{padding:11px 14px;border-radius:12px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;line-height:1.45;border:1px solid;}
    .rental-alert i{margin-top:2px;opacity:.9;}
    .rental-alert--ok{border-color:color-mix(in srgb,#22c55e 38%,var(--border));background:linear-gradient(135deg,color-mix(in srgb,#22c55e 8%,transparent),color-mix(in srgb,var(--card) 96%,transparent));}
    .rental-alert--err{border-color:color-mix(in srgb,#f87171 42%,var(--border));background:color-mix(in srgb,#f87171 7%,transparent);}
    .rental-modal__banner{margin:0 0 12px;}
    .rental-inline-create,.rental-form-card{box-sizing:border-box;width:100%;max-width:none;margin-top:6px;padding:22px;border-radius:var(--rf-radius);border:1px solid color-mix(in srgb,var(--primary) 16%,var(--border));background:linear-gradient(160deg,color-mix(in srgb,var(--primary) 5%,transparent),var(--card));box-shadow:0 14px 44px -30px rgba(0,0,0,.38);}
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
    .rental-select,.rental-form-page .acct-warehouse-branch-el{width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text);transition:border-color .15s ease,box-shadow .15s ease;}
    .rental-form-page #acct-warehouse-wrap label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
    .rental-form-page .acct-warehouse-branch-el:focus{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));outline:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 16%,transparent);}
    .rental-field-err{display:block;color:#f87171;font-size:12px;margin-top:5px;line-height:1.35;}
    .rental-submit-wrap{margin-top:14px;display:flex;flex-wrap:wrap;gap:14px;align-items:center;padding-top:14px;border-top:1px solid var(--border);}
    .rental-submit-wrap .linkbtn{border-radius:10px;font-weight:800;padding:11px 20px;font-size:14px;display:inline-flex;align-items:center;gap:9px;}
    .rental-submit-note{font-size:12px;color:var(--muted);max-width:40ch;line-height:1.45;}
</style>
