<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\IndexOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->whereBelongsTo($request->user())
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->enum('status', OrderStatus::class)),
            )
            ->with('items')
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request): Order {
            $order = $request->user()->orders()->create([
                'status' => OrderStatus::Pending,
            ]);

            $order->replaceItems($request->validated('items'));

            return $order->load('items');
        });

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return OrderResource::make($order->load('items'));
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $this->authorize('update', $order);

        DB::transaction(function () use ($request, $order): void {
            if ($request->exists('status')) {
                $order->update([
                    'status' => $request->enum('status', OrderStatus::class),
                ]);
            }

            if ($request->exists('items')) {
                $order->replaceItems($request->validated('items'));
            }
        });

        return OrderResource::make($order->load('items'));
    }

    public function destroy(Order $order): Response|JsonResponse
    {
        $this->authorize('delete', $order);

        if ($order->hasPayments()) {
            return response()->json([
                'message' => 'An order with payments cannot be deleted.',
            ], 409);
        }

        $order->delete();

        return response()->noContent();
    }
}
