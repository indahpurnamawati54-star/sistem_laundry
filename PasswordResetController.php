<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::latest()->get();
        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:kiloan,satuan,ekspres',
            'price_per_kg' => 'required_if:type,kiloan|nullable|numeric|min:0',
            'price_per_item' => 'required_if:type,satuan,ekspres|nullable|numeric|min:0',
            'estimated_hours' => 'required|integer|min:1',
            'discount' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        Service::create([
            'name' => $request->name,
            'type' => $request->type,
            'price_per_kg' => $request->price_per_kg,
            'price_per_item' => $request->price_per_item,
            'estimated_hours' => $request->estimated_hours,
            'discount' => $request->discount ?? 0,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.services.index')
            ->with('success', 'Layanan berhasil dibuat');
    }

    public function show(Service $service)
    {
        $service->load('transactions.customer');
        return view('admin.services.show', compact('service'));
    }

    public function edit(Service $service)
    {
        return view('admin.services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:kiloan,satuan,ekspres',
            'price_per_kg' => 'required_if:type,kiloan|nullable|numeric|min:0',
            'price_per_item' => 'required_if:type,satuan,ekspres|nullable|numeric|min:0',
            'estimated_hours' => 'required|integer|min:1',
            'discount' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        $service->update([
            'name' => $request->name,
            'type' => $request->type,
            'price_per_kg' => $request->price_per_kg,
            'price_per_item' => $request->price_per_item,
            'estimated_hours' => $request->estimated_hours,
            'discount' => $request->discount ?? 0,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.services.index')
            ->with('success', 'Layanan berhasil diperbarui');
    }

    public function destroy(Service $service)
    {
        if (!$service->canDelete()) {
            return redirect()->back()
                ->with('error', 'Tidak dapat menghapus layanan yang memiliki transaksi');
        }

        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Layanan berhasil dihapus');
    }

    public function toggleStatus(Service $service)
    {
        $service->update([
            'is_active' => !$service->is_active,
        ]);

        $status = $service->is_active ? 'diaktifkan' : 'dinonaktifkan';
        
        return redirect()->back()
            ->with('success', "Layanan berhasil {$status}");
    }
}