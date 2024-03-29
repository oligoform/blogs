<?php $page_id = 'awpcp-admin-csv-importer' ?>
<?php $page_title = awpcp_admin_page_title( __( 'Import Listings', 'another-wordpress-classifieds-plugin' ) ); ?>

<?php include(AWPCP_DIR . '/admin/templates/admin-panel-header.tpl.php') ?>

<?php if (!is_null($importer) && ($importer->ads_imported > 0 || $importer->ads_rejected > 0)): ?>
	<?php if ($test_import): ?>
			<h3><?php echo esc_html( __( 'Import Testing Results', 'another-wordpress-classifieds-plugin' ) ); ?></h3>
	<?php else: ?>
			<h3><?php echo esc_html( __( 'Final Import Results', 'another-wordpress-classifieds-plugin' ) ); ?></h3>
	<?php endif ?>

			<ul>
				<li><b><?php echo esc_html( __( 'Imported rows:', 'another-wordpress-classifieds-plugin' ) ); ?></b> <?php echo esc_html( $importer->ads_imported ); ?></li>
				<li><b><?php echo esc_html( __( 'Imported Picture count:', 'another-wordpress-classifieds-plugin' ) ); ?></b> <?php echo esc_html( $importer->images_imported ); ?></li>
				<li><b><?php echo esc_html( __( 'Rejected rows:', 'another-wordpress-classifieds-plugin' ) ); ?></b> <?php echo esc_html( $importer->ads_rejected ); ?></li>
			</ul>
