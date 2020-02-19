<?php
    
    /**
     * Plugin Name: NG WooCommerce Moedelo.org integration
     * Plugin URI: https://nikita.global
     * Description: Integrates WooCommerce and moedelo.org
     * Author: Nikita Menshutin
     * Version: 1.0
     * Text Domain: NGWMD
     * Domain Path: languages
     *
     * PHP version 7.2
     *
     * @category NikitaGlobal
     * @package  NikitaGlobal
     * @author   Nikita Menshutin <nikita@nikita.global>
     * @license  https://nikita.global commercial
     * @link     https://nikita.global
     * */
    defined('ABSPATH') or die("No script kiddies please!");
if (! class_exists("NGWMD")) {
    /**
         * Our main class goes here
         *
         * @category NikitaGlobal
         * @package  NikitaGlobal
         * @author   Nikita Menshutin <wpplugins@nikita.global>
         * @license  http://opensource.org/licenses/gpl-license.php GNU
         * @link     https://nikita.global
         */
    Class NGWMD
    {
            
        /**
             * Construct
             *
             * @return void
             **/
        public function __construct()
        {
            $this->prefix     = self::prefix();
            $this->version    = self::version();
            $this->methodname=self::methodname();
            $this->pluginName = __('NG WooCommerce Moedelo.org integration');
            $this->options    = get_option($this->prefix);
            load_plugin_textdomain(
                $this->prefix,
                false,
                $this->prefix . '/languages'
            );
            add_action('plugins_loaded', array($this, 'initPaymentMethod'));
            add_filter('woocommerce_payment_gateways', array($this, 'addMethod'));
            $this->settings = self::settings();
            $this->_metaboxProduct();
            add_action('init', array($this, 'trackPayments'));
        }
    
        /**
         * Загружаем класс оплаты
         *
         * @return void
         */
        public function initPaymentMethod()
        {
            include_once dirname(__FILE__) . '/includes/payment-moedelo.php';
        }
    
        /**
         * Добавляем метод оплаты
         *
         * @param array $methods методы
         *
         * @return array
         */
        public function addMethod($methods)
        {
            $methods[] = $this->methodname;
                
            return $methods;
        }
            
        /**
         * Create new fields and tabs
         * Main method
         *
         * @return void
         */
        private function _metaboxProduct()
        {
            //$this->_metaboxProduct();
            $this->_metaboxFields=array(
                'type'=>array(
                    'id'=>$this->prefix.'type',
                    'type'=>'select',
                    'label'=>__('Product type for moedelo.org', $this->prefix),
                    'options'=>array(
                        '1'=>__('Product', $this->prefix),
                        '2'=>__('Service', $this->prefix)
                    ),
                    'tab'=>'moedelo.org'
                ),
                'units'=>array(
                    'id'=>$this->prefix.'units',
                    'type'=>'text',
                    'label'=>__('Product units', $this->prefix),
                    'tab'=>'moedelo.org'
                ),
                'NdsType'=>array(
                    'id'=>$this->prefix.'NdsType',
                    
                )
            );
            add_filter(
                'woocommerce_product_data_tabs',
                array($this,'productTabs')
            );
            add_action(
                'woocommerce_product_data_panels',
                array($this,'metaboxProductInit')
            );
    
            add_action(
                'woocommerce_process_product_meta',
                array($this,'metaboxSaveFields'), 10,
                2 
            );
        }
    
        /**
         * Create new tabs
         *
         * @param array $tabs tabs
         *
         * @return array tabs
         */
        public function productTabs($tabs) 
        {
            $this->_metaboxToTabs=array();
            foreach ($this->_metaboxFields as $field) {
                if (!isset($field['tab'])) {
                    continue;
                }
                $tabid=$this->prefix.crc32($field['tab']);
                if (!isset($this->_metaboxToTabs)) {
                    $this->_metaboxToTabs=array();
                }
                $this->_metaboxToTabs[$tabid][]=$field;
                if (isset($tabs[$tabid])) {
                    continue;
                }
                $tabs[$tabid]=array(
                    'label'    => $field['tab'],
                    'target'   => $tabid.'_product_data',
                    //'class'    => array('show_if_virtual'),
                    'priority' => 21
                );
            }
            
            return $tabs;
        }
    
        /**
         * Init product metaboxes in the tabs
         *
         * @return void
         */
        public function metaboxProductInit()
        {
            foreach ($this->_metaboxToTabs as $tab=>$fields) {
                echo '<div id="'.$tab.'_product_data" class="panel '
                 .'woocommerce_options_panel hidden">';
                foreach ($fields as $field) {
                    $methodname='_makeMetabox'.$field['type'];
                    $this->{$methodname}($field);
                }
                echo '</div>';
            }
        }
    
        /**
         * Make select field for product metabox
         *
         * @param array $field field arguments
         *
         * @return void
         */
        private function _makeMetaboxSelect($field)
        {
            woocommerce_wp_select(
                array
                (
                'id'          => $field['id'],
                'value'       => get_post_meta(get_the_ID(), $field['id'], true),
                'wrapper_class' => isset(
                    $field['wrapper_class']
                ) ? $field['wrapper_class'] : '',
                'label'       => $field['label'],
                'options'     => $field['options']
                 ) 
            );
        }
    
        /**
         * Make input field for product metabox
         *
         * @param array $field field arguments
         *
         * @return void
         */
        private function _makeMetaboxText($field)
        {
            woocommerce_wp_text_input(
                array(
                'id'=>$field['id'],
                'value'       => get_post_meta(get_the_ID(), $field['id'], true),
                'wrapper_class' => isset(
                    $field['wrapper_class']
                ) ? $field['wrapper_class'] : '',
                'label'       => $field['label'],
                )
            );
        }
        
        /**
         * Save fields
         *
         * @param int $id   postid
         * @param any $post unused
         *
         * @return void
         */
        public function metaboxSaveFields($id,$post)
        {
            foreach ($this->_metaboxFields as $field) {
                if (!isset($_POST[$field['id']])) {
                    continue;
                }
                update_post_meta($id, $field['id'], $_POST[$field['id']]);
            }
        }
    
        /**
         * Здесь отправляем запросы
         * в API
         *
         * @param string $method GET|POST
         * @param string $args   аргументы
         *
         * @return mixed ответ API
         */
        public static function __callStatic($method, $args)
        {
            include_once dirname(__FILE__) . '/includes/api.php';
            $api = new ngmoedeloapi();
                
            return $api->{$method}($args);
        }
    
        /**
         * Настройки
         *
         * @return array
         */
        public static function settings()
        {
            return get_option('woocommerce_' . self::prefix() . '_settings');
        }
            
        /**
             * Method returns prefix
             *
             * @return string prefix
             **/
        public static function prefix()
        {
            return 'ngmoedelo';
        }
            
        /**
             * Method returns plugin version
             *
             * @return string version
             **/
        public static function version()
        {
            return '1.0';
        }
    
        /**
         * Название метода
         *
         * @return string
         */
        public static function methodname()
        {
            return 'WC_Gateway_Moedelo';
        }
        
        /**
         * Проверяем неоплаченыне счета
         * Если есть соответствующая настройка
         * Каждые 5 минут
         *
         * @return void
         */
        public function trackPayments()
        {
            if (!isset($this->settings['checkCovered']) 
                || $this->settings['checkCovered']!='yes'
            ) {
                
                return;
            }
            $transkey=$this->prefix.'check';
            if (get_transient($transkey)) {
                return;
            }
            $orders=wc_get_orders(
                array(
                'limit'=>-1,
                'status' => $this->settings['invoiceUnpaid'],
                'orderby' => 'rand',
                'payment_method' => $this->prefix
                )
            );
            foreach ($orders as $order) {
                $this->_trackPayment($order);
            }
        }
    
        /**
         * Проверить оплату заказа
         * Если оплачен, обновить статус
         *
         * @param object $order заказа
         *
         * @return void
         */
        private function _trackPayment($order) 
        {
            $trans=$this->prefix.crc32(serialize($order));
            if (get_transient($trans)) {
                return;
            }
            $billinfo=get_post_meta($order->get_id(), $this->prefix, true);
            
            if (!$billinfo) {
                return;
            }
            if (!isset($billinfo['Id'])) {
                return;
            }
            $billid=$billinfo['Id'];
            $remotebill=self::get('/accounting/api/v1/sales/bill/'.$billid);
            if ($remotebill['Status']==6) {
                $order->update_status($this->settings['invoicePaid']);
            }
            set_transient($trans, true, 60*5);
        }
   
        public static function settingsItemFieldValues($field)
        {
            $fields=array(
                'NdsPositionType'=>array(
                    'title'=>__('VAT', self::prefix()),
                    'type'=>'select',
                    'default'=>'1',
                    'options'=>array(
                        '1'=>__('Do not implement', self::prefix()),
                        '2'=>__('Above', self::prefix()),
                        '3'=>__('Include', self::prefix())
                    )
                ),
                'defaultProductNdsType'=>array(
                    'title'=>__('Default VAT rate', self::prefix()),
                    'type'=>'select',
                    'default'=>'1',
                    'options'=>array(
                        '1'=>__('No VAT', self::prefix()),
                        '0'=>__('VAT 0%', self::prefix()),
                        '18'=>__('VAT 18%', self::prefix())
                    ),
                )
            );
            return $fields[$field];
        }
        
        /**
         * Для логов - часть вывода к
         * ласса и метода/функции
         *
         * @param object $debug debug_backtrace
         *
         * @return string
         */
        private static function _logString($debug)
        {
            $out = implode(
                '', array(
                    $debug['class'],
                    $debug['type'],
                    $debug['function']
                )
            );
        
            return $out;
        }
    }
}
    new NGWMD();
