@extends('theme::layouts.app', ['title' => 'Connect Facebook Page', 'heading' => 'Connect Facebook Page'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.fbp-wrap{max-width:600px;margin:0 auto;}
.fbp-header{text-align:center;padding:28px 0 24px;}
.fbp-header-icon{width:64px;height:64px;border-radius:18px;background:#1877F214;color:#1877F2;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 14px;}
.fbp-header h2{margin:0 0 6px;font-size:20px;font-weight:800;letter-spacing:-.02em;}
.fbp-header p{margin:0;font-size:13px;color:var(--muted);}
.fbp-grid{display:flex;flex-direction:column;gap:10px;margin-bottom:24px;}
.fbp-card{
    border:1.5px solid var(--border);border-radius:14px;padding:16px 18px;
    display:flex;align-items:center;gap:14px;background:var(--bg);
    transition:border-color .15s,box-shadow .15s;cursor:pointer;
}
.fbp-card:hover{border-color:#1877F2;box-shadow:0 0 0 3px rgba(24,119,242,.1);}
.fbp-card.selected{border-color:#1877F2;box-shadow:0 0 0 3px rgba(24,119,242,.15);background:rgba(24,119,242,.04);}
.fbp-avatar{width:48px;height:48px;border-radius:12px;object-fit:cover;flex-shrink:0;background:#1877F210;display:flex;align-items:center;justify-content:center;color:#1877F2;font-size:20px;overflow:hidden;}
.fbp-avatar img{width:100%;height:100%;object-fit:cover;}
.fbp-info{flex:1;min-width:0;}
.fbp-info__name{font-size:14px;font-weight:700;color:var(--text);margin:0 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fbp-info__meta{font-size:11px;color:var(--muted);margin:0;}
.fbp-radio{width:20px;height:20px;border-radius:50%;border:2px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .15s;}
.fbp-card.selected .fbp-radio{border-color:#1877F2;background:#1877F2;}
.fbp-radio::after{content:'';width:8px;height:8px;border-radius:50%;background:#fff;display:none;}
.fbp-card.selected .fbp-radio::after{display:block;}
.fbp-actions{display:flex;gap:10px;justify-content:flex-end;}
.fbp-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:10px;font-size:13px;font-weight:700;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;text-decoration:none;transition:all .15s;}
.fbp-btn:hover{background:var(--border);}
.fbp-btn--primary{background:#1877F2;border-color:#1877F2;color:#fff;}
.fbp-btn--primary:hover{background:color-mix(in srgb,#1877F2 88%,#000);color:#fff;}
.fbp-btn--primary:disabled{opacity:.45;pointer-events:none;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:18px 24px;">
    <div class="fbp-wrap">

        @if(session('error'))
            <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ session('error') }}</div>
        @endif

        <div class="fbp-header">
            <div class="fbp-header-icon"><i class="fa fa-facebook" aria-hidden="true"></i></div>
            <h2>Choose a Facebook Page</h2>
            <p>Select the page you want to connect to <strong style="color:var(--text);">{{ $business->name }}</strong></p>
        </div>

        <form method="POST" action="{{ route('designstudio.facebook.connect-page') }}" id="fbpForm">
            @csrf
            <input type="hidden" name="page_id" id="fbpPageId" value="">

            <div class="fbp-grid">
                @foreach($pages as $page)
                    @php
                        $picUrl = $page['picture']['data']['url'] ?? null;
                        $fans   = isset($page['fan_count']) ? number_format($page['fan_count']) . ' followers' : null;
                    @endphp
                    <div class="fbp-card" data-page-id="{{ $page['id'] }}" onclick="fbpSelect(this)">
                        <div class="fbp-avatar">
                            @if($picUrl)
                                <img src="{{ $picUrl }}" alt="{{ $page['name'] }}">
                            @else
                                <i class="fa fa-facebook" aria-hidden="true"></i>
                            @endif
                        </div>
                        <div class="fbp-info">
                            <p class="fbp-info__name">{{ $page['name'] }}</p>
                            <p class="fbp-info__meta">
                                {{ $page['category'] ?? 'Facebook Page' }}
                                @if($fans) &bull; {{ $fans }} @endif
                            </p>
                        </div>
                        <div class="fbp-radio"></div>
                    </div>
                @endforeach
            </div>

            <div class="fbp-actions">
                <a href="{{ route('designstudio.social-media.index') }}" class="fbp-btn">
                    <i class="fa fa-times" aria-hidden="true"></i> Cancel
                </a>
                <button type="submit" class="fbp-btn fbp-btn--primary" id="fbpSubmit" disabled>
                    <i class="fa fa-plug" aria-hidden="true"></i> Connect Page
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function fbpSelect(card) {
    document.querySelectorAll('.fbp-card').forEach(function(c) { c.classList.remove('selected'); });
    card.classList.add('selected');
    document.getElementById('fbpPageId').value = card.dataset.pageId;
    document.getElementById('fbpSubmit').disabled = false;
}
</script>
@endsection
