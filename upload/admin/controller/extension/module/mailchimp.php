<?php


class ControllerExtensionModuleMailchimp extends Controller {

	private $errorMessage = '';
	private $successMessage = '';
	private $warningMessage = '';

	public function index() {

		$this->load->language('extension/module/mailchimp');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->getList();
	}

	public function syncProducts()
	{
		$this->load->language('extension/module/mailchimp');

		$this->load->model('catalog/product');
		
		$this->load->model('catalog/manufacturer');
		
		$this->load->model('setting/setting');
		
		$this->load->model('tool/image');

		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
		}

		$products = $this->model_catalog_product->getProducts();

		$apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');
		$mailchimp = new Mailchimp($apiKey);

		$totalProducts = count($products);
		$synchronizedProducts = 0;
		foreach($products AS $product) {
			$manufacturer = $this->model_catalog_manufacturer->getManufacturer($product['manufacturer_id']);

			$image = empty($product['image']) ? 'no_image.png' : $product['image'];
			$imageUrl = $this->model_tool_image->resize($image, 600, 695);

			// manufacturer_id
			$productData = [
				'id'    => $product['product_id'],
				'title' => $product['name'],
				'url' => $this->url->link('product/product', 'product_id=' . $product['product_id']),
				'description' => $product['description'],
				'image_url' => $imageUrl,
				'variants' => [
					[
						'id'    => $product['product_id'],
						'title' => $product['name'],
						'url'   => $this->url->link('product/product', 'product_id=' . $product['product_id']),
						'sku'	=> $product['sku'],
						'price' => (float)$product['price'],
						'inventory_quantity' => (int)$product['quantity'],
						'image_url' => $imageUrl,
					]
				]	
			];


			if (isset($manufacturer)) {
				$productData['vendor'] = (string)$manufacturer['name'];
			}

			$returnedProduct = $mailchimp->syncProduct('default', $productData);
			if (!isset($returnedProduct->status)) {
				$synchronizedProducts++;
			}
		}

		if ($synchronizedProducts == 0 && $totalProducts > 0) {
			$this->setErrorMessage($this->language->get('text_error_sync_products'));
		}

		if ($synchronizedProducts != $totalProducts) {
			$warningMessage = $this->language->get('text_warning_sync_products');
			$this->setWarningMessage(sprintf($warningMessage, ($totalProducts - $synchronizedProducts), $totalProducts));
		}

		if ($synchronizedProducts == $totalProducts) {
			$this->setSuccessMessage($this->language->get('text_success_sync_products'));
		}

