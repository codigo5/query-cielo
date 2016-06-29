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

class Query_Cielo_Model_Cc_MaxInstallments
{
	/**
	 * 
	 * Opcoes de numero de parcelas
	 * 
	 */
	
	public function toOptionArray()
	{
		$options = array();
        
        $options['1'] = Mage::helper('adminhtml')->__('1x - Sem parcelamento');
        $options['2'] = Mage::helper('adminhtml')->__('2x');
        $options['3'] = Mage::helper('adminhtml')->__('3x');
        $options['4'] = Mage::helper('adminhtml')->__('4x');
        $options['5'] = Mage::helper('adminhtml')->__('5x');
        $options['6'] = Mage::helper('adminhtml')->__('6x');
        $options['7'] = Mage::helper('adminhtml')->__('7x');
        $options['8'] = Mage::helper('adminhtml')->__('8x');
        $options['9'] = Mage::helper('adminhtml')->__('9x');
        $options['10'] = Mage::helper('adminhtml')->__('10x');
        $options['11'] = Mage::helper('adminhtml')->__('11x');
        $options['12'] = Mage::helper('adminhtml')->__('12x');
    
        
		return $options;
	}
}
