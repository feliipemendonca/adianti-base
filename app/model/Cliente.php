<?php

use Adianti\Database\TRecord;

class Cliente extends TRecord
{
    const TABLENAME = 'clientes';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';

}