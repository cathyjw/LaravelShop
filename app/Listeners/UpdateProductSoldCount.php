<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\OrderItem;
//  implements ShouldQueue 
class UpdateProductSoldCount implements ShouldQueue
{
    //
    public function handle(OrderPaid $event)
    {
        //
        $order = $event->getOrder();
        // reload product's data
        $order->load('items.product');
        // go through all the product in order
        foreach ($order->items as $item) {
            $product   = $item->product;
            // calculte each item's sales volume
            $soldCount = OrderItem::query()
                ->where('product_id', $product->id)
                ->whereHas('order', function ($query) {
                    $query->whereNotNull('paid_at');  // if the order is paid
                })->sum('amount');
            // update sales volume
            $product->update([
                'sold_count' => $soldCount,
            ]);
        }
    }
}
