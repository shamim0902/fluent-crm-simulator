<?php
defined('ABSPATH') || exit;

/** @var array $settings */
/** @var array $stats */
/** @var int $simulatedCount */
/** @var int $campaignsPerHour */
/** @var bool $isRunning */
/** @var \Illuminate\Support\Collection $tags */
/** @var \Illuminate\Support\Collection $lists */

$selectedTagIds = $settings['selected_tag_ids'] ?? [];
$selectedListIds = $settings['selected_list_ids'] ?? [];
$excludedTagIds = $settings['excluded_tag_ids'] ?? [];
$excludedListIds = $settings['excluded_list_ids'] ?? [];
?>
<div class="wrap">
    <h1>FluentCRM Simulator</h1>
    <p>Generate test campaigns and simulate email engagement (opens/clicks) for FluentCRM. All simulated data is marked and can be purged at any time.</p>

    <?php if ($isRunning): ?>
        <div class="notice notice-warning inline" style="margin: 15px 0;">
            <p><strong>Simulation is ACTIVE</strong> &mdash; generating approximately <?php echo intval($campaignsPerHour); ?> campaigns per hour.</p>
        </div>
    <?php endif; ?>

    <!-- Status Panel -->
    <div class="card" style="max-width: 700px; margin-bottom: 20px;">
        <h2 style="margin-top: 0;">Status</h2>
        <table class="widefat striped" style="border: 0;">
            <tbody>
                <tr>
                    <td><strong>Simulation</strong></td>
                    <td>
                        <?php if ($isRunning): ?>
                            <span style="color: #d63638; font-weight: bold;">Running (<?php echo intval($campaignsPerHour); ?>/hr)</span>
                        <?php else: ?>
                            <span style="color: #50575e;">Stopped</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Total Campaigns Created</strong></td>
                    <td id="fcrmsim-total-campaigns"><?php echo intval($stats['total_campaigns'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Clicks Simulated</strong></td>
                    <td id="fcrmsim-total-clicks"><?php echo intval($stats['total_clicks'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td><strong>Last Run</strong></td>
                    <td id="fcrmsim-last-run"><?php echo esc_html($stats['last_run'] ?? 'Never'); ?></td>
                </tr>
                <tr>
                    <td><strong>Simulated Campaigns in DB</strong></td>
                    <td id="fcrmsim-sim-count"><?php echo intval($simulatedCount); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Settings Form -->
    <form id="fcrmsim-settings-form" method="post">
        <?php wp_nonce_field('fcrmsim_nonce', 'fcrmsim_nonce_field'); ?>

        <h2>Campaign Settings</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Select Tags</th>
                <td>
                    <fieldset>
                        <?php if ($tags->isEmpty()): ?>
                            <p class="description">No tags found in FluentCRM.</p>
                        <?php else: ?>
                            <?php foreach ($tags as $tag): ?>
                                <label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
                                    <input type="checkbox" name="selected_tag_ids[]" value="<?php echo intval($tag->id); ?>"
                                           <?php checked(in_array($tag->id, $selectedTagIds)); ?>>
                                    <?php echo esc_html($tag->title); ?>
                                    <small style="color: #888;">(<?php echo intval($tag->totalCount()); ?>)</small>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <p class="description">Select tags to target subscribers from. Subscribers with any selected tag will be included.</p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">Select Lists</th>
                <td>
                    <fieldset>
                        <?php if ($lists->isEmpty()): ?>
                            <p class="description">No lists found in FluentCRM.</p>
                        <?php else: ?>
                            <?php foreach ($lists as $list): ?>
                                <label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
                                    <input type="checkbox" name="selected_list_ids[]" value="<?php echo intval($list->id); ?>"
                                           <?php checked(in_array($list->id, $selectedListIds)); ?>>
                                    <?php echo esc_html($list->title); ?>
                                    <small style="color: #888;">(<?php echo intval($list->totalCount()); ?>)</small>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <p class="description">Select lists to target subscribers from. Optional — can use tags only.</p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">Exclude Tags</th>
                <td>
                    <fieldset>
                        <?php if (!$tags->isEmpty()): ?>
                            <?php foreach ($tags as $tag): ?>
                                <label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
                                    <input type="checkbox" name="excluded_tag_ids[]" value="<?php echo intval($tag->id); ?>"
                                           <?php checked(in_array($tag->id, $excludedTagIds)); ?>>
                                    <?php echo esc_html($tag->title); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <p class="description">Subscribers with these tags will be excluded from the campaign.</p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">Exclude Lists</th>
                <td>
                    <fieldset>
                        <?php if (!$lists->isEmpty()): ?>
                            <?php foreach ($lists as $list): ?>
                                <label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
                                    <input type="checkbox" name="excluded_list_ids[]" value="<?php echo intval($list->id); ?>"
                                           <?php checked(in_array($list->id, $excludedListIds)); ?>>
                                    <?php echo esc_html($list->title); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <p class="description">Subscribers in these lists will be excluded from the campaign.</p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">Date Range</th>
                <td>
                    <label>
                        Start: <input type="date" name="date_range_start"
                                      value="<?php echo esc_attr($settings['date_range_start'] ?? ''); ?>"
                                      class="regular-text">
                    </label>
                    &nbsp;&nbsp;
                    <label>
                        End: <input type="date" name="date_range_end"
                                    value="<?php echo esc_attr($settings['date_range_end'] ?? ''); ?>"
                                    class="regular-text">
                    </label>
                    <p class="description">Sent timestamps will be spread across this date range for realistic distribution.</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="campaign_subject">Email Subject</label></th>
                <td>
                    <input type="text" id="campaign_subject" name="campaign_subject"
                           value="<?php echo esc_attr($settings['campaign_subject'] ?? 'Simulated Campaign'); ?>"
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="campaign_body">Email Body</label></th>
                <td>
                    <textarea id="campaign_body" name="campaign_body" rows="5" class="large-text"><?php echo esc_textarea($settings['campaign_body'] ?? ''); ?></textarea>
                    <p class="description">HTML content for the simulated email body.</p>
                </td>
            </tr>
        </table>

        <h2>Click Simulation</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Click Range (per campaign)</th>
                <td>
                    <label>
                        Min: <input type="number" name="click_min"
                                    value="<?php echo intval($settings['click_min'] ?? 100); ?>"
                                    min="1" max="10000" class="small-text">
                    </label>
                    &nbsp;&nbsp;
                    <label>
                        Max: <input type="number" name="click_max"
                                    value="<?php echo intval($settings['click_max'] ?? 200); ?>"
                                    min="1" max="10000" class="small-text">
                    </label>
                    <p class="description">Random number of emails between min-max will be selected for engagement simulation.</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="open_rate_percent">Open-Only Rate (%)</label></th>
                <td>
                    <input type="number" id="open_rate_percent" name="open_rate_percent"
                           value="<?php echo intval($settings['open_rate_percent'] ?? 70); ?>"
                           min="0" max="100" class="small-text">
                    <p class="description">Percentage of selected emails that are opened but NOT clicked. The rest will get both opens and clicks.</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="campaigns_per_hour">Campaigns per Hour (auto)</label></th>
                <td>
                    <input type="number" id="campaigns_per_hour" name="campaigns_per_hour"
                           value="<?php echo intval($campaignsPerHour); ?>"
                           min="0" max="100" class="small-text">
                    <p class="description">Set to 0 to disable automatic generation. Campaigns are generated every 5 minutes.</p>
                </td>
            </tr>
        </table>

        <p class="submit" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="submit" class="button button-primary" id="fcrmsim-save">Save Settings</button>
            <button type="button" class="button button-secondary" id="fcrmsim-generate" style="background: #2271b1; color: #fff; border-color: #2271b1;">
                Generate Campaign & Simulate
            </button>
            <button type="button" class="button" id="fcrmsim-simulate-clicks">Simulate Clicks Only</button>
            <?php if ($isRunning): ?>
                <button type="button" class="button" id="fcrmsim-stop" style="color: #d63638; border-color: #d63638;">
                    Stop Simulation
                </button>
            <?php endif; ?>
        </p>
    </form>

    <!-- Danger Zone -->
    <hr style="margin: 30px 0 20px;">
    <div class="card" style="max-width: 700px; border-left-color: #d63638;">
        <h2 style="margin-top: 0; color: #d63638;">Danger Zone</h2>
        <p>This will permanently delete all simulated campaigns, their emails, and tracking data. Real campaigns are never affected.</p>
        <p>
            <button type="button" class="button" id="fcrmsim-purge" style="color: #d63638; border-color: #d63638;">
                Purge All Simulated Data (<span id="fcrmsim-purge-count"><?php echo intval($simulatedCount); ?></span> campaigns)
            </button>
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = $('#fcrmsim_nonce_field').val();
    var $feedback = $('<div class="notice inline" style="margin: 10px 0; display: none;"><p></p></div>');
    $('#fcrmsim-settings-form .submit').before($feedback);

    function showFeedback(message, type) {
        $feedback
            .removeClass('notice-success notice-error notice-info')
            .addClass('notice-' + (type || 'success'))
            .find('p').text(message).end()
            .slideDown(200);
        setTimeout(function() { $feedback.slideUp(200); }, 8000);
    }

    function setButtonLoading($btn, loading) {
        if (loading) {
            $btn.prop('disabled', true).data('orig-text', $btn.text()).text('Processing...');
        } else {
            $btn.prop('disabled', false).text($btn.data('orig-text'));
        }
    }

    // Save Settings
    $('#fcrmsim-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#fcrmsim-save');
        setButtonLoading($btn, true);

        var formData = $(this).serialize();
        formData += '&action=fcrmsim_save_settings&nonce=' + nonce;

        $.post(ajaxurl, formData, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                showFeedback(response.data.message || 'Error saving settings.', 'error');
            }
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed. Please try again.', 'error');
        });
    });

    // Generate Campaign & Simulate
    $('#fcrmsim-generate').on('click', function() {
        // First save settings, then generate
        var $btn = $(this);
        setButtonLoading($btn, true);

        var formData = $('#fcrmsim-settings-form').serialize();
        formData += '&action=fcrmsim_save_settings&nonce=' + nonce;

        // Save first
        $.post(ajaxurl, formData, function(saveResponse) {
            if (!saveResponse.success) {
                setButtonLoading($btn, false);
                showFeedback(saveResponse.data.message || 'Error saving settings.', 'error');
                return;
            }

            // Then generate
            $.post(ajaxurl, {
                action: 'fcrmsim_generate_campaign',
                nonce: nonce
            }, function(response) {
                setButtonLoading($btn, false);
                if (response.success) {
                    showFeedback(response.data.message, 'success');
                    if (response.data.stats) {
                        $('#fcrmsim-total-campaigns').text(response.data.stats.total_campaigns || 0);
                        $('#fcrmsim-total-clicks').text(response.data.stats.total_clicks || 0);
                        $('#fcrmsim-last-run').text(response.data.stats.last_run || 'Just now');
                    }
                    var currentCount = parseInt($('#fcrmsim-sim-count').text()) || 0;
                    $('#fcrmsim-sim-count').text(currentCount + 1);
                    $('#fcrmsim-purge-count').text(currentCount + 1);
                } else {
                    showFeedback(response.data.message || 'Error generating campaign.', 'error');
                }
            }).fail(function() {
                setButtonLoading($btn, false);
                showFeedback('Request failed. Please try again.', 'error');
            });
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed. Please try again.', 'error');
        });
    });

    // Simulate Clicks Only
    $('#fcrmsim-simulate-clicks').on('click', function() {
        var $btn = $(this);
        setButtonLoading($btn, true);

        $.post(ajaxurl, {
            action: 'fcrmsim_simulate_clicks',
            nonce: nonce,
            target_campaign_id: 0
        }, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                if (response.data.stats) {
                    $('#fcrmsim-total-clicks').text(response.data.stats.total_clicks || 0);
                    $('#fcrmsim-last-run').text(response.data.stats.last_run || 'Just now');
                }
            } else {
                showFeedback(response.data.message || 'Error simulating clicks.', 'error');
            }
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed. Please try again.', 'error');
        });
    });

    // Stop Simulation
    $('#fcrmsim-stop').on('click', function() {
        if (!confirm('Stop the campaign simulation?')) return;
        var $btn = $(this);
        setButtonLoading($btn, true);

        $.post(ajaxurl, {
            action: 'fcrmsim_stop_simulation',
            nonce: nonce
        }, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                setTimeout(function() { location.reload(); }, 1000);
            }
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed.', 'error');
        });
    });

    // Purge Data
    $('#fcrmsim-purge').on('click', function() {
        var count = $('#fcrmsim-purge-count').text();
        if (!confirm('Are you sure you want to permanently delete ' + count + ' simulated campaigns and all related data? This cannot be undone.')) return;
        var $btn = $(this);
        setButtonLoading($btn, true);

        $.post(ajaxurl, {
            action: 'fcrmsim_purge_data',
            nonce: nonce
        }, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                $('#fcrmsim-sim-count').text('0');
                $('#fcrmsim-purge-count').text('0');
                $('#fcrmsim-total-campaigns').text('0');
                $('#fcrmsim-total-clicks').text('0');
            } else {
                showFeedback(response.data.message || 'Error purging data.', 'error');
            }
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed.', 'error');
        });
    });
});
</script>
