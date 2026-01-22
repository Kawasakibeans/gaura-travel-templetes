<?php

namespace App\Services;

use App\DAL\MarketingRemarkDAL;
use Exception;

class MarketingRemarkService
{
    private $marketingRemarkDAL;

    public function __construct()
    {
        $this->marketingRemarkDAL = new MarketingRemarkDAL();
    }

    public function getMarketingRemarks()
    {
        return $this->marketingRemarkDAL->getMarketingRemarks();
    }
}