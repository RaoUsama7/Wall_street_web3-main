<?php

namespace App\Http\Controllers\Admin;

use App\Models\Gateway;
use App\Lib\RequiredConfig;
use Illuminate\Http\Request;
use App\Models\GatewayCurrency;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AutomaticGatewayController extends Controller {
    public function index() {
        $pageTitle = 'Automatic Gateways';
        $gateways  = Gateway::automatic()->with('currencies')->get();
        return view('admin.gateways.automatic.list', compact('pageTitle', 'gateways'));
    }

    public function edit($alias) {
        $gateway   = Gateway::automatic()->with('currencies', 'currencies.method')->where('alias', $alias)->firstOrFail();
        $pageTitle = 'Update Gateway';

        $supportedCurrencies = collect($gateway->supported_currencies)->except($gateway->currencies->pluck('currency'));
        $parameters          = collect(json_decode($gateway->gateway_parameters));
        $globalParameters    = null;
        $hasCurrencies       = false;
        $currencyIndex       = 1;

        if ($gateway->currencies->count()) {
            $globalParameters = json_decode($gateway->currencies->first()->gateway_parameter);
            $hasCurrencies    = true;
        }

        return view('admin.gateways.automatic.edit', compact('pageTitle', 'gateway', 'supportedCurrencies', 'parameters', 'hasCurrencies', 'currencyIndex', 'globalParameters'));
    }

    public function update(Request $request, $code) {
        try {
            Log::info('Gateway Update Started', [
                'gateway_code' => $code,
                'request_data' => $request->except(['_token', 'global']),
                'has_currency' => $request->has('currency'),
                'currency_count' => $request->has('currency') ? count($request->currency) : 0
            ]);

            $gateway = Gateway::where('code', $code)->firstOrFail();
            Log::info('Gateway Found', ['gateway_id' => $gateway->id, 'gateway_name' => $gateway->name]);

            // Validate gateway
            try {
                $this->gatewayValidator($request)->validate();
                Log::info('Gateway validation passed');
            } catch (ValidationException $e) {
                Log::error('Gateway Validation Failed', [
                    'errors' => $e->errors(),
                    'request' => $request->all()
                ]);
                throw $e;
            }

            // Validate currency
            try {
                $this->gatewayCurrencyValidator($request, $gateway)->validate();
                Log::info('Currency validation passed');
            } catch (ValidationException $e) {
                Log::error('Currency Validation Failed', [
                    'errors' => $e->errors(),
                    'currency_data' => $request->currency ?? 'No currency data'
                ]);
                throw $e;
            }

            $parameters = collect(json_decode($gateway->gateway_parameters));
            Log::info('Gateway Parameters', ['parameters' => $parameters->toArray()]);

            foreach ($parameters->where('global', true) as $key => $pram) {
                if (!isset($request->global[$key])) {
                    Log::warning('Missing global parameter', ['key' => $key]);
                    continue;
                }
                $parameters[$key]->value = $request->global[$key];
            }

            $gateway->alias              = $request->alias;
            $gateway->gateway_parameters = json_encode($parameters);
            $gateway->save();
            Log::info('Gateway saved successfully');

            // Check if currency data exists in request
            if ($request->has('currency') && is_array($request->currency) && count($request->currency) > 0) {
                Log::info('Processing currencies', ['count' => count($request->currency)]);

                $gateway->currencies()->delete();
                Log::info('Deleted existing gateway currencies');

                foreach ($request->currency as $key => $currency) {
                    Log::info('Processing currency', [
                        'index' => $key,
                        'currency_data' => $currency
                    ]);

                    // Validate currency data exists
                    if (empty($currency['currency']) || empty($currency['symbol'])) {
                        Log::warning('Skipping invalid currency entry', [
                            'index' => $key,
                            'currency' => $currency['currency'] ?? 'missing',
                            'symbol' => $currency['symbol'] ?? 'missing'
                        ]);
                        continue; // Skip invalid currency entries
                    }

                    $param = [];
                    foreach ($parameters->where('global', true) as $pkey => $pram) {
                        $param[$pkey] = $pram->value;
                    }

                    // Handle non-global parameters
                    $nonGlobalParams = $parameters->where('global', false);
                    if ($nonGlobalParams->count() > 0) {
                        if (!isset($currency['param']) || !is_array($currency['param'])) {
                            Log::error('Missing param array for currency', [
                                'currency' => $currency['currency'],
                                'index' => $key
                            ]);
                            throw new \Exception('Missing required parameters for currency: ' . $currency['currency']);
                        }

                        foreach ($nonGlobalParams as $paramKey => $paramValue) {
                            if (!isset($currency['param'][$paramKey])) {
                                Log::error('Missing required parameter', [
                                    'currency' => $currency['currency'],
                                    'param_key' => $paramKey
                                ]);
                                throw new \Exception('Missing required parameter: ' . $paramKey . ' for currency: ' . $currency['currency']);
                            }
                            $param[$paramKey] = $currency['param'][$paramKey];
                        }
                    }

                    try {
                        $gatewayCurrency                    = new GatewayCurrency();
                        $gatewayCurrency->name              = $currency['name'];
                        $gatewayCurrency->gateway_alias     = $gateway->alias;
                        $gatewayCurrency->currency          = $currency['currency'];
                        $gatewayCurrency->min_amount        = $currency['min_amount'];
                        $gatewayCurrency->max_amount        = $currency['max_amount'];
                        $gatewayCurrency->fixed_charge      = $currency['fixed_charge'];
                        $gatewayCurrency->percent_charge    = $currency['percent_charge'];
                        $gatewayCurrency->symbol            = $currency['symbol'];
                        $gatewayCurrency->method_code       = $code;
                        $gatewayCurrency->gateway_parameter = json_encode($param);
                        $gatewayCurrency->save();

                        Log::info('Gateway currency saved successfully', [
                            'currency_id' => $gatewayCurrency->id,
                            'currency' => $currency['currency']
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to save gateway currency', [
                            'currency' => $currency['currency'] ?? 'unknown',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        throw $e;
                    }
                }

                Log::info('All currencies processed successfully');
            } else {
                Log::warning('No currency data in request', [
                    'has_currency_key' => $request->has('currency'),
                    'is_array' => is_array($request->currency ?? null),
                    'count' => is_array($request->currency ?? null) ? count($request->currency) : 0
                ]);
            }

            RequiredConfig::configured('deposit_method');

            $notify[] = ['success', $gateway->name . ' updated successfully'];
            Log::info('Gateway update completed successfully');
            return to_route('admin.gateway.automatic.edit', $gateway->alias)->withNotify($notify);

        } catch (ValidationException $e) {
            Log::error('Validation Exception in Gateway Update', [
                'errors' => $e->errors(),
                'gateway_code' => $code
            ]);
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Exception in Gateway Update', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'gateway_code' => $code,
                'request_data' => $request->except(['_token'])
            ]);

            $notify[] = ['error', 'Something went wrong: ' . $e->getMessage() . ' Check logs for details.'];
            return back()->withNotify($notify)->withInput();
        }
    }

    public function remove($id) {
        $gatewayCurrency = GatewayCurrency::findOrFail($id);
        fileManager()->removeFile(getFilePath('gateway') . '/' . $gatewayCurrency->image);
        $gatewayCurrency->delete();
        $notify[] = ['success', 'Gateway currency removed successfully'];
        return back()->withNotify($notify);
    }

    public function status($id) {
        return Gateway::changeStatus($id);
    }

    public function gatewayValidator(Request $request) {
        $validationRule = [
            'alias' => 'required',
        ];
        $validator = Validator::make($request->all(), $validationRule);
        return $validator;
    }

    public function gatewayCurrencyValidator(Request $request, Gateway $gateway) {
        $customAttributes = [];
        $validationRule   = [];

        $paramList           = collect(json_decode($gateway->gateway_parameters));
        $supportedCurrencies = collect($gateway->supported_currencies)->flip()->implode(',');

        foreach ($paramList->where('global', true) as $key => $pram) {
            $validationRule['global.' . $key]   = 'required';
            $customAttributes['global.' . $key] = keyToTitle($key);
        }

        if ($request->has('currency')) {
            foreach ($request->currency as $key => $currency) {
                $validationRule['currency.' . $key . '.currency'] = 'required|string|in:' . $supportedCurrencies;
                $validationRule['currency.' . $key . '.symbol']   = 'required|string';

                $validationRule['currency.' . $key . '.name']           = 'required';
                $validationRule['currency.' . $key . '.min_amount']     = 'required|numeric|gt:0|lte:currency.' . $key . '.max_amount';
                $validationRule['currency.' . $key . '.max_amount']     = 'required|numeric|gt:0|gte:currency.' . $key . '.min_amount';
                $validationRule['currency.' . $key . '.fixed_charge']   = 'required|numeric|gte:0';
                $validationRule['currency.' . $key . '.percent_charge'] = 'required|numeric|gte:0|max:100';

                $supportedCurrencies = explode(',', $supportedCurrencies);

                $supportedCurrencies = collect(removeElement($supportedCurrencies, $currency['currency']))->implode(',');

                $currencyIdentifier = $this->currencyIdentifier($currency['name'], $gateway->name . ' ' . $currency['currency']);

                $customAttributes['currency.' . $key . '.name']           = $currencyIdentifier . ' name';
                $customAttributes['currency.' . $key . '.min_amount']     = $currencyIdentifier . ' ' . keyToTitle('min_amount');
                $customAttributes['currency.' . $key . '.max_amount']     = $currencyIdentifier . ' ' . keyToTitle('max_amount');
                $customAttributes['currency.' . $key . '.fixed_charge']   = $currencyIdentifier . ' ' . keyToTitle('fixed_charge');
                $customAttributes['currency.' . $key . '.percent_charge'] = $currencyIdentifier . ' ' . keyToTitle('percent_charge');
                $customAttributes['currency.' . $key . '.currency']       = $currencyIdentifier . ' ' . keyToTitle('currency');
                $customAttributes['currency.' . $key . '.symbol']         = $currencyIdentifier . ' ' . keyToTitle('symbol');

                foreach ($paramList->where('global', false) as $param_key => $param_value) {
                    $validationRule['currency.' . $key . '.param.' . $param_key]   = 'required';
                    $customAttributes['currency.' . $key . '.param.' . $param_key] = $currencyIdentifier . ' ' . keyToTitle($param_value->title);
                }
            }
        }

        $validator = Validator::make($request->all(), $validationRule, $customAttributes);
        return $validator;
    }

    private function currencyIdentifier($name, $default = '') {
        return $name ?? $default;
    }

}
