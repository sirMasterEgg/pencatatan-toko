@extends('layouts.app')

@section('title', 'Tambah Barang')

@section('content')
    <h1 class="h4 mb-3">Tambah barang</h1>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('products.store') }}">
                @csrf
                @include('products._form')
                <button type="submit" class="btn btn-primary w-100">Simpan</button>
            </form>
        </div>
    </div>
@endsection
