<?php

use Adianti\Wrapper\BootstrapFormBuilder;

class Clientes extends TPage {
    
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form');
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->createFields();
        $this->createActions();
        $this->createDataGrid();

        $inputSearch = new TEntry('search');
        $inputSearch->placeholder = 'Pesquisar cliente';
        $inputSearch->setSize('100%');

        $this->datagrid->enableSearch($inputSearch, 'name');

        $panel = new TPanelGroup('Lista de Clientes');
        $panel->addHeaderWidget($inputSearch);
        $panel->add($this->datagrid)->style = 'width: 100%; overflow-x: auto';
        $panel->addFooter('Total de clientes: ' . ClienteService::count());

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $vbox->add($panel);

       
        parent::add($this->form);
        parent::add($vbox);

        $this->onReload();
    }

    private function createDataGrid()
    {
        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'left', '10%'));
        $this->datagrid->addColumn(new TDataGridColumn('name', 'Nome', 'left', '60%'));
        $this->datagrid->addColumn(new TDataGridColumn('email', 'Email', 'left', '30%'));

        $actionEdit = new TDataGridAction([$this, 'onEdit']);
        $actionEdit->setField('id');
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:pencil blue');

        $actionDelete = new TDataGridAction([$this, 'onDelete']);
        $actionDelete->setField('id');
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();
    }

    public function onEdit($param)
    {
        try {

            $id = $param['key'] ?? $param['id'] ?? null;

            if (!$id) {
                return;
            }

            $cliente = ClienteService::find($id);

            if ($cliente instanceof Cliente) {
                $this->form->setData($cliente);
            }

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function onDelete($param)
    {
        try {
            
            $id = $param['key'] ?? $param['id'] ?? null;

            if (!$id) {
                return;
            }

            $cliente = ClienteService::find($id);

            if ($cliente instanceof Cliente) {
                ClienteService::delete($cliente);
                $this->onReload();
                new TMessage('info', 'Cliente excluído com sucesso');
            }

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function onReload()
    {
        $this->datagrid->clear();

        $clientes = ClienteService::getAll();

        if (is_array($clientes)) {
            $this->datagrid->addItems($clientes);
        }
    }

    private function createFields()
    {
        $name =  new TEntry('name');
        $name->setSize('100%');

        $email =  new TEntry('email');
        $email->setSize('100%');
        
        $id = new THidden('id');

        $this->form->addFields([$id]);
        $this->form->addFields([new TLabel('Nome')], [$name]);
        $this->form->addFields([new TLabel('Email')], [$email]);

        $this->configureValidations();
    }

    private function createActions()
    {
        $action = $this->form->getData(Cliente::class)->id ? 'Atualizar' : 'Salvar';
        $this->form->addAction($action, new TAction([$this, 'onSave']), 'fa:save');
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
            $this->onReload();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        } 
    } 
}

 