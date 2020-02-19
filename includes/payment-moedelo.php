<?php
    
    /**
     * Class WC_Gateway_Moedelo
     *
     * PHP version 7.2
     *
     * @category NikitaGlobal
     * @package  NikitaGlobal
     * @author   Nikita Menshutin <nikita@nikita.global>
     * @license  https://nikita.global commercial
     * @link     https://nikita.global
     * */
class WC_Gateway_Moedelo extends WC_Payment_Gateway
{
    /**
     * WC_Gateway_Moedelo constructor.
     *
     * @category NikitaGlobal
     * @package  NikitaGlobal
     * @author   Nikita Menshutin <wpplugins@nikita.global>
     * @license  http://opensource.org/licenses/gpl-license.php GNU
     * @link     https://nikita.global
     */
    function __construct()
    {
            
        $this->id      = NGWMD::prefix();
        $this->prefix  = $this->id;
        $this->version = NGWMD::version();
        //$this->icon='';
        $this->has_fields         = true;
        $this->method_title       = __('Moedelo.org', $this->prefix);
        $this->method_description = __(
            'Invoicing via https://moedelo.org',
            $this->prefix
        );
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
    
    /**
     * Настройки платежа
     *
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'             => array(
                'title'   => __('Enable/Disable', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable Cheque Payment', 'woocommerce'),
                'default' => 'no'
            ),
            'title'               => array(
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'This controls the title which the user sees during checkout.',
                    'woocommerce'
                ),
                'default'     => __('Invoice for bank transfer', $this->prefix),
                'desc_tip'    => true,
            ),
            'invoiceType'=>array(
                'title'=>__('Invoice type', $this->prefix),
                'type'=>'select',
                'default'=>'1',
                'options'=>array(
                    '1'=>__('Simple', $this->prefix),
                    '2'=>__("Contract", $this->prefix)
                )
            ),
            'NdsPositionType'=>NGWMD::settingsItemFieldValues(
                'NdsPositionType'
            ),
            'defaultProductNdsType'=>NGWMD::settingsItemFieldValues(
                'defaultProductNdsType'
            ),
            'description'         => array(
                'title'   => __('Customer Message', 'woocommerce'),
                'type'    => 'textarea',
                'default' => ''
            ),
            'invoiceUnpaid'       => array(
                'title'   => __('Order status after bill is issued'),
                'type'    => 'select',
                'options' => $this->_getOrderStatuses()
            ),
            'invoicePaid'         => array(
                'title'   => __('Order status after bill is paid'),
                'type'    => 'select',
                'options' => $this->_getOrderStatuses()
            ),
            'defaultProducttype'  => array(
                'title'   => __('Default item type is', $this->prefix),
                'type'    => 'select',
                'options' => array(
                    '1' => 'product',
                    '2' => 'service'
                ),
                'default' => 1
            ),
            'defaultProductunits' => array(
                'title'   => __('Default item units are', $this->prefix),
                'type'    => 'text',
                'default' => 'pcs'
            ),
            'checkCovered'        => array(
                'title'       => __('Check if bill is covered', $this->prefix),
                'description' => __(
                    'Will check every 5 minutes via moedelo API if bill is covered'
                ),
                'type'        => 'checkbox',
                'default'     => 'no'
            ),
            'apikey'              => array(
                'title'       => __('moedelo.org api key'),
                'type'        => 'text',
                'description' => __(
                    'Can be found in moedelo.org dashboard -> partners\' integration'
                )
            )
        );
    }
    
    /**
     * Поля для оплаты
     * при оформлении заказа.
     * Ввод ИНН
     *
     * @return void
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        echo '<fieldset id="wc-' .
             esc_attr($this->id) .
             '-cc-form" class="wc-credit-card-form wc-payment-form" '.
             'style="background:transparent;">';
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
    
    /**
     * Обработка платежа
     * Получаем контрагента,
     * Создаем счет
     *
     * @param int $orderId номер заказа
     *
     * @return array
     */
    public function process_payment($orderId)
    {
        $order = wc_get_order($orderId);
        //$fields=WC()->session->get($this->prefix);
            
        $bill = array(
            'KontragentId'   => NGWMD::getCompanyByINN(
                (int)$_POST[
                $this->prefix . 'inn'
                ]
            ),
            'AdditionalInfo' => __('Order #', $this->prefix) . $orderId,
            'Sum'            => $order->get_total(),
            'NdsPositionType'=> $this->get_option('NdsPositionType'),
            'Type'           => $this->get_option('InvoiceType'),
            'items'          => $this->_orderItems($order)
        );
        $bill = NGWMD::postBill($bill);
        if (! isset($bill['Number']) || ! isset($bill['Online'])) {
            return;
        }
        $order->payment_complete();
        WC()->cart->empty_cart();
        update_post_meta($orderId, $this->prefix . 'bill', $bill['Number']);
        update_post_meta($orderId, $this->prefix, $bill);
        $order->add_order_note(
            __(
                'Bill id',
                $this->prefix
            ) . ' ' . $bill['Number'], true
        );
        $order->add_order_note(
            __(
                'Invoice is available here :',
                $this->prefix
            ) . ' ' . 'https://moedelo.org/' . $bill['Online']
        );
        $order->set_customer_note(
            __(
                'Invoice is available here :',
                $this->prefix
            ) . ' <a target="_blank" href="' . 'https://moedelo.org/' . $bill['Online'].'">moedelo.org'.
            '</a>'
        );
        $order->update_status($this->get_option('invoiceUnpaid'));
            
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
    
    /**
     * Создаем массив товаров
     * для заказа
     *
     * @param array $order заказ
     *
     * @return array
     */
    private function _orderItems($order)
    {
        $items       = array();
        $order_items = $order->get_items(array('line_item', 'fee', 'shipping'));
        foreach ($order_items as $item_id => $order_item) {
            $items[] = array(
                'Name'  => $order_item->get_name(),
                'Type'  => (string)$this->_orderItemGetValue(
                    new
                    WC_Order_Item_Product($item_id), 'type'
                ),
                'Count' => $order_item->get_quantity(),
                'Price' => $order_item->get_total() / $order_item->get_quantity(),
                'Unit'  => (string)$this->_orderItemGetValue(
                    new
                    WC_Order_Item_Product($item_id), 'units'
                )
            );
        }
            
        return $items;
    }
    
    /**
     * Получаем необходимую
     * мета-запись товара
     *
     * @param object $product товар
     * @param string $fieldid ключ мета
     *
     * @return any
     */
    private function _orderItemGetValue($product, $fieldid)
    {
        $productid = $product->get_product_id();
        $value = get_post_meta(
            $productid,
            $this->prefix . $fieldid,
            true
        );
        if (! $value) {
            return $this->get_option('defaultProduct' . $fieldid);
        }
            
                return $value;
    }
    
    /**
     * Проверяем поля
     * при оформлении заказа
     *
     * @return bool
     */
    public function validate_fields()
    {
        foreach ($this->frontEndFields as $field) {
            if (isset($field['required'])
                && $field['required']
                && empty($_POST[$this->prefix . $field['name']])
            ) {
                $field_name = __($field['label'], $this->prefix);
                $field_key  = $this->prefix . $field['name'];
                wc_add_notice(
                    sprintf(
                        __(
                            '%s is a required field.',
                            'woocommerce'
                        ),
                        '<strong>' . esc_html($field_name) . '</strong>'
                    ), 'error',
                    array('id' => $field_key)
                );
                continue;
            }
            $method = '_validateField' . $field['name'];
            if (! method_exists($this, $method)) {
                continue;
            }
                
            return $this->{$method}($field);
        }
        return true;
    }
    
    /**
     * Получаем все статусы заказов,
     * которые есть у Woocommerce
     *
     * @return array
     */
    private function _getOrderStatuses()
    {
        $statuses = array();
        foreach (wc_get_order_statuses() as $k => $v) {
            $statuses[str_replace('wc_', '', $k)] = $v;
        }
            
        return $statuses;
    }
    
    /**
     * Проверка поля ИНН
     * при оформлении заказа
     *
     * @param array $field поле
     *
     * @return bool
     */
    private function _validateFieldINN($field)
    {
        if (! NGWMD::isCompany($field['value'])) {
            wc_add_notice(
                __('Please recheck INN', $this->prefix), 'error',
                array('id' => $this->prefix . $field['name'])
            );
                
            return false;
        }
        return true;
    }
    
    /**
     * Проставляем значения для полей
     * при оформлении заказа
     *
     * @return void
     */
    private function _prefillValues()
    {
        if (! isset($_POST['payment_method'])
            || ($_POST['payment_method'] != $this->prefix)
        ) {
            return;
        }
        foreach ($this->frontEndFields as $key => $field) {
            $method = '_prefillValue' . $field['name'];
            if (isset($_POST[$this->prefix . $field['name']])) {
                $this->frontEndFields[$key]['value'] = $_POST[$this->prefix .
                                                              $field['name']];
            }
            if (method_exists($this, $method)) {
                $this->frontEndFields[$key]['value'] = $this->{$method}($key);
            }
        }
        WC()->session->set($this->prefix, $this->frontEndFields);
    }
    
    /**
     * Делаем ряд для таблицы
     * оформления заказа
     *
     * @param string $row ряд
     *
     * @return string
     */
    private function _feMakeRow($row)
    {
        return '<div class="form-row">' . $row . '</div>';
    }
    
    /**
     * Делаем поле для таблицы
     * оформления заказа
     *
     * @param array $field поле
     *
     * @return string html
     */
    private function _feMakeField($field)
    {
        if (! isset($field['type'])) {
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
        if (! isset($field['value'])) {
            $field['value'] = '';
        }
        if (! method_exists($this, $method)) {
            $out .= '<input type="' . $field['type'] . '" name="' .
                    $this->prefix . $field['name'] . '" value="'
                    . $field['value'] . '" id="' .
                    $this->prefix . $field['name'] . '"';
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
