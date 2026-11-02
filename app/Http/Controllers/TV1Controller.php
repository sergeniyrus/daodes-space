<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TV1Controller extends Controller
{
    /**
     * Экран меню TV1.
     */
    public function index(): View
    {
        $menuItems = DB::table('menu_items')
            ->where('menu_section_id', 9)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'weight',
                'unit',
                'price',
                'description',
                'sort_order',
            ]);

        return view('tv1.index', compact('menuItems'));
    }
}
