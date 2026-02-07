<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Variant;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function show(Request $request, CartService $cartService)
    {
        $customerId = optional($request->user())->id ?? null;
        $guestToken = $request->header('X-Guest-Token') ?? $request->get('guest_token');

        $cart = $cartService->getOrCreateCart(null, $guestToken);
        $cart->load(['items.variant.product']);

        $summary = $cartService->summary(
            $cart,
            $request->get('fulfillment_method', 'delivery'),
            $request->get('payment_method_code', 'COD')
        );

        return response()->json([
            'cart' => $cart,
            'guest_token' => $cart->guest_token,
            'summary' => $summary,
        ]);
    }

    public function addItem(Request $request, CartService $cartService)
    {
        $data = $request->validate([
            'variant_id' => 'required|integer|exists:variants,id',
            'quantity'   => 'required|integer|min:1|max:50',
        ]);

        $guestToken = $request->header('X-Guest-Token') ?? $request->get('guest_token');
        $cart = $cartService->getOrCreateCart(null, $guestToken);

        $variant = Variant::with('product')->findOrFail($data['variant_id']);

        // Optional stock check at add-to-cart
        if ($variant->is_active != 1) {
            return response()->json(['message' => 'Variant not available'], 422);
        }

        // Snapshot unit price from variant
        $unitPrice = $variant->price;

        DB::transaction(function () use ($cart, $variant, $data, $unitPrice) {
            $item = $cart->items()->where('variant_id', $variant->id)->first();

            if ($item) {
                $item->quantity += $data['quantity'];
                $item->unit_price = $unitPrice;
                $item->save();
            } else {
                $cart->items()->create([
                    'variant_id' => $variant->id,
                    'quantity' => $data['quantity'],
                    'unit_price' => $unitPrice,
                    'currency' => $variant->currency ?? 'LKR',
                ]);
            }
        });

        $cart->load(['items.variant.product']);

        return response()->json([
            'cart' => $cart,
            'guest_token' => $cart->guest_token,
        ]);
    }

    public function updateItem(Request $request, CartService $cartService)
    {
        $data = $request->validate([
            'variant_id' => 'required|integer|exists:variants,id',
            'quantity'   => 'required|integer|min:0|max:50',
        ]);

        $guestToken = $request->header('X-Guest-Token') ?? $request->get('guest_token');
        $cart = $cartService->getOrCreateCart(null, $guestToken);

        $item = $cart->items()->where('variant_id', $data['variant_id'])->first();

        if (!$item) {
            return response()->json(['message' => 'Item not found in cart'], 404);
        }

        if ($data['quantity'] === 0) {
            $item->delete();
        } else {
            $item->quantity = $data['quantity'];
            $item->save();
        }

        $cart->load(['items.variant.product']);

        return response()->json([
            'cart' => $cart,
            'guest_token' => $cart->guest_token,
        ]);
    }

    public function clear(Request $request, CartService $cartService)
    {
        $guestToken = $request->header('X-Guest-Token') ?? $request->get('guest_token');
        $cart = $cartService->getOrCreateCart(null, $guestToken);

        $cart->items()->delete();

        return response()->json([
            'message' => 'Cart cleared',
            'guest_token' => $cart->guest_token,
        ]);
    }
}
