@extends('layouts.app')

@section('title', 'Transaksi baru')

@section('content')
    <h1 class="h4 mb-3">Transaksi baru</h1>
    @if (session('last_transaction'))
        <p class="mb-3">
            <a href="{{ route('transactions.show', session('last_transaction')['id']) }}">Lihat {{ session('last_transaction')['number'] }}</a>
        </p>
    @endif
    <livewire:transaction-form />
@endsection
