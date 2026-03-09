<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Feature;
use App\Models\PlanFeature;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['slug' => 'dashboard', 'name' => 'Dashboard', 'route' => '/dashboard', 'icon' => 'layout-dashboard', 'group' => 'utama', 'sort_order' => 1],
            ['slug' => 'pos', 'name' => 'POS / Kasir', 'route' => '/pos', 'icon' => 'shopping-cart', 'group' => 'utama', 'sort_order' => 2],
            ['slug' => 'products', 'name' => 'Produk', 'route' => '/products', 'icon' => 'package', 'group' => 'master', 'sort_order' => 3],
            ['slug' => 'product-categories', 'name' => 'Kategori Produk', 'route' => '/categories', 'icon' => 'tags', 'group' => 'master', 'sort_order' => 4],
            ['slug' => 'stores', 'name' => 'Toko', 'route' => '/stores', 'icon' => 'store', 'group' => 'master', 'sort_order' => 5],
            ['slug' => 'employees', 'name' => 'Karyawan', 'route' => '/employees', 'icon' => 'users', 'group' => 'master', 'sort_order' => 6],
            ['slug' => 'designations', 'name' => 'Jabatan', 'route' => '/designations', 'icon' => 'briefcase', 'group' => 'master', 'sort_order' => 7],
            ['slug' => 'sales', 'name' => 'Penjualan Offline', 'route' => '/sales', 'icon' => 'receipt', 'group' => 'transaksi', 'sort_order' => 8],
            ['slug' => 'sales-online', 'name' => 'Penjualan Online', 'route' => '/sales-online', 'icon' => 'globe', 'group' => 'transaksi', 'sort_order' => 9],
            ['slug' => 'expenses', 'name' => 'Pengeluaran', 'route' => '/expenses', 'icon' => 'wallet', 'group' => 'keuangan', 'sort_order' => 10],
            ['slug' => 'expense-categories', 'name' => 'Kategori Pengeluaran', 'route' => '/expense-categories', 'icon' => 'folder', 'group' => 'keuangan', 'sort_order' => 11],
            ['slug' => 'wifi-credentials', 'name' => 'WiFi', 'route' => '/wifi-credentials', 'icon' => 'wifi', 'group' => 'lainnya', 'sort_order' => 12],
            ['slug' => 'stock-opname', 'name' => 'Stock Opname', 'route' => '/stock-opname', 'icon' => 'clipboard-list', 'group' => 'master', 'sort_order' => 13],
            ['slug' => 'reporting', 'name' => 'Laporan', 'route' => '/reporting', 'icon' => 'bar-chart', 'group' => 'keuangan', 'sort_order' => 14],
            ['slug' => 'export-transaction', 'name' => 'Export Transaksi', 'route' => '/export-transaction', 'icon' => 'download', 'group' => 'transaksi', 'sort_order' => 15],
        ];

        // Use Eloquent so UUIDs are auto-generated via HasUuids
        foreach ($features as $featureData) {
            Feature::updateOrCreate(
                ['slug' => $featureData['slug']],
                $featureData
            );
        }

        // Basic plan features (stock-opname is Pro only)
        $basicSlugs = [
            'dashboard', 'pos', 'products', 'product-categories', 'stores',
            'employees', 'designations', 'sales', 'wifi-credentials',
        ];

        // Pro plan = all features
        $allSlugs = array_column($features, 'slug');

        $plans = [
            'basic' => $basicSlugs,
            'pro' => $allSlugs,
            'custom' => $allSlugs,
        ];

        foreach ($plans as $plan => $slugs) {
            Feature::whereIn('slug', $slugs)->each(function (Feature $feature) use ($plan) {
                PlanFeature::updateOrCreate(
                    ['plan' => $plan, 'feature_id' => $feature->id],
                    ['is_active' => true]
                );
            });
        }
    }
}
