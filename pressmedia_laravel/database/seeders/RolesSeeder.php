<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions, ported 1:1 from the prototype (prototype_html/assets/js/admin.js: MODULES, ACTIONS, defaults()).
 * Permission names are "<module>.<action>", e.g. posts.publish, podcasts.create.
 */
class RolesSeeder extends Seeder
{
    private const MODULES = ['posts', 'categories', 'media', 'featured', 'comments', 'newsletter', 'ads', 'podcasts', 'menus', 'settings', 'users', 'ai',
        'analytics', 'tags', 'videos', 'magazine', 'radios', 'television', 'audit', 'backups'];

    private const ACTIONS = ['view', 'create', 'edit', 'delete', 'publish'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODULES as $m) {
            foreach (self::ACTIONS as $a) {
                Permission::findOrCreate("$m.$a", 'web');
            }
        }
        // Extra rights outside the module x action grid. Granted to admin only (below), like the prototype's
        // administrator-only actions: restoring the default home carousel (spec EX-41).
        Permission::findOrCreate('featured.restore', 'web');
        $all = Permission::pluck('name')->all();
        $only = fn (array $mods, array $acts) => collect($mods)->flatMap(fn ($m) => collect($acts)->map(fn ($a) => "$m.$a"))->all();

        Role::findOrCreate('admin', 'web')->syncPermissions($all);
        // The audit trail and backups are administrator-only (prototype defaults(), PRD phase 1 exit criterion).
        Role::findOrCreate('editor', 'web')->syncPermissions(array_merge(
            $only(array_diff(self::MODULES, ['settings', 'users', 'ads', 'menus', 'analytics', 'audit', 'backups']), self::ACTIONS),   // never featured.restore
            $only(['settings', 'users', 'ads', 'menus', 'analytics'], ['view'])
        ));
        Role::findOrCreate('author', 'web')->syncPermissions(array_merge(
            $only(['posts'], ['view', 'create', 'edit']), $only(['media', 'ai'], ['view', 'create']), $only(['categories', 'tags'], ['view'])
        ));
        Role::findOrCreate('moderator', 'web')->syncPermissions(array_merge(
            $only(['comments'], self::ACTIONS), $only(['posts', 'newsletter'], ['view'])
        ));
    }
}
