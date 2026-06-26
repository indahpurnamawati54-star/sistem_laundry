<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['customer', 'service', 'cashier']);

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status') && $request->payment_status !== '') {
            $query->where('is_paid', $request->payment_status === 'paid');
        }

        if ($request->has('start_date') && $request->start_date !== '') {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date !== '') {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $transactions = $query->latest()->paginate(20);

        return view('admin.transactions.index', compact('transactions'));
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['customer', 'service', 'cashier']);
        return view('admin.transactions.show', compact('transaction'));
    }

    public function updateStatus(Request $request, Transaction $transaction)
    {
        $request->validate([
            'status' => 'required|in:diterima,dalam_proses,selesai,diambil',
        ]);

        if (!$transaction->canUpdateStatus($request->status)) {
            return redirect()->back()
                ->with('error', 'Tidak dapat mengubah status ke ' . $request->status);
        }

        $transaction->updateStatus($request->status);

        return redirect()->back()
            ->with('success', 'Status transaksi berhasil diperbarui');
    }

    public function processPayment(Request $request, Transaction $transaction)
    {
        $request->validate([
            'payment_method' => 'required|in:cash,transfer,e-wallet',
        ]);

        $transaction->processPayment($request->payment_method);

        return redirect()->back()
            ->with('success', 'Pembayaran berhasil diproses');
    }

    public function destroy(Transaction $transaction)
    {
        // Only allow deletion of recent transactions that haven't been processed
        if ($transaction->status !== 'diterima' || $transaction->created_at->diffInHours(now()) > 24) {
            return redirect()->back()
                ->with('error', 'Hanya dapat menghapus transaksi yang baru dibuat (dalam 24 jam) dan masih berstatus "Diterima"');
        }

        $transaction->delete();

        return redirect()->route('admin.transactions.index')
            ->with('success', 'Transaksi berhasil dihapus');
    }

    public function printInvoice(Transaction $transaction)
    {
        $transaction->load(['customer', 'service', 'cashier']);
        
        // You would typically return a PDF view here
        return view('admin.transactions.invoice', compact('transaction'));
    }
}