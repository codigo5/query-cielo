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
	
class Query_Cielo_Model_WebServiceOrder
{
	public $ccType;								// bandeira do cartao de credito
	public $paymentType;						// forma de pagameto (debito, credito - a vista ou parcelado)
	public $paymentParcels;						// numero de parcelas
	
	public $clientOrderNumber;					// clientOrderNumber
	public $clientOrderValue;					// clientOrderValue
	public $clientOrderCurrency = "986";		// numero de indice da moeda utilizada (R$)
	public $clientOrderDate;					// data da operacao
	public $clientOrderDescription;				// descricao
	public $clientOrderLocale = "PT";			// idioma
	public $clientSoftDesc;						// identificador que aparece na fatura do cliente
	
	public $cieloNumber;						// identificador da loja na cielo
	public $cieloKey;							// chave da loja a cielo
	
	public $generateToken;						// flag indicando se deve gerar um token para o cliente
	public $capture;							// flag indicando quando pedido deve ser capturado
	public $autorize;							// flag indicando quando pedido deve ser autorizado
	public $postbackURL;						// url para qual o pagamento retornara o resultado da operacao
	public $tid;								// id da transacao
	public $status;								// status da transacao
	private $_xmlResponse;						// texto xml vindo da resposta da transacao
	private $_transactionError;					// erro ocorrido na transicao
	
	private $_sslVersion;						// ssl version a ser usado dependendo da versao do cUrl
	private $_webServiceURL;					// url do webservice da cielo
	private $_SSLCertificatePath;				// caminho no sistema de arquivos do certificado SSL
	private $_URLAuthTag = "url-autenticacao";	// tag que armazena a url de autenticacao da transacao
	
	const ENCODING = "ISO-8859-1";				// codificacao do xml
	const VERSION = "1.2.1";					// versao do webservice da cielo
	
	
	function __construct($params)
	{
		$baseURL 			= (isset($params['enderecoBase']))		? $params['enderecoBase'] 			: "https://qasecommerce.cielo.com.br";
		$certificatePath 	= (isset($params['caminhoCertificado']) && $params['caminhoCertificado'] != "")	? $params['caminhoCertificado'] 	: Mage::getModuleDir('', 'Query_Cielo') . "/ssl/VeriSignClass3PublicPrimaryCertificationAuthority-G5.crt";
		
		$this->_webServiceURL = $baseURL . "/servicos/ecommwsec.do";
		$this->_SSLCertificatePath = $certificatePath;


		$curlInfo = curl_version();

		if($curlInfo['version'] <= '7.26.0')
		{
			$this->_sslVersion = 1;
		}
		else
		{
			$this->_sslVersion = 4;			
		}
	}
	
	
	/**
	 *
	 * funcao utilizada para atribuir os valores base
	 * do pedido da cielo
	 * 
	 * @param string $index
	 * @param string $value
	 * 
	 * ou
	 * 
	 * @param array $index
	 */
	
	public function setData($index, $value = null)
	{
		if(is_array($index))
		{
			foreach($index as $i => $v)
			{
				$this->$i = $v;
			}
		}
		else
		{
			$this->$index = $value;
		}
	}
	
	
	
	/**
	 *
	 * funcao responsavel por montar o xml de requisicao e 
	 * realizar a criacao da transacao na cielo
	 * 
	 * @param boolean $ownerIncluded
	 * @return boolean
	 * 
	 */
	
	public function requestTransaction($ownerData)
	{
		$msg  = $this->_getXMLHeader() . "\n";
		$msg .= '<requisicao-transacao id="' . md5(date("YmdHisu")) . '" versao="' . self::VERSION . '">' . "\n   ";
		$msg .= $this->_getXMLCieloData() . "\n   ";
		$msg .= $this->_getXMLOwnerData($ownerData) . "\n   ";
		$msg .= $this->_getXMLOrderData() . "\n   ";
		$msg .= $this->_getXMLPaymentData() . "\n   ";
		$msg .= $this->_getXMLPostbackURL() . "\n   ";
		$msg .= $this->_getXMLAutorize() . "\n   ";
		$msg .= $this->_getXMLCapture() . "\n   ";
		$msg .= $this->_getXMLToken() . "\n   ";
		$msg .= '</requisicao-transacao>';
		
		$maxAttempts = 3;
		
		while($maxAttempts > 0)
		{
			if($this->_sendRequest("mensagem=" . $msg, "Transacao"))
			{
				if($this->_hasConsultationError())
				{
					Mage::log($this->_transactionError);
					return false;
				}
				
				$xml = simplexml_load_string($this->_xmlResponse);
				
				// pega dados do xml
				$this->tid = (string) $xml->tid;

				$URLAuthTag = $this->_URLAuthTag;
				
				return ((string) $xml->$URLAuthTag);
			}
			
			$maxAttempts--;
		}
		
		if($maxAttempts == 0)
		{
			Mage::log("[CIELO] Não conseguiu consultar o servidor.");
		}
		
		return false;
	}

