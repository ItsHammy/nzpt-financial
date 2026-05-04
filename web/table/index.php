<?php
function fetchDonationData(): array {
    $db_path = __DIR__ . '/../financial.sqlite3';

    if (!file_exists($db_path)) {
        return ["error" => "Error 2 Occurred with the code (MISSINGDB). Please email cj@cjs.nz to report this error."]; //Database not found. Has the scraper run yet?
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

function getPartyColor(string $party, array $colors): string {
    foreach ($colors as $name => $color) {
        if (stripos($party, $name) !== false || stripos($name, $party) !== false) {
            return $color;
        }
    }
    $fallbacks = ['#E07B39','#3BBFBF','#9B59B6','#E74C3C','#1ABC9C'];
    return $fallbacks[abs(crc32($party)) % count($fallbacks)];
}

function normaliseDonor(string $name, array $aliases): string {
    return $aliases[$name] ?? $name;
}

$result       = fetchDonationData();
$donations    = $result["donations"] ?? [];
$last_updated = $result["last_updated"] ?? null;

$party_colors = [
    'ACT New Zealand'                         => '#FFD700',
    'The New Zealand National Party'          => '#00529F',
    'New Zealand Labour Party'                => '#CC0000',
    'The Green Party of Aotearoa New Zealand' => '#098137',
    'New Zealand First Party'                 => '#929090',
    'Te Pāti Māori'                           => '#B22222',
    'Opportunity Party'                       => '#6A0DAD',
    'DemocracyNZ'                             => '#888888',
];

$donor_aliases = [
    'Nicholas Mowbray' => 'Nicholas Mowbray (Zuru)',
    'Phillip Mills' => 'Phillip Mills (Les Mills)',
    "GMP Environmental Limited" => 'GMP Environmental Limited (Greymouth Petroleum)',
];

// --- Aggregate stats ---
$party_totals = [];
$donor_totals = [];
$total_all    = 0;
$top_donation = null;

foreach ($donations as $row) {
    $amt   = parseDollar($row["donation_amount"]);
    $party = trim($row["party"]);
    $donor = normaliseDonor(trim($row["donor_name"]), $donor_aliases);

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
$top_party  = array_key_first($party_totals);
$num_donors = count(array_unique(array_column($donations, 'donor_name')));

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
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script defer src="https://cloud.umami.is/script.js" data-website-id="1492dd3b-f626-44b3-a8d5-b074177af097"></script>
</head>
<body>

<header>
    <div class="header-inner">
        <div class="logo">
            <h1>Party <span>Financial</span> Dashboard</h1>
            <small>New Zealand Politics Toolbox (NZPT) &mdash; Tracking Party Finances</small>
        </div>
        <nav>
            <a href="../">Financial Dashboard</a>
            <a href="#" class="active">Full Table</a>
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
        <h2>All NZPol Party Donations <em>Exceeding $20,000</em></h2>
        <div class="meta-row">
            <?php if ($last_updated): ?>
            <span class="badge">Last updated: <span><?= htmlspecialchars($last_updated) ?></span></span>
            <?php endif; ?>
            <span class="badge">Source: <a href="https://elections.nz" target="_blank"><span>Electoral Commission</span></a></span>
            <span class="badge">Voting Year: <span>2026</span></span>
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
                        $bg    = $color . '22';
                    ?>
                    <tr>
                        <td>
                            <span class="party-pill" style="background:<?= $bg ?>;color:<?= $color ?>;border:1px solid <?= $color ?>44">
                                <?= htmlspecialchars($row["party"]) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row["donor_name"]) ?></td>
                        <td style="color:var(--muted);font-size:0.8rem"><?= htmlspecialchars($row["donor_address"]) ?></td>
                        <td class="amount"><?= htmlspecialchars($row["donation_amount"]) ?></td>
                        <td class="date"><?= htmlspecialchars($row["donation_date"]) ?></td>
                        <td class="date"><?= htmlspecialchars($row["filing_date"]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pass data to JS -->
    <script>
    window.NZPT_DATA = {
        labels:      <?= $chart_labels ?>,
        values:      <?= $chart_values ?>,
        colors:      <?= $chart_colors ?>,
        donorLabels: <?= $donor_labels ?>,
        donorValues: <?= $donor_values ?>,
    };
    </script>

    <?php endif; ?>
</main>

<script src="assets/script.js"></script>
</body>
</html>