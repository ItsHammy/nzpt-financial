<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard - NZPT</title>
    <meta name="description" content="View the donations and loans for each NZ Political Party. Data sourced from Electoral Commission of New Zealand and powered by the NZPolToolbox.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/modern-normalize/1.0.0/modern-normalize.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="assets/script.js"></script>
    <!-- Privacy Analytics -->
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
            <h2>Interactive Donation Viewer</h2>
            <table id="donation-table"> <!-- This will be made interactive with chart js, leaderboard etc. Proof of concept for now -->
                <thead>
                    <tr>
                        <th>Party</th>
                        <th>Donation Amount</th>
                        <th>Donation Date</th>
                        <th>Donor Name and Address</th>
                    </tr>
                </thead>
                <?php 
                // Pull data from https://elections.nz/democracy-in-nz/political-parties-in-new-zealand/donations-exceeding-20000 2026 table 
                $sourceurl = "https://elections.nz/democracy-in-nz/political-parties-in-new-zealand/donations-exceeding-20000";
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $sourceurl); // Specify the URL
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Return response as a string
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
                $htmlContent = curl_exec($curl); // Execute the request
                

                ?>
            </table>
        </div>
    </main>
</body>
</html>