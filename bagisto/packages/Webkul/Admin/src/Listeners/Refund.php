<?php

namespace Webkul\Admin\Listeners;

use Webkul\Admin\Mail\Order\RefundedNotification;
use Webkul\Paypal\Payment\SmartButton;
use Illuminate\Support\Facades\Log;

class Refund extends Base
{
    /**
     * After order is created
     *
     * @param  \Webkul\Sales\Contracts\Refund  $refund
     * @return void
     */
    public function afterCreated($refund)
    {
        $this->refundOrder($refund);

        Log::info('Refund Order: ' . $refund->id);

        try {
            if (! core()->getConfigData('emails.general.notifications.emails.general.notifications.new_refund')) {
                return;
            }

            $this->prepareMail($refund, new RefundedNotification($refund));
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * After Refund is created
     *
     * @param  \Webkul\Sales\Contracts\Refund  $refund
     * @return void
     */
    public function refundOrder($refund)
    {
        $order = $refund->order;

        if ($order->payment->method === 'paypal_smart_button') {
            /* getting smart button instance */
            $smartButton = new SmartButton;

            /* getting paypal order id */
            $paypalOrderID = $order->payment->additional['id'];

            if ($paypalOrderID === null) {
                Log::error('PayPal Order ID is null 123', ['order_id' => $order->id]);
                return;
            }

            Log::info('PayPal Order ID:', ['order_id' => $paypalOrderID]);

            /* getting capture id by paypal order id */
            $captureID = $smartButton->getCaptureId($paypalOrderID);

            Log::info('PayPal Capture ID:', ['capture_id' => $captureID]);

            /* now refunding order on the basis of capture id and refund data */
            $smartButton->refundOrder($captureID, [
                'amount' => [
                    'value'         => round($refund->grand_total, 2),
                    'currency_code' => $refund->order_currency_code,
                ],
            ]);

            Log::info('Refund operation completed for capture ID:', ['capture_id' => $captureID]);
        }
    }
}
