@extends('billing.layout')

@section('title', __('billing.pay_title'))

@section('content')
    <section class="card">
        <h1>{{ __('billing.pay_title') }}</h1>
        <p class="muted">{{ __('billing.pay_intro') }}</p>
        <div class="row"><span>{{ __('billing.plan') }}</span><strong>{{ $checkout->plan->name(app()->getLocale()) }}</strong></div>
        <div class="row"><span>{{ __('billing.period') }}</span><strong>{{ __('billing.periods.'.$checkout->billing_period->value) }}</strong></div>
        <div class="row"><span>{{ __('billing.amount') }}</span><strong class="amount">{{ $amount }} {{ $checkout->currency }}</strong></div>
        <button type="button" id="pay" disabled>{{ __('billing.pay_button') }}</button>
        <p class="muted" id="pay-error" role="alert" hidden>{{ __('billing.pay_error') }}</p>
    </section>

    <script>
        function doryPaymentError() {
            document.getElementById('pay-error').hidden = false;
            document.getElementById('pay').disabled = false;
        }
        function doryPaymentCancel() {
            window.location.href = @json($returnUrl);
        }
    </script>
    <script src="{{ $page['script'] }}" data-error="doryPaymentError" data-cancel="doryPaymentCancel"></script>
    <script>
        (function () {
            var button = document.getElementById('pay');
            if (typeof Checkout === 'undefined') {
                doryPaymentError();
                return;
            }
            Checkout.configure({ session: { id: @json($page['session']) } });
            button.disabled = false;
            button.addEventListener('click', function () {
                button.disabled = true;
                Checkout.showPaymentPage();
            });
        })();
    </script>
@endsection
