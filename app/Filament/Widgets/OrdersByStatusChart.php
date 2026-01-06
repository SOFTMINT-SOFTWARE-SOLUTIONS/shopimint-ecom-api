<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrdersByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Orders by Status';
    protected static ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        // Your finalized status set:
        $statuses = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'ready_to_pickup' => 'Ready to Pickup',
            'on_delivery' => 'On Delivery',
            'delivered' => 'Delivered',
            'canceled' => 'Canceled',
            'refunded' => 'Refunded',
        ];

        $counts = [];
        foreach (array_keys($statuses) as $status) {
            $counts[] = Order::where('status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $counts,
                ],
            ],
            'labels' => array_values($statuses),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
