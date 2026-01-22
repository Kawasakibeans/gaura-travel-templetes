<?php

namespace App\DAL;

class MarketingRemarkDAL extends BaseDAL
{

    // Get all marketing remarks
    public function getMarketingRemarks()
    {
        return $this->query("SELECT channel, metric_impact, start_date, end_date FROM wpk4_backend_marketing_remarks WHERE start_date IS NOT NULL AND end_date IS NOT NULL");
    }

}