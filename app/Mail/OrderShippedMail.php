<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function build()
    {
        $this->order->loadMissing('items');

        return $this
            ->subject("Order shipped: {$this->order->order_number} | Siriwardana Mobile")
            ->markdown('emails.orders.shipped', [
                'order' => $this->order,
            ]);
    }
}
