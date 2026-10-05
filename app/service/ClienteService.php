<?php

class ClienteService
{
    public static function getAll()
    {
        try {

            TTransaction::open('database');
            $clientes = Cliente::orderBy('name', 'asc')->load();
            TTransaction::close();

            return $clientes;

        } catch (Exception $e) {

            TTransaction::rollback();
            return new TMessage('error', $e->getMessage());

        }
    }

    public static function find($id)
    {
        try {

            TTransaction::open('database');
            $cliente = Cliente::find($id);
            TTransaction::close();

            return $cliente;

        } catch (Exception $e) {

            TTransaction::rollback();
            return new TMessage('error', $e->getMessage());

        }
    }

    public static function create(Cliente $cliente)
    {
        try {

            TTransaction::open('database');
            $cliente->store();
            TTransaction::close();

            return new TMessage('info', 'Cliente salvo com sucesso');

        } catch (Exception $e) {

            TTransaction::rollback();
            return new TMessage('error', $e->getMessage());
        }
    }

    public static function delete(Cliente $cliente)
    {
        try {
            
            TTransaction::open('database');
            $cliente->delete();
            TTransaction::close();

            return true;

        } catch (Exception $e) {

            TTransaction::rollback();
            return new TMessage('error', $e->getMessage());

        }
    }

    public static function count()
    {
        try {

            TTransaction::open('database');
            $count = Cliente::count();
            TTransaction::close();

            return $count;

        } catch (Exception $e) {

            TTransaction::rollback();
            return new TMessage('error', $e->getMessage());
            
        }
    }
}