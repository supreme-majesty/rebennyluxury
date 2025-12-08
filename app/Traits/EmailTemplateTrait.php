<?php

namespace App\Traits;

use App\Mail\SendMail;
use App\Models\SocialMedia;
use App\Models\EmailTemplate;
use Exception;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailTemplateService;

trait EmailTemplateTrait
{
    use FileManagerTrait;

    protected function formatProductList($order): string
    {
        if (!$order) {
            return '';
        }

        $orderDetails = is_object($order) ? $order->details : (isset($order['details']) ? $order['details'] : []);

        if (empty($orderDetails)) {
            return '';
        }

        $productList = '<ul style="list-style: none; padding: 0; margin: 10px 0;">';
        $row = 0;

        foreach ($orderDetails as $detail) {
            $productDetails = null;
            if (is_object($detail)) {
                $productDetails = $detail->productAllStatus ?? json_decode($detail->product_details);
                $productName = $productDetails->name ?? '';
                $quantity = $detail->qty ?? 0;
                $price = $detail->price ?? 0;
            } else {
                $productDetails = isset($detail['productAllStatus']) ? $detail['productAllStatus'] : json_decode($detail['product_details'] ?? '{}');
                $productName = is_object($productDetails) ? ($productDetails->name ?? '') : ($detail['product_name'] ?? '');
                $quantity = $detail['qty'] ?? 0;
                $price = $detail['price'] ?? 0;
            }

            if ($productName) {
                $row++;
                $totalPrice = $price * $quantity;
                $productList .= '<li style="margin-bottom: 8px; padding: 8px; background-color: #f8f9fa; border-left: 3px solid #007bff;">';
                $productList .= '<strong>' . $row . '. ' . htmlspecialchars($productName) . '</strong>';
                $productList .= '<br><span style="color: #666; font-size: 14px;">';
                $productList .= 'Quantity: ' . $quantity . ' × ' . webCurrencyConverter(amount: $price);
                $productList .= ' = ' . webCurrencyConverter(amount: $totalPrice);
                $productList .= '</span></li>';
            }
        }

        $productList .= '</ul>';
        return $productList;
    }

    protected function calculateTotalQuantity($order): int
    {
        if (!$order) {
            return 0;
        }

        $orderDetails = is_object($order) ? $order->details : (isset($order['details']) ? $order['details'] : []);

        if (empty($orderDetails)) {
            return 0;
        }

        $totalQty = 0;
        foreach ($orderDetails as $detail) {
            if (is_object($detail)) {
                $totalQty += $detail->qty ?? 0;
            } else {
                $totalQty += $detail['qty'] ?? 0;
            }
        }

        return $totalQty;
    }

    protected function formatPaymentStatus($paymentStatus): string
    {
        if (!$paymentStatus) {
            return '';
        }

        // Translate common payment status values
        $status = strtolower($paymentStatus);
        if ($status == 'paid') {
            return translate('paid');
        } elseif ($status == 'unpaid') {
            return translate('unpaid');
        } else {
            // For any other status, try to translate it or return as-is
            return translate($status) != $status ? translate($status) : ucfirst($status);
        }
    }

    protected function formatOrderStatus($orderStatus): string
    {
        if (!$orderStatus) {
            return '';
        }

        // Translate and format order status values
        $status = strtolower($orderStatus);
        
        // Handle special cases
        if ($status == 'processing') {
            return translate('packaging');
        } elseif ($status == 'failed') {
            return translate('failed_to_deliver');
        } else {
            // Replace underscores with spaces and translate
            $formattedStatus = str_replace('_', ' ', $status);
            $translated = translate($formattedStatus);
            
            // If translation exists, use it; otherwise capitalize
            return $translated != $formattedStatus ? $translated : ucfirst($formattedStatus);
        }
    }

