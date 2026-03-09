<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeExport;
use App\Exports\ExpenseCategoryExport;
use App\Exports\ExpenseExport;
use App\Exports\ProductCategoryExport;
use App\Exports\ProductExport;
use App\Exports\StoreExport;
use App\Exports\TransactionExport;
use App\Traits\UsesCompanyScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    use UsesCompanyScope;

    public function exportTransactions(Request $request)
    {
        $companyId = $this->getCompanyId();
        $start     = $request->query('start_date', Carbon::now('Asia/Jakarta')->subDays(29)->format('Y-m-d'));
        $end       = $request->query('end_date', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        $status    = $request->query('status');
        $type      = $request->query('type');
        $storeId   = $request->query('store_id');

        $filename = 'penjualan_' . Carbon::now('Asia/Jakarta')->format('Ymd') . '.xlsx';

        return Excel::download(
            new TransactionExport($companyId, $start, $end, $status, $type, $storeId),
            $filename,
        );
    }

    public function exportExpenses(Request $request)
    {
        $companyId  = $this->getCompanyId();
        $start      = $request->query('start_date', Carbon::now('Asia/Jakarta')->subDays(29)->format('Y-m-d'));
        $end        = $request->query('end_date', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        $storeId    = $request->query('store_id');
        $categoryId = $request->query('category_id');

        $filename = 'pengeluaran_' . Carbon::now('Asia/Jakarta')->format('Ymd') . '.xlsx';

        return Excel::download(
            new ExpenseExport($companyId, $start, $end, $storeId, $categoryId),
            $filename,
        );
    }

    public function exportProducts(Request $request)
    {
        $companyId   = $this->getCompanyId();
        $storeId     = $request->query('store_id');
        $sellingType = $request->query('selling_type');
        $filename    = 'produk_' . Carbon::now('Asia/Jakarta')->format('Ymd') . '.xlsx';

        return Excel::download(new ProductExport($companyId, $storeId, $sellingType), $filename);
    }

    public function exportEmployees(Request $request)
    {
        $companyId = $this->getCompanyId();
        $filename  = 'karyawan_' . Carbon::now('Asia/Jakarta')->format('Ymd') . '.xlsx';

        return Excel::download(new EmployeeExport($companyId), $filename);
    }

    public function exportStores(Request $request)
    {
        $companyId = $this->getCompanyId();
        $filename  = 'toko_' . Carbon::now('Asia/Jakarta')->format('Ymd') . '.xlsx';

        return Excel::download(new StoreExport($companyId), $filename);
    }

    public function exportProductCategories(Request $request)
    {
        $companyId = $this->getCompanyId();
        $filename  = 'kategori_produk_' . Carbon::now('Asia/Jakarta')->format('Ymd') . '.xlsx';

        return Excel::download(new ProductCategoryExport($companyId), $filename);
    }

    public function exportExpenseCategories(Request $request)
    {
        $companyId = $this->getCompanyId();
        $filename  = 'kategori_pengeluaran_' . Carbon::now('Asia/Jakarta')->format('Ymd') . '.xlsx';

        return Excel::download(new ExpenseCategoryExport($companyId), $filename);
    }
}
