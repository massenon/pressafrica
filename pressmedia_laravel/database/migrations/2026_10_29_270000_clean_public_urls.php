<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Public URLs lost their ".html" (owner decision 2026-10-29). Links already typed into the admin and the visit log
// are rewritten so menus, slides and audience reports use the new addresses; the old ones still redirect (301).
return new class extends Migration
{
    private const COLUMNS = [['hero_slide_translations', 'url'], ['menu_item_translations', 'url'], ['page_views', 'path']];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'like', '%.html%')->orderBy('id')->each(function ($row) use ($table, $column) {
                $clean = preg_replace(['#(/(?:fr|en))/index\.html#', '#(/(?:fr|en)/[a-z0-9/-]+)\.html#'], '$1', $row->$column);
                DB::table($table)->where('id', $row->id)->update([$column => $clean]);
            });
        }
    }
};
