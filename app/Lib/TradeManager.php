<?php

namespace App\Lib;

use App\Models\Order;
use App\Models\Trade;
use App\Models\Wallet;
use App\Constants\Status;
use App\Events\Trade as EventsTrade;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Exception;

class TradeManager
{
    private $transactions    = [];
    private $trades          = [];
    private $tradeWithSymbol = [];

    public function trade()
    {
        $buySideOrders = Order::with('pair.coin', 'pair.market', 'user')->buySideOrder()->open()->orderBy('id', 'ASC')->get();
        
        Log::info('TradeManager: Processing buy orders', [
            'total_buy_orders' => $buySideOrders->count()
        ]);

        $tradeSideSell = Status::SELL_SIDE_ORDER;
        $tradeSideBuy  = Status::BUY_SIDE_TRADE;
        
        $tradesCreated = 0;

        foreach ($buySideOrders as $buySideOrder) {

            $pairId    = $buySideOrder->pair_id;
            $rate      = $buySideOrder->rate;

            $sellSideOrders = Order::with('pair', 'user')
                ->where('user_id', '!=', $buySideOrder->user_id)
                ->where('pair_id', $pairId)
                ->where('rate', $rate)
                ->sellSideOrder()
                ->open()
                ->whereDate('created_at', ">=", now()->subMinutes(10))
                ->orderBy('id', 'ASC')
                ->get();

            if ($sellSideOrders->count() <= 0) {
                Log::debug('TradeManager: No matching sell orders found', [
                    'pair_id' => $pairId,
                    'rate' => $rate,
                    'buy_order_id' => $buySideOrder->id
                ]);
                continue;
            }
            
            Log::info('TradeManager: Found matching sell orders', [
                'pair_id' => $pairId,
                'rate' => $rate,
                'buy_order_id' => $buySideOrder->id,
                'sell_orders_count' => $sellSideOrders->count()
            ]);

            foreach ($sellSideOrders as $sellSideOrder) {

                $buyAmount     = $buySideOrder->amount - $buySideOrder->filled_amount;
                $sellingAmount = $sellSideOrder->amount - $sellSideOrder->filled_amount;

                if ($sellingAmount <= 0 || $buyAmount <= 0) continue;

                $tradeAmount = $sellingAmount >= $buyAmount ? $buyAmount : $sellingAmount;

                Log::info('TradeManager: Creating trade match', [
                    'pair' => $buySideOrder->pair->symbol,
                    'rate' => $rate,
                    'amount' => $tradeAmount,
                    'buyer_id' => $buySideOrder->user_id,
                    'seller_id' => $sellSideOrder->user_id,
                    'buy_order_id' => $buySideOrder->id,
                    'sell_order_id' => $sellSideOrder->id
                ]);

                $this->createTrade($tradeSideBuy, $buySideOrder, $rate, $tradeAmount, $buySideOrder->user_id);
                $tradesCreated++;

                $buyerWallet  = Wallet::where('user_id', $buySideOrder->user_id)->where('currency_id', $buySideOrder->pair->coin->id)->spot()->first();
                $buySideOrder = $this->updateOrder($buySideOrder, $tradeAmount);

                $details = showAmount($tradeAmount,currencyFormat:false) . ' ' . $buySideOrder->pair->coin->symbol . ' Buy completed on pair ' . $buySideOrder->pair->symbol;
                $this->createTrx($buySideOrder, $buyerWallet, $tradeAmount, 'trade_buy', $details, $tradeAmount, "Buy");

                $totalSellingAmount = $tradeAmount * $rate;
                $charge             = 0;

                if ($sellSideOrder->charge > 0) {
                    $sellingPercentage   = ($tradeAmount / $sellSideOrder->amount) * 100;
                    $charge              = ($sellSideOrder->charge / 100) * $sellingPercentage;
                }

                $this->createTrade($tradeSideSell, $sellSideOrder, $rate, $tradeAmount, $sellSideOrder->user_id, $charge);
                $sellerWallet = Wallet::where('user_id', $sellSideOrder->user_id)->where('currency_id', $sellSideOrder->pair->market->currency_id)->spot()->first();
                $this->updateOrder($sellSideOrder, $tradeAmount);

                $details = showAmount($tradeAmount,currencyFormat:false) . ' ' . $sellSideOrder->pair->coin->symbol . ' Sell completed on pair ' . $sellSideOrder->pair->symbol;
                $this->createTrx($sellSideOrder, $sellerWallet, $totalSellingAmount, 'trade_sell', $details, $tradeAmount, "Sell", $charge);
            }
        }

        Log::info('TradeManager: Inserting trades and transactions', [
            'trades_count' => count($this->trades),
            'transactions_count' => count($this->transactions)
        ]);
        
        if (count($this->trades) > 0) {
            Trade::insert($this->trades);
            Log::info('TradeManager: Trades inserted successfully', [
                'count' => count($this->trades)
            ]);
        }
        
        if (count($this->transactions) > 0) {
            Transaction::insert($this->transactions);
            Log::info('TradeManager: Transactions inserted successfully', [
                'count' => count($this->transactions)
            ]);
        }
        
        try {
            event(new EventsTrade($this->tradeWithSymbol));
            Log::info('TradeManager: Trade events fired successfully');
        } catch (Exception $ex) {
            Log::error('TradeManager: Failed to fire trade events', [
                'error' => $ex->getMessage()
            ]);
            $general                     = gs();
            $general->cron_error_message = $ex->getMessage();
            $general->save();
        }
        
        Log::info('TradeManager: Trade processing completed', [
            'total_trades_created' => $tradesCreated
        ]);
    }

