<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menus = [
            ['title' => 'Mailers', 'route' => 'mailers.index', 'route_is' => 'mailers', 'enabled' => true],
            ['title' => 'Sheetmailers', 'route' => 'sheetmailers.index', 'route_is' => 'sheetmailers', 'enabled' => true],
            ['title' => 'Items', 'route' => 'items.index', 'route_is' => 'items', 'enabled' => true],
            ['title' => 'Chatbots', 'route' => 'chatbots.index', 'route_is' => 'chatbots', 'enabled' => true],
            ['title' => 'Log Reader', 'route' => 'log-reader', 'route_is' => 'log-reader', 'enabled' => true],
        ];

        foreach ($menus as $m) {
            Menu::updateOrCreate(
                ['route_is' => $m['route_is']],
                $m
            );
        }
    }
}
