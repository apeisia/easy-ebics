<?php

namespace Apeisia\EasyEbics;

use Kingsquare\Banking\Statement as BaseStatement;

class Statement extends BaseStatement
{

    /**
     * @return Transaction[]
     */
    public function getTransactions()
    {
        // just change the return type
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::getTransactions();
    }
}
