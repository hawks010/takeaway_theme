<?php

defined('ABSPATH') || exit;

final class TTOS_Client_Intake {
    const INDEX_OPTION          = 'ttos_client_intake_index';
    const SETTINGS_OPTION       = 'ttos_client_intake_settings';
    const STARTUP_PENDING       = 'ttos_client_intake_startup_pending';
    const STARTUP_LAST_REQUEST  = 'ttos_client_intake_last_request';
    const FRESH_TOKEN_TTL       = 15 * MINUTE_IN_SECONDS;
    const MAX_PUBLIC_ATTEMPTS   = 30;

    public static function hooks(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'), 22);
        add_action('admin_init', array(__CLASS__, 'handle_admin_posts'));
        add_action('init', array(__CLASS__, 'handle_public_posts'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_public_form'));
        add_action('admin_post_ttos_client_intake_download', array(__CLASS__, 'download_upload'));
    }

    public static function menu(): void {
        add_submenu_page(
            'takeaway-os',
            'Client Intake',
            'Client Intake',
            'ttos_manage_settings',
            'takeaway-os-client-intake',
            array(__CLASS__, 'page')
        );
    }

    public static function startup_pending(): bool {
        return get_option(self::STARTUP_PENDING, '1') === '1';
    }

    public static function set_startup_pending(bool $pending): void {
        update_option(self::STARTUP_PENDING, $pending ? '1' : '0', false);
    }

    private static function requested_sections(): array {
        return array(
            'business_basics'     => 'Business basics',
            'branding'            => 'Branding',
            'photos_media'        => 'Photos & media',
            'menu_content'        => 'Menu & content',
            'opening_hours'       => 'Opening hours',
            'delivery_collection' => 'Delivery & collection',
            'ordering_payment'    => 'Ordering & payment',
            'social_trust'        => 'Social & trust',
            'policies'            => 'Policies',
        );
    }

    private static function statuses(): array {
        return array(
            'draft'               => 'Draft',
            'sent'                => 'Sent',
            'opened'              => 'Opened',
            'partially_completed' => 'Partially completed',
            'submitted'           => 'Submitted',
            'imported'            => 'Imported',
            'expired'             => 'Expired',
            'revoked'             => 'Revoked',
        );
    }

    private static function default_request_sections(): array {
        return array_keys(self::requested_sections());
    }

    private static function email_defaults(): array {
        return array(
            'subject' => 'Send us your takeaway website details',
            'body'    => "Hi [Client Name],\n\nWe’re getting your new takeaway website ready.\n\nPlease use the secure link below to send us your business details, opening hours, menu, branding, logo, and photos. You do not need to log in.\n\n[Magic Link]\n\nThis link is private and will expire on [Expiry Date].\n\nThanks,\n[Agency Name]",
        );
    }

    private static function settings(): array {
        return wp_parse_args(get_option(self::SETTINGS_OPTION, array()), self::email_defaults());
    }

    private static function option_key(string $id): string {
        return 'ttos_client_intake_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    }

    private static function transient_key(string $id, int $user_id): string {
        return 'ttos_client_intake_token_' . md5($id . '|' . $user_id);
    }

    private static function fresh_token(string $id): string {
        $user_id = get_current_user_id();
        if ($user_id < 1) {
            return '';
        }
        return (string) get_transient(self::transient_key($id, $user_id));
    }

    private static function remember_fresh_token(string $id, string $token): void {
        $user_id = get_current_user_id();
        if ($user_id < 1) {
            return;
        }
        set_transient(self::transient_key($id, $user_id), $token, self::FRESH_TOKEN_TTL);
    }

    private static function clear_fresh_token(string $id): void {
        $user_id = get_current_user_id();
        if ($user_id < 1) {
            return;
        }
        delete_transient(self::transient_key($id, $user_id));
    }

    private static function all_ids(): array {
        $ids = get_option(self::INDEX_OPTION, array());
        return is_array($ids) ? array_values(array_unique(array_filter(array_map('sanitize_text_field', $ids)))) : array();
    }

    private static function save_index(array $ids): void {
        update_option(self::INDEX_OPTION, array_values(array_unique(array_filter(array_map('sanitize_text_field', $ids)))), false);
    }

    private static function get_record(string $id): array {
        $record = get_option(self::option_key($id), array());
        return is_array($record) ? $record : array();
    }

    private static function save_record(array $record): void {
        if (empty($record['id'])) {
            return;
        }
        $record['updated_at'] = current_time('mysql');
        update_option(self::option_key((string) $record['id']), $record, false);
        $ids = self::all_ids();
        if (!in_array($record['id'], $ids, true)) {
            $ids[] = $record['id'];
            self::save_index($ids);
        }
    }

    private static function records(): array {
        $records = array();
        foreach (self::all_ids() as $id) {
            $record = self::get_record($id);
            if ($record) {
                $records[] = $record;
            }
        }
        usort($records, static function(array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });
        return $records;
    }

    private static function add_history(array $record, string $message, string $type = 'note'): array {
        if (empty($record['history']) || !is_array($record['history'])) {
            $record['history'] = array();
        }
        $record['history'][] = array(
            'at'      => current_time('mysql'),
            'by'      => get_current_user_id(),
            'message' => sanitize_text_field($message),
            'type'    => sanitize_key($type),
        );
        return $record;
    }

    private static function create_record(array $args): array {
        $id = wp_generate_uuid4();
        $token = wp_generate_password(40, false, false);
        $expiry_days = max(1, absint($args['expiry_days'] ?? 7));
        $record = array(
            'id'                 => $id,
            'client_name'        => sanitize_text_field((string) ($args['client_name'] ?? '')),
            'business_name'      => sanitize_text_field((string) ($args['business_name'] ?? '')),
            'client_email'       => sanitize_email((string) ($args['client_email'] ?? '')),
            'status'             => 'draft',
            'token_hash'         => wp_hash_password($token),
            'expires_at'         => gmdate('c', time() + ($expiry_days * DAY_IN_SECONDS)),
            'requested_sections' => self::sanitize_requested_sections($args['requested_sections'] ?? self::default_request_sections()),
            'submitted_data'     => array(),
            'uploads'            => array(),
            'created_by'         => get_current_user_id(),
            'created_at'         => current_time('mysql'),
            'updated_at'         => current_time('mysql'),
            'opened_at'          => '',
            'submitted_at'       => '',
            'imported_at'        => '',
            'history'            => array(),
        );
        $record = self::add_history($record, 'Client intake request created.', 'created');
        self::save_record($record);
        self::remember_fresh_token($id, $token);
        return array($record, $token);
    }

    private static function sanitize_requested_sections($raw): array {
        $allowed = array_keys(self::requested_sections());
        $clean = array();
        foreach ((array) $raw as $section) {
            $section = sanitize_key((string) $section);
            if (in_array($section, $allowed, true)) {
                $clean[] = $section;
            }
        }
        return $clean ? array_values(array_unique($clean)) : self::default_request_sections();
    }

    private static function intake_url(string $id, string $token, array $args = array()): string {
        return add_query_arg(
            array_merge(
                array(
                    'ttos_intake' => $id,
                    'token'       => $token,
                ),
                $args
            ),
            home_url('/')
        );
    }

    private static function is_expired(array $record): bool {
        if (empty($record['expires_at'])) {
            return false;
        }
        return strtotime((string) $record['expires_at']) < time();
    }

    private static function effective_status(array $record): string {
        $status = sanitize_key((string) ($record['status'] ?? 'draft'));
        if ($status !== 'revoked' && $status !== 'imported' && self::is_expired($record)) {
            return 'expired';
        }
        return $status;
    }

    private static function status_label(array $record): string {
        $status = self::effective_status($record);
        $labels = self::statuses();
        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private static function send_magic_link_email(array $record, string $token): bool {
        $settings = self::settings();
        $client_name = $record['client_name'] !== '' ? $record['client_name'] : $record['business_name'];
        $link = self::intake_url((string) $record['id'], $token);
        $expiry = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $record['expires_at']));
        $search = array('[Client Name]', '[Business Name]', '[Magic Link]', '[Expiry Date]', '[Agency Name]');
        $replace = array(
            $client_name,
            $record['business_name'],
            $link,
            $expiry,
            get_bloginfo('name'),
        );
        $subject = str_replace($search, $replace, (string) $settings['subject']);
        $body = str_replace($search, $replace, (string) $settings['body']);
        return wp_mail($record['client_email'], $subject, $body);
    }

    public static function handle_admin_posts(): void {
        if (!is_admin() || empty($_POST['ttos_action']) || !current_user_can('ttos_manage_settings')) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['ttos_action']));
        if (!in_array($action, array(
            'client_intake_startup',
            'create_client_intake',
            'save_client_intake_email',
            'revoke_client_intake',
            'regenerate_client_intake',
            'import_client_intake',
            'reset_client_intake_startup',
        ), true)) {
            return;
        }

        check_admin_referer('ttos_' . $action);

        if ($action === 'client_intake_startup') {
            $business_name = sanitize_text_field((string) ($_POST['startup_business_name'] ?? ''));
            $tagline = sanitize_text_field((string) ($_POST['startup_tagline'] ?? ''));
            $client_email = sanitize_email((string) ($_POST['startup_client_email'] ?? ''));
            if ($business_name === '' || $client_email === '' || !is_email($client_email)) {
                self::redirect_notice('startup-invalid', 'takeaway-os-launchpad');
            }

            $business = TTOS_Settings::get('business');
            $business['restaurant_name'] = $business_name;
            $business['tagline'] = $tagline;
            TTOS_Settings::update_section('business', $business);
            TTOS_Settings::sync_business_runtime($business);
            TTOS_Settings::sync_business_to_site_content($business);

            list($record, $token) = self::create_record(array(
                'client_name'        => $business_name,
                'business_name'      => $business_name,
                'client_email'       => $client_email,
                'requested_sections' => self::default_request_sections(),
                'expiry_days'        => 7,
            ));

            $sent = self::send_magic_link_email($record, $token);
            $record['status'] = $sent ? 'sent' : 'draft';
            $record = self::add_history($record, $sent ? 'Magic link sent from developer startup flow.' : 'Developer startup flow created the request, but email sending failed.', $sent ? 'sent' : 'warning');
            self::save_record($record);
            update_option(self::STARTUP_LAST_REQUEST, $record['id'], false);
            self::set_startup_pending(false);
            self::redirect_notice($sent ? 'startup-complete' : 'startup-created-no-email', 'takeaway-os-client-intake', array('view' => $record['id']));
        }

        if ($action === 'create_client_intake') {
            $client_name = sanitize_text_field((string) ($_POST['client_name'] ?? ''));
            $business_name = sanitize_text_field((string) ($_POST['business_name'] ?? ''));
            $client_email = sanitize_email((string) ($_POST['client_email'] ?? ''));
            $expiry_days = max(1, absint($_POST['expiry_days'] ?? 7));
            $send_now = !empty($_POST['send_now']);
            if ($business_name === '' || $client_email === '' || !is_email($client_email)) {
                self::redirect_notice('create-invalid', 'takeaway-os-client-intake');
            }

            list($record, $token) = self::create_record(array(
                'client_name'        => $client_name,
                'business_name'      => $business_name,
                'client_email'       => $client_email,
                'requested_sections' => $_POST['requested_sections'] ?? array(),
                'expiry_days'        => $expiry_days,
            ));

            if ($send_now) {
                $sent = self::send_magic_link_email($record, $token);
                $record['status'] = $sent ? 'sent' : 'draft';
                $record = self::add_history($record, $sent ? 'Magic link emailed to the client.' : 'Request created, but email sending failed.', $sent ? 'sent' : 'warning');
                self::save_record($record);
                self::redirect_notice($sent ? 'request-sent' : 'request-created-no-email', 'takeaway-os-client-intake', array('view' => $record['id']));
            }

            self::redirect_notice('request-created', 'takeaway-os-client-intake', array('view' => $record['id']));
        }

        if ($action === 'save_client_intake_email') {
            update_option(self::SETTINGS_OPTION, array(
                'subject' => sanitize_text_field((string) ($_POST['email_subject'] ?? self::email_defaults()['subject'])),
                'body'    => sanitize_textarea_field((string) ($_POST['email_body'] ?? self::email_defaults()['body'])),
            ), false);
            self::redirect_notice('email-template-saved', 'takeaway-os-client-intake');
        }

        if ($action === 'revoke_client_intake') {
            $record = self::get_record(sanitize_text_field((string) ($_POST['intake_id'] ?? '')));
            if (!$record) {
                self::redirect_notice('request-missing', 'takeaway-os-client-intake');
            }
            $record['status'] = 'revoked';
            $record = self::add_history($record, 'Magic link revoked.', 'revoked');
            self::save_record($record);
            self::clear_fresh_token((string) $record['id']);
            self::redirect_notice('request-revoked', 'takeaway-os-client-intake', array('view' => $record['id']));
        }

        if ($action === 'regenerate_client_intake') {
            $record = self::get_record(sanitize_text_field((string) ($_POST['intake_id'] ?? '')));
            if (!$record) {
                self::redirect_notice('request-missing', 'takeaway-os-client-intake');
            }
            $token = wp_generate_password(40, false, false);
            $record['token_hash'] = wp_hash_password($token);
            $record['status'] = 'sent';
            $record['expires_at'] = gmdate('c', time() + (7 * DAY_IN_SECONDS));
            $record = self::add_history($record, 'Magic link regenerated.', 'sent');
            self::save_record($record);
            self::remember_fresh_token((string) $record['id'], $token);
            self::redirect_notice('request-regenerated', 'takeaway-os-client-intake', array('view' => $record['id']));
        }

        if ($action === 'import_client_intake') {
            $record = self::get_record(sanitize_text_field((string) ($_POST['intake_id'] ?? '')));
            if (!$record) {
                self::redirect_notice('request-missing', 'takeaway-os-client-intake');
            }
            if (empty($record['submitted_data']) || !is_array($record['submitted_data'])) {
                self::redirect_notice('request-empty', 'takeaway-os-client-intake', array('view' => $record['id']));
            }
            self::import_submission($record);
            $record['status'] = 'imported';
            $record['imported_at'] = current_time('mysql');
            $record = self::add_history($record, 'Submitted intake data imported into site settings.', 'imported');
            self::save_record($record);
            self::redirect_notice('request-imported', 'takeaway-os-client-intake', array('view' => $record['id']));
        }

        if ($action === 'reset_client_intake_startup') {
            self::set_startup_pending(true);
            update_option('ttos_do_activation_redirect', '1', false);
            self::redirect_notice('startup-reset', 'takeaway-os-settings');
        }
    }

