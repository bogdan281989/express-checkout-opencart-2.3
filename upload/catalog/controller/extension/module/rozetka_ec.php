<?php
class ControllerExtensionModuleRozetkaEc extends Controller {			
	/**
     * @var \RozetkaPayCheckout
     */
    private $rpay;

    /**
     * Конструктор класу
     * 
     * @param Registry $registry Реєстр OpenCart
     */
	
	public function __construct($registry) {
		parent::__construct($registry);
		
		require_once DIR_SYSTEM . 'library/rozetka_ec/rozetka_pay_simple.php';

		$this->rpay = new \RozetkaPayCheckout();

        $this->rpay->setBasicAuth($this->config->get('rozetka_ec_login'), $this->config->get('rozetka_ec_password'));
		
		$this->load->language('extension/module/rozetka_ec');
		$this->load->model('extension/module/rozetka_ec');
		$this->load->model('checkout/order');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');
	}
	
	public function index() {
		// Посилання на документацію RozetkaPay API
        /* https://cdn.rozetkapay.com/public-docs/index.html */
	}
	
	 /**
     * Генерує посилання на оплату для замовлення
     * 
     * @param int $order_id Ідентифікатор замовлення
     * @return string|false Посилання на оплату або false у разі помилки
     */
	public function getLinkPay($order_id) {		
		if(!$order_id) {
			return false;
		}
		
		$order_info = $this->model_checkout_order->getOrder($order_id);
		
		$currency = 'UAH';
		
		$dataCheckout = new \RPayCheckoutCreatRequest();
		
		$dataCheckout->mode = 'express_checkout';		
		$dataCheckout->external_id = $order_id;
		$dataCheckout->amount = $this->currency->format($order_info['total'], $currency, $this->currency->getValue($currency), false);
		
		if($this->customer->isLogged()) {
			$customer = new \RPayCustomer();
			$customer->phone = $this->customer->getTelephone();
			$dataCheckout->customer = $customer;
		}
		
		$products = $this->model_extension_module_rozetka_ec->getDefaultOrderProducts($order_id);
        
		foreach ($products as $product_) {
			
			$product_info = $this->model_catalog_product->getProduct($product_['product_id']);
			
			$productNew = new \RPayProduct();
			
			$productNew->id = $product_['product_id'];
			$productNew->name = html_entity_decode($product_['name']);
			$productNew->description = html_entity_decode($product_['model'], ENT_QUOTES, 'UTF-8');
			$productNew->currency = $currency;
			$productNew->quantity = $product_['quantity'];
			$productNew->net_amount = $this->currency->format($product_['price'], $currency, $this->currency->getValue($currency), false);
			$productNew->vat_amount = $product_['tax'];
			
			if(!empty($product_info['image'])){
				$productNew->image = $image = $this->model_tool_image->resize($product_info['image'], 250, 250);
			}
			
			$productNew->url = $this->url->link('product/product', 'product_id=' . $product_['product_id'] , true);
			
			$dataCheckout->products[] = $productNew;
		}
		
		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}
		
		$dataCheckout->callback_url = $server . 'index.php?route=extension/module/rozetka_ec/callback';
		$dataCheckout->result_url = $server . 'index.php?route=extension/module/rozetka_ec/success&order_id=' . $order_id;
		
		//Log
		$this->setLog($dataCheckout, 'Дані що йдуть на платіжну систему');
		
		$result = $this->convertToObjectArray($this->rpay->checkoutCreat($dataCheckout));
		
		//Log
		$this->setLog($result, 'Генерація сторінки оплати');
		
		if(!empty($result[0]) && $result[0]['is_success']) {
			return $result[0]['action']['value'];
		}
		
