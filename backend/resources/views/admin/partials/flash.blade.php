@if (session('status'))
    <div class="alert success" role="status">
        @include('admin.partials.icon', ['name' => 'check'])
        <div>{{ session('status') }}</div>
    </div>
@endif
@if (session('error'))
    <div class="alert error" role="alert">
        @include('admin.partials.icon', ['name' => 'alert'])
        <div>{{ session('error') }}</div>
    </div>
@endif
@if ($errors->any())
    <div class="alert error" role="alert">
        @include('admin.partials.icon', ['name' => 'alert'])
        <div>{{ __('admin.common.check_fields') }}</div>
    </div>
@endif
