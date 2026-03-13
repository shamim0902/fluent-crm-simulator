<?php

namespace FluentCrmSimulator;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Tag;

class SimulatorAdmin
{
    public function register()
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_bar_menu', [$this, 'addAdminBarMenu'], 120);

        add_action('wp_ajax_fcrmsim_save_settings', [$this, 'ajaxSaveSettings']);
        add_action('wp_ajax_fcrmsim_generate_campaign', [$this, 'ajaxGenerateCampaign']);
        add_action('wp_ajax_fcrmsim_simulate_clicks', [$this, 'ajaxSimulateClicks']);
        add_action('wp_ajax_fcrmsim_purge_data', [$this, 'ajaxPurgeData']);
        add_action('wp_ajax_fcrmsim_stop_simulation', [$this, 'ajaxStopSimulation']);

        add_action('admin_notices', [$this, 'showRunningNotice']);
    }

    public function addAdminMenu()
    {
        add_management_page(
            'FluentCRM Simulator',
            'FCRM Simulator',
            'manage_options',
            'fluent-crm-simulator',
            [$this, 'renderSettingsPage']
        );
    }

    public function addAdminBarMenu($wpAdminBar)
    {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }

        $settings = self::getSettings();
        $campaignsPerHour = intval($settings['campaigns_per_hour'] ?? 0);

        $title = __('FCRM Simulator', 'fluent-crm-simulator');
        if ($campaignsPerHour > 0) {
            $title .= sprintf(' (%d/hr)', $campaignsPerHour);
        }

        $wpAdminBar->add_node([
            'id'    => 'fluent_crm_simulator',
            'title' => esc_html($title),
            'href'  => esc_url(admin_url('tools.php?page=fluent-crm-simulator')),
            'meta'  => [
                'title' => __('Open FluentCRM Simulator', 'fluent-crm-simulator')
            ]
        ]);
    }

    public function renderSettingsPage()
    {
        $settings = self::getSettings();
        $stats = get_option('fcrmsim_stats', [
            'total_campaigns' => 0,
            'total_clicks'    => 0,
            'last_run'        => null,
        ]);
        $simulatedCount = PurgeHandler::getSimulatedCampaignCount();
        $campaignsPerHour = intval($settings['campaigns_per_hour'] ?? 0);
        $isRunning = $campaignsPerHour > 0;

        $tags = Tag::orderBy('title', 'ASC')->get();
        $lists = Lists::orderBy('title', 'ASC')->get();

        include FCRMSIM_PLUGIN_DIR . 'views/settings-page.php';
    }

    public function showRunningNotice()
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'tools_page_fluent-crm-simulator') {
            return;
        }

        $settings = self::getSettings();
        $campaignsPerHour = intval($settings['campaigns_per_hour'] ?? 0);

        if ($campaignsPerHour > 0) {
            printf(
                '<div class="notice notice-warning"><p><strong>FluentCRM Simulator</strong> is actively generating %d campaigns per hour. <a href="%s">Manage Simulator</a></p></div>',
                $campaignsPerHour,
                esc_url(admin_url('tools.php?page=fluent-crm-simulator'))
            );
        }
    }

    public function ajaxSaveSettings()
    {
        check_ajax_referer('fcrmsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $selectedTagIds = array_filter(array_map('intval', (array) ($_POST['selected_tag_ids'] ?? [])));
        $selectedListIds = array_filter(array_map('intval', (array) ($_POST['selected_list_ids'] ?? [])));
        $excludedTagIds = array_filter(array_map('intval', (array) ($_POST['excluded_tag_ids'] ?? [])));
        $excludedListIds = array_filter(array_map('intval', (array) ($_POST['excluded_list_ids'] ?? [])));

        $clickMin = max(1, intval($_POST['click_min'] ?? 100));
        $clickMax = max($clickMin, intval($_POST['click_max'] ?? 200));
        $openRatePercent = max(0, min(100, intval($_POST['open_rate_percent'] ?? 70)));

        $dateRangeStart = sanitize_text_field($_POST['date_range_start'] ?? '');
        $dateRangeEnd = sanitize_text_field($_POST['date_range_end'] ?? '');

        $campaignSubject = sanitize_text_field($_POST['campaign_subject'] ?? 'Simulated Campaign');
        $campaignBody = wp_kses_post($_POST['campaign_body'] ?? '<p>This is a simulated email body for testing purposes.</p>');

        $campaignsPerHour = max(0, intval($_POST['campaigns_per_hour'] ?? 0));

        $settings = [
            'selected_tag_ids'   => $selectedTagIds,
            'selected_list_ids'  => $selectedListIds,
            'excluded_tag_ids'   => $excludedTagIds,
            'excluded_list_ids'  => $excludedListIds,
            'click_min'          => $clickMin,
            'click_max'          => $clickMax,
            'open_rate_percent'  => $openRatePercent,
            'date_range_start'   => $dateRangeStart,
            'date_range_end'     => $dateRangeEnd,
            'campaign_subject'   => $campaignSubject,
            'campaign_body'      => $campaignBody,
            'campaigns_per_hour' => $campaignsPerHour,
        ];

        update_option('fcrmsim_settings', $settings);

        SimulatorScheduler::reschedule($campaignsPerHour);

        wp_send_json_success([
            'message'  => 'Settings saved successfully.',
            'settings' => $settings,
        ]);
    }

    public function ajaxGenerateCampaign()
    {
        check_ajax_referer('fcrmsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $settings = self::getSettings();

        if (empty($settings['selected_tag_ids']) && empty($settings['selected_list_ids'])) {
            wp_send_json_error(['message' => 'Please select at least one tag or list.']);
        }

        $campaignId = CampaignGenerator::generate($settings);

        if (!$campaignId) {
            wp_send_json_error(['message' => 'Failed to generate campaign. No subscribers found matching the criteria.']);
        }

        // Simulate clicks on the newly created campaign
        $clickSettings = array_merge($settings, ['target_campaign_id' => $campaignId]);
        $clickStats = ClickSimulator::simulate($clickSettings);

        $stats = get_option('fcrmsim_stats', ['total_campaigns' => 0, 'total_clicks' => 0, 'last_run' => null]);
        $stats['total_campaigns'] = ($stats['total_campaigns'] ?? 0) + 1;
        $stats['total_clicks'] = ($stats['total_clicks'] ?? 0) + ($clickStats['total_clicks'] ?? 0);
        $stats['last_run'] = current_time('mysql');
        update_option('fcrmsim_stats', $stats);

        wp_send_json_success([
            'message'     => sprintf(
                'Campaign created with %d emails. Simulated %d opens and %d clicks.',
                $clickStats['total_emails'] ?? 0,
                $clickStats['total_opens'] ?? 0,
                $clickStats['total_clicks'] ?? 0
            ),
            'campaign_id' => $campaignId,
            'click_stats' => $clickStats,
            'stats'       => $stats,
        ]);
    }

    public function ajaxSimulateClicks()
    {
        check_ajax_referer('fcrmsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $settings = self::getSettings();
        $targetCampaignId = intval($_POST['target_campaign_id'] ?? 0);
        $clickSettings = array_merge($settings, ['target_campaign_id' => $targetCampaignId]);

        $clickStats = ClickSimulator::simulate($clickSettings);

        if (empty($clickStats['total_opens']) && empty($clickStats['total_clicks'])) {
            wp_send_json_error(['message' => 'No simulated campaigns with sent emails found.']);
        }

        $stats = get_option('fcrmsim_stats', ['total_campaigns' => 0, 'total_clicks' => 0, 'last_run' => null]);
        $stats['total_clicks'] = ($stats['total_clicks'] ?? 0) + ($clickStats['total_clicks'] ?? 0);
        $stats['last_run'] = current_time('mysql');
        update_option('fcrmsim_stats', $stats);

        wp_send_json_success([
            'message'     => sprintf(
                'Simulated %d opens and %d clicks across %d campaigns.',
                $clickStats['total_opens'] ?? 0,
                $clickStats['total_clicks'] ?? 0,
                $clickStats['campaigns_processed'] ?? 0
            ),
            'click_stats' => $clickStats,
            'stats'       => $stats,
        ]);
    }

    public function ajaxPurgeData()
    {
        check_ajax_referer('fcrmsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $result = PurgeHandler::purge();

        wp_send_json_success($result);
    }

    public function ajaxStopSimulation()
    {
        check_ajax_referer('fcrmsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $settings = self::getSettings();
        $settings['campaigns_per_hour'] = 0;
        update_option('fcrmsim_settings', $settings);

        SimulatorScheduler::clearSchedule();

        wp_send_json_success(['message' => 'Simulation stopped.']);
    }

    public static function getSettings()
    {
        return wp_parse_args(get_option('fcrmsim_settings', []), [
            'selected_tag_ids'   => [],
            'selected_list_ids'  => [],
            'excluded_tag_ids'   => [],
            'excluded_list_ids'  => [],
            'click_min'          => 100,
            'click_max'          => 200,
            'open_rate_percent'  => 70,
            'date_range_start'   => gmdate('Y-m-d', strtotime('-30 days')),
            'date_range_end'     => gmdate('Y-m-d'),
            'campaign_subject'   => 'Simulated Campaign',
            'campaign_body'      => '<p>This is a simulated email body for testing purposes.</p>',
            'campaigns_per_hour' => 0,
        ]);
    }
}
