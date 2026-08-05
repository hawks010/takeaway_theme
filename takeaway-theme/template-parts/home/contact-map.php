<?php
/** Contact & map — hidden entirely when there is no contact data and no map. */

defined('ABSPATH') || exit;

$address = tt_address_lines();
$phone = tt_phone();
$email = tt_email();

$provider = (string) tt_content('contact_map', 'map_provider', 'osm');
$lat = (string) tt_content('contact_map', 'lat', '');
$lng = (string) tt_content('contact_map', 'lng', '');
$google_maps_url = (string) tt_content('contact_map', 'google_maps_url', '');
$parking = (string) tt_content('contact_map', 'parking_note', '');
$access  = (string) tt_content('contact_map', 'accessibility_note', '');

$has_osm = ($provider === 'osm' && $lat !== '' && $lng !== '');
$has_google = ($provider === 'google' && $google_maps_url !== '');
$fallback_map = '';
if (!$has_osm && !$has_google && $address) {
    $fallback_map = 'https://maps.google.com/maps?q=' . rawurlencode(implode(', ', $address)) . '&z=15&output=embed';
}
$has_map = $has_osm || $has_google || $fallback_map !== '';

if (!$address && $phone === '' && $email === '' && !$has_map) return;

$osm_src = '';
if ($has_osm) {
    $lat_f = (float) $lat; $lng_f = (float) $lng;
    $bbox = sprintf('%F,%F,%F,%F', $lng_f - 0.004, $lat_f - 0.002, $lng_f + 0.004, $lat_f + 0.002);
    $osm_src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat_f . ',' . $lng_f);
}
?>
<section class="tt-section tt-home-contact">
    <div class="tt-wrap tt-contact-grid<?php echo $has_map ? '' : ' no-map'; ?>">
        <div class="tt-contact-card">
            <p class="tt-eyebrow"><?php esc_html_e('Visit or call', 'takeaway-theme'); ?></p>
            <h2><?php echo esc_html((string) tt_content('contact_map', 'title', __('Find us', 'takeaway-theme'))); ?></h2>
            <p class="tt-contact-lede"><?php esc_html_e('Pop in, collect, or get directions before you order.', 'takeaway-theme'); ?></p>
            <?php if ($address) : ?>
                <address class="tt-contact-address"><?php echo esc_html(implode("\n", $address)); ?></address>
            <?php endif; ?>
            <ul class="tt-contact-list">
                <?php if ($phone !== '') : ?><li><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></li><?php endif; ?>
                <?php if ($email !== '') : ?><li><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></li><?php endif; ?>
            </ul>
            <?php if ($parking !== '') : ?><p class="tt-contact-note"><strong><?php esc_html_e('Parking:', 'takeaway-theme'); ?></strong> <?php echo esc_html($parking); ?></p><?php endif; ?>
            <?php if ($access !== '') : ?><p class="tt-contact-note"><strong><?php esc_html_e('Accessibility:', 'takeaway-theme'); ?></strong> <?php echo esc_html($access); ?></p><?php endif; ?>
            <?php if ($google_maps_url !== '' && !$has_google) : ?>
                <p><a class="tt-btn ghost" href="<?php echo esc_url($google_maps_url); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e('Open in Google Maps', 'takeaway-theme'); ?></a></p>
            <?php endif; ?>
        </div>
        <?php if ($has_osm) : ?>
            <div class="tt-contact-map">
                <iframe title="<?php echo esc_attr(sprintf(__('Map showing %s', 'takeaway-theme'), tt_business_name())); ?>" src="<?php echo esc_url($osm_src); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        <?php elseif ($has_google) : ?>
            <div class="tt-contact-map tt-contact-map-link">
                <a class="tt-btn" href="<?php echo esc_url($google_maps_url); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e('Open map & directions', 'takeaway-theme'); ?></a>
            </div>
        <?php elseif ($fallback_map !== '') : ?>
            <div class="tt-contact-map">
                <iframe title="<?php echo esc_attr(sprintf(__('Map showing %s', 'takeaway-theme'), tt_business_name())); ?>" src="<?php echo esc_url($fallback_map); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        <?php endif; ?>
    </div>
</section>
