<?php

namespace App\Http\Controllers;

use App\Services\HelloService;
use Illuminate\Http\Request;

class SimpleController extends Controller
{
    public function hello(Request $request)
    {
        $message = HelloService::greet();

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
