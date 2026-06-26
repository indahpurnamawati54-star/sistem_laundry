<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function dashboard()
    {
        // Today's statistics
        $todayIncome = Transaction::whereDate('created_at', today())
            ->where('is_paid', true)
            ->sum('total_amount');

        $todayTransactions = Transaction::today()->count();
        $pendingTransactions = Transaction::where('status', 'diterima')->count();
        $processingTransactions = Transaction::where('status', 'dalam_proses')->count();

        // Weekly statistics
        $weeklyIncome = Transaction::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('is_paid', true)
            ->sum('total_amount');

        // Monthly statistics
        $monthlyIncome = Transaction::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('is_paid', true)
            ->sum('total_amount');

        // Chart data for monthly income
        $monthlyIncomeData = Transaction::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_amount) as total')
            )
            ->whereYear('created_at', now()->year)
            ->where('is_paid', true)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Recent transactions
        $recentTransactions = Transaction::with(['customer', 'service', 'cashier'])
            ->latest()
            ->take(10)
            ->get();

        // Service popularity
        $popularServices = Service::withCount('transactions')
            ->orderBy('transactions_count', 'desc')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'todayIncome',
            'todayTransactions',
            'pendingTransactions',
            'processingTransactions',
            'weeklyIncome',
            'monthlyIncome',
            'monthlyIncomeData',
            'recentTransactions',
            'popularServices'
        ));
    }

    public function reports(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $query = Transaction::with(['customer', 'service', 'cashier'])
            ->whereBetween('created_at', [$startDate, Carbon::parse($endDate)->endOfDay()]);

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status') && $request->payment_status !== '') {
            $query->where('is_paid', $request->payment_status === 'paid');
        }

        $transactions = $query->get();

        // Summary statistics
        $summary = [
            'total_transactions' => $transactions->count(),
            'total_income' => $transactions->where('is_paid', true)->sum('total_amount'),
            'pending_payments' => $transactions->where('is_paid', false)->sum('total_amount'),
            'average_transaction' => $transactions->where('is_paid', true)->avg('total_amount'),
        ];

        // Daily report for chart
        $dailyReport = Transaction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(total_amount) as total_income')
            )
            ->whereBetween('created_at', [$startDate, Carbon::parse($endDate)->endOfDay()])
            ->where('is_paid', true)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.reports.index', compact(
            'transactions',
            'summary',
            'dailyReport',
            'startDate',
            'endDate'
        ));
    }

    public function exportReports(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $transactions = Transaction::with(['customer', 'service', 'cashier'])
            ->whereBetween('created_at', [$startDate, Carbon::parse($endDate)->endOfDay()])
            ->get();

        // For Excel/PDF export, you would use packages like Maatwebsite/Laravel-Excel or barryvdh/laravel-dompdf
        // This is a simplified version

        if ($request->type === 'excel') {
            // Return Excel file
            return response()->streamDownload(function () use ($transactions) {
                echo $this->generateExcel($transactions);
            }, 'laporan-transaksi-' . now()->format('Y-m-d') . '.xlsx');
        } else {
            // Return PDF file
            return response()->streamDownload(function () use ($transactions) {
                echo $this->generatePDF($transactions);
            }, 'laporan-transaksi-' . now()->format('Y-m-d') . '.pdf');
        }
    }

    private function generateExcel($transactions)
    {
        // Implementation for Excel generation
        // You would typically use Maatwebsite/Laravel-Excel here
        return "Excel content would be generated here";
    }

    private function generatePDF($transactions)
    {
        // Implementation for PDF generation
        // You would typically use barryvdh/laravel-dompdf here
        return "PDF content would be generated here";
    }
}