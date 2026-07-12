@php $templates = $templates ?? []; @endphp
<div class="lf-tpl-field">
    <div class="lf-tpl-field__caption">Template</div>
    <div class="lf-tpl-grid" role="radiogroup" aria-label="Form template">
        @foreach($templates as $i => $tpl)
            <label class="lf-tpl-card">
                <input type="radio" name="template" value="{{ $tpl['key'] }}" @checked($i === 0) class="lf-tpl-card__radio">

                <span class="lf-tpl-card__preview" aria-hidden="true">
                    @forelse(array_slice($tpl['blocks'], 0, 6) as $b)
                        @php $bt = $b['type'] ?? 'text'; @endphp
                        @if($bt === 'heading')
                            <span class="lf-tpl-mini lf-tpl-mini--heading-{{ $b['size'] ?? 'lg' }}"></span>
                        @elseif($bt === 'text')
                            <span class="lf-tpl-mini lf-tpl-mini--text"></span>
                        @elseif($bt === 'image')
                            <span class="lf-tpl-mini lf-tpl-mini--image"></span>
                        @elseif($bt === 'divider')
                            <span class="lf-tpl-mini lf-tpl-mini--divider"></span>
                        @elseif($bt === 'field')
                            <span class="lf-tpl-mini lf-tpl-mini--field"></span>
                        @endif
                    @empty
                        <span class="lf-tpl-mini lf-tpl-mini--empty"><i class="fa fa-plus"></i></span>
                    @endforelse
                </span>

                <span class="lf-tpl-card__label"><i class="fa {{ $tpl['icon'] }}"></i> {{ $tpl['label'] }}</span>
                <span class="lf-tpl-card__desc">{{ $tpl['description'] }}</span>
            </label>
        @endforeach
    </div>
</div>

@once('crm-template-picker-assets')
<style>
.lf-tpl-field__caption{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:8px;}
.lf-tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;}
.lf-tpl-card{position:relative;display:flex;flex-direction:column;gap:6px;padding:10px;border:1.5px solid var(--border);border-radius:12px;background:var(--card);cursor:pointer;transition:border-color .12s,background .12s,box-shadow .12s;}
.lf-tpl-card:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.lf-tpl-card.is-selected{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--card));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.lf-tpl-card__radio{position:absolute;top:9px;right:9px;margin:0;z-index:1;}
.lf-tpl-card__preview{display:flex;flex-direction:column;justify-content:center;gap:5px;padding:10px 12px;border-radius:9px;background:#f1f5f9;border:1px solid #e2e8f0;min-height:88px;}
.lf-tpl-mini{display:block;border-radius:3px;flex-shrink:0;}
.lf-tpl-mini--heading-lg{height:8px;width:72%;background:#0f172a;}
.lf-tpl-mini--heading-md{height:6px;width:55%;background:#0f172a;}
.lf-tpl-mini--text{height:4px;width:92%;background:#cbd5e1;}
.lf-tpl-mini--image{height:20px;width:100%;background:#cbd5e1;border-radius:4px;}
.lf-tpl-mini--divider{height:1px;width:100%;background:#cbd5e1;margin:1px 0;}
.lf-tpl-mini--field{height:10px;width:100%;background:#fff;border:1px solid #cbd5e1;}
.lf-tpl-mini--empty{display:flex;align-items:center;justify-content:center;width:100%;height:100%;color:#94a3b8;font-size:16px;}
.lf-tpl-card__label{font-size:12.5px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:6px;}
.lf-tpl-card__label i{color:var(--primary);width:14px;text-align:center;}
.lf-tpl-card__desc{font-size:11px;color:var(--muted);line-height:1.35;}
</style>
<script>
(function () {
    function syncSelected(root) {
        (root || document).querySelectorAll('.lf-tpl-card__radio').forEach(function (r) {
            r.closest('.lf-tpl-card')?.classList.toggle('is-selected', r.checked);
        });
    }
    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('lf-tpl-card__radio')) return;
        syncSelected(e.target.closest('.lf-tpl-grid'));
    });
    document.addEventListener('DOMContentLoaded', function () { syncSelected(); });
})();
</script>
@endonce
