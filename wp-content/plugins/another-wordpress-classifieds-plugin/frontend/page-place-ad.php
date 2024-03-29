<?php

require_once(AWPCP_DIR . '/includes/helpers/page.php');


/**
 * @since  2.1.4
 */
class AWPCP_Place_Ad_Page extends AWPCP_Page {

    protected $context = 'place-ad';

    public $messages = array();

    protected $authorization;

    public function __construct($page='awpcp-place-ad', $title=null) {
        parent::__construct($page, $title);

        $this->authorization = awpcp_listing_authorization();
    }

    public function get_current_action($default=null) {
        return awpcp_post_param('step', awpcp_request_param('step', $default));
    }

    public function url($params=array()) {
        $url = parent::url($params);
        // Payments API redirects to this page including this two parameters.
        // Those URL paramters are necessary only to *arrive* to the Payment
        // Completed step page for the first time. The same parameters are
        // then passed in the POST requests.
        return remove_query_arg(array('step', 'transaction_id'), $url);
    }

    public function transaction_error() {
        return __('There was an error processing your Payment Request. Please try again or contact an Administrator.', 'another-wordpress-classifieds-plugin');
    }

    public function get_transaction($create=false) {
        if ( $create ) {
            $this->transaction = awpcp_payments_api()->get_or_create_transaction();
        } else {
            $this->transaction = awpcp_payments_api()->get_transaction();
        }

        if (!is_null($this->transaction) && $this->transaction->is_new()) {
            $this->transaction->user_id = wp_get_current_user()->ID;
            $this->transaction->set('context', $this->context);
            $this->transaction->set('redirect', $this->url());
            $this->transaction->set('redirect-data', array('step' => 'payment-completed'));
            $this->transaction->set( 'user-just-logged-in', awpcp_request_param( 'loggedin', false ) );
        }

        return $this->transaction;
    }

    protected function get_preview_hash($ad) {
        return wp_create_nonce( "preview-ad-{$ad->ad_id}" );
    }

    protected function verify_preview_hash($ad) {
        return wp_verify_nonce( awpcp_post_param( 'preview-hash' ), "preview-ad-{$ad->ad_id}" );
    }

    protected function is_user_allowed_to_edit($ad) {
        if ( $this->authorization->is_current_user_allowed_to_edit_listing( $ad ) ) {
            return true;
        }

        if ( $this->request_includes_authorized_hash( $ad ) ) {
            return true;
        }

        return false;
    }

    protected function request_includes_authorized_hash( $ad ) {
        return $this->verify_preview_hash( $ad );
    }

    public function dispatch($default=null) {
        do_action( 'awpcp-before-post-listing-page' );

        wp_enqueue_style('awpcp-jquery-ui');
        wp_enqueue_script('awpcp-page-place-ad');

        $awpcp = awpcp();

        $awpcp->js->localize( 'page-place-ad-order', array(
            'category' => __( 'Please select a category.', 'another-wordpress-classifieds-plugin' ),
            'user' => __( "Please select the Ad's owner.", 'another-wordpress-classifieds-plugin' ),
            'payment_term' => __( 'Please select a payment term.', 'another-wordpress-classifieds-plugin' ),
        ) );

        $awpcp->js->localize( 'page-place-ad-details', array(
            'ad_title' => __( 'Please type in a title for your Ad.', 'another-wordpress-classifieds-plugin' ),
            'websiteurl' => __( 'Please type in a valid URL.', 'another-wordpress-classifieds-plugin' ),
            'ad_contact_name' => __( 'Please type in the name of the person to contact.', 'another-wordpress-classifieds-plugin' ),
            'ad_contact_email' => __( 'Please type in the email address of the person to contact.', 'another-wordpress-classifieds-plugin' ),
            'ad_contact_phone' => __( 'Please type in the phone number of the person to contact.', 'another-wordpress-classifieds-plugin' ),
            'ad_country' => __( 'The country is a required field.', 'another-wordpress-classifieds-plugin' ),
            'ad_county_village' => __( 'The county is a required field.', 'another-wordpress-classifieds-plugin' ),
            'ad_state' => __( 'The state is a required field.', 'another-wordpress-classifieds-plugin' ),
            'ad_city' => __( 'The city is a required field.', 'another-wordpress-classifieds-plugin' ),
            'ad_item_price' => __( 'Please type in a price for your Ad.', 'another-wordpress-classifieds-plugin' ),
            'ad_details' => __( 'Please type in the details of your Ad.', 'another-wordpress-classifieds-plugin' ),
            'captcha' => __( 'Please type in the result of the operation.', 'another-wordpress-classifieds-plugin' ),
        ) );

        if (is_admin()) {
            echo $this->_dispatch($default);
        } else {
            return $this->_dispatch($default);
        }
    }

