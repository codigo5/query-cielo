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

class Query_Cielo_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{
	public function consultRequest($order)
	{
		$payment = $order->getPayment();

		// pega os dados para requisicao e realiza a consulta		
		$methodCode 		= $payment->getMethodInstance()->getCode();
		$cieloNumber 		= Mage::getStoreConfig('payment/' . $methodCode . '/cielo_number');
		$cieloKey 			= Mage::getStoreConfig('payment/' . $methodCode . '/cielo_key');
		$environment		= Mage::getStoreConfig('payment/' . $methodCode . '/environment');
		$sslFile			= Mage::getStoreConfig('payment/' . $methodCode . '/ssl_file');
		
		$model = Mage::getModel('Query_Cielo/webServiceOrder', array('enderecoBase' => $environment, 'caminhoCertificado' => $sslFile));
		
		if($order->getPayment()->getAdditionalInformation('Cielo_tid'))
		{
			$model->tid = $order->getPayment()->getAdditionalInformation ('Cielo_tid');
			$model->cieloNumber = $cieloNumber;
			$model->cieloKey = $cieloKey;
			
			$model->requestConsultation();
			$xml = $model->getXmlResponse();

			if(isset($xml->status))
			{
				$payment->setAdditionalInformation('Cielo_status', (string) $xml->status);
				$payment->save();
			}
		}
		else 
		{
			$quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($order->getQuoteId());
			
			$model->clientOrderNumber = $quote->getPayment()->getPaymentId();
			$model->cieloNumber = $cieloNumber;
			$model->cieloKey = $cieloKey;
			
			$model->requestConsultationByStoreId();
			$xml = $model->getXmlResponse();
			
			$eci = (isset($xml->autenticacao->eci)) ? ((string) $xml->autenticacao->eci) : "";
			$tid = (string) $xml->tid;
			$type =  (string) $xml->{'forma-pagamento'}->bandeira;
			$parcels = (string) $xml->{'forma-pagamento'}->parcelas;
			$status = (string) $xml->status;
			
			$payment->setAdditionalInformation('Cielo_tid', $tid);
			$payment->setAdditionalInformation('Cielo_cardType', $type);
			$payment->setAdditionalInformation('Cielo_installments', $parcels );
			$payment->setAdditionalInformation('Cielo_eci', $eci);
			$payment->setAdditionalInformation('Cielo_status', $status);
			$payment->save();
		}

		return $xml;
	}


	public function cancelRequest($order, $value = false)
	{
		// pega os dados para requisicao e realiza a consulta
		$methodCode 		= $order->getPayment()->getMethodInstance()->getCode();
		$cieloNumber 		= Mage::getStoreConfig('payment/Query_Cielo_Cc/cielo_number');
		$cieloKey 			= Mage::getStoreConfig('payment/Query_Cielo_Cc/cielo_key');
		$environment		= Mage::getStoreConfig('payment/' . $methodCode . '/environment');
		$sslFile			= Mage::getStoreConfig('payment/' . $methodCode . '/ssl_file');
		
		$model = Mage::getModel('Query_Cielo/webServiceOrder', array('enderecoBase' => $environment, 'caminhoCertificado' => $sslFile));
		
		$model->tid = $order->getPayment()->getAdditionalInformation ('Cielo_tid');
		$model->cieloNumber = $cieloNumber;
		$model->cieloKey = $cieloKey;
		$value = ($value !== false) ? Mage::helper('Query_Cielo')->formatValueForCielo($value) : false;

		// requisita cancelamento
		if($model->requestCancellation($value))
		{
			$xml = $model->getXmlResponse();

			// atualiza os dados da compra
			if(isset($xml->status))
			{
				$payment = $order->getPayment();
				$payment->setAdditionalInformation('Cielo_status', (string) $xml->status);
				$payment->save();
			}

			return $xml;
		}
		else
		{
			return false;
		}
	}


	public function captureRequest($order)
	{
		$value = Mage::helper('Query_Cielo')->formatValueForCielo($order->getGrandTotal());
		
		// pega os dados para requisicao e realiza a consulta
		$methodCode 		= $order->getPayment()->getMethodInstance()->getCode();
		$cieloNumber 		= Mage::getStoreConfig('payment/Query_Cielo_Cc/cielo_number');
		$cieloKey 			= Mage::getStoreConfig('payment/Query_Cielo_Cc/cielo_key');
		$environment		= Mage::getStoreConfig('payment/' . $methodCode . '/environment');
		$sslFile			= Mage::getStoreConfig('payment/' . $methodCode . '/ssl_file');
		
		$model = Mage::getModel('Query_Cielo/webServiceOrder', array('enderecoBase' => $environment, 'caminhoCertificado' => $sslFile));
		
		$model->tid = $order->getPayment()->getAdditionalInformation ('Cielo_tid');
		$model->cieloNumber = $cieloNumber;
		$model->cieloKey = $cieloKey;
		
		// requisita captura
		$model->requestCapture($value);
		return $model->getXmlResponse();
	}
}