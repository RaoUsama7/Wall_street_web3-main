<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Page;
use App\Models\CoinPair;
use App\Models\Currency;
use App\Models\Frontend;
use App\Models\Language;
use App\Constants\Status;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\AdminNotification;
use App\Models\FutureTradeConfig;
use App\Models\Market;
use App\Models\MarketData;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;





class SiteController extends Controller
{
    public function index()
    {
        $reference = @$_GET['reference'];

        if ($reference) {
            session()->put('reference', $reference);
        }

        $pageTitle   = 'Home';
        $sections    = Page::where('tempname', activeTemplate())->where('slug', '/')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::home', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
    }

    public function pages($slug)
    {
        $page        = Page::where('tempname', activeTemplate())->where('slug', $slug)->firstOrFail();
        $pageTitle   = $page->name;
        $sections    = $page->secs;
        $seoContents = $page->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::pages', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
    }

    public function contact()
    {
        $pageTitle   = "Contact Us";
        $user        = auth()->user();
        $sections    = Page::where('tempname', activeTemplate())->where('slug', 'contact')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::contact', compact('pageTitle', 'user', 'sections', 'seoContents', 'seoImage'));
    }

    public function contactSubmit(Request $request)
    {
        $request->validate([
            'name'    => 'required',
            'email'   => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required',
        ]);

        $request->session()->regenerateToken();

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $random = getNumber();

        $ticket           = new SupportTicket();
        $ticket->user_id  = auth()->id() ?? 0;
        $ticket->name     = $request->name;
        $ticket->email    = $request->email;
        $ticket->priority = Status::PRIORITY_MEDIUM;

        $ticket->ticket     = $random;
        $ticket->subject    = $request->subject;
        $ticket->last_reply = Carbon::now();
        $ticket->status     = Status::TICKET_OPEN;
        $ticket->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = auth()->user() ? auth()->user()->id : 0;
        $adminNotification->title     = 'A new contact message has been submitted';
        $adminNotification->click_url = urlPath('admin.ticket.view', $ticket->id);
        $adminNotification->save();

        $message                    = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message           = $request->message;
        $message->save();

        $notify[] = ['success', 'Ticket created successfully!'];

        return to_route('ticket.view', [$ticket->ticket])->withNotify($notify);
    }

    public function policyPages($slug)
    {
        $policy      = Frontend::where('slug', $slug)->where('data_keys', 'policy_pages.element')->firstOrFail();
        $pageTitle   = $policy->data_values->title;
        $seoContents = $policy->seo_content;
        $seoImage    = @$seoContents->image ? frontendImage('policy_pages', $seoContents->image, getFileSize('seo'), true) : null;
        return view('Template::policy', compact('policy', 'pageTitle', 'seoContents', 'seoImage'));
    }

    public function changeLanguage($lang = null)
    {
        $language = Language::where('code', $lang)->first();
        if (!$language) {
            $lang = 'en';
        }

        session()->put('lang', $lang);
        return back();
    }

    public function blogDetails($slug)
    {
        $blog        = Frontend::where('slug', $slug)->where('data_keys', 'blog.element')->firstOrFail();
        $pageTitle   = $blog->data_values->title;
        $seoContents = $blog->seo_content;
        $seoImage    = @$seoContents->image ? frontendImage('blog', $seoContents->image, getFileSize('seo'), true) : null;
        return view('Template::blog_details', compact('blog', 'pageTitle', 'seoContents', 'seoImage'));
    }

    public function cookieAccept()
    {
        Cookie::queue('gdpr_cookie', gs('site_name'), 43200);
    }

    public function cookiePolicy()
    {
        $cookieContent = Frontend::where('data_keys', 'cookie.data')->first();
        abort_if($cookieContent->data_values->status != Status::ENABLE, 404);
        $pageTitle = 'Cookie Policy';
        $cookie    = Frontend::where('data_keys', 'cookie.data')->first();
        return view('Template::cookie', compact('pageTitle', 'cookie'));
    }

