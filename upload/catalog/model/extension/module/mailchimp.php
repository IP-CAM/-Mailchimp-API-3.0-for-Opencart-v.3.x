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
}
?>
