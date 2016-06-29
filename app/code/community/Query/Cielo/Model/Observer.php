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
	
class Query_Cielo_Model_Observer
{
	public function addButtons($observer)
	{
		$block = $observer->getEvent()->getBlock();
		
		if ($block && 
			$block->getType() == 'adminhtml/sales_order_view' )
		{
			$order = $block->getOrder();
			$payment = $order->getPayment();
			$method = $payment->getMethodInstance()->getCode();
			$tid = $payment->getAdditionalInformation('Cielo_tid');
			$status = $payment->getAdditionalInformation('Cielo_status');
			
			if($method == "Query_Cielo_Cc")
			{
				if($status == 4) 																	// somente transacoes autenticadas podem ser capturadas
				{
					$block->addButton('query_cielo_capture', array
					(
						'label'     => Mage::helper('Query_Cielo')->__('Capture'),
						'onclick'   => "captureCieloOrder('" . $tid . "', " . $order->getId() . ");",
						'class'     => 'go'
					));
				}
			}
			
			if($method == "Query_Cielo_Cc" || $method == "Query_Cielo_Dc")
			{
				$block->addButton('query_cielo_consult', array
				(
					'label'     => Mage::helper('Query_Cielo')->__('Consult WebService'),
					'onclick'   => "loadCieloWebServiceData('" . $tid . "', " . $order->getId() . ");",
					'class'     => 'go'
				));
			}
			
			if($method == "Query_Cielo_Cc")
			{
				if ($status != 9 && $order->getState() == "canceled") 								// magento cancelado, mas na cielo nao
				{
					$block->addButton('query_cielo_cancel', array
					(
						'label'     => Mage::helper('Query_Cielo')->__('Cancel on Cielo'),
						'onclick'   => "cancelCieloOrder('" . $tid . "', " . $order->getId() . ");",
						'class'     => 'go'
					));
				}
			}
		}
	}


	/**
	 *
	 * Observador responsavel pelo cancelamento de pedido
	 *
	 **/

	public function cancelOrder($observer)
	{
		$order = $observer->getEvent()->getOrder();
		
		if( !$order 
			|| !$order->getId() 
			|| ($order->getPayment()->getMethodInstance()->getCode() != "Query_Cielo_Cc" 
				&& $order->getPayment()->getMethodInstance()->getCode() != "Query_Cielo_Dc"))
		{
			return;
		}

		if($order->getPayment()->getMethodInstance()->cancelRequest($order))
		{
			Mage::getSingleton('core/session')->addSuccess('Pedido cancelado com sucesso na Cielo.'); 
		}
		else
		{
			Mage::getSingleton('core/session')->addError('Erro ao tentar pedido na Cielo. Tente novamente de forma manual e se o problema persistir, por favor entre em contato o suporte.'); 
		}
	}
	
	
	/**
	 *
	 * Observador responsavel pela sincronizacao de estoque
	 * de produtos que sejam comprados na loja
	 *
	 **/

	public function createCreditmemo($observer)
	{
		$creditmemo = $observer->getEvent()->getCreditmemo();
		$order = Mage::getModel('sales/order')->load($creditmemo->getOrderId());
		
		if( !$order 
			|| !$order->getId() 
			|| ($order->getPayment()->getMethodInstance()->getCode() != "Query_Cielo_Cc" 
				&& $order->getPayment()->getMethodInstance()->getCode() != "Query_Cielo_Dc"))
		{
			return;
		}

		if($order->getPayment()->getMethodInstance()->cancelRequest($order, $creditmemo->getGrandTotal()))
		{
			Mage::getSingleton('core/session')->addSuccess('Pedido cancelado com sucesso na Cielo.'); 
		}
		else
		{
			Mage::getSingleton('core/session')->addError('Erro ao tentar pedido na Cielo. Tente novamente de forma manual e se o problema persistir, por favor entre em contato o suporte.'); 
		}
	}
}