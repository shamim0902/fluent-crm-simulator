# FluentCRM Simulator

Automatically generate test campaigns and simulate email engagement (opens/clicks) for FluentCRM. A developer tool for testing reports, analytics, automations, and performance at scale.

## Requirements

- WordPress 5.6+
- PHP 7.4+
- FluentCRM (latest version recommended)

## Installation

[![Download Latest Release](https://img.shields.io/github/v/release/shamim0902/fluent-crm-simulator?label=Download%20Latest&style=for-the-badge&color=0073aa)](https://github.com/shamim0902/fluent-crm-simulator/releases/latest/download/fluent-crm-simulator.zip)

1. Download the latest release zip from the button above (or from [Releases](https://github.com/shamim0902/fluent-crm-simulator/releases))
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin

Or manually:

1. Extract the zip to `/wp-content/plugins/fluent-crm-simulator/`
2. Activate the plugin from the WordPress Plugins screen

## How to Use

Navigate to **Tools > FCRM Simulator** in your WordPress admin dashboard.

### Quick Start — Manual Mode (Recommended)

Use this mode to generate campaigns one at a time with full control.

1. **Select Tags/Lists** — Click the tag/list pills to select which subscribers to target. Blue pills = included, red pills = excluded.
2. **Set Date Range** — Choose start and end dates. Email "sent" timestamps will be randomly spread across this range for realistic distribution.
3. **Set Click Range** — Configure how many emails (min–max) should receive simulated engagement per campaign. Default is 100–200.
4. **Click "Generate Campaign & Simulate"** — This does everything in one step:
   - Creates a campaign targeting the selected tags/lists
   - Generates one `CampaignEmail` record per matching subscriber (marked as "sent")
   - Randomly selects emails within the click range
   - Simulates opens and clicks with tracking metrics
5. **Repeat** as needed to generate more campaigns.

> **Note:** The status panel will show "Stopped" in manual mode. This is normal — the status indicator only reflects whether **auto-generation** is enabled, not whether you've generated campaigns manually.

### Auto Mode — Continuous Generation

Use this mode to generate campaigns automatically on a schedule.

1. **Configure all settings** as described above (tags, lists, date range, click range)
2. **Set "Auto-generate"** to a number greater than `0` (e.g., `2` for 2 campaigns per hour)
3. **Click "Save Settings"**
4. The status will change to **"Running (2/hr)"** with a pulsing indicator
5. Campaigns are generated in batches every 5 minutes (e.g., 2/hr = 1 campaign every 30 minutes)
6. A warning banner appears across all admin pages while auto-generation is active
7. **To stop:** Click **"Stop Simulation"** or set auto-generate to `0` and save

### Simulate Clicks Only

Click **"Simulate Clicks Only"** to add more engagement data (opens/clicks) to **existing** simulated campaigns without creating new ones. This is useful when you want to increase engagement metrics on campaigns you've already generated.

### Clean Up

Click **"Purge All Simulated Data"** to permanently delete all simulator-created campaigns, their emails, and tracking metrics. **Real campaigns are never affected** — the purge only targets data marked with the simulator's internal flag.

## Settings Reference

### Campaign Settings

| Setting | Description | Default |
|---|---|---|
| **Include Tags** | Subscribers with any selected tag will be targeted | None |
| **Include Lists** | Subscribers in any selected list will be targeted | None |
| **Exclude Tags** | Subscribers with these tags will be removed from targeting | None |
| **Exclude Lists** | Subscribers in these lists will be removed from targeting | None |
| **Date Range** | Start/end dates for spreading email timestamps | Last 30 days |
| **Subject** | Email subject line for the simulated campaign | "Simulated Campaign" |
| **Email Body** | HTML content for the simulated email body | Default placeholder |

> You must select at least one tag or list. The simulator only targets contacts with `status = subscribed`.

### Engagement Settings

| Setting | Description | Default |
|---|---|---|
| **Click Range (min–max)** | Number of emails randomly selected for engagement simulation per campaign. Capped by the actual number of emails in the campaign. | 100–200 |
| **Open-Only Rate (%)** | Of the selected emails, this percentage will be marked as **opened only** (no click). The remaining emails will receive **both opens and clicks** (1–3 random URLs each). | 70% |
| **Auto-generate** | Number of campaigns to generate per hour. Set to `0` to disable automatic generation. | 0 (disabled) |

### Example Scenarios

**"I want 150 emails clicked out of my campaign"**
- Set click range: Min `150`, Max `150`
- Set open-only rate: `0%` (all selected emails get clicks)

**"I want realistic engagement: mostly opens, some clicks"**
- Set click range: Min `100`, Max `200`
- Set open-only rate: `70%` (70% open-only, 30% get clicks too)

**"I want to test with high volume over the last quarter"**
- Set date range: 3 months ago to today
- Select your target tags
- Click "Generate Campaign & Simulate" multiple times, or set auto-generate to `5/hr`

## How It Works

1. **Campaign Creation** — Creates a FluentCRM campaign with status `archived` (bypasses the normal sending pipeline — no actual emails are ever sent)
2. **Email Records** — Generates one `fc_campaign_emails` row per matching subscriber with `status = sent`, a unique `email_hash`, and a randomized timestamp within the date range
3. **Open Simulation** — Sets `is_open = 1` on selected campaign emails and inserts `CampaignUrlMetric` records with `type = open`
4. **Click Simulation** — Increments `click_counter` on campaign emails and inserts `CampaignUrlMetric` records with `type = click`, referencing URLs stored in `fc_url_stores`
5. **Scheduling** — Uses Action Scheduler (bundled with FluentCRM) for auto-generation, with WP-Cron as fallback. Runs every 5 minutes.
6. **Data Safety** — All simulated campaigns are tagged with `_fcrmsim_simulated` in `fc_meta`. The purge function queries this marker to identify and delete only simulated data.

### What Gets Created

| Table | Records Created |
|---|---|
| `fc_campaigns` | One campaign per generation |
| `fc_campaign_emails` | One row per subscriber in the targeted tags/lists |
| `fc_campaign_url_metrics` | Open and click tracking records |
| `fc_url_stores` | 5 placeholder URLs for click tracking |
| `fc_meta` | `_fcrmsim_simulated` marker per campaign |

### What Does NOT Get Modified

- **Subscribers** — No contacts are created, modified, or deleted
- **Tags/Lists** — No tag or list associations are changed
- **Real Campaigns** — Existing campaigns are never touched
- **Email Sending** — No emails are actually sent, no mail queue is affected

## Important Notes

- Designed for **development and staging environments** — not recommended for production use
- Simulated campaigns appear in FluentCRM analytics and reports (by design, for realistic testing)
- No actual emails are sent — campaigns are created directly in `archived` status
- The simulator works with **existing subscribers** — make sure you have contacts with the selected tags/lists
- When the plugin is deactivated, all scheduled events are cleared; existing simulated data remains until purged
- Auto-updates are supported via GitHub releases

## License

GPLv2 or later
