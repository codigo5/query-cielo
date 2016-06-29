<?php

/*
 * Query Commerce Cielo Module - payment method module for Magento,
 * integrating the billing forms with a Cielo's gateway Web Service.
 * Copyright (C) 2013  Fillipe Almeida Dutra
 * Belo Horizonte, Minas Gerais - Brazil
 * 
 * Contact: lawsann@gmail.com
 * Project link: http://code.google.com/p/magento-cielo/
 * Group discussion: http://groups.google.com/group/cielo-magento
 * 
 * Team: 
 * Fillipe Almeida Dutra - lawsann@gmail.com
 * Hermes Luciano Monteiro Junior - hermeslmj@gmail.com
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class Query_Cielo_Model_Quote_Address_Interest extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    /** 
     * Constructor that should initiaze 
     */
    public function __construct()
    {
        $this->setCode('interest');
    }

    /**
     * Used each time when collectTotals is invoked
     * 
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Your_Module_Model_Total_Custom
     */
    
	public function collect(Mage_Sales_Model_Quote_Address $address)
	{
		if ($address->getData('address_type') == 'billing') return $this;
		
		$this->_setAddress($address);
		
		
		if($ammount = $address->getQuote()->getInterest())
		{
			$this->_setBaseAmount($ammount);
			$this->_setAmount($address->getQuote()->getStore()->convertPrice($ammount, false));
			$address->setInterest($ammount);
		}
		else
		{
			$this->_setBaseAmount(0.00);
			$this->_setAmount(0.00);
		}
		
		return $this;
	}

    /**
     * Used each time when totals are displayed
     * 
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Your_Module_Model_Total_Custom
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getInterest() != 0) 
        {
            $address->addTotal(array
            (
                'code' => $this->getCode(),
                'title' => Mage::helper('Query_Cielo')->__('Interest'),
                'value' => $address->getInterest()
            ));
        }
    }
}
