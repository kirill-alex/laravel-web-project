<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MainController extends Controller
{
    public function index()
    {
        $json = file_get_contents(public_path('data.json'));
        $items = json_decode($json, true);

        return view('welcome', ['items' => $items]);
    }

    public function gallery($id)
    {
        $json = file_get_contents(public_path('data.json'));
        $items = json_decode($json, true);

        $item = collect($items)->firstWhere('id', (int) $id);

        return view('gallery', ['item' => $item]);
    }
}