<?php
function fetchDonationData(): array {
    $db_path = __DIR__ . '/financial.sqlite3';

    if (!file_exists($db_path)) {
        return ["error" => "Database not found. Has the scraper run yet?"];
    }

    try {
        $db = new PDO("sqlite:$db_path");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $donations = $db->query("
            SELECT party, filing_date, donor_name, donor_address, donation_amount, donation_date
            FROM donations
            ORDER BY donation_date DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $meta = $db->query("SELECT value FROM meta WHERE key = 'last_updated'")->fetchColumn();

        return ["donations" => $donations, "last_updated" => $meta];

    } catch (PDOException $e) {
        return ["error" => "Database error: " . $e->getMessage()];
    }
}

function parseDollar(string $amount): float {
    return (float) preg_replace('/[^0-9.]/', '', $amount);
}

$result = fetchDonationData();
$donations = $result["donations"] ?? [];
$last_updated = $result["last_updated"] ?? null;

// --- Aggregate stats ---
$party_totals = [];
$donor_totals = [];
$total_all = 0;
$top_donation = null;

foreach ($donations as $row) {
    $amt = parseDollar($row["donation_amount"]);
    $party = trim($row["party"]);
    $donor = trim($row["donor_name"]);

    $party_totals[$party] = ($party_totals[$party] ?? 0) + $amt;
    $donor_totals[$donor] = ($donor_totals[$donor] ?? 0) + $amt;
    $total_all += $amt;

    if ($top_donation === null || $amt > parseDollar($top_donation["donation_amount"])) {
        $top_donation = $row;
    }
}

arsort($party_totals);
arsort($donor_totals);

$top_donors = array_slice($donor_totals, 0, 5, true);
$top_party = array_key_first($party_totals);
$num_donors = count(array_unique(array_column($donations, 'donor_name')));

// Party colours
$party_colors = [
    'ACT New Zealand'                          => '#FFD700',
    'The New Zealand National Party'           => '#00529F',
    'New Zealand Labour Party'                 => '#CC0000',
    'The Green Party of Aotearoa New Zealand'  => '#098137',
    'New Zealand First Party'                  => '#000000',
    'Te Pāti Māori'                            => '#B22222',
    'The Opportunities Party'                  => '#6A0DAD',
    'Opportunity Party'                        => '#6A0DAD',
    'DemocracyNZ'                              => '#888888',
];

function getPartyColor(string $party, array $colors): string {
    foreach ($colors as $name => $color) {
        if (stripos($party, $name) !== false || stripos($name, $party) !== false) {
            return $color;
        }
    }
    // fallback palette
    $fallbacks = ['#E07B39','#3BBFBF','#9B59B6','#E74C3C','#1ABC9C'];
    return $fallbacks[crc32($party) % count($fallbacks)];
}

$chart_labels = json_encode(array_keys($party_totals));
$chart_values = json_encode(array_values($party_totals));
$chart_colors = json_encode(array_map(fn($p) => getPartyColor($p, $party_colors), array_keys($party_totals)));

$donor_labels = json_encode(array_keys($top_donors));
$donor_values = json_encode(array_values($top_donors));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard — NZPT</title>
    <meta name="description" content="View the donations and loans for each NZ Political Party. Data sourced from Electoral Commission of New Zealand.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script defer src="https://cloud.umami.is/script.js" data-website-id="1492dd3b-f626-44b3-a8d5-b074177af097"></script>
    <style>
        :root {
            --bg:        #0b0c0f;
            --surface:   #13151a;
            --surface2:  #1c1f27;
            --border:    #2a2d38;
            --text:      #e8eaf0;
            --muted:     #6b7080;
            --accent:    #c9f542;
            --accent2:   #42b4f5;
            --danger:    #f54260;
            --radius:    12px;
            --font-display: 'DM Serif Display', Georgia, serif;
            --font-body:    'DM Sans', sans-serif;
            --font-mono:    'DM Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body);
            font-size: 15px;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Subtle grid texture */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 40px 40px;
            opacity: 0.2;
            pointer-events: none;
            z-index: 0;
        }

        header {
            position: relative;
            z-index: 1;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            padding: 0 2rem;
        }

        .header-inner {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 0;
            gap: 2rem;
        }

        .logo {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .logo h1 {
            font-family: var(--font-display);
            font-size: 1.4rem;
            letter-spacing: -0.02em;
            color: var(--text);
        }

        .logo h1 span { color: var(--accent); }

        .logo small {
            font-size: 0.72rem;
            color: var(--muted);
            font-family: var(--font-mono);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        nav {
            display: flex;
            gap: 0.25rem;
        }

        nav a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.45rem 0.9rem;
            border-radius: 6px;
            transition: all 0.15s;
        }

        nav a:hover { color: var(--text); background: var(--surface2); }
        nav a.active { color: var(--accent); background: rgba(201,245,66,0.08); }

        main {
            position: relative;
            z-index: 1;
            max-width: 1300px;
            margin: 0 auto;
            padding: 2.5rem 2rem 4rem;
        }

        .page-header {
            margin-bottom: 2.5rem;
        }

        .page-header h2 {
            font-family: var(--font-display);
            font-size: 2.4rem;
            letter-spacing: -0.03em;
            line-height: 1.1;
            color: var(--text);
        }

        .page-header h2 em {
            font-style: italic;
            color: var(--accent);
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-top: 0.6rem;
        }

        .meta-row .badge {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: var(--muted);
            background: var(--surface2);
            border: 1px solid var(--border);
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            letter-spacing: 0.04em;
        }

        .meta-row .badge span { color: var(--accent2); }

        /* ── Stat cards ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.4rem 1.6rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            transition: border-color 0.2s;
        }

        .stat-card:hover { border-color: var(--accent); }

        .stat-card .label {
            font-size: 0.72rem;
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .stat-card .value {
            font-family: var(--font-display);
            font-size: 2rem;
            line-height: 1;
            color: var(--text);
        }

        .stat-card .sub {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 0.2rem;
        }

        .stat-card.accent-card { border-color: rgba(201,245,66,0.3); }
        .stat-card.accent-card .value { color: var(--accent); }

        /* ── Charts row ── */
        .charts-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.6rem;
        }

        .chart-card h3 {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
            margin-bottom: 1.4rem;
        }

        .chart-wrap {
            position: relative;
        }

        /* ── Bottom grid ── */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        /* ── Table ── */
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            margin-top: 1rem;
        }

        .table-card-header {
            padding: 1.2rem 1.6rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-card-header h3 {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
        }

        .table-card-header .count {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: var(--accent);
            background: rgba(201,245,66,0.08);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
        }

        .donations-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .donations-table thead th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-family: var(--font-mono);
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .donations-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.1s;
        }

        .donations-table tbody tr:last-child { border-bottom: none; }
        .donations-table tbody tr:hover { background: var(--surface2); }

        .donations-table td {
            padding: 0.8rem 1rem;
            vertical-align: middle;
        }

        .donations-table td.amount {
            font-family: var(--font-mono);
            font-size: 0.9rem;
            color: var(--accent);
            font-weight: 500;
            white-space: nowrap;
        }

        .donations-table td.date {
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: var(--muted);
            white-space: nowrap;
        }

        .party-pill {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ── Top donors list ── */
        .donor-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .donor-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .donor-rank {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--muted);
            width: 1.5rem;
            flex-shrink: 0;
        }

        .donor-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .donor-name-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .donor-name {
            font-size: 0.85rem;
            color: var(--text);
            font-weight: 500;
        }

        .donor-amount {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--accent);
        }

        .donor-bar-bg {
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }

        .donor-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            transition: width 1s cubic-bezier(0.4,0,0.2,1);
        }

        /* ── Party breakdown list ── */
        .party-breakdown {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }

        .party-row {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .party-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .party-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .party-name-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .party-name-text {
            font-size: 0.82rem;
            color: var(--text);
        }

        .party-total-text {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--muted);
        }

        .party-bar-bg {
            height: 3px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }

        .party-bar-fill {
            height: 100%;
            border-radius: 2px;
        }

        /* ── Error ── */
        .error-card {
            background: rgba(245,66,96,0.1);
            border: 1px solid var(--danger);
            border-radius: var(--radius);
            padding: 1.5rem;
            color: var(--danger);
            font-family: var(--font-mono);
            font-size: 0.85rem;
        }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .bottom-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
            .header-inner { flex-direction: column; align-items: flex-start; }
            nav { flex-wrap: wrap; }
            main { padding: 1.5rem 1rem 3rem; }
        }

        /* Fade-in animation */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .stat-card, .chart-card, .table-card {
            animation: fadeUp 0.4s ease both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.20s; }
    </style>
