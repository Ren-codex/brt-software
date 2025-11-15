<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Services\Dashboard\EmployeeClass;

class DashboardController extends Controller
{
    protected $dropdown;

    public function __construct(
            DropdownClass $dropdown
        ){
        $this->dropdown = $dropdown;
    }

    public function index(Request $request){
        return inertia('Modules/Dashboard/Index');
    }


}
