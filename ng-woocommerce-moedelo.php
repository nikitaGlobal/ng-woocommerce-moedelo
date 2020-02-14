<?php
    
    /**
     * Plugin Name: NG WooCommerce Moedelo.org integration
     * Plugin URI: https://nikita.global
     * Description: Integrates WooCommerce and moedelo.org
     * Author: Nikita Menshutin
     * Version: 1.0
     * Text Domain: NGWooMoeDeloOrg
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
if (! class_exists("NGWooMoeDeloOrg")) {
    /**
         * Our main class goes here
         *
         * @category NikitaGlobal
         * @package  NikitaGlobal
         * @author   Nikita Menshutin <wpplugins@nikita.global>
         * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
         * @link     https://nikita.global
         */
    Class NGWOOMoeDeloOrg
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
                
            return;
            $this->settings = array(
                'set1'      => array(
                    'key'         => 'set1',
                    'title'       => __('set1'),
                    'placeholder' => __('placeholder'),
                    'group'       => __('group1'),
                    'type'        => 'text',
                    'required'    => true,
                    'default'     => 'default'
                ),
                'set2'      => array(
                    'key'         => 'set2',
                    'title'       => __('set2'),
                    'group'       => 'group2',
                    'type'        => 'text',
                    'placeholder' => __('placeholder 2'),
                    'default'     => 'default 2'
                ),
                'checkbox1' => array(
                    'key'     => 'checkbox1',
                    'title'   => __('checkbox field'),
                    'group'   => __('group1'),
                    'type'    => 'checkbox',
                    'default' => false
                ),
                'select1'   => array(
                    'key'     => 'select1',
                    'title'   => __('select field'),
                    'group'   => 'group1',
                    'type'    => 'select',
                    'default' => false,
                    'args'    => array(
                        'none'      => 'Do not filter',
                        'subject'   => 'Send subject only',
                        'html'      => 'Send as html',
                        'short'     => 'Cut long message',
                        'stripTags' => 'Strip html tags',
                        'file'      => 'Send file'
                    )
                )
            );
            foreach ($this->settings as $k => $setting) {
                if (isset($this->options[$setting['key']])) {
                    $this->settings[$k]['value'] = $this->options[$setting['key']];
                }
            }
            add_action('wp_enqueue_scripts', array($this, 'scripts'));
            add_action('admin_menu', array($this, 'menuItemAdd'));
            add_action('admin_init', array($this, 'settingsRegister'));
            add_filter(
                'plugin_action_links',
                array(
                    $this,
                    'pluginActionLinks'
                ), 10, 2
            );
        }
            
        public function initPaymentMethod()
        {
            include_once dirname(__FILE__) . '/includes/payment-moedelo.php';
        }
            
        public function addMethod($methods)
        {
            $methods[] = 'WC_Gateway_Moedelo';
                
            return $methods;
        }
            
        /**
             * Register settings
             *
             * @return void
             **/
        public function settingsRegister()
        {
            register_setting($this->prefix, $this->prefix);
            $groups = array();
            foreach ($this->settings as $settingname => $array) {
                if (! in_array($array['group'], $groups)) {
                    add_settings_section(
                        $this->prefix . $array['group'],
                        __($array['group'], $this->prefix),
                        array($this, 'sectionCallBack'),
                        $this->prefix
                    );
                    $this->groups[] = $array['group'];
                }
                add_settings_field(
                    $array['key'],
                    __($array['title'], $this->prefix),
                    array($this, 'makeField'),
                    $this->prefix,
                    $this->prefix . $array['group'],
                    $array
                );
            }
        }
            
        /**
             * Options page in settings
             *
             * @return void
             **/
        public function menuItemAdd()
        {
            add_options_page(
                $this->pluginName,
                $this->pluginName,
                'manage_options',
                $this->prefix,
                array($this, 'optionsPage')
            );
        }
            
        /**
             * Backend options options page
             *
             * @return void
             **/
        public function optionsPage()
        {
            ?>
            <form action='options.php' method='post'>
            <h2><?php echo $this->pluginName; ?></h2>
                <?php
                settings_fields($this->prefix);
                do_settings_sections($this->prefix);
                submit_button();
                ?></form><?php
        }
            
        /**
             * Settings field - default
             *
             * @param array $args arguments
             *
             * @return void
             **/
        public function makeField($args)
        {
            $methodName = 'makeField' . $args['type'];
            if (method_exists($this, $methodName)) {
                return $this->{$methodName}($args);
            }
            echo '<input ';
            echo ' class="regular-text"';
            echo ' type="';
            echo $args['type'];
            echo '"';
            echo $this->_makeFieldAttr($args);
            echo ' value="';
            if (isset($args['value'])) {
                echo $args['value'];
            } else {
                if (isset($args['default'])) {
                    echo $args['default'];
                }
            }
            echo '"';
            echo '>';
        }
            
        /**
             * Settings field - checkbox
             *
             * @param array $args arguments
             *
             * @return void
             **/
        public function makeFieldCheckBox($args)
        {
            echo '<input type="checkbox" value="1"';
            echo $this->_makeFieldAttr($args);
            if (isset($this->options[$args['key']])
                && $this->options[$args['key']]
            ) {
                echo 'checked';
            }
                
            echo '>';
        }
            
        /**
             * Settings field select
             *
             * @param array $args settings arguments
             *
             * @return void
             */
        public function makeFieldSelect($args)
        {
            echo '<select ';
            echo $this->_makeFieldAttr($args);
            echo '>';
            echo $this->_makeFieldSelectOptions($args);
            echo '</select>';
        }
            
        /**
             * Settings field select - options tags
             *
             * @param array $args settings arguments
             *
             * @return void
             */
        private function _makeFieldSelectOptions($args)
        {
            foreach ($args['args'] as $k => $v) {
                echo '<option ';
                echo 'value="' . $k . '" ';
                if (isset($args['value']) && ($args['value'] == $k)) {
                    echo 'selected ';
                }
                echo ">";
                _e($v, $this->prefix);
                echo '</option>';
                    
            }
        }
            
        /**
             * Name and Required attribute for field
             *
             * @param array $args arguments
             *
             * @return void
             **/
        private function _makeFieldAttr($args)
        {
            echo " name=\"";
            echo $this->prefix . '[';
            echo $args['key'] . ']" ';
            if (isset($args['placeholder'])) {
                echo ' placeholder="';
                echo __($args['placeholder'], $this->prefix) . '"';
            }
            if (isset($args['required']) && $args['required']) {
                echo ' required="required"';
            }
        }
            
        /**
             * Enqueue scripts
             *
             * @return void
             **/
        public function scripts()
        {
            wp_register_script(
                $this->prefix,
                plugin_dir_url(__FILE__) . '/plugin.js',
                array('jquery'),
                $this->version,
                true
            );
            wp_localize_script(
                $this->prefix,
                $this->prefix,
                array(
                    'ajax_url' => admin_url('admin-ajax.php')
                )
            );
            wp_enqueue_script($this->prefix);
        }
            
        /**
             * Output under sectionCallBack
             *
             * @return void
             **/
        public function sectionCallBack()
        {
            echo '<hr>';
        }
            
        /**
             * Link to settings page from plugins list page
             *
             * @param array  $links links
             * @param string $file  plugin file
             *
             * @return array links
             */
        public function pluginActionLinks($links, $file)
        {
            if ($file == plugin_basename(__FILE__)) {
                $settings_link = '<a href="' . admin_url(
                    'options-general.php?page=' . $this->prefix
                ) . '">' . __('Settings') . '</a>';
                array_unshift($links, $settings_link);
            }
                
            return $links;
        }
            
        public static function __callStatic($method, $args)
        {
            include_once dirname(__FILE__) . '/includes/api.php';
            $api = new ngmoedeloapi();
                
            return $api->{$method}($args);
        }
            
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
             * Пишем в логи полученные значения,
             * если режим отладки
             *
             * @param any    $value  значение
             * @param string $prefix разделитель значений
             *
             * @return void
             */
        public static function log($value, $prefix = '')
        {
            if ($prefix != '') {
                $prefix .= '-';
            }
            if (! defined('WP_DEBUG') || ! WP_DEBUG) {
                return;
            }
            ob_start();
            $debug = debug_backtrace();
            echo "\n";
            echo date('Y-m-d H:i:s ');
            echo "\n";
            //      echo(json_encode($debug[1]));
            echo self::_logString($debug[1]);
            echo "\n";
            if (is_bool($value)) {
                var_dump($value);
            } else {
                print_r($value);
            }
            $string = ob_get_clean();
            file_put_contents(
                dirname(__FILE__) . '/logs/' . $prefix . date('Y-m-d') . '.log',
                $string,
                FILE_APPEND
            );
            file_put_contents(
                dirname(__FILE__) . '/logs/' . $debug[1]['function'] . '-' .
                date('Y-m-d') . '.log',
                $string,
                FILE_APPEND
            );
        }
    }
}
    new NGWooMoeDeloOrg();
