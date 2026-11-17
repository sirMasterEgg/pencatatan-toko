<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Omzet</div>
            <div class="fs-5 fw-semibold">{{ \App\Support\Format::rupiah($report['revenue']) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Transaksi</div>
            <div class="fs-5 fw-semibold">{{ $report['transaction_count'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Rata-rata nota</div>
            <div class="fs-5 fw-semibold">{{ \App\Support\Format::rupiah($report['average']) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Belum lunas</div>
            <div class="fs-5 fw-semibold">{{ \App\Support\Format::rupiah($report['unpaid_total']) }}</div>
            <div class="small text-muted">{{ $report['unpaid_count'] }} transaksi</div>
        </div></div>
    </div>
</div>
@if ($report['cancelled_count'] > 0)
    <p class="small text-muted">{{ $report['cancelled_count'] }} transaksi dibatalkan</p>
@endif
