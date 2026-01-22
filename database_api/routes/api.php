<?php
/**
 * API Routes - Optimized for scalability
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Autoload core classes
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) require $file;
});

function getHttpStatusCode(Exception $e): int
{
    $code = $e->getCode();
    // Convert to integer if it's numeric, otherwise default to 500
    // MySQL error codes (like '42S02') are strings and not valid HTTP status codes
    $code = is_numeric($code) ? (int)$code : 500;
    // Ensure it's a valid HTTP status code range
    return ($code >= 400 && $code < 600) ? $code : 500;
}
// Helper functions
function jsonResponse($response, $data, $status = 'success', $message = null, $code = 200) {
    $response->getBody()->write(json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]));
    return $response->withStatus($code)->withHeader('Content-Type', 'application/json');
}

function errorResponse($response, $message, $code = 500) {
    return jsonResponse($response, null, 'error', $message, $code);
}

// API Root - GET /v1
$app->get('/v1', function (Request $request, Response $response) {
    $uri = $request->getUri();
    return jsonResponse($response, [
        'api' => 'Database API v1',
        'version' => '1.0',
        'status' => 'operational',
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoints' => [
            'health' => '/v1/health',
            'documentation' => 'See API documentation for available endpoints',
            'base_url' => $uri->getScheme() . '://' . $uri->getHost() . $uri->getPath()
        ]
    ], 'success', 'Database API v1 is operational');
});

// Health check
$app->get('/v1/health', function (Request $request, Response $response) {
    try {
        $this->get('db')->query('SELECT 1');
        return jsonResponse($response, [
            'status' => 'operational',
            'database' => 'connected',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        return errorResponse($response, 'Database connection failed', 500);
    }
});

// ======================================
// FLIGHT MULTICITY ENDPOINTS
// ======================================

// GET/POST IP check endpoint (supports both GET with query params and POST with body)
$app->map(['GET', 'POST'], '/v1/flight-multicity/ip-check', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\FlightMulticityService();
        
        // Support both GET (query params) and POST (body params)
        $params = $request->getMethod() === 'POST' 
            ? ($request->getParsedBody() ?? []) 
            : $request->getQueryParams();
        
        $data = $service->checkIp($params);

        return jsonResponse($response, $data, 'success', 'IP check completed successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/flight-multicity/promo', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\FlightMulticityService();
        $data = $service->getPromoEligibility($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Promo eligibility evaluated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/flight-multicity/trip-availability', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\FlightMulticityService();
        $data = $service->getTripAvailability($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Trip availability retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// API info
$app->get('/v1/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'name' => 'Database API Layer',
        'version' => 'v1.0',
        'framework' => 'Slim 4',
        'endpoints' => [
            'health' => 'GET /v1/health',
            'payments' => [
                'GET /v1/customers/order/{orderId}/payments',
                'POST /v1/customers/order/{orderId}/payments',
                'DELETE /v1/customers/order/{orderId}/payments'
            ],
            'tables' => [
                'fit-flight-schedule' => ['GET /v1/fit-flight-schedule', 'GET /v1/fit-flight-schedule/{id}', 'POST /v1/fit-flight-schedule', 'PUT /v1/fit-flight-schedule/{id}', 'DELETE /v1/fit-flight-schedule/{id}'],
                'team-member-incentives' => ['GET /v1/team-member-incentives', 'GET /v1/team-member-incentives/{id}', 'POST /v1/team-member-incentives', 'PUT /v1/team-member-incentives/{id}', 'DELETE /v1/team-member-incentives/{id}'],
                'travel-costs' => ['GET /v1/travel-costs', 'GET /v1/travel-costs/{id}', 'POST /v1/travel-costs', 'PUT /v1/travel-costs/{id}', 'DELETE /v1/travel-costs/{id}'],
                'accounts-bank-account' => ['GET /v1/accounts-bank-account', 'GET /v1/accounts-bank-account/{id}', 'POST /v1/accounts-bank-account', 'PUT /v1/accounts-bank-account/{id}', 'DELETE /v1/accounts-bank-account/{id}'],
                'agent-booking' => ['GET /v1/agent-booking', 'GET /v1/agent-booking/{id}', 'POST /v1/agent-booking', 'PUT /v1/agent-booking/{id}', 'DELETE /v1/agent-booking/{id}'],
                'agent-codes' => ['GET /v1/agent-codes', 'GET /v1/agent-codes/{id}', 'POST /v1/agent-codes', 'PUT /v1/agent-codes/{id}', 'DELETE /v1/agent-codes/{id}'],
                'agent-inbound-call' => ['GET /v1/agent-inbound-call', 'GET /v1/agent-inbound-call/{id}', 'POST /v1/agent-inbound-call', 'PUT /v1/agent-inbound-call/{id}', 'DELETE /v1/agent-inbound-call/{id}'],
                'agent-leadership-checklist' => ['GET /v1/agent-leadership-checklist', 'GET /v1/agent-leadership-checklist/{id}', 'POST /v1/agent-leadership-checklist', 'PUT /v1/agent-leadership-checklist/{id}', 'DELETE /v1/agent-leadership-checklist/{id}'],
                'agent-leadership-checklist-tasks' => ['GET /v1/agent-leadership-checklist-tasks', 'GET /v1/agent-leadership-checklist-tasks/{id}', 'POST /v1/agent-leadership-checklist-tasks', 'PUT /v1/agent-leadership-checklist-tasks/{id}', 'DELETE /v1/agent-leadership-checklist-tasks/{id}'],
                'agent-nobel-data-addistats' => ['GET /v1/agent-nobel-data-addistats', 'GET /v1/agent-nobel-data-addistats/{id}', 'POST /v1/agent-nobel-data-addistats', 'PUT /v1/agent-nobel-data-addistats/{id}', 'DELETE /v1/agent-nobel-data-addistats/{id}'],
                'agent-nobel-data-appl-status' => ['GET /v1/agent-nobel-data-appl-status', 'GET /v1/agent-nobel-data-appl-status/{id}', 'POST /v1/agent-nobel-data-appl-status', 'PUT /v1/agent-nobel-data-appl-status/{id}', 'DELETE /v1/agent-nobel-data-appl-status/{id}'],
                'agent-nobel-data-call-history' => ['GET /v1/agent-nobel-data-call-history', 'GET /v1/agent-nobel-data-call-history/{id}', 'POST /v1/agent-nobel-data-call-history', 'PUT /v1/agent-nobel-data-call-history/{id}', 'DELETE /v1/agent-nobel-data-call-history/{id}'],
                'agent-nobel-data-call-log-callback' => ['GET /v1/agent-nobel-data-call-log-callback', 'GET /v1/agent-nobel-data-call-log-callback/{id}', 'POST /v1/agent-nobel-data-call-log-callback', 'PUT /v1/agent-nobel-data-call-log-callback/{id}', 'DELETE /v1/agent-nobel-data-call-log-callback/{id}'],
                'agent-nobel-data-call-log-history' => ['GET /v1/agent-nobel-data-call-log-history', 'GET /v1/agent-nobel-data-call-log-history/{id}', 'POST /v1/agent-nobel-data-call-log-history', 'PUT /v1/agent-nobel-data-call-log-history/{id}', 'DELETE /v1/agent-nobel-data-call-log-history/{id}'],
                'agent-nobel-data-call-log-master' => ['GET /v1/agent-nobel-data-call-log-master', 'GET /v1/agent-nobel-data-call-log-master/{id}', 'POST /v1/agent-nobel-data-call-log-master', 'PUT /v1/agent-nobel-data-call-log-master/{id}', 'DELETE /v1/agent-nobel-data-call-log-master/{id}'],
                'agent-nobel-data-call-log-sequence' => ['GET /v1/agent-nobel-data-call-log-sequence', 'GET /v1/agent-nobel-data-call-log-sequence/{id}', 'POST /v1/agent-nobel-data-call-log-sequence', 'PUT /v1/agent-nobel-data-call-log-sequence/{id}', 'DELETE /v1/agent-nobel-data-call-log-sequence/{id}'],
                'agent-nobel-data-call-rec' => ['GET /v1/agent-nobel-data-call-rec', 'GET /v1/agent-nobel-data-call-rec/{id}', 'POST /v1/agent-nobel-data-call-rec', 'PUT /v1/agent-nobel-data-call-rec/{id}', 'DELETE /v1/agent-nobel-data-call-rec/{id}'],
                'agent-nobel-data-callhisthold' => ['GET /v1/agent-nobel-data-callhisthold', 'GET /v1/agent-nobel-data-callhisthold/{id}', 'POST /v1/agent-nobel-data-callhisthold', 'PUT /v1/agent-nobel-data-callhisthold/{id}', 'DELETE /v1/agent-nobel-data-callhisthold/{id}'],
                'agent-nobel-data-cust-ob-inb-hst' => ['GET /v1/agent-nobel-data-cust-ob-inb-hst', 'GET /v1/agent-nobel-data-cust-ob-inb-hst/{id}', 'POST /v1/agent-nobel-data-cust-ob-inb-hst', 'PUT /v1/agent-nobel-data-cust-ob-inb-hst/{id}', 'DELETE /v1/agent-nobel-data-cust-ob-inb-hst/{id}'],
                'agent-nobel-data-eod-sale-booking' => ['GET /v1/agent-nobel-data-eod-sale-booking', 'GET /v1/agent-nobel-data-eod-sale-booking/{id}', 'POST /v1/agent-nobel-data-eod-sale-booking', 'PUT /v1/agent-nobel-data-eod-sale-booking/{id}', 'DELETE /v1/agent-nobel-data-eod-sale-booking/{id}']
            ]
        ]
    ]);
});

// ======================================
// PAYMENTS ENDPOINT
// ======================================

$app->get('/v1/customers/order/{orderId}/payments', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        
        if (!is_numeric($orderId)) {
            throw new Exception('Invalid order ID', 400);
        }
        
        $service = new \App\Services\CustomerService();
        $payments = $service->getPaymentsByOrderId($orderId);
        
        return jsonResponse($response, $payments, 'success', 'Payment details retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/customers/order/{orderId}/payments', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Payment data required', 400);
        }
        
        $service = new \App\Services\CustomerService();
        $result = $service->createPayment($orderId, $input);
        
        return jsonResponse($response, $result, 'success', 'Payment created', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->delete('/v1/customers/order/{orderId}/payments', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        
        // Support both query parameter and body
        $params = $request->getQueryParams();
        $body = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $paymentId = $params['payment_id'] ?? $body['payment_id'] ?? null;
        
        if (empty($paymentId)) {
            throw new Exception('Payment ID required', 400);
        }
        
        $service = new \App\Services\CustomerService();
        $result = $service->deletePayment($orderId, $paymentId);
        
        return jsonResponse($response, $result, 'success', 'Payment deleted');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// FRANCO ROUTES - PAYMENT AND AIRLINES PAYMENT DETAILS
// ======================================

// Update payment clearing fields by payment ID
$app->post('/v1/payments/{paymentId}/clear', function (Request $request, Response $response, array $args) {
	try {
		$paymentId = (int)$args['paymentId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\CustomerService();
		$result = $service->updatePaymentClearing($paymentId, $input);
		return jsonResponse($response, $result, 'success', 'Payment clearing updated');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Clear payment_deadline by pnr and group_id (separate route to avoid shadowing)
$app->post('/v1/airlines-payment-details-clear-payment-deadline', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->clearPaymentDeadline($input);
		return jsonResponse($response, $result, 'success', 'Payment deadline cleared');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Clear ticketing_deadline by pnr and group_id (separate route to avoid shadowing)
$app->post('/v1/airlines-payment-details-clear-ticketing-deadline', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->clearTicketingDeadline($input);
		return jsonResponse($response, $result, 'success', 'Ticketing deadline cleared');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// STOCK MANAGEMENT - Update a single field by auto_id
$app->post('/v1/stock-management/stock/{autoId}/field', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\StockManagementService();
		$result = $service->updateStockField($autoId, $input);
		return jsonResponse($response, $result, 'success', 'Stock field updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// STOCK MANAGEMENT - Get a single stock row by auto_id
$app->get('/v1/stock-management/stock/{autoId}', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$service = new \App\Services\StockManagementService();
		$result = $service->getStockById($autoId);
		return jsonResponse($response, $result, 'success', 'Stock row retrieved');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Update a single field by auto_id
$app->post('/v1/airlines-payment-details/{autoId}/field', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->updateField($autoId, $input);
		return jsonResponse($response, $result, 'success', 'Airlines payment details field updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Update deposit last modified (on/by) by auto_id
$app->post('/v1/airlines-payment-details/{autoId}/deposit-last-modified', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->updateDepositLastModified($autoId, $input);
		return jsonResponse($response, $result, 'success', 'Deposit last modified updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Update paid last modified (on/by) by auto_id
$app->post('/v1/airlines-payment-details/{autoId}/paid-last-modified', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->updatePaidLastModified($autoId, $input);
		return jsonResponse($response, $result, 'success', 'Paid last modified updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Update ticketed last modified (on/by) by auto_id
$app->post('/v1/airlines-payment-details/{autoId}/ticketed-last-modified', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->updateTicketedLastModified($autoId, $input);
		return jsonResponse($response, $result, 'success', 'Ticketed last modified updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Update refund updated (on/by) by auto_id
$app->post('/v1/airlines-payment-details/{autoId}/refund-updated', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->updateRefundUpdated($autoId, $input);
		return jsonResponse($response, $result, 'success', 'Refund updated fields updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Update split updated (on/by) by auto_id
$app->post('/v1/airlines-payment-details/{autoId}/split-updated', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->updateSplitUpdated($autoId, $input);
		return jsonResponse($response, $result, 'success', 'Split updated fields updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Update done updated (on/by) by auto_id
$app->post('/v1/airlines-payment-details/{autoId}/done-updated', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->updateDoneUpdated($autoId, $input);
		return jsonResponse($response, $result, 'success', 'Done updated fields updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Get a single row by auto_id
$app->get('/v1/airlines-payment-details/{autoId}', function (Request $request, Response $response, array $args) {
	try {
		$autoId = (int)$args['autoId'];
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->getById($autoId);
		return jsonResponse($response, $result, 'success', 'Airlines payment details row retrieved');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - List EMD entries by airlines_payment_details_id
$app->get('/v1/airlines-payment-details/{autoId}/emd', function (Request $request, Response $response, array $args) {
	try {
		$airlinesPaymentDetailsId = (int)$args['autoId'];
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->listEmdByPaymentDetailsId($airlinesPaymentDetailsId);
		return jsonResponse($response, $result, 'success', 'EMD entries retrieved');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Create EMD payment entry
$app->post('/v1/airlines-payment-details/emd', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->createEmdPayment($input);
		return jsonResponse($response, $result, 'success', 'EMD payment created');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Create main payment details entry
$app->post('/v1/airlines-payment-details', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->createAirlinesPaymentDetails($input);
		return jsonResponse($response, $result, 'success', 'Airlines payment details created');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - List by group_name
$app->get('/v1/airlines-payment-details-group', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->listByGroupName($params);
		return jsonResponse($response, $result, 'success', 'Airlines payment details retrieved by group_name');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AIRLINES PAYMENT DETAILS - Clear deposit_deadline by pnr and group_id (separate route to avoid shadowing)
$app->post('/v1/airlines-payment-details-clear-deposit-deadline', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AirlinesPaymentDetailsService();
		$result = $service->clearDepositDeadline($input);
		return jsonResponse($response, $result, 'success', 'Deposit deadline cleared');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// TRAVEL BOOKINGS - Sum partially paid pax (nonpaid) for WPT by trip_code+date patterns
$app->get('/v1/travel-bookings/nonpaid-pax-by-trip-date', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\CustomerService();
		$result = $service->getNonPaidPaxByTripDate($params);
		return jsonResponse($response, $result, 'success', 'Nonpaid pax computed successfully');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// TRAVEL BOOKINGS - Add update history entry
$app->post('/v1/travel-bookings/{orderId}/update-history', function (Request $request, Response $response, array $args) {
	try {
		$orderId = (int)$args['orderId'];
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\CustomerService();
		$result = $service->addBookingUpdateHistory($orderId, $input);
		return jsonResponse($response, $result, 'success', 'Update history added');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// STOCK MANAGEMENT SHEET + PAYMENT DETAILS
$app->get('/v1/stock-management/stock-payments', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\StockManagementService();
		$result = $service->listStockWithPayments($params);
		return jsonResponse($response, $result, 'success', 'Stock with payment details retrieved');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// STOCK MANAGEMENT - Lookup stocks by trip_id LIKE and dep_date prefix
$app->get('/v1/stock-management/stocks-lookup', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\StockManagementService();
		$result = $service->listStocksByTripAndDatePrefix($params);
		return jsonResponse($response, $result, 'success', 'Stocks retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// STOCK MANAGEMENT - Lookup child stocks by trip_id LIKE, dep_date prefix, excluding PNRS
$app->get('/v1/stock-management/stocks-child-lookup', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\StockManagementService();
		$result = $service->listChildStocksByTripDateExcludePnrs($params);
		return jsonResponse($response, $result, 'success', 'Child stocks retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// STOCK PRODUCT MANAGER - TRIP CODE BY PRODUCT ID AND TRAVEL DATE
$app->get('/v1/stock-product-manager/trip-code', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\StockProductManagerService();
		$result = $service->getTripCodeByProductAndDate($params);
		return jsonResponse($response, $result, 'success', 'Trip code retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// Delete payment only if uncleared (cleared_date IS NULL)
$app->delete('/v1/payments/{paymentId}/uncleared', function (Request $request, Response $response, array $args) {
	try {
		$paymentId = (int)$args['paymentId'];
		$service = new \App\Services\CustomerService();
		$result = $service->deleteUnclearedPaymentById($paymentId);
		return jsonResponse($response, $result, 'success', 'Payment deleted if uncleared');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// TRAVEL BOOKINGS - Sum paid pax for WPT by trip_code+date patterns
$app->get('/v1/travel-bookings/paid-pax-by-trip-date', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\CustomerService();
		$result = $service->getPaidPaxByTripDate($params);
		return jsonResponse($response, $result, 'success', 'Paid pax computed successfully');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// TRAVEL BOOKINGS - Count paid pax for WPT by exact trip_code+date key
$app->get('/v1/travel-bookings/paid-pax-count-by-trip-date', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\CustomerService();
		$result = $service->getPaidPaxCountByTripDateExact($params);
		return jsonResponse($response, $result, 'success', 'Paid pax count computed successfully');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// TRAVEL BOOKINGS - Count partially paid (nonpaid) pax for WPT by exact trip_code+date key
$app->get('/v1/travel-bookings/nonpaid-pax-count-by-trip-date', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\CustomerService();
		$result = $service->getNonPaidPaxCountByTripDateExact($params);
		return jsonResponse($response, $result, 'success', 'Nonpaid pax count computed successfully');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// TRAVEL BOOKINGS - List by trip_code suffix and travel_date prefix with paid/partially_paid statuses
$app->get('/v1/travel-bookings/by-trip-date', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\CustomerService();
		$result = $service->listBookingsByTripAndDatePrefix($params);
		return jsonResponse($response, $result, 'success', 'Bookings retrieved successfully');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// TRAVEL BOOKINGS - List by trip_code suffix and travel_date prefix with payment_status = 'paid'
$app->get('/v1/travel-bookings/by-trip-date-paid', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\CustomerService();
		$result = $service->listPaidBookingsByTripAndDatePrefix($params);
		return jsonResponse($response, $result, 'success', 'Paid bookings retrieved successfully');
	} catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// MARKETING CATEGORIES ENDPOINT
// ======================================

$app->get('/v1/marketing-categories', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarketingCategoryService();
        $categories = $service->getMarketingCategories();
        return jsonResponse($response, $categories, 'success', 'Marketing categories retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/marketing-channels-with-categories', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarketingCategoryService();
        $channels = $service->getActiveChannelsWithCategories();
        return jsonResponse($response, $channels, 'success', 'Marketing channels retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MARKETING REMARKS ENDPOINT
// ======================================

$app->get('/v1/marketing-remarks', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarketingRemarkService();
        $remarks = $service->getMarketingRemarks();
        return jsonResponse($response, $remarks, 'success', 'Marketing remarks retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INCENTIVE CRITERIA ENDPOINT
// ======================================

$app->get('/v1/incentive-criteria-periods', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\IncentiveCriteriaService();
        $periods = $service->getDistinctPeriods();
        return jsonResponse($response, $periods, 'success', 'Incentive criteria periods retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT CODES ENDPOINT
// ======================================

$app->get('/v1/agent-codes-sale-managers', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentCodesService();
        $saleManagers = $service->getDistinctSaleManagers();
        return jsonResponse($response, $saleManagers, 'success', 'Agent codes sale managers retrieved successfully');
    }   
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// AGENT NAMES BY LOCATION
$app->get('/v1/agent-codes-agent-names', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $location = $params['location'] ?? '';
        if ($location === '') {
            throw new Exception('location is required', 400);
        }
        $status = $params['status'] ?? 'Active';
        $service = new \App\Services\AgentCodesService();
        $agents = $service->getDistinctAgentNamesByLocation($location, $status);
        return jsonResponse($response, $agents, 'success', 'Agent names retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AFTER-SALE ABDN CALL STATUS LOGS
// ======================================

$app->get('/v1/after-sale-abdn-call-status-logs', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $service = new \App\Services\AfterSaleAbdnCallStatusLogService();
        $rows = $service->getLogs($params);
        return jsonResponse($response, $rows, 'success', 'Logs retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AFTER-SALE PRODUCTIVITY - AGENT NAMES
// ======================================

$app->get('/v1/after-sale-productivity-agent-names', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$agents = $service->getDistinctAgentNames($params);
		return jsonResponse($response, $agents, 'success', 'After-sale productivity agent names retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT CODES - ACTIVE ROSTER CODES
$app->get('/v1/agent-codes-active-roster', function (Request $request, Response $response) {
	try {
		$service = new \App\Services\AgentCodesService();
		$rows = $service->getActiveRosterCodes();
		return jsonResponse($response, $rows, 'success', 'Active roster codes retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT CODES - ROSTER CODE BY AGENT NAME
$app->get('/v1/agent-codes-roster-code', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$agentName = $params['agent_name'] ?? '';
		$service = new \App\Services\AgentCodesService();
		$row = $service->getActiveRosterCodeByAgentName($agentName);
		return jsonResponse($response, $row, 'success', 'Roster code retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT CODES - AGENT NAME BY ROSTER CODE
$app->get('/v1/agent-codes-agent-name-by-roster', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$rosterCode = $params['roster_code'] ?? '';
		$service = new \App\Services\AgentCodesService();
		$row = $service->getAgentNameByRosterCode($rosterCode);
		return jsonResponse($response, $row, 'success', 'Agent name retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT CODES - DISTINCT AGENT NAMES (ALL)
$app->get('/v1/agent-codes-distinct-agent-names', function (Request $request, Response $response) {
	try {
		$service = new \App\Services\AgentCodesService();
		$rows = $service->getDistinctAgentNames();
		return jsonResponse($response, $rows, 'success', 'Distinct agent names retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT CODES - DISTINCT TEAM NAMES (ALL)
$app->get('/v1/agent-codes-distinct-team-names', function (Request $request, Response $response) {
	try {
		$service = new \App\Services\AgentCodesService();
		$rows = $service->getDistinctTeamNames();
		return jsonResponse($response, $rows, 'success', 'Distinct team names retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT CODES - CRUD ENDPOINTS
// Custom GET endpoint with filter support
$app->get('/v1/agent-codes', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        // Extract filters
        $filters = [];
        if (isset($queryParams['status']) && $queryParams['status'] !== '') {
            $filters['status'] = $queryParams['status'];
        }
        if (isset($queryParams['location']) && $queryParams['location'] !== '') {
            $filters['location'] = $queryParams['location'];
        }
        if (isset($queryParams['tsr']) && $queryParams['tsr'] !== '') {
            $filters['tsr'] = $queryParams['tsr'];
        }
        if (isset($queryParams['team_name']) && $queryParams['team_name'] !== '') {
            $filters['team_name'] = $queryParams['team_name'];
        }
        if (isset($queryParams['employee_status']) && $queryParams['employee_status'] !== '') {
            $filters['employee_status'] = $queryParams['employee_status'];
        }
        if (isset($queryParams['sale_manager']) && $queryParams['sale_manager'] !== '') {
            $filters['sale_manager'] = $queryParams['sale_manager'];
        }
        
        $limit = (int)($queryParams['limit'] ?? 100);
        $offset = (int)($queryParams['offset'] ?? 0);
        
        $service = new \App\Services\AgentCodesService();
        $data = $service->getAll($limit, $offset, $filters);
        
        return jsonResponse($response, $data, 'success', 'Agent codes retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET by ID
$app->get('/v1/agent-codes/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $service = new \App\Services\AgentCodesService();
        $data = $service->getById($id);
        return jsonResponse($response, $data, 'success', 'Record retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create
$app->post('/v1/agent-codes', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        if (empty($input)) {
            throw new Exception('Data required', 400);
        }
        $service = new \App\Services\AgentCodesService();
        $id = $service->create($input);
        return jsonResponse($response, ['id' => $id], 'success', 'Record created', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update
$app->put('/v1/agent-codes/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        if (empty($input)) {
            throw new Exception('Data required', 400);
        }
        $service = new \App\Services\AgentCodesService();
        $service->update($id, $input);
        return jsonResponse($response, ['id' => $id], 'success', 'Record updated');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// DELETE
$app->delete('/v1/agent-codes/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $service = new \App\Services\AgentCodesService();
        $service->delete($id);
        return jsonResponse($response, ['id' => $id], 'success', 'Record deleted');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// STOCK PRODUCT MANAGER - PRODUCT IDS BY TRIP CODE AND TRAVEL DATE
$app->get('/v1/stock-product-manager/product-ids', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\StockProductManagerService();
		$result = $service->getProductIdsByTripAndDate($params);
		return jsonResponse($response, $result, 'success', 'Product IDs retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT TARGET PATHWAY - BY ROSTER CODE AND PERIOD
$app->get('/v1/agent-target-pathway', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AgentTargetPathwayService();
		$row = $service->getByRosterCodeAndPeriod($params);
		return jsonResponse($response, $row, 'success', 'Agent target pathway retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT TARGET PATHWAY - LATEST BY ROSTER CODE
$app->get('/v1/agent-target-pathway/latest', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AgentTargetPathwayService();
		$row = $service->getLatestByRosterCode($params);
		return jsonResponse($response, $row, 'success', 'Latest agent target pathway retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT TARGET PATHWAY - LIST BY ROSTER CODE AND PERIOD
$app->get('/v1/agent-target-pathway/list', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AgentTargetPathwayService();
		$rows = $service->listByRosterCodeAndPeriod($params);
		return jsonResponse($response, $rows, 'success', 'Agent target pathway (all rows) retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT TARGET PATHWAY - CREATE HISTORY
$app->post('/v1/agent-target-pathway/history', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AgentTargetPathwayService();
		$result = $service->createHistory($input);
		return jsonResponse($response, $result, 'success', 'Agent target pathway history created', 201);
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT TARGET PATHWAY - UPSERT
$app->post('/v1/agent-target-pathway', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AgentTargetPathwayService();
		$result = $service->upsertPathway($input);
		return jsonResponse($response, $result, 'success', 'Agent target pathway saved');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// AFTER-SALE PRODUCTIVITY - SUMMARY
// ======================================

$app->get('/v1/after-sale-productivity-report', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getProductivitySummary($params);
		return jsonResponse($response, $rows, 'success', 'After-sale productivity report retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

$app->get('/v1/after-sale-productivity-report-by-agent', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getProductivitySummaryByAgent($params);
		return jsonResponse($response, $rows, 'success', 'After-sale productivity report by agent retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// AFTER-SALE PRODUCTIVITY - AGENT METRICS
// ======================================

$app->get('/v1/after-sale-productivity-agent-metrics', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getAgentMetrics($params);
		return jsonResponse($response, $rows, 'success', 'After-sale productivity agent metrics retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// DATE CHANGE SUMMARY
// ======================================

$app->get('/v1/datechange-summary', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\DateChangeSummaryService();
		$data = $service->getSummary($params);
		return jsonResponse($response, $data, 'success', 'Date change summary retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// DATE CHANGE SALES SUMMARY
$app->get('/v1/datechange-sales-summary', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\DateChangeSummaryService();
		$data = $service->getSalesSummary($params);
		return jsonResponse($response, $data, 'success', 'Date change sales summary retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// AFTER-SALE PRODUCTIVITY - MONTHLY CALL SUMMARY
// ======================================

$app->get('/v1/after-sale-productivity-monthly-call-summary', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getMonthlyAgentCallSummary($params);
		return jsonResponse($response, $rows, 'success', 'Monthly call summary retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// AFTER-SALE PRODUCTIVITY - AGENT SUCCESS SUMMARY
// ======================================

$app->get('/v1/after-sale-productivity-agent-success', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getAgentSuccessSummary($params);
		return jsonResponse($response, $rows, 'success', 'Agent success summary retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AFTER-SALE PRODUCTIVITY - DISTINCT MONTHS BY YEAR
$app->get('/v1/after-sale-productivity-months', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$months = $service->getDistinctMonths($params);
		return jsonResponse($response, $months, 'success', 'Distinct months retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AFTER-SALE PRODUCTIVITY - MONTHLY GT AGGREGATES BY YEAR
$app->get('/v1/after-sale-productivity-monthly-gt', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $service = new \App\Services\AfterSaleProductivityService();
        $rows = $service->getMonthlyGt($params);
        return jsonResponse($response, $rows, 'success', 'Monthly GT aggregates retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// AFTER-SALE PRODUCTIVITY - YEARLY AGENT CONNECT SUMMARY
$app->get('/v1/after-sale-productivity-agent-connect-summary', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getYearlyAgentConnectSummary($params);
		return jsonResponse($response, $rows, 'success', 'Yearly agent connect summary retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AFTER-SALE PRODUCTIVITY - ALL-TIME AGENT CONNECT SUMMARY
$app->get('/v1/after-sale-productivity-agent-connect-summary-all', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getAgentConnectSummaryAll($params);
		return jsonResponse($response, $rows, 'success', 'All-time agent connect summary retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AFTER-SALE PRODUCTIVITY - AGENT SUCCESS BY YEAR/MONTH/DAY RANGE
$app->get('/v1/after-sale-productivity-agent-success-by-month', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getAgentSuccessSummaryByYearMonth($params);
		return jsonResponse($response, $rows, 'success', 'Agent success summary retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AFTER-SALE PRODUCTIVITY - AGENT SUCCESS BY YEAR/MONTH (NO DAY FILTER)
$app->get('/v1/after-sale-productivity-agent-success-month', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getAgentSuccessSummaryByYearMonthNoDay($params);
		return jsonResponse($response, $rows, 'success', 'Agent success (monthly) retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT INBOUND CALL - AUTO IDS FOR UPDATE
$app->get('/v1/agent-inbound-call/auto-ids-for-update', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AgentInboundCallExtrasService();
		$result = $service->getAutoIdsForUpdate($params);
		return jsonResponse($response, $result, 'success', 'Auto IDs for update retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AGENT INBOUND CALL - UPDATE FLAGS
$app->post('/v1/agent-inbound-call/update-flags', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\AgentInboundCallExtrasService();
		$result = $service->updateFlags($input);
		return jsonResponse($response, $result, 'success', 'Flags updated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AFTER-SALE PRODUCTIVITY - AGENT DC SUMMARY (DATE RANGE, EXCLUDE ABDN)
$app->get('/v1/after-sale-productivity-agent-dc-summary-range', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getAgentDcSummaryByDateRangeExcludeAbdn($params);
		return jsonResponse($response, $rows, 'success', 'Agent DC summary (date range) retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AFTER-SALE PRODUCTIVITY - AGENT TICKET SUMMARY (DATE RANGE, OPTIONAL AGENT)
$app->get('/v1/after-sale-productivity-agent-ticket-summary', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getAgentTicketSummary($params);
		return jsonResponse($response, $rows, 'success', 'Agent ticket summary retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// AFTER-SALE PRODUCTIVITY - AGENT TICKET SUMMARY (ACTIVE CODES VIA TSR)
$app->get('/v1/after-sale-productivity-agent-ticket-summary-active', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\AfterSaleProductivityService();
		$rows = $service->getAgentTicketSummaryActive($params);
		return jsonResponse($response, $rows, 'success', 'Agent ticket summary (active) retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// CALL CENTER DATA ENDPOINT
// ======================================

$app->get('/v1/call-center-data', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\CallCenterService();
		$data = $service->getCallCenterData($params);
		return jsonResponse($response, $data, 'success', 'Call center data retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// BOOKING NOTE SUMMARY ENDPOINT
// ======================================

$app->get('/v1/booking-note-summary-departments', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BookingNoteSummaryService();
        $departments = $service->getDistinctNoteDepartments();
        return jsonResponse($response, $departments, 'success', 'Booking note summary departments retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/booking-note-summary-categories', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BookingNoteSummaryService();
        $categories = $service->getDistinctNoteCategories();
        return jsonResponse($response, $categories, 'success', 'Booking note summary categories retrieved successfully');
    }
    catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TICKET NUMBERS - EXISTING CHECK
// ======================================

$app->post('/v1/ticket-numbers/existing', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (!isset($input['document_numbers']) || !is_array($input['document_numbers'])) {
			throw new Exception('document_numbers (array) is required', 400);
		}

		$service = new \App\Services\TicketNumberService();
		$result = $service->getExistingDocuments($input['document_numbers']);

		return jsonResponse($response, $result, 'success', 'Existing ticket documents retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// TICKET RECONCILIATION - EXISTING CHECK
// ======================================

$app->post('/v1/ticket-reconciliation/existing', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (!isset($input['document_numbers']) || !is_array($input['document_numbers'])) {
			throw new Exception('document_numbers (array) is required', 400);
		}

		$service = new \App\Services\TicketReconciliationService();
		$result = $service->getExistingDocuments($input['document_numbers']);

		return jsonResponse($response, $result, 'success', 'Existing reconciliation ticket documents retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// TICKET NUMBER HOTFILE - CREATE
// ======================================

$app->post('/v1/ticket-number-hotfile', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input)) {
			throw new Exception('Payload required', 400);
		}
		$service = new \App\Services\TicketNumberHotfileService();
		$result = $service->create($input);
		return jsonResponse($response, $result, 'success', 'Hotfile ticket created', 201);
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// TICKET NUMBER HOTFILE - LIST (BY DATE RANGE AND OPTIONAL VENDOR)
// ======================================

$app->get('/v1/ticket-number-hotfile', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\TicketNumberHotfileService();
		$rows = $service->listByDateVendor($params);
		return jsonResponse($response, $rows, 'success', 'Hotfile tickets retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// TICKET NUMBER - NON-HOTFILE TICKETS (BY DATE RANGE)
$app->get('/v1/ticket-number/non-hotfile', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		$service = new \App\Services\TicketNumberHotfileService();
		$rows = $service->listNonHotfileByDate($params);
		return jsonResponse($response, $rows, 'success', 'Non-hotfile tickets retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// Recalculate order amounts in reconciliation by date range (optional pax_id)
$app->post('/v1/ticket-reconciliation/recalculate-order-amounts', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input['start']) || empty($input['end'])) {
			throw new Exception('start and end (YYYY-MM-DD) are required', 400);
		}
		$service = new \App\Services\TicketReconciliationService();
		$result = $service->recalculateOrderAmounts($input);
		return jsonResponse($response, $result, 'success', 'Order amounts recalculated');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// Import reconciliation rows from hotfile by document numbers
$app->post('/v1/ticket-reconciliation/import-from-hotfile', function (Request $request, Response $response) {
	try {
		$input = json_decode($request->getBody()->getContents(), true);
		if (empty($input['document_numbers']) || !is_array($input['document_numbers'])) {
			throw new Exception('document_numbers (non-empty array) is required', 400);
		}
		$service = new \App\Services\TicketReconciliationService();
		$result = $service->importFromHotfile($input);
		return jsonResponse($response, $result, 'success', 'Imported from hotfile');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// EMPLOYEE SCHEDULE - LOCK STATUS
// ======================================

$app->get('/v1/employee-schedule/lock-status', function (Request $request, Response $response) {
	try {
		$service = new \App\Services\EmployeeScheduleService();
		$data = $service->getLockStatus();
		return jsonResponse($response, $data, 'success', 'Employee schedule lock status retrieved successfully');
	}
	catch (Exception $e) {
		$code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
		return errorResponse($response, $e->getMessage(), $code);
	}
});

// ======================================
// ABANDONED CALL METRICS ENDPOINT
// ======================================

// Get abandoned call metrics for a specific agent
$app->get('/v1/abandoned-calls/agent/{agentName}', function (Request $request, Response $response, array $args) {
    try {
        $agentName = $args['agentName'];
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        
        $service = new \App\Services\AbandonedCallService();
        $metrics = $service->getAbandonedCallMetrics($agentName, $startDate, $endDate);
        
        return jsonResponse($response, $metrics, 'success', 'Abandoned call metrics retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get abandoned call metrics for all agents
$app->get('/v1/abandoned-calls', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        
        $service = new \App\Services\AbandonedCallService();
        $metrics = $service->getAllAgentsAbandonedCalls($startDate, $endDate);
        
        return jsonResponse($response, $metrics, 'success', 'Abandoned call metrics retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ROSTER APPROVAL ENDPOINTS
// ======================================

// Get pending admin approval requests
$app->get('/v1/roster-approvals/pending', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\RosterApprovalService();
        $data = $service->getPendingAdminApprovals();
        
        return jsonResponse($response, $data, 'success', 'Pending approvals retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get processed requests
$app->get('/v1/roster-approvals/processed', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\RosterApprovalService();
        $data = $service->getProcessedRequests();
        
        return jsonResponse($response, $data, 'success', 'Processed requests retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Approve a request
$app->post('/v1/roster-approvals/{requestId}/approve', function (Request $request, Response $response, array $args) {
    try {
        $requestId = (int)$args['requestId'];
        
        if ($requestId <= 0) {
            throw new Exception('Invalid request ID', 400);
        }
        
        $service = new \App\Services\RosterApprovalService();
        $result = $service->approveRequest($requestId);
        
        return jsonResponse($response, $result, 'success', 'Request approved successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Reject a request
$app->post('/v1/roster-approvals/{requestId}/reject', function (Request $request, Response $response, array $args) {
    try {
        $requestId = (int)$args['requestId'];
        
        if ($requestId <= 0) {
            throw new Exception('Invalid request ID', 400);
        }
        
        $service = new \App\Services\RosterApprovalService();
        $result = $service->rejectRequest($requestId);
        
        return jsonResponse($response, $result, 'success', 'Request rejected successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create new roster record
$app->post('/v1/roster', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        if (empty($input)) {
            throw new Exception('Data required', 400);
        }
        
        $service = new \App\Services\RosterService();
        $id = $service->create($input);
        
        return jsonResponse($response, ['id' => $id], 'success', 'Roster record created successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update roster record
$app->put('/v1/roster', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        if (empty($input)) {
            throw new Exception('Data required', 400);
        }
        
        // Required fields for update
        if (empty($input['employee_code'])) {
            throw new Exception('employee_code is required', 400);
        }
        if (empty($input['month'])) {
            throw new Exception('month is required', 400);
        }
        if (empty($input['year'])) {
            throw new Exception('year is required', 400);
        }
        
        $service = new \App\Services\RosterService();
        $service->update($input['employee_code'], $input['month'], $input['year'], $input);
        
        return jsonResponse($response, [
            'employee_code' => $input['employee_code'],
            'month' => $input['month'],
            'year' => $input['year']
        ], 'success', 'Roster record updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AFTER SALES CALL METRICS ENDPOINTS
// ======================================

// Get after sales call metrics
$app->get('/v1/after-sales-call-metrics', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        $agent = $queryParams['agent_name'] ?? '';
        
        $service = new \App\Services\AfterSalesCallMetricsService();
        $metrics = $service->getAfterSalesCallMetrics($startDate, $endDate, $agent);
        
        return jsonResponse($response, $metrics, 'success', 'After sales call metrics retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent data for a specific date
$app->get('/v1/after-sales-call-metrics/agent-data/{date}', function (Request $request, Response $response, array $args) {
    try {
        $date = $args['date'];
        $queryParams = $request->getQueryParams();
        $agent = $queryParams['agent_name'] ?? '';
        
        $service = new \App\Services\AfterSalesCallMetricsService();
        $data = $service->getAgentDataByDate($date, $agent);
        
        return jsonResponse($response, $data, 'success', 'Agent data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct agents list
$app->get('/v1/after-sales-call-metrics/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AfterSalesCallMetricsService();
        $agents = $service->getDistinctAgents();
        
        return jsonResponse($response, ['agents' => $agents], 'success', 'Agents list retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT DATE CHANGE (DC) METRICS ENDPOINTS
// ======================================

// Get date change metrics
$app->get('/v1/agent-dc-metrics', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        $agent = $queryParams['agent_name'] ?? '';
        
        $service = new \App\Services\AgentDCService();
        $metrics = $service->getDateChangeMetrics($startDate, $endDate, $agent);
        
        return jsonResponse($response, $metrics, 'success', 'Date change metrics retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent data for a specific date
$app->get('/v1/agent-dc-metrics/agent-data/{date}', function (Request $request, Response $response, array $args) {
    try {
        $date = $args['date'];
        $queryParams = $request->getQueryParams();
        $agent = $queryParams['agent_name'] ?? '';
        
        $service = new \App\Services\AgentDCService();
        $data = $service->getAgentDetailsByDate($date, $agent);
        
        return jsonResponse($response, $data, 'success', 'Agent data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct agents list
$app->get('/v1/agent-dc-metrics/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentDCService();
        $agents = $service->getDistinctAgents();
        
        return jsonResponse($response, ['agents' => $agents], 'success', 'Agents list retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SALE ID UPDATE ENDPOINTS
// ======================================

// Get pending records with filters
$app->get('/v1/saleid-updates', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $orderIdFilter = $queryParams['order_id'] ?? '';
        $dateFilter = $queryParams['date'] ?? '';
        
        $service = new \App\Services\SaleIdUpdateService();
        $data = $service->getPendingRecords($orderIdFilter, $dateFilter);
        
        return jsonResponse($response, $data, 'success', 'Pending records retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get single record by ID
$app->get('/v1/saleid-updates/{id}', function (Request $request, Response $response, array $args) {
    try {
        $recordId = (int)$args['id'];
        
        $service = new \App\Services\SaleIdUpdateService();
        $data = $service->getRecordById($recordId);
        
        return jsonResponse($response, $data, 'success', 'Record retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Approve a record
$app->post('/v1/saleid-updates/{id}/approve', function (Request $request, Response $response, array $args) {
    try {
        $recordId = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $modifiedBy = $input['modified_by'] ?? 'system';
        
        $service = new \App\Services\SaleIdUpdateService();
        $result = $service->approveRecord($recordId, $modifiedBy);
        
        return jsonResponse($response, $result, 'success', 'Record approved successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Reject a record
$app->post('/v1/saleid-updates/{id}/reject', function (Request $request, Response $response, array $args) {
    try {
        $recordId = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $modifiedBy = $input['modified_by'] ?? 'system';
        
        $service = new \App\Services\SaleIdUpdateService();
        $result = $service->rejectRecord($recordId, $modifiedBy);
        
        return jsonResponse($response, $result, 'success', 'Record rejected successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update check status
$app->patch('/v1/saleid-updates/{id}/check-status', function (Request $request, Response $response, array $args) {
    try {
        $recordId = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($input['is_checked'])) {
            throw new Exception('is_checked parameter is required', 400);
        }
        
        $service = new \App\Services\SaleIdUpdateService();
        $result = $service->updateCheckStatus($recordId, $input['is_checked']);
        
        return jsonResponse($response, $result, 'success', 'Check status updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get unique order IDs for filters
$app->get('/v1/saleid-updates/filters/order-ids', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SaleIdUpdateService();
        $orderIds = $service->getUniqueOrderIds();
        
        return jsonResponse($response, ['order_ids' => $orderIds], 'success', 'Order IDs retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get unique dates for filters
$app->get('/v1/saleid-updates/filters/dates', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SaleIdUpdateService();
        $dates = $service->getUniqueDates();
        
        return jsonResponse($response, ['dates' => $dates], 'success', 'Dates retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AUDIT REVIEW ENDPOINTS
// ======================================

// Get audit review metrics
$app->get('/v1/audit-review', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $fromDate = $queryParams['from'] ?? null;
        $toDate = $queryParams['to'] ?? null;
        $agent = $queryParams['agent_name'] ?? '';
        
        $service = new \App\Services\AuditReviewService();
        $metrics = $service->getAuditReviewMetrics($fromDate, $toDate, $agent);
        
        return jsonResponse($response, $metrics, 'success', 'Audit review metrics retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent data for a specific date
$app->get('/v1/audit-review/agent-data/{date}', function (Request $request, Response $response, array $args) {
    try {
        $date = $args['date'];
        $queryParams = $request->getQueryParams();
        $agent = $queryParams['agent_name'] ?? '';
        
        $service = new \App\Services\AuditReviewService();
        $data = $service->getAgentDetailsByDate($date, $agent);
        
        return jsonResponse($response, $data, 'success', 'Agent data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct agents list
$app->get('/v1/audit-review/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AuditReviewService();
        $agents = $service->getDistinctAgents();
        
        return jsonResponse($response, ['agents' => $agents], 'success', 'Agents list retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// DATE CHANGE REQUEST ENDPOINTS
// ======================================

// Get date change request metrics
$app->get('/v1/date-change-request', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $fromDate = $queryParams['from'] ?? null;
        $toDate = $queryParams['to'] ?? null;
        $agent = $queryParams['agent_name'] ?? '';
        
        $service = new \App\Services\DateChangeRequestService();
        $metrics = $service->getDateChangeRequestMetrics($fromDate, $toDate, $agent);
        
        return jsonResponse($response, $metrics, 'success', 'Date change request metrics retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent data for a specific date
$app->get('/v1/date-change-request/agent-data/{date}', function (Request $request, Response $response, array $args) {
    try {
        $date = $args['date'];
        $queryParams = $request->getQueryParams();
        $agent = $queryParams['agent_name'] ?? '';
        
        $service = new \App\Services\DateChangeRequestService();
        $data = $service->getAgentDetailsByDate($date, $agent);
        
        return jsonResponse($response, $data, 'success', 'Agent data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct agents list
$app->get('/v1/date-change-request/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\DateChangeRequestService();
        $agents = $service->getDistinctAgents();
        
        return jsonResponse($response, ['agents' => $agents], 'success', 'Agents list retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// DATE CHANGE REQUEST DASHBOARD ENDPOINTS
// ======================================

// Get dashboard data with all summaries
$app->get('/v1/date-change-request-dashboard', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $fromDate = $queryParams['from'] ?? null;
        $toDate = $queryParams['to'] ?? null;
        
        $service = new \App\Services\DateChangeRequestService();
        $data = $service->getDashboardData($fromDate, $toDate);
        
        return jsonResponse($response, $data, 'success', 'Dashboard data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get daily summary
$app->get('/v1/date-change-request-dashboard/daily-summary', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $fromDate = $queryParams['from'] ?? null;
        $toDate = $queryParams['to'] ?? null;
        
        $service = new \App\Services\DateChangeRequestService();
        $data = $service->getDailySummary($fromDate, $toDate);
        
        return jsonResponse($response, $data, 'success', 'Daily summary retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent summary
$app->get('/v1/date-change-request-dashboard/agent-summary', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $fromDate = $queryParams['from'] ?? null;
        $toDate = $queryParams['to'] ?? null;
        
        $service = new \App\Services\DateChangeRequestService();
        $data = $service->getAgentSummary($fromDate, $toDate);
        
        return jsonResponse($response, $data, 'success', 'Agent summary retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// DUPLICATE PASSENGER BOOKINGS ENDPOINTS
// ======================================

// Get duplicate passenger bookings
$app->get('/v1/dupe-pax', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $odFrom = $queryParams['od_from'] ?? null;
        $odTo = $queryParams['od_to'] ?? null;
        $pmFrom = $queryParams['pm_from'] ?? null;
        $pmTo = $queryParams['pm_to'] ?? null;
        
        $service = new \App\Services\DupePaxService();
        $data = $service->getDuplicateBookings($odFrom, $odTo, $pmFrom, $pmTo);
        
        return jsonResponse($response, $data, 'success', 'Duplicate passenger bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get duplicate passenger bookings by email
$app->get('/v1/dupe-pax-email', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $travelFrom = $queryParams['travel_from'] ?? null;
        $travelTo = $queryParams['travel_to'] ?? null;
        $email = $queryParams['email'] ?? '';
        
        $service = new \App\Services\DupePaxEmailService();
        $data = $service->getDuplicateBookingsByEmail($travelFrom, $travelTo, $email);
        
        return jsonResponse($response, $data, 'success', 'Duplicate passenger bookings by email retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// EMPLOYEE ROSTER ENDPOINTS
// ======================================

// Get roster data for an employee
$app->get('/v1/employee-roster', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $wordpressUsername = $queryParams['wordpress_username'] ?? null;
        $agentName = $queryParams['agent_name'] ?? null;
        $month = $queryParams['month'] ?? null;
        
        if (!$wordpressUsername && !$agentName) {
            throw new Exception('Either wordpress_username or agent_name is required', 400);
        }
        
        $service = new \App\Services\EmployeeRosterService();
        $data = $service->getRosterData($wordpressUsername, $agentName, $month);
        
        return jsonResponse($response, $data, 'success', 'Roster data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get approval history for an employee
$app->get('/v1/employee-roster/approval-history', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $wordpressUsername = $queryParams['wordpress_username'] ?? null;
        $agentName = $queryParams['agent_name'] ?? null;
        
        if (!$wordpressUsername && !$agentName) {
            throw new Exception('Either wordpress_username or agent_name is required', 400);
        }
        
        $service = new \App\Services\EmployeeRosterService();
        $data = $service->getApprovalHistory($wordpressUsername, $agentName);
        
        return jsonResponse($response, $data, 'success', 'Approval history retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Create shift change request
$app->post('/v1/employee-roster/shift-change-request', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Request data required', 400);
        }
        
        $service = new \App\Services\EmployeeRosterService();
        $data = $service->createShiftChangeRequest($input);
        
        return jsonResponse($response, $data, 'success', 'Shift change request created successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Create RDO change request
$app->post('/v1/employee-roster/rdo-change-request', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Request data required', 400);
        }
        
        $service = new \App\Services\EmployeeRosterService();
        $data = $service->createRDOChangeRequest($input);
        
        return jsonResponse($response, $data, 'success', 'RDO change request created successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Create leave request
$app->post('/v1/employee-roster/leave-request', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Request data required', 400);
        }
        
        $service = new \App\Services\EmployeeRosterService();
        $data = $service->createLeaveRequest($input);
        
        return jsonResponse($response, $data, 'success', 'Leave request created successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Confirm roster
$app->post('/v1/employee-roster/confirm', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['roster_code']) || empty($input['month'])) {
            throw new Exception('Roster code and month are required', 400);
        }
        
        $service = new \App\Services\EmployeeRosterService();
        $data = $service->confirmRoster($input['roster_code'], $input['month']);
        
        return jsonResponse($response, $data, 'success', 'Roster confirmed successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ESCALATION ENDPOINTS (Pamitha specific)
// ======================================

// Get escalation metrics
$app->get('/v1/escalation-metrics', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        $status = $queryParams['status'] ?? null;
        $escalatedTo = $queryParams['escalated_to'] ?? null;
        
        $service = new \App\Services\EscalationService();
        $data = $service->getEscalationMetrics($startDate, $endDate, $status, $escalatedTo);
        
        return jsonResponse($response, $data, 'success', 'Escalation metrics retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get escalation details for a specific date
$app->get('/v1/escalations/date/{date}', function (Request $request, Response $response, array $args) {
    try {
        $date = $args['date'];
        $queryParams = $request->getQueryParams();
        
        $status = $queryParams['status'] ?? null;
        $escalatedTo = $queryParams['escalated_to'] ?? null;
        
        $service = new \App\Services\EscalationService();
        $data = $service->getEscalationDetailsByDate($date, $status, $escalatedTo);
        
        return jsonResponse($response, $data, 'success', 'Escalation details retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct escalation types
$app->get('/v1/escalations/filters/types', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EscalationService();
        $types = $service->getDistinctEscalationTypes();
        
        return jsonResponse($response, ['types' => $types], 'success', 'Escalation types retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct statuses
$app->get('/v1/escalations/filters/statuses', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EscalationService();
        $statuses = $service->getDistinctStatuses();
        
        return jsonResponse($response, ['statuses' => $statuses], 'success', 'Statuses retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct escalated_to values
$app->get('/v1/escalations/filters/escalated-to', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EscalationService();
        $escalatedTo = $service->getDistinctEscalatedTo();
        
        return jsonResponse($response, ['escalated_to' => $escalatedTo], 'success', 'Escalated to values retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ESCALATION AGENTWISE ENDPOINTS
// ======================================

// Get escalation metrics grouped by user/agent
$app->get('/v1/escalations-agentwise', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        
        $service = new \App\Services\EscalationAgentwiseService();
        $data = $service->getEscalationMetricsByUser($startDate, $endDate);
        
        return jsonResponse($response, $data, 'success', 'Escalation metrics by user retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get escalation details for a specific user
$app->get('/v1/escalations-agentwise/user/{user}', function (Request $request, Response $response, array $args) {
    try {
        $user = $args['user'];
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        
        $service = new \App\Services\EscalationAgentwiseService();
        $data = $service->getEscalationDetailsByUser($user, $startDate, $endDate);
        
        return jsonResponse($response, $data, 'success', 'Escalation details for user retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ESCALATION STATUSWISE ENDPOINTS
// ======================================

// Get daily rollup data grouped by status and escalated_to
$app->get('/v1/escalations-statuswise', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        
        $service = new \App\Services\EscalationStatuswiseService();
        $data = $service->getDailyRollup($startDate, $endDate);
        
        return jsonResponse($response, $data, 'success', 'Daily rollup data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get escalation details for a specific date
$app->get('/v1/escalations-statuswise/date/{date}', function (Request $request, Response $response, array $args) {
    try {
        $date = $args['date'];
        
        $service = new \App\Services\EscalationStatuswiseService();
        $data = $service->getEscalationDetailsByDate($date);
        
        return jsonResponse($response, $data, 'success', 'Escalation details for date retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TRAMS INVOICE ENDPOINTS
// ======================================

// Check invoice existence (batch)
$app->post('/v1/trams-invoice/check-existence', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $invoiceNumbers = $body['invoice_numbers'] ?? [];
        
        $service = new \App\Services\TramsInvoiceService();
        $data = $service->checkInvoiceExistence($invoiceNumbers);
        
        return jsonResponse($response, $data, 'success', 'Invoice existence check completed');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get invoice details
$app->get('/v1/trams-invoice/{invoice_number}', function (Request $request, Response $response, array $args) {
    try {
        $invoiceNumber = $args['invoice_number'];
        
        $service = new \App\Services\TramsInvoiceService();
        $data = $service->getInvoiceDetails($invoiceNumber);
        
        return jsonResponse($response, $data, 'success', 'Invoice details retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update invoice
$app->put('/v1/trams-invoice/{invoice_number}', function (Request $request, Response $response, array $args) {
    try {
        $invoiceNumber = $args['invoice_number'];
        $body = $request->getParsedBody();
        
        $body['invoicenumber'] = $invoiceNumber;
        
        $service = new \App\Services\TramsInvoiceService();
        $data = $service->updateInvoice($body);
        
        return jsonResponse($response, $data, $data['success'] ? 'success' : 'error', $data['message']);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Batch update invoices
$app->post('/v1/trams-invoice/batch-update', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $invoices = $body['invoices'] ?? [];
        
        $service = new \App\Services\TramsInvoiceService();
        $data = $service->batchUpdateInvoices($invoices);
        
        return jsonResponse($response, $data, $data['success'] ? 'success' : 'error', 'Batch update completed');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MATCHED TICKET ENDPOINTS
// ======================================

// Get matched tickets
$app->get('/v1/matched-ticket', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $fromDate = $queryParams['from_date'] ?? null;
        $toDate = $queryParams['to_date'] ?? null;
        $vendor = $queryParams['vendor'] ?? null;
        
        if (!$fromDate || !$toDate) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\MatchedTicketService();
        $data = $service->getMatchedTickets($fromDate, $toDate, $vendor);
        
        return jsonResponse($response, $data, 'success', 'Matched tickets retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get ticket reconciliation records
$app->get('/v1/matched-ticket/reconciliation', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $fromDate = $queryParams['from_date'] ?? null;
        $toDate = $queryParams['to_date'] ?? null;
        $vendor = $queryParams['vendor'] ?? null;
        $page = $queryParams['page'] ?? 1;
        $perPage = $queryParams['per_page'] ?? 100;
        
        $service = new \App\Services\MatchedTicketService();
        $data = $service->getTicketReconciliation($fromDate, $toDate, $vendor, $page, $perPage);
        
        return jsonResponse($response, $data, 'success', 'Ticket reconciliation records retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Insert matched tickets into reconciliation
$app->post('/v1/matched-ticket/insert-matched', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $documents = $body['documents'] ?? [];
        
        $service = new \App\Services\MatchedTicketService();
        $data = $service->insertMatchedTickets($documents);
        
        return jsonResponse($response, $data, 'success', 'Matched tickets inserted successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update order amounts
$app->post('/v1/matched-ticket/update-order-amounts', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $startDate = $body['start_date'] ?? null;
        $endDate = $body['end_date'] ?? null;
        $paxId = $body['pax_id'] ?? null;
        
        if (!$startDate || !$endDate) {
            throw new Exception('start_date and end_date are required', 400);
        }
        
        $service = new \App\Services\MatchedTicketService();
        $data = $service->updateOrderAmounts($startDate, $endDate, $paxId);
        
        return jsonResponse($response, $data, 'success', 'Order amounts updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// EMD RECONCILIATION ENDPOINTS
// ======================================

// Import EMD CSV file
$app->post('/v1/emd/import', function (Request $request, Response $response) {
    try {
        $uploadedFiles = $request->getUploadedFiles();
        
        if (empty($uploadedFiles['emd_csv'])) {
            throw new Exception('Missing file: emd_csv', 400);
        }
        
        $file = $uploadedFiles['emd_csv'];
        
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error', 400);
        }
        
        // Save uploaded file temporarily
        $tempFile = tempnam(sys_get_temp_dir(), 'emd_');
        $file->moveTo($tempFile);
        
        $db = $this->get('db');
        $dal = new \App\DAL\EMDDAL($db);
        $service = new \App\Services\EMDService($dal);
        $addedBy = $request->getAttribute('user') ?? 'api';
        $processed = $service->importEMDFromCSV($tempFile, $addedBy);
        
        // Clean up temp file
        @unlink($tempFile);
        
        return jsonResponse($response, [
            'processed' => $processed,
            'message' => "EMD CSV imported. Rows processed: {$processed}."
        ], 'success', 'EMD CSV imported successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get available flights for a travel date
$app->get('/v1/emd/flights', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $travelDateRaw = $queryParams['travel_date'] ?? null;
        
        if ($travelDateRaw === null || $travelDateRaw === '') {
            throw new Exception('Missing travel_date parameter. Please provide travel_date in format YYYY-MM-DD or DD/MM/YYYY', 400);
        }
        
        $travelDate = parseDate($travelDateRaw);
        
        if (!$travelDate) {
            throw new Exception('Invalid travel_date format. Received: "' . htmlspecialchars($travelDateRaw) . '". Please use YYYY-MM-DD or DD/MM/YYYY format', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\EMDDAL($db);
        $service = new \App\Services\EMDService($dal);
        $flights = $service->getFlights($travelDate);
        
        return jsonResponse($response, [
            'travel_date' => $travelDate,
            'flights' => $flights
        ], 'success', 'Flights retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get PAX list filtered by travel_date and int_flight
$app->get('/v1/emd/pax', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $travelDateRaw = $queryParams['travel_date'] ?? null;
        
        if ($travelDateRaw === null || $travelDateRaw === '') {
            throw new Exception('Missing travel_date parameter. Please provide travel_date in format YYYY-MM-DD or DD/MM/YYYY', 400);
        }
        
        $travelDate = parseDate($travelDateRaw);
        $intFlight = strtoupper(trim($queryParams['int_flight'] ?? ''));
        
        if (!$travelDate) {
            throw new Exception('Invalid travel_date format. Received: "' . htmlspecialchars($travelDateRaw) . '". Please use YYYY-MM-DD or DD/MM/YYYY format', 400);
        }
        
        if ($intFlight === '') {
            throw new Exception('Missing int_flight parameter', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\EMDDAL($db);
        $service = new \App\Services\EMDService($dal);
        $result = $service->getPax($travelDate, $intFlight);
        
        return jsonResponse($response, [
            'travel_date' => $travelDate,
            'int_flight' => $intFlight,
            'count' => count($result['rows']),
            'summary' => $result['summary'],
            'rows' => $result['rows']
        ], 'success', 'PAX list retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Assign EMD to selected PAX
$app->post('/v1/emd/assign', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $emdDocument = $body['emd_document'] ?? '';
        $paxIds = $body['pax_ids'] ?? [];
        
        if (empty($paxIds)) {
            throw new Exception('No PAX IDs provided', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\EMDDAL($db);
        $service = new \App\Services\EMDService($dal);
        $addedBy = $request->getAttribute('user') ?? 'api';
        $result = $service->assignEMD($emdDocument, $paxIds, $addedBy);
        
        $insCount = $result['details']['reconciliation_inserted'];
        $updCount = $result['details']['order_amounts_updated'];
        
        $message = "Reconciled EMD {$result['emd_document']} for {$result['pax_count']} PAX. " .
                   ($insCount ? "Inserted {$insCount} reconciliation row(s). " : "Reconciliation insert skipped (unknown schema). ") .
                   ($updCount ? "Recalculated {$updCount} order amount(s)." : "Order amount update skipped (target table/columns not found).");
        
        return jsonResponse($response, [
            'emd_document' => $result['emd_document'],
            'pax_count' => $result['pax_count'],
            'pax_ids' => $result['pax_ids'],
            'details' => $result['details']
        ], 'success', $message, 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get recent EMDs
$app->get('/v1/emd/recent', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $limit = (int)($queryParams['limit'] ?? 30);
        $limit = max(1, min(100, $limit)); // Clamp between 1 and 100
        
        $db = $this->get('db');
        $dal = new \App\DAL\EMDDAL($db);
        $service = new \App\Services\EMDService($dal);
        $emds = $service->getRecentEMDs($limit);
        
        return jsonResponse($response, [
            'limit' => $limit,
            'count' => count($emds),
            'emds' => $emds
        ], 'success', 'Recent EMDs retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Search EMDs with filters
$app->get('/v1/emd/search', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [];
        
        if (!empty($queryParams['document'])) {
            $filters['document'] = $queryParams['document'];
        }
        
        if (!empty($queryParams['vendor'])) {
            $filters['vendor'] = $queryParams['vendor'];
        }
        
        if (!empty($queryParams['pnr'])) {
            $filters['pnr'] = $queryParams['pnr'];
        }
        
        if (!empty($queryParams['date_from'])) {
            $dateFrom = parseDate($queryParams['date_from']);
            if ($dateFrom) {
                $filters['date_from'] = $dateFrom;
            }
        }
        
        if (!empty($queryParams['date_to'])) {
            $dateTo = parseDate($queryParams['date_to']);
            if ($dateTo) {
                $filters['date_to'] = $dateTo;
            }
        }
        
        if (!empty($queryParams['min_amount'])) {
            $filters['min_amount'] = (float)$queryParams['min_amount'];
        }
        
        if (!empty($queryParams['max_amount'])) {
            $filters['max_amount'] = (float)$queryParams['max_amount'];
        }
        
        if (!empty($queryParams['limit'])) {
            $filters['limit'] = (int)$queryParams['limit'];
        }
        
        if (!empty($queryParams['offset'])) {
            $filters['offset'] = (int)$queryParams['offset'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\EMDDAL($db);
        $service = new \App\Services\EMDService($dal);
        $result = $service->searchEMDs($filters);
        
        return jsonResponse($response, [
            'emds' => $result['emds'],
            'total' => $result['total'],
            'count' => $result['count'],
            'limit' => $result['limit'],
            'offset' => $result['offset']
        ], 'success', 'EMDs retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PRODUCT MANAGEMENT ENDPOINTS
// ======================================

// Get available products for insertion
$app->get('/v1/products/available', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\ProductDAL($db);
        $service = new \App\Services\ProductService($dal);
        $products = $service->getAvailableProducts();
        
        return jsonResponse($response, [
            'count' => count($products),
            'products' => $products
        ], 'success', 'Available products retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Bulk insert products
$app->post('/v1/products/insert', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\ProductDAL($db);
        $service = new \App\Services\ProductService($dal);
        $addedBy = $request->getAttribute('user') ?? 'api';
        $result = $service->insertProducts($addedBy);
        
        return jsonResponse($response, $result, 'success', 
            "Inserted {$result['inserted']} products out of {$result['total_available']} available", 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get products with filters
$app->get('/v1/products', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [];
        
        if (!empty($queryParams['product_id'])) {
            $filters['product_id'] = $queryParams['product_id'];
        }
        
        if (!empty($queryParams['pricing_id'])) {
            $filters['pricing_id'] = $queryParams['pricing_id'];
        }
        
        if (!empty($queryParams['trip_code'])) {
            $filters['trip_code'] = $queryParams['trip_code'];
        }
        
        if (!empty($queryParams['travel_date'])) {
            $travelDate = parseDate($queryParams['travel_date']);
            if ($travelDate) {
                $filters['travel_date'] = $travelDate;
            }
        }
        
        if (!empty($queryParams['limit'])) {
            $filters['limit'] = (int)$queryParams['limit'];
        }
        
        if (!empty($queryParams['offset'])) {
            $filters['offset'] = (int)$queryParams['offset'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\ProductDAL($db);
        $service = new \App\Services\ProductService($dal);
        $result = $service->getProducts($filters);
        
        return jsonResponse($response, [
            'products' => $result['products'],
            'total' => $result['total'],
            'count' => $result['count'],
            'limit' => $result['limit'],
            'offset' => $result['offset']
        ], 'success', 'Products retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get product by ID
$app->get('/v1/products/{id}', function (Request $request, Response $response, array $args) {
    try {
        $autoId = $args['id'];
        
        $db = $this->get('db');
        $dal = new \App\DAL\ProductDAL($db);
        $service = new \App\Services\ProductService($dal);
        $product = $service->getProductById($autoId);
        
        return jsonResponse($response, $product, 'success', 'Product retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Create new product
$app->post('/v1/products', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        if (empty($body)) {
            throw new Exception('Product data required', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\ProductDAL($db);
        $service = new \App\Services\ProductService($dal);
        $result = $service->createProduct($body);
        
        return jsonResponse($response, $result, 'success', 'Product created successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update product
$app->put('/v1/products/{id}', function (Request $request, Response $response, array $args) {
    try {
        $autoId = $args['id'];
        $body = $request->getParsedBody();
        
        if (empty($body)) {
            throw new Exception('Product data required', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\ProductDAL($db);
        $service = new \App\Services\ProductService($dal);
        $result = $service->updateProduct($autoId, $body);
        
        return jsonResponse($response, $result, 'success', 'Product updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Delete product
$app->delete('/v1/products/{id}', function (Request $request, Response $response, array $args) {
    try {
        $autoId = $args['id'];
        
        $db = $this->get('db');
        $dal = new \App\DAL\ProductDAL($db);
        $service = new \App\Services\ProductService($dal);
        $result = $service->deleteProduct($autoId);
        
        return jsonResponse($response, $result, 'success', 'Product deleted successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PREDEPARTURE CHECKLIST ENDPOINTS
// ======================================

// Get bookings with filters
$app->get('/v1/predeparture-checklist/bookings', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [];
        
        // Route filter
        if (!empty($queryParams['route'])) {
            $filters['route'] = $queryParams['route'];
        }
        
        // Airline filter
        if (!empty($queryParams['airline'])) {
            $filters['airline'] = $queryParams['airline'];
        }
        
        // Travel date filter
        if (!empty($queryParams['travel_date'])) {
            $travelDateRaw = $queryParams['travel_date'];
            $travelDate = parseDate($travelDateRaw);
            if (!$travelDate) {
                throw new Exception('Invalid travel_date format. Received: "' . htmlspecialchars($travelDateRaw) . '". Please use YYYY-MM-DD or DD/MM/YYYY format', 400);
            }
            $filters['travel_date'] = $travelDate;
        }
        
        // Status filter
        if (!empty($queryParams['status'])) {
            $status = $queryParams['status'];
            if (!in_array($status, ['completed', 'not_completed'])) {
                throw new Exception('Invalid status. Must be "completed" or "not_completed"', 400);
            }
            $filters['status'] = $status;
        }
        
        // Limit
        if (!empty($queryParams['limit'])) {
            $filters['limit'] = (int)$queryParams['limit'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\PredepartureChecklistDAL($db);
        $service = new \App\Services\PredepartureChecklistService($dal);
        $result = $service->getBookings($filters);
        
        return jsonResponse($response, $result, 'success', 'Bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get booking details by order ID
$app->get('/v1/predeparture-checklist/bookings/{order_id}', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['order_id'];
        
        $db = $this->get('db');
        $dal = new \App\DAL\PredepartureChecklistDAL($db);
        $service = new \App\Services\PredepartureChecklistService($dal);
        $result = $service->getOrderChecklist($orderId);
        
        return jsonResponse($response, $result, 'success', 'Booking checklist retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get PAX list for an order
$app->get('/v1/predeparture-checklist/bookings/{order_id}/pax', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['order_id'];
        $queryParams = $request->getQueryParams();
        
        $productId = $queryParams['product_id'] ?? null;
        $coOrderId = $queryParams['co_order_id'] ?? null;
        
        $db = $this->get('db');
        $dal = new \App\DAL\PredepartureChecklistDAL($db);
        $service = new \App\Services\PredepartureChecklistService($dal);
        $result = $service->getPaxList($orderId, $productId, $coOrderId);
        
        return jsonResponse($response, $result, 'success', 'PAX list retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get checklist categories
$app->get('/v1/predeparture-checklist/categories', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\PredepartureChecklistDAL($db);
        $service = new \App\Services\PredepartureChecklistService($dal);
        $result = $service->getChecklistCategories();
        
        return jsonResponse($response, $result, 'success', 'Checklist categories retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get checklist for a specific order and pax
$app->get('/v1/predeparture-checklist/bookings/{order_id}/pax/{pax_id}/checklist', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['order_id'];
        $paxId = $args['pax_id'];
        
        $db = $this->get('db');
        $dal = new \App\DAL\PredepartureChecklistDAL($db);
        $service = new \App\Services\PredepartureChecklistService($dal);
        $result = $service->getChecklist($orderId, $paxId);
        
        return jsonResponse($response, $result, 'success', 'Checklist retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update single checklist item
$app->put('/v1/predeparture-checklist/bookings/{order_id}/pax/{pax_id}/checklist', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['order_id'];
        $paxId = $args['pax_id'];
        $body = $request->getParsedBody();
        
        if (empty($body)) {
            throw new Exception('Checklist item data required', 400);
        }
        
        if (empty($body['check_title'])) {
            throw new Exception('check_title is required', 400);
        }
        
        $checkTitle = $body['check_title'];
        $checkValue = $body['check_value'] ?? '';
        $checkOutcome = $body['check_outcome'] ?? '';
        $updatedBy = $request->getAttribute('user') ?? 'api';
        
        $db = $this->get('db');
        $dal = new \App\DAL\PredepartureChecklistDAL($db);
        $service = new \App\Services\PredepartureChecklistService($dal);
        $result = $service->updateChecklistItem($orderId, $paxId, $checkTitle, $checkValue, $checkOutcome, $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'Checklist item updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Bulk update checklist items
$app->post('/v1/predeparture-checklist/bookings/{order_id}/pax/{pax_id}/checklist/bulk', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['order_id'];
        $paxId = $args['pax_id'];
        $body = $request->getParsedBody();
        
        if (empty($body)) {
            throw new Exception('Checklist items data required', 400);
        }
        
        if (empty($body['items']) || !is_array($body['items'])) {
            throw new Exception('items must be a non-empty array', 400);
        }
        
        $updatedBy = $request->getAttribute('user') ?? 'api';
        
        $db = $this->get('db');
        $dal = new \App\DAL\PredepartureChecklistDAL($db);
        $service = new \App\Services\PredepartureChecklistService($dal);
        $result = $service->updateChecklistItems($orderId, $paxId, $body['items'], $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'Checklist items updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SEAT AND PRICE MANAGEMENT ENDPOINTS
// ======================================

// Get seat and price data with filters
$app->get('/v1/seat-price', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $year = $queryParams['year'] ?? null;
        $month = $queryParams['month'] ?? null;
        
        if (!$year || !$month) {
            throw new Exception('year and month parameters are required', 400);
        }
        
        $filters = [];
        
        if (!empty($queryParams['travel_type'])) {
            $filters['travel_type'] = $queryParams['travel_type'];
        }
        
        if (!empty($queryParams['airline'])) {
            $filters['airline'] = $queryParams['airline'];
        }
        
        if (!empty($queryParams['from_location'])) {
            $filters['from_location'] = $queryParams['from_location'];
        }
        
        if (!empty($queryParams['to_location'])) {
            $filters['to_location'] = $queryParams['to_location'];
        }
        
        if (!empty($queryParams['search_type'])) {
            $searchType = $queryParams['search_type'];
            if (!in_array($searchType, ['all', 'qf'])) {
                throw new Exception('search_type must be either "all" or "qf"', 400);
            }
            $filters['search_type'] = $searchType;
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceDAL($db);
        $service = new \App\Services\SeatPriceService($dal);
        $result = $service->getSeatPriceData($year, $month, $filters);
        
        return jsonResponse($response, $result, 'success', 'Seat and price data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get filter options (airlines, locations)
$app->get('/v1/seat-price/filter-options', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $month = $queryParams['month'] ?? null;
        
        if (!$month) {
            throw new Exception('month parameter is required', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceDAL($db);
        $service = new \App\Services\SeatPriceService($dal);
        $result = $service->getFilterOptions($month);
        
        return jsonResponse($response, $result, 'success', 'Filter options retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// QANTAS (QF) SEAT AND PRICE ENDPOINTS
// ======================================

// Get Qantas seat and price data with filters
$app->get('/v1/seat-price/qantas', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $year = $queryParams['year'] ?? null;
        $month = $queryParams['month'] ?? null;
        
        if (!$year || !$month) {
            throw new Exception('year and month parameters are required', 400);
        }
        
        $filters = [];
        
        if (!empty($queryParams['travel_type'])) {
            $filters['travel_type'] = $queryParams['travel_type'];
        }
        
        if (!empty($queryParams['from_location'])) {
            $filters['from_location'] = $queryParams['from_location'];
        }
        
        if (!empty($queryParams['to_location'])) {
            $filters['to_location'] = $queryParams['to_location'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceDAL($db);
        $service = new \App\Services\SeatPriceService($dal);
        $result = $service->getQantasSeatPriceData($year, $month, $filters);
        
        return jsonResponse($response, $result, 'success', 'Qantas seat and price data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get Qantas filter options (locations only, airline is always QF)
$app->get('/v1/seat-price/qantas/filter-options', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $month = $queryParams['month'] ?? null;
        
        if (!$month) {
            throw new Exception('month parameter is required', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceDAL($db);
        $service = new \App\Services\SeatPriceService($dal);
        $result = $service->getQantasFilterOptions($month);
        
        return jsonResponse($response, $result, 'success', 'Qantas filter options retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SEAT AND PRICE CRON ENDPOINTS
// ======================================

// Delete upcoming months data
$app->delete('/v1/seat-price-cron/upcoming-months', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $monthsAhead = (int)($queryParams['months_ahead'] ?? 6);
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceCronDAL($db);
        $service = new \App\Services\SeatPriceCronService($dal);
        $result = $service->deleteUpcomingMonthsData($monthsAhead);
        
        return jsonResponse($response, $result, 'success', 'Upcoming months data deleted successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get trip dates for a date range
$app->get('/v1/seat-price-cron/trip-dates', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        $limit = (int)($queryParams['limit'] ?? 200);
        
        if (!$startDate || !$endDate) {
            throw new Exception('start_date and end_date parameters are required', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceCronDAL($db);
        $service = new \App\Services\SeatPriceCronService($dal);
        $result = $service->getTripDates($startDate, $endDate, $limit);
        
        return jsonResponse($response, $result, 'success', 'Trip dates retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get trip metadata
$app->get('/v1/seat-price-cron/trip-metadata/{trip_id}', function (Request $request, Response $response, array $args) {
    try {
        $tripId = $args['trip_id'];
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceCronDAL($db);
        $service = new \App\Services\SeatPriceCronService($dal);
        $result = $service->getTripMetadata($tripId);
        
        if (!$result) {
            return jsonResponse($response, null, 'success', 
                "No metadata found for trip ID: $tripId. The trip may not have itinerary type or trip code configured.", 
                404);
        }
        
        return jsonResponse($response, $result, 'success', 'Trip metadata retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get pricing for a specific date and trip
$app->get('/v1/seat-price-cron/pricing', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $tripId = $queryParams['trip_id'] ?? null;
        $date = $queryParams['date'] ?? null;
        
        // Provide more helpful error message
        if (!$tripId || !$date) {
            $missing = [];
            if (!$tripId) $missing[] = 'trip_id';
            if (!$date) $missing[] = 'date';
            
            throw new Exception(
                'Missing required parameters: ' . implode(', ', $missing) . 
                '. Please provide them as query parameters: ?trip_id=XXX&date=YYYY-MM-DD'
            , 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceCronDAL($db);
        $service = new \App\Services\SeatPriceCronService($dal);
        $result = $service->getPricingForDate($tripId, $date);
        
        if (!$result) {
            return jsonResponse($response, null, 'success', 'No pricing found for the given trip and date', 404);
        }
        
        return jsonResponse($response, $result, 'success', 'Pricing retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Calculate remaining seats for a trip and date
$app->get('/v1/seat-price-cron/remaining-seats', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $tripId = $queryParams['trip_id'] ?? null;
        $travelDate = $queryParams['travel_date'] ?? null;
        
        // Provide more helpful error message
        if (!$tripId || !$travelDate) {
            $missing = [];
            if (!$tripId) $missing[] = 'trip_id';
            if (!$travelDate) $missing[] = 'travel_date';
            
            throw new Exception(
                'Missing required parameters: ' . implode(', ', $missing) . 
                '. Please provide them as query parameters: ?trip_id=XXX&travel_date=YYYY-MM-DD'
            , 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceCronDAL($db);
        $service = new \App\Services\SeatPriceCronService($dal);
        $result = $service->calculateRemainingSeats($tripId, $travelDate);
        
        if (!$result) {
            return jsonResponse($response, null, 'success', 'No stock information found for the given trip and date', 404);
        }
        
        return jsonResponse($response, $result, 'success', 'Remaining seats calculated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Process and insert seat stock data for a date range
$app->post('/v1/seat-price-cron/process', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $startDate = $body['start_date'] ?? null;
        $endDate = $body['end_date'] ?? null;
        $limit = (int)($body['limit'] ?? 200);
        
        if (!$startDate || !$endDate) {
            throw new Exception('start_date and end_date are required', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SeatPriceCronDAL($db);
        $service = new \App\Services\SeatPriceCronService($dal);
        $result = $service->processSeatStockData($startDate, $endDate, $limit);
        
        return jsonResponse($response, $result, 'success', 
            "Processed {$result['processed_count']} records" . 
            ($result['error_count'] > 0 ? " with {$result['error_count']} errors" : ""), 
            201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// QUOTE MANAGEMENT ENDPOINTS
// ======================================

// Get quotes with filters
$app->get('/v1/quotes', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [];
        
        if (!empty($queryParams['quote_id'])) {
            $filters['quote_id'] = $queryParams['quote_id'];
        }
        
        if (isset($queryParams['gdeals']) && ($queryParams['gdeals'] === '0' || $queryParams['gdeals'] === '1')) {
            $filters['gdeals'] = $queryParams['gdeals'];
        }
        
        if (!empty($queryParams['from'])) {
            $filters['from'] = $queryParams['from'];
        }
        
        if (!empty($queryParams['to'])) {
            $filters['to'] = $queryParams['to'];
        }
        
        if (!empty($queryParams['depart_date'])) {
            $filters['depart_date'] = $queryParams['depart_date'];
        }
        
        if (!empty($queryParams['email'])) {
            $filters['email'] = $queryParams['email'];
        }
        
        if (!empty($queryParams['phone_num'])) {
            $filters['phone_num'] = $queryParams['phone_num'];
        }
        
        if (!empty($queryParams['user_id'])) {
            $filters['user_id'] = $queryParams['user_id'];
        }
        
        if (!empty($queryParams['call_id'])) {
            $filters['call_id'] = $queryParams['call_id'];
        }
        
        if (!empty($queryParams['limit'])) {
            $filters['limit'] = (int)$queryParams['limit'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\QuoteDAL($db);
        $service = new \App\Services\QuoteService($dal);
        $result = $service->getQuotes($filters);
        
        return jsonResponse($response, $result, 'success', 'Quotes retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get multicity quotes with filters
$app->get('/v1/quotes/multicity', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [];
        
        if (!empty($queryParams['multi_quote_id'])) {
            $filters['multi_quote_id'] = $queryParams['multi_quote_id'];
        }
        
        if (isset($queryParams['gdeals']) && ($queryParams['gdeals'] === '0' || $queryParams['gdeals'] === '1')) {
            $filters['gdeals'] = $queryParams['gdeals'];
        }
        
        if (!empty($queryParams['from'])) {
            $filters['from'] = $queryParams['from'];
        }
        
        if (!empty($queryParams['to'])) {
            $filters['to'] = $queryParams['to'];
        }
        
        if (!empty($queryParams['email'])) {
            $filters['email'] = $queryParams['email'];
        }
        
        if (!empty($queryParams['phone_num'])) {
            $filters['phone_num'] = $queryParams['phone_num'];
        }
        
        if (!empty($queryParams['user_id'])) {
            $filters['user_id'] = $queryParams['user_id'];
        }
        
        if (!empty($queryParams['limit'])) {
            $filters['limit'] = (int)$queryParams['limit'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\QuoteDAL($db);
        $service = new \App\Services\QuoteService($dal);
        $result = $service->getMulticityQuotes($filters);
        
        return jsonResponse($response, $result, 'success', 'Multicity quotes retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET product availability (for quote backend)
$app->get('/v1/quotes/product-availability', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['product_id']) || empty($params['travel_date'])) {
            throw new Exception('product_id and travel_date are required', 400);
        }
        
        $service = new \App\Services\QuoteService();
        $data = $service->getProductAvailability($params['product_id'], $params['travel_date']);
        
        return jsonResponse($response, $data, 'success', 'Product availability retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET search users (for quote backend)
$app->get('/v1/quotes/search-users', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['query'])) {
            throw new Exception('Search query is required', 400);
        }
        
        $service = new \App\Services\QuoteService();
        $data = $service->searchUsers($params['query']);
        
        return jsonResponse($response, $data, 'success', 'Users retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get quote by ID
$app->get('/v1/quotes/{id}', function (Request $request, Response $response, array $args) {
    try {
        $quoteId = (int)$args['id'];
        
        if ($quoteId <= 0) {
            throw new Exception('Invalid quote ID', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\QuoteDAL($db);
        $service = new \App\Services\QuoteService($dal);
        $quote = $service->getQuoteById($quoteId);
        
        if (!$quote) {
            return jsonResponse($response, null, 'success', 'Quote not found', 404);
        }
        
        return jsonResponse($response, $quote, 'success', 'Quote retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get G360 subquotes for a quote
$app->get('/v1/quotes/{id}/g360-subquotes', function (Request $request, Response $response, array $args) {
    try {
        $quoteId = (int)$args['id'];
        
        if ($quoteId <= 0) {
            throw new Exception('Invalid quote ID', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\QuoteDAL($db);
        $service = new \App\Services\QuoteService($dal);
        $subquotes = $service->getG360Subquotes($quoteId);
        
        return jsonResponse($response, [
            'quote_id' => $quoteId,
            'subquotes' => $subquotes,
            'count' => count($subquotes)
        ], 'success', 'G360 subquotes retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// REALTIME CALL DATA ENDPOINTS
// ======================================

// Get realtime call data with filters
$app->get('/v1/realtime-call-data', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [];
        
        if (!empty($queryParams['team'])) {
            $filters['team'] = $queryParams['team'];
        }
        
        if (!empty($queryParams['campaign'])) {
            $filters['campaign'] = $queryParams['campaign'];
        }
        
        if (!empty($queryParams['location'])) {
            $filters['location'] = $queryParams['location'];
        }
        
        if (!empty($queryParams['from'])) {
            $filters['from'] = $queryParams['from'];
        }
        
        if (!empty($queryParams['to'])) {
            $filters['to'] = $queryParams['to'];
        }
        
        if (!empty($queryParams['status'])) {
            $filters['status'] = $queryParams['status'];
        }
        
        if (!empty($queryParams['duration'])) {
            $filters['duration'] = $queryParams['duration'];
        }
        
        if (!empty($queryParams['limit'])) {
            $filters['limit'] = (int)$queryParams['limit'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\RealtimeCallDataDAL($db);
        $service = new \App\Services\RealtimeCallDataService($dal);
        $result = $service->getRealtimeCallData($filters);
        
        return jsonResponse($response, $result, 'success', 'Realtime call data retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct teams
$app->get('/v1/realtime-call-data/filters/teams', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\RealtimeCallDataDAL($db);
        $service = new \App\Services\RealtimeCallDataService($dal);
        $teams = $service->getDistinctTeams();
        
        return jsonResponse($response, ['teams' => $teams], 'success', 'Teams retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct locations
$app->get('/v1/realtime-call-data/filters/locations', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\RealtimeCallDataDAL($db);
        $service = new \App\Services\RealtimeCallDataService($dal);
        $locations = $service->getDistinctLocations();
        
        return jsonResponse($response, ['locations' => $locations], 'success', 'Locations retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct campaigns
$app->get('/v1/realtime-call-data/filters/campaigns', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\RealtimeCallDataDAL($db);
        $service = new \App\Services\RealtimeCallDataService($dal);
        $campaigns = $service->getDistinctCampaigns();
        
        return jsonResponse($response, ['campaigns' => $campaigns], 'success', 'Campaigns retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get realtime call data results (using realtime tables)
$app->get('/v1/realtime-call-data/results', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\RealtimeCallDataDAL($db);
        $service = new \App\Services\RealtimeCallDataService($dal);
        $result = $service->getRealtimeCallDataResults();
        
        return jsonResponse($response, $result, 'success', 'Realtime call data results retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Check for new requests
$app->get('/v1/realtime-call-data/check-new-requests', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $lastValue = $queryParams['lastvalue'] ?? null;
        
        $db = $this->get('db');
        $dal = new \App\DAL\RealtimeCallDataDAL($db);
        $service = new \App\Services\RealtimeCallDataService($dal);
        $result = $service->checkNewRequests($lastValue);
        
        return jsonResponse($response, $result, 'success', 'New requests check completed');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// REFUND MANAGEMENT ENDPOINTS
// ======================================

// Get refunds with filters
$app->get('/v1/refunds', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [];
        
        if (!empty($queryParams['order_id'])) {
            $filters['order_id'] = $queryParams['order_id'];
        }
        
        if (!empty($queryParams['refund_id'])) {
            $filters['refund_id'] = $queryParams['refund_id'];
        }
        
        if (!empty($queryParams['pcc'])) {
            $filters['pcc'] = $queryParams['pcc'];
        }
        
        if (!empty($queryParams['pnr'])) {
            $filters['pnr'] = $queryParams['pnr'];
        }
        
        if (!empty($queryParams['airline'])) {
            $filters['airline'] = $queryParams['airline'];
        }
        
        if (!empty($queryParams['consolidator'])) {
            $filters['consolidator'] = $queryParams['consolidator'];
        }
        
        if (!empty($queryParams['status'])) {
            $filters['status'] = $queryParams['status'];
        }
        
        if (!empty($queryParams['ticket_country'])) {
            $filters['ticket_country'] = $queryParams['ticket_country'];
        }
        
        if (!empty($queryParams['ticketed_date'])) {
            $filters['ticketed_date'] = $queryParams['ticketed_date'];
        }
        
        if (!empty($queryParams['refund_received_date'])) {
            $filters['refund_received_date'] = $queryParams['refund_received_date'];
        }
        
        if (!empty($queryParams['limit'])) {
            $filters['limit'] = (int)$queryParams['limit'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\RefundDAL($db);
        $service = new \App\Services\RefundService($dal);
        $result = $service->getRefunds($filters);
        
        return jsonResponse($response, $result, 'success', 'Refunds retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get refund by ID
$app->get('/v1/refunds/{id}', function (Request $request, Response $response, array $args) {
    try {
        $refundId = $args['id'];
        
        if (empty($refundId)) {
            throw new Exception('Invalid refund ID', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\RefundDAL($db);
        $service = new \App\Services\RefundService($dal);
        $refund = $service->getRefundById($refundId);
        
        if (!$refund) {
            return jsonResponse($response, null, 'success', 'Refund not found', 404);
        }
        
        return jsonResponse($response, $refund, 'success', 'Refund retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get conversations for a refund
$app->get('/v1/refunds/{id}/conversations', function (Request $request, Response $response, array $args) {
    try {
        $refundId = $args['id'];
        $queryParams = $request->getQueryParams();
        $limit = !empty($queryParams['limit']) ? (int)$queryParams['limit'] : null;
        
        if (empty($refundId)) {
            throw new Exception('Invalid refund ID', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\RefundDAL($db);
        $service = new \App\Services\RefundService($dal);
        $conversations = $service->getRefundConversations($refundId, $limit);
        
        return jsonResponse($response, [
            'refund_id' => $refundId,
            'conversations' => $conversations,
            'count' => count($conversations)
        ], 'success', 'Conversations retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get attachments for a refund
$app->get('/v1/refunds/{id}/attachments', function (Request $request, Response $response, array $args) {
    try {
        $refundId = $args['id'];
        
        if (empty($refundId)) {
            throw new Exception('Invalid refund ID', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\RefundDAL($db);
        $service = new \App\Services\RefundService($dal);
        $attachments = $service->getRefundAttachments($refundId);
        
        return jsonResponse($response, [
            'refund_id' => $refundId,
            'attachments' => $attachments,
            'count' => count($attachments)
        ], 'success', 'Attachments retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get term keys for dropdown options
$app->get('/v1/refunds/filters/term-keys', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $category = $queryParams['category'] ?? 'refund';
        $optionType = $queryParams['option_type'] ?? null;
        
        if (empty($optionType)) {
            throw new Exception('option_type parameter is required', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\RefundDAL($db);
        $service = new \App\Services\RefundService($dal);
        $termKeys = $service->getTermKeys($category, $optionType);
        
        return jsonResponse($response, [
            'category' => $category,
            'option_type' => $optionType,
            'term_keys' => $termKeys,
            'count' => count($termKeys)
        ], 'success', 'Term keys retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SALE PRICE MANAGEMENT ENDPOINTS
// ======================================

// Get sale prices with filters
$app->get('/v1/sale-prices', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [];
        
        if (!empty($queryParams['airline'])) {
            $filters['airline'] = $queryParams['airline'];
        }
        
        if (!empty($queryParams['route'])) {
            $filters['route'] = $queryParams['route'];
        }
        
        if (!empty($queryParams['travel_date'])) {
            // Parse date range format: "YYYY-MM-DD - YYYY-MM-DD"
            $dateRange = $queryParams['travel_date'];
            if (strpos($dateRange, ' - ') !== false) {
                $dates = explode(' - ', $dateRange);
                $filters['travel_date_from'] = trim($dates[0]);
                $filters['travel_date_to'] = trim($dates[1] ?? $dates[0]);
            } else {
                $filters['travel_date_from'] = $dateRange;
                $filters['travel_date_to'] = $dateRange;
            }
        }
        
        if (!empty($queryParams['route_type'])) {
            $filters['route_type'] = $queryParams['route_type'];
        }
        
        if (!empty($queryParams['flight_type'])) {
            $filters['flight_type'] = $queryParams['flight_type'];
        }
        
        if (!empty($queryParams['limit'])) {
            $filters['limit'] = (int)$queryParams['limit'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\SalePriceDAL($db);
        $service = new \App\Services\SalePriceService($dal);
        $salePrices = $service->getSalePrices($filters);
        
        return jsonResponse($response, [
            'sale_prices' => $salePrices,
            'count' => count($salePrices),
            'filters_applied' => $filters
        ], 'success', 'Sale prices retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct airlines for filters
$app->get('/v1/sale-prices/filters/airlines', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20;
        
        $db = $this->get('db');
        $dal = new \App\DAL\SalePriceDAL($db);
        $service = new \App\Services\SalePriceService($dal);
        $airlines = $service->getAirlines($limit);
        
        return jsonResponse($response, [
            'airlines' => $airlines,
            'count' => count($airlines)
        ], 'success', 'Airlines retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get distinct routes for filters
$app->get('/v1/sale-prices/filters/routes', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 60;
        
        $db = $this->get('db');
        $dal = new \App\DAL\SalePriceDAL($db);
        $service = new \App\Services\SalePriceService($dal);
        $routes = $service->getRoutes($limit);
        
        return jsonResponse($response, [
            'routes' => $routes,
            'count' => count($routes)
        ], 'success', 'Routes retrieved successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update sale price for a pricing ID
$app->put('/v1/sale-prices/{id}', function (Request $request, Response $response, array $args) {
    try {
        $pricingId = (int)$args['id'];
        $body = $request->getParsedBody();
        
        if (empty($body['column_name'])) {
            throw new Exception('column_name is required', 400);
        }
        
        if (!isset($body['value'])) {
            throw new Exception('value is required', 400);
        }
        
        $columnName = $body['column_name'];
        $newValue = $body['value'];
        $updatedUser = $body['updated_user'] ?? 'system';
        
        $db = $this->get('db');
        $dal = new \App\DAL\SalePriceDAL($db);
        $service = new \App\Services\SalePriceService($dal);
        
        $success = $service->updateSalePrice($pricingId, $columnName, $newValue, $updatedUser);
        
        if ($success) {
            return jsonResponse($response, [
                'pricing_id' => $pricingId,
                'column_name' => $columnName,
                'new_value' => $newValue,
                'updated' => true
            ], 'success', 'Sale price updated successfully');
        } else {
            throw new Exception('Failed to update sale price', 500);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TICKET MANAGEMENT ENDPOINTS
// ======================================

// Get tickets with filters (POST method)
$app->post('/v1/tickets', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $filters = [];
        
        if (!empty($body['document'])) {
            $filters['document'] = $body['document'];
        }
        
        if (!empty($body['limit'])) {
            $filters['limit'] = (int)$body['limit'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\TicketDAL($db);
        $service = new \App\Services\TicketService($dal);
        $tickets = $service->getTickets($filters);
        
        $response = jsonResponse($response, [
            'tickets' => $tickets,
            'count' => count($tickets),
            'filters_applied' => $filters
        ], 'success', 'Tickets retrieved successfully');
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Delete ticket (POST method)
$app->post('/v1/tickets/delete', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        if (empty($body['auto_id'])) {
            throw new Exception('auto_id is required', 400);
        }
        
        $autoId = (int)$body['auto_id'];
        
        $db = $this->get('db');
        $dal = new \App\DAL\TicketDAL($db);
        $service = new \App\Services\TicketService($dal);
        
        $success = $service->deleteTicket($autoId);
        
        if ($success) {
            $response = jsonResponse($response, [
                'auto_id' => $autoId,
                'deleted' => true
            ], 'success', 'Ticket deleted successfully');
            
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        } else {
            throw new Exception('Failed to delete ticket', 500);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// TICKETING MANAGEMENT ENDPOINTS
// ======================================

// Get bookings with filters (POST method)
$app->post('/v1/ticketing/bookings', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $filters = [];
        
        if (!empty($body['tripcode'])) {
            $filters['tripcode'] = $body['tripcode'];
        }
        
        if (!empty($body['travel_date'])) {
            $filters['travel_date'] = $body['travel_date'];
        }
        
        if (!empty($body['order_date_from'])) {
            $filters['order_date_from'] = $body['order_date_from'];
        }
        
        if (!empty($body['order_date_to'])) {
            $filters['order_date_to'] = $body['order_date_to'];
        }
        
        if (!empty($body['order_id'])) {
            $filters['order_id'] = $body['order_id'];
        }
        
        if (!empty($body['pnr'])) {
            $filters['pnr'] = $body['pnr'];
        }
        
        if (!empty($body['payment_status'])) {
            $filters['payment_status'] = $body['payment_status'];
        }
        
        if (!empty($body['pax_status'])) {
            $filters['pax_status'] = $body['pax_status'];
        }
        
        if (!empty($body['name_updated'])) {
            $filters['name_updated'] = $body['name_updated'];
        }
        
        if (!empty($body['domestic_filter'])) {
            $filters['domestic_filter'] = $body['domestic_filter'];
        }
        
        if (!empty($body['limit'])) {
            $filters['limit'] = (int)$body['limit'];
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\TicketingDAL($db);
        $service = new \App\Services\TicketingService($dal);
        $bookings = $service->getBookings($filters);
        
        $response = jsonResponse($response, [
            'bookings' => $bookings,
            'count' => count($bookings),
            'filters_applied' => $filters
        ], 'success', 'Bookings retrieved successfully');
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Update passenger ticketing information (POST method)
$app->post('/v1/ticketing/update-pax', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        if (empty($body['pax_auto_id'])) {
            throw new Exception('pax_auto_id is required', 400);
        }
        
        $paxAutoId = (int)$body['pax_auto_id'];
        $updatedUser = $body['updated_user'] ?? 'system';
        
        // Extract update data (exclude pax_auto_id and updated_user)
        $updateData = $body;
        unset($updateData['pax_auto_id']);
        unset($updateData['updated_user']);
        
        if (empty($updateData)) {
            throw new Exception('No data provided to update', 400);
        }
        
        $db = $this->get('db');
        $dal = new \App\DAL\TicketingDAL($db);
        $service = new \App\Services\TicketingService($dal);
        
        $success = $service->updatePaxTicketing($paxAutoId, $updateData, $updatedUser);
        
        if ($success) {
            $response = jsonResponse($response, [
                'pax_auto_id' => $paxAutoId,
                'updated' => true,
                'updated_fields' => array_keys($updateData)
            ], 'success', 'Passenger ticketing information updated successfully');
            
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        } else {
            throw new Exception('Failed to update passenger ticketing information', 500);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Get distinct airlines for filters (POST method)
$app->post('/v1/ticketing/filters/airlines', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\TicketingDAL($db);
        $service = new \App\Services\TicketingService($dal);
        $airlines = $service->getAirlines();
        
        $response = jsonResponse($response, [
            'airlines' => $airlines,
            'count' => count($airlines)
        ], 'success', 'Airlines retrieved successfully');
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Get distinct trip codes for filters (POST method)
$app->post('/v1/ticketing/filters/trip-codes', function (Request $request, Response $response) {
    try {
        $db = $this->get('db');
        $dal = new \App\DAL\TicketingDAL($db);
        $service = new \App\Services\TicketingService($dal);
        $tripCodes = $service->getTripCodes();
        
        $response = jsonResponse($response, [
            'trip_codes' => $tripCodes,
            'count' => count($tripCodes)
        ], 'success', 'Trip codes retrieved successfully');
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// PNR CHECKUP ENDPOINTS
// ======================================

// Get passenger by first name, last name, and PNR
$app->get('/v1/pnr-checkup/pax-by-name-pnr', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $firstName = $queryParams['first_name'] ?? '';
        $lastName = $queryParams['last_name'] ?? '';
        $pnr = $queryParams['pnr'] ?? '';
        
        if (empty($firstName)) {
            throw new Exception('first_name parameter is required', 400);
        }
        
        if (empty($lastName)) {
            throw new Exception('last_name parameter is required', 400);
        }
        
        if (empty($pnr)) {
            throw new Exception('pnr parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $pax = $service->getPaxByNameAndPNR($firstName, $lastName, $pnr);
        
        if ($pax) {
            return jsonResponse($response, $pax, 'success', 'Passenger found');
        } else {
            return jsonResponse($response, null, 'success', 'Passenger not found', 404);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get currency conversion rate for a booking
$app->get('/v1/pnr-checkup/currency-rate/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = (int)$args['orderId'];
        
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $rate = $service->getCurrencyRate($orderId);
        
        if ($rate !== null) {
            return jsonResponse($response, ['order_id' => $orderId, 'rate' => $rate], 'success', 'Currency rate retrieved');
        } else {
            return jsonResponse($response, null, 'success', 'Currency rate not found', 404);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get passenger by order_id, first name, and last name
$app->get('/v1/pnr-checkup/pax-by-order-name', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $orderId = (int)($queryParams['order_id'] ?? 0);
        $firstName = $queryParams['first_name'] ?? '';
        $lastName = $queryParams['last_name'] ?? '';
        
        if ($orderId <= 0) {
            throw new Exception('order_id parameter is required and must be a positive integer', 400);
        }
        
        if (empty($firstName)) {
            throw new Exception('first_name parameter is required', 400);
        }
        
        if (empty($lastName)) {
            throw new Exception('last_name parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $pax = $service->getPaxByOrderAndName($orderId, $firstName, $lastName);
        
        if ($pax) {
            return jsonResponse($response, $pax, 'success', 'Passenger found');
        } else {
            return jsonResponse($response, null, 'success', 'Passenger not found', 404);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get fare information for a passenger
$app->get('/v1/pnr-checkup/fare/{paxId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $paxId = $args['paxId'];
        
        if (empty($paxId)) {
            throw new Exception('paxId parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $fare = $service->getFareByPaxId($paxId);
        
        return jsonResponse($response, [
            'pax_id' => $paxId,
            'fare' => $fare
        ], 'success', 'Fare information retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get tax information for a passenger
$app->get('/v1/pnr-checkup/tax/{paxId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $paxId = $args['paxId'];
        
        if (empty($paxId)) {
            throw new Exception('paxId parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $tax = $service->getTaxByPaxId($paxId);
        
        return jsonResponse($response, [
            'pax_id' => $paxId,
            'tax' => $tax
        ], 'success', 'Tax information retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get metadata from history of updates
$app->get('/v1/pnr-checkup/history-metadata', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $metaKey = $queryParams['meta_key'] ?? '';
        $orderId = (int)($queryParams['order_id'] ?? 0);
        
        if (empty($metaKey)) {
            throw new Exception('meta_key parameter is required', 400);
        }
        
        if ($orderId <= 0) {
            throw new Exception('order_id parameter is required and must be a positive integer', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $metaValue = $service->getHistoryMetadata($metaKey, $orderId);
        
        if ($metaValue !== null) {
            return jsonResponse($response, [
                'order_id' => $orderId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue
            ], 'success', 'Metadata retrieved');
        } else {
            return jsonResponse($response, null, 'success', 'Metadata not found', 404);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get booking by order_id
$app->get('/v1/pnr-checkup/booking/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = (int)$args['orderId'];
        
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $booking = $service->getBookingByOrderId($orderId);
        
        if ($booking) {
            return jsonResponse($response, $booking, 'success', 'Booking retrieved');
        } else {
            return jsonResponse($response, null, 'success', 'Booking not found', 404);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get passenger by PNR and GDS PAX ID
$app->get('/v1/pnr-checkup/pax-by-pnr-gds', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $pnr = $queryParams['pnr'] ?? '';
        $gdsPaxId = $queryParams['gds_pax_id'] ?? '';
        
        if (empty($pnr)) {
            throw new Exception('pnr parameter is required', 400);
        }
        
        if (empty($gdsPaxId)) {
            throw new Exception('gds_pax_id parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $pax = $service->getPaxByPnrAndGdsPaxId($pnr, $gdsPaxId);
        
        if ($pax) {
            return jsonResponse($response, $pax, 'success', 'Passenger found');
        } else {
            return jsonResponse($response, null, 'success', 'Passenger not found', 404);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get all history metadata for an order_id
$app->get('/v1/pnr-checkup/history-all/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = (int)$args['orderId'];
        
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $history = $service->getAllHistoryMetadata($orderId);
        
        return jsonResponse($response, [
            'order_id' => $orderId,
            'history' => $history,
            'count' => count($history)
        ], 'success', 'History metadata retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Insert passenger mismatch record
$app->post('/v1/pnr-checkup/pax-mismatch', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $orderId = (int)($body['order_id'] ?? 0);
        $paxId = (int)($body['pax_id'] ?? 0);
        $uniqueId = $body['unique_id'] ?? '';
        $metaKey = $body['meta_key'] ?? '';
        $apiResponse = $body['api_response'] ?? '';
        $dbResults = $body['db_results'] ?? '';
        $checkedDate = $body['checked_date'] ?? date('Y-m-d H:i:s');
        $checkedBy = $body['checked_by'] ?? 'api';
        
        if ($orderId <= 0) {
            throw new Exception('order_id is required and must be a positive integer', 400);
        }
        
        if ($paxId <= 0) {
            throw new Exception('pax_id is required and must be a positive integer', 400);
        }
        
        if (empty($uniqueId)) {
            throw new Exception('unique_id is required', 400);
        }
        
        if (empty($metaKey)) {
            throw new Exception('meta_key is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $success = $service->insertPaxMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy);
        
        if ($success) {
            return jsonResponse($response, [
                'order_id' => $orderId,
                'pax_id' => $paxId,
                'unique_id' => $uniqueId,
                'inserted' => true
            ], 'success', 'Passenger mismatch record inserted successfully', 201);
        } else {
            throw new Exception('Failed to insert passenger mismatch record', 500);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Insert itinerary mismatch record
$app->post('/v1/pnr-checkup/itinerary-mismatch', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $orderId = (int)($body['order_id'] ?? 0);
        $paxId = (int)($body['pax_id'] ?? 0);
        $uniqueId = $body['unique_id'] ?? '';
        $metaKey = $body['meta_key'] ?? '';
        $apiResponse = $body['api_response'] ?? '';
        $dbResults = $body['db_results'] ?? '';
        $checkedDate = $body['checked_date'] ?? date('Y-m-d H:i:s');
        $checkedBy = $body['checked_by'] ?? 'api';
        
        if ($orderId <= 0) {
            throw new Exception('order_id is required and must be a positive integer', 400);
        }
        
        if ($paxId <= 0) {
            throw new Exception('pax_id is required and must be a positive integer', 400);
        }
        
        if (empty($uniqueId)) {
            throw new Exception('unique_id is required', 400);
        }
        
        if (empty($metaKey)) {
            throw new Exception('meta_key is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PNRCheckupDAL($db);
        $service = new \App\Services\PNRCheckupService($dal);
        
        $success = $service->insertItineraryMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy);
        
        if ($success) {
            return jsonResponse($response, [
                'order_id' => $orderId,
                'pax_id' => $paxId,
                'unique_id' => $uniqueId,
                'inserted' => true
            ], 'success', 'Itinerary mismatch record inserted successfully', 201);
        } else {
            throw new Exception('Failed to insert itinerary mismatch record', 500);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TICKET NUMBER UPDATOR ENDPOINTS
// ======================================

// Get passenger info by auto_id
$app->get('/v1/ticket-number-updator/pax/{autoId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $autoId = (int)$args['autoId'];
        
        if ($autoId <= 0) {
            throw new Exception('Invalid auto_id', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\TicketNumberUpdatorDAL($db);
        
        $pax = $dal->getPaxByAutoId($autoId);
        
        if ($pax) {
            return jsonResponse($response, $pax, 'success', 'Passenger info retrieved');
        } else {
            return jsonResponse($response, null, 'success', 'Passenger not found', 404);
        }
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update ticket numbers (POST method)
$app->post('/v1/ticket-number-updator/update', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        if (empty($body['autoid'])) {
            throw new Exception('autoid is required', 400);
        }
        
        $autoid = (int)$body['autoid'];
        $fname = $body['fname'] ?? '';
        $lname = $body['lname'] ?? '';
        $airlineCode = $body['airline'] ?? '';
        $updatedBy = $body['updated_by'] ?? 'system';
        $ticketsJson = $body['tickets_json'] ?? '[]';
        
        $tickets = json_decode($ticketsJson, true);
        if (!is_array($tickets)) {
            $tickets = [];
        }
        
        if (empty($tickets)) {
            throw new Exception('tickets_json is required and must be a valid JSON array', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\TicketNumberUpdatorDAL($db);
        $service = new \App\Services\TicketNumberUpdatorService($dal);
        
        $result = $service->updateTicketNumbers($autoid, $fname, $lname, $airlineCode, $updatedBy, $tickets);
        
        $response = jsonResponse($response, $result, 'success', 'Ticket numbers updated successfully');
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// PAYMENT STATUS ENDPOINTS
// ======================================

// Get current payment details for an order
$app->get('/v1/payment-status/details/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = (int)$args['orderId'];
        
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PaymentStatusDAL($db);
        $service = new \App\Services\PaymentStatusService($dal);
        
        $result = $service->getCurrentPaymentDetails($orderId);
        
        return jsonResponse($response, $result, 'success', 'Payment details retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get current payment status (simple version)
$app->get('/v1/payment-status/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = (int)$args['orderId'];
        
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PaymentStatusDAL($db);
        $service = new \App\Services\PaymentStatusService($dal);
        
        $status = $service->getCurrentPaymentStatus($orderId);
        
        return jsonResponse($response, [
            'order_id' => $orderId,
            'payment_status' => $status
        ], 'success', 'Payment status retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get current payment status with BPAY checks (with optional auto-update)
$app->get('/v1/payment-status/advanced/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = (int)$args['orderId'];
        
        if ($orderId <= 0) {
            throw new Exception('Invalid order_id', 400);
        }
        
        $queryParams = $request->getQueryParams();
        $autoUpdate = isset($queryParams['auto_update']) && $queryParams['auto_update'] === '1';
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\PaymentStatusDAL($db);
        $service = new \App\Services\PaymentStatusService($dal);
        
        $result = $service->getCurrentPaymentStatus2($orderId, $autoUpdate);
        
        return jsonResponse($response, $result, 'success', 'Payment status retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ORDER MANAGEMENT ENDPOINTS
// ======================================

// Get booking details by order_id
$app->get('/v1/order-management/booking/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = $args['orderId'];
        $queryParams = $request->getQueryParams();
        $coOrderId = $queryParams['co_order_id'] ?? '';
        
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\OrderManagementDAL($db);
        $service = new \App\Services\OrderManagementService($dal);
        
        $result = $service->getBookingDetails($orderId, $coOrderId);
        
        return jsonResponse($response, $result, 'success', 'Booking details retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get recent bookings
$app->get('/v1/order-management/recent', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 60;
        
        if ($limit < 1 || $limit > 500) {
            $limit = 60;
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\OrderManagementDAL($db);
        $service = new \App\Services\OrderManagementService($dal);
        
        $result = $service->getRecentBookings($limit);
        
        return jsonResponse($response, $result, 'success', 'Recent bookings retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Divide order (split passengers into separate bookings)
$app->post('/v1/order-management/divide', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $orderId = $body['order_id'] ?? '';
        $productId = $body['product_id'] ?? '';
        $coOrderId = $body['co_order_id'] ?? '';
        $paxAutoIds = $body['pax_auto_ids'] ?? [];
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }
        if (empty($productId)) {
            throw new Exception('product_id is required', 400);
        }
        if (empty($paxAutoIds) || !is_array($paxAutoIds)) {
            throw new Exception('pax_auto_ids array is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\OrderManagementDAL($db);
        $service = new \App\Services\OrderManagementService($dal);
        
        $result = $service->divideOrder($orderId, $productId, $coOrderId, $paxAutoIds, $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'Order divided successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Update booking movement
$app->post('/v1/order-management/movement', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $orderId = $body['order_id'] ?? '';
        $productId = $body['product_id'] ?? '';
        $coOrderId = $body['co_order_id'] ?? '';
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        $movementData = [
            'new_product_id' => $body['new_product_id'] ?? null,
            'product_title' => $body['product_title'] ?? null,
            'trip_code' => $body['trip_code'] ?? null,
            'travel_date' => $body['travel_date'] ?? null,
            'pax' => isset($body['pax']) ? (int)$body['pax'] : null,
            'remarks' => $body['remarks'] ?? null,
            'pnr' => $body['pnr'] ?? null
        ];
        
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }
        if (empty($productId)) {
            throw new Exception('product_id is required', 400);
        }
        if (empty($coOrderId)) {
            $coOrderId = '';
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\OrderManagementDAL($db);
        $service = new \App\Services\OrderManagementService($dal);
        
        $result = $service->updateBookingMovement($orderId, $productId, $coOrderId, $movementData, $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'Booking movement updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Get booking with passenger details
$app->get('/v1/order-management/booking/{orderId}/passenger/{paxId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = $args['orderId'];
        $paxId = (int)$args['paxId'];
        
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }
        if ($paxId <= 0) {
            throw new Exception('pax_id is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\OrderManagementDAL($db);
        $service = new \App\Services\OrderManagementService($dal);
        
        $result = $service->getBookingWithPassenger($orderId, $paxId);
        
        return jsonResponse($response, $result, 'success', 'Booking with passenger details retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// YPSILON/AOBC MANAGEMENT ENDPOINTS
// ======================================

// Get Ypsilon/AOBC status
$app->get('/v1/ypsilon-aobc/status', function (Request $request, Response $response) use ($app) {
    try {
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonAobcDAL($db);
        $service = new \App\Services\YpsilonAobcService($dal);
        
        $result = $service->getStatus();
        
        return jsonResponse($response, $result, 'success', 'Ypsilon/AOBC status retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update Ypsilon/AOBC status
$app->post('/v1/ypsilon-aobc/status', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $value = $body['value'] ?? '';
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        if ($value === '') {
            throw new Exception('value is required (0, 1, 2, or 3)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonAobcDAL($db);
        $service = new \App\Services\YpsilonAobcService($dal);
        
        $result = $service->updateStatus($value, $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'Ypsilon/AOBC status updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// YPSILON IFRAME MANAGEMENT ENDPOINTS
// ======================================

// Get all Ypsilon iframe settings
$app->get('/v1/ypsilon-iframe/settings', function (Request $request, Response $response) use ($app) {
    try {
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonIframeDAL($db);
        $service = new \App\Services\YpsilonIframeService($dal);
        
        $result = $service->getAllSettings();
        
        return jsonResponse($response, $result, 'success', 'Ypsilon iframe settings retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get Ypsilon active status
$app->get('/v1/ypsilon-iframe/ypsilon-active', function (Request $request, Response $response) use ($app) {
    try {
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonIframeDAL($db);
        $service = new \App\Services\YpsilonIframeService($dal);
        
        $result = $service->getYpsilonActiveStatus();
        
        return jsonResponse($response, $result, 'success', 'Ypsilon active status retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update Ypsilon active status
$app->post('/v1/ypsilon-iframe/ypsilon-active', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $value = $body['value'] ?? '';
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        if ($value === '') {
            throw new Exception('value is required (0 or 1)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonIframeDAL($db);
        $service = new \App\Services\YpsilonIframeService($dal);
        
        $result = $service->updateYpsilonActiveStatus($value, $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'Ypsilon active status updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Get AOBC enabled for users
$app->get('/v1/ypsilon-iframe/aobc-enabled-for', function (Request $request, Response $response) use ($app) {
    try {
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonIframeDAL($db);
        $service = new \App\Services\YpsilonIframeService($dal);
        
        $result = $service->getAobcEnabledFor();
        
        return jsonResponse($response, $result, 'success', 'AOBC enabled for retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update AOBC enabled for users
$app->post('/v1/ypsilon-iframe/aobc-enabled-for', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $value = $body['value'] ?? '';
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        if ($value === '') {
            throw new Exception('value is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonIframeDAL($db);
        $service = new \App\Services\YpsilonIframeService($dal);
        
        $result = $service->updateAobcEnabledFor($value, $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'AOBC enabled for updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Get manage ticketing message
$app->get('/v1/ypsilon-iframe/manage-ticketing-message', function (Request $request, Response $response) use ($app) {
    try {
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonIframeDAL($db);
        $service = new \App\Services\YpsilonIframeService($dal);
        
        $result = $service->getManageTicketingMessage();
        
        return jsonResponse($response, $result, 'success', 'Manage ticketing message retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update manage ticketing message
$app->post('/v1/ypsilon-iframe/manage-ticketing-message', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $value = $body['value'] ?? '';
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        if ($value === '') {
            throw new Exception('value is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\YpsilonIframeDAL($db);
        $service = new \App\Services\YpsilonIframeService($dal);
        
        $result = $service->updateManageTicketingMessage($value, $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'Manage ticketing message updated successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// MONTHLY PRICE CALENDAR ENDPOINTS
// ======================================

// Get routes
$app->get('/v1/monthly-price-calendar/routes', function (Request $request, Response $response) use ($app) {
    try {
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarService($dal);
        
        $result = $service->getRoutes();
        
        return jsonResponse($response, $result, 'success', 'Routes retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get monthly price calendar data
$app->get('/v1/monthly-price-calendar/data', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $routeFrom = $queryParams['route_from'] ?? '';
        $routeTo = $queryParams['route_to'] ?? '';
        $month = $queryParams['month'] ?? '';
        
        if (empty($routeFrom)) {
            throw new Exception('route_from parameter is required', 400);
        }
        if (empty($routeTo)) {
            throw new Exception('route_to parameter is required', 400);
        }
        if (empty($month)) {
            throw new Exception('month parameter is required (format: YYYY-MM)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarService($dal);
        
        $result = $service->getMonthlyPriceData($routeFrom, $routeTo, $month);
        
        return jsonResponse($response, $result, 'success', 'Monthly price calendar data retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get airline codes
$app->get('/v1/monthly-price-calendar/airline-codes', function (Request $request, Response $response) use ($app) {
    try {
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarService($dal);
        
        $result = $service->getAirlineCodes();
        
        return jsonResponse($response, $result, 'success', 'Airline codes retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MONTHLY PRICE CALENDAR V2 ENDPOINTS (WITH RETURN ROUTE)
// ======================================

// Get routes (departures and destinations) - V2
$app->get('/v1/monthly-price-calendar-v2/routes', function (Request $request, Response $response) use ($app) {
    try {
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendar2DAL($db);
        $service = new \App\Services\MonthlyPriceCalendar2Service($dal);
        
        $result = $service->getRoutes();
        
        return jsonResponse($response, $result, 'success', 'Routes retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get monthly price calendar data with return route support
$app->get('/v1/monthly-price-calendar-v2/data', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $routeFrom = $queryParams['route_from'] ?? '';
        $routeTo = $queryParams['route_to'] ?? '';
        $outboundMonth = $queryParams['outbound_month'] ?? '';
        $returnMonth = $queryParams['return_month'] ?? '';
        
        if (empty($routeFrom)) {
            throw new Exception('route_from parameter is required', 400);
        }
        if (empty($routeTo)) {
            throw new Exception('route_to parameter is required', 400);
        }
        if (empty($outboundMonth)) {
            throw new Exception('outbound_month parameter is required (format: YYYY-MM)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendar2DAL($db);
        $service = new \App\Services\MonthlyPriceCalendar2Service($dal);
        
        $result = $service->getMonthlyPriceDataWithReturn($routeFrom, $routeTo, $outboundMonth, $returnMonth);
        
        return jsonResponse($response, $result, 'success', 'Monthly price calendar data with return route retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MONTHLY PRICE CALENDAR BACKEND ENDPOINTS
// ======================================

// Get routes by airline code
$app->get('/v1/monthly-price-calendar-backend/routes-by-airline', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $airlineCode = $queryParams['airline'] ?? '';
        
        if (empty($airlineCode)) {
            throw new Exception('airline parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarBackendDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarBackendService($dal);
        
        $result = $service->getRoutesByAirline($airlineCode);
        
        return jsonResponse($response, $result, 'success', 'Routes by airline retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get sale prices by route
$app->get('/v1/monthly-price-calendar-backend/sale-prices', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $route = $queryParams['route'] ?? '';
        
        if (empty($route)) {
            throw new Exception('route parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarBackendDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarBackendService($dal);
        
        $result = $service->getSalePricesByRoute($route);
        
        return jsonResponse($response, $result, 'success', 'Sale prices by route retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get detailed monthly calendar data with fares and seat availability
$app->get('/v1/monthly-price-calendar-backend/detailed-calendar', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $route = $queryParams['route'] ?? '';
        $month = $queryParams['month'] ?? '';
        $airlineCode = $queryParams['airline'] ?? '';
        
        if (empty($route)) {
            throw new Exception('route parameter is required', 400);
        }
        if (empty($month)) {
            throw new Exception('month parameter is required (format: YYYY-MM)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarBackendDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarBackendService($dal);
        
        $result = $service->getDetailedCalendarData($route, $month, $airlineCode);
        
        return jsonResponse($response, $result, 'success', 'Detailed calendar data retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MONTHLY PRICE CALENDAR BACKEND (SIMPLE) ENDPOINTS
// ======================================

// Get routes by airline code (simple version)
$app->get('/v1/monthly-price-calendar-backend-simple/routes-by-airline', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $airlineCode = $queryParams['airline'] ?? '';
        
        if (empty($airlineCode)) {
            throw new Exception('airline parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarBackendDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarBackendService($dal);
        
        $result = $service->getRoutesByAirline($airlineCode);
        
        return jsonResponse($response, $result, 'success', 'Routes by airline retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get sale prices by route (simple version)
$app->get('/v1/monthly-price-calendar-backend-simple/sale-prices', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $route = $queryParams['route'] ?? '';
        
        if (empty($route)) {
            throw new Exception('route parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarBackendDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarBackendService($dal);
        
        $result = $service->getSalePricesByRoute($route);
        
        return jsonResponse($response, $result, 'success', 'Sale prices by route retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get simple monthly calendar data (without return route)
$app->get('/v1/monthly-price-calendar-backend-simple/calendar', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $route = $queryParams['route'] ?? '';
        $month = $queryParams['month'] ?? '';
        $airlineCode = $queryParams['airline'] ?? '';
        
        if (empty($route)) {
            throw new Exception('route parameter is required', 400);
        }
        if (empty($month)) {
            throw new Exception('month parameter is required (format: YYYY-MM)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\MonthlyPriceCalendarBackendDAL($db);
        $service = new \App\Services\MonthlyPriceCalendarBackendService($dal);
        
        $result = $service->getDetailedCalendarData($route, $month, $airlineCode);
        
        return jsonResponse($response, $result, 'success', 'Monthly calendar data retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// NAME REPLACEMENT REQUEST ENDPOINTS
// ======================================

// Get datechange name replacement candidates
$app->get('/v1/name-replacement-request/datechange-candidates', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50;
        
        if ($limit < 1 || $limit > 500) {
            $limit = 50;
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NameReplacementRequestDAL($db);
        $service = new \App\Services\NameReplacementRequestService($dal);
        
        $result = $service->getDatechangeCandidates($limit);
        
        return jsonResponse($response, $result, 'success', 'Datechange name replacement candidates retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get refund name replacement candidates
$app->get('/v1/name-replacement-request/refund-candidates', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50;
        
        if ($limit < 1 || $limit > 500) {
            $limit = 50;
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NameReplacementRequestDAL($db);
        $service = new \App\Services\NameReplacementRequestService($dal);
        
        $result = $service->getRefundCandidates($limit);
        
        return jsonResponse($response, $result, 'success', 'Refund name replacement candidates retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Process datechange name replacement (with optional auto-update)
$app->post('/v1/name-replacement-request/process-datechange', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $limit = isset($body['limit']) ? (int)$body['limit'] : 50;
        $autoUpdate = isset($body['auto_update']) && $body['auto_update'] === true;
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        if ($limit < 1 || $limit > 500) {
            $limit = 50;
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NameReplacementRequestDAL($db);
        $service = new \App\Services\NameReplacementRequestService($dal);
        
        $result = $service->processDatechangeNameReplacement($limit, $autoUpdate);
        
        return jsonResponse($response, $result, 'success', 'Datechange name replacement processed');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Process refund name replacement (with optional auto-update)
$app->post('/v1/name-replacement-request/process-refund', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $limit = isset($body['limit']) ? (int)$body['limit'] : 50;
        $autoUpdate = isset($body['auto_update']) && $body['auto_update'] === true;
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        if ($limit < 1 || $limit > 500) {
            $limit = 50;
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NameReplacementRequestDAL($db);
        $service = new \App\Services\NameReplacementRequestService($dal);
        
        $result = $service->processRefundNameReplacement($limit, $autoUpdate);
        
        return jsonResponse($response, $result, 'success', 'Refund name replacement processed');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Update pax status to name replacement request
$app->post('/v1/name-replacement-request/update-pax-status', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $orderId = $body['order_id'] ?? '';
        $productId = $body['product_id'] ?? '';
        $updatedBy = $body['updated_by'] ?? 'api_user';
        
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }
        if (empty($productId)) {
            throw new Exception('product_id is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NameReplacementRequestDAL($db);
        $service = new \App\Services\NameReplacementRequestService($dal);
        
        $result = $service->updatePaxStatusToNameReplacement($orderId, $productId, $updatedBy);
        
        return jsonResponse($response, $result, 'success', 'Pax status updated to name replacement request');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// NOBEL EOD SALE RECORDS ENDPOINTS
// ======================================

// Get EOD sale booking data (without inserting)
$app->get('/v1/nobel-eod-sale-records/booking-data', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $targetDate = $queryParams['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelEodSaleRecordsDAL($db);
        $service = new \App\Services\NobelEodSaleRecordsService($dal);
        
        $result = $service->getEodSaleBookingData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'EOD sale booking data retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get EOD sale call data (without inserting)
$app->get('/v1/nobel-eod-sale-records/call-data', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $targetDate = $queryParams['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelEodSaleRecordsDAL($db);
        $service = new \App\Services\NobelEodSaleRecordsService($dal);
        
        $result = $service->getEodSaleCallData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'EOD sale call data retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Process and insert EOD sale booking records
$app->post('/v1/nobel-eod-sale-records/process-booking', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelEodSaleRecordsDAL($db);
        $service = new \App\Services\NobelEodSaleRecordsService($dal);
        
        $result = $service->processEodSaleBookingRecords($targetDate);
        
        return jsonResponse($response, $result, 'success', 'EOD sale booking records processed');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Process and insert EOD sale call records
$app->post('/v1/nobel-eod-sale-records/process-call', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelEodSaleRecordsDAL($db);
        $service = new \App\Services\NobelEodSaleRecordsService($dal);
        
        $result = $service->processEodSaleCallRecords($targetDate);
        
        return jsonResponse($response, $result, 'success', 'EOD sale call records processed');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Process both EOD sale booking and call records
$app->post('/v1/nobel-eod-sale-records/process-all', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelEodSaleRecordsDAL($db);
        $service = new \App\Services\NobelEodSaleRecordsService($dal);
        
        $result = $service->processAllEodSaleRecords($targetDate);
        
        return jsonResponse($response, $result, 'success', 'All EOD sale records processed');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// NOBEL POSTGRESQL CAPTURE ENDPOINTS
// ======================================

// Capture cust_ob_inb_hst data from PostgreSQL
$app->post('/v1/nobel-postgres-capture/cust-ob-inb-hst', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresCaptureDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCaptureService($dal);
        
        $result = $service->captureCustObInbHst($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Cust ob inb hst data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture call rec data from PostgreSQL
$app->post('/v1/nobel-postgres-capture/call-rec', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        $realtime = isset($body['realtime']) && $body['realtime'] === true;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresCaptureDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCaptureService($dal);
        
        $result = $service->captureCallRec($targetDate, $realtime);
        
        return jsonResponse($response, $result, 'success', 'Call rec data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture inboundcall rec data from PostgreSQL
$app->post('/v1/nobel-postgres-capture/inboundcall-rec', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        $realtime = isset($body['realtime']) && $body['realtime'] === true;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresCaptureDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCaptureService($dal);
        
        $result = $service->captureInboundcallRec($targetDate, $realtime);
        
        return jsonResponse($response, $result, 'success', 'Inboundcall rec data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture tsktsrday data from PostgreSQL
$app->post('/v1/nobel-postgres-capture/tsktsrday', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelPostgresCaptureDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCaptureService($dal);
        
        $result = $service->captureTsktsrday();
        
        return jsonResponse($response, $result, 'success', 'Tsktsrday data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture tskpauday data from PostgreSQL
$app->post('/v1/nobel-postgres-capture/tskpauday', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelPostgresCaptureDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCaptureService($dal);
        
        $result = $service->captureTskpauday();
        
        return jsonResponse($response, $result, 'success', 'Tskpauday data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Update call rec realtime status
$app->post('/v1/nobel-postgres-capture/update-call-rec-status', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresCaptureDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCaptureService($dal);
        
        $result = $service->updateCallRecRealtimeStatus($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Call rec status updated');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture all realtime data
$app->post('/v1/nobel-postgres-capture/realtime-all', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresCaptureDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCaptureService($dal);
        
        $result = $service->captureAllRealtime($targetDate);
        
        return jsonResponse($response, $result, 'success', 'All realtime data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture all data (comprehensive)
$app->post('/v1/nobel-postgres-capture/all', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresCaptureDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCaptureService($dal);
        
        $result = $service->captureAll($targetDate);
        
        return jsonResponse($response, $result, 'success', 'All data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// NOBEL POSTGRESQL CALL TASK ENDPOINTS
// ======================================

// Capture addistats data from PostgreSQL
$app->post('/v1/nobel-postgres-call-task/addistats', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $limit = isset($body['limit']) ? (int)$body['limit'] : 30;
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        
        if ($limit < 1 || $limit > 100) {
            $limit = 30;
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelPostgresCallTaskDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCallTaskService($dal);
        
        $result = $service->captureAddistats($limit);
        
        return jsonResponse($response, $result, 'success', 'Addistats data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture appl_status data from PostgreSQL
$app->post('/v1/nobel-postgres-call-task/appl-status', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $limit = isset($body['limit']) ? (int)$body['limit'] : 30;
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        
        if ($limit < 1 || $limit > 100) {
            $limit = 30;
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelPostgresCallTaskDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCallTaskService($dal);
        
        $result = $service->captureApplStatus($limit);
        
        return jsonResponse($response, $result, 'success', 'Appl status data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture call_history data from PostgreSQL
$app->post('/v1/nobel-postgres-call-task/call-history', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $limit = isset($body['limit']) ? (int)$body['limit'] : 10;
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        
        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelPostgresCallTaskDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresCallTaskService($dal);
        
        $result = $service->captureCallHistory($limit);
        
        return jsonResponse($response, $result, 'success', 'Call history data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// NOBEL POSTGRESQL QUEST (QA EVALUATION) ENDPOINTS
// ======================================

// Capture QA evaluation quest data from PostgreSQL
$app->post('/v1/nobel-postgres-quest/qa-eval-quest', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresQuestDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresQuestService($dal);
        
        $result = $service->captureQaEvalQuest($targetDate);
        
        return jsonResponse($response, $result, 'success', 'QA evaluation quest data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture QA evaluation notes data from PostgreSQL
$app->post('/v1/nobel-postgres-quest/qa-eval-notes', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresQuestDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresQuestService($dal);
        
        $result = $service->captureQaEvalNotes($targetDate);
        
        return jsonResponse($response, $result, 'success', 'QA evaluation notes data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture QA evaluation data from PostgreSQL
$app->post('/v1/nobel-postgres-quest/qa-eval', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresQuestDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresQuestService($dal);
        
        $result = $service->captureQaEval($targetDate);
        
        return jsonResponse($response, $result, 'success', 'QA evaluation data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// SKYPAY PAYMENTS MANAGEMENT
// ======================================

// Get SkyPay callbacks with optional filters
$app->get('/v1/skypay-payments/callbacks', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $orderId = $queryParams['order_id'] ?? null;
        $date = $queryParams['date'] ?? null;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100;
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\SkyPayPaymentsDAL($db);
        $service = new \App\Services\SkyPayPaymentsService($dal);
        
        $result = $service->getSkyPayCallbacks($orderId, $date, $limit);
        
        return jsonResponse($response, $result, 'success', 'SkyPay callbacks retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get booking payment details for an order
$app->get('/v1/skypay-payments/booking/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = $args['orderId'] ?? null;
        
        if (!$orderId) {
            throw new Exception('orderId parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\SkyPayPaymentsDAL($db);
        $service = new \App\Services\SkyPayPaymentsService($dal);
        
        $result = $service->getBookingPaymentDetails($orderId);
        
        return jsonResponse($response, $result, 'success', 'Booking payment details retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// NOBEL POSTGRES REALTIME CALL DATA CAPTURE
// ======================================

// Capture call log master data from PostgreSQL
$app->post('/v1/nobel-postgres-realtime-call/call-log-master', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresRealtimeCallDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresRealtimeCallService($dal);
        
        $result = $service->captureCallLogMaster($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Call log master data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture call log sequence data from PostgreSQL
$app->post('/v1/nobel-postgres-realtime-call/call-log-sequence', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresRealtimeCallDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresRealtimeCallService($dal);
        
        $result = $service->captureCallLogSequence($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Call log sequence data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Capture callback data from PostgreSQL
$app->post('/v1/nobel-postgres-realtime-call/callback', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresRealtimeCallDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresRealtimeCallService($dal);
        
        $result = $service->captureCallback($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Callback data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// NOBEL POSTGRES REALTIME CALL DATA VIEWONLY (NO INSERT)
// ======================================

// Get call log master data (viewonly - no insert)
$app->get('/v1/nobel-postgres-realtime-call-viewonly/call-log-master', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $targetDate = $queryParams['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $queryParams['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresRealtimeCallViewonlyDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresRealtimeCallViewonlyService($dal);
        
        $result = $service->getCallLogMasterData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Call log master data retrieved (viewonly)');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get call log sequence data (viewonly - no insert)
$app->get('/v1/nobel-postgres-realtime-call-viewonly/call-log-sequence', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $targetDate = $queryParams['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $queryParams['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresRealtimeCallViewonlyDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresRealtimeCallViewonlyService($dal);
        
        $result = $service->getCallLogSequenceData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Call log sequence data retrieved (viewonly)');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get callback data (viewonly - no insert)
$app->get('/v1/nobel-postgres-realtime-call-viewonly/callback', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $targetDate = $queryParams['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $queryParams['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresRealtimeCallViewonlyDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresRealtimeCallViewonlyService($dal);
        
        $result = $service->getCallbackData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Callback data retrieved (viewonly)');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get call history data (viewonly - no insert)
$app->get('/v1/nobel-postgres-realtime-call-viewonly/call-history', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $targetDate = $queryParams['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $queryParams['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresRealtimeCallViewonlyDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresRealtimeCallViewonlyService($dal);
        
        $result = $service->getCallHistoryData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Call history data retrieved (viewonly)');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// NOBEL POSTGRES TASK DATA CAPTURE
// ======================================

// Capture tsktsrday data from PostgreSQL
$app->post('/v1/nobel-postgres-task/tsktsrday', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\NobelPostgresTaskDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresTaskService($dal);
        
        $result = $service->captureTsktsrday();
        
        return jsonResponse($response, $result, 'success', 'Tsktsrday data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// NOBEL POSTGRES TASKSRDAY DATA CAPTURE
// ======================================

// Capture tsktsrday data from PostgreSQL
$app->post('/v1/nobel-postgres-tasksrday/tsktsrday', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresTasksrdayDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresTasksrdayService($dal);
        
        $result = $service->captureTsktsrday($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Tsktsrday data captured');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// Get tsktsrday data (viewonly - no insert)
$app->get('/v1/nobel-postgres-tasksrday/tsktsrday', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $targetDate = $queryParams['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $queryParams['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresTasksrdayDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresTasksrdayService($dal);
        
        $result = $service->getTsktsrdayData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Tsktsrday data retrieved (viewonly)');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// NOBEL POSTGRES VIEWONLY DATA RETRIEVAL
// ======================================

// Get cust_ob_inb_hst data (viewonly - no insert)
$app->get('/v1/nobel-postgres-viewonly/cust-ob-inb-hst', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $targetDate = $queryParams['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $targetDate)) {
            throw new Exception('target_date must be in DD/MM/YYYY format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $queryParams['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresViewonlyDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresViewonlyService($dal);
        
        $result = $service->getCustObInbHstData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Cust ob inb hst data retrieved (viewonly)');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// NOBEL POSTGRES INBOUND CALL DATA CAPTURE
// ======================================

// Capture inboundcall data from PostgreSQL
$app->post('/v1/nobel-postgres-inbound-call/inboundcall-quote', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $targetDate = $body['target_date'] ?? null;
        
        if ($targetDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new Exception('target_date must be in YYYY-MM-DD format', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $pgConnectionString = $body['pg_connection_string'] ?? null;
        $dal = new \App\DAL\NobelPostgresInboundCallDAL($db, $pgConnectionString);
        $service = new \App\Services\NobelPostgresInboundCallService($dal);
        
        $result = $service->captureInboundcallData($targetDate);
        
        return jsonResponse($response, $result, 'success', 'Inbound call data captured successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        $errorResponse = errorResponse($response, $e->getMessage(), $code);
        return $errorResponse
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
});

// ======================================
// EMAIL MANAGER
// ======================================

// Get orders with email status
$app->get('/v1/email-manager/orders', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $orderId = $queryParams['order_id'] ?? null;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100;
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\EmailManagerDAL($db);
        $service = new \App\Services\EmailManagerService($dal);
        
        $result = $service->getOrdersWithEmailStatus($orderId, $limit);
        
        return jsonResponse($response, $result, 'success', 'Orders with email status retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get email status for a specific order
$app->get('/v1/email-manager/order/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = $args['orderId'] ?? null;
        
        if (!$orderId) {
            throw new Exception('orderId parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\EmailManagerDAL($db);
        $service = new \App\Services\EmailManagerService($dal);
        
        $result = $service->getOrderEmailStatus($orderId);
        
        return jsonResponse($response, $result, 'success', 'Order email status retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Record email history
$app->post('/v1/email-manager/email-history', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $orderId = $body['order_id'] ?? null;
        $emailType = $body['email_type'] ?? null;
        $emailAddress = $body['email_address'] ?? null;
        $emailSubject = $body['email_subject'] ?? null;
        $initiatedBy = $body['initiated_by'] ?? 'api';
        
        if (!$orderId) {
            throw new Exception('order_id is required', 400);
        }
        if (!$emailType) {
            throw new Exception('email_type is required', 400);
        }
        if (!$emailSubject) {
            throw new Exception('email_subject is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\EmailManagerDAL($db);
        $service = new \App\Services\EmailManagerService($dal);
        
        $result = $service->recordEmailHistory($orderId, $emailType, $emailAddress, $emailSubject, $initiatedBy);
        
        return jsonResponse($response, $result, 'success', 'Email history recorded', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOM ITINERARY MANAGER (STAFF PORTAL)
// ======================================

// Get requests with filters
$app->get('/v1/custom-itinerary/requests', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        
        $filters = [
            'case_type' => $queryParams['case_type'] ?? null,
            'user_id' => $queryParams['user_id'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'case_id' => $queryParams['case_id'] ?? null,
            'reservation_ref' => $queryParams['reservation_ref'] ?? null,
            'limit' => isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100
        ];
        
        // Remove null values
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\CustomItineraryDAL($db);
        $service = new \App\Services\CustomItineraryService($dal);
        
        $result = $service->getRequests($filters);
        
        return jsonResponse($response, $result, 'success', 'Requests retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get request by case_id
$app->get('/v1/custom-itinerary/request/{caseId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $caseId = $args['caseId'] ?? null;
        
        if (!$caseId) {
            throw new Exception('caseId parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\CustomItineraryDAL($db);
        $service = new \App\Services\CustomItineraryService($dal);
        
        $result = $service->getRequestByCaseId($caseId);
        
        return jsonResponse($response, $result, 'success', 'Request retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Create new request
$app->post('/v1/custom-itinerary/request', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        
        $caseType = $body['case_type'] ?? null;
        $reservationRef = $body['reservation_ref'] ?? null;
        $userId = $body['user_id'] ?? null;
        $information = $body['information'] ?? null;
        $priority = $body['priority'] ?? 'P4';
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\CustomItineraryDAL($db);
        $service = new \App\Services\CustomItineraryService($dal);
        
        $result = $service->createRequest($caseType, $reservationRef, $userId, $information, $priority);
        
        return jsonResponse($response, $result, 'success', 'Request created successfully', 201);
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// EMAIL REMINDER
// ======================================

// Get bookings due in X days
$app->get('/v1/email-reminder/bookings', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $days = isset($queryParams['days']) ? (int)$queryParams['days'] : null;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : null;
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\EmailReminderDAL($db);
        $service = new \App\Services\EmailReminderService($dal);
        
        if ($days !== null) {
            $result = $service->getBookingsDueInDays($days, $limit);
        } else {
            $result = $service->getAllUpcomingReminders();
        }
        
        return jsonResponse($response, $result, 'success', 'Bookings retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Process reminders for specific days
$app->post('/v1/email-reminder/process', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $days = isset($body['days']) ? (int)$body['days'] : null;
        $insertHistory = isset($body['insert_history']) ? (bool)$body['insert_history'] : true;
        
        if ($days === null) {
            throw new Exception('days parameter is required (must be 1, 4, or 7)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\EmailReminderDAL($db);
        $service = new \App\Services\EmailReminderService($dal);
        
        $result = $service->processRemindersForDays($days, $insertHistory);
        
        return jsonResponse($response, $result, 'success', 'Reminders processed successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Process all reminders (7, 4, 1 days)
$app->post('/v1/email-reminder/process-all', function (Request $request, Response $response) use ($app) {
    try {
        $body = $request->getParsedBody();
        $insertHistory = isset($body['insert_history']) ? (bool)$body['insert_history'] : true;
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\EmailReminderDAL($db);
        $service = new \App\Services\EmailReminderService($dal);
        
        $result = $service->processAllReminders($insertHistory);
        
        return jsonResponse($response, $result, 'success', 'All reminders processed successfully');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// BACKEND FUNCTIONS
// ======================================

// Get incentive dates for a month
$app->get('/v1/backend-functions/incentive-dates', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $month = $queryParams['month'] ?? null;
        
        if (!$month) {
            throw new Exception('month parameter is required (format: YYYY-MM)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\BackendFunctionsDAL($db);
        $service = new \App\Services\BackendFunctionsService($dal);
        
        $result = $service->getIncentiveDatesForMonth($month);
        
        return jsonResponse($response, $result, 'success', 'Incentive dates retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get product info by tripcode and date
$app->get('/v1/backend-functions/product-info', function (Request $request, Response $response) use ($app) {
    try {
        $queryParams = $request->getQueryParams();
        $tripcode = $queryParams['tripcode'] ?? null;
        $date = $queryParams['date'] ?? null;
        
        if (!$tripcode) {
            throw new Exception('tripcode parameter is required', 400);
        }
        if (!$date) {
            throw new Exception('date parameter is required (format: YYYY-MM-DD)', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\BackendFunctionsDAL($db);
        $service = new \App\Services\BackendFunctionsService($dal);
        
        $result = $service->getProductInfoByTripcodeAndDate($tripcode, $date);
        
        return jsonResponse($response, $result, 'success', 'Product info retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get paid amount for adjustment (G360 version)
$app->get('/v1/backend-functions/paid-amount/{orderId}', function (Request $request, Response $response, array $args) use ($app) {
    try {
        $orderId = $args['orderId'] ?? null;
        $queryParams = $request->getQueryParams();
        $type = $queryParams['type'] ?? 'g360'; // g360, simple, deadline
        
        if (!$orderId) {
            throw new Exception('orderId parameter is required', 400);
        }
        
        $container = $app->getContainer();
        $db = $container->get('db');
        $dal = new \App\DAL\BackendFunctionsDAL($db);
        $service = new \App\Services\BackendFunctionsService($dal);
        
        if ($type === 'g360') {
            $result = $service->getPaidAmountForAdjustmentG360($orderId);
        } elseif ($type === 'deadline') {
            $amount = $service->getPaidAmountForAdjustmentWithDeadline($orderId);
            $result = ['total_paid' => $amount];
        } else {
            $amount = $service->getPaidAmountForAdjustment($orderId);
            $result = ['total_paid' => $amount];
        }
        
        return jsonResponse($response, $result, 'success', 'Paid amount retrieved');
        
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AUTHENTICATION ENDPOINTS
// ======================================

// Helper function to ensure AuthService is loaded
if (!function_exists('ensureAuthService')) {
    function ensureAuthService() {
        if (!class_exists('App\Services\AuthService')) {
            $serviceFile = __DIR__ . '/../src/Services/AuthService.php';
            if (file_exists($serviceFile)) {
                require_once $serviceFile;
                error_log("Manually loaded AuthService from: $serviceFile");
            } else {
                throw new Exception("AuthService class file not found at: $serviceFile", 500);
            }
        }
    }
}

// Get redirect URL
$app->get('/v1/auth/redirect', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $redirectParam = $queryParams['redirect'] ?? null;
        
        // Base URL from original code
        $baseUrl = 'https://gauratravel.com.au/';
        
        if (!empty($redirectParam)) {
            // Decode the redirect URL
            $redirectUrl = urldecode($redirectParam);
            $fullUrl = $baseUrl . $redirectUrl;
        } else {
            // Default to homepage
            $fullUrl = $baseUrl;
        }
        
        return jsonResponse($response, [
            'redirect_url' => $fullUrl
        ], 'success', 'Redirect URL generated');
        
    } catch (\Throwable $e) {
        error_log("Exception in auth redirect: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Login endpoint
$app->post('/v1/auth/login', function (Request $request, Response $response) {
    try {
        ensureAuthService();
        
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        $username = $input['username'] ?? $input['email'] ?? null;
        $password = $input['password'] ?? null;
        
        if (empty($username)) {
            return errorResponse($response, 'Username or email is required', 400);
        }
        
        if (empty($password)) {
            return errorResponse($response, 'Password is required', 400);
        }
        
        $service = new \App\Services\AuthService();
        $result = $service->login($username, $password);
        
        return jsonResponse($response, $result, 'success', 'Login successful');
        
    } catch (\Throwable $e) {
        error_log("Exception in login: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INCENTIVE CRONJOB ENDPOINTS
// ======================================

// Helper function to ensure IncentiveCronjobService is loaded
if (!function_exists('ensureIncentiveCronjobService')) {
    function ensureIncentiveCronjobService() {
        if (!class_exists('App\Services\IncentiveCronjobService')) {
            $serviceFile = __DIR__ . '/../src/Services/IncentiveCronjobService.php';
            if (file_exists($serviceFile)) {
                require_once $serviceFile;
                error_log("Manually loaded IncentiveCronjobService from: $serviceFile");
            } else {
                throw new Exception("IncentiveCronjobService class file not found at: $serviceFile", 500);
            }
        }
    }
}

// Calculate incentive data
$app->post('/v1/incentives/calculate', function (Request $request, Response $response) {
    try {
        ensureIncentiveCronjobService();
        
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        $date = $input['date'] ?? null;
        $teamName = $input['team_name'] ?? null;
        $incentiveTitle = $input['incentive_title'] ?? null;
        
        if (empty($date)) {
            return errorResponse($response, 'Date is required', 400);
        }
        
        $service = new \App\Services\IncentiveCronjobService();
        $result = $service->calculateIncentiveData($date, $teamName, $incentiveTitle);
        
        return jsonResponse($response, $result, 'success', 'Incentive data calculated and stored');
        
    } catch (\Throwable $e) {
        error_log("Exception in calculate incentive data: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent performance data
$app->get('/v1/incentives/agent-performance', function (Request $request, Response $response) {
    try {
        ensureIncentiveCronjobService();
        
        $queryParams = $request->getQueryParams();
        $date = $queryParams['date'] ?? null;
        $teamName = $queryParams['team_name'] ?? null;
        $agentName = $queryParams['agent_name'] ?? null;
        
        if (empty($date)) {
            return errorResponse($response, 'Date is required', 400);
        }
        
        $service = new \App\Services\IncentiveCronjobService();
        $result = $service->getAgentPerformance($date, $teamName);
        
        // Filter by agent name if provided
        if (!empty($agentName)) {
            $result['agents'] = array_filter($result['agents'], function($agent) use ($agentName) {
                return $agent['agent_name'] === $agentName;
            });
            $result['agents'] = array_values($result['agents']);
        }
        
        return jsonResponse($response, $result, 'success', 'Agent performance data retrieved');
        
    } catch (\Throwable $e) {
        error_log("Exception in get agent performance: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get incentive conditions
$app->get('/v1/incentives/conditions', function (Request $request, Response $response) {
    try {
        ensureIncentiveCronjobService();
        
        $queryParams = $request->getQueryParams();
        $date = $queryParams['date'] ?? null;
        $type = $queryParams['type'] ?? null;
        $incentiveTitle = $queryParams['incentive_title'] ?? null;
        
        $service = new \App\Services\IncentiveCronjobService();
        $result = $service->getIncentiveConditions($date, $type, $incentiveTitle);
        
        return jsonResponse($response, $result, 'success', 'Incentive conditions retrieved');
        
    } catch (\Throwable $e) {
        error_log("Exception in get incentive conditions: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get calculated incentive data
$app->get('/v1/incentives/data', function (Request $request, Response $response) {
    try {
        ensureIncentiveCronjobService();
        
        $queryParams = $request->getQueryParams();
        $filters = [];
        
        if (!empty($queryParams['date'])) {
            $filters['date'] = $queryParams['date'];
        }
        
        if (!empty($queryParams['agent_name'])) {
            $filters['agent_name'] = $queryParams['agent_name'];
        }
        
        if (!empty($queryParams['incentive_title'])) {
            $filters['incentive_title'] = $queryParams['incentive_title'];
        }
        
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100;
        $offset = isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0;
        
        $service = new \App\Services\IncentiveCronjobService();
        $result = $service->getIncentiveData($filters, $limit, $offset);
        
        return jsonResponse($response, $result, 'success', 'Incentive data retrieved');
        
    } catch (\Throwable $e) {
        error_log("Exception in get incentive data: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INTERNAL SITE ENDPOINTS
// ======================================

// Helper function to ensure InternalSiteService is loaded
if (!function_exists('ensureInternalSiteService')) {
    function ensureInternalSiteService() {
        if (!class_exists('App\Services\InternalSiteService')) {
            $serviceFile = __DIR__ . '/../src/Services/InternalSiteService.php';
            if (file_exists($serviceFile)) {
                require_once $serviceFile;
                error_log("Manually loaded InternalSiteService from: $serviceFile");
            } else {
                throw new Exception("InternalSiteService class file not found at: $serviceFile", 500);
            }
        }
    }
}

// Get trip itinerary
$app->get('/v1/internal/trips/{trip_id}/itinerary', function (Request $request, Response $response, $args) {
    try {
        ensureInternalSiteService();
        
        $tripId = $args['trip_id'] ?? null;
        
        if (empty($tripId)) {
            return errorResponse($response, 'Trip ID is required', 400);
        }
        
        $service = new \App\Services\InternalSiteService();
        $result = $service->getTripItinerary($tripId);
        
        return jsonResponse($response, $result, 'success', 'Trip itinerary retrieved');
        
    } catch (\Throwable $e) {
        error_log("Exception in get trip itinerary: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ISSUE FLAG DASHBOARD ENDPOINTS
// ======================================

// Helper function to ensure IssueFlagDashboardService is loaded
if (!function_exists('ensureIssueFlagDashboardService')) {
    function ensureIssueFlagDashboardService() {
        if (!class_exists('App\Services\IssueFlagDashboardService')) {
            $serviceFile = __DIR__ . '/../src/Services/IssueFlagDashboardService.php';
            if (file_exists($serviceFile)) {
                require_once $serviceFile;
                error_log("Manually loaded IssueFlagDashboardService from: $serviceFile");
            } else {
                throw new Exception("IssueFlagDashboardService class file not found at: $serviceFile", 500);
            }
        }
    }
}

// Get issue flag dashboard data
$app->get('/v1/issues/flag-dashboard', function (Request $request, Response $response) {
    try {
        // Increase execution time limit for this endpoint
        set_time_limit(120); // 2 minutes
        ini_set('max_execution_time', 120);
        
        ensureIssueFlagDashboardService();
        
        $queryParams = $request->getQueryParams();
        $filters = [];
        
        if (!empty($queryParams['order_id'])) {
            $filters['order_id'] = $queryParams['order_id'];
        }
        
        if (!empty($queryParams['order_type'])) {
            $filters['order_type'] = $queryParams['order_type'];
        }
        
        if (!empty($queryParams['category'])) {
            $filters['category'] = $queryParams['category'];
        }
        
        if (!empty($queryParams['priority'])) {
            $filters['priority'] = $queryParams['priority'];
        }
        
        $filters['limit'] = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100;
        $filters['offset'] = isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0;
        
        // Log start time for performance monitoring
        $startTime = microtime(true);
        error_log("Issue Flag Dashboard API: Starting request at " . date('Y-m-d H:i:s'));
        
        $service = new \App\Services\IssueFlagDashboardService();
        $result = $service->getIssues($filters);
        
        $executionTime = round(microtime(true) - $startTime, 2);
        error_log("Issue Flag Dashboard API: Completed in {$executionTime} seconds");
        
        return jsonResponse($response, $result, 'success', 'Issue flag dashboard data retrieved');
        
    } catch (\Throwable $e) {
        error_log("Exception in get issue flag dashboard: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get issue statistics
$app->get('/v1/issues/statistics', function (Request $request, Response $response) {
    try {
        ensureIssueFlagDashboardService();
        
        $queryParams = $request->getQueryParams();
        $filters = [];
        
        if (!empty($queryParams['order_id'])) {
            $filters['order_id'] = $queryParams['order_id'];
        }
        
        if (!empty($queryParams['order_type'])) {
            $filters['order_type'] = $queryParams['order_type'];
        }
        
        if (!empty($queryParams['category'])) {
            $filters['category'] = $queryParams['category'];
        }
        
        if (!empty($queryParams['priority'])) {
            $filters['priority'] = $queryParams['priority'];
        }
        
        $service = new \App\Services\IssueFlagDashboardService();
        $statistics = $service->calculateStatistics(null, $filters);
        
        return jsonResponse($response, $statistics, 'success', 'Issue statistics retrieved');
        
    } catch (\Throwable $e) {
        error_log("Exception in get issue statistics: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Send email report
$app->post('/v1/issues/send-email-report', function (Request $request, Response $response) {
    try {
        ensureIssueFlagDashboardService();
        
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        $filters = $input['filters'] ?? [];
        $recipientEmail = $input['recipient_email'] ?? null;
        
        $service = new \App\Services\IssueFlagDashboardService();
        $result = $service->sendEmailReport($filters, $recipientEmail);
        
        return jsonResponse($response, $result, 'success', 'Email report sent successfully');
        
    } catch (\Throwable $e) {
        error_log("Exception in send email report: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// LANDING PAGE ENDPOINTS
// ======================================

// Helper function to ensure LandingPageService is loaded
if (!function_exists('ensureLandingPageService')) {
    function ensureLandingPageService() {
        if (!class_exists('App\Services\LandingPageService')) {
            $serviceFile = __DIR__ . '/../src/Services/LandingPageService.php';
            if (file_exists($serviceFile)) {
                require_once $serviceFile;
                error_log("Manually loaded LandingPageService from: $serviceFile");
            } else {
                throw new Exception("LandingPageService class file not found at: $serviceFile", 500);
            }
        }
    }
}

// Get landing page configuration
$app->get('/v1/landing-pages/flights-december', function (Request $request, Response $response) {
    try {
        ensureLandingPageService();
        
        $queryParams = $request->getQueryParams();
        $pageSlugOrId = $queryParams['page'] ?? 'flights-december';
        
        $service = new \App\Services\LandingPageService();
        $result = $service->getLandingPageConfig($pageSlugOrId);
        
        return jsonResponse($response, $result, 'success', 'Landing page configuration retrieved');
        
    } catch (\Throwable $e) {
        error_log("Exception in get landing page config: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT ENDPOINTS
// ======================================

$app->get('/v1/agents/search', function (Request $request, Response $response) {
    try {
        $term = $request->getQueryParams()['term'] ?? '';
        
        if (empty($term)) {
            return jsonResponse($response, [], 'success', 'No search term provided');
        }
        
        $service = new \App\Services\AgentService();
        $results = $service->searchAgents($term);
        
        return jsonResponse($response, $results, 'success', 'Agents retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get team performance (supports optional team query parameter)
$app->get('/v1/agents/teams/performance', function (Request $request, Response $response) {
    try {
        $team = $request->getQueryParams()['team'] ?? 'ALL';
        $fromDate = $request->getQueryParams()['from_date'] ?? '';
        $toDate = $request->getQueryParams()['to_date'] ?? '';
        
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\AgentService();
        $teamData = $service->getTeamPerformance($fromDate, $toDate, $team);
        $agentData = $service->getAgentPerformance($fromDate, $toDate, $team);
        
        return jsonResponse($response, [
            'current' => $teamData,
            'agent_current' => $agentData
        ], 'success', 'Team performance retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get team performance by specific team (path parameter)
$app->get('/v1/agents/team/{team}/performance', function (Request $request, Response $response, array $args) {
    try {
        $team = $args['team'] ?? 'ALL';
        $fromDate = $request->getQueryParams()['from_date'] ?? '';
        $toDate = $request->getQueryParams()['to_date'] ?? '';
        
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\AgentService();
        $teamData = $service->getTeamPerformance($fromDate, $toDate, $team);
        $agentData = $service->getAgentPerformance($fromDate, $toDate, $team);
        
        return jsonResponse($response, [
            'current' => $teamData,
            'agent_current' => $agentData
        ], 'success', 'Team performance retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get GTMD by team
$app->get('/v1/agents/teams/gtmd', function (Request $request, Response $response) {
    try {
        $team = $request->getQueryParams()['team'] ?? 'ALL';
        $fromDate = $request->getQueryParams()['from_date'] ?? '';
        $toDate = $request->getQueryParams()['to_date'] ?? '';
        
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\AgentService();
        $gtmdData = $service->getGTMDByTeam($fromDate, $toDate, $team);
        
        return jsonResponse($response, $gtmdData, 'success', 'GTMD data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent performance data
$app->get('/v1/agents/performance', function (Request $request, Response $response) {
    try {
        $team = $request->getQueryParams()['team'] ?? 'ALL';
        $fromDate = $request->getQueryParams()['from_date'] ?? '';
        $toDate = $request->getQueryParams()['to_date'] ?? '';
        
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\AgentService();
        $agentData = $service->getAgentPerformance($fromDate, $toDate, $team);
        
        return jsonResponse($response, $agentData, 'success', 'Agent performance data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get team trends (last 7 days)
$app->get('/v1/agents/teams/trends', function (Request $request, Response $response) {
    try {
        $team = $request->getQueryParams()['team'] ?? 'ALL';
        $fromDate = $request->getQueryParams()['from_date'] ?? '';
        $toDate = $request->getQueryParams()['to_date'] ?? '';
        
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\AgentService();
        $trendsData = $service->getTeamTrends($fromDate, $toDate, $team);
        
        return jsonResponse($response, $trendsData, 'success', 'Team trends data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get latest booking
$app->get('/v1/bookings/latest', function (Request $request, Response $response) {
    try {
        $minDate = $request->getQueryParams()['min_date'] ?? '2025-05-09';
        
        $service = new \App\Services\AgentService();
        $latestBooking = $service->getLatestBooking($minDate);
        
        if ($latestBooking === null) {
            return jsonResponse($response, null, 'success', 'No booking found');
        }
        
        return jsonResponse($response, $latestBooking, 'success', 'Latest booking retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get team QA compliance (Garland)
$app->get('/v1/agents/teams/qa-compliance', function (Request $request, Response $response) {
    try {
        $team = $request->getQueryParams()['team'] ?? 'ALL';
        $fromDate = $request->getQueryParams()['from_date'] ?? '';
        $toDate = $request->getQueryParams()['to_date'] ?? '';
        
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\AgentService();
        $qaData = $service->getTeamQACompliance($fromDate, $toDate, $team);
        
        return jsonResponse($response, $qaData, 'success', 'Team QA compliance data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent QA compliance (Garland)
$app->get('/v1/agents/qa-compliance', function (Request $request, Response $response) {
    try {
        $team = $request->getQueryParams()['team'] ?? 'ALL';
        $fromDate = $request->getQueryParams()['from_date'] ?? '';
        $toDate = $request->getQueryParams()['to_date'] ?? '';
        
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\AgentService();
        $qaData = $service->getAgentQACompliance($fromDate, $toDate, $team);
        
        return jsonResponse($response, $qaData, 'success', 'Agent QA compliance data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ==================== Racing Championship Endpoints ====================
// Note: These endpoints are used by championship-racing-round7.php and championship-racing-round8.php
// IMPORTANT: More specific routes must be defined before less specific ones

// Get agent performance by team (Racing Championship)
// This must be defined BEFORE /v1/championships/racing/teams to avoid route conflicts
// Supports both GET and POST methods
$app->map(['GET', 'POST'], '/v1/championships/racing/teams/{team}/agents/performance', function (Request $request, Response $response, $args) {
    try {
        $teamName = $args['team'] ?? '';
        
        // Get parsed body (Slim's body parsing middleware handles this)
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        // Also check query parameters as fallback
        $queryParams = $request->getQueryParams();
        $startDate = $input['start_date'] ?? $queryParams['start_date'] ?? '';
        $endDate = $input['end_date'] ?? $queryParams['end_date'] ?? '';
        
        if (empty($teamName)) {
            return errorResponse($response, 'Team name is required', 400);
        }
        if (empty($startDate) || empty($endDate)) {
            return errorResponse($response, 'Date range is required (start_date and end_date)', 400);
        }
        
        $service = new \App\Services\ChampionshipService();
        $result = $service->getAgentPerformanceByTeam($teamName, $startDate, $endDate);
        
        return jsonResponse($response, $result, 'success', 'Agent performance retrieved successfully');
    } catch (PDOException $e) {
        error_log("PDO Error in racing championship endpoint: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
        return errorResponse($response, "Database error: " . $e->getMessage(), 500);
    } catch (Exception $e) {
        error_log("Error in racing championship endpoint: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    } catch (Throwable $e) {
        error_log("Fatal error in racing championship endpoint: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
        return errorResponse($response, "An unexpected error occurred", 500);
    }
});

// Get team metrics for racing championship
$app->get('/v1/championships/racing/teams/metrics', function (Request $request, Response $response) {
    try {
        $metric = $request->getQueryParams()['metric'] ?? 'conversion';
        $startDate = $request->getQueryParams()['start_date'] ?? '';
        $endDate = $request->getQueryParams()['end_date'] ?? '';
        $subMetric = $request->getQueryParams()['sub_metric'] ?? 'all';
        
        if (empty($startDate) || empty($endDate)) {
            throw new Exception('start_date and end_date are required', 400);
        }
        
        $service = new \App\Services\ChampionshipService();
        $metrics = $service->getTeamMetrics($metric, $startDate, $endDate, $subMetric);
        
        return jsonResponse($response, $metrics, 'success', 'Team metrics retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get active teams for racing championship
// This must be defined AFTER more specific routes
$app->get('/v1/championships/racing/teams', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ChampionshipService();
        $teams = $service->getActiveTeams();
        
        return jsonResponse($response, $teams, 'success', 'Active teams retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CHAMPIONSHIP ENDPOINTS
// ======================================

$app->get('/v1/championships/comments', function (Request $request, Response $response) {
    try {
        $action = $request->getQueryParams()['action'] ?? 'list';
        $fromDate = $request->getQueryParams()['from_date'] ?? null;
        $toDate = $request->getQueryParams()['to_date'] ?? null;
        
        if ($action !== 'list') {
            throw new Exception('Invalid action', 400);
        }
        
        $service = new \App\Services\ChampionshipService();
        $comments = $service->getComments($request->getQueryParams());
        
        return jsonResponse($response, [
            'success' => true,
            'comments' => $comments
        ], 'success', 'Comments retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/championships/comments', function (Request $request, Response $response) {
    try {
        $action = $request->getQueryParams()['action'] ?? 'post';
        
        if ($action !== 'post') {
            throw new Exception('Invalid action', 400);
        }
        
        // Note: In a real implementation, you would get user from session/auth
        // For now, using placeholder values
        $input = json_decode($request->getBody()->getContents(), true);
        if (empty($input)) {
            $input = [];
            parse_str($request->getBody()->getContents(), $input);
        }
        
        $userId = $input['user_id'] ?? 1; // Should come from auth
        $displayName = $input['user_display_name'] ?? 'Guest';
        $message = $input['message'] ?? '';
        $parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : null;
        
        if (empty($message)) {
            throw new Exception('Message is required', 400);
        }
        
        $service = new \App\Services\ChampionshipService();
        $result = $service->createComment($userId, $displayName, $message, $parentId);
        
        return jsonResponse($response, $result, 'success', 'Comment created', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// USER PORTAL ENDPOINTS
// ======================================

// IMPORTANT: More specific routes must be defined BEFORE less specific ones
// POST /v1/user-portal/requests/{caseId}/meta/bulk must come before POST /v1/user-portal/requests/{caseId}/meta
// and both must come before GET /v1/user-portal/requests

$app->post('/v1/user-portal/requests/{caseId}/meta/bulk', function (Request $request, Response $response, array $args) {
    try {
        $caseId = (int)($args['caseId'] ?? 0);
        
        if ($caseId <= 0) {
            throw new Exception('Invalid case_id', 400);
        }
        
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['data']) || !is_array($input['data'])) {
            throw new Exception('Data is required and must be an array', 400);
        }
        
        $service = new \App\Services\UserPortalService();
        $result = $service->bulkUpdateRequestMeta($caseId, $input['data']);
        
        return jsonResponse($response, $result, 'success', 'Request meta updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/user-portal/requests/{caseId}/meta', function (Request $request, Response $response, array $args) {
    try {
        $caseId = (int)($args['caseId'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['data'])) {
            throw new Exception('Data is required', 400);
        }
        
        $service = new \App\Services\UserPortalService();
        $result = $service->bulkUpdateRequestMeta($caseId, $input['data']);
        
        return jsonResponse($response, $result, 'success', 'Request meta updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/user-portal/orders/{orderId}/payment-status', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? 0;
        
        if (empty($orderId) || !is_numeric($orderId)) {
            throw new Exception('Invalid order ID', 400);
        }
        
        $service = new \App\Services\UserPortalService();
        $result = $service->getPaymentStatus($orderId);
        
        return jsonResponse($response, $result, 'success', 'Payment status retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INCENTIVE ENDPOINTS
// ======================================

$app->get('/v1/incentives/daily-performance', function (Request $request, Response $response) {
    try {
        $fromDate = $request->getQueryParams()['from_date'] ?? '';
        $toDate = $request->getQueryParams()['to_date'] ?? '';
        
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $service = new \App\Services\IncentiveService();
        $data = $service->getDailyPerformance($fromDate, $toDate);
        
        return jsonResponse($response, $data, 'success', 'Daily performance retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/incentives/agents', function (Request $request, Response $response) {
    try {
        $date = $request->getQueryParams()['date'] ?? '';
        
        if (empty($date)) {
            throw new Exception('date is required', 400);
        }
        
        $service = new \App\Services\IncentiveService();
        $data = $service->getAgentDataByDate($date);
        
        return jsonResponse($response, $data, 'success', 'Agent data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AUDITOR/QA ENDPOINTS
// ======================================

$app->get('/v1/auditors/filters', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AuditorService();
        $filters = $service->getFilterOptions();
        
        return jsonResponse($response, [
            'success' => true,
            'filter_options' => $filters
        ], 'success', 'Filter options retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/auditors/dashboard', function (Request $request, Response $response) {
    try {
        $filters = [
            'date' => $request->getQueryParams()['date'] ?? '',
            'qa_user' => $request->getQueryParams()['qa_user'] ?? '',
            'team_name' => $request->getQueryParams()['team_name'] ?? '',
            'agent_id' => $request->getQueryParams()['agent_id'] ?? ''
        ];
        
        $limit = (int)($request->getQueryParams()['limit'] ?? 25);
        $offset = (int)($request->getQueryParams()['offset'] ?? 0);
        
        $service = new \App\Services\AuditorService();
        $data = $service->getDashboardData($filters, $limit, $offset);
        
        return jsonResponse($response, [
            'success' => true,
            'data' => $data
        ], 'success', 'Dashboard data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GDS BOOKING IMPORT ENDPOINTS
// ======================================

$app->post('/v1/bookings/gds/import', function (Request $request, Response $response) {
    try {
        $pg = $request->getQueryParams()['pg'] ?? '';
        
        if ($pg !== 'check') {
            throw new Exception('Invalid request. Use ?pg=check', 400);
        }
        
        // Note: File upload handling would be implemented here
        // For now, return structure
        $service = new \App\Services\GDSBookingService();
        
        return jsonResponse($response, [
            'success' => true,
            'message' => 'CSV import preview (file handling to be implemented)'
        ], 'success', 'Import preview generated');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/bookings/gds/import/submit', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Booking data is required', 400);
        }
        
        $service = new \App\Services\GDSBookingService();
        // Implementation would process the submitted bookings
        // For now, return success
        
        return jsonResponse($response, [
            'success' => true,
            'message' => 'Bookings submitted (implementation pending)'
        ], 'success', 'Bookings submitted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// EMPLOYEE LOSS RECOVERY ENDPOINTS
// ======================================

// IMPORTANT: More specific routes (with more path segments) must be defined BEFORE less specific routes
// This ensures Slim matches the correct route

// Add loss-signed record (most specific - must be first)
$app->post('/v1/loss-recovery/employees/{id}/loss-signed', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->addLossSignedRecord($id, $input);
        return jsonResponse($response, $result, 'success', 'Loss-signed record added successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get employee loss record by ID
$app->get('/v1/loss-recovery/employees/{id}', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        if ($id <= 0) {
            throw new Exception('Invalid employee ID', 400);
        }
        
        $dal = new \App\DAL\LossRecoveryDAL();
        $result = $dal->getEmployeeLossById($id);
        
        if (!$result) {
            // Check if table exists by trying a simple query
            try {
                $testResult = $dal->getAllLossRecords([]);
                // If we can query the table but no result, record doesn't exist
                throw new Exception("Employee loss record with ID {$id} not found", 404);
            } catch (\Exception $tableError) {
                // Table might not exist
                throw new Exception("Employee loss recovery table does not exist. Please create the table first.", 500);
            }
        }
        
        return jsonResponse($response, $result, 'success', 'Employee loss record retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update employee loss record
$app->put('/v1/loss-recovery/employees/{id}', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->updateEmployeeLoss($id, $input);
        return jsonResponse($response, $result, 'success', 'Employee loss record updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update employee loss record (PATCH method support)
$app->patch('/v1/loss-recovery/employees/{id}', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->updateEmployeeLoss($id, $input);
        return jsonResponse($response, $result, 'success', 'Employee loss record updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Create employee loss record
$app->post('/v1/loss-recovery/employees', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->createEmployeeLoss($input);
        return jsonResponse($response, $result, 'success', 'Employee loss record created successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get payments by case (deductions grouped by month)
$app->get('/v1/loss-recovery/cases/{id}/payments', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getDeductionsByCase($id);
        return jsonResponse($response, ['by_month' => $result], 'success', 'Payments retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get deductions by case
$app->get('/v1/loss-recovery/cases/{id}/deductions', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getDeductionsByCase($id);
        return jsonResponse($response, ['by_month' => $result], 'success', 'Deductions retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get deductions list
$app->get('/v1/loss-recovery/cases/{id}/deductions/list', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getDeductionsList($id);
        return jsonResponse($response, ['rows' => $result], 'success', 'Deductions list retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update deductions (bulk)
$app->put('/v1/loss-recovery/cases/{id}/deductions', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->updateDeductions($id, $input);
        return jsonResponse($response, $result, 'success', 'Deductions updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Add deduction record
$app->post('/v1/loss-recovery/cases/{id}/deductions', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->addDeductionRecord($id, $input);
        return jsonResponse($response, $result, 'success', 'Deduction record added successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get installment plan
$app->get('/v1/loss-recovery/cases/{id}/plan', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getInstallmentPlan($id);
        return jsonResponse($response, ['rows' => $result], 'success', 'Installment plan retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Save installment plan
$app->post('/v1/loss-recovery/cases/{id}/plan', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->saveInstallmentPlan($id, $input);
        return jsonResponse($response, $result, 'success', 'Installment plan saved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get loss signed detail
$app->get('/v1/loss-recovery/cases/{id}/loss-signed-detail', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getLossSignedDetail($id);
        return jsonResponse($response, $result, 'success', 'Loss signed detail retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get all loss records
$app->get('/v1/loss-recovery/employees', function (Request $request, Response $response) {
    try {
        $filters = [];
        $location = $request->getQueryParams()['location'] ?? null;
        $excludeLocation = $request->getQueryParams()['exclude_location'] ?? null;
        
        if ($location) {
            $filters['location'] = $location;
        }
        if ($excludeLocation) {
            $filters['exclude_location'] = $excludeLocation;
        }
        
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getAllLossRecords($filters);
        return jsonResponse($response, $result, 'success', 'Loss records retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get filter options
$app->get('/v1/loss-recovery/filters', function (Request $request, Response $response) {
    try {
        $filters = [];
        $location = $request->getQueryParams()['location'] ?? null;
        $excludeLocation = $request->getQueryParams()['exclude_location'] ?? null;
        
        if ($location) {
            $filters['location'] = $location;
        }
        if ($excludeLocation) {
            $filters['exclude_location'] = $excludeLocation;
        }
        
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getFilterOptions($filters);
        return jsonResponse($response, $result, 'success', 'Filter options retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ==================== BOM Loss Recovery Endpoints ====================
// Note: BOM endpoints use the same Service methods but with BOM-specific DAL methods
// that filter by location = 'BOM' and exclude 'CCU' location

// IMPORTANT: More specific routes (with more path segments) must be defined BEFORE less specific routes

// Add loss-signed record (BOM) - most specific
$app->post('/v1/loss-recovery/bom/employees/{id}/loss-signed', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->addLossSignedRecord($id, $input);
        return jsonResponse($response, $result, 'success', 'Loss-signed record added successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get employee loss record by ID (BOM)
$app->get('/v1/loss-recovery/bom/employees/{id}', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        if ($id <= 0) {
            throw new Exception('Invalid employee ID', 400);
        }
        
        $dal = new \App\DAL\LossRecoveryDAL();
        $result = $dal->getEmployeeLossById($id);
        
        if (!$result) {
            try {
                $testResult = $dal->getAllLossRecordsBOM([]);
                throw new Exception("Employee loss record with ID {$id} not found", 404);
            } catch (\Exception $tableError) {
                throw new Exception("Employee loss recovery table does not exist. Please create the table first.", 500);
            }
        }
        
        return jsonResponse($response, $result, 'success', 'Employee loss record retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update employee loss record (BOM)
$app->put('/v1/loss-recovery/bom/employees/{id}', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->updateEmployeeLoss($id, $input);
        return jsonResponse($response, $result, 'success', 'Employee loss record updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update employee loss record (PATCH) (BOM)
$app->patch('/v1/loss-recovery/bom/employees/{id}', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->updateEmployeeLoss($id, $input);
        return jsonResponse($response, $result, 'success', 'Employee loss record updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Create employee loss record (BOM)
$app->post('/v1/loss-recovery/bom/employees', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->createEmployeeLoss($input);
        return jsonResponse($response, $result, 'success', 'Employee loss record created successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get payments by case (BOM)
$app->get('/v1/loss-recovery/bom/cases/{id}/payments', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getDeductionsByCase($id);
        return jsonResponse($response, ['by_month' => $result], 'success', 'Payments retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get deductions by case (BOM)
$app->get('/v1/loss-recovery/bom/cases/{id}/deductions', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getDeductionsByCase($id);
        return jsonResponse($response, ['by_month' => $result], 'success', 'Deductions retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get deductions list (BOM)
$app->get('/v1/loss-recovery/bom/cases/{id}/deductions/list', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getDeductionsList($id);
        return jsonResponse($response, ['rows' => $result], 'success', 'Deductions list retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Update deductions (bulk) (BOM)
$app->put('/v1/loss-recovery/bom/cases/{id}/deductions', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->updateDeductions($id, $input);
        return jsonResponse($response, $result, 'success', 'Deductions updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Add deduction record (BOM)
$app->post('/v1/loss-recovery/bom/cases/{id}/deductions', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->addDeductionRecord($id, $input);
        return jsonResponse($response, $result, 'success', 'Deduction record added successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get installment plan (BOM)
$app->get('/v1/loss-recovery/bom/cases/{id}/plan', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getInstallmentPlan($id);
        return jsonResponse($response, ['rows' => $result], 'success', 'Installment plan retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Save installment plan (BOM)
$app->post('/v1/loss-recovery/bom/cases/{id}/plan', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        $service = new \App\Services\LossRecoveryService();
        $result = $service->saveInstallmentPlan($id, $input);
        return jsonResponse($response, $result, 'success', 'Installment plan saved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get loss signed detail (BOM)
$app->get('/v1/loss-recovery/bom/cases/{id}/loss-signed-detail', function (Request $request, Response $response, $args) {
    try {
        $id = (int)$args['id'];
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getLossSignedDetail($id);
        return jsonResponse($response, $result, 'success', 'Loss signed detail retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get all loss records (BOM)
$app->get('/v1/loss-recovery/bom/employees', function (Request $request, Response $response) {
    try {
        $filters = [];
        $location = $request->getQueryParams()['location'] ?? null;
        
        if ($location) {
            $filters['location'] = $location;
        }
        
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getAllLossRecordsBOM($filters);
        return jsonResponse($response, $result, 'success', 'Loss records retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get filter options (BOM)
$app->get('/v1/loss-recovery/bom/filters', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getFilterOptionsBOM();
        return jsonResponse($response, $result, 'success', 'Filter options retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get active agents (BOM)
$app->get('/v1/loss-recovery/bom/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\LossRecoveryService();
        $result = $service->getActiveAgentsBOM();
        return jsonResponse($response, $result, 'success', 'Active agents retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// USER RIGHTS ENDPOINTS
// ======================================

// Get agent access rights (general)
$app->get('/v1/user-rights/agents', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? max(1, (int)$queryParams['limit']) : 20;
        $offset = isset($queryParams['offset']) ? max(0, (int)$queryParams['offset']) : 0;
        
        // Support page parameter as alternative to offset
        if (isset($queryParams['page']) && !isset($queryParams['offset'])) {
            $page = max(1, (int)$queryParams['page']);
            $offset = ($page - 1) * $limit;
        }
        
        $filters = [];
        if (isset($queryParams['location'])) {
            $filters['location'] = $queryParams['location'];
        }
        
        $service = new \App\Services\UserRightsService();
        $result = $service->getAgentAccessRights($limit, $offset, $filters);
        return jsonResponse($response, $result, 'success', 'Agent access rights retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent access rights count (general)
$app->get('/v1/user-rights/agents/count', function (Request $request, Response $response) {
    try {
        $filters = [];
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['location'])) {
            $filters['location'] = $queryParams['location'];
        }
        
        $dal = new \App\DAL\UserRightsDAL();
        $count = $dal->getAgentAccessRightsCount($filters);
        return jsonResponse($response, ['total' => $count], 'success', 'Count retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ==================== BOM User Rights Endpoints ====================

// Get agent access rights (BOM)
$app->get('/v1/user-rights/bom/agents', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? max(1, (int)$queryParams['limit']) : 20;
        $offset = isset($queryParams['offset']) ? max(0, (int)$queryParams['offset']) : 0;
        
        // Support page parameter as alternative to offset
        if (isset($queryParams['page']) && !isset($queryParams['offset'])) {
            $page = max(1, (int)$queryParams['page']);
            $offset = ($page - 1) * $limit;
        }
        
        $filters = ['location' => 'BOM'];
        
        $service = new \App\Services\UserRightsService();
        $result = $service->getAgentAccessRights($limit, $offset, $filters);
        return jsonResponse($response, $result, 'success', 'BOM agent access rights retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get agent access rights count (BOM)
$app->get('/v1/user-rights/bom/agents/count', function (Request $request, Response $response) {
    try {
        $filters = ['location' => 'BOM'];
        $dal = new \App\DAL\UserRightsDAL();
        $count = $dal->getAgentAccessRightsCount($filters);
        return jsonResponse($response, ['total' => $count], 'success', 'BOM count retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CART ENDPOINTS
// ======================================

// IMPORTANT: Cart POST routes must be defined BEFORE any generic CRUD routes
// to ensure they are matched correctly

// Debug route to check routing and base path
$app->get('/v1/cart/debug', function (Request $request, Response $response) {
    $uri = $request->getUri();
    return jsonResponse($response, [
        'message' => 'Cart debug endpoint',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not set',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'not set',
        'path_info' => $_SERVER['PATH_INFO'] ?? 'not set',
        'uri_path' => $uri->getPath(),
        'uri_base_path' => $request->getAttribute('basePath', 'not set'),
        'endpoints' => [
            'GET /v1/cart/test - Test endpoint',
            'POST /v1/cart/validate-stock',
            'POST /v1/cart/validate-stock-round-trip',
            'POST /v1/cart/check-recent-booking',
            'POST /v1/cart/cancel-previous-booking'
        ]
    ], 'success', 'Cart debug endpoint');
});

// Test route to verify cart endpoints are accessible
$app->get('/v1/cart/test', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Cart endpoints are accessible',
        'endpoints' => [
            'POST /v1/cart/validate-stock',
            'POST /v1/cart/validate-stock-round-trip',
            'POST /v1/cart/check-recent-booking',
            'POST /v1/cart/cancel-previous-booking'
        ]
    ], 'success', 'Cart test endpoint');
});

// Validate stock availability (single trip)
$app->post('/v1/cart/validate-stock', function (Request $request, Response $response) {
    error_log("POST /v1/cart/validate-stock route matched");
    try {
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        $pricingId = isset($input['pricing_id']) ? (int)$input['pricing_id'] : 0;
        $pax = isset($input['pax']) ? (int)$input['pax'] : 1;
        
        $service = new \App\Services\CartService();
        $result = $service->validateStock($pricingId, $pax);
        
        return jsonResponse($response, $result, 'success', 'Stock validation completed');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Validate stock availability (round trip)
$app->post('/v1/cart/validate-stock-round-trip', function (Request $request, Response $response) {
    try {
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        $pricingId = isset($input['pricing_id']) ? (int)$input['pricing_id'] : 0;
        $pricingIdReturn = isset($input['pricing_id_return']) ? (int)$input['pricing_id_return'] : 0;
        $pax = isset($input['pax']) ? (int)$input['pax'] : 1;
        
        $service = new \App\Services\CartService();
        $result = $service->validateStockRoundTrip($pricingId, $pricingIdReturn, $pax);
        
        return jsonResponse($response, $result, 'success', 'Round trip stock validation completed');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Check recent booking
$app->post('/v1/cart/check-recent-booking', function (Request $request, Response $response) {
    try {
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        $emailId = $input['email_id'] ?? '';
        
        $service = new \App\Services\CartService();
        $result = $service->checkRecentBooking($emailId);
        
        return jsonResponse($response, $result, 'success', 'Recent booking check completed');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Cancel previous booking
$app->post('/v1/cart/cancel-previous-booking', function (Request $request, Response $response) {
    try {
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        $orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
        $action = $input['action'] ?? '';
        
        if ($action !== 'cancel') {
            throw new Exception('action must be "cancel"', 400);
        }
        
        $service = new \App\Services\CartService();
        $result = $service->cancelPreviousBooking($orderId);
        
        return jsonResponse($response, $result, 'success', 'Previous booking canceled successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Add item to cart (requires WordPress WP_Travel_Cart class)
$app->post('/v1/cart/add', function (Request $request, Response $response) {
    error_log("=== POST /v1/cart/add route handler called ===");
    error_log("Request method: " . $request->getMethod());
    error_log("Request URI: " . $request->getUri()->getPath());
    
    try {
        // Load WordPress if not already loaded
        if (!defined('ABSPATH')) {
            // Try multiple possible paths for wp-load.php (same as custom-cart-handler.php)
            $possiblePaths = [
                $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
                dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))) . '/wp-load.php',
                dirname(dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))) . '/wp-load.php',
            ];
            
            $wpLoaded = false;
            foreach ($possiblePaths as $wpLoadPath) {
                if (file_exists($wpLoadPath)) {
                    error_log("Loading WordPress from: $wpLoadPath");
                    require_once $wpLoadPath;
                    $wpLoaded = true;
                    break;
                }
            }
            
            if (!$wpLoaded) {
                error_log("WordPress wp-load.php not found. Checked paths: " . implode(', ', $possiblePaths));
                throw new Exception('WordPress not found. This endpoint requires WordPress WP_Travel_Cart class.', 501);
            }
        }
        
        // Check if WP_Travel_Cart class exists
        if (!class_exists('WP_Travel_Cart')) {
            throw new Exception('WP_Travel_Cart class not found. Please ensure WP Travel plugin is active.', 501);
        }
        
        global $wt_cart;
        if (!$wt_cart) {
            throw new Exception('Cart object not initialized.', 500);
        }
        
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        if (empty($input['args']) || !is_array($input['args'])) {
            throw new Exception('Cart args are required', 400);
        }
        
        // Call WordPress cart add2() method
        $result = $wt_cart->add2($input['args']);
        
        if ($result) {
            return jsonResponse($response, [
                'success' => true,
                'message' => 'Item added to cart successfully.'
            ], 'success', 'Item added to cart');
        } else {
            throw new Exception('Failed to add item to cart', 500);
        }
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Add item to cart with coupon (requires WordPress WP_Travel_Cart class)
// IMPORTANT: This route must be defined before any generic CRUD routes
$app->post('/v1/cart/add-with-coupon', function (Request $request, Response $response) {
    error_log("=== POST /v1/cart/add-with-coupon route handler called ===");
    
    try {
        // Load WordPress if not already loaded
        if (!defined('ABSPATH')) {
            // Try multiple possible paths for wp-load.php
            $possiblePaths = [
                $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
                dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))) . '/wp-load.php',
                dirname(dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))) . '/wp-load.php',
            ];
            
            $wpLoaded = false;
            foreach ($possiblePaths as $wpLoadPath) {
                if (file_exists($wpLoadPath)) {
                    error_log("Loading WordPress from: $wpLoadPath");
                    require_once $wpLoadPath;
                    $wpLoaded = true;
                    break;
                }
            }
            
            if (!$wpLoaded) {
                error_log("WordPress wp-load.php not found in any of the checked paths");
                throw new Exception('WordPress not found. This endpoint requires WordPress WP_Travel_Cart class.', 501);
            }
        }
        
        // Check if WP_Travel_Cart class exists
        if (!class_exists('WP_Travel_Cart')) {
            throw new Exception('WP_Travel_Cart class not found. Please ensure WP Travel plugin is active.', 501);
        }
        
        global $wt_cart;
        if (!$wt_cart) {
            throw new Exception('Cart object not initialized.', 500);
        }
        
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        if (empty($input['args']) || !is_array($input['args'])) {
            throw new Exception('Cart args are required', 400);
        }
        
        // Call WordPress cart add3() method
        $result = $wt_cart->add3($input['args']);
        
        // Apply discount if coupon provided
        if (!empty($input['coupon_code']) && function_exists('add_discount_values')) {
            add_discount_values($input['coupon_code']);
        }
        
        if ($result) {
            return jsonResponse($response, [
                'success' => true,
                'message' => 'Item added to cart with coupon successfully.'
            ], 'success', 'Item added to cart');
        } else {
            throw new Exception('Failed to add item to cart', 500);
        }
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER ANALYSIS ENDPOINTS
// ======================================
// IMPORTANT: These routes must be defined BEFORE any generic CRUD routes
// to ensure they are matched correctly

// Debug endpoint for customer analysis
$app->get('/v1/customers/analysis/debug', function (Request $request, Response $response) {
    $uri = $request->getUri();
    return jsonResponse($response, [
        'message' => 'Customer analysis debug endpoint',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not set',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'not set',
        'path_info' => $_SERVER['PATH_INFO'] ?? 'not set',
        'uri_path' => $uri->getPath(),
        'uri_base_path' => $request->getAttribute('basePath', 'not set'),
        'query_params' => $request->getQueryParams(),
        'endpoints' => [
            'GET /v1/customers/analysis - Get customer analysis data',
            'POST /v1/customers/analysis/ai-insights - Get AI insights'
        ]
    ], 'success', 'Customer analysis debug endpoint');
});

// Get customer analysis data
$app->get('/v1/customers/analysis', function (Request $request, Response $response) {
    error_log("=== GET /v1/customers/analysis route handler called ===");
    try {
        $queryParams = $request->getQueryParams();
        
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        $dateRange = $queryParams['date_range'] ?? $queryParams['dr'] ?? null;
        
        $service = new \App\Services\CustomerAnalysisService();
        $result = $service->getAnalysisData($startDate, $endDate, $dateRange);
        
        // Return in the format expected by the frontend
        $response->getBody()->write(json_encode($result));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Helper function to generate placeholder AI insights
function generatePlaceholderInsight($action, $payload) {
    $insights = [
        'sources' => '<div class="alert alert-info"><h6>Source Performance Insights</h6><p>AI insights for source performance analysis are not yet configured. This feature requires integration with an external AI service.</p><p><small>Action: ' . htmlspecialchars($action) . '</small></p></div>',
        'charts' => '<div class="alert alert-info"><h6>Chart Analysis Insights</h6><p>AI insights for chart analysis (age groups, gender distribution, churn rate) are not yet configured. This feature requires integration with an external AI service.</p><p><small>Action: ' . htmlspecialchars($action) . '</small></p></div>',
        'journey' => '<div class="alert alert-info"><h6>Customer Journey Insights</h6><p>AI insights for customer journey analysis are not yet configured. This feature requires integration with an external AI service.</p><p><small>Action: ' . htmlspecialchars($action) . '</small></p></div>',
        'personas' => '<div class="alert alert-info"><h6>Persona Analysis Insights</h6><p>AI insights for persona analysis are not yet configured. This feature requires integration with an external AI service.</p><p><small>Action: ' . htmlspecialchars($action) . '</small></p></div>'
    ];
    
    return $insights[$action] ?? '<div class="alert alert-warning"><p>Unknown action: ' . htmlspecialchars($action) . '</p></div>';
}

// Get customer AI insights (placeholder - requires AI service integration)
// IMPORTANT: This route must be defined BEFORE any generic CRUD routes
$app->post('/v1/customers/analysis/ai-insights', function (Request $request, Response $response) {
    error_log("=== POST /v1/customers/analysis/ai-insights route handler called ===");
    error_log("Request method: " . $request->getMethod());
    error_log("Request URI: " . $request->getUri()->getPath());
    
    try {
        $parsedBody = $request->getParsedBody();
        $input = is_array($parsedBody) ? $parsedBody : [];
        
        error_log("Parsed body: " . json_encode($input));
        
        $action = $input['action'] ?? $input['act'] ?? '';
        
        if (empty($action)) {
            throw new Exception('Action parameter is required', 400);
        }
        
        // Note: This is a placeholder implementation
        // Actual AI insights require integration with an AI service
        // For now, return a placeholder response that matches frontend expectations
        $payload = $input['payload'] ?? [];
        
        // Generate a basic insight message based on the action
        $insightHtml = generatePlaceholderInsight($action, $payload);
        
        $response->getBody()->write(json_encode([
            'ok' => true,
            'html' => $insightHtml
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log("Error in AI insights endpoint: " . $e->getMessage());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ROSTER MANAGEMENT ENDPOINTS
// ======================================

// Get pending roster requests
$app->get('/v1/roster/requests/pending', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        $saleManager = $queryParams['sale_manager'] ?? '';
        
        $service = new \App\Services\RosterService();
        $result = $service->getPendingRequests($startDate, $endDate, $saleManager);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get processed roster requests
$app->get('/v1/roster/requests/processed', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        $saleManager = $queryParams['sale_manager'] ?? '';
        
        $service = new \App\Services\RosterService();
        $result = $service->getProcessedRequests($startDate, $endDate, $saleManager);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get leave requests by sale_manager (legacy endpoint - moved to different path to avoid conflict)
$app->get('/v1/roster/leave-requests/by-manager', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $saleManager = $queryParams['sale_manager'] ?? '';
        
        $service = new \App\Services\RosterService();
        $result = $service->getLeaveRequests($saleManager);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get processed leave requests
$app->get('/v1/roster/leave-requests/processed', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $saleManager = $queryParams['sale_manager'] ?? '';
        
        $service = new \App\Services\RosterService();
        $result = $service->getProcessedLeaveRequests($saleManager);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get sales managers list
$app->get('/v1/roster/sales-managers', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\RosterService();
        $result = $service->getSalesManagers();
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Approve roster request
$app->post('/v1/roster/requests/{id}/approve', function (Request $request, Response $response, $args) {
    try {
        $requestId = (int)$args['id'];
        if ($requestId <= 0) {
            throw new Exception('Invalid request ID', 400);
        }
        
        $service = new \App\Services\RosterService();
        $result = $service->approveRosterRequest($requestId);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Reject roster request
$app->post('/v1/roster/requests/{id}/reject', function (Request $request, Response $response, $args) {
    try {
        $requestId = (int)$args['id'];
        if ($requestId <= 0) {
            throw new Exception('Invalid request ID', 400);
        }
        
        $service = new \App\Services\RosterService();
        $result = $service->rejectRosterRequest($requestId);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Approve leave request
$app->post('/v1/roster/leave-requests/{id}/approve', function (Request $request, Response $response, $args) {
    try {
        $leaveId = (int)$args['id'];
        if ($leaveId <= 0) {
            throw new Exception('Invalid leave request ID', 400);
        }
        
        $service = new \App\Services\RosterService();
        $result = $service->approveLeaveRequest($leaveId);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Reject leave request
$app->post('/v1/roster/leave-requests/{id}/reject', function (Request $request, Response $response, $args) {
    try {
        $leaveId = (int)$args['id'];
        if ($leaveId <= 0) {
            throw new Exception('Invalid leave request ID', 400);
        }
        
        $service = new \App\Services\RosterService();
        $result = $service->rejectLeaveRequest($leaveId);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ROSTER - GET ALL WITH FILTERS (similar to agent-codes)
$app->get('/v1/roster', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        // Extract filters
        $filters = [];
        if (isset($queryParams['employee_code']) && $queryParams['employee_code'] !== '') {
            $filters['employee_code'] = $queryParams['employee_code'];
        }
        if (isset($queryParams['employee_name']) && $queryParams['employee_name'] !== '') {
            $filters['employee_name'] = $queryParams['employee_name'];
        }
        if (isset($queryParams['department']) && $queryParams['department'] !== '') {
            $filters['department'] = $queryParams['department'];
        }
        if (isset($queryParams['team']) && $queryParams['team'] !== '') {
            $filters['team'] = $queryParams['team'];
        }
        if (isset($queryParams['sm']) && $queryParams['sm'] !== '') {
            $filters['sm'] = $queryParams['sm'];
        }
        if (isset($queryParams['month']) && $queryParams['month'] !== '') {
            $filters['month'] = $queryParams['month'];
        }
        if (isset($queryParams['year']) && $queryParams['year'] !== '') {
            $filters['year'] = $queryParams['year'];
        }
        if (isset($queryParams['shift_time']) && $queryParams['shift_time'] !== '') {
            $filters['shift_time'] = $queryParams['shift_time'];
        }
        if (isset($queryParams['shift_type']) && $queryParams['shift_type'] !== '') {
            $filters['shift_type'] = $queryParams['shift_type'];
        }
        if (isset($queryParams['rdo']) && $queryParams['rdo'] !== '') {
            $filters['rdo'] = $queryParams['rdo'];
        }
        if (isset($queryParams['confirm']) && $queryParams['confirm'] !== '') {
            $filters['confirm'] = $queryParams['confirm'];
        }
        if (isset($queryParams['role']) && $queryParams['role'] !== '') {
            $filters['role'] = $queryParams['role'];
        }
        if (isset($queryParams['tl']) && $queryParams['tl'] !== '') {
            $filters['tl'] = $queryParams['tl'];
        }
        
        $limit = (int)($queryParams['limit'] ?? 100);
        $offset = (int)($queryParams['offset'] ?? 0);
        
        $service = new \App\Services\RosterService();
        $data = $service->getAll($limit, $offset, $filters);
        
        return jsonResponse($response, $data, 'success', 'Employee roster records retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get employee roster data
$app->get('/v1/roster/employees/{roster_code}', function (Request $request, Response $response, $args) {
    try {
        $rosterCode = $args['roster_code'] ?? '';
        $queryParams = $request->getQueryParams();
        $month = $queryParams['month'] ?? '';
        
        if (empty($rosterCode) || empty($month)) {
            throw new Exception('Roster code and month are required', 400);
        }
        
        $service = new \App\Services\RosterService();
        $result = $service->getEmployeeRoster($rosterCode, $month);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get employee roster data by employee_code, month, and year
$app->get('/v1/roster/employees/{employee_code}/roster', function (Request $request, Response $response, $args) {
    try {
        $employeeCode = $args['employee_code'] ?? '';
        $queryParams = $request->getQueryParams();
        $month = $queryParams['month'] ?? '';
        $year = $queryParams['year'] ?? '';
        
        $service = new \App\Services\RosterService();
        $result = $service->getEmployeeRosterByCodeMonthYear($employeeCode, $month, $year);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// Get employee approval history
$app->get('/v1/roster/employees/{agent_name}/approval-history', function (Request $request, Response $response, $args) {
    try {
        $agentName = $args['agent_name'] ?? '';
        
        $service = new \App\Services\RosterService();
        $result = $service->getEmployeeApprovalHistory($agentName);
        
        return jsonResponse($response, $result);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INCENTIVE PAYOUT ENDPOINTS
// ======================================

// GET all incentive payouts with filters
$app->get('/v1/incentive-payouts', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'agent_name' => $params['agent_name'] ?? null,
            'status' => $params['status'] ?? null,
            'sales_manager' => $params['sales_manager'] ?? null,
            'team_name' => $params['team_name'] ?? null,
            'start_date' => $params['start_date'] ?? null,
            'end_date' => $params['end_date'] ?? null,
            'search' => $params['search'] ?? null,
            'limit' => (int)($params['limit'] ?? 100),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\IncentivePayoutService();
        $data = $service->getPayouts($filters);
        
        return jsonResponse($response, $data, 'success', 'Payouts retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent details
$app->get('/v1/incentive-payouts/agent/{agentName}', function (Request $request, Response $response, array $args) {
    try {
        // Decode and normalize the agent name
        $agentName = urldecode($args['agentName']);
        $agentName = trim($agentName);
        
        if (empty($agentName)) {
            throw new Exception('Agent name is required', 400);
        }
        
        $service = new \App\Services\IncentivePayoutService();
        $data = $service->getAgentDetails($agentName);
        
        return jsonResponse($response, $data, 'success', 'Agent details retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST bulk approve incentives
$app->post('/v1/incentive-payouts/approve-bulk', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['agents']) || !is_array($input['agents'])) {
            throw new Exception('Agents array required', 400);
        }
        
        $service = new \App\Services\IncentivePayoutService();
        $result = $service->approveBulkIncentives($input['agents']);
        
        return jsonResponse($response, $result, 'success', 'Incentives approved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST approve single incentive
$app->post('/v1/incentive-payouts/approve/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = (int)$args['id'];
        
        $service = new \App\Services\IncentivePayoutService();
        $result = $service->approveIncentive($id);
        
        return jsonResponse($response, $result, 'success', 'Incentive approved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST bulk release funds
$app->post('/v1/incentive-payouts/release-bulk', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['agents']) || !is_array($input['agents'])) {
            throw new Exception('Agents array required', 400);
        }
        
        $service = new \App\Services\IncentivePayoutService();
        $result = $service->releaseBulkFunds($input['agents']);
        
        return jsonResponse($response, $result, 'success', 'Funds released successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST release single fund
$app->post('/v1/incentive-payouts/release/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = (int)$args['id'];
        
        $service = new \App\Services\IncentivePayoutService();
        $result = $service->releaseFunds($id);
        
        return jsonResponse($response, $result, 'success', 'Funds released successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST confirm incentive (set confirm=1)
$app->post('/v1/incentive-payouts/confirm/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = (int)$args['id'];
        
        $service = new \App\Services\IncentivePayoutService();
        $result = $service->confirmIncentive($id);
        
        return jsonResponse($response, $result, 'success', 'Incentive confirmed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET filter options
$app->get('/v1/incentive-payouts/filter-options', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\IncentivePayoutService();
        $data = $service->getFilterOptions();
        
        return jsonResponse($response, $data, 'success', 'Filter options retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INTERIM PERFORMANCE REMARK ENDPOINTS
// ======================================

// GET remark history for a TSR
$app->get('/v1/interim-performance-remark/history', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $tsr = $params['tsr'] ?? '';

        if (empty($tsr)) {
            throw new Exception('TSR parameter is required', 400);
        }

        $service = new \App\Services\InterimPerformanceRemarkService();
        $data = $service->getRemarkHistory($tsr);

        return jsonResponse($response, $data, 'success', 'Remark history retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST save or update remark
$app->post('/v1/interim-performance-remark/save', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\InterimPerformanceRemarkService();
        $data = $service->saveRemark($params);

        $message = $data['action'] === 'updated' ? 'Remark updated successfully' : 'Remark saved successfully';
        return jsonResponse($response, $data, 'success', $message);

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AMADEUS API ENDPOINTS
// ======================================

// GET check if passenger exists in name update log
$app->get('/v1/amadeus-api/check-passenger-exists', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AmadeusAPIService();
        $data = $service->checkPassengerExists($params);

        return jsonResponse($response, $data, 'success', 'Passenger existence checked successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET stock management data by PNR
$app->get('/v1/amadeus-api/stock-management', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $pnr = $params['pnr'] ?? '';

        if (empty($pnr)) {
            throw new Exception('PNR parameter is required', 400);
        }

        $service = new \App\Services\AmadeusAPIService();
        $data = $service->getStockManagementByPnr($pnr);

        if ($data === null) {
            return jsonResponse($response, null, 'success', 'No stock management data found');
        }

        return jsonResponse($response, $data, 'success', 'Stock management data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET order date by order ID
$app->get('/v1/amadeus-api/order-date', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $orderId = $params['order_id'] ?? '';

        if (empty($orderId)) {
            throw new Exception('order_id parameter is required', 400);
        }

        $service = new \App\Services\AmadeusAPIService();
        $orderDate = $service->getOrderDateByOrderId($orderId);

        return jsonResponse($response, [
            'order_id' => $orderId,
            'order_date' => $orderDate
        ], 'success', 'Order date retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET infant order ID by adult order ID
$app->get('/v1/amadeus-api/infant-order-id', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $adultOrderId = $params['adult_order_id'] ?? '';

        if (empty($adultOrderId)) {
            throw new Exception('adult_order_id parameter is required', 400);
        }

        $service = new \App\Services\AmadeusAPIService();
        $infantOrderId = $service->getInfantOrderIdByAdultOrder($adultOrderId);

        return jsonResponse($response, [
            'adult_order_id' => $adultOrderId,
            'infant_order_id' => $infantOrderId
        ], 'success', 'Infant order ID retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET passenger meal and wheelchair
$app->get('/v1/amadeus-api/passenger-ssr', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $paxId = $params['pax_id'] ?? 0;

        if (empty($paxId) || !is_numeric($paxId)) {
            throw new Exception('Valid pax_id parameter is required', 400);
        }

        $service = new \App\Services\AmadeusAPIService();
        $data = $service->getPassengerMealAndWheelchair((int)$paxId);

        if ($data === null) {
            return jsonResponse($response, null, 'success', 'No passenger SSR data found');
        }

        return jsonResponse($response, $data, 'success', 'Passenger SSR data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update passenger status
$app->post('/v1/amadeus-api/update-passenger-status', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\AmadeusAPIService();
        $data = $service->updatePassengerStatus($params);

        return jsonResponse($response, $data, 'success', 'Passenger status updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST insert name update log
$app->post('/v1/amadeus-api/name-update-log', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\AmadeusAPIService();
        $data = $service->insertNameUpdateLog($params);

        return jsonResponse($response, $data, 'success', 'Name update log inserted successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update name update log
$app->put('/v1/amadeus-api/name-update-log/{log_id}', function (Request $request, Response $response, array $args) {
    try {
        $logId = (int)$args['log_id'];
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\AmadeusAPIService();
        $data = $service->updateNameUpdateLog($logId, $params);

        return jsonResponse($response, $data, 'success', 'Name update log updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST insert name update history log
$app->post('/v1/amadeus-api/name-update-history-log', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\AmadeusAPIService();
        $data = $service->insertNameUpdateHistoryLog($params);

        return jsonResponse($response, $data, 'success', 'Name update history log inserted successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Amadeus name update candidates by order ID
$app->get('/v1/amadeus-name-update/candidates', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $orderId = isset($queryParams['order_id']) ? (int)$queryParams['order_id'] : null;
        $agentName = $queryParams['agent_name'] ?? 'Auto Try';

        if (empty($orderId) || $orderId <= 0) {
            throw new Exception('order_id parameter is required and must be greater than 0', 400);
        }

        // Get candidates using AmadeusNameUpdateCheckService
        $service = new \App\Services\AmadeusNameUpdateCheckService();
        $result = $service->checkPassengersForNameUpdate($orderId, $agentName);

        // Get order date from DAL
        $dal = new \App\DAL\AmadeusNameUpdateCheckDAL();
        $orderDate = $dal->getOrderDate($orderId);

        // Map passengers to candidates format expected by PHP file
        $candidates = [];
        foreach ($result['passengers'] ?? [] as $passenger) {
            $candidate = [
                'pnr' => $passenger['pnr'] ?? '',
                'officeID' => $passenger['officeID'] ?? '',
                'trip_code' => $passenger['trip_code'] ?? '',
                'travel_date' => $passenger['travel_date'] ?? '',
                'pax_id' => $passenger['pax_id'] ?? '',
                'airline' => $passenger['airline'] ?? '',
                'pax' => $passenger['pax'] ?? []
            ];

            // Extract seat availability from pnr_check if available
            if (isset($passenger['pnr_check']['max_pax']) || isset($passenger['pnr_check']['stock'])) {
                $candidate['seat_availability'] = [
                    'pax' => $passenger['pnr_check']['max_pax'] ?? 0,
                    'stock' => $passenger['pnr_check']['stock'] ?? 0
                ];
            }

            // Add infant data if available
            if (isset($passenger['pax']['infant'])) {
                $candidate['pax']['infant'] = $passenger['pax']['infant'];
            }

            $candidates[] = $candidate;
        }

        $responseData = [
            'candidates' => $candidates,
            'order_date' => $orderDate
        ];

        return jsonResponse($response, $responseData, 'success', 'Amadeus name update candidates retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST insert SSR update log
$app->post('/v1/amadeus-api/ssr-update-log', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\AmadeusAPIService();
        $data = $service->insertSSRUpdateLog($params);

        return jsonResponse($response, $data, 'success', 'SSR update log inserted successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT DASHBOARD ENDPOINTS
// ======================================

// GET team performance statistics
$app->get('/v1/agent-dashboard/team-stats', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'from_date' => $params['from_date'] ?? date('Y-m-d'),
            'to_date' => $params['to_date'] ?? date('Y-m-d'),
            'from_time' => $params['from_time'] ?? '00:00:00',
            'to_time' => $params['to_time'] ?? '23:59:59',
            'team_name' => $params['team_name'] ?? null
        ];
        
        $service = new \App\Services\AgentDashboardService();
        $data = $service->getTeamStats($filters);
        
        return jsonResponse($response, $data, 'success', 'Team statistics retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent performance statistics
$app->get('/v1/agent-dashboard/agent-stats', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'from_date' => $params['from_date'] ?? date('Y-m-d'),
            'to_date' => $params['to_date'] ?? date('Y-m-d'),
            'from_time' => $params['from_time'] ?? '00:00:00',
            'to_time' => $params['to_time'] ?? '23:59:59',
            'agent_id' => $params['agent_id'] ?? null,
            'team_name' => $params['team_name'] ?? null
        ];
        
        $service = new \App\Services\AgentDashboardService();
        $data = $service->getAgentStats($filters);
        
        return jsonResponse($response, $data, 'success', 'Agent statistics retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent call history
$app->get('/v1/agent-dashboard/agents/{agentId}/call-history', function (Request $request, Response $response, array $args) {
    try {
        $agentId = $args['agentId'];
        $params = $request->getQueryParams();
        
        $filters = [
            'from_date' => $params['from_date'] ?? date('Y-m-d'),
            'to_date' => $params['to_date'] ?? date('Y-m-d'),
            'from_time' => $params['from_time'] ?? '00:00:00',
            'to_time' => $params['to_time'] ?? '23:59:59',
            'limit' => (int)($params['limit'] ?? 100),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\AgentDashboardService();
        $data = $service->getAgentCallHistory($agentId, $filters);
        
        return jsonResponse($response, $data, 'success', 'Call history retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET team list
$app->get('/v1/agent-dashboard/teams', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentDashboardService();
        $data = $service->getTeamList();
        
        return jsonResponse($response, ['teams' => $data], 'success', 'Team list retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent list
$app->get('/v1/agent-dashboard/agents', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $teamName = $params['team_name'] ?? null;
        
        $service = new \App\Services\AgentDashboardService();
        $data = $service->getAgentList($teamName);
        
        return jsonResponse($response, ['agents' => $data], 'success', 'Agent list retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT RECORDS ENDPOINTS
// ======================================

// GET all agents
$app->get('/v1/agent-records', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'team_name' => $params['team_name'] ?? null,
            'employee_status' => $params['employee_status'] ?? null,
            'sale_manager' => $params['sale_manager'] ?? null,
            'status' => $params['status'] ?? null,
            'location' => $params['location'] ?? null,
            'tsr' => $params['tsr'] ?? null,
            'limit' => (int)($params['limit'] ?? 100),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getAllAgents($filters);
        
        return jsonResponse($response, $data, 'success', 'Agents retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET teams with team leaders (MUST be before /v1/agent-records/{id})
$app->get('/v1/agent-records/teams-with-leaders', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getTeamsWithLeaders();
        
        return jsonResponse($response, $data, 'success', 'Teams with leaders retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Agent Records Teams Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET TSRs with agent names (MUST be before /v1/agent-records/{id})
$app->get('/v1/agent-records/tsrs-with-agent-names', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getTsrsWithAgentNames();
        
        return jsonResponse($response, $data, 'success', 'TSRs with agent names retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Agent Records TSRs Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET team names from inbound call table (MUST be before /v1/agent-records/{id})
$app->get('/v1/agent-records/team-names-from-inbound-call', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getTeamNamesFromInboundCall();
        
        return jsonResponse($response, $data, 'success', 'Team names from inbound call retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Agent Records Team Names Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET combined agent records (MUST be before /v1/agent-records/{id})
$app->get('/v1/agent-records/combined', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'start_date' => $params['start_date'] ?? null,
            'end_date' => $params['end_date'] ?? null,
            'team_name' => $params['team_name'] ?? null,
            'group_by' => $params['group_by'] ?? 'team_name',
            'order_by' => $params['order_by'] ?? null
        ];
        
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getCombinedAgentRecords($filters);
        
        return jsonResponse($response, $data, 'success', 'Combined agent records retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Agent Records Combined Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET dropdown options (MUST be before /v1/agent-records/{id})
$app->get('/v1/agent-records/options/dropdown', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getDropdownOptions();
        
        return jsonResponse($response, $data, 'success', 'Dropdown options retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET team details (MUST be before /v1/agent-records/{id})
$app->get('/v1/agent-records/teams/{teamName}/details', function (Request $request, Response $response, array $args) {
    try {
        $teamName = urldecode($args['teamName']);
        
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getTeamDetails($teamName);
        
        return jsonResponse($response, $data, 'success', 'Team details retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agents by team (MUST be before /v1/agent-records/{id})
$app->get('/v1/agent-records/teams/{teamName}/agents', function (Request $request, Response $response, array $args) {
    try {
        $teamName = urldecode($args['teamName']);
        
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getAgentsByTeam($teamName);
        
        return jsonResponse($response, $data, 'success', 'Team agents retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent by ID (MUST be after all specific routes)
$app->get('/v1/agent-records/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\AgentRecordsService();
        $data = $service->getAgentById($id);
        
        return jsonResponse($response, $data, 'success', 'Agent retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create agent
$app->post('/v1/agent-records', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Agent data required', 400);
        }
        
        $service = new \App\Services\AgentRecordsService();
        $result = $service->createAgent($input);
        
        return jsonResponse($response, $result, 'success', 'Agent created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update agent
$app->put('/v1/agent-records/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Agent data required', 400);
        }
        
        $service = new \App\Services\AgentRecordsService();
        $result = $service->updateAgent($id, $input);
        
        return jsonResponse($response, $result, 'success', 'Agent updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// DELETE agent
$app->delete('/v1/agent-records/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\AgentRecordsService();
        $result = $service->deleteAgent($id);
        
        return jsonResponse($response, $result, 'success', 'Agent deleted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT NOTES ENDPOINTS
// ======================================

// GET agent notes with filters
$app->get('/v1/agent-notes', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'updated_date' => $params['updated_date'] ?? null,
            'updated_date_start' => $params['updated_date_start'] ?? null,
            'updated_date_end' => $params['updated_date_end'] ?? null,
            'call_date' => $params['call_date'] ?? null,
            'department' => $params['department'] ?? null,
            'category' => $params['category'] ?? null
        ];
        
        $service = new \App\Services\AgentNotesService();
        $data = $service->getAgentNotes($filters);
        
        return jsonResponse($response, $data, 'success', 'Agent notes retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Agent Notes Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AUTO CANCELLATION ENDPOINTS
// ======================================

// GET all pending cancellations summary
$app->get('/v1/auto-cancellation/summary', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationService();
        $data = $service->getAllPendingCancellations();
        
        return jsonResponse($response, $data, 'success', 'Pending cancellations summary retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings pending email reminder (20 minutes)
$app->get('/v1/auto-cancellation/pending-email-reminders', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $limit = (int)($params['limit'] ?? 100);
        
        $service = new \App\Services\AutoCancellationService();
        $data = $service->getPendingEmailReminders($limit);
        
        return jsonResponse($response, $data, 'success', 'Pending email reminders retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for 3-hour cancellation (zero paid)
$app->get('/v1/auto-cancellation/pending-3hour', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $limit = (int)($params['limit'] ?? 100);
        
        $service = new \App\Services\AutoCancellationService();
        $data = $service->getPending3HourCancellation($limit);
        
        return jsonResponse($response, $data, 'success', '3-hour cancellation bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for 25-hour cancellation (FIT partially paid)
$app->get('/v1/auto-cancellation/pending-25hour', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $limit = (int)($params['limit'] ?? 100);
        
        $service = new \App\Services\AutoCancellationService();
        $data = $service->getPending25HourCancellation($limit);
        
        return jsonResponse($response, $data, 'success', '25-hour cancellation bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for 96-hour cancellation (partially paid)
$app->get('/v1/auto-cancellation/pending-96hour', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $limit = (int)($params['limit'] ?? 100);
        
        $service = new \App\Services\AutoCancellationService();
        $data = $service->getPending96HourCancellation($limit);
        
        return jsonResponse($response, $data, 'success', '96-hour cancellation bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings past deposit deadline
$app->get('/v1/auto-cancellation/pending-deposit-deadline', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $limit = (int)($params['limit'] ?? 100);
        
        $service = new \App\Services\AutoCancellationService();
        $data = $service->getPendingDepositDeadline($limit);
        
        return jsonResponse($response, $data, 'success', 'Deposit deadline bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process 20-minute cancellation
$app->post('/v1/auto-cancellation/process-20min', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['order_ids']) || !is_array($input['order_ids'])) {
            throw new Exception('order_ids array is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationService();
        $result = $service->processCancellation20Min($input['order_ids']);
        
        return jsonResponse($response, $result, 'success', 'Cancellation processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process 3-hour cancellation
$app->post('/v1/auto-cancellation/process-3hour', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['order_ids']) || !is_array($input['order_ids'])) {
            throw new Exception('order_ids array is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationService();
        $result = $service->processCancellation3Hour($input['order_ids']);
        
        return jsonResponse($response, $result, 'success', 'Cancellation processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process 25-hour cancellation
$app->post('/v1/auto-cancellation/process-25hour', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['order_ids']) || !is_array($input['order_ids'])) {
            throw new Exception('order_ids array is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationService();
        $result = $service->processCancellation25Hour($input['order_ids']);
        
        return jsonResponse($response, $result, 'success', 'Cancellation processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process 96-hour cancellation
$app->post('/v1/auto-cancellation/process-96hour', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['order_ids']) || !is_array($input['order_ids'])) {
            throw new Exception('order_ids array is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationService();
        $result = $service->processCancellation96Hour($input['order_ids']);
        
        return jsonResponse($response, $result, 'success', 'Cancellation processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process deposit deadline cancellation
$app->post('/v1/auto-cancellation/process-deposit-deadline', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['order_ids']) || !is_array($input['order_ids'])) {
            throw new Exception('order_ids array is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationService();
        $result = $service->processCancellationDepositDeadline($input['order_ids']);
        
        return jsonResponse($response, $result, 'success', 'Cancellation processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process full payment deadline cancellation
$app->post('/v1/auto-cancellation/process-fullpayment-deadline', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['order_ids']) || !is_array($input['order_ids'])) {
            throw new Exception('order_ids array is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationService();
        $result = $service->processCancellationFullPaymentDeadline($input['order_ids']);
        
        return jsonResponse($response, $result, 'success', 'Cancellation processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// BOOKING ISSUES ENDPOINTS
// ======================================

// GET all booking issues
$app->get('/v1/booking-issues', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'category' => $params['category'] ?? null,
            'order_id' => $params['order_id'] ?? null,
            'status' => $params['status'] ?? null,
            'limit' => (int)($params['limit'] ?? 100),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\BookingIssuesService();
        $data = $service->getAllIssues($filters);
        
        return jsonResponse($response, $data, 'success', 'Booking issues retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET issue by ID
$app->get('/v1/booking-issues/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\BookingIssuesService();
        $data = $service->getIssueById($id);
        
        return jsonResponse($response, $data, 'success', 'Issue retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET issue categories
$app->get('/v1/booking-issues/options/categories', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BookingIssuesService();
        $data = $service->getIssueCategories();
        
        return jsonResponse($response, ['categories' => $data], 'success', 'Issue categories retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create issue
$app->post('/v1/booking-issues', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Issue data required', 400);
        }
        
        $service = new \App\Services\BookingIssuesService();
        $result = $service->createIssue($input);
        
        return jsonResponse($response, $result, 'success', 'Issue created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update issue
$app->put('/v1/booking-issues/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Issue data required', 400);
        }
        
        $service = new \App\Services\BookingIssuesService();
        $result = $service->updateIssue($id, $input);
        
        return jsonResponse($response, $result, 'success', 'Issue updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST close issue
$app->post('/v1/booking-issues/{id}/close', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        $username = $input['username'] ?? 'system';
        
        $service = new \App\Services\BookingIssuesService();
        $result = $service->closeIssue($id, $username);
        
        return jsonResponse($response, $result, 'success', 'Issue closed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST escalate to HO
$app->post('/v1/booking-issues/{id}/escalate-to-ho', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        $username = $input['username'] ?? 'system';
        
        $service = new \App\Services\BookingIssuesService();
        $result = $service->escalateToHO($id, $username);
        
        return jsonResponse($response, $result, 'success', 'Issue escalated to HO successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST transfer to escalation
$app->post('/v1/booking-issues/{id}/transfer-to-escalation', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        $username = $input['username'] ?? 'system';
        
        $service = new \App\Services\BookingIssuesService();
        $result = $service->transferToEscalation($id, $username);
        
        return jsonResponse($response, $result, 'success', 'Issue transferred to escalation successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// DELETE issue
$app->delete('/v1/booking-issues/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\BookingIssuesService();
        $result = $service->deleteIssue($id);
        
        return jsonResponse($response, $result, 'success', 'Issue deleted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CHARGE BACKS ENDPOINTS
// ======================================

// GET all chargebacks
$app->get('/v1/charge-backs', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'order_id' => $params['order_id'] ?? null,
            'charge_back_number' => $params['charge_back_number'] ?? null,
            'charge_back_date' => $params['charge_back_date'] ?? null,
            'responded_date_to_cba' => $params['responded_date_to_cba'] ?? null,
            'status' => $params['status'] ?? null,
            'bank_debit_date' => $params['bank_debit_date'] ?? null,
            'limit' => (int)($params['limit'] ?? 100),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\ChargeBacksService();
        $data = $service->getAllChargebacks($filters);
        
        return jsonResponse($response, $data, 'success', 'Chargebacks retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET chargeback by ID
$app->get('/v1/charge-backs/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\ChargeBacksService();
        $data = $service->getChargebackById($id);
        
        return jsonResponse($response, $data, 'success', 'Chargeback retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create chargeback
$app->post('/v1/charge-backs', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Chargeback data required', 400);
        }
        
        $service = new \App\Services\ChargeBacksService();
        $result = $service->createChargeback($input);
        
        return jsonResponse($response, $result, 'success', 'Chargeback created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update chargeback
$app->put('/v1/charge-backs/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Chargeback data required', 400);
        }
        
        $service = new \App\Services\ChargeBacksService();
        $result = $service->updateChargeback($id, $input);
        
        return jsonResponse($response, $result, 'success', 'Chargeback updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// DELETE chargeback
$app->delete('/v1/charge-backs/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\ChargeBacksService();
        $result = $service->deleteChargeback($id);
        
        return jsonResponse($response, $result, 'success', 'Chargeback deleted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER SEARCH ENDPOINTS
// ======================================

// GET search customers by multiple criteria
$app->get('/v1/customers/search', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'customer_id' => $params['customer_id'] ?? null,
            'family_id' => $params['family_id'] ?? null,
            'profile_id' => $params['profile_id'] ?? null,
            'order_id' => $params['order_id'] ?? null,
            'email' => $params['email'] ?? null,
            'phone' => $params['phone'] ?? null,
            'limit' => (int)($params['limit'] ?? 10)
        ];
        
        $service = new \App\Services\CustomerSearchService();
        $data = $service->searchCustomers($filters);
        
        return jsonResponse($response, $data, 'success', 'Customer search completed');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET search bookings by phone
$app->get('/v1/customers/search-by-phone/bookings', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['phone'])) {
            throw new Exception('Phone number is required', 400);
        }
        
        $limit = (int)($params['limit'] ?? 100);
        
        $service = new \App\Services\CustomerSearchService();
        $data = $service->searchBookingsByPhone($params['phone'], $limit);
        
        return jsonResponse($response, $data, 'success', 'Bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET search quotes by phone
$app->get('/v1/customers/search-by-phone/quotes', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['phone'])) {
            throw new Exception('Phone number is required', 400);
        }
        
        $limit = (int)($params['limit'] ?? 100);
        
        $service = new \App\Services\CustomerSearchService();
        $data = $service->searchQuotesByPhone($params['phone'], $limit);
        
        return jsonResponse($response, $data, 'success', 'Quotes retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET complete customer profile by phone
$app->get('/v1/customers/profile-by-phone', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['phone'])) {
            throw new Exception('Phone number is required', 400);
        }
        
        $service = new \App\Services\CustomerSearchService();
        $data = $service->getCustomerProfileByPhone($params['phone']);
        
        return jsonResponse($response, $data, 'success', 'Customer profile retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET customer by ID
$app->get('/v1/customers/{customerId}', function (Request $request, Response $response, array $args) {
    try {
        $customerId = $args['customerId'];
        
        $service = new \App\Services\CustomerSearchService();
        $data = $service->getCustomerById($customerId);
        
        return jsonResponse($response, $data, 'success', 'Customer retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// DOMESTIC STOCK ENDPOINTS
// ======================================

// GET stock by trip code and date
$app->get('/v1/domestic-stock/trip/{tripCode}/date/{depDate}', function (Request $request, Response $response, array $args) {
    try {
        $tripCode = $args['tripCode'];
        $depDate = $args['depDate'];
        $params = $request->getQueryParams();
        
        $pnr = $params['pnr'] ?? null;
        $intStatus = $params['int_status'] ?? null;
        $domStatus = $params['dom_status'] ?? null;
        
        $service = new \App\Services\DomesticStockService();
        $data = $service->getStockByTripAndDate($tripCode, $depDate, $pnr, $intStatus, $domStatus);
        
        return jsonResponse($response, $data, 'success', 'Stock retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET stock by PNR
$app->get('/v1/domestic-stock/pnr/{pnr}', function (Request $request, Response $response, array $args) {
    try {
        $pnr = $args['pnr'];
        $params = $request->getQueryParams();
        
        $stockType = $params['type'] ?? 'both'; // international, domestic, or both
        
        $service = new \App\Services\DomesticStockService();
        $data = $service->getStockByPNR($pnr, $stockType);
        
        return jsonResponse($response, $data, 'success', 'Stock retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update international stock
$app->put('/v1/domestic-stock/international/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Stock data required', 400);
        }
        
        $service = new \App\Services\DomesticStockService();
        $result = $service->updateInternationalStock($id, $input);
        
        return jsonResponse($response, $result, 'success', 'International stock updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update domestic stock
$app->put('/v1/domestic-stock/domestic/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Stock data required', 400);
        }
        
        $service = new \App\Services\DomesticStockService();
        $result = $service->updateDomesticStock($id, $input);
        
        return jsonResponse($response, $result, 'success', 'Domestic stock updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INCENTIVE MANAGEMENT ENDPOINTS
// ======================================

// GET all incentive conditions
$app->get('/v1/incentive-management/conditions', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'campaign' => $params['campaign'] ?? null,
            'limit' => (int)($params['limit'] ?? 100),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\IncentiveManagementService();
        $data = $service->getAllConditions($filters);
        
        return jsonResponse($response, $data, 'success', 'Conditions retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET incentive condition by ID
$app->get('/v1/incentive-management/conditions/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\IncentiveManagementService();
        $data = $service->getConditionById($id);
        
        return jsonResponse($response, $data, 'success', 'Condition retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET incentive data by date and type
$app->get('/v1/incentive-management/data', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['date']) || empty($params['type'])) {
            throw new Exception('Date and type parameters are required', 400);
        }
        
        $service = new \App\Services\IncentiveManagementService();
        $data = $service->getIncentiveData($params['date'], $params['type']);
        
        return jsonResponse($response, $data, 'success', 'Incentive data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET team names
$app->get('/v1/incentive-management/teams', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\IncentiveManagementService();
        $data = $service->getTeamNames();
        
        return jsonResponse($response, ['teams' => $data], 'success', 'Team names retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create incentive condition
$app->post('/v1/incentive-management/conditions', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Condition data required', 400);
        }
        
        $service = new \App\Services\IncentiveManagementService();
        $result = $service->createCondition($input);
        
        return jsonResponse($response, $result, 'success', 'Condition created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update incentive condition
$app->put('/v1/incentive-management/conditions/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Condition data required', 400);
        }
        
        $service = new \App\Services\IncentiveManagementService();
        $result = $service->updateCondition($id, $input);
        
        return jsonResponse($response, $result, 'success', 'Condition updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// DELETE incentive condition
$app->delete('/v1/incentive-management/conditions/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\IncentiveManagementService();
        $result = $service->deleteCondition($id);
        
        return jsonResponse($response, $result, 'success', 'Condition deleted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INVOICE ENDPOINTS
// ======================================

// GET invoice data with security validation
$app->get('/v1/invoices/{orderId}', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        $params = $request->getQueryParams();
        
        if (empty($params['key']) || empty($params['pass'])) {
            throw new Exception('Security keys (key and pass) are required', 400);
        }
        
        $service = new \App\Services\InvoiceService();
        $data = $service->getInvoiceData($orderId, $params['key'], $params['pass']);
        
        return jsonResponse($response, $data, 'success', 'Invoice data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// LEADERSHIP CHECKLIST ENDPOINTS
// ======================================

// GET checklist for team leader
$app->get('/v1/leadership-checklist/tl/{username}', function (Request $request, Response $response, array $args) {
    try {
        $username = $args['username'];
        $params = $request->getQueryParams();
        
        $date = $params['date'] ?? null;
        
        $service = new \App\Services\LeadershipChecklistService();
        $data = $service->getChecklistForTL($username, $date);
        
        return jsonResponse($response, $data, 'success', 'Checklist retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET checklist tasks
$app->get('/v1/leadership-checklist/tasks', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\LeadershipChecklistService();
        $data = $service->getChecklistTasks();
        
        return jsonResponse($response, $data, 'success', 'Checklist tasks retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create checklist entry
$app->post('/v1/leadership-checklist', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Checklist data required', 400);
        }
        
        $service = new \App\Services\LeadershipChecklistService();
        $result = $service->createChecklistEntry($input);
        
        return jsonResponse($response, $result, 'success', 'Checklist entry created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update checklist entry
$app->put('/v1/leadership-checklist/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Checklist data required', 400);
        }
        
        $service = new \App\Services\LeadershipChecklistService();
        $result = $service->updateChecklistEntry($id, $input);
        
        return jsonResponse($response, $result, 'success', 'Checklist entry updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// DELETE checklist entry
$app->delete('/v1/leadership-checklist/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\LeadershipChecklistService();
        $result = $service->deleteChecklistEntry($id);
        
        return jsonResponse($response, $result, 'success', 'Checklist entry deleted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// NAME UPDATE ENDPOINTS
// ======================================

// GET pending name updates
$app->get('/v1/name-updates/pending', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $orderId = $params['order_id'] ?? null;
        
        $service = new \App\Services\NameUpdateService();
        $data = $service->getPendingNameUpdates($orderId);
        
        return jsonResponse($response, $data, 'success', 'Pending name updates retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET name update history
$app->get('/v1/name-updates/history', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $orderId = $params['order_id'] ?? null;
        $pnr = $params['pnr'] ?? null;
        
        $service = new \App\Services\NameUpdateService();
        $data = $service->getNameUpdateHistory($orderId, $pnr);
        
        return jsonResponse($response, $data, 'success', 'Name update history retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST log name update
$app->post('/v1/name-updates/log', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Name update data required', 400);
        }
        
        $service = new \App\Services\NameUpdateService();
        $result = $service->logNameUpdate($input);
        
        return jsonResponse($response, $result, 'success', 'Name update logged successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update passenger name update status
$app->post('/v1/name-updates/update-pax-status', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        if (empty($params['pax_id'])) {
            return errorResponse($response, 'pax_id is required', 400);
        }

        $service = new \App\Services\NameUpdateService();
        $data = $service->updatePaxNameUpdateStatus(
            $params['pax_id'],
            $params['status'] ?? 'Name Updated',
            $params['check_on'] ?? null,
            $params['check'] ?? 'Amadeus Name Update'
        );

        return jsonResponse($response, $data, 'success', 'Passenger status updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET passenger meal and wheelchair preferences
$app->get('/v1/name-updates/pax/{paxId}/meal-wheelchair', function (Request $request, Response $response, array $args) {
    try {
        $paxId = $args['paxId'] ?? null;
        if (empty($paxId)) {
            return errorResponse($response, 'pax_id is required', 400);
        }

        $service = new \App\Services\NameUpdateService();
        $data = $service->getPaxMealWheelchair($paxId);

        return jsonResponse($response, $data, 'success', 'Meal and wheelchair preferences retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET order date by order ID
$app->get('/v1/name-updates/order/{orderId}/date', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        if (empty($orderId)) {
            return errorResponse($response, 'order_id is required', 400);
        }

        $service = new \App\Services\NameUpdateService();
        $data = $service->getOrderDate($orderId);

        return jsonResponse($response, $data, 'success', 'Order date retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET seat availability by trip code and travel date
$app->get('/v1/name-updates/seat-availability', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['trip_code']) || empty($params['travel_date'])) {
            return errorResponse($response, 'trip_code and travel_date are required', 400);
        }

        $service = new \App\Services\NameUpdateService();
        $data = $service->getSeatAvailability($params['trip_code'], $params['travel_date']);

        return jsonResponse($response, $data, 'success', 'Seat availability retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create SSR update log
$app->post('/v1/name-updates/ssr-log', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\NameUpdateService();
        $data = $service->createSSRUpdateLog($params);

        return jsonResponse($response, $data, 'success', 'SSR update log created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create name update log with full details
$app->post('/v1/name-updates/log-full', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\NameUpdateService();
        $data = $service->createNameUpdateLogFull($params);

        return jsonResponse($response, $data, 'success', 'Name update log created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET passengers for name update by order ID
$app->get('/v1/name-updates/order/{orderId}/passengers', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        $params = $request->getQueryParams();
        $includeInfants = isset($params['include_infants']) && $params['include_infants'] === 'true';
        $requirePaid = isset($params['require_paid']) && $params['require_paid'] === 'true';

        if (empty($orderId)) {
            return errorResponse($response, 'order_id is required', 400);
        }

        $service = new \App\Services\NameUpdateService();
        $data = $service->getPassengersForNameUpdate($orderId, $includeInfants, $requirePaid);

        return jsonResponse($response, $data, 'success', 'Passengers retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET adult order passengers (for infant linking)
$app->get('/v1/name-updates/adult-order/{adultOrderId}/passengers', function (Request $request, Response $response, array $args) {
    try {
        $adultOrderId = $args['adultOrderId'] ?? null;
        if (empty($adultOrderId)) {
            return errorResponse($response, 'adult_order_id is required', 400);
        }

        $service = new \App\Services\NameUpdateService();
        $data = $service->getAdultOrderPassengers($adultOrderId);

        return jsonResponse($response, $data, 'success', 'Adult order passengers retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// LANDING PAGE ENDPOINTS
// ======================================

// GET all landing pages
$app->get('/v1/landing-pages', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'status' => $params['status'] ?? 'publish',
            'post_type' => $params['post_type'] ?? 'page',
            'limit' => (int)($params['limit'] ?? 50),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\LandingPageService();
        $data = $service->getAllLandingPages($filters);
        
        return jsonResponse($response, $data, 'success', 'Landing pages retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET landing page by ID or slug
$app->get('/v1/landing-pages/{identifier}', function (Request $request, Response $response, array $args) {
    try {
        $identifier = $args['identifier'];
        
        $service = new \App\Services\LandingPageService();
        $data = $service->getLandingPage($identifier);
        
        return jsonResponse($response, $data, 'success', 'Landing page retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET page meta data
$app->get('/v1/landing-pages/{id}/meta', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\LandingPageService();
        $data = $service->getPageMeta($id);
        
        return jsonResponse($response, $data, 'success', 'Page metadata retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AUTHENTICATION ENDPOINTS
// ======================================

// GET user secret by Firebase ID
$app->get('/v1/auth/firebase/{firebaseId}/secret', function (Request $request, Response $response, array $args) {
    try {
        $firebaseId = $args['firebaseId'];
        
        $service = new \App\Services\AuthenticationService();
        $data = $service->getUserSecret($firebaseId);
        
        return jsonResponse($response, $data, 'success', 'User secret retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST save user secret
$app->post('/v1/auth/firebase/secret', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['firebase_id']) || empty($input['secret'])) {
            throw new Exception('firebase_id and secret are required', 400);
        }
        
        $service = new \App\Services\AuthenticationService();
        $result = $service->saveUserSecret($input['firebase_id'], $input['secret']);
        
        return jsonResponse($response, $result, 'success', 'User secret saved successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET customer account by email
$app->get('/v1/auth/customer/email/{email}', function (Request $request, Response $response, array $args) {
    try {
        $email = urldecode($args['email']);
        
        $service = new \App\Services\AuthenticationService();
        $data = $service->getCustomerAccountByEmail($email);
        
        return jsonResponse($response, $data, 'success', 'Customer account retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create customer account
$app->post('/v1/auth/customer/account', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Customer data required', 400);
        }
        
        $service = new \App\Services\AuthenticationService();
        $result = $service->createCustomerAccount($input);
        
        return jsonResponse($response, $result, 'success', 'Customer account created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ASIAPAY PAYMENT CAPTURE ENDPOINTS
// ======================================

// POST capture missing payments (today)
$app->post('/v1/payments/asiapay/capture-missing', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Get optional parameters
        $queryDate = $body['query_date'] ?? null;
        $merchantId = $body['merchant_id'] ?? null;
        $source = $body['source'] ?? 'gds';
        
        // Validate source
        if (!in_array($source, ['gds', 'wpt'])) {
            throw new Exception('Source must be either "gds" or "wpt"', 400);
        }
        
        // Convert query_date format if provided (accepts dmY format like "20250115")
        if ($queryDate !== null && strlen($queryDate) == 8) {
            // Already in dmY format
        } elseif ($queryDate !== null) {
            // Try to convert from other formats
            $queryDate = date('dmY', strtotime($queryDate));
        }
        
        $service = new \App\Services\AsiaPayPaymentCaptureService();
        $result = $service->captureMissingPayments($queryDate, $source, $merchantId);
        
        if ($result['success']) {
            return jsonResponse($response, $result, 'success', $result['message'], 200);
        } else {
            return errorResponse($response, $result['message'], 500);
        }
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST capture missing payments (yesterday)
$app->post('/v1/payments/asiapay/capture-missing/yesterday', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Get optional parameters
        $merchantId = $body['merchant_id'] ?? null;
        $source = $body['source'] ?? 'gds';
        
        // Validate source
        if (!in_array($source, ['gds', 'wpt'])) {
            throw new Exception('Source must be either "gds" or "wpt"', 400);
        }
        
        // Set query date to yesterday in dmY format
        $queryDate = date('dmY', strtotime('yesterday'));
        
        $service = new \App\Services\AsiaPayPaymentCaptureService();
        $result = $service->captureMissingPayments($queryDate, $source, $merchantId);
        
        // If source is 'gds', also process WPT merchant (16001455)
        if ($source === 'gds') {
            $wptResult = $service->captureMissingPayments($queryDate, 'wpt', '16001455');
            
            // Merge results
            $result['summary']['total_references_queried'] += $wptResult['summary']['total_references_queried'];
            $result['summary']['transactions_inserted'] += $wptResult['summary']['transactions_inserted'];
            $result['summary']['transactions_updated'] += $wptResult['summary']['transactions_updated'];
            $result['summary']['transactions_modified'] += $wptResult['summary']['transactions_modified'];
            $result['summary']['payment_history_inserted'] += $wptResult['summary']['payment_history_inserted'];
            $result['summary']['payment_history_deleted'] += $wptResult['summary']['payment_history_deleted'];
            $result['summary']['journal_entries_created'] += $wptResult['summary']['journal_entries_created'];
            
            $result['detailed_results'] = array_merge(
                $result['detailed_results'],
                $wptResult['detailed_results']
            );
            
            if (!$wptResult['success']) {
                $result['message'] .= ' | WPT processing: ' . $wptResult['message'];
            }
        }
        
        if ($result['success']) {
            return jsonResponse($response, $result, 'success', $result['message'], 200);
        } else {
            return errorResponse($response, $result['message'], 500);
        }
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST Capture Missing Payments (Custom Date)
$app->post('/v1/payments/asiapay/capture-missing/custom', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['query_date'])) {
            throw new Exception('query_date is required', 400);
        }
        
        $queryDate = $body['query_date']; // Required: Date in dmY format
        $merchantId = $body['merchant_id'] ?? null;
        $source = $body['source'] ?? 'gds';
        
        // Validate source
        if (!in_array($source, ['gds', 'wpt'])) {
            throw new Exception('Source must be either "gds" or "wpt"', 400);
        }
        
        // Validate query_date format (should be 8 characters for dmY format)
        if (strlen($queryDate) !== 8 || !preg_match('/^\d{8}$/', $queryDate)) {
            throw new Exception('query_date must be in dmY format (8 digits, e.g., "31102025")', 400);
        }
        
        $service = new \App\Services\AsiaPayPaymentCaptureService();
        $result = $service->captureMissingPayments($queryDate, $source, $merchantId);
        
        // If source is 'gds', also process WPT merchant (16001455)
        if ($source === 'gds') {
            $wptResult = $service->captureMissingPayments($queryDate, 'wpt', '16001455');
            
            // Merge results
            $result['summary']['total_references_queried'] += $wptResult['summary']['total_references_queried'];
            $result['summary']['transactions_inserted'] += $wptResult['summary']['transactions_inserted'];
            $result['summary']['transactions_updated'] += $wptResult['summary']['transactions_updated'];
            $result['summary']['transactions_modified'] += $wptResult['summary']['transactions_modified'];
            $result['summary']['payment_history_inserted'] += $wptResult['summary']['payment_history_inserted'];
            $result['summary']['payment_history_deleted'] += $wptResult['summary']['payment_history_deleted'];
            $result['summary']['journal_entries_created'] += $wptResult['summary']['journal_entries_created'];
            
            $result['detailed_results'] = array_merge(
                $result['detailed_results'],
                $wptResult['detailed_results']
            );
            
            if (!$wptResult['success']) {
                $result['message'] .= ' | WPT processing: ' . $wptResult['message'];
            }
        }
        
        if ($result['success']) {
            return jsonResponse($response, $result, 'success', $result['message'], 200);
        } else {
            return errorResponse($response, $result['message'], 500);
        }
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INTERNAL EMAIL ENDPOINTS
// ======================================
// IMPORTANT: Static routes must be defined BEFORE variable routes to avoid route shadowing
// Order: POST routes -> Static GET routes -> Variable GET routes

// POST Create or Update Email
$app->post('/v1/internal-emails', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        $senderId = $body['sender_id'] ?? null;
        $receiverId = $body['receiver_id'] ?? $body['receiver'] ?? null;
        $subject = $body['subject'] ?? null;
        $message = $body['message'] ?? null;
        $isDraft = isset($body['is_draft']) ? (int)$body['is_draft'] : 0;
        $parentEmailId = $body['parent_email_id'] ?? null;
        $draftId = $body['draft_id'] ?? null;
        
        $service = new \App\Services\InternalEmailService();
        $result = $service->createOrUpdateEmail(
            $senderId,
            $receiverId,
            $subject,
            $message,
            $isDraft,
            $parentEmailId,
            $draftId
        );
        
        return jsonResponse($response, $result, 'success', 'Email ' . ($result['type'] === 'created' ? 'created' : 'updated') . ' successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Search Users (Static route - must be before variable routes)
$app->get('/v1/internal-emails/users/search', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['query'] ?? '';
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20;
        
        $service = new \App\Services\InternalEmailService();
        $result = $service->searchUsers($query, $limit);
        
        return jsonResponse($response, $result, 'success', 'Users retrieved successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Inbox Emails (Static route - must be before variable routes)
$app->get('/v1/internal-emails/inbox', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $userId = $queryParams['user_id'] ?? null;
        
        if (empty($userId) || !is_numeric($userId)) {
            throw new Exception('Valid user ID is required', 400);
        }
        
        $service = new \App\Services\InternalEmailService();
        $result = $service->getInboxEmails((int)$userId);
        
        return jsonResponse($response, $result, 'success', 'Inbox emails retrieved successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Sent Emails (Static route - must be before variable routes)
$app->get('/v1/internal-emails/sent', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $userId = $queryParams['user_id'] ?? null;
        
        if (empty($userId) || !is_numeric($userId)) {
            throw new Exception('Valid user ID is required', 400);
        }
        
        $service = new \App\Services\InternalEmailService();
        $result = $service->getSentEmails((int)$userId);
        
        return jsonResponse($response, $result, 'success', 'Sent emails retrieved successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Draft Emails (Static route - must be before variable routes)
$app->get('/v1/internal-emails/draft', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $userId = $queryParams['user_id'] ?? null;
        
        if (empty($userId) || !is_numeric($userId)) {
            throw new Exception('Valid user ID is required', 400);
        }
        
        $service = new \App\Services\InternalEmailService();
        $result = $service->getDraftEmails((int)$userId);
        
        return jsonResponse($response, $result, 'success', 'Draft emails retrieved successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Email Thread (Variable route with prefix - safe to define here)
$app->get('/v1/internal-emails/thread/{thread_id}', function (Request $request, Response $response, $args) {
    try {
        $threadId = $args['thread_id'] ?? null;
        
        if (empty($threadId) || !is_numeric($threadId)) {
            throw new Exception('Valid thread ID is required', 400);
        }
        
        $service = new \App\Services\InternalEmailService();
        $result = $service->getEmailThread((int)$threadId);
        
        return jsonResponse($response, $result, 'success', 'Thread retrieved successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Email by ID (Variable route - MUST be defined LAST to avoid shadowing static routes)
$app->get('/v1/internal-emails/{id}', function (Request $request, Response $response, $args) {
    try {
        $emailId = $args['id'] ?? null;
        
        if (empty($emailId) || !is_numeric($emailId)) {
            throw new Exception('Valid email ID is required', 400);
        }
        
        $service = new \App\Services\InternalEmailService();
        $result = $service->getEmailById((int)$emailId);
        
        return jsonResponse($response, $result, 'success', 'Email retrieved successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// QUOTE SUBMISSION ENDPOINTS
// ======================================

// POST Submit Quote
$app->post('/v1/quotes/submit', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['flights']) || !is_array($body['flights'])) {
            throw new Exception('flights array is required and must not be empty', 400);
        }
        
        // Get optional phone number
        $phone = $body['phone'] ?? null;
        
        $service = new \App\Services\QuoteSubmissionService();
        $result = $service->submitQuote($phone, $body['flights']);
        
        return jsonResponse($response, $result['data'], 'success', $result['message'], 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PAX NAME UPDATE ENDPOINTS
// ======================================

// POST Preview Pax Name Update Import
$app->post('/v1/pax-name-update/preview', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['csv_data']) || !is_array($body['csv_data'])) {
            throw new Exception('csv_data is required and must be an array', 400);
        }
        
        $service = new \App\Services\PaxNameUpdateService();
        $result = $service->previewPaxNameUpdate($body['csv_data']);
        
        return jsonResponse($response, $result, 'success', 'Preview generated successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST Import Pax Name Update
$app->post('/v1/pax-name-update/import', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['records']) || !is_array($body['records'])) {
            throw new Exception('records is required and must be an array', 400);
        }
        
        // Get updated_by from request or use default
        $updatedBy = $body['updated_by'] ?? 'pax_name_update_import';
        
        $service = new \App\Services\PaxNameUpdateService();
        $result = $service->importPaxNameUpdate($body['records'], $updatedBy);
        
        return jsonResponse($response, $result, 'success', $result['message'], 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// STOCK PNR UPDATE ENDPOINTS
// ======================================

// POST Preview Stock PNR Update by ID
$app->post('/v1/stock/pnr-update/preview', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['csv_data']) || !is_array($body['csv_data'])) {
            throw new Exception('csv_data is required and must be an array', 400);
        }
        
        $service = new \App\Services\StockService();
        $result = $service->previewStockPnrUpdate($body['csv_data']);
        
        return jsonResponse($response, $result, 'success', 'Preview generated successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST Import Stock PNR Update by ID
$app->post('/v1/stock/pnr-update/import', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['records']) || !is_array($body['records'])) {
            throw new Exception('records is required and must be an array', 400);
        }
        
        // Get updated_by from request or use default
        $updatedBy = $body['updated_by'] ?? 'stock_pnr_update_import';
        
        $service = new \App\Services\StockService();
        $result = $service->importStockPnrUpdate($body['records'], $updatedBy);
        
        return jsonResponse($response, $result, 'success', $result['message'], 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// STOCK UPDATE ENDPOINTS
// ======================================

// POST Preview Stock Update Import
$app->post('/v1/stock/update/preview', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['csv_data']) || !is_array($body['csv_data'])) {
            throw new Exception('csv_data is required and must be an array', 400);
        }
        
        $service = new \App\Services\StockService();
        $result = $service->previewStockUpdate($body['csv_data']);
        
        return jsonResponse($response, $result, 'success', 'Preview generated successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST Import Stock Update
$app->post('/v1/stock/update/import', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['records']) || !is_array($body['records'])) {
            throw new Exception('records is required and must be an array', 400);
        }
        
        // Get updated_by from request or use default
        $updatedBy = $body['updated_by'] ?? 'stock_update_import';
        
        $service = new \App\Services\StockService();
        $result = $service->importStockUpdate($body['records'], $updatedBy);
        
        return jsonResponse($response, $result, 'success', $result['message'], 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// STOCK FLIGHT COLUMN ENDPOINTS
// ======================================

// POST Preview Stock Price Import
$app->post('/v1/stock/price/preview', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['csv_data']) || !is_array($body['csv_data'])) {
            throw new Exception('csv_data is required and must be an array', 400);
        }
        
        $service = new \App\Services\StockService();
        $result = $service->previewStockPrice($body['csv_data']);
        
        return jsonResponse($response, $result, 'success', 'Preview generated successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST Import Stock Price
$app->post('/v1/stock/price/import', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['records']) || !is_array($body['records'])) {
            throw new Exception('records is required and must be an array', 400);
        }
        
        // Validate each record
        foreach ($body['records'] as $record) {
            if (empty($record['auto_id'])) {
                throw new Exception('Each record must have an auto_id', 400);
            }
            if (!isset($record['price'])) {
                throw new Exception('Each record must have a price', 400);
            }
        }
        
        // Get updated_by from request or use default
        $updatedBy = $body['updated_by'] ?? 'stock_price_import';
        
        $service = new \App\Services\StockService();
        $result = $service->importStockPrice($body['records'], $updatedBy);
        
        return jsonResponse($response, $result, 'success', $result['message'], 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// STOCK FLIGHT COLUMN ENDPOINTS
// ======================================

// POST Preview Stock Flight Column Import
$app->post('/v1/stock/flight-column/preview', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['csv_data']) || !is_array($body['csv_data'])) {
            throw new Exception('csv_data is required and must be an array', 400);
        }
        
        $service = new \App\Services\StockService();
        $result = $service->previewStockFlightColumn($body['csv_data']);
        
        return jsonResponse($response, $result, 'success', 'Preview generated successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST Import Stock Flight Column
$app->post('/v1/stock/flight-column/import', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['records']) || !is_array($body['records'])) {
            throw new Exception('records is required and must be an array', 400);
        }
        
        // Validate each record
        foreach ($body['records'] as $record) {
            if (empty($record['auto_id'])) {
                throw new Exception('Each record must have an auto_id', 400);
            }
            if (!isset($record['flight_2'])) {
                throw new Exception('Each record must have a flight_2', 400);
            }
        }
        
        // Get updated_by from request or use default
        $updatedBy = $body['updated_by'] ?? 'stock_flight_import';
        
        $service = new \App\Services\StockService();
        $result = $service->importStockFlightColumn($body['records'], $updatedBy);
        
        return jsonResponse($response, $result, 'success', $result['message'], 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// REFUND PORTAL NAMES ENDPOINTS
// ======================================

// POST Preview Refund Portal Names Import
$app->post('/v1/refund-portal-names/preview', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['csv_data']) || !is_array($body['csv_data'])) {
            throw new Exception('csv_data is required and must be an array', 400);
        }
        
        $service = new \App\Services\RefundPortalNamesService();
        $result = $service->previewRefundPortalNames($body['csv_data']);
        
        return jsonResponse($response, $result, 'success', 'Preview generated successfully', 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST Import Refund Portal Names
$app->post('/v1/refund-portal-names/import', function (Request $request, Response $response) {
    try {
        $body = $request->getParsedBody();
        
        // Validate required field
        if (empty($body['records']) || !is_array($body['records'])) {
            throw new Exception('records is required and must be an array', 400);
        }
        
        // Validate each record
        foreach ($body['records'] as $record) {
            if (empty($record['auto_id'])) {
                throw new Exception('Each record must have an auto_id', 400);
            }
            if (empty($record['accountname'])) {
                throw new Exception('Each record must have an accountname', 400);
            }
        }
        
        $service = new \App\Services\RefundPortalNamesService();
        $result = $service->importRefundPortalNames($body['records']);
        
        return jsonResponse($response, $result, 'success', $result['message'], 200);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PAYMENT CALLBACK ENDPOINTS
// ======================================

// POST process payment callback
$app->post('/v1/payment-callback/process', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Callback data required', 400);
        }
        
        $service = new \App\Services\PaymentCallbackService();
        $result = $service->processCallback($input);
        
        return jsonResponse($response, $result, 'success', 'Payment callback processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment callback history
$app->get('/v1/payment-callback/history/{orderId}', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        
        $service = new \App\Services\PaymentCallbackService();
        $data = $service->getCallbackHistory($orderId);
        
        return jsonResponse($response, $data, 'success', 'Payment history retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST WPT payment success callback (for partial payments)
// Accepts Ref and orderId as query parameters or in POST body
$app->post('/v1/payments/wpt/success', function (Request $request, Response $response) {
    try {
        // Get parameters from query string or POST body
        $params = $request->getQueryParams();
        $body = $request->getParsedBody() ?? [];
        
        $paymentRef = $params['Ref'] ?? $body['Ref'] ?? null;
        $orderId = $params['orderId'] ?? $body['orderId'] ?? null;
        
        if (empty($paymentRef)) {
            return errorResponse($response, 'Payment reference (Ref) is required', 400);
        }
        
        // Extract order ID from payment reference if not provided
        if (empty($orderId)) {
            $orderId = substr($paymentRef, 0, 6);
        }
        
        $service = new \App\Services\PaymentService();
        $result = $service->handleWPTPartialPaymentSuccess($orderId, $paymentRef);
        
        return jsonResponse($response, $result, 'success', 'WPT partial payment success processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST WPT full payment success callback (for full payments)
// Accepts Ref, orderId, and amount as query parameters or in POST body
$app->post('/v1/payments/wpt/success-full', function (Request $request, Response $response) {
    try {
        // Get parameters from query string or POST body
        $params = $request->getQueryParams();
        $body = $request->getParsedBody() ?? [];
        
        $paymentRef = $params['Ref'] ?? $body['Ref'] ?? null;
        $orderId = $params['orderId'] ?? $body['orderId'] ?? null;
        $paymentAmount = $params['amount'] ?? $body['amount'] ?? null;
        
        if (empty($paymentRef)) {
            return errorResponse($response, 'Payment reference (Ref) is required', 400);
        }
        
        // Extract order ID from payment reference if not provided
        if (empty($orderId)) {
            $orderId = substr($paymentRef, 0, 6);
        }
        
        $service = new \App\Services\PaymentService();
        $result = $service->handleWPTFullPaymentSuccess($orderId, $paymentRef, $paymentAmount);
        
        return jsonResponse($response, $result, 'success', 'WPT full payment success processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PRICE WATCH ENDPOINTS
// ======================================

// POST create price watch subscription
$app->post('/v1/price-watch/subscribe', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Subscription data required', 400);
        }
        
        $service = new \App\Services\PriceWatchService();
        $result = $service->createSubscription($input);
        
        return jsonResponse($response, $result, 'success', 'Subscribed successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all price watch subscriptions
$app->get('/v1/price-watch/subscriptions', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'email' => $params['email'] ?? null,
            'from_city' => $params['from_city'] ?? null,
            'to_city' => $params['to_city'] ?? null,
            'limit' => (int)($params['limit'] ?? 100),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\PriceWatchService();
        $data = $service->getAllSubscriptions($filters);
        
        return jsonResponse($response, $data, 'success', 'Subscriptions retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET price watch subscription by ID
$app->get('/v1/price-watch/subscriptions/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\PriceWatchService();
        $data = $service->getSubscriptionById($id);
        
        return jsonResponse($response, $data, 'success', 'Subscription retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// DELETE price watch subscription
$app->delete('/v1/price-watch/subscriptions/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        
        $service = new \App\Services\PriceWatchService();
        $result = $service->deleteSubscription($id);
        
        return jsonResponse($response, $result, 'success', 'Subscription deleted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PROFICIENCY ENDPOINTS
// ======================================

// GET proficiency report by tier
$app->get('/v1/proficiency/report', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['tier']) || empty($params['team']) || 
            empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('tier, team, from_date, and to_date are required', 400);
        }
        
        $service = new \App\Services\ProficiencyService();
        $data = $service->getProficiencyReport(
            $params['tier'], 
            $params['team'], 
            $params['from_date'], 
            $params['to_date']
        );
        
        return jsonResponse($response, $data, 'success', 'Proficiency report generated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET tier rules
$app->get('/v1/proficiency/tier-rules', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ProficiencyService();
        $data = $service->getTierRules();
        
        return jsonResponse($response, $data, 'success', 'Tier rules retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// INTERIM PROFICIENCY ENDPOINTS
// ======================================

// GET interim performance report
$app->get('/v1/proficiency-interim/report', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $team = $params['team'] ?? null;
        $mode = $params['mode'] ?? '10day';
        
        $service = new \App\Services\InterimProficiencyService();
        $data = $service->getInterimReport($team, $params['from_date'], $params['to_date'], $mode);
        
        return jsonResponse($response, $data, 'success', 'Interim report generated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET QA summary
$app->get('/v1/proficiency-interim/qa-summary', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $team = $params['team'] ?? null;
        $mode = $params['mode'] ?? '10day';
        
        $service = new \App\Services\InterimProficiencyService();
        $data = $service->getQASummary($team, $params['from_date'], $params['to_date'], $mode);
        
        return jsonResponse($response, $data, 'success', 'QA summary generated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent summary (after sales)
$app->get('/v1/proficiency-interim/agent-summary-after-sales', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $team = $params['team'] ?? null;
        $mode = $params['mode'] ?? '10day';
        
        $service = new \App\Services\InterimProficiencyService();
        $data = $service->getAgentSummaryAfterSales($team, $params['from_date'], $params['to_date'], $mode);
        
        return jsonResponse($response, $data, 'success', 'Agent summary generated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET performance remarks
$app->get('/v1/proficiency-interim/remarks', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $tsr = $params['tsr'] ?? null;
        
        $service = new \App\Services\InterimProficiencyService();
        $data = $service->getPerformanceRemarks($tsr);
        
        return jsonResponse($response, $data, 'success', 'Performance remarks retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create performance remark
$app->post('/v1/proficiency-interim/remarks', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Remark data required', 400);
        }
        
        $service = new \App\Services\InterimProficiencyService();
        $result = $service->createPerformanceRemark($input);
        
        return jsonResponse($response, $result, 'success', 'Performance remark created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update performance remark
$app->put('/v1/proficiency-interim/remarks/{tsr}/{dateRangeStart}', function (Request $request, Response $response, array $args) {
    try {
        $tsr = $args['tsr'];
        $dateRangeStart = $args['dateRangeStart'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input['remark'])) {
            throw new Exception('Remark is required', 400);
        }
        
        $service = new \App\Services\InterimProficiencyService();
        $result = $service->updatePerformanceRemark($tsr, $dateRangeStart, $input['remark']);
        
        return jsonResponse($response, $result, 'success', 'Performance remark updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// DELETE performance remark
$app->delete('/v1/proficiency-interim/remarks/{tsr}/{dateRangeStart}', function (Request $request, Response $response, array $args) {
    try {
        $tsr = $args['tsr'];
        $dateRangeStart = $args['dateRangeStart'];
        
        $service = new \App\Services\InterimProficiencyService();
        $result = $service->deletePerformanceRemark($tsr, $dateRangeStart);
        
        return jsonResponse($response, $result, 'success', 'Performance remark deleted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET available teams
$app->get('/v1/proficiency-interim/teams', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\InterimProficiencyService();
        $data = $service->getAvailableTeams();
        
        return jsonResponse($response, ['teams' => $data], 'success', 'Teams retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PROFICIENCY REPORT ENDPOINTS
// ======================================

// GET proficiency report
$app->get('/v1/proficiency-report/generate', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $team = $params['team'] ?? null;
        
        $service = new \App\Services\ProficiencyReportService();
        $data = $service->generateReport($team, $params['from_date'], $params['to_date']);
        
        return jsonResponse($response, $data, 'success', 'Proficiency report generated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET available teams
$app->get('/v1/proficiency-report/teams', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ProficiencyReportService();
        $data = $service->getAvailableTeams();
        
        return jsonResponse($response, ['teams' => $data], 'success', 'Teams retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PROJECT MANAGER ENDPOINTS
// ======================================

// GET all projects
$app->get('/v1/project-manager/projects', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ProjectManagerService();
        $data = $service->getAllProjects();
        
        return jsonResponse($response, $data, 'success', 'Projects retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all boards
$app->get('/v1/project-manager/boards', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ProjectManagerService();
        $data = $service->getAllBoards();
        
        return jsonResponse($response, $data, 'success', 'Boards retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET time tracker for task
$app->get('/v1/project-manager/tasks/{taskId}/time-tracker', function (Request $request, Response $response, array $args) {
    try {
        $taskId = $args['taskId'];
        
        $service = new \App\Services\ProjectManagerService();
        $data = $service->getTimeTracker($taskId);
        
        return jsonResponse($response, $data, 'success', 'Time tracker retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create project
$app->post('/v1/project-manager/projects', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Project data required', 400);
        }
        
        $service = new \App\Services\ProjectManagerService();
        $result = $service->createProject($input);
        
        return jsonResponse($response, $result, 'success', 'Project created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create board
$app->post('/v1/project-manager/boards', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Board data required', 400);
        }
        
        $service = new \App\Services\ProjectManagerService();
        $result = $service->createBoard($input);
        
        return jsonResponse($response, $result, 'success', 'Board created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PAYMENT UPLOAD ENDPOINTS
// ======================================

// GET payment information by order ID
$app->get('/v1/payment-upload/order/{orderId}', function (Request $request, Response $response, array $args) {
    try {
        $orderId = (int)$args['orderId'];
        
        $service = new \App\Services\PaymentUploadService();
        $data = $service->getPaymentInfo($orderId);
        
        return jsonResponse($response, $data, 'success', 'Payment information retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST get payment information for multiple order IDs
$app->post('/v1/payment-upload/orders/batch', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $orderIds = $params['order_ids'] ?? [];
        
        if (empty($orderIds) || !is_array($orderIds)) {
            return errorResponse($response, 'order_ids array is required', 400);
        }
        
        $service = new \App\Services\PaymentUploadService();
        $data = $service->getPaymentInfoBatch($orderIds);
        
        return jsonResponse($response, $data, 'success', 'Payment information retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update payment status for an order
$app->put('/v1/payment-upload/order/{orderId}/status', function (Request $request, Response $response, array $args) {
    try {
        $orderId = (int)$args['orderId'];
        $params = $request->getParsedBody();
        
        $status = $params['status'] ?? '';
        
        if (empty($status)) {
            return errorResponse($response, 'status is required', 400);
        }
        
        $service = new \App\Services\PaymentUploadService();
        $data = $service->updatePaymentStatus($orderId, $status);
        
        return jsonResponse($response, $data, 'success', 'Payment status updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update payment status for multiple orders
$app->put('/v1/payment-upload/orders/batch/status', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $orderIds = $params['order_ids'] ?? [];
        $status = $params['status'] ?? '';
        
        if (empty($orderIds) || !is_array($orderIds)) {
            return errorResponse($response, 'order_ids array is required', 400);
        }
        
        if (empty($status)) {
            return errorResponse($response, 'status is required', 400);
        }
        
        $service = new \App\Services\PaymentUploadService();
        $data = $service->updatePaymentStatusBatch($orderIds, $status);
        
        return jsonResponse($response, $data, 'success', 'Payment status updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update payment status for order and payment post (complete update)
$app->put('/v1/payment-upload/order/{orderId}/status/complete', function (Request $request, Response $response, array $args) {
    try {
        $orderId = (int)$args['orderId'];
        $params = $request->getParsedBody();
        
        $status = $params['status'] ?? '';
        
        if (empty($status)) {
            return errorResponse($response, 'status is required', 400);
        }
        
        $service = new \App\Services\PaymentUploadService();
        $data = $service->updatePaymentStatusComplete($orderId, $status);
        
        return jsonResponse($response, $data, 'success', 'Payment status updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// OUTBOUND PAYMENT ENDPOINTS
// ======================================

// POST check IP address access
$app->post('/v1/outbound-payment/check-ip', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $ipAddress = $params['ip_address'] ?? null;
        
        if (empty($ipAddress)) {
            return errorResponse($response, 'ip_address is required', 400);
        }
        
        $service = new \App\Services\OutboundPaymentService();
        $data = $service->checkIpAccess($ipAddress);
        
        return jsonResponse($response, $data, 'success', 'IP access checked successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Outbound Payment Check IP Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET outbound payments with filters
$app->get('/v1/outbound-payment/payments', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $service = new \App\Services\OutboundPaymentService();
        $data = $service->getOutboundPayments($queryParams);
        
        return jsonResponse($response, $data, 'success', 'Outbound payments retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET outbound payment by ID
$app->get('/v1/outbound-payment/payments/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = (int)$args['id'];
        
        $service = new \App\Services\OutboundPaymentService();
        $data = $service->getOutboundPaymentById($id);
        
        return jsonResponse($response, $data, 'success', 'Outbound payment retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create outbound payment request
$app->post('/v1/outbound-payment/payments', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\OutboundPaymentService();
        $data = $service->createOutboundPayment($params);
        
        return jsonResponse($response, $data, 'success', 'Outbound payment request created successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT initial approval
$app->put('/v1/outbound-payment/payments/{id}/initial-approve', function (Request $request, Response $response, array $args) {
    try {
        $id = (int)$args['id'];
        $params = $request->getParsedBody();
        
        $confirmedBy = $params['confirmed_by'] ?? '';
        if (empty($confirmedBy)) {
            return errorResponse($response, 'confirmed_by is required', 400);
        }
        
        $service = new \App\Services\OutboundPaymentService();
        $data = $service->updateInitialApproval($id, $confirmedBy);
        
        return jsonResponse($response, $data, 'success', 'Initial approval granted successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT final approval
$app->put('/v1/outbound-payment/payments/{id}/final-approve', function (Request $request, Response $response, array $args) {
    try {
        $id = (int)$args['id'];
        $params = $request->getParsedBody();
        
        $confirmedBy = $params['confirmed_by'] ?? '';
        $azupayData = $params['azupay_data'] ?? [];
        
        if (empty($confirmedBy)) {
            return errorResponse($response, 'confirmed_by is required', 400);
        }
        
        if (empty($azupayData)) {
            return errorResponse($response, 'azupay_data is required', 400);
        }
        
        $service = new \App\Services\OutboundPaymentService();
        $data = $service->updateFinalApproval($id, $azupayData, $confirmedBy);
        
        return jsonResponse($response, $data, 'success', 'Final approval granted and payment processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT decline payment request
$app->put('/v1/outbound-payment/payments/{id}/decline', function (Request $request, Response $response, array $args) {
    try {
        $id = (int)$args['id'];
        $params = $request->getParsedBody();
        
        $confirmedBy = $params['confirmed_by'] ?? '';
        if (empty($confirmedBy)) {
            return errorResponse($response, 'confirmed_by is required', 400);
        }
        
        $service = new \App\Services\OutboundPaymentService();
        $data = $service->updateDeclinedStatus($id, $confirmedBy);
        
        return jsonResponse($response, $data, 'success', 'Payment request declined successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PAYMENT FOLLOWUP ENDPOINTS
// ======================================

// GET payment followups with filters
$app->get('/v1/payment-followup/followups', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $service = new \App\Services\PaymentFollowupService();
        $data = $service->getPaymentFollowups($queryParams);
        
        return jsonResponse($response, $data, 'success', 'Payment followups retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment followup by order ID
$app->get('/v1/payment-followup/order/{orderId}', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        
        $service = new \App\Services\PaymentFollowupService();
        $data = $service->getPaymentFollowupByOrderId($orderId);
        
        if (!$data) {
            return jsonResponse($response, null, 'success', 'No followup data found for this order');
        }
        
        return jsonResponse($response, $data, 'success', 'Payment followup retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST save payment followup status
$app->post('/v1/payment-followup/status', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\PaymentFollowupService();
        $data = $service->savePaymentFollowupStatus($params);
        
        return jsonResponse($response, $data, 'success', 'Payment followup status saved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET remarks by order ID
$app->get('/v1/payment-followup/order/{orderId}/remarks', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        
        $service = new \App\Services\PaymentFollowupService();
        $data = $service->getRemarksByOrderId($orderId);
        
        return jsonResponse($response, $data, 'success', 'Remarks retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST add remark
$app->post('/v1/payment-followup/remarks', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\PaymentFollowupService();
        $data = $service->addRemark($params);
        
        return jsonResponse($response, $data, 'success', 'Remark added successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PAYMENT MANAGER ENDPOINTS
// ======================================

// GET bookings with payment filters
$app->get('/v1/payment-manager/bookings', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $service = new \App\Services\PaymentManagerService();
        $data = $service->getBookingsWithFilters($queryParams);
        
        return jsonResponse($response, $data, 'success', 'Bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment history by order ID
$app->get('/v1/payment-manager/order/{orderId}/payment-history', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        
        $service = new \App\Services\PaymentManagerService();
        $data = $service->getPaymentHistoryByOrderId($orderId);
        
        return jsonResponse($response, $data, 'success', 'Payment history retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update payment conversation
$app->post('/v1/payment-manager/conversations', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\PaymentManagerService();
        $data = $service->updatePaymentConversation($params);
        
        return jsonResponse($response, $data, 'success', 'Payment conversation updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET booking notes by order ID
$app->get('/v1/payment-manager/order/{orderId}/notes', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        
        $service = new \App\Services\PaymentManagerService();
        $data = $service->getBookingNotesByOrderId($orderId);
        
        return jsonResponse($response, $data, 'success', 'Booking notes retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET matched payments
$app->get('/v1/payment-manager/matched-payments', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $service = new \App\Services\PaymentManagerService();
        $data = $service->getMatchedPayments($queryParams);
        
        return jsonResponse($response, $data, 'success', 'Matched payments retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET non-matched payments
$app->get('/v1/payment-manager/non-matched-payments', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        $service = new \App\Services\PaymentManagerService();
        $data = $service->getNonMatchedPayments($queryParams);
        
        return jsonResponse($response, $data, 'success', 'Non-matched payments retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET orders for 72-hour cancellation
$app->get('/v1/payment-manager/orders-72h-cancellation', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\PaymentManagerService();
        $data = $service->getOrdersFor72HourCancellation();
        
        return jsonResponse($response, $data, 'success', 'Orders for 72-hour cancellation retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PAYMENT STATUS UPDATE ENDPOINTS
// ======================================

// POST validate CSV data
$app->post('/v1/payment-status-update/validate-csv', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $csvRows = $params['csv_rows'] ?? [];
        
        if (empty($csvRows) || !is_array($csvRows)) {
            return errorResponse($response, 'csv_rows array is required', 400);
        }
        
        $service = new \App\Services\PaymentStatusUpdateService();
        $data = $service->validateCsvData($csvRows);
        
        return jsonResponse($response, $data, 'success', 'CSV data validated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update payment statuses from CSV
$app->post('/v1/payment-status-update/update-from-csv', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $validatedRows = $params['validated_rows'] ?? [];
        $updatedBy = $params['updated_by'] ?? 'system';
        
        if (empty($validatedRows) || !is_array($validatedRows)) {
            return errorResponse($response, 'validated_rows array is required', 400);
        }
        
        $service = new \App\Services\PaymentStatusUpdateService();
        $data = $service->updatePaymentStatuses($validatedRows, $updatedBy);
        
        return jsonResponse($response, $data, 'success', 'Payment statuses updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// QUARTERLY PROFICIENCY ENDPOINTS
// ======================================

// GET quarterly proficiency report (uses same service as interim with quarterly intervals)
$app->get('/v1/proficiency-quarterly/report', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $team = $params['team'] ?? null;
        
        $service = new \App\Services\InterimProficiencyService();
        $data = $service->getInterimReport($team, $params['from_date'], $params['to_date'], 'monthly');
        
        return jsonResponse($response, $data, 'success', 'Quarterly report generated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// QUOTE PRICE CHECK ENDPOINTS
// ======================================

// GET recent quotes for price checking
$app->get('/v1/quote-price-check/recent', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $days = (int)($params['days'] ?? 2);
        
        $service = new \App\Services\QuotePriceCheckService();
        $data = $service->getRecentQuotesForPriceCheck($days);
        
        return jsonResponse($response, $data, 'success', 'Recent quotes retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET pending price checks
$app->get('/v1/quote-price-check/pending', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\QuotePriceCheckService();
        $data = $service->getPendingPriceChecks();
        
        return jsonResponse($response, $data, 'success', 'Pending price checks retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST mark quote as price checked
$app->post('/v1/quote-price-check/{quoteId}/mark-checked', function (Request $request, Response $response, array $args) {
    try {
        $quoteId = $args['quoteId'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        $newPrice = $input['new_price'] ?? null;
        
        $service = new \App\Services\QuotePriceCheckService();
        $result = $service->markQuoteAsChecked($quoteId, $newPrice);
        
        return jsonResponse($response, $result, 'success', 'Quote marked as checked');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ROSTER MANAGER ENDPOINTS
// ======================================

// GET all employees
$app->get('/v1/roster-manager/employees', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'emp_name' => $params['emp_name'] ?? null,
            'team' => $params['team'] ?? null,
            'department' => $params['department'] ?? null,
            'month' => $params['month'] ?? null,
            'year' => $params['year'] ?? date('Y')
        ];
        
        $service = new \App\Services\RosterManagerService();
        $data = $service->getAllEmployees($filters);
        
        return jsonResponse($response, $data, 'success', 'Employees retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET employee by ID
$app->get('/v1/roster-manager/employees/{empId}', function (Request $request, Response $response, array $args) {
    try {
        $empId = $args['empId'];
        
        $service = new \App\Services\RosterManagerService();
        $data = $service->getEmployeeById($empId);
        
        return jsonResponse($response, $data, 'success', 'Employee retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET employee timesheet
$app->get('/v1/roster-manager/employees/{empId}/timesheet', function (Request $request, Response $response, array $args) {
    try {
        $empId = $args['empId'];
        $params = $request->getQueryParams();
        
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;
        
        $service = new \App\Services\RosterManagerService();
        $data = $service->getEmployeeTimesheet($empId, $fromDate, $toDate);
        
        return jsonResponse($response, $data, 'success', 'Timesheet retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create employee
$app->post('/v1/roster-manager/employees', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Employee data required', 400);
        }
        
        $service = new \App\Services\RosterManagerService();
        $result = $service->createEmployee($input);
        
        return jsonResponse($response, $result, 'success', 'Employee created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update employee
$app->put('/v1/roster-manager/employees/{empId}', function (Request $request, Response $response, array $args) {
    try {
        $empId = $args['empId'];
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Employee data required', 400);
        }
        
        $service = new \App\Services\RosterManagerService();
        $result = $service->updateEmployee($empId, $input);
        
        return jsonResponse($response, $result, 'success', 'Employee updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create timesheet entry
$app->post('/v1/roster-manager/timesheet', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Timesheet data required', 400);
        }
        
        $service = new \App\Services\RosterManagerService();
        $result = $service->createTimesheetEntry($input);
        
        return jsonResponse($response, $result, 'success', 'Timesheet entry created successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET roster requests
$app->get('/v1/roster-manager/requests', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\RosterManagerService();
        $data = $service->getRosterRequests();
        
        return jsonResponse($response, $data, 'success', 'Roster requests retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SALES REPORT ENDPOINTS
// ======================================

// GET sales dashboard
$app->get('/v1/sales-report/dashboard', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $date = $params['date'] ?? null;
        $team = $params['team'] ?? null;
        $groupBy = $params['group_by'] ?? 'team';
        
        $service = new \App\Services\SalesReportService();
        $data = $service->getSalesDashboard($date, $team, $groupBy);
        
        return jsonResponse($response, $data, 'success', 'Sales dashboard retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET team names
$app->get('/v1/sales-report/teams', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SalesReportService();
        $data = $service->getTeamNames();
        
        return jsonResponse($response, ['teams' => $data], 'success', 'Team names retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET top performers
$app->get('/v1/sales-report/top-performers', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $limit = (int)($params['limit'] ?? 10);
        
        $service = new \App\Services\SalesReportService();
        $data = $service->getTopPerformers($params['from_date'], $params['to_date'], $limit);
        
        return jsonResponse($response, $data, 'success', 'Top performers retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bottom performers
$app->get('/v1/sales-report/bottom-performers', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $limit = (int)($params['limit'] ?? 10);
        
        $service = new \App\Services\SalesReportService();
        $data = $service->getBottomPerformers($params['from_date'], $params['to_date'], $limit);
        
        return jsonResponse($response, $data, 'success', 'Bottom performers retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET export sales data
$app->get('/v1/sales-report/export', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        if (empty($params['from_date']) || empty($params['to_date'])) {
            throw new Exception('from_date and to_date are required', 400);
        }
        
        $team = $params['team'] ?? null;
        
        $service = new \App\Services\SalesReportService();
        $data = $service->exportSalesData($params['from_date'], $params['to_date'], $team);
        
        return jsonResponse($response, $data, 'success', 'Sales data exported successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SKYPAY/SLICEPAY CALLBACK ENDPOINTS
// ======================================

// POST SkyPay post callback (reuses PaymentCallbackService)
$app->post('/v1/skypay-callback/process', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Callback data required', 400);
        }
        
        $service = new \App\Services\PaymentCallbackService();
        $result = $service->processCallback($input);
        
        return jsonResponse($response, $result, 'success', 'SkyPay callback processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET SlicePay thankyou redirect status
$app->get('/v1/slicepay/thankyou/{orderId}', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'];
        $params = $request->getQueryParams();
        
        $service = new \App\Services\PaymentCallbackService();
        $data = $service->getCallbackHistory($orderId);
        
        return jsonResponse($response, $data, 'success', 'Payment status retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ACCOUNT - AIRLINE DEPOSIT LOSS
// ======================================

// GET airline deposit loss records with filters and totals
$app->get('/v1/account/airline-deposit-loss', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AirlineDepositLossService();
        $data = $service->getDepositLossData($params);

        return jsonResponse($response, $data, 'success', 'Airline deposit loss data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ACCOUNT - RECONCILIATION V2
// ======================================

// GET reconciled ticket + booking dataset
$app->get('/v1/account/reconciliation-v2', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AccountReconciliationV2Service();
        $data = $service->getReconciliationData($params);

        return jsonResponse($response, $data, 'success', 'Account reconciliation data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ACCOUNT - TICKET RECONCILIATION
// ======================================

// GET ticket reconciliation dataset
$app->get('/v1/account/ticket-reconciliation', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $service = new \App\Services\TicketReconciliationService();
        $data = $service->getTickets($params);

        return jsonResponse($response, $data, 'success', 'Ticket reconciliation data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET ticket reconciliation history
$app->get('/v1/account/ticket-reconciliation/{autoId}/history', function (Request $request, Response $response, array $args) {
    try {
        $autoId = (int)$args['autoId'];
        $limit = (int)($request->getQueryParams()['limit'] ?? 100);

        $service = new \App\Services\TicketReconciliationService();
        $data = $service->getHistory($autoId, $limit);

        return jsonResponse($response, $data, 'success', 'Ticket history retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST ticket reconciliation remark
$app->post('/v1/account/ticket-reconciliation/{autoId}/remark', function (Request $request, Response $response, array $args) {
    try {
        $autoId = (int)$args['autoId'];
        $payload = json_decode($request->getBody()->getContents(), true) ?? [];

        $service = new \App\Services\TicketReconciliationService();
        $result = $service->addRemark($autoId, $payload);

        return jsonResponse($response, $result, 'success', 'Remark added successfully', 201);

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT ticket reconciliation update
$app->put('/v1/account/ticket-reconciliation/{autoId}', function (Request $request, Response $response, array $args) {
    try {
        $autoId = (int)$args['autoId'];
        $payload = json_decode($request->getBody()->getContents(), true) ?? [];

        $service = new \App\Services\TicketReconciliationService();
        $result = $service->updateTicket($autoId, $payload);

        return jsonResponse($response, $result, 'success', 'Ticket updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment history by order IDs
$app->get('/v1/account/ticket-reconciliation/payments/orders', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $orderIdsParam = $params['order_ids'] ?? $params['order_id'] ?? null;

        $orderIds = [];
        if (is_array($orderIdsParam)) {
            $orderIds = $orderIdsParam;
        } elseif ($orderIdsParam !== null) {
            $orderIds = explode(',', $orderIdsParam);
        }

        $orderIds = array_values(array_filter(array_map('trim', $orderIds), function ($value) {
            return $value !== '';
        }));

        if (empty($orderIds)) {
            throw new Exception('order_ids parameter is required', 400);
        }

        $service = new \App\Services\TicketReconciliationService();
        $data = $service->getOrderPayments($orderIds);

        return jsonResponse($response, $data, 'success', 'Order payments retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment history by profile numbers
$app->get('/v1/account/ticket-reconciliation/payments/profiles', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $profileParam = $params['profile_nos'] ?? $params['profile_no'] ?? null;
        $limit = (int)($params['limit'] ?? 500);

        $profiles = [];
        if (is_array($profileParam)) {
            $profiles = $profileParam;
        } elseif ($profileParam !== null) {
            $profiles = explode(',', $profileParam);
        }

        $profiles = array_values(array_filter(array_map('trim', $profiles), function ($value) {
            return $value !== '';
        }));

        if (empty($profiles)) {
            throw new Exception('profile_nos parameter is required', 400);
        }

        $service = new \App\Services\TicketReconciliationService();
        $data = $service->getProfilePayments($profiles, $limit);

        return jsonResponse($response, $data, 'success', 'Profile payments retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST ticket reconciliation upload verification
$app->post('/v1/account/ticket-reconciliation/upload/check', function (Request $request, Response $response) {
    try {
        $payload = json_decode($request->getBody()->getContents(), true) ?? [];

        $service = new \App\Services\TicketReconciliationUploadService();
        $data = $service->checkRows($payload);

        return jsonResponse($response, $data, 'success', 'Upload rows verified successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST ticket reconciliation upload updates
$app->post('/v1/account/ticket-reconciliation/upload/update', function (Request $request, Response $response) {
    try {
        $payload = json_decode($request->getBody()->getContents(), true) ?? [];

        $service = new \App\Services\TicketReconciliationUploadService();
        $data = $service->updateRows($payload);

        return jsonResponse($response, $data, 'success', 'Upload updates processed successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ACCOUNT - FINANCE CONSOLE
// ======================================

// GET profit & loss report
$app->get('/v1/account/finance/pl', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $service = new \App\Services\FinanceConsoleService();
        $data = $service->getProfitAndLoss($params);

        return jsonResponse($response, $data, 'success', 'Profit & loss data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET balance sheet snapshot
$app->get('/v1/account/finance/balance-sheet', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $asOf = $params['as_of'] ?? null;
        if (empty($asOf)) {
            throw new Exception('as_of parameter is required (YYYY-MM)', 400);
        }

        $service = new \App\Services\FinanceConsoleService();
        $data = $service->getBalanceSheet($asOf);

        return jsonResponse($response, $data, 'success', 'Balance sheet retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET journal entries
$app->get('/v1/account/finance/journal', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $service = new \App\Services\FinanceConsoleService();
        $data = $service->getJournalEntries($params);

        return jsonResponse($response, $data, 'success', 'Journal entries retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ACCOUNT - DASHBOARD
// ======================================

// GET account dashboard summary
$app->get('/v1/account/dashboard/summary', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $service = new \App\Services\AccountDashboardService();
        $data = $service->getSummary($params);

        return jsonResponse($response, $data, 'success', 'Dashboard summary retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment & ticket monthly drilldown
$app->get('/v1/account/dashboard/payment-ticket', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $month = $params['month'] ?? null;
        if (empty($month)) {
            throw new Exception('month parameter is required (YYYY-MM)', 400);
        }

        $service = new \App\Services\AccountDashboardService();
        $data = $service->getPaymentTicketDrilldown($month);

        return jsonResponse($response, $data, 'success', 'Payment & ticket drilldown retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET deferred revenue drilldown
$app->get('/v1/account/dashboard/deferred-revenue', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $month = $params['month'] ?? null;
        if (empty($month)) {
            throw new Exception('month parameter is required (YYYY-MM)', 400);
        }

        $service = new \App\Services\AccountDashboardService();
        $data = $service->getDeferredRevenueDrilldown($month);

        return jsonResponse($response, $data, 'success', 'Deferred revenue drilldown retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET trade debtors drilldown
$app->get('/v1/account/dashboard/trade-debtors', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $orderId = $params['order_id'] ?? null;
        $journalDate = $params['journal_date'] ?? null;
        if (empty($orderId) || empty($journalDate)) {
            throw new Exception('order_id and journal_date parameters are required', 400);
        }

        $service = new \App\Services\AccountDashboardService();
        $data = $service->getTradeDebtorsDrilldown($orderId, $journalDate);

        return jsonResponse($response, $data, 'success', 'Trade debtors drilldown retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET order ledger drilldown
$app->get('/v1/account/dashboard/order-ledger', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $orderId = $params['order_id'] ?? null;
        if (empty($orderId)) {
            throw new Exception('order_id parameter is required', 400);
        }

        $start = $params['start_date'] ?? null;
        $end = $params['end_date'] ?? null;

        $service = new \App\Services\AccountDashboardService();
        $data = $service->getOrderLedger($orderId, $start, $end);

        return jsonResponse($response, $data, 'success', 'Order ledger retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AI CHATBOT ENDPOINTS
// ======================================

// GET assistant routing info
$app->get('/v1/ai-chatbot/assistants', function (Request $request, Response $response) {
    try {
        $assistants = [
            'gauri' => [
                'name' => 'Gauri',
                'type' => 'customer_service',
                'display_name' => 'Customer Service Assistant',
                'session_format' => 'dm_{userId}_gauri'
            ],
            'genie' => [
                'name' => 'Genie',
                'type' => 'ticketing',
                'display_name' => 'Ticketing Assistant',
                'session_format' => 'dm_{userId}_genie'
            ],
            'hema' => [
                'name' => 'Hema',
                'type' => 'hr',
                'display_name' => 'HR Assistant',
                'session_format' => 'dm_{userId}_hema'
            ]
        ];
        
        return jsonResponse($response, [
            'assistants' => $assistants,
            'total_count' => count($assistants)
        ], 'success', 'Assistant routing info retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GENERIC CRUD ENDPOINTS FOR ALL TABLES
// ======================================

// Helper function to create CRUD endpoints for a table
function createCrudEndpoints($app, $route, $serviceClass) {
    // GET all records
    $app->get($route, function (Request $request, Response $response) use ($serviceClass) {
        try {
            $limit = (int)($request->getQueryParams()['limit'] ?? 100);
            $offset = (int)($request->getQueryParams()['offset'] ?? 0);
            $service = new $serviceClass();
            $data = $service->getAll($limit, $offset);
            return jsonResponse($response, $data, 'success', 'Records retrieved successfully');
        } catch (Exception $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            return errorResponse($response, $e->getMessage(), $code);
        }
    });
    
    // GET record by ID
    $app->get($route . '/{id}', function (Request $request, Response $response, array $args) use ($serviceClass) {
        try {
            $id = $args['id'];
            $service = new $serviceClass();
            $data = $service->getById($id);
            return jsonResponse($response, $data, 'success', 'Record retrieved successfully');
        } catch (Exception $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            return errorResponse($response, $e->getMessage(), $code);
        }
    });
    
    // POST create record
    $app->post($route, function (Request $request, Response $response) use ($serviceClass) {
        try {
            $input = json_decode($request->getBody()->getContents(), true);
            if (empty($input)) {
                throw new Exception('Data required', 400);
            }
            $service = new $serviceClass();
            $id = $service->create($input);
            return jsonResponse($response, ['id' => $id], 'success', 'Record created', 201);
        } catch (Exception $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            return errorResponse($response, $e->getMessage(), $code);
        }
    });
    
    // PUT update record
    $app->put($route . '/{id}', function (Request $request, Response $response, array $args) use ($serviceClass) {
        try {
            $id = $args['id'];
            $input = json_decode($request->getBody()->getContents(), true);
            if (empty($input)) {
                throw new Exception('Data required', 400);
            }
            $service = new $serviceClass();
            $service->update($id, $input);
            return jsonResponse($response, ['id' => $id], 'success', 'Record updated');
        } catch (Exception $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            return errorResponse($response, $e->getMessage(), $code);
        }
    });
    
    // DELETE record
    $app->delete($route . '/{id}', function (Request $request, Response $response, array $args) use ($serviceClass) {
        try {
            $id = $args['id'];
            $service = new $serviceClass();
            $service->delete($id);
            return jsonResponse($response, ['id' => $id], 'success', 'Record deleted');
        } catch (Exception $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            return errorResponse($response, $e->getMessage(), $code);
        }
    });
}

// ======================================
// AGENT INCENTIVE ENDPOINTS
// ======================================

// GET frontend incentive data
$app->get('/v1/agent-incentive/frontend-data', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentIncentiveService();
        $data = $service->getFrontendIncentiveData($params);

        return jsonResponse($response, $data, 'success', 'Frontend incentive data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET incentive criteria
$app->get('/v1/agent-incentive/criteria', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentIncentiveService();
        $data = $service->getIncentiveCriteria($params);

        return jsonResponse($response, $data, 'success', 'Incentive criteria retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET 10-day and daily incentive data
$app->get('/v1/agent-incentive/10day-daily-data', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentIncentiveService();
        $data = $service->get10DayAndDailyIncentiveData($params);

        return jsonResponse($response, $data, 'success', '10-day and daily incentive data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent target pathway
$app->get('/v1/agent-incentive/target-pathway', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentIncentiveService();
        $data = $service->getAgentTargetPathway($params);

        return jsonResponse($response, $data, 'success', 'Agent target pathway retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all agent target pathways
$app->get('/v1/agent-incentive/target-pathways', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentIncentiveService();
        $data = $service->getAllAgentTargetPathways($params);

        return jsonResponse($response, $data, 'success', 'Agent target pathways retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST save agent target pathway
$app->post('/v1/agent-incentive/target-pathway', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\AgentIncentiveService();
        $data = $service->saveAgentTargetPathway($params);

        return jsonResponse($response, $data, 'success', 'Agent target pathway saved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CHAMPIONSHIP ENDPOINTS
// ======================================

// GET championship comments
$app->get('/v1/championship/comments', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\ChampionshipService();
        $data = $service->getComments($params);

        return jsonResponse($response, $data, 'success', 'Comments retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST championship comment
$app->post('/v1/championship/comments', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        // Note: In production, you would get user info from WordPress session/auth
        // For now, we'll accept user_id and display_name from request
        $userId = $params['user_id'] ?? 0;
        $displayName = $params['display_name'] ?? 'Anonymous';
        
        if (!$userId) {
            throw new Exception('User ID is required', 400);
        }

        $service = new \App\Services\ChampionshipService();
        $data = $service->postComment($params, $userId, $displayName);

        return jsonResponse($response, $data, 'success', 'Comment posted successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CHATGPT API ENDPOINTS
// ======================================

// GET teams list
$app->get('/v1/chatgpt-api/teams', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\ChatGPTAPIService();
        $data = $service->getTeams($params);

        return jsonResponse($response, $data, 'success', 'Teams retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST save AI results
$app->post('/v1/chatgpt-api/ai-results', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\ChatGPTAPIService();
        $data = $service->saveAIResults($params);

        return jsonResponse($response, $data, 'success', 'AI results saved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET load day rows
$app->get('/v1/chatgpt-api/day-rows', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\ChatGPTAPIService();
        $data = $service->loadDayRows($params);

        return jsonResponse($response, $data, 'success', 'Day rows retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER ANALYSIS ENDPOINTS
// ======================================

// GET customer analysis data
$app->get('/v1/customer/analysis', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\CustomerAnalysisService();
        $data = $service->getCustomerAnalysis($params);

        return jsonResponse($response, $data, 'success', 'Customer analysis retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER AI SUMMARIZE ENDPOINTS
// ======================================

// POST save AI summary
$app->post('/v1/customer/ai-summarize', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\CustomerAISummarizeService();
        $data = $service->saveSummary($params);

        return jsonResponse($response, $data, 'success', 'AI summary saved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// EMPLOYEE SCHEDULE ENDPOINTS
// ======================================

// EMPLOYEE SCHEDULE - GET ALL WITH FILTERS (similar to agent-codes and roster)
$app->get('/v1/employee-schedule', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        // Normalize parameter keys to lowercase for case-insensitive matching
        $params = array_change_key_case($queryParams, CASE_LOWER);
        
        // Extract filters
        $filters = [];
        if (isset($params['emp_id']) && $params['emp_id'] !== '') {
            $filters['emp_id'] = trim($params['emp_id']);
        }
        if (isset($params['employee_name']) && $params['employee_name'] !== '') {
            $filters['employee_name'] = trim($params['employee_name']);
        }
        if (isset($params['department']) && $params['department'] !== '') {
            $filters['department'] = trim($params['department']);
        }
        if (isset($params['role']) && $params['role'] !== '') {
            $filters['role'] = trim($params['role']);
        }
        if (isset($params['gender']) && $params['gender'] !== '') {
            $filters['gender'] = trim($params['gender']);
        }
        if (isset($params['is_locked']) && $params['is_locked'] !== '') {
            $filters['is_locked'] = $params['is_locked'];
        }
        
        // Time slot filters (e.g., 8_00_AM, 9_00_AM, etc.)
        // Column names in DB: 1_00_AM, 4_00_AM, 5_00_AM, 6_00_AM, 7_00_AM, 8_00_AM, 9_00_AM, 10_00_AM, 11_00_AM, 12_00_PM, 1_00_PM, 4_00_PM
        $timeSlots = [
            '1_00_AM', '4_00_AM', '5_00_AM', '6_00_AM',
            '7_00_AM', '8_00_AM', '9_00_AM', '10_00_AM',
            '11_00_AM', '12_00_PM', '1_00_PM', '4_00_PM'
        ];
        
        foreach ($timeSlots as $slot) {
            // Check both lowercase and original case
            $lowerSlot = strtolower($slot);
            if (isset($params[$lowerSlot]) && $params[$lowerSlot] !== '') {
                $filters[$slot] = trim($params[$lowerSlot]);
            } elseif (isset($params[$slot]) && $params[$slot] !== '') {
                $filters[$slot] = trim($params[$slot]);
            }
        }
        
        $limit = (int)($params['limit'] ?? 100);
        $offset = (int)($params['offset'] ?? 0);
        
        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->getAll($limit, $offset, $filters);
        
        return jsonResponse($response, $data, 'success', 'Employee schedule records retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET lock status
$app->get('/v1/employee-schedule/lock', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->getLockStatus();

        return jsonResponse($response, $data, 'success', 'Availability lock status retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST set lock status
$app->post('/v1/employee-schedule/lock', function (Request $request, Response $response) {
    try {
        $payload = json_decode($request->getBody()->getContents(), true) ?? [];
        if (!array_key_exists('is_locked', $payload)) {
            throw new Exception('is_locked is required', 400);
        }

        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->setLockStatus((bool)$payload['is_locked']);

        return jsonResponse($response, $data, 'success', 'Availability lock status updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent by WordPress username
$app->get('/v1/employee-schedule/agent-by-user', function (Request $request, Response $response) {
    try {
        $username = $request->getQueryParams()['wordpress_user_name'] ?? null;
        if (!$username) {
            throw new Exception('wordpress_user_name is required', 400);
        }

        $service = new \App\Services\EmployeeScheduleService();
        $agent = $service->getAgentByWordpressUsername($username);

        return jsonResponse($response, $agent, 'success', 'Agent retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent by sales ID
$app->get('/v1/employee-schedule/agent', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->getAgentBySalesId($params);

        return jsonResponse($response, $data, 'success', 'Agent retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all active agents
$app->get('/v1/employee-schedule/agents', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $filters = [
            'status' => $params['status'] ?? 'active',
            'sales_manager' => $params['sales_manager'] ?? null,
            'team_name' => $params['team_name'] ?? null,
        ];

        if (!empty($params['role_exclude'])) {
            $filters['role_exclude'] = array_map('trim', explode(',', $params['role_exclude']));
        }

        if (!empty($params['team_not'])) {
            $filters['team_not'] = $params['team_not'];
        }

        $service = new \App\Services\EmployeeScheduleService();
        $agents = $service->listAgents($filters);

        return jsonResponse($response, ['agents' => $agents], 'success', 'Agents retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent availability details by employee ID
$app->get('/v1/employee-schedule/agents/{empId}', function (Request $request, Response $response, array $args) {
    try {
        $options = [
            'include_team_members' => filter_var($request->getQueryParams()['include_team_members'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_direct_reports' => filter_var($request->getQueryParams()['include_direct_reports'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->getAvailabilityDetails($args['empId'], $options);

        return jsonResponse($response, $data, 'success', 'Availability details retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET availability for employee
$app->get('/v1/employee-schedule/availability', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->getAvailability($params);

        return jsonResponse($response, $data, 'success', 'Availability retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST save availability
$app->post('/v1/employee-schedule/availability', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->saveAvailability($params);

        return jsonResponse($response, $data, 'success', 'Availability saved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET sales managers
$app->get('/v1/employee-schedule/sales-managers', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EmployeeScheduleService();
        $data = [
            'sales_managers' => $service->listSalesManagers()
        ];

        return jsonResponse($response, $data, 'success', 'Sales managers retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET team names by sales manager
$app->get('/v1/employee-schedule/team-names', function (Request $request, Response $response) {
    try {
        $salesManager = $request->getQueryParams()['sales_manager'] ?? null;
        if (!$salesManager) {
            throw new Exception('sales_manager is required', 400);
        }

        $service = new \App\Services\EmployeeScheduleService();
        $data = [
            'team_names' => $service->listTeamNamesForSalesManager($salesManager)
        ];

        return jsonResponse($response, $data, 'success', 'Team names retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET team members
$app->get('/v1/employee-schedule/team-members', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->getTeamMembers($params);

        return jsonResponse($response, $data, 'success', 'Team members retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET direct reports
$app->get('/v1/employee-schedule/direct-reports', function (Request $request, Response $response) {
    try {
        $managerName = $request->getQueryParams()['manager_name'] ?? null;
        if (!$managerName) {
            throw new Exception('manager_name is required', 400);
        }

        $service = new \App\Services\EmployeeScheduleService();
        $data = [
            'direct_reports' => $service->listDirectReports($managerName)
        ];

        return jsonResponse($response, $data, 'success', 'Direct reports retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// EMPLOYEE PROFILE ENDPOINTS
// ======================================

$app->get('/v1/employee-profile/monthly-performance', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $filters = [
            'month' => $params['month'] ?? null,
            'tsr' => $params['tsr'] ?? null,
        ];

        $service = new \App\Services\EmployeeProfileService();
        $data = [
            'monthly_performance' => $service->getMonthlyPerformance($filters)
        ];

        return jsonResponse($response, $data, 'success', 'Monthly performance retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/employee-profile/daily-performance', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $filters = [
            'month' => $params['month'] ?? null,
            'tsr' => $params['tsr'] ?? null,
        ];

        $service = new \App\Services\EmployeeProfileService();
        $data = [
            'daily_performance' => $service->getDailyPerformance($filters)
        ];

        return jsonResponse($response, $data, 'success', 'Daily performance retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/employee-profile/gaura-miles', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $filters = [
            'tsr' => $params['tsr'] ?? null,
            'limit' => isset($params['limit']) ? (int)$params['limit'] : null,
        ];

        $service = new \App\Services\EmployeeProfileService();
        $data = [
            'transactions' => $service->getGauraMilesTransactions($filters)
        ];

        return jsonResponse($response, $data, 'success', 'Gaura miles transactions retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/employee-profile/fun-facts', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EmployeeProfileService();
        $data = [
            'fun_facts' => $service->getFunFacts()
        ];

        return jsonResponse($response, $data, 'success', 'Fun facts retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GCHAT ACCOUNTS ENDPOINTS
// ======================================

$app->get('/v1/gchat-accounts', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int)$params['limit'] : null;

        $service = new \App\Services\GChatAccountService();
        $accounts = $service->listAccounts(['limit' => $limit]);

        return jsonResponse($response, [
            'accounts' => $accounts,
            'count' => count($accounts),
        ], 'success', 'GChat accounts retrieved successfully');
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/gchat-accounts', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody() ?? [];

        $service = new \App\Services\GChatAccountService();
        $account = $service->createAccount($payload);

        return jsonResponse($response, $account, 'success', 'GChat account log created successfully', 201);
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GTIB AGENT PRODUCTIVITY ENDPOINTS
// ======================================

$app->get('/v1/gtib/agent-productivity', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GTIBAgentProductivityService();
        $data = $service->getReport([
            'start_date' => $params['start_date'] ?? null,
            'team' => $params['team'] ?? null,
            'manager' => $params['manager'] ?? null,
        ]);

        return jsonResponse($response, $data, 'success', 'GTIB agent productivity report generated successfully');
    } catch (Exception $e) {
        $code = getHttpStatusCode($e);
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// HOYTS VOUCHERS ENDPOINTS
// ======================================

$app->get('/v1/hoyts-vouchers/next', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\HoytsVoucherService();
        $voucher = $service->getVoucher([
            'id' => $params['id'] ?? null,
        ]);

        return jsonResponse($response, $voucher, 'success', 'Voucher retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// HOTFILE IATA ENDPOINTS
// ======================================

$app->get('/v1/hotfile-iata/records', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\HotfileIataService();
        $data = $service->list([
            'from_date' => $params['from_date'] ?? null,
            'to_date' => $params['to_date'] ?? null,
        ]);

        return jsonResponse($response, $data, 'success', 'Hotfile records retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/hotfile-iata/import', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody() ?? [];

        $service = new \App\Services\HotfileIataService();
        $result = $service->import($payload);

        return jsonResponse($response, $result, 'success', 'Hotfile records imported successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// LEAVE ROSTER ENDPOINTS
// ======================================

// LEAVE ROSTER - GET ALL WITH FILTERS (similar to agent-codes, roster, employee-schedule)
$app->get('/v1/leave-roster', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        // Normalize parameter keys to lowercase for case-insensitive matching
        $params = array_change_key_case($queryParams, CASE_LOWER);
        
        // Extract filters
        $filters = [];
        if (isset($params['employee_code']) && $params['employee_code'] !== '') {
            $filters['employee_code'] = trim($params['employee_code']);
        }
        if (isset($params['id']) && $params['id'] !== '') {
            $filters['id'] = trim($params['id']);
        }
        if (isset($params['doc_no']) && $params['doc_no'] !== '') {
            $filters['doc_no'] = trim($params['doc_no']);
        }
        if (isset($params['employee_name']) && $params['employee_name'] !== '') {
            $filters['employee_name'] = trim($params['employee_name']);
        }
        if (isset($params['leave_type']) && $params['leave_type'] !== '') {
            $filters['leave_type'] = trim($params['leave_type']);
        }
        if (isset($params['current_status']) && $params['current_status'] !== '') {
            $filters['current_status'] = trim($params['current_status']);
        }
        if (isset($params['from_date']) && $params['from_date'] !== '') {
            $filters['from_date'] = trim($params['from_date']);
        }
        if (isset($params['till_date']) && $params['till_date'] !== '') {
            $filters['till_date'] = trim($params['till_date']);
        }
        
        $limit = (int)($params['limit'] ?? 100);
        $offset = (int)($params['offset'] ?? 0);
        
        $service = new \App\Services\LeaveRosterService();
        $data = $service->getAll($limit, $offset, $filters);
        
        return jsonResponse($response, $data, 'success', 'Leave roster approval records retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/leave-roster/import', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody() ?? [];

        $service = new \App\Services\LeaveRosterService();
        $result = $service->import($payload);

        return jsonResponse($response, $result, 'success', $result['dry_run'] ? 'Leave roster preview generated' : 'Leave roster imported successfully', $result['dry_run'] ? 200 : 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MANUAL BOOKINGS ENDPOINTS
// ======================================

$app->post('/v1/manual-bookings/preview', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody() ?? [];

        $service = new \App\Services\ManualBookingService();
        $result = $service->preview($payload);

        return jsonResponse($response, $result, 'success', 'Manual booking preview generated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/manual-bookings/import', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody() ?? [];

        $service = new \App\Services\ManualBookingService();
        $result = $service->import($payload);

        return jsonResponse($response, $result, 'success', 'Manual bookings imported successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// DC REMARKS ENDPOINTS
// ======================================

$app->get('/v1/dc-remarks', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\DCRemarksService();
        $data = $service->getRemarks($params);

        return jsonResponse($response, $data, 'success', 'DC remarks retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/dc-remarks', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }

        $service = new \App\Services\DCRemarksService();
        $data = $service->createRemark($payload);

        return jsonResponse($response, $data, 'success', 'DC remark created successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// DIWALI INCENTIVE ENDPOINTS
// ======================================

$app->get('/v1/diwali-incentive/daily-performance', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $filters = [
            'from_date' => $params['from_date'] ?? null,
            'to_date' => $params['to_date'] ?? null,
            'team_name' => $params['team_name'] ?? null,
        ];

        $service = new \App\Services\DiwaliIncentiveService();
        $data = $service->getDailyPerformance($filters);

        return jsonResponse($response, $data, 'success', 'Diwali incentive performance retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/diwali-incentive/daily-performance/{date}', function (Request $request, Response $response, array $args) {
    try {
        $date = urldecode($args['date']);
        $teamName = $request->getQueryParams()['team_name'] ?? null;

        $service = new \App\Services\DiwaliIncentiveService();
        $data = $service->getDailyPerformanceByDate($date, $teamName);

        return jsonResponse($response, $data, 'success', 'Diwali incentive performance retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/diwali-incentive/comments', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $filters = [
            'from_date' => $params['from_date'] ?? null,
            'to_date' => $params['to_date'] ?? null,
        ];

        $service = new \App\Services\DiwaliIncentiveService();
        $data = $service->listComments($filters);

        return jsonResponse($response, $data, 'success', 'Diwali incentive comments retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/diwali-incentive/comments', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();

        $service = new \App\Services\DiwaliIncentiveService();
        $data = $service->createComment(is_array($payload) ? $payload : []);

        return jsonResponse($response, $data, 'success', 'Diwali incentive comment created successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// JULY JACKPOT INCENTIVE ENDPOINTS
// ======================================

$app->get('/v1/july-jackpot/monthly-agents', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'start_date' => $params['start_date'] ?? '2025-07-01',
            'end_date' => $params['end_date'] ?? '2025-07-31'
        ];
        
        $service = new \App\Services\JulyJackpotService();
        $data = $service->getMonthlyAgentData($filters);
        
        return jsonResponse($response, $data, 'success', 'Monthly agent data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/july-jackpot/daily-performance', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'start_date' => $params['start_date'] ?? '2025-07-01',
            'end_date' => $params['end_date'] ?? '2025-07-31'
        ];
        
        $service = new \App\Services\JulyJackpotService();
        $data = $service->getDailyPerformanceData($filters);
        
        return jsonResponse($response, $data, 'success', 'Daily performance data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ROCKTOBER INCENTIVE ENDPOINTS
// ======================================

$app->get('/v1/rocktober/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\RocktoberIncentiveService();
        $data = $service->getAgentMetrics($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Rocktober agent metrics retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/rocktober/daily-performance', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\RocktoberIncentiveService();
        $data = $service->getDailyPerformance($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Rocktober daily performance retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// STEPTEMBER INCENTIVE ENDPOINTS
// ======================================

$app->get('/v1/steptember/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SteptemberIncentiveService();
        $data = $service->getAgentMetrics($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Steptember agent metrics retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/steptember/daily-performance', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SteptemberIncentiveService();
        $data = $service->getDailyPerformance($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Steptember daily performance retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// KNOWVEMBER INCENTIVE ENDPOINTS
// ======================================

$app->get('/v1/knowvember/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\KnowvemberIncentiveService();
        $data = $service->getAgentMetrics($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Knowvember incentive agent metrics retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/knowvember/daily-performance', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\KnowvemberIncentiveService();
        $data = $service->getDailyPerformance($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Knowvember incentive daily performance retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// LETTER COACHING SESSION ENDPOINTS
// ======================================

$app->get('/v1/letter-coaching-session/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\LetterCoachingSessionService();
        $data = $service->listAgents();

        return jsonResponse($response, $data, 'success', 'Coaching session agents retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/letter-coaching-session/review', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\LetterCoachingSessionService();
        $data = $service->getReview($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Coaching session review retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MARATHON INCENTIVE ENDPOINTS
// ======================================

$app->get('/v1/marathon/championship/round1', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarathonIncentiveService();
        $data = $service->getCompetition(
            $request->getQueryParams(),
            [
                'start_date' => '2025-07-01',
                'end_date' => '2025-07-31',
                'label' => 'Marathon Championship - Round 1 (July)',
            ]
        );

        return jsonResponse($response, $data, 'success', 'Marathon championship round 1 data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/marathon/championship/round2', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarathonIncentiveService();
        $data = $service->getCompetition(
            $request->getQueryParams(),
            [
                'start_date' => '2025-08-01',
                'end_date' => '2025-08-31',
                'label' => 'Marathon Championship - Round 2 (August)',
            ]
        );

        return jsonResponse($response, $data, 'success', 'Marathon championship round 2 data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/marathon/championship/round3', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarathonIncentiveService();
        $data = $service->getCompetition(
            $request->getQueryParams(),
            [
                'start_date' => '2025-09-01',
                'end_date' => '2025-09-30',
                'label' => 'Marathon Championship - Round 3 (September)',
            ]
        );

        return jsonResponse($response, $data, 'success', 'Marathon championship round 3 data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/marathon/incentive/october', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarathonIncentiveService();
        $data = $service->getCompetition(
            $request->getQueryParams(),
            [
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-31',
                'label' => 'Marathon Incentive - October',
            ]
        );

        return jsonResponse($response, $data, 'success', 'Marathon incentive October data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/marathon/incentive/november', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarathonIncentiveService();
        $data = $service->getCompetition(
            $request->getQueryParams(),
            [
                'start_date' => '2025-11-01',
                'end_date' => '2025-11-30',
                'label' => 'Marathon Incentive - November',
            ]
        );

        return jsonResponse($response, $data, 'success', 'Marathon incentive November data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MARKETING PERFORMANCE ENDPOINTS
// ======================================

$app->get('/v1/marketing/categories', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarketingPerformanceService();
        $data = $service->listCategories($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Marketing categories retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SALES DATA ENDPOINTS
// ======================================

$app->get('/v1/sales/upcoming-seats', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SalesDataService();
        $data = $service->listUpcomingSeats($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Upcoming seat availability retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// OBSERVATION DASHBOARD ENDPOINTS
// ======================================

$app->get('/v1/observation/summary', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ObservationDashboardService();
        $data = $service->getSummary($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Observation dashboard summary retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TICKETING AUDIT REPORT ENDPOINTS
// ======================================

$app->get('/v1/ticketing/audit/issued-detail', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketingAuditReportService();
        $data = $service->getIssuedDetail($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Ticket issued detail retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/ticketing/audit/issued-summary', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketingAuditReportService();
        $data = $service->getTicketIssuedSummary($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Ticket issued summary retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/ticketing/audit/audited-summary', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketingAuditReportService();
        $data = $service->getTicketAuditedSummary($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Ticket audited summary retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/ticketing/audit/audited-detail', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketingAuditReportService();
        $data = $service->getAuditedTickets($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Audited tickets retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/ticketing/audit/name-updates', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketingAuditReportService();
        $data = $service->getNameUpdatesSummary($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Name update summary retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/ticketing/audit/updated-names', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketingAuditReportService();
        $data = $service->getUpdatedNames($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Updated names retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TICKETING DASHBOARD ENDPOINTS
// ======================================

$app->get('/v1/ticketing/dashboard/counts', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketingDashboardService();
        $data = $service->getCounts($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Ticketing dashboard counts retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/ticketing/dashboard/datasets/{dataset}', function (Request $request, Response $response, array $args) {
    try {
        $service = new \App\Services\TicketingDashboardService();
        $data = $service->getDataset($args['dataset'], $request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Ticketing dashboard dataset retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TOTAL MILES ENDPOINTS
// ======================================

$app->get('/v1/total-miles/totals', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TotalMilesService();
        $data = $service->listTotals();

        return jsonResponse($response, $data, 'success', 'Total miles retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/total-miles/transactions', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TotalMilesService();
        $data = $service->listTransactions($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Total miles transactions retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// WHATSAPP MESSAGE ENDPOINTS
// ======================================

$app->post('/v1/whatsapp/messages', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }

        $service = new \App\Services\WhatsAppMessageService();
        $data = $service->sendMessage($payload);

        return jsonResponse($response, $data, 'success', 'WhatsApp message sent successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// VICIDIAL SYNC ENDPOINTS
// ======================================

$app->post('/v1/vici/sync', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }

        $service = new \App\Services\ViciSyncService();
        $data = $service->run($payload);

        return jsonResponse($response, $data, 'success', 'Vicidial sync completed');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// FIT CHECKOUT ENDPOINTS
// ======================================

$app->get('/v1/fit-checkout/billing', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\FitCheckoutService();
        $data = $service->getBillingData($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Billing address retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/fit-checkout/customers', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\FitCheckoutExistingService();
        $data = $service->getCustomers($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Customers retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/fit-checkout/order/book-pay', function (Request $request, Response $response) {
    try {
        $orderId = (int)($request->getQueryParams()['order_id'] ?? 0);
        $service = new \App\Services\FitCheckoutOrderService();
        $data = $service->getBookPayData($orderId);

        return jsonResponse($response, $data, 'success', 'Order data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/fit-checkout/order/verify-pay', function (Request $request, Response $response) {
    try {
        $orderId = (int)($request->getQueryParams()['order_id'] ?? 0);
        $service = new \App\Services\FitCheckoutOrderService();
        $data = $service->getVerifyPayData($orderId);

        return jsonResponse($response, $data, 'success', 'Verification data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/fit-checkout/order/thank-you', function (Request $request, Response $response) {
    try {
        $orderId = (int)($request->getQueryParams()['order_id'] ?? 0);
        $service = new \App\Services\FitCheckoutOrderService();
        $data = $service->getThankYouData($orderId);

        return jsonResponse($response, $data, 'success', 'Thank you data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/fit-checkout/history', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $orderId = (int)($payload['order_id'] ?? 0);
        $metaKey = (string)($payload['meta_key'] ?? '');
        $metaValue = (string)($payload['meta_value'] ?? '');
        $updatedBy = (string)($payload['updated_by'] ?? 'fit_checkout_by_agent');
        $service = new \App\Services\FitCheckoutOrderService();
        $data = $service->logHistory($orderId, $metaKey, $metaValue, $updatedBy);

        return jsonResponse($response, $data, 'success', 'History entry logged successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/fit-checkout/new', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\FitCheckoutNewService();
        $data = $service->storePassengers($payload);

        return jsonResponse($response, $data, 'success', 'Passenger information stored successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT BOOKINGS ENDPOINTS
// ======================================

$app->get('/v1/agent-bookings/payment-methods', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentBookingService();
        $data = $service->listPaymentMethods();

        return jsonResponse($response, $data, 'success', 'Payment methods retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/agent-bookings', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }

        $service = new \App\Services\AgentBookingService();
        $data = $service->createBooking($payload);

        return jsonResponse($response, $data, 'success', 'Agent booking created successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/agent-bookings/stock-product', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $tripCode = $params['trip_code'] ?? '';
        $travelDate = $params['travel_date'] ?? '';

        $service = new \App\Services\AgentBookingService();
        $data = $service->getStockProduct($tripCode, $travelDate);

        return jsonResponse($response, $data, 'success', 'Stock product retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/agent-bookings/last-order', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentBookingService();
        $data = $service->getLastLargeOrder();

        return jsonResponse($response, $data, 'success', 'Last agent booking order retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/agent-bookings/pnr', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $tripCode = $params['trip_code'] ?? '';
        $travelDate = $params['travel_date'] ?? '';

        $service = new \App\Services\AgentBookingService();
        $data = $service->getTripPnr($tripCode, $travelDate);

        return jsonResponse($response, $data, 'success', 'Trip PNR retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/agent-bookings/{autoId}', function (Request $request, Response $response, array $args) {
    try {
        $service = new \App\Services\AgentBookingService();
        $data = $service->getBooking((int)$args['autoId']);

        return jsonResponse($response, $data, 'success', 'Agent booking retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT CALLS ENDPOINTS
// ======================================

$app->get('/v1/agent-calls/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentCallsService();
        $data = $service->listAgents();

        return jsonResponse($response, $data, 'success', 'Agent list retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/agent-calls', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentCallsService();
        $data = $service->listCalls($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Agent calls retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->delete('/v1/agent-calls/local-audio/{callId}', function (Request $request, Response $response, array $args) {
    try {
        $service = new \App\Services\AgentCallsService();
        $data = $service->clearLocalAudio($args['callId']);

        return jsonResponse($response, $data, 'success', 'Local audio paths cleared successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT GDEAL CHECKOUT ENDPOINTS
// ======================================

$app->get('/v1/agent-gdeal/dates', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AgentGdealCheckoutService();
        $data = $service->listDates($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Trip dates retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/agent-gdeal/pricing', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $pricingId = isset($params['pricing_id']) ? (int)$params['pricing_id'] : 0;

        $service = new \App\Services\AgentGdealCheckoutService();
        $data = $service->getPricing($pricingId);

        return jsonResponse($response, $data, 'success', 'Pricing information retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/agent-gdeal/prices', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $pricingId = isset($params['pricing_id']) ? (int)$params['pricing_id'] : 0;
        $categoryId = isset($params['pricing_category_id']) ? (int)$params['pricing_category_id'] : 953;

        $service = new \App\Services\AgentGdealCheckoutService();
        $data = $service->getPriceCategory($pricingId, $categoryId);

        return jsonResponse($response, $data, 'success', 'Price category data retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// REFUND REQUESTS DASHBOARD ENDPOINTS
// ======================================

$app->get('/v1/refund-requests', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\RefundRequestsDashboardService();
        $data = $service->listCases($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Refund requests retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/refund-requests/meta', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\RefundRequestsDashboardService();
        $data = $service->saveMeta($payload);

        return jsonResponse($response, $data, 'success', 'Meta value saved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// BOM ROSTER APPROVAL ENDPOINTS
// ======================================

$app->get('/v1/bom/roster-requests', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BomRosterApprovalService();
        $data = $service->listRequests($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Roster requests retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/bom/roster-requests/sales-managers', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BomRosterApprovalService();
        $data = $service->listSalesManagers();

        return jsonResponse($response, $data, 'success', 'Sales managers retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/bom/roster-requests/leaves', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BomRosterApprovalService();
        $data = $service->listLeaveRequests($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Leave requests retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/bom/roster-requests/decision', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\BomRosterApprovalService();
        $data = $service->decide($payload);

        return jsonResponse($response, $data, 'success', 'Roster request updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// BOM ROSTER OVERVIEW ENDPOINTS
// ======================================

$app->get('/v1/bom/roster-overview', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BomRosterOverviewService();
        $data = $service->getOverview($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Roster overview retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/bom/roster-overview/employee-shift', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\BomRosterOverviewService();
        $data = $service->updateEmployeeShift($payload);

        return jsonResponse($response, $data, 'success', 'Employee shift updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/bom/roster-overview/month-update', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\BomRosterOverviewService();
        $data = $service->bulkUpdate($payload);

        return jsonResponse($response, $data, 'success', 'Roster month updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SCHEDULE CHANGE CASES ENDPOINTS
// ======================================

$app->get('/v1/schedule-change/cases', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ScheduleChangeCasesService();
        $data = $service->listCases($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Schedule change cases retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/schedule-change/cases', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\ScheduleChangeCasesService();
        $data = $service->addCase($payload);

        return jsonResponse($response, $data, 'success', 'Schedule change case created successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->patch('/v1/schedule-change/cases/{id}', function (Request $request, Response $response, array $args) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $payload['id'] = $args['id'] ?? null;
        $service = new \App\Services\ScheduleChangeCasesService();
        $data = $service->updateCase($payload);

        return jsonResponse($response, $data, 'success', 'Schedule change case updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/schedule-change/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ScheduleChangeCasesService();
        $data = $service->listAgents();

        return jsonResponse($response, $data, 'success', 'Agents retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TICKETED REVIEW ENDPOINTS
// ======================================

$app->get('/v1/ticketed-review/summary', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketedReviewService();
        $data = $service->getSummary($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Ticketed review summary retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/ticketed-review/details', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketedReviewService();
        $data = $service->getAgentDetails($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Ticketed review details retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/ticketed-review/agents', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TicketedReviewService();
        $data = $service->listAgents();

        return jsonResponse($response, $data, 'success', 'Ticketed review agents retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// TRAMS MATCHED RECORDS ENDPOINTS
// ======================================

$app->post('/v1/trams-matched/check', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\TramsMatchedRecordsService();
        $data = $service->checkInvoices($payload);

        return jsonResponse($response, $data, 'success', 'Invoice existence checked successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/trams-matched/update', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\TramsMatchedRecordsService();
        $data = $service->updateInvoice($payload);

        return jsonResponse($response, $data, 'success', 'Invoice updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/trams-matched/invoices', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\TramsMatchedRecordsService();
        $data = $service->listInvoices($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Invoices retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// BOOKING COUNT REPORT ENDPOINTS
// ======================================

$app->get('/v1/booking-count/summary', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BookingCountReportService();
        $data = $service->getReport($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Booking count summary retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// BOOKING PAX LOOKUP ENDPOINTS
// ======================================

$app->get('/v1/bookings/pax-search', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\BookingPaxService();
        $data = $service->searchByPhone($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Passengers retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// HUBSPOT CALL DATA ENDPOINTS
// ======================================

$app->get('/v1/hubspot/calls', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\HubspotCallDataService();
        $data = $service->getCallEmailMappings($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Call data prepared successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// EOD SALES REPORT ENDPOINTS
// ======================================

$app->get('/v1/eod-sales/teams', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EodSalesReportService();
        $data = $service->listTeams();

        return jsonResponse($response, $data, 'success', 'Teams retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/eod-sales/report', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EodSalesReportService();
        $data = $service->getReport($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'EOD sales report generated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ESCALATION PORTAL ENDPOINTS
// ======================================

$app->get('/v1/escalations', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\EscalationPortalService();
        $data = $service->listEscalations($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Escalations retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/escalations', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\EscalationPortalService();
        $data = $service->createEscalation($payload);

        return jsonResponse($response, $data, 'success', 'Escalation created successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/escalations/{id}', function (Request $request, Response $response, array $args) {
    try {
        $service = new \App\Services\EscalationPortalService();
        $data = $service->getEscalation((int)$args['id']);

        return jsonResponse($response, $data, 'success', 'Escalation retrieved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/escalations/{id}/chat', function (Request $request, Response $response, array $args) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\EscalationPortalService();
        $data = $service->addChat((int)$args['id'], $payload);

        return jsonResponse($response, $data, 'success', 'Escalation chat added successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/escalations/{id}/assign', function (Request $request, Response $response, array $args) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\EscalationPortalService();
        $data = $service->assignEscalation((int)$args['id'], $payload);

        return jsonResponse($response, $data, 'success', 'Escalation assignment updated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/escalations/{id}/close', function (Request $request, Response $response, array $args) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }
        $service = new \App\Services\EscalationPortalService();
        $data = $service->closeEscalation((int)$args['id'], $payload);

        return jsonResponse($response, $data, 'success', 'Escalation closed successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// FIT DIRECT CHECKOUT ENDPOINTS
// ======================================

$app->post('/v1/fit-direct-checkout/url', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }

        $service = new \App\Services\FitDirectCheckoutService();
        $data = $service->createShortUrl($payload);

        return jsonResponse($response, $data, 'success', 'Short URL created successfully', 201);
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->get('/v1/fit-direct-checkout/url', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\FitDirectCheckoutService();
        $data = $service->resolveShortUrl($request->getQueryParams());

        return jsonResponse($response, $data, 'success', 'Short URL resolved successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/fit-direct-checkout/sms', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }

        $service = new \App\Services\FitDirectCheckoutService();
        $data = $service->sendSms($payload);

        return jsonResponse($response, $data, 'success', 'SMS sent successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// YPSILON BOOKINGS ENDPOINTS
// ======================================

$app->get('/v1/ypsilon/ibe-url', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        $currentUrl = $queryParams['url'] ?? '';
        
        if (empty($currentUrl)) {
            throw new Exception('URL parameter is required', 400);
        }
        
        $service = new \App\Services\YpsilonBookingService();
        $data = $service->generateIbeUrl($currentUrl);
        
        // Return format expected by tpl_ypsilon_booking_confirmation.php: {"url": "..."}
        return jsonResponse($response, ['url' => $data['ibe_url']], 'success', 'Ypsilon IBE URL generated successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

$app->post('/v1/ypsilon/bookings/sync', function (Request $request, Response $response) {
    try {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode($request->getBody()->getContents(), true) ?: [];
        }

        $service = new \App\Services\YpsilonBookingService();
        $data = $service->syncBooking($payload);

        return jsonResponse($response, $data, 'success', 'Ypsilon booking synchronised successfully');
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET employee schedules
$app->get('/v1/employee-schedule/schedules', function (Request $request, Response $response) {
    try {
        $queryParams = $request->getQueryParams();
        
        // Normalize parameter keys to lowercase for case-insensitive matching
        $params = array_change_key_case($queryParams, CASE_LOWER);
        
        $filters = [
            'emp_id' => $params['emp_id'] ?? null,
            'department' => $params['department'] ?? null,
            'limit' => (int)($params['limit'] ?? 100),
            'offset' => (int)($params['offset'] ?? 0)
        ];
        
        $service = new \App\Services\EmployeeScheduleService();
        $data = $service->getEmployeeSchedules($filters);
        
        return jsonResponse($response, $data, 'success', 'Employee schedules retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SMS DISPATCHER ENDPOINTS
// ======================================

// GET check if SMS was sent today
$app->get('/v1/sms-dispatcher/check-sent', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $type = $params['type'] ?? '';
        $phone = $params['phone'] ?? '';
        
        if (empty($type) || empty($phone)) {
            return errorResponse($response, 'Type and phone are required', 400);
        }
        
        $service = new \App\Services\SmsDispatcherService();
        $wasSent = $service->checkSmsSentToday($type, $phone);
        
        return jsonResponse($response, [
            'type' => $type,
            'phone' => $phone,
            'was_sent_today' => $wasSent
        ], 'success', 'SMS check completed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST log SMS history
$app->post('/v1/sms-dispatcher/log-history', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $orderId = $params['order_id'] ?? '';
        $message = $params['message'] ?? '';
        $phone = $params['phone'] ?? '';
        $source = $params['source'] ?? 'TransmitSMS';
        $messageId = $params['message_id'] ?? '';
        $addedBy = $params['added_by'] ?? 'system';
        $type = $params['type'] ?? '';
        
        if (empty($orderId) || empty($message) || empty($phone) || empty($type)) {
            return errorResponse($response, 'order_id, message, phone, and type are required', 400);
        }
        
        $service = new \App\Services\SmsDispatcherService();
        $data = $service->logSmsHistory($orderId, $message, $phone, $source, $messageId, $addedBy, $type);
        
        return jsonResponse($response, $data, 'success', 'SMS history logged successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SMS PORTAL ENDPOINTS
// ======================================

// GET mobile numbers by list ID
$app->get('/v1/sms-portal/lists/{listId}/numbers', function (Request $request, Response $response, $args) {
    try {
        $listId = (int)$args['listId'];
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->getMobileNumbersByListId($listId);
        
        return jsonResponse($response, $data, 'success', 'Mobile numbers retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST get phone numbers by order IDs
$app->post('/v1/sms-portal/orders/phone-numbers', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $orderIds = $params['order_ids'] ?? [];
        
        if (empty($orderIds) || !is_array($orderIds)) {
            return errorResponse($response, 'order_ids array is required', 400);
        }
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->getPhoneNumbersByOrderIds($orderIds);
        
        return jsonResponse($response, $data, 'success', 'Phone numbers retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all SMS templates
$app->get('/v1/sms-portal/templates', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SmsPortalService();
        $data = $service->getAllSmsTemplates();
        
        return jsonResponse($response, $data, 'success', 'SMS templates retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET SMS templates by type
$app->get('/v1/sms-portal/templates/by-type', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SmsPortalService();
        $data = $service->getSmsTemplatesByType();
        
        return jsonResponse($response, $data, 'success', 'SMS templates by type retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all SMS contact lists
$app->get('/v1/sms-portal/contact-lists', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SmsPortalService();
        $data = $service->getAllSmsContactLists();
        
        return jsonResponse($response, $data, 'success', 'SMS contact lists retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create batch tracking
$app->post('/v1/sms-portal/batch-tracking', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $message = $params['message'] ?? '';
        $totalChunks = (int)($params['total_chunks'] ?? 0);
        $delayMinutes = (int)($params['delay_minutes'] ?? 0);
        $chunkSize = (int)($params['chunk_size'] ?? 500);
        $totalNumbers = (int)($params['total_numbers'] ?? 0);
        $createdBy = $params['created_by'] ?? 'system';
        
        if (empty($message) || $totalChunks <= 0 || $totalNumbers <= 0) {
            return errorResponse($response, 'message, total_chunks, and total_numbers are required', 400);
        }
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->createBatchTracking($message, $totalChunks, $delayMinutes, $chunkSize, $totalNumbers, $createdBy);
        
        return jsonResponse($response, $data, 'success', 'Batch tracking created successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET batch tracking by batch ID
$app->get('/v1/sms-portal/batch-tracking/{batchId}', function (Request $request, Response $response, $args) {
    try {
        $batchId = $args['batchId'];
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->getBatchTrackingByBatchId($batchId);
        
        return jsonResponse($response, $data, 'success', 'Batch tracking retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update batch status
$app->put('/v1/sms-portal/batch-tracking/{batchId}/status', function (Request $request, Response $response, $args) {
    try {
        $batchId = $args['batchId'];
        $params = $request->getParsedBody();
        
        $status = $params['status'] ?? '';
        $successfulChunks = isset($params['successful_chunks']) ? (int)$params['successful_chunks'] : null;
        $failedChunks = isset($params['failed_chunks']) ? (int)$params['failed_chunks'] : null;
        
        if (empty($status)) {
            return errorResponse($response, 'status is required', 400);
        }
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->updateBatchStatus($batchId, $status, $successfulChunks, $failedChunks);
        
        return jsonResponse($response, $data, 'success', 'Batch status updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create chunk log
$app->post('/v1/sms-portal/chunk-logs', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $batchId = $params['batch_id'] ?? '';
        $chunkNumber = (int)($params['chunk_number'] ?? 0);
        $mobileNumbers = $params['mobile_numbers'] ?? [];
        $scheduledTime = $params['scheduled_time'] ?? null;
        
        if (empty($batchId) || $chunkNumber <= 0 || empty($mobileNumbers)) {
            return errorResponse($response, 'batch_id, chunk_number, and mobile_numbers are required', 400);
        }
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->insertChunkLog($batchId, $chunkNumber, $mobileNumbers, $scheduledTime);
        
        return jsonResponse($response, $data, 'success', 'Chunk log created successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET chunk logs by batch ID
$app->get('/v1/sms-portal/batch-tracking/{batchId}/chunk-logs', function (Request $request, Response $response, $args) {
    try {
        $batchId = $args['batchId'];
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->getChunkLogsByBatchId($batchId);
        
        return jsonResponse($response, $data, 'success', 'Chunk logs retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update chunk status
$app->put('/v1/sms-portal/chunk-logs/{batchId}/{chunkNumber}/status', function (Request $request, Response $response, $args) {
    try {
        $batchId = $args['batchId'];
        $chunkNumber = (int)$args['chunkNumber'];
        $params = $request->getParsedBody();
        
        $status = $params['status'] ?? '';
        $messageId = $params['message_id'] ?? null;
        $errorMessage = $params['error_message'] ?? null;
        $responseData = isset($params['response_data']) ? json_encode($params['response_data']) : null;
        
        if (empty($status)) {
            return errorResponse($response, 'status is required', 400);
        }
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->updateChunkStatus($batchId, $chunkNumber, $status, $messageId, $errorMessage, $responseData);
        
        return jsonResponse($response, $data, 'success', 'Chunk status updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST log SMS history
$app->post('/v1/sms-portal/sms-history', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $orderId = $params['order_id'] ?? '';
        $message = $params['message'] ?? '';
        $phone = $params['phone'] ?? '';
        $source = $params['source'] ?? 'TransmitSMS';
        $messageId = $params['message_id'] ?? '';
        $addedBy = $params['added_by'] ?? 'system';
        
        if (empty($orderId) || empty($message) || empty($phone)) {
            return errorResponse($response, 'order_id, message, and phone are required', 400);
        }
        
        $service = new \App\Services\SmsPortalService();
        $data = $service->logSmsHistory($orderId, $message, $phone, $source, $messageId, $addedBy);
        
        return jsonResponse($response, $data, 'success', 'SMS history logged successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MARKET DATA ENDPOINTS
// ======================================

// GET metadata (distinct values for filters)
$app->get('/v1/market-data/meta', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarketDataService();
        $data = $service->getMetadata();

        return jsonResponse($response, $data, 'success', 'Metadata retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET market trend rows
$app->get('/v1/market-data/trend', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketDataService();
        $data = $service->getMarketTrendRows($params);

        return jsonResponse($response, $data, 'success', 'Market trend data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AGENT PERFORMANCE VIEW ENDPOINTS
// ======================================

// GET championship/floor data
$app->get('/v1/agent-performance/championship', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->getChampionshipData($params);

        return jsonResponse($response, $data, 'success', 'Championship data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent bookings
$app->get('/v1/agent-performance/bookings', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->getAgentBookings($params);

        return jsonResponse($response, $data, 'success', 'Agent bookings retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent working time
$app->get('/v1/agent-performance/working-time', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->getAgentWorkingTime($params);

        return jsonResponse($response, $data, 'success', 'Agent working time retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent FCS calls
$app->get('/v1/agent-performance/fcs-calls', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->getAgentFCSCalls($params);

        return jsonResponse($response, $data, 'success', 'Agent FCS calls retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent detail
$app->get('/v1/agent-performance/detail', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->getAgentDetail($params);

        return jsonResponse($response, $data, 'success', 'Agent detail retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET agent 10-day view
$app->get('/v1/agent-performance/10-day-view', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->getAgent10DayView($params);

        return jsonResponse($response, $data, 'success', 'Agent 10-day view data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET remarks
$app->get('/v1/agent-performance/remarks', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->getRemarks($params);

        return jsonResponse($response, $data, 'success', 'Remarks retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST add remark
$app->post('/v1/agent-performance/remarks', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->addRemark($params);

        return jsonResponse($response, $data, 'success', 'Remark added successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET observation dashboard data
$app->get('/v1/agent-performance/observation', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AgentPerformanceService();
        $data = $service->getObservationData($params);

        return jsonResponse($response, $data, 'success', 'Observation data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MARKETING ENDPOINTS
// ======================================

// GET monthly marketing data
$app->get('/v1/marketing/monthly-data', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketingService();
        $data = $service->getMonthlyData($params);

        return jsonResponse($response, $data, 'success', 'Monthly marketing data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET 10-day marketing data
$app->get('/v1/marketing/10-day-data', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketingService();
        $data = $service->get10DayData($params);

        return jsonResponse($response, $data, 'success', '10-day marketing data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET 10-day data by source
$app->get('/v1/marketing/10-day-data-by-source', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketingService();
        $data = $service->get10DayDataBySource($params);

        return jsonResponse($response, $data, 'success', '10-day marketing data by source retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET monthly comparison data
$app->get('/v1/marketing/monthly-comparison', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketingService();
        $data = $service->getMonthlyComparison($params);

        return jsonResponse($response, $data, 'success', 'Monthly comparison data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET marketing remarks
$app->get('/v1/marketing/remarks', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketingService();
        $data = $service->fetchRemarks($params);

        return jsonResponse($response, $data, 'success', 'Marketing remarks retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST marketing remark
$app->post('/v1/marketing/remarks', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\MarketingService();
        $data = $service->insertRemark($params);

        return jsonResponse($response, $data, 'success', 'Marketing remark inserted successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET comparison remarks
$app->get('/v1/marketing/comparison-remarks', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketingService();
        $data = $service->fetchComparisonRemarks($params);

        return jsonResponse($response, $data, 'success', 'Comparison remarks retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Google Ads campaign data
$app->get('/v1/marketing/google-ads/campaigns', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketingService();
        $data = $service->getGoogleAdsCampaignData($params);

        return jsonResponse($response, $data, 'success', 'Google Ads campaign data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET Google Ads ad group data
$app->get('/v1/marketing/google-ads/ad-groups', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\MarketingService();
        $data = $service->getGoogleAdsAdGroupData($params);

        return jsonResponse($response, $data, 'success', 'Google Ads ad group data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all campaigns
$app->get('/v1/marketing/campaigns', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarketingService();
        $data = $service->getAllCampaigns();

        return jsonResponse($response, $data, 'success', 'Campaigns retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET campaign by ID
$app->get('/v1/marketing/campaigns/{campaignId}', function (Request $request, Response $response, $args) {
    try {
        $campaignId = $args['campaignId'] ?? null;
        if (empty($campaignId)) {
            return errorResponse($response, 'campaign_id is required', 400);
        }

        $service = new \App\Services\MarketingService();
        $data = $service->getCampaignById($campaignId);

        if ($data === null) {
            return errorResponse($response, 'Campaign not found', 404);
        }

        return jsonResponse($response, $data, 'success', 'Campaign retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET campaigns by group ID
$app->get('/v1/marketing/campaigns/by-group/{groupId}', function (Request $request, Response $response, $args) {
    try {
        $groupId = $args['groupId'] ?? null;
        if (empty($groupId)) {
            return errorResponse($response, 'group_id is required', 400);
        }

        $service = new \App\Services\MarketingService();
        $data = $service->getCampaignsByGroupId($groupId);

        return jsonResponse($response, $data, 'success', 'Campaigns retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create campaign
$app->post('/v1/marketing/campaigns', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\MarketingService();
        $data = $service->createCampaign($params);

        return jsonResponse($response, $data, 'success', 'Campaign created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update campaign field
$app->post('/v1/marketing/campaigns/{campaignId}/update-field', function (Request $request, Response $response, $args) {
    try {
        $campaignId = $args['campaignId'] ?? null;
        $params = $request->getParsedBody();

        if (empty($campaignId)) {
            return errorResponse($response, 'campaign_id is required', 400);
        }

        $field = $params['field'] ?? null;
        $value = $params['value'] ?? null;

        if (empty($field)) {
            return errorResponse($response, 'field is required', 400);
        }

        $service = new \App\Services\MarketingService();
        $data = $service->updateCampaignField($campaignId, $field, $value);

        return jsonResponse($response, $data, 'success', 'Campaign updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET campaign change log
$app->get('/v1/marketing/campaigns/{campaignId}/change-log', function (Request $request, Response $response, $args) {
    try {
        $campaignId = $args['campaignId'] ?? null;
        if (empty($campaignId)) {
            return errorResponse($response, 'campaign_id is required', 400);
        }

        $service = new \App\Services\MarketingService();
        $data = $service->getCampaignChangeLog($campaignId);

        return jsonResponse($response, $data, 'success', 'Campaign change log retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create campaign change log entry
$app->post('/v1/marketing/campaigns/change-log', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\MarketingService();
        $data = $service->createCampaignChangeLog($params);

        return jsonResponse($response, $data, 'success', 'Change log entry created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all channels
$app->get('/v1/marketing/channels', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarketingService();
        $data = $service->getAllChannels();

        return jsonResponse($response, $data, 'success', 'Channels retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET groups by channel ID
$app->get('/v1/marketing/channels/{channelId}/groups', function (Request $request, Response $response, $args) {
    try {
        $channelId = $args['channelId'] ?? null;
        if (empty($channelId)) {
            return errorResponse($response, 'channel_id is required', 400);
        }

        $service = new \App\Services\MarketingService();
        $data = $service->getGroupsByChannelId($channelId);

        return jsonResponse($response, $data, 'success', 'Groups retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all change types
$app->get('/v1/marketing/change-types', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\MarketingService();
        $data = $service->getAllChangeTypes();

        return jsonResponse($response, $data, 'success', 'Change types retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// MONTHLY REPORT ENDPOINTS
// ======================================

// POST update FCS inbound call
$app->post('/v1/monthly-report/fcs-inbound-call', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\MonthlyReportService();
        $data = $service->updateFCSInboundCall($params);

        return jsonResponse($response, $data, 'success', 'FCS inbound call data updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PROJECT ENDPOINTS
// ======================================

// GET departments
$app->get('/v1/project/departments', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ProjectService();
        $data = $service->getDepartments();

        return jsonResponse($response, $data, 'success', 'Departments retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET members
$app->get('/v1/project/members', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ProjectService();
        $data = $service->getMembers();

        return jsonResponse($response, $data, 'success', 'Members retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET project months
$app->get('/v1/project/months', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ProjectService();
        $data = $service->getProjectMonths();

        return jsonResponse($response, $data, 'success', 'Project months retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET projects
$app->get('/v1/project/projects', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\ProjectService();
        $data = $service->getProjects($params);

        return jsonResponse($response, $data, 'success', 'Projects retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET tasks
$app->get('/v1/project/tasks', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\ProjectService();
        $data = $service->getTasks($params);

        return jsonResponse($response, $data, 'success', 'Tasks retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET single task
$app->get('/v1/project/tasks/{id}', function (Request $request, Response $response, $args) {
    try {
        $params = array_merge($request->getQueryParams(), ['id' => $args['id']]);

        $service = new \App\Services\ProjectService();
        $data = $service->getTask($params);

        return jsonResponse($response, $data, 'success', 'Task retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create task
$app->post('/v1/project/tasks', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\ProjectService();
        $data = $service->createTask($params);

        return jsonResponse($response, $data, 'success', 'Task created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update task
$app->post('/v1/project/tasks/update', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\ProjectService();
        $data = $service->updateTask($params);

        return jsonResponse($response, $data, 'success', 'Task updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update task status
$app->post('/v1/project/tasks/status', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\ProjectService();
        $data = $service->updateTaskStatus($params);

        return jsonResponse($response, $data, 'success', 'Task status updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create project
$app->post('/v1/project/projects', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\ProjectService();
        $data = $service->createProject($params);

        return jsonResponse($response, $data, 'success', 'Project created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update project
$app->post('/v1/project/projects/update', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\ProjectService();
        $data = $service->updateProject($params);

        return jsonResponse($response, $data, 'success', 'Project updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET KPIs
$app->get('/v1/project/kpis', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ProjectService();
        $data = $service->getKPIs();

        return jsonResponse($response, $data, 'success', 'KPIs retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// QA REPORT ENDPOINTS
// ======================================

// GET filter options
$app->get('/v1/qa-report/filters', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $location = $params['location'] ?? null;

        $service = new \App\Services\QAReportService();
        $data = $service->getFilters($location);

        return jsonResponse($response, $data, 'success', 'Filter options retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET auditor view data
$app->get('/v1/qa-report/auditor-view', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\QAReportService();
        $data = $service->getAuditorViewData($params);

        return jsonResponse($response, $data, 'success', 'Auditor view data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET after-sales auditor view data
$app->get('/v1/qa-report/after-sales-auditor-view', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\QAReportService();
        $data = $service->getAfterSalesAuditorViewData($params);

        return jsonResponse($response, $data, 'success', 'After-sales auditor view data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET after-sales agent view data
$app->get('/v1/qa-report/after-sales-agent-view', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\QAReportService();
        $data = $service->getAfterSalesAgentViewData($params);

        return jsonResponse($response, $data, 'success', 'After-sales agent view data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// SALES PERFORMANCE ENDPOINTS
// ======================================

// GET sales dashboard data
$app->get('/v1/sales-performance/dashboard', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\SalesPerformanceService();
        $data = $service->getSalesData($params);

        return jsonResponse($response, $data, 'success', 'Sales dashboard data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET revenue data
$app->get('/v1/sales-performance/revenue', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\SalesPerformanceService();
        $data = $service->getRevenueData($params);

        return jsonResponse($response, $data, 'success', 'Revenue data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET advance purchase booking data
$app->get('/v1/sales-performance/advance-purchase-booking', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\SalesPerformanceService();
        $data = $service->getAdvancePurchaseBookingData($params);

        return jsonResponse($response, $data, 'success', 'Advance purchase booking data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET materialization months
$app->get('/v1/sales-performance/materialization/months', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SalesPerformanceService();
        $data = $service->getMaterializationMonths();

        return jsonResponse($response, $data, 'success', 'Materialization months retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET materialization data
$app->get('/v1/sales-performance/materialization', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $travelMonth = $params['travelMonth'] ?? null;

        $service = new \App\Services\SalesPerformanceService();
        $data = $service->getMaterializationData($travelMonth);

        return jsonResponse($response, $data, 'success', 'Materialization data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET pricing velocity data
$app->get('/v1/sales-performance/pricing-velocity', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SalesPerformanceService();
        $data = $service->getPricingVelocityData();

        return jsonResponse($response, $data, 'success', 'Pricing velocity data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST AI summarize
$app->post('/v1/sales-performance/ai-summarize', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\SalesPerformanceService();
        $summaryId = $service->saveAISummary($params);

        if ($summaryId === null) {
            return errorResponse($response, 'Failed to save AI summary', 500);
        }

        return jsonResponse($response, ['summary_id' => $summaryId], 'success', 'AI summary saved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CHILD ORDER ENDPOINTS
// ======================================

// GET child orders
$app->get('/v1/child-order', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\ChildOrderService();
        $data = $service->getChildOrders($params);

        return jsonResponse($response, $data, 'success', 'Child orders retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CLIENT BASE MANAGER ENDPOINTS
// ======================================

// GET updated clients
$app->get('/v1/client-base-manager/clients', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\ClientBaseManagerService();
        $data = $service->getUpdatedClients($params);

        return jsonResponse($response, $data, 'success', 'Clients retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET distinct remark types
$app->get('/v1/client-base-manager/remark-types', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ClientBaseManagerService();
        $data = $service->getDistinctRemarkTypes();

        return jsonResponse($response, $data, 'success', 'Remark types retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET remark type counts
$app->get('/v1/client-base-manager/remark-type-counts', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\ClientBaseManagerService();
        $data = $service->getRemarkTypeCounts();

        return jsonResponse($response, $data, 'success', 'Remark type counts retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update client
$app->put('/v1/client-base-manager/clients/{client_id}', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getParsedBody();
        $params['client_id'] = $args['client_id'];

        $service = new \App\Services\ClientBaseManagerService();
        $success = $service->updateClient($params);

        return jsonResponse($response, ['success' => $success], 'success', 'Client updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST bulk update clients
$app->post('/v1/client-base-manager/clients/bulk-update', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $updates = $params['updates'] ?? [];

        $service = new \App\Services\ClientBaseManagerService();
        $results = $service->bulkUpdateClients($updates);

        return jsonResponse($response, ['results' => $results], 'success', 'Clients updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST import clients
$app->post('/v1/client-base-manager/clients/import', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $clients = $params['clients'] ?? [];
        $currentUser = $params['current_user'] ?? 'api_user';

        $service = new \App\Services\ClientBaseManagerService();
        $results = $service->importClients($clients, $currentUser);

        return jsonResponse($response, ['results' => $results], 'success', 'Clients imported successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// COUNTER DATA ENDPOINTS
// ======================================

// GET booking details
$app->get('/v1/counter-data/bookings', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\CounterDataService();
        $data = $service->getBookingDetails($params);

        return jsonResponse($response, $data, 'success', 'Booking details retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// COUNTER ENDPOINTS
// ======================================

// GET trip summary
$app->get('/v1/counter/trip-summary', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\CounterService();
        $data = $service->getTripSummary();

        return jsonResponse($response, $data, 'success', 'Trip summary retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER VIEW BOOKINGS ENDPOINTS
// ======================================

// GET search bookings
$app->get('/v1/customer-view-bookings/search', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\CustomerViewBookingsService();
        $data = $service->searchBookings($params);

        return jsonResponse($response, $data, 'success', 'Bookings retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET booking details
$app->get('/v1/customer-view-bookings/{order_id}', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['order_id'];

        $service = new \App\Services\CustomerViewBookingsService();
        $data = $service->getBookingDetails($orderId);

        return jsonResponse($response, $data, 'success', 'Booking details retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET pax details
$app->get('/v1/customer-view-bookings/{order_id}/pax', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getQueryParams();
        $params['order_id'] = $args['order_id'];

        $service = new \App\Services\CustomerViewBookingsService();
        $data = $service->getPaxDetails($params);

        return jsonResponse($response, $data, 'success', 'Pax details retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment history
$app->get('/v1/customer-view-bookings/{order_id}/payments', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['order_id'];

        $service = new \App\Services\CustomerViewBookingsService();
        $data = $service->getPaymentHistory($orderId);

        return jsonResponse($response, $data, 'success', 'Payment history retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment attachments
$app->get('/v1/customer-view-bookings/{order_id}/attachments', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getQueryParams();
        $params['order_id'] = $args['order_id'];

        $service = new \App\Services\CustomerViewBookingsService();
        $data = $service->getPaymentAttachments($params);

        return jsonResponse($response, $data, 'success', 'Payment attachments retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET portal requests
$app->get('/v1/customer-view-bookings/{order_id}/portal-requests', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getQueryParams();
        $orderId = $args['order_id'];
        $pnrs = isset($params['pnrs']) ? explode(',', $params['pnrs']) : null;

        $service = new \App\Services\CustomerViewBookingsService();
        $data = $service->getPortalRequests($orderId, $pnrs);

        return jsonResponse($response, $data, 'success', 'Portal requests retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// DUMMY USER PORTAL ENDPOINTS
// ======================================

// POST login
$app->post('/v1/dummy-user-portal/login', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\DummyUserPortalService();
        $data = $service->login($params);

        return jsonResponse($response, $data, 'success', 'Login successful');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST register
$app->post('/v1/dummy-user-portal/register', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\DummyUserPortalService();
        $data = $service->register($params);

        return jsonResponse($response, $data, 'success', 'Registration successful');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET user profile
$app->get('/v1/dummy-user-portal/profile/{user_id}', function (Request $request, Response $response, array $args) {
    try {
        $userId = (int)$args['user_id'];

        $service = new \App\Services\DummyUserPortalService();
        $data = $service->getUserProfile($userId);

        return jsonResponse($response, $data, 'success', 'User profile retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update password
$app->put('/v1/dummy-user-portal/profile/{user_id}/password', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getParsedBody();
        $params['user_id'] = (int)$args['user_id'];

        $service = new \App\Services\DummyUserPortalService();
        $success = $service->updatePassword($params);

        return jsonResponse($response, ['success' => $success], 'success', 'Password updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET user requests
$app->get('/v1/dummy-user-portal/requests', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\DummyUserPortalService();
        $data = $service->getUserRequests($params);

        return jsonResponse($response, $data, 'success', 'User requests retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET request details
$app->get('/v1/dummy-user-portal/requests/{case_id}', function (Request $request, Response $response, array $args) {
    try {
        $caseId = $args['case_id'];

        $service = new \App\Services\DummyUserPortalService();
        $data = $service->getRequestDetails($caseId);

        return jsonResponse($response, $data, 'success', 'Request details retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create request
$app->post('/v1/dummy-user-portal/requests', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();

        $service = new \App\Services\DummyUserPortalService();
        $data = $service->createRequest($params);

        return jsonResponse($response, $data, 'success', 'Request created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// DUMMY USER PORTAL BACKEND ENDPOINTS
// ======================================

// GET requests
$app->get('/v1/dummy-user-portal-backend/requests', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\DummyUserPortalBackendService();
        $data = $service->getRequests($params);

        return jsonResponse($response, $data, 'success', 'Requests retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET request details
$app->get('/v1/dummy-user-portal-backend/requests/{case_id}', function (Request $request, Response $response, array $args) {
    try {
        $caseId = $args['case_id'];

        $service = new \App\Services\DummyUserPortalBackendService();
        $data = $service->getRequestDetails($caseId);

        return jsonResponse($response, $data, 'success', 'Request details retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET request chats
$app->get('/v1/dummy-user-portal-backend/requests/{request_id}/chats', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getQueryParams();
        $params['request_id'] = $args['request_id'];

        $service = new \App\Services\DummyUserPortalBackendService();
        $data = $service->getRequestChats($params);

        return jsonResponse($response, $data, 'success', 'Chats retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST send reply
$app->post('/v1/dummy-user-portal-backend/requests/{request_id}/reply', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getParsedBody();
        $params['request_id'] = $args['request_id'];

        $service = new \App\Services\DummyUserPortalBackendService();
        $data = $service->sendReply($params);

        return jsonResponse($response, $data, 'success', 'Reply sent successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST upload attachment
$app->post('/v1/dummy-user-portal-backend/requests/{request_id}/attachment', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getParsedBody();
        $params['request_id'] = $args['request_id'];

        $service = new \App\Services\DummyUserPortalBackendService();
        $data = $service->uploadAttachment($params);

        return jsonResponse($response, $data, 'success', 'Attachment uploaded successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET check new updates
$app->get('/v1/dummy-user-portal-backend/requests/{request_id}/check-updates', function (Request $request, Response $response, array $args) {
    try {
        $params = $request->getQueryParams();
        $params['request_id'] = $args['request_id'];

        $service = new \App\Services\DummyUserPortalBackendService();
        $data = $service->checkNewUpdates($params);

        return jsonResponse($response, $data, 'success', 'Update check completed');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// ACCOUNTING PAYMENT ENDPOINTS
// ======================================

// GET payment history
$app->get('/v1/accounting/payment-history', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\AccountingPaymentService();
        $data = $service->getPaymentHistory($params);

        return jsonResponse($response, $data, 'success', 'Payment history retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update payment records
$app->post('/v1/accounting/payment-history/update', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\AccountingPaymentService();
        $data = $service->updatePaymentRecords($params);

        return jsonResponse($response, $data, 'success', 'Payment records updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST delete payment record
$app->post('/v1/accounting/payment-history/delete', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\AccountingPaymentService();
        $data = $service->deletePaymentRecord($params);

        return jsonResponse($response, $data, 'success', 'Payment record deleted successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// FLIGHT FIT CHECKOUT ENDPOINTS
// ======================================

// GET booking info by order ID
$app->get('/v1/flight-fit-checkout/booking-info', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\FlightFITCheckoutService();
        $data = $service->getBookingInfo($params);

        return jsonResponse($response, $data, 'success', 'Booking info retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET airport info
$app->get('/v1/flight-fit-checkout/airport-info', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\FlightFITCheckoutService();
        $data = $service->getAirportInfo($params);

        return jsonResponse($response, $data, 'success', 'Airport info retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// FLIGHT PRICING ENDPOINTS
// ======================================

// GET airport info
$app->get('/v1/flight-pricing/airport-info', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\FlightPricingService();
        $data = $service->getAirportInfo($params);

        return jsonResponse($response, $data, 'success', 'Airport info retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// FLIGHT QUOTE ENDPOINTS
// ======================================

// POST create regular quote
$app->post('/v1/flight-quote', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\FlightQuoteService();
        $data = $service->createQuote($params);

        return jsonResponse($response, $data, 'success', 'Quote created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create multicity quote
$app->post('/v1/flight-quote/multicity', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\FlightQuoteService();
        $data = $service->createMulticityQuote($params);

        return jsonResponse($response, $data, 'success', 'Multicity quote created successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET passenger info by phone
$app->get('/v1/flight-quote/passenger-info', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\FlightQuoteService();
        $data = $service->getPassengerInfo($params);

        return jsonResponse($response, $data, 'success', 'Passenger info retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// FLIGHT AVAILABILITY ENDPOINTS
// ======================================

// POST save flight availability check (must come BEFORE GET route with {id} parameter)
// Note: OPTIONS requests are handled by the global catch-all handler in index.php
$app->post('/v1/flight-availability/check', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody() ?? [];
        
        $service = new \App\Services\FlightAvailabilityService();
        $data = $service->saveAvailabilityCheck($params);
        
        return jsonResponse($response, $data, 'success', 'Flight availability check saved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET flight availability check by ID (must come AFTER POST route without parameters)
$app->get('/v1/flight-availability/check/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'] ?? null;
        
        if (empty($id)) {
            return errorResponse($response, 'ID is required', 400);
        }
        
        $service = new \App\Services\FlightAvailabilityService();
        $data = $service->getAvailabilityCheck($id);
        
        return jsonResponse($response, $data, 'success', 'Flight availability check retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// G360 COMMON SETTINGS ENDPOINTS
// ======================================

// GET setting value
$app->get('/v1/g360-common-settings/setting', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\G360CommonSettingsService();
        $data = $service->getSetting($params);

        return jsonResponse($response, $data, 'success', 'Setting retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update setting
$app->post('/v1/g360-common-settings/setting', function (Request $request, Response $response) {
    try {
        $params = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);

        $service = new \App\Services\G360CommonSettingsService();
        $data = $service->updateSetting($params);

        return jsonResponse($response, $data, 'success', 'Setting updated successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all settings
$app->get('/v1/g360-common-settings/all', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\G360CommonSettingsService();
        $data = $service->getAllSettings();

        return jsonResponse($response, $data, 'success', 'All settings retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GAURA GRAND PRIX ENDPOINTS
// ======================================

// GET GTIB data
$app->get('/v1/gaura-grand-prix/gtib', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GauraGrandPrixService();
        $data = $service->getGTIBData($params);

        return jsonResponse($response, $data, 'success', 'GTIB data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET FCS data
$app->get('/v1/gaura-grand-prix/fcs', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GauraGrandPrixService();
        $data = $service->getFCSData($params);

        return jsonResponse($response, $data, 'success', 'FCS data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET conversion data
$app->get('/v1/gaura-grand-prix/conversion', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GauraGrandPrixService();
        $data = $service->getConversionData($params);

        return jsonResponse($response, $data, 'success', 'Conversion data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GDEAL CHECKOUT AGENT EXISTING ENDPOINTS
// ======================================

// GET passenger data by customer ID
$app->get('/v1/gdeal-checkout-agent-existing/passenger', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GDealCheckoutAgentExistingService();
        $data = $service->getPassengerData($params);

        return jsonResponse($response, $data, 'success', 'Passenger data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET passengers by customer IDs
$app->get('/v1/gdeal-checkout-agent-existing/passengers', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GDealCheckoutAgentExistingService();
        $data = $service->getPassengersByCustomerIds($params);

        return jsonResponse($response, $data, 'success', 'Passengers retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET billing address by customer ID
$app->get('/v1/gdeal-checkout-agent-existing/billing-address', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GDealCheckoutAgentExistingService();
        $data = $service->getBillingAddress($params);

        return jsonResponse($response, $data, 'success', 'Billing address retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GDEALS 108 ENDPOINTS
// ======================================

// GET booking tracker
$app->get('/v1/gdeals-108/tracker', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GDeals108Service();
        $data = $service->getBookingTracker($params);

        return jsonResponse($response, $data, 'success', 'Booking tracker data retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GDEALS MISSING ORDER SEARCH ENDPOINTS
// ======================================

// GET check if booking exists
$app->get('/v1/gdeals-missing-order-search/check', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GDealsMissingOrderSearchService();
        $data = $service->checkBookingExists($params);

        return jsonResponse($response, $data, 'success', 'Booking check completed successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GDS API MISSING ORDER SEARCH ENDPOINTS
// ======================================

// GET check if PNR exists
$app->get('/v1/gds-api-missing-order-search/check', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GDSAPIMissingOrderSearchService();
        $data = $service->checkPnrExists($params);

        return jsonResponse($response, $data, 'success', 'PNR check completed successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// GDS API BOOKING MANUAL IMPORT ENDPOINTS
// ======================================

// GET last order ID
$app->get('/v1/gds-api-booking-manual-import/last-order-id', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\GDSAPIBookingManualImportService();
        $data = $service->getLastOrderId();

        return jsonResponse($response, $data, 'success', 'Last order ID retrieved successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET check if passenger exists
$app->get('/v1/gds-api-booking-manual-import/check-passenger', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();

        $service = new \App\Services\GDSAPIBookingManualImportService();
        $data = $service->checkPassengerExists($params);

        return jsonResponse($response, $data, 'success', 'Passenger check completed successfully');

    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER CRONJOB ENDPOINTS
// ======================================

// POST log cronjob execution step
$app->post('/v1/customer-cronjob/log-step', function (Request $request, Response $response) {
    try {
        $input = json_decode($request->getBody()->getContents(), true);
        
        if (empty($input)) {
            throw new Exception('Log data required', 400);
        }
        
        $service = new \App\Services\CustomerCronjobService();
        $result = $service->logStep($input);
        
        return jsonResponse($response, ['logged' => true], 'success', 'Step logged successfully', 201);
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET logs by run ID
$app->get('/v1/customer-cronjob/logs/{runId}', function (Request $request, Response $response, array $args) {
    try {
        $runId = $args['runId'];
        
        $service = new \App\Services\CustomerCronjobService();
        $logs = $service->getLogsByRunId($runId);
        
        return jsonResponse($response, $logs, 'success', 'Logs retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET recent executions
$app->get('/v1/customer-cronjob/recent-executions', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $limit = (int)($params['limit'] ?? 10);
        
        $service = new \App\Services\CustomerCronjobService();
        $executions = $service->getRecentExecutions($limit);
        
        return jsonResponse($response, $executions, 'success', 'Recent executions retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER BOOKING ACTIVITY ENDPOINTS
// ======================================

// POST update customer booking activity
$app->post('/v1/customer-booking-activity/update', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $from = $params['from'] ?? $input['from'] ?? null;
        $to = $params['to'] ?? $input['to'] ?? null;
        
        if (empty($from) || empty($to)) {
            // Default to last 2 hours
            $now = new \DateTime('now', new \DateTimeZone('Australia/Melbourne'));
            $twoHoursAgo = (clone $now)->modify('-2 hours');
            $from = $from ?: $twoHoursAgo->format('Y-m-d H:i:s');
            $to = $to ?: $now->format('Y-m-d H:i:s');
        }
        
        $service = new \App\Services\CustomerBookingActivityService();
        $result = $service->updateBookingActivity($from, $to);
        
        return jsonResponse($response, $result, 'success', 'Booking activity updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER CALL ACTIVITY ENDPOINTS
// ======================================

// POST update customer call activity
$app->post('/v1/customer-call-activity/update', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $from = $params['from'] ?? $input['from'] ?? null;
        $to = $params['to'] ?? $input['to'] ?? null;
        
        if (empty($from) || empty($to)) {
            // Default to last 2 hours
            $now = new \DateTime('now', new \DateTimeZone('Australia/Melbourne'));
            $twoHoursAgo = (clone $now)->modify('-2 hours');
            $from = $from ?: $twoHoursAgo->format('Y-m-d H:i:s');
            $to = $to ?: $now->format('Y-m-d H:i:s');
        }
        
        $service = new \App\Services\CustomerCallActivityService();
        $result = $service->updateCallActivity($from, $to);
        
        return jsonResponse($response, $result, 'success', 'Call activity updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER INFO ENDPOINTS
// ======================================

// POST update customer info
$app->post('/v1/customer-info/update', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $from = $params['from'] ?? $input['from'] ?? null;
        $to = $params['to'] ?? $input['to'] ?? null;
        $chunkHours = (int)($params['chunk_hours'] ?? $input['chunk_hours'] ?? 6);
        $batchRows = (int)($params['batch_rows'] ?? $input['batch_rows'] ?? 500);
        
        if (empty($from) || empty($to)) {
            // Default to last 2 hours
            $now = new \DateTime('now', new \DateTimeZone('Australia/Melbourne'));
            $twoHoursAgo = (clone $now)->modify('-2 hours');
            $from = $from ?: $twoHoursAgo->format('Y-m-d H:i:s');
            $to = $to ?: $now->format('Y-m-d H:i:s');
        }
        
        $service = new \App\Services\CustomerInfoService();
        $result = $service->updateCustomerInfo($from, $to, $chunkHours, $batchRows);
        
        return jsonResponse($response, $result, 'success', 'Customer info updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER PROFILE ENDPOINTS
// ======================================

// POST update customer profiles
$app->post('/v1/customer-profile/update', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $from = $params['from'] ?? $input['from'] ?? null;
        $to = $params['to'] ?? $input['to'] ?? null;
        
        if (empty($from) || empty($to)) {
            // Default to last 2 hours
            $now = new \DateTime('now', new \DateTimeZone('Australia/Melbourne'));
            $twoHoursAgo = (clone $now)->modify('-2 hours');
            $from = $from ?: $twoHoursAgo->format('Y-m-d H:i:s');
            $to = $to ?: $now->format('Y-m-d H:i:s');
        }
        
        $service = new \App\Services\CustomerProfileService();
        $result = $service->updateCustomerProfiles($from, $to);
        
        return jsonResponse($response, $result, 'success', 'Customer profiles updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// CUSTOMER WEBSITE ACTIVITY ENDPOINTS
// ======================================

// POST update customer website activity
$app->post('/v1/customer-website-activity/update', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $from = $params['from'] ?? $input['from'] ?? null;
        $to = $params['to'] ?? $input['to'] ?? null;
        
        if (empty($from) || empty($to)) {
            // Default to last 1 hour
            $now = new \DateTime('now', new \DateTimeZone('Australia/Melbourne'));
            $oneHourAgo = (clone $now)->modify('-1 hour');
            $from = $from ?: $oneHourAgo->format('Y-m-d H:i:s');
            $to = $to ?: $now->format('Y-m-d H:i:s');
        }
        
        $service = new \App\Services\CustomerWebsiteActivityService();
        $result = $service->updateWebsiteActivity($from, $to);
        
        return jsonResponse($response, $result, 'success', 'Website activity updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// NOBEL CRONJOB ENDPOINTS
// ======================================

// POST update paid FCS status
$app->post('/v1/nobel/paid-fcs/update', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\NobelPaidFcsService();
        $result = $service->updatePaidFcs();
        
        return jsonResponse($response, $result, 'success', 'Paid FCS Update completed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process Amadeus stock check
$app->post('/v1/nobel/amadeus-stock-check/process', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $startDate = $params['start_date'] ?? $input['start_date'] ?? '2025-09-02';
        $endDate = $params['end_date'] ?? $input['end_date'] ?? '2026-03-31';
        
        $service = new \App\Services\AmadeusStockCheckService();
        $result = $service->processStockCheck($startDate, $endDate);
        
        return jsonResponse($response, $result, 'success', 'Amadeus stock check processed successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process Nobel insert table cronjob (agent booking and inbound call)
$app->post('/v1/nobel/insert-table/process', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\NobelInsertTableCronService();
        $result = $service->processAll();
        
        return jsonResponse($response, $result, 'success', 'Nobel insert table cronjob processed successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Nobel Insert Table Cron Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process agent booking data
$app->post('/v1/nobel/insert-table/agent-booking', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\NobelInsertTableCronService();
        $result = $service->processAgentBookingData();
        
        return jsonResponse($response, $result, 'success', 'Agent booking data processed successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Nobel Agent Booking Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process agent inbound call data
$app->post('/v1/nobel/insert-table/agent-inbound-call', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\NobelInsertTableCronService();
        $result = $service->processAgentInboundCallData();
        
        return jsonResponse($response, $result, 'success', 'Agent inbound call data processed successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Nobel Agent Inbound Call Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST cleanup realtime tables
$app->post('/v1/nobel/insert-table/cleanup', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $dateBefore = $params['date_before'] ?? null;
        
        $service = new \App\Services\NobelInsertTableCronService();
        $result = $service->cleanupRealtimeTables($dateBefore);
        
        return jsonResponse($response, $result, 'success', 'Realtime tables cleaned up successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Nobel Cleanup Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update agent booking PIF data
$app->post('/v1/nobel/insert-table-pif/update', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\NobelInsertTableCronPifService();
        $result = $service->updateAgentBookingPifData();
        
        return jsonResponse($response, $result, 'success', 'Agent booking PIF data updated successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Nobel PIF Update Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update FCS status in realtime
$app->post('/v1/nobel/realtime-update-fcs/update', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\NobelRealtimeUpdateFcsService();
        $result = $service->updateFcsStatus();
        
        return jsonResponse($response, $result, 'success', 'FCS status updated successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Nobel Realtime FCS Update Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process TSR day report
$app->post('/v1/nobel/tsksrday-report/process', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $callDate = $params['call_date'] ?? null;
        
        $service = new \App\Services\NobelTsksrdayReportService();
        $result = $service->processTsrDayReport($callDate);
        
        return jsonResponse($response, $result, 'success', 'TSR day report processed successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        error_log("Nobel TSR Day Report Error: " . $e->getMessage());
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ============================================
// Email Endpoints
// ============================================

// GET email recipients for an order
$app->get('/v1/email/order/{orderId}/recipients', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getEmailRecipients($orderId);
        
        return jsonResponse($response, $data, 'success', 'Email recipients retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET booking information for email
$app->get('/v1/email/order/{orderId}/booking-info', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getBookingInfo($orderId);
        
        return jsonResponse($response, $data, 'success', 'Booking information retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET tax invoice data
$app->get('/v1/email/order/{orderId}/tax-invoice-data', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getTaxInvoiceData($orderId);
        
        return jsonResponse($response, $data, 'success', 'Tax invoice data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET reminder booking data (24h, 4d, 7d)
$app->get('/v1/email/order/{orderId}/reminder-data', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        $params = $request->getQueryParams();
        $daysThreshold = isset($params['days_threshold']) ? (int)$params['days_threshold'] : null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getReminderBookingData($orderId, $daysThreshold);
        
        return jsonResponse($response, $data, 'success', 'Reminder booking data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST log email history
$app->post('/v1/email/log-history', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\EmailService();
        $result = $service->logEmailHistory(
            $params['order_id'] ?? null,
            $params['email_type'] ?? null,
            $params['email_address'] ?? null,
            $params['email_subject'] ?? null,
            $params['initiated_by'] ?? null,
            $params['email_body'] ?? null
        );
        
        return jsonResponse($response, ['id' => $result], 'success', 'Email history logged successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update e-ticket status
$app->post('/v1/email/order/{orderId}/eticket-status', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        $params = $request->getParsedBody();
        
        $service = new \App\Services\EmailService();
        $result = $service->updateEticketStatus(
            $orderId,
            $params['status'] ?? null,
            $params['file_path'] ?? null,
            $params['created_by'] ?? null
        );
        
        return jsonResponse($response, $result, 'success', 'E-ticket status updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all bookings for an order (ordered by travel_date)
$app->get('/v1/email/order/{orderId}/bookings', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getAllBookingsForOrder($orderId);
        
        return jsonResponse($response, $data, 'success', 'Bookings retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET history of updates data
$app->get('/v1/email/order/{orderId}/history-updates', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        $params = $request->getQueryParams();
        $metaKey = $params['meta_key'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getHistoryOfUpdates($orderId, $metaKey);
        
        return jsonResponse($response, $data, 'success', 'History of updates retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET passengers by product_id
$app->get('/v1/email/order/{orderId}/passengers', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        $params = $request->getQueryParams();
        $productId = $params['product_id'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getPassengersByProductId($orderId, $productId);
        
        return jsonResponse($response, $data, 'success', 'Passengers retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET trip extras
$app->get('/v1/email/trip-extras', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $productId = $params['product_id'] ?? null;
        $newProductId = $params['new_product_id'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getTripExtras($productId, $newProductId);
        
        return jsonResponse($response, $data, 'success', 'Trip extras retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET custom email itinerary
$app->get('/v1/email/order/{orderId}/custom-itinerary', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        $params = $request->getQueryParams();
        $isEmailed = isset($params['is_emailed']) ? $params['is_emailed'] : null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getCustomEmailItinerary($orderId, $isEmailed);
        
        return jsonResponse($response, $data, 'success', 'Custom email itinerary retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET payment history
$app->get('/v1/email/order/{orderId}/payment-history', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getPaymentHistory($orderId);
        
        return jsonResponse($response, $data, 'success', 'Payment history retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET flight leg details
$app->get('/v1/email/order/{orderId}/flight-legs', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getFlightLegDetails($orderId);
        
        return jsonResponse($response, $data, 'success', 'Flight leg details retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET baggage information
$app->get('/v1/email/order/{orderId}/baggage-info', function (Request $request, Response $response, array $args) {
    try {
        $orderId = $args['orderId'] ?? null;
        $params = $request->getQueryParams();
        $gdsPaxId = $params['gds_pax_id'] ?? null;
        $departureAirport = $params['departure_airport'] ?? null;
        
        $service = new \App\Services\EmailService();
        $data = $service->getBaggageInfo($orderId, $gdsPaxId, $departureAirport);
        
        return jsonResponse($response, $data, 'success', 'Baggage information retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ============================================
// Seat Availability Endpoints
// ============================================

// POST check IP address access
$app->post('/v1/seat-availability/check-ip', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $ipAddress = $params['ip_address'] ?? null;
        
        $service = new \App\Services\SeatAvailabilityService();
        $data = $service->checkIpAccess($ipAddress);
        
        return jsonResponse($response, $data, 'success', 'IP access checked successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET airlines list
$app->get('/v1/seat-availability/airlines', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $excludeAirlines = isset($params['exclude']) ? explode(',', $params['exclude']) : ['FC', 'MH'];
        
        $service = new \App\Services\SeatAvailabilityService();
        $data = $service->getAirlines($excludeAirlines);
        
        return jsonResponse($response, $data, 'success', 'Airlines retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET routes by airline
$app->get('/v1/seat-availability/airlines/{airlineCode}/routes', function (Request $request, Response $response, array $args) {
    try {
        $airlineCode = $args['airlineCode'] ?? null;
        
        $service = new \App\Services\SeatAvailabilityService();
        $data = $service->getRoutesByAirline($airlineCode);
        
        return jsonResponse($response, $data, 'success', 'Routes retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET all routes
$app->get('/v1/seat-availability/routes', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\SeatAvailabilityService();
        $data = $service->getAllRoutes();
        
        return jsonResponse($response, $data, 'success', 'Routes retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET seat availability
$app->get('/v1/seat-availability/search', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'airline_code' => $params['airline_code'] ?? null,
            'route' => $params['route'] ?? null,
            'date_from' => $params['date_from'] ?? null,
            'date_to' => $params['date_to'] ?? null
        ];
        
        $service = new \App\Services\SeatAvailabilityService();
        $data = $service->getSeatAvailability($filters);
        
        return jsonResponse($response, $data, 'success', 'Seat availability retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET seat availability (internal view with pricing)
$app->get('/v1/seat-availability/internal/search', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'airline_code' => $params['airline_code'] ?? null,
            'route' => $params['route'] ?? null,
            'date_from' => $params['date_from'] ?? null,
            'date_to' => $params['date_to'] ?? null,
            'sale_price' => $params['sale_price'] ?? null
        ];
        
        $service = new \App\Services\SeatAvailabilityService();
        $data = $service->getSeatAvailabilityInternal($filters);
        
        return jsonResponse($response, $data, 'success', 'Seat availability retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET sale prices by route
$app->get('/v1/seat-availability/routes/{route}/prices', function (Request $request, Response $response, array $args) {
    try {
        $route = $args['route'] ?? null;
        
        $service = new \App\Services\SeatAvailabilityService();
        $data = $service->getSalePricesByRoute($route);
        
        return jsonResponse($response, $data, 'success', 'Sale prices retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET trip pricing information
$app->get('/v1/seat-availability/trip/{tripCode}/pricing', function (Request $request, Response $response, array $args) {
    try {
        $tripCode = $args['tripCode'] ?? null;
        $params = $request->getQueryParams();
        $travelDate = $params['travel_date'] ?? null;
        
        if (empty($travelDate)) {
            throw new Exception('travel_date is required', 400);
        }
        
        $service = new \App\Services\SeatAvailabilityService();
        $data = $service->getTripPricing($tripCode, $travelDate);
        
        return jsonResponse($response, $data, 'success', 'Trip pricing retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ============================================
// Employee Performance Endpoints
// ============================================

// GET active agents
$app->get('/v1/employee-performance/agents', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $source = $params['source'] ?? 'agent_codes';
        
        $service = new \App\Services\EmployeePerformanceService();
        $data = $service->getActiveAgents($source);
        
        return jsonResponse($response, $data, 'success', 'Active agents retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET yearly performance data (from performance data table)
$app->get('/v1/employee-performance/yearly/{tsr}', function (Request $request, Response $response, array $args) {
    try {
        $tsr = $args['tsr'] ?? null;
        $params = $request->getQueryParams();
        $year = $params['year'] ?? date('Y');
        
        $service = new \App\Services\EmployeePerformanceService();
        $data = $service->getYearlyPerformanceData($tsr, $year);
        
        return jsonResponse($response, $data, 'success', 'Yearly performance data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET combined performance data (yearly)
$app->get('/v1/employee-performance/combined/yearly/{tsr}', function (Request $request, Response $response, array $args) {
    try {
        $tsr = $args['tsr'] ?? null;
        $params = $request->getQueryParams();
        $year = $params['year'] ?? date('Y');
        
        $service = new \App\Services\EmployeePerformanceService();
        $data = $service->getCombinedPerformanceDataYearly($tsr, $year);
        
        return jsonResponse($response, $data, 'success', 'Combined yearly performance data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET combined performance data (monthly)
$app->get('/v1/employee-performance/combined/monthly/{tsr}', function (Request $request, Response $response, array $args) {
    try {
        $tsr = $args['tsr'] ?? null;
        $params = $request->getQueryParams();
        $year = $params['year'] ?? date('Y');
        $month = $params['month'] ?? null;
        
        if (empty($month)) {
            throw new Exception('month is required', 400);
        }
        
        $service = new \App\Services\EmployeePerformanceService();
        $data = $service->getCombinedPerformanceDataMonthly($tsr, $year, $month);
        
        return jsonResponse($response, $data, 'success', 'Combined monthly performance data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET combined performance data (last 3 months)
$app->get('/v1/employee-performance/combined/last-3-months/{tsr}', function (Request $request, Response $response, array $args) {
    try {
        $tsr = $args['tsr'] ?? null;
        
        $service = new \App\Services\EmployeePerformanceService();
        $data = $service->getCombinedPerformanceDataLast3Months($tsr);
        
        return jsonResponse($response, $data, 'success', 'Last 3 months performance data retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET performance reviews
$app->get('/v1/employee-performance/reviews', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $searchTerm = $params['search'] ?? null;
        
        $service = new \App\Services\EmployeePerformanceService();
        $data = $service->getPerformanceReviews($searchTerm);
        
        return jsonResponse($response, $data, 'success', 'Performance reviews retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET performance review by ID
$app->get('/v1/employee-performance/reviews/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'] ?? null;
        
        $service = new \App\Services\EmployeePerformanceService();
        $data = $service->getPerformanceReviewById($id);
        
        return jsonResponse($response, $data, 'success', 'Performance review retrieved successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST create performance review
$app->post('/v1/employee-performance/reviews', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\EmployeePerformanceService();
        $result = $service->createPerformanceReview($params);
        
        return jsonResponse($response, ['id' => $result], 'success', 'Performance review created successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// PUT update performance review
$app->put('/v1/employee-performance/reviews/{id}', function (Request $request, Response $response, array $args) {
    try {
        $id = $args['id'] ?? null;
        $params = $request->getParsedBody();
        
        $service = new \App\Services\EmployeePerformanceService();
        $result = $service->updatePerformanceReview($id, $params);
        
        return jsonResponse($response, ['success' => true], 'success', 'Performance review updated successfully');
        
    } catch (Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AUTO CANCELLATION ENDPOINTS
// ======================================

// GET bookings for cancellation view
$app->get('/v1/auto-cancel-bookings', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $previousDays = $params['previous_days'] ?? null;
        
        $service = new \App\Services\AutoCancelBookingsService();
        $data = $service->getBookingsForCancellation($previousDays);
        
        return jsonResponse($response, $data, 'success', 'Bookings retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST check IP access for auto cancel bookings
$app->post('/v1/auto-cancel-bookings/check-ip', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $ipAddress = $params['ip_address'] ?? null;
        
        if (empty($ipAddress)) {
            throw new \Exception('IP address is required', 400);
        }
        
        $service = new \App\Services\AutoCancelBookingsService();
        $data = $service->checkIpAccess($ipAddress);
        
        return jsonResponse($response, $data, 'success', 'IP access checked successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for deposit deadline cancellation (every 30 mins)
$app->get('/v1/auto-cancellation/deposit-deadline', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationEvery30MinsService();
        $data = $service->getBookingsForDepositDeadlineCancellation();
        
        return jsonResponse($response, $data, 'success', 'Bookings for deposit deadline cancellation retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update seat availability for cancelled booking
$app->post('/v1/auto-cancellation/update-seat-availability', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $orderId = $params['order_id'] ?? null;
        $byUser = $params['by_user'] ?? 'auto_cancellation_cron';
        
        if (empty($orderId)) {
            throw new \Exception('Order ID is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationEvery30MinsService();
        $data = $service->updateSeatAvailability($orderId, $byUser);
        
        return jsonResponse($response, $data, 'success', 'Seat availability updated successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET FIT bookings for full payment cancellation
$app->get('/v1/auto-cancellation/fit-fullpayment', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationFitFullpaymentDepositService();
        $data = $service->getFitBookingsForFullPaymentCancellation();
        
        return jsonResponse($response, $data, 'success', 'FIT bookings for full payment cancellation retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for deposit deadline cancellation (FIT)
$app->get('/v1/auto-cancellation/fit-deposit-deadline', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationFitFullpaymentDepositService();
        $data = $service->getBookingsForDepositDeadlineCancellation();
        
        return jsonResponse($response, $data, 'success', 'Bookings for deposit deadline cancellation retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST cancel booking for full payment deadline (FIT)
$app->post('/v1/auto-cancellation/fit-fullpayment/cancel', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $orderId = $params['order_id'] ?? null;
        
        if (empty($orderId)) {
            throw new \Exception('Order ID is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationFitFullpaymentDepositService();
        $data = $service->cancelBookingForFullPayment($orderId);
        
        return jsonResponse($response, $data, 'success', 'Booking cancelled successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST cancel booking for deposit deadline (FIT)
$app->post('/v1/auto-cancellation/fit-deposit-deadline/cancel', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $orderId = $params['order_id'] ?? null;
        
        if (empty($orderId)) {
            throw new \Exception('Order ID is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationFitFullpaymentDepositService();
        $data = $service->cancelBookingForDepositDeadline($orderId);
        
        return jsonResponse($response, $data, 'success', 'Booking cancelled successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET GDeals bookings for full payment cancellation
$app->get('/v1/auto-cancellation/gdeals-fullpayment', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationGdealsFullpaymentService();
        $data = $service->getGdealsBookingsForFullPaymentCancellation();
        
        return jsonResponse($response, $data, 'success', 'GDeals bookings for full payment cancellation retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST cancel booking for full payment deadline (GDeals)
$app->post('/v1/auto-cancellation/gdeals-fullpayment/cancel', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $orderId = $params['order_id'] ?? null;
        
        if (empty($orderId)) {
            throw new \Exception('Order ID is required', 400);
        }
        
        $service = new \App\Services\AutoCancellationGdealsFullpaymentService();
        $data = $service->cancelBookingForFullPayment($orderId);
        
        return jsonResponse($response, $data, 'success', 'Booking cancelled successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for payment reminder (without logging)
$app->get('/v1/auto-cancellation/payment-reminder', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationPaymentReminderService();
        $data = $service->getBookingsForPaymentReminder();
        
        return jsonResponse($response, $data, 'success', 'Bookings for payment reminder retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process payment reminders
$app->post('/v1/auto-cancellation/payment-reminder/process', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationPaymentReminderService();
        $data = $service->processPaymentReminders();
        
        return jsonResponse($response, $data, 'success', 'Payment reminders processed successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for full payment cancellation (midnight)
$app->get('/v1/auto-cancellation/midnight-fullpayment', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationMidnightService();
        $data = $service->getBookingsForFullPaymentCancellation();
        
        return jsonResponse($response, $data, 'success', 'Bookings for full payment cancellation retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for payment reminder (non-payment)
$app->get('/v1/auto-cancellation/non-payment/reminder', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationNonPaymentService();
        $data = $service->getBookingsForPaymentReminder();
        
        return jsonResponse($response, $data, 'success', 'Bookings for payment reminder retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for zero payment cancellation (3 hours)
$app->get('/v1/auto-cancellation/non-payment/zero-payment-3hours', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationNonPaymentService();
        $data = $service->getBookingsForZeroPaymentCancellation3Hours();
        
        return jsonResponse($response, $data, 'success', 'Bookings for zero payment cancellation (3 hours) retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET FIT bookings for cancellation (25 hours)
$app->get('/v1/auto-cancellation/non-payment/fit-25hours', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationNonPaymentService();
        $data = $service->getFitBookingsForCancellation25Hours();
        
        return jsonResponse($response, $data, 'success', 'FIT bookings for cancellation (25 hours) retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for BPAY cancellation (96 hours)
$app->get('/v1/auto-cancellation/non-payment/bpay-96hours', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AutoCancellationNonPaymentService();
        $data = $service->getBookingsForBpayCancellation96Hours();
        
        return jsonResponse($response, $data, 'success', 'Bookings for BPAY cancellation (96 hours) retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET bookings for zero payment cancellation (WPT with custom payments)
$app->get('/v1/auto-cancellation/zero-payment', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $previousDays = $params['previous_days'] ?? null;
        
        $service = new \App\Services\AutoCancellationZeroPaymentService();
        $data = $service->getBookingsForZeroPaymentCancellation($previousDays);
        
        return jsonResponse($response, $data, 'success', 'Bookings for zero payment cancellation retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AZUPAY SETTLEMENT ENDPOINTS
// ======================================

// POST check IP access for Azupay settlement
$app->post('/v1/azupay-settlement/check-ip', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $ipAddress = $params['ip_address'] ?? null;
        
        if (empty($ipAddress)) {
            throw new \Exception('IP address is required', 400);
        }
        
        $service = new \App\Services\AzupaySettlementService();
        $data = $service->checkIpAccess($ipAddress);
        
        return jsonResponse($response, $data, 'success', 'IP access checked successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST process Azupay settlement transaction
$app->post('/v1/azupay-settlement/process-transaction', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $transaction = $params['transaction'] ?? null;
        
        if (empty($transaction) || !is_array($transaction)) {
            throw new \Exception('Transaction data is required', 400);
        }
        
        $service = new \App\Services\AzupaySettlementService();
        $data = $service->processSettlementTransaction($transaction);
        
        return jsonResponse($response, $data, 'success', 'Settlement transaction processed successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update payment reconciliation
$app->post('/v1/azupay-settlement/update-reconciliation', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\AzupaySettlementService();
        $result = $service->updatePaymentReconciliation($params);
        
        return jsonResponse($response, ['updated' => $result], 'success', 'Payment reconciliation updated successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// PAYMENT RECONCILIATION ENDPOINTS
// ======================================

// GET payment reconciliation data
$app->get('/v1/payment-reconciliation', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        
        $service = new \App\Services\PaymentReconciliationService();
        $data = $service->getPaymentReconciliation($startDate, $endDate);
        
        return jsonResponse($response, $data, 'success', 'Payment reconciliation data retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ======================================
// AIRASIA WALLET RECONCILIATION ENDPOINTS
// ======================================

// FR-1: POST import wallet ledger
$app->post('/v1/airasia-wallet-reconciliation/import-wallet', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $result = $service->importWalletLedger($params);
        
        if (isset($result['success']) && $result['success']) {
            return jsonResponse($response, $result, 'success', 'Wallet ledger imported successfully');
        } else {
            return errorResponse($response, $result['error'] ?? 'Import failed', 400);
        }
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-2: POST import G360 transactions
$app->post('/v1/airasia-wallet-reconciliation/import-g360', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $result = $service->importG360Transactions($params);
        
        return jsonResponse($response, $result, 'success', 'G360 transactions imported successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-9: POST run reconciliation
$app->post('/v1/airasia-wallet-reconciliation/reconcile', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        
        if (empty($startDate) || empty($endDate)) {
            return errorResponse($response, 'start_date and end_date are required', 400);
        }
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $result = $service->runReconciliation($startDate, $endDate);
        
        return jsonResponse($response, $result, 'success', 'Reconciliation completed successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-12: GET summary report
$app->get('/v1/airasia-wallet-reconciliation/summary', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        
        if (empty($startDate) || empty($endDate)) {
            return errorResponse($response, 'start_date and end_date are required', 400);
        }
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $data = $service->getSummary($startDate, $endDate);
        
        return jsonResponse($response, $data, 'success', 'Summary report retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-13: GET category breakdown
$app->get('/v1/airasia-wallet-reconciliation/category-breakdown', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        
        if (empty($startDate) || empty($endDate)) {
            return errorResponse($response, 'start_date and end_date are required', 400);
        }
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $data = $service->getCategoryBreakdown($startDate, $endDate);
        
        return jsonResponse($response, $data, 'success', 'Category breakdown retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-14: GET PNR detail view
$app->get('/v1/airasia-wallet-reconciliation/pnr-detail', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $pnr = $params['pnr'] ?? null;
        $startDate = $params['start_date'] ?? date('Y-m-01');
        $endDate = $params['end_date'] ?? date('Y-m-t');
        
        if (empty($pnr)) {
            return errorResponse($response, 'pnr is required', 400);
        }
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $data = $service->getPnrDetail($pnr, $startDate, $endDate);
        
        return jsonResponse($response, $data, 'success', 'PNR detail retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-7: GET category mapping rules
$app->get('/v1/airasia-wallet-reconciliation/mapping-rules', function (Request $request, Response $response) {
    try {
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $rules = $service->getCategoryMappingRules();
        
        return jsonResponse($response, ['rules' => $rules], 'success', 'Mapping rules retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-7: PUT update category mapping rules
$app->put('/v1/airasia-wallet-reconciliation/mapping-rules', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $rules = $params['rules'] ?? [];
        
        if (empty($rules)) {
            return errorResponse($response, 'rules array is required', 400);
        }
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $result = $service->updateCategoryMappingRules($rules);
        
        return jsonResponse($response, ['updated' => $result], 'success', 'Mapping rules updated successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-8: GET uncategorized records
$app->get('/v1/airasia-wallet-reconciliation/uncategorized', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $source = $params['source'] ?? 'both'; // 'wallet', 'g360', or 'both'
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $data = $service->getUncategorizedRecords($source);
        
        return jsonResponse($response, $data, 'success', 'Uncategorized records retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-11: POST add match note
$app->post('/v1/airasia-wallet-reconciliation/{matchId}/note', function (Request $request, Response $response, array $args) {
    try {
        $matchId = (int)($args['matchId'] ?? 0);
        $params = $request->getParsedBody();
        $reason = $params['reason'] ?? '';
        $note = $params['note'] ?? '';
        
        if ($matchId <= 0) {
            return errorResponse($response, 'Invalid match ID', 400);
        }
        
        if (empty($reason) && empty($note)) {
            return errorResponse($response, 'reason or note is required', 400);
        }
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        $result = $service->addMatchNote($matchId, $reason, $note);
        
        return jsonResponse($response, ['updated' => $result], 'success', 'Note added successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// FR-15: GET export summary
$app->get('/v1/airasia-wallet-reconciliation/export', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $type = $params['type'] ?? 'summary'; // 'summary', 'exceptions', 'transactions'
        $format = $params['format'] ?? 'csv'; // 'csv', 'excel', 'pdf'
        
        if (empty($startDate) || empty($endDate)) {
            return errorResponse($response, 'start_date and end_date are required', 400);
        }
        
        $service = new \App\Services\AirAsiaWalletReconciliationService();
        
        if ($type === 'summary') {
            $data = $service->exportSummary($startDate, $endDate, $format);
        } elseif ($type === 'exceptions') {
            $data = $service->exportExceptions($startDate, $endDate, $format);
        } else {
            return errorResponse($response, 'Invalid export type', 400);
        }
        
        return jsonResponse($response, $data, 'success', 'Export data retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});


// ======================================
// AMADEUS ENDORSEMENT ENDPOINTS
// ======================================

// GET endorsement IDs and prices for date range
$app->get('/v1/amadeus-endorsement/endorsement-ids-prices', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $startDate = $params['date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        
        if (empty($startDate) || empty($endDate)) {
            throw new \Exception('Date and end_date parameters are required', 400);
        }
        
        $service = new \App\Services\AmadeusEndorsementBackendService();
        $data = $service->getEndorsementIdsAndPrices($startDate, $endDate);
        
        return jsonResponse($response, $data, 'success', 'Endorsement IDs and prices retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST check IP access for Amadeus endorsement
$app->post('/v1/amadeus-endorsement/check-ip', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $ipAddress = $params['ip_address'] ?? null;
        
        if (empty($ipAddress)) {
            throw new \Exception('IP address is required', 400);
        }
        
        $service = new \App\Services\AmadeusEndorsementService();
        $data = $service->checkIpAccess($ipAddress);
        
        return jsonResponse($response, $data, 'success', 'IP access checked successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET stock management records with filters
$app->get('/v1/amadeus-endorsement/stock-management', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        
        $filters = [
            'tripcode' => $params['tripcode'] ?? null,
            'date' => $params['date'] ?? null,
            'end_id' => $params['end_id'] ?? null,
            'price' => $params['price'] ?? null,
            'pnr' => $params['pnr'] ?? null,
            'exactmatch' => isset($params['exactmatch']) && $params['exactmatch'] == '1'
        ];
        
        $service = new \App\Services\AmadeusEndorsementService();
        $data = $service->getStockManagementRecords($filters);
        
        return jsonResponse($response, $data, 'success', 'Stock management records retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET stock management records by endorsement IDs
$app->get('/v1/amadeus-endorsement/stock-management/by-endorsement-ids', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $endorsementIdsParam = $params['end_id'] ?? null;
        
        if (empty($endorsementIdsParam)) {
            throw new \Exception('end_id parameter is required (comma-separated endorsement IDs)', 400);
        }
        
        $endorsementIds = array_map('trim', explode(',', $endorsementIdsParam));
        $endorsementIds = array_filter($endorsementIds); // Remove empty values
        
        if (empty($endorsementIds)) {
            throw new \Exception('At least one endorsement ID is required', 400);
        }
        
        $service = new \App\Services\AmadeusEndorsementService();
        $data = $service->getStockManagementByEndorsementIds($endorsementIds);
        
        return jsonResponse($response, $data, 'success', 'Stock management records retrieved successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update group name for multiple records
$app->post('/v1/amadeus-endorsement/update-group-name', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $autoIds = $params['auto_ids'] ?? [];
        $groupName = $params['group_name'] ?? null;
        $updatedBy = $params['updated_by'] ?? 'api_user';
        
        if (empty($autoIds) || !is_array($autoIds)) {
            throw new \Exception('auto_ids array is required', 400);
        }
        
        if (empty($groupName)) {
            throw new \Exception('group_name is required', 400);
        }
        
        $service = new \App\Services\AmadeusEndorsementService();
        $data = $service->updateGroupName($autoIds, $groupName, $updatedBy);
        
        return jsonResponse($response, $data, 'success', 'Group name updated successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// POST update endorsement fields (bulk)
$app->post('/v1/amadeus-endorsement/update-fields', function (Request $request, Response $response) {
    try {
        $params = $request->getParsedBody();
        $updates = $params['updates'] ?? [];
        $updatedBy = $params['updated_by'] ?? 'api_user';
        
        if (empty($updates) || !is_array($updates)) {
            throw new \Exception('updates array is required', 400);
        }
        
        $service = new \App\Services\AmadeusEndorsementService();
        $data = $service->updateEndorsementFields($updates, $updatedBy);
        
        return jsonResponse($response, $data, 'success', 'Endorsement fields updated successfully');
        
    } catch (\Exception $e) {
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});


// ======================================
// SEO REPORT ENDPOINTS
// ======================================

// GET quarterly performance
$app->get('/v1/seo-report/quarterly-performance', function (Request $request, Response $response) {
    try {
        $year = (int)($request->getQueryParams()['year'] ?? 2025);
        $service = new \App\Services\SeoReportService();
        $data = $service->getQuarterlyPerformance($year);
        return jsonResponse($response, $data, 'success', 'Quarterly performance retrieved successfully');
    } catch (Exception $e) {
        error_log("SEO Report Error - Quarterly Performance: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET period metrics
$app->get('/v1/seo-report/period-metrics', function (Request $request, Response $response) {
    try {
        $period = $request->getQueryParams()['period'] ?? null;
        $service = new \App\Services\SeoReportService();
        $data = $service->getPeriodMetrics($period);
        return jsonResponse($response, $data, 'success', 'Period metrics retrieved successfully');
    } catch (Exception $e) {
        error_log("SEO Report Error - Period Metrics: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET country traffic
$app->get('/v1/seo-report/country-traffic', function (Request $request, Response $response) {
    try {
        $period = $request->getQueryParams()['period'] ?? null;
        $service = new \App\Services\SeoReportService();
        $data = $service->getCountryTraffic($period);
        return jsonResponse($response, $data, 'success', 'Country traffic retrieved successfully');
    } catch (Exception $e) {
        error_log("SEO Report Error - Country Traffic: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET backlink analytics
$app->get('/v1/seo-report/backlink-analytics', function (Request $request, Response $response) {
    try {
        $months = (int)($request->getQueryParams()['months'] ?? 12);
        $service = new \App\Services\SeoReportService();
        $data = $service->getBacklinkAnalytics($months);
        return jsonResponse($response, $data, 'success', 'Backlink analytics retrieved successfully');
    } catch (Exception $e) {
        error_log("SEO Report Error - Backlink Analytics: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// GET period growth comparison
$app->get('/v1/seo-report/period-growth', function (Request $request, Response $response) {
    try {
        $period1 = $request->getQueryParams()['period1'] ?? 'Q2 2025';
        $period2 = $request->getQueryParams()['period2'] ?? 'Q3 2025';
        $service = new \App\Services\SeoReportService();
        $data = $service->getPeriodGrowth($period1, $period2);
        return jsonResponse($response, $data, 'success', 'Period growth retrieved successfully');
    } catch (Exception $e) {
        error_log("SEO Report Error - Period Growth: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        return errorResponse($response, $e->getMessage(), $code);
    }
});

// ... rest stays the same ...