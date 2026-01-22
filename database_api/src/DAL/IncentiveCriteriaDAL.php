<?php

namespace App\DAL;

class IncentiveCriteriaDAL extends BaseDAL
{
    public function getDistinctPeriods()
    {
        return $this->query("SELECT DISTINCT period FROM wpk4_backend_incentive_criteria ORDER BY period DESC");
    }
}