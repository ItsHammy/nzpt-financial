"""
    Scrapes party donation data from elections.nz and stores it in financial.sqlite3.
    Uses Playwright headless browser with stealth to bypass Incapsula WAF.

    Schema:
    - donations: id, party, filing_date, donor_name, donor_address, donation_amount, donation_date
    - meta: key, value (stores last_updated timestamp)
"""

import asyncio
import html
import re
import sqlite3
from datetime import datetime

from playwright.async_api import async_playwright
from playwright_stealth import stealth

DB_PATH = "/var/www/nzpt/finances/financial.sqlite3"
URL = "https://elections.nz/democracy-in-nz/political-parties-in-new-zealand/donations-exceeding-20000"
HEADING_TEXT = "Party donations exceeding $20,000 since 1 January 2026"
PARTY_ALIASES = {
    "The Opportunities Party": "Opportunity Party",
    "Opportunity Party":       "Opportunity Party",
}


def init_db():
    conn = sqlite3.connect(DB_PATH)
    conn.execute("""
        CREATE TABLE IF NOT EXISTS donations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            party TEXT,
            filing_date TEXT,
            donor_name TEXT,
            donor_address TEXT,
            donation_amount TEXT,
            donation_date TEXT,
            UNIQUE(party, donor_name, donation_amount, donation_date)
        )
    """)
    conn.execute("""
        CREATE TABLE IF NOT EXISTS meta (
            key TEXT PRIMARY KEY,
            value TEXT
        )
    """)
    conn.commit()
    conn.close()


def clean(text):
    """Replace non-breaking spaces with regular spaces and unescape HTML entities."""
    return html.unescape(text.replace('\xa0', ' ').replace('&amp;nbsp;', ' ').replace('&nbsp;', ' ')).strip()


async def scrape():
    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=[
                "--disable-blink-features=AutomationControlled",
                "--no-sandbox",
                "--disable-dev-shm-usage",
            ]
        )

        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
            viewport={"width": 1920, "height": 1080},
            locale="en-NZ",
            timezone_id="Pacific/Auckland",
            extra_http_headers={
                "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
                "Accept-Language": "en-NZ,en;q=0.9",
                "Accept-Encoding": "gzip, deflate, br",
                "DNT": "1",
                "Upgrade-Insecure-Requests": "1",
            }
        )

        await context.add_init_script("""
            Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
            Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3] });
            Object.defineProperty(navigator, 'languages', { get: () => ['en-NZ', 'en'] });
            window.chrome = { runtime: {} };
        """)

        page = await context.new_page()

        # Apply stealth if available
        try:
            await stealth(page)
        except Exception as e:
            print(f"Stealth not applied: {e}")

        print(f"Loading {URL}...")
        await page.goto(URL, wait_until="networkidle")

        title = await page.title()
        print(f"Page title: {title}")

        content = await page.content()
        if '<iframe id="main-iframe"' in content and 'page__content' not in content:
            print("ERROR: Blocked by Incapsula WAF")
            await browser.close()
            return []

        table_html = await page.evaluate(f"""() => {{
            const divs = document.querySelectorAll('div.page__content');
            for (const div of divs) {{
                const h2 = div.querySelector('h2');
                if (h2 && h2.textContent.includes('Party donations exceeding $20,000 since 1 January 2026')) {{
                    const table = div.querySelector('table');
                    return table ? table.outerHTML : null;
                }}
            }}
            return null;
        }}""")

        await browser.close()

        if not table_html:
            print("ERROR: Could not find the 2026 donations table")
            return []

        rows = re.findall(r'<tr[^>]*>(.*?)</tr>', table_html, re.DOTALL | re.IGNORECASE)
        donations = []

        for row in rows:
            cells = re.findall(r'<td[^>]*>(.*?)</td>', row, re.DOTALL | re.IGNORECASE)
            if len(cells) < 3:
                continue

            # Column 1: Party + filing date split by <br>
            col1_parts = [clean(re.sub(r'<[^>]+>', '', part))
              for part in re.split(r'<br\s*/?>', cells[0], flags=re.IGNORECASE)]
            col1_parts  = [p for p in col1_parts if p]
            party       = col1_parts[0] if len(col1_parts) > 0 else ''
            filing_date = col1_parts[1] if len(col1_parts) > 1 else ''

            # Normalise party name aliases
            party = PARTY_ALIASES.get(party, party)

            # Skip header rows
            if not party or party.lower().startswith('party'):
                continue

            # Column 2: Donor name + address split by <br>
            col2_parts = [clean(re.sub(r'<[^>]+>', '', part))
                          for part in re.split(r'<br\s*/?>', cells[1], flags=re.IGNORECASE)]
            col2_parts = [p for p in col2_parts if p]
            donor_name    = col2_parts[0] if len(col2_parts) > 0 else ''
            donor_address = ', '.join(col2_parts[1:]) if len(col2_parts) > 1 else ''

            # Column 3: Amount + date from anchor text e.g. "$50,000, 29 April 2026"
            anchor_match = re.search(r'<a[^>]*>([^<]+)</a>', cells[2], re.IGNORECASE)
            anchor_text  = clean(anchor_match.group(1)) if anchor_match else ''
            amount_match = re.match(r'^(\$[\d,]+(?:\.\d+)?),\s*(.+)$', anchor_text)
            if amount_match:
                donation_amount = amount_match.group(1)
                donation_date   = amount_match.group(2).strip()
            else:
                donation_amount = anchor_text
                donation_date   = ''

            donations.append({
                "party":           clean(party),
                "filing_date":     filing_date,
                "donor_name":      clean(donor_name),
                "donor_address":   clean(donor_address),
                "donation_amount": donation_amount,
                "donation_date":   donation_date,
            })
            
            # fix dates in the format "29 April 2026" to "2026-04-29"
            try:
                parsed_date = datetime.strptime(donation_date, "%d %B %Y")
                donations[-1]["donation_date"] = parsed_date.strftime("%Y-%m-%d")

            except ValueError:
                print(f"ERROR: Unable to parse date: {donation_date}")
            
            try:
                parsed_date = datetime.strptime(filing_date, "%d %B %Y")
                donations[-1]["filing_date"] = parsed_date.strftime("%Y-%m-%d")
            except ValueError:
                print(f"ERROR: Unable to parse date: {filing_date}")

        return donations


def save(donations):
    conn = sqlite3.connect(DB_PATH)
    inserted = 0
    for d in donations:
        try:
            cursor = conn.execute("""
                INSERT OR IGNORE INTO donations
                    (party, filing_date, donor_name, donor_address, donation_amount, donation_date)
                VALUES (?, ?, ?, ?, ?, ?)
            """, (d["party"], d["filing_date"], d["donor_name"], d["donor_address"],
                  d["donation_amount"], d["donation_date"]))
            inserted += cursor.rowcount
        except sqlite3.Error as e:
            print(f"DB error: {e}")

    conn.execute("INSERT OR REPLACE INTO meta (key, value) VALUES ('last_updated', ?)",
                 (datetime.now().strftime("%d %B %Y %H:%M"),))
    conn.commit()
    conn.close()
    return inserted


async def main():
    init_db()
    print("Scraping...")
    donations = await scrape()
    print(f"Found {len(donations)} donations")
    if donations:
        inserted = save(donations)
        print(f"Inserted {inserted} new rows into {DB_PATH}")
    else:
        print("No donations found — page structure may have changed.")


if __name__ == "__main__":
    asyncio.run(main())