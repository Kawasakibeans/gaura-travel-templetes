<?php
/**
 * Customer AI Summarize Service
 * Business logic for AI summary endpoints
 */

namespace App\Services;

use App\DAL\CustomerAISummarizeDAL;

class CustomerAISummarizeService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new CustomerAISummarizeDAL();
    }

    /**
     * Save AI summary
     */
    public function saveSummary(array $params): array
    {
        $data = $params['data'] ?? [];
        $period = $data['period'] ?? [];
        
        $startDate = isset($period['start']) ? substr($period['start'], 0, 10) : null;
        $endDate = isset($period['end']) ? substr($period['end'], 0, 10) : null;
        $prevStart = isset($period['prev_start']) ? substr($period['prev_start'], 0, 10) : null;
        $prevEnd = isset($period['prev_end']) ? substr($period['prev_end'], 0, 10) : null;
        
        $model = $params['model'] ?? 'gpt-5-chat-latest';
        $summaryText = $params['summary_text'] ?? '';
        $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $hashSrc = json_encode([
            'start' => $startDate,
            'end' => $endDate,
            'prev_start' => $prevStart,
            'prev_end' => $prevEnd,
            'model' => $model,
            'text' => $summaryText
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $summaryHash = hash('sha256', $hashSrc);
        
        $insertData = [
            'model' => $model,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'prev_start' => $prevStart,
            'prev_end' => $prevEnd,
            'payload_json' => $payloadJson,
            'summary_text' => $summaryText,
            'prompt_tokens' => $params['prompt_tokens'] ?? null,
            'completion_tokens' => $params['completion_tokens'] ?? null,
            'total_tokens' => $params['total_tokens'] ?? null,
            'request_ms' => $params['request_ms'] ?? null,
            'avg_logprob' => $params['avg_logprob'] ?? null,
            'sum_logprob' => $params['sum_logprob'] ?? null,
            'summary_hash' => $summaryHash
        ];
        
        $tableName = $params['table_name'] ?? 'wpk4_ai_customer_summaries';
        $id = $this->dal->insertSummary($insertData, $tableName);
        
        return [
            'success' => true,
            'id' => $id,
            'message' => 'Summary saved successfully'
        ];
    }
}

