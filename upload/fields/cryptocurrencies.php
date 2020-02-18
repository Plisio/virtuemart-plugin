<?php
/**
 *
 * Paypal  payment plugin
 *
 * @author Jeremy Magne
 * @version $Id: paypal.php 7217 2013-09-18 13:42:54Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2004 - 2019 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */


defined('_JEXEC') or die();

JFormHelper::loadFieldClass('list');

class JFormFieldCryptocurrencies extends JFormFieldList
{

    protected $type = 'Cryptocurrencies';

    protected function getOptions()
    {
        $plisio = new PlisioClient('');
        $currencies = $plisio->getCurrencies();

        $options = [];
        $options[] = JHtml::_('select.option', '', 'Any');

        foreach ($currencies['data'] as $item)
        {
            $options[] = JHtml::_('select.option', $item['cid'], $item['name'] . ' (' . $item['currency'] . ')');
        }

        return $options;
    }
}