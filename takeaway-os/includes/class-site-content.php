<?php

defined('ABSPATH') || exit;

/**
 * Site Content CRM — structured, owner-editable content consumed by the
 * native theme templates. Not a page builder: the layout is protected,
 * the content is editable. Stored in one versioned option separate from
 * ttos_settings so operational settings and site content evolve apart.
 */
final class TTOS_Site_Content {

    const OPTION          = 'ttos_site_content';
    const CONTENT_VERSION = '1.3.0';

    public static function hooks(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'), 21);
        add_action('admin_init', array(__CLASS__, 'handle_posts'));
        add_action('admin_post_ttos_export_site_content', array(__CLASS__, 'export'));
    }

    public static function menu(): void {
        add_submenu_page('takeaway-os', 'Site Content', 'Site Content', 'ttos_manage_settings', 'takeaway-os-site-content', array(__CLASS__, 'page'));
    }

    /* ---------------------------------------------------------------------
     * Data model
     * ------------------------------------------------------------------- */

    public static function defaults(): array {
        $day = array('closed' => '0', 'open' => '', 'close' => '', 'note' => '', 'delivery_open' => '', 'delivery_close' => '', 'collection_open' => '', 'collection_close' => '');
        return array(
            'version'  => self::CONTENT_VERSION,
            'homepage' => array(
                'hero_eyebrow' => '', 'hero_title' => '', 'hero_subtitle' => '',
                'hero_image_id' => 0, 'hero_bg_image_id' => 0,
                'primary_cta_text' => '', 'primary_cta_target' => '',
                'secondary_cta_text' => '', 'secondary_cta_target' => '',
                'show_fulfilment_toggle' => '1', 'show_postcode_checker' => '1',
                'show_open_status' => '1', 'show_trust_strip' => '1',
                'featured_title' => '', 'featured_subtitle' => '',
                'featured_product_ids' => array(), 'featured_category_ids' => array(),
                'why_direct_title' => '', 'why_direct_cards' => array(),
                'about_title' => '', 'about_text' => '', 'about_image_id' => 0,
                'show_booking' => '0', 'booking_title' => '', 'booking_text' => '',
                'booking_cta_text' => '', 'booking_cta_target' => '',
                'show_reviews' => '1', 'show_newsletter' => '0',
                'newsletter_title' => '', 'newsletter_text' => '',
                'bottom_cta_title' => '', 'bottom_cta_text' => '',
                'bottom_cta_button_text' => '', 'bottom_cta_url' => '',
            ),
            'menu_page' => array(
                'eyebrow' => '', 'title' => '', 'subtitle' => '', 'hero_image_id' => 0,
                'show_postcode_checker' => '1', 'show_search' => '1',
                'show_dietary_filters' => '1', 'show_allergen_filters' => '1',
                'show_popular_badges' => '1', 'show_sticky_basket' => '1',
                'empty_categories' => 'hide',
                'intro_text' => '', 'footer_text' => '',
            ),
            'business_info' => array(
                'business_name' => '', 'trading_name' => '',
                'address_1' => '', 'address_2' => '', 'town' => '', 'county' => '', 'postcode' => '', 'country' => 'United Kingdom',
                'phone' => '', 'email' => '', 'whatsapp' => '',
                'company_number' => '', 'vat_number' => '',
                'hygiene_rating' => '', 'hygiene_authority_url' => '',
                'google_rating' => '', 'google_review_count' => '', 'google_url' => '',
                'tripadvisor_rating' => '', 'tripadvisor_review_count' => '', 'tripadvisor_url' => '',
                'business_type' => 'takeaway',
            ),
            'opening_times' => array(
                'days' => array(
                    'monday' => $day, 'tuesday' => $day, 'wednesday' => $day, 'thursday' => $day,
                    'friday' => $day, 'saturday' => $day, 'sunday' => $day,
                ),
                'temporary_closure' => '0', 'temporary_closure_message' => '',
                'override' => 'normal',
            ),
            'delivery_collection' => array(
                'delivery_enabled' => '1', 'collection_enabled' => '1', 'default_fulfilment' => 'delivery',
                'delivery_intro' => '', 'collection_intro' => '', 'zone_summary' => '',
                'min_order_text' => '', 'delivery_estimate_text' => '', 'collection_estimate_text' => '',
                'free_delivery_text' => '', 'paused_message' => '',
                'table_booking_enabled' => '0',
            ),
            'contact_map' => array(
                'title' => '', 'intro' => '',
                'map_provider' => 'osm', 'lat' => '', 'lng' => '', 'google_maps_url' => '',
                'parking_note' => '', 'accessibility_note' => '',
                'booking_enabled' => '0', 'booking_type' => 'phone',
                'booking_cta_text' => '', 'booking_target' => '', 'booking_note' => '',
            ),
            'reviews' => array(
                'title' => '', 'subtitle' => '',
                'show_google_link' => '1', 'show_tripadvisor_link' => '1',
                'items' => array(),
            ),
            'offers' => array(
                'items' => array(),
            ),
            'social_links' => array(
                'instagram' => '', 'facebook' => '', 'tiktok' => '', 'whatsapp' => '',
                'google' => '', 'tripadvisor' => '', 'twitter' => '', 'youtube' => '',
            ),
            'footer' => array(
                'logo_id' => 0, 'text' => '',
                'show_contact' => '1', 'show_opening_times' => '1', 'show_social' => '1',
                'show_hygiene' => '1', 'show_google' => '1', 'show_tripadvisor' => '1',
                'show_legal_links' => '1', 'show_allergen_link' => '1', 'show_accessibility_link' => '1',
                'show_built_by' => '1', 'built_by_text' => 'Built by Inkfire', 'built_by_url' => 'https://inkfire.co.uk',
            ),
            'policies' => self::policy_defaults(),
            'banner' => array(
                'enabled' => '0', 'title' => '', 'message' => '', 'cta_text' => '', 'cta_url' => '',
                'style' => 'info', 'start' => '', 'end' => '',
                'pages' => 'all', 'page_ids' => array(),
                'dismissible' => '1', 'remember_dismissal' => '1',
            ),
            'popup' => array(
                'enabled' => '0', 'type' => 'notice', 'title' => '', 'message' => '', 'image_id' => 0,
                'cta_text' => '', 'cta_url' => '', 'start' => '', 'end' => '',
                'page_ids' => array(), 'delay' => '3', 'frequency' => 'session',
                'dismissible' => '1', 'allow_on_checkout' => '0',
            ),
        );
    }

