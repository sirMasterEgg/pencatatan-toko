@php($class = match ($status) {
    \App\Enums\ProductStatus::Active => 'text-bg-success',
    \App\Enums\ProductStatus::NeedsReview => 'text-bg-warning',
    default => 'text-bg-secondary',
})
<span class="badge {{ $class }}">{{ $status->label() }}</span>
