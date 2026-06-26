<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KasirController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();

        $todayIncome = Transaction::whereDate('created_at', today())
            ->where('cashier_id', $user->id)
            ->where('is_paid', true)
            ->sum('total_amount');

        $todayTransactions = Transaction::whereDate('created_at', today())
            ->where('cashier_id', $user->id)
            ->count();

        $processingLaundry = Transaction::where('status', 'dalam_proses')
            ->where('cashier_id', $user->id)
            ->count();

        $pendingPayments = Transaction::where('cashier_id', $user->id)
            ->where('is_paid', false)
            ->count();

        // Recent transactions by this cashier
        $recentTransactions = Transaction::with(['customer', 'service'])
            ->where('cashier_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        return view('kasir.dashboard', compact(
            'todayIncome',
            'todayTransactions',
            'processingLaundry',
            'pendingPayments',
            'recentTransactions'
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
            'weight' => 'required_if:service_type,kiloan|nullable|numeric|min:0.1',
            'quantity' => 'required_if:service_type,satuan,ekspres|nullable|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $service = Service::findOrFail($request->service_id);
        
        // Calculate price based on service type
        if ($service->type === 'kiloan') {
            $weight = $request->weight ?? 0;
            $price = $service->calculatePrice($weight);
            $quantity = null;
        } else {
            $quantity = $request->quantity ?? 1;
            $price = $service->calculatePrice(null, $quantity);
            $weight = null;
        }

        // Generate invoice number
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . (Transaction::whereDate('created_at', today())->count() + 1);

        $transaction = Transaction::create([
            'invoice_number' => $invoiceNumber,
            'customer_id' => $request->customer_id,
            'cashier_id' => auth()->id(),
            'service_id' => $request->service_id,
            'weight' => $weight,
            'quantity' => $quantity,
            'price' => $price,
            'discount' => $price * ($service->discount / 100),
            'total_amount' => $price - ($price * ($service->discount / 100)),
            'notes' => $request->notes,
            'received_at' => now(),
        ]);

        return redirect()->route('kasir.transactions.show', $transaction->id)
            ->with('success', 'Transaksi berhasil dibuat. Invoice: ' . $invoiceNumber);
    }

    public function indexTransaction(Request $request)
    {
        $query = Transaction::with(['customer', 'service'])
            ->where('cashier_id', auth()->id());

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
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

        $transactions = $query->latest()->paginate(15);

        return view('kasir.transactions.index', compact('transactions'));
    }

    public function showTransaction(Transaction $transaction)
    {
        // Ensure the transaction belongs to the authenticated cashier
        if ($transaction->cashier_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $transaction->load(['customer', 'service']);
        return view('kasir.transactions.show', compact('transaction'));
    }

    public function updateStatus(Request $request, Transaction $transaction)
    {
        // Ensure the transaction belongs to the authenticated cashier
        if ($transaction->cashier_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

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
        // Ensure the transaction belongs to the authenticated cashier
        if ($transaction->cashier_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'payment_method' => 'required|in:cash,transfer,e-wallet',
        ]);

        $transaction->processPayment($request->payment_method);

        return redirect()->back()
            ->with('success', 'Pembayaran berhasil diproses');
    }

    public function indexCustomer(Request $request)
    {
        $query = User::where('role', 'pelanggan');

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->withCount('customerTransactions')->latest()->paginate(15);

        return view('kasir.customers.index', compact('customers'));
    }

    public function showCustomer(User $customer)
    {
        // Ensure the user is a customer
        if ($customer->role !== 'pelanggan') {
            abort(404);
        }

        $transactions = $customer->customerTransactions()
            ->with(['service', 'cashier'])
            ->latest()
            ->paginate(10);

        return view('kasir.customers.show', compact('customer', 'transactions'));
    }

    public function createCustomer()
    {
        return view('kasir.customers.create');
    }

    public function storeCustomer(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
        ]);

        $customer = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'role' => 'pelanggan',
            'password' => bcrypt('password'), // Default password
        ]);

        return redirect()->route('kasir.customers.show', $customer->id)
            ->with('success', 'Pelanggan berhasil ditambahkan');
    }

    public function printReceipt(Transaction $transaction)
    {
        // Ensure the transaction belongs to the authenticated cashier
        if ($transaction->cashier_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $transaction->load(['customer', 'service']);
        
        // You would typically return a PDF view here
        return view('kasir.transactions.receipt', compact('transaction'));
    }
}