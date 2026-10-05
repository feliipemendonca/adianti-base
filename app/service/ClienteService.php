<?php

class ClienteService
{
    public static function create(Cliente $cliente)
    {
        try {

            TTransaction::open('sample');
            $cliente->store();
            TTransaction::close();

            return new TMessage('info', 'Cliente salvo com sucesso');

        } catch (Exception $e) {

            TTransaction::rollback();
            return new TMessage('error', $e->getMessage());
        }
    }
}