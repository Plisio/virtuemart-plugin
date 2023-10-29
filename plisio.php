<?php
/**
 *
 * Plisio payment plugin
 *
 * @author Plisio
 * @version 4.0.0
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2019 - 2020 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */


defined('_JEXEC') or die('Restricted access');
define('PLISIO_VIRTUEMART_EXTENSION_VERSION', '4.0.0');

require_once('lib/Plisio/PlisioClient.php');

if (!class_exists('vmPSPlugin')) {
    require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

if (!class_exists('vmCrypt')) {
    require(VMPATH_ADMIN . DS . 'helpers' . DS . 'vmcrypt.php');
}

class plgVmPaymentPlisio extends vmPSPlugin
{
    private $plisio;
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = $this->getVarsToPush();

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getVmPluginCreateTableSQL(): string
    {
        return $this->createTableSQL('Payment Plisio Table');
    }

    function getTableSQLFields(): array
    {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(5)',
            'logo' => 'varchar(5000)'
        );

        return $SQLfields;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices): int
    {
        return 0;
    }

    protected function checkConditions($cart, $method, $cart_prices): bool
    {
        return true;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id): ?bool
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name): ?bool
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId): ?bool
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
            return NULL;

        if (!$this->selectedThisElement($method->payment_element))
            return false;

        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;

        return TRUE;
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices, &$paymentCounter): ?array
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices,$paymentCounter);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name): void
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data): bool
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data): bool
    {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table): bool
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    private function verifyCallbackData($post, $apiKey): bool
    {
        if (!isset($post['verify_hash'])) {
            return false;
        }

        $verifyHash = $post['verify_hash'];
        unset($post['verify_hash']);
        ksort($post);
        $postString = serialize($post);
        $checkKey = hash_hmac('sha1', $postString, $apiKey);
        if ($checkKey != $verifyHash) {
            return false;
        }

        return true;
    }

    function plgVmOnPaymentNotification()
    {
        try {
            $jinput = JFactory::getApplication()->input;
            $post = $jinput->getArray($_POST);

            if (!isset($post['order_number']) && !isset($post['order_id'])) {
                throw new Exception('order_number was not found in callback');
            }
            $virtuemartOrderId = isset($post['order_id']) ? $post['order_id'] : $post['order_number'];

            $modelOrder = VmModel::getModel('orders');
            $order = $modelOrder->getOrder($virtuemartOrderId);

            if (!$order)
                throw new Exception('Order #' . $post['order_number'] . ' does not exists');
            $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);

            if ($method && $this->verifyCallbackData($post, $method->api_key)) {
                if (!$this->selectedThisElement($method->payment_element))
                    return false;

                switch ($post['status']) {
                    case 'completed':
                        $orderStatus = $method->completed_status;
                        $orderComment = 'Plisio invoice was paid successfully.';
                        break;
                    case 'mismatch':
                        $orderStatus = $method->overpaid_status;
                        $orderComment = 'Plisio invoice was overpaid. ' . $post['comment'];
                        break;
                    case 'error':
                        $orderStatus = $method->invalid_status;
                        $orderComment = 'Plisio invoice has some internal error. Please contact support for details.';
                        break;
                    case 'cancelled':
                        $orderStatus = $method->canceled_status;
                        $orderComment = 'Plisio invoice is expired.';
                        break;
                    case 'expired':
                        if ($post['source_amount'] > 0) {
                            $orderStatus = $method->expired_status;
                            $orderComment = 'Plisio invoice is underpaid. ' . $post['comment'];
                        } else {
                            $orderStatus = $method->canceled_status;
                            $orderComment = 'Plisio invoice is expired.';
                        }

                        break;
                    default:
                        $orderStatus = NULL;
                        $orderComment = NULL;
                }

                if (!is_null($orderStatus)) {
                    $modelOrder = new VirtueMartModelOrders();
                    $order['order_status'] = $orderStatus;
                    $order['virtuemart_order_id'] = $virtuemartOrderId;
                    $order['customer_notified'] = 1;
                    $order['comments'] = $orderComment;
                    if (!empty($post['source_amount']) && $post['source_amount'] > 0) {
                        $order['paid'] = $post['source_amount'];
                        $order['paid_on'] = JFactory::getDate();
                    }

                    $modelOrder->updateStatusForOneOrder($virtuemartOrderId, $order, true);

                }
            }
        } catch (Exception $e) {
            exit(get_class($e) . ': ' . $e->getMessage());
        }
        return TRUE;
    }

    function plgVmOnPaymentResponseReceived(&$html): ?bool
    {
        $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);

        $order_number = vRequest::getString('order_number', 0);

        if (!$virtuemart_paymentmethod_id) {
            return FALSE;
        }
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }

        $payment_name = $this->renderPluginName($method);
        $html = $this->renderByLayout('response_page', array(
            'TransactionID' => $order_number,
            'ResponseCode' => 200,
            'ResponseMessage' => 'Order Created Successfully',
            'pageTitle' => $payment_name,
        ));

        return true;
    }

    public function onSelectedCalculatePrice (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name): ?bool
    {

        $idName = $this->_idName;

        if (!($method = $this->selectedThisByMethodId ($cart->{$idName}))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$method = $this->getVmPluginMethod ($cart->{$idName}) or empty($method->{$idName})) {
            return NULL;
        }

        $cart_prices_name = '';
        $cart_prices['cost'] = 0;

        if (!$this->checkConditions ($cart, $method, $cart_prices)) {
            return FALSE;
        }

        $cart_prices_name = $this->renderPluginName ($method, $cart->automaticSelectedPayment);

        $this->setCartPrices ($cart, $cart_prices, $method);

        return TRUE;
    }

    protected function renderPluginName($plugin, $addSelect = false)
    {

        static $c = array();
        $idN = 'virtuemart_' . $this->_psType . 'method_id';

        if (isset($c[$this->_psType][$plugin->$idN])) {
            return $c[$this->_psType][$plugin->$idN];
        }

        $return = '';
        $plugin_name = $this->_psType . '_name';
        $plugin_desc = $this->_psType . '_desc';
        $description = '';
        $logosFieldName = $this->_psType . '_logos';
        $logos = property_exists($plugin, $logosFieldName) ? $plugin->$logosFieldName : array();
        if (!empty($logos)) {
            $return = $this->displayLogos($logos) . ' ';
        }
        if (!empty($plugin->$plugin_desc)) {
            $description = '<span class="' . $this->_type . '_description">' . $plugin->$plugin_desc . '</span>';
        }
        $c[$this->_psType][$plugin->$idN] = $return . '<span class="' . $this->_type . '_name">' . $plugin->$plugin_name . '</span>' . $description;

        return $c[$this->_psType][$plugin->$idN];
    }

    protected function getPluginHtml($plugin, $selectedPlugin, $pluginSalesPrice): string
    {

        $pluginmethod_id = $this->_idName;
        $pluginName = $this->_psType . '_name';
        if ($selectedPlugin == $plugin->$pluginmethod_id) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }

        $html = '<input type="radio" name="' . $pluginmethod_id . '" id="' . $this->_psType . '_id_' . $plugin->$pluginmethod_id . '"   value="' . $plugin->$pluginmethod_id . '" ' . $checked . ">\n"
            . '<label for="' . $this->_psType . '_id_' . $plugin->$pluginmethod_id . '">' . '<span class="' . $this->_type . '">' . $plugin->$pluginName . "</span></label>\n";

        return $html;
    }

    function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected, &$htmlIn): bool
    {
        $session = JFactory::getApplication()->getSession();
        $errors = $session->get('errorMessages', 0, 'vm');

        if ($errors != "") {
            $errors = unserialize($errors);
            $session->set('errorMessages', "", 'vm');
        } else
            $errors = array();

        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
            return NULL;

        if (!$this->selectedThisElement($method->payment_element))
            return false;

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

        VmLanguage::loadJLang('com_virtuemart', true);
        VmLanguage::loadJLang('com_virtuemart_orders', true);

        $orderID = $order['details']['BT']->virtuemart_order_id;
        $paymentMethodID = $order['details']['BT']->virtuemart_paymentmethod_id;

        $api_key = $method->api_key;

        $currency_code_3 = shopFunctions::getCurrencyByID($order['details']['BT']->order_currency, 'currency_code_3');

        $this->plisio = new PlisioClient($api_key);

        $request = array(
            'order_number' => $orderID,
            'order_name' => "Order #$orderID",
            'source_amount' => $order['details']['BT']->order_total,
            'source_currency' => $currency_code_3,
            'cancel_url' => (JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=cart')),
            'callback_url' => (JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component')),
            'success_url' => (JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $paymentMethodID)),
            'email' => $order['details']['BT']->email,
            'plugin' => 'virtuemart',
            'version' => PLISIO_VIRTUEMART_EXTENSION_VERSION
        );
        $response = $this->plisio->createTransaction($request);
        if ($response && $response['status'] !== 'error' && !empty($response['data'])) {
            $cart->emptyCart();
            JFactory::getApplication()->redirect($response['data']['invoice_url']);
            exit();
        } else {
            $html = "<h3> An error occurred while placing order! Error info: " . implode(',', json_decode($response['data']['message'], true)) . "</h3>";
            $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, 'Plisio', '');
            return TRUE;
        }
    }
}

defined('_JEXEC') or die('Restricted access');

if (!class_exists('VmConfig'))
    require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');

if (!class_exists('ShopFunctions'))
    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');

defined('JPATH_BASE') or die();
