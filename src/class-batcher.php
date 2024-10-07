<?php

namespace GWiz_Batcher;

defined( 'ABSPATH' ) || die();

/**
 * @phpstan-type GWizBatcherArgs array{
 *     title: string,
 *     id: string,
 *     show_form_selector?: boolean,
 *     require_form_selection?: boolean,
 *     additional_inputs?: string,
 *     size: number,
 *     get_items: callable,
 *     process_item: callable,
 *     on_finish: callable,
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
			#gwb-preview {
				border: 1px solid #ccc;
				height: 20px;
				width: 100%;
				margin-bottom: 20px;
				padding: 2px;
				border-radius: 4px;
			}

			#gwb-preview span {
				display: block;
				height: 100%;
				width: 0;
				background-color: #999;
				border-radius: 3px;
				transition: all 0.5s ease;
			}
		</style>

		<div id="gw-batcher">
			<div class="notice updated" id="gwb-success" style="display: none;">
				<p><strong>Success!</strong></p>
			</div>

			<div id="gwb-preview"><span></span></div>

			<?php
			if ( isset( $this->_args['show_form_selector'] ) && $this->_args['show_form_selector'] ) {
				$forms = \GFAPI::get_forms( true, false, 'title', 'ASC' );

				echo '<select name="gwb-form" id="gwb-form">';

				if ( ! $this->_args['require_form_selection'] ) {
					echo '<option value="">All Forms</option>';
				}

				foreach ( $forms as $form ) {
					echo '<option value="' . $form['id'] . '">' . $form['title'] . '</option>';
				}

				echo '</select>';
			}
			?>

			<?php
			if ( ! empty( $this->_args['additional_inputs'] ) ) {
				echo '<div id="gwb-additional-inputs">';
				echo $this->_args['additional_inputs'];
				echo '</div>';
			}
			?>

			<button id="gwb-start" class="button-primary">Start Batch</button>
		</div>

		<script>

			var ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>',
				action = 'gw_batch_<?php echo $this->_args['id']; ?>',
				nonce = '<?php echo wp_create_nonce( "gw_batch_{$this->_args['id']}" ); ?>',
				size = <?php echo $this->_args['size']; ?>;

			(function ($) {

				var $preview = $('#gwb-preview'),
					$start = $('#gwb-start');

				$start.click(function () {
					var formId = undefined;
					var additionalInputs = undefined;

					$start.prop('disabled', true);

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
						} else if (response.success) {
							if (typeof response.data == 'string' && response.data == 'done') {
								$preview.find('span').width('100%');

								$('#gwb-success').show(500);
								$preview.hide(500);
								$('#gwb-start, #gwb-form').hide(500);
							} else {
								$preview.find('span').width((response.data.count / response.data.total * 100) + '%');
								gwBatch(response.data.size, response.data.page, response.data.count, response.data.total, response.data.form_id);
							}
						}

					});

				}

			})(jQuery);
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
		?>
		<style>
			h1 {
				font-family: sans-serif;
				margin-bottom: 20px;
			}
		</style>

		<div class="wrap">
			<h2><?php echo $this->_args['title']; ?></h2>

			<?php echo $this->render(); ?>
		</div>
		<?php
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
