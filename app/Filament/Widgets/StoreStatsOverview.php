<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return OrderResource::staffCan('orders.view');
    }

    protected function getStats(): array
    {
        $threshold = (int) config('shop.low_stock_threshold', 5);
        $currency = config('shop.currency', 'EUR');

        return [
            Stat::make('Orders', number_format(Order::count()))
                ->description(Order::where('status', OrderStatus::PENDING)->count().' pending'),
            Stat::make('Revenue', $currency.' '.number_format((float) Order::where('status', OrderStatus::COMPLETED)->sum('total_amount'), 2))
                ->description('Completed orders'),
            Stat::make('Low-stock products', number_format(Product::where('stock_quantity', '<=', $threshold)->count()))
                ->description("{$threshold} units or fewer")
                ->color('danger'),
        ];
    }
}
