<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class TV3Controller extends Controller
{
    public function index()
    {
        $menuItems = DB::table('menu_items')
            ->whereIn('menu_section_id', [5, 6, 7, 8, 11, 12, 13])
            ->where('is_active', 1)
            ->orderBy('menu_section_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get([
                'id',
                'menu_section_id',
                'name',
                'weight',
                'unit',
                'price',
                'description',
                'sort_order',
                'is_active',
            ]);

        return view('tv.tv3', compact('menuItems'));
    }
}