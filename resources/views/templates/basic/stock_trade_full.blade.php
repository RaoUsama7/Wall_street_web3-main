@extends($activeTemplate . 'layouts.frontend')

@section('content')
    <div class="sidebar-overlay"></div>
    <div class="trading-section py-3 bg-color">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="future-trading-main">
                        <div class="future-trading-left flex-fill">
                            <div class="future-trading-content">
                                <div class="future-trading-content__chart">
                                    <div class="d-flex trading-header-wrapper flex-wrap">
                                        <div class="trading-header-wrapper-left">
                                            <div class="trading-price">
                                                <h4 class="mb-1">{{ showAmount($stock['price']) }} <span class="d-block trading-dropdown-button-title">{{ $stock['symbol'] }}</span></h4>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="trading-bottom__tab">
                                        {{-- Placeholder chart area --}}
                                        <div style="height:400px; background:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                                            <strong>@lang('Chart area for') {{ $stock['symbol'] }}</strong>
                                        </div>
                                    </div>

                                </div>
                                <div class="future-trading-content__history mt-4">
                                    <div class="trading-right__bottom">
                                        <div class="trading-history trading-left__top">
                                            <h5 class="trading-history__title">@lang('Stock Details')</h5>
                                        </div>
                                        <div class="market-wrapper">
                                            <p>@lang('Symbol'): {{ $stock['symbol'] }}</p>
                                            <p>@lang('Name'): {{ $stock['name'] }}</p>
                                            <p>@lang('Price'): {{ showAmount($stock['price']) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="future-trading-right d-none d-lg-block">
                            <div class="trading-bottom mt-0">
                                {{-- include simplified buy/sell form or redirect button --}}
                                <a href="{{ route('trade', $stock['symbol']) }}" class="btn btn--base w-100 btn--sm">@lang('Open Full Trade Page')</a>
                            </div>

                            <div class="trading-asset mt-3">
                                <div class="trading-asset__header">
                                    <h4 class="text--base">@lang('Assets')</h4>
                                </div>
                                <div class="trading-asset__body">
                                    <ul class="trading-asset-info">
                                        <li class="trading-asset-info__item">
                                            <span>@lang('Market Price'):</span> <span>{{ showAmount($stock['price']) }}</span>
                                        </li>
                                    </ul>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
    </div>
@endsection


