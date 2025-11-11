let currentPage = 1;
let totalPages = 1;
let searchQuery = '';
let searchTimeout;

document.addEventListener("DOMContentLoaded", function() {
    // Initial load
    loadStocks(1);

    // Search functionality with debounce
    const searchInput = document.getElementById('stockSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchQuery = this.value.trim();
            
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadStocks(1);
            }, 500);
        });
    }
});

function loadStocks(page) {
    const loadingIndicator = document.getElementById('loadingIndicator');
    const tableBody = document.getElementById('stockTableBody');
    
    // Show loading
    if (loadingIndicator) loadingIndicator.style.display = 'flex';
    if (tableBody) tableBody.innerHTML = '';

    // AJAX request
    fetch(`${US_MARKETS_DATA_ROUTE}?page=${page}&search=${encodeURIComponent(searchQuery)}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (loadingIndicator) loadingIndicator.style.display = 'none';
        
        if (data.stocks && data.stocks.length > 0) {
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
        if (loadingIndicator) loadingIndicator.style.display = 'none';
        showErrorState();
    });
}

function renderStocks(stocks) {
    const tableBody = document.getElementById('stockTableBody');
    if (!tableBody) return;
    tableBody.innerHTML = '';

    stocks.forEach(stock => {
        const row = document.createElement('tr');

        // Dynamic trade URL (replace with your actual route pattern)
        const tradeUrl = `${US_MARKETS_TRADE_PREFIX}${encodeURIComponent(stock.symbol)}`;

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
                        <span>${stock.symbol}/USD</span>
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
                <a href="${tradeUrl}" class="btn  btn-sm trade-btn">
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

    if (!paginationInfo || !paginationControls || !pageJump) return;

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
    if (!pageInput) return;
    const page = parseInt(pageInput.value);
    
    if (page && page >= 1 && page <= totalPages) {
        loadStocks(page);
        pageInput.value = '';
    }
}

// Allow Enter key in jump input
document.addEventListener('DOMContentLoaded', function() {
    const pageJumpEl = document.getElementById('pageJump');
    if (pageJumpEl) {
        pageJumpEl.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                jumpToPage();
            }
        });
    }
});

function showEmptyState() {
    const tableBody = document.getElementById('stockTableBody');
    if (!tableBody) return;
    tableBody.innerHTML = `
        <tr>
            <td colspan="7">
                <div class="empty-thumb text-center py-5">
                    <img src="${ASSET_EMPTY_IMAGE}" width="90" alt="No Stock Found">
                    <p class="empty-sell mt-3">${NO_STOCK_TEXT}</p>
                </div>
            </td>
        </tr>
    `;
    const paginationControls = document.getElementById('paginationControls');
    const paginationInfo = document.getElementById('paginationInfo');
    if (paginationControls) paginationControls.innerHTML = '';
    if (paginationInfo) paginationInfo.textContent = '';
}

function showErrorState() {
    const tableBody = document.getElementById('stockTableBody');
    if (!tableBody) return;
    tableBody.innerHTML = `
        <tr>
            <td colspan="7">
                <div class="text-center py-5">
                    <p class="text-danger mb-3">${ERROR_LOADING_TEXT}</p>
                    <button class="btn btn-primary" onclick="loadStocks(currentPage)">
                        <i class="las la-redo-alt"></i> ${RETRY_TEXT}
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function formatAmount(value, decimals = 2) {
    return parseFloat(value).toFixed(decimals);
}