    private static function policy_defaults(): array {
        $intro = "Starter content only. Review before production.\n\n";
        // Tokens replaced at render time with live business CRM data:
        // {business_name}, {phone}, {email}, {address}, {website}
        return array(
            'privacy' => array('title' => 'Privacy Policy', 'content' =>
                "This Privacy Policy explains how {business_name} collects, uses, and protects the personal information you provide when you use our website or place an order with us.\n\n## What information we collect\n\nWhen you place an order we collect: your name, email address, phone number, delivery address, and order history. If you create an account we also store your login credentials (password stored encrypted — never readable by us).\n\nWe do not collect payment card numbers. All card payments are processed securely by our payment provider; only a transaction reference is stored on our system.\n\n## How we use your information\n\nWe use your information to: process and deliver your order; contact you about your order status; send order confirmations and receipts; and — only if you have opted in — to send you promotions and offers. We do not use your data for any other purpose without your explicit consent.\n\n## Who we share it with\n\nWe do not sell your personal data. We share data only with: our payment processor (to charge your card), our delivery software (to route orders), and our hosting provider (to run this website). Each of these operates under strict data-processing agreements.\n\n## Your rights\n\nUnder UK GDPR you have the right to: access the data we hold about you; correct inaccurate data; request deletion of your data; object to processing; and request a portable copy of your data. To exercise any of these rights, contact us at {email} or by phone on {phone}.\n\n## Cookies\n\nWe use essential cookies to run the ordering basket and keep you signed in. See our Cookie Policy for full details.\n\n## Contact\n\nData controller: {business_name}, {address}. Email: {email}. Phone: {phone}."),

            'cookies' => array('title' => 'Cookie Policy', 'content' =>
                "This Cookie Policy explains what cookies {business_name} sets when you visit our website and why.\n\n## What are cookies?\n\nCookies are small text files stored on your device by your browser. They help websites remember your preferences and keep features working between pages.\n\n## Essential cookies (always active)\n\nThese cookies are required for the website to function. You cannot disable them without breaking the ordering system.\n\n- **Session cookie** — keeps your basket intact while you browse the menu.\n- **Login cookie** — keeps you signed in to your account.\n- **Security token** — protects forms from cross-site request forgery.\n- **Delivery preference** — remembers whether you chose delivery or collection.\n\n## Analytics cookies (optional)\n\nIf you agree to analytics cookies, we may use a third-party service (such as Google Analytics) to understand how visitors use our site. These cookies do not identify you personally and can be blocked without affecting ordering.\n\n## How to manage cookies\n\nYou can clear or block cookies at any time in your browser settings. Please note that blocking essential cookies will prevent the ordering basket from working.\n\n## Contact\n\nFor questions about how we use cookies, contact us at {email}."),

            'terms' => array('title' => 'Terms & Conditions', 'content' =>
                "These Terms & Conditions govern orders placed through the {business_name} website. Please read them before placing your order.\n\n## 1. Who we are\n\n{business_name} operates this website and the food ordering service described here. Our contact details are at the bottom of this page.\n\n## 2. Placing an order\n\nWhen you submit an order you are making an offer to purchase. The contract between you and {business_name} is formed when we confirm the order on-screen or by email. We reserve the right to decline any order.\n\n## 3. Pricing and VAT\n\nAll prices on the menu include VAT where applicable. We reserve the right to change prices at any time; the price shown at the time you confirm your order is the price you pay.\n\n## 4. Availability\n\nMenu items are subject to availability. If an item is unavailable after you have ordered, we will contact you to offer a suitable alternative or a full refund for that item.\n\n## 5. Allergens and dietary information\n\nAllergen information is provided as a guide. Our kitchen handles all 14 UK-regulated allergens and cross-contamination cannot be guaranteed. If you have a severe allergy, please call us before ordering on {phone}.\n\n## 6. Delivery and collection\n\nDelivery times are estimated and can be affected by demand, traffic, and weather. We will contact you if there is a significant delay. Collection times are also estimated; please wait at the counter if your order is not ready.\n\n## 7. Payment\n\nPayment is taken at the time of order. We accept the payment methods shown at checkout. Your payment details are processed securely by our payment provider and are never stored on our servers.\n\n## 8. Cancellations\n\nYou may cancel an order at no charge any time before we accept it in the kitchen. Once preparation has begun, cancellation may not be possible. See our Refunds Policy for full details.\n\n## 9. Liability\n\n{business_name} is not liable for indirect losses, loss of profit, or consequential loss arising from use of this website or from an order. Our liability is limited to the value of the order in question.\n\n## 10. Governing law\n\nThese terms are governed by the laws of England and Wales. Any disputes will be subject to the exclusive jurisdiction of the courts of England and Wales.\n\n## 11. Changes to these terms\n\nWe may update these terms from time to time. The version published on this website is always the current version.\n\n## Contact\n\n{business_name} — {address}. Email: {email}. Phone: {phone}."),

            'refunds' => array('title' => 'Refunds & Cancellations', 'content' =>
                "We want you to be completely satisfied with every order. This policy explains your rights if something goes wrong.\n\n## Cancelling before the kitchen accepts your order\n\nYou can cancel your order free of charge at any time before we accept it in our kitchen. Call us immediately on {phone} to request a cancellation. If the order has not yet been accepted, a full refund will be issued to your original payment method within 3–5 business days.\n\n## Cancelling after the kitchen has started preparing\n\nOnce food preparation has begun, cancellation may not be possible. If you still wish to cancel, please call us on {phone} and we will do our best to assist, though a refund may not be possible at this stage.\n\n## Something wrong with your order?\n\nIf you receive an order with: a missing item; a wrong item; a quality problem; or damage in transit — please contact us the same evening you received the order. We will investigate and resolve the matter with one of the following:\n\n- A replacement delivered at no extra charge\n- A credit to your account for a future order\n- A partial or full refund to your original payment method\n\nRefunds are processed within 3–5 business days and appear on your statement within 7–10 business days depending on your bank.\n\n## Non-refundable situations\n\nWe cannot offer refunds where: the order was correct but you changed your mind after delivery; incorrect delivery details were provided; the food was not accepted from the driver.\n\n## How to contact us about a refund\n\nCall us on {phone} during opening hours, or email {email}. Please have your order number ready."),

            'delivery' => array('title' => 'Delivery Policy', 'content' =>
                "## Delivery area\n\nWe deliver to postcodes within our delivery zone, which you can check using the postcode checker on our menu page. If your postcode is outside our area, collection from {address} is always available.\n\n## Delivery charges and minimum orders\n\nA minimum order value and delivery fee may apply depending on your postcode. Both are shown clearly before you confirm payment — there are no hidden charges.\n\n## Estimated delivery times\n\nDelivery times shown at checkout are estimates based on current order volume and distance. Typical delivery takes 30–60 minutes from order confirmation. Times can be longer during peak periods (Friday and Saturday evenings) or in adverse weather conditions.\n\nIf your order is significantly delayed, we will contact you on the phone number provided.\n\n## Delivery instructions\n\nPlease ensure your delivery address and phone number are correct when you order. If you have specific instructions (e.g. leave at door, access code for a gate), add them to the order notes field at checkout.\n\nOur driver will call if they cannot find you. If there is no answer after a reasonable wait, the order may be returned to the kitchen and a refund may not be possible.\n\n## Contactless delivery\n\nIf you prefer contactless delivery, add a note at checkout requesting the driver to leave your order at the door. Photo confirmation may be provided.\n\n## Unable to deliver?\n\nIf we are unable to complete delivery for reasons within our control (e.g. driver unavailability), we will contact you to rearrange or issue a full refund.\n\n## Contact\n\nFor delivery queries, call {phone} during opening hours or email {email}."),

            'allergens' => array('title' => 'Allergen Information', 'content' =>
                "The safety of our customers is our priority. This page explains how we handle allergens at {business_name}.\n\n## The 14 UK regulated allergens\n\nUnder UK Food Information Regulations, we are required to declare the presence of the following allergens:\n\n1. Cereals containing gluten (wheat, rye, barley, oats)\n2. Crustaceans (prawns, crabs, lobster, crayfish)\n3. Eggs\n4. Fish\n5. Peanuts\n6. Soybeans\n7. Milk (including lactose)\n8. Nuts (almonds, hazelnuts, walnuts, cashews, pecans, pistachios, macadamia)\n9. Celery and celeriac\n10. Mustard\n11. Sesame seeds\n12. Sulphur dioxide and sulphites\n13. Lupin\n14. Molluscs (clams, mussels, oysters, scallops, squid)\n\n## How allergens are listed on our menu\n\nEach dish on our menu shows which of the above allergens are present. This information is based on the ingredients used in the recipe. We update allergen information whenever a recipe changes.\n\n## Cross-contamination risk\n\nOur kitchen prepares dishes containing all 14 regulated allergens. Despite our best efforts to keep equipment clean and separate, we cannot guarantee that any dish is completely free from traces of any allergen. For this reason, we cannot prepare food that is guaranteed safe for people with severe allergies.\n\n## What to do if you have an allergy\n\nIf you or someone you are ordering for has a food allergy or intolerance:\n\n- Add a note to your order at checkout specifying the allergy\n- Call us before ordering on {phone} so we can advise whether we can safely accommodate your needs\n- For severe or life-threatening allergies, we recommend you do not order from us without speaking to us first\n\n## Intolerances\n\nIf you have a food intolerance (not an allergy), please also contact us before ordering. We will do our best to advise on suitable dishes and preparation methods.\n\n## Contact\n\nFor allergen queries, call {phone} during opening hours or email {email}."),

            'accessibility' => array('title' => 'Accessibility Statement', 'content' =>
                "## Our commitment\n\n{business_name} is committed to making this website accessible to everyone, including people with disabilities. We want every customer to be able to browse our menu and place an order with ease.\n\n## Standards we aim to meet\n\nThis website aims to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA. These guidelines explain how to make websites more accessible to people with a wide range of disabilities.\n\n## Accessibility features on this site\n\n- **Keyboard navigation** — all interactive elements (menu items, buttons, forms) can be accessed using only a keyboard.\n- **Screen reader support** — the site uses semantic HTML, ARIA labels, and logical heading structure to work with screen readers such as NVDA, JAWS, and VoiceOver.\n- **Visible focus indicators** — focusable elements have a visible outline to help keyboard users track their position on the page.\n- **Colour contrast** — text and interactive elements meet the WCAG 2.1 AA contrast ratio requirements (4.5:1 for normal text).\n- **Resizable text** — text can be enlarged to 200% using browser zoom without loss of functionality.\n- **Alt text** — meaningful images have descriptive alternative text for screen reader users.\n- **Skip to content** — a skip link at the top of each page allows keyboard users to bypass the navigation menu.\n\n## Known limitations\n\nWe are continuously working to improve the accessibility of this website. If you encounter an issue not mentioned here, please let us know.\n\n## How to request an accessible alternative\n\nIf you need information in a different format (e.g. large print, audio, easy-read), or if you need help placing an order, please contact us:\n\n- Phone: {phone} (speaking with a team member is the fastest way to get help)\n- Email: {email}\n\nWe will respond as quickly as possible and make every effort to help you.\n\n## Feedback\n\nWe welcome feedback on the accessibility of this website. If you find any content that is not accessible, please contact us using the details above so we can improve."),

            'hygiene' => array('title' => 'Food Hygiene & Safety', 'content' =>
                "## Our food hygiene rating\n\nOur food hygiene rating is issued under the national Food Hygiene Rating Scheme (FHRS) by our local authority environmental health team. Our current rating is displayed on this website and at the premises.\n\n## Food safety procedures\n\nAt {business_name} we operate a rigorous food safety management system in compliance with UK food hygiene law. Our procedures cover:\n\n### Temperature control\n\n- All chilled foods are stored at or below 5°C and frozen foods at or below -18°C.\n- Hot food is cooked to a safe core temperature and held at or above 63°C during service.\n- Temperature logs are checked and recorded daily.\n\n### Cross-contamination prevention\n\n- Separate chopping boards and utensils are used for different food types (raw meat, fish, vegetables).\n- Raw and ready-to-eat foods are stored separately.\n- Surfaces and equipment are sanitised before and after each use.\n\n### Staff training\n\n- All food handlers complete Level 2 Food Safety in Catering training before working with food.\n- Refresher training is provided regularly.\n- Staff experiencing symptoms of illness are not permitted to handle food.\n\n### Allergen management\n\n- Full allergen records are maintained for every dish.\n- Please see our Allergen Information page for details of allergens present in our menu items.\n\n### Cleaning and pest control\n\n- A documented cleaning schedule is followed daily, weekly, and monthly.\n- A professional pest control contractor carries out regular inspections.\n\n## Questions about food preparation\n\nIf you have any questions about how your food is prepared, the ingredients we use, or our hygiene practices, please do not hesitate to contact us:\n\n- Phone: {phone}\n- Email: {email}"),

            'contact_details' => array('title' => 'Contact & Business Details', 'content' =>
                "## How to reach us\n\nThe fastest way to get in touch about an order is by phone during our opening hours. For non-urgent enquiries, email works well and we aim to respond within one business day.\n\n- **Phone:** {phone}\n- **Email:** {email}\n- **Address:** {address}\n\n## Order enquiries\n\nFor questions about an existing order — status, changes, or problems — please call us on {phone}. Our team can access your order details immediately and resolve most issues on the call.\n\n## Refunds and complaints\n\nIf something went wrong with your order, we want to make it right. Please call us or send an email including your order number and a description of the problem. See our Refunds & Cancellations page for our full policy.\n\n## Feedback and suggestions\n\nWe value customer feedback and read every message. If you have a suggestion about our menu, service, or website, please email us at {email}.\n\n## Business information\n\nThis website is operated by {business_name}. Our registered business details are listed in the table below."),

            '_notice' => array('title' => '', 'content' => $intro), // marker, not rendered publicly
        );
    }

    /* ---------------------------------------------------------------------
     * Read / write / migrate
     * ------------------------------------------------------------------- */

    public static function get(string $section = '', $key = null, $default = null) {
        $stored = get_option(self::OPTION, array());
        if (!is_array($stored)) $stored = array();
        $defaults = self::defaults();
        $content = array('version' => $stored['version'] ?? $defaults['version']);
        foreach ($defaults as $section_key => $section_defaults) {
            if ($section_key === 'version') continue;
            $content[$section_key] = wp_parse_args(
                isset($stored[$section_key]) && is_array($stored[$section_key]) ? $stored[$section_key] : array(),
                $section_defaults
            );
        }
        if ($section === '') return $content;
        if ($key === null) return $content[$section] ?? array();
        return $content[$section][$key] ?? $default;
    }

    public static function update_section(string $section, array $values): void {
        $stored = get_option(self::OPTION, array());
        if (!is_array($stored)) $stored = array();
        $stored['version'] = self::CONTENT_VERSION;
        $stored[$section] = $values;
        update_option(self::OPTION, $stored, false);
    }

