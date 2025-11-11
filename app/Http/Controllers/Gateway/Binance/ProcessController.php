<?php

namespace App\Http\Controllers\Gateway\Binance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController;
use App\Lib\CurlRequest;
use App\Models\Deposit;
use App\Models\Gateway;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller
{
    public static function process($deposit)
    {
        try {
            Log::info('Binance Deposit Process Started', [
                'deposit_id' => $deposit->id,
                'trx' => $deposit->trx,
                'amount' => $deposit->final_amount,
                'currency' => $deposit->method_currency
            ]);

            $gatewayCurrency = $deposit->gatewayCurrency();
            if (!$gatewayCurrency) {
                Log::error('Gateway currency not found', ['deposit_id' => $deposit->id]);
                $send['error'] = true;
                $send['message'] = 'Gateway configuration error';
                return json_encode($send);
            }

            $gatewayParameter = $gatewayCurrency->gateway_parameter;
            Log::info('Gateway Parameter', ['parameter' => $gatewayParameter]);
            
            $binanceAcc = json_decode($gatewayParameter);
            if (!$binanceAcc) {
                Log::error('Failed to decode gateway parameter', [
                    'parameter' => $gatewayParameter,
                    'json_error' => json_last_error_msg()
                ]);
                $send['error'] = true;
                $send['message'] = 'Gateway configuration error: Invalid JSON';
                return json_encode($send);
            }

            Log::info('Binance Account Config', [
                'has_api_key' => isset($binanceAcc->api_key),
                'has_secret_key' => isset($binanceAcc->secret_key),
                'binance_acc_structure' => get_object_vars($binanceAcc)
            ]);

            // Handle different JSON structures (direct properties vs nested)
            $apiKey = $binanceAcc->api_key ?? $binanceAcc->{'api_key'} ?? null;
            $secretKey = $binanceAcc->secret_key ?? $binanceAcc->{'secret_key'} ?? null;

            if (!$apiKey || !$secretKey) {
                Log::error('Missing Binance credentials', [
                    'api_key_present' => !empty($apiKey),
                    'secret_key_present' => !empty($secretKey),
                    'available_keys' => array_keys(get_object_vars($binanceAcc))
                ]);
                $send['error'] = true;
                $send['message'] = 'Gateway configuration error: Missing API credentials';
                return json_encode($send);
            }

            $nonce = Str::random(32);
            $timestamp = round(microtime(true) * 1000);
            $request = array(
                "env" => array(
                    "terminalType" => "APP"
                ),
                "merchantTradeNo" => $deposit->trx,
                "orderAmount" => $deposit->final_amount,
                "currency" => $deposit->method_currency,
                "goods" => array(
                    "goodsType" =>  "01",
                    "goodsCategory" => "Z000",
                    "referenceGoodsId" =>  $deposit->trx,
                    "goodsName" => "Deposit to " . gs('site_name'),
                    "goodsDetail" => "Deposit to " . gs('site_name')
                ),
            );

            Log::info('Binance Request Prepared', [
                'request' => $request,
                'timestamp' => $timestamp
            ]);

            $jsonRequest = json_encode($request);
            $payload = $timestamp . "\n" . $nonce . "\n" . $jsonRequest . "\n";
            $signature = strtoupper(hash_hmac('SHA512', $payload, $secretKey));

            $headers = array();
            $headers[] = "Content-Type: application/json";
            $headers[] = "BinancePay-Timestamp: $timestamp";
            $headers[] = "BinancePay-Nonce: $nonce";
            $headers[] = "BinancePay-Certificate-SN: $apiKey";
            $headers[] = "BinancePay-Signature: $signature";

            Log::info('Sending request to Binance API');
            $result = CurlRequest::curlPostContent('https://bpay.binanceapi.com/binancepay/openapi/v2/order', $request, $headers);
            
            Log::info('Binance API Response', [
                'raw_response' => $result,
                'response_length' => strlen($result)
            ]);

            $result = json_decode($result);

            if (!$result) {
                Log::error('Failed to decode Binance API response', [
                    'raw_response' => $result,
                    'json_error' => json_last_error_msg()
                ]);
                $send['error'] = true;
                $send['message'] = 'Invalid response from payment gateway';
                return json_encode($send);
            }

            Log::info('Binance Response Decoded', [
                'status' => $result->status ?? 'missing',
                'code' => $result->code ?? 'missing',
                'message' => $result->message ?? 'missing',
                'errorMessage' => $result->errorMessage ?? 'missing',
                'full_response' => $result
            ]);

            if (@$result->status == "SUCCESS") {
                Log::info('Binance payment successful', [
                    'checkout_url' => $result->data->checkoutUrl ?? 'missing'
                ]);
                $send['redirect'] = true;
                $send['redirect_url'] = @$result->data->checkoutUrl;
            } else {
                $errorMessage = @$result->errorMessage ?? @$result->message ?? 'Something went wrong';
                Log::error('Binance payment failed', [
                    'status' => $result->status ?? 'unknown',
                    'code' => $result->code ?? 'unknown',
                    'message' => $errorMessage,
                    'full_response' => $result
                ]);
                $send['error'] = true;
                $send['message'] = $errorMessage;
            }
            
            return json_encode($send);
        } catch (\Exception $e) {
            Log::error('Exception in Binance Process', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'deposit_id' => $deposit->id ?? 'unknown'
            ]);
            
            $send['error'] = true;
            $send['message'] = 'Payment processing error: ' . $e->getMessage();
            return json_encode($send);
        }
    }

    public function ipn(){
        $binance = Gateway::where('alias', 'Binance')->first();
        $binanceAcc   = json_decode($binance->gateway_parameters);
        $deposits = Deposit::initiated()->where('method_code', $binance->code)->where('created_at','>=',now()->subHours(24))->orderBy('last_cron')->limit(10)->get();
        $apiKey    = $binanceAcc->api_key->value;
        $secretKey = $binanceAcc->secret_key->value;
        $url = "https://bpay.binanceapi.com/binancepay/openapi/v2/order/query";

        foreach ($deposits as $deposit) {
            $deposit->last_cron = time();
            $deposit->save();
            $nonce = Str::random(32);
            $timestamp = round(microtime(true) * 1000);

            $request = array(
                "merchantTradeNo" => $deposit->trx,
            );

            $jsonRequest       = json_encode($request);
            $payload            = $timestamp . "\n" . $nonce . "\n" . $jsonRequest . "\n";
            $signature          = strtoupper(hash_hmac('SHA512', $payload, $secretKey));
            $headers            = array();
            $headers[]          = "Content-Type: application/json";
            $headers[]          = "BinancePay-Timestamp: $timestamp";
            $headers[]          = "BinancePay-Nonce: $nonce";
            $headers[]          = "BinancePay-Certificate-SN: $apiKey";
            $headers[]          = "BinancePay-Signature: $signature";

            $result = CurlRequest::curlPostContent($url,$request,$headers);
            $result = json_decode($result);
            if (@$result->data && @$result->data->status == "PAID" && @$result->data->orderAmount == $deposit->final_amount) {
                PaymentController::userDataUpdate($deposit);
            }

        }
    }

}
