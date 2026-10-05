<?php

class ClienteValidator 
{
    public static function validate($form) {
        $form->getField('name')
            ->addValidation('Nome é obrigatório', new TRequiredValidator());
        $form->getField('email')
            ->addValidation('Email é obrigatório', new TRequiredValidator());
        $form->getField('email')
            ->addValidation('Email inválido', new TEmailValidator());
    }

}