@extends('templates.basic.layouts.frontend')

@section('content')
    <section class="us-indices-section py-5">
        <div class="container">
            <div class="index-card mb-4 text-center">
                <h2 class="us-indices-title">@lang('US Stock Market')</h2>
                <p class="mb-3">
                    @lang('Explore the complete list of NASDAQ-listed U.S. stocks including name, symbol, type, and exchange.')
                </p>
            </div>

            <div class="table-wrapper">
                <div class="table-wrapper__item d-flex justify-content-between align-items-center flex-wrap mb-3">
                    <div class="table-header-menu">
                        <button type="button" class="table-header-menu__link market-type active">
                            <i class="las la-chart-line"></i> @lang('NASDAQ')
                        </button>
                    </div>

                    <!-- Search input -->
                    <input type="search" id="stockSearch" class="market-list-search-field form--control me-2"
                        placeholder="@lang('Search here...')" style="width: 200px; min-width: 150px;">
                </div>

                <!-- Loading indicator -->
                <div id="loadingIndicator" class="loading-overlay" style="display: none;">
                    <div class="loading-content">
                        <div class="spinner"></div>
                        <p class="loading-text">@lang('Loading stocks...')</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table coin-pair-list-table table--responsive--lg coin-pair-list">
                        <thead>
                            <tr>
                                <th>@lang('Pair')</th>
                                <th>@lang('Name')</th>
                                <th>@lang('Price')</th>
                                <th>@lang('1h Change')</th>
                                <th>@lang('24h Change')</th>
                                <th>@lang('Market Cap')</th>
                                <th class="text-end">@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody id="stockTableBody">
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Professional Pagination -->
                <div class="pagination-wrapper">
                    <div class="pagination-info" id="paginationInfo"></div>
                    <nav class="pagination-nav" id="paginationNav">
                        <ul class="pagination-list" id="paginationControls">
                            <!-- Pagination buttons will be generated here -->
                        </ul>
                    </nav>
                    <div class="pagination-jump">
                        <input type="number" id="pageJump" class="page-jump-input" placeholder="Page" min="1">
                        <button class="page-jump-btn" onclick="jumpToPage()">
                            <i class="las la-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection


