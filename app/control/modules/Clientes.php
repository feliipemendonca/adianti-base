<?php

use Adianti\Wrapper\BootstrapFormBuilder;

class Clientes extends TPage {
    
    private $form;

    public function __construct(){
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form');
        $this->createFields();
        $this->createActions();

        parent::add($this->form);
    }

    private function createFields()
    {
        $name =  new TEntry('name');
        $name->setSize('100%');

        $email =  new TEntry('email');
        $email->setSize('100%');
        
        $this->form->addFields([new TLabel('Nome')], [$name]);
        $this->form->addFields([new TLabel('Email')], [$email]);

        $this->configureValidations();
    }

    private function createActions()
    {
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save');
        // $this->form->addAction('Cancelar', new TAction([$this, 'onCancel']), 'fa:times');
    }

    private function configureValidations()
    {
        ClienteValidator::validate($this->form);
    }

    public function onSave()
    {
        try {
            $this->form->validate();

            ClienteService::create($this->form->getData(Cliente::class));
            $this->form->clear();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        } 
    } 
}

 