    protected function _dispatch($default=null) {
        $is_admin_user = awpcp_current_user_is_admin();

        // only admin users are allowed to place Ads
        if (get_awpcp_option('onlyadmincanplaceads') && ($is_admin_user != 1)) {
            $message = __("You do not have permission to perform the function you are trying to perform. Access to this page has been denied",'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        // only registered users are allowed to place Ads
        if (get_awpcp_option('requireuserregistration') && !is_user_logged_in()) {
            return $this->login_step();
        }

        $transaction = $this->get_transaction();

        if (!is_null($transaction) && $transaction->get('context') != $this->context) {
            $page_name = awpcp_get_page_name('place-ad-page-name');
            $page_url = awpcp_get_page_url('place-ad-page-name');
            $message = __('You are trying to post an Ad using a transaction created for a different purpose. Pelase go back to the <a href="%s">%s</a> page.<br>If you think this is an error please contact the administrator and provide the following transaction ID: %s', 'another-wordpress-classifieds-plugin');
            $message = sprintf($message, $page_url, $page_name, $transaction->id);
            return $this->render('content', awpcp_print_error($message));
        }

        $action = $this->get_current_action($default);

        if (!is_null($transaction) && $transaction->is_payment_completed()) {
            if (!($transaction->was_payment_successful() || $transaction->payment_is_canceled())) {
                $message = __('You can\'t post an Ad at this time because the payment associated with this transaction failed (see reasons below).', 'another-wordpress-classifieds-plugin');
                $message = awpcp_print_message($message);
                $message = $message . awpcp_payments_api()->render_transaction_errors($transaction);
                return $this->render('content', $message);
            }

            $pay_first = get_awpcp_option('pay-before-place-ad');
            $forbidden = in_array($action, array('order', 'checkout'));
            if ( $forbidden ) {
                $action = 'payment-completed';
            }
        }

        if (!is_null($transaction) && $transaction->is_completed()) {
            $action = 'finish';
        }

        switch ($action) {
            case 'order':
                return $this->order_step();
            case 'checkout':
                return $this->checkout_step();
            case 'payment-completed':
                return $this->payment_completed_step();
            case 'details':
            case 'save-details':
                return $this->details_step();
                break;
            case 'upload-images':
                return $this->upload_images_step();
                break;
            case 'preview-ad':
                return $this->preview_step();
                break;
            case 'finish':
                return $this->finish_step();
                break;
            default:
                return $this->place_ad();
        }
    }

    public function place_ad() {
        return $this->order_step();
    }

    /**
     * @since 3.0.2
     */
    protected function get_required_fields() {
        $required['start-date'] = false;
        $required['end-date'] = false;
        $required['ad-title'] = true;
        $required['website-url'] = get_awpcp_option( 'displaywebsitefieldreqop' );
        $required['ad-contact-name'] = true;
        $required['ad-contact-email'] = true;
        $required['ad-contact-phone'] = get_awpcp_option( 'displayphonefieldreqop' );
        $required['ad-item-price'] = get_awpcp_option( 'displaypricefieldreqop' );
        $required['ad-details'] = true;
        $required['country'] = get_awpcp_option( 'displaycountryfieldreqop' );
        $required['state'] = get_awpcp_option( 'displaystatefieldreqop' );
        $required['county'] = get_awpcp_option( 'displaycountyvillagefieldreqop' );
        $required['city'] = get_awpcp_option( 'displaycityfieldreqop' );
        $required['terms-of-service'] = true;

        return $required;
    }

    protected function validate_order($data, &$errors=array()) {
        if ($data['category'] <= 0) {
            $errors['category'] = __('Ad Category field is required', 'another-wordpress-classifieds-plugin');
        }

        try {
            $category = awpcp_categories_collection()->get( $data['category'] );
            $category_name = $category->name;
        } catch ( AWPCP_Exception $e ) {
            $category_name = 'Unknown';
        }

        if (get_awpcp_option('noadsinparentcat') && !category_is_child($data['category'])) {
            $message = __("You cannot list your Ad in top level categories. You need to select a sub-category of category %s.", 'another-wordpress-classifieds-plugin');
            $errors['category'] = sprintf( $message, $category_name );
        }

        if (awpcp_current_user_is_moderator() && empty($data['user'])) {
            $errors['user'] = __('You should select an owner for this Ad.', 'another-wordpress-classifieds-plugin');
        }

        if (is_null($data['term'])) {
            $errors['payment-term'] = __('You should choose one of the available Payment Terms.', 'another-wordpress-classifieds-plugin');
        }

        if (!empty($data['term']->categories) && !in_array($data['category'], $data['term']->categories)) {
            $message = __('The Payment Term you selected is not valid for the category %s', 'another-wordpress-classifieds-plugin');
            $errors['payment-term'] = sprintf( $message, $category_name );
        }

        if ( ! awpcp_current_user_is_admin() && ! is_null( $data['term'] ) && $data['term']->private ) {
            $message = __( 'The Payment Term you selected is not available for non-administrator users.', 'another-wordpress-classifieds-plugin' );
            $errors['payment-term'] = $message;
        }

        $additional_errors = apply_filters( 'awpcp-validate-post-listing-order', array(), $data );

        array_splice( $errors, count( $errors ), 0, $additional_errors );
    }

    public function login_step() {
        $message = __('Hi, You need to be a registered user to post Ads in this website. Please use the form below to login or click the link to register.', 'another-wordpress-classifieds-plugin');

        $params = array(
            'message' => $message,
            'page_url' => add_query_arg( 'loggedin', true, awpcp_get_page_url( 'place-ad-page-name' ) ),
        );

        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-login-step.tpl.php';

        return $this->render( $template, $params );
    }

    public function order_step() {
        $form_errors = array();
        $transaction_errors = array();

        $pay_first = get_awpcp_option('pay-before-place-ad');
        $skip_payment_term_selection = false;

        $payments = awpcp_payments_api();
        $_payment_terms = $payments->get_payment_terms();

        // validate submitted data and set relevant transaction attributes
        if (!empty($_POST)) {
            $transaction = $this->get_transaction(true);

            if ($transaction->is_new()) {
                $payments->set_transaction_status_to_open($transaction, $transaction_errors);
            }

            $skip_payment_term_selection = $transaction->get( 'skip-payment-term-selection' );

            $user = awpcp_post_param( 'user', intval( $transaction->user_id ) );
            $category = awpcp_post_param( 'category', $transaction->get('category', 0) );

            if ( $skip_payment_term_selection ) {
                $payment_terms = null;
                $term = $payments->get_transaction_payment_term($transaction);
                $payment_type = $transaction->get( 'payment-term-payment-type' );
            } else {
                $payment_terms = new AWPCP_PaymentTermsTable( $_payment_terms, $transaction->get('payment-term') );
                $term = $payment_terms->get_payment_term($payment_type, $selected);
            }

            $this->validate_order(compact('user', 'category', 'term'), $form_errors);

            if (empty($form_errors) && empty($transaction_errors)) {
                $transaction->user_id = $user;
                $transaction->set('category', $category);

                if ( ! $skip_payment_term_selection ) {
                    $transaction->set( 'payment-term', $selected );
                    $transaction->set( 'payment-term-type', $term->type );
                    $transaction->set( 'payment-term-id', $term->id );
                    $transaction->set( 'payment-term-payment-type', $payment_type );

                    $transaction->remove_all_items();
                    $payment_terms->set_transaction_item( $transaction );

                    // process transaction to grab Credit Plan information
                    $payments->set_transaction_credit_plan($transaction);
                }
            }

            // Ignore errors if category and user parameters were not sent. This
            // happens every time someone tries to place an Ad starting in the
            // Buy Subscription page.
            if ( $skip_payment_term_selection && ! isset( $_POST['category'] ) ) {
                unset( $form_errors['category'] );
            }
            if ( $skip_payment_term_selection && ! isset( $_POST['user'] ) ) {
                unset( $form_errors['user'] );
            }

            // let other parts of the plugin know a transaction is being processed
            $payments->process_transaction($transaction);
        } else {
            $transaction = null;

            $payment_terms = new AWPCP_PaymentTermsTable($_payment_terms);

            $user = wp_get_current_user()->ID;
            $category = 0;
            $term = null;
        }


        // are we done here? what next?
        if ($category > 0 && !is_null($term)) {
            if (empty($form_errors) && empty($transaction_errors)) {
                $payments->set_transaction_status_to_ready_to_checkout($transaction, $transaction_errors);

                if ($pay_first && empty($transaction_errors)) {
                    return $this->checkout_step();
                } else if (empty($transaction_errors)) {
                    return $this->details_step();
                }
            }
        }


        // display initial form and show errors, if any
        $messages = $this->messages;
        if (awpcp_current_user_is_admin()) {
            $messages[] = __("You are logged in as an administrator. Any payment steps will be skipped.", 'another-wordpress-classifieds-plugin');
        }

        $params = array(
            'page' => $this,
            'payments' => $payments,
            'table' => $payment_terms,
            'transaction' => $transaction,

            'skip_payment_term_selection' => $skip_payment_term_selection,

            'categories' => awpcp_get_categories(),
            'form' => compact('category', 'user'),

            'messages' => $messages,
            'form_errors' => $form_errors,
            'transaction_errors' => $transaction_errors
        );

        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-order-step.tpl.php';

        return $this->render($template, $params);
    }

    public function checkout_step() {
        $transaction = $this->get_transaction();
        $payments = awpcp_payments_api();

        $errors = array();

        // verify transaction pre-conditions

        if (is_null($transaction)) {
            $message = $this->transaction_error();
            return $this->render('content', awpcp_print_error($message));
        }

        if ($transaction->is_payment_completed()) {
            return $this->payment_completed_step();
        }

        if ( $transaction->is_ready_to_checkout() ) {
            $payments->set_transaction_status_to_checkout( $transaction, $errors );
        }

        if ( empty( $errors ) && $transaction->payment_is_not_required() ) {
            $payments->set_transaction_status_to_payment_completed($transaction, $errors);

            if ( empty( $errors ) ) {
                return $this->payment_completed_step();
            }
        }

        if ( !$transaction->is_doing_checkout() && !$transaction->is_processing_payment() ) {
            $message = __('We can\'t process payments for this Payment Transaction at this time. Please contact the website administrator and provide the following transaction ID: %s', 'another-wordpress-classifieds-plugin');
            $message = sprintf($message, $transaction->id);
            return $this->render('content', awpcp_print_error($message));
        }


        // proceess transaction to grab Payment Method information
        $payments->set_transaction_payment_method($transaction);


        // show checkout page.

        // If a Payment Method was already selected, the Payments API already
        // processed the transaction and will (depending of the Payment Method):
        // show a checkout button, show a billing information form or
        // automatically redirect the user to the payment gateway.

        $params = array(
            'payments' => $payments,
            'transaction' => $transaction,
            'messages' => $this->messages,
            'hidden' => array('step' => 'checkout')
        );

        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-checkout-step.tpl.php';

        return $this->render($template, $params);
    }

    public function payment_completed_step() {
        $transaction = $this->get_transaction();
        $payments = awpcp_payments_api();
        $pay_first = get_awpcp_option('pay-before-place-ad');

        if ($pay_first && $transaction->payment_is_not_required()) {
            return $this->details_step();
        } else if ($transaction->payment_is_not_required()) {
            return $this->finish_step();
        }

        $params = array(
            'payments' => $payments,
            'transaction' => $transaction,
            'messages' => $this->messages,
            'url' => $this->url(),
            'hidden' => array('step' => $pay_first ? 'details' : 'finish')
        );

        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-payment-completed-step.tpl.php';

        return $this->render($template, $params);
    }

    protected function get_ad_info($ad_id) {
        global $wpdb, $hasextrafieldsmodule;

        $fields = array(
            'ad_id',
            'user_id',
            'adterm_id',
            'ad_title',
            'ad_contact_name',
            'ad_contact_email',
            'ad_category_id',
            'ad_contact_phone',
            'ad_item_price',
            'ad_details',
            'websiteurl',
            'ad_startdate',
            'ad_enddate',
            'ad_key',
        );

        if ($hasextrafieldsmodule) {
            foreach (x_fields_fetch_fields() as $field) {
                $fields[] = "`$field` AS `awpcp-$field`";
            }
        }

        $query = "SELECT " . join(', ', $fields) . " ";
        $query.= "FROM " . AWPCP_TABLE_ADS . " ";
        $query.= "WHERE ad_id=%d";

        $data = $wpdb->get_row( $wpdb->prepare( $query, (int) $ad_id ), ARRAY_A );

        if ( get_awpcp_option('allowhtmlinadtext') ) {
            $data['ad_details'] = awpcp_esc_textarea( $data['ad_details'] );
        }

        // please note we are dividing the Ad price by 100
        // Ad prices have been historically stored in cents
        $data['ad_category'] = $data['ad_category_id'];
        $data['ad_item_price'] = $data['ad_item_price'] / 100;
        $data['start_date'] = $data['ad_startdate'];
        $data['end_date'] = $data['ad_enddate'];

        $data['regions'] = AWPCP_Ad::get_ad_regions( $ad_id );

        return $data;
    }

    protected function get_user_info($user_id=false) {
        $user_id = $user_id === false ? get_current_user_id() : $user_id;

        $data = awpcp_users_collection()->find_by_id(
            $user_id,
            array(
                'ID', 'user_login', 'user_email', 'user_url', 'display_name',
                'public_name', 'first_name', 'last_name', 'nickname', 'awpcp-profile'
            )
        );

        $translations = array(
            'ad_contact_name' => 'public_name',
            'ad_contact_email' => 'user_email',
            'ad_contact_phone' => 'phone',
            'websiteurl' => 'user_url',
            'ad_country' => 'country',
            'ad_state' => 'state',
            'ad_city' => 'city',
            'ad_county_village' => 'county',
        );

        $info = array();

        foreach ( $translations as $field => $key ) {
            if ( isset( $data->$key ) && !empty( $data->$key ) ) {
                $info[ $field ] = $data->$key;
            }
        }

        if ( empty( $info['ad_contact_name'] ) ) {
            $info['ad_contact_name'] = trim( $data->first_name . " " . $data->last_name );
        }

        $user_region = array_filter( array(
            'country' => awpcp_array_data( 'ad_country', '', $info ),
            'state' => awpcp_array_data( 'ad_state', '', $info ),
            'city' => awpcp_array_data( 'ad_city', '', $info ),
            'county' => awpcp_array_data( 'ad_county_village', '', $info ),
        ), 'strlen' );

        if ( ! empty( $user_region ) ) {
            $info['regions'][] = $user_region;
        }

        $info = apply_filters( 'awpcp-listing-details-user-info', $info, $user_id );

        return $info;
    }

    protected function get_characters_allowed($ad_id, $transaction=null) {
        $max_characters_in_title = false;
        $remaining_characters_in_title = false;
        $remaining_characters_in_body = false;
        $max_characters_in_body = false;

        if ($ad = AWPCP_Ad::find_by_id($ad_id)) {
            $max_characters_in_title = $ad->get_characters_allowed_in_title();
            $remaining_characters_in_title = $ad->get_remaining_characters_in_title();
            $max_characters_in_body = $ad->get_characters_allowed();
            $remaining_characters_in_body = $ad->get_remaining_characters_count();

        } else if (!is_null($transaction)) {
            $term = awpcp_payments_api()->get_transaction_payment_term($transaction);
            if ($term) {
                $max_characters_in_title = $remaining_characters_in_title = $term->get_characters_allowed_in_title();
                $max_characters_in_body = $remaining_characters_in_body = $term->get_characters_allowed();
            } else {
                $max_characters_in_title = $remaining_characters_in_title = 0;
                $max_characters_in_body = $remaining_characters_in_body = get_awpcp_option('maxcharactersallowed');
            }
        }

        return array(
            'characters_allowed_in_title' => $max_characters_in_title,
            'remaining_characters_in_title' => $remaining_characters_in_title,
            'characters_allowed' => $max_characters_in_body,
            'remaining_characters' => $remaining_characters_in_body,
        );
    }

    protected function get_regions_allowed( $ad_id, $transaction=null ) {
        $regions_allowed = 1;

        if ( $ad = AWPCP_Ad::find_by_id( $ad_id ) ) {
            $regions_allowed = $ad->get_regions_allowed();
        } else if ( ! is_null( $transaction ) ) {
            $term = awpcp_payments_api()->get_transaction_payment_term( $transaction );
            if ( $term ) {
                $regions_allowed = $term->get_regions_allowed();
            }
        }

        return $regions_allowed;
    }

    protected function get_posted_details($from, $transaction=null) {
        $defaults = array(
            'user_id' => '',

            'ad_id' => '',
            'adterm_id' => '',
            'ad_category' => '',
            'ad_title' => '',
            'ad_contact_name' => '',
            'ad_contact_phone' => '',
            'ad_contact_email' => '',
            'websiteurl' => '',
            'ad_item_price' => '',
            'ad_details' => '',
            'ad_payment_term' => '',
            'is_featured_ad' => '',

            'regions' => array(),

            'start_date' => '',
            'end_date' => '',

            'characters_allowed' => '',
            'remaining_characters' => '',

            'user_payment_term' => '',

            'terms-of-service' => '',
        );

        $data = array();
        foreach ($defaults as $name => $default) {
            $value = awpcp_array_data( $name, $default, $from );
            $value = stripslashes_deep( $value );

            if ( $name != 'ad_details' ) {
                $value = awpcp_strip_all_tags_deep( $value );
            }

            $data[ $name ] = $value;
        }

        $data['websiteurl'] = awpcp_maybe_add_http_to_url( $data['websiteurl'] );

        if (empty($data['user_id'])) {
            $data['user_id'] = (int) awpcp_array_data('user', 0, $from);
        }

        if (!is_null($transaction)) {
            $data['ad_category'] = $transaction->get('category', $data['ad_category']);
            $data['user_id'] = (int) awpcp_get_property($transaction, 'user_id', $data['user_id']);

            $payment_term_type = $transaction->get('payment-term-type');
            $payment_term_id = $transaction->get('payment-term-id');
            if (!empty($payment_term_type) && !empty($payment_term_id)) {
                $data['user_payment_term'] = "{$payment_term_type}-{$payment_term_id}";
                $data['ad_payment_term'] = "{$payment_term_type}-{$payment_term_id}";
            }

            $data['transaction_id'] = $transaction->id;
        }

        // parse the value provided by the user and convert it to a float value
        $data['ad_item_price'] = awpcp_parse_money( $data['ad_item_price'] );

        $data['is_featured_ad'] = absint($data['is_featured_ad']);

        $data = apply_filters( 'awpcp-get-posted-data', $data, 'details', $from );

        return $data;
    }

    public function details_form($form=array(), $edit=false, $hidden=array(), $required=array(), $errors=array()) {
        global $hasregionsmodule, $hasextrafieldsmodule;

        $is_admin_user = awpcp_current_user_is_admin();
        $is_moderator = awpcp_current_user_is_moderator();
        $payments_enabled = get_awpcp_option('freepay') == 1;
        $pay_first = get_awpcp_option('pay-before-place-ad');

        $messages = $this->messages;

        if ( $edit ) {
            $messages[] = __("Your Ad details have been filled out in the form below. Make any changes needed and then resubmit the Ad to update it.", 'another-wordpress-classifieds-plugin');
        } else if ($is_admin_user) {
            $messages[] = __("You are logged in as an administrator. Any payment steps will be skipped.", 'another-wordpress-classifieds-plugin');
        } else if (empty($errors)) {
            $messages[] = __("Fill out the form below to post your classified Ad.", 'another-wordpress-classifieds-plugin');
        }

        if (!empty($errors)) {
            $message = __( "We found errors in the details you submitted. A detailed error message is shown in front or below each invalid field. Please fix the errors and submit the form again.", 'another-wordpress-classifieds-plugin' );
            $errors = array_merge(array($message), $errors);
        }

        $ui = array();
        // TODO: add form validation
        // TODO: strip slashes from title, details
        $ui['listing-actions'] = !is_admin() && $edit;
        // show categories dropdown if $category is not set
        $ui['category-field'] = ( $edit || empty( $form['ad_category'] ) ) && $is_moderator;
        $ui['user-dropdown'] = $edit && $is_admin_user;
        $ui['start-end-date'] = $edit && $is_moderator;
        // $ui['payment-term-dropdown'] = !$pay_first || ($is_admin_user && !$edit && $payments_enabled);
        $ui['website-field'] = get_awpcp_option('displaywebsitefield') == 1;
        $ui['website-field-required'] = get_awpcp_option('displaywebsitefieldreqop') == 1;
        $ui['contact-name-field-readonly'] = !empty( $form['ad_contact_name'] ) && !$is_moderator;
        $ui['contact-email-field-readonly'] = !empty( $form['ad_contact_email'] ) && !$is_moderator;
        $ui['contact-phone-field'] = get_awpcp_option('displayphonefield') == 1;
        $ui['contact-phone-field-required'] = get_awpcp_option('displayphonefieldreqop') == 1;
        $ui['price-field'] = get_awpcp_option('displaypricefield') == 1;
        $ui['price-field-required'] = get_awpcp_option('displaypricefieldreqop') == 1;
        $ui['allow-regions-modification'] = $is_moderator || !$edit || get_awpcp_option( 'allow-regions-modification' );
        $ui['price-field'] = get_awpcp_option('displaypricefield') == 1;
        $ui['extra-fields'] = $hasextrafieldsmodule && function_exists('awpcp_extra_fields_render_form');
        $ui['terms-of-service'] = !$edit && !$is_moderator && get_awpcp_option('requiredtos');
        $ui['captcha'] = !$edit && !is_admin() && ( get_awpcp_option( 'captcha-enabled-in-place-listing-form' ) == 1 );

        $hidden['step'] = 'save-details';
        $hidden['ad_id'] = $form['ad_id'];
        $hidden['ad_category'] = $form['ad_category'];
        $hidden['adterm_id'] = $form['adterm_id'];

        // propagate preview parameter sent when this step is accesed from the
        // Preview Ad screen
        $hidden['preview-hash'] = awpcp_post_param( 'preview-hash', false );
        $preview = strlen( $hidden['preview-hash'] ) > 0;

        if ( isset( $form['transaction_id'] ) ) {
            $hidden['transaction_id'] = $form['transaction_id'];
        }

        $page = $this;
        $url = $this->url();

        $transaction = $this->get_transaction();
        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-details-step.tpl.php';

        $params = compact('transaction', 'page', 'ui', 'messages', 'form', 'hidden', 'required', 'url', 'edit', 'preview', 'errors');

        if ( isset( $this->ad ) && is_object( $this->ad ) ) {
            $params['listing'] = $this->ad;
        }

        return $this->render($template, $params);
    }

    public function details_step_form($transaction, $form=array(), $errors=array()) {
        $form = $this->get_posted_details($form, $transaction);
        $form = array_merge( $form, $this->get_characters_allowed( $form['ad_id'], $transaction ) );

        $form['regions-allowed'] = $this->get_regions_allowed( $form['ad_id'], $transaction );

        // pre-fill user information if we are placing a new Ad
        if ($transaction->user_id) {
            foreach ($this->get_user_info($transaction->user_id) as $field => $value) {
                $form[$field] = empty($form[$field]) ? $value : $form[$field];
            }
        }

        // pref-fill ad information if we are editing a new Ad
        if ($transaction->get('ad-id', false)) {
            $ad_id = $transaction->get('ad-id', $form['ad_id']);
            foreach ($this->get_ad_info($ad_id) as $field => $value) {
                $form[$field] = empty($form[$field]) ? $value : $form[$field];
            }
        }

        $required = $this->get_required_fields();

        return $this->details_form($form, false, array(), $required, $errors);
    }

    public function details_step() {
        $transaction = $this->get_transaction(!get_awpcp_option('pay-before-place-ad'));

        $errors = array();
        $form = array();

        if (is_null($transaction)) {
            $message = __("Hi, Payment is required for posting Ads in this website and we couldn't find a Payment Transaction assigned to you. You can't post Ads this time. If you think this is an error please contact the website Administrator.", 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        if (strcmp($this->get_current_action(), 'save-details') === 0) {
            return $this->save_details_step($transaction, $errors);
        } else {
            return $this->details_step_form($transaction, array(), $errors);
        }
    }

    /**
     * @param  array  $data     Normalized array with Ad details. All fields are expected
     *                          to be present: isset($data['param']) === true
     * @param  array  $errors
     * @return boolean          true if data validates, false otherwise
     */
    protected function validate_details($data=array(), $edit=false, $payment_term = null, &$errors=array()) {
        global $hasextrafieldsmodule;

        // $edit = !empty($data['ad_id']);

        $is_moderator = awpcp_current_user_is_moderator();

        $user_id = awpcp_array_data('user_id', 0, $data);
        $user_payment_term = awpcp_array_data('user_payment_term', '', $data);
        if (get_awpcp_option('freepay') == 1 && $user_id > 0 && empty($user_payment_term) && !$edit) {
            $errors['user_payment_term'] = __('You did not select a Payment Term. Please select a Payment Term for this Ad.', 'another-wordpress-classifieds-plugin');
        }

        $start_date = strtotime($data['start_date']);
        if ($edit && $is_moderator && empty($data['start_date'])) {
            $errors['start_date'] = __('Please enter a start date for the Ad.', 'another-wordpress-classifieds-plugin');
        }

        $end_date = strtotime($data['end_date']);
        if ($edit && $is_moderator && empty($data['end_date'])) {
            $errors['end_date'] = __('Please enter an end date for the Ad.', 'another-wordpress-classifieds-plugin');
        }

        if ($edit && $is_moderator && $start_date > $end_date) {
            $errors['start_date'] = __('The start date must occur before the end date.', 'another-wordpress-classifieds-plugin');
        }

        // Check for ad title
        if (empty($data['ad_title'])) {
            $errors['ad_title'] = __("You did not enter a title for your Ad", 'another-wordpress-classifieds-plugin');
        }

        // Check for ad details
        if (empty($data['ad_details'])) {
            $errors['ad_details'] = __("You did not enter any text for your Ad. Please enter some text for your Ad.", 'another-wordpress-classifieds-plugin');
        }

        // Check for ad category
        if (empty($data['ad_category']) && $edit) {
            $errors['ad_category'] = __("You did not select a category for your Ad. Please select a category for your Ad.", 'another-wordpress-classifieds-plugin');
        }

        // If website field is checked and required make sure website value was entered
        if ((get_awpcp_option('displaywebsitefield') == 1) &&
            (get_awpcp_option('displaywebsitefieldreqop') == 1))
        {
            if (empty($data['websiteurl'])) {
                $errors['websiteurl'] = __("You did not enter your website address. Your website address is required.",'another-wordpress-classifieds-plugin');
            }
        }

        //If they have submitted a website address make sure it is correctly formatted
        if (!empty($data['websiteurl']) && !isValidURL($data['websiteurl'])) {
            $errors['websiteurl'] = __("Your website address is not properly formatted. Please make sure you have included the http:// part of your website address",'another-wordpress-classifieds-plugin');
        }

        // Check for ad poster's name
        if (empty($data['ad_contact_name'])) {
            $errors['ad_contact_name'] = __("You did not enter your name. Your name is required.", 'another-wordpress-classifieds-plugin');
        }

        // Check for ad poster's email address
        if (empty($data['ad_contact_email'])) {
            $errors['ad_contact_email'] = __("You did not enter your email. Your email is required.", 'another-wordpress-classifieds-plugin');
        }

        // Check if email address entered is in a valid email address format
        if ( ! awpcp_is_valid_email_address( $data['ad_contact_email'] ) ) {
            $errors['ad_contact_email'] = __("The email address you entered was not a valid email address. Please check for errors and try again.", 'another-wordpress-classifieds-plugin');
        } else if ( ! awpcp_is_email_address_allowed( $data['ad_contact_email'] ) ) {
            $message = __( 'The email address you entered is not allowed in this website. Please use an email address from one of the following domains: %s.', 'another-wordpress-classifieds-plugin' );
            $domains_whitelist = explode( "\n", get_awpcp_option( 'ad-poster-email-address-whitelist' ) );
            $domains_list = '<strong>' . implode( '</strong>, <strong>', $domains_whitelist ) . '</strong>';
            $errors['ad_contact_email'] = sprintf( $message, $domains_list );
        }

        // If phone field is checked and required make sure phone value was entered
        if ((get_awpcp_option('displayphonefield') == 1) &&
            (get_awpcp_option('displayphonefieldreqop') == 1))
        {
            if (empty($data['ad_contact_phone'])) {
                $errors['ad_contact_phone'] = __("You did not enter your phone number. Your phone number is required.", 'another-wordpress-classifieds-plugin');
            }
        }

        $region_fields = array();
        foreach ( $data['regions'] as $region ) {
            foreach ( $region as $type => $value ) {
                if ( !empty( $value ) ) {
                    $region_fields[ $type ] = true;
                }
            }
        }

        // If country field is checked and required make sure country value was entered
        if ( $payment_term->regions > 0 && (get_awpcp_option('displaycountryfield') == 1) &&
            (get_awpcp_option('displaycountryfieldreqop') == 1))
        {
            if ( ! awpcp_array_data( 'country', false, $region_fields ) ) {
                $errors['regions'] = __("You did not enter your country. Your country is required.", 'another-wordpress-classifieds-plugin');
            }
        }

        // If state field is checked and required make sure state value was entered
        if ( $payment_term->regions > 0 && (get_awpcp_option('displaystatefield') == 1) &&
            (get_awpcp_option('displaystatefieldreqop') == 1))
        {
            if ( ! awpcp_array_data( 'state', false, $region_fields ) ) {
                $errors['regions'] = __("You did not enter your state. Your state is required.", 'another-wordpress-classifieds-plugin');
            }
        }

        // If city field is checked and required make sure city value was entered
        if ( $payment_term->regions > 0 && (get_awpcp_option('displaycityfield') == 1) &&
            (get_awpcp_option('displaycityfieldreqop') == 1))
        {
            if ( ! awpcp_array_data( 'city', false, $region_fields ) ) {
                $errors['regions'] = __("You did not enter your city. Your city is required.", 'another-wordpress-classifieds-plugin');
            }
        }

        // If county/village field is checked and required make sure county/village value was entered
        if ( $payment_term->regions > 0 && (get_awpcp_option('displaycountyvillagefield') == 1) &&
            (get_awpcp_option('displaycountyvillagefieldreqop') == 1))
        {
            if ( ! awpcp_array_data( 'county', false, $region_fields ) ) {
                $errors['regions'] = __("You did not enter your county/village. Your county/village is required.", 'another-wordpress-classifieds-plugin');
            }
        }

        // If price field is checked and required make sure a price has been entered
        if ( get_awpcp_option('displaypricefield') == 1 && get_awpcp_option('displaypricefieldreqop') == 1 ) {
            if ( strlen($data['ad_item_price']) === 0 || $data['ad_item_price'] === false )
                $errors['ad_item_price'] = __("You did not enter the price of your item. The item price is required.",'another-wordpress-classifieds-plugin');
        }

        // Make sure the item price is a numerical value
        if ( get_awpcp_option('displaypricefield') == 1 && strlen( $data['ad_item_price'] ) > 0 ) {
            if ( !is_numeric( $data['ad_item_price'] ) )
                $errors['ad_item_price'] = __("You have entered an invalid item price. Make sure your price contains numbers only. Please do not include currency symbols.",'another-wordpress-classifieds-plugin');
        }

        if ($hasextrafieldsmodule == 1) {
            // backward compatibility with old extra fields
            if (function_exists('validate_extra_fields_form')) {
                $_errors = validate_extra_fields_form($data['ad_category']);
            } else if (function_exists('validate_x_form')) {
                $_errors = validate_x_form();
            }

            if (isset($_errors) && !empty($_errors)) {
                $errors = array_merge($errors, (array) $_errors);
            }
        }

        // Terms of service required and accepted?
        if (!$edit && !$is_moderator && get_awpcp_option('requiredtos') && empty($data['terms-of-service'])) {
            $errors['terms-of-service'] = __("You did not accept the terms of service", 'another-wordpress-classifieds-plugin');
        }

        if ( !$edit && !is_admin() && get_awpcp_option( 'captcha-enabled-in-place-listing-form' ) ) {
            $captcha = awpcp_create_captcha( get_awpcp_option( 'captcha-provider' ) );

            $error = '';
            if ( !$captcha->validate( $error ) ) {
                $errors['captcha'] = $error;
            }
        }

        if ( get_awpcp_option( 'use-akismet-in-place-listing-form' ) ) {
            $spam_filter = awpcp_listing_spam_filter();

            if ( $spam_filter->is_spam( $data ) ) {
                $errors[] = __("Your Ad was flagged as spam. Please contact the administrator of this site.", 'another-wordpress-classifieds-plugin');
            }
        }

        return count(array_filter($errors)) === 0;
    }

    protected function prepare_ad_title($title, $characters) {
        $$title = $title;

        if ( $characters > 0 && awpcp_utf8_strlen( $title ) > $characters ) {
            $title = awpcp_utf8_substr( $title, 0, $characters );
        }

        return $title;
    }

    protected function prepare_ad_details($details, $characters) {
        $allow_html = (bool) get_awpcp_option('allowhtmlinadtext');

        if (!$allow_html) {
            $details = esc_html( $details );
        } else {
            $details = wp_kses_post( $details );
        }

        if ( $characters > 0 && awpcp_utf8_strlen( $details ) > $characters ) {
            $details = awpcp_utf8_substr( $details, 0, $characters );
        }

        if ($allow_html) {
            $details = force_balance_tags($details);
        }

        return $details;
    }

    public function save_details_step($transaction, $errors=array()) {
        global $wpdb, $hasextrafieldsmodule;

        $data = $this->get_posted_details($_POST, $transaction);
        $characters = $this->get_characters_allowed( $data['ad_id'], $transaction );
        $errors = array();

        $payment_term = awpcp_payments_api()->get_transaction_payment_term( $transaction );

        if (!$this->validate_details($data, false, $payment_term, $errors)) {
            return $this->details_step_form($transaction, $data, $errors);
        }

        $now = current_time('mysql');

        if ($transaction->get('ad-id')) {
            $ad = AWPCP_Ad::find_by_id($transaction->get('ad-id'));
        } else {
            $ad = new AWPCP_Ad;

            $totals = $transaction->get_totals();

            $ad->adterm_id = $transaction->get('payment-term-id');
            $ad->payment_term_type = $transaction->get('payment-term-type');
            $ad->ad_transaction_id = $transaction->id;
            $ad->ad_fee_paid = $totals['money'];
            $ad->ad_key = AWPCP_Ad::generate_key();

            $timestamp = awpcp_datetime( 'timestamp', $now );
            $payment_term = $ad->get_payment_term();

            $ad->set_start_date($now);
            $ad->set_end_date($payment_term->calculate_end_date($timestamp));
            $ad->ad_postdate = $now;

            $ad->disabled = true;
            $ad->payment_status = 'Unpaid';
        }

        if ( !$transaction->get('ad-id') || $this->verify_preview_hash($ad) ) {
            $ad->user_id = $data['user_id'];
            $ad->ad_category_id = $data['ad_category'];
            $ad->ad_category_parent_id = get_cat_parent_ID($data['ad_category']);
            $ad->ad_title = $this->prepare_ad_title( $data['ad_title'], $characters['characters_allowed_in_title']);
            $ad->ad_details = $this->prepare_ad_details($data['ad_details'], $characters['characters_allowed']);
            $ad->ad_contact_name = $data['ad_contact_name'];
            $ad->ad_contact_phone = $data['ad_contact_phone'];
            $ad->ad_contact_email = $data['ad_contact_email'];
            $ad->websiteurl = $data['websiteurl'];
            $ad->ad_item_price = $data['ad_item_price'] * 100;
            $ad->is_featured_ad = $data['is_featured_ad'];
            $ad->ad_last_updated = $now;
            $ad->posterip = awpcp_getip();

            $ad = apply_filters( 'awpcp-before-save-listing', $ad, $data );

            if (!$ad->save()) {
                $errors[] = __('There was an unexpected error trying to save your Ad details. Please try again or contact an administrator.', 'another-wordpress-classifieds-plugin');
                return $this->details_step_form($transaction, $data, $errors);
            }

            $regions_allowed = $this->get_regions_allowed( $ad->ad_id, $transaction );
            awpcp_basic_regions_api()->update_ad_regions( $ad, $data['regions'], $regions_allowed );

            $transaction->set('ad-id', $ad->ad_id);

            do_action('awpcp-save-ad-details', $ad, $transaction);

            $transaction->save();
        }

        if ( awpcp_post_param('preview-hash', false) ) {
            return $this->preview_step();
        } else if ( $this->should_show_upload_files_step( $ad ) ) {
            return $this->upload_images_step();
        } else if ((bool) get_awpcp_option('pay-before-place-ad')) {
            return $this->finish_step();
        } else if ((bool) get_awpcp_option('show-ad-preview-before-payment')) {
            return $this->preview_step();
        } else {
            return $this->checkout_step();
        }
    }

    private function should_show_upload_files_step( $listing ) {
        $allowed_files = awpcp_listing_upload_limits()->get_listing_upload_limits( $listing );

        foreach ( $allowed_files as $file_type => $limits ) {
            if ( $limits['allowed_file_count'] >= $limits['uploaded_file_count'] ) {
                return true;
            }
        }

        return false;
    }

    public function get_images_config( $ad ) {
        $payment_term = awpcp_payments_api()->get_ad_payment_term($ad);

        $images_allowed = awpcp_get_property( $payment_term, 'images', get_awpcp_option( 'imagesallowedfree', 0 ) );
        $images_uploaded = $ad->count_image_files();

        return array(
            'images_allowed' => $images_allowed,
            'images_uploaded' => $images_uploaded,
        );
    }

    public function upload_images_step() {
        $transaction = $this->get_transaction();

        if (is_null($transaction)) {
            $message = __('We were unable to find a Payment Transaction assigned to this operation. No images can be added at this time.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        $ad = AWPCP_Ad::find_by_id($transaction->get('ad-id', 0));

        if (is_null($ad)) {
            $message = __('The specified Ad doesn\'t exists. No images can be added at this time.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        extract( $params = $this->get_images_config( $ad ) );

        // see if we can move to the next step
        $skip = ! $this->should_show_upload_files_step( $ad );
        $skip = $skip || awpcp_post_param( 'submit-no-images', false );
        $skip = $skip || $images_allowed == 0;

        $show_preview = (bool) get_awpcp_option('show-ad-preview-before-payment');
        $pay_first = (bool) get_awpcp_option('pay-before-place-ad');

        if ( $skip && $show_preview ) {
            return $this->preview_step();
        } else if ( $skip && $pay_first ) {
            return $this->finish_step();
        } else if ( $skip ) {
            return $this->checkout_step();
        } else {
            return $this->show_upload_images_form( $ad, $transaction, $params, array() );
        }
    }

    protected function show_upload_images_form( $ad, $transaction, $params, $errors ) {
        $allowed_files = awpcp_listing_upload_limits()->get_listing_upload_limits( $ad );

        $params = array_merge( $params, array(
            'transaction' => $transaction,
            'hidden' => array( 'transaction_id' => $transaction->id ),
            'errors' => $errors,
            'media_manager_configuration' => array(
                'nonce' => wp_create_nonce( 'awpcp-manage-listing-media-' . $ad->ad_id ),
                'allowed_files' => $allowed_files,
                'show_admin_actions' => awpcp_current_user_is_moderator(),
            ),
            'media_uploader_configuration' => array(
                'listing_id' => $ad->ad_id,
                'nonce' => wp_create_nonce( 'awpcp-upload-media-for-listing-' . $ad->ad_id ),
                'allowed_files' => $allowed_files,
            ),
        ) );

        return $this->upload_images_form( $ad, $params );
    }

    public function upload_images_form( $ad, $params=array() ) {
        $show_preview = (bool) get_awpcp_option('show-ad-preview-before-payment');
        $pay_first = (bool) get_awpcp_option('pay-before-place-ad');

        extract( $params );

        if ( $show_preview ) {
            $next = __( 'Preview Ad', 'another-wordpress-classifieds-plugin' );
        } else if ( $pay_first ) {
            $next = __( 'Place Ad', 'another-wordpress-classifieds-plugin' );
        } else {
            $next = __( 'Checkout', 'another-wordpress-classifieds-plugin' );
        }

        $params = array_merge( $params, array(
            'listing' => $ad,
            'files' => awpcp_media_api()->find_by_ad_id( $ad->ad_id ),
            'messages' => $this->messages,
            'next' => $next,
        ) );

        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-upload-images-step.tpl.php';

        return $this->render( $template, $params );
    }

    public function preview_step() {
        $transaction = $this->get_transaction();

        if ( is_null( $transaction ) ) {
            $message = __('We were unable to find a Payment Transaction assigned to this operation.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        $ad = AWPCP_Ad::find_by_id($transaction->get('ad-id', 0));

        if ( is_null( $ad ) ) {
            $message = __('The Ad associated with this transaction doesn\'t exists.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        $pay_first = (bool) get_awpcp_option('pay-before-place-ad');

        if ( isset( $_POST['edit-details'] ) ) {
            return $this->details_step();
        } else if ( isset( $_POST['manage-images'] ) ) {
            return $this->upload_images_step();
        } else if ( $pay_first && isset( $_POST['finish'] ) ) {
            return $this->finish_step();
        } else if ( isset( $_POST['finish'] ) ) {
            return $this->checkout_step();
        } else {
            $payment_term = awpcp_payments_api()->get_ad_payment_term($ad);
            $manage_images = awpcp_are_images_allowed() && $payment_term->images > 0;

            $params = array(
                'ad' => $ad,
                'edit' => false,
                'messages' => $this->messages,
                'hidden' => array(
                    'preview-hash' => $this->get_preview_hash( $ad ),
                    'transaction_id' => $transaction->id,
                ),
                'ui' => array(
                    'manage-images' => $manage_images,
                ),
            );

            $template = AWPCP_DIR . '/frontend/templates/page-place-ad-preview-step.tpl.php';

            return $this->render($template, $params);
        }
    }

    public function finish_step() {
        $transaction = $this->get_transaction();

        $messages = $this->messages;
        $send_email = false;

        if (is_null($transaction)) {
            $message = __('We were unable to find a Payment Transaction assigned to this operation.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        $ad = AWPCP_Ad::find_by_id($transaction->get('ad-id', 0));

        if (is_null($ad)) {
            $message = __('The Ad associated with this transaction doesn\'t exists.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        if (!$transaction->is_completed()) {
            awpcp_payments_api()->set_transaction_status_to_completed( $transaction, $errors );

            if (!empty($errors)) {
                return $this->render('content', join(',', array_map('awpcp_print_error', $errors)));
            }

            $transaction->save();
        }

        // reload Ad, since modifications were probably made as part of the
        // transaction handling workflow
        $ad = AWPCP_Ad::find_by_id( $transaction->get( 'ad-id', 0 ) );

        $params = array(
            'edit' => false,
            'ad' => $ad,
            'messages' => array_merge( $messages, awpcp_listings_api()->get_ad_alerts( $ad ) ),
            'transaction' => $transaction,
            'transaction_id' => $transaction->id
        );

        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-finish-step.tpl.php';

        return $this->render($template, $params);
    }
}
