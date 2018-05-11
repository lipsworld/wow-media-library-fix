<?php

namespace WowMediaLibraryFix;

?>
<div class="wrap">
	<h1>Fix Media Library Inconsistence</h1>

	<?php echo $messages ?>

	<h2>Process All Attachments</h2>
	<form method="post" novalidate="novalidate">
		<?php wp_nonce_field( 'wow-media-library-fix' ); ?>

		<p>
			Don't forget to make a full backup of your files and database
			before trying.
		</p>
		<table class="form-table" id="wow_mlf_config" <?php echo $style_config ?>>
			<?php
			AdminUi::tr_checkbox( 'Metadata', array(
				'id' => 'wow_mlf_config_regenerate_metadata',
				'name' => 'Regenerate attachments metadata and thumbnails',
				'value' => true,
				'description' => 'Rebuilds _wp_attachment_metadata meta field based on actual image size and regenerate all thumbnails'
			) );
			AdminUi::tr_radiogroup( 'Unreferenced Images', array(
				'name' => 'wow_mlf_config_files_unreferenced',
				'value' => '',
				'values' => array(
					array(
						'value' => '',
						'name' => "Don't analyze"
					),
					array(
						'value' => 'log',
						'name' => 'Write a note to log'
					),
					array(
						'value' => 'move',
						'name' => 'Move to wp-content/uploads/unreferenced folder'
					),
					array(
						'value' => 'delete',
						'name' => 'Delete'
					)
				),
				'description' => 'Finds out all unreferenced images in your wp-content/uploads/&lt;year&gt; folders and acts appropriately. Search is made against references in Media Library only.'
			) );
			AdminUi::tr_radiogroup( 'Unreferenced Thumbnails', array(
				'name' => 'wow_mlf_config_files_thumbnails',
				'value' => 'move',
				'values' => array(
					array(
						'value' => '',
						'name' => "Don't analyze"
					),
					array(
						'value' => 'log',
						'name' => 'Write a note to log'
					),
					array(
						'value' => 'move',
						'name' => 'Move to wp-content/uploads/unreferenced folder'
					),
					array(
						'value' => 'delete',
						'name' => 'Delete'
					)
				),
				'description' => 'Finds all image files identified as thumbnails belonging to the attachment before thumbnails regeneration, even if there is no reference to them in the database. Helpful if there are many unknown old thumbnails exists of already unregistered sizes.'
			) );
			AdminUi::tr_radiogroup( 'Post GUID', array(
				'name' => 'wow_mlf_config_guid',
				'value' => '',
				'values' => array(
					array(
						'value' => '',
						'name' => "Don't analyze"
					),
					array(
						'value' => 'log',
						'name' => 'Write a note to log if there is a mismatch'
					),
					array(
						'value' => 'fix',
						'name' => 'Update if there is a mismatch'
					)
				),
				'description' => "It's not suggested to change post's GUID field since it's supposed to be a constant since creation. But normally those is built based on image URL for attachments, and it may be helpful to normalize it sometimes."
			) );

			AdminUi::tr_checkbox( 'Broken Attachments', array(
				'id' => 'wow_mlf_config_posts_delete_with_missing_images',
				'name' => 'Delete attachments with missing image files',
				'value' => false,
				'description' => 'Delete attachments from Media Library is image file it references to is missing and plugin failed to find it from GUID. Otherwise just log entry created.'
			) );
			AdminUi::tr_radiogroup( 'Duplicate Attachments', array(
				'name' => 'wow_mlf_config_posts_delete_duplicate_url',
				'value' => '',
				'values' => array(
					array(
						'value' => '',
						'name' => "Don't analyze"
					),
					array(
						'value' => 'log',
						'name' => 'Write a note to log if duplication found'
					),
					array(
						'value' => 'delete',
						'name' => 'Delete duplicate attachments'
					)
				),
				'description' => 'Attachments pointing the same image file with the same post_parent are often caused by some malfunction during original image upload process.'
			) );
			AdminUi::tr_radiogroup( 'Logging to', array(
				'name' => 'wow_mlf_config_log_to',
				'value' => 'screen',
				'values' => array(
					array(
						'value' => 'screen',
						'name' => 'Browser window'
					),
					array(
						'value' => 'file',
						'name' => 'wp-content/uploads/media-library.log file'
					)
				)
			) );
			AdminUi::tr_checkbox( 'Verbose logging', array(
				'id' => 'wow_mlf_config_log_verbose',
				'name' => 'Verbose logging',
				'value' => false
			) );
			?>
		</table>

		<div class="submit" id="wow_mlf_start_outer"
			<?php echo $style_start_outer ?>>
			<button	class="button button-primary wow_mlf_start">
				Start processing
			</button>
		</div>
		<div class="submit" id="wow_mlf_restart_outer" style="display: none">
			<button	class="button button-primary wow_mlf_start">
				Restart processing
			</button>
			<button	class="button wow_mlf_show_config">
				Change options
			</button>
		</div>
		<div class="submit" id="wow_mlf_continue_outer"
			<?php echo $style_continue_outer ?>>
			<button	class="button button-primary" id="wow_mlf_continue">
				Continue processing
			</button>
			<button	class="button wow_mlf_show_config">
				Change options
			</button>
		</div>
		<div class="submit" id="wow_mlf_working_outer" style="display: none">
			<div class="wow_mlf_loader" style="float: left"></div>

			<button class="button" id="wow_mlf_stop">Stop processing</button>
		</div>
	</form>

	<div id="wow_mlf_process" <?php echo $style_process ?>>
		<h2>Process Status</h2>

		<table class="form-table" style="float: left">
			<tr>
				<th>Attachments found:</th>
				<td id="wow_mlf_total">
					<?php echo htmlspecialchars( $process_total ) ?>
				</td>
			</tr>
			<tr>
				<th>Attachments processed:</th>
				<td id="wow_mlf_processed">
					<?php echo htmlspecialchars( $process_processed ) ?>
				</td>
			</tr>
			<tr>
				<th>Errors found:</th>
				<td id="wow_mlf_errors">
					<?php echo htmlspecialchars( $process_errors ) ?>
				</td>
			</tr>
			<tr id="wow_mlf_working_now" <?php echo $style_working_now ?>>
				<th>Now processing:</th>
				<td id="wow_mlf_now"></td>
			</tr>
			<tr id="wow_mlf_done" style="display: none">
				<th></th>
				<td><strong>Finished</strong></td>
			</tr>
			<tr id="wow_mlf_failed" style="display: none">
				<th></th>
				<td><strong>Failed</strong></td>
			</tr>
		</table>

		<h2>Messages</h2>
		<div id="wow_mlf_notices"></div>
	</div>
</div>

<p>
	Still need help with your website?
	<a href="https://wowpress.host/professional-services/" target="_blank">Reach us</a>
</p>
