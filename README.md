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

### 1. Open the Simulator

Navigate to **Tools > FCRM Simulator** in your WordPress admin dashboard.

### 2. Configure Campaign Settings

| Setting | Description |
|---|---|
| **Select Tags** | Choose which tags to target subscribers from. Subscribers with any selected tag will be included. |
| **Select Lists** | Choose which lists to target subscribers from. Optional — can use tags only. |
| **Exclude Tags** | Subscribers with these tags will be excluded from the campaign. |
| **Exclude Lists** | Subscribers in these lists will be excluded from the campaign. |
| **Date Range** | Start and end dates. Sent timestamps are spread across this range for realistic distribution. |
| **Email Subject** | Subject line for the simulated campaign. |
| **Email Body** | HTML content for the simulated email body. |

### 3. Configure Click Simulation

| Setting | Description |
|---|---|
| **Click Range** | Min and max number of emails to simulate engagement on (e.g., 100–200). |
| **Open-Only Rate (%)** | Percentage of selected emails that are opened but NOT clicked. The rest get both opens and clicks. |
| **Campaigns per Hour** | Set to auto-generate campaigns on a schedule. Set to `0` to disable. |

### 4. Generate & Simulate

- Click **Generate Campaign & Simulate** to create a campaign targeting the selected tags/lists, generate email records for matching subscribers, and simulate opens/clicks in one step
- Click **Simulate Clicks Only** to add more engagement data to existing simulated campaigns
- A warning banner appears across admin pages while auto-generation is active

### 5. Stop the Simulation

Click **Stop Simulation** or set campaigns per hour to `0` and save. Deactivating the plugin also stops all scheduled generation.

### 6. Clean Up

Click **Purge All Simulated Data** to permanently delete all simulator-created campaigns, emails, and tracking metrics. Real campaigns are never affected.

## How It Works

- Creates campaigns with status `archived` (bypasses actual email sending)
- Generates `CampaignEmail` records for each subscriber matching tag/list criteria
- Simulates email opens by setting `is_open=1` and inserting `CampaignUrlMetric` records with `type=open`
- Simulates email clicks by incrementing `click_counter` and inserting `CampaignUrlMetric` records with `type=click`
- Uses **Action Scheduler** (bundled with FluentCRM) for scheduled generation, with WP-Cron as fallback
- All simulated campaigns are marked with `_fcrmsim_simulated` meta — the purge function targets only these
- Subscribers are NOT created or modified — the simulator works with your existing contacts

## Important Notes

- Designed for **development and staging environments** — not recommended for production use
- Simulated campaigns appear in FluentCRM analytics and reports (by design, for realistic testing)
- No actual emails are sent — campaigns are created directly in `archived` status
- When the plugin is deactivated, all scheduled events are cleared; existing simulated data remains until purged

## License

GPLv2 or later
