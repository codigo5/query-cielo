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

class Query_Cielo_Block_Form_Cc extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('cielo/form/cc.phtml');
    }
    
    
    /**
     * 
     * Lista opcoes de meses
     * 
     */
    
    public function getMonths()
	{
    	$months = array();
		
		for($i = 1; $i <= 12; $i++)
		{
			$label = ($i < 10) ? ("0" . $i) : $i;
			
			$months[] = array("num" => $i, "label" => $this->htmlEscape($label));
		}
		
		return $months;
	}
	
	/**
     * 
     * Lista opcoes de anos
     * 
     */
    
    public function getYears()
	{
    	$years = array();
		
		$initYear = (int) date("Y");
		
		for($i = $initYear; $i <= $initYear + 10; $i++)
		{
			$years[] = array("num" => $i, "label" => $i);
		}
		
		return $years;
	}
    
    
    /**
     * 
     * Lista opcoes de parcelamento
     * 
     */
    
    public function getInstallments()
	{
    	// pega dados de parcelamento
    	$maxInstallments = intval(Mage::getStoreConfig('payment/Query_Cielo_Cc/max_parcels_number'));
    	$minInstallmentValue = floatval(Mage::getStoreConfig('payment/Query_Cielo_Cc/min_parcels_value'));
		
		$minInstallmentValue = ($minInstallmentValue <= 5.01) ? 5.01 : $minInstallmentValue;
		
		// atualiza taxa de juros para 0,
		// caso o usuario tenha voltado na navegacao
		$quote = Mage::getSingleton('checkout/cart')->getQuote();
		$quote->setInterest(0.0);
		$quote->setBaseInterest(0.0);
		
		$quote->setTotalsCollectedFlag(false)->collectTotals();
		$quote->save();
		
		// pega dados de juros
		$withoutInterest = intval(Mage::getStoreConfig('payment/Query_Cielo_Cc/installment_without_interest'));
		$interestValue = floatval(Mage::getStoreConfig('payment/Query_Cielo_Cc/installment_interest_value'));
		
		// pega valores do pedido
		$total = Mage::getSingleton('checkout/cart')->getQuote()->getGrandTotal();
		
		$installments = array();
		
		for($i = 1; $i <= $maxInstallments; $i++)
		{
			// caso nao haja juros na parcela
			if($i <= $withoutInterest)
			{
				$orderTotal = $total;
				$installmentValue = round($orderTotal / $i, 2);
			}
			// caso haja juros
			else
			{
				$installmentValue = round(Mage::helper('Query_Cielo')->calcInstallmentValue($total, $interestValue / 100, $i), 2);
				$orderTotal = $i * $installmentValue;
			}
			
			
			
			// confere se a parcela nao estah abaixo do minimo
			if($minInstallmentValue >= 0 && $installmentValue < $minInstallmentValue)
			{
				break;
			}
			
			// monta o texto da parcela
			if($i == 1)
			{
				$label = "à vista (" . Mage::helper('core')->currency(($total), true, false) . ")";
			}
			else
			{
				if($i <= $withoutInterest)
				{
					$label = $i . "x sem juros (" . Mage::helper('core')->currency(($installmentValue), true, false) . " cada)";
				}
				else
				{
					$label = $i . "x (" . Mage::helper('core')->currency(($installmentValue), true, false) . " cada)";
				}
			}
			
			// adiciona no vetor de parcelas
			$installments[] = array("num" => $i, "label" => $this->htmlEscape($label));
		}
		
		// caso o valor da parcela minima seja maior do que o valor da compra,
		// deixa somente opcao a vista
		if($minInstallmentValue > $total)
		{
			$label = "à vista (" . Mage::helper('core')->currency(($total), true, false) . ")";
			$installments[] = array("num" => 1, "label" => $this->htmlEscape($label));
		}
		
		return $installments;
	}
	
	/**
     * 
     * Retorna vetor com os codigos dos cartoes habilitados
     * 
     */
    
    public function getAllowedCards()
	{
    	$allowedCards = explode(",", Mage::getStoreConfig('payment/Query_Cielo_Cc/card_types'));
    	$allCards = Mage::getModel('Query_Cielo/cc_types')->toOptionArray();
    	
    	$validCards = array();
    	
    	foreach($allCards as $card)
    	{
			if(in_array($card['value'], $allowedCards))
			{
				$validCards[] = $card;
			}
    	}
    	
    	return $validCards;
	}
	
	/**
     * 
     * Retorna vetor com numero maximo de parcelamento aceito
	 * para cada bandeira
     * 
     */
    
    public function getMaxCardsInstallments()
	{
    	$maxInstallments = intval(Mage::getStoreConfig('payment/Query_Cielo_Cc/max_parcels_number'));
		$installmentType = Mage::getStoreConfig('payment/Query_Cielo_Cc/installments_type');
    	$allCards = Mage::getModel('Query_Cielo/cc_types')->toOptionArray();
    	
    	$installmentsArray = array();
    	
    	foreach($allCards as $card)
    	{
			$installmentsNumber = $maxInstallments;
			
			// caso loja
			if($installmentType == '2')
			{
				$installmentsNumber = ($installmentsNumber > $card['inst_s']) ? $card['inst_s'] : $installmentsNumber;
			}
			// caso administradora
			else if($installmentType == '3')
			{
				$installmentsNumber = ($installmentsNumber > $card['inst_a']) ? $card['inst_a'] : $installmentsNumber;
			}
			
			$installmentsArray[$card['value']] = $installmentsNumber;
    	}
    	
    	return $installmentsArray;
	}

	/**
	*	Retorna todos os tokens que o cliente tem na loja
	*
	*/

	public function getCieloTokens()
	{
		// Soh pesquisa por token se a loja permiter tokenize
		if($this->getConfigData('tokenize') && Mage::getSingleton('customer/session')->isLoggedIn())
		{
			$tablePrefix = (string) Mage::getConfig()->getTablePrefix();
			
			if($tablePrefix)
			{
				$tablePrefix = "_" . $tablePrefix;
			}
			
			$readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
			$customerId = Mage::getSingleton('checkout/cart')->getQuote()->getCustomerId();		
			$query = "SELECT token_id as id,cc_type,last_digits FROM " . $tablePrefix . "query_cielo_customer_token WHERE customer_id=".$customerId;
			$cardsAllowed = $this->getAllowedCards();
 
 			$tokens = $readConnection->fetchAll($query);

 			for ($i=0; $i < count($tokens); $i++)
			{
 				foreach ($cardsAllowed as $card)
				{
 					if($tokens[$i]['cc_type'] == $card['value'])
					{
 						$tokens[$i]['image'] = $card['image'];

 					}
 				}
 			}
 			
 			return $tokens;
		}
		else
		{
			return false;
		}
	}
	
	/**
     * 
     * Pega os valores da configuracao do modulo
     * 
     */
    
    public function getConfigData($config)
	{
    	return Mage::getStoreConfig('payment/Query_Cielo_Cc/' . $config);
	}
}
