<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\View\View;

class EmployerDashboardController extends Controller
{
    /**
     * The landing page of a company workspace. Open to every active
     * member, not just owners and managers -- everyone on a hiring team
     * needs somewhere to start.
     */
    public function index(Company $company): View
    {
        return view('employer.dashboard', [
            'company' => $company,
        ]);
    }
}
