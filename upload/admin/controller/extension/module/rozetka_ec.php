<?php
class ControllerExtensionModuleRozetkaEc extends Controller {
	/**
     * @var array Массив помилок
     */
    private $error = array();

    /**
     * @var RozetkaPayCheckout Екземпляр класу RozetkaPayCheckout
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
	}

	/**
     * Головний метод контролера (налаштування модуля)
     */
	public function index() {
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('rozetka_ec', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true));
		}
		
		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_selector_default'] = $this->language->get('text_selector_default');
		$data['text_black'] = $this->language->get('text_black');
		$data['text_white'] = $this->language->get('text_white');
		$data['text_preview'] = $this->language->get('text_preview');
		$data['text_buy_rpay'] = $this->language->get('text_buy_rpay');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled'); 
		
		$data['entry_login'] = $this->language->get('entry_login');
		$data['entry_password'] = $this->language->get('entry_password');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_order_fail'] = $this->language->get('entry_order_fail');
		$data['entry_order_success_hold'] = $this->language->get('entry_order_success_hold');
		$data['entry_order_refund'] = $this->language->get('entry_order_refund');
		$data['entry_post_pay'] = $this->language->get('entry_post_pay');
		$data['entry_button_cart'] = $this->language->get('entry_button_cart');
		$data['entry_button_cart_js'] = $this->language->get('entry_button_cart_js');
		$data['entry_button_product'] = $this->language->get('entry_button_product');
		$data['entry_button_checkout'] = $this->language->get('entry_button_checkout');
		$data['entry_button_variant'] = $this->language->get('entry_button_variant');
		$data['entry_color_button'] = $this->language->get('entry_color_button');
		$data['entry_button_css'] = $this->language->get('entry_button_css');
		$data['entry_status_log'] = $this->language->get('entry_status_log');
		
		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_status'] = $this->language->get('tab_status');
		$data['tab_added'] = $this->language->get('tab_added');
		$data['tab_design'] = $this->language->get('tab_design');
		$data['tab_log'] = $this->language->get('tab_log');
		
		$data['help_login'] = $this->language->get('help_login');
		$data['help_password'] = $this->language->get('help_password');
		$data['help_button_cart_js'] = $this->language->get('help_button_cart_js');
		$data['help_button_css'] = $this->language->get('help_button_css');
		
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_download'] = $this->language->get('button_download');
		$data['button_clear'] = $this->language->get('button_clear');
		
		$this->document->addStyle('view/stylesheet/rozetka_ec.css?v=1.0.2');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['login'])) {
			$data['error_login'] = $this->error['login'];
		} else {
			$data['error_login'] = '';
		}

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		if (isset($this->error['type'])) {
			$data['error_type'] = $this->error['type'];
		} else {
			$data['error_type'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/rozetka_ec', 'token=' . $this->session->data['token'], true)
		);

		$data['action'] = $this->url->link('extension/module/rozetka_ec', 'token=' . $this->session->data['token'], true);

		$data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true);
		
		$parametrs = array(
			'login',
			'password',
			'total',
			'order_status_id',
			'order_fail_status_id',
			'order_success_hold_status_id',
			'order_refund_status_id',
			'order_post_pay_status_id',
			'status',
			'status_log',
			'button_color',
			'button_css',
			'button_cart',
			'button_cart_js',
			'position_button_cart_js',
			'button_product',
			'position_button_product_js',
			'button_product_js',
			'button_checkout_js',
			'button_checkout',
			'position_button_checkout_js'
		);
		
		foreach($parametrs as $parametr) {
			if (isset($this->request->post['rozetka_ec_' . $parametr])) {
				$data['rozetka_ec_' . $parametr] = $this->request->post['rozetka_ec_' . $parametr];
			} else {
				$data['rozetka_ec_' . $parametr] = $this->config->get('rozetka_ec_' . $parametr);
			}
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();
		
		// Log
		$file = DIR_LOGS . 'rozetka_ec.log';
		
		$data['log'] = '';
		$data['error_warning_log'] = '';
		
		if (file_exists($file)) {
			$size = filesize($file);

			if ($size >= 5242880) {
				$suffix = array(
					'B',
					'KB',
					'MB',
					'GB',
					'TB',
					'PB',
					'EB',
					'ZB',
					'YB'
				);

				$i = 0;

				while (($size / 1024) > 1) {
					$size = $size / 1024;
					$i++;
				}

				$data['error_warning_log'] = sprintf($this->language->get('error_warning'), basename($file), round(substr($size, 0, strpos($size, '.') + 4), 2) . $suffix[$i]);
			} else {
				$data['log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
			}
		}
		
		$data['positions'] = array(
			'before'	=> $this->language->get('text_before'),
			'append'	=> $this->language->get('text_append'),
			'after'		=> $this->language->get('text_after'),
		);
		
		if ($this->request->server['HTTPS']) {
			$data['server'] = HTTPS_CATALOG;
		} else {
			$data['server'] = HTTP_CATALOG;
		}

		$data['clear_log'] = $this->url->link('extension/module/rozetka_ec/clearlog', 'token=' . $this->session->data['token'], true);
		$data['download_log'] = $this->url->link('extension/module/rozetka_ec/downloadLog', 'token=' . $this->session->data['token'], true);
		$data['language_id'] = $this->config->get('config_language_id');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/rozetka_ec', $data));
	}
	
	/**
     * Очищення лог файлу
     */
	public function clearlog() {
		$handle = fopen(DIR_LOGS . 'rozetka_ec.log', 'w+');

		fclose($handle);

		$this->session->data['success'] = $this->language->get('text_success_log');

		$this->response->redirect($this->url->link('extension/module/rozetka_ec', 'token=' . $this->session->data['token'], true));
	}
	
	/**
     * Скачування лог файлу
     */
	public function downloadLog() {
		$file = DIR_LOGS . 'rozetka_ec.log';

		if (file_exists($file) && filesize($file) > 0) {
			$this->response->addheader('Pragma: public');
			$this->response->addheader('Expires: 0');
			$this->response->addheader('Content-Description: File Transfer');
			$this->response->addheader('Content-Type: application/octet-stream');
			$this->response->addheader('Content-Disposition: attachment; filename="' . $this->config->get('config_name') . '_' . date('Y-m-d_H-i-s', time()) . '_rozetka_ec.log"');
			$this->response->addheader('Content-Transfer-Encoding: binary');

			$this->response->setOutput(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
		}
	}
	
	 /**
     * Перевірка валідності введених даних
     *
     * @return bool Повертає true, якщо немає помилок, инакше false
     */
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/rozetka_ec')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['rozetka_ec_login']) {
			$this->error['login'] = $this->language->get('error_login');
		}

		if (!$this->request->post['rozetka_ec_password']) {
			$this->error['password'] = $this->language->get('error_password');
		}
		
		$this->load->model('localisation/currency');
		
		$currency_info = $this->model_localisation_currency->getCurrencyByCode('UAH');

		if(!$currency_info) {
			$this->error['warning'] = $this->language->get('error_currency_uah');
		}

		return !$this->error;
	}
	
	/**
     * Повторний виклик колбеку для замовлення
     *
     * @return string Повертає текст успішної відправки або текст помилки
     */
	public function getResendCallback() {
		$json = array();
		
		if(!empty($this->request->post['order_id'])) {
			$result = $this->convertToObjectArray($this->rpay->repeatCallback($this->request->post['order_id']));
			
			if(!empty($result[1])) {
				$json['error'] = sprintf($this->language->get('text_callback_error'), $result[1]['code'], $result[1]['message']);
			} else {
				$json['success'] = $this->language->get('text_callback_success');
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	/**
     * Запит інформації за платежем по API
     *
     * @return string Повертає html з результатами запиту
     */
	public function getInfoPayment() {
		$data['result'] = array();
		$data['token'] = $this->session->data['token'];
		
		if(isset($this->request->get['order_id'])) {
			
			$data['text_info_payment_id'] = $this->language->get('text_info_payment_id');
			$data['text_info_status'] = $this->language->get('text_info_status');
			$data['text_info_amount'] = $this->language->get('text_info_amount');
			$data['text_info_amount_final'] = $this->language->get('text_info_amount_final');
			$data['text_info_amount_refunded'] = $this->language->get('text_info_amount_refunded');
			$data['text_info_final_amount'] = $this->language->get('text_info_final_amount');
			$data['text_info_currency'] = $this->language->get('text_info_currency');
			$data['text_info_create_date'] = $this->language->get('text_info_create_date');
			$data['text_info_end_date'] = $this->language->get('text_info_end_date');
			$data['text_info_customer'] = $this->language->get('text_info_customer');
			$data['text_write_off'] = $this->language->get('text_write_off');
			$data['text_cancel_pay'] = $this->language->get('text_cancel_pay');
			$data['text_empty'] = $this->language->get('text_empty');
			
			$order_id = (int)$this->request->get['order_id'];
			
			$result = $this->convertToObjectArray($this->rpay->paymentInfo($order_id));

			if(!empty($result[0])) {
				$result_data = $result[0];
				
				//Log
				$this->setLog($result_data, 'Отримання інформації по платежу');
				
				$customer_rows = array('first_name', 'last_name', 'patronym', 'phone', 'email');
				
				$customer = array();
				
				foreach($result_data['customer'] as $key => $customer_) {
					if(in_array($key, $customer_rows) && !empty($customer_)) {
						$customer[] = $customer_;
					}
				}
				
				$status = $this->language->get('text_list_status_success');
				
				if($result_data['refunded']) {
					if($result_data['purchase_details'][0]['amount'] == $result_data['refund_details'][0]['amount']) {
						$status = $this->language->get('text_list_status_full_refund');
					} else {
						$status = $this->language->get('text_list_status_part_refund');
					}
				}
				
				$amount_final = 0;
				
				if($result_data['amount_refunded']) {
					$amount_final = $result_data['amount'] - $result_data['amount_refunded'];
				}
				
				$data['result'] = array(
					'order_id'			=> $order_id,
					'text_status'		=> $status,
					'status'				=> $result_data['purchase_details'][0]['status_code'],
					'failureReason'		=> !empty($result_data['canceled']) ? $result_data['cancellation_details'] : '',
					'amount'				=> $result_data['amount'],
					'amount_refunded'		=> $result_data['amount_refunded'],
					'amount_final'		=> $amount_final,
					'currency'			=> $result_data['currency'],
					'refunded'			=> $result_data['refunded'],
					'customer'			=> implode(" ", $customer),
					'createdDate'		=> date('Y-m-d H:i:s', strtotime($result_data['purchase_details'][0]['created_at'])),
					'modifiedDate'		=> date('Y-m-d H:i:s', strtotime($result_data['purchase_details'][0]['processed_at'])),
				);
			}
		}
		
		$this->response->setOutput($this->load->view('extension/module/rozetka_ec_info', $data));
	}
	
	/**
     * Запит на повне або часткове повернення
     *
	 * @param array $request Дані POST-запиту (асоціативний масив з ідентифікатором замовлення і сумою для повернення).
     * @return string Повертає текст успішного повернення або текст помилки
     */
	public function paymentRefund() {
		$json = array();
		
		if(!empty($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];
			
			if($order_id) {
				$this->load->model('sale/order');
				
				$order_info = $this->model_sale_order->getOrder($order_id);
			
				if($order_info) {
					$dataCheckout = new \RPayCheckoutCreatRequest();

					$dataCheckout->amount = $this->currency->format($this->request->get['amount'], $order_info['currency_code'], false, false);
					$dataCheckout->external_id = $order_id;
					$dataCheckout->currency = $order_info['currency_code'];
					
					if ($this->request->server['HTTPS']) {
						$server = HTTPS_CATALOG;
					} else {
						$server = HTTP_CATALOG;
					}
					
					$result = $this->convertToObjectArray($this->rpay->paymentRefund($dataCheckout));
					
					//Log
					$this->setLog($result, 'Повернення коштів');
					
					if(!empty($result[0]['is_success']) && $result[0]['is_success']) {
						$json['success'] = $this->language->get('text_success_refund');
					} elseif(!empty($result[1])) {						
						$json['error'] = sprintf($this->language->get('text_error_refund_detail'), $result[1]['message']);
					} else {						
						$json['error'] = $this->language->get('text_error_refund');
					}
				}
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
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
     * Інсталяція модуля
     */
	public function install() {
		$this->model_extension_module_rozetka_ec->install();
	}
	
	/**
     * Деінсталяція модуля
     */
	public function uninstall() {
		$this->model_extension_module_rozetka_ec->uninstall();
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
}