    /**
     * Add-only migration: creates the option if missing and adds any missing
     * sections/keys. Existing values are never modified.
     */
    public static function migrate(): void {
        $stored = get_option(self::OPTION, null);
        $defaults = self::defaults();
        if (!is_array($stored)) {
            add_option(self::OPTION, $defaults, '', false);
            return;
        }
        $changed = false;
        foreach ($defaults as $section_key => $section_defaults) {
            if ($section_key === 'version') continue;
            if (!isset($stored[$section_key]) || !is_array($stored[$section_key])) {
                $stored[$section_key] = $section_defaults;
                $changed = true;
                continue;
            }
            foreach ($section_defaults as $key => $value) {
                if (!array_key_exists($key, $stored[$section_key])) {
                    $stored[$section_key][$key] = $value;
                    $changed = true;
                }
            }
        }
        if (empty($stored['version']) || $stored['version'] !== self::CONTENT_VERSION) {
            $stored['version'] = self::CONTENT_VERSION;
            $changed = true;
        }
        if ($changed) {
            update_option(self::OPTION, $stored, false);
        }
    }

    /* ---------------------------------------------------------------------
     * Sanitisation
     * ------------------------------------------------------------------- */

    private static function bool($value): string {
        return !empty($value) ? '1' : '0';
    }

    private static function choice($value, array $allowed, string $fallback): string {
        $key = sanitize_key((string) $value);
        return in_array($key, $allowed, true) ? $key : $fallback;
    }

    private static function attachment_id($value): int {
        $id = absint($value);
        return ($id && get_post_type($id) === 'attachment') ? $id : 0;
    }

    private static function url($value): string {
        $url = esc_url_raw(trim((string) $value));
        return $url && preg_match('#^https?://#i', $url) ? $url : '';
    }

    /** CTA targets may be a URL, tel: link, mailto:, WhatsApp link or relative path. */
    private static function cta_target($value): string {
        $value = trim((string) $value);
        if ($value === '') return '';
        if (preg_match('#^(tel:|mailto:|https://wa\.me/|whatsapp:)#i', $value)) {
            return sanitize_text_field($value);
        }
        if ($value[0] === '/' || $value[0] === '#') {
            return sanitize_text_field($value);
        }
        return self::url($value);
    }

    private static function time_value($value): string {
        $value = trim((string) $value);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
    }

    private static function datetime_value($value): string {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}T([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
    }

    private static function rating($value): string {
        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) return '';
        $n = max(0, min(5, (float) $value));
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }

    private static function int_list($values, string $post_type = ''): array {
        if (!is_array($values)) return array();
        $out = array();
        foreach ($values as $value) {
            $id = absint($value);
            if (!$id) continue;
            if ($post_type === 'term') {
                if (!term_exists($id, 'product_cat')) continue;
            } elseif ($post_type !== '' && get_post_type($id) !== $post_type) {
                continue;
            }
            $out[] = $id;
        }
        return array_values(array_unique($out));
    }

    public static function sanitize_section(string $section, array $raw): array {
        $defaults = self::defaults()[$section] ?? array();
        $clean = $defaults;

        switch ($section) {
            case 'homepage':
                foreach (array('hero_eyebrow','hero_title','hero_subtitle','primary_cta_text','secondary_cta_text','featured_title','featured_subtitle','why_direct_title','about_title','booking_title','booking_cta_text','newsletter_title','bottom_cta_title','bottom_cta_button_text') as $key) {
                    $clean[$key] = sanitize_text_field((string) ($raw[$key] ?? ''));
                }
                foreach (array('hero_image_id','hero_bg_image_id','about_image_id') as $key) {
                    $clean[$key] = self::attachment_id($raw[$key] ?? 0);
                }
                foreach (array('primary_cta_target','secondary_cta_target','booking_cta_target') as $key) {
                    $clean[$key] = self::cta_target($raw[$key] ?? '');
                }
                $clean['bottom_cta_url'] = self::cta_target($raw['bottom_cta_url'] ?? '');
                foreach (array('show_fulfilment_toggle','show_postcode_checker','show_open_status','show_trust_strip','show_booking','show_reviews','show_newsletter') as $key) {
                    $clean[$key] = self::bool($raw[$key] ?? '');
                }
                foreach (array('about_text','booking_text','newsletter_text','bottom_cta_text') as $key) {
                    $clean[$key] = wp_kses_post(trim((string) ($raw[$key] ?? '')));
                }
                $clean['featured_product_ids']  = self::int_list($raw['featured_product_ids'] ?? array(), 'product');
                $clean['featured_category_ids'] = self::int_list($raw['featured_category_ids'] ?? array(), 'term');
                $cards = array();
                foreach ((array) ($raw['why_direct_cards'] ?? array()) as $row) {
                    if (!is_array($row)) continue;
                    $title = sanitize_text_field((string) ($row['title'] ?? ''));
                    $text  = sanitize_text_field((string) ($row['text'] ?? ''));
                    if ($title === '' && $text === '') continue;
                    $cards[] = array('title' => $title, 'text' => $text);
                    if (count($cards) >= 6) break;
                }
                $clean['why_direct_cards'] = $cards;
                break;

            case 'menu_page':
                foreach (array('eyebrow','title','subtitle') as $key) {
                    $clean[$key] = sanitize_text_field((string) ($raw[$key] ?? ''));
                }
                $clean['hero_image_id'] = self::attachment_id($raw['hero_image_id'] ?? 0);
                foreach (array('show_postcode_checker','show_search','show_dietary_filters','show_allergen_filters','show_popular_badges','show_sticky_basket') as $key) {
                    $clean[$key] = self::bool($raw[$key] ?? '');
                }
                $clean['empty_categories'] = self::choice($raw['empty_categories'] ?? '', array('hide', 'admin_helper'), 'hide');
                $clean['intro_text']  = wp_kses_post(trim((string) ($raw['intro_text'] ?? '')));
                $clean['footer_text'] = wp_kses_post(trim((string) ($raw['footer_text'] ?? '')));
                break;

            case 'business_info':
                foreach (array('business_name','trading_name','address_1','address_2','town','county','postcode','country','phone','whatsapp','company_number','vat_number','hygiene_rating','google_review_count','tripadvisor_review_count') as $key) {
                    $clean[$key] = sanitize_text_field((string) ($raw[$key] ?? ''));
                }
                $clean['email'] = sanitize_email((string) ($raw['email'] ?? ''));
                foreach (array('hygiene_authority_url','google_url','tripadvisor_url') as $key) {
                    $clean[$key] = self::url($raw[$key] ?? '');
                }
                $clean['google_rating']      = self::rating($raw['google_rating'] ?? '');
                $clean['tripadvisor_rating'] = self::rating($raw['tripadvisor_rating'] ?? '');
                $clean['business_type'] = self::choice($raw['business_type'] ?? '', array('takeaway','restaurant_takeaway','collection_only','delivery_only','food_truck','dark_kitchen'), 'takeaway');
                break;

            case 'opening_times':
                $days = array();
                foreach (array('monday','tuesday','wednesday','thursday','friday','saturday','sunday') as $day) {
                    $row = isset($raw['days'][$day]) && is_array($raw['days'][$day]) ? $raw['days'][$day] : array();
                    $days[$day] = array(
                        'closed'           => self::bool($row['closed'] ?? ''),
                        'open'             => self::time_value($row['open'] ?? ''),
                        'close'            => self::time_value($row['close'] ?? ''),
                        'note'             => sanitize_text_field((string) ($row['note'] ?? '')),
                        'delivery_open'    => self::time_value($row['delivery_open'] ?? ''),
                        'delivery_close'   => self::time_value($row['delivery_close'] ?? ''),
                        'collection_open'  => self::time_value($row['collection_open'] ?? ''),
                        'collection_close' => self::time_value($row['collection_close'] ?? ''),
                    );
                }
                $clean['days'] = $days;
                $clean['temporary_closure'] = self::bool($raw['temporary_closure'] ?? '');
                $clean['temporary_closure_message'] = sanitize_text_field((string) ($raw['temporary_closure_message'] ?? ''));
                $clean['override'] = self::choice($raw['override'] ?? '', array('normal','force_open','force_closed','preorder'), 'normal');
                break;

            case 'delivery_collection':
                foreach (array('delivery_enabled','collection_enabled') as $key) {
                    $clean[$key] = self::bool($raw[$key] ?? '');
                }
                $clean['default_fulfilment'] = self::choice($raw['default_fulfilment'] ?? '', array('delivery','collection'), 'delivery');
                foreach (array('zone_summary','min_order_text','delivery_estimate_text','collection_estimate_text','free_delivery_text','paused_message') as $key) {
                    $clean[$key] = sanitize_text_field((string) ($raw[$key] ?? ''));
                }
                foreach (array('delivery_intro','collection_intro') as $key) {
                    $clean[$key] = wp_kses_post(trim((string) ($raw[$key] ?? '')));
                }
                $clean['table_booking_enabled'] = self::bool($raw['table_booking_enabled'] ?? '');
                break;

            case 'contact_map':
                $clean['title'] = sanitize_text_field((string) ($raw['title'] ?? ''));
                $clean['intro'] = wp_kses_post(trim((string) ($raw['intro'] ?? '')));
                $clean['map_provider'] = self::choice($raw['map_provider'] ?? '', array('osm','google','none'), 'osm');
                foreach (array('lat','lng') as $key) {
                    $value = trim((string) ($raw[$key] ?? ''));
                    $clean[$key] = ($value !== '' && is_numeric($value) && abs((float) $value) <= 180) ? (string) (float) $value : '';
                }
                $clean['google_maps_url'] = self::url($raw['google_maps_url'] ?? '');
                $clean['parking_note'] = sanitize_text_field((string) ($raw['parking_note'] ?? ''));
                $clean['accessibility_note'] = sanitize_text_field((string) ($raw['accessibility_note'] ?? ''));
                $clean['booking_enabled'] = self::bool($raw['booking_enabled'] ?? '');
                $clean['booking_type'] = self::choice($raw['booking_type'] ?? '', array('phone','whatsapp','url','note'), 'phone');
                $clean['booking_cta_text'] = sanitize_text_field((string) ($raw['booking_cta_text'] ?? ''));
                $clean['booking_target'] = self::cta_target($raw['booking_target'] ?? '');
                $clean['booking_note'] = sanitize_text_field((string) ($raw['booking_note'] ?? ''));
                break;

            case 'reviews':
                $clean['title'] = sanitize_text_field((string) ($raw['title'] ?? ''));
                $clean['subtitle'] = sanitize_text_field((string) ($raw['subtitle'] ?? ''));
                $clean['show_google_link'] = self::bool($raw['show_google_link'] ?? '');
                $clean['show_tripadvisor_link'] = self::bool($raw['show_tripadvisor_link'] ?? '');
                $items = array();
                foreach ((array) ($raw['items'] ?? array()) as $row) {
                    if (!is_array($row)) continue;
                    $name = sanitize_text_field((string) ($row['name'] ?? ''));
                    $text = wp_kses_post(trim((string) ($row['text'] ?? '')));
                    if ($name === '' && $text === '') continue;
                    $items[] = array(
                        'name'         => $name,
                        'rating'       => self::rating($row['rating'] ?? ''),
                        'text'         => $text,
                        'source_label' => sanitize_text_field((string) ($row['source_label'] ?? '')),
                        'source_url'   => self::url($row['source_url'] ?? ''),
                        'featured'     => self::bool($row['featured'] ?? ''),
                    );
                    if (count($items) >= 12) break;
                }
                $clean['items'] = $items;
                break;

            case 'offers':
                $items = array();
                foreach ((array) ($raw['items'] ?? array()) as $row) {
                    if (!is_array($row)) continue;
                    $title = sanitize_text_field((string) ($row['title'] ?? ''));
                    $text  = wp_kses_post(trim((string) ($row['text'] ?? '')));
                    if ($title === '' && $text === '') continue;
                    $items[] = array(
                        'title'    => $title,
                        'text'     => $text,
                        'image_id' => self::attachment_id($row['image_id'] ?? 0),
                        'cta_text' => sanitize_text_field((string) ($row['cta_text'] ?? '')),
                        'cta_url'  => self::cta_target($row['cta_url'] ?? ''),
                        'start'    => self::datetime_value($row['start'] ?? ''),
                        'end'      => self::datetime_value($row['end'] ?? ''),
                        'enabled'  => self::bool($row['enabled'] ?? ''),
                    );
                    if (count($items) >= 12) break;
                }
                $clean['items'] = $items;
                break;

            case 'social_links':
                foreach (array_keys(self::defaults()['social_links']) as $key) {
                    $clean[$key] = self::url($raw[$key] ?? '');
                }
                break;

            case 'footer':
                $clean['logo_id'] = self::attachment_id($raw['logo_id'] ?? 0);
                $clean['text'] = wp_kses_post(trim((string) ($raw['text'] ?? '')));
                foreach (array('show_contact','show_opening_times','show_social','show_hygiene','show_google','show_tripadvisor','show_legal_links','show_allergen_link','show_accessibility_link','show_built_by') as $key) {
                    $clean[$key] = self::bool($raw[$key] ?? '');
                }
                $clean['built_by_text'] = sanitize_text_field((string) ($raw['built_by_text'] ?? ''));
                $clean['built_by_url'] = self::url($raw['built_by_url'] ?? '');
                break;

            case 'policies':
                foreach (self::policy_defaults() as $key => $default_policy) {
                    if ($key === '_notice') { $clean[$key] = $default_policy; continue; }
                    $row = isset($raw[$key]) && is_array($raw[$key]) ? $raw[$key] : array();
                    $clean[$key] = array(
                        'title'   => sanitize_text_field((string) ($row['title'] ?? $default_policy['title'])),
                        'content' => wp_kses_post(trim((string) ($row['content'] ?? ''))),
                    );
                }
                break;

            case 'banner':
                $clean['enabled'] = self::bool($raw['enabled'] ?? '');
                $clean['title'] = sanitize_text_field((string) ($raw['title'] ?? ''));
                $clean['message'] = wp_kses_post(trim((string) ($raw['message'] ?? '')));
                $clean['cta_text'] = sanitize_text_field((string) ($raw['cta_text'] ?? ''));
                $clean['cta_url'] = self::cta_target($raw['cta_url'] ?? '');
                $clean['style'] = self::choice($raw['style'] ?? '', array('info','warning','offer','closed'), 'info');
                $clean['start'] = self::datetime_value($raw['start'] ?? '');
                $clean['end'] = self::datetime_value($raw['end'] ?? '');
                $clean['pages'] = self::choice($raw['pages'] ?? '', array('all','selected'), 'all');
                $clean['page_ids'] = self::int_list($raw['page_ids'] ?? array(), 'page');
                $clean['dismissible'] = self::bool($raw['dismissible'] ?? '');
                $clean['remember_dismissal'] = self::bool($raw['remember_dismissal'] ?? '');
                break;

            case 'popup':
                $clean['enabled'] = self::bool($raw['enabled'] ?? '');
                $clean['type'] = self::choice($raw['type'] ?? '', array('offer','notice','newsletter','closure','loyalty'), 'notice');
                $clean['title'] = sanitize_text_field((string) ($raw['title'] ?? ''));
                $clean['message'] = wp_kses_post(trim((string) ($raw['message'] ?? '')));
                $clean['image_id'] = self::attachment_id($raw['image_id'] ?? 0);
                $clean['cta_text'] = sanitize_text_field((string) ($raw['cta_text'] ?? ''));
                $clean['cta_url'] = self::cta_target($raw['cta_url'] ?? '');
                $clean['start'] = self::datetime_value($raw['start'] ?? '');
                $clean['end'] = self::datetime_value($raw['end'] ?? '');
                $clean['page_ids'] = self::int_list($raw['page_ids'] ?? array(), 'page');
                $clean['delay'] = (string) min(120, absint($raw['delay'] ?? 3));
                $clean['frequency'] = self::choice($raw['frequency'] ?? '', array('session','day','week'), 'session');
                $clean['dismissible'] = self::bool($raw['dismissible'] ?? '');
                $clean['allow_on_checkout'] = self::bool($raw['allow_on_checkout'] ?? '');
                break;

            default:
                return $defaults;
        }

        return $clean;
    }

