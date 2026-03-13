<?php

namespace FluentCrmSimulator;

class SimulatorScheduler
{
    const HOOK_NAME = 'fcrmsim_generate_campaign';

    public function register()
    {
        add_action(self::HOOK_NAME, [$this, 'runScheduledGeneration']);
    }

    public function runScheduledGeneration()
    {
        $settings = SimulatorAdmin::getSettings();
        $campaignsPerHour = intval($settings['campaigns_per_hour'] ?? 0);

        if ($campaignsPerHour <= 0) {
            self::clearSchedule();
            return;
        }

        // 5-minute intervals = 12 runs per hour
        $campaignsPerRun = max(1, (int) ceil($campaignsPerHour / 12));

        $totalCreated = 0;
        $totalClicks = 0;

        for ($i = 0; $i < $campaignsPerRun; $i++) {
            $campaignId = CampaignGenerator::generate($settings);

            if ($campaignId) {
                $totalCreated++;
                $clickSettings = array_merge($settings, ['target_campaign_id' => $campaignId]);
                $clickStats = ClickSimulator::simulate($clickSettings);
                $totalClicks += ($clickStats['total_clicks'] ?? 0);
            }
        }

        $stats = get_option('fcrmsim_stats', [
            'total_campaigns' => 0,
            'total_clicks'    => 0,
            'last_run'        => null,
        ]);
        $stats['total_campaigns'] = ($stats['total_campaigns'] ?? 0) + $totalCreated;
        $stats['total_clicks'] = ($stats['total_clicks'] ?? 0) + $totalClicks;
        $stats['last_run'] = current_time('mysql');
        update_option('fcrmsim_stats', $stats);
    }

    public static function reschedule($campaignsPerHour)
    {
        self::clearSchedule();

        if ($campaignsPerHour <= 0) {
            return;
        }

        if (function_exists('as_schedule_recurring_action')) {
            as_schedule_recurring_action(
                time(),
                5 * MINUTE_IN_SECONDS,
                self::HOOK_NAME,
                [],
                'fluent-crm-simulator',
                true
            );
        } else {
            if (!wp_next_scheduled(self::HOOK_NAME)) {
                wp_schedule_event(time(), 'fcrmsim_five_minutes', self::HOOK_NAME);
            }
        }
    }

    public static function clearSchedule()
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK_NAME);
        }

        $timestamp = wp_next_scheduled(self::HOOK_NAME);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK_NAME);
        }
    }
}
