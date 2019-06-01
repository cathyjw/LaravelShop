<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Order;

// 
class CloseOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    public function __construct(Order $order, $delay)
    {
        $this->order = $order;
        // delay() execution delay ..seconds
        $this->delay($delay);
    }

    // 
    // 
    public function handle()
    {
        // for paid order, exit automaticlly
        if ($this->order->paid_at) {
            return;
        }
        //  sql
        \DB::transaction(function() {
            // closed -> true，close ordersku
            $this->order->update(['closed' => true]);
            // add the quantity into SKU stock
            foreach ($this->order->items as $item) {
                $item->productSku->addStock($item->amount);
            }
            if ($this->order->couponCode) {
                $this->order->couponCode->changeUsed(false);
            }
        });
    }
}
