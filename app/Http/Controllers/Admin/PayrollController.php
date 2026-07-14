<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class PayrollController extends Controller
{
    public function index()
    {
        return view('admin.payroll.index');
    }
}
