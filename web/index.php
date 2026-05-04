<?php
function fetchDonationData(): array {
    $sourceurl = "https://elections.nz/democracy-in-nz/political-parties-in-new-zealand/donations-exceeding-20000";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $sourceurl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ["Connection: close"],
        CURLOPT_USERAGENT      => "Mozilla/5.0 (compatible; NZPT-Bot/1.0)",
    ]);

    $html = curl_exec($curl);

    if ($html === false) {
        die("Curl error: " . curl_error($curl));
    }

    file_put_contents('/tmp/elections_debug.html', $html);

    if ($html === false) {
        return ["error" => curl_error($curl)];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Find the h2 with our target heading, then get the next table sibling
    $heading = $xpath->query("//h2[contains(text(), 'Party donations exceeding')]");
    if ($heading->length === 0) {
        return ["error" => "Could not find the donations heading on the page."];
    }

    // Walk siblings until we hit a table
    $table = null;
    $sibling = $heading->item(0)->nextSibling;
    while ($sibling !== null) {
        if ($sibling->nodeName === "table") {
            $table = $sibling;
            break;
        }
        $sibling = $sibling->nextSibling;
    }

    if ($table === null) {
        return ["error" => "Could not find the donations table after the heading."];
    }

    $rows = $xpath->query(".//tbody/tr", $table);
    $donations = [];

    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName("td");
        if ($cells->length < 3) continue;

        // --- Column 1: Party and filing date, separated by <br> ---
        $col1Html = innerHtml($cells->item(0));
        $col1Parts = array_map('trim', explode('<br>', preg_replace('/<br\s*\/?>/i', '<br>', $col1Html)));
        $party      = strip_tags($col1Parts[0] ?? '');
        $filingDate = strip_tags($col1Parts[1] ?? '');

        // --- Column 2: Name and address, separated by <br> ---
        $col2Html   = innerHtml($cells->item(1));
        $col2Parts  = array_map('trim', explode('<br>', preg_replace('/<br\s*\/?>/i', '<br>', $col2Html)));
        $col2Parts  = array_filter(array_map(fn($p) => trim(strip_tags($p)), $col2Parts));
        $col2Parts  = array_values($col2Parts);
        $donorName  = $col2Parts[0] ?? '';
        $donorAddress = implode(', ', array_slice($col2Parts, 1)); // Remaining parts = address

        // --- Column 3: Amount and date, inside an <a> tag, separated by comma ---
        $anchor = $cells->item(2)->getElementsByTagName("a")->item(0);
        $anchorText = $anchor ? trim($anchor->textContent) : '';
        // Format: "$50,000, 29 April 2026" — split on last comma before a date
        // Use regex to split amount and date reliably
        if (preg_match('/^(\$[\d,]+),\s*(.+)$/', $anchorText, $matches)) {
            $donationAmount = trim($matches[1]);
            $donationDate   = trim($matches[2]);
        } else {
            $donationAmount = $anchorText;
            $donationDate   = '';
        }

        $donations[] = [
            "party"           => $party,
            "filing_date"     => $filingDate,
            "donor_name"      => $donorName,
            "donor_address"   => $donorAddress,
            "donation_amount" => $donationAmount,
            "donation_date"   => $donationDate,
        ];
    }

    return $donations;
}

// Helper: get inner HTML of a DOMNode as a string
function innerHtml(DOMNode $node): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return $html;
}

$donations = fetchDonationData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard - NZPT</title>
    <meta name="description" content="View the donations and loans for each NZ Political Party. Data sourced from Electoral Commission of New Zealand and powered by the NZPolToolbox.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/modern-normalize/1.0.0/modern-normalize.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="assets/script.js"></script>
    <script defer src="https://cloud.umami.is/script.js" data-website-id="1492dd3b-f626-44b3-a8d5-b074177af097"></script>
</head>
<body>
    <header>
        <h1>NZPT | Financial Dashboard</h1>
        <h5>New Zealand Politics Toolbox -> Tracking Party Finances</h5>
        <nav>
            <a href="#" class="active">Financial Dashboard</a>
            <a href="all-stats/">Full Breakdown</a>
            <a href="https://nzpt.cjs.nz/urgency">Urgency Viewer</a>
            <a href="https://cjs.nz/socials" target="_blank">Contact</a>
        </nav>
    </header>
    <main>
        <div id="dashboard">
            <h2>Party Donations Exceeding $20,000</h2>

            <?php if (isset($donations["error"])): ?>
                <p class="error">Error: <?= htmlspecialchars($donations["error"]) ?></p>
            <?php else: ?>
                <table id="donation-table">
                    <thead>
                        <tr>
                            <th>Party</th>
                            <th>Filing Date</th>
                            <th>Donor Name</th>
                            <th>Donor Address</th>
                            <th>Donation Amount</th>
                            <th>Donation Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row["party"]) ?></td>
                                <td><?= htmlspecialchars($row["filing_date"]) ?></td>
                                <td><?= htmlspecialchars($row["donor_name"]) ?></td>
                                <td><?= htmlspecialchars($row["donor_address"]) ?></td>
                                <td><?= htmlspecialchars($row["donation_amount"]) ?></td>
                                <td><?= htmlspecialchars($row["donation_date"]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>