<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLevel;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\Order;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

class InventoryReservationService
{
    public function mainLocationId(): int
    {
        return (int) (Location::where('code', 'MAIN_SHOP')->value('id')
            ?? Location::query()->value('id'));
    }

    private function ensureItem(int $variantId): InventoryItem
    {
        return InventoryItem::firstOrCreate(['variant_id' => $variantId]);
    }

    private function getLevelForUpdate(int $variantId, int $locationId): InventoryLevel
    {
        $item = $this->ensureItem($variantId);

        return InventoryLevel::where('inventory_item_id', $item->id)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->firstOrCreate(
                ['inventory_item_id' => $item->id, 'location_id' => $locationId],
                ['available' => 0, 'reserved' => 0]
            );
    }

    /**
     * Reserve stock for an order (idempotent).
     * Increases inventory_levels.reserved by qty.
     */
    public function reserveForOrder(Order $order, int $locationId, array $items): void
    {
        DB::transaction(function () use ($order, $locationId, $items) {
            foreach ($items as $row) {
                $variantId = (int) $row['variant_id'];
                $qty = (int) $row['quantity'];

                $variant = Variant::lockForUpdate()->findOrFail($variantId);

                // If tracking disabled or backorder allowed, do not reserve
                if (!($variant->track_inventory ?? true) || ($variant->allow_backorder ?? false)) {
                    continue;
                }

                // Idempotent: if reservation exists, skip
                $existing = InventoryReservation::where('order_id', $order->id)
                    ->where('variant_id', $variantId)
                    ->where('location_id', $locationId)
                    ->first();

                if ($existing) {
                    continue;
                }

                $level = $this->getLevelForUpdate($variantId, $locationId);

                $free = (int)$level->available - (int)$level->reserved;
                if ($qty > $free) {
                    throw new \RuntimeException("Not enough stock for SKU {$variant->sku}. Available: {$free}");
                }

                $level->reserved += $qty;
                $level->save();

                InventoryReservation::create([
                    'order_id' => $order->id,
                    'variant_id' => $variantId,
                    'location_id' => $locationId,
                    'quantity' => $qty,
                    'status' => 'reserved',
                ]);
            }
        });
    }

    /**
     * Capture reservation after payment success / COD confirm:
     * reserved -= qty and available -= qty
     */
    public function captureOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $rows = InventoryReservation::where('order_id', $order->id)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $res) {
                $level = $this->getLevelForUpdate((int)$res->variant_id, (int)$res->location_id);

                $level->reserved = max(0, (int)$level->reserved - (int)$res->quantity);
                $level->available = max(0, (int)$level->available - (int)$res->quantity);
                $level->save();

                $res->status = 'captured';
                $res->save();
            }
        });
    }

    /**
     * Release reservation on payment fail / cancel / timeout:
     * reserved -= qty
     */
    public function releaseOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $rows = InventoryReservation::where('order_id', $order->id)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $res) {
                $level = $this->getLevelForUpdate((int)$res->variant_id, (int)$res->location_id);

                $level->reserved = max(0, (int)$level->reserved - (int)$res->quantity);
                $level->save();

                $res->status = 'released';
                $res->save();
            }
        });
    }
}