    /* ---------------------------------------------------------------------
     * Save / export / import handling
     * ------------------------------------------------------------------- */

    public static function handle_posts(): void {
        if (!is_admin() || empty($_POST['ttos_action'])) return;
        $action = sanitize_key(wp_unslash($_POST['ttos_action']));
        if (!in_array($action, array('save_site_content', 'import_site_content_upload', 'import_site_content_confirm', 'import_site_content_cancel'), true)) return;
        if (!current_user_can('ttos_manage_settings')) {
            wp_die('You do not have permission to manage site content.');
        }
        check_admin_referer('ttos_' . $action);

        if ($action === 'save_site_content') {
            $section = sanitize_key(wp_unslash($_POST['section'] ?? ''));
            $sections = self::defaults();
            unset($sections['version']);
            if (!array_key_exists($section, $sections)) {
                self::redirect('invalid-section', $section);
            }
            $raw = wp_unslash($_POST['content'] ?? array());
            if (!is_array($raw)) $raw = array();
            self::update_section($section, self::sanitize_section($section, $raw));
            self::redirect('content-saved', $section);
        }

        if ($action === 'import_site_content_upload') {
            self::import_upload();
        }
        if ($action === 'import_site_content_confirm') {
            self::import_confirm();
        }
        if ($action === 'import_site_content_cancel') {
            delete_transient(self::import_transient_key());
            self::redirect('import-cancelled', 'export_import');
        }
    }

