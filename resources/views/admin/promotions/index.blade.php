@extends('layouts.admin')

@section('title', 'Promotions — Villa Elena Admin')
@section('page-title', 'Promotions')
@section('page-subtitle', 'Seasonal discounts that apply automatically to the villa rate')

@push('styles')
    <style>
        .btn-add {
            display: flex;
            align-items: center;
            gap: 7px;
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-add:hover {
            background: var(--gold);
            color: #fff;
        }

        .promo-note {
            background: var(--cream);
            border: 1px solid var(--border);
            border-left: 3px solid var(--gold);
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
        }

        .promo-note strong {
            color: var(--text-main);
        }

        .promo-value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--terracotta);
            line-height: 1;
        }

        .promo-label-cell {
            font-weight: 600;
            color: var(--text-main);
        }

        .promo-desc {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
            max-width: 280px;
        }

        .badge.bg-success  { background: #dcfce7 !important; color: #15803d; }
        .badge.bg-info     { background: #dbeafe !important; color: #1d4ed8; }
        .badge.bg-secondary{ background: #e2e8f0 !important; color: #475569; }
        .badge.bg-warning  { background: #fef3c7 !important; color: #92400e; }

        .visibility-tag {
            font-size: 13px;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="promo-note">
        Promos apply <strong>automatically</strong> — guests never type a code. A booking gets the discount when its
        <strong>check-in date</strong> falls inside the promo window and the slot matches. Overlapping promos don't stack:
        the one giving the <strong>largest peso discount</strong> wins. The discount comes off the
        <strong>villa base rate only</strong>, never off extras.
        A <strong>Scheduled</strong> promo already shows on the landing page so guests can book ahead — it just doesn't
        discount a stay until its window starts.
    </div>

    <div class="table-card">
        <div class="table-header">
            <h3>{{ $promos->total() }} Promo{{ $promos->total() != 1 ? 's' : '' }}</h3>
            <a href="{{ route('admin.promotions.create') }}" class="btn-add">
                <i class="bi bi-plus-lg"></i> New Promo
            </a>
        </div>

        @if ($promos->isEmpty())
            <div class="empty-state">
                <i class="bi bi-tags"></i>
                <p>No promos yet. Create one to start discounting the villa rate automatically.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Promo</th>
                        <th>Discount</th>
                        <th>Window</th>
                        <th>Slot</th>
                        <th>Used</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($promos as $promo)
                        <tr>
                            <td>
                                <div class="promo-label-cell">{{ $promo->label }}</div>
                                @if ($promo->description)
                                    <div class="promo-desc">{{ $promo->description }}</div>
                                @endif
                                <div class="promo-desc">
                                    @if ($promo->is_public)
                                        <span class="visibility-tag"><i class="bi bi-globe"></i> Shown on landing page</span>
                                    @else
                                        <span class="visibility-tag"><i class="bi bi-eye-slash"></i> Not advertised</span>
                                    @endif
                                    @if ($promo->notified_at)
                                        &middot; announced {{ $promo->notified_at->diffForHumans() }}
                                    @endif
                                </div>
                            </td>
                            <td><span class="promo-value">{{ $promo->value_label }}</span></td>
                            <td class="text-muted-theme" style="font-size: 14px;">{{ $promo->window_label }}</td>
                            <td class="text-muted-theme" style="font-size: 14px;">{{ $promo->slot_label }}</td>
                            <td style="text-align:center;">
                                {{ $promo->bookings_count }}
                                @if ($promo->usage_limit)
                                    <span class="text-muted-theme" style="font-size: 13px;">/ {{ $promo->usage_limit }}</span>
                                @endif
                            </td>
                            <td>{!! $promo->state_badge !!}</td>
                            <td>
                                <div class="action-btns">
                                    <a href="{{ route('admin.promotions.edit', $promo) }}" class="btn-icon" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <form method="POST" action="{{ route('admin.promotions.toggle', $promo) }}" style="display:inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn-icon warning"
                                            title="{{ $promo->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="bi bi-{{ $promo->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
                                        </button>
                                    </form>

                                    @if (! $promo->notified_at)
                                        <form method="POST" action="{{ route('admin.promotions.notify', $promo) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn-icon" title="Announce to all customers">
                                                <i class="bi bi-megaphone"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" class="btn-icon danger" title="Delete"
                                        onclick="confirmPromoDelete({{ $promo->id }}, '{{ addslashes($promo->label) }}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($promos->hasPages())
                <div class="pagination-wrap">{{ $promos->links() }}</div>
            @endif
        @endif
    </div>

@endsection

@section('modals')
    <div class="modal fade" id="deletePromoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:14px;border:none;">
                <div class="modal-body" style="padding:24px;">
                    <h5 style="font-family:'Cormorant Garamond',serif;margin-bottom:8px;">Delete promo?</h5>
                    <p style="font-size:13px;color:var(--muted);margin-bottom:20px;">
                        <strong id="deletePromoName"></strong> will be removed. Bookings already made under it
                        keep their discounted totals — only the link back to this promo is lost.
                        Deactivate instead if you may want it back.
                    </p>
                    <form id="deletePromoForm" method="POST">
                        @csrf @method('DELETE')
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light w-50" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger w-50">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmPromoDelete(id, label) {
            document.getElementById('deletePromoName').textContent = label;
            document.getElementById('deletePromoForm').action = '{{ url('admin/promotions') }}/' + id;
            new bootstrap.Modal(document.getElementById('deletePromoModal')).show();
        }
    </script>
@endsection
