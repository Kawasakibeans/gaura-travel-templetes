<?php

namespace App\DAL;

class MarketingCategoryDAL extends BaseDAL
{
    public function getMarketingCategories()
    {
        return $this->query("SELECT id, category_name FROM wpk4_backend_marketing_category ORDER BY category_name");
    }

    public function getActiveChannelsWithCategories()
    {
        return $this->query("SELECT c.channel_name, cat.id as category_id, cat.category_name 
        FROM wpk4_backend_marketing_channel c
        LEFT JOIN wpk4_backend_marketing_category cat ON c.category_id = cat.id
        WHERE c.status = 'Active'
        ORDER BY c.channel_name");
    }
}