<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\OrderStats::class,
            \App\Filament\Widgets\OrdersByStatusChart::class,
            \App\Filament\Widgets\RecentOrders::class
        ];
    }
}
