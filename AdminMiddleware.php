<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class KasirController extends Controller
{
    public function dashboard()
    {
        $todayIncome = Transaction::whereDate('created_at', today())
            ->where('cashier_id', auth()->id())
            ->where('is_paid', true)
            ->sum('total_amount');

        $todayTransactions = Transaction::whereDate('created_at', today())
            ->where('cashier_id', auth()->id())
            ->count();

        $processingLaundry = Transaction::where('status', 'dalam_proses')
            ->where('cashier_id', auth()->id())
            ->count();

        return view('kasir.dashboard', compact(
            'todayIncome',
            'todayTransactions',
            'processingLaundry'
        ));
    }

    public function createTransaction()
    {
        $services = Service::where('is_active', true)->get();
        $customers = User::where('role', 'pelanggan')->get();
        
        return view('kasir.transactions.create', compact('services', 'customers'));
    }

    public function storeTransaction(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:services,id',
            'weight' => 'required_if:service_type,kiloan|numeric|min:0.1',
            'quantity' => 'required_if:service_type,satuan|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $service = Service::find($request->service_id);
        
        if ($service->type === 'kiloan') {
            $price = $service->price_per_kg * $request->weight;
        } else {
            $price = $service->price_per_item * $request->quantity;
        }

        $discountAmount = $price * ($service->discount / 100);
        $totalAmount = $price - $discountAmount;

        $transaction = Transaction::create([
            'invoice_number' => 'INV-' . date('Ymd') . '-' . Transaction::count() + 1,
            'customer_id' => $request->customer_id,
            'cashier_id' => auth()->id(),
            'service_id' => $request->service_id,
            'weight' => $request->weight,
            'quantity' => $request->quantity,
            'price' => $price,
            'discount' => $discountAmount,
            'total_amount' => $totalAmount,
            'notes' => $request->notes,
            'received_at' => now(),
        ]);

        return redirect()->route('kasir.transactions.show', $transaction->id)
            ->with('success', 'Transaksi berhasil dibuat');
    }

    public function updateStatus(Request $request, Transaction $transaction)
    {
        $request->validate([
            'status' => 'required|in:diterima,dalam_proses,selesai,diambil',
        ]);

        $transaction->updateStatus($request->status);

        return back()->with('success', 'Status transaksi berhasil diperbarui');
    }
}