    protected function textVariableFormat(
        $value, $userName = null, $adminName = null, $vendorName = null, $shopName = null, $shopId = null,
        $deliveryManName = null, $orderId = null, $emailId = null, $transactionId = null, $time = null, $productList = null, $totalQty = null, $paymentStatus = null, $customerMessage = null, $orderStatus = null, $deliveryManMessage = null)
    {
        $data = $value;
        if ($data) {
            $data = $userName ? str_replace("{userName}", $userName, $data) : $data;
            $data = $vendorName ? str_replace("{vendorName}", $vendorName, $data) : $data;
            $data = $adminName ? str_replace("{adminName}", $adminName, $data) : $data;
            $data = $shopName ? str_replace("{shopName}", $shopName, $data) : $data;
            $data = $shopName ? str_replace("{shopId}", $shopId, $data) : $data;
            $data = $deliveryManName ? str_replace("{deliveryManName}", $deliveryManName, $data) : $data;
            $data = $orderId ? str_replace("{orderId}", $orderId, $data) : $data;
            $data = $emailId ? str_replace("{emailId}", $emailId, $data) : $data;
            $data = $transactionId ? str_replace("{transactionId}", $transactionId, $data) : $data;
            $data = $time ? str_replace("{time}", $time, $data) : $data;
            $data = $productList ? str_replace("{productList}", $productList, $data) : $data;
            $data = $totalQty !== null ? str_replace("{totalQty}", $totalQty, $data) : $data;
            $data = $totalQty !== null ? str_replace("{qty}", $totalQty, $data) : $data;
            $data = $paymentStatus ? str_replace("{paymentStatus}", $paymentStatus, $data) : $data;
            $data = $customerMessage ? str_replace("{customerMessage}", htmlspecialchars($customerMessage), $data) : $data;
            $data = $orderStatus ? str_replace("{orderStatus}", $orderStatus, $data) : $data;
            $data = $deliveryManMessage ? str_replace("{deliveryManMessage}", htmlspecialchars($deliveryManMessage), $data) : $data;
        }
        return $data;
    }

