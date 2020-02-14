<?php
    
    Class ngmoedeloapi
    {
        public function __construct()
        {
            $this->prefix   = NGWooMoeDeloOrg::prefix();
            $this->version  = NGWooMoeDeloOrg::version();
            $this->host     = 'https://restapi.moedelo.org';
            $this->settings = NGWooMoeDeloOrg::settings();
        }
        
        public function isCompany($inn)
        {
            $res = $this->get(
                array(
                    '/kontragents/api/v1/kontragent',
                    array('inn' => (int)$inn[0])
                ));
            if ($res && isset($res[0]['Form']) && in_array($res[0]['Form'], array(1, 2))) {
                return true;
            }
            $res = $this->post(array('/kontragents/api/v1/kontragent/inn', array('inn' => (int)$inn[0])));
            if ($res && isset($res['Form']) && in_array($res['Form'], array(1, 2))) {
                return true;
            }
            if ($res && isset($res['Form']) && $res['Form']==3) {
                wc_add_notice(__('Personal account'), true);
            }
            return false;
        }
        
        public function getCompanyByINN($inn)
        {
            $res = $this->get(
                array(
                    '/kontragents/api/v1/kontragent',
                    array('inn' => (int)$inn[0])
                ));
            return (int)$res[0]['Id'];
        }
        
        public function postBill($bill)
        {
            $billDefaults=array(
                'DocDate'=>date('Y-m-d'),
                'items'=>false
            );
            $bill=array_merge($billDefaults,$bill[0]);
            error_log(print_r($bill,true));
            $res=$this->post(array('/accounting/api/v1/sales/bill', $bill));
            error_log(print_r($res,true));
        }
        
        public
        function post(
            $args
        ) {
            $args                    = self::_prepareArgs($args);
            $host                    = $this->host . $args[0];
            $headers                 = $this->_prepareHeaders();
            $headers['Content-Type'] = 'application/json';
            $req                     = array(
                'headers' => $headers,
                'method'  => 'POST',
                'body'    => json_encode($args[1])
            );
            $res = wp_remote_request($host,
                $req
            );
            if (isset($res['ValidationErrors']) || is_wp_error($res)) {
                error_log('post error');
                error_log(print_r($res,true));
                return false;
            }
            $result = json_decode(wp_remote_retrieve_body($res), true);
            return $result;
        }
        
        public
        function get(
            $args
        ) {
            $args = self::_prepareArgs($args);
            $host = add_query_arg($args[1], $this->host . $args[0]);
            $res  = wp_remote_request($host,
                array(
                    'headers' => $this->_prepareHeaders(),
                    'method'  => 'GET'
                )
            );
            if (is_wp_error($res)) {
                return false;
            }
            $res = json_decode(wp_remote_retrieve_body($res), true);
            if ( ! isset($res['ResourceList'])) {
                return false;
            }
            if ( ! isset($res['ResourceList'][0])) {
                return false;
            }
            
            return $res['ResourceList'];
        }
        
        private
        function _prepareHeaders()
        {
            return array(
                'Accept'     => 'application/json',
                'md-api-key' => $this->settings['apikey']
            );
        }
        
        private
        static function _prepareArgs(
            $args
        ) {
            $path   = $args[0];
            $params = array();
            if (isset($args[1])) {
                $params = $args[1];
            }
            
            return array($path, $params);
        }
    }