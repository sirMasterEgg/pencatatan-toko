<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CustomerPriceService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $query = Customer::query();

        if ($q !== '') {
            $query->where(function ($query) use ($q) {
                $query->where('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%');

                if (preg_match('/^[\d\s\-+().]+$/', $q)) {
                    $normalized = Phone::normalize($q);
                    if ($normalized !== null) {
                        $query->orWhere('phone', 'like', '%'.$normalized.'%');
                    }
                }
            });
        }

        return view('customers.index', [
            'customers' => $query->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Customer::create($this->validated($request));

        $route = $request->input('after_save') === 'add_another' ? 'customers.create' : 'customers.index';

        return redirect()->route($route)->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function show(Customer $customer, CustomerPriceService $prices): View
    {
        $confirmed = $customer->transactions()->where('status', TransactionStatus::Confirmed);
        $specialPrices = $prices->activeForCustomer($customer->id);

        return view('customers.show', [
            'customer' => $customer,
            'transactionCount' => (clone $confirmed)->count(),
            'totalSpent' => (int) (clone $confirmed)->sum('total'),
            'transactions' => $customer->transactions()->latest('transacted_at')->latest('id')->limit(20)->get(),
            'specialPrices' => $specialPrices,
            'specialProducts' => Product::with('currentPrice')->whereKey(array_keys($specialPrices))->orderBy('name')->get(),
            'selectableProducts' => Product::selectable()->with('currentPrice')->orderBy('name')->get(),
            'priceHistory' => $customer->prices()->with('product')->latest('effective_from')->latest('id')->limit(20)->get(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('success', 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->transactions()->exists()) {
            return back()->with('error', 'Pelanggan tidak bisa dihapus karena sudah punya transaksi.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Pelanggan berhasil dihapus.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($customer?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'phone.unique' => 'Nomor HP ini sudah dipakai pelanggan lain.',
        ]);
    }
}