    private static function redirect_notice(string $notice, string $page, array $extra_args = array()): void {
        $url = add_query_arg(array_merge(array('page' => $page, 'ttos_notice' => $notice), $extra_args), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public static function page(): void {
        $current_view = sanitize_text_field((string) ($_GET['view'] ?? ''));
        $record = $current_view !== '' ? self::get_record($current_view) : array();
        $secondary = array(
            array(
                'label'  => 'Requests',
                'url'    => admin_url('admin.php?page=takeaway-os-client-intake'),
                'active' => $current_view === '',
            ),
            array(
                'label'  => 'New request',
                'url'    => admin_url('admin.php?page=takeaway-os-client-intake#ttos-client-intake-new'),
                'active' => false,
            ),
        );

        TTOS_Admin_Shell::render_start(array(
            'title' => 'Client Intake',
            'subtitle' => 'Generate secure client content forms without sending clients into WordPress. Developers can send a private magic link, review submissions, and import safe structured data back into Takeaway OS.',
            'active' => 'takeaway-os-site-content',
            'secondary_nav' => $secondary,
            'secondary_nav_label' => 'Sections',
            'secondary_nav_aria_label' => 'Client intake sections',
        ));

        self::render_notices();
        self::overview_cards();
        if ($record) {
            self::request_detail($record);
        }
        self::new_request_panel();
        self::email_template_panel();
        self::request_table();
        TTOS_Admin_Shell::render_end();
    }

    private static function render_notices(): void {
        if (empty($_GET['ttos_notice'])) {
            return;
        }
        $notice = sanitize_key((string) $_GET['ttos_notice']);
        $map = array(
            'startup-invalid'          => array('err', 'Add a site name and a valid client email before sending the first content form.'),
            'startup-complete'         => array('ok', 'Developer startup complete. The client content form was sent.'),
            'startup-created-no-email' => array('warn', 'Developer startup saved the request, but the email did not send. Copy the fresh private link below or regenerate it.'),
            'startup-reset'            => array('ok', 'Developer startup has been reset. Open Launchpad to run it again for the next build.'),
            'create-invalid'           => array('err', 'Add a business name and a valid client email before creating the request.'),
            'request-created'          => array('ok', 'Client intake request created.'),
            'request-created-no-email' => array('warn', 'Client intake request created, but the email did not send.'),
            'request-sent'             => array('ok', 'Client intake request created and emailed.'),
            'request-revoked'          => array('ok', 'The private link was revoked.'),
            'request-regenerated'      => array('ok', 'A fresh private link was generated.'),
            'request-imported'         => array('ok', 'Submitted intake data was imported into the site settings.'),
            'request-missing'          => array('err', 'That client intake request could not be found.'),
            'request-empty'            => array('err', 'There is no submitted data to import yet.'),
            'email-template-saved'     => array('ok', 'Magic-link email copy updated.'),
        );
        if (empty($map[$notice])) {
            return;
        }
        $class = $map[$notice][0] === 'err' ? ' ttos-notice-error' : ($map[$notice][0] === 'warn' ? ' ttos-notice-warning' : '');
        echo '<div class="ttos-notice' . esc_attr($class) . '">' . esc_html($map[$notice][1]) . '</div>';
    }

    private static function overview_cards(): void {
        $records = self::records();
        $counts = array_fill_keys(array_keys(self::statuses()), 0);
        foreach ($records as $record) {
            $status = self::effective_status($record);
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }
        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Intake overview</h2><p class="ttos-muted">Track what has been created, sent, opened, submitted and imported.</p></div></div>';
        echo '<div class="ttos-grid ttos-grid-4">';
        echo '<div class="ttos-mini-card"><strong>' . esc_html((string) count($records)) . '</strong><span>Total requests</span></div>';
        echo '<div class="ttos-mini-card"><strong>' . esc_html((string) ($counts['sent'] + $counts['opened'] + $counts['partially_completed'])) . '</strong><span>Currently in progress</span></div>';
        echo '<div class="ttos-mini-card"><strong>' . esc_html((string) $counts['submitted']) . '</strong><span>Submitted and waiting for review</span></div>';
        echo '<div class="ttos-mini-card"><strong>' . esc_html((string) $counts['imported']) . '</strong><span>Imported into the site</span></div>';
        echo '</div></section>';
    }

    private static function request_detail(array $record): void {
        $fresh_token = self::fresh_token((string) $record['id']);
        $fresh_link = $fresh_token !== '' ? self::intake_url((string) $record['id'], $fresh_token) : '';
        $requested = self::requested_sections();
        echo '<section class="ttos-card"><div class="ttos-card-head"><div><h2>Request detail</h2><p class="ttos-muted">Review the client link, submitted data, uploaded files, and what the safe import will update.</p></div><p><a class="ttos-mini" href="' . esc_url(admin_url('admin.php?page=takeaway-os-client-intake')) . '">Back to all requests</a></p></div>';
        echo '<div class="ttos-grid ttos-grid-3">';
        echo '<div class="ttos-mini-card"><strong>' . esc_html($record['business_name'] ?: 'Untitled') . '</strong><span>Business</span></div>';
        echo '<div class="ttos-mini-card"><strong>' . esc_html($record['client_email'] ?: '—') . '</strong><span>Client email</span></div>';
        echo '<div class="ttos-mini-card"><strong>' . esc_html(self::status_label($record)) . '</strong><span>Status</span></div>';
        echo '</div>';

        if ($fresh_link !== '') {
            echo '<div class="ttos-callout"><strong>Fresh private link</strong><p class="ttos-muted">For security, the exact link is only shown right after creation or regeneration. Copy it now if you need it.</p><input type="text" readonly value="' . esc_attr($fresh_link) . '" onclick="this.select()"></div>';
        } else {
            echo '<div class="ttos-callout"><strong>Security note</strong><p class="ttos-muted">This install stores only a hashed token. If you need the exact private URL again, use Regenerate link below.</p></div>';
        }

        echo '<div class="ttos-installer-actions">';
        echo '<form method="post">';
        wp_nonce_field('ttos_regenerate_client_intake');
        echo '<input type="hidden" name="ttos_action" value="regenerate_client_intake"><input type="hidden" name="intake_id" value="' . esc_attr((string) $record['id']) . '"><button class="ttos-button" type="submit">Regenerate link</button></form>';
        echo '<form method="post">';
        wp_nonce_field('ttos_revoke_client_intake');
        echo '<input type="hidden" name="ttos_action" value="revoke_client_intake"><input type="hidden" name="intake_id" value="' . esc_attr((string) $record['id']) . '"><button class="ttos-mini" type="submit">Revoke link</button></form>';
        if (!empty($record['submitted_data'])) {
            echo '<form method="post">';
            wp_nonce_field('ttos_import_client_intake');
            echo '<input type="hidden" name="ttos_action" value="import_client_intake"><input type="hidden" name="intake_id" value="' . esc_attr((string) $record['id']) . '"><button class="ttos-button ttos-button-dark" type="submit">Import submitted data</button></form>';
        }
        echo '</div>';

        echo '<h3>Requested sections</h3><ul class="ttos-list">';
        foreach ((array) ($record['requested_sections'] ?? array()) as $section) {
            if (!isset($requested[$section])) {
                continue;
            }
            echo '<li><span>' . esc_html($requested[$section]) . '</span><strong>Requested</strong></li>';
        }
        echo '</ul>';

        echo '<h3>Uploaded files</h3>';
        self::render_upload_table($record);

        if (!empty($record['submitted_data'])) {
            echo '<h3>Submitted data</h3>';
            echo '<div class="ttos-grid ttos-grid-2">';
            echo '<div class="ttos-card">';
            self::render_value_tree((array) $record['submitted_data']);
            echo '</div>';
            echo '<div class="ttos-card"><h4>Safe import summary</h4><ul class="ttos-list">';
            foreach (self::import_summary_items($record) as $item) {
                echo '<li><span>' . esc_html($item) . '</span><strong>Will update</strong></li>';
            }
            echo '</ul><p class="ttos-muted">Branding, menu structure, delivery rules, and policy notes now import into the relevant Takeaway OS settings. Raw uploads and connector outcomes still stay review-first.</p></div>';
            echo '</div>';
        } else {
            echo '<div class="ttos-callout"><strong>No submission yet</strong><p class="ttos-muted">The client has not submitted the content form yet. Once they do, the structured data and uploaded files will appear here for review.</p></div>';
        }

        if (!empty($record['history']) && is_array($record['history'])) {
            echo '<h3>History</h3><table class="ttos-table"><thead><tr><th>When</th><th>Action</th></tr></thead><tbody>';
            foreach (array_reverse($record['history']) as $entry) {
                echo '<tr><td>' . esc_html((string) ($entry['at'] ?? '')) . '</td><td>' . esc_html((string) ($entry['message'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '</section>';
    }

    private static function new_request_panel(): void {
        echo '<section id="ttos-client-intake-new" class="ttos-card"><h2>Create a client intake request</h2><p class="ttos-muted">Create a private content form for a client. You can send the email now or just create the request and copy the fresh link from the detail screen.</p><form method="post">';
        wp_nonce_field('ttos_create_client_intake');
        echo '<input type="hidden" name="ttos_action" value="create_client_intake">';
        echo '<div class="ttos-grid ttos-grid-4">';
        self::admin_field('Client name', 'client_name', '');
        self::admin_field('Business name', 'business_name', (string) TTOS_Settings::get('business', 'restaurant_name'));
        self::admin_field('Client email', 'client_email', '');
        self::admin_field('Link expiry (days)', 'expiry_days', '7', 'number');
        echo '</div><div class="ttos-grid ttos-grid-3">';
        foreach (self::requested_sections() as $key => $label) {
            echo '<label class="ttos-check"><input type="checkbox" name="requested_sections[]" value="' . esc_attr($key) . '" checked> ' . esc_html($label) . '</label>';
        }
        echo '</div>';
        echo '<label class="ttos-check"><input type="checkbox" name="send_now" value="1" checked> Email the secure link immediately</label>';
        echo '<button class="ttos-button">Create client intake request</button></form></section>';
    }

    private static function email_template_panel(): void {
        $settings = self::settings();
        echo '<section class="ttos-card"><h2>Magic-link email copy</h2><p class="ttos-muted">Available tokens: <code>[Client Name]</code>, <code>[Business Name]</code>, <code>[Magic Link]</code>, <code>[Expiry Date]</code>, <code>[Agency Name]</code>.</p><form method="post">';
        wp_nonce_field('ttos_save_client_intake_email');
        echo '<input type="hidden" name="ttos_action" value="save_client_intake_email">';
        self::admin_field('Subject', 'email_subject', (string) $settings['subject']);
        echo '<label>Body<textarea name="email_body" rows="8">' . esc_textarea((string) $settings['body']) . '</textarea></label>';
        echo '<button class="ttos-button">Save email copy</button></form></section>';
    }

    private static function request_table(): void {
        $records = self::records();
        echo '<section class="ttos-card"><h2>All requests</h2><table class="ttos-table"><thead><tr><th>Business</th><th>Email</th><th>Status</th><th>Expiry</th><th>Submitted</th><th>Actions</th></tr></thead><tbody>';
        if (!$records) {
            echo '<tr><td colspan="6"><span class="ttos-muted">No client intake requests yet.</span></td></tr>';
        }
        foreach ($records as $record) {
            $view_url = add_query_arg(array('page' => 'takeaway-os-client-intake', 'view' => $record['id']), admin_url('admin.php'));
            echo '<tr>';
            echo '<td><strong>' . esc_html((string) ($record['business_name'] ?: 'Untitled')) . '</strong></td>';
            echo '<td>' . esc_html((string) ($record['client_email'] ?: '—')) . '</td>';
            echo '<td>' . esc_html(self::status_label($record)) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime((string) ($record['expires_at'] ?? '')))) . '</td>';
            echo '<td>' . esc_html((string) ($record['submitted_at'] ?: '—')) . '</td>';
            echo '<td><a class="ttos-mini" href="' . esc_url($view_url) . '">Open</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></section>';
    }

    public static function render_startup_panel(): void {
        if (!current_user_can('ttos_manage_settings') || !self::startup_pending()) {
            return;
        }
        $business_name = (string) TTOS_Settings::get('business', 'restaurant_name');
        $tagline = (string) TTOS_Settings::get('business', 'tagline');
        echo '<section class="ttos-card ttos-setup-alert"><div class="ttos-installer-head"><div><p class="ttos-eyebrow">Developer startup</p><h2>Send the client content form first</h2><p class="ttos-muted">Before the deeper setup work, enter the project name, tagline, and client email. Takeaway OS will create a secure private intake link and email it so the client can send branding, menu files, hours, and business details without touching WordPress.</p></div></div>';
        echo '<form method="post">';
        wp_nonce_field('ttos_client_intake_startup');
        echo '<input type="hidden" name="ttos_action" value="client_intake_startup">';
        echo '<div class="ttos-grid ttos-grid-3">';
        self::admin_field('Site / business name', 'startup_business_name', $business_name);
        self::admin_field('Tagline', 'startup_tagline', $tagline);
        self::admin_field('Client email', 'startup_client_email', '');
        echo '</div><div class="ttos-installer-actions"><button class="ttos-button">Create and send content form</button><span class="ttos-muted">This step can be reset later from Settings for the next build.</span></div></form></section>';
    }

    public static function render_settings_panel(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $last = sanitize_text_field((string) get_option(self::STARTUP_LAST_REQUEST, ''));
        $last_link = $last !== '' ? add_query_arg(array('page' => 'takeaway-os-client-intake', 'view' => $last), admin_url('admin.php')) : admin_url('admin.php?page=takeaway-os-client-intake');
        echo '<section class="ttos-card"><h2>Developer startup reset</h2><p class="ttos-muted">Use this when you clone the build for a new restaurant and want Launchpad to ask for the new site name, tagline, and client email again before sending the client content form.</p>';
        echo '<ul class="ttos-list">';
        echo '<li><span>Startup currently pending</span><strong>' . (self::startup_pending() ? 'Yes' : 'No') . '</strong></li>';
        echo '<li><span>Last client intake request</span><strong><a href="' . esc_url($last_link) . '">Open latest request</a></strong></li>';
        echo '</ul><form method="post">';
        wp_nonce_field('ttos_reset_client_intake_startup');
        echo '<input type="hidden" name="ttos_action" value="reset_client_intake_startup"><button class="ttos-button">Reset developer startup flow</button></form></section>';
    }

    private static function admin_field(string $label, string $name, $value, string $type = 'text'): void {
        echo '<label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"></label>';
    }

    private static function upload_specs(): array {
        return array(
            'branding_logo'            => array('extensions' => array('jpg', 'jpeg', 'png', 'webp', 'svg'), 'max' => 5 * 1024 * 1024, 'multiple' => false),
            'branding_favicon'         => array('extensions' => array('jpg', 'jpeg', 'png', 'webp', 'svg', 'ico'), 'max' => 2 * 1024 * 1024, 'multiple' => false),
            'branding_guidelines'      => array('extensions' => array('pdf', 'doc', 'docx'), 'max' => 8 * 1024 * 1024, 'multiple' => false),
            'shopfront_photos'         => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
            'food_photos'              => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
            'staff_team_photos'        => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
            'interior_photos'          => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
            'delivery_vehicle_photos'  => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
            'menu_category_photo'     => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
            'menu_subcategory_photo'   => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
            'menu_item_photos'         => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
        );
    }

    private static function validate_upload_mime(array $file, array $allowed_extensions) {
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, $allowed_extensions, true)) {
            return new WP_Error('ttos_intake_upload_type', __('That file type is not allowed for this upload.', 'takeaway-os'));
        }
        if (empty($check['ext'])) {
            return new WP_Error('ttos_intake_upload_mime', __('That upload could not be verified safely. Please use a standard image, PDF, or spreadsheet file.', 'takeaway-os'));
        }
        return true;
    }

    private static function extract_file_item(array $file, int $index = 0): array {
        if (is_array($file['name'])) {
            return array(
                'name'     => (string) ($file['name'][$index] ?? ''),
                'type'     => (string) ($file['type'][$index] ?? ''),
                'tmp_name' => (string) ($file['tmp_name'][$index] ?? ''),
                'error'    => (int) ($file['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($file['size'][$index] ?? 0),
            );
        }
        return $file;
    }

    private static function collect_uploads(array $record): array {
        $uploads = isset($record['uploads']) && is_array($record['uploads']) ? $record['uploads'] : array();
        foreach (self::upload_specs() as $field => $spec) {
            if (empty($_FILES[$field])) {
                continue;
            }
            $file = $_FILES[$field];
            $items = is_array($file['name']) ? array_keys(array_filter((array) $file['name'])) : array(0);
            foreach ($items as $index) {
                $item = self::extract_file_item($file, (int) $index);
                if (empty($item['name']) || (int) $item['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $mime_check = self::validate_upload_mime($item, (array) $spec['extensions']);
                if (is_wp_error($mime_check)) {
                    continue;
                }
                $stored = TTOS_Hardening::stash_uploaded_file($item, (array) $spec['extensions'], (int) $spec['max'], 'client-intake-');
                if (is_wp_error($stored)) {
                    continue;
                }
                if (empty($uploads[$field]) || !is_array($uploads[$field])) {
                    $uploads[$field] = array();
                }
                $uploads[$field][] = array(
                    'stored_name'   => basename((string) $stored['path']),
                    'original_name' => sanitize_file_name((string) $item['name']),
                    'mime'          => sanitize_text_field((string) ($item['type'] ?? '')),
                    'size'          => (int) ($item['size'] ?? 0),
                    'uploaded_at'   => current_time('mysql'),
                );
            }
        }
        return $uploads;
    }

    private static function public_rate_limit_key(string $id): string {
        $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return 'ttos_intake_limit_' . md5($id . '|' . $ip);
    }

    private static function rate_limit_exceeded(string $id): bool {
        $attempts = (int) get_transient(self::public_rate_limit_key($id));
        if ($attempts >= self::MAX_PUBLIC_ATTEMPTS) {
            return true;
        }
        set_transient(self::public_rate_limit_key($id), $attempts + 1, HOUR_IN_SECONDS);
        return false;
    }

    private static function validated_public_record(string $id, string $token, bool $mark_open = false) {
        $record = self::get_record($id);
        if (!$record) {
            return new WP_Error('ttos_intake_missing', __('This secure link is invalid. Please ask us for a fresh link.', 'takeaway-os'));
        }
        $status = self::effective_status($record);
        if ($status === 'revoked') {
            return new WP_Error('ttos_intake_revoked', __('This secure link has been turned off. Please contact us for a new one.', 'takeaway-os'));
        }
        if ($status === 'expired') {
            $record['status'] = 'expired';
            self::save_record($record);
            return new WP_Error('ttos_intake_expired', __('This secure link has expired. Please ask us for a fresh link.', 'takeaway-os'));
        }
        if ($token === '' || empty($record['token_hash']) || !wp_check_password($token, (string) $record['token_hash'])) {
            return new WP_Error('ttos_intake_invalid', __('This secure link is invalid. Please ask us for a fresh link.', 'takeaway-os'));
        }
        if ($mark_open && empty($record['opened_at']) && in_array($status, array('draft', 'sent'), true)) {
            $record['opened_at'] = current_time('mysql');
            $record['status'] = 'opened';
            $record = self::add_history($record, 'Client opened the private intake form.', 'opened');
            self::save_record($record);
        }
        return $record;
    }

    public static function handle_public_posts(): void {
        if (empty($_POST['ttos_client_intake_public_action'])) {
            return;
        }

        $id = sanitize_text_field((string) ($_POST['ttos_intake_id'] ?? ''));
        $token = sanitize_text_field((string) ($_POST['ttos_intake_token'] ?? ''));
        if ($id === '' || self::rate_limit_exceeded($id)) {
            wp_die(esc_html__('Too many attempts. Please wait and try again.', 'takeaway-os'), 429);
        }

        $record = self::validated_public_record($id, $token);
        if (is_wp_error($record)) {
            wp_die(esc_html($record->get_error_message()), 403);
        }
        if (!wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'ttos_public_intake_' . $id)) {
            wp_die(esc_html__('This form session has expired. Please reopen the secure link and try again.', 'takeaway-os'), 403);
        }

        $action = sanitize_key((string) $_POST['ttos_client_intake_public_action']);
        $submitted_data = self::sanitize_public_submission(wp_unslash($_POST['intake'] ?? array()));
        $record['submitted_data'] = array_replace_recursive((array) ($record['submitted_data'] ?? array()), $submitted_data);
        $record['uploads'] = self::collect_uploads($record);

        if ($action === 'save_draft') {
            $record['status'] = 'partially_completed';
            $record = self::add_history($record, 'Client saved progress on the intake form.', 'saved');
            self::save_record($record);
            wp_safe_redirect(self::intake_url($id, $token, array('saved' => '1')));
            exit;
        }

        $errors = self::validate_final_submission($record['submitted_data']);
        if ($errors) {
            set_transient('ttos_public_intake_errors_' . md5($id . '|' . $token), $errors, 2 * MINUTE_IN_SECONDS);
            wp_safe_redirect(self::intake_url($id, $token, array('saved' => '0')));
            exit;
        }

        $record['status'] = 'submitted';
        $record['submitted_at'] = current_time('mysql');
        $record = self::add_history($record, 'Client submitted the intake form.', 'submitted');
        self::save_record($record);
        wp_safe_redirect(self::intake_url($id, $token, array('submitted' => '1')));
        exit;
    }

    private static function sanitize_public_submission($raw): array {
        if (!is_array($raw)) {
            $raw = array();
        }

        $hours = array();
        foreach (array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') as $day) {
            $row = is_array($raw['opening_hours']['days'][$day] ?? null) ? $raw['opening_hours']['days'][$day] : array();
            $hours[$day] = array(
                'closed'            => !empty($row['closed']) ? '1' : '0',
                'open'              => sanitize_text_field((string) ($row['open'] ?? '')),
                'close'             => sanitize_text_field((string) ($row['close'] ?? '')),
                'collection_open'   => sanitize_text_field((string) ($row['collection_open'] ?? '')),
                'collection_close'  => sanitize_text_field((string) ($row['collection_close'] ?? '')),
                'delivery_open'     => sanitize_text_field((string) ($row['delivery_open'] ?? '')),
                'delivery_close'    => sanitize_text_field((string) ($row['delivery_close'] ?? '')),
                'note'              => sanitize_text_field((string) ($row['note'] ?? '')),
            );
        }

        return array(
            'business_basics' => array(
                'takeaway_name'      => sanitize_text_field((string) ($raw['business_basics']['takeaway_name'] ?? '')),
                'legal_name'         => sanitize_text_field((string) ($raw['business_basics']['legal_name'] ?? '')),
                'phone'              => sanitize_text_field((string) ($raw['business_basics']['phone'] ?? '')),
                'email'              => sanitize_email((string) ($raw['business_basics']['email'] ?? '')),
                'website'            => esc_url_raw((string) ($raw['business_basics']['website'] ?? '')),
                'address_1'          => sanitize_text_field((string) ($raw['business_basics']['address_1'] ?? '')),
                'address_2'          => sanitize_text_field((string) ($raw['business_basics']['address_2'] ?? '')),
                'town'               => sanitize_text_field((string) ($raw['business_basics']['town'] ?? '')),
                'postcode'           => sanitize_text_field((string) ($raw['business_basics']['postcode'] ?? '')),
                'google_maps_url'    => esc_url_raw((string) ($raw['business_basics']['google_maps_url'] ?? '')),
                'hygiene_rating'     => sanitize_text_field((string) ($raw['business_basics']['hygiene_rating'] ?? '')),
                'hygiene_url'        => esc_url_raw((string) ($raw['business_basics']['hygiene_url'] ?? '')),
                'vat_number'         => sanitize_text_field((string) ($raw['business_basics']['vat_number'] ?? '')),
                'company_number'     => sanitize_text_field((string) ($raw['business_basics']['company_number'] ?? '')),
            ),
            'branding' => array(
                'primary_color'      => sanitize_hex_color((string) ($raw['branding']['primary_color'] ?? '')) ?: '',
                'accent_color'       => sanitize_hex_color((string) ($raw['branding']['accent_color'] ?? '')) ?: '',
                'background_color'   => sanitize_hex_color((string) ($raw['branding']['background_color'] ?? '')) ?: '',
                'text_color'         => sanitize_hex_color((string) ($raw['branding']['text_color'] ?? '')) ?: '',
                'font_preference'    => sanitize_text_field((string) ($raw['branding']['font_preference'] ?? '')),
                'style_notes'        => sanitize_textarea_field((string) ($raw['branding']['style_notes'] ?? '')),
            ),
            'photos_media' => array(
                'social_media_image_links' => sanitize_textarea_field((string) ($raw['photos_media']['social_media_image_links'] ?? '')),
                'image_rights_confirmed'   => !empty($raw['photos_media']['image_rights_confirmed']) ? '1' : '0',
            ),
            'menu_content' => array(
                'manual_menu_notes'  => sanitize_textarea_field((string) ($raw['menu_content']['manual_menu_notes'] ?? '')),
                'categories'         => sanitize_textarea_field((string) ($raw['menu_content']['categories'] ?? '')),
                'popular_dishes'     => sanitize_textarea_field((string) ($raw['menu_content']['popular_dishes'] ?? '')),
                'allergen_notes'     => sanitize_textarea_field((string) ($raw['menu_content']['allergen_notes'] ?? '')),
                'dietary_options'    => sanitize_textarea_field((string) ($raw['menu_content']['dietary_options'] ?? '')),
                'spice_level_notes'  => sanitize_textarea_field((string) ($raw['menu_content']['spice_level_notes'] ?? '')),
                'modifier_notes'     => sanitize_textarea_field((string) ($raw['menu_content']['modifier_notes'] ?? '')),
                'meal_deals'         => sanitize_textarea_field((string) ($raw['menu_content']['meal_deals'] ?? '')),
            ),
            'opening_hours' => array(
                'days'                   => $hours,
                'special_closing_days'   => sanitize_textarea_field((string) ($raw['opening_hours']['special_closing_days'] ?? '')),
                'bank_holiday_notes'     => sanitize_textarea_field((string) ($raw['opening_hours']['bank_holiday_notes'] ?? '')),
                'preorder_preference'    => !empty($raw['opening_hours']['preorder_preference']) ? '1' : '0',
            ),
            'delivery_collection' => array(
                'collection_enabled'     => !empty($raw['delivery_collection']['collection_enabled']) ? '1' : '0',
                'delivery_enabled'       => !empty($raw['delivery_collection']['delivery_enabled']) ? '1' : '0',
                'delivery_postcodes'     => sanitize_textarea_field((string) ($raw['delivery_collection']['delivery_postcodes'] ?? '')),
                'delivery_fee'           => sanitize_text_field((string) ($raw['delivery_collection']['delivery_fee'] ?? '')),
                'free_delivery_threshold'=> sanitize_text_field((string) ($raw['delivery_collection']['free_delivery_threshold'] ?? '')),
                'minimum_order'          => sanitize_text_field((string) ($raw['delivery_collection']['minimum_order'] ?? '')),
                'prep_time'              => sanitize_text_field((string) ($raw['delivery_collection']['prep_time'] ?? '')),
                'delivery_time'          => sanitize_text_field((string) ($raw['delivery_collection']['delivery_time'] ?? '')),
            ),
            'ordering_payment' => array(
                'cash_collection'        => !empty($raw['ordering_payment']['cash_collection']) ? '1' : '0',
                'cash_delivery'          => !empty($raw['ordering_payment']['cash_delivery']) ? '1' : '0',
                'card_online'            => !empty($raw['ordering_payment']['card_online']) ? '1' : '0',
                'stripe_status'          => sanitize_text_field((string) ($raw['ordering_payment']['stripe_status'] ?? '')),
                'payment_notes'          => sanitize_textarea_field((string) ($raw['ordering_payment']['payment_notes'] ?? '')),
                'order_notification_email'  => sanitize_email((string) ($raw['ordering_payment']['order_notification_email'] ?? '')),
                'kitchen_notification_email'=> sanitize_email((string) ($raw['ordering_payment']['kitchen_notification_email'] ?? '')),
            ),
            'social_trust' => array(
                'facebook'          => esc_url_raw((string) ($raw['social_trust']['facebook'] ?? '')),
                'instagram'         => esc_url_raw((string) ($raw['social_trust']['instagram'] ?? '')),
                'tiktok'            => esc_url_raw((string) ($raw['social_trust']['tiktok'] ?? '')),
                'twitter'           => esc_url_raw((string) ($raw['social_trust']['twitter'] ?? '')),
                'tripadvisor'       => esc_url_raw((string) ($raw['social_trust']['tripadvisor'] ?? '')),
                'google_business'   => esc_url_raw((string) ($raw['social_trust']['google_business'] ?? '')),
                'justeat'           => esc_url_raw((string) ($raw['social_trust']['justeat'] ?? '')),
                'ubereats'          => esc_url_raw((string) ($raw['social_trust']['ubereats'] ?? '')),
                'deliveroo'         => esc_url_raw((string) ($raw['social_trust']['deliveroo'] ?? '')),
                'review_links'      => sanitize_textarea_field((string) ($raw['social_trust']['review_links'] ?? '')),
            ),
            'policies' => array(
                'allergy_disclaimer' => sanitize_textarea_field((string) ($raw['policies']['allergy_disclaimer'] ?? '')),
                'refund_notes'       => sanitize_textarea_field((string) ($raw['policies']['refund_notes'] ?? '')),
                'privacy_contact'    => sanitize_textarea_field((string) ($raw['policies']['privacy_contact'] ?? '')),
                'terms_notes'        => sanitize_textarea_field((string) ($raw['policies']['terms_notes'] ?? '')),
            ),
            'final_confirmation' => array(
                'details_accurate'   => !empty($raw['final_confirmation']['details_accurate']) ? '1' : '0',
                'rights_confirmed'   => !empty($raw['final_confirmation']['rights_confirmed']) ? '1' : '0',
                'website_use_ok'     => !empty($raw['final_confirmation']['website_use_ok']) ? '1' : '0',
            ),
        );
    }

    private static function validate_final_submission(array $data): array {
        $errors = array();
        $business = (array) ($data['business_basics'] ?? array());
        $confirm = (array) ($data['final_confirmation'] ?? array());

        if (empty($business['takeaway_name'])) {
            $errors[] = 'Add the takeaway name before submitting.';
        }
        if (empty($business['phone'])) {
            $errors[] = 'Add the main business phone number before submitting.';
        }
        if (empty($business['email']) || !is_email($business['email'])) {
            $errors[] = 'Add a valid business email address before submitting.';
        }
        if (($confirm['details_accurate'] ?? '0') !== '1') {
            $errors[] = 'Confirm that the submitted details are accurate.';
        }
        if (($confirm['rights_confirmed'] ?? '0') !== '1') {
            $errors[] = 'Confirm that you have the rights to use the uploaded assets.';
        }
        if (($confirm['website_use_ok'] ?? '0') !== '1') {
            $errors[] = 'Confirm that we can use this content on the website.';
        }
        return $errors;
    }

    public static function maybe_render_public_form(): void {
        if (is_admin() || empty($_GET['ttos_intake'])) {
            return;
        }

        // This is a private, per-token page. It must never be page-cached or
        // asset-optimized (JS combine/defer), or the React wizard's scripts get
        // dropped or run out of order. Tell cache plugins to leave it alone.
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        do_action('litespeed_control_set_nocache', 'ttos client intake wizard');
        do_action('litespeed_disable_all', 'ttos client intake wizard');
        add_filter('litespeed_optm_js_defer', '__return_false');
        add_filter('litespeed_optm_js_comb', '__return_false');
        add_filter('litespeed_optm_css_comb', '__return_false');
        add_filter('litespeed_optm_html_min', '__return_false');

        $id = sanitize_text_field((string) $_GET['ttos_intake']);
        $token = sanitize_text_field((string) ($_GET['token'] ?? ''));
        $record = self::validated_public_record($id, $token, true);

        status_header(is_wp_error($record) ? 403 : 200);
        nocache_headers();
        do_action('wp_enqueue_scripts');
        self::render_public_document(is_wp_error($record) ? array() : $record, $token, $record);
        exit;
    }

    private static function public_errors(string $id, string $token): array {
        $key = 'ttos_public_intake_errors_' . md5($id . '|' . $token);
        $errors = get_transient($key);
        if (is_array($errors)) {
            delete_transient($key);
            return $errors;
        }
        return array();
    }

    private static function render_public_document(array $record, string $token, $state): void {
        $is_error = is_wp_error($state);
        $business_name = !$is_error && !empty($record['business_name']) ? $record['business_name'] : get_bloginfo('name');
        $use_wizard = !$is_error && empty($_GET['legacy']) && self::wizard_available();
        ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($business_name . ' intake'); ?></title>
    <?php wp_head(); ?>
    <?php if ($use_wizard) : ?>
        <link rel="stylesheet" href="<?php echo esc_url(self::intake_asset_url('intake.css')); ?>">
    <?php endif; ?>
</head>
<body <?php body_class('ttos-intake-body'); ?>>
<main class="tt-wrap tt-section ttos-intake-shell">
    <section class="tt-card ttos-intake-hero">
        <p class="tt-eyebrow">Client intake</p>
        <h1><?php echo esc_html($business_name); ?></h1>
        <p class="ttos-intake-lead"><?php echo $is_error ? esc_html($state->get_error_message()) : esc_html__('Use this private form to send us your branding, hours, menu notes, uploads, and core business details. You do not need to log in to WordPress.', 'takeaway-os'); ?></p>
        <?php
        if (!$is_error) {
            echo '<div class="ttos-intake-meta">';
            echo '<span><strong>Status:</strong> ' . esc_html(self::status_label($record)) . '</span>';
            echo '<span><strong>Expires:</strong> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) ($record['expires_at'] ?? '')))) . '</span>';
            echo '</div>';
        }
        ?>
    </section>
    <?php
    if ($is_error) {
        echo '<section class="tt-card"><p>If you need a fresh link, please contact the developer or agency handling this build.</p></section>';
    } else {
        if (!$use_wizard) {
            self::render_public_notices($record, $token);
        }
        if (self::effective_status($record) === 'submitted') {
            echo '<section class="tt-card"><h2>Thank you</h2><p>Your details have been submitted and are waiting for review. We will use this information to continue the website build. If we need anything else, we will contact you directly.</p></section>';
        } elseif (self::effective_status($record) === 'imported') {
            echo '<section class="tt-card"><h2>Received and imported</h2><p>Your details have already been reviewed and imported into the site setup. Thank you.</p></section>';
        } elseif ($use_wizard) {
            self::render_public_wizard_shell($record, $token);
        } else {
            self::render_public_form($record, $token);
        }
    }
    ?>
</main>
<?php if ($use_wizard) : ?>
    <script>
        window.ttosIntake = <?php echo wp_json_encode(array(
            'apiBase' => untrailingslashit(rest_url(TTOS_Intake_REST::NAMESPACE)),
            'intakeId' => (string) ($record['id'] ?? ''),
            'token' => $token,
            'fallbackUrl' => self::intake_fallback_url((string) ($record['id'] ?? ''), $token),
            'supportEmail' => 'support@inkfire.co.uk',
        )); ?>;
    </script>
    <script src="<?php echo esc_url(self::intake_asset_url('intake.js')); ?>"></script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html><?php
    }

    private static function render_public_wizard_shell(array $record, string $token): void {
        $fallback_url = self::intake_fallback_url((string) $record['id'], $token);
        echo '<section class="tt-card ttos-intake-callout"><strong>Autosave enabled.</strong> Work through the steps at your own pace. Your progress is saved to this private link as you go.</section>';
        echo '<section class="tt-card"><div id="ttos-intake-app" aria-live="polite"></div>';
        echo '<noscript><p>This onboarding wizard needs JavaScript to run. Use the fallback form instead.</p><p><a class="tt-btn" href="' . esc_url($fallback_url) . '">Open legacy form</a></p></noscript>';
        echo '<p class="ttos-muted" style="margin-top:16px">Need the previous form? <a href="' . esc_url($fallback_url) . '">Open the legacy intake form</a>.</p></section>';
    }

    private static function render_public_notices(array $record, string $token): void {
        if (!empty($_GET['submitted'])) {
            echo '<section class="tt-card"><strong>Thanks.</strong> Your content form was submitted successfully.</section>';
            return;
        }
        if (!empty($_GET['saved'])) {
            echo '<section class="tt-card"><strong>Progress saved.</strong> You can come back to this private link later and continue.</section>';
        }
        $errors = self::public_errors((string) $record['id'], $token);
        if ($errors) {
            echo '<section class="tt-card"><strong>Please fix these before submitting:</strong><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></section>';
        }
    }

    private static function render_public_form(array $record, string $token): void {
        $data = isset($record['submitted_data']) && is_array($record['submitted_data']) ? $record['submitted_data'] : array();
        $requested = array_flip((array) ($record['requested_sections'] ?? self::default_request_sections()));
        echo '<form class="tt-card ttos-intake-form" method="post" enctype="multipart/form-data">';
        wp_nonce_field('ttos_public_intake_' . $record['id']);
        echo '<input type="hidden" name="ttos_client_intake_public_action" value="save_draft">';
        echo '<input type="hidden" name="ttos_intake_id" value="' . esc_attr((string) $record['id']) . '">';
        echo '<input type="hidden" name="ttos_intake_token" value="' . esc_attr($token) . '">';
        echo '<nav class="ttos-intake-nav" aria-label="Form sections">';
        foreach (self::requested_sections() as $key => $label) {
            if (isset($requested[$key])) {
                echo '<a href="#section-' . esc_attr($key) . '">' . esc_html($label) . '</a>';
            }
        }
        echo '<a href="#section-final_confirmation">Final confirmation</a>';
        echo '</nav>';

        if (isset($requested['business_basics'])) {
            echo '<section id="section-business_basics" class="ttos-intake-section"><h2>Business basics</h2><div class="ttos-intake-grid">';
            self::public_field('Takeaway name', 'intake[business_basics][takeaway_name]', $data['business_basics']['takeaway_name'] ?? '');
            self::public_field('Legal / business name', 'intake[business_basics][legal_name]', $data['business_basics']['legal_name'] ?? '');
            self::public_field('Phone number', 'intake[business_basics][phone]', $data['business_basics']['phone'] ?? '', 'tel');
            self::public_field('Email address', 'intake[business_basics][email]', $data['business_basics']['email'] ?? '', 'email');
            self::public_field('Website / domain', 'intake[business_basics][website]', $data['business_basics']['website'] ?? '', 'url');
            self::public_field('Address line 1', 'intake[business_basics][address_1]', $data['business_basics']['address_1'] ?? '');
            self::public_field('Address line 2', 'intake[business_basics][address_2]', $data['business_basics']['address_2'] ?? '');
            self::public_field('Town / city', 'intake[business_basics][town]', $data['business_basics']['town'] ?? '');
            self::public_field('Postcode', 'intake[business_basics][postcode]', $data['business_basics']['postcode'] ?? '');
            self::public_field('Google Maps link', 'intake[business_basics][google_maps_url]', $data['business_basics']['google_maps_url'] ?? '', 'url');
            self::public_field('Hygiene rating value', 'intake[business_basics][hygiene_rating]', $data['business_basics']['hygiene_rating'] ?? '');
            self::public_field('Hygiene rating link', 'intake[business_basics][hygiene_url]', $data['business_basics']['hygiene_url'] ?? '', 'url');
            self::public_field('VAT number', 'intake[business_basics][vat_number]', $data['business_basics']['vat_number'] ?? '');
            self::public_field('Company number', 'intake[business_basics][company_number]', $data['business_basics']['company_number'] ?? '');
            echo '</div></section>';
        }

        if (isset($requested['branding'])) {
            echo '<section id="section-branding" class="ttos-intake-section"><h2>Branding</h2><div class="ttos-intake-grid">';
            self::public_field('Primary colour', 'intake[branding][primary_color]', $data['branding']['primary_color'] ?? '', 'color');
            self::public_field('Accent colour', 'intake[branding][accent_color]', $data['branding']['accent_color'] ?? '', 'color');
            self::public_field('Background colour', 'intake[branding][background_color]', $data['branding']['background_color'] ?? '', 'color');
            self::public_field('Text colour', 'intake[branding][text_color]', $data['branding']['text_color'] ?? '', 'color');
            self::public_field('Font preference', 'intake[branding][font_preference]', $data['branding']['font_preference'] ?? '');
            self::public_textarea('Brand / style notes', 'intake[branding][style_notes]', $data['branding']['style_notes'] ?? '', 4);
            echo '</div>';
            self::public_upload('Logo upload', 'branding_logo', false, 'PNG, JPG, WEBP or SVG up to 5MB.');
            self::public_upload('Favicon upload', 'branding_favicon', false, 'PNG, JPG, WEBP, SVG or ICO up to 2MB.');
            self::public_upload('Brand guidelines upload', 'branding_guidelines', false, 'PDF or Word document up to 8MB.');
            echo '</section>';
        }

        if (isset($requested['photos_media'])) {
            echo '<section id="section-photos_media" class="ttos-intake-section"><h2>Photos and media</h2>';
            self::public_upload('Shopfront photos', 'shopfront_photos', true, 'Upload one or more photos.');
            self::public_upload('Food photos', 'food_photos', true, 'Upload one or more photos.');
            self::public_upload('Staff / team photos', 'staff_team_photos', true, 'Optional.');
            self::public_upload('Interior photos', 'interior_photos', true, 'Optional.');
            self::public_upload('Delivery vehicle photos', 'delivery_vehicle_photos', true, 'Optional.');
            self::public_textarea('Social media image links', 'intake[photos_media][social_media_image_links]', $data['photos_media']['social_media_image_links'] ?? '', 3);
            self::public_check('I confirm that I have the rights to use the uploaded images.', 'intake[photos_media][image_rights_confirmed]', $data['photos_media']['image_rights_confirmed'] ?? '0');
            echo '</section>';
        }

        if (isset($requested['menu_content'])) {
            echo '<section id="section-menu_content" class="ttos-intake-section"><h2>Menu and content</h2>';
            self::public_upload('Menu PDF', 'menu_pdf', false, 'PDF up to 8MB.');
            self::public_upload('Menu images', 'menu_images', true, 'Upload screenshots or photos of the menu.');
            self::public_upload('Menu spreadsheet', 'menu_spreadsheet', false, 'CSV, XLS or XLSX up to 6MB.');
            self::public_textarea('Manual menu notes', 'intake[menu_content][manual_menu_notes]', $data['menu_content']['manual_menu_notes'] ?? '', 4);
            self::public_textarea('Categories', 'intake[menu_content][categories]', $data['menu_content']['categories'] ?? '', 3);
            self::public_textarea('Popular dishes', 'intake[menu_content][popular_dishes]', $data['menu_content']['popular_dishes'] ?? '', 3);
            self::public_textarea('Allergen notes', 'intake[menu_content][allergen_notes]', $data['menu_content']['allergen_notes'] ?? '', 3);
            self::public_textarea('Dietary options', 'intake[menu_content][dietary_options]', $data['menu_content']['dietary_options'] ?? '', 3);
            self::public_textarea('Spice level notes', 'intake[menu_content][spice_level_notes]', $data['menu_content']['spice_level_notes'] ?? '', 3);
            self::public_textarea('Modifiers and extras notes', 'intake[menu_content][modifier_notes]', $data['menu_content']['modifier_notes'] ?? '', 3);
            self::public_textarea('Meal deals and offers', 'intake[menu_content][meal_deals]', $data['menu_content']['meal_deals'] ?? '', 3);
            echo '</section>';
        }

        if (isset($requested['opening_hours'])) {
            echo '<section id="section-opening_hours" class="ttos-intake-section"><h2>Opening hours</h2><div class="ttos-intake-hours">';
            foreach (array('monday','tuesday','wednesday','thursday','friday','saturday','sunday') as $day) {
                $row = $data['opening_hours']['days'][$day] ?? array();
                echo '<div class="ttos-intake-day tt-card"><h3>' . esc_html(ucfirst($day)) . '</h3>';
                self::public_check('Closed', 'intake[opening_hours][days][' . $day . '][closed]', $row['closed'] ?? '0');
                echo '<div class="ttos-intake-grid">';
                self::public_field('Open', 'intake[opening_hours][days][' . $day . '][open]', $row['open'] ?? '', 'time');
                self::public_field('Close', 'intake[opening_hours][days][' . $day . '][close]', $row['close'] ?? '', 'time');
                self::public_field('Collection open', 'intake[opening_hours][days][' . $day . '][collection_open]', $row['collection_open'] ?? '', 'time');
                self::public_field('Collection close', 'intake[opening_hours][days][' . $day . '][collection_close]', $row['collection_close'] ?? '', 'time');
                self::public_field('Delivery open', 'intake[opening_hours][days][' . $day . '][delivery_open]', $row['delivery_open'] ?? '', 'time');
                self::public_field('Delivery close', 'intake[opening_hours][days][' . $day . '][delivery_close]', $row['delivery_close'] ?? '', 'time');
                echo '</div>';
                self::public_field('Day note', 'intake[opening_hours][days][' . $day . '][note]', $row['note'] ?? '');
                echo '</div>';
            }
            echo '</div>';
            self::public_textarea('Special closing days', 'intake[opening_hours][special_closing_days]', $data['opening_hours']['special_closing_days'] ?? '', 3);
            self::public_textarea('Bank holiday notes', 'intake[opening_hours][bank_holiday_notes]', $data['opening_hours']['bank_holiday_notes'] ?? '', 3);
            self::public_check('Allow preorders when closed if possible', 'intake[opening_hours][preorder_preference]', $data['opening_hours']['preorder_preference'] ?? '0');
            echo '</section>';
        }

        if (isset($requested['delivery_collection'])) {
            echo '<section id="section-delivery_collection" class="ttos-intake-section"><h2>Delivery and collection</h2>';
            self::public_check('Collection enabled', 'intake[delivery_collection][collection_enabled]', $data['delivery_collection']['collection_enabled'] ?? '0');
            self::public_check('Delivery enabled', 'intake[delivery_collection][delivery_enabled]', $data['delivery_collection']['delivery_enabled'] ?? '0');
            echo '<div class="ttos-intake-grid">';
            self::public_textarea('Delivery postcode areas', 'intake[delivery_collection][delivery_postcodes]', $data['delivery_collection']['delivery_postcodes'] ?? '', 4);
            self::public_field('Delivery fee', 'intake[delivery_collection][delivery_fee]', $data['delivery_collection']['delivery_fee'] ?? '');
            self::public_field('Free delivery threshold', 'intake[delivery_collection][free_delivery_threshold]', $data['delivery_collection']['free_delivery_threshold'] ?? '');
            self::public_field('Minimum order amount', 'intake[delivery_collection][minimum_order]', $data['delivery_collection']['minimum_order'] ?? '');
            self::public_field('Estimated prep time (mins)', 'intake[delivery_collection][prep_time]', $data['delivery_collection']['prep_time'] ?? '', 'number');
            self::public_field('Estimated delivery time (mins)', 'intake[delivery_collection][delivery_time]', $data['delivery_collection']['delivery_time'] ?? '', 'number');
            echo '</div></section>';
        }

        if (isset($requested['ordering_payment'])) {
            echo '<section id="section-ordering_payment" class="ttos-intake-section"><h2>Ordering and payment preferences</h2>';
            self::public_check('Cash on collection', 'intake[ordering_payment][cash_collection]', $data['ordering_payment']['cash_collection'] ?? '0');
            self::public_check('Cash on delivery', 'intake[ordering_payment][cash_delivery]', $data['ordering_payment']['cash_delivery'] ?? '0');
            self::public_check('Card online', 'intake[ordering_payment][card_online]', $data['ordering_payment']['card_online'] ?? '0');
            echo '<div class="ttos-intake-grid">';
            self::public_field('Stripe status', 'intake[ordering_payment][stripe_status]', $data['ordering_payment']['stripe_status'] ?? '');
            self::public_field('Order notification email', 'intake[ordering_payment][order_notification_email]', $data['ordering_payment']['order_notification_email'] ?? '', 'email');
            self::public_field('Kitchen notification email', 'intake[ordering_payment][kitchen_notification_email]', $data['ordering_payment']['kitchen_notification_email'] ?? '', 'email');
            echo '</div>';
            self::public_textarea('Payment notes', 'intake[ordering_payment][payment_notes]', $data['ordering_payment']['payment_notes'] ?? '', 3);
            echo '</section>';
        }

        if (isset($requested['social_trust'])) {
            echo '<section id="section-social_trust" class="ttos-intake-section"><h2>Social and trust links</h2><div class="ttos-intake-grid">';
            self::public_field('Facebook', 'intake[social_trust][facebook]', $data['social_trust']['facebook'] ?? '', 'url');
            self::public_field('Instagram', 'intake[social_trust][instagram]', $data['social_trust']['instagram'] ?? '', 'url');
            self::public_field('TikTok', 'intake[social_trust][tiktok]', $data['social_trust']['tiktok'] ?? '', 'url');
            self::public_field('X / Twitter', 'intake[social_trust][twitter]', $data['social_trust']['twitter'] ?? '', 'url');
            self::public_field('TripAdvisor', 'intake[social_trust][tripadvisor]', $data['social_trust']['tripadvisor'] ?? '', 'url');
            self::public_field('Google Business Profile', 'intake[social_trust][google_business]', $data['social_trust']['google_business'] ?? '', 'url');
            self::public_field('Just Eat', 'intake[social_trust][justeat]', $data['social_trust']['justeat'] ?? '', 'url');
            self::public_field('Uber Eats', 'intake[social_trust][ubereats]', $data['social_trust']['ubereats'] ?? '', 'url');
            self::public_field('Deliveroo', 'intake[social_trust][deliveroo]', $data['social_trust']['deliveroo'] ?? '', 'url');
            echo '</div>';
            self::public_textarea('Review links and trust notes', 'intake[social_trust][review_links]', $data['social_trust']['review_links'] ?? '', 3);
            echo '</section>';
        }

        if (isset($requested['policies'])) {
            echo '<section id="section-policies" class="ttos-intake-section"><h2>Policies</h2>';
            self::public_textarea('Allergy disclaimer', 'intake[policies][allergy_disclaimer]', $data['policies']['allergy_disclaimer'] ?? '', 4);
            self::public_textarea('Refund / cancellation notes', 'intake[policies][refund_notes]', $data['policies']['refund_notes'] ?? '', 4);
            self::public_textarea('Privacy / contact notes', 'intake[policies][privacy_contact]', $data['policies']['privacy_contact'] ?? '', 4);
            self::public_textarea('Terms notes', 'intake[policies][terms_notes]', $data['policies']['terms_notes'] ?? '', 4);
            echo '</section>';
        }

        echo '<section id="section-final_confirmation" class="ttos-intake-section"><h2>Final confirmation</h2>';
        self::public_check('I confirm that these details are accurate.', 'intake[final_confirmation][details_accurate]', $data['final_confirmation']['details_accurate'] ?? '0');
        self::public_check('I confirm that I have the rights to use the uploaded assets.', 'intake[final_confirmation][rights_confirmed]', $data['final_confirmation']['rights_confirmed'] ?? '0');
        self::public_check('I understand this content will be used on the website build.', 'intake[final_confirmation][website_use_ok]', $data['final_confirmation']['website_use_ok'] ?? '0');
        echo '</section>';

        self::render_existing_uploads($record);
        echo '<div class="ttos-intake-actions"><button class="tt-btn ghost" type="submit" onclick="this.form.ttos_client_intake_public_action.value=\'save_draft\'">Save progress</button><button class="tt-btn" type="submit" onclick="this.form.ttos_client_intake_public_action.value=\'submit_final\'">Submit details</button></div>';
        echo '</form>';
    }

    private static function public_field(string $label, string $name, $value, string $type = 'text'): void {
        echo '<label class="tt-field"><span>' . esc_html($label) . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"></label>';
    }

    private static function public_textarea(string $label, string $name, $value, int $rows = 4): void {
        echo '<label class="tt-field"><span>' . esc_html($label) . '</span><textarea name="' . esc_attr($name) . '" rows="' . absint($rows) . '">' . esc_textarea((string) $value) . '</textarea></label>';
    }

    private static function public_check(string $label, string $name, $value): void {
        echo '<label class="ttos-intake-check"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($value, '1', false) . '> <span>' . esc_html($label) . '</span></label>';
    }

    private static function public_upload(string $label, string $field, bool $multiple, string $help): void {
        echo '<label class="tt-field"><span>' . esc_html($label) . '</span><input type="file" name="' . esc_attr($field) . ($multiple ? '[]' : '') . '" ' . ($multiple ? 'multiple ' : '') . '><small>' . esc_html($help) . '</small></label>';
    }

    private static function render_existing_uploads(array $record): void {
        if (empty($record['uploads']) || !is_array($record['uploads'])) {
            return;
        }
        echo '<section class="ttos-intake-section"><h2>Uploaded so far</h2><ul class="ttos-intake-upload-list">';
        foreach ($record['uploads'] as $field => $items) {
            foreach ((array) $items as $item) {
                echo '<li><strong>' . esc_html(ucwords(str_replace('_', ' ', $field))) . ':</strong> ' . esc_html((string) ($item['original_name'] ?? 'File')) . '</li>';
            }
        }
        echo '</ul></section>';
    }

    private static function render_upload_table(array $record): void {
        echo '<table class="ttos-table"><thead><tr><th>Field</th><th>File</th><th>Type</th><th>Action</th></tr></thead><tbody>';
        $has = false;
        foreach ((array) ($record['uploads'] ?? array()) as $field => $items) {
            foreach ((array) $items as $item) {
                $has = true;
                $download_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action'    => 'ttos_client_intake_download',
                            'intake_id' => $record['id'],
                            'file'      => $item['stored_name'],
                        ),
                        admin_url('admin-post.php')
                    ),
                    'ttos_client_intake_download_' . $record['id'] . '_' . $item['stored_name']
                );
                echo '<tr><td>' . esc_html(ucwords(str_replace('_', ' ', $field))) . '</td><td>' . esc_html((string) ($item['original_name'] ?? 'File')) . '</td><td>' . esc_html((string) ($item['mime'] ?? '—')) . '</td><td><a class="ttos-mini" href="' . esc_url($download_url) . '">Download</a></td></tr>';
            }
        }
        if (!$has) {
            echo '<tr><td colspan="4"><span class="ttos-muted">No files uploaded yet.</span></td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function wizard_steps(): array {
        return array(
            array('id' => 'welcome', 'title' => 'Welcome', 'required' => true),
            array('id' => 'business_basics', 'title' => 'Business basics', 'required' => true),
            array('id' => 'palette', 'title' => 'Colours', 'required' => true),
            array('id' => 'layout_style', 'title' => 'Layout style', 'required' => true),
            array('id' => 'typography', 'title' => 'Typography', 'required' => true),
            array('id' => 'logo_photos', 'title' => 'Logo & photos', 'required' => true),
            array('id' => 'brand_summary', 'title' => 'Brand summary', 'required' => true),
            array('id' => 'opening_hours', 'title' => 'Opening hours', 'required' => true),
            array('id' => 'menu_builder', 'title' => 'Menu builder', 'required' => true),
            array('id' => 'delivery_collection', 'title' => 'Delivery & collection', 'required' => true),
            array('id' => 'payments', 'title' => 'Payments', 'required' => true),
            array('id' => 'order_email', 'title' => 'Order emails', 'required' => false),
            array('id' => 'accounting', 'title' => 'Accounting', 'required' => false),
            array('id' => 'newsletter', 'title' => 'Newsletter', 'required' => false),
            array('id' => 'analytics', 'title' => 'Google Analytics', 'required' => false),
            array('id' => 'vat_legal', 'title' => 'VAT & legal', 'required' => true),
            array('id' => 'finish', 'title' => 'Finish', 'required' => true),
        );
    }

    public static function wizard_available(): bool {
        return is_file(self::intake_asset_path('intake.js')) && is_file(self::intake_asset_path('intake.css'));
    }

    private static function intake_asset_path(string $asset): string {
        return TTOS_DIR . 'intake/dist/' . ltrim($asset, '/');
    }

    private static function intake_asset_url(string $asset): string {
        $path = self::intake_asset_path($asset);
        $version = is_file($path) ? (string) filemtime($path) : TTOS_VERSION;
        return add_query_arg('ver', rawurlencode($version), TTOS_URL . 'intake/dist/' . ltrim($asset, '/'));
    }

    private static function intake_fallback_url(string $id, string $token): string {
        return self::intake_url($id, $token, array('legacy' => '1'));
    }

    private static function upload_fields_for_step(): array {
        return array(
            'logo_photos' => array(
                'branding_logo',
                'branding_favicon',
                'branding_guidelines',
                'shopfront_photos',
                'food_photos',
                'staff_team_photos',
                'interior_photos',
                'delivery_vehicle_photos',
            ),
            'menu_builder' => array(
                'menu_pdf',
                'menu_images',
                'menu_spreadsheet',
            ),
        );
    }

    private static function wizard_state(array $record): array {
        $submitted_data = isset($record['submitted_data']) && is_array($record['submitted_data']) ? $record['submitted_data'] : array();
        $meta = isset($submitted_data['wizard_meta']) && is_array($submitted_data['wizard_meta']) ? $submitted_data['wizard_meta'] : array();
        $last_step = sanitize_key((string) ($meta['last_step'] ?? 'welcome'));
        $steps = self::wizard_steps();
        $step_ids = wp_list_pluck($steps, 'id');
        if (!in_array($last_step, $step_ids, true)) {
            $last_step = 'welcome';
        }
        $current_index = array_search($last_step, $step_ids, true);
        if ($current_index === false) {
            $current_index = 0;
        }
        return array(
            'current_step' => $last_step,
            'current_index' => (int) $current_index,
            'steps' => $steps,
            'skips' => isset($meta['skips']) && is_array($meta['skips']) ? $meta['skips'] : array(),
        );
    }

    private static function public_record_payload(array $record): array {
        return array(
            'id' => (string) ($record['id'] ?? ''),
            'business_name' => (string) ($record['business_name'] ?? ''),
            'client_name' => (string) ($record['client_name'] ?? ''),
            'status' => self::effective_status($record),
            'status_label' => self::status_label($record),
            'expires_at' => (string) ($record['expires_at'] ?? ''),
            'submitted_at' => (string) ($record['submitted_at'] ?? ''),
            'requested_sections' => array_values((array) ($record['requested_sections'] ?? array())),
            'submitted_data' => isset($record['submitted_data']) && is_array($record['submitted_data']) ? $record['submitted_data'] : array(),
            'uploads' => isset($record['uploads']) && is_array($record['uploads']) ? $record['uploads'] : array(),
            'wizard' => self::wizard_state($record),
            'connectors' => class_exists('TTOS_OAuth_Connectors') ? TTOS_OAuth_Connectors::status_payload() : array(),
            'support_email' => 'support@inkfire.co.uk',
        );
    }

    public static function rest_load_record(string $id, string $token) {
        $record = self::validated_public_record($id, $token, true);
        if (is_wp_error($record)) {
            $record->add_data(array('status' => 403));
            return $record;
        }
        return self::public_record_payload($record);
    }

    public static function rest_save_step(string $id, string $token, string $step_id, $raw) {
        $record = self::validated_public_record($id, $token, true);
        if (is_wp_error($record)) {
            $record->add_data(array('status' => 403));
            return $record;
        }
        $step_id = sanitize_key($step_id);
        $clean = self::sanitize_wizard_step($step_id, $raw);
        if (is_wp_error($clean)) {
            $clean->add_data(array('status' => 422));
            return $clean;
        }

        $record['submitted_data'] = array_replace_recursive((array) ($record['submitted_data'] ?? array()), $clean);
        $record['submitted_data']['wizard_meta'] = isset($record['submitted_data']['wizard_meta']) && is_array($record['submitted_data']['wizard_meta'])
            ? $record['submitted_data']['wizard_meta']
            : array();
        $record['submitted_data']['wizard_meta']['last_step'] = $step_id;
        $record['submitted_data']['wizard_meta']['updated_at'] = current_time('mysql');

        if (in_array($step_id, array('order_email', 'accounting', 'newsletter'), true) && isset($raw['skip_note'])) {
            if (empty($record['submitted_data']['wizard_meta']['skips']) || !is_array($record['submitted_data']['wizard_meta']['skips'])) {
                $record['submitted_data']['wizard_meta']['skips'] = array();
            }
            $record['submitted_data']['wizard_meta']['skips'][$step_id] = sanitize_text_field((string) $raw['skip_note']);
        }

        if (!in_array(self::effective_status($record), array('submitted', 'imported'), true)) {
            $record['status'] = 'partially_completed';
        }
        $record = self::add_history($record, sprintf('Client saved the %s wizard step.', str_replace('_', ' ', $step_id)), 'saved');
        self::save_record($record);
        return self::public_record_payload($record);
    }

    public static function rest_submit(string $id, string $token, $raw = array()) {
        $record = self::validated_public_record($id, $token, true);
        if (is_wp_error($record)) {
            $record->add_data(array('status' => 403));
            return $record;
        }

        if (is_array($raw) && $raw) {
            $clean = self::sanitize_wizard_step('finish', $raw);
            if (!is_wp_error($clean)) {
                $record['submitted_data'] = array_replace_recursive((array) ($record['submitted_data'] ?? array()), $clean);
            }
        }

        $errors = self::validate_wizard_submission((array) ($record['submitted_data'] ?? array()));
        if ($errors) {
            return new WP_Error(
                'ttos_intake_validation',
                __('Please complete the required steps before submitting the wizard.', 'takeaway-os'),
                array('status' => 422, 'errors' => $errors)
            );
        }

        $record['status'] = 'submitted';
        $record['submitted_at'] = current_time('mysql');
        $record = self::add_history($record, 'Client submitted the onboarding wizard.', 'submitted');
        self::save_record($record);

        return self::public_record_payload($record);
    }

    public static function rest_upload(string $id, string $token, string $field) {
        $record = self::validated_public_record($id, $token, true);
        if (is_wp_error($record)) {
            $record->add_data(array('status' => 403));
            return $record;
        }
        $specs = self::upload_specs();
        if (!isset($specs[$field])) {
            return new WP_Error('ttos_intake_upload_field', __('That upload field is not recognised by this intake form.', 'takeaway-os'), array('status' => 422));
        }
        if (empty($_FILES[$field])) {
            return new WP_Error('ttos_intake_upload_missing', __('No upload was received for that field.', 'takeaway-os'), array('status' => 422));
        }

        $record['uploads'] = self::collect_uploads($record);
        if (!in_array(self::effective_status($record), array('submitted', 'imported'), true)) {
            $record['status'] = 'partially_completed';
        }
        $record = self::add_history($record, sprintf('Client uploaded file(s) for %s.', str_replace('_', ' ', $field)), 'saved');
        self::save_record($record);

        return self::public_record_payload($record);
    }

    public static function rest_start_connect(string $id, string $token, string $provider) {
        $record = self::validated_public_record($id, $token, true);
        if (is_wp_error($record)) {
            $record->add_data(array('status' => 403));
            return $record;
        }
        if (!class_exists('TTOS_OAuth_Connectors')) {
            return new WP_Error('ttos_connector_missing', __('Connector support is not loaded in this build.', 'takeaway-os'), array('status' => 500));
        }

        $start = TTOS_OAuth_Connectors::start($provider);
        if (is_wp_error($start)) {
            $start->add_data(array('status' => 422));
            return $start;
        }

        $record['submitted_data']['connectors'] = isset($record['submitted_data']['connectors']) && is_array($record['submitted_data']['connectors'])
            ? $record['submitted_data']['connectors']
            : array();
        $record['submitted_data']['connectors'][$provider] = array(
            'status' => 'started',
            'label' => (string) ($start['label'] ?? ucfirst($provider)),
            'mode' => (string) ($start['mode'] ?? ''),
            'started_at' => current_time('mysql'),
        );
        $record = self::add_history($record, sprintf('Client opened the %s connection step.', sanitize_text_field((string) ($start['label'] ?? $provider))), 'saved');
        self::save_record($record);

        return array(
            'start' => $start,
            'record' => self::public_record_payload($record),
        );
    }

    private static function sanitize_yes_no_not_sure($value): string {
        $value = sanitize_key((string) $value);
        return in_array($value, array('yes', 'no', 'not_sure'), true) ? $value : '';
    }

    private static function sanitize_hours_step($raw): array {
        $hours = array();
        foreach (array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') as $day) {
            $row = is_array($raw['days'][$day] ?? null) ? $raw['days'][$day] : array();
            $hours[$day] = array(
                'closed'           => !empty($row['closed']) ? '1' : '0',
                'open'             => sanitize_text_field((string) ($row['open'] ?? '')),
                'close'            => sanitize_text_field((string) ($row['close'] ?? '')),
                'collection_open'  => sanitize_text_field((string) ($row['collection_open'] ?? '')),
                'collection_close' => sanitize_text_field((string) ($row['collection_close'] ?? '')),
                'delivery_open'    => sanitize_text_field((string) ($row['delivery_open'] ?? '')),
                'delivery_close'   => sanitize_text_field((string) ($row['delivery_close'] ?? '')),
                'note'             => sanitize_text_field((string) ($row['note'] ?? '')),
            );
        }
        return array(
            'opening_hours' => array(
                'days' => $hours,
                'special_closing_days' => sanitize_textarea_field((string) ($raw['special_closing_days'] ?? '')),
                'bank_holiday_notes' => sanitize_textarea_field((string) ($raw['bank_holiday_notes'] ?? '')),
                'preorder_preference' => !empty($raw['preorder_preference']) ? '1' : '0',
            ),
        );
    }

    private static function sanitize_menu_items($items): array {
        $clean = array();
        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = sanitize_text_field((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $groups = array();
            foreach ((array) ($item['option_groups'] ?? array()) as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $group_name = sanitize_text_field((string) ($group['name'] ?? ''));
                if ($group_name === '') {
                    continue;
                }
                $options = array();
                foreach ((array) ($group['options'] ?? array()) as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    $label = sanitize_text_field((string) ($option['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $options[] = array(
                        'label' => $label,
                        'price' => function_exists('wc_format_decimal') ? wc_format_decimal($option['price'] ?? 0) : sanitize_text_field((string) ($option['price'] ?? '0')),
                        'default' => !empty($option['default']),
                        'sold_out' => !empty($option['sold_out']),
                    );
                }
                $groups[] = array(
                    'name' => $group_name,
                    'type' => ($group['type'] ?? 'multiple') === 'single' ? 'single' : 'multiple',
                    'required' => !empty($group['required']),
                    'min' => max(0, absint($group['min'] ?? 0)),
                    'max' => max(0, absint($group['max'] ?? 1)),
                    'options' => $options,
                );
            }
            $allergens = array_values(array_filter(array_map('sanitize_key', (array) ($item['allergens'] ?? array()))));
            $dietary = array_values(array_filter(array_map('sanitize_key', (array) ($item['dietary'] ?? array()))));
            $clean[] = array(
                'id' => sanitize_key((string) ($item['id'] ?? wp_generate_uuid4())),
                'name' => $name,
                'description' => sanitize_textarea_field((string) ($item['description'] ?? '')),
                'price' => function_exists('wc_format_decimal') ? wc_format_decimal($item['price'] ?? 0) : sanitize_text_field((string) ($item['price'] ?? '0')),
                'allergens' => $allergens,
                'dietary' => $dietary,
                'option_groups' => $groups,
                'kitchen_note' => sanitize_textarea_field((string) ($item['kitchen_note'] ?? '')),
                'image_ref' => sanitize_text_field((string) ($item['image_ref'] ?? '')),
                'image_name' => sanitize_text_field((string) ($item['image_name'] ?? '')),
            );
        }
        return $clean;
    }

    private static function sanitize_menu_categories($categories): array {
        $clean = array();
        foreach ((array) $categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $name = sanitize_text_field((string) ($category['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $subs = array();
            foreach ((array) ($category['sub_categories'] ?? array()) as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $sub_name = sanitize_text_field((string) ($sub['name'] ?? ''));
                if ($sub_name === '') {
                    continue;
                }
                $subs[] = array(
                    'id' => sanitize_key((string) ($sub['id'] ?? wp_generate_uuid4())),
                    'name' => $sub_name,
                    'image_ref' => sanitize_text_field((string) ($sub['image_ref'] ?? '')),
                    'image_name' => sanitize_text_field((string) ($sub['image_name'] ?? '')),
                    'items' => self::sanitize_menu_items($sub['items'] ?? array()),
                );
            }
            $clean[] = array(
                'id' => sanitize_key((string) ($category['id'] ?? wp_generate_uuid4())),
                'name' => $name,
                'image_ref' => sanitize_text_field((string) ($category['image_ref'] ?? '')),
                'image_name' => sanitize_text_field((string) ($category['image_name'] ?? '')),
                'sub_categories' => $subs,
            );
        }
        return $clean;
    }

    private static function sanitise_measurement_id($value): string {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^G-[A-Z0-9]+$/', $value) ? $value : '';
    }

    private static function sanitize_wizard_step(string $step_id, $raw) {
        if (!is_array($raw)) {
            $raw = array();
        }

        switch ($step_id) {
            case 'welcome':
            case 'brand_summary':
                return array('wizard_meta' => array('last_step' => $step_id));

            case 'business_basics':
                return array(
                    'business_basics' => array(
                        'takeaway_name' => sanitize_text_field((string) ($raw['takeaway_name'] ?? '')),
                        'legal_name' => sanitize_text_field((string) ($raw['legal_name'] ?? '')),
                        'tagline' => sanitize_text_field((string) ($raw['tagline'] ?? '')),
                        'phone' => sanitize_text_field((string) ($raw['phone'] ?? '')),
                        'email' => sanitize_email((string) ($raw['email'] ?? '')),
                        'website' => esc_url_raw((string) ($raw['website'] ?? '')),
                        'address_1' => sanitize_text_field((string) ($raw['address_1'] ?? '')),
                        'address_2' => sanitize_text_field((string) ($raw['address_2'] ?? '')),
                        'town' => sanitize_text_field((string) ($raw['town'] ?? '')),
                        'postcode' => sanitize_text_field((string) ($raw['postcode'] ?? '')),
                        'google_maps_url' => esc_url_raw((string) ($raw['google_maps_url'] ?? '')),
                        'hygiene_rating' => sanitize_text_field((string) ($raw['hygiene_rating'] ?? '')),
                        'hygiene_url' => esc_url_raw((string) ($raw['hygiene_url'] ?? '')),
                        'vat_number' => sanitize_text_field((string) ($raw['vat_number'] ?? '')),
                        'company_number' => sanitize_text_field((string) ($raw['company_number'] ?? '')),
                        'cuisine' => sanitize_text_field((string) ($raw['cuisine'] ?? '')),
                    ),
                );

            case 'palette':
                return array(
                    'branding' => array(
                        'primary_color' => sanitize_hex_color((string) ($raw['primary_color'] ?? '')) ?: '',
                        'accent_color' => sanitize_hex_color((string) ($raw['accent_color'] ?? '')) ?: '',
                        'background_color' => sanitize_hex_color((string) ($raw['background_color'] ?? '')) ?: '',
                        'text_color' => sanitize_hex_color((string) ($raw['text_color'] ?? '')) ?: '',
                        'style_notes' => sanitize_textarea_field((string) ($raw['style_notes'] ?? '')),
                    ),
                );

            case 'layout_style':
                return array(
                    'branding' => array(
                        'header_style' => sanitize_key((string) ($raw['header_style'] ?? 'utility_header')),
                        'hero_style' => sanitize_key((string) ($raw['hero_style'] ?? 'editorial_split')),
                        'footer_style' => sanitize_key((string) ($raw['footer_style'] ?? 'trust_led')),
                        'card_style' => sanitize_key((string) ($raw['card_style'] ?? 'soft')),
                    ),
                );

            case 'typography':
                return array(
                    'branding' => array(
                        'font_heading' => preg_replace('/[^a-zA-Z0-9 ,\-\'"]+/', '', (string) ($raw['font_heading'] ?? '')),
                        'font_body' => preg_replace('/[^a-zA-Z0-9 ,\-\'"]+/', '', (string) ($raw['font_body'] ?? '')),
                    ),
                );

            case 'logo_photos':
                return array(
                    'photos_media' => array(
                        'social_media_image_links' => sanitize_textarea_field((string) ($raw['social_media_image_links'] ?? '')),
                        'image_rights_confirmed' => !empty($raw['image_rights_confirmed']) ? '1' : '0',
                    ),
                );

            case 'opening_hours':
                return self::sanitize_hours_step($raw);

            case 'menu_builder':
                $categories = self::sanitize_menu_categories($raw['categories_tree'] ?? array());
                $category_labels = array();
                $item_labels = array();
                foreach ($categories as $category) {
                    $category_labels[] = $category['name'];
                    foreach ((array) $category['sub_categories'] as $sub_category) {
                        foreach ((array) $sub_category['items'] as $item) {
                            $item_labels[] = $item['name'];
                        }
                    }
                }
                return array(
                    'menu_content' => array(
                        'manual_menu_notes' => sanitize_textarea_field((string) ($raw['manual_menu_notes'] ?? '')),
                        'allergen_notes' => sanitize_textarea_field((string) ($raw['allergen_notes'] ?? '')),
                        'modifier_notes' => sanitize_textarea_field((string) ($raw['modifier_notes'] ?? '')),
                        'meal_deals' => sanitize_textarea_field((string) ($raw['meal_deals'] ?? '')),
                        'categories' => implode(', ', $category_labels),
                        'popular_dishes' => implode(', ', array_slice($item_labels, 0, 6)),
                        'categories_tree' => $categories,
                    ),
                );

            case 'delivery_collection':
                return array(
                    'delivery_collection' => array(
                        'collection_enabled' => !empty($raw['collection_enabled']) ? '1' : '0',
                        'delivery_enabled' => !empty($raw['delivery_enabled']) ? '1' : '0',
                        'delivery_postcodes' => sanitize_textarea_field((string) ($raw['delivery_postcodes'] ?? '')),
                        'delivery_fee' => sanitize_text_field((string) ($raw['delivery_fee'] ?? '')),
                        'free_delivery_threshold' => sanitize_text_field((string) ($raw['free_delivery_threshold'] ?? '')),
                        'minimum_order' => sanitize_text_field((string) ($raw['minimum_order'] ?? '')),
                        'prep_time' => sanitize_text_field((string) ($raw['prep_time'] ?? '')),
                        'delivery_time' => sanitize_text_field((string) ($raw['delivery_time'] ?? '')),
                        'delivery_intro' => sanitize_textarea_field((string) ($raw['delivery_intro'] ?? '')),
                        'collection_intro' => sanitize_textarea_field((string) ($raw['collection_intro'] ?? '')),
                    ),
                );

            case 'payments':
                return array(
                    'ordering_payment' => array(
                        'provider' => sanitize_key((string) ($raw['provider'] ?? '')),
                        'existing_gateway' => sanitize_text_field((string) ($raw['existing_gateway'] ?? '')),
                        'cash_collection' => !empty($raw['cash_collection']) ? '1' : '0',
                        'cash_delivery' => !empty($raw['cash_delivery']) ? '1' : '0',
                        'card_online' => !empty($raw['card_online']) ? '1' : '0',
                        'stripe_status' => sanitize_text_field((string) ($raw['stripe_status'] ?? '')),
                        'payment_notes' => sanitize_textarea_field((string) ($raw['payment_notes'] ?? '')),
                    ),
                );

            case 'order_email':
                return array(
                    'order_email' => array(
                        'provider' => sanitize_key((string) ($raw['provider'] ?? '')),
                        'status' => sanitize_text_field((string) ($raw['status'] ?? 'pending')),
                        'note' => sanitize_textarea_field((string) ($raw['note'] ?? '')),
                    ),
                    'ordering_payment' => array(
                        'order_notification_email' => sanitize_email((string) ($raw['order_notification_email'] ?? '')),
                        'kitchen_notification_email' => sanitize_email((string) ($raw['kitchen_notification_email'] ?? '')),
                    ),
                );

            case 'accounting':
                return array(
                    'accounting' => array(
                        'provider' => sanitize_key((string) ($raw['provider'] ?? '')),
                        'status' => sanitize_text_field((string) ($raw['status'] ?? 'pending')),
                        'note' => sanitize_textarea_field((string) ($raw['note'] ?? '')),
                    ),
                );

            case 'newsletter':
                return array(
                    'newsletter' => array(
                        'provider' => sanitize_key((string) ($raw['provider'] ?? '')),
                        'status' => sanitize_text_field((string) ($raw['status'] ?? 'pending')),
                        'note' => sanitize_textarea_field((string) ($raw['note'] ?? '')),
                    ),
                );

            case 'analytics':
                return array(
                    'analytics' => array(
                        'measurement_id' => self::sanitise_measurement_id($raw['measurement_id'] ?? ''),
                    ),
                );

            case 'vat_legal':
                return array(
                    'vat_legal' => array(
                        'vat_registered' => self::sanitize_yes_no_not_sure($raw['vat_registered'] ?? ''),
                    ),
                    'business_basics' => array(
                        'vat_number' => sanitize_text_field((string) ($raw['vat_number'] ?? '')),
                        'company_number' => sanitize_text_field((string) ($raw['company_number'] ?? '')),
                    ),
                    'policies' => array(
                        'allergy_disclaimer' => sanitize_textarea_field((string) ($raw['allergy_disclaimer'] ?? '')),
                        'refund_notes' => sanitize_textarea_field((string) ($raw['refund_notes'] ?? '')),
                        'privacy_contact' => sanitize_textarea_field((string) ($raw['privacy_contact'] ?? '')),
                        'terms_notes' => sanitize_textarea_field((string) ($raw['terms_notes'] ?? '')),
                    ),
                );

            case 'finish':
                return array(
                    'final_confirmation' => array(
                        'details_accurate' => !empty($raw['details_accurate']) ? '1' : '0',
                        'rights_confirmed' => !empty($raw['rights_confirmed']) ? '1' : '0',
                        'website_use_ok' => !empty($raw['website_use_ok']) ? '1' : '0',
                    ),
                );
        }

        return new WP_Error('ttos_intake_step', __('That onboarding step is not recognised in this build.', 'takeaway-os'));
    }

    private static function validate_wizard_submission(array $data): array {
        $errors = self::validate_final_submission($data);
        $branding = (array) ($data['branding'] ?? array());
        $menu = (array) ($data['menu_content'] ?? array());
        $payments = (array) ($data['ordering_payment'] ?? array());
        $vat_legal = (array) ($data['vat_legal'] ?? array());

        if (empty($branding['primary_color']) || empty($branding['accent_color']) || empty($branding['background_color']) || empty($branding['text_color'])) {
            $errors[] = 'Choose the brand colours before submitting.';
        }
        if (empty($branding['header_style']) || empty($branding['hero_style']) || empty($branding['footer_style'])) {
            $errors[] = 'Choose the header, hero, and footer styles before submitting.';
        }
        if (empty($menu['categories_tree']) || !is_array($menu['categories_tree'])) {
            $errors[] = 'Add at least one menu category and item before submitting.';
        }
        if (empty($payments['provider']) && empty($payments['cash_collection']) && empty($payments['cash_delivery']) && empty($payments['card_online'])) {
            $errors[] = 'Choose at least one payment path before submitting.';
        }
        if (empty($vat_legal['vat_registered'])) {
            $errors[] = 'Answer the VAT registration question before submitting.';
        }

        return array_values(array_unique($errors));
    }

    private static function render_value_tree(array $values): void {
        echo '<ul class="ttos-list">';
        foreach ($values as $key => $value) {
            $label = ucwords(str_replace(array('_', '-'), ' ', (string) $key));
            if (is_array($value)) {
                echo '<li><span>' . esc_html($label) . '</span><strong>Section</strong></li>';
                self::render_value_tree($value);
            } else {
                $display = (string) $value === '' ? '—' : (string) $value;
                echo '<li><span>' . esc_html($label) . '</span><strong>' . esc_html($display) . '</strong></li>';
            }
        }
        echo '</ul>';
    }

    private static function import_summary_items(array $record): array {
        $items = array();
        $data = (array) ($record['submitted_data'] ?? array());
        if (!empty($data['business_basics'])) {
            $items[] = 'Business details and WordPress/WooCommerce runtime address data';
        }
        if (!empty($data['branding'])) {
            $items[] = 'Brand Guide settings: colours, layout styles, typography, and key media attachments';
        }
        if (!empty($data['opening_hours'])) {
            $items[] = 'Site Content opening hours and preorder preference';
        }
        if (!empty($data['delivery_collection'])) {
            $items[] = 'Delivery and collection settings plus trading values';
        }
        if (!empty($data['menu_content']['categories_tree'])) {
            $items[] = 'Structured menu categories and products for WooCommerce';
        }
        if (!empty($data['policies']) || !empty($data['vat_legal'])) {
            $items[] = 'Draft legal and policy content seeded from VAT, refund, privacy, and allergen answers';
        }
        if (!empty($data['analytics']['measurement_id'])) {
            $items[] = 'Stored Google Analytics measurement ID for later frontend wiring';
        }
        if (!empty($data['social_trust'])) {
            $items[] = 'Social links and trust URLs used by the public templates';
        }
        return $items;
    }

    private static function import_submission(array $record): void {
        $data = (array) ($record['submitted_data'] ?? array());
        $business_data = (array) ($data['business_basics'] ?? array());
        $branding_data = (array) ($data['branding'] ?? array());
        $hours_data = (array) ($data['opening_hours'] ?? array());
        $delivery_data = (array) ($data['delivery_collection'] ?? array());
        $social_data = (array) ($data['social_trust'] ?? array());
        $analytics_data = (array) ($data['analytics'] ?? array());

        $business = TTOS_Settings::get('business');
        $business['restaurant_name'] = $business_data['takeaway_name'] ?: $business['restaurant_name'];
        $business['tagline'] = !empty($business_data['tagline']) ? $business_data['tagline'] : $business['tagline'];
        $business['phone'] = $business_data['phone'] ?: $business['phone'];
        $business['email'] = $business_data['email'] ?: $business['email'];
        $business['address_1'] = $business_data['address_1'] ?: $business['address_1'];
        $business['address_2'] = $business_data['address_2'] ?: $business['address_2'];
        $business['town'] = $business_data['town'] ?: $business['town'];
        $business['postcode'] = $business_data['postcode'] ?: $business['postcode'];
        $business['vat_number'] = $business_data['vat_number'] ?: $business['vat_number'];
        $business['company_number'] = $business_data['company_number'] ?: ($business['company_number'] ?? '');
        $business['fsa_rating'] = $business_data['hygiene_rating'] ?: $business['fsa_rating'];
        $business['cuisine'] = !empty($business_data['cuisine']) ? $business_data['cuisine'] : ($business['cuisine'] ?? 'Takeaway');
        TTOS_Settings::update_section('business', $business);
        TTOS_Settings::sync_business_runtime($business);
        TTOS_Settings::sync_business_to_site_content($business);

        $branding = TTOS_Settings::get('branding');
        foreach (array(
            'primary_color'    => 'primary',
            'accent_color'     => 'accent',
            'background_color' => 'bg',
            'text_color'       => 'text',
        ) as $from => $to) {
            if (!empty($branding_data[$from])) {
                $branding[$to] = $branding_data[$from];
            }
        }
        foreach (array(
            'font_heading',
            'font_body',
            'header_style',
            'hero_style',
            'footer_style',
            'card_style',
        ) as $field) {
            if (!empty($branding_data[$field])) {
                $branding[$field] = $branding_data[$field];
            }
        }
        $logo_attachment = self::attachment_from_upload($record, 'branding_logo');
        $favicon_attachment = self::attachment_from_upload($record, 'branding_favicon');
        $hero_attachment = self::first_attachment_from_upload_fields($record, array('food_photos', 'shopfront_photos', 'interior_photos'));
        $about_attachment = self::first_attachment_from_upload_fields($record, array('shopfront_photos', 'interior_photos'));
        if ($logo_attachment > 0) {
            $branding['logo_id'] = $logo_attachment;
            set_theme_mod('custom_logo', $logo_attachment);
        }
        if ($favicon_attachment > 0) {
            $branding['favicon_id'] = $favicon_attachment;
            update_option('site_icon', $favicon_attachment, false);
        }
        if ($hero_attachment > 0) {
            $branding['hero_image_id'] = $hero_attachment;
        }
        TTOS_Settings::update_section('branding', $branding);

        $homepage = TTOS_Site_Content::get('homepage');
        if ($hero_attachment > 0) {
            $homepage['hero_image_id'] = $hero_attachment;
        }
        if ($about_attachment > 0) {
            $homepage['about_image_id'] = $about_attachment;
        }
        TTOS_Site_Content::update_section('homepage', $homepage);

        $business_info = TTOS_Site_Content::get('business_info');
        $business_info['business_name'] = $business_data['takeaway_name'] ?: $business_info['business_name'];
        $business_info['trading_name'] = $business_data['legal_name'] ?: $business_info['trading_name'];
        $business_info['phone'] = $business_data['phone'] ?: $business_info['phone'];
        $business_info['email'] = $business_data['email'] ?: $business_info['email'];
        $business_info['address_1'] = $business_data['address_1'] ?: $business_info['address_1'];
        $business_info['address_2'] = $business_data['address_2'] ?: $business_info['address_2'];
        $business_info['town'] = $business_data['town'] ?: $business_info['town'];
        $business_info['postcode'] = $business_data['postcode'] ?: $business_info['postcode'];
        $business_info['company_number'] = $business_data['company_number'] ?: $business_info['company_number'];
        $business_info['vat_number'] = $business_data['vat_number'] ?: $business_info['vat_number'];
        $business_info['hygiene_rating'] = $business_data['hygiene_rating'] ?: $business_info['hygiene_rating'];
        $business_info['hygiene_authority_url'] = $business_data['hygiene_url'] ?: $business_info['hygiene_authority_url'];
        $business_info['google_url'] = $social_data['google_business'] ?: ($business_data['google_maps_url'] ?: $business_info['google_url']);
        $business_info['tripadvisor_url'] = $social_data['tripadvisor'] ?: $business_info['tripadvisor_url'];
        TTOS_Site_Content::update_section('business_info', $business_info);

        if (!empty($hours_data['days']) && is_array($hours_data['days'])) {
            $opening = TTOS_Site_Content::get('opening_times');
            foreach ($hours_data['days'] as $day => $row) {
                if (!isset($opening['days'][$day]) || !is_array($row)) {
                    continue;
                }
                $opening['days'][$day] = wp_parse_args($row, $opening['days'][$day]);
            }
            if (!empty($hours_data['preorder_preference'])) {
                $opening['override'] = 'preorder';
            }
            TTOS_Site_Content::update_section('opening_times', $opening);
        }

        $trading = TTOS_Settings::get('trading');
        $trading['collection_enabled'] = $delivery_data['collection_enabled'] ?? $trading['collection_enabled'];
        $trading['delivery_enabled'] = $delivery_data['delivery_enabled'] ?? $trading['delivery_enabled'];
        $trading['delivery_postcodes'] = $delivery_data['delivery_postcodes'] ?: $trading['delivery_postcodes'];
        $trading['delivery_fee'] = $delivery_data['delivery_fee'] ?: $trading['delivery_fee'];
        $trading['free_delivery_over'] = $delivery_data['free_delivery_threshold'] ?: $trading['free_delivery_over'];
        $trading['min_order'] = $delivery_data['minimum_order'] ?: $trading['min_order'];
        $trading['prep_time'] = $delivery_data['prep_time'] ?: $trading['prep_time'];
        $trading['delivery_time'] = $delivery_data['delivery_time'] ?: $trading['delivery_time'];
        TTOS_Settings::update_section('trading', $trading);

        $delivery_content = TTOS_Site_Content::get('delivery_collection');
        $delivery_content['collection_enabled'] = $delivery_data['collection_enabled'] ?? $delivery_content['collection_enabled'];
        $delivery_content['delivery_enabled'] = $delivery_data['delivery_enabled'] ?? $delivery_content['delivery_enabled'];
        if (!empty($delivery_data['delivery_intro'])) {
            $delivery_content['delivery_intro'] = $delivery_data['delivery_intro'];
        }
        if (!empty($delivery_data['collection_intro'])) {
            $delivery_content['collection_intro'] = $delivery_data['collection_intro'];
        }
        TTOS_Site_Content::update_section('delivery_collection', $delivery_content);

        $social = TTOS_Site_Content::get('social_links');
        foreach (array('facebook', 'instagram', 'tiktok', 'twitter') as $field) {
            if (!empty($social_data[$field])) {
                $social[$field] = $social_data[$field];
            }
        }
        if (!empty($social_data['google_business'])) {
            $social['google'] = $social_data['google_business'];
        }
        if (!empty($social_data['tripadvisor'])) {
            $social['tripadvisor'] = $social_data['tripadvisor'];
        }
        TTOS_Site_Content::update_section('social_links', $social);

        self::import_menu_products($record);
        self::update_policy_drafts($record);

        if (!empty($analytics_data['measurement_id'])) {
            update_option('ttos_ga_measurement_id', self::sanitise_measurement_id($analytics_data['measurement_id']), false);
        }
    }

    private static function first_attachment_from_upload_fields(array $record, array $fields): int {
        foreach ($fields as $field) {
            $attachment_id = self::attachment_from_upload($record, (string) $field);
            if ($attachment_id > 0) {
                return $attachment_id;
            }
        }
        return 0;
    }

    private static function intake_product_id(string $record_id, string $item_key): int {
        $posts = get_posts(array(
            'post_type' => 'product',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(
                array('key' => '_ttos_client_intake_source', 'value' => $record_id),
                array('key' => '_ttos_client_intake_item_key', 'value' => $item_key),
            ),
        ));
        return !empty($posts[0]) ? (int) $posts[0] : 0;
    }

    /**
     * Find-or-create a product_cat term, optionally as a child of $parent_id,
     * and attach a term thumbnail (WooCommerce's own 'thumbnail_id' term meta
     * convention, so category images show up in WooCommerce's native category
     * widgets/admin, not a bespoke meta key).
     */
    private static function ensure_menu_term(string $name, int $parent_id, array $record, string $upload_field, string $image_ref): int {
        if ($name === '') {
            return 0;
        }
        $existing = term_exists($name, 'product_cat', $parent_id ?: 0);
        if ($existing) {
            $term_id = is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
        } else {
            $result = wp_insert_term($name, 'product_cat', array('parent' => $parent_id));
            $term_id = is_wp_error($result) ? 0 : (int) $result['term_id'];
        }
        if ($term_id > 0 && $image_ref !== '') {
            $thumb_id = self::attach_specific_upload($record, $upload_field, $image_ref);
            if ($thumb_id > 0) {
                update_term_meta($term_id, 'thumbnail_id', $thumb_id);
            }
        }
        return $term_id;
    }

    private static function import_menu_products(array $record): void {
        if (!class_exists('TTOS_WooCommerce') || !TTOS_WooCommerce::active()) {
            return;
        }

        $tree = (array) ($record['submitted_data']['menu_content']['categories_tree'] ?? array());
        $sort_order = 0;
        foreach ($tree as $category) {
            $category_name = sanitize_text_field((string) ($category['name'] ?? ''));
            $category_image_ref = sanitize_text_field((string) ($category['image_ref'] ?? ''));
            $parent_term_id = self::ensure_menu_term($category_name, 0, $record, 'menu_category_photo', $category_image_ref);

            foreach ((array) ($category['sub_categories'] ?? array()) as $sub_category) {
                $sub_name = sanitize_text_field((string) ($sub_category['name'] ?? ''));
                $sub_image_ref = sanitize_text_field((string) ($sub_category['image_ref'] ?? ''));
                $term_name = $sub_name !== '' ? $sub_name : $category_name;
                // Only nest as a true child term when the sub-category has its own
                // distinct name; otherwise the parent term itself is the product's term.
                $leaf_term_id = $sub_name !== ''
                    ? self::ensure_menu_term($sub_name, $parent_term_id, $record, 'menu_subcategory_photo', $sub_image_ref)
                    : $parent_term_id;

                foreach ((array) ($sub_category['items'] ?? array()) as $item) {
                    $item_id = sanitize_key((string) ($item['id'] ?? ''));
                    $item_name = sanitize_text_field((string) ($item['name'] ?? ''));
                    if ($item_id === '' || $item_name === '') {
                        continue;
                    }
                    $sort_order++;
                    $product_id = self::intake_product_id((string) $record['id'], $item_id);
                    $product_id = TTOS_WooCommerce::create_or_update_product(array(
                        'product_id' => $product_id,
                        'name' => $item_name,
                        'price' => $item['price'] ?? '0',
                        'description' => $item['description'] ?? '',
                        'allergens' => implode(',', array_map('sanitize_key', (array) ($item['allergens'] ?? array()))),
                        'allergen_slugs' => array_map('sanitize_key', (array) ($item['allergens'] ?? array())),
                        'dietary' => array_map('sanitize_key', (array) ($item['dietary'] ?? array())),
                        'option_groups' => wp_json_encode((array) ($item['option_groups'] ?? array())),
                        'sort_order' => (string) $sort_order,
                    ));
                    if (!$product_id) {
                        continue;
                    }
                    if ($leaf_term_id > 0) {
                        wp_set_object_terms($product_id, array($leaf_term_id), 'product_cat');
                    }
                    $image_ref = sanitize_text_field((string) ($item['image_ref'] ?? ''));
                    if ($image_ref !== '') {
                        $thumb_id = self::attach_specific_upload($record, 'menu_item_photos', $image_ref);
                        if ($thumb_id > 0) {
                            set_post_thumbnail($product_id, $thumb_id);
                        }
                    }
                    update_post_meta($product_id, '_ttos_generated_by', 'client_intake_menu');
                    update_post_meta($product_id, '_ttos_client_intake_source', (string) $record['id']);
                    update_post_meta($product_id, '_ttos_client_intake_item_key', $item_id);
                    update_post_meta($product_id, '_ttos_client_intake_category', $category_name);
                    update_post_meta($product_id, '_ttos_client_intake_sub_category', $term_name);
                }
            }
        }
    }

    private static function append_policy_note_once(string $content, string $heading, string $note): string {
        $note = trim($note);
        if ($note === '') {
            return $content;
        }
        $marker = "\n\n## " . $heading . "\n\n" . $note;
        if (strpos($content, $marker) !== false) {
            return $content;
        }
        return rtrim($content) . $marker;
    }

    private static function menu_allergen_snapshot(array $tree): string {
        $lines = array();
        foreach ($tree as $category) {
            foreach ((array) ($category['sub_categories'] ?? array()) as $sub_category) {
                foreach ((array) ($sub_category['items'] ?? array()) as $item) {
                    $allergens = array_values(array_filter(array_map('sanitize_key', (array) ($item['allergens'] ?? array()))));
                    if (!$allergens) {
                        continue;
                    }
                    $lines[] = sanitize_text_field((string) ($item['name'] ?? 'Item')) . ': ' . implode(', ', $allergens);
                }
            }
        }
        return implode("\n", array_slice($lines, 0, 20));
    }

    private static function update_policy_drafts(array $record): void {
        if (!class_exists('TTOS_Site_Content')) {
            return;
        }

        $data = (array) ($record['submitted_data'] ?? array());
        $policies_data = (array) ($data['policies'] ?? array());
        $vat_legal = (array) ($data['vat_legal'] ?? array());
        $menu_tree = (array) ($data['menu_content']['categories_tree'] ?? array());
        $policies = TTOS_Site_Content::get('policies');

        if (!is_array($policies)) {
            return;
        }

        if (!empty($policies['allergens']['content'])) {
            $policies['allergens']['content'] = self::append_policy_note_once(
                (string) $policies['allergens']['content'],
                'Client allergen note',
                (string) ($policies_data['allergy_disclaimer'] ?? '')
            );
            $snapshot = self::menu_allergen_snapshot($menu_tree);
            $policies['allergens']['content'] = self::append_policy_note_once(
                (string) $policies['allergens']['content'],
                'Current menu allergen snapshot',
                $snapshot
            );
        }

        if (!empty($policies['refunds']['content'])) {
            $policies['refunds']['content'] = self::append_policy_note_once(
                (string) $policies['refunds']['content'],
                'Client refund note',
                (string) ($policies_data['refund_notes'] ?? '')
            );
        }

        if (!empty($policies['privacy']['content'])) {
            $policies['privacy']['content'] = self::append_policy_note_once(
                (string) $policies['privacy']['content'],
                'Client privacy contact note',
                (string) ($policies_data['privacy_contact'] ?? '')
            );
        }

        if (!empty($policies['terms']['content'])) {
            $terms_note = (string) ($policies_data['terms_notes'] ?? '');
            if (!empty($vat_legal['vat_registered'])) {
                $vat_note = $vat_legal['vat_registered'] === 'yes'
                    ? 'The business confirmed it is VAT registered and supplied a VAT number during onboarding.'
                    : ($vat_legal['vat_registered'] === 'no'
                        ? 'The business said it is not currently VAT registered during onboarding.'
                        : 'The business was unsure about VAT registration during onboarding and needs a developer follow-up before launch.');
                $terms_note = trim($terms_note . "\n\n" . $vat_note);
            }
            $policies['terms']['content'] = self::append_policy_note_once(
                (string) $policies['terms']['content'],
                'Client commercial note',
                $terms_note
            );
        }

        TTOS_Site_Content::update_section('policies', $policies);
    }

    private static function attach_specific_upload(array $record, string $field, string $stored_name): int {
        if ($stored_name === '') {
            return 0;
        }
        $match = null;
        foreach ((array) ($record['uploads'][$field] ?? array()) as $entry) {
            if ((string) ($entry['stored_name'] ?? '') === $stored_name) {
                $match = $entry;
                break;
            }
        }
        if (!$match) {
            return 0;
        }
        $file = self::stored_file_path($stored_name);
        if ($file === '' || !is_file($file)) {
            return 0;
        }
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return 0;
        }
        $target_dir = trailingslashit($upload_dir['path']);
        if (!is_dir($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        $filename = wp_unique_filename($target_dir, sanitize_file_name((string) ($match['original_name'] ?? basename($file))));
        $target = $target_dir . $filename;
        if (!copy($file, $target)) {
            return 0;
        }
        $filetype = wp_check_filetype($filename, null);
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $filetype['type'] ?? '',
            'post_title'     => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_status'    => 'inherit',
        ), $target);
        if (!$attachment_id || is_wp_error($attachment_id)) {
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $target);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        return (int) $attachment_id;
    }

    private static function attachment_from_upload(array $record, string $field): int {
        $items = (array) ($record['uploads'][$field] ?? array());
        if (!$items) {
            return 0;
        }
        $item = $items[0];
        $file = self::stored_file_path((string) ($item['stored_name'] ?? ''));
        if ($file === '' || !is_file($file)) {
            return 0;
        }
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return 0;
        }
        $target_dir = trailingslashit($upload_dir['path']);
        if (!is_dir($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        $filename = wp_unique_filename($target_dir, sanitize_file_name((string) ($item['original_name'] ?? basename($file))));
        $target = $target_dir . $filename;
        if (!copy($file, $target)) {
            return 0;
        }
        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $filetype['type'] ?? '',
            'post_title'     => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $attachment_id = wp_insert_attachment($attachment, $target);
        if (!$attachment_id || is_wp_error($attachment_id)) {
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $target);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        return (int) $attachment_id;
    }

    private static function stored_file_path(string $stored_name): string {
        $dir = TTOS_Hardening::import_dir(false);
        if (empty($dir['path']) || $stored_name === '') {
            return '';
        }
        $path = trailingslashit($dir['path']) . basename($stored_name);
        return is_file($path) ? $path : '';
    }

    public static function download_upload(): void {
        if (!current_user_can('ttos_manage_settings')) {
            wp_die('Not allowed.');
        }
        $intake_id = sanitize_text_field((string) ($_GET['intake_id'] ?? ''));
        $stored_name = sanitize_file_name((string) ($_GET['file'] ?? ''));
        check_admin_referer('ttos_client_intake_download_' . $intake_id . '_' . $stored_name);
        $record = self::get_record($intake_id);
        if (!$record) {
            wp_die('Missing request.');
        }
        $original_name = $stored_name;
        foreach ((array) ($record['uploads'] ?? array()) as $items) {
            foreach ((array) $items as $item) {
                if (($item['stored_name'] ?? '') === $stored_name) {
                    $original_name = sanitize_file_name((string) ($item['original_name'] ?? $stored_name));
                }
            }
        }
        $path = self::stored_file_path($stored_name);
        if ($path === '') {
            wp_die('Missing file.');
        }
        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $original_name . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }
}
