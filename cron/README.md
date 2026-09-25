# Daily stock reports (Stock In 9:30am / Stock Out 7:00pm)

These two scripts email a CSV report to the admin at a fixed time every day:

- `stock_in_daily_report.php` — everything received into stock **today**
- `stock_out_daily_report.php` — everything dispatched from stock **today**
  (covers manual Stock Out entries, sales order/DC dispatches, manual sales,
  and website orders — every way stock leaves)

Each also saves a copy of that day's CSV into `cron/reports/` (not web-accessible —
see `reports/.htaccess`).

## One-time setup on the server (SSH, since this is Docker/VPS)

These are plain PHP scripts triggered by an HTTP request — no cron daemon
needs to be installed inside the container. On the **host** machine (not
inside the container), add two crontab entries that `curl` the scripts at the
right time:

```
crontab -e
```

Add these two lines (adjust the domain if needed):

```
30 9 * * * curl -s "https://valluvamproducts.com/cron/stock_in_daily_report.php?key=cbf64ff8f511ba21bbcf2f8f9300b1730f8f0154" >/dev/null 2>&1
0 19 * * * curl -s "https://valluvamproducts.com/cron/stock_out_daily_report.php?key=cbf64ff8f511ba21bbcf2f8f9300b1730f8f0154" >/dev/null 2>&1
```

That's 9:30am and 7:00pm server time, every day. If your server's clock is in
a different timezone than you expect, check with `date` on the server and
adjust the hour/minute accordingly.

## The secret key

The `key=...` value above is a security check so a stranger can't trigger
these (or read your data) by guessing the URL. It defaults to the value
already baked into both scripts, but you can override it without editing any
code by setting an environment variable the same way `DB_HOST`/`DB_PASS`
etc. are already set for this project:

```
CRON_REPORT_KEY=your-own-long-random-string
```

If you change it, update the `key=` value in both crontab lines above to match.

## Changing the report recipient

Defaults to `admin@valluvam.com`. Override with an environment variable, same
pattern as above:

```
STOCK_REPORT_EMAIL=someone@yourcompany.com
```

## Testing it right now (don't wait for 9:30am)

Visit either URL directly in a browser with the key, e.g.:

```
https://valluvamproducts.com/cron/stock_in_daily_report.php?key=cbf64ff8f511ba21bbcf2f8f9300b1730f8f0154
```

It replies with a one-line status (`OK — N row(s). Email: sent`) and sends
the email immediately — the exact same thing that happens automatically at
9:30am / 7:00pm once the crontab entries above are in place.