		return false;
	}
	
	/**
     * Отримання, верифікація та обробка колбека від платіжної системи
     */
	public function callback() {				
		$result = $this->convertToObjectArray($this->rpay->callbacks());

		if(!$result) {
			//Log
			$this->setLog($result, 'Помилка, callback пустий!');
			
			return false;
		} else {
			//Log
			$this->setLog($result, 'Дані що прийшли в callback');
			
			$order_status_id = 0;
			
			if(!empty($result['is_success']) && $result['is_success']) {
				//замовлення успішно оплачено, заповнюємо його дані
				
				$status_code = $result['details']['status_code'] ?? '';
	
				if($result['operation'] == 'payment' || $status_code == 'transaction_successful') {
					$order_id = $this->model_extension_module_rozetka_ec->setOrderData($result);
					
					$order_status_id = $this->config->get('rozetka_ec_order_status_id');
				} 
				
				//повернення
				if($result['operation'] == 'refund') {
					$order_id = $result['external_id'];
					
					$order_status_id = $this->config->get('rozetka_ec_order_refund_status_id');
				}
				
			} elseif(!empty($result['purchase_details'][0]['status_code']) && $result['purchase_details'][0]['status_code'] == 'order_with_postpayment_confirmed') {				
				//Оплата при отриманні
				$order_id = $this->model_extension_module_rozetka_ec->setOrderData($result);
					
				$order_status_id = $this->config->get('rozetka_ec_order_post_pay_status_id');
			} else {				
				//Невдала оплата замовлення
				$order_id = $this->model_extension_module_rozetka_ec->setOrderData($result);
					
				$order_status_id = $this->config->get('rozetka_ec_order_fail_status_id');
			}
			
			if($order_status_id) {
				$this->model_checkout_order->addOrderHistory($order_id, $order_status_id);
			}
		}
	}
	
	/**
     * Проміжна сторінка з перевіркою статусу оплати замовлення, використовується для перенаправлення на сторінку успішної або неуспішної сторінки
     */
	public function success() {		
		$order_id = !empty($this->request->get['order_id']) ? $this->request->get['order_id'] : false;

		if(!$order_id) {
			$this->setLog(array('text' => 'Order ID empty!'), 'Помилка при поверненні з платіжної системи');
			
			return false;
		}
		
		$data['lang'] = $this->language->get('code');
		$data['text_check_pay'] = $this->language->get('text_check_pay');
		$data['redirect_success'] = $this->url->link('checkout/success', '', 'SSL');
		$data['redirect_fail'] = $this->url->link('extension/module/rozetka_ec/failPay', '', 'SSL');
		$data['query_url'] = $this->url->link('extension/module/rozetka_ec/checkStatusPay', 'order_id=' . $order_id, 'SSL');
		
		$this->response->setOutput($this->load->view('extension/module/rozetka_ec_check', $data));
	}
	
	/**
     * Метод перевірки успішності оплати по API
     * 
     * @param int $order_id Ідентифікатор замовлення, також є перевірка чи це не замовлення з післясплатою
     * @return bool повертає true або false
     */
	public function checkStatusPay() {
		$json = array();
		
		$order_id = !empty($this->request->get['order_id']) ? $this->request->get['order_id'] : false;
		
		$result = $this->convertToObjectArray($this->rpay->paymentInfo((string)$order_id));
	
		if(!empty($result[0]) && !empty($order_id)) {
			$json['status'] = true;
		} else {			
			$json['status'] = false;
			
			$order_postpayment = $this->model_extension_module_rozetka_ec->checkOrderPostpayment($order_id);
            
            if($order_postpayment) {
                $json['status'] = true;
            }
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	/**
     * Метод який формує і віддає в шаблон js частину додатку
     * 
     * @return html 
     */
	public function getFooterJs() {
		if($this->config->get('rozetka_ec_status')) {
			$data['button_cart'] = $this->config->get('rozetka_ec_button_cart');
			$data['button_cart_js'] = html_entity_decode($this->config->get('rozetka_ec_button_cart_js'), ENT_QUOTES, 'UTF-8');
			$data['position_button_cart_js'] = $this->config->get('rozetka_ec_position_button_cart_js');
			
			$data['button_product'] = $this->config->get('rozetka_ec_button_product');
			$data['button_product_js'] = html_entity_decode($this->config->get('rozetka_ec_button_product_js'), ENT_QUOTES, 'UTF-8');
			$data['position_button_product_js'] = $this->config->get('rozetka_ec_position_button_product_js');
			
			$data['button_checkout'] = $this->config->get('rozetka_ec_button_checkout');
			$data['button_checkout_js'] = html_entity_decode($this->config->get('rozetka_ec_button_checkout_js'), ENT_QUOTES, 'UTF-8');
			$data['position_button_checkout_js'] = $this->config->get('rozetka_ec_position_button_checkout_js');
			
			$data['button_rozetka_pay'] = $this->getButtonPay();

			return $this->load->view('extension/module/rozetka_ec_js', $data);
		}
	}
	
	/**
     * Метод який формує і віддає в шаблон html кнопок платіжної системи
     * 
     * @return html 
     */
	private function getButtonPay() {
		$color = $this->config->get('rozetka_ec_button_color');
		$variant = 'variant_2';
		$image_path = 'catalog/view/theme/default/image/payment/rozetka_ec/';
		
		$data['button_text'] = $this->language->get('text_button_default');
		$data['button_image'] = $color == 'white' ? $image_path . 'rozetka_ec_logo_' . $variant . '_black.svg' : $image_path . 'rozetka_ec_logo_' . $variant . '_white.svg';	
		$data['color'] = $color;
		$data['variant'] = $variant;
		
		return $this->load->view('extension/module/rozetka_ec_button_pay', $data);
	}
	
	/**
     * Метод створення замовлення в opencart і отримання посилання на оплату
     * 
     * @param string $mode Тип кнопки (місця) звідки здійснено виклик метода
     * @return string повертає посилання у разі успішної генерації або текст помилки
     */
	public function createOrder() {
		$json = array();
		
		if(!empty($this->request->post['mode'])) {
			$mode = $this->request->post['mode'];
		} else {
			$mode = false;
		}
		
		if($mode) {
			$order_id = $this->createOrderOpencart($mode);
			
			if($order_id) {
				$link = $this->getLinkPay($order_id);
				
				if($link) {
					$json['success'] = $link;
				} else {
					$json['error'] = $this->language->get('text_error_order_opencart');
				}
			} else {
				$json['error'] = $this->language->get('text_error_order_opencart');
			}
			
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	/**
     * Метод створення замовлення в opencart
     * 
     * @param string $mode Тип кнопки (місця) звідки здійснено виклик метода
     * @return int повертає order_id
     */
	private function createOrderOpencart($mode) {
		if(($mode == 'cart' || $mode == 'checkout') && !empty($this->session->data['order_id'])) {
			//return $this->session->data['order_id'];
		}	
		
		$this->language->load('checkout/cart');

		$this->load->model('extension/extension');
		$this->load->model('account/customer');
		$this->load->model('checkout/order');
		
		$order_data = array();

		if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
		  $forwarded_ip = $this->request->server['HTTP_X_FORWARDED_FOR'];
		} elseif(!empty($this->request->server['HTTP_CLIENT_IP'])) {
		  $forwarded_ip = $this->request->server['HTTP_CLIENT_IP'];
		} else {
		  $forwarded_ip = '';
		}

		$accept_language = isset($this->request->server['HTTP_ACCEPT_LANGUAGE']) ? $this->request->server['HTTP_ACCEPT_LANGUAGE'] : '';
		$user_agent = isset($this->request->server['HTTP_USER_AGENT']) ? $this->request->server['HTTP_USER_AGENT'] : '';

		// Affiliate
		$affiliate_id = 0;
		$commission = 0;
		$marketing_id = 0;
		$tracking = '';

		$order_products = array();			
		
		foreach ($this->cart->getProducts() as $product) {
		  $option_data = array();

			foreach ($product['option'] as $option) {
				$option_data[] = array(
					'product_option_id'       => $option['product_option_id'],
					'product_option_value_id' => $option['product_option_value_id'],
					'option_id'               => $option['option_id'],
					'option_value_id'         => $option['option_value_id'],
					'name'                    => $option['name'],
					'value'                   => $option['value'],
					'type'                    => $option['type']
				);
			}

		  $order_products[] = array(
				'product_id' => $product['product_id'],
				'name'       => $product['name'],
				'model'      => $product['model'],
				'option'     => $option_data,
				'download'   => $product['download'],
				'quantity'   => $product['quantity'],
				'subtract'   => $product['subtract'],
				'price'      => $product['price'],
				'total'      => $product['total'],
				'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
				'reward'     => $product['reward']
			);
		}
		

		// totals and total
		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;

		// Because __call can not keep var references so we put them into an array. 
		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			$sort_order = array();

			$results = $this->model_extension_extension->getExtensions('total');

			foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
			}

			array_multisort($sort_order, SORT_ASC, $results);

			foreach ($results as $result) {
				if ($this->config->get($result['code'] . '_status')) {
					$this->load->model('extension/total/' . $result['code']);
					
					// We have to put the totals in an array so that they pass by reference.
					$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
				}
			}

			$sort_order = array();

			foreach ($totals as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}

			array_multisort($sort_order, SORT_ASC, $totals);
		}

		$order_data = array(
			'invoice_prefix'          => $this->config->get('config_invoice_prefix'),
			'store_id'                => $store_id = (int)$this->config->get('config_store_id'),
			'store_name'              => $this->config->get('config_name'),
			'store_url'               => $store_id ? $this->config->get('config_url') : HTTPS_SERVER,
			'customer_id'             => $this->customer->isLogged() ? $this->customer->getId() : 0,
			'customer_group_id'       => $this->customer->isLogged() ? $this->customer->getGroupId() : $this->config->get('config_customer_group_id'),
			'firstname'               => '',
			'lastname'                => '',
			'email'                   => 'localhost@' . $this->request->server['HTTP_HOST'],
			'telephone'               => '',
			'fax'                     => '',
			'surname'                 => '',
			'shipping_city'           => '',
			'shipping_postcode'       => '',
			'shipping_country'        => '',
			'shipping_country_id'     => '',
			'shipping_zone_id'        => '',
			'shipping_zone'           => '',
			'shipping_address_format' => '',
			'shipping_firstname'      => '',
			'shipping_lastname'       => '',
			'shipping_company'        => '',
			'shipping_address_1'      => '',
			'shipping_address_2'      => '',
			'shipping_code'           => '',
			'shipping_method'         => '',
			'shipping_building'       => '',
			'payment_city'            => '',
			'payment_postcode'        => '',
			'payment_country'         => '',
			'payment_country_id'      => '',
			'payment_zone'            => '',
			'payment_zone_id'         => '',
			'payment_address_format'  => '',
			'payment_firstname'       => '',
			'payment_lastname'        => '',
			'payment_company'         => '',
			'payment_address_1'       => '',
			'payment_address_2'       => '',
			'payment_company_id'      => '',
			'payment_tax_id'          => '',
			'payment_code'            => '',
			'payment_method'          => '',
			'forwarded_ip'            => $forwarded_ip,
			'user_agent'              => $user_agent,
			'accept_language'         => $accept_language,
			'vouchers'                => array(),
			'comment'                 => (isset($this->request->post['comment'])) ? $this->request->post['comment'] : '',
			'total'                   => $this->cart->getTotal(),
			'reward'                  => '',
			'affiliate_id'            => $affiliate_id,
			'tracking'                => $tracking,
			'commission'              => $commission,
			'marketing_id'            => $marketing_id,
			'language_id'             => $this->config->get('config_language_id'),
			'currency_id'             => $this->currency->getId($this->session->data['currency']),
			'currency_code'           => $this->session->data['currency'],
			'currency_value'          => $this->currency->getValue($this->session->data['currency']),
			'ip'                      => $this->request->server['REMOTE_ADDR'],
			'products'                => $order_products,
			'totals'                  => $totals
		);

		$this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);

		$order_id = (int)$this->session->data['order_id'];

		$this->cart->clear();
		
		return $order_id;
	}
	
	/**
     * Метод конвертації об'єкта в асоціативний масив
     * 
     * @param object $data Обєкт
     * @return array повертає асоціативний масив
     */
	private function convertToObjectArray($data) {
		if (is_object($data)) {
			$data = (array) $data;
		}
		
		if (is_array($data)) {
			foreach ($data as &$value) {
				$value = $this->convertToObjectArray($value);
			}
		}
		
		return $data;
	}
	
	/**
     * Метод логування запитів і відповідей API
     * 
     * @param object Приймає дані у вигляді масиву та текст з коментарем запису в лог
     */
	private function setLog($data, $text) {
		if($this->config->get('rozetka_ec_status_log')) {
			$log = new Log('rozetka_ec.log');
			$log->write('--------- ' . $text . ': ПОЧАТОК ---------');
			$log->write(json_encode($data));
			$log->write('--------- ' . $text . ': КІНЕЦЬ ---------');
		}
	}
	
	/**
     * Сторінка невдалої оплати
     * 
     * @return string повертає html сторінки
     */
	public function failPay() {
		$this->load->language('extension/module/rozetka_ec');

		$this->document->setTitle($this->language->get('text_fail'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_fail'),
			'href' => $this->url->link('checkout/rozetka_ec_fail')
		);

		$data['heading_title'] = $this->language->get('text_fail');

		$data['text_message'] = $this->language->get('text_message');

		$data['button_continue'] = $this->language->get('button_continue');

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}
}