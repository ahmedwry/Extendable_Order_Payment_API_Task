<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPaymentRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\OrderNotConfirmedException;
use App\Payments\ProcessPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(IndexPaymentRequest $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->whereHas('order', fn ($query) => $query->whereBelongsTo($request->user()))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return PaymentResource::collection($payments);
    }

    public function forOrder(IndexPaymentRequest $request, Order $order): AnonymousResourceCollection
    {
        $this->authorize('view', $order);

        $payments = $order->payments()
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request, Order $order, ProcessPayment $processPayment): JsonResponse
    {
        $this->authorize('pay', $order);

        try {
            $payment = $processPayment->handle(
                $order,
                $request->validated('method'),
                $request->boolean('decline'),
            );
        } catch (OrderNotConfirmedException) {
            return response()->json([
                'message' => 'Payments can only be processed for confirmed orders.',
            ], 409);
        }

        return PaymentResource::make($payment)
            ->response()
            ->setStatusCode(201);
    }
}
