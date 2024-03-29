<?php

require_once(AWPCP_DIR . '/frontend/page-place-ad.php');


/**
 * @since  2.1.4
 */
class AWPCP_EditAdPage extends AWPCP_Place_Ad_Page {

    protected $ad = null;

    public $active = false;
    public $messages = array();

    public function __construct($page='awpcp-edit-ad', $title=null) {
        parent::__construct($page, $title);
    }

    public function get_ad() {
        if (is_null($this->ad)) {
            if ( $id = $this->get_listing_id() ) {
                $this->ad = AWPCP_Ad::find_by_id($id);
            }
        }

        return $this->ad;
    }

    private function get_listing_id() {
        return awpcp_request_param( 'ad_id', awpcp_request_param( 'id', get_query_var( 'id' ) ) );
    }

    public function get_edit_hash($ad) {
        return wp_create_nonce("edit-ad-{$ad->ad_id}");
    }

    protected function request_includes_authorized_hash( $ad ) {
        return wp_verify_nonce(awpcp_request_param('edit-hash'), "edit-ad-{$ad->ad_id}");
    }

    protected function _dispatch($default=null) {
        if ( $this->should_redirect_user_to_ad_management_panel() ) {
            $url = admin_url('admin.php?page=awpcp-panel');
            $message = __('Please go to the Ad Management panel to edit your Ads.', 'another-wordpress-classifieds-plugin');
            $message = sprintf('%s <a href="%s">%s</a>.', $message, $url, __('Click here', 'another-wordpress-classifieds-plugin'));
            return $this->render('content', awpcp_print_message($message));
        } else {
            return $this->handle_request( $default );
        }
    }

    private function should_redirect_user_to_ad_management_panel() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        if ( is_admin() ) {
            return false;
        }

        if ( ! get_awpcp_option( 'enable-user-panel' ) ) {
            return false;
        }