@push('style')
    <style>
        /* --- Light / Dark Mode Base Styling --- */
        body.light-mode .us-indices-section {
            background-color: #ffffff;
            color: #111;
        }

        body.dark-mode .us-indices-section {
            background-color: #0d1117;
            color: #e0e0e0;
        }

        body.light-mode .table {
            background: #fff;
            color: #222;
            border-color: #e0e0e0;
        }

        body.dark-mode .table {
            background: #161b22;
            color: #ddd;
            border-color: #30363d;
        }

        body.light-mode .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        body.dark-mode .table thead th {
            background: #1c2128;
            border-bottom: 2px solid #30363d;
        }

        body.light-mode .table tbody tr:hover {
            background: #f8f9fa;
        }

        body.dark-mode .table tbody tr:hover {
            background: #1c2128;
        }

        /* Header Menu */
        body.light-mode .table-header-menu__link {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }

        body.dark-mode .table-header-menu__link {
            background: #21262d;
            color: #c9d1d9;
            border: 1px solid #30363d;
        }

        body.light-mode .table-header-menu__link.active {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        body.dark-mode .table-header-menu__link.active {
            background: #1f6feb;
            color: #fff;
            border-color: #1f6feb;
        }

        .table-header-menu__link {
            padding: 10px 20px;
            border-radius: 6px;
            transition: all 0.3s ease;
            margin-right: 8px;
            font-weight: 500;
            cursor: pointer;
        }

        /* Search Field */
        body.light-mode .market-list-search-field {
            background: #fff;
            color: #333;
            border: 1px solid #ced4da;
        }

        body.dark-mode .market-list-search-field {
            background: #0d1117;
            color: #c9d1d9;
            border: 1px solid #30363d;
        }

        .market-list-search-field {
            border-radius: 6px;
            padding: 8px 14px;
            transition: all 0.3s ease;
        }

        body.light-mode .market-list-search-field:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        body.dark-mode .market-list-search-field:focus {
            border-color: #1f6feb;
            box-shadow: 0 0 0 0.2rem rgba(31, 111, 235, 0.25);
        }

        body.light-mode .market-list-search-field::placeholder {
            color: #6c757d;
        }

        body.dark-mode .market-list-search-field::placeholder {
            color: #8b949e;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: relative;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
        }

        .loading-content {
            text-align: center;
        }

        .spinner {
            width: 50px;
            height: 50px;
            margin: 0 auto 20px;
            border-radius: 50%;
            border: 4px solid;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        body.light-mode .spinner {
            border-color: #0d6efd;
            border-top-color: transparent;
        }

        body.dark-mode .spinner {
            border-color: #1f6feb;
            border-top-color: transparent;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        body.light-mode .loading-text {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }

        body.dark-mode .loading-text {
            color: #8b949e;
            font-size: 14px;
            font-weight: 500;
        }

        /* Professional Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding: 20px 0;
            flex-wrap: wrap;
            gap: 15px;
        }

        body.light-mode .pagination-info {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }

        body.dark-mode .pagination-info {
            color: #8b949e;
            font-size: 14px;
            font-weight: 500;
        }

        .pagination-nav {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .pagination-list {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 6px;
            align-items: center;
        }

        .pagination-list .page-item {
            margin: 0;
        }

        .pagination-list .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
            border: 1px solid;
            text-decoration: none;
        }

        /* Light Mode Pagination */
        body.light-mode .pagination-list .page-link {
            background: #fff;
            color: #495057;
            border-color: #dee2e6;
        }

        body.light-mode .pagination-list .page-link:hover:not(.disabled) {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
        }

        body.light-mode .pagination-list .page-item.active .page-link {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: #fff;
            border-color: #0d6efd;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
            font-weight: 600;
            transform: scale(1.05);
        }

        body.light-mode .pagination-list .page-item.disabled .page-link {
            background: #f8f9fa;
            color: #adb5bd;
            border-color: #dee2e6;
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Dark Mode Pagination */
        body.dark-mode .pagination-list .page-link {
            background: #21262d;
            color: #c9d1d9;
            border-color: #30363d;
        }

        body.dark-mode .pagination-list .page-link:hover:not(.disabled) {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(31, 111, 235, 0.3);
        }

        body.dark-mode .pagination-list .page-item.active .page-link {
            background: linear-gradient(135deg, #1f6feb 0%, #1a5fd9 100%);
            color: #fff;
            border-color: #1f6feb;
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.5);
            font-weight: 600;
            transform: scale(1.05);
        }

        body.dark-mode .pagination-list .page-item.disabled .page-link {
            background: #161b22;
            color: #484f58;
            border-color: #30363d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Page Number Badge */
        .page-current-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-right: 10px;
        }

        body.light-mode .page-current-badge {
            background: linear-gradient(135deg, #e7f3ff 0%, #cfe7ff 100%);
            color: #0d6efd;
            border: 1px solid #b6d7ff;
        }

        body.dark-mode .page-current-badge {
            background: linear-gradient(135deg, #1a2942 0%, #152238 100%);
            color: #58a6ff;
            border: 1px solid #1f3b5f;
        }

        /* Pagination Jump */
        .pagination-jump {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .page-jump-input {
            width: 70px;
            height: 40px;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid;
            font-size: 14px;
            text-align: center;
            transition: all 0.2s ease;
        }

        body.light-mode .page-jump-input {
            background: #fff;
            color: #495057;
            border-color: #dee2e6;
        }

        body.dark-mode .page-jump-input {
            background: #0d1117;
            color: #c9d1d9;
            border-color: #30363d;
        }

        body.light-mode .page-jump-input:focus {
            border-color: #0d6efd;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        body.dark-mode .page-jump-input:focus {
            border-color: #1f6feb;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(31, 111, 235, 0.25);
        }

        .page-jump-btn {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            border: 1px solid;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        body.light-mode .page-jump-btn {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        body.dark-mode .page-jump-btn {
            background: #1f6feb;
            color: #fff;
            border-color: #1f6feb;
        }

        body.light-mode .page-jump-btn:hover {
            background: #0b5ed7;
            border-color: #0a58ca;
        }

        body.dark-mode .page-jump-btn:hover {
            background: #1a5fd9;
            border-color: #1857c7;
        }

        /* Trade Button */
        body.light-mode .trade-btn {
            background: #0d6efd;
            color: #fff;
            border: 1px solid #0d6efd;
        }

        body.dark-mode .trade-btn {
            background: #1f6feb;
            color: #fff;
            border: 1px solid #1f6feb;
        }

        body.light-mode .trade-btn:hover {
            background: #0b5ed7;
            border-color: #0a58ca;
        }

        body.dark-mode .trade-btn:hover {
            background: #1a5fd9;
            border-color: #1857c7;
        }

        .trade-btn {
            padding: 6px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .pagination-wrapper {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .pagination-info {
                text-align: center;
            }

            .pagination-nav {
                justify-content: center;
            }

            .pagination-jump {
                justify-content: center;
            }

            .pagination-list .page-link {
                min-width: 36px;
                height: 36px;
                padding: 6px 10px;
                font-size: 13px;
            }

            .page-jump-input,
            .page-jump-btn {
                height: 36px;
            }
        }
    </style>
@endpush

@push('script')
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let searchQuery = '';
        let searchTimeout;

        document.addEventListener("DOMContentLoaded", function() {
            // Initial load
            loadStocks(1);

            // Search functionality with debounce
            const searchInput = document.getElementById('stockSearch');
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchQuery = this.value.trim();
                
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadStocks(1);
                }, 500);
            });
        });

        let quoteCurrency = 'USDT';
        let tradeRouteTemplate = @json(route('stocks.us.trade', ['symbol' => '__symbol__']));

        function loadStocks(page) {
            const loadingIndicator = document.getElementById('loadingIndicator');
            const tableBody = document.getElementById('stockTableBody');
            
            // Show loading
            loadingIndicator.style.display = 'flex';
            tableBody.innerHTML = '';

            // AJAX request
            fetch(`{{ route('stocks.us.data') }}?page=${page}&search=${encodeURIComponent(searchQuery)}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                loadingIndicator.style.display = 'none';
                
                if (data.stocks && data.stocks.length > 0) {
                    quoteCurrency = data.quote_currency || 'USDT';
                    renderStocks(data.stocks);
                    renderPagination(data.pagination);
                    currentPage = page;
                    totalPages = data.pagination.last_page;
                } else {
                    showEmptyState();
                }
            })
            .catch(error => {
                console.error('Error loading stocks:', error);
                loadingIndicator.style.display = 'none';
                showErrorState();
            });
        }

      function renderStocks(stocks) {
    const tableBody = document.getElementById('stockTableBody');
    tableBody.innerHTML = '';

    stocks.forEach(stock => {
        const row = document.createElement('tr');

        // Build icon HTML
        let iconsHtml = '';
        if (stock.pair_icons && stock.pair_icons.length > 0) {
            stock.pair_icons.forEach(icon => {
                iconsHtml += `
                    <div class="me-1">
                        <img src="${icon}"
                             onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=${stock.symbol}&background=random';"
                             alt="${stock.symbol}" width="24" height="24"
                             class="rounded-circle border">
                    </div>
                `;
            });
        }

        // Build table row
        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    ${iconsHtml}
                    <div>
                        <span>${stock.symbol}/${quoteCurrency}</span>
                    </div>
                </div>
            </td>
            <td>
                <span style="font-size: 14px;">${stock.name}</span>
            </td>
            <td>
                <span class="${stock.change_24h >= 0 ? 'text--success' : 'text--danger'}">
                    ${formatAmount(stock.price)}
                </span>
            </td>
            <td>
                <span class="${stock.change_1h >= 0 ? 'text--success' : 'text--danger'}">
                    ${formatAmount(stock.change_1h, 2)}%
                </span>
            </td>
            <td>
                <span class="${stock.change_24h >= 0 ? 'text--success' : 'text--danger'}">
                    ${formatAmount(stock.change_24h, 2)}%
                </span>
            </td>
            <td>${formatAmount(stock.market_cap)}</td>
            <td class="text-end">
                <a href="${tradeRouteTemplate.replace('__symbol__', encodeURIComponent(stock.symbol))}" class="btn  btn-sm trade-btn">
                    Trade
                </a>
            </td>
        `;

        tableBody.appendChild(row);
    });
}

        function renderPagination(pagination) {
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationControls = document.getElementById('paginationControls');
            const pageJump = document.getElementById('pageJump');

            // Update info with current page badge
            paginationInfo.innerHTML = `
                <span class="page-current-badge">
                    <i class="las la-file-alt"></i>
                    Page ${pagination.current_page} of ${pagination.last_page}
                </span>
                <span>Showing ${pagination.from} to ${pagination.to} of ${pagination.total} stocks</span>
            `;
            pageJump.max = pagination.last_page;

            // Clear previous pagination
            paginationControls.innerHTML = '';

            // Previous button
            paginationControls.appendChild(createPageButton('Previous', pagination.current_page - 1, pagination.current_page === 1, '<i class="las la-angle-left"></i>'));

            // Page numbers logic
            const maxVisible = 5;
            let startPage = Math.max(1, pagination.current_page - 2);
            let endPage = Math.min(pagination.last_page, startPage + maxVisible - 1);

            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

            // First page
            if (startPage > 1) {
                paginationControls.appendChild(createPageButton(1, 1, false));
                if (startPage > 2) {
                    paginationControls.appendChild(createEllipsis());
                }
            }

            // Page numbers
            for (let i = startPage; i <= endPage; i++) {
                paginationControls.appendChild(createPageButton(i, i, false, null, i === pagination.current_page));
            }

            // Last page
            if (endPage < pagination.last_page) {
                if (endPage < pagination.last_page - 1) {
                    paginationControls.appendChild(createEllipsis());
                }
                paginationControls.appendChild(createPageButton(pagination.last_page, pagination.last_page, false));
            }

            // Next button
            paginationControls.appendChild(createPageButton('Next', pagination.current_page + 1, pagination.current_page === pagination.last_page, '<i class="las la-angle-right"></i>'));
        }

        function createPageButton(text, page, disabled = false, html = null, active = false) {
            const li = document.createElement('li');
            li.className = `page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}`;
            
            const link = document.createElement('a');
            link.className = 'page-link';
            
            if (html) {
                link.innerHTML = html;
            } else {
                link.textContent = text;
            }
            
            if (!disabled) {
                link.onclick = () => loadStocks(page);
            }
            
            li.appendChild(link);
            return li;
        }

        function createEllipsis() {
            const li = document.createElement('li');
            li.className = 'page-item disabled';
            const span = document.createElement('span');
            span.className = 'page-link';
            span.textContent = '...';
            li.appendChild(span);
            return li;
        }

        function jumpToPage() {
            const pageInput = document.getElementById('pageJump');
            const page = parseInt(pageInput.value);
            
            if (page && page >= 1 && page <= totalPages) {
                loadStocks(page);
                pageInput.value = '';
            }
        }

        // Allow Enter key in jump input
        document.getElementById('pageJump').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                jumpToPage();
            }
        });

        function showEmptyState() {
            const tableBody = document.getElementById('stockTableBody');
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-thumb text-center py-5">
                            <img src="{{ asset('assets/images/extra_images/empty.png') }}" width="90" alt="No Stock Found">
                            <p class="empty-sell mt-3">@lang('No stock data found')</p>
                        </div>
                    </td>
                </tr>
            `;
            document.getElementById('paginationControls').innerHTML = '';
            document.getElementById('paginationInfo').textContent = '';
        }

        function showErrorState() {
            const tableBody = document.getElementById('stockTableBody');
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="text-center py-5">
                            <p class="text-danger mb-3">@lang('Error loading stocks. Please try again.')</p>
                            <button class="btn btn-primary" onclick="loadStocks(currentPage)">
                                <i class="las la-redo-alt"></i> @lang('Retry')
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }

        function formatAmount(value, decimals = 2) {
            return parseFloat(value).toFixed(decimals);
        }
    </script>
@endpush