    private static function redirect(string $notice, string $tab = ''): void {
        $url = add_query_arg(array(
            'page' => 'takeaway-os-site-content',
            'tab' => $tab ?: null,
            'ttos_notice' => $notice,
        ), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private static function import_transient_key(): string {
        return 'ttos_sc_import_' . get_current_user_id();
    }

    public static function export(): void {
        if (!current_user_can('ttos_manage_settings')) {
            wp_die('You do not have permission to export site content.');
        }
        check_admin_referer('ttos_export_site_content');
        $payload = array(
            'format'       => 'ttos-site-content',
            'version'      => self::CONTENT_VERSION,
            'plugin'       => TTOS_VERSION,
            'exported_at'  => gmdate('c'),
            'site'         => home_url('/'),
            'branding'     => TTOS_Settings::get('branding'),
            'site_content' => self::get(),
        );
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=takeaway-site-content-' . gmdate('Ymd-His') . '.json');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function import_upload(): void {
        if (empty($_FILES['ttos_import_file']['tmp_name']) || !is_uploaded_file($_FILES['ttos_import_file']['tmp_name'])) {
            self::redirect('import-no-file', 'export_import');
        }
        $size = (int) ($_FILES['ttos_import_file']['size'] ?? 0);
        if ($size <= 0 || $size > 1024 * 1024) {
            self::redirect('import-bad-file', 'export_import');
        }
        $json = file_get_contents($_FILES['ttos_import_file']['tmp_name']);
        $data = json_decode((string) $json, true);
        if (!is_array($data) || ($data['format'] ?? '') !== 'ttos-site-content' || empty($data['version'])) {
            self::redirect('import-bad-file', 'export_import');
        }
        $has_content = isset($data['site_content']) && is_array($data['site_content']);
        $has_branding = isset($data['branding']) && is_array($data['branding']);
        if (!$has_content && !$has_branding) {
            self::redirect('import-bad-file', 'export_import');
        }
        set_transient(self::import_transient_key(), $data, 15 * MINUTE_IN_SECONDS);
        self::redirect('import-preview', 'export_import');
    }

    private static function import_confirm(): void {
        $data = get_transient(self::import_transient_key());
        if (!is_array($data)) {
            self::redirect('import-expired', 'export_import');
        }
        // Site content: run every imported section through the same sanitisers as manual saves.
        if (isset($data['site_content']) && is_array($data['site_content'])) {
            $sections = self::defaults();
            unset($sections['version']);
            foreach (array_keys($sections) as $section) {
                if (isset($data['site_content'][$section]) && is_array($data['site_content'][$section])) {
                    self::update_section($section, self::sanitize_section($section, $data['site_content'][$section]));
                }
            }
        }
        // Branding: sanitise through the shared token rules.
        if (isset($data['branding']) && is_array($data['branding']) && class_exists('TTOS_Settings')) {
            $raw = $data['branding'];
            $branding = TTOS_Settings::get('branding');
            foreach (array('logo_id','favicon_id','hero_image_id') as $key) {
                if (array_key_exists($key, $raw)) $branding[$key] = self::attachment_id($raw[$key]);
            }
            foreach (array('primary','accent','bg','surface','surface_soft','text','muted','border','success','warning','error','secondary','dark','cream') as $key) {
                if (!array_key_exists($key, $raw)) continue;
                $hex = sanitize_hex_color((string) $raw[$key]);
                if ($hex) $branding[$key] = $hex;
            }
            foreach (array('radius_sm','radius_md','radius_lg') as $key) {
                if (array_key_exists($key, $raw)) $branding[$key] = (string) min(60, absint($raw[$key]));
            }
            foreach (array('shadow' => array('none','soft','strong'), 'default_mode' => array('light','dark','system'), 'header_style' => array('solid','transparent'), 'hero_style' => array('angled','minimal','photo'), 'card_style' => array('soft','outlined','flat'), 'footer_style' => array('dark','light'), 'style_skin' => array('charcoal','burger','pizza','clean')) as $key => $allowed) {
                if (!array_key_exists($key, $raw)) continue;
                $value = sanitize_key((string) $raw[$key]);
                if (in_array($value, $allowed, true)) $branding[$key] = $value;
            }
            TTOS_Settings::update_section('branding', $branding);
        }
        delete_transient(self::import_transient_key());
        self::redirect('import-done', 'export_import');
    }

    /* ---------------------------------------------------------------------
     * Admin screen
     * ------------------------------------------------------------------- */

    public static function tabs(): array {
        return array(
            'homepage'            => 'Homepage',
            'menu_page'           => 'Menu Page',
            'business_info'       => 'Business Info',
            'opening_times'       => 'Opening Times',
            'delivery_collection' => 'Delivery & Collection',
            'contact_map'         => 'Contact & Map',
            'reviews'             => 'Reviews',
            'offers'              => 'Offers',
            'social_links'        => 'Social Links',
            'footer'              => 'Footer',
            'policies'            => 'Policies',
            'banner'              => 'Banner',
            'popup'               => 'Popup',
            'export_import'       => 'Export / Import',
        );
    }

    public static function page(): void {
        $tabs = self::tabs();
        $current = sanitize_key(wp_unslash($_GET['tab'] ?? 'homepage'));
        if (!isset($tabs[$current])) $current = 'homepage';

        self::shell_start('Site Content', 'Structured content for the public website. The layout is protected; the content is editable. Templates pick these fields up as the new front end rolls out.');

        echo '<nav class="ttos-subtabs" aria-label="Site content sections">';
        foreach ($tabs as $key => $label) {
            $class = $key === $current ? 'active' : '';
            $url = add_query_arg(array('page' => 'takeaway-os-site-content', 'tab' => $key), admin_url('admin.php'));
            echo '<a class="' . esc_attr($class) . '" ' . ($key === $current ? 'aria-current="page" ' : '') . 'href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        $renderer = 'tab_' . $current;
        if (method_exists(__CLASS__, $renderer)) {
            self::$renderer();
        }
        self::shell_end();
    }

    private static function shell_start(string $title, string $subtitle): void {
        echo '<div class="ttos-wrap"><div class="ttos-shell">';
        echo '<p class="ttos-eyebrow">TAKEAWAY OS</p><h1>' . esc_html($title) . '</h1><p class="ttos-muted">' . esc_html($subtitle) . '</p>';
        self::notices();
    }

    private static function shell_end(): void {
        echo '</div></div>';
    }

    private static function notices(): void {
        if (empty($_GET['ttos_notice'])) return;
        $notice = sanitize_key(wp_unslash($_GET['ttos_notice']));
        $map = array(
            'content-saved'    => array('ok', 'Saved. Site content was updated.'),
            'invalid-section'  => array('err', 'That content section was not recognised, nothing was saved.'),
            'import-no-file'   => array('err', 'Choose a JSON export file first.'),
            'import-bad-file'  => array('err', 'That file is not a valid Takeaway site content export.'),
            'import-preview'   => array('ok', 'Export file loaded. Review the preview below, then confirm to apply it.'),
            'import-expired'   => array('err', 'The import preview expired. Upload the file again.'),
            'import-cancelled' => array('ok', 'Import cancelled. Nothing was changed.'),
            'import-done'      => array('ok', 'Import applied. Branding and site content were updated.'),
        );
        if (!isset($map[$notice])) return;
        echo '<div class="ttos-notice' . ($map[$notice][0] === 'err' ? ' ttos-notice-error' : '') . '">' . esc_html($map[$notice][1]) . '</div>';
    }

    /* ---- field helpers -------------------------------------------------- */

    private static function form_open(string $section): void {
        echo '<form method="post" class="ttos-sc-form">';
        wp_nonce_field('ttos_save_site_content');
        echo '<input type="hidden" name="ttos_action" value="save_site_content">';
        echo '<input type="hidden" name="section" value="' . esc_attr($section) . '">';
    }

    private static function form_close(string $label = 'Save changes'): void {
        echo '<p class="ttos-sc-save"><button class="ttos-button">' . esc_html($label) . '</button></p></form>';
    }

    private static function text(string $label, string $name, $value, string $help = '', string $type = 'text'): void {
        echo '<label class="ttos-sc-field">' . esc_html($label);
        echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        if ($help) echo '<small>' . esc_html($help) . '</small>';
        echo '</label>';
    }

    private static function textarea(string $label, string $name, $value, string $help = '', int $rows = 4): void {
        echo '<label class="ttos-sc-field">' . esc_html($label);
        echo '<textarea name="' . esc_attr($name) . '" rows="' . absint($rows) . '">' . esc_textarea((string) $value) . '</textarea>';
        if ($help) echo '<small>' . esc_html($help) . '</small>';
        echo '</label>';
    }

    private static function check(string $label, string $name, $value, string $help = ''): void {
        echo '<label class="ttos-check"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($value, '1', false) . '> ' . esc_html($label);
        if ($help) echo ' <small class="ttos-muted">' . esc_html($help) . '</small>';
        echo '</label>';
    }

    private static function select(string $label, string $name, $value, array $options, string $help = ''): void {
        echo '<label class="ttos-sc-field">' . esc_html($label) . '<select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $option_label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected((string) $value, (string) $key, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        if ($help) echo '<small>' . esc_html($help) . '</small>';
        echo '</label>';
    }

    private static function media(string $label, string $name, int $value, string $button = 'Choose image'): void {
        $id = 'ttos-sc-media-' . md5($name);
        $thumb = $value ? wp_get_attachment_image_url($value, 'medium') : '';
        echo '<div class="ttos-media-field ttos-sc-field"><span class="ttos-sc-label">' . esc_html($label) . '</span>';
        echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        echo '<span class="ttos-media-preview" data-empty="No image selected">';
        if ($thumb) {
            echo '<img src="' . esc_url($thumb) . '" alt="">';
        } else {
            echo '<em>No image selected</em>';
        }
        echo '</span><span class="ttos-media-actions">';
        echo '<button type="button" class="ttos-mini ttos-pick-media" data-target="#' . esc_attr($id) . '" aria-label="' . esc_attr($button . ' for ' . $label) . '">' . esc_html($button) . '</button>';
        echo '<button type="button" class="ttos-mini ttos-clear-media" data-target="#' . esc_attr($id) . '" aria-label="' . esc_attr('Remove ' . $label) . '">Remove</button>';
        echo '</span></div>';
    }

    private static function multi_select(string $label, string $name, array $selected, array $options, string $help = ''): void {
        echo '<label class="ttos-sc-field">' . esc_html($label);
        echo '<select name="' . esc_attr($name) . '[]" multiple size="' . min(8, max(3, count($options))) . '">';
        foreach ($options as $key => $option_label) {
            $is_selected = in_array((int) $key, array_map('intval', $selected), true) ? 'selected' : '';
            echo '<option value="' . esc_attr($key) . '" ' . $is_selected . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        echo '<small>' . esc_html($help ?: 'Hold Cmd/Ctrl to select more than one.') . '</small>';
        echo '</label>';
    }

    private static function product_options(): array {
        if (!post_type_exists('product')) return array();
        $posts = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC'));
        $out = array();
        foreach ($posts as $post) $out[$post->ID] = $post->post_title . ' (#' . $post->ID . ')';
        return $out;
    }

    private static function category_options(): array {
        if (!taxonomy_exists('product_cat')) return array();
        $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        if (is_wp_error($terms)) return array();
        $out = array();
        foreach ($terms as $term) {
            if ($term->slug === 'uncategorized') continue;
            $out[$term->term_id] = $term->name;
        }
        return $out;
    }

    private static function page_options(): array {
        $pages = get_pages(array('post_status' => 'publish'));
        $out = array();
        foreach ((array) $pages as $page) $out[$page->ID] = $page->post_title;
        return $out;
    }

    /* ---- tab renderers --------------------------------------------------- */

    private static function tab_homepage(): void {
        $c = self::get('homepage');
        self::form_open('homepage');

        echo '<section class="ttos-card"><h2>Hero</h2><p class="ttos-muted">The first thing customers see. Leave fields empty to fall back to the business name and neutral copy.</p>';
        echo '<div class="ttos-grid ttos-grid-3">';
        self::text('Eyebrow', 'content[hero_eyebrow]', $c['hero_eyebrow'], 'Short label above the title, e.g. "Fresh pizza · Order direct".');
        self::text('Title', 'content[hero_title]', $c['hero_title'], 'Main headline. Defaults to the business name when empty.');
        self::text('Subtitle', 'content[hero_subtitle]', $c['hero_subtitle'], 'One supporting sentence.');
        echo '</div><div class="ttos-grid ttos-grid-2">';
        self::media('Hero food image', 'content[hero_image_id]', (int) $c['hero_image_id']);
        self::media('Hero background image', 'content[hero_bg_image_id]', (int) $c['hero_bg_image_id']);
        echo '</div><div class="ttos-grid ttos-grid-4">';
        self::text('Primary button text', 'content[primary_cta_text]', $c['primary_cta_text'], 'Defaults to "Order now".');
        self::text('Primary button link', 'content[primary_cta_target]', $c['primary_cta_target'], 'URL or path, e.g. /menu/.');
        self::text('Secondary button text', 'content[secondary_cta_text]', $c['secondary_cta_text'], 'e.g. "Check delivery".');
        self::text('Secondary button link', 'content[secondary_cta_target]', $c['secondary_cta_target'], 'URL or path.');
        echo '</div><div class="ttos-check-grid">';
        self::check('Show pickup/delivery toggle', 'content[show_fulfilment_toggle]', $c['show_fulfilment_toggle']);
        self::check('Show postcode checker', 'content[show_postcode_checker]', $c['show_postcode_checker']);
        self::check('Show open/closed status', 'content[show_open_status]', $c['show_open_status']);
        self::check('Show trust strip', 'content[show_trust_strip]', $c['show_trust_strip'], 'Hygiene rating, Google/TripAdvisor scores, delivery estimates.');
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Featured food</h2><p class="ttos-muted">Pick the dishes or categories to showcase. The homepage shows these instead of the full menu grid.</p>';
        echo '<div class="ttos-grid ttos-grid-2">';
        self::text('Section title', 'content[featured_title]', $c['featured_title'], 'e.g. "House favourites".');
        self::text('Section subtitle', 'content[featured_subtitle]', $c['featured_subtitle']);
        self::multi_select('Featured products', 'content[featured_product_ids]', (array) $c['featured_product_ids'], self::product_options());
        self::multi_select('Featured categories', 'content[featured_category_ids]', (array) $c['featured_category_ids'], self::category_options());
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Why order direct</h2>';
        self::text('Section title', 'content[why_direct_title]', $c['why_direct_title'], 'e.g. "Why order direct?".');
        $cards = array_values((array) $c['why_direct_cards']);
        $rows = max(3, count($cards) + 1);
        echo '<p class="ttos-muted">Up to six cards. Empty rows are removed on save.</p>';
        for ($i = 0; $i < min(6, $rows); $i++) {
            $row = $cards[$i] ?? array('title' => '', 'text' => '');
            echo '<div class="ttos-grid ttos-grid-2 ttos-sc-row">';
            self::text('Card ' . ($i + 1) . ' title', 'content[why_direct_cards][' . $i . '][title]', $row['title']);
            self::text('Card ' . ($i + 1) . ' text', 'content[why_direct_cards][' . $i . '][text]', $row['text']);
            echo '</div>';
        }
        echo '</section>';

        echo '<section class="ttos-card"><h2>About the business</h2><div class="ttos-grid ttos-grid-2"><div>';
        self::text('About title', 'content[about_title]', $c['about_title'], 'e.g. "Family-run since 1998".');
        self::textarea('About text', 'content[about_text]', $c['about_text'], 'A short story about the business. Basic formatting allowed.', 5);
        echo '</div>';
        self::media('About image', 'content[about_image_id]', (int) $c['about_image_id']);
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Booking</h2><p class="ttos-muted">For restaurant + takeaway businesses. This links out — it is not a booking engine.</p>';
        self::check('Show booking section on the homepage', 'content[show_booking]', $c['show_booking']);
        echo '<div class="ttos-grid ttos-grid-2">';
        self::text('Booking title', 'content[booking_title]', $c['booking_title'], 'e.g. "Book a table".');
        self::text('Booking button text', 'content[booking_cta_text]', $c['booking_cta_text'], 'e.g. "Call to book".');
        echo '</div>';
        self::textarea('Booking text', 'content[booking_text]', $c['booking_text'], '', 3);
        self::text('Booking link', 'content[booking_cta_target]', $c['booking_cta_target'], 'URL, tel:01234567890, or https://wa.me/447… link.');
        echo '</section>';

        echo '<section class="ttos-card"><h2>Reviews, newsletter & bottom call-to-action</h2><div class="ttos-check-grid">';
        self::check('Show reviews section', 'content[show_reviews]', $c['show_reviews'], 'Reviews are managed in the Reviews tab.');
        self::check('Show newsletter signup', 'content[show_newsletter]', $c['show_newsletter']);
        echo '</div><div class="ttos-grid ttos-grid-2">';
        self::text('Newsletter title', 'content[newsletter_title]', $c['newsletter_title'], 'e.g. "Get offers first".');
        self::textarea('Newsletter text', 'content[newsletter_text]', $c['newsletter_text'], '', 2);
        echo '</div><div class="ttos-grid ttos-grid-2">';
        self::text('Bottom CTA title', 'content[bottom_cta_title]', $c['bottom_cta_title'], 'e.g. "Hungry now?".');
        self::textarea('Bottom CTA text', 'content[bottom_cta_text]', $c['bottom_cta_text'], '', 2);
        self::text('Bottom CTA button text', 'content[bottom_cta_button_text]', $c['bottom_cta_button_text'], 'Defaults to "Order now".');
        self::text('Bottom CTA link', 'content[bottom_cta_url]', $c['bottom_cta_url'], 'URL or path, e.g. /menu/.');
        echo '</div></section>';

        self::form_close('Save homepage content');
    }

    private static function tab_menu_page(): void {
        $c = self::get('menu_page');
        self::form_open('menu_page');
        echo '<section class="ttos-card"><h2>Menu page heading</h2><p class="ttos-muted">Kept compact — the menu itself is the star.</p><div class="ttos-grid ttos-grid-3">';
        self::text('Eyebrow', 'content[eyebrow]', $c['eyebrow'], 'e.g. "Order direct".');
        self::text('Title', 'content[title]', $c['title'], 'Defaults to "Menu".');
        self::text('Subtitle', 'content[subtitle]', $c['subtitle']);
        echo '</div>';
        self::media('Optional heading background image', 'content[hero_image_id]', (int) $c['hero_image_id']);
        echo '</section>';

        echo '<section class="ttos-card"><h2>Ordering tools</h2><div class="ttos-check-grid">';
        self::check('Show postcode checker', 'content[show_postcode_checker]', $c['show_postcode_checker']);
        self::check('Show menu search', 'content[show_search]', $c['show_search']);
        self::check('Show dietary filters', 'content[show_dietary_filters]', $c['show_dietary_filters'], 'Vegan, vegetarian, halal, spicy badges.');
        self::check('Show allergen filters', 'content[show_allergen_filters]', $c['show_allergen_filters'], 'Requires the allergen filters module.');
        self::check('Show "popular" badges', 'content[show_popular_badges]', $c['show_popular_badges']);
        self::check('Show sticky basket bar', 'content[show_sticky_basket]', $c['show_sticky_basket']);
        echo '</div>';
        self::select('Empty categories on the public menu', 'content[empty_categories]', $c['empty_categories'], array(
            'hide' => 'Hide them completely (recommended)',
            'admin_helper' => 'Hide publicly, show a helper note to logged-in managers',
        ));
        echo '</section>';

        echo '<section class="ttos-card"><h2>Intro & help text</h2>';
        self::textarea('Intro text (above the menu)', 'content[intro_text]', $c['intro_text'], 'Optional. Shown under the heading.', 3);
        self::textarea('Help text (below the menu)', 'content[footer_text]', $c['footer_text'], 'Optional. Good place for allergy or service notes.', 3);
        echo '</section>';
        self::form_close('Save menu page content');
    }

    private static function tab_business_info(): void {
        $c = self::get('business_info');
        self::form_open('business_info');
        echo '<section class="ttos-card"><h2>Business identity</h2><div class="ttos-grid ttos-grid-3">';
        self::text('Business name', 'content[business_name]', $c['business_name'], 'Public name customers see.');
        self::text('Trading name (if different)', 'content[trading_name]', $c['trading_name']);
        self::select('Business type', 'content[business_type]', $c['business_type'], array(
            'takeaway' => 'Takeaway only',
            'restaurant_takeaway' => 'Restaurant + takeaway',
            'collection_only' => 'Collection only',
            'delivery_only' => 'Delivery only',
            'food_truck' => 'Food truck / mobile kitchen',
            'dark_kitchen' => 'Dark kitchen',
        ), 'Controls which sections templates emphasise.');
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Address & contact</h2><div class="ttos-grid ttos-grid-3">';
        self::text('Address line 1', 'content[address_1]', $c['address_1']);
        self::text('Address line 2', 'content[address_2]', $c['address_2']);
        self::text('Town / city', 'content[town]', $c['town']);
        self::text('County', 'content[county]', $c['county']);
        self::text('Postcode', 'content[postcode]', $c['postcode']);
        self::text('Country', 'content[country]', $c['country']);
        self::text('Phone', 'content[phone]', $c['phone'], 'Customers call this number.', 'tel');
        self::text('Email', 'content[email]', $c['email'], '', 'email');
        self::text('WhatsApp number', 'content[whatsapp]', $c['whatsapp'], 'International format, e.g. 447700900123.');
        self::text('Company number', 'content[company_number]', $c['company_number']);
        self::text('VAT number', 'content[vat_number]', $c['vat_number']);
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Ratings & trust</h2><div class="ttos-grid ttos-grid-3">';
        self::text('Food hygiene rating (0–5)', 'content[hygiene_rating]', $c['hygiene_rating']);
        self::text('Hygiene rating link', 'content[hygiene_authority_url]', $c['hygiene_authority_url'], 'Link to ratings.food.gov.uk listing.', 'url');
        echo '<span></span>';
        self::text('Google rating (0–5)', 'content[google_rating]', $c['google_rating']);
        self::text('Google review count', 'content[google_review_count]', $c['google_review_count']);
        self::text('Google profile URL', 'content[google_url]', $c['google_url'], '', 'url');
        self::text('TripAdvisor rating (0–5)', 'content[tripadvisor_rating]', $c['tripadvisor_rating']);
        self::text('TripAdvisor review count', 'content[tripadvisor_review_count]', $c['tripadvisor_review_count']);
        self::text('TripAdvisor profile URL', 'content[tripadvisor_url]', $c['tripadvisor_url'], '', 'url');
        echo '</div></section>';
        self::form_close('Save business info');
    }

    private static function tab_opening_times(): void {
        $c = self::get('opening_times');
        self::form_open('opening_times');
        echo '<section class="ttos-card"><h2>Weekly opening hours</h2><p class="ttos-muted">Times use the 24-hour clock. Leave delivery/collection hours empty when they match the main hours.</p>';
        echo '<div class="ttos-sc-hours" role="group" aria-label="Weekly opening hours">';
        echo '<div class="ttos-sc-hours-head"><span>Day</span><span>Closed</span><span>Open</span><span>Close</span><span>Delivery open</span><span>Delivery close</span><span>Collection open</span><span>Collection close</span><span>Note</span></div>';
        foreach (array('monday' => 'Monday','tuesday' => 'Tuesday','wednesday' => 'Wednesday','thursday' => 'Thursday','friday' => 'Friday','saturday' => 'Saturday','sunday' => 'Sunday') as $key => $label) {
            $day = $c['days'][$key] ?? array();
            $n = 'content[days][' . $key . ']';
            echo '<div class="ttos-sc-hours-row"><span class="ttos-sc-day">' . esc_html($label) . '</span>';
            echo '<span><input type="checkbox" name="' . esc_attr($n) . '[closed]" value="1" ' . checked($day['closed'] ?? '0', '1', false) . ' aria-label="' . esc_attr($label . ' closed') . '"></span>';
            foreach (array('open','close','delivery_open','delivery_close','collection_open','collection_close') as $field) {
                echo '<span><input type="time" name="' . esc_attr($n . '[' . $field . ']') . '" value="' . esc_attr($day[$field] ?? '') . '" aria-label="' . esc_attr($label . ' ' . str_replace('_', ' ', $field)) . '"></span>';
            }
            echo '<span><input type="text" name="' . esc_attr($n . '[note]') . '" value="' . esc_attr($day['note'] ?? '') . '" aria-label="' . esc_attr($label . ' note') . '" placeholder="e.g. Last orders 21:30"></span>';
            echo '</div>';
        }
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Closures & overrides</h2>';
        self::check('Temporary closure', 'content[temporary_closure]', $c['temporary_closure'], 'Shows the closure message instead of normal hours.');
        self::text('Temporary closure message', 'content[temporary_closure_message]', $c['temporary_closure_message'], 'e.g. "Closed for refurbishment until Friday".');
        self::select('Open/closed override', 'content[override]', $c['override'], array(
            'normal'      => 'Normal — follow the weekly hours',
            'force_open'  => 'Force OPEN (ignore hours)',
            'force_closed' => 'Force CLOSED (ignore hours)',
            'preorder'    => 'Pre-order mode — taking orders for later',
        ), 'Use for one-off situations; remember to set it back to normal.');
        echo '</section>';
        self::form_close('Save opening times');
    }

    private static function tab_delivery_collection(): void {
        $c = self::get('delivery_collection');
        self::form_open('delivery_collection');
        echo '<section class="ttos-card"><h2>Service options</h2><p class="ttos-muted">Content and wording only — checkout rules stay in Business Settings → Delivery.</p><div class="ttos-check-grid">';
        self::check('Delivery enabled', 'content[delivery_enabled]', $c['delivery_enabled']);
        self::check('Collection enabled', 'content[collection_enabled]', $c['collection_enabled']);
        self::check('Show "Book a table" tab on homepage', 'content[table_booking_enabled]', $c['table_booking_enabled'] ?? '0', 'Also requires table booking to be enabled in Contact &amp; Map settings.');
        echo '</div>';
        self::select('Default fulfilment', 'content[default_fulfilment]', $c['default_fulfilment'], array('delivery' => 'Delivery', 'collection' => 'Collection'));
        echo '</section>';

        echo '<section class="ttos-card"><h2>Customer-facing wording</h2><div class="ttos-grid ttos-grid-2">';
        self::textarea('Delivery intro', 'content[delivery_intro]', $c['delivery_intro'], '', 3);
        self::textarea('Collection intro', 'content[collection_intro]', $c['collection_intro'], '', 3);
        echo '</div><div class="ttos-grid ttos-grid-2">';
        self::text('Delivery zone summary', 'content[zone_summary]', $c['zone_summary'], 'e.g. "We deliver within 4 miles of MK18".');
        self::text('Minimum order text', 'content[min_order_text]', $c['min_order_text'], 'e.g. "Minimum delivery order £12".');
        self::text('Delivery estimate text', 'content[delivery_estimate_text]', $c['delivery_estimate_text'], 'e.g. "Delivery in 35–50 minutes".');
        self::text('Collection estimate text', 'content[collection_estimate_text]', $c['collection_estimate_text'], 'e.g. "Ready to collect in 20 minutes".');
        self::text('Free delivery text', 'content[free_delivery_text]', $c['free_delivery_text'], 'e.g. "Free delivery over £30".');
        self::text('Service paused message', 'content[paused_message]', $c['paused_message'], 'Shown when ordering is paused.');
        echo '</div></section>';
        self::form_close('Save delivery & collection content');
    }

    private static function tab_contact_map(): void {
        $c = self::get('contact_map');
        self::form_open('contact_map');
        echo '<section class="ttos-card"><h2>Contact page</h2><div class="ttos-grid ttos-grid-2">';
        self::text('Page title', 'content[title]', $c['title'], 'Defaults to "Contact us".');
        echo '</div>';
        self::textarea('Intro text', 'content[intro]', $c['intro'], '', 3);
        echo '</section>';

        echo '<section class="ttos-card"><h2>Map</h2>';
        self::select('Map provider', 'content[map_provider]', $c['map_provider'], array(
            'osm' => 'OpenStreetMap (no API key needed)',
            'google' => 'Google Maps link',
            'none' => 'No map',
        ));
        echo '<div class="ttos-grid ttos-grid-3">';
        self::text('Latitude', 'content[lat]', $c['lat'], 'e.g. 51.9966');
        self::text('Longitude', 'content[lng]', $c['lng'], 'e.g. -0.9879');
        self::text('Google Maps URL', 'content[google_maps_url]', $c['google_maps_url'], 'Share link from Google Maps.', 'url');
        echo '</div><div class="ttos-grid ttos-grid-2">';
        self::text('Parking note', 'content[parking_note]', $c['parking_note'], 'e.g. "Free parking after 6pm on the high street".');
        self::text('Accessibility note', 'content[accessibility_note]', $c['accessibility_note'], 'e.g. "Step-free entrance on Mill Lane".');
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Table booking</h2><p class="ttos-muted">Links customers to phone, WhatsApp or an external booking page. No booking engine.</p>';
        self::check('Booking enabled', 'content[booking_enabled]', $c['booking_enabled']);
        echo '<div class="ttos-grid ttos-grid-3">';
        self::select('Booking type', 'content[booking_type]', $c['booking_type'], array(
            'phone' => 'Phone call', 'whatsapp' => 'WhatsApp', 'url' => 'External booking link', 'note' => 'Information note only',
        ));
        self::text('Booking button text', 'content[booking_cta_text]', $c['booking_cta_text'], 'e.g. "Call to book a table".');
        self::text('Booking target', 'content[booking_target]', $c['booking_target'], 'Phone (tel:…), wa.me link, or URL depending on type.');
        echo '</div>';
        self::text('Booking note', 'content[booking_note]', $c['booking_note'], 'e.g. "Bookings of 6+ by phone only".');
        echo '</section>';
        self::form_close('Save contact & map content');
    }

    private static function tab_reviews(): void {
        $c = self::get('reviews');
        self::form_open('reviews');
        echo '<section class="ttos-card"><h2>Reviews section</h2><div class="ttos-grid ttos-grid-2">';
        self::text('Section title', 'content[title]', $c['title'], 'e.g. "What customers say".');
        self::text('Section subtitle', 'content[subtitle]', $c['subtitle']);
        echo '</div><div class="ttos-check-grid">';
        self::check('Show Google profile link', 'content[show_google_link]', $c['show_google_link'], 'Uses the URL from Business Info.');
        self::check('Show TripAdvisor link', 'content[show_tripadvisor_link]', $c['show_tripadvisor_link']);
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Reviews</h2><p class="ttos-muted">Add reviews manually (with the customer’s permission). Empty rows are removed on save. Up to 12.</p>';
        $items = array_values((array) $c['items']);
        $rows = min(12, max(3, count($items) + 1));
        for ($i = 0; $i < $rows; $i++) {
            $row = $items[$i] ?? array('name' => '', 'rating' => '', 'text' => '', 'source_label' => '', 'source_url' => '', 'featured' => '0');
            $n = 'content[items][' . $i . ']';
            echo '<fieldset class="ttos-sc-repeat"><legend>Review ' . ($i + 1) . '</legend><div class="ttos-grid ttos-grid-4">';
            self::text('Name', $n . '[name]', $row['name']);
            self::text('Rating (0–5)', $n . '[rating]', $row['rating']);
            self::text('Source label', $n . '[source_label]', $row['source_label'], 'e.g. "Google review".');
            self::text('Source URL', $n . '[source_url]', $row['source_url'], '', 'url');
            echo '</div>';
            self::textarea('Review text', $n . '[text]', $row['text'], '', 2);
            self::check('Featured review', $n . '[featured]', $row['featured']);
            echo '</fieldset>';
        }
        echo '</section>';
        self::form_close('Save reviews');
    }

    private static function tab_offers(): void {
        $c = self::get('offers');
        self::form_open('offers');
        echo '<section class="ttos-card"><h2>Offers & promotions</h2><p class="ttos-muted">Promo cards for the homepage and offers areas. These are content cards — they do not create Woo coupons. Empty rows are removed on save. Up to 12.</p>';
        $items = array_values((array) $c['items']);
        $rows = min(12, max(2, count($items) + 1));
        for ($i = 0; $i < $rows; $i++) {
            $row = $items[$i] ?? array('title' => '', 'text' => '', 'image_id' => 0, 'cta_text' => '', 'cta_url' => '', 'start' => '', 'end' => '', 'enabled' => '1');
            $n = 'content[items][' . $i . ']';
            echo '<fieldset class="ttos-sc-repeat"><legend>Offer ' . ($i + 1) . '</legend><div class="ttos-grid ttos-grid-2">';
            self::text('Title', $n . '[title]', $row['title'], 'e.g. "Monday meal deal".');
            self::textarea('Text', $n . '[text]', $row['text'], '', 2);
            echo '</div><div class="ttos-grid ttos-grid-4">';
            self::text('Button text', $n . '[cta_text]', $row['cta_text']);
            self::text('Button link', $n . '[cta_url]', $row['cta_url'], 'URL or path.');
            self::text('Starts', $n . '[start]', $row['start'], 'Optional.', 'datetime-local');
            self::text('Ends', $n . '[end]', $row['end'], 'Optional.', 'datetime-local');
            echo '</div><div class="ttos-grid ttos-grid-2">';
            self::media('Offer image', $n . '[image_id]', (int) $row['image_id']);
            echo '<div class="ttos-sc-field-stack">';
            self::check('Offer enabled', $n . '[enabled]', $row['enabled']);
            echo '</div></div></fieldset>';
        }
        echo '</section>';
        self::form_close('Save offers');
    }

    private static function tab_social_links(): void {
        $c = self::get('social_links');
        self::form_open('social_links');
        echo '<section class="ttos-card"><h2>Social & profile links</h2><p class="ttos-muted">Full URLs including https://. Empty links are hidden on the public site.</p><div class="ttos-grid ttos-grid-2">';
        $labels = array(
            'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp link',
            'google' => 'Google profile', 'tripadvisor' => 'TripAdvisor', 'twitter' => 'X / Twitter', 'youtube' => 'YouTube',
        );
        foreach ($labels as $key => $label) {
            self::text($label, 'content[' . $key . ']', $c[$key], '', 'url');
        }
        echo '</div></section>';
        self::form_close('Save social links');
    }

    private static function tab_footer(): void {
        $c = self::get('footer');
        self::form_open('footer');
        echo '<section class="ttos-card"><h2>Footer content</h2><div class="ttos-grid ttos-grid-2"><div>';
        self::textarea('Footer text', 'content[text]', $c['text'], 'Short line under the business name.', 2);
        echo '</div>';
        self::media('Footer logo (optional)', 'content[logo_id]', (int) $c['logo_id']);
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Footer sections</h2><div class="ttos-check-grid">';
        self::check('Show contact info', 'content[show_contact]', $c['show_contact']);
        self::check('Show opening times', 'content[show_opening_times]', $c['show_opening_times']);
        self::check('Show social links', 'content[show_social]', $c['show_social']);
        self::check('Show hygiene rating', 'content[show_hygiene]', $c['show_hygiene']);
        self::check('Show Google link', 'content[show_google]', $c['show_google']);
        self::check('Show TripAdvisor link', 'content[show_tripadvisor]', $c['show_tripadvisor']);
        self::check('Show legal links', 'content[show_legal_links]', $c['show_legal_links']);
        self::check('Show allergen link', 'content[show_allergen_link]', $c['show_allergen_link']);
        self::check('Show accessibility link', 'content[show_accessibility_link]', $c['show_accessibility_link']);
        echo '</div></section>';

        echo '<section class="ttos-card"><h2>Built-by credit</h2>';
        self::check('Show built-by credit', 'content[show_built_by]', $c['show_built_by'], 'Admin-controlled. Off hides it completely.');
        echo '<div class="ttos-grid ttos-grid-2">';
        self::text('Credit text', 'content[built_by_text]', $c['built_by_text']);
        self::text('Credit URL', 'content[built_by_url]', $c['built_by_url'], '', 'url');
        echo '</div></section>';
        self::form_close('Save footer content');
    }

    private static function tab_policies(): void {
        $c = self::get('policies');
        self::form_open('policies');
        echo '<div class="ttos-callout ttos-callout-warning"><strong>Starter content only. Review before production.</strong> These texts are generic starting points — they are not legal advice. Phase 6 generates the public policy pages from these fields; nothing is published automatically yet.</div>';
        $labels = array(
            'privacy' => 'Privacy Policy', 'cookies' => 'Cookie Policy', 'terms' => 'Terms & Conditions',
            'refunds' => 'Refunds & Cancellations', 'delivery' => 'Delivery Policy', 'allergens' => 'Allergen Information',
            'accessibility' => 'Accessibility Statement', 'hygiene' => 'Food Hygiene & Safety', 'contact_details' => 'Contact & Business Details',
        );
        foreach ($labels as $key => $label) {
            $row = isset($c[$key]) && is_array($c[$key]) ? $c[$key] : array('title' => $label, 'content' => '');
            echo '<section class="ttos-card"><h2>' . esc_html($label) . '</h2>';
            self::text('Page title', 'content[' . $key . '][title]', $row['title']);
            self::textarea('Content', 'content[' . $key . '][content]', $row['content'], 'Plain paragraphs. Business details are merged in automatically where templates support it.', 6);
            echo '</section>';
        }
        self::form_close('Save policies');
    }

    private static function tab_banner(): void {
        $c = self::get('banner');
        self::form_open('banner');
        echo '<section class="ttos-card"><h2>Top banner</h2><p class="ttos-muted">Sitewide announcement bar for holiday hours, delays or offers. Configuration only in this phase — the public banner renders from Phase 7 and stays hidden while disabled.</p>';
        self::check('Banner enabled', 'content[enabled]', $c['enabled']);
        echo '<div class="ttos-grid ttos-grid-2">';
        self::text('Title', 'content[title]', $c['title'], 'e.g. "Christmas opening hours".');
        self::select('Style', 'content[style]', $c['style'], array('info' => 'Info', 'warning' => 'Warning', 'offer' => 'Offer', 'closed' => 'Closed'));
        echo '</div>';
        self::textarea('Message', 'content[message]', $c['message'], '', 2);
        echo '<div class="ttos-grid ttos-grid-4">';
        self::text('Button text', 'content[cta_text]', $c['cta_text']);
        self::text('Button link', 'content[cta_url]', $c['cta_url'], 'URL or path.');
        self::text('Starts', 'content[start]', $c['start'], 'Empty = immediately.', 'datetime-local');
        self::text('Ends', 'content[end]', $c['end'], 'Empty = until disabled.', 'datetime-local');
        echo '</div>';
        self::select('Show on', 'content[pages]', $c['pages'], array('all' => 'All pages', 'selected' => 'Selected pages only'));
        self::multi_select('Selected pages', 'content[page_ids]', (array) $c['page_ids'], self::page_options(), 'Only used when "Selected pages only" is chosen.');
        echo '<div class="ttos-check-grid">';
        self::check('Dismissible', 'content[dismissible]', $c['dismissible']);
        self::check('Remember dismissal', 'content[remember_dismissal]', $c['remember_dismissal'], 'Customers who close it will not see it again until it changes.');
        echo '</div></section>';
        self::form_close('Save banner settings');
    }

    private static function tab_popup(): void {
        $c = self::get('popup');
        self::form_open('popup');
        echo '<section class="ttos-card"><h2>Popup</h2><p class="ttos-muted">Native popup for offers and notices. Configuration only in this phase — public rendering arrives in Phase 7 and stays hidden while disabled. Popups never block checkout unless explicitly allowed below.</p>';
        self::check('Popup enabled', 'content[enabled]', $c['enabled']);
        echo '<div class="ttos-grid ttos-grid-2">';
        self::select('Type', 'content[type]', $c['type'], array('offer' => 'Offer', 'notice' => 'Notice', 'newsletter' => 'Newsletter', 'closure' => 'Closure', 'loyalty' => 'Loyalty'));
        self::text('Title', 'content[title]', $c['title']);
        echo '</div>';
        self::textarea('Message', 'content[message]', $c['message'], '', 3);
        echo '<div class="ttos-grid ttos-grid-2">';
        self::media('Popup image', 'content[image_id]', (int) $c['image_id']);
        echo '<div class="ttos-sc-field-stack">';
        self::text('Button text', 'content[cta_text]', $c['cta_text']);
        self::text('Button link', 'content[cta_url]', $c['cta_url'], 'URL or path.');
        echo '</div></div><div class="ttos-grid ttos-grid-4">';
        self::text('Starts', 'content[start]', $c['start'], 'Empty = immediately.', 'datetime-local');
        self::text('Ends', 'content[end]', $c['end'], 'Empty = until disabled.', 'datetime-local');
        self::text('Delay (seconds)', 'content[delay]', $c['delay'], 'Wait before showing. 0–120.', 'number');
        self::select('Frequency', 'content[frequency]', $c['frequency'], array('session' => 'Once per session', 'day' => 'Once per day', 'week' => 'Once per week'));
        echo '</div>';
        self::multi_select('Target pages (empty = all pages)', 'content[page_ids]', (array) $c['page_ids'], self::page_options());
        echo '<div class="ttos-check-grid">';
        self::check('Dismissible', 'content[dismissible]', $c['dismissible']);
        self::check('Allow on checkout', 'content[allow_on_checkout]', $c['allow_on_checkout'], 'Off by default — popups should never block checkout.');
        echo '</div></section>';
        self::form_close('Save popup settings');
    }

    private static function tab_export_import(): void {
        echo '<section class="ttos-card"><h2>Export</h2><p class="ttos-muted">Downloads Branding and Site Content as one versioned JSON file. Use it to copy a setup between sites or keep a content backup.</p>';
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=ttos_export_site_content'), 'ttos_export_site_content');
        echo '<p><a class="ttos-button" href="' . esc_url($export_url) . '">Download export (JSON)</a></p></section>';

        $pending = get_transient(self::import_transient_key());
        if (is_array($pending)) {
            echo '<section class="ttos-card"><h2>Import preview</h2>';
            echo '<p>File from <strong>' . esc_html((string) ($pending['site'] ?? 'unknown site')) . '</strong>, exported ' . esc_html((string) ($pending['exported_at'] ?? 'unknown date')) . ', structure version ' . esc_html((string) ($pending['version'] ?? '?')) . '.</p>';
            echo '<ul class="ttos-list">';
            echo '<li><span>Branding</span><strong>' . (isset($pending['branding']) && is_array($pending['branding']) ? count($pending['branding']) . ' fields' : 'not included') . '</strong></li>';
            if (isset($pending['site_content']) && is_array($pending['site_content'])) {
                foreach (self::tabs() as $key => $label) {
                    if ($key === 'export_import') continue;
                    $included = isset($pending['site_content'][$key]) && is_array($pending['site_content'][$key]);
                    echo '<li><span>' . esc_html($label) . '</span><strong>' . ($included ? 'included' : '—') . '</strong></li>';
                }
            } else {
                echo '<li><span>Site content</span><strong>not included</strong></li>';
            }
            echo '</ul>';
            echo '<p class="ttos-muted"><strong>Confirming replaces the matching sections on this site.</strong> Sections not in the file are left untouched. This cannot be undone from the screen — export first if unsure.</p>';
            echo '<div class="ttos-installer-actions"><form method="post" style="display:inline">';
            wp_nonce_field('ttos_import_site_content_confirm');
            echo '<input type="hidden" name="ttos_action" value="import_site_content_confirm"><button class="ttos-button">Confirm import</button></form> ';
            echo '<form method="post" style="display:inline">';
            wp_nonce_field('ttos_import_site_content_cancel');
            echo '<input type="hidden" name="ttos_action" value="import_site_content_cancel"><button class="ttos-mini">Cancel</button></form></div></section>';
        }

        echo '<section class="ttos-card"><h2>Import</h2><p class="ttos-muted">Upload a Takeaway site content JSON export. You will see a preview and must confirm before anything is replaced.</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('ttos_import_site_content_upload');
        echo '<input type="hidden" name="ttos_action" value="import_site_content_upload">';
        echo '<label class="ttos-sc-field">Export file (.json)<input type="file" name="ttos_import_file" accept="application/json,.json" required></label>';
        echo '<p><button class="ttos-button">Upload & preview</button></p></form></section>';
    }
}

/* -------------------------------------------------------------------------
 * Template helper functions
 * ----------------------------------------------------------------------- */

if (!function_exists('ttos_get_site_content')) {
    function ttos_get_site_content(): array {
        return class_exists('TTOS_Site_Content') ? TTOS_Site_Content::get() : array();
    }
}

if (!function_exists('ttos_get_site_content_value')) {
    function ttos_get_site_content_value(string $section, string $key, $default = null) {
        if (!class_exists('TTOS_Site_Content')) return $default;
        $value = TTOS_Site_Content::get($section, $key, $default);
        return ($value === '' || $value === null) ? $default : $value;
    }
}

if (!function_exists('ttos_get_business_type')) {
    function ttos_get_business_type(): string {
        return (string) ttos_get_site_content_value('business_info', 'business_type', 'takeaway');
    }
}

if (!function_exists('ttos_get_opening_hours')) {
    function ttos_get_opening_hours(): array {
        return class_exists('TTOS_Site_Content') ? (array) TTOS_Site_Content::get('opening_times') : array();
    }
}