	/**
	 *
	 * funcao responsavel por montar o xml de requisicao de token e 
	 * realizar o pedido a cielo
	 * 
	 * @param boolean $ownerData
	 * @return boolean | XML
	 * 
	 */

	/**
	* funcao responsavel por realizar uma requisição de criação de token
	* @param $ownerData	
	*/
	public function requestToken($ownerData)
	{
		$msg  = $this->_getXMLHeader() . "\n";
		$msg .= '<requisicao-token id="' . md5(date("YmdHisu")) . '" versao="' . self::VERSION . '">' . "\n   ";
		$msg .= $this->_getXMLCieloData() . "\n   ";
		$msg .= $this->_getXMLOwnerData($ownerData) . "\n   ";
		$msg .= '</requisicao-token>';
		
		$maxAttempts = 3;
		
		while($maxAttempts > 0)
		{
			if($this->_sendRequest("mensagem=" . $msg, "Token"))
			{
				if($this->_hasConsultationError())
				{
					Mage::log($this->_transactionError);
					return false;
				}
				
				$xml = simplexml_load_string($this->_xmlResponse);
				
				return $xml;
			}
			
			$maxAttempts--;
		}
		
		if($maxAttempts == 0)
		{
			Mage::log("[CIELO] Não conseguiu consultar o servidor.");
		}
		
		return false;	
	}
	
	
	/**
	 *
	 * funcao responsavel por montar o xml de requisicao e 
	 * realizar a consulta do status da transacao
	 * 
	 * @return boolean | string
	 * 
	 */
	 
	public function requestConsultation()
	{
		$msg  = $this->_getXMLHeader() . "\n";
		$msg .= '<requisicao-consulta id="' . md5(date("YmdHisu")) . '" versao="' . self::VERSION . '">' . "\n   ";
		$msg .= '<tid>' . $this->tid . '</tid>' . "\n   ";
		$msg .= $this->_getXMLCieloData() . "\n   ";
		$msg .= '</requisicao-consulta>';
		
		$maxAttempts = 3;
		
		while($maxAttempts > 0)
		{
			if($this->_sendRequest("mensagem=" . $msg, "Consulta"))
			{
				if($this->_hasConsultationError())
				{
					Mage::log($this->_transactionError);
					return false;
				}
				
				$xml = simplexml_load_string($this->_xmlResponse);
				$this->status = (string) $xml->status;
				
				return $this->status;
			}
			
			$maxAttempts--;
		}
		
		if($maxAttempts == 0)
		{
			Mage::log("[CIELO] Não conseguiu consultar o servidor.");
		}
		
		return false;
	}
	
	public function requestConsultationByStoreId()
	{
		$msg  = $this->_getXMLHeader() . "\n";
		$msg .= '<requisicao-consulta-chsec id="' . md5(date("YmdHisu")) . '" versao="' . self::VERSION . '">' . "\n   ";
		$msg .= '<numero-pedido>' . $this->clientOrderNumber . '</numero-pedido>' . "\n   ";
		$msg .= $this->_getXMLCieloData() . "\n   ";
		$msg .= '</requisicao-consulta-chsec>';

		$maxAttempts = 1;
		
		while($maxAttempts > 0)
		{
			if($this->_sendRequest("mensagem=" . $msg, "Consulta"))
			{
				if($this->_hasConsultationError())
				{
					Mage::log($this->_transactionError);
					return false;
				}
				
				$xml = simplexml_load_string($this->_xmlResponse);
				$this->status = (string) $xml->status;
				$this->tid = (string) $xml->tid;
				
				return $this->status;
			}
			
			$maxAttempts--;
		}
		
		if($maxAttempts == 0)
		{
			Mage::log("[CIELO] Não conseguiu consultar o servidor.");
		}
		
		return false;
	}
	
	
	
	/**
	 *
	 * funcao responsavel por montar o xml de requisicao e 
	 * realizar a captura da transacao
	 * 
	 * @return boolean | string
	 * 
	 */
	 
