<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderPlacedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function build()
    {
        $this->order->loadMissing('items');

        return $this
            ->subject("Order placed: {$this->order->order_number} | Siriwardana Mobile")
            ->markdown('emails.orders.placed', [
                'order' => $this->order,
            ]);
    }
}
