<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartResource;
use App\Http\Resources\ItemResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Discount;
use App\Models\History;
use App\Models\Item;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function add_to_cart(Request $request)
    {
        $validated = $request->validate([
            "product_id" => "required|exists:products,id",
            "quantity" => "required|integer|not_in:0",
        ]);
        $cart = $request->user('sanctum')->carts()->whereNull('order_submitted_at')->first();
        if (!$cart) {
            return response()->json(['error' => 'Cart not found'], 404);
        }
        if ($cart->order_submitted_at) {
            return response()->json(["error" => "You cant add products to already submitted carts"], 400);
        }
        $product = Product::query()->findOrFail($validated["product_id"]);
        if ($validated['quantity'] > 0 && $validated["quantity"] > $product->stock) {
            return response()->json(["error" => "quantity is out of stock"], 400);
        }
        $item = $cart->items()->where('product_id', $validated["product_id"])->first();
        if ($item) {
            if ($validated["quantity"] < 0 && abs($validated["quantity"]) > $item->quantity) {
                return response()->json(["error" => "Cannot remove more items than are in cart"]);
            }
            $item->quantity += $validated["quantity"];
            $item->save();
            $cart->recalculateTotals();
            if ($item->quantity < 1) {
                $item->forceDelete();
                return response()->json(["message" => "Item removed from cart"]);
            }
            return response()->json(ItemResource::make($item));
        }
        if ($validated["quantity"] < 0) {
            return response()->json(["error" => "invalid quantity"]);
        }
        $item = Item::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $validated["product_id"],
            'quantity' => $validated["quantity"]
        ]);
        $cart->recalculateTotals();
        return response()->json(ItemResource::make($item));
    }

    public function remove_from_cart(string $id, Request $request)
    {
        $cart = $request->user('sanctum')->carts()->whereNull('order_submitted_at')->first();
        if (!$cart) {
            return response()->json(['error' => 'Cart not found'], 404);
        }
        try {
            $item = $cart->items()->where('product_id', $id)->firstOrFail();
        } catch (ModelNotFoundException) {
            return response()->json(["error" => "Item not found in cart"], 404);
        }
        if ($cart->order_submitted_at) {
            return response()->json(["error" => "You cant remove product from already submitted carts"], 400);
        }
        $item->delete();
        $cart->recalculateTotals();
        return response()->json(["message" => "Item removed from cart"]);
    }

    public function apply_discount(Request $request)
    {
        $validated = $request->validate([
            'discount_id' => 'required|exists:discounts,id'
        ]);
        $cart = $request->user('sanctum')->carts()->whereNull('order_submitted_at')->first();
        if ($cart->order_submitted_at) {
            return response()->json(["error" => "You cant apply discount for already submitted carts"], 400);
        }
        $discount = Discount::query()->find($validated["discount_id"]);
        if ($discount->expires_at && $discount->expires_at < now()) {
            return response()->json(["error" => "Discount has expired"], 400);
        }
        if ($cart->discount_id) {
            return response()->json(["error" => "You cant apply discount for already discounted carts"], 400);
        }
        $cart->discount_id = $validated["discount_id"];
        $cart->save();
        $cart->recalculateTotals();
        return response()->json(CartResource::make($cart));
    }

    public function set_address(Request $request, string $id)
    {
        $user = $request->user('sanctum');
        $cart = $user->carts()->whereNull('order_submitted_at')->first();
        $address = Address::query()->findOrFail($id);
        if ($address->user_id !== $user->id) {
            return response()->json(["error" => "You cant set someone else's address to your cart"], 400);
        }
        if ($cart->order_submitted_at) {
            return response()->json(["error" => "You cant set address for already submitted carts"], 400);
        }
        $cart->address_id = $id;
        $cart->save();
        return response()->json(CartResource::make($cart));
    }

    public function get_cart(Request $request)
    {
        $user = $request->user('sanctum');
        $cart = $user->carts()->with('items.product.subcategory.parent', 'discount', 'address')->whereNull('order_submitted_at')->first();
        return response()->json(CartResource::make($cart));
    }

    public function submit_order(Request $request)
    {
        $user = $request->user('sanctum');
        $cart = $user->carts()->whereNUll("order_submitted_at")->first();
        if ($cart->before_discount_amount <= 0) {
            return response()->json(["error" => "Cart is empty"]);
        }
        foreach ($cart->items as $item) {
            if ($item->quantity > $item->product->stock) {
                return response()->json(["error" => "quantity out of stock"]);
            }
            $item->product->stock -= $item->quantity;
            $item->product->sold_count += $item->quantity;
            $item->product->save();
            if ($item->product->stock < 1) {
                $item->product->delete();
            }
        }
        if (str_contains($cart->payment_method, "wallet") && $user->wallet_balance < $cart->total_amount) {
            return response()->json(["error" => "Insufficient funds"]);
        }
        if (!$cart->address || !$cart->address_id) {
            return response()->json(["error" => "Address not found"]);
        }
        $cart->order_submitted_at = now();
        $cart->payment_method = explode("|", $cart->payment_method)[0] . "|processing";
        $cart->save();
        if (str_contains($cart->payment_method, "wallet")) {
            $user->wallet_balance -= $cart->total_amount;
            $user->save();
            History::query()->create([
                "user_id" => $user->id,
                "amount" => $cart->total_amount,
            ]);
        }
        Cart::query()->create([
            'user_id' => $user->id,
        ]);
        return response()->json(CartResource::make($cart));
    }

    public function get_orders(Request $request)
    {
        $user = $request->user('sanctum');
        $orders = $user->carts()->with('items.product.subcategory.parent', 'discount', 'address')->whereNotNull("order_submitted_at")->get();
        return response()->json(CartResource::collection($orders));
    }

    public function get_order_by_id(string $id, Request $request)
    {
        $user = $request->user('sanctum');
        $order = $user->carts()->with('items.product.subcategory.parent', 'discount', 'address')->whereNotNull("order_submitted_at")->findOrFail($id);
        return response()->json(CartResource::make($order));
    }

    public function get_orders_admin(Request $request)
    {
        $validated = $request->validate([
            'from_created_at' => 'sometimes|string',
            'to_created_at' => 'sometimes|string',
            'user_id' => 'sometimes|integer|exists:users,id',
            'status' => 'sometimes|string|in:processing,shipped',
        ]);
        $query = Cart::with('items.product.subcategory.parent', 'discount', 'address')->whereNotNull("order_submitted_at");
        if ($request->has('from_created_at') && !empty($validated["from_created_at"])) {
            $query->where('order_submitted_at', '>=', $validated["from_created_at"]);
        }
        if ($request->has('to_created_at') && !empty($validated["to_created_at"])) {
            $query->where('order_submitted_at', '<=', $validated["to_created_at"]);
        }
        if ($request->has('user_id') && !empty($validated["user_id"])) {
            $query->where('user_id', $validated["user_id"]);
        }
        if ($request->has('status') && !empty($validated["status"])) {
            $query->where('payment_method', "LIKE", "%" . $validated["status"]);
        }
        $orders = $query->get();
        return response()->json(CartResource::collection($orders));
    }

    public function get_order_by_id_admin(string $id)
    {
        $order = Cart::with('items.product.subcategory.parent', 'discount_admin', 'address')->whereNotNull("order_submitted_at")->findOrFail($id);
        return response()->json(CartResource::make($order));
    }

    public function update_order(Request $request, string $id)
    {
        $order = Cart::with('items.product.subcategory.parent', 'discount_admin', 'address')->whereNotNull("order_submitted_at")->findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|string|in:processing,shipped',
        ]);
        $order->payment_method = explode("|", $order->payment_method)[0] . "|" . $validated["status"];
        $order->save();
        return response()->json(CartResource::make($order));
    }
}
