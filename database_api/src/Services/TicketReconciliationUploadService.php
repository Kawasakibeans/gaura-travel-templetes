<?php
/**
 * Ticket Reconciliation Upload Service
 * Handles CSV-style verification and record updates
 */

namespace App\Services;

use App\DAL\TicketReconciliationUploadDAL;
use Exception;

class TicketReconciliationUploadService
{
    private const DATASET_PAST = 'past_data';
    private const DATASET_TICKET_ONLY = 'ticket_only';

    private TicketReconciliationUploadDAL $dal;

    public function __construct()
    {
        $this->dal = new TicketReconciliationUploadDAL();
    }

    /**
     * Verify uploaded rows against database
     *
     * @param array<string, mixed> $payload
     */
    public function checkRows(array $payload): array
    {
        $dataset = strtolower((string)($payload['dataset'] ?? self::DATASET_PAST));
        if (!in_array($dataset, [self::DATASET_PAST, self::DATASET_TICKET_ONLY], true)) {
            throw new Exception('dataset must be past_data or ticket_only', 400);
        }

        if (empty($payload['rows']) || !is_array($payload['rows'])) {
            throw new Exception('rows array is required', 400);
        }

        $rows = $payload['rows'];
        $documents = [];
        foreach ($rows as $row) {
            $document = trim((string)($row['document'] ?? ''));
            if ($document !== '') {
                $documents[] = $document;
            }
        }
        $documents = array_values(array_unique($documents));

        if ($dataset === self::DATASET_PAST) {
            $dbMap = $this->dal->getPastDataDocuments($documents);
        } else {
            $dbMap = $this->dal->getTicketOnlyDocuments($documents);
        }

        $results = [];
        foreach ($rows as $row) {
            $document = trim((string)($row['document'] ?? ''));
            if ($document === '') {
                $results[] = [
                    'document' => null,
                    'status' => 'invalid',
                    'messages' => ['Document number is required'],
                ];
                continue;
            }

            $netDueInput = $this->extractNumeric($row['net_due'] ?? null);
            $travelerName = (string)($row['traveler_name'] ?? '');
            $issueDate = $this->parseDate($row['issue_date'] ?? null);

            $dbRow = $dbMap[$document] ?? null;
            $messages = [];
            $matched = false;
            $passengerMatch = false;
            $dbPassenger = null;
            $dbNetDue = null;

            if ($dbRow) {
                $dbPassenger = (string)($dbRow['passenger_name'] ?? '');
                $dbNetDue = $this->extractNumeric($dbRow['net_due'] ?? null);

                $netDueDiffers = ($netDueInput !== null && abs($netDueInput - $dbNetDue) > 0.01);

                if ($netDueDiffers) {
                    $messages[] = sprintf(
                        'Net due mismatch (uploaded %.2f, database %.2f)',
                        $netDueInput,
                        $dbNetDue
                    );
                }

                if ($travelerName !== '') {
                    $passengerMatch = $this->fuzzyNameMatch($travelerName, $dbPassenger);
                    if (!$passengerMatch) {
                        $messages[] = 'Passenger name differs';
                    }
                }

                $matched = !$netDueDiffers;
                if ($dataset === self::DATASET_TICKET_ONLY) {
                    // Ticket-only requires only document match; passenger check is informational
                    $matched = true;
                }
            } else {
                $messages[] = 'Document not found in database';
            }

            $results[] = [
                'document' => $document,
                'net_due_input' => $netDueInput,
                'db_net_due' => $dbNetDue,
                'traveler_name' => $travelerName,
                'db_passenger_name' => $dbPassenger,
                'issue_date' => $issueDate,
                'matched' => $matched,
                'passenger_match' => $passengerMatch,
                'messages' => $messages,
            ];
        }

        return [
            'dataset' => $dataset,
            'checked' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Update past data records (used by reconcile-amount)
     *
     * @param array<string, mixed> $payload
     */
    public function updateRows(array $payload): array
    {
        $dataset = strtolower((string)($payload['dataset'] ?? self::DATASET_PAST));
        if ($dataset !== self::DATASET_PAST) {
            throw new Exception('Updates are only supported for dataset=past_data', 400);
        }

        if (empty($payload['rows']) || !is_array($payload['rows'])) {
            throw new Exception('rows array is required', 400);
        }

        $rows = $payload['rows'];
        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $document = trim((string)($row['document'] ?? ''));
                $documentType = trim((string)($row['document_type'] ?? ''));
                $vendor = trim((string)($row['vendor'] ?? ''));

                if ($document === '' || $documentType === '' || $vendor === '') {
                    throw new Exception('document, document_type, and vendor are required');
                }

                $payload = [
                    'transaction_amount' => $this->extractNumeric($row['transaction_amount'] ?? null),
                    'fare' => $this->extractNumeric($row['fare'] ?? null),
                    'vendor' => $vendor,
                    'a_l' => $row['a_l'] ?? '',
                    'tax' => $this->extractNumeric($row['tax'] ?? null),
                    'fee' => $this->extractNumeric($row['fee'] ?? null),
                    'comm' => $this->extractNumeric($row['comm'] ?? null),
                    'remark' => $row['remark'] ?? '',
                    'tax_inr' => $this->extractNumeric($row['tax_inr'] ?? null),
                    'comm_inr' => $this->extractNumeric($row['comm_inr'] ?? null),
                    'transaction_amount_inr' => $this->extractNumeric($row['transaction_amount_inr'] ?? null),
                    'fare_inr' => $this->extractNumeric($row['fare_inr'] ?? null),
                    'added_by' => $row['added_by'] ?? 'api',
                    'document' => $document,
                    'document_type' => $documentType,
                ];

                $result = $this->dal->updatePastDataRecord($payload);

                if ($result['success'] && $result['rows_affected'] > 0) {
                    $updated += $result['rows_affected'];
                } else {
                    $errors[] = [
                        'row' => $index,
                        'document' => $document,
                        'message' => 'No rows updated (check document/document_type/vendor combination)',
                    ];
                }
            } catch (Exception $e) {
                $errors[] = [
                    'row' => $index,
                    'document' => $row['document'] ?? null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'dataset' => $dataset,
            'requested' => count($rows),
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Attempt to parse Excel-like date strings
     *
     * @param mixed $value
     */
    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string)$value);

        // Already formatted YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Excel numeric date
        if (is_numeric($value)) {
            $numeric = (int)$value;
            if ($numeric > 25569) {
                $unix = ($numeric - 25569) * 86400;
                return gmdate('Y-m-d', $unix);
            }
        }

        $uppercase = strtoupper($value);
        if (preg_match('/^\d{1,2}[A-Z]{3}\d{2}$/', $uppercase)) {
            $date = \DateTime::createFromFormat('dMy', $uppercase);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'd.M.Y', 'j/n/Y', 'j-n-Y', 'dMY'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Extract numeric value from strings (e.g. "23.80 D5")
     *
     * @param mixed $value
     */
    private function extractNumeric($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        $value = str_replace(',', '', (string)$value);
        if (preg_match('/-?\d+\.?\d*/', $value, $matches)) {
            return (float)$matches[0];
        }

        return null;
    }

    /**
     * Normalise passenger name for comparison
     */
    private function normalizePaxName(string $name): string
    {
        $name = strtoupper($name);
        $name = $this->extractPaxBeforeId($name);
        $name = preg_replace('/\b(MR|MRS|MS|DR)\b/', '', $name);
        $name = preg_replace('/[^A-Z\/ ]+/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    private function extractPaxBeforeId(string $name): string
    {
        $parts = preg_split('/\s+ID/', $name, 2);
        return $parts[0];
    }

    /**
     * Rough passenger fuzzy match threshold
     */
    private function fuzzyNameMatch(string $csvName, string $dbName, float $threshold = 0.3): bool
    {
        $csvNorm = $this->normalizePaxName($csvName);
        $dbNorm = $this->normalizePaxName($dbName);

        [$lastA, $firstA] = explode('/', $csvNorm . '/');
        [$lastB, $firstB] = explode('/', $dbNorm . '/');

        if ($lastA !== $lastB) {
            return false;
        }

        $tokensA = array_filter(explode(' ', $firstA));
        $tokensB = array_filter(explode(' ', $firstB));
        $common = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));

        if (count($union) === 0) {
            return false;
        }

        $similarity = count($common) / count($union);
        return $similarity >= $threshold;
    }
}

