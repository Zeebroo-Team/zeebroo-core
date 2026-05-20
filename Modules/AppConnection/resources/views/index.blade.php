@extends('theme::layouts.app', [
    'title' => __('App connections'),
    'heading' => __('App connections'),
])

@section('content')
<style>
    /* Full-bleed width within main content (theme .card defaults to max-width:920px) */
    .ac-app-connections-wrap.card{
        max-width:none;width:100%;box-sizing:border-box;
        padding:20px 22px 22px;
        border-radius:16px;
    }
    .ac-lead{
        margin:0 0 20px;font-size:14px;line-height:1.55;color:var(--muted);
        max-width:none;width:100%;
    }
    .ac-grid{
        display:flex;flex-direction:column;gap:12px;width:100%;
    }
    .ac-card{
        width:100%;box-sizing:border-box;
        border-radius:14px;border:1px solid var(--border);
        background:linear-gradient(165deg,color-mix(in srgb,var(--card) 98%,transparent),color-mix(in srgb,var(--card) 92%,transparent));
        padding:16px 18px;
        box-shadow:0 8px 28px -22px rgba(0,0,0,.35);
        display:grid;
        grid-template-columns:52px minmax(0,1fr);
        grid-template-rows:auto auto;
        align-items:start;
        gap:12px 16px;
    }
    @media (min-width:768px){
        .ac-card{
            grid-template-columns:52px minmax(0,1fr) auto;
            grid-template-rows:auto;
            align-items:center;
            gap:14px 20px;
        }
    }
    .ac-card__icon{
        grid-column:1;grid-row:1;
        width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;
        font-size:22px;flex-shrink:0;color:#fff;
        box-shadow:none;
        border:none;
    }
    .ac-card__body{grid-column:2;grid-row:1;min-width:0;}
    .ac-card__title{margin:0 0 4px;font-size:16px;font-weight:750;color:var(--text);letter-spacing:-.02em;line-height:1.25;}
    .ac-card__desc{margin:0;font-size:13px;line-height:1.5;color:var(--muted);}
    .ac-card__foot{
        grid-column:1/-1;grid-row:2;
        display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:8px;
        padding-top:4px;margin:0;border-top:1px solid color-mix(in srgb,var(--border) 75%,transparent);
    }
    @media (min-width:768px){
        .ac-card__foot{
            grid-column:3;grid-row:1;
            padding-top:0;margin:0;border-top:none;
            align-self:center;
            max-width:min(280px,38vw);
            flex-direction:column;
            align-items:flex-end;
            gap:10px;
        }
        .ac-card__foot .ac-card__connected{width:100%;max-width:100%;text-align:right;}
    }
    .ac-card__btn{
        font-size:12px;font-weight:650;padding:8px 14px;border-radius:10px;border:1px solid var(--border);
        background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--muted);cursor:not-allowed;
        text-decoration:none;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;
    }
    .ac-card__btn--primary{
        cursor:pointer;border-color:color-mix(in srgb,var(--primary) 45%,var(--border));
        background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);
    }
    .ac-card__btn--primary:hover{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 22%,transparent);}
    .ac-card__btn--danger{cursor:pointer;color:color-mix(in srgb,#f87171 85%,var(--text));}
    .ac-card__connected{
        font-size:11px;color:var(--muted);text-align:left;width:100%;
    }
    @media (min-width:768px){
        .ac-card__connected{text-align:right;margin-left:auto;width:auto;max-width:14rem;}
    }
    .ac-card__connected strong{color:var(--text);font-weight:600;display:block;word-break:break-word;}
    .ac-card__foot-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;width:100%;}
    @media (min-width:768px){
        .ac-card__foot-actions{width:auto;}
    }
    .ac-notify,.ac-err{margin:0 0 14px;padding:9px 12px;border-radius:10px;font-size:13px;line-height:1.4;border:1px solid var(--border);}
    .ac-notify{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
    .ac-err{border-color:color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 10%,transparent);}
</style>

<div class="card ac-app-connections-wrap">
    @if(session('status'))
        <p class="ac-notify" role="status">{{ session('status') }}</p>
    @endif
    @if($errors->has('google'))
        <p class="ac-err" role="alert">{{ $errors->first('google') }}</p>
    @endif

    <p class="ac-lead">{{ __('Choose an integration to connect external apps to SociBiz. Google account uses OAuth; other providers are coming soon.') }}</p>

    <div class="ac-grid" role="list">
        @foreach($integrations as $item)
            <article class="ac-card" role="listitem" aria-labelledby="ac-title-{{ $item['key'] }}">
                <span class="ac-card__icon" style="background-color:{{ $item['accent'] }};" aria-hidden="true">
                    <i class="{{ $item['icon_class'] }}"></i>
                </span>
                <div class="ac-card__body">
                    <h2 id="ac-title-{{ $item['key'] }}" class="ac-card__title">{{ $item['label'] }}</h2>
                    <p class="ac-card__desc">{{ $item['description'] }}</p>
                </div>
                <div class="ac-card__foot">
                    @if($item['key'] === 'google')
                        @if($googleConnection ?? null)
                            <span class="ac-card__connected"><span>{{ __('Connected as') }}</span><strong>{{ $googleConnection->email ?? $googleConnection->name ?? __('Google account') }}</strong></span>
                            <div class="ac-card__foot-actions">
                                <form method="post" action="{{ route('app-connection.google.disconnect') }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="ac-card__btn ac-card__btn--danger" onclick="return confirm(@json(__('Disconnect your Google account from SociBiz?')));">{{ __('Disconnect') }}</button>
                                </form>
                                @if($googleOAuthConfigured ?? false)
                                    <a href="{{ route('app-connection.google.redirect') }}" class="ac-card__btn ac-card__btn--primary"><i class="fa fa-rotate"></i>{{ __('Reconnect') }}</a>
                                @endif
                            </div>
                        @else
                            @if($googleOAuthConfigured ?? false)
                                <a href="{{ route('app-connection.google.redirect') }}" class="ac-card__btn ac-card__btn--primary"><i class="fa-brands fa-google"></i>{{ __('Connect Google account') }}</a>
                            @else
                                <span class="ac-card__btn" title="{{ __('Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in .env') }}">{{ __('Connect Google account') }}</span>
                            @endif
                        @endif
                    @else
                        <span class="ac-card__btn">{{ __('Coming soon') }}</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</div>
@endsection
