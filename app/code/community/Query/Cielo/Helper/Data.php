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

class Query_Cielo_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Formata o valor da compra de acordo com a definicao da Cielo
     *
     * @param   string $originalValue
     * @return  string
     */
    public function formatValueForCielo($originalValue)
    {
		if(strpos($originalValue, ".") == false)
		{
			$value = $originalValue . "00";
		}
		else
		{
			list($integers, $decimals) = explode(".", $originalValue);
			
			if(strlen($decimals) > 2)
			{
				$decimals = substr($decimals, 0, 2);
			}
			
			while(strlen($decimals) < 2)
			{
				$decimals .= "0";
			}
			
			$value = $integers . $decimals;
		}
		
		return $value;
    }
    
    /**
     * Retorna mensagem adequada ao codigo de retorno da cielo
     *
     * @param   string $statusCode
     * @return  string
     */
    public function getStatusMessage($statusCode)
    {
		switch($statusCode)
		{
			case 1:
				$label = "Processing";
				break;
			case 2:
				$label = "Authenticated";
				break;
			case 3:
				$label = "Unauthenticated";
				break;
			case 4:
				$label = "Authorized";
				break;
			case 5:
				$label = "Unauthorized";
				break;
			case 6:
				$label = "Complete";
				break;
			case 9:
				$label = "Canceled";
				break;
			case 10:
				$label = "Authenticating";
				break;
			case 12:
				$label = "Canceling";
				break;
			default:
				$label = "Error on transaction: if persists, please contact us.";
		}
		
		return $this->__($label);
    }
    
    
    /**
     * Retorna o valor de uma parcela, dados o valor total a ser parcelado, 
     * a taxa de juros e o numero de prestacoes
     *
     * @param   string $total
     * @param   string $interest
     * @param   string $periods
     * @return  string
     */
    public function calcInstallmentValue($total, $interest, $periods)
    {
		/* 
		 * Formula do coeficiente:
		 * 
		 * juros / ( 1 - 1 / (1 + i)^n )
		 * 
		 */
		
		// confere se taxa de juros = 0 
		if($interest <= 0)
		{
			return ($total / $periods);
		}
		
		// calcula o coeficiente, seguindo a formula acima
		$coefficient = pow((1 + $interest), $periods);
		$coefficient = 1 / $coefficient;
		$coefficient = 1 - $coefficient;
		$coefficient = $interest / $coefficient;
		
		// retorna o valor da parcela
		return ($total * $coefficient);
    }
    
    
    /**
     * 
     * Percorre um objeto XML, passando-o para HTML
     * 
     */
    
    public function xmlToHtml($xmlNode, $tab = 0)
	{
    	if(count($xmlNode) > 0)
		{
			$childrenNode = $xmlNode->children();
			$childrenString = "";
			
			// monta o valor do noh
			foreach($childrenNode as $cn)
			{
				$childrenString .= $this->xmlToHtml($cn, $tab + 1);
			}
			
			$nodeString = "<div style='margin-left: " . ($tab * 25) . "px;'><b>" . $xmlNode->getName() . "</b></div>";
			
			return $nodeString . $childrenString;
		}
		else
		{
			$nodeString = "<div style='margin-left: " . ($tab * 25) . "px;'><b>" . $xmlNode->getName() . ":</b> " . ((string) $xmlNode) . "</div>";
			return $nodeString;
		}
	}
}
