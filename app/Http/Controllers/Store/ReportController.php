<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function deliveryRates()
    {
        return view('store.reports.delivery-rates');
    }
}
