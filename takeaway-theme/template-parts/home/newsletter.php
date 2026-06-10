<?php
/**
 * Newsletter teaser — only when enabled with content. No provider integration
 * yet: renders an honest mailto CTA using the business email, never a fake form.
 */

defined('ABSPATH') || exit;

if ((string) tt_content('homepage', 'show_newsletter', '0') !== '1') return;

$title = (string) tt_content('homepage', 'newsletter_title', '');
$text  = (string) tt_content('homepage', 'newsletter_text', '');
$email = tt_email();
if (($title === '' && $text === '') || $email === '') return;

$mailto = 'mailto:' . $email . '?subject=' . rawurlencode(__('Add me to the offers list', 'takeaway-theme'));
?>
<section class="tt-section tt-home-newsletter">
    <div class="tt-wrap tt-newsletter-card tt-card">
        <div>
            <p class="tt-eyebrow"><?php esc_html_e('Offers', 'takeaway-theme'); ?></p>
            <h2><?php echo esc_html($title !== '' ? $title : __('Get offers first', 'takeaway-theme')); ?></h2>
            <?php if ($text !== '') : ?><div class="tt-newsletter-text"><?php echo wp_kses_post(wpautop($text)); ?></div><?php endif; ?>
        </div>
        <a class="tt-btn" href="<?php echo esc_url($mailto); ?>"><?php esc_html_e('Get offers by email', 'takeaway-theme'); ?></a>
    </div>
</section>
