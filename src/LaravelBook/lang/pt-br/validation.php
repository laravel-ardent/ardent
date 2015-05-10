<?php

return array(
	'validation' => array(
		/*
		|--------------------------------------------------------------------------
		| Validation Language Lines
		|--------------------------------------------------------------------------
		|
		| The following language lines contain the default error messages used by
		| the validator class. Some of these rules have multiple versions such
		| such as the size rules. Feel free to tweak each of these messages.
		|
		*/

		"accepted"         => "O campo :attribute deve ser marcado",
		"active_url"       => "O campo :attribute não é uma URL válida.",
		"after"            => "O campo :attribute deve ser uma data após :date.",
		"alpha"            => "O campo :attribute só pode conter letras.",
		"alpha_dash"       => "O campo :attribute só pode conter letras, números e hífens..",
		"alpha_num"        => "O campo :attribute só pode conter letras e números",
		"before"           => "O campo :attribute só pode conter uma data antes de :date.",
		"between"          => array(
			"numeric" => "O campo :attribute deve conter valores entre :min - :max.",
			"file"    => "O campo :attribute deve ter o tamanho entre :min - :max kilobytes.",
			"string"  => "O campo :attribute deve ter entre :min - :max caracteres.",
		),
		"confirmed"        => "A confirmação do campo :attribute não corresponde.",
		"date"             => "O campo :attribute contém uma data inválida.",
		"date_format"      => "O campo :attribute não conrrespode ao formato :format.",
		"different"        => "Os campos :attribute e :other devem ser diferentes.",
		"digits"           => "O campo :attribute deve ser :digits digitos.",
		"digits_between"   => "O campo :attribute deve estar entre :min e :max digitos.",
		"email"            => "O campo :attribute tem formato inválido.",
		"exists"           => "O campo selecionado :attribute é inválido.",
		"image"            => "O campo :attribute deve conter uma imagem.",
		"in"               => "O valor selecionado em :attribute é invalido.",
		"integer"          => "O campo :attribute deve conter um valor númerico.",
		"ip"               => "O campo :attribute deve conter um endereço de IP válido.",
		"max"              => array(
			"numeric" => "O campo :attribute não pode ser maior que :max.",
			"file"    => "O campo :attribute não pode ser maior que :max kilobytes.",
			"string"  => "O campo :attribute não pode ser maior que  :max caracteres.",
		),
		"mimes"            => "O campo :attribute deve conter um arquivo do  tipo: :values.",
		"min"              => array(
			"numeric" => "O campo :attribute deve ser no mínimo :min.",
			"file"    => "O campo :attribute deve ser no mínimo :min kilobytes.",
			"string"  => "O campo :attribute deve ser no mínimo :min caracteres.",
		),
		"not_in"           => "O campo :attribute selecionado é inválido.",
		"numeric"          => "O campo :attribute deve ser númerico.",
		"regex"            => "O campo :attribute é inválido.",
		"required"         => "O campo :attribute é obrigatório.",
		"required_if"      => "O campo :attribute é obrigatório quando :other é :value.",
		"required_with"    => "O campo :attribute é obrigatório quando :values está presente.",
		"required_without" => "O campo :attribute é obrigatório quando :values não está presente.",
		"same"             => "Os campos :attribute e :other devem conrrespoder.",
		"size"             => array(
			"numeric" => "O campo :attribute deve ser :size.",
			"file"    => "O campo :attribute deve ser de :size kilobytes.",
			"string"  => "O campo :attribute deve ser de :size caracteres.",
		),
		"unique"           => "O campo :attribute já existe.",
		"url"              => "O campo :attribute tem formato inválido.",

		/*
		|--------------------------------------------------------------------------
		| Custom Validation Language Lines
		|--------------------------------------------------------------------------
		|
		| Here you may specify custom validation messages for attributes using the
		| convention "attribute.rule" to name the lines. This makes it quick to
		| specify a specific custom language line for a given attribute rule.
		|
		*/

		'custom'           => array(),

		/*
		|--------------------------------------------------------------------------
		| Custom Validation Attributes
		|--------------------------------------------------------------------------
		|
		| The following language lines are used to swap attribute place-holders
		| with something more reader friendly such as E-Mail Address instead
		| of "email". This simply helps us make messages a little cleaner.
		|
		*/

		'attributes'       => array(),
	)
);
