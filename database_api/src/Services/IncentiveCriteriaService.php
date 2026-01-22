<?php

namespace App\Services;

use App\DAL\IncentiveCriteriaDAL;
use Exception;

class IncentiveCriteriaService
{
    private $incentiveCriteriaDAL;

    public function __construct()
    {
        $this->incentiveCriteriaDAL = new IncentiveCriteriaDAL();
    }

    public function getDistinctPeriods()
    {
        return $this->incentiveCriteriaDAL->getDistinctPeriods();
    }
}