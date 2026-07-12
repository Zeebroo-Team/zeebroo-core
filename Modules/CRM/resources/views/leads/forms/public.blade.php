@php
    $style = $form->styleSettings();
    $layout = $style['layout'] ?? 'card';
    $firstImage = collect($form->blocks ?? [])->first(fn ($b) => ($b['type'] ?? null) === 'image' && filled($b['url'] ?? null));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $form->name }}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--pf-accent:{{ $style['accent_color'] }};--pf-bg:{{ $style['background_color'] }};}
*{box-sizing:border-box;}
body{margin:0;font-family:'Segoe UI',Arial,sans-serif;font-size:15px;color:#1e293b;background:var(--pf-bg);min-height:100vh;}
.pf-wrap{max-width:560px;margin:0 auto;padding:40px 16px;}
.pf-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px 28px;box-shadow:0 4px 24px rgba(0,0,0,.06);}
.pf-heading-lg{font-size:24px;font-weight:800;color:#0f172a;letter-spacing:-.02em;margin:0 0 12px;}
.pf-heading-md{font-size:18px;font-weight:700;color:#0f172a;margin:0 0 10px;}
.pf-text{font-size:14px;line-height:1.6;color:#475569;margin:0 0 14px;white-space:pre-line;}
.pf-image{width:100%;border-radius:10px;margin:0 0 14px;display:block;}
.pf-divider{border:none;border-top:1px solid #e2e8f0;margin:18px 0;}
.pf-row{display:flex;gap:14px;margin:0 0 14px;flex-wrap:wrap;}
.pf-col{flex:1 1 0;min-width:140px;}
.pf-col .pf-field:last-child{margin-bottom:0;}
@media(max-width:560px){.pf-row{flex-direction:column;}}
.pf-field{margin:0 0 14px;}
.pf-field label{display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;}
.pf-field input[type="text"],.pf-field input[type="email"],.pf-field input[type="tel"],.pf-field input[type="number"],.pf-field input[type="date"],.pf-field textarea,.pf-field select{
    width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;font-family:inherit;
}
.pf-field input:focus,.pf-field textarea:focus,.pf-field select:focus{outline:none;border-color:var(--pf-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--pf-accent) 20%,transparent);}
.pf-field textarea{min-height:80px;resize:vertical;}
.pf-checkbox{display:flex;align-items:center;gap:8px;font-size:13px;color:#475569;}
.pf-help{font-size:11px;color:#94a3b8;margin-top:5px;line-height:1.4;}
.pf-error{color:#ef4444;font-size:12px;margin-top:4px;}
.pf-submit{width:100%;padding:12px 16px;font-size:14px;font-weight:700;border-radius:9px;border:none;background:var(--pf-accent);color:#fff;cursor:pointer;margin-top:8px;}
.pf-submit:hover{background:color-mix(in srgb,var(--pf-accent) 82%,#000);}
.pf-success{text-align:center;padding:20px 0;}
.pf-success i{font-size:36px;color:#10b981;margin-bottom:12px;display:block;}
.pf-success p{font-size:15px;color:#334155;line-height:1.6;white-space:pre-line;}
.pf-footer{text-align:center;margin-top:16px;font-size:11px;color:#94a3b8;}

/* Minimal layout: no card chrome, form sits directly on the page background */
body.pf-layout-minimal .pf-card{background:transparent;border:none;box-shadow:none;padding:0;}

/* Split layout: decorative visual panel + form panel side by side */
body.pf-layout-split{padding:0;}
.pf-split{display:flex;min-height:100vh;}
.pf-split__visual{flex:1 1 42%;background:linear-gradient(135deg,var(--pf-accent),color-mix(in srgb,var(--pf-accent) 55%,#000));background-size:cover;background-position:center;display:flex;align-items:flex-end;padding:32px;}
.pf-split__visual-title{color:#fff;font-size:22px;font-weight:800;text-shadow:0 2px 14px rgba(0,0,0,.3);margin:0;}
.pf-split__form{flex:1 1 58%;display:flex;align-items:center;justify-content:center;padding:40px 24px;}
.pf-split__form .pf-wrap{padding:0;max-width:420px;width:100%;}
.pf-split__form .pf-card{box-shadow:none;border:none;padding:0;}
@media(max-width:760px){.pf-split{flex-direction:column;} .pf-split__visual{flex:0 0 140px;padding:20px;}}
</style>
</head>
<body class="pf-layout-{{ $layout }}">
@if($layout === 'split')
<div class="pf-split">
    <div class="pf-split__visual" @if($firstImage) style="background-image:url('{{ $firstImage['url'] }}');" @endif>
        <h2 class="pf-split__visual-title">{{ $form->name }}</h2>
    </div>
    <div class="pf-split__form">
        <div class="pf-wrap">
            <div class="pf-card">
                @include('crm::leads.forms.partials.public-form-body', ['form' => $form, 'customFields' => $customFields, 'submitted' => $submitted])
            </div>
            <p class="pf-footer">Powered by {{ $form->project->business->name ?? 'Zeebroo' }}</p>
        </div>
    </div>
</div>
@else
<div class="pf-wrap">
    <div class="pf-card">
        @include('crm::leads.forms.partials.public-form-body', ['form' => $form, 'customFields' => $customFields, 'submitted' => $submitted])
    </div>
    <p class="pf-footer">Powered by {{ $form->project->business->name ?? 'Zeebroo' }}</p>
</div>
@endif
</body>
</html>
