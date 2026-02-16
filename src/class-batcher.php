<?php

namespace GWiz_Batcher;

defined( 'ABSPATH' ) || die();

/**
 * @phpstan-type GWizBatcherArgs array{
 *     title: string,
 *     id: string,
 *     show_form_selector?: boolean,
 *     require_form_selection?: boolean,
 *     create_admin_page?: boolean,
 *     additional_inputs?: string|null,
 *     hide_on_complete?: boolean,
 *     size: int,
 *     get_items: callable,
 *     process_item: callable,
 *     on_finish?: callable,
 * }
 *
 * @phpstan-type GFMenuItem array{
 *     name: string,
 *     label: string,
 *     callback: callable,
 *     permission: string,
 * }
 */
class Batcher {

	/**
	 * @var GWizBatcherArgs
	 */
	private $_args;

	/**
	 * @param GWizBatcherArgs $args
	 */
	public function __construct( $args ) {

		/** @phpstan-ignore-next-line */
		$this->_args = wp_parse_args( $args, [
			'title'                  => 'GW Batcher',
			'id'                     => 'gw-batcher',
			'show_form_selector'     => false,
			'require_form_selection' => false,
			'create_admin_page'      => true,
			'additional_inputs'      => null,
			'hide_on_complete'       => true,
		] );

		add_action( 'wp_ajax_gw_batch_' . $this->_args['id'], [ $this, 'batch' ] );
		add_action( 'wp_ajax_nopriv_gw_batch_' . $this->_args['id'], [ $this, 'batch' ] );

		if ( $this->_args['create_admin_page'] ) {
			add_filter( 'gform_addon_navigation', [ $this, 'add_menu_item' ] );
		}
	}

	/**
	 * Adds the menu item to the Gravity Forms menu.
	 *
	 * @param GFMenuItem[] $menu_items
	 *
	 * @return GFMenuItem[]
	 */
	function add_menu_item( $menu_items ) {
		$menu_items[] = [
			'name'       => $this->_args['id'],
			'label'      => $this->_args['title'],
			'callback'   => [ $this, 'admin_page' ],
			'permission' => 'gform_full_access',
		];

		return $menu_items;
	}

	/**
	 * Renders the output for the batcher. If create_admin_page is set to false, this method is meant to be called
	 * directly to place batchers into GF Settings API, etc.
	 *
	 * @return string
	 */
	public function render() {
		ob_start();
		?>
		<style>

			.gform-settings-save-container {
				flex-wrap: wrap;
			}

			#gwb-progress-container {
				display: none;
				align-items: center;
				gap: 16px;
				width: 100%;
				flex: none;
				border-top: 1px solid #E4ECF6;
			}

