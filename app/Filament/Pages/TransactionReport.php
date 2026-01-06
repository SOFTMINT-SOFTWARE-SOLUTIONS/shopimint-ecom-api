<?php

namespace App\Filament\Pages;

use App\Models\PaymentIntent;
use App\Models\PaymentMethod;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

class TransactionReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Transaction Report';
    protected static ?string $title = 'Transaction Report';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.transaction-report';

    // ---------------------------
    // TABLE
    // ---------------------------

    protected function getTableQuery(): Builder
    {
        return PaymentIntent::query()
            ->with(['order', 'method'])
            ->latest('id');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')
                ->label('Date')
                ->dateTime()
                ->sortable(),

            Tables\Columns\TextColumn::make('order.order_number')
                ->label('Order')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('method.code')
                ->label('Method')
                ->badge()
                ->sortable(),

            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->sortable(),

            Tables\Columns\TextColumn::make('amount')
                ->label('Amount')
                ->money(fn ($record) => $record->currency ?? 'LKR')
                ->sortable(),

            Tables\Columns\TextColumn::make('gateway_reference')
                ->label('Gateway Ref')
                ->copyable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('payment_method_id')
                ->label('Payment Method')
                ->options(
                    PaymentMethod::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->searchable(),

            Tables\Filters\Filter::make('date_range')
                ->form([
                    Forms\Components\DatePicker::make('from')->label('From'),
                    Forms\Components\DatePicker::make('to')->label('To'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('open_order')
                ->label('Open Order')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn ($record) => $record->order
                    ? \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->order])
                    : null
                )
                ->openUrlInNewTab()
                ->visible(fn ($record) => (bool) $record->order),
        ];
    }

    protected function getTableDefaultPaginationPageOption(): int
    {
        return 25;
    }

    // ---------------------------
    // SUMMARY (respects filters)
    // ---------------------------

    protected function getBaseFilteredQuery(): Builder
    {
        $query = PaymentIntent::query();

        // The table filter state is stored in $this->tableFilters
        $filters = (array) ($this->tableFilters ?? []);

        // Payment method filter
        if (!empty($filters['payment_method_id']['value'])) {
            $query->where('payment_method_id', $filters['payment_method_id']['value']);
        }

        // Date range filter
        $from = $filters['date_range']['from'] ?? null;
        $to   = $filters['date_range']['to'] ?? null;

        if (!empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }
        if (!empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    public function getSummaryStatsProperty(): array
    {
        $base = $this->getBaseFilteredQuery();

        $totalTransactions = (clone $base)->count();

        // Paid amount: treat captured/paid as paid states
        $paidStatuses = ['captured', 'paid'];

        $totalPaidAmount = (float) (clone $base)
            ->whereIn('status', $paidStatuses)
            ->sum('amount');

        // Unpaid/failed count: everything that is NOT paid/captured
        $unpaidFailedCount = (clone $base)
            ->whereNotIn('status', $paidStatuses)
            ->count();

        return [
            'total_transactions' => $totalTransactions,
            'total_paid_amount' => $totalPaidAmount,
            'unpaid_failed_count' => $unpaidFailedCount,
        ];
    }
}