        return true;
    }

    protected function handle_request( $default_action = null ) {
        $ad = $this->get_ad();

        if ( ! is_null( $ad ) ) {
            if ( $this->is_user_allowed_to_edit( $ad ) ) {
                return $this->render_page( $this->get_current_action( 'details' ) );
            } else {
                $message = __( 'You are not allowed to edit the specified Ad.', 'another-wordpress-classifieds-plugin' );
                return $this->render( 'content', awpcp_print_error( $message ) );
            }
        } else {
            return $this->render_page( $this->get_current_action( $default_action ) );
        }
    }

    protected function render_page( $action ) {
        switch ($action) {
            case 'details':
            case 'save-details':
                return $this->details_step();
                break;
            case 'upload-images':
                return $this->upload_images_step();
                break;
            case 'delete-ad':
                return $this->delete_ad_step();
                break;
            case 'send-access-key':
                return $this->send_access_key_step();
                break;
            default:
                return $this->handle_custom_listing_actions( $action );
                break;
        }
    }

    public function enter_email_and_key_step($show_errors=true) {
        global $wpdb;

        $errors = array();
        $messages = $this->messages;

        $form = array(
            'ad_email' => awpcp_post_param('ad_email'),
            'ad_key' => awpcp_post_param('ad_key'),
            'attempts' => (int) awpcp_post_param('attempts', 0));

        if ($form['attempts'] == 0 && get_awpcp_option('enable-user-panel') == 1) {
            $url = admin_url('admin.php?page=awpcp-panel');
            $message = __('You are currently not logged in, if you have an account in this website you can log in and go to the Ad Management panel to edit your Ads.', 'another-wordpress-classifieds-plugin');
            $message = sprintf('%s <a href="%s">%s</a>', $message, $url, __('Click here', 'another-wordpress-classifieds-plugin'));
            $this->messages[] = $message;
        }

        $send_access_key_url = add_query_arg( array( 'step' => 'send-access-key' ), $this->url() );

        if (empty($form['ad_email'])) {
            $errors['ad_email'] = __('Please enter the email address you used when you created your Ad in addition to the Ad access key that was emailed to you after your Ad was submitted.', 'another-wordpress-classifieds-plugin');
        } else if (!is_email($form['ad_email'])) {
            $errors['ad_email'] = __('Please enter a valid email address.', 'another-wordpress-classifieds-plugin');
        }

        if (empty($form['ad_key'])) {
            $errors['ad_key'] = __('Please enter your Ad access key.', 'another-wordpress-classifieds-plugin');
        }

        if (empty($errors)) {
            $this->ad = AWPCP_Ad::find_by_email_and_key($form['ad_email'], $form['ad_key']);
            if (is_null($this->ad)) {
                $errors[] = __('The email address and access key you entered does not match any of the Ads in our system.', 'another-wordpress-classifieds-plugin');
            } else {
                return $this->details_step();
            }
        } else if ($form['attempts'] == 0 || $show_errors === false) {
            $errors = array();
        }

        $hidden = array('attempts' => $form['attempts'] + 1);
        $params = compact( 'form', 'hidden', 'messages', 'errors', 'send_access_key_url' );
        $template = AWPCP_DIR . '/frontend/templates/page-edit-ad-email-key-step.tpl.php';

        return $this->render($template, $params);
    }

    public function details_step() {
        $ad = $this->get_ad();

        if ( is_null( $ad ) ) {
            return $this->handle_missing_listing_exception();
        }

        if (strcmp($this->get_current_action(), 'save-details') === 0) {
            return $this->save_details_step();
        } else {
            return $this->details_step_form($ad, array());
        }
    }

    private function handle_missing_listing_exception() {
        $listing_id = $this->get_listing_id();

        if ( $listing_id ) {
            $message = __( 'The specified Ad doesn\'t exists.', 'another-wordpress-classifieds-plugin' );
            return $this->render( 'content', awpcp_print_error( $message ) );
        } else {
            return $this->enter_email_and_key_step();
        }
    }

    public function details_step_form($ad, $form=array(), $errors=array()) {
        $form = $this->get_posted_details( $form );
        $form = array_merge( $form, $this->get_characters_allowed( $ad->ad_id ) );

        $form['regions-allowed'] = $this->get_regions_allowed( $ad->ad_id );

        // if there are errors then the user already sent edited information,
        // and we don't need to provide defaults from Ad object
        if (empty($errors)) {
            foreach ($this->get_ad_info($ad->ad_id) as $field => $value) {
                $form[$field] = empty($form[$field]) ? $value : $form[$field];
            }
        }

        // overwrite user email and name using Profile information
        if ( $ad->user_id ) {
            $info = $this->get_user_info( $ad->user_id );

            $fields = array( 'ad_contact_name', 'ad_contact_email', 'ad_contact_phone' );
            foreach ($fields as $field) {
                if ( empty( $form[ $field ] ) && isset( $info[ $field ] ) && ! empty( $info[ $field ] ) ) {
                    $form[ $field ] = $info[ $field ];
                }
            }
        }

        $hidden = array('edit-hash' => $this->get_edit_hash($ad));
        $required = $this->get_required_fields();

        if ( is_admin() ) {
            $manage_attachments = __( 'Manage Attachments', 'another-wordpress-classifieds-plugin' );
            $url = add_query_arg( array( 'action' => 'manage-images', 'id' => $ad->ad_id ), $this->url() );
            $link = sprintf( '<strong><a href="%s" title="%s">%s</a></strong>', esc_url( $url ), esc_attr( $manage_attachments ), esc_html( $manage_attachments ) );
            $message = __( "Go to the %s section to manage the Images and Attachments for this Ad.", 'another-wordpress-classifieds-plugin');

            $this->messages[] = sprintf( $message, $link );
        }

        return $this->details_form($form, true, $hidden, $required, $errors);
    }

    /**
     * @param transaction   unused but required to match method
     *                          signature in parent class.
     */
    public function save_details_step($transaction=null, $errors=array()) {
        global $wpdb, $hasextrafieldsmodule;

        $ad = $this->get_ad();

        if (is_null($ad)) {
            $message = __('The specified Ad doesn\'t exists.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        $data = $this->get_posted_details( $_POST );
        $characters = $this->get_characters_allowed( $ad->ad_id );
        $errors = array();

        $payment_term = awpcp_payments_api()->get_ad_payment_term( $ad );

        if ( ! $this->validate_details( $data, true, $payment_term, $errors ) ) {
            return $this->details_step_form($ad, $data, $errors);
        }

        do_action('awpcp_before_edit_ad', $ad);

        // only admins can change the owner of an Ad
        if ( ! awpcp_current_user_is_moderator() || empty( $data['user_id'] ) ) {
            $data['user_id'] = $ad->user_id;
        }

        $ad->user_id = $data['user_id'];
        $ad->ad_title = $this->prepare_ad_title( $data['ad_title'], $characters['characters_allowed_in_title']);
        $ad->ad_details = $this->prepare_ad_details($data['ad_details'], $characters['characters_allowed']);
        $ad->ad_contact_name = $data['ad_contact_name'];
        $ad->ad_contact_phone = $data['ad_contact_phone'];
        $ad->ad_contact_email = $data['ad_contact_email'];
        $ad->websiteurl = $data['websiteurl'];
        $ad->ad_item_price = $data['ad_item_price'] * 100;
        $ad->ad_last_updated = current_time('mysql');

        if ( awpcp_current_user_is_moderator() ) {
            $ad->ad_startdate = awpcp_set_datetime_date( $ad->ad_startdate, $data['start_date'] );
            $ad->ad_enddate = awpcp_set_datetime_date( $ad->ad_enddate, $data['end_date'] );
        }

        if ( awpcp_current_user_is_moderator() && ! empty( $data['ad_category'] ) ) {
            $category = AWPCP_Category::find_by_id( $data['ad_category'] );
            if ( ! is_null( $category ) ) {
                $ad->ad_category_id = $category->id;
                $ad->ad_category_parent_id = $category->parent;
            }
        }

        if (!$ad->save()) {
            $errors[] = __('There was an unexpected error trying to save your Ad details. Please try again or contact an administrator.', 'another-wordpress-classifieds-plugin');
            return $this->details_step_form($ad, $data, $errors);
        }

        if ( awpcp_current_user_is_moderator() || get_awpcp_option( 'allow-regions-modification' ) ) {
            $regions_allowed = $this->get_regions_allowed( $ad->ad_id );
            awpcp_basic_regions_api()->update_ad_regions( $ad, $data['regions'], $regions_allowed );
        }

        do_action('awpcp_edit_ad', $ad);

        if ( is_admin() || ! awpcp_are_images_allowed() ) {
            return $this->finish_step();
        } else {
            return $this->upload_images_step();
        }
    }

    public function upload_images_step() {
        $ad = $this->get_ad();

        if (is_null($ad)) {
            $message = __('The specified Ad doesn\'t exists. No images can be added at this time.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        extract( $params = $this->get_images_config( $ad ) );

        // see if we can move to the next step
        if ( ! awpcp_are_images_allowed() ) {
            return $this->finish_step();
        } else if ( awpcp_post_param( 'submit-no-images', false ) ) {
            return $this->finish_step();
        } else if (($images_uploaded == 0 && $images_allowed == 0)) {
            return $this->finish_step();
        }

        // we are still here... let's show the upload images form

        return $this->show_upload_images_form( $ad, null, $params, array() );
    }

    /**
     * TODO: merge with the same method from Page Place Ad.
     */
    protected function show_upload_images_form( $ad, $transaction, $params, $errors ) {
        $allowed_files = awpcp_listing_upload_limits()->get_listing_upload_limits( $ad );

        $params = array_merge( $params, array(
            'hidden' => array(),
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
        $params = array_merge( $params, array(
            'listing' => $ad,
            'files' => awpcp_media_api()->find_by_ad_id( $ad->ad_id ),
            'hidden' => array(
                'ad_id' => $ad->ad_id,
                'edit-hash' => $this->get_edit_hash( $ad ) ),
            'messages' => $this->messages,
            'next' => __( 'Finish', 'another-wordpress-classifieds-plugin' ),
        ) );

        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-upload-images-step.tpl.php';

        return $this->render( $template, $params );
    }

    public function finish_step() {
        $ad = $this->get_ad();

        if (is_null($ad)) {
            $message = __('The specified Ad doesn\'t exists.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        awpcp_listings_api()->consolidate_existing_ad( $ad );

        if (is_admin()) {
            $message = __('The Ad has been edited successfully. <a href="%s">Go back to view listings</a>.', 'another-wordpress-classifieds-plugin');
            $page = awpcp_current_user_is_moderator() ? 'awpcp-listings' : 'awpcp-panel';
            $url = add_query_arg('page', $page, admin_url('admin.php'));

            $this->messages[] = sprintf( $message, esc_url( $url) );
        }

        $template = AWPCP_DIR . '/frontend/templates/page-place-ad-finish-step.tpl.php';
        $params = array(
            'messages' => array_merge( $this->messages, awpcp_listings_api()->get_ad_alerts( $ad ) ),
            'edit' => true,
            'ad' => $ad
        );

        return $this->render($template, $params);
    }

    public function delete_ad_step() {
        $ad = $this->get_ad();

        if (is_null($ad)) {
            $message = __('The specified Ad doesn\'t exists.', 'another-wordpress-classifieds-plugin');
            return $this->render('content', awpcp_print_error($message));
        }

        if ( ! awpcp_post_param( 'confirm', false ) || ! $ad->delete() ) {
            $this->messages[] = __('There was a problem trying to delete your Ad. The Ad was not deleted.', 'another-wordpress-classifieds-plugin');
            return $this->details_step();
        }

        if ( get_awpcp_option( 'requireuserregistration' ) ) {
            return $this->render_delete_listing_confirmation();
        } else {
            return $this->enter_email_and_key_step();
        }
    }

    private function render_delete_listing_confirmation() {
        $this->messages[] = __( 'Your Ad has been successfully deleted.', 'another-wordpress-classifieds-plugin' );
        $template = AWPCP_DIR . '/templates/frontend/edit-listing-page-delete-listing-confirmation.tpl.php';

        return $this->render( $template, array(
            'messages' => $this->messages,
            'main_page_url' => awpcp_get_main_page_url()
        ) );
    }

    public function send_access_key_step() {
        global $wpdb;

        $errors = array();
        $form = array(
            'ad_email' => awpcp_post_param('ad_email'),
            'attempts' => (int) awpcp_post_param('attempts', 0)
        );

        if ($form['attempts'] == 0 && get_awpcp_option('enable-user-panel') == 1) {
            $url = admin_url('admin.php?page=awpcp-panel');
            $message = __('You are currently not logged in, if you have an account in this website you can log in and go to the Ad Management panel to edit your Ads.', 'another-wordpress-classifieds-plugin');
            $message = sprintf('%s <a href="%s">%s</a>', $message, $url, __('Click here', 'another-wordpress-classifieds-plugin'));
            $this->messages[] = $message;
        }

        if (empty($form['ad_email'])) {
            $errors['ad_email'] = __('Please enter the email address you used when you created your Ad.', 'another-wordpress-classifieds-plugin');
        } else if (!is_email($form['ad_email'])) {
            $errors['ad_email'] = __('Please enter a valid email address.', 'another-wordpress-classifieds-plugin');
        }

        $ads = array();
        if ( empty( $errors ) ) {
            $ads = AWPCP_Ad::find_by_email( $form['ad_email'] );
            if ( empty( $ads ) ) {
                $errors[] = __('The email address you entered does not match any of the Ads in our system.', 'another-wordpress-classifieds-plugin');
            }
        } else if ( $form['attempts'] == 0 ) {
            $errors = array();
        }

        // if $ads is non-empty then $errors is empty
        if ( !empty( $ads ) ) {
            $access_keys_sent = $this->send_access_keys( $ads, $errors );
        } else {
            $access_keys_sent = false;
        }

        if ( !$access_keys_sent ) {
            $send_access_key_url = add_query_arg( array( 'step' => 'send-access-key' ), $this->url() );

            $messages = $this->messages;
            $hidden = array('attempts' => $form['attempts'] + 1);
            $params = compact( 'form', 'hidden', 'messages', 'errors', 'send_access_key_url' );
            $template = AWPCP_DIR . '/frontend/templates/page-edit-ad-send-access-key-step.tpl.php';

            return $this->render($template, $params);
        } else {
            return $this->enter_email_and_key_step(false);
        }
    }

    public function send_access_keys($ads, &$errors=array()) {
        $ad = reset( $ads );

        $recipient = "{$ad->ad_contact_name} <{$ad->ad_contact_email}>";
        $template = AWPCP_DIR . '/frontend/templates/email-send-all-ad-access-keys.tpl.php';

        $message = new AWPCP_Email;
        $message->to[] = $recipient;
        $message->subject = get_awpcp_option( 'resendakeyformsubjectline' );

        $message->prepare($template,  array(
            'ads' => $ads,
            'introduction' => get_awpcp_option('resendakeyformbodymessage'),
        ));

        if ($message->send()) {
            $this->messages[] = sprintf( __( 'The access keys were sent to %s.', 'another-wordpress-classifieds-plugin' ), esc_html( $recipient ) );
            return true;
        } else {
            $errors[] = sprintf( __( 'There was an error trying to send the email to %s.', 'another-wordpress-classifieds-plugin' ), esc_html( $recipient ) );
            return false;
        }
    }

    private function handle_custom_listing_actions( $action ) {
        $listing = $this->get_ad();

        if ( is_null( $listing ) ) {
            return $this->handle_missing_listing_exception();
        }

        $output = apply_filters( "awpcp-custom-listing-action-$action", null, $listing );

        if ( is_null( $output ) ) {
            if ( $this->is_user_allowed_to_edit( $listing ) ) {
                return $this->details_step();
            } else {
                return $this->enter_email_and_key_step();
            }
        } else if ( is_array( $output ) && isset( $output['redirect'] ) ) {
            return $this->render_page( $output['redirect'] );
        } else {
            return $output;
        }
    }
}
