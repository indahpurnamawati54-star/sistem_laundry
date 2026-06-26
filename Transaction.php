<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class PelangganController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        
        $pendingTransactions = $user->customerTransactions()
            ->where('status', 'diterima')
            ->count();

        $processingTransactions = $user->customerTransactions()
            ->where('status', 'dalam_proses')
            ->count();

        $recentTransactions = $user->customerTransactions()
            ->with('service')
            ->latest()
            ->take(5)
            ->get();

        return view('pelanggan.dashboard', compact(
            'pendingTransactions',
            'processingTransactions',
            'recentTransactions'
        ));
    }

    public function transactions()
    {
        $transactions = auth()->user()->customerTransactions()
            ->with('service', 'cashier')
            ->latest()
            ->get();

        return view('pelanggan.transactions.index', compact('transactions'));
    }

    public function showTransaction(Transaction $transaction)
    {
        // Ensure the transaction belongs to the authenticated user
        if ($transaction->customer_id !== auth()->id()) {
            abort(403);
        }

        $transaction->load('service', 'cashier');

        return view('pelanggan.transactions.show', compact('transaction'));
    }
}