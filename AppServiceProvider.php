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

        $completedTransactions = $user->customerTransactions()
            ->where('status', 'selesai')
            ->count();

        $totalSpent = $user->customerTransactions()
            ->where('is_paid', true)
            ->sum('total_amount');

        $recentTransactions = $user->customerTransactions()
            ->with('service')
            ->latest()
            ->take(5)
            ->get();

        // Current active transactions
        $activeTransactions = $user->customerTransactions()
            ->with('service')
            ->whereIn('status', ['diterima', 'dalam_proses', 'selesai'])
            ->latest()
            ->get();

        return view('pelanggan.dashboard', compact(
            'pendingTransactions',
            'processingTransactions',
            'completedTransactions',
            'totalSpent',
            'recentTransactions',
            'activeTransactions'
        ));
    }

    public function transactions(Request $request)
    {
        $user = auth()->user();
        
        $query = $user->customerTransactions()
            ->with(['service', 'cashier']);

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('service', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $transactions = $query->latest()->paginate(10);

        return view('pelanggan.transactions.index', compact('transactions'));
    }

    public function showTransaction(Transaction $transaction)
    {
        // Ensure the transaction belongs to the authenticated user
        if ($transaction->customer_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $transaction->load(['service', 'cashier']);

        // Get timeline for progress tracking
        $timeline = $transaction->getTimeline();

        return view('pelanggan.transactions.show', compact('transaction', 'timeline'));
    }

    public function history(Request $request)
    {
        $user = auth()->user();
        
        $query = $user->customerTransactions()
            ->with(['service', 'cashier'])
            ->where('status', 'diambil');

        if ($request->has('month') && $request->month !== '') {
            $query->whereMonth('created_at', $request->month);
        }

        if ($request->has('year') && $request->year !== '') {
            $query->whereYear('created_at', $request->year);
        }

        $transactions = $query->latest()->paginate(15);

        // Statistics
        $monthlyStats = $user->customerTransactions()
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, SUM(total_amount) as total')
            ->where('status', 'diambil')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return view('pelanggan.history.index', compact('transactions', 'monthlyStats'));
    }

    public function trackLaundry()
    {
        $user = auth()->user();
        
        $activeTransactions = $user->customerTransactions()
            ->with('service')
            ->whereIn('status', ['diterima', 'dalam_proses', 'selesai'])
            ->latest()
            ->get();

        return view('pelanggan.tracking.index', compact('activeTransactions'));
    }

    public function getTransactionStatus(Transaction $transaction)
    {
        // Ensure the transaction belongs to the authenticated user
        if ($transaction->customer_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => $transaction->status,
            'status_label' => $transaction->status_label,
            'progress_percentage' => $transaction->progress_percentage,
            'estimated_completion' => $transaction->estimated_completion_time?->format('d M Y H:i'),
            'timeline' => $transaction->getTimeline(),
        ]);
    }
}