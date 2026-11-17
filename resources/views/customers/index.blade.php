@extends('layouts.app')

@section('title', 'Pelanggan')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Pelanggan</h1>
        <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">Tambah pelanggan</a>
    </div>

    <form method="GET" action="{{ route('customers.index') }}" class="mb-3">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama atau nomor HP" aria-label="Cari">
    </form>

    @if ($customers->isEmpty())
        <p class="text-muted">Belum ada pelanggan.</p>
    @else
        <div class="list-group mb-3">
            @foreach ($customers as $customer)
                <a href="{{ route('customers.show', $customer) }}" class="list-group-item list-group-item-action">
                    <div class="fw-semibold">{{ $customer->name }}</div>
                    <small class="text-muted">{{ $customer->phone ?: 'Tanpa nomor HP' }}</small>
                </a>
            @endforeach
        </div>
        {{ $customers->links() }}
    @endif
@endsection
