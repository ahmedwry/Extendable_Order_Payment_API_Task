<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    public function update(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    public function delete(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    public function pay(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    private function owns(User $user, Order $order): Response
    {
        return $order->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
