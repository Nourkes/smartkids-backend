<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Child;

class ChildController extends Controller
{
    public function index()
    {
        return Child::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'age' => 'required|integer',
        ]);

        return Child::create($validated);
    }
}
