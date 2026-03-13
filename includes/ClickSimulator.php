<?php

namespace FluentCrmSimulator;

use FluentCrm\App\Models\CampaignUrlMetric;

class ClickSimulator
{
    /**
     * Simulate opens and clicks on campaign emails.
     *
     * @param array $settings
     * @return array Stats about the simulation
     */
    public static function simulate(array $settings)
    {
        $clickMin = intval($settings['click_min'] ?? 100);
        $clickMax = intval($settings['click_max'] ?? 200);
        $openRatePercent = intval($settings['open_rate_percent'] ?? 70);
        $targetCampaignId = intval($settings['target_campaign_id'] ?? 0);

        $campaignIds = self::getTargetCampaignIds($targetCampaignId);

        if (empty($campaignIds)) {
            return [
                'total_opens'         => 0,
                'total_clicks'        => 0,
                'total_emails'        => 0,
                'campaigns_processed' => 0,
            ];
        }

        $urlIds = CampaignGenerator::getFakeUrlIds();

        $totalOpens = 0;
        $totalClicks = 0;
        $totalEmails = 0;

        foreach ($campaignIds as $campaignId) {
            $result = self::simulateCampaign($campaignId, $clickMin, $clickMax, $openRatePercent, $urlIds);
            $totalOpens += $result['opens'];
            $totalClicks += $result['clicks'];
            $totalEmails += $result['emails'];
        }

        return [
            'total_opens'         => $totalOpens,
            'total_clicks'        => $totalClicks,
            'total_emails'        => $totalEmails,
            'campaigns_processed' => count($campaignIds),
        ];
    }

    private static function simulateCampaign($campaignId, $clickMin, $clickMax, $openRatePercent, $urlIds)
    {
        $db = fluentCrmDb();

        // Get all sent emails for this campaign
        $emails = $db->table('fc_campaign_emails')
            ->where('campaign_id', $campaignId)
            ->where('status', 'sent')
            ->select(['id', 'subscriber_id', 'updated_at'])
            ->get();

        if ($emails->isEmpty()) {
            return ['opens' => 0, 'clicks' => 0, 'emails' => 0];
        }

        $totalAvailable = $emails->count();

        // Pick a random count within the range (capped by available emails)
        $targetCount = wp_rand($clickMin, $clickMax);
        $targetCount = min($targetCount, $totalAvailable);

        // Randomly select emails
        $emailArray = $emails->toArray();
        shuffle($emailArray);
        $selectedEmails = array_slice($emailArray, 0, $targetCount);

        // Split: open_rate_percent get opened only, rest get opened + clicked
        // "Open rate" here means: what % are open-only (no click)
        $openOnlyCount = (int) ceil($targetCount * ($openRatePercent / 100));
        $clickCount = $targetCount - $openOnlyCount;

        $openOnlyEmails = array_slice($selectedEmails, 0, $openOnlyCount);
        $clickEmails = array_slice($selectedEmails, $openOnlyCount);

        // Simulate opens for ALL selected emails
        $allSelectedIds = array_column($selectedEmails, 'id');
        if (!empty($allSelectedIds)) {
            foreach (array_chunk($allSelectedIds, 500) as $chunk) {
                $db->table('fc_campaign_emails')
                    ->whereIn('id', $chunk)
                    ->update(['is_open' => 1]);
            }
        }

        // Insert open metrics for all selected emails
        foreach ($selectedEmails as $email) {
            CampaignUrlMetric::maybeInsert([
                'campaign_id'   => $campaignId,
                'subscriber_id' => $email->subscriber_id,
                'type'          => 'open',
                'ip_address'    => self::randomIp(),
                'counter'       => 1,
                'created_at'    => $email->updated_at,
                'updated_at'    => $email->updated_at,
            ]);
        }

        // Simulate clicks for the click group
        $totalClickEvents = 0;
        if (!empty($clickEmails) && !empty($urlIds)) {
            foreach ($clickEmails as $email) {
                // Each clicked email clicks 1-3 random URLs
                $numClicks = wp_rand(1, min(3, count($urlIds)));
                $clickedUrlIds = (array) array_rand(array_flip($urlIds), $numClicks);

                foreach ($clickedUrlIds as $urlId) {
                    CampaignUrlMetric::maybeInsert([
                        'campaign_id'   => $campaignId,
                        'subscriber_id' => $email->subscriber_id,
                        'type'          => 'click',
                        'url_id'        => $urlId,
                        'ip_address'    => self::randomIp(),
                        'counter'       => 1,
                        'created_at'    => $email->updated_at,
                        'updated_at'    => $email->updated_at,
                    ]);
                    $totalClickEvents++;
                }

                // Update click_counter on the campaign email
                $db->table('fc_campaign_emails')
                    ->where('id', $email->id)
                    ->update([
                        'click_counter' => $db->raw('COALESCE(click_counter, 0) + ' . $numClicks),
                    ]);
            }
        }

        return [
            'opens'  => count($selectedEmails),
            'clicks' => $totalClickEvents,
            'emails' => $totalAvailable,
        ];
    }

    /**
     * Get campaign IDs to simulate clicks on.
     */
    private static function getTargetCampaignIds($targetCampaignId)
    {
        $db = fluentCrmDb();

        if ($targetCampaignId > 0) {
            // Verify it's a simulated campaign
            $exists = $db->table('fc_meta')
                ->where('object_id', $targetCampaignId)
                ->where('object_type', 'FluentCrm\App\Models\Campaign')
                ->where('key', '_fcrmsim_simulated')
                ->exists();

            return $exists ? [$targetCampaignId] : [];
        }

        // Get all simulated campaign IDs
        return $db->table('fc_meta')
            ->where('object_type', 'FluentCrm\App\Models\Campaign')
            ->where('key', '_fcrmsim_simulated')
            ->pluck('object_id')
            ->toArray();
    }

    private static function randomIp()
    {
        return wp_rand(10, 223) . '.' . wp_rand(0, 255) . '.' . wp_rand(0, 255) . '.' . wp_rand(1, 254);
    }
}
