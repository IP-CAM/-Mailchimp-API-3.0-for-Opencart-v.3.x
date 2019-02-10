<?php


class ControllerExtensionModuleMailchimp extends Controller {
    
    public function customerTrigger(&$route, &$data, &$output) 
	{
        $this->load->model('setting/setting');

        $this->load->model('extension/module/mailchimp');
        
        $apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');

        if ($apiKey) {

            $customer = $data[0];
            $customerId = $output;
            $newCustomer = true;

            if (is_null($customerId)) {
                $customerId = $this->customer->getId();
                $customer = $data[1];
                $customer['newsletter'] = $this->customer->getNewsletter();
                $newCustomer = false;
            }

            $customerData = [
                'id' => (string)$customerId,
                'email_address' => trim(strtolower($customer['email'])),
                'opt_in_status' => $customer['newsletter'] == '1' ? true : false,
                'first_name' => ucwords(strtolower($customer['firstname'])),
                'last_name' => ucwords(strtolower($customer['lastname'])), 
                'orders_count' => $this->model_extension_module_mailchimp->getOrdersCountByCustomer($customerId), 
                'total_spent' => $this->model_extension_module_mailchimp->getTotalSpentByCustomer($customerId),
            ];

            $mailchimp = new Mailchimp($apiKey);
            $synchronizedCustomer = $mailchimp->syncCustomer('default', $customerData);
        }    
    }

    public function cartTrigger(&$route, &$data) 
	{
        $this->load->model('setting/setting');  
        
        $apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');

        $sessionId = $this->session->getId();
        $customerId = $this->customer->getId();

        // If is editing cart
        if ($route == 'checkout/cart/edit' && !empty($this->request->post['quantity'])) {
            foreach ($this->request->post['quantity'] as $key => $value) {
                $this->cart->update($key, $value);
            }
        }

        if ($apiKey && $customerId > 0) {

            $mailchimp = new Mailchimp($apiKey);
            
            if ($this->cart->countProducts() > 0) {
                $cartData = [
					'id' => $sessionId,
					'customer' => [
						'id' => $customerId
					],
					'currency_code' => $this->config->get('config_currency'),
					'order_total' => $this->cart->getTotal(),
					'lines' => []
                ];
                
                $cartProducts = $this->cart->getProducts();
				
				foreach ($cartProducts AS $product) {
					$cartData['lines'][] = [
						'id' => $product['cart_id'],
						'product_id' => $product['product_id'],
						'product_variant_id' => $product['product_id'],
						'quantity' => (int)$product['quantity'],
						'price' => $product['price']
					];
                }
                
                $cart = $mailchimp->syncCart('default', $cartData);
            }

            if ($this->cart->countProducts() == 0) {
                $mailchimp->deleteCart('default', $sessionId);
            }
        }    
    }

    public function clearCartTrigger(&$route, &$data) 
	{
        $this->load->model('setting/setting');  
        
        $apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');

        $sessionId = $this->session->getId();
        
        if ($apiKey && $sessionId > 0) {
            $mailchimp = new Mailchimp($apiKey);
            $mailchimp->deleteCart('default', $sessionId);
        }    
    }

    public function orderTrigger(&$route, &$data, &$output)
	{
        $this->load->model('setting/setting');

        $this->load->model('checkout/order');

		$this->load->model('localisation/order_status');
        
        $apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');
        
        if ($apiKey) {

            $orderId = $data[0];

            $paidStatuses = [
                17, 19, 18, 3, 5
            ];
    
            $pendingStatuses = [
                1, 9, 2, 15,
            ];
    
            $refundedStatuses = [
                11, 12
            ];
    
            $cancelledStatuses = [
                16, 7, 13, 8, 10, 14, 
            ];
    
            $shippedStatuses = [
                19, 18, 3, 5
            ];
           
            $orderDetails = $this->model_checkout_order->getOrder($orderId);
            $orderTotals = $this->model_checkout_order->getOrderTotals($orderId);
            $orderProducts = $this->model_checkout_order->getOrderProducts($orderId);

            // Searching For Shipping Price
            $shippingValue = 0;
            $discountValue = 0;
            foreach ($orderTotals AS $total) {
                if ($total['code'] == 'shipping') {
                    $shippingValue += $total['value'];
                }

                if ($total['code'] == 'coupon') {
                    $discountValue += abs($total['value']);
                }
            }
            
            $financialStatus = null;
            $shippedStatus = null;

            if (in_array($orderDetails['order_status_id'], $paidStatuses)) {
                $financialStatus = 'paid';
            }

            if (in_array($orderDetails['order_status_id'], $pendingStatuses)) {
                $financialStatus = 'pending';
            }

            if (in_array($orderDetails['order_status_id'], $refundedStatuses)) {
                $financialStatus = 'refunded';
            }

            if (in_array($orderDetails['order_status_id'], $cancelledStatuses)) {
                $financialStatus = 'cancelled';
            }

            if (in_array($orderDetails['order_status_id'], $shippedStatuses)) {
                $shippedStatus = 'shipped';
            }

            $orderData = [
                'id' => (string)$orderId,
                'customer' => [
                    'id' => (string)$orderDetails['customer_id']
                ],
                'currency_code' => $orderDetails['currency_code'],
                'order_total' => $orderDetails['total'],
                'discount_total' => (float)$discountValue,
                'shipping_total' => (float)$shippingValue,
                'updated_at_foreign' => $orderDetails['date_modified'],
                'lines' => [],
                'landing_site' => HTTPS_CATALOG
            ];

            if (!is_null($financialStatus)) {
                $orderData['financial_status'] =  $financialStatus;
            }

            if (!is_null($shippedStatus)) {
                $orderData['fulfillment_status'] =  $shippedStatus;
            }

            foreach($orderProducts AS $orderedProduct) {
                $orderData['lines'][] = [
                    'id' => (string)$orderedProduct['order_product_id'],
                    'product_id' => (string)$orderedProduct['product_id'],
                    'product_variant_id' => (string)$orderedProduct['product_id'],
                    'quantity' => (int)$orderedProduct['quantity'],
                    'price' => (float)$orderedProduct['total']
                ];
            }

            $mailchimp = new Mailchimp($apiKey);
            $orderReturned = $mailchimp->syncOrder('default', $orderData);
        }      
    }
}