	public function requestCapture($value)
	{
		$msg  = $this->_getXMLHeader() . "\n";
		$msg .= '<requisicao-captura id="' . md5(date("YmdHisu")) . '" versao="' . self::VERSION . '">' . "\n   ";
		$msg .= '<tid>' . $this->tid . '</tid>' . "\n   ";
		$msg .= $this->_getXMLCieloData() . "\n   ";
		$msg .= '<valor>' . $value . '</valor>' . "\n   ";
		$msg .= '</requisicao-captura>';
		
		$maxAttempts = 3;
		
		while($maxAttempts > 0)
		{	
			if($this->_sendRequest("mensagem=" . $msg, "Captura"))
			{
				if($this->_hasConsultationError())
				{
					Mage::log($this->_transactionError);
					return false;
				}
				
				$xml = simplexml_load_string($this->_xmlResponse);
				$this->status = (string) $xml->status;
				
				return $this->status;
			}
			
			$maxAttempts--;
		}
		
		if($maxAttempts == 0)
		{
			Mage::log("[CIELO] Não conseguiu consultar o servidor.");
		}
		
		return false;
	}
	
	
	
	/**
	 *
	 * funcao responsavel por montar o xml de requisicao e 
	 * realizar o cancelamento da transacao
	 * 
	 * @return boolean | string
	 * 
	 */
	 
	public function requestCancellation($value)
	{
		$msg  = $this->_getXMLHeader() . "\n";
		$msg .= '<requisicao-cancelamento id="' . md5(date("YmdHisu")) . '" versao="' . self::VERSION . '">' . "\n   ";
		$msg .= '<tid>' . $this->tid . '</tid>' . "\n   ";
		$msg .= $this->_getXMLCieloData() . "\n   ";
		if($value !== false)
		{
			$msg .= '<valor>' . $value . '</valor>' . "\n   ";
		}
		$msg .= '</requisicao-cancelamento>';
		
		$maxAttempts = 3;
		
		while($maxAttempts > 0)
		{
			if($this->_sendRequest("mensagem=" . $msg, "Cancelamento"))
			{
				if($this->_hasConsultationError())
				{
					Mage::log($this->_transactionError);
					return false;
				}
				
				$xml = simplexml_load_string($this->_xmlResponse);
				$this->status = (string) $xml->status;
				
				return $this->status;
			}
			
			$maxAttempts--;
		}
		
		if($maxAttempts == 0)
		{
			Mage::log("[CIELO] Não conseguiu consultar o servidor.");
		}
		
		return false;
	}
	
	
	
	/**
	 *
	 * funcao responsavel por conferir se houve erro na requisicao
	 * 
	 * @return boolean
	 * 
	 */
	
	private function _hasConsultationError()
	{
		// certificao SSL invalido
		if(stripos($this->_xmlResponse, "SSL certificate problem") !== false)
		{
			$this->_transactionError = "[CIELO] Certificado SSL inválido.";
			return true;
		}
		
		$xml = simplexml_load_string($this->_xmlResponse);
		
		// tempo de requisicao expirou
		if($xml == null)
		{
			$this->_transactionError = "[CIELO] Tempo de espera na requisição expirou.";
			return true;
		}
		
		// retorno de erro da cielo
		if($xml->getName() == "erro")
		{
			$this->_transactionError = "[CIELO: " . $xml->codigo . "] " . utf8_decode($xml->mensagem);
			return true;
		}
		
		return false;
	}
	
	
	/**
	 *
	 * retorna a msg de erro da requisicao
	 * 
	 * @return string
	 * 
	 */
	
	public function getError()
	{
		return $this->_transactionError;
	}
	
	
	/**
	 *
	 * funcao que realiza a requisicao
	 * 
	 * @param string $postMsg
	 * @param string $transacao
	 * 
	 * @return string | boolean
	 * 
	 */
	