</head>
<body>
<header>
    <div class="header-inner">
        <div class="logo">
            <h1>NZPT | <span>Financial</span> Dashboard</h1>
            <small>New Zealand Politics Toolbox &mdash; Tracking Party Finances</small>
        </div>
        <nav>
            <a href="#" class="active">Financial Dashboard</a>
            <a href="all-stats/">Full Breakdown</a>
            <a href="https://nzpt.cjs.nz/urgency">Urgency Viewer</a>
            <a href="https://cjs.nz/socials" target="_blank">Contact</a>
        </nav>
    </div>
</header>

<main>
    <?php if (isset($result["error"])): ?>
        <div class="error-card">Error: <?= htmlspecialchars($result["error"]) ?></div>
    <?php else: ?>

    <div class="page-header">
        <h2>Party Donations <em>Exceeding $20,000</em></h2>
        <div class="meta-row">
            <?php if ($last_updated): ?>
            <span class="badge">Last updated: <span><?= htmlspecialchars($last_updated) ?></span></span>
            <?php endif; ?>
            <span class="badge">Source: <span>Electoral Commission NZ</span></span>
            <span class="badge">Year: <span>2026</span></span>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card accent-card">
            <div class="label">Total Donated</div>
            <div class="value">$<?= number_format($total_all / 1000000, 2) ?>M</div>
            <div class="sub"><?= count($donations) ?> donations recorded</div>
        </div>
        <div class="stat-card">
            <div class="label">Leading Party</div>
            <div class="value" style="font-size:1.2rem; line-height:1.3"><?= htmlspecialchars($top_party ?? '—') ?></div>
            <div class="sub">$<?= number_format($party_totals[$top_party] ?? 0) ?> total</div>
        </div>
        <div class="stat-card">
            <div class="label">Unique Donors</div>
            <div class="value"><?= $num_donors ?></div>
            <div class="sub">across <?= count($party_totals) ?> parties</div>
        </div>
        <div class="stat-card">
            <div class="label">Largest Single Donation</div>
            <div class="value" style="font-size:1.3rem"><?= htmlspecialchars($top_donation["donation_amount"] ?? '—') ?></div>
            <div class="sub"><?= htmlspecialchars($top_donation["donor_name"] ?? '') ?></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3>Total donations by party</h3>
            <div class="chart-wrap" style="height:280px">
                <canvas id="partyBarChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>Share of total donations</h3>
            <div class="chart-wrap" style="height:280px">
                <canvas id="partyDoughnut"></canvas>
            </div>
        </div>
    </div>

    <!-- Bottom grid -->
    <div class="bottom-grid">
        <!-- Top donors -->
        <div class="chart-card">
            <h3>Top donors by total given</h3>
            <?php $max_donor = max($top_donors ?: [1]); ?>
            <ul class="donor-list">
                <?php $rank = 1; foreach ($top_donors as $name => $amt): ?>
                <li class="donor-item">
                    <span class="donor-rank">#<?= $rank++ ?></span>
                    <div class="donor-bar-wrap">
                        <div class="donor-name-row">
                            <span class="donor-name"><?= htmlspecialchars($name) ?></span>
                            <span class="donor-amount">$<?= number_format($amt) ?></span>
                        </div>
                        <div class="donor-bar-bg">
                            <div class="donor-bar-fill" style="width:<?= round($amt / $max_donor * 100) ?>%"></div>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Party breakdown -->
        <div class="chart-card">
            <h3>Party breakdown</h3>
            <?php $max_party = max($party_totals ?: [1]); ?>
            <ul class="party-breakdown">
                <?php foreach ($party_totals as $party => $amt):
                    $color = getPartyColor($party, $party_colors);
                    $pct   = round($amt / $total_all * 100, 1);
                ?>
                <li class="party-row">
                    <div class="party-dot" style="background:<?= $color ?>"></div>
                    <div class="party-info">
                        <div class="party-name-row">
                            <span class="party-name-text"><?= htmlspecialchars($party) ?></span>
                            <span class="party-total-text">$<?= number_format($amt) ?> &middot; <?= $pct ?>%</span>
                        </div>
                        <div class="party-bar-bg">
                            <div class="party-bar-fill" style="width:<?= round($amt / $max_party * 100) ?>%; background:<?= $color ?>"></div>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Full donations table -->
    <div class="table-card">
        <div class="table-card-header">
            <h3>All donations</h3>
            <span class="count"><?= count($donations) ?> records</span>
        </div>
        <div style="overflow-x:auto">
            <table class="donations-table">
                <thead>
                    <tr>
                        <th>Party</th>
                        <th>Donor</th>
                        <th>Address</th>
                        <th>Amount</th>
                        <th>Donation Date</th>
                        <th>Filing Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $row):
                        $color = getPartyColor($row["party"], $party_colors);
                        $bg    = $color . '22'; // ~14% opacity hex
                    ?>
                    <tr>
                        <td>
                            <span class="party-pill" style="background:<?= $bg ?>; color:<?= $color ?>; border: 1px solid <?= $color ?>44">
                                <?= htmlspecialchars($row["party"]) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row["donor_name"]) ?></td>
                        <td style="color:var(--muted); font-size:0.8rem"><?= htmlspecialchars($row["donor_address"]) ?></td>
                        <td class="amount"><?= htmlspecialchars($row["donation_amount"]) ?></td>
                        <td class="date"><?= htmlspecialchars($row["donation_date"]) ?></td>
                        <td class="date"><?= htmlspecialchars($row["filing_date"]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</main>

<script>
const labels  = <?= $chart_labels ?>;
const values  = <?= $chart_values ?>;
const colors  = <?= $chart_colors ?>;

const gridColor  = 'rgba(255,255,255,0.05)';
const tickColor  = '#6b7080';
const font       = { family: "'DM Mono', monospace", size: 11 };

// Bar chart
new Chart(document.getElementById('partyBarChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            data: values,
            backgroundColor: colors.map(c => c + '99'),
            borderColor: colors,
            borderWidth: 1.5,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1c1f27',
                borderColor: '#2a2d38',
                borderWidth: 1,
                titleColor: '#e8eaf0',
                bodyColor: '#c9f542',
                titleFont: font,
                bodyFont: { ...font, size: 13 },
                callbacks: {
                    label: ctx => ' $' + ctx.parsed.y.toLocaleString()
                }
            }
        },
        scales: {
            x: {
                ticks: { color: tickColor, font, maxRotation: 30 },
                grid: { color: gridColor },
                border: { color: gridColor }
            },
            y: {
                ticks: {
                    color: tickColor,
                    font,
                    callback: v => '$' + (v >= 1000000 ? (v/1000000).toFixed(1)+'M' : (v/1000).toFixed(0)+'K')
                },
                grid: { color: gridColor },
                border: { color: gridColor }
            }
        }
    }
});

// Doughnut chart
new Chart(document.getElementById('partyDoughnut'), {
    type: 'doughnut',
    data: {
        labels,
        datasets: [{
            data: values,
            backgroundColor: colors.map(c => c + 'cc'),
            borderColor: '#0b0c0f',
            borderWidth: 2,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: tickColor,
                    font,
                    padding: 12,
                    boxWidth: 10,
                    boxHeight: 10,
                    usePointStyle: true,
                }
            },
            tooltip: {
                backgroundColor: '#1c1f27',
                borderColor: '#2a2d38',
                borderWidth: 1,
                titleColor: '#e8eaf0',
                bodyColor: '#c9f542',
                titleFont: font,
                bodyFont: { ...font, size: 13 },
                callbacks: {
                    label: ctx => ' $' + ctx.parsed.toLocaleString()
                }
            }
        }
    }
});
</script>
</body>
</html>