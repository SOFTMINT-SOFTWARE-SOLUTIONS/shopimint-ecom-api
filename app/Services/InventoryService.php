<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLevel;
use App\Models\Location;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function ensureItemForVariant(int $variantId): InventoryItem
    {
        return InventoryItem::firstOrCreate(['variant_id' => $variantId]);
    }

    public function getMainLocation(): Location
    {
        return Location::where('code', 'MAIN_SHOP')->firstOrFail();
    }

    /**
     * Reserve stock for checkout (prevents overselling).
     */
    public function reserve(int $variantId, int $qty, ?int $locationId = null): void
    {
        DB::transaction(function () use ($variantId, $qty, $locationId) {
            $variant = Variant::lockForUpdate()->findOrFail($variantId);

            // if variant tracking disabled, skip
            if (!($variant->track_inventory ?? true)) {
                return;
            }

            // if backorder allowed, skip
            if (($variant->allow_backorder ?? false)) {
                return;
            }

            $item = $this->ensureItemForVariant($variantId);
            $locId = $locationId ?: $this->getMainLocation()->id;

            $level = InventoryLevel::where('inventory_item_id', $item->id)
                ->where('location_id', $locId)
                ->lockForUpdate()
                ->firstOrCreate([
                    'inventory_item_id' => $item->id,
                    'location_id' => $locId,
                ], [
                    'available' => 0,
                    'reserved' => 0,
                ]);

            $free = (int)$level->available - (int)$level->reserved;

            if ($qty > $free) {
                throw new \RuntimeException("Not enough stock. Available: {$free}");
            }

            $level->reserved += $qty;
            $level->save();
        });
    }

    /**
     * Capture reservation (when payment successful).
     * Moves reserved -> deducted from available.
     */
    public function capture(int $variantId, int $qty, ?int $locationId = null): void
    {
        DB::transaction(function () use ($variantId, $qty, $locationId) {
            $item = $this->ensureItemForVariant($variantId);
            $locId = $locationId ?: $this->getMainLocation()->id;

            $level = InventoryLevel::where('inventory_item_id', $item->id)
                ->where('location_id', $locId)
                ->lockForUpdate()
                ->firstOrFail();

            $level->reserved = max(0, (int)$level->reserved - $qty);
            $level->available = max(0, (int)$level->available - $qty);
            $level->save();
        });
    }

    /**
     * Release reservation (payment failed / cancelled).
     */
    public function release(int $variantId, int $qty, ?int $locationId = null): void
    {
        DB::transaction(function () use ($variantId, $qty, $locationId) {
            $item = $this->ensureItemForVariant($variantId);
            $locId = $locationId ?: $this->getMainLocation()->id;

            $level = InventoryLevel::where('inventory_item_id', $item->id)
                ->where('location_id', $locId)
                ->lockForUpdate()
                ->firstOrFail();

            $level->reserved = max(0, (int)$level->reserved - $qty);
            $level->save();
        });
    }
}
