<?php

namespace Apeisia\EasyEbics;

use Kingsquare\Banking\Transaction as BaseTransaction;

class Transaction extends BaseTransaction
{
    private $structuredDescription;

    /**
     * @return mixed
     */
    public function getStructuredDescription()
    {
        return $this->structuredDescription;
    }

    /**
     * @param mixed $structuredDescription
     * @return self
     */
    public function setStructuredDescription($structuredDescription): self
    {
        $this->structuredDescription = $structuredDescription;

        return $this;
    }

}
