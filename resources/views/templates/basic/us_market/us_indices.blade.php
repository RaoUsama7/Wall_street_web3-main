@extends('layouts.frontend')

@section('content')
<section class="us-indices-section">
    <div class="container">
        <div class="index-card">
            <h2 class="us-indices-title">US Indices Market</h2>
            <p class="mb-3">Track and trade major US indices like NASDAQ, S&P 500, and Dow Jones in real-time.</p>
        </div>

        <div class="table-responsive">
            <table class="us-indices-table">
                <thead>
                    <tr>
                        <th>Pair</th>
                        <th>Price</th>
                        <th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pairs as $pair)
                        <tr>
                            <td>{{ $pair->symbol }}</td>
                            <td>${{ number_format($pair->price, 2) }}</td>
                            <td class="price-up">+0.00%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">No index data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection


@push('style')
<style>
    /* ========== US Indices Page Styles ========== */
    body {
        background-color: #0f172a;
        color: #e2e8f0;
        font-family: 'Poppins', sans-serif;
    }

    .us-indices-section {
        padding: 80px 0;
        min-height: 80vh;
    }

    .us-indices-title {
        font-size: 2rem;
        font-weight: 700;
        text-align: center;
        color: #60a5fa;
        margin-bottom: 40px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .us-indices-table {
        width: 100%;
        border-collapse: collapse;
        background-color: #1e293b;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 0 20px rgba(0,0,0,0.3);
    }

    .us-indices-table thead {
        background-color: #1d4ed8;
        color: #fff;
    }

    .us-indices-table th,
    .us-indices-table td {
        padding: 15px 20px;
        text-align: center;
    }

    .us-indices-table tbody tr {
        border-bottom: 1px solid #334155;
        transition: background 0.3s ease;
    }

    .us-indices-table tbody tr:hover {
        background-color: #1e40af;
        color: #fff;
    }

    .price-up {
        color: #22c55e;
        font-weight: 600;
    }

    .price-down {
        color: #ef4444;
        font-weight: 600;
    }

    .index-card {
        background: linear-gradient(135deg, #002b5b, #1e90ff);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 40px;
        text-align: center;
        color: #fff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        transition: transform 0.2s ease;
    }

    .index-card:hover {
        transform: scale(1.03);
    }

    @media (max-width: 768px) {
        .us-indices-table th,
        .us-indices-table td {
            padding: 10px;
            font-size: 14px;
        }
    }
</style>
@endpush
