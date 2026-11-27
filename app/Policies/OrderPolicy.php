<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        return $order->user_id !== null
            && (int) $order->user_id === (int) $user->getAuthIdentifier()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function uploadImage(User $user, Order $order): Response
    {
        return $this->view($user, $order);
    }
}
