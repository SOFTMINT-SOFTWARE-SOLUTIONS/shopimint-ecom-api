<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentIntentsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentIntents';

    protected static ?string $title = 'Payment Intents';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('method.code')
                    ->label('Method')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'LKR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('gateway_reference')
                    ->label('Gateway Ref')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_payloads')
                    ->label('Payloads')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading('Payment Intent Payloads')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function ($record) {
                        $request = json_encode($record->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        $response = json_encode($record->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        $webhook = json_encode($record->webhook_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                        $request = $request ?: '{}';
                        $response = $response ?: '{}';
                        $webhook = $webhook ?: '{}';

                        $html = '
<div style="display:grid; gap:16px;">
  <div>
    <div style="font-weight:600; margin-bottom:6px;">request_payload</div>
    <pre style="white-space:pre-wrap; word-break:break-word; padding:12px; background:#0b1220; color:#e5e7eb; border-radius:10px;">' . e($request) . '</pre>
  </div>
  <div>
    <div style="font-weight:600; margin-bottom:6px;">response_payload</div>
    <pre style="white-space:pre-wrap; word-break:break-word; padding:12px; background:#0b1220; color:#e5e7eb; border-radius:10px;">' . e($response) . '</pre>
  </div>
  <div>
    <div style="font-weight:600; margin-bottom:6px;">webhook_payload</div>
    <pre style="white-space:pre-wrap; word-break:break-word; padding:12px; background:#0b1220; color:#e5e7eb; border-radius:10px;">' . e($webhook) . '</pre>
  </div>
</div>';

                        return new \Illuminate\Support\HtmlString($html);
                    }),
            ]);
    }
}
