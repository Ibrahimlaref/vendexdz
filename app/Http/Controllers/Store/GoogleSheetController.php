<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;

class GoogleSheetController extends Controller
{
    public function index()
    {
        return view('store.google-sheets.index');
    }
}
