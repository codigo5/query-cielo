
	function queryCieloUpdateInstallments(cardType,installments)
	{
		var installmentsField = document.getElementById('cielo-installments');
		
		// ajusta valor do campo
		if(installmentsField.value > installments)
		{
			installmentsField.value = 1;
		}
		
		// ajusta opcoes mostradas
		for(var i = 0; i < installmentsField.options.length; i++)
		{
			if(installments < (i + 1))
			{
				installmentsField.options[i].style.display = "none";
			}
			else
			{
				installmentsField.options[i].style.display = "block";
			}
		}
		
		// limpa o restante do formulario
		cleanCcForm(cardType);
	}
	
	function denyNotNumberCc(field, event)
	{
        var keyCode = ('which' in event) ? event.which : event.keyCode;
		
		// teclas backspace e delete
        if(keyCode == 8 || keyCode == 46)
			return true;
		
		// tecla tab
        if(keyCode == 9)
			return true;
		
		// teclas <- e ->
        if(keyCode == 37 || keyCode == 39)
			return true;
		
		// teclas home e end
        if(keyCode == 36 || keyCode == 35)
			return true;
		
		// teclas numericas
        if((keyCode >= 48 && keyCode <= 57) || (keyCode >= 96 && keyCode <= 105))
			return true;
        
		return false;
	}

	function showCardDataCc(show)
	{
		if(show)
		{
			$('card-data-cc').show();
		}
		else
		{
			$('card-data-cc').hide();
		}
	}

	function unlockRequiredFields()
	{
		$('cielo-cc-card-number').removeClassName('required-entry');
		$('cielo-cc-security-code').removeClassName('required-entry');
		$('cielo-cc-card_expiration-mh').removeClassName('required-entry');
		$('cielo-cc-card_expiration-yr').removeClassName('required-entry');
		$('cielo-cc-card_owner').removeClassName('required-entry');
	}

	function lockRequiredFields()
	{
		$('cielo-cc-card-number').addClassName('required-entry');
		$('cielo-cc-security-code').addClassName('required-entry');
		$('cielo-cc-card_expiration-mh').addClassName('required-entry');
		$('cielo-cc-card_expiration-yr').addClassName('required-entry');
		$('cielo-cc-card_owner').addClassName('required-entry');	
	}
	
	function cleanCcForm(cardType)
	{
		var currentCcType = $('cielo-current-cc-type').value;
		$('cielo-current-cc-type').value = cardType;
		
		if(currentCcType != cardType)
		{
			document.getElementById('cielo-cc-card-number').value = "";
			document.getElementById('cielo-cc-security-code').value = "";
			document.getElementById('cielo-cc-card_expiration-mh').value = "";
			document.getElementById('cielo-cc-card_expiration-yr').value = "";
			document.getElementById('cielo-cc-card_owner').value = "";
		}
	}
	
	function selectCardTypeCc(element)
	{
		// remove as bandeiras previamente selecionadas
		$$('img.card-type-image.selected').forEach(function(img)
		{
			img.removeClassName('selected');
		});
		
		// adiciona a bandeira atual como selecionada
		$(element).addClassName('selected');
		$('query-cielo-cc-type').value = $(element).getAttribute('ccType');
		
		// mostra formulario
		$$('li.card-data-form').forEach(function(li)
		{
			li.setStyle({"display": "block"});
		});
	}

	function queryCieloCcMask(event)
	{	
		var field = event.currentTarget;
		
		field.maxLength=14;
		
		if(queryCieloCcSpecialKeys(event))
		{
			return true;
		}
		
		if(!queryCieloCcNumberKeys(event))
		{
			Event.stop(event);
			return false;
		}
		
		if(event.which == 8)
			return;
	}

	function querySetCieloCcMask(event)
	{
		var field = event.currentTarget;

		// verifica o tamanho do campo e determina qual mascara usar
		if (field.value.length == 11)
		{ 
			//CPF

			//Coloca um ponto entre o terceiro e o quarto dígitos
			field.value = field.value.replace(/(\d{3})(\d)/,"$1.$2");

			//Coloca um ponto entre o terceiro e o quarto dígitos
			field.value = field.value.replace(/(\d{3})(\d)/,"$1.$2");

			//Coloca um hífen entre o terceiro e o quarto dígitos
			field.value = field.value.replace(/(\d{3})(\d{1,2})$/,"$1-$2");
					
			//field.addClassName('validate-cpf');

		}
		else if(field.value.length == 14)
		{ 
			//CNPJ

			//Coloca ponto entre o segundo e o terceiro dígitos
			field.value = field.value.replace(/^(\d{2})(\d)/,"$1.$2");

			//Coloca ponto entre o quinto e o sexto dígitos
			field.value = field.value.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3");

			//Coloca uma barra entre o oitavo e o nono dígitos
			field.value = field.value.replace(/\.(\d{3})(\d)/,".$1/$2");

			//Coloca um hífen depois do bloco de quatro dígitos
			field.value = field.value.replace(/(\d{4})(\d)/,"$1-$2");

		}
	}

	function queryCieloCcNumberKeys(event)
	{	
		var keyCode = ('which' in event) ? event.which : event.keyCode;
		
		// teclas numericas
		if((keyCode >= 48 && keyCode <= 57) || (keyCode >= 96 && keyCode <= 105))
			return true;
			
		return false;
	}

	function queryCieloCcSpecialKeys(event)
	{
		var keyCode = ('which' in event) ? event.which : event.keyCode;
		
		// teclas backspace e delete
		if(keyCode == 8 || keyCode == 46)
			return true;
		
		// tecla tab
		if(keyCode == 9)
			return true;
		
		// teclas <- e ->
		if(keyCode == 37 || keyCode == 39)
			return true;
		
		// teclas home e end
		if(keyCode == 36 || keyCode == 35)
			return true;
			
		return false;
	}

