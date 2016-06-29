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

class Query_Cielo_Model_Cc extends Query_Cielo_Model_Abstract
{

    protected $_code  = 'Query_Cielo_Cc';
    protected $_formBlockType = 'Query_Cielo/form_cc';
    protected $_infoBlockType = 'Query_Cielo/info_cc';
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = false;
    
    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object))
		{
            $data = new Varien_Object($data);
        }
        
		// salva a bandeira, o numero de parcelas e o token
		$info = $this->getInfoInstance();
        $additionaldata = array
        (
			'parcels_number' => $data->getParcelsNumber()
		);
		
		if($data->getToken())
		{
			$tokenData = $this->_getTokenById($data->getToken());
			$additionaldata['token'] = $tokenData['token'];
			$data->setCcType($tokenData['ccType']);
		}

		$ccNumberSize = strlen($data->getCcNumber());
		$ccLast4 = substr($data->getCcNumber(), $ccNumberSize - 4, 4);
		
		$info->setCcType($data->getCcType())
			 ->setCcNumber(Mage::helper('core')->encrypt($data->getCcNumber()))
			 ->setCcOwner($data->getCcOwner())
			 ->setCcExpMonth($data->getCcExpMonth())
			 ->setCcExpYear($data->getCcExpYear())
			 ->setCcOwnerDoc($data->getCcOwnerDoc())
			 ->setCcLast4(Mage::helper('core')->encrypt($ccLast4))
			 ->setCcCid(Mage::helper('core')->encrypt($data->getCcCid()))
			 ->setAdditionalData(serialize($additionaldata));
		
		
		// pega dados de juros
		$withoutInterest = intval($this->getConfigData('installment_without_interest', $this->getStoreId()));
		$interestValue = floatval($this->getConfigData('installment_interest_value', $this->getStoreId()));
		
		
		// verifica se há juros
		if($data->getParcelsNumber() > $withoutInterest)
		{
			// confere se jah ha valor atribuido a juros
			// caso haja, evita que seja calculado duas vezes
			if($info->getQuote()->getInterest() > 0)
			{
				$totalValue = $info->getQuote()->getGrandTotal() - $info->getQuote()->getInterest();
			}
			else
			{
				$totalValue = $info->getQuote()->getGrandTotal();
			}
			
			
			$installmentValue = Mage::helper('Query_Cielo')->calcInstallmentValue
								(
									$totalValue, 
									$interestValue / 100, 
									$data->getParcelsNumber()
								);
			
			$installmentValue = round($installmentValue, 2);
			$interest = ($installmentValue * $data->getParcelsNumber()) - $totalValue;
			
			$info->getQuote()->setInterest($info->getQuote()->getStore()->convertPrice($interest, false));
			$info->getQuote()->setBaseInterest($interest);
			
			$info->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
			$info->getQuote()->save();
		}
		else
		{
			$info->getQuote()->setInterest(0.0);
			$info->getQuote()->setBaseInterest(0.0);
			
			$info->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
			$info->getQuote()->save();
		}
		
		
        return $this;
    }
	
	
	/**
	 * Valida dados
	 *
	 * @param   Mage_Payment_Model_Info $info
	 * @return  Mage_Payment_Model_Abstract
	 */
	public function validate()
	{
		/*
		 * chama validacao do metodo abstrato
		 */
		parent::validate();
		
		$info = $this->getInfoInstance();
		$errorMsg = false;
		
		if($this->getConfigData('buypage', $this->getStoreId()) != "loja")
		{
			return $this;
		}
		
		$additionalData = unserialize($info->getAdditionalData());

		if(isset($additionalData['token']) && $additionalData['token'] != '')
		{
			$availableTypes = Mage::getModel('Query_Cielo/cc_types')->getCodes();
			if(!in_array($info->getCcType(), $availableTypes))
			{
				$errorMsg = Mage::helper('Query_Cielo')->__('Credit card type is not allowed for this payment method.');
			}						
		}
		else
		{

			$availableTypes = Mage::getModel('Query_Cielo/cc_types')->getCodes();
			$ccNumber = Mage::helper('core')->decrypt($info->getCcNumber());

			// remove delimitadores do cartao, como "-" e espaco
			$ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
			$info->setCcNumber(Mage::helper('core')->encrypt($ccNumber));

			$ccType = '';
			
			// valida o numero do cartao de credito
			if(in_array($info->getCcType(), $availableTypes))
			{
				if ($this->validateCcNum($ccNumber))
				{
					$ccType = 'OT';
					$ccTypeRegExpList = array
					(
						//Solo, Switch or Maestro. International safe
						/*
						// Maestro / Solo
						'SS'  => '/^((6759[0-9]{12})|(6334|6767[0-9]{12})|(6334|6767[0-9]{14,15})'
								. '|(5018|5020|5038|6304|6759|6761|6763[0-9]{12,19})|(49[013][1356][0-9]{12})'
								. '|(633[34][0-9]{12})|(633110[0-9]{10})|(564182[0-9]{10}))([0-9]{2,3})?$/',
						*/
						// Solo only
						'SO' => '/(^(6334)[5-9](\d{11}$|\d{13,14}$))|(^(6767)(\d{12}$|\d{14,15}$))/',
						/*'SM' => '/(^(5[0678])\d{11,18}$)|(^(6[^05])\d{11,18}$)|(^(601)[^1]\d{9,16}$)|(^(6011)\d{9,11}$)'
								. '|(^(6011)\d{13,16}$)|(^(65)\d{11,13}$)|(^(65)\d{15,18}$)'
								. '|(^(49030)[2-9](\d{10}$|\d{12,13}$))|(^(49033)[5-9](\d{10}$|\d{12,13}$))'
								. '|(^(49110)[1-2](\d{10}$|\d{12,13}$))|(^(49117)[4-9](\d{10}$|\d{12,13}$))'
								. '|(^(49118)[0-2](\d{10}$|\d{12,13}$))|(^(4936)(\d{12}$|\d{14,15}$))/',*/
						// Visa
						'visa'  => '/^4[0-9]{12}([0-9]{3})?$/',
						// Master Card
						'mastercard'  => '/^5[1-5][0-9]{14}$/',
						// American Express
						'amex'  => '/^3[47][0-9]{13}$/',
						// Discovery
						'discover'  => '/^6011[0-9]{12}$/',
						// JCB
						'jcb' => '/^(3[0-9]{15}|(2131|1800)[0-9]{11})$/',
						// Diners Club
						'diners' => '/^3[0,6,8]\d{12}$/',
						//aura
						'aura' => '/^\d{19}$/'
					);

					foreach ($ccTypeRegExpList as $ccTypeMatch => $ccTypeRegExp)
					{
						if (preg_match($ccTypeRegExp, $ccNumber))
						{
							$ccType = $ccTypeMatch;
							break;
						}
					}

					if ($info->getCcType() != 'elo' && ($ccType != $info->getCcType()))
					{
						$errorMsg = Mage::helper('Query_Cielo')->__('Credit card number mismatch with credit card type.');
					}
				}
				else
				{
					$errorMsg = Mage::helper('Query_Cielo')->__('Invalid Credit Card Number');
				}

			}
			else
			{
				$errorMsg = Mage::helper('Query_Cielo')->__('Credit card type is not allowed for this payment method.');
			}

			// valida o numero de verificacao
			if ($errorMsg === false)
			{
				$verificationRegEx = $this->getVerificationRegEx();
				$regExp = isset($verificationRegEx[$info->getCcType()]) ? $verificationRegEx[$info->getCcType()] : '';
				
				if ($regExp != '' && (!$info->getCcCid() || !preg_match($regExp, Mage::helper('core')->decrypt($info->getCcCid()))))
				{
					$errorMsg = Mage::helper('Query_Cielo')->__('Please enter a valid credit card verification number.');
				}
			}

			if (!$this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth()))
			{
				
				$errorMsg = Mage::helper('Query_Cielo')->__('Incorrect credit card expiration date.');
			}

			if($errorMsg)
			{
				Mage::throwException($errorMsg);
			}

			//This must be after all validation conditions
			//if ($this->getIsCentinelValidationEnabled())
			//{
			//	$this->getCentinelValidator()->validate($this->getCentinelValidationData());
			//}
		}
		return $this;
	}
	
	
	/**
     * Validacao retirada do modelo cc da versao 1.7 do Magento
     *
     * @param   string $cc_number
     * @return  bool
     */
    public function validateCcNum($ccNumber)
    {
        $cardNumber = strrev($ccNumber);
        $numSum = 0;

        for ($i=0; $i<strlen($cardNumber); $i++)
        {
            $currentNum = substr($cardNumber, $i, 1);

            /**
             * Double every second digit
             */
            if ($i % 2 == 1)
            {
                $currentNum *= 2;
            }

            /**
             * Add digits of 2-digit numbers together
             */
            if ($currentNum > 9)
            {
                $firstNum = $currentNum % 10;
                $secondNum = ($currentNum - $firstNum) / 10;
                $currentNum = $firstNum + $secondNum;
            }

            $numSum += $currentNum;
        }

        /**
         * If the total has no remainder it's OK
         */
        
        return ($numSum % 10 == 0);
    }
    
    
    /**
     * Expressao regular retirada do modelo cc da versao 1.7 do Magento
     *
     * @return  strig regExp
     */
     
    public function getVerificationRegEx()
    {
        $verificationExpList = array
        (
            'visa' 			=> '/^[0-9]{3}$/', 			// Visa
            'mastercard' 	=> '/^[0-9]{3}$/',       	// Master Card
            'amex' 			=> '/^[0-9]{4}$/',        	// American Express
            'discover' 		=> '/^[0-9]{3}$/',         	// Discovery
            'SS' 			=> '/^[0-9]{3,4}$/',
            'SM' 			=> '/^[0-9]{3,4}$/', 		// Switch or Maestro
            'SO' 			=> '/^[0-9]{3,4}$/', 		// Solo
            'OT' 			=> '/^[0-9]{3,4}$/',
            'jcb' 			=> '/^[0-9]{3,4}$/', 		//JCB
			'aura'			=> '/^[0-9]{3}$/'
        );
        return $verificationExpList;
    }
    
    
    /**
     * Validacao retirada do modelo cc da versao 1.7 do Magento
     *
     * @return  strig regExp
     */
    
    protected function _validateExpDate($expYear, $expMonth)
    {
        $date = Mage::app()->getLocale()->date();
        
        if (!$expYear || !$expMonth || ($date->compareYear($expYear) == 1)
            || ($date->compareYear($expYear) == 0 && ($date->compareMonth($expMonth) == 1)))
        {
            return false;
        }
        
        return true;
    }
    
    /**
     *  Getter da instancia do pedido
     *
     *  @return	  Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if ($this->_order == null) {}
		
        return $this->_order;
    }

    /**
     *  Setter do pedido
     *
     *  @param Mage_Sales_Model_Order $order
     */
    public function setOrder($order)
    {
        if ($order instanceof Mage_Sales_Model_Order)
		{
            $this->_order = $order;
        }
		elseif (is_numeric($order))
		{
            $this->_order = Mage::getModel('sales/order')->load($order);
        }
		else
		{
            $this->_order = null;
        }
        return $this;
    }
	
	/**
     * Abre transacao com a Cielo para uma compra e redirectiona para a 
     * pagina de pagamento na Cielo. Em caso de erro, redireciona para pagina
     * de erro.
     *
     * @return  string
     */
	public function getOrderPlaceRedirectUrl()
	{
		$info = $this->getInfoInstance();
		$quote = $info->getQuote();
		$storeId = $this->getStoreId();
		$payment = $quote->getPayment();
		$additionaldata = unserialize($payment->getData('additional_data'));
		
		// coleta os dados necessarios
		$value 				= Mage::helper('Query_Cielo')->formatValueForCielo($quote->getGrandTotal());
		$paymentType 		= $additionaldata["parcels_number"];
		$ccType 			= $payment->getCcType();
		$paymentParcels 	= $this->getConfigData('installments_type', $storeId);
		$cieloNumber 		= $this->getConfigData('cielo_number', $storeId);
		$cieloKey 			= $this->getConfigData('cielo_key', $storeId);
		$autoCapture		= $this->getConfigData('auto_capture', $storeId);
		$environment 		= $this->getConfigData('environment', $storeId);
		$sslFile	 		= $this->getConfigData('ssl_file', $storeId);

		// cria instancia do pedido
		$webServiceOrder = Mage::getModel('Query_Cielo/webServiceOrder', array('enderecoBase' => $environment, 'caminhoCertificado' => $sslFile));
		
		// preenche dados coletados
		$webServiceOrderData = array
		(
			'ccType'			=> $ccType,
			'cieloNumber'		=> $cieloNumber,
			'cieloKey'			=> $cieloKey,
			'capture'			=> ($autoCapture == 1) ? 'true' : 'false',
			'autorize'			=> '1',
			'generateToken'		=> 'false',
			'clientOrderNumber'	=> $payment->getId(),
			'clientOrderValue'	=> $value,
			'postbackURL'		=> Mage::getUrl('querycielo/pay/verify'),
			'clientSoftDesc'	=> $this->getConfigData('softdescriptor', $storeId)	
		);
		
		// conforme mostrado no manual versao 2.5.1, pagina 13,
		// caso o cartao seja Dinners, Discover, Elo, Amex,Aura ou JCB
		// o valor do flag autorizar deve ser 3
		if($ccType == "diners" 		|| 
		   $ccType == "discover" 	|| 
		   $ccType == "elo" 		|| 
		   $ccType == "amex" 		||
		   $ccType == "aura" 		|| 
		   $ccType == "jcb" 		||
		   !$this->getConfigData('autenticate', $storeId))
		{
			$webServiceOrderData['autorize'] = '3';
		}
		
		if($paymentType == "1")
		{
			$webServiceOrderData['paymentType'] = $paymentType;
			$webServiceOrderData['paymentParcels'] = 1;
		}
		else
		{
			$webServiceOrderData['paymentType'] = $paymentParcels;
			$webServiceOrderData['paymentParcels'] = $paymentType;
		}
		
		// caso seja buy page loja, passa dados do cliente
		if($this->getConfigData('buypage', $storeId) == "loja")
		{
			$ccExpMonth = $info->getCcExpMonth();
			$ccExpMonth = ($ccExpMonth < 10) ? ("0" . $ccExpMonth) : $ccExpMonth;
			$additionalData = unserialize($info->getAdditionalData());
			
			$ownerData = array
			(
				'number' 	=> Mage::helper('core')->decrypt($info->getCcNumber()),
				'exp_date' 	=> $info->getCcExpYear() . $ccExpMonth,
				'sec_code' 	=> Mage::helper('core')->decrypt($info->getCcCid()),
				'name' 		=> $info->getCcOwner()
			);
			
			// confere se ha utilizacao de tokens
			if($this->getConfigData('tokenize', $storeId))
			{
				// confere se foi passado um token
				if(isset($additionalData['token']) && $additionalData['token'] != '')
				{
					$ownerData = array('token' => $additionalData['token']);
					$webServiceOrderData['autorize'] = '3';
				}
				// confere se existe token para o cartao inserido
				else if($token = $this->_getToken($quote->getCustomerId(), $ccType, $ownerData['number']))
				{
					$ownerData = array('token' => $token);
					$webServiceOrderData['autorize'] = '3';
				}
				// cria um novo token para o cliente
				else
				{
					$webServiceOrderData['generateToken'] = 'true';
				}
			}
		}
		else
		{
			$ownerData = false;
		}
		
		// faz a requisicao a cielo
		$webServiceOrder->setData($webServiceOrderData);
		$redirectUrl = $webServiceOrder->requestTransaction($ownerData);
		

		// caso volte um token, armazena-o para um usuario
		$this->_saveNewToken
		(
			$webServiceOrder->getXmlResponse(),
			$quote->getCustomerId(),
			$ccType
		);
		
		
		Mage::getSingleton('core/session')->setData('cielo-transaction', $webServiceOrder);
		
		if($redirectUrl == false)
		{
			// caso nao haja autenticacao, enviar para o tratamento final do pedido
			if(($this->getConfigData('buypage', $storeId) == "loja") && ($webServiceOrderData['autorize'] == '3'))
			{
				return Mage::getUrl('querycielo/pay/verify');
			}
			// erro nao indentificado
			else
			{
				return Mage::getUrl('querycielo/pay/failure');
			}
		}
		else
		{
			return $redirectUrl;
		}
    }
	
	private function _getToken($customerId, $ccType, $ccNumber)
	{
		$resource = Mage::getSingleton('core/resource');
		$readConnection = $resource->getConnection('core_read');
		
		$tablePrefix = (string) Mage::getConfig()->getTablePrefix();
		if($tablePrefix)
		{
			$tablePrefix = "_" . $tablePrefix;
		}
		
		$last4ccNumbers = substr($ccNumber,(strlen($ccNumber) - 4), 4);
		
		$sql = "SELECT token FROM " . $tablePrefix . "query_cielo_customer_token WHERE customer_id=" . $customerId . " AND " . 
				"cc_type='" . $ccType . "' AND last_digits ='" . Mage::Helper('core')->encrypt($last4ccNumbers) . "'";
		$result = $readConnection->fetchCol($sql);
		
		// verifica se o cliente tem token
		if(!$result || count($result) != 1)
		{
			return false;
		}
		else
		{
			return $result[0];
		}
	}
	
	private function _saveNewToken($responseXml, $customerId, $ccType)
	{
		$tokenDataTag = "dados-token";
		$codeTag = "codigo-token";
		$cardNumberTag = "numero-cartao-truncado";
		
		// confere se houve geracao de token
		if( !$responseXml || 
			!$responseXml->token || 
			!$responseXml->token->$tokenDataTag || 
			!$responseXml->token->$tokenDataTag->$codeTag ||
			!$responseXml->token->$tokenDataTag->$cardNumberTag ||
			!$responseXml->token->$tokenDataTag->status ||
			((string) $responseXml->token->$tokenDataTag->status) != "1")
		{
			return;
		}
		
		$token = (string) $responseXml->token->$tokenDataTag->$codeTag;
		$cardNumber = (string) $responseXml->token->$tokenDataTag->$cardNumberTag;
		$lastDigits = Mage::Helper('core')->encrypt(substr($cardNumber, (strlen($cardNumber) - 4), 4));
		
		// insere dados no banco
		$resource = Mage::getSingleton('core/resource');
       	$writeConnection = $resource->getConnection('core_write');
		$tablePrefix = (string) Mage::getConfig()->getTablePrefix();
		if($tablePrefix)
		{
			$tablePrefix = "_" . $tablePrefix;
		}
		$sql = "INSERT INTO `" . $tablePrefix . "query_cielo_customer_token`" . 
				"	(`customer_id`,`token`,`cc_type`,`last_digits`) " . 
				"VALUES " . 
				"	(". $customerId .",'" . $token . "','" . $ccType . "','" . $lastDigits . "')";
		
		$writeConnection->query($sql);
		
		$additionalData = unserialize($this->getInfoInstance()->getAdditionalData());
		$additionalData["token"] = $token;
		$this->getInfoInstance()->setAdditionalData(serialize($additionalData));
		$this->getInfoInstance()->save();
	}
	
	private function _getTokenById($tokenString)
	{

		$quote = $this->getInfoInstance()->getQuote();
		$tokenData = explode("/", $tokenString, 2);
		
		$tablePrefix = (string) Mage::getConfig()->getTablePrefix();
		if($tablePrefix)
		{
			$tablePrefix = "_" . $tablePrefix;
		}
		
		$readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
		$query = "SELECT token FROM " . $tablePrefix . "query_cielo_customer_token WHERE token_id=" . $tokenData[1] ." AND customer_id = ".$quote->getCustomerId();
		$returnValues = array();
		$returnValues['token'] = $readConnection->fetchOne($query);
		$returnValues['ccType'] = $tokenData[0];
		return $returnValues;
	}
}
