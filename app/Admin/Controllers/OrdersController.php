<?php

namespace App\Admin\Controllers;

use App\Exceptions\InternalException;
use App\Exceptions\InvalidRequestException;
use App\Http\Requests\Admin\HandleRefundRequest;
use App\Models\Order;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    use HasResourceActions;

    public function index(Content $content)
    {
        return $content
            ->header('Order List')
            ->body($this->grid());
    }

    public function show(Order $order, Content $content)
    {
        return $content
            ->header('View Order')
            
            ->body(view('admin.orders.show', ['order' => $order]));
    }

    protected function grid()
    {
        $grid = new Grid(new Order);

        // Show paid order and list from recent order 
        $grid->model()->whereNotNull('paid_at')->orderBy('paid_at', 'desc');

        $grid->no('Order Number');
        // 
        $grid->column('user.name', 'Purchaser');
        $grid->total_amount('Total Amount')->sortable();
        $grid->paid_at('Paid Time')->sortable();
        $grid->ship_status('Delivery')->display(function($value) {
            return Order::$shipStatusMap[$value];
        });
        $grid->refund_status('Refund Status')->display(function($value) {
            return Order::$refundStatusMap[$value];
        });
        // Cannot create order from backstage
        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            
            $actions->disableDelete();
            $actions->disableEdit();
        });
        $grid->tools(function ($tools) {
           
            $tools->batch(function ($batch) {
                $batch->disableDelete();
            });
        });

        return $grid;
    }

    public function ship(Order $order, Request $request)
    {
        // if the order is paid
        if (!$order->paid_at) {
            throw new InvalidRequestException('This order has not been paid');
        }
        // if delivery
        if ($order->ship_status !== Order::SHIP_STATUS_PENDING) {
            throw new InvalidRequestException('The order has been shipped');
        }
        // 
        $data = $this->validate($request, [
            'express_company' => ['required'],
            'express_no'      => ['required'],
        ], [], [
            'express_company' => 'Delivery Services by',
            'express_no'      => 'Tracking Number',
        ]);
        // After shipping, change the status and update the delivery information
        $order->update([
            'ship_status' => Order::SHIP_STATUS_DELIVERED,
            
            'ship_data'   => $data,
        ]);

        // back to previous
        return redirect()->back();
    }

    public function handleRefund(Order $order, HandleRefundRequest $request)
    {
        // Order Status
        if ($order->refund_status !== Order::REFUND_STATUS_APPLIED) {
            throw new InvalidRequestException('The order status is incorrect');
        }
        // if agree to refund
        if ($request->input('agree')) {
            // disagree refund
            $extra = $order->extra ?: [];
            unset($extra['refund_disagree_reason']);
            $order->update([
                'extra' => $extra,
            ]);
            // agree refund
            $this->_refundOrder($order);
        } else {
            // show the reason why can not refund in extra
            $extra = $order->extra ?: [];
            $extra['refund_disagree_reason'] = $request->input('reason');
            // refund status change to pending
            $order->update([
                'refund_status' => Order::REFUND_STATUS_PENDING,
                'extra'         => $extra,
            ]);
        }

        return $order;
    }

    protected function _refundOrder(Order $order)
    {
        // payment method
        switch ($order->payment_method) {
            case 'wechat':
                // refund number
                $refundNo = Order::getAvailableRefundNo();
                app('wechat_pay')->refund([
                    'out_trade_no' => $order->no, // Order number
                    'total_fee' => $order->total_amount * 100, //price(cents)
                    'refund_fee' => $order->total_amount * 100, // refund amount
                    'out_refund_no' => $refundNo, // refund number
                    // wechat refund port
                    'notify_url' => route('payment.wechat.refund_notify'),
                ]);
                // refund status change to processing
                $order->update([
                    'refund_no' => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_PROCESSING,
                ]);
                break;
            case 'alipay':
                // 
                $refundNo = Order::getAvailableRefundNo();
                // Alipay refund 
                $ret = app('alipay')->refund([
                    'out_trade_no' => $order->no, // Order number(dollar)
                    'refund_amount' => $order->total_amount, // price
                    'out_request_no' => $refundNo, // refund amount
                ]);
                // if return %sub_code% -> refund failed
                if ($ret->sub_code) {
                    // save to extra 
                    $extra = $order->extra;
                    $extra['refund_failed_code'] = $ret->sub_code;
                    // refund status change to failed
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_FAILED,
                        'extra' => $extra,
                    ]);
                } else {
                    // refund status change to success
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                    ]);
                }
                break;
            default:
                // do not know the payment method
                throw new InternalException('未知订单支付方式：'.$order->payment_method);
                break;
        }
    }
}
