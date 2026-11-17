@extends('layouts.app')

@section('title', 'Tambah Pelanggan')

@section('content')
    <h1 class="h4 mb-3">Tambah pelanggan</h1>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.store') }}">
                @csrf
                @include('customers._form')
                <button type="submit" class="btn btn-primary w-100">Simpan</button>
            </form>
        </div>
    </div>
@endsection
