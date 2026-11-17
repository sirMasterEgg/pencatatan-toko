@extends('layouts.app')

@section('title', 'Ubah '.$transaction->number)

@section('content')
    <h1 class="h4 mb-3">Ubah {{ $transaction->status === \App\Enums\TransactionStatus::Draft ? 'draf' : 'transaksi' }} {{ $transaction->number }}</h1>
    <livewire:transaction-form :transaction="$transaction" />
@endsection
