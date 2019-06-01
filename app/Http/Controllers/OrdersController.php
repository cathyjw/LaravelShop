<?php

namespace App\Http\Controllers;

use App\Events\OrderReviewed;
use App\Exceptions\CouponCodeUnavailableException;
use App\Exceptions\InvalidRequestException;
use App\Http\Requests\ApplyRefundRequest;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\SendReviewRequest;
use App\Models\CouponCode;
use App\Models\UserAddress;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\OrderService;

class OrdersController extends Controller
{
    public function store(OrderRequest $request, OrderService $orderService)
    {
        $user    = $request->user();
        $address = UserAddress::find($request->input('address_id'));
        $coupon  = null;

        // submit coupon
        if ($code = $request->input('coupon_code')) {
            $coupon = CouponCode::where('code', $code)->first();
            if (!$coupon) {
                throw new CouponCodeUnavailableException('Coupon does not exist');
            }
        }
        //  $coupon 
        return $orderService->store($user, $address, $request->input('remark'), $request->input('items'), $coupon);
    }

    public function index(Request $request)
    {
        $orders = Order::query()
            //  with method to preloading
            ->with(['items.product', 'items.productSku'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate();

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        return view('orders.show', ['order' => $order->load(['items.productSku', 'items.product'])]);
    }

    public function received(Order $order, Request $request)
    {
        // if operate by owner
        $this->authorize('own', $order);

        // if delivery
        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            throw new InvalidRequestException('Incorrect shipment status');
        }

        // Update ship to received
        $order->update(['ship_status' => Order::SHIP_STATUS_RECEIVED]);

        // return order
        return $order;
    }

    public function review(Order $order)
    {
        // if operate by owner
        $this->authorize('own', $order);
        // Determine whether the payment has been made
        if (!$order->paid_at) {
            throw new InvalidRequestException('Not paid, can not write review');
        }
        // Load method to preloading
        return view('orders.review', ['order' => $order->load(['items.productSku', 'items.product'])]);
    }

    public function sendReview(Order $order, SendReviewRequest $request)
    {
        // if operate by owner
        $this->authorize('own', $order);
        if (!$order->paid_at) {
            throw new InvalidRequestException('Not paid, can not write review');
        }
        // 判断是否已经评价
        if ($order->reviewed) {
            throw new InvalidRequestException('Review alread exist and cannot be resubmitted');
        }
        $reviews = $request->input('reviews');
        // 
        \DB::transaction(function () use ($reviews, $order) {
            // 
            foreach ($reviews as $review) {
                $orderItem = $order->items()->find($review['id']);
                // save review and rating
                $orderItem->update([
                    'rating'      => $review['rating'],
                    'review'      => $review['review'],
                    'reviewed_at' => Carbon::now(),
                ]);
            }
            // the order is reviewed
            $order->update(['reviewed' => true]);
            event(new OrderReviewed($order));
        });

        return redirect()->back();
    }

    public function applyRefund(Order $order, ApplyRefundRequest $request)
    {
        // if right owner for the order
        $this->authorize('own', $order);
        // paid or not
        if (!$order->paid_at) {
            throw new InvalidRequestException('This order is non-refundable and unpaid');
        }
        // check refund status
        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('A refund has been applied for this order. Please do not repeat the application');
        }
        // refund reason in extra
        $extra                  = $order->extra ?: [];
        $extra['refund_reason'] = $request->input('reason');
        // updata refund status
        $order->update([
            'refund_status' => Order::REFUND_STATUS_APPLIED,
            'extra'         => $extra,
        ]);

        return $order;
    }
}
