<?php

    /**
     * CdekpaySDK.
     *
     * @class       CdekpaySDK
     * @version     1.0.0
     */
    class CdekpaySDK {

        private const CDEKPAY_API_HOST = 'https://secure.cdekfin.ru';
        private const CDEKPAY_FAST_PAY_URL = 'https://secure.cdekfin.ru/fast_pay/';

        private const CDEKPAY_API_ROUTES = [
            // init of payment
            'init_payment' => [
                'test' => '/test_merchant_api/payment_orders',
                'prod' => '/merchant_api/payment_orders',
            ],

            // get_payments
            'get_payments' => [
                'test' => '/test_merchant_api/payments',
                'prod' => '/merchant_api/payments',
            ],

            // init refund of payment
            'init_refund' => [
                'test' => null, // отсутствует в API, use only prod
                'prod' => '/merchant_api/cancellation_requests',
            ],
        ];

        /**
         * undefined - не оплачен
         * success - оплачен
         * cancellation_requested - запрошен возврат оплаты клиенту
         * success_cancellation - успешно инициирован возврат оплаты клиенту
         * cancelled - оплата возвращена клиенту
         * voided - аннулирован, т.к не был вовремя оплачен
         */
        private const CDEKPAY_PAYMENT_STATUSES = [
            'undefined' => 0,
            'success' => 1,
            'cancellation_requested' => 2,
            'success_cancellation' => 3,
            'cancelled' => 4,
            'voided' => 5,
        ];

        private const CDEKPAY_CURRENCY = [
            'test' => 'TST',
            'prod' => 'RUR',
        ];

        private string $merchant;
        private array  $secret;
        private bool   $mode_test;
        private array  $accounts_test;
        private array  $order_statuses;

        /**
         * @param array $settings
         *
         * @param array $order_statuses
         * $order_statuses = [
         * 'pending'        => $CMSOrderStatus->status_pending,
         * 'paid'           => $CMSOrderStatus->status_paid,
         * 'refund'         => $CMSOrderStatus->status_refund,
         * 'refunded'       => $CMSOrderStatus->status_cancel_request,
         * 'void'           => $CMSOrderStatus->status_void,
         * ];
         */
        public function __construct(array $settings = [], array $order_statuses = [])
        {
            $settings = (object)$settings;

            $this->setMerchant($settings->merchant_login);
            $this->setModeTest($settings->mode_test);
            $this->setSecret($settings->secret_key, $settings->secret_key_test);
            $this->setAllowedAccounts($settings->accounts_test);
            $this->setOrderStatuses($order_statuses);
        }

        public function initRefundPayment($order_id,$payment_id, $refund_amount,$products =[]): array
        {
            $data = [
                'data' => [
                    'payment_id' => $payment_id,
                    'value_refund' => $refund_amount,
                    'reason' => __('Order return','cdekpay')
                ],
            ];

            if($products){
                $data['data']['receipt_details'] = $products;
            }

            $data['signature'] = self::signWithSignature($data,  $this->getSecret(true));
            $data['login'] = $this->getMerchant();

	        $paymentsSums = $this->getPaymentSums($order_id);

	        if($paymentsSums['available'] < $data['data']['value_refund']){
				return [
					"error" => esc_html(__( 'Refunding not accepted. Check sums', 'cdekpay' )),
					"status" => '302',
				];
	        }

            $url = $this->getAPIUrlInitRefund();

            return self::sendPost($url, $data);
        }
		/*
		 * @return array 'available' - сумма, доступная к возврату 'paid' - сумма оплаченных платежей, 'refund' - сумма возвращенных платежей включая замороженных платежей
		 */
		private function getPaymentSums($order_id):array
		{
			$result = [];
			global $wpdb;
			$cdekpay_order = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}cdekpay_orders WHERE order_id = %s",$order_id ));

			$payments =$this->getPaymentsBy($cdekpay_order->cdekpay_order_id);

			foreach($payments['payments'] as $payment){
				if($payment['pay_amount'] < 0 && !in_array($payment['status'],['system_error','failed','undefined'])){
					$result['refund'] += abs($payment['pay_amount']);
				}
				else if($payment['pay_amount'] > 0){
					$result['paid'] += $payment['pay_amount'];
				}
			}
			$result['available'] = $result['paid'] - $result['refund'];

			return $result;
		}

	    public function getPaymentsBy($order_id,$page = 1, $per_page = 50,$sortBy = 'id', $sortDirection = 'ASC'): array
	    {
		    $data = [
			    'p' => [
				    'page' => $page,
				    'per_page' => $per_page,
			    ],
			    'o' => [
				    'column' => $sortBy,
				    'direction' => $sortDirection,
			    ],
			    'q' => [
				    'access_key' => $order_id,
			    ],
		    ];

		    $data['signature'] = self::signWithSignature($data,  $this->getSecret());
		    $data['login'] = $this->getMerchant();

		    $url = $this->getAPIUrlPayments() . '?' . http_build_query($data);

		    return self::sendGet($url);

	    }


	    public function getPayments($order_id): array
        {
            $data = [
                'p' => [
                    'page' => 1,
                    'per_page' => 50,
                ],
                'o' => [
                    'column' => 'id',
                    'direction' => 'ASC',
                ],
                'q' => [
                    'access_key' => $order_id,
                ],
            ];

            $data['signature'] = self::signWithSignature($data,  $this->getSecret());
            $data['login'] = $this->getMerchant();

            $url = $this->getAPIUrlPayments() . '?' . http_build_query($data);

            return self::sendGet($url);
        }

        public function initPayment(
            float  $total_price,
            int    $order_number,
            string $client_phone,
            string $client_email,
            string $url_success,
            string $url_fail,
	        array $paymentItems = []
        ): array
        {
            $data = [
                'login' => $this->getMerchant(),
                'payment_order' => [
                    'pay_amount' => self::convertRublesInPennies($total_price), // сумма заказа в копейках
                    'pay_for'    => (string)$order_number,
                    'user_phone' => $client_phone,
                    'user_email' => $client_email,
                    'currency'   => $this->getCurrency(),
                    'return_url_success' => $url_success,
                    'return_url_fail'    => $url_fail,
                    'pay_for_details'    => [
                        'payment_type' => '',
                        'payment_base_type' => '',
                        'payment_base' => '',
                        'payment_id' => '',
                    ],
                ],
            ];

			if(!empty($paymentItems)){
				$data['payment_order']['receipt_details'] = $paymentItems;
			}
			else {
				$data['payment_order']['payment_object'] = 1;
			}

            $data['signature'] = self::signWithSignature($data['payment_order'], $this->getSecret());
	        //Cdekpay_Logger::info('$data: '.json_encode($data) );
            $url = $this->getAPIUrlInitPayment();

            return self::sendPost($url, $data);
        }

        public function getOrderStatuses(): object
        {
            return (object)$this->order_statuses;
        }

        public function getPaymentStatuses(): array
        {
            return self::CDEKPAY_PAYMENT_STATUSES;
        }

        public function getPaymentUrlByOrderId(int $payment_order_id): string
        {
            return self::CDEKPAY_FAST_PAY_URL.$payment_order_id ;
        }

        public function isAcceptedSignature($data)
        {
            $signature = self::signWithSignature($data['payment'], $this->getSecret());

            if ($signature === $data['signature']) {
                return true;
            }

            return false;
        }

        public function isAllowedAccount($email): bool
        {
            if (in_array($email, $this->accounts_test)) {
                return true;
            }

            return false;
        }

        public function getMerchant(): string
        {
            return $this->merchant;
        }

        public function getSecret($onlyProd = false): string
        {
            return $secret = ($this->getModeTest() && !$onlyProd) ? ($this->secret['test']) : ($this->secret['prod']);
        }

        public function getModeTest(): bool
        {
            return $this->mode_test;
        }

        public function getCurrency(): string
        {
            return $this->getModeTest() ? self::CDEKPAY_CURRENCY['test'] : self::CDEKPAY_CURRENCY['prod'];
        }

        public static function convertPenniesInRubles($sum): int
        {
            return intval(round($sum / 100));
        }

        public static function convertRublesInPennies($sum): int
        {
            return intval(round($sum * 100));
        }

        private function getAPIUrlInitRefund(): string
        {
            return $this->getAPIRoute('init_refund', true);
        }

        private function getAPIUrlPayments(): string
        {
            return $this->getAPIRoute('get_payments');
        }

        private function getAPIUrlInitPayment(): string
        {
            return $this->getAPIRoute('init_payment');
        }

        private function getAPIRoute($key, $onlyProd = false)
        {
            $mode = ($this->getModeTest() && !$onlyProd) ? 'test' : 'prod';
            $route = self::CDEKPAY_API_ROUTES[$key][$mode];

            return self::getAPIHost() . $route;
        }

        private function setMerchant(string $merchant_login): void
        {
            $this->merchant = $merchant_login;
        }

        private function setModeTest(bool $mode_test): void
        {
            $this->mode_test = $mode_test;
        }

        private function setSecret(string $secret_key, string $secret_key_test): void
        {
            $this->secret = [
                'prod' => $secret_key,
                'test' => $secret_key_test,
            ];
        }

        private function setAllowedAccounts(string $accounts_test): void
        {
            $this->accounts_test = explode(' ', $accounts_test);
        }

        private function setOrderStatuses($order_statuses): void
        {
            $this->order_statuses = $order_statuses;
        }

        private static function getAPIHost(): string
        {
            return self::CDEKPAY_API_HOST;
        }

        private static function sendGet($url): array
        {

            $response = wp_remote_get( $url, array(

                'httpversion' => '1.0',
                'headers'     => array(),
                'body'        => null,
            ) );

            return self::analiseResponseFromCdekPayServer($response);
        }

        private static function sendPost($url, $data): array
        {

            $response = wp_remote_post( $url, array(
                'headers'     => array('Content-Type'=> 'application/json; charset=utf-8'),
                'method' => 'POST',
                'sslverify' => false,
                'cookies' => array(),
                'body'        =>  wp_json_encode($data)
            ) );Cdekpay_Logger::info('$response: '.json_encode($response) );
            return self::analiseResponseFromCdekPayServer($response);
        }

        private static function analiseResponseFromCdekPayServer($response): array
        {
            $cdekPayResponse = [];
            $msg = 'HTTP_BAD_REQUEST #APICDK-';
			$error = false;
            $status_code = $response['response']['code'] ?? null;

            switch ($status_code) {
                case 200:
                    if (isset($response['body'])) {
                        $cdekPayResponse =json_decode($response['body'], true);
                    }
                    else {
                        $msg = $msg."002 - error_unknown_answer (200)";
                    }

                    break;

                case 403:
                    $msg = $msg."003 (403)";
	                $error = true;
                    break;

                case 422:
                    $msg = $msg."004 (422)";
	                $error = true;
                    break;

                default:
                    $msg = $msg."005 - error_unknown";
	                $error = true;
                    break;
            }

            if ($error) {
                return [
                    "error" => $msg,
                    "status" => $status_code,
                ];
            }

            return $cdekPayResponse;
        }


        private static function signWithSignature($data, $secret_key): string
        {
            $str = self::concatString($data);

            return strtoupper(hash('sha256', $str . $secret_key));
        }

        private static function concatString($data): string
        {
            ksort($data);

            $str = '';
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $str .= self::concatString($value);
                } else if (is_scalar($value)) {
                    $str .= $value.'|';
                }
            }

            return $str;
        }
    }