		$this->getList();
	}

	public function syncOrders()
	{
		$this->load->language('extension/module/mailchimp');
		
		$this->load->model('sale/order');

		$this->load->model('localisation/order_status');

		$this->load->model('setting/setting');

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

		

		$orders = $this->model_sale_order->getOrders();
		$totalOrders = count($orders);
		$synchronizedOrders = 0;
		if ($totalOrders > 0) {
			foreach ($orders AS $order) {
				$orderDetails = $this->model_sale_order->getOrder($order['order_id']);

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

				
				$orderProducts = $this->model_sale_order->getOrderProducts($order['order_id']);
				
				$orderData = [
					'id' => $order['order_id'],
					'customer' => [
						'id' => $orderDetails['customer_id']
					],
					'currency_code' => $orderDetails['currency_code'],
					'order_total' => $orderDetails['total'],
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
						'id' => $orderedProduct['order_product_id'],
						'product_id' => $orderedProduct['product_id'],
						'product_variant_id' => $orderedProduct['product_id'],
						'quantity' => (int)$orderedProduct['quantity'],
						'price' => (float)$orderedProduct['total']
					];
				}

				$apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');
				$mailchimp = new Mailchimp($apiKey);
				$orderReturned = $mailchimp->syncOrder('default', $orderData);

				if (!isset($orderReturned->status)) {
					$synchronizedOrders++;
				}
			}
		}
		
		if ($synchronizedOrders == 0 && $totalOrders > 0) {
			$this->setErrorMessage($this->language->get('text_error_sync_orders'));
		}

		if ($synchronizedOrders != $totalOrders) {
			$warningMessage = $this->language->get('text_warning_sync_orders');
			$this->setWarningMessage(sprintf($warningMessage, ($totalOrders - $synchronizedOrders), $totalOrders));
		}

		if ($synchronizedOrders == $totalOrders) {
			$this->setSuccessMessage($this->language->get('text_success_sync_orders'));
		}

		$this->getList();
	}

	public function syncCarts()
	{
		$this->load->language('extension/module/mailchimp');

		$this->load->model('extension/module/mailchimp');

		$this->load->model('setting/setting');

		$carts = $this->model_extension_module_mailchimp->getCarts();
		
		$apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');
		$mailchimp = new Mailchimp($apiKey);
		
		$totalCarts = count($carts);
		$synchronizedCarts = 0;
		if ($totalCarts > 0) {
			foreach ($carts AS $cart) {
				$cartData = [
					'id' => $cart['session_id'],
					'customer' => [
						'id' => $cart['customer_id']
					],
					'currency_code' => $this->config->get('config_currency'),
					'order_total' => $cart['total'],
					'lines' => []
				];

				$cartProducts = $this->model_extension_module_mailchimp->getCartProducts($cart['session_id']);
				
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
				if (!$cart->status) {
					$synchronizedCarts++;
				}
			}
		}

		if ($synchronizedCarts == 0 && $totalCarts > 0) {
			$this->setErrorMessage($this->language->get('text_error_sync_carts'));
		}

		if ($synchronizedCarts != $totalCarts) {
			$warningMessage = $this->language->get('text_warning_sync_carts');
			$this->setWarningMessage(sprintf($warningMessage, ($totalCarts - $synchronizedCarts), $totalCarts));
		}

		if ($synchronizedCarts == $totalCarts) {
			$this->setSuccessMessage($this->language->get('text_success_sync_carts'));
		}

		$this->getList(); 
	}



	public function syncLists()
	{
		$this->load->language('extension/module/mailchimp');
		
		$this->load->model('setting/setting');

		$this->load->model('localisation/country');

		$this->load->model('localisation/currency');

		$this->load->model('localisation/zone');

		$this->load->model('customer/customer');

		// Verifing if default list already exists
		$defaultListId = $this->model_setting_setting->getSettingValue('module_mailchimp_default_list_id');

		$zone = $this->model_localisation_zone->getZone($this->config->get('config_zone_id'));

		$country = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));

		$defaultList = [
			'name' => 'Default List For ' . $this->config->get('config_name'),
			'contact' => [
				'company' => $this->config->get('config_name'),
				'address1' => $this->config->get('config_address'),
				'city' => 'Mogi das Cruzes',
				'state' => $zone['name'],
				'zip' => '08773535',
				'country' => $country['iso_code_2'],
			],
			'permission_reminder' => $this->language->get('text_permission_reminder') . ' - ' . $this->config->get('config_name'),
			'campaign_defaults' => [
				'from_name' => $this->config->get('config_name'),
				'from_email' => $this->config->get('config_email'),
				'subject' => $this->language->get('text_default_subject'),
				'language' => $this->config->get('config_language'),
			],
			'email_type_option' => false
		];

		$apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');
		$mailchimp = new Mailchimp($apiKey);
		$list = $mailchimp->syncList($defaultList, $defaultListId);

		if (!isset($list->id)) {
			$this->setErrorMessage($this->language->get('text_error_sync_list'));
		}
		
		if (isset($list->id)) {
			$this->model_setting_setting->editSetting('module_mailchimp_default_list_id', ['module_mailchimp_default_list_id' => $list->id]);

			// Creating list members
			$members = [];
			$clients = $this->model_customer_customer->getCustomers();
			$totalClients = count($clients);
			$synchronizedClients = 0;
			foreach ($clients AS $client) {
				$members[] = [
					'email_address' => trim(strtolower($client['email'])),
					'email_type' => 'html',
					'name' => ucwords(strtolower(str_replace('  ', ' ', $client['name']))),
					'language' => $this->config->get('config_language'),
					'ip_signup' => $client['ip'],
					'timestamp_signup' => $client['date_added'],
					'ip_opt' => $client['ip'],
					'timestamp_opt' => $client['date_added'],
					'status' => 'subscribed',
					'merge_fields' => [
						'FNAME' => ucwords(strtolower(trim($client['firstname']))),
						'LNAME' => ucwords(strtolower(trim($client['lastname']))),
						'PHONE' => $client['telephone'],
					]
				];
			}

			$listMembers = $mailchimp->syncListMembers($list->id, [
				'members' => $members,
				'update_existing' => true
			]);

			$synchronizedClients += count($listMembers->new_members);
			$synchronizedClients += count($listMembers->updated_members);

			if ($synchronizedClients == 0 && $totalClients > 0) {
				$this->setErrorMessage($this->language->get('text_error_sync_customers'));
			}

			if ($synchronizedClients > 0 && $totalClients != $synchronizedClients) {
				$warningMessage = $this->language->get('text_warning_sync_customers');
				$this->setWarningMessage(sprintf($warningMessage, $totalClients, $synchronizedClients));
			}

			if ($synchronizedClients == $totalClients) {
				$this->setSuccessMessage($this->language->get('text_success_sync_customers'));
			}
		}

		$this->getList();
	}

	public function syncCustomers()
	{
		$this->load->language('extension/module/mailchimp');
		
		$this->load->model('setting/setting');

		$this->load->model('localisation/country');

		$this->load->model('localisation/currency');

		$this->load->model('localisation/zone');

		$this->load->model('customer/customer');

		$this->load->model('extension/module/mailchimp');

		$customers = $this->model_customer_customer->getCustomers();
		$totalCustomers = count($customers);
		$synchronizedCustomers = 0;
		foreach ($customers AS $customer) {

			$address = $this->model_customer_customer->getAddresses($customer['customer_id']);
			

			$customerData = [
				'id' => $customer['customer_id'],
				'email_address' => trim(strtolower($customer['email'])),
				'opt_in_status' => (bool)$customer['newsletter'],
				'first_name' => ucwords(strtolower($customer['firstname'])),
				'last_name' => ucwords(strtolower($customer['lastname'])), 
				'orders_count' => $this->model_extension_module_mailchimp->getOrdersCountByCustomer($customer['customer_id']), 
				'total_spent' => $this->model_extension_module_mailchimp->getTotalSpentByCustomer($customer['customer_id']),
			];

			if (count($address)) {
				$address = current($address);
				$customerData['address'] = [
					'address1' => $address['address_1'],
					'address2' => $address['address_2'],
					'city'     => $address['city'],
					'province' => $address['zone'],
					'province_code' => $address['zone_code'],
					'postal_code' => $address['postcode'],
					'country' => $address['country'],
					'country_code' => $address['iso_code_3']
				];
			}

			$apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');
			$mailchimp = new Mailchimp($apiKey);
			$customer = $mailchimp->syncCustomer('default', $customerData);
			if(!isset($customer->status)) {
				$synchronizedCustomers++;
			}
		}

		if ($synchronizedCustomers == 0 && $totalCustomers > 0) {
			$this->setErrorMessage($this->language->get('text_error_sync_customers'));
		}

		if ($synchronizedCustomers != $totalCustomers) {
			$warningMessage = $this->language->get('text_warning_sync_customers');
			$this->setWarningMessage(sprintf($warningMessage, ($totalCustomers - $synchronizedCustomers), $totalCustomers));
		}

		if ($synchronizedCustomers == $totalCustomers) {
			$this->setSuccessMessage($this->language->get('text_success_sync_customers'));
		}

		$this->getList();
	}

	public function eventTest(&$route, &$data) 
	{
		var_dump($route);
	}
	
	public function syncStore()
	{
		$this->load->language('extension/module/mailchimp');

		$this->load->model('setting/setting');

		$this->load->model('localisation/country');

		$this->load->model('localisation/currency');
		
		$country = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));

		$currency = $this->model_localisation_currency->getCurrencyByCode($this->config->get('config_currency'));

		// Verifing if default list already exists
		$defaultListId = $this->model_setting_setting->getSettingValue('module_mailchimp_default_list_id');
		if (is_null($defaultListId)) {
			$this->syncLists();
			$defaultListId = $this->model_setting_setting->getSettingValue('module_mailchimp_default_list_id');
		}
		
		$defaultStoreData = [
			'id' => 'default',
			'list_id' => $defaultListId,
			'name'     => $this->config->get('config_name'),
			'platform'  => 'OpenCart',
			'domain'      => $this->config->get('config_secure') ? HTTPS_CATALOG : HTTP_CATALOG,
			'email_address'	   => $this->config->get('config_email'),
			'phone' => $this->config->get('config_telephone'),
			'timezone' => ini_get('date.timezone'),
		];

		if (isset($country['iso_code_2'])) {
			$defaultStoreData['primary_locale'] = $country['iso_code_2'];
		}

		if (isset($currency['code'])) {
			$defaultStoreData['currency_code'] = trim($currency['code']);
			$defaultStoreData['money_format'] = trim($currency['symbol_left']);
		}

		$apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');
		$mailchimp = new Mailchimp($apiKey);
		$store = $mailchimp->syncStore($defaultStoreData);

		if (isset($store->status)) {
			$this->setErrorMessage($this->language->get('text_error_sync_store'));
		}

		if (!isset($store->status)) {
			$this->setSuccessMessage($this->language->get('text_success_sync_store'));
		}

		$this->getList();
	}

	public function rewrite($link) 
	{
		$url_info = parse_url(str_replace('&amp;', '&', $link));
		
		$url = '';

		$data = [];

		parse_str($url_info['query'], $data);

		foreach ($data as $key => $value) {
			if (isset($data['route'])) {
				if (($data['route'] == 'product/product' && $key == 'product_id')) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($key . '=' . (int)$value) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

					if ($query->num_rows && $query->row['keyword']) {
						$url .= '/' . $query->row['keyword'];

						unset($data[$key]);
					}
				} 
			}
		}

		if ($url) {
			unset($data['route']);

			$query = '';

			if ($data) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
				}

				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			if ($this->request->server['HTTPS']) {
				return trim(HTTPS_CATALOG, '/') . str_replace('//', '/', $url . $query);
			} else {
				return trim(HTTP_CATALOG, '/') . str_replace('//', '/', $url . $query);
			}
		} else {
			return $link;
		}
	}

	protected function getList() 
	{
		$this->load->language('extension/module/mailchimp');

		$this->load->model('setting/setting');

		$this->load->model('extension/module/mailchimp');

		$this->load->model('customer/customer');

		$this->load->model('sale/order');

		$data['module_mailchimp_status'] = $this->model_setting_setting->getSettingValue('module_mailchimp_status');
		$data['module_mailchimp_api_key'] = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$data['module_mailchimp_status'] = $this->request->post['module_mailchimp_status'];
			$data['module_mailchimp_api_key'] = $this->request->post['module_mailchimp_api_key'];
			$this->model_setting_setting->editSetting('module_mailchimp_status', $this->request->post);
			$this->model_setting_setting->editSetting('module_mailchimp_api_key', $this->request->post);
		}

		
		$apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');
		$mailchimp = new Mailchimp($apiKey);
		
		$data['totalProducts'] = $this->model_extension_module_mailchimp->getTotalProducts();
		$data['totalCustomers'] = $this->model_customer_customer->getTotalCustomers();
		$data['totalOrders'] = $this->model_sale_order->getTotalOrders();
		$data['totalCarts'] = $this->model_extension_module_mailchimp->getTotalCarts();
		$data['totalListContacts'] = $data['totalCustomers'];
		
		$data['sycronizedProducts'] = $mailchimp->countSynchronizedProducts('default');
		$data['syncronizedStores'] = $mailchimp->countSynchronizedStores();
		$data['syncronizedCustomers'] = $mailchimp->countSynchronizedCustomers('default');
		$data['syncronizedOrders'] = $mailchimp->countSynchronizedOrders('default');
		$data['syncronizedCarts'] = $mailchimp->countSynchronizedCarts('default');
		$data['syncronizedLists'] =  0;
		
		$listId = $this->model_setting_setting->getSettingValue('module_mailchimp_default_list_id');
		if ($listId)
		{
			$listContacts = $mailchimp->countListContacts($listId);
			if($listContacts) {
				$data['syncronizedLists'] = 1;
				$data['syncronizedListsContacts'] = $listContacts;
			}
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/mailchimp', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$data['syncLists'] = $this->url->link('extension/module/mailchimp/syncLists', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['syncStore'] = $this->url->link('extension/module/mailchimp/syncStore', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['syncProducts'] = $this->url->link('extension/module/mailchimp/syncProducts', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['syncCustomers'] = $this->url->link('extension/module/mailchimp/syncCustomers', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['syncOrders'] = $this->url->link('extension/module/mailchimp/syncOrders', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['syncCarts'] = $this->url->link('extension/module/mailchimp/syncCarts', 'user_token=' . $this->session->data['user_token'] . $url, true);
		
		if (!empty($this->errorMessage)) {
			$data['errorMessage'] = $this->errorMessage; 
		}

		if (!empty($this->successMessage)) {
			$data['successMessage'] = $this->successMessage; 
		}

		if (!empty($this->warningMessage)) {
			$data['warningMessage'] = $this->warningMessage; 
		}

		$this->response->setOutput($this->load->view('extension/module/mailchimp', $data));
	}

	private function setErrorMessage($message) 
	{
		$this->warningMessage = '';
		$this->errorMessage = $message;
		$this->successMessage = '';
	}

	private function setWarningMessage($message) 
	{
		$this->warningMessage = $message;
		$this->errorMessage = '';
		$this->successMessage = '';
	}

	private function setSuccessMessage($message) 
	{
		$this->warningMessage = '';
		$this->errorMessage = '';
		$this->successMessage = $message;
	}

	public function install() 
	{
		$this->load->model('setting/extension');
		$this->model_setting_extension->install('module', 'mailchimp');
	}

	public function uninstall() 
	{
		$this->load->model('setting/extension');
		$this->model_setting_extension->uninstall('module', 'mailchimp');

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_mailchimp_status');
	}
}