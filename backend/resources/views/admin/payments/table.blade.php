{{-- Payment records. $payments: invoices with plan (and business when
     $showBusiness). Recorded payments and bank-verified ones look different
     on purpose: only the provider can verify a gateway payment. --}}
@use('App\Support\AdminFormat')
<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>{{ __('admin.payments.record_col') }}</th>
            @if ($showBusiness ?? true)
                <th>{{ __('admin.common.business') }}</th>
            @endif
            <th>{{ __('admin.common.plan') }}</th>
            <th class="num">{{ __('admin.common.amount') }}</th>
            <th>{{ __('admin.payments.source') }}</th>
            <th>{{ __('admin.common.status') }}</th>
            <th>{{ __('admin.common.date') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($payments as $payment)
            @php
                $statusKey = $payment->awaitsApproval() ? 'awaiting' : $payment->status->value;
                $sourceKey = $payment->provider === 'manual' ? 'manual' : ($payment->provider === 'admin' ? 'admin' : 'gateway');
            @endphp
            <tr>
                <td>
                    <a href="{{ route('admin.payments.show', $payment) }}"><strong class="ltr nowrap">{{ $payment->number ?? __('admin.payments.no_number') }}</strong></a>
                    <span class="sub">{{ $payment->payment_method ? __('admin.methods.'.$payment->payment_method->value) : '—' }}@if ($payment->provider_reference) · <span class="ltr">{{ $payment->provider_reference }}</span>@endif</span>
                </td>
                @if ($showBusiness ?? true)
                    <td>
                        @if ($payment->business)
                            <a href="{{ route('admin.businesses.show', $payment->business) }}">{{ $payment->business->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                @endif
                <td>{{ $payment->plan?->name(app()->getLocale()) ?? '—' }} <span class="sub">{{ __('admin.periods.'.$payment->billing_period->value) }}</span></td>
                <td class="num ltr nowrap">{{ AdminFormat::money($payment->amount_minor, $payment->currency) }}</td>
                <td>
                    @include('admin.partials.badge', [
                        'tone' => $sourceKey === 'gateway' ? ($payment->isGatewayVerified() ? 'success' : '') : 'info',
                        'label' => __('admin.payments.sources.'.$sourceKey),
                    ])
                </td>
                <td>@include('admin.partials.badge', ['tone' => $statusKey === 'awaiting' ? 'warning' : AdminFormat::tone($statusKey), 'label' => __('admin.payments.statuses.'.$statusKey)])</td>
                <td class="nowrap">{{ AdminFormat::date($payment->paid_at ?? $payment->received_at ?? $payment->issued_at) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ ($showBusiness ?? true) ? 7 : 6 }}" class="empty">{{ __('admin.payments.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
