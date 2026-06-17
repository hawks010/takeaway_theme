<?php
/** Opening hours — structured weekly summary + today status. Hidden until hours are configured. */

defined('ABSPATH') || exit;

$hours = tt_hours_summary();
if (!$hours) return;

$status = tt_open_status();
$closure = function_exists('ttos_get_opening_hours') ? ttos_get_opening_hours() : array();
$closure_on = ($closure['temporary_closure'] ?? '0') === '1';
$closure_message = (string) ($closure['temporary_closure_message'] ?? '');
?>
<section class="tt-section tt-home-hours">
    <div class="tt-wrap tt-hours-grid">
        <div class="tt-hours-intro">
            <p class="tt-eyebrow"><?php esc_html_e('Opening times', 'takeaway-theme'); ?></p>
            <h2><?php esc_html_e('When to find us', 'takeaway-theme'); ?></h2>
            <?php echo tt_open_status_pill(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php if ($closure_on && $closure_message !== '') : ?>
                <p class="tt-hours-closure"><?php echo esc_html($closure_message); ?></p>
            <?php endif; ?>
        </div>
        <ul class="tt-card tt-hours-list">
            <?php foreach ($hours as $row) : ?>
                <li><span><?php echo esc_html($row['label']); ?></span><strong><?php echo esc_html($row['value']); ?></strong></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
