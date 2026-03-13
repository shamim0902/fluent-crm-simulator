<?php

namespace FluentCrmSimulator;

class PurgeHandler
{
    public static function purge()
    {
        $db = fluentCrmDb();
        $campaignIds = self::getSimulatedCampaignIds();

        if (empty($campaignIds)) {
            return ['deleted' => 0, 'message' => 'No simulated campaigns found.'];
        }

        $totalDeleted = 0;

        foreach (array_chunk($campaignIds, 100) as $chunk) {
            // Delete URL metrics
            $db->table('fc_campaign_url_metrics')
                ->whereIn('campaign_id', $chunk)
                ->delete();

            // Delete campaign emails
            $db->table('fc_campaign_emails')
                ->whereIn('campaign_id', $chunk)
                ->delete();

            // Delete campaign meta (including our _fcrmsim_simulated marker)
            $db->table('fc_meta')
                ->whereIn('object_id', $chunk)
                ->where('object_type', 'FluentCrm\App\Models\Campaign')
                ->delete();

            // Delete campaigns
            $deleted = $db->table('fc_campaigns')
                ->whereIn('id', $chunk)
                ->delete();

            $totalDeleted += (int) $deleted;
        }

        update_option('fcrmsim_stats', [
            'total_campaigns' => 0,
            'total_clicks'    => 0,
            'last_run'        => null,
            'last_purge'      => current_time('mysql'),
        ]);

        return [
            'deleted' => $totalDeleted,
            'message' => sprintf('Successfully deleted %d simulated campaigns and related data.', $totalDeleted),
        ];
    }

    public static function getSimulatedCampaignCount()
    {
        $db = fluentCrmDb();

        return (int) $db->table('fc_meta')
            ->where('object_type', 'FluentCrm\App\Models\Campaign')
            ->where('key', '_fcrmsim_simulated')
            ->count();
    }

    private static function getSimulatedCampaignIds()
    {
        $db = fluentCrmDb();

        return $db->table('fc_meta')
            ->where('object_type', 'FluentCrm\App\Models\Campaign')
            ->where('key', '_fcrmsim_simulated')
            ->pluck('object_id');
    }
}