    protected function sendingMail($sendMailTo, $userType, $templateName, $data = null): void
    {
        $template = EmailTemplate::with('translationCurrentLanguage')->where(['user_type' => $userType, 'template_name' => $templateName])->first();
        if ($template) {
            if (count($template['translationCurrentLanguage'])) {
                foreach ($template?->translationCurrentLanguage ?? [] as $translate) {
                    $template['title'] = $translate->key == 'title' ? $translate->value : $template['title'];
                    $template['body'] = $translate->key == 'body' ? $translate->value : $template['body'];
                    $template['footer_text'] = $translate->key == 'copyright_text' ? $translate->value : $template['footer_text'];
                    $template['copyright_text'] = $translate->key == 'footer_text' ? $translate->value : $template['copyright_text'];
                    $template['button_name'] = $translate->key == 'button_name' ? $translate->value : $template['button_name'];
                }
            }
            $socialMedia = SocialMedia::where(['status' => 1])->get();

            // Extract transactionId from various sources
            $transactionId = $data['transactionId'] ?? null;
            if (!$transactionId && isset($data['walletTransaction']) && is_object($data['walletTransaction'])) {
                $transactionId = $data['walletTransaction']->transaction_id ?? null;
            }
            if (!$transactionId && isset($data['order']) && is_object($data['order'])) {
                $transactionId = $data['order']->transaction_ref ?? null;
            }
            if (!$transactionId && isset($data['order']) && is_array($data['order'])) {
                $transactionId = $data['order']['transaction_ref'] ?? null;
            }

            // Extract time from various sources
            $time = $data['time'] ?? null;
            if (!$time && isset($data['order']) && is_object($data['order'])) {
                $time = $data['order']->expected_delivery_date ?? null;
            }
            if (!$time && isset($data['order']) && is_array($data['order'])) {
                $time = $data['order']['expected_delivery_date'] ?? null;
            }

            // Extract and format product list from order
            $productList = null;
            $totalQty = null;
            $paymentStatus = null;
            $customerMessage = null;
            $orderStatus = null;
            $deliveryManMessage = null;
            if (isset($data['order'])) {
                $productList = $this->formatProductList($data['order']);
                $totalQty = $this->calculateTotalQuantity($data['order']);

                // Extract order data
                if (is_object($data['order'])) {
                    $paymentStatusRaw = $data['order']->payment_status ?? null;
                    $customerMessage = $data['order']->order_note ?? null;
                    $orderStatusRaw = $data['order']->order_status ?? null;
                    $deliveryManMessage = $data['order']->cause ?? null;
                } else {
                    $paymentStatusRaw = $data['order']['payment_status'] ?? null;
                    $customerMessage = $data['order']['order_note'] ?? null;
                    $orderStatusRaw = $data['order']['order_status'] ?? null;
                    $deliveryManMessage = $data['order']['cause'] ?? null;
                }
                if ($paymentStatusRaw) {
                    $paymentStatus = $this->formatPaymentStatus($paymentStatusRaw);
                }
                if ($orderStatusRaw) {
                    $orderStatus = $this->formatOrderStatus($orderStatusRaw);
                }
            }

            $template['body'] = $this->textVariableFormat(
                value: $template['body'],
                userName: $data['userName'] ?? null,
                adminName: $data['adminName'] ?? null,
                vendorName: $data['vendorName'] ?? null,
                shopName: $data['shopName'] ?? null,
                shopId: $data['shopId'] ?? null,
                deliveryManName: $data['deliveryManName'] ?? null,
                orderId: $data['orderId'] ?? null,
                emailId: $data['emailId'] ?? null,
                transactionId: $transactionId,
                time: $time,
                productList: $productList,
                totalQty: $totalQty,
                paymentStatus: $paymentStatus,
                customerMessage: $customerMessage,
                orderStatus: $orderStatus,
                deliveryManMessage: $deliveryManMessage
            );
            $template['title'] = $this->textVariableFormat(
                value: $template['title'],
                userName: $data['userName'] ?? null,
                adminName: $data['adminName'] ?? null,
                vendorName: $data['vendorName'] ?? null,
                shopName: $data['shopName'] ?? null,
                shopId: $data['shopId'] ?? null,
                deliveryManName: $data['deliveryManName'] ?? null,
                orderId: $data['orderId'] ?? null,
                emailId: $data['emailId'] ?? null,
                transactionId: $transactionId,
                time: $time
            );
            $data['send-mail'] = true;
            if ($template['status'] == 1) {
                try {
                    Mail::to($sendMailTo)->send(new SendMail($data, $template, $socialMedia));
                } catch (Exception $exception) {
                }
            }
            if (isset($data['attachmentPath'])) {
                unlink($data['attachmentPath']);
            }
        }
    }

    public function getEmailTemplateDataForUpdate($userType): void
    {
        $emailTemplates = EmailTemplate::where(['user_type' => $userType])->get();
        $emailTemplateArray = (new EmailTemplateService)->getEmailTemplateData(userType: $userType);
        foreach ($emailTemplateArray as $value) {
            $checkKey = $emailTemplates->where('template_name', $value)->first();
            if ($checkKey === null) {
                $hideField = (new EmailTemplateService)->getHiddenField(userType: $userType, templateName: $value);
                $title = (new EmailTemplateService)->getTitleData(userType: $userType, templateName: $value);
                $body = (new EmailTemplateService)->getBodyData(userType: $userType, templateName: $value);
                $addData = (new EmailTemplateService)->getAddData(userType: $userType, templateName: $value, hideField: $hideField, title: $title, body: $body);
                EmailTemplate::create($addData);
            }
        }
        foreach ($emailTemplates as $value) {
            if (!in_array($value['template_name'], $emailTemplateArray)) {
                EmailTemplate::find($value['id'])->delete();
            }
        }
    }
}