<?php endif ?>

			<?php if (!empty($messages)): ?>
				<h3><?php echo esc_html( _x( 'Messages', 'csv importer', 'another-wordpress-classifieds-plugin' ) ); ?></h3>
				<ul>
				<?php foreach ($messages as $message): ?>
					<li><?php echo "$message" ?></li>
				<?php endforeach ?>
				</ul>
			<?php endif ?>

			<?php if (!empty($errors)): ?>
				<h3><?php echo esc_html( _x( 'Errors', 'csv importer', 'another-wordpress-classifieds-plugin' ) ); ?></h3>
				<ul>
				<?php foreach ($errors as $error): ?>
					<li><?php echo "$error" ?></li>
				<?php endforeach ?>
				</ul>
			<?php endif ?>

			<form enctype="multipart/form-data" method="post">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="awpcp-importer-csv-file"><?php echo esc_html( __( 'CSV file', 'another-wordpress-classifieds-plugin' ) ); ?></label>
							</th>
							<td>
								<input id="awpcp-importer-csv-file" type="file" name="import" id="import" />
								<br/><?php echo awpcp_form_error('import', $form_errors) ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="awpcp-importer-zip-file"><?php echo esc_html( __( 'Zip file containing images', 'another-wordpress-classifieds-plugin' ) ); ?></label>
							</th>
							<td>
								<input id="awpcp-importer-zip-file" type="file" name="import_zip" id="import_zip" />
								<br/><?php echo awpcp_form_error('import_zip', $form_errors) ?>
							</td>
						</tr>
					</tbody>
				</table>

				<h3><?php echo esc_html( _x( 'If the CSV does not contain start/end dates, use these by default:', 'csv-importer', 'another-wordpress-classifieds-plugin' ) ); ?></h3>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="awpcp-importer-start-date"><?php echo esc_html( __( 'Start Date (mm/dd/yyyy)', 'another-wordpress-classifieds-plugin' ) ); ?></label>
							</th>
							<td>
								<input id="awpcp-importer-start-date" type="text" name="startDate" value="<?php echo esc_attr( $start_date ); ?>" />
								<br/><br/><?php echo awpcp_form_error('startDate', $form_errors) ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="awpcp-importer-end-date"><?php echo esc_html( __( 'End Date (mm/dd/yyyy)', 'another-wordpress-classifieds-plugin' ) ); ?></label>
							</th>
							<td>
								<input id="awpcp-importer-end-date" type="text" name="endDate" value="<?php echo esc_attr( $end_date ); ?>" />
								<br/><br/><?php echo awpcp_form_error('endDate', $form_errors) ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php echo esc_html( __( 'Date Format', 'another-wordpress-classifieds-plugin' ) ); ?>
							</th>
							<td>
								<br/><br/><?php echo awpcp_form_error('date_fmt', $form_errors) ?>
								<input id="awpcp-importer-format-us-date" type="radio" name="date_fmt" value="us_date" <?php if ($import_date_format == "us_date") echo "checked"; ?> />
								<label for="awpcp-importer-format-us-date">
									<?php echo esc_html( __( 'US Date Only (mm/dd/year)', 'another-wordpress-classifieds-plugin' ) ); ?></label>
								<br/>

								<input id="awpcp-importer-format-uk-date" type="radio" name="date_fmt" value="uk_date" <?php if ($import_date_format == "uk_date") echo "checked"; ?> />
								<label for="awpcp-importer-format-uk-date"><?php echo esc_html( __( 'UK Date Only (dd/mm/year)', 'another-wordpress-classifieds-plugin' ) ); ?></label>
								<br/>

								<input id="awpcp-importer-format-us-date-time" type="radio" name="date_fmt" value="us_date_time" <?php if ($import_date_format == "us_date_time") echo "checked"; ?> />
								<label for="awpcp-importer-format-us-date-time">
									<?php echo esc_html( __( 'US Date and Time (mm/dd/year hh:mm:ss)', 'another-wordpress-classifieds-plugin' ) ); ?></label>
								<br/>

								<input id="awpcp-importer-format-uk-date-time" type="radio" name="date_fmt" value="uk_date_time" <?php if ($import_date_format == "uk_date_time") echo "checked"; ?> />
								<label for="awpcp-importer-format-uk-date-time"><?php echo esc_html( __( 'UK Date and Time (dd/mm/year hh:mm:ss)', 'another-wordpress-classifieds-plugin' ) ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php echo esc_html( __( 'Separators Used in CSV', 'another-wordpress-classifieds-plugin' ) ); ?>
							</th>
							<td>
								<label for="awpcp-importer-date-separator"><?php echo esc_html( __( 'Date Separator', 'another-wordpress-classifieds-plugin' ) ); ?></label>
								<input id="awpcp-importer-date-separator" type="text" maxlength="1" size="1" name="sep_date" value="<?php echo esc_attr( $date_sep ); ?>" />
								<br/><br/><?php echo awpcp_form_error('sep_date', $form_errors) ?>

								<label for="awpcp-importer-time-separator"><?php echo esc_html( __( 'Time Separator', 'another-wordpress-classifieds-plugin' ) ); ?></label>
								<input id="awpcp-importer-time-separator" type="text" maxlength="1" size="1" name="sep_time" value="<?php echo esc_attr( $time_sep ); ?>" />
								<br/><br/><?php echo awpcp_form_error('sep_time', $form_errors) ?>

								<label for="awpcp-importer-image-separator"><?php echo esc_html( __( 'Image Separator', 'another-wordpress-classifieds-plugin' ) ); ?></label>
								<input id="awpcp-importer-image-separator" type="text" maxlength="1" size="1" name="sep_image" value=";" disabled="disabled" /> <?php echo esc_html( __( '(semi-colon)', 'another-wordpress-classifieds-plugin') ); ?>
								<br/><br/><?php echo awpcp_form_error('sep_image', $form_errors) ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php echo esc_html( __( 'Category Handling on Import', 'another-wordpress-classifieds-plugin' ) ); ?>
							</th>
							<td>
								<select name="auto_cat" id="auto_cat">
									<option value=1 <?php if ($auto_cat == "1") echo 'selected="selected"'; ?>><?php echo esc_html( __( 'Auto create Categories', 'another-wordpress-classifieds-plugin' ) ); ?></option>
									<option value=0 <?php if ($auto_cat == "0") echo 'selected="selected"'; ?>><?php echo esc_html( __( 'Generate errors if Category not found', 'another-wordpress-classifieds-plugin' ) ); ?></option>
								</select><br/>
								<?php echo awpcp_form_error('auto_cat', $form_errors) ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php echo esc_html( _x( 'Assign Ads to a user?', 'csv-importer', 'another-wordpress-classifieds-plugin' ) ); ?>
							</th>
							<td>
								<input type="checkbox" name="assign_user" id="awpcp-importer-auto-assign-user" value="1" <?php echo $assign_user == 1 ? 'checked="checked"' : ''; ?> />
								<label for="awpcp-importer-auto-assign-user"><?php echo esc_html( _x( 'Assign Ads to a user?', 'csv-importer', 'another-wordpress-classifieds-plugin' ) ); ?></label><br/>
								<span class="description"><?php echo esc_html( __( 'If checked, the Ads will belong to the user specified below.', 'another-wordpress-classifieds-plugin' ) ); ?></span>
								<br/><br/><?php echo awpcp_form_error('assign_user', $form_errors) ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="awpcp-importer-user"><?php echo esc_html( __( 'Default user', 'another-wordpress-classifieds-plugin' ) ); ?></label>
							</th>
							<td>
                                <?php
                                    echo awpcp_users_field()->render( array(
                                        'selected' => empty( $assigned_user ) ? null : $assigned_user,
                                        'label' => false,
                                        'default' => __( 'use spreadsheet information', 'another-wordpress-classifieds-plugin' ),
                                        'id' => 'awpcp-importer-user',
                                        'name' => 'user',
                                        'include-full-user-information' => false,
                                    ) );
                                ?><br/>
								<span class="description"><?php echo esc_html( _x(  "Any value other than 'use spreadsheet information' means the Ads will be associated to the selected user if: the username column is not present in the CSV file, there is no user with that username and we couldn't find a user with the contact_email address specified in the CSV file.", 'csv-importer', 'another-wordpress-classifieds-plugin' ) ); ?></span>
								<br/><br/><?php echo awpcp_form_error('user', $form_errors) ?>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" class="button" name="do_test_import" value="<?php echo esc_html( __( 'Test Import', 'another-wordpress-classifieds-plugin' ) ); ?>"></input>
					<input type="submit" class="button-primary button" name="do_import" value="<?php echo esc_html( __( 'Import', 'another-wordpress-classifieds-plugin' ) ); ?>"></input>
				</p>
			</form>
		</div><!-- end of .awpcp-main-content -->
	</div><!-- end of .page-content -->
</div><!-- end of #page_id -->
