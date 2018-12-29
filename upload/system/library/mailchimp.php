<?php
class Mailchimp 
{
    private $urlSuffixBase = '.api.mailchimp.com/3.0';
    private $serverScheme = 'https';
    private $apiKey;
    private $serverCompleteUlr;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->serverCompleteUlr = $this->mountCompleteUrlBase($apiKey);
    }
    
    public function syncStore($store)
    {
        $existResponse = $this->get('/ecommerce/stores/' . $store['id']);

        if ($existResponse->status ==  404) {
            $response = $this->post('/ecommerce/stores/', $store);
        } else {
            $response = $this->patch('/ecommerce/stores/'  . $store['id'], $store);
        }

        return $response;
    }

    public function syncList(array $list, $id = null)
    {
        if (is_null($id)) {
            $response = $this->post('/lists/', $list);
        }

        if (!is_null($id)) {
            $response = $this->patch('/lists/' . $id, $list);
        }

        return $response;
    }

    public function syncProduct($store, $product) 
    {
        // checking if product already exists
        $existResponse = $this->get('/ecommerce/stores/' . $store . '/products/' . $product['id']);
        
        if ($existResponse->status ==  404) {
            $response = $this->post('/ecommerce/stores/' . $store . '/products/', $product);
        } else {
            try {
                $response = $this->patch('/ecommerce/stores/' . $store . '/products/'  . $product['id'], $product);
            } catch(Exception $e) {
                $response = $existResponse;
            }
        }

        return $response;
    }

    public function syncListMembers($listId, array $members)
    {
        $response = $this->post('/lists/' . $listId, $members);

        return $response;
    }
    
    public function syncListMember($listId, array $member)
    {
        $hash = md5($member['email']);
        
        $response = $this->patch('/lists/' . $listId . '/members/' . $hash, $member);
        
        if ($response->status == 404) {
            $response = $this->post('/lists/' . $listId . '/members/', $member);
        }
        
        return $response;


        
        $response = $this->post('/lists/' . $listId, $members);

        return $response;
    }

    public function syncCustomer($storeId, array $customerData)
    {
        
        $response = $this->patch('/ecommerce/stores/' . $storeId . '/customers/' . $customerData['id'], $customerData);
        
        if ($response->status == 404) {
            $response = $this->post('/ecommerce/stores/' . $storeId . '/customers', $customerData);
        }
        
        return $response;
    }

    public function syncCart($storeId, array $cartData)
    {
        
        $response = $this->patch('/ecommerce/stores/' . $storeId . '/carts/' . $cartData['id'], $cartData);
        
        if ($response->status == 404) {
            $response = $this->post('/ecommerce/stores/' . $storeId . '/carts', $cartData);
        }
        
        return $response;
    }


    public function syncOrder($storeId, array $orderData)
    {
        $response = $this->patch('/ecommerce/stores/' . $storeId . '/orders/' . $orderData['id'], $orderData);
        
        if ($response->status == 404) {
            $response = $this->post('/ecommerce/stores/' . $storeId . '/orders', $orderData);
        }
        
        return $response;
    }
    
    public function countSynchronizedProducts($storeId) 
    {
        $response = $this->get('/ecommerce/stores/' . $storeId . '/products?fields=total_items');
        
        if($response->status == 404) {
            return 0;
        }

        return $response->total_items;
    }

    public function countSynchronizedCustomers($storeId) 
    {
        $response = $this->get('/ecommerce/stores/' . $storeId . '/customers?fields=total_items');
        
        if($response->status == 404) {
            return 0;
        }

        return $response->total_items;
    }

    public function countSynchronizedOrders($storeId) 
    {
        $response = $this->get('/ecommerce/stores/' . $storeId . '/orders?fields=total_items');
        
        if($response->status == 404) {
            return 0;
        }

        return $response->total_items;
    }

    public function countSynchronizedCarts($storeId) 
    {
        $response = $this->get('/ecommerce/stores/' . $storeId . '/carts?fields=total_items');
        
        if($response->status == 404) {
            return 0;
        }

        return $response->total_items;
    }

    public function countSynchronizedLists($storeId) 
    {
        $response = $this->get('/ecommerce/stores/' . $storeId . '/carts?fields=total_items');
        
        if($response->status == 404) {
            return 0;
        }

        return $response->total_items;
    }

    public function countListContacts($listId) 
    {
        $response = $this->get('/lists/' . $listId . '/?fields=stats.member_count');
        
        if($response->status == 404) {
            return false;
        }

        return $response->stats->member_count;
    }

    public function countSynchronizedStores() 
    {
        $response = $this->get('/ecommerce/stores?fields=total_items');
        
        if($response->status == 404) {
            return 0;
        }

        return $response->total_items;
    }

    public function deleteCart($storeId, $cartId)
    {
        return $this->delete('/ecommerce/stores/' . $storeId . '/carts/' . $cartId);
    }

    public function post($url, $data)
    {
        return $this->sendRequest($url, $data, 'POST');
    }

    public function delete($url)
    {
        return $this->sendRequest($url, [], 'DELETE');
    }

    public function patch($url, $data)
    {
        return $this->sendRequest($url, $data, 'PATCH');
    }

    public function get($url, $data = null)
    {
        return $this->sendRequest($url, $data);
    }

    private function sendRequest($url, $data, $method = 'GET')
    {
        
        $url = $this->serverCompleteUlr . $url;
        
        
        $headers = [
            'Authorization: Basic ' . base64_encode('anystring:' . $this->apiKey),
            'Accept: application/json',
            'cache-control: no-cache',
            'Content-Type: application/json'
        ];

        $channel = curl_init();

        if ($method == 'POST') {
            curl_setopt($channel, CURLOPT_URL, $url);
            curl_setopt($channel, CURLOPT_POST, 1);

            if(isset($data)) {
                curl_setopt($channel, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        if ($method == 'DELETE') {
            curl_setopt($channel, CURLOPT_URL, $url);
            curl_setopt($channel, CURLOPT_MAXREDIRS, 3);
            curl_setopt($channel, CURLOPT_TIMEOUT, 5);
            curl_setopt($channel, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($channel, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        

        if ($method == 'PATCH') {
            curl_setopt($channel, CURLOPT_URL, $url);
            curl_setopt($channel, CURLOPT_ENCODING, "");
            curl_setopt($channel, CURLOPT_MAXREDIRS, 3);
            curl_setopt($channel, CURLOPT_TIMEOUT, 10);
            curl_setopt($channel, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($channel, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if(isset($data)) {
                curl_setopt($channel, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        

        if ($method == 'GET') {
            if(isset($data) && is_array($data)) {
                $url = $url . '?' . http_build_query($data);
            }

            curl_setopt($channel, CURLOPT_URL, $url);
        }

        curl_setopt($channel, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($channel, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($channel);
        if (!curl_errno($channel)) {
            $this->info = curl_getinfo($channel);
        }    
        if ($response === false) {
            throw new Exception(curl_error($channel) . ' - ' . $url . ' - ' . $method );
        }
        curl_close($channel);
        return json_decode($response);
    }

    private function mountCompleteUrlBase($apiKey)
    {
        $parts = explode('-', $apiKey);
        return $this->serverScheme . '://' . $parts[1] . $this->urlSuffixBase;
    }
}