<?php
class ModelExtensionModuleMailchimp extends Model {
   
    public function getOrdersCountByCustomer($customerId) 
    {
        $implode = [];

		$order_statuses = $this->config->get('config_complete_status');

		foreach ($order_statuses as $order_status_id) {
			$implode[] = "order_status_id = '" . (int)$order_status_id . "'";
		}

		if ($implode) {
			$query = $this->db->query("SELECT sum(total) total FROM `" . DB_PREFIX . "order` WHERE customer_id = " . (int)$customerId . " AND (" .  implode(" OR ", $implode) . ")");
            return (int)$query->row['total'];
		} 
        
        return 0;
    }


    public function getTotalProducts() 
    {
    	$query = $this->db->query("SELECT count(product_id) total FROM `" . DB_PREFIX . "product`");
        return (int)$query->row['total'];
	}

    public function getTotalSpentByCustomer($customerId) {
		$implode = [];

		$order_statuses = $this->config->get('config_complete_status');

		foreach ($order_statuses as $order_status_id) {
			$implode[] = "order_status_id = '" . (int)$order_status_id . "'";
		}

		if ($implode) {
			$query = $this->db->query("SELECT sum(total) total FROM `" . DB_PREFIX . "order` WHERE customer_id = " . (int)$customerId . " AND (" .  implode(" OR ", $implode) . ")");
            return (float)$query->row['total'];
		} 
        
        return 0;
    }
    
    public function getCarts() {
        $query = $this->db->query("SELECT 
                                    session_id,
                                    cart.customer_id,
                                    SUM(COALESCE((
                                        SELECT price FROM `" . DB_PREFIX . "product_special`  
                                        WHERE product_id = pro.product_id 
                                        AND (date_start = '0000-00-00' OR date_start <= now()) 
                                        AND (date_end = '0000-00-00' OR date_end > now())
                                        ORDER BY priority LIMIT 1
                                    ), pro.price)) AS total,
                                    count(pro.product_id) AS quantity_products
                                FROM `" . DB_PREFIX . "cart`  cart
                                INNER JOIN `" . DB_PREFIX . "product` pro ON pro.product_id = cart.product_id
                                INNER JOIN `" . DB_PREFIX . "customer` cus ON cus.customer_id = cart.customer_id
                                GROUP BY session_id;");
        return $query->rows;
    }

    public function getTotalCarts() {
        $query = $this->db->query("SELECT count(*) as total FROM (SELECT 
                                    session_id
                                FROM `" . DB_PREFIX . "cart`  cart
                                INNER JOIN `" . DB_PREFIX . "product` pro ON pro.product_id = cart.product_id
                                INNER JOIN `" . DB_PREFIX . "customer` cus ON cus.customer_id = cart.customer_id
                                GROUP BY session_id) t1;");

        return $query->row['total'];
    }

    public function getCartProducts($sessionId) {
        $query = $this->db->query("SELECT 
                                    cart.cart_id,
                                    cart.product_id,
                                    cart.quantity,
                                    COALESCE((
                                        SELECT price FROM `" . DB_PREFIX . "product_special` 
                                        WHERE product_id = pro.product_id 
                                        AND (date_start = '0000-00-00' OR date_start <= now()) 
                                        AND (date_end = '0000-00-00' OR date_end > now())
                                        ORDER BY priority LIMIT 1
                                    ), pro.price) AS price
                                FROM `" . DB_PREFIX . "cart`  cart
                                INNER JOIN `" . DB_PREFIX . "product` pro ON pro.product_id = cart.product_id
                                WHERE session_id = '" . $sessionId . "';");
        return $query->rows;
    }
}
?>
