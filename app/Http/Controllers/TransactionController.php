<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class TransactionController extends Controller
{
    public function create(): View
    {
        return view('transactions.create');
    }
}
