<?php
/**
 * Customer Service - Business Logic Layer
 * Adapted from existing CustomerService.php
 */

namespace App\Services;

use App\DAL\MarketingCategoryDAL;
use Exception;

class MarketingCategoryService
{
    private $marketingCategoryDAL;

    public function __construct()
    {
        $this->marketingCategoryDAL = new MarketingCategoryDAL();
    }

    public function getMarketingCategories()
    {
        return $this->marketingCategoryDAL->getMarketingCategories();
    }

    public function getActiveChannelsWithCategories()
    {
        return $this->marketingCategoryDAL->getActiveChannelsWithCategories();
    }



}

