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

			$special = null;
			$product_specials = $this->model_catalog_product->getProductSpecials($product['product_id']);
			foreach ($product_specials  as $product_special) {
				if (($product_special['date_start'] == '0000-00-00' || strtotime($product_special['date_start']) < time()) && ($product_special['date_end'] == '0000-00-00' || strtotime($product_special['date_end']) > time())) {
					$special = $this->currency->format($product_special['price'], $this->config->get('config_currency'));
					break;
				}
			}

			$manufacturer = $this->model_catalog_manufacturer->getManufacturer($product['manufacturer_id']);

			$image = empty($product['image']) ? 'no_image.png' : $product['image'];
			$imageUrl = $this->model_tool_image->resize($image, 600, 695);
			$price = is_null($special) ? $product['price'] : $special;

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
						'price' => (float)$price,
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

				$cartProducts = $cartProducts = $this->model_extension_module_mailchimp->getCartProducts($cart['session_id']);

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

	public function addComplementaryDataOnConfigPage(&$route, &$data, &$lascou) 
	{
		$this->load->language('extension/module/mailchimp');
		
		$data['entry_city'] = $this->language->get('entry_city');
		$data['entry_zip'] = $this->language->get('entry_zip');
		$data['config_city'] = $this->config->get('config_city');
		$data['config_zip'] = $this->config->get('config_zip');
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
		
		
			
		$data['totalProducts'] = $this->model_extension_module_mailchimp->getTotalProducts();
		$data['totalCustomers'] = $this->model_customer_customer->getTotalCustomers();
		$data['totalOrders'] = $this->model_sale_order->getTotalOrders();
		$data['totalCarts'] = $this->model_extension_module_mailchimp->getTotalCarts();
		$data['totalListContacts'] = $data['totalCustomers'];
		
		$data['synchronizedProducts'] = 0;
		$data['synchronizedStores'] = 0;
		$data['synchronizedCustomers'] = 0;
		$data['synchronizedOrders'] = 0;
		$data['synchronizedCarts'] = 0;
		$data['synchronizedLists'] =  0;
		$data['synchronizedLists'] = 0;
		$data['synchronizedListsContacts'] = 0;
		
		if ($apiKey) {
			$mailchimp = new Mailchimp($apiKey);
			$data['synchronizedProducts'] = $mailchimp->countSynchronizedProducts('default');
			$data['synchronizedStores'] = $mailchimp->countSynchronizedStores();
			$data['synchronizedCustomers'] = $mailchimp->countSynchronizedCustomers('default');
			$data['synchronizedOrders'] = $mailchimp->countSynchronizedOrders('default');
			$data['synchronizedCarts'] = $mailchimp->countSynchronizedCarts('default');
			
			$listId = $this->model_setting_setting->getSettingValue('module_mailchimp_default_list_id');
			if ($listId)
			{
				$listContacts = $mailchimp->countListContacts($listId);
				if($listContacts) {
					$data['synchronizedLists'] = 1;
					$data['synchronizedListsContacts'] = $listContacts;
				}
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

		$this->load->model('setting/event');

		$this->model_setting_extension->install('module', 'mailchimp');

		if (!$this->model_setting_event->getEventByCode('mailchimp_add_complementary_data')) {
			$code = "mailchimp_add_complementary_data";
			$trigger = "admin/view/setting/setting/before";
			$action = "extension/module/mailchimp/addComplementaryDataOnConfigPage";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_add_product')) {
			$code = "mailchimp_add_product";
			$trigger = "admin/model/catalog/product/addProduct/after";
			$action = "extension/module/mailchimp/productTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_edit_product')) {
			$code = "mailchimp_edit_product";
			$trigger = "admin/model/catalog/product/editProduct/after";
			$action = "extension/module/mailchimp/productTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_add_customer')) {
			$code = "mailchimp_add_customer";
			$trigger = "catalog/model/account/customer/addCustomer/after";
			$action = "extension/module/mailchimp/customerTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_edit_customer')) {
			$code = "mailchimp_edit_customer";
			$trigger = "catalog/model/account/customer/editCustomer/after";
			$action = "extension/module/mailchimp/customerTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_add_cart')) {
			$code = "mailchimp_add_cart";
			$trigger = "catalog/controller/checkout/cart/add/after";
			$action = "extension/module/mailchimp/cartTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_edit_cart')) {
			$code = "mailchimp_edit_cart";
			$trigger = "catalog/controller/checkout/cart/edit/before";
			$action = "extension/module/mailchimp/cartTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_remove_cart')) {
			$code = "mailchimp_remove_cart";
			$trigger = "catalog/controller/checkout/cart/remove/after";
			$action = "extension/module/mailchimp/cartTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_clear_cart')) {
			$code = "mailchimp_clear_cart";
			$trigger = "catalog/controller/checkout/success/before";
			$action = "extension/module/mailchimp/clearCartTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}

		if (!$this->model_setting_event->getEventByCode('mailchimp_add_order_history')) {
			$code = "mailchimp_add_order_history";
			$trigger = "catalog/model/checkout/order/addOrderHistory/after";
			$action = "extension/module/mailchimp/orderTrigger";
			$this->model_setting_event->addEvent($code, $trigger, $action);
		}
	}

	public function productTrigger(&$route, &$data, &$output) 
	{
		$this->load->model('setting/setting');

		$this->load->model('catalog/product');
		
		$this->load->model('tool/image');

		$apiKey = $this->model_setting_setting->getSettingValue('module_mailchimp_api_key');

		if ($apiKey) {

			$product = $data[0];
			$productId = $output;
			
			if (is_null($productId)) {
				$productId = $data[0];
				$product = $data[1];
			}

			if ($this->config->get('config_seo_url')) {
				$this->url->addRewrite($this);
			}
			
			$special = null;
			$product_specials = $this->model_catalog_product->getProductSpecials($productId);
			foreach ($product_specials  as $product_special) {
				if (($product_special['date_start'] == '0000-00-00' || strtotime($product_special['date_start']) < time()) && ($product_special['date_end'] == '0000-00-00' || strtotime($product_special['date_end']) > time())) {
					$special = $this->currency->format($product_special['price'], $this->config->get('config_currency'));
					break;
				}
			}
			
			$price = is_null($special) ? $product['price'] : $special;
			
			$image = empty($product['image']) ? 'no_image.png' : $product['image'];
			
			$imageUrl = $this->model_tool_image->resize($image, 600, 695);

			// manufacturer_id
			$productData = [
				'id'    => (string)$productId,
				'title' => $product['product_description'][1]['name'],
				'url' => $this->url->link('product/product', 'product_id=' . $productId),
				'description' => $product['product_description'][1]['description'],
				'image_url' => $imageUrl,
				'variants' => [
					[
						'id'    => (string)$productId,
						'title' => $product['product_description'][1]['name'],
						'url'   => $this->url->link('product/product', 'product_id=' . $productId),
						'sku'	=> $product['sku'],
						'price' => (float)$price,
						'inventory_quantity' => (int)$product['quantity'],
						'image_url' => $imageUrl,
					]
				]	
			];


			if (!empty($product['manufacturer'])) {
				$productData['vendor'] = (string)$product['manufacturer'];
			}

			
			$mailchimp = new Mailchimp($apiKey);
			$returnedProduct = $mailchimp->syncProduct('default', $productData);
		}
	}

	public function uninstall() 
	{
		$this->load->model('setting/extension');
		$this->model_setting_extension->uninstall('module', 'mailchimp');

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_mailchimp_status');

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('mailchimp_add_complementary_data');
		$this->model_setting_event->deleteEventByCode('mailchimp_add_customer');
		$this->model_setting_event->deleteEventByCode('mailchimp_edit_customer');
		$this->model_setting_event->deleteEventByCode('mailchimp_add_product');
		$this->model_setting_event->deleteEventByCode('mailchimp_edit_product');
		$this->model_setting_event->deleteEventByCode('mailchimp_add_cart');
		$this->model_setting_event->deleteEventByCode('mailchimp_edit_cart');
		$this->model_setting_event->deleteEventByCode('mailchimp_remove_cart');
		$this->model_setting_event->deleteEventByCode('mailchimp_add_order_history');
		$this->model_setting_event->deleteEventByCode('mailchimp_clear_cart');
		
	}
}