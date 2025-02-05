<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreRequest;
use App\Http\Requests\Sale\UpdateRequest;
use App\Models\Sale;
use App\Models\Client;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Request;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class SaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index()
    {
        $sales = Sale::all();
        return view('admin.sale.index', compact('sales'));
    }

    public function create()
    {
        $clients = Client::get();
        $products = Product::get();
        return view('admin.sale.create', compact('clients', 'products'));
    }

    public function store(StoreRequest $request)
    {
        $sale = Sale::create($request->all() + [
            'user_id' => Auth::user()->id,
            'sale_date' => Carbon::now('America/El_Salvador'),
        ]);

        foreach ($request->product_id as $key => $value) {
            $results[] = ['product_id' => $request->product_id[$key], 
            'quantity' => $request->quantity[$key], 'price' => $request->price[$key],
            'discount' => $request->discount[$key]];
        }

        $sale->saleDetails()->createMany($results);

        return redirect()->route('sales.index');
    }
    
    public function show(Sale $sale)
    {
        $subtotal = 0;
        $saleDetails = $sale->saleDetails;
        foreach ($saleDetails as $saleDetail) {
            $subtotal += ($saleDetail->quantity*$saleDetail->price)-($saleDetail->quantity*$saleDetail->price*$saleDetail->discount/100);
        }
        return view('admin.sale.show', compact('sale', 'subtotal', 'saleDetails'));
    }
    
    public function edit(Sale $sale)
    {
        $clients = Client::get();
        return view('admin.sale.index', compact('sale', 'clients'));
    }
    
    public function update(UpdateRequest $request, Sale $sale)
    {
        $sale->update($request->all());
        return redirect()->route('sales.index');
    }
    
    public function destroy(Sale $sale)
    {
        $sale->delete();
        return redirect()->route('sales.index');
    }

    public function pdf(Sale $sale)
    {
        $subtotal = 0;
        $saleDetails = $sale->saleDetails;
        foreach ($saleDetails as $saleDetail) {
            $subtotal += ($saleDetail->quantity*$saleDetail->price)-($saleDetail->quantity*$saleDetail->price*$saleDetail->discount/100);
        }
        $pdf = PDF::loadView('admin.sale.pdf', compact('sale', 'subtotal', 'saleDetails'));
        return $pdf->download('Reporte_de_venta_'.$sale->id.'.pdf');
    }

    public function upload(Request $request, Sale $sale)
    {

    }

    public function change_status(Sale $sale)
    {
        $sale->status == 'VALID' ? $sale->update(['status' => 'CANCELED']) : $sale->update(['status' => 'VALID']);
        return redirect()->back();
    }

    public function print(Sale $sale)
    {
        try {
            $subtotal = 0;
            $saleDetails = $sale->saleDetails;
            foreach ($saleDetails as $saleDetail) {
                $subtotal += ($saleDetail->quantity*$saleDetail->price)-($saleDetail->quantity*$saleDetail->price*$saleDetail->discount/100);
            }
            
            $printer_name = "TM20";
            $connector = new WindowsPrintConnector($printer_name);
            $printer = new Printer($connector);

            $printer->text("€ 9,95\n");

            $printer->cut();
            $printer->close();

            return redirect()->back();
        } catch (\Throwable $th) {
            return redirect()->back();
        }

    }
}