			#gwb-preview-label {
				font-weight: bold;
				font-size: 14px;
				line-height: 1;
			}

			#gwb-preview {
				background: #DCE7F4;
				border-radius: 22px;
				height: 1.7rem;
				flex: 1;
				max-width: 100%;
				overflow: hidden;
			}

			#gwb-preview span {
				display: block;
				height: 100%;
				width: 0;
				background: #0f3d6c;
				border-radius: 0;
				transition: width 0.3s ease;
			}

			#gw-batcher .gform-settings-field {
				margin-bottom: 16px;
			}

			#gw-batcher .gform-settings-field .gform-settings-field__header {
				margin-bottom: 8px;
			}

			#gw-batcher .gform-settings-field .gform-settings-label {
				font-weight: 600;
				font-size: 13px;
			}

			#gw-batcher .gform-settings-field .gform-settings-description {
				display: block;
				margin-top: 8px;
				color: #646970;
				font-size: 12px;
			}

			#gw-batcher .gform-settings-field select,
			#gw-batcher .gform-settings-field input[type="number"],
			#gw-batcher .gform-settings-field input[type="text"] {
				width: 100%;
				max-width: 400px;
			}

			#gw-batcher .gform-settings-input__container {
				max-width: 400px;
			}

			#gw-batcher .gform-admin-input {
				padding: 8px 12px;
				border: 1px solid #8c8f94;
				border-radius: 4px;
				font-size: 14px;
			}

			#gw-batcher .gform-admin-input:focus {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
				outline: none;
			}

			.gform-settings-save-container {
				display: flex;
				align-items: flex-start;
				gap: 16px;
			}

			.gform-settings-save-container .alert {
				margin: -1px 0 0;
				height: 44px;
				padding: 0 1rem 0 2.875rem;
				line-height: 44px;
			}

			.gform-settings-save-container .alert::before {
				height: 1.75rem;
				width: 1.75rem;
				margin-top: -0.875rem;
			}

			.gform-settings-save-container .alert::after {
				height: 1rem;
				width: 1rem;
				left: 0.875rem;
				margin-top: -0.5rem;
				background-size: 50%;
			}
		</style>

		<div id="gw-batcher">
			<?php
			if ( isset( $this->_args['show_form_selector'] ) && $this->_args['show_form_selector'] ) {
				$forms = \GFAPI::get_forms( true, false, 'title', 'ASC' );
				?>
				<div class="gform-settings-field gform-settings-field__select" id="gform_setting_gwb_form">
					<div class="gform-settings-field__header">
						<label class="gform-settings-label" for="gwb-form"><?php esc_html_e( 'Select a Form', 'gravityforms' ); ?></label>
					</div>
					<select name="gwb-form" id="gwb-form" class="gform-admin-input">
						<?php if ( ! $this->_args['require_form_selection'] ) : ?>
							<option value=""><?php esc_html_e( 'All Forms', 'gravityforms' ); ?></option>
						<?php endif; ?>
						<?php foreach ( $forms as $form ) : ?>
							<option value="<?php echo esc_attr( $form['id'] ); ?>"><?php echo esc_html( $form['title'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php
			}
			?>

			<?php
			if ( ! empty( $this->_args['additional_inputs'] ) ) {
				echo '<div id="gwb-additional-inputs">';
				echo $this->_args['additional_inputs'];
				echo '</div>';
			}
			?>
		</div>

		<script>

			var ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>',
				action = 'gw_batch_<?php echo $this->_args['id']; ?>',
				nonce = '<?php echo wp_create_nonce( "gw_batch_{$this->_args['id']}" ); ?>',
				size = <?php echo $this->_args['size']; ?>,
				hideOnComplete = <?php echo $this->_args['hide_on_complete'] ? 'true' : 'false'; ?>;

			jQuery(document).ready(function($) {
				var $preview = $('#gwb-preview'),
					$progressContainer = $('#gwb-progress-container'),
					$start = $('#gwb-start');

				$start.on('click', function () {
					var formId = undefined;
					var additionalInputs = undefined;

					$start.prop('disabled', true);

					// Hide success/error banners and show progress bar when starting a new batch
					$('#gwb-success, #gwb-error').hide();
					$progressContainer.css('display', 'block');
					$preview.find('span').width('0%');

					if ($('select#gwb-form').length) {
						formId = $('select#gwb-form').val();
					}

					if ($('#gwb-additional-inputs').length) {
						additionalInputs = jQuery('#gwb-additional-inputs :input').serializeArray();

						/*
						 * Serialize array will create an array that looks like...
						 *
						 * [
						 *     {
						 *         "name": "number_of_entries",
						 *         "value": "50"
						 *     }
						 * ]
						 *
						 * Convert it to a simple key/value object.
						 */
						additionalInputs = additionalInputs.reduce(function (acc, input) {
							acc[input.name] = input.value;
							return acc;
						}, {});
					}

					gwBatch(size, 1, 0, null, formId, additionalInputs);
				});

				function gwBatch(size, page, count, total, formId, additionalInputs) {

					if ( !additionalInputs ) {
						additionalInputs = {};
					}

					$.post(ajaxUrl, Object.assign({}, {
						action: action,
						nonce: nonce,
						size: size,
						page: page,
						total: total,
						count: count,
						form_id: formId,
					}, additionalInputs), function (response) {

						if (response.error) {
							console.log(response.data);
							$('#gwb-error').show();
							$progressContainer.hide();
							$start.prop('disabled', false);
						} else if (response.success) {
							if (typeof response.data == 'string' && response.data == 'done') {
								$preview.find('span').width('100%');

								$('#gwb-success').show();

								if (hideOnComplete) {
									$progressContainer.hide();
									$start.hide();
									$('.gform-settings-panel').hide();
								} else {
									// Hide progress bar and re-enable the start button for another batch
									$progressContainer.hide();
									$start.prop('disabled', false);
								}
							} else {
								$preview.find('span').width((response.data.count / response.data.total * 100) + '%');
								gwBatch(response.data.size, response.data.page, response.data.count, response.data.total, response.data.form_id);
							}
						}

					});

				}

			});
		</script>
		<?php
		/** @var string */
		$output = ob_get_clean();

		return $output;
	}

	/**
	 * Renders the admin page for the batcher.
	 *
	 * @return void
	 */
	public function admin_page() {
		\GFForms::admin_header( array(), false );
		?>
		<div class="gform-settings-panel gform-settings-panel--full">
			<header class="gform-settings-panel__header">
				<h4 class="gform-settings-panel__title"><?php echo esc_html( $this->_args['title'] ); ?></h4>
			</header>
			<div class="gform-settings-panel__content">
				<?php echo $this->render(); ?>
			</div>
		</div>

		<div class="gform-settings-save-container">
			<button id="gwb-start" class="button primary large"><?php esc_html_e( 'Start Batch', 'gravityforms' ); ?></button>
			<div id="gwb-progress-container">
				<p id="gwb-preview-label"><?php esc_html_e( 'Processing batch…', 'gravityforms' ); ?></p>
				<div id="gwb-preview"><span></span></div>
			</div>
			<div class="alert gforms_note_success" id="gwb-success" style="display: none;" role="alert">
				<?php esc_html_e( 'Batch completed successfully!', 'gravityforms' ); ?>
			</div>
			<div class="alert gforms_note_error" id="gwb-error" style="display: none;" role="alert">
				<?php esc_html_e( 'An error occurred during the batch process.', 'gravityforms' ); ?>
			</div>
		</div>
		<?php
		\GFForms::admin_footer();
	}

	/**
	 * Processes each batch.
	 *
	 * @return void
	 */
	public function batch() {

		$action  = $_POST['action'];
		$nonce   = $_POST['nonce'];
		$form_id = rgar( $_POST, 'form_id' );

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( 'Invalid nonce.' );
		}

		$size   = $_POST['size'];
		$page   = $_POST['page'];
		$offset = ( $page * $size ) - $size;
		$count  = max( 0, (int) $_POST['count'] );

		if ( $form_id ) {
			$items = $this->_args['get_items']( $size, $offset, $form_id );
		} else {
			$items = $this->_args['get_items']( $size, $offset );
		}

		$total = $items['total'];
		$items = $items['items'];

		foreach ( $items as $item ) {
			$this->_args['process_item']( $item );
			$count ++;
		}

		if ( $count >= $total ) {
			if ( isset( $this->_args['on_finish'] ) && is_callable( $this->_args['on_finish'] ) ) {
				$this->_args['on_finish']( $count, $total );
			}

			wp_send_json_success( 'done' );
		} else {
			$page ++;
			wp_send_json_success( compact( 'size', 'page', 'count', 'total', 'form_id' ) );
		}

	}

}
