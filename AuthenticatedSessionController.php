<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // HAPUS constructor atau gunakan pengecekan manual
    
    public function dashboard()
    {
        // Pengecekan role manual
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        
        // 1. Pemasukan hari ini
        $todayIncome = Transaction::whereDate('created_at', today())
            ->where('is_paid', true)
            ->sum('total_amount');
            
        // 2. Transaksi hari ini
        $todayTransactions = Transaction::whereDate('created_at', today())->count();
        
        // 3. Transaksi pending (diterima)
        $pendingTransactions = Transaction::where('status', 'diterima')->count();
        
        // 4. Transaksi dalam proses - TAMBAHKAN INI
        $processingTransactions = Transaction::where('status', 'dalam_proses')->count();
        
        // 5. Total users
        $totalUsers = User::count();
        
        // 6. Transaksi terbaru
        $recentTransactions = Transaction::with(['customer', 'service'])
            ->latest()
            ->take(5)
            ->get();
            
        // 7. Layanan populer - TAMBAHKAN INI
        $popularServices = Service::withCount(['transactions' => function($query) {
            $query->whereMonth('created_at', now()->month);
        }])
        ->orderBy('transactions_count', 'desc')
        ->take(5)
        ->get();
        
        // 8. Data pendapatan bulanan - TAMBAHKAN INI
        $monthlyIncomeData = Transaction::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_amount) as total')
            )
            ->whereYear('created_at', now()->year)
            ->where('is_paid', true)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy('month')
            ->get();
            
        return view('admin.dashboard', compact(
            'todayIncome',
            'todayTransactions',
            'pendingTransactions',
            'processingTransactions', // TAMBAHKAN INI
            'totalUsers',
            'recentTransactions',
            'popularServices',        // TAMBAHKAN INI
            'monthlyIncomeData'       // TAMBAHKAN INI
        ));
    }
    
    public function reports(Request $request)
    {
        // Pengecekan role manual
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        
        $transactions = Transaction::with(['customer', 'service'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
            
        $totalIncome = $transactions->where('is_paid', true)->sum('total_amount');
        $totalTransactions = $transactions->count();
        
        return view('admin.reports.index', compact(
            'transactions',
            'totalIncome',
            'totalTransactions',
            'startDate',
            'endDate'
        ));
    }
}