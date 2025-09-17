<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Discount;
use App\Models\History;
use App\Models\Order;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function add_to_cart(Request $request)
    {
        try {
            $validated = $request->validate([
                "product_id" => "required|exists:products,id",
                "quantity" => "required|integer|not_in:0",
            ]);

            $user = $request->user('sanctum');
            $product = Product::query()->findOrFail($validated["product_id"]);

            if ($validated['quantity'] > 0 && $validated["quantity"] > $product->stock) {
                return response()->json(["error" => "quantity is out of stock"], 400);
            }

            $cartItem = Cart::query()->where('user_id', $user->id)
                ->where('product_id', $validated["product_id"])
                ->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $validated["quantity"];

                if ($newQuantity < 1) {
                    $cartItem->delete();
                    return response()->json(["message" => "Item removed from cart"]);
                }

                if ($newQuantity > $product->stock) {
                    return response()->json(["error" => "quantity is out of stock"], 400);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->save();
            } else {
                if ($validated["quantity"] < 0) {
                    return response()->json(["error" => "invalid quantity"]);
                }

                $cartItem = Cart::query()->create([
                    'user_id' => $user->id,
                    'product_id' => $validated["product_id"],
                    'quantity' => $validated["quantity"]
                ]);
            }

            return response()->json(CartResource::make($cartItem));
        } catch (Exception $e) {
            Log::error('Add to cart error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function remove_from_cart(string $id, Request $request)
    {
        try {
            $user = $request->user('sanctum');

            $cartItem = Cart::query()->where('user_id', $user->id)
                ->where('product_id', $id)
                ->first();

            if (!$cartItem) {
                return response()->json(['error' => 'Item not found in cart'], 404);
            }

            $cartItem->delete();

            return response()->json(["message" => "Item removed from cart"]);
        } catch (Exception $e) {
            Log::error('Remove from cart error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function apply_discount(Request $request)
    {
        try {
            $validated = $request->validate([
                'discount_code' => 'required|exists:discounts,code'
            ]);

            $user = $request->user('sanctum');
            $cartItems = $user->cart;

            if ($cartItems->isEmpty()) {
                return response()->json(['error' => 'Cart not found'], 404);
            }

            $discount = Discount::query()->where('code', $validated["discount_code"])->first();

            if ($discount->expires_at && $discount->expires_at < now()) {
                return response()->json(["error" => "Discount has expired"], 400);
            }

            session(['discount_id' => $discount->id]);

            return response()->json([
                'message' => 'Discount applied successfully',
                'discount' => $discount
            ]);
        } catch (Exception $e) {
            Log::error('Apply discount error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function set_address(Request $request, string $id)
    {
        try {
            $user = $request->user('sanctum');
            $address = Address::query()->findOrFail($id);

            if ($address->user_id !== $user->id) {
                return response()->json(["error" => "You can't set someone else's address to your cart"], 400);
            }

            session(['address_id' => $id]);

            return response()->json(["message" => "Address set successfully"]);
        } catch (Exception $e) {
            Log::error('Set address error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function get_cart(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $cartItems = $user->cart()->with('product.subcategory.parent')->get();

            $formattedItems = $cartItems->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'product' => ProductResource::make($item->product)
                ];
            })->toArray();

            return response()->json([
                'user_id' => $user->id,
                'items' => $formattedItems
            ]);
        } catch (Exception $e) {
            Log::error('Get cart error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function submit_order(Request $request)
    {
        try {
            $validated = $request->validate([
                'address_id' => 'required|exists:addresses,id',
                'payment_method' => 'sometimes|in:wallet,cash',
                'discount_code' => 'sometimes|string|exists:discounts,code'
            ]);

            if (!isset($validated['payment_method'])) {
                $validated['payment_method'] = 'wallet';
            }

            $user = $request->user('sanctum');
            $cartItems = $user->cart()->with('product')->get();

            if ($cartItems->isEmpty()) {
                return response()->json(["error" => "Cart is empty"]);
            }
            $before_discount_amount = 0;
            $productsInfo = [];

            foreach ($cartItems as $item) {
                if (!$item->product || $item->quantity > $item->product->stock) {
                    return response()->json(["error" => "Product out of stock or not found"]);
                }

                $before_discount_amount += $item->product->price * $item->quantity;

                $productsInfo[] = [
                    'product' => new ProductResource($item->product),
                    'quantity' => $item->quantity
                ];
            }

            $total_amount = $before_discount_amount;
            $discountInfo = null;

            if (!empty($validated['discount_code'])) {
                $discount = Discount::query()->where('code', $validated['discount_code'])->first();

                if ($discount) {
                    if ($discount->expires_at && $discount->expires_at < now()) {
                        return response()->json(["error" => "Discount has expired"], 400);
                    }

                    $discountInfo = [
                        'id' => $discount->id,
                        'code' => $discount->code,
                        'discount_percentage' => $discount->discount_percentage,
                        'max_amount' => $discount->max_amount
                    ];

                    if ($discount->max_amount && $before_discount_amount > $discount->max_amount) {
                        $total_amount = $before_discount_amount - $discount->max_amount;
                    } else {
                        $total_amount = $before_discount_amount * (100 - $discount->discount_percentage) / 100;
                    }
                }
            }

            if ($validated['payment_method'] === 'wallet' && $user->wallet_balance < $total_amount) {
                return response()->json(["error" => "Insufficient funds"]);
            }

            $address = Address::query()->find($validated['address_id']);
            if (!$address || $address->user_id !== $user->id) {
                return response()->json(["error" => "Valid address required"]);
            }

            $orderInfo = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number
                ],
                'address' => [
                    'id' => $address->id,
                    'description' => $address->description,
                    'province' => $address->province,
                    'city' => $address->city
                ],
                'payment_method' => $validated['payment_method'],
                'status' => 'processing',
                'discount' => $discountInfo,
                'products' => [],
                'before_discount_amount' => $before_discount_amount,
                'total_amount' => $total_amount
            ];

            foreach ($cartItems as $item) {
                $product = $item->product;

                $features = $product->features;
                if (is_string($features)) {
                    $decoded = json_decode($features, true);
                    $features = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [$features];
                } elseif (!is_array($features)) {
                    $features = [];
                }

                $orderInfo['products'][] = [
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'description' => $product->description,
                        'features' => $features,
                        'image_1' => $product->image_1,
                        'image_2' => $product->image_2,
                        'image_3' => $product->image_3,
                        'subcategory' => $product->subcategory ? [
                            'id' => $product->subcategory->id,
                            'title' => $product->subcategory->title,
                            'parent_id' => $product->subcategory->parent_id
                        ] : null,
                        'show_in_home_page' => $product->show_in_home_page,
                        'stock' => $product->stock,
                        'before_discount_price' => $product->before_discount_price,
                        'discount_percentage' => $product->discount_percentage,
                        'price' => $product->price,
                        'sold_count' => $product->sold_count
                    ],
                    'quantity' => $item->quantity
                ];
            }

            $order = Order::query()->create([
                'info' => $orderInfo,
                'order_submitted_at' => now()
            ]);

            foreach ($cartItems as $item) {
                $product = $item->product;
                $product->stock -= $item->quantity;
                $product->sold_count += $item->quantity;
                $product->save();

                if ($product->stock < 1) {
                    $product->delete();
                }
            }

            if ($validated['payment_method'] === 'wallet') {
                $user->wallet_balance -= $total_amount;
                $user->save();

                History::query()->create([
                    "user_id" => $user->id,
                    "amount" => $total_amount,
                ]);
            }

            $user->cart()->delete();

            return response()->json([
                "message" => "Order submitted successfully",
                "order" => OrderResource::make($order)
            ]);
        } catch (Exception $e) {
            Log::error('Submit order error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function get_orders(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $orders = Order::query()->whereRaw('JSON_EXTRACT(info, "$.user.id") = ?', [$user->id])->get();

            return response()->json(OrderResource::collection($orders));
        } catch (Exception $e) {
            Log::error('Get orders error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function get_order_by_id(string $id, Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $order = Order::query()->findOrFail($id);
            if ($order->info['user']['id'] != $user->id) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            return response()->json(OrderResource::make($order));
        } catch (Exception $e) {
            Log::error('Get order by ID error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function get_orders_admin(Request $request)
    {
        try {
            $validated = $request->validate([
                'from_created_at' => 'sometimes|string',
                'to_created_at' => 'sometimes|string',
                'user_id' => 'sometimes|integer|exists:users,id',
                'status' => 'sometimes|string|in:processing,shipped',
            ]);

            $query = Order::query();

            if ($request->has('from_created_at') && !empty($validated["from_created_at"])) {
                $query->where('order_submitted_at', '>=', $validated["from_created_at"]);
            }

            if ($request->has('to_created_at') && !empty($validated["to_created_at"])) {
                $query->where('order_submitted_at', '<=', $validated["to_created_at"]);
            }

            if ($request->has('user_id') && !empty($validated["user_id"])) {
                $query->whereRaw('JSON_EXTRACT(info, "$.user.id") = ?', [$validated["user_id"]]);
            }

            if ($request->has('status') && !empty($validated["status"])) {
                $query->whereRaw('JSON_EXTRACT(info, "$.status") = ?', [$validated["status"]]);
            }

            $orders = $query->get();

            return response()->json(OrderResource::collection($orders));
        } catch (Exception $e) {
            Log::error('Get orders admin error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function get_order_by_id_admin(string $id)
    {
        try {
            $order = Order::query()->findOrFail($id);
            return response()->json(OrderResource::make($order));
        } catch (Exception $e) {
            Log::error('Get order by ID admin error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function update_order(Request $request, string $id)
    {
        try {
            $order = Order::query()->findOrFail($id);
            $validated = $request->validate([
                'status' => 'required|string|in:processing,shipped',
            ]);

            $info = $order->info;
            $info['status'] = $validated["status"];
            $order->update([
                'info' => $info
            ]);
            return response()->json(OrderResource::make($order));
        } catch (Exception $e) {
            Log::error('Update order error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }
}
