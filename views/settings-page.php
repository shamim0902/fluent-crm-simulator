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

<style>
    .fcrmsim-wrap { max-width: 820px; }
    .fcrmsim-wrap h1 { font-size: 22px; font-weight: 600; margin-bottom: 4px; }
    .fcrmsim-wrap .fcrmsim-subtitle { color: #646970; margin: 0 0 20px; }
    .fcrmsim-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
    .fcrmsim-card {
        background: #fff; border: 1px solid #dcdcde; border-radius: 4px;
        padding: 16px 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .fcrmsim-card h3 { margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #1d2327; }
    .fcrmsim-card-full { grid-column: 1 / -1; }
    .fcrmsim-stat-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f1; }
    .fcrmsim-stat-row:last-child { border-bottom: 0; }
    .fcrmsim-stat-label { color: #50575e; font-size: 13px; }
    .fcrmsim-stat-value { font-weight: 600; font-size: 13px; color: #1d2327; }
    .fcrmsim-pill-group { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
    .fcrmsim-pill {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 10px; border-radius: 20px; font-size: 12px;
        background: #f0f0f1; border: 1px solid #dcdcde; cursor: pointer;
        transition: all .15s ease; user-select: none;
    }
    .fcrmsim-pill:hover { border-color: #2271b1; background: #f0f6fc; }
    .fcrmsim-pill input[type="checkbox"] { display: none; }
    .fcrmsim-pill.checked { background: #2271b1; color: #fff; border-color: #2271b1; }
    .fcrmsim-pill.checked-exclude { background: #d63638; color: #fff; border-color: #d63638; }
    .fcrmsim-pill .fcrmsim-count { opacity: .7; font-size: 11px; }
    .fcrmsim-field-row { display: flex; gap: 12px; align-items: center; margin-bottom: 10px; }
    .fcrmsim-field-row:last-child { margin-bottom: 0; }
    .fcrmsim-field-row label { font-size: 13px; color: #50575e; white-space: nowrap; }
    .fcrmsim-field-row input[type="number"],
    .fcrmsim-field-row input[type="date"],
    .fcrmsim-field-row input[type="text"] {
        padding: 4px 8px; border: 1px solid #8c8f94; border-radius: 3px; font-size: 13px;
    }
    .fcrmsim-field-row input[type="number"] { width: 70px; }
    .fcrmsim-field-row input[type="date"] { width: 150px; }
    .fcrmsim-field-row input[type="text"] { width: 100%; }
    .fcrmsim-hint { color: #8c8f94; font-size: 12px; margin: 4px 0 0; }
    .fcrmsim-actions { display: flex; gap: 8px; flex-wrap: wrap; margin: 20px 0; }
    .fcrmsim-btn-primary {
        background: #2271b1; color: #fff; border: 1px solid #2271b1; border-radius: 3px;
        padding: 6px 16px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background .15s;
    }
    .fcrmsim-btn-primary:hover { background: #135e96; }
    .fcrmsim-btn-primary:disabled { opacity: .6; cursor: not-allowed; }
    .fcrmsim-btn-secondary {
        background: #f6f7f7; color: #2271b1; border: 1px solid #2271b1; border-radius: 3px;
        padding: 6px 16px; font-size: 13px; cursor: pointer; transition: all .15s;
    }
    .fcrmsim-btn-secondary:hover { background: #f0f6fc; }
    .fcrmsim-btn-secondary:disabled { opacity: .6; cursor: not-allowed; }
    .fcrmsim-btn-danger {
        background: #fff; color: #d63638; border: 1px solid #d63638; border-radius: 3px;
        padding: 6px 16px; font-size: 13px; cursor: pointer; transition: all .15s;
    }
    .fcrmsim-btn-danger:hover { background: #fcf0f1; }
    .fcrmsim-btn-danger:disabled { opacity: .6; cursor: not-allowed; }
    .fcrmsim-danger-card { border-color: #d63638; border-left-width: 4px; }
    .fcrmsim-danger-card h3 { color: #d63638; }
    .fcrmsim-section-label { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #8c8f94; margin-bottom: 6px; font-weight: 600; }
    .fcrmsim-textarea {
        width: 100%; padding: 8px 10px; border: 1px solid #8c8f94; border-radius: 3px;
        font-size: 13px; font-family: inherit; resize: vertical; min-height: 60px;
    }
    .fcrmsim-running-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: #fcf0f1; color: #d63638; padding: 3px 10px; border-radius: 12px;
        font-size: 12px; font-weight: 600;
    }
    .fcrmsim-running-badge::before {
        content: ''; width: 6px; height: 6px; background: #d63638;
        border-radius: 50%; animation: fcrmsim-pulse 1.5s infinite;
    }
    @keyframes fcrmsim-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }
</style>

<div class="wrap fcrmsim-wrap">
    <h1>FluentCRM Simulator</h1>
    <p class="fcrmsim-subtitle">Generate test campaigns and simulate email engagement.</p>

    <?php if ($isRunning): ?>
        <div class="notice notice-warning inline" style="margin: 0 0 16px; padding: 8px 12px;">
            <p style="margin:0; display: flex; align-items: center; gap: 8px;">
                <span class="fcrmsim-running-badge">ACTIVE</span>
                Generating ~<?php echo intval($campaignsPerHour); ?> campaigns/hr
            </p>
        </div>
    <?php endif; ?>

    <form id="fcrmsim-settings-form" method="post">
        <?php wp_nonce_field('fcrmsim_nonce', 'fcrmsim_nonce_field'); ?>

        <div class="fcrmsim-grid">

            <!-- Status -->
            <div class="fcrmsim-card">
                <h3>Status</h3>
                <div class="fcrmsim-stat-row">
                    <span class="fcrmsim-stat-label">State</span>
                    <span class="fcrmsim-stat-value">
                        <?php if ($isRunning): ?>
                            <span style="color: #d63638;">Running (<?php echo intval($campaignsPerHour); ?>/hr)</span>
                        <?php else: ?>
                            Stopped
                        <?php endif; ?>
                    </span>
                </div>
                <div class="fcrmsim-stat-row">
                    <span class="fcrmsim-stat-label">Campaigns Created</span>
                    <span class="fcrmsim-stat-value" id="fcrmsim-total-campaigns"><?php echo intval($stats['total_campaigns'] ?? 0); ?></span>
                </div>
                <div class="fcrmsim-stat-row">
                    <span class="fcrmsim-stat-label">Clicks Simulated</span>
                    <span class="fcrmsim-stat-value" id="fcrmsim-total-clicks"><?php echo intval($stats['total_clicks'] ?? 0); ?></span>
                </div>
                <div class="fcrmsim-stat-row">
                    <span class="fcrmsim-stat-label">Last Run</span>
                    <span class="fcrmsim-stat-value" id="fcrmsim-last-run"><?php echo esc_html($stats['last_run'] ?? 'Never'); ?></span>
                </div>
                <div class="fcrmsim-stat-row">
                    <span class="fcrmsim-stat-label">In Database</span>
                    <span class="fcrmsim-stat-value" id="fcrmsim-sim-count"><?php echo intval($simulatedCount); ?> campaigns</span>
                </div>
            </div>

            <!-- Click Simulation -->
            <div class="fcrmsim-card">
                <h3>Engagement Settings</h3>
                <div class="fcrmsim-field-row">
                    <label>Click range</label>
                    <input type="number" name="click_min" value="<?php echo intval($settings['click_min'] ?? 100); ?>" min="1" max="10000">
                    <span style="color:#8c8f94;">to</span>
                    <input type="number" name="click_max" value="<?php echo intval($settings['click_max'] ?? 200); ?>" min="1" max="10000">
                </div>
                <p class="fcrmsim-hint">Emails selected for engagement per campaign</p>

                <div class="fcrmsim-field-row" style="margin-top: 12px;">
                    <label>Open-only rate</label>
                    <input type="number" name="open_rate_percent" value="<?php echo intval($settings['open_rate_percent'] ?? 70); ?>" min="0" max="100" style="width:55px;">
                    <span style="color:#8c8f94;">%</span>
                </div>
                <p class="fcrmsim-hint">% opened but not clicked. Rest get both opens + clicks.</p>

                <div class="fcrmsim-field-row" style="margin-top: 12px;">
                    <label>Auto-generate</label>
                    <input type="number" name="campaigns_per_hour" value="<?php echo intval($campaignsPerHour); ?>" min="0" max="100" style="width:55px;">
                    <span style="color:#8c8f94;">/ hour</span>
                </div>
                <p class="fcrmsim-hint">Set 0 to disable. Runs every 5 minutes.</p>
            </div>

            <!-- Include Tags -->
            <div class="fcrmsim-card">
                <h3>Include Tags</h3>
                <?php if ($tags->isEmpty()): ?>
                    <p class="fcrmsim-hint">No tags found in FluentCRM.</p>
                <?php else: ?>
                    <div class="fcrmsim-pill-group">
                        <?php foreach ($tags as $tag): ?>
                            <label class="fcrmsim-pill <?php echo in_array($tag->id, $selectedTagIds) ? 'checked' : ''; ?>">
                                <input type="checkbox" name="selected_tag_ids[]" value="<?php echo intval($tag->id); ?>"
                                       <?php checked(in_array($tag->id, $selectedTagIds)); ?>>
                                <?php echo esc_html($tag->title); ?>
                                <span class="fcrmsim-count"><?php echo intval($tag->totalCount()); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Include Lists -->
            <div class="fcrmsim-card">
                <h3>Include Lists</h3>
                <?php if ($lists->isEmpty()): ?>
                    <p class="fcrmsim-hint">No lists found in FluentCRM.</p>
                <?php else: ?>
                    <div class="fcrmsim-pill-group">
                        <?php foreach ($lists as $list): ?>
                            <label class="fcrmsim-pill <?php echo in_array($list->id, $selectedListIds) ? 'checked' : ''; ?>">
                                <input type="checkbox" name="selected_list_ids[]" value="<?php echo intval($list->id); ?>"
                                       <?php checked(in_array($list->id, $selectedListIds)); ?>>
                                <?php echo esc_html($list->title); ?>
                                <span class="fcrmsim-count"><?php echo intval($list->totalCount()); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Exclude Tags -->
            <div class="fcrmsim-card">
                <h3>Exclude Tags</h3>
                <?php if ($tags->isEmpty()): ?>
                    <p class="fcrmsim-hint">No tags available.</p>
                <?php else: ?>
                    <div class="fcrmsim-pill-group">
                        <?php foreach ($tags as $tag): ?>
                            <label class="fcrmsim-pill <?php echo in_array($tag->id, $excludedTagIds) ? 'checked-exclude' : ''; ?>" data-exclude="1">
                                <input type="checkbox" name="excluded_tag_ids[]" value="<?php echo intval($tag->id); ?>"
                                       <?php checked(in_array($tag->id, $excludedTagIds)); ?>>
                                <?php echo esc_html($tag->title); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Exclude Lists -->
            <div class="fcrmsim-card">
                <h3>Exclude Lists</h3>
                <?php if ($lists->isEmpty()): ?>
                    <p class="fcrmsim-hint">No lists available.</p>
                <?php else: ?>
                    <div class="fcrmsim-pill-group">
                        <?php foreach ($lists as $list): ?>
                            <label class="fcrmsim-pill <?php echo in_array($list->id, $excludedListIds) ? 'checked-exclude' : ''; ?>" data-exclude="1">
                                <input type="checkbox" name="excluded_list_ids[]" value="<?php echo intval($list->id); ?>"
                                       <?php checked(in_array($list->id, $excludedListIds)); ?>>
                                <?php echo esc_html($list->title); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Campaign Content -->
            <div class="fcrmsim-card fcrmsim-card-full">
                <h3>Campaign Content</h3>
                <div class="fcrmsim-field-row">
                    <label>Date range</label>
                    <input type="date" name="date_range_start" value="<?php echo esc_attr($settings['date_range_start'] ?? ''); ?>">
                    <span style="color:#8c8f94;">to</span>
                    <input type="date" name="date_range_end" value="<?php echo esc_attr($settings['date_range_end'] ?? ''); ?>">
                </div>
                <div class="fcrmsim-field-row" style="margin-top: 10px;">
                    <label>Subject</label>
                    <input type="text" name="campaign_subject" value="<?php echo esc_attr($settings['campaign_subject'] ?? 'Simulated Campaign'); ?>">
                </div>
                <div style="margin-top: 10px;">
                    <label style="font-size:13px; color:#50575e; display:block; margin-bottom:4px;">Email Body</label>
                    <textarea name="campaign_body" class="fcrmsim-textarea" rows="3"><?php echo esc_textarea($settings['campaign_body'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="fcrmsim-card fcrmsim-card-full fcrmsim-danger-card">
                <h3>Danger Zone</h3>
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:13px; color:#50575e;">Delete all simulated campaigns, emails, and tracking data. Real data is never affected.</span>
                    <button type="button" class="fcrmsim-btn-danger" id="fcrmsim-purge">
                        Purge <span id="fcrmsim-purge-count"><?php echo intval($simulatedCount); ?></span> campaigns
                    </button>
                </div>
            </div>

        </div><!-- .fcrmsim-grid -->

        <div class="fcrmsim-actions">
            <button type="submit" class="fcrmsim-btn-secondary" id="fcrmsim-save">Save Settings</button>
            <button type="button" class="fcrmsim-btn-primary" id="fcrmsim-generate">Generate Campaign & Simulate</button>
            <button type="button" class="fcrmsim-btn-secondary" id="fcrmsim-simulate-clicks">Simulate Clicks Only</button>
            <?php if ($isRunning): ?>
                <button type="button" class="fcrmsim-btn-danger" id="fcrmsim-stop">Stop Simulation</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = $('#fcrmsim_nonce_field').val();

    // Pill toggle behavior
    $('.fcrmsim-pill').on('click', function(e) {
        e.preventDefault();
        var $pill = $(this);
        var $cb = $pill.find('input[type="checkbox"]');
        var isExclude = $pill.data('exclude');
        var checkedClass = isExclude ? 'checked-exclude' : 'checked';

        $cb.prop('checked', !$cb.prop('checked'));
        $pill.toggleClass(checkedClass, $cb.prop('checked'));
    });

    // Feedback
    var $feedback = $('<div class="notice inline" style="margin: 0 0 12px; display: none; border-radius: 3px;"><p style="margin:6px 0;"></p></div>');
    $('.fcrmsim-actions').before($feedback);

    function showFeedback(message, type) {
        $feedback
            .removeClass('notice-success notice-error notice-info')
            .addClass('notice-' + (type || 'success'))
            .find('p').text(message).end()
            .slideDown(150);
        setTimeout(function() { $feedback.slideUp(150); }, 6000);
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
        var $btn = $(this);
        setButtonLoading($btn, true);

        var formData = $('#fcrmsim-settings-form').serialize();
        formData += '&action=fcrmsim_save_settings&nonce=' + nonce;

        $.post(ajaxurl, formData, function(saveResponse) {
            if (!saveResponse.success) {
                setButtonLoading($btn, false);
                showFeedback(saveResponse.data.message || 'Error saving settings.', 'error');
                return;
            }

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
                    $('#fcrmsim-sim-count').text((currentCount + 1) + ' campaigns');
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
        if (!confirm('Permanently delete ' + count + ' simulated campaigns and all related data? This cannot be undone.')) return;
        var $btn = $(this);
        setButtonLoading($btn, true);

        $.post(ajaxurl, {
            action: 'fcrmsim_purge_data',
            nonce: nonce
        }, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                $('#fcrmsim-sim-count').text('0 campaigns');
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