    private function createTrx($order, $wallet, $amount, $remark, $details, $tradeAmount, $orderSide, $charge = 0)
    {
        $amount = (float) $amount;
        $charge = (float) $charge;
        $wallet->balance = (float) $wallet->balance;

        $wallet->balance = $wallet->balance + $amount;
        $wallet->save();

        $this->transactions[] = [
            'user_id'      => $order->user_id,
            'wallet_id'    => $wallet->id,
            'amount'       => $amount,
            'post_balance' => $wallet->balance,
            'charge'       => 0,
            'trx_type'     => '+',
            'details'      => $details,
            'trx'          => getTrx(),
            'remark'       => $remark,
            'created_at'    => now()
        ];

        if ($charge > 0) {

            $wallet->balance = $wallet->balance - $charge;
            $wallet->save();

            $this->transactions[] = [
                'user_id'      => $order->user_id,
                'wallet_id'    => $wallet->id,
                'amount'       => $charge,
                'post_balance' => $wallet->balance,
                'charge'       => 0,
                'trx_type'     => '-',
                'details'      => "Charge for" . $details,
                'trx'          => getTrx(),
                'remark'       => $remark,
                'created_at'    => now()
            ];
        }

        notify($order->user, 'ORDER_COMPLETE', [
            'pair'                   => $order->pair->symbol,
            'amount'                 => showAmount($tradeAmount,currencyFormat:false),
            'total'                  => showAmount($order->total,currencyFormat:false),
            'rate'                   => showAmount($order->rate,currencyFormat:false),
            'price'                  => showAmount($order->price,currencyFormat:false),
            'coin_symbol'            => @$order->pair->coin->symbol,
            'order_side'             => $orderSide,
            'market_currency_symbol' => @$order->pair->market->currency->symbol,
            'market'                 => @$order->pair->market->name,
            'filled_amount'          => showAmount(@$order->filled_amount,currencyFormat:false),
            'filled_percentage'      => getAmount(@$order->filed_percentage),
        ]);

        if (gs('trade_commission')) {
            levelCommission($order->user, $tradeAmount, 'trade_commission', $order->trx, $order->coin_id);
        }
    }

    private function createTrade($tradeSide, $order, $rate, $amount, $traderId, $charge = 0)
    {
        $trade = [
            'trader_id'  => $traderId,
            'pair_id'    => $order->pair_id,
            'trade_side' => $tradeSide,
            'order_id'   => $order->id,
            'rate'       => $rate,
            'amount'     => $amount,
            'total'      => $rate * $amount,
            'charge'     => $charge,
            'created_at' => now()
        ];
        $this->tradeWithSymbol[@$order->pair->symbol][] = $trade;
        $this->trades[] = $trade;
    }

    private function updateOrder($order, $amount)
    {
        $filedAmount    = $order->filled_amount + $amount;
        $filePercentage = ($filedAmount / $order->amount) * 100;

        if ($filedAmount == $order->amount) {
            $order->status = Status::ORDER_COMPLETED;
        }
        $order->filled_amount    = $filedAmount;
        $order->filed_percentage = $filePercentage;
        $order->save();
        return $order;
    }
}
