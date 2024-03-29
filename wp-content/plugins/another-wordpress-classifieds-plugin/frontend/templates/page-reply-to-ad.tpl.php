<?php
    foreach ($messages as $message) {
        echo awpcp_print_message($message);
    }
?>

<?php $msg = __('You are responding to Ad: %s.', 'another-wordpress-classifieds-plugin'); ?>
<p><?php echo sprintf($msg, $ad_link); ?></p>

<form class="awpcp-reply-to-ad-form" method="post" name="myform">
    <?php foreach($hidden as $name => $value): ?>
    <input type="hidden" name="<?php echo esc_attr($name) ?>" value="<?php echo esc_attr($value) ?>" />
    <?php endforeach ?>

    <?php $disabled = $ui['disable-sender-fields'] ? 'disabled="disabled"' : ''; ?>

    <p class="awpcp-form-spacer">
        <label for="awpcp-contact-sender-name"><?php _e("Your name", 'another-wordpress-classifieds-plugin'); ?></label>
        <input id="awpcp-contact-sender-name" class="inputbox required" type="text" name="awpcp_sender_name" value="<?php echo esc_attr( $form['awpcp_sender_name'] ); ?>" <?php echo $disabled; ?> />
        <?php echo awpcp_form_error('awpcp_sender_name', $errors) ?>
    </p>

    <p class="awpcp-form-spacer">
        <label for="awpcp-contact-sender-email"><?php _e("Your email address", 'another-wordpress-classifieds-plugin'); ?></label>
        <input id="awpcp-contact-sender-email" class="inputbox required email" type="text" name="awpcp_sender_email" value="<?php echo esc_attr( $form['awpcp_sender_email'] ); ?>" <?php echo $disabled; ?> />
        <?php echo awpcp_form_error('awpcp_sender_email', $errors) ?>
    </p>

    <p class="awpcp-form-spacer">
        <label for="awpcp-contact-message"><?php _e("Your message", 'another-wordpress-classifieds-plugin'); ?></label>
        <textarea id="awpcp-contact-message" class="awpcp-textarea textareainput required" name="awpcp_contact_message" rows="5" cols="90%"><?php echo esc_textarea( $form['awpcp_contact_message'] ); ?></textarea>
        <?php echo awpcp_form_error('awpcp_contact_message', $errors) ?>
    </p>

    <?php if ($ui['captcha']): ?>
    <p class='awpcp-form-spacer'>
        <?php $captcha = awpcp_create_captcha( get_awpcp_option( 'captcha-provider' ) ); ?>
        <?php echo $captcha->render(); ?>
        <?php echo awpcp_form_error('captcha', $errors) ?>
    </p>
    <?php endif ?>

    <input type="submit" class="button" value="<?php echo esc_attr( __( "Continue",'another-wordpress-classifieds-plugin' ) ); ?>" />
</form>