	private function _sendRequest($postMsg, $transacao)
	{
		$curl_session = curl_init();
		
		curl_setopt($curl_session, CURLOPT_URL, $this->_webServiceURL);
		curl_setopt($curl_session, CURLOPT_FAILONERROR, true);
		curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl_session, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl_session, CURLOPT_CAINFO, $this->_SSLCertificatePath);
		curl_setopt($curl_session, CURLOPT_SSLVERSION, $this->_sslVersion);
		curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl_session, CURLOPT_TIMEOUT, 40);
		curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_session, CURLOPT_POST, true);
		curl_setopt($curl_session, CURLOPT_POSTFIELDS, $postMsg );
		
		$this->_xmlResponse = curl_exec($curl_session);
	
		if(!$this->_xmlResponse)
		{
			return false;
		}
		
		curl_close($curl_session);
		
		return true;
	}		
	
	/**
	 *
	 * funcao que que consulta o retorno xml
	 * 
	 * @return string | boolean
	 * 
	 */
	
	public function getXmlResponse()
	{
		try
		{
			return simplexml_load_string($this->_xmlResponse);
		}
		catch(Exception $e)
		{
			return false;
		}
	}
	
	/**
	 *
	 * funcoes que montam o conteudo xml da requisicao
	 * 
	 * @return string
	 * 
	 */
	
	private function _getXMLHeader()
	{
		return '<?xml version="1.0" encoding="' . self::ENCODING . '" ?>'; 
	}
	
	private function _getXMLCieloData()
	{
		$msg = '<dados-ec>' . "\n      " .
					'<numero>'
						. $this->cieloNumber . 
					'</numero>' . "\n      " .
					'<chave>'
						. $this->cieloKey .
					'</chave>' . "\n   " .
				'</dados-ec>';
						
		return $msg;
	}
	
	private function _getXMLOwnerData($ownerData)
	{
		if(!$ownerData)
		{
			return "";
		}
		
		if(isset($ownerData['token']) && $ownerData['token'])
		{
			$msg = '<dados-portador>' . "\n      " . 
						'<token>' 
							. urlencode($ownerData['token']) .
						'</token>' . "\n     ".
					'</dados-portador>';
		}
		else
		{
			$msg = '<dados-portador>' . "\n      " . 
						'<numero>' 
							. $ownerData['number'] .
						'</numero>' . "\n      " .
						'<validade>'
							. $ownerData['exp_date'] .
						'</validade>' . "\n      " .
						'<indicador>'
							. "1" .
						'</indicador>' . "\n      " .
						'<codigo-seguranca>'
							. $ownerData['sec_code'] .
						'</codigo-seguranca>' . "\n      " . 
						'<nome-portador>'
							. $ownerData['name'] .
						'</nome-portador>' . "\n   " .
					'</dados-portador>';
		}
		
		return $msg;
	}
	
	private function _getXMLOrderData()
	{
		$this->clientOrderDate = date("Y-m-d") . "T" . date("H:i:s");
		
		$msg = '<dados-pedido>' . "\n      " .
					'<numero>'
						. $this->clientOrderNumber . 
					'</numero>' . "\n      " .
					'<valor>'
						. $this->clientOrderValue.
					'</valor>' . "\n      " .
					'<moeda>'
						. $this->clientOrderCurrency .
					'</moeda>' . "\n      " .
					'<data-hora>'
						. $this->clientOrderDate .
					'</data-hora>' . "\n      ";
		
		if($this->clientOrderDescription != null && $this->clientOrderDescription != "")
		{
			$msg .= '<descricao>'
				. $this->clientOrderDescription .
				'</descricao>' . "\n      ";
		}
		
		$msg .= '<idioma>'
					. $this->clientOrderLocale .
				'</idioma>' . "\n      ";
		
		if($this->clientSoftDesc != null && $this->clientSoftDesc != "")
		{
			'<softDescriptor>'
				. $this->clientSoftDesc .
			'</softDescriptor>' . "\n   ";
		}
		
		$msg .= '</dados-pedido>';
						
		return $msg;
	}
	
	private function _getXMLPaymentData()
	{
		$msg = '<forma-pagamento>' . "\n      " .
					'<bandeira>' 
						. $this->ccType .
					'</bandeira>' . "\n      " .
					'<produto>'
						. $this->paymentType .
					'</produto>' . "\n      " .
					'<parcelas>'
						. $this->paymentParcels .
					'</parcelas>' . "\n   " .
				'</forma-pagamento>';
						
		return $msg;
	}
	
	private function _getXMLPostbackURL()
	{
		$msg = '<url-retorno>' . $this->postbackURL . '</url-retorno>';
		
		return $msg;
	}
	
	private function _getXMLAutorize()
	{
		$msg = '<autorizar>' . $this->autorize . '</autorizar>';
		
		return $msg;
	}
	
	private function _getXMLCapture()
	{
		$msg = '<capturar>' . $this->capture . '</capturar>';
		
		return $msg;
	}
	
	private function _getXMLToken()
	{
		$msg = '<gerar-token>' . $this->generateToken . '</gerar-token>';
		
		return $msg;
	}
}
	