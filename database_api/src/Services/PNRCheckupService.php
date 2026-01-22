<?php

namespace App\Services;

use App\DAL\PNRCheckupDAL;

class PNRCheckupService
{
    private $dal;

    public function __construct(PNRCheckupDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get passenger by first name, last name, and PNR
     */
    public function getPaxByNameAndPNR($firstName, $lastName, $pnr)
    {
        return $this->dal->getPaxByNameAndPNR($firstName, $lastName, $pnr);
    }

    /**
     * Get currency conversion rate for a booking
     */
    public function getCurrencyRate($orderId)
    {
        return $this->dal->getCurrencyRate($orderId);
    }

    /**
     * Get passenger by order_id, first name, and last name
     */
    public function getPaxByOrderAndName($orderId, $firstName, $lastName)
    {
        return $this->dal->getPaxByOrderAndName($orderId, $firstName, $lastName);
    }

    /**
     * Get fare information for a passenger
     */
    public function getFareByPaxId($paxId)
    {
        return $this->dal->getFareByPaxId($paxId);
    }

    /**
     * Get tax information for a passenger
     */
    public function getTaxByPaxId($paxId)
    {
        return $this->dal->getTaxByPaxId($paxId);
    }

    /**
     * Get metadata from history of updates
     */
    public function getHistoryMetadata($metaKey, $orderId)
    {
        return $this->dal->getHistoryMetadata($metaKey, $orderId);
    }

    /**
     * Insert passenger mismatch record
     */
    public function insertPaxMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy)
    {
        return $this->dal->insertPaxMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy);
    }

    /**
     * Insert itinerary mismatch record
     */
    public function insertItineraryMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy)
    {
        return $this->dal->insertItineraryMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy);
    }

    /**
     * Get booking by order_id
     */
    public function getBookingByOrderId($orderId)
    {
        return $this->dal->getBookingByOrderId($orderId);
    }

    /**
     * Get passenger by PNR and GDS PAX ID
     */
    public function getPaxByPnrAndGdsPaxId($pnr, $gdsPaxId)
    {
        return $this->dal->getPaxByPnrAndGdsPaxId($pnr, $gdsPaxId);
    }

    /**
     * Get all history metadata for an order_id
     */
    public function getAllHistoryMetadata($orderId)
    {
        return $this->dal->getAllHistoryMetadata($orderId);
    }
}

