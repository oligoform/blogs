<?php
    foreach ($messages as $message) {
        echo awpcp_print_message($message);
    }

    foreach ($errors as $index => $error) {
        if (is_numeric($index)) {
            echo awpcp_print_message($error, array('error'));
        } else {
            echo awpcp_print_message($error, array('error', 'ghost'));
        }
    }
?>

<form class="awpcp-search-ads-form" method="get" action="<?php echo esc_url( $action_url ); ?>"name="myform">
    <?php echo awpcp_html_hidden_fields( $hidden ); ?>

    <p class='awpcp-form-spacer'>
        <label for="query"><?php _e("Search for Ads containing this word or phrase", 'another-wordpress-classifieds-plugin'); ?>:</label>
        <input type="text" id="query" class="inputbox" size="50" name="keywordphrase" value="<?php echo esc_attr($form['query']); ?>" />
        <?php echo awpcp_form_error('query', $errors); ?>
    </p>

    <p class="awpcp-form-spacer">
        <?php $dropdown = new AWPCP_CategoriesDropdown(); ?>
        <?php echo $dropdown->render( array(
                'context' => 'search',
                'selected' => awpcp_array_data('category', '', $form),
                'name' => 'searchcategory',
                'required' => false,
              ) ); ?>
    </p>

    <?php if ($ui['posted-by-field']): ?>
    <p class='awpcp-form-spacer'>
        <label for="name"><?php _e("For Ads Posted By", 'another-wordpress-classifieds-plugin'); ?></label>
        <select id="name" name="searchname">
            <option value=""><?php _e("All Users", 'another-wordpress-classifieds-plugin'); ?></option>
            <?php echo create_ad_postedby_list($form['name']); ?>
        </select>
    </p>
    <?php endif ?>

    <?php if ($ui['price-field']): ?>
    <p class="awpcp-form-spacer">
        <label for="min-price"><?php _e( 'Price', 'another-wordpress-classifieds-plugin' ); ?></label>
        <span class="awpcp-range-search">
            <label for="min-price"><?php _e( "Min", 'another-wordpress-classifieds-plugin' ); ?></label>
            <input id="min-price" class="inputbox money" type="text" name="searchpricemin" value="<?php echo esc_attr( $form['min_price'] ); ?>">
            <label for="max-price"><?php _e( "Max", 'another-wordpress-classifieds-plugin' ); ?></label>
            <input id="max-price" class="inputbox money" type="text" name="searchpricemax" value="<?php echo esc_attr( $form['max_price'] ); ?>">
        </label>
        <?php echo awpcp_form_error('min_price', $errors); ?>
        <?php echo awpcp_form_error('max_price', $errors); ?>
    </p>
    <?php endif ?>

    <?php
    $options = array(
        'showTextField' => true,
        'maxRegions' => ($ui['allow-user-to-search-in-multiple-regions'] ? 10 : 1),
    );

    $selector = awpcp_multiple_region_selector( $form['regions'], $options );
    echo $selector->render( 'search', array(), $errors );
    ?>

    <?php
        echo awpcp_form_fields()->render_fields(
            $form,
            $errors,
            null,
            array( 'category' => 0, 'action' => 'search' )
        );
    ?>

    <input type="submit" class="button" value="<?php echo esc_attr( _x( 'Start Search', 'ad search form', 'another-wordpress-classifieds-plugin' ) ); ?>" />
</form>