    public function placeholderImage($size = null)
    {
        $imgWidth  = explode('x', $size)[0];
        $imgHeight = explode('x', $size)[1];
        $text      = $imgWidth . '×' . $imgHeight;
        $fontFile  = realpath('assets/font/solaimanLipi_bold.ttf');
        $fontSize  = round(($imgWidth - 50) / 8);
        if ($fontSize <= 9) {
            $fontSize = 9;
        }
        if ($imgHeight < 100 && $fontSize > 30) {
            $fontSize = 30;
        }

        $image     = imagecreatetruecolor($imgWidth, $imgHeight);
        $colorFill = imagecolorallocate($image, 100, 100, 100);
        $bgFill    = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgFill);
        $textBox    = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth  = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);
        $textX      = ($imgWidth - $textWidth) / 2;
        $textY      = ($imgHeight + $textHeight) / 2;
        header('Content-Type: image/jpeg');
        imagettftext($image, $fontSize, 0, $textX, $textY, $colorFill, $fontFile, $text);
        imagejpeg($image);
        imagedestroy($image);
    }

    public function maintenance()
    {
        $pageTitle = 'Maintenance Mode';
        if (gs('maintenance_mode') == Status::DISABLE) {
            return to_route('home');
        }
        $maintenance = Frontend::where('data_keys', 'maintenance.data')->first();
        return view('Template::maintenance', compact('pageTitle', 'maintenance'));
    }

    public function pusherAuthentication($socketId, $channelName)
    {
        $general      = gs();
        $pusherSecret = @$general->pusher_config->pusher_app_secret;
        $str          = $socketId . ":" . $channelName;
        $hash         = hash_hmac('sha256', $str, $pusherSecret);

        return response()->json([
            'auth' => @$general->pusher_config->pusher_app_key . ":" . $hash,
        ]);
    }

    public function market()
    {
        $pageTitle = 'Market List';
        $sections  = Page::where('tempname', activeTemplate())->where('slug', 'market')->first();
        return view('Template::market_list', compact('pageTitle', 'sections'));
    }
    public function crypto()
    {
        $pageTitle = 'Cryptocurrency';
        $sections  = Page::where('tempname', activeTemplate())->where('slug', 'crypto-currency')->first();
        return view('Template::crypto_currency', compact('pageTitle', 'sections'));
    }

    public function marketList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:all,crypto,fiat',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->all(),
            ]);
        }

        $query = CoinPair::searchable(['symbol'])
            ->select('id', 'market_id', 'coin_id', 'symbol', 'type')
            ->whereHas('marketData');

        if ($request->type != 'all') {
            $query->whereHas('market', function ($q) use ($request) {
                $q->whereHas('currency', function ($c) use ($request) {
                    if ($request->type == 'crypto') {
                        return $c->crypto();
                    }
                    $c->fiat();
                });
            });
        }

        $query = $query->with('market:id,name,currency_id', 'coin:id,name,symbol,image', 'market.currency:id,name,symbol,image', 'marketData')
            ->withCount('trade as total_trade')
            ->orderBy('total_trade', 'desc');

        $total = (clone $query)->count();
        $pairs = (clone $query)->skip($request->skip ?? 0)
            ->take($request->limit ?? 20)
            ->get();

        $activeFuturePairs = FutureTradeConfig::active()->pluck('pair_id')->toArray();

        return response()->json([
            'success' => true,
            'pairs'   => $pairs,
            'total'   => $total,
            'active_future_pairs' => $activeFuturePairs
        ]);
    }

    public function cryptoCurrencyList(Request $request)
    {
        $query = Currency::active()->crypto()->with('marketData')->rankOrdering()
            ->searchable(['name', 'symbol']);

        $total      = (clone $query)->count();
        $currencies = (clone $query)->skip($request->skip ?? 0)
            ->take($request->limit ?? 20)
            ->get();

        return response()->json([
            'success'    => true,
            'currencies' => $currencies,
            'total'      => $total,
        ]);
    }

    public function pwaConfiguration()
    {
        $gs   = gs();
        $json = [
            "name"             => $gs->site_name,
            "sign"             => $gs->site_name,
            "start_url"        => route('trade'),
            "display"          => "standalone",
            "background_color" => "#5900b3",
            "theme_color"      => "black",
            "description"      => $gs->site_name . " PWA",
            "icons"            => [
                [
                    "src"   => getImage(getFilePath('logoIcon') . '/pwa_favicon.png'),
                    "sizes" => "192x192",
                    "type"  => "image/png",
                ],
                [
                    "src"   => getImage(getFilePath('logoIcon') . '/pwa_thumb.png'),
                    "sizes" => "512x512",
                    "type"  => "image/png",
                ],
            ],
        ];

        return response()->json($json);
    }
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:subscribers,email',
        ], [
            'email.unique' => "You have already subscribed",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => $validator->errors()->all(),
                'success' => false,
            ]);
        }

        $subscribe        = new Subscriber();
        $subscribe->email = $request->email;
        $subscribe->save();

        return response()->json([
            'message' => "Thank you for subscribing us",
            'success' => true,
        ]);
    }

    public function about()
    {
        $pageTitle = "About Us";
        $sections  = Page::where('tempname', activeTemplate())->where('slug', 'about-us')->firstOrFail();
        return view('Template::about', compact('pageTitle', 'sections'));
    }


    public function StocksUS()
    {
        $pageTitle = 'US Market Stocks';
        return view('templates.basic.us_market', compact('pageTitle'));
    }



    public function getStocksData(Request $request)
    {
        $page = max((int)$request->get('page', 1), 1);
        $perPage = 50;
        $search = trim($request->get('search', ''));
        $cacheKey = "nasdaq_stocks_page_{$page}_search_" . md5($search);

        $result = Cache::remember($cacheKey, 300, function () use ($page, $perPage, $search) {
            $finnhubKey = env('FINNHUB_API_KEY');

            $topSymbols = [
                'AAPL', 'MSFT', 'NVDA', 'AMZN', 'GOOGL', 'GOOG', 'META', 'TSLA', 'AVGO', 'JPM',
                'V', 'UNH', 'MA', 'XOM', 'LLY', 'JNJ', 'PG', 'ORCL', 'COST', 'HD',
                'MRK', 'CVX', 'ABBV', 'KO', 'PEP', 'BAC', 'WMT', 'MCD', 'CRM', 'ADBE',
                'NFLX', 'TMO', 'AMD', 'PFE', 'AXP', 'NKE', 'DHR', 'INTC', 'LIN', 'IBM',
                'TXN', 'CAT', 'HON', 'UPS', 'QCOM', 'AMAT', 'SBUX', 'ISRG', 'BA', 'GS',
            ];

            $topCryptoSymbols = [
                'BTC', 'ETH', 'BNB', 'SOL', 'XRP', 'USDC', 'DOGE', 'ADA', 'AVAX', 'DOT',
                'TRX', 'LINK', 'MATIC', 'SHIB', 'LTC', 'UNI', 'BCH', 'ATOM', 'XLM', 'ETC',
                'APT', 'FIL', 'HBAR', 'ICP', 'NEAR', 'ARB', 'OP', 'MKR', 'AAVE', 'STX',
                'QNT', 'EGLD', 'FLOW', 'SAND', 'AXS', 'MANA', 'XTZ', 'RUNE', 'THETA', 'FTM',
                'KLAY', 'ALGO', 'GRT', 'INJ', 'IMX', 'SEI', 'RNDR', 'BONK', 'XMR', 'TWT',
            ];
            $allStocks = $topSymbols;

            if (empty($allStocks)) {
                return [
                    'stocks' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                    ],
                    'error' => 'Unable to fetch stock list'
                ];
            }

            if (!empty($search)) {
                $searchUpper = strtoupper($search);
                $allStocks = array_filter($allStocks, function ($symbol) use ($searchUpper) {
                    return str_contains($symbol, $searchUpper);
                });
                $allStocks = array_values($allStocks);
            }

            $total = count($allStocks);
            $lastPage = (int)ceil($total / $perPage);
            $offset = ($page - 1) * $perPage;
            $pageSymbols = array_slice($allStocks, $offset, $perPage);

            $stocks = [];
            $usdtIcon = "https://s2.coinmarketcap.com/static/img/coins/64x64/825.png";

            foreach ($pageSymbols as $symbol) {
                try {
                    $quoteData = Cache::remember("stock_quote_{$symbol}", 60, function () use ($symbol, $finnhubKey) {
                        $url = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$finnhubKey}";
                        $response = @file_get_contents($url);

                        if (!$response) return null;
                        $data = json_decode($response, true);

                        if (isset($data['c']) && $data['c'] > 0) {
                            return [
                                'price' => $data['c'],
                                'change' => $data['d'] ?? 0,
                                'percent' => $data['dp'] ?? 0,
                                'high' => $data['h'] ?? 0,
                                'low' => $data['l'] ?? 0,
                                'open' => $data['o'] ?? 0,
                                'prev_close' => $data['pc'] ?? 0,
                            ];
                        }
                        return null;
                    });

                    if (!$quoteData || $quoteData['price'] == 0) continue;

                    $profile = Cache::remember("stock_profile_{$symbol}", 86400, function () use ($symbol, $finnhubKey) {
                        $url = "https://finnhub.io/api/v1/stock/profile2?symbol={$symbol}&token={$finnhubKey}";
                        $response = @file_get_contents($url);

                        if (!$response) return null;
                        return json_decode($response, true);
                    });

                    $name = $profile['name'] ?? $symbol;
                    $marketCap = isset($profile['marketCapitalization']) ? $profile['marketCapitalization'] * 1_000_000 : 0;

                    $fallbackIcon = "https://ui-avatars.com/api/?name={$symbol}&background=random&rounded=true";
                    $stockIcon = $fallbackIcon;

                    if (!empty($profile['logo'])) {
                        $stockIcon = $profile['logo'];
                    } else {
                        $tradingViewLogo = "https://s3-symbol-logo.tradingview.com/" . strtolower($symbol) . "--big.svg";
                        $headers = @get_headers($tradingViewLogo);
                        if ($headers && strpos($headers[0], '200') !== false) {
                            $stockIcon = $tradingViewLogo;
                        }
                    }

                    $change1h = $quoteData['percent'] / 24;

                    $stocks[] = [
                        'symbol' => $symbol,
                        'name' => $name,
                        'price' => round($quoteData['price'], 2),
                        'change_1h' => round($change1h, 2),
                        'change_24h' => round($quoteData['percent'], 2),
                        'market_cap' => $marketCap,
                        'pair_icons' => [$stockIcon, $usdtIcon],
                    ];

                    usleep(100000);
                } catch (\Throwable $e) {
                    \Log::error("Error fetching stock data for {$symbol}: " . $e->getMessage());
                    continue;
                }
            }

            return [
                'stocks' => $stocks,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ],
                'quote_currency' => 'USDT',
                'supported_pairs' => array_map(function ($symbol) {
                    return $symbol . '/USDT';
                }, $allStocks),
                'top_crypto_pairs' => array_map(function ($symbol) {
                    return $symbol . '/USDT';
                }, $topCryptoSymbols),
            ];
        });

        return response()->json($result);
    }

    public function stockTradeFull($symbol)
    {

        $pair = CoinPair::active()->activeMarket()->activeCoin()->where('symbol', $symbol)->with('market.currency', 'coin', 'marketData')->first();

        if (!$pair && str_contains($symbol, '/')) {
            $afterSlash = last(explode('/', $symbol));
            $tryVariants = [str_replace('/', '_', $symbol), $afterSlash];
            foreach ($tryVariants as $try) {
                $pair = CoinPair::active()->activeMarket()->activeCoin()->where('symbol', $try)->with('market.currency', 'coin', 'marketData')->first();
                if ($pair) break;
            }
        }

        if (!$pair) {
            // Try common variants mapped to USDT market
            $normalizedSymbol = strtoupper(str_replace(['/', '_'], '', $symbol));
            $possibleSymbols = array_unique([
                $symbol . '/USDT',
                $symbol . 'USDT',
                $normalizedSymbol . '/USDT',
                $normalizedSymbol . 'USDT',
                $normalizedSymbol . '_USDT',
            ]);

            $pair = CoinPair::active()
                ->activeMarket()
                ->activeCoin()
                ->whereIn('symbol', $possibleSymbols)
                ->with('market.currency', 'coin', 'marketData')
                ->first();
        }

        if ($pair) {
            $userId = Auth::id() ?? 0;
            $originalPairSymbol = $pair->symbol;

            $apiSymbol = null;
            if (isset($pair->coin) && isset($pair->coin->symbol) && !empty($pair->coin->symbol)) {
                $apiSymbol = strtoupper(trim($pair->coin->symbol));
            }

            if (empty($apiSymbol)) {
                $pairSymbol = strtoupper(str_replace(['/', '_'], '', $pair->symbol));
                $currencySuffixes = ['USD', 'USDT', 'USDC', 'BTC', 'ETH', 'BNB', 'BUSD'];
                foreach ($currencySuffixes as $suffix) {
                    if (strlen($pairSymbol) > strlen($suffix) && strtoupper(substr($pairSymbol, -strlen($suffix))) === $suffix) {
                        $apiSymbol = substr($pairSymbol, 0, -strlen($suffix));
                        break;
                    }
                }
                if (empty($apiSymbol)) {
                    $apiSymbol = $pairSymbol;
                }
            }

            $normalizedSymbol = strtoupper(trim($apiSymbol));
            $finnhubKey = env('FINNHUB_API_KEY');

            if ($finnhubKey && $pair->marketData) {
                $quoteData = Cache::remember("stock_quote_{$normalizedSymbol}", 60, function () use ($normalizedSymbol, $finnhubKey) {
                    $url = "https://finnhub.io/api/v1/quote?symbol={$normalizedSymbol}&token={$finnhubKey}";
                    $response = @file_get_contents($url);

                    if (!$response) return null;
                    $data = json_decode($response, true);

                    if (isset($data['c']) && $data['c'] > 0) {
                        return [
                            'price' => $data['c'],
                            'change' => $data['d'] ?? 0,
                            'percent' => $data['dp'] ?? 0,
                            'high' => $data['h'] ?? 0,
                            'low' => $data['l'] ?? 0,
                            'open' => $data['o'] ?? 0,
                            'prev_close' => $data['pc'] ?? 0,
                        ];
                    }
                    return null;
                });

                $profile = Cache::remember("stock_profile_{$normalizedSymbol}", 86400, function () use ($normalizedSymbol, $finnhubKey) {
                    $url = "https://finnhub.io/api/v1/stock/profile2?symbol={$normalizedSymbol}&token={$finnhubKey}";
                    $response = @file_get_contents($url);

                    if (!$response) return null;
                    return json_decode($response, true);
                });

                if ($quoteData) {
                    $marketData = $pair->marketData;

                    $previousPrice = $marketData->price ?? 0;
                    $previousPercent1h = $marketData->percent_change_1h ?? 0;
                    $previousPercent24h = $marketData->percent_change_24h ?? 0;
                    $previousPercent7d = $marketData->percent_change_7d ?? 0;

                    $stockPrice = $quoteData['price'] ?? 0;
                    $stockPercent = $quoteData['percent'] ?? 0;
                    $percentChange1h = round($stockPercent / 24, 2);
                    $marketCap = isset($profile['marketCapitalization']) ? (float) $profile['marketCapitalization'] * 1_000_000 : 0;

                    $marketData->last_price = $previousPrice;
                    $marketData->last_percent_change_1h = $previousPercent1h;
                    $marketData->last_percent_change_24h = $previousPercent24h;
                    $marketData->last_percent_change_7d = $previousPercent7d;

                    $marketData->price = $stockPrice;
                    $marketData->percent_change_1h = $percentChange1h;
                    $marketData->percent_change_24h = $stockPercent;
                    $marketData->percent_change_7d = $marketData->percent_change_7d ?? 0;
                    $marketData->market_cap = $marketCap;

                    $marketData->html_classes = [
                        'price_change' => upOrDown($marketData->price, $marketData->last_price),
                        'percent_change_1h' => upOrDown($marketData->percent_change_1h, $marketData->last_percent_change_1h),
                        'percent_change_24h' => upOrDown($marketData->percent_change_24h, $marketData->last_percent_change_24h),
                        'percent_change_7d' => upOrDown($marketData->percent_change_7d, $marketData->last_percent_change_7d),
                    ];

                    $marketData->save();
                    $marketData->refresh();
                    $pair->setRelation('marketData', $marketData);

                    if ($pair->coin) {
                        $pair->coin->rate = $stockPrice;
                        $pair->coin->save();

                        $coinMarketData = $pair->coin->marketData;

                        if ($coinMarketData) {
                            $previousCoinPrice = $coinMarketData->price ?? 0;
                            $previousCoinPercent1h = $coinMarketData->percent_change_1h ?? 0;
                            $previousCoinPercent24h = $coinMarketData->percent_change_24h ?? 0;
                            $previousCoinPercent7d = $coinMarketData->percent_change_7d ?? 0;

                            $coinMarketData->last_price = $previousCoinPrice;
                            $coinMarketData->last_percent_change_1h = $previousCoinPercent1h;
                            $coinMarketData->last_percent_change_24h = $previousCoinPercent24h;
                            $coinMarketData->last_percent_change_7d = $previousCoinPercent7d;

                            $coinMarketData->price = $stockPrice;
                            $coinMarketData->percent_change_1h = $percentChange1h;
                            $coinMarketData->percent_change_24h = $stockPercent;
                            $coinMarketData->percent_change_7d = $coinMarketData->percent_change_7d ?? 0;
                            $coinMarketData->market_cap = $marketCap;

                            $coinMarketData->html_classes = [
                                'price_change' => upOrDown($coinMarketData->price, $coinMarketData->last_price),
                                'percent_change_1h' => upOrDown($coinMarketData->percent_change_1h, $coinMarketData->last_percent_change_1h),
                                'percent_change_24h' => upOrDown($coinMarketData->percent_change_24h, $coinMarketData->last_percent_change_24h),
                                'percent_change_7d' => upOrDown($coinMarketData->percent_change_7d, $coinMarketData->last_percent_change_7d),
                            ];

                            $coinMarketData->save();
                            $coinMarketData->refresh();
                            $pair->coin->setRelation('marketData', $coinMarketData);
                        }
                    }
                }
            }

            $futureConfig = \App\Models\FutureTradeConfig::active()->where('pair_id', $pair->id)->with('coinPair')->first();

            // Create FutureTradeConfig if it doesn't exist (needed for trading to work)
            if (!$futureConfig) {
                $futureConfig = new \App\Models\FutureTradeConfig();
                $futureConfig->pair_id = $pair->id;
                $futureConfig->leverage = 10; // Default leverage
                $futureConfig->min_buy_amount = 0.01;
                $futureConfig->max_buy_amount = -1; // No limit
                $futureConfig->min_sell_amount = 0.01;
                $futureConfig->max_sell_amount = -1; // No limit
                $futureConfig->buy_charge = 0;
                $futureConfig->sell_charge = 0;
                $futureConfig->maintenance_margin_rate = 0.01; // 1% maintenance margin
                $futureConfig->status = Status::ENABLE;
                $futureConfig->save();
            }

            $futurePair = $futureConfig;
            $otherPairs = \App\Models\FutureTradeConfig::active()->with('coinPair')->where('id', '!=', $futurePair->id)->get();
            $favoritePairs = \App\Models\FavoritePair::where('user_id', $userId)->where('future_trade_config_id', '>', 0)->pluck('future_trade_config_id')->toArray();
            $recentTrades = \App\Models\FutureTrade::where('future_trade_config_id', $futurePair->id)->orderBy('id', 'desc')->take(6)->get();
            
            // Ensure wallets exist for coin and market currency
            if ($userId) {
                $walletTypes = gs('wallet_types');
                $currenciesToCheck = [$pair->coin->id, $pair->market->currency_id];
                
                foreach ($currenciesToCheck as $currencyId) {
                    foreach ($walletTypes as $walletType) {
                        $existingWallet = \App\Models\Wallet::where('user_id', $userId)
                            ->where('currency_id', $currencyId)
                            ->where('wallet_type', $walletType->type_value)
                            ->first();
                        
                        if (!$existingWallet) {
                            \App\Models\Wallet::create([
                                'user_id' => $userId,
                                'currency_id' => $currencyId,
                                'wallet_type' => $walletType->type_value,
                            ]);
                        }
                    }
                }
            }
            
            $coinWallet = \App\Models\Wallet::where('user_id', $userId)->where('currency_id', $pair->coin->id)->future()->first();
            $asset['wallet_balance'] = \App\Models\Wallet::future()->where('user_id', $userId)->join('currencies', 'wallets.currency_id', 'currencies.id')->sum(\Illuminate\Support\Facades\DB::raw('currencies.rate * wallets.balance'));

            // Format symbol for TradingView widget - use clean symbol without exchange prefix
            $exchange = $pair->listed_market_name ?? 'NASDAQ';
            
            // Extract clean stock symbol
            $stockSymbol = null;
            if (isset($pair->coin) && isset($pair->coin->symbol) && !empty($pair->coin->symbol)) {
                $stockSymbol = strtoupper(trim($pair->coin->symbol));
            }
            
            // Fallback: extract from pair symbol if coin symbol not available
            if (empty($stockSymbol)) {
                $pairSymbol = strtoupper(str_replace(['/', '_'], '', $pair->symbol));
                if (strlen($pairSymbol) > 4 && strtoupper(substr($pairSymbol, -4)) === 'USDT') {
                    $stockSymbol = substr($pairSymbol, 0, -4);
                } else {
                    $stockSymbol = $pairSymbol;
                }
            }
            
            // Use clean symbol without exchange prefix
            $normalizedSymbol = strtoupper(trim($stockSymbol));
            $pair->display_symbol = $normalizedSymbol;
            $pair->chart_symbol = $normalizedSymbol;
            $pair->symbol = $originalPairSymbol;
            $pair->listed_market_name = $exchange;

            $pageTitle = showAmount(@$pair->marketData->price, currencyFormat: false) . ' | ' . $normalizedSymbol;

            return view('Template::future.trade', compact('pageTitle', 'futurePair', 'pair', 'coinWallet', 'otherPairs', 'favoritePairs', 'recentTrades', 'asset'));
        }

        // If no pair exists, render the future trade layout using lightweight stock data
        $finnhubKey = env('FINNHUB_API_KEY');
        
        // Get quote data from FinnHub (same as getStocksData method)
        $quoteData = Cache::remember("stock_quote_{$symbol}", 60, function () use ($symbol, $finnhubKey) {
            $url = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$finnhubKey}";
            $response = @file_get_contents($url);

            if (!$response) return null;
            $data = json_decode($response, true);

            if (isset($data['c']) && $data['c'] > 0) {
                return [
                    'price' => $data['c'],
                    'change' => $data['d'] ?? 0,
                    'percent' => $data['dp'] ?? 0,
                    'high' => $data['h'] ?? 0,
                    'low' => $data['l'] ?? 0,
                    'open' => $data['o'] ?? 0,
                    'prev_close' => $data['pc'] ?? 0,
                ];
            }
            return null;
        });

        // Get profile data from FinnHub (same as getStocksData method)
        $profile = Cache::remember("stock_profile_{$symbol}", 86400, function () use ($symbol, $finnhubKey) {
            $url = "https://finnhub.io/api/v1/stock/profile2?symbol={$symbol}&token={$finnhubKey}";
            $response = @file_get_contents($url);

            if (!$response) return null;
            return json_decode($response, true);
        });

        $stockPrice = $quoteData['price'] ?? 0;
        $stockChange = $quoteData['change'] ?? 0;
        $stockPercent = $quoteData['percent'] ?? 0;
        $stockName = $profile['name'] ?? $symbol;
        $marketCap = isset($profile['marketCapitalization']) ? (float) $profile['marketCapitalization'] * 1_000_000 : 0;

        // Determine the correct exchange from profile data or default to NASDAQ
        $exchange = 'NASDAQ';
        if (isset($profile['exchange'])) {
            $exchange = strtoupper($profile['exchange']);
            // Map common exchange codes to TradingView format (use exact TradingView codes)
            $exchangeMap = [
                'US' => 'NASDAQ',
                'NASDAQ' => 'NASDAQ', 
                'NYSE' => 'NYSE',
                'AMEX' => 'AMEX',
                'NYSEARCA' => 'NASDAQ', // ETFs typically show as NASDAQ on TradingView
                'BATS' => 'NASDAQ',
                'OTC' => 'OTC',
                'PINK' => 'OTC'
            ];
            $exchange = $exchangeMap[$exchange] ?? 'NASDAQ';
        }

        // Keep symbol clean for TradingView
        $cleanSymbol = strtoupper($symbol);
        $pairSymbol = $cleanSymbol . '_USDT';
        
        // Ensure USDT currency exists
        $usdtCurrency = Currency::where('symbol', 'USDT')->first();
        if (!$usdtCurrency) {
            $usdtCurrency = new Currency();
            $usdtCurrency->name = 'Tether USD';
            $usdtCurrency->symbol = 'USDT';
            $usdtCurrency->type = Status::CRYPTO_CURRENCY;
            $usdtCurrency->rate = 1;
            $usdtCurrency->status = Status::ENABLE;
            $usdtCurrency->save();
        }

        // Ensure USDT market exists
        $usdtMarket = Market::where('currency_id', $usdtCurrency->id)->first();
        if (!$usdtMarket) {
            $usdtMarket = new Market();
            $usdtMarket->name = 'USDT Market';
            $usdtMarket->currency_id = $usdtCurrency->id;
            $usdtMarket->status = Status::ENABLE;
            $usdtMarket->save();
        }

        // Create or get stock currency
        $stockCurrency = Currency::where('symbol', $cleanSymbol)->first();
        if (!$stockCurrency) {
            $stockCurrency = new Currency();
            $stockCurrency->name = $stockName;
            $stockCurrency->symbol = $cleanSymbol;
            $stockCurrency->type = Status::CRYPTO_CURRENCY; // Treat stocks as crypto for trading purposes
            $stockCurrency->rate = $stockPrice;
            $stockCurrency->status = Status::ENABLE;
            $stockCurrency->save();
        } else {
            // Update rate if currency exists
            $stockCurrency->rate = $stockPrice;
            $stockCurrency->save();
        }

        // Create or get coin pair
        $pair = CoinPair::where('symbol', $pairSymbol)->first();
        if (!$pair) {
            $pair = new CoinPair();
            $pair->market_id = $usdtMarket->id;
            $pair->coin_id = $stockCurrency->id;
            $pair->symbol = $pairSymbol;
            $pair->type = Status::BOTH_TRADE; // Allow both spot and future trading
            $pair->listed_market_name = $exchange;
            $pair->minimum_buy_amount = 0.01;
            $pair->maximum_buy_amount = -1; // No limit
            $pair->minimum_sell_amount = 0.01;
            $pair->maximum_sell_amount = -1; // No limit
            $pair->percent_charge_for_buy = 0;
            $pair->percent_charge_for_sell = 0;
            $pair->status = Status::ENABLE;
            $pair->save();
        } else {
            // Update listed market name if pair exists
            $pair->listed_market_name = $exchange;
            $pair->save();
        }

        $percentChange1h = round($stockPercent / 24, 2);

        // Create or update market data
        $marketData = MarketData::where('pair_id', $pair->id)->first();
        $previousPrice = $marketData->price ?? $stockPrice;
        $previousPercent1h = $marketData->percent_change_1h ?? $percentChange1h;
        $previousPercent24h = $marketData->percent_change_24h ?? $stockPercent;
        $previousPercent7d = $marketData->percent_change_7d ?? 0;

        if (!$marketData) {
            $marketData = new MarketData();
            $marketData->pair_id = $pair->id;
            $marketData->currency_id = $stockCurrency->id;
            $marketData->symbol = $pairSymbol;
        }

        $marketData->last_price = $previousPrice;
        $marketData->last_percent_change_1h = $previousPercent1h;
        $marketData->last_percent_change_24h = $previousPercent24h;
        $marketData->last_percent_change_7d = $previousPercent7d;

        $marketData->price = $stockPrice;
        $marketData->percent_change_24h = $stockPercent;
        $marketData->percent_change_1h = $percentChange1h;
        $marketData->percent_change_7d = 0;
        $marketData->market_cap = $marketCap;
        $marketData->symbol = $pairSymbol;

        $marketData->html_classes = [
            'price_change' => upOrDown($marketData->price, $marketData->last_price),
            'percent_change_1h' => upOrDown($marketData->percent_change_1h, $marketData->last_percent_change_1h),
            'percent_change_24h' => upOrDown($marketData->percent_change_24h, $marketData->last_percent_change_24h),
            'percent_change_7d' => upOrDown($marketData->percent_change_7d, $marketData->last_percent_change_7d),
        ];

        $marketData->save();

        // Create or get future trade config
        $futureConfig = FutureTradeConfig::where('pair_id', $pair->id)->first();
        if (!$futureConfig) {
            $futureConfig = new FutureTradeConfig();
            $futureConfig->pair_id = $pair->id;
            $futureConfig->leverage = 10; // Default leverage
            $futureConfig->min_buy_amount = 0.01;
            $futureConfig->max_buy_amount = -1; // No limit
            $futureConfig->min_sell_amount = 0.01;
            $futureConfig->max_sell_amount = -1; // No limit
            $futureConfig->buy_charge = 0;
            $futureConfig->sell_charge = 0;
            $futureConfig->maintenance_margin_rate = 0.01; // 1% maintenance margin
            $futureConfig->status = Status::ENABLE;
            $futureConfig->save();
        }

        // Reload pair with relationships (ensure coin is loaded for symbol extraction)
        $pair = CoinPair::where('id', $pair->id)->with('market.currency', 'coin', 'marketData')->first();
        $originalPairSymbol = $pair->symbol;
        
        $userId = Auth::id() ?? 0;
        
        // Ensure wallets exist for USDT and stock currency if user is logged in
        if ($userId) {
            $walletTypes = gs('wallet_types');
        $currenciesToCheck = [$usdtCurrency->id, $stockCurrency->id];
            
            foreach ($currenciesToCheck as $currencyId) {
                foreach ($walletTypes as $walletType) {
                    $existingWallet = \App\Models\Wallet::where('user_id', $userId)
                        ->where('currency_id', $currencyId)
                        ->where('wallet_type', $walletType->type_value)
                        ->first();
                    
                    if (!$existingWallet) {
                        \App\Models\Wallet::create([
                            'user_id' => $userId,
                            'currency_id' => $currencyId,
                            'wallet_type' => $walletType->type_value,
                        ]);
                    }
                }
            }
        }
        
        $futurePair = $futureConfig;
        $otherPairs = FutureTradeConfig::active()->with('coinPair')->where('id', '!=', $futurePair->id)->get();
        $favoritePairs = \App\Models\FavoritePair::where('user_id', $userId)->where('future_trade_config_id', '>', 0)->pluck('future_trade_config_id')->toArray();
        $recentTrades = \App\Models\FutureTrade::where('future_trade_config_id', $futurePair->id)->orderBy('id', 'desc')->take(6)->get();
        
        // Get coin wallet (for the stock currency)
        $coinWallet = \App\Models\Wallet::where('user_id', $userId)->where('currency_id', $pair->coin->id)->future()->first();
        
        // Calculate total wallet balance (converted using currency rates)
        $asset['wallet_balance'] = \App\Models\Wallet::future()
            ->where('user_id', $userId)
            ->join('currencies', 'wallets.currency_id', 'currencies.id')
            ->sum(\Illuminate\Support\Facades\DB::raw('currencies.rate * wallets.balance'));

        // Format symbol for TradingView widget - use clean symbol without exchange prefix
        // Extract clean stock symbol
        $stockSymbol = null;
        if (isset($pair->coin) && isset($pair->coin->symbol) && !empty($pair->coin->symbol)) {
            $stockSymbol = strtoupper(trim($pair->coin->symbol));
        }
        
        // Fallback: extract from pair symbol if coin symbol not available
        if (empty($stockSymbol)) {
            $pairSymbol = strtoupper(str_replace(['/', '_'], '', $pair->symbol));
            if (strlen($pairSymbol) > 4 && strtoupper(substr($pairSymbol, -4)) === 'USDT') {
                $stockSymbol = substr($pairSymbol, 0, -4);
            } else {
                $stockSymbol = $pairSymbol;
            }
        }
        
        // Use clean symbol without exchange prefix
        $normalizedSymbol = strtoupper(trim($stockSymbol));
        $pair->display_symbol = $normalizedSymbol;
        $pair->chart_symbol = $normalizedSymbol;
        $pair->symbol = $originalPairSymbol;
        $pair->listed_market_name = $exchange;

        $pageTitle = showAmount($pair->marketData->price, currencyFormat: false) . ' | ' . $normalizedSymbol;

        return view('Template::future.trade', compact('pageTitle', 'futurePair', 'pair', 'coinWallet', 'otherPairs', 'favoritePairs', 'recentTrades', 'asset'));
    }
}
