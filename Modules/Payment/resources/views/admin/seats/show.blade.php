@extends('theme::layouts.app', ['title' => 'User Seats — '.$business->name, 'heading' => 'User Seats & Extra Users'])

@section('content')
<style>
.aud-wrap{max-width:1280px;margin:0 auto;}
.aud-back{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;margin-bottom:14px;}
.aud-back:hover{color:var(--primary);}
.aud-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.aud-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.aud-sub{margin:4px 0 0;font-size:13px;color:var(--muted);}
.aud-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;}
.aud-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.aud-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:22px;}
@media(max-width:860px){.aud-stats{grid-template-columns:repeat(2,minmax(0,1fr));}}
.aud-stat{padding:14px 16px;border:1px solid var(--border);border-radius:14px;background:var(--card);}
.aud-stat-label{margin:0;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.aud-stat-value{margin:6px 0 0;font-size:20px;font-weight:800;color:var(--text);line-height:1.25;}
.aud-stat-note{margin:4px 0 0;font-size:11.5px;color:var(--muted);}
.aud-bar{height:8px;border-radius:999px;background:color-mix(in srgb,var(--border) 80%,transparent);margin:8px 0 2px;overflow:hidden;}
.aud-bar>div{height:100%;border-radius:999px;}
.aud-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);margin-bottom:22px;}
.aud-card-head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--border);}
.aud-card-title{margin:0;font-size:15px;font-weight:750;}
.aud-card-meta{font-size:12px;color:var(--muted);}
.aud-table{width:100%;border-collapse:collapse;}
.aud-table th{padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
.aud-table td{padding:12px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.aud-table tr:last-child td{border-bottom:none;}
.aud-table .num{text-align:right;white-space:nowrap;}
.aud-main{font-weight:650;color:var(--text);}
.aud-meta{font-size:12px;color:var(--muted);margin-top:1px;}
.aud-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:13px;flex-shrink:0;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
.aud-user{display:flex;align-items:center;gap:10px;}
.aud-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;}
.aud-badge--ok,.aud-badge--succeeded,.aud-badge--active,.aud-badge--accepted{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.aud-badge--pending,.aud-badge--processing,.aud-badge--near{background:color-mix(in srgb,#f59e0b 15%,transparent);color:#b45309;}
.aud-badge--full{background:color-mix(in srgb,#d97706 15%,transparent);color:#b45309;}
.aud-badge--failed,.aud-badge--canceled,.aud-badge--cancelled,.aud-badge--expired{background:color-mix(in srgb,#ef4444 14%,transparent);color:#dc2626;}
.aud-badge--muted,.aud-badge--refunded,.aud-badge--inactive{background:color-mix(in srgb,#64748b 15%,transparent);color:#64748b;}
.aud-empty{padding:26px 18px;text-align:center;font-size:13px;color:var(--muted);}
.aud-perm{display:inline-block;margin:2px 3px 0 0;padding:1px 7px;border-radius:6px;font-size:10.5px;background:color-mix(in srgb,var(--border) 55%,transparent);color:var(--muted);}
</style>

@php
    $s = $detail['summary'];
    $r = $detail['row'];
    $owner = $detail['owner'];
    $pct = min(100, (int) round($r['usage_ratio'] * 100));
    $initial = fn ($name) => mb_strtoupper(mb_substr(trim((string) $name) ?: '?', 0, 1));
    $openCount = $detail['open_invitations']->count();
@endphp

<div class="aud-wrap">
    <a href="{{ route('admin.user-seats.index') }}" class="aud-back"><i class="fa fa-arrow-left"></i> User Seats &amp; Extra Users</a>

    <div class="aud-header">
        <div>
            <h1 class="aud-title"><i class="fa fa-building" style="color:var(--primary);margin-right:8px;"></i>{{ $business->name }}</h1>
            <p class="aud-sub">
                {{ $s['plan'] ?? 'No package' }}@if($s['custom_limit']) · <strong>custom user limit set by admin</strong>@endif
                · Business #{{ $business->id }} · created {{ $business->created_at?->format('d M Y') }}
            </p>
        </div>
        @if($owner)
            <a href="{{ route('admin.users.show', $owner->id) }}" class="aud-btn"><i class="fa fa-user"></i> Owner account</a>
        @endif
    </div>

    <div class="aud-stats">
        <div class="aud-stat">
            <p class="aud-stat-label">Users</p>
            @if($s['limited'])
                <p class="aud-stat-value" style="{{ $r['is_full'] ? 'color:#d97706;' : '' }}">{{ $s['users_used'] }} / {{ $s['user_limit'] }}</p>
                <div class="aud-bar"><div style="width:{{ $pct }}%;background:{{ $r['is_full'] ? '#d97706' : ($r['is_near_full'] ? '#f59e0b' : 'var(--primary)') }};"></div></div>
                <p class="aud-stat-note">{{ $r['is_full'] ? 'Full — no new users can be invited' : $s['users_remaining'].' seat'.($s['users_remaining'] === 1 ? '' : 's').' free' }}</p>
            @else
                <p class="aud-stat-value">{{ $s['users_used'] }}</p>
                <p class="aud-stat-note">Unlimited users on this package</p>
            @endif
        </div>
        <div class="aud-stat">
            <p class="aud-stat-label">Seat breakdown</p>
            <p class="aud-stat-value" style="font-size:16px;">1 owner · {{ $detail['members']->count() }} member{{ $detail['members']->count() === 1 ? '' : 's' }}</p>
            <p class="aud-stat-note">+ {{ $openCount }} open invitation{{ $openCount === 1 ? '' : 's' }} (holding a seat)</p>
        </div>
        <div class="aud-stat">
            <p class="aud-stat-label">{{ $s['custom_limit'] ? 'Custom limit' : 'Included' }} + extra users</p>
            <p class="aud-stat-value">{{ $s['included_users'] ?? '∞' }} <span style="font-size:14px;color:var(--muted);">+ {{ $s['extra_users'] }} extra</span></p>
            <p class="aud-stat-note">
                {{ $s['extra_user_price'] !== null ? $s['currency'].' '.number_format($s['extra_user_price'], 2).' per extra user / month' : 'Package has no extra-user price' }}
            </p>
        </div>
        <div class="aud-stat">
            <p class="aud-stat-label">Monthly total</p>
            <p class="aud-stat-value" style="font-size:18px;">{{ $s['currency'] }} {{ number_format($s['monthly_total'], 2) }}</p>
            <p class="aud-stat-note">
                plan {{ number_format($s['base_amount'], 2) }} + extra {{ number_format($s['extra_monthly'], 2) }}
                @if($s['next_renewal_at']) · renews {{ \Illuminate\Support\Carbon::parse($s['next_renewal_at'])->format('d M Y') }}@endif
            </p>
        </div>
    </div>

    {{-- ── USERS ── --}}
    <div class="aud-card">
        <div class="aud-card-head">
            <h3 class="aud-card-title"><i class="fa fa-users" style="color:var(--primary);margin-right:6px;"></i>Users in this business</h3>
            <span class="aud-card-meta">{{ 1 + $detail['members']->count() }} user{{ $detail['members']->count() === 0 ? '' : 's' }} · {{ $detail['active_members'] }} active member{{ $detail['active_members'] === 1 ? '' : 's' }}</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="aud-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Permissions</th>
                        <th>Added by</th>
                        <th>Joined</th>
                        <th>Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    @if($owner)
                        <tr>
                            <td>
                                <div class="aud-user">
                                    <div class="aud-avatar">{{ $initial($owner->name) }}</div>
                                    <div>
                                        <a href="{{ route('admin.users.show', $owner->id) }}" class="aud-main" style="text-decoration:none;">{{ $owner->name }}</a>
                                        <div class="aud-meta">{{ $owner->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="aud-badge" style="background:color-mix(in srgb,var(--primary) 15%,transparent);color:var(--primary);">Owner</span></td>
                            <td><span class="aud-badge aud-badge--active">Active</span></td>
                            <td><span class="aud-meta">Full access</span></td>
                            <td><span class="aud-meta">—</span></td>
                            <td style="white-space:nowrap;">{{ $business->created_at?->format('d M Y') }}</td>
                            <td style="white-space:nowrap;" title="{{ $owner->last_seen_at?->format('d M Y, H:i') }}">{{ $owner->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                        </tr>
                    @endif
                    @foreach($detail['members'] as $m)
                        @php $perms = (array) ($m->permissions ?? []); @endphp
                        <tr>
                            <td>
                                <div class="aud-user">
                                    <div class="aud-avatar">{{ $initial($m->user->name) }}</div>
                                    <div>
                                        <a href="{{ route('admin.users.show', $m->user->id) }}" class="aud-main" style="text-decoration:none;">{{ $m->user->name }}</a>
                                        <div class="aud-meta">{{ $m->user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="aud-badge" style="background:color-mix(in srgb,{{ $m->roleBadgeColor() }} 15%,transparent);color:{{ $m->roleBadgeColor() }};">{{ $m->roleLabel() }}</span></td>
                            <td><span class="aud-badge aud-badge--{{ $m->status === 'active' ? 'active' : 'inactive' }}">{{ ucfirst((string) $m->status) }}</span></td>
                            <td style="max-width:280px;">
                                @if(empty($perms))
                                    <span class="aud-meta">Role defaults</span>
                                @else
                                    <span class="aud-meta" style="font-weight:700;">{{ count($perms) }} permission{{ count($perms) === 1 ? '' : 's' }}</span>
                                    <div>
                                        @foreach(array_slice($perms, 0, 6) as $p)<span class="aud-perm">{{ is_string($p) ? $p : json_encode($p) }}</span>@endforeach
                                        @if(count($perms) > 6)<span class="aud-perm">+{{ count($perms) - 6 }} more</span>@endif
                                    </div>
                                @endif
                            </td>
                            <td>{{ $m->invitedBy?->name ?? '—' }}</td>
                            <td style="white-space:nowrap;" title="{{ $m->created_at?->format('d M Y, H:i') }}">{{ $m->created_at?->format('d M Y') }}</td>
                            <td style="white-space:nowrap;" title="{{ $m->user->last_seen_at?->format('d M Y, H:i') }}">{{ $m->user->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($detail['members']->isEmpty())
            <div class="aud-empty" style="border-top:1px solid var(--border);">Only the owner — no other users have been added to this business.</div>
        @endif
    </div>

    {{-- ── OPEN INVITATIONS ── --}}
    <div class="aud-card">
        <div class="aud-card-head">
            <h3 class="aud-card-title"><i class="fa fa-envelope-open-text" style="color:var(--primary);margin-right:6px;"></i>Open invitations</h3>
            <span class="aud-card-meta">Each open invitation holds a seat until it is accepted, cancelled or expires</span>
        </div>
        @if($detail['open_invitations']->isEmpty())
            <div class="aud-empty">No open invitations.</div>
        @else
            <div style="overflow-x:auto;">
                <table class="aud-table">
                    <thead><tr><th>Email</th><th>Role</th><th>Invited by</th><th>Sent</th><th>Expires</th></tr></thead>
                    <tbody>
                        @foreach($detail['open_invitations'] as $inv)
                            <tr>
                                <td class="aud-main">{{ $inv->email }}</td>
                                <td>{{ ucfirst((string) $inv->role) }}</td>
                                <td>{{ $inv->invitedBy?->name ?? '—' }}</td>
                                <td style="white-space:nowrap;">{{ ($inv->last_sent_at ?? $inv->created_at)?->format('d M Y, H:i') }}</td>
                                <td style="white-space:nowrap;">{{ $inv->expires_at?->format('d M Y, H:i') }} <div class="aud-meta">{{ $inv->expires_at?->diffForHumans() }}</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── EXTRA USER PURCHASES ── --}}
    <div class="aud-card">
        <div class="aud-card-head">
            <h3 class="aud-card-title"><i class="fa fa-cart-plus" style="color:var(--primary);margin-right:6px;"></i>Extra-user purchases</h3>
            <span class="aud-card-meta">
                {{ $detail['seats_bought_total'] }} seat{{ $detail['seats_bought_total'] === 1 ? '' : 's' }} bought ·
                {{ $s['currency'] }} {{ number_format($detail['purchase_revenue'], 2) }} paid
            </span>
        </div>
        @if($detail['purchases']->isEmpty())
            <div class="aud-empty">This business has not bought any extra users.</div>
        @else
            <div style="overflow-x:auto;">
                <table class="aud-table">
                    <thead>
                        <tr><th>#</th><th>Bought by</th><th class="num">Extra users</th><th class="num">Price / user</th><th class="num">Amount</th><th>Status</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($detail['purchases'] as $p)
                            @php
                                $qty = max(1, (int) ($p->metadata['quantity'] ?? 1));
                                $unit = (float) ($p->metadata['unit_price'] ?? ((float) $p->amount / $qty));
                                $date = $p->paid_at ?? $p->created_at;
                            @endphp
                            <tr>
                                <td><span class="aud-meta" style="font-family:ui-monospace,monospace;">#{{ $p->id }}</span></td>
                                <td>{{ $p->user?->name ?? '—' }}<div class="aud-meta">{{ $p->user?->email }}</div></td>
                                <td class="num"><strong>+{{ $qty }}</strong></td>
                                <td class="num">{{ strtoupper($p->currency ?? 'USD') }} {{ number_format($unit, 2) }}</td>
                                <td class="num"><strong>{{ strtoupper($p->currency ?? 'USD') }} {{ number_format((float) $p->amount, 2) }}</strong></td>
                                <td><span class="aud-badge aud-badge--{{ $p->payment_status }}">{{ ucfirst((string) $p->payment_status) }}</span></td>
                                <td style="white-space:nowrap;">{{ $date?->format('d M Y') }}<div class="aud-meta">{{ $date?->format('H:i') }} · {{ $p->paid_at ? 'paid' : 'created' }}</div></td>
                                <td style="text-align:right;"><a href="{{ route('admin.payments.show', $p->id) }}" class="aud-btn" style="padding:5px 10px;font-size:12px;"><i class="fa fa-eye"></i> View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── INVITATION HISTORY ── --}}
    @if($detail['past_invitations']->isNotEmpty())
        <div class="aud-card">
            <div class="aud-card-head">
                <h3 class="aud-card-title"><i class="fa fa-clock-rotate-left" style="color:var(--primary);margin-right:6px;"></i>Invitation history</h3>
                <span class="aud-card-meta">Accepted, cancelled and expired invitations (no seat held)</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="aud-table">
                    <thead><tr><th>Email</th><th>Role</th><th>Status</th><th>Invited by</th><th>Sent</th></tr></thead>
                    <tbody>
                        @foreach($detail['past_invitations'] as $inv)
                            @php $invStatus = $inv->status === \Modules\Business\Models\BusinessInvitation::STATUS_PENDING ? 'expired' : $inv->status; @endphp
                            <tr>
                                <td>{{ $inv->email }}</td>
                                <td>{{ ucfirst((string) $inv->role) }}</td>
                                <td><span class="aud-badge aud-badge--{{ $invStatus }}">{{ ucfirst((string) $invStatus) }}</span></td>
                                <td>{{ $inv->invitedBy?->name ?? '—' }}</td>
                                <td style="white-space:nowrap;">{{ $inv->created_at?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
