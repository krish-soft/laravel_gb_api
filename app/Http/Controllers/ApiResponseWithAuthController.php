<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponserTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class ApiResponseWithAuthController extends Controller
{
    //

    use ApiResponserTrait;

    // public function __construct()
    // {
    //     $this->middleware('auth:sanctum');
    // }
}
