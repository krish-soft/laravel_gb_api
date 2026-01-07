<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponserTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class ApiResponseWithAdminAuthController extends Controller
{
    //

    use ApiResponserTrait;

    // public function __construct()
    // {
    //     // Auth + Admin User Check Middleware
    //     // Covering Admin Auth Protected Routes
    //     // $this->middleware(['auth:sanctum', 'admin-user-checker']);

    //     // Other things can be added here if needed
    // }
}
