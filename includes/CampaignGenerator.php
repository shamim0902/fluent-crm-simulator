<?php

namespace FluentCrmSimulator;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\UrlStores;

class CampaignGenerator
{
    private static $fakeUrls = [
        'https://example.com/product-page',
        'https://example.com/pricing',
        'https://example.com/blog/latest-post',
        'https://example.com/contact-us',
        'https://example.com/signup',
    ];

    /**
     * Generate a simulated campaign targeting selected tags/lists.
     *
     * @param array $settings
     * @return int|null Campaign ID or null on failure
     */
    public static function generate(array $settings)
    {
        $subscriberIds = self::getSubscriberIds($settings);

        if (empty($subscriberIds)) {
            return null;
        }

        $campaign = self::createCampaign($settings);

        if (!$campaign) {
            return null;
        }

        $emailCount = self::createCampaignEmails($campaign, $subscriberIds, $settings);

        // Update recipients count
        $campaign->recipients_count = $emailCount;
        $campaign->save();

        // Mark as simulated
        fluentcrm_update_campaign_meta($campaign->id, '_fcrmsim_simulated', FCRMSIM_VERSION);

        // Create URL stores for click tracking
        self::createUrlStores();

        return $campaign->id;
    }

    private static function createCampaign(array $settings)
    {
        $subject = $settings['campaign_subject'] ?? 'Simulated Campaign';
        $body = $settings['campaign_body'] ?? '<p>This is a simulated email body.</p>';
        $startDate = $settings['date_range_start'] ?? gmdate('Y-m-d', strtotime('-30 days'));

        $subscriberSettings = [];

        if (!empty($settings['selected_tag_ids'])) {
            foreach ($settings['selected_tag_ids'] as $tagId) {
                $subscriberSettings[] = ['list' => 'all', 'tag' => $tagId];
            }
        }

        if (!empty($settings['selected_list_ids'])) {
            foreach ($settings['selected_list_ids'] as $listId) {
                $subscriberSettings[] = ['list' => $listId, 'tag' => 'all'];
            }
        }

        if (empty($subscriberSettings)) {
            $subscriberSettings[] = ['list' => 'all', 'tag' => 'all'];
        }

        $excludedSettings = [];
        if (!empty($settings['excluded_tag_ids'])) {
            foreach ($settings['excluded_tag_ids'] as $tagId) {
                $excludedSettings[] = ['list' => null, 'tag' => $tagId];
            }
        }
        if (!empty($settings['excluded_list_ids'])) {
            foreach ($settings['excluded_list_ids'] as $listId) {
                $excludedSettings[] = ['list' => $listId, 'tag' => null];
            }
        }

        return Campaign::create([
            'title'           => $subject . ' - ' . gmdate('Y-m-d H:i:s'),
            'slug'            => sanitize_title($subject . '-' . time()),
            'email_subject'   => $subject,
            'email_body'      => $body,
            'status'          => 'archived',
            'type'            => 'campaign',
            'design_template' => 'simple',
            'settings'        => maybe_serialize([
                'mailer_settings'      => [
                    'from_name'      => 'Simulator',
                    'from_email'     => '',
                    'reply_to_name'  => '',
                    'reply_to_email' => '',
                    'is_custom'      => 'no',
                ],
                'subscribers'          => $subscriberSettings,
                'excludedSubscribers'  => $excludedSettings,
                'sending_filter'       => 'list_tag',
            ]),
            'scheduled_at'    => $startDate . ' 00:00:00',
            'created_at'      => gmdate('Y-m-d H:i:s'),
            'updated_at'      => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private static function getSubscriberIds(array $settings)
    {
        $db = fluentCrmDb();

        $tagIds = $settings['selected_tag_ids'] ?? [];
        $listIds = $settings['selected_list_ids'] ?? [];
        $excludedTagIds = $settings['excluded_tag_ids'] ?? [];
        $excludedListIds = $settings['excluded_list_ids'] ?? [];

        if (empty($tagIds) && empty($listIds)) {
            return [];
        }

        // Get subscriber IDs matching selected tags
        $subscriberIds = [];

        if (!empty($tagIds)) {
            $tagSubscribers = $db->table('fc_subscriber_pivot')
                ->select('subscriber_id')
                ->where('object_type', 'FluentCrm\App\Models\Tag')
                ->whereIn('object_id', $tagIds)
                ->distinct()
                ->pluck('subscriber_id')
                ->toArray();

            $subscriberIds = array_merge($subscriberIds, $tagSubscribers);
        }

        if (!empty($listIds)) {
            $listSubscribers = $db->table('fc_subscriber_pivot')
                ->select('subscriber_id')
                ->where('object_type', 'FluentCrm\App\Models\Lists')
                ->whereIn('object_id', $listIds)
                ->distinct()
                ->pluck('subscriber_id')
                ->toArray();

            $subscriberIds = array_merge($subscriberIds, $listSubscribers);
        }

        $subscriberIds = array_unique($subscriberIds);

        if (empty($subscriberIds)) {
            return [];
        }

        // Filter to only subscribed contacts
        $subscriberIds = $db->table('fc_subscribers')
            ->whereIn('id', $subscriberIds)
            ->where('status', 'subscribed')
            ->pluck('id')
            ->toArray();

        // Exclude subscribers by excluded tags
        if (!empty($excludedTagIds) && !empty($subscriberIds)) {
            $excludedByTag = $db->table('fc_subscriber_pivot')
                ->select('subscriber_id')
                ->where('object_type', 'FluentCrm\App\Models\Tag')
                ->whereIn('object_id', $excludedTagIds)
                ->whereIn('subscriber_id', $subscriberIds)
                ->distinct()
                ->pluck('subscriber_id')
                ->toArray();

            $subscriberIds = array_diff($subscriberIds, $excludedByTag);
        }

        // Exclude subscribers by excluded lists
        if (!empty($excludedListIds) && !empty($subscriberIds)) {
            $excludedByList = $db->table('fc_subscriber_pivot')
                ->select('subscriber_id')
                ->where('object_type', 'FluentCrm\App\Models\Lists')
                ->whereIn('object_id', $excludedListIds)
                ->whereIn('subscriber_id', $subscriberIds)
                ->distinct()
                ->pluck('subscriber_id')
                ->toArray();

            $subscriberIds = array_diff($subscriberIds, $excludedByList);
        }

        return array_values($subscriberIds);
    }

    private static function createCampaignEmails(Campaign $campaign, array $subscriberIds, array $settings)
    {
        $db = fluentCrmDb();
        $startDate = $settings['date_range_start'] ?? gmdate('Y-m-d', strtotime('-30 days'));
        $endDate = $settings['date_range_end'] ?? gmdate('Y-m-d');

        // Generate spread timestamps
        $timestamps = self::generateSpreadTimestamps(count($subscriberIds), $startDate, $endDate);

        // Get subscriber emails in bulk
        $subscribers = $db->table('fc_subscribers')
            ->whereIn('id', $subscriberIds)
            ->select(['id', 'email'])
            ->get()
            ->keyBy('id');

        $inserted = 0;
        $chunks = array_chunk($subscriberIds, 500);

        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $subscriberId) {
                $subscriber = $subscribers->get($subscriberId);
                if (!$subscriber) {
                    continue;
                }

                $timestamp = $timestamps[$inserted] ?? gmdate('Y-m-d H:i:s');

                $rows[] = [
                    'campaign_id'   => $campaign->id,
                    'email_type'    => 'campaign',
                    'subscriber_id' => $subscriberId,
                    'email_address' => $subscriber->email,
                    'email_subject' => $campaign->email_subject,
                    'email_body'    => $campaign->email_body,
                    'email_hash'    => wp_generate_uuid4(),
                    'status'        => 'sent',
                    'is_open'       => 0,
                    'is_parsed'     => 1,
                    'scheduled_at'  => $timestamp,
                    'created_at'    => $timestamp,
                    'updated_at'    => $timestamp,
                ];

                $inserted++;
            }

            if (!empty($rows)) {
                $db->table('fc_campaign_emails')->insert($rows);
            }
        }

        return $inserted;
    }

    private static function generateSpreadTimestamps($count, $startDate, $endDate)
    {
        $start = strtotime($startDate . ' 08:00:00');
        $end = strtotime($endDate . ' 20:00:00');

        if ($end <= $start) {
            $end = $start + 86400; // 1 day fallback
        }

        $timestamps = [];
        for ($i = 0; $i < $count; $i++) {
            $timestamps[] = gmdate('Y-m-d H:i:s', wp_rand($start, $end));
        }

        sort($timestamps);

        return $timestamps;
    }

    /**
     * Create URL store entries for click tracking simulation.
     *
     * @return array URL IDs
     */
    public static function createUrlStores()
    {
        $urlIds = [];
        foreach (self::$fakeUrls as $url) {
            UrlStores::getUrlSlug($url);
            $urlStore = UrlStores::where('url', $url)->first();
            if ($urlStore) {
                $urlIds[] = $urlStore->id;
            }
        }

        return $urlIds;
    }

    /**
     * Get the fake URL IDs from url_stores.
     *
     * @return array
     */
    public static function getFakeUrlIds()
    {
        $urlIds = [];
        foreach (self::$fakeUrls as $url) {
            $urlStore = UrlStores::where('url', $url)->first();
            if ($urlStore) {
                $urlIds[] = $urlStore->id;
            }
        }

        if (empty($urlIds)) {
            $urlIds = self::createUrlStores();
        }

        return $urlIds;
    }
}
