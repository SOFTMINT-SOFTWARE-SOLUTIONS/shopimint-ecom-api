<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class OrderStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = (float) Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('grand_total');

        $monthRevenue = (float) Order::where('created_at', '>=', $monthStart)
            ->where('payment_status', 'paid')
            ->sum('grand_total');

        $unpaid = Order::whereIn('payment_status', ['unpaid', 'pending'])->count();
        $customers = Customer::count();

        return [
            Stat::make("Today's Orders", $todayOrders)
                ->description('New orders today')
                ->icon('heroicon-o-shopping-bag'),

            Stat::make("Today's Revenue (Paid)", number_format($todayRevenue, 2) . ' LKR')
                ->description('Paid orders only')
                ->icon('heroicon-o-banknotes'),

            Stat::make("This Month Revenue (Paid)", number_format($monthRevenue, 2) . ' LKR')
                ->description('Paid orders only')
                ->icon('heroicon-o-chart-bar'),

            Stat::make("Unpaid / Pending Orders", $unpaid)
                ->description('Need attention')
                ->icon('heroicon-o-exclamation-triangle'),

            Stat::make("Customers", $customers)
                ->description('Total customers')
                ->icon('heroicon-o-users'),
        ];
    }
}
