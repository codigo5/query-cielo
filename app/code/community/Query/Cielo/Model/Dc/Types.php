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

class Query_Cielo_Model_Dc_Types
{

    /**
     * Formato vetor de vetores
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array
	(
			array
			(
				'value' 	=> 'visa-electron',
				'label' 	=> Mage::helper('adminhtml')->__('Visa Electron'),
				'image' 	=> 'Visa-Electron.png'
			),   
	               array
	                (
                	        'value'     => 'mastercard-maestro',
		                'label'     => Mage::helper('adminhtml')->__('Mastercard Maestro'),
		                'image'     => 'Master-maestro.png'
	                )
        );
    }

    /**
     * Formato chave-valor
     *
     * @return array
     */
    public function toArray()
    {
        return array		
	(
            'visa' 	=> Mage::helper('adminhtml')->__('Visa Electron'),
	    'mastercard'  => Mage::helper('adminhtml')->__('Mastercard Maestro')
        );
    }
	
	/**
     * Formato chave
     *
     * @return array
     */
    public function getCodes()
    {
        return array
	(
            'visa',
            'mastercard'
        );
    }
}
