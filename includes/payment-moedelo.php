<?php
    
    class WC_Gateway_Moedelo extends WC_Payment_Gateway
    {
        function __construct()
        {
            
            $this->id      = NGWooMoeDeloOrg::prefix();
            $this->prefix  = $this->id;
            $this->version = NGWooMoeDeloOrg::version();
            //$this->icon='';
            $this->has_fields         = true;
            $this->method_title       = __('Moedelo.org', $this->prefix);
            $this->method_description = __('Invoicing via https://moedelo.org', $this->prefix);
            $this->supports           = array(
                'products'
            );
            $this->init_form_fields();
            $this->init_settings();
            $this->title       = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled     = $this->get_option('enabled');
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array($this, 'process_admin_options')
            );
            //add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
            $this->frontEndFields = array(
                'inn' => array(
                    'type'     => 'text',
                    'name'     => 'inn',
                    'label'    => __('Your company INN', $this->prefix),
                    'required' => true
                ),
                /*    'company' => array(
                        'type'     => 'text',
                        'name'     => 'company',
                        'label'    => __('Company name', $this->prefix),
                        'disabled' => true
                    )*/
            );
            $this->_prefillValues();
        }
        
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled'     => array(
                    'title'   => __('Enable/Disable', 'woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Cheque Payment', 'woocommerce'),
                    'default' => 'no'
                ),
                'title'       => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default'     => __('Invoice for bank transfer', $this->prefix),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'   => __('Customer Message', 'woocommerce'),
                    'type'    => 'textarea',
                    'default' => ''
                ),
                'defaultProductTypeIs'=>array(
                    'title'=>__('Default item type is', $this->prefix),
                    'type'=>'select',
                    'options'=>array(
                        '1'=>'product',
                        '2'=>'service'
                    ),
                    'default'=>1
                ),
                'checkCovered'=>array(
                    'title'=>__('Check if bill is covered', $this->prefix),
                    'description'=>__('Will check every 5 minutes via moedelo API if bill is covered'),
                    'type'=>'checkbox',
                    'default'=>'no'
                ),
                'apikey'      => array(
                    'title'       => __('moedelo.org api key'),
                    'type'        => 'text',
                    'description' => __('Can be found in moedelo.org dashboard -> partners\' integration')
                )
            );
        }
        
        public function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
            do_action('woocommerce_credit_card_form_start', $this->id);
            foreach ($this->frontEndFields as $field) {
                echo $this->_feMakeRow($this->_femakeField($field));
            }
            echo '<div class="clear"></div>';
            echo '</fieldset>';
            do_action('woocommerce_credit_card_form_end', $this->id);
            echo '<div class="clear"></div></fieldset>';
            do_action('woocommerce_credit_card_form_end', $this->id);
            echo '<div class="clear"></div></fieldset>';
        }
    
        public function process_payment( $orderId ) {
            $order=wc_get_order($orderId);
            //$fields=WC()->session->get($this->prefix);
            
            $bill=array(
                'KontragentId'=>NGWOOMoeDeloOrg::getCompanyByINN((int)$_POST[$this->prefix.'inn']),
                'AdditionalInfo'=>__('Order #',$this->prefix).$orderId,
                'Sum'=>$order->get_total(),
                'Type'=>1,//@Todo сделать вариант счета
                'items'=>$this->_orderItems($order)
            );
            NGWOOMoeDeloOrg::postBill($bill);
        }
        
        private function _orderItems($order) {
            $items=array();
            $order_items = $order->get_items( array('line_item', 'fee', 'shipping') );
            foreach( $order_items as $item_id => $order_item ) {
                $items[]=array(
                    'Name'=>$order_item->get_name(),
                    'Type'=>(string)$this->_orderItemType(new WC_Order_Item_Product($item_id)),
                    'Count'=>$order_item->get_quantity(),
                    'Price'=>$order_item->get_total(),
                    'Unit'=>'шт.'
                );
            }
            return $items;
        }
        
        private function _orderItemType($product){
            return 1;
        }
        
        public function validate_fields()
        {
            foreach ($this->frontEndFields as $field) {
                if (isset($field['required']) && $field['required'] && empty($_POST[$this->prefix . $field['name']])) {
                    $field_name = __($field['label'], $this->prefix);
                    $field_key  = $this->prefix . $field['name'];
                    wc_add_notice(sprintf(__('%s is a required field.', 'woocommerce'),
                        '<strong>' . esc_html($field_name) . '</strong>'), 'error', array('id' => $field_key));
                    continue;
                }
                $method = '_validateField' . $field['name'];
                if ( ! method_exists($this, $method)) {
                    continue;
                }
                return $this->{$method}($field);
            }
            return true;
        }
        
        private function _validateFieldINN($field)
        {
            if (!NGWOOMoeDeloOrg::isCompany($field['value'])) {
                wc_add_notice(__('Please recheck INN', $this->prefix),'error', array('id'=>$this->prefix.$field['name']));
                return false;
            }
            return true;
        }
        
        private function _prefillValues()
        {
            if (!class_exists('WC')) {
                return;
            }
            if (!is_callable(WC()->session->set())) {
                return;
            }
            foreach ($this->frontEndFields as $key => $field) {
                $method = '_prefillValue' . $field['name'];
                if (isset($_POST[$this->prefix . $field['name']])) {
                    $this->frontEndFields[$key]['value'] = $_POST[$this->prefix . $field['name']];
                }
                if (method_exists($this, $method)) {
                    $this->frontEndFields[$key]['value'] = $this->{$method}($key);
                }
            }
            WC()->session->set($this->prefix, $this->frontEndFields);
        }
        
        private function _feMakeRow($row)
        {
            return '<div class="form-row">' . $row . '</div>';
        }
        
        private function _feMakeField($field)
        {
            if ( ! isset($field['type'])) {
                $field['type'] = 'text';
            }
            $out      = '';
            $method   = '_feMakeField' . $field['type'];
            $out      .= '<label for="' . $this->prefix . $field['name'] . '">';
            $out      .= $field['label'];
            $required = false;
            if (isset($field['required']) && $field['required']) {
                $required = true;
            }
            if ($required) {
                $out .= ' <span class="required">*</span>';
            }
            if ( ! isset($field['value'])) {
                $field['value'] = '';
            }
            if ( ! method_exists($this, $method)) {
                $out .= '<input type="' . $field['type'] . '" name="' .
                        $this->prefix . $field['name'] . '" value="'
                        . $field['value'] . '" id="' . $this->prefix . $field['name'] . '"';
                if ($required) {
                    $out .= ' required';
                }
                if (isset($field['disabled']) && $field['disabled']) {
                    $out .= ' disabled';
                }
                $out .= '>';
            } else {
                $out .= $this->{$method}($field);
            }
            $out .= '</label>';
            
            return $out;
        }
    }
