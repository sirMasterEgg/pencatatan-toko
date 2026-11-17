@php($class = match ($status) {
    \App\Enums\PaymentStatus::Paid => 'text-bg-success',
    \App\Enums\PaymentStatus::Partial => 'text-bg-warning',
    \App\Enums\PaymentStatus::Unpaid => 'text-bg-danger',
})
<span class="badge {{ $class }}">{{ $status->label() }}</span>
