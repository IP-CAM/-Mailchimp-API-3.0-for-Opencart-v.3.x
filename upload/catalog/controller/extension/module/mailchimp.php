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
}