<?php

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Paystack_Recurrent_Billing_Subscribers extends WP_List_Table {

	/** ************************************************************************
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 ***************************************************************************/
	function __construct(){
		global $status, $page;

		//Set parent defaults
		parent::__construct( array(
			'singular'	=> 'subscriber',	 //singular name of the listed records
			'plural'	=> 'subscribers',	//plural name of the listed records
			'ajax'		=> false		//does this table support ajax?
		) );

	}


	/** ************************************************************************
	 * Recommended. This method is called when the parent class can't find a method
	 * specifically build for a given column. Generally, it's recommended to include
	 * one method for each column you want to render, keeping your package class
	 * neat and organized. For example, if the class needs to process a column
	 * named 'title', it would first see if a method named $this->column_title()
	 * exists - if it does, that method will be used. If it doesn't, this one will
	 * be used. Generally, you should try to use custom column methods as much as
	 * possible.
	 *
	 * Since we have defined a column_title() method later on, this method doesn't
	 * need to concern itself with any column with a name of 'title'. Instead, it
	 * needs to handle everything else.
	 *
	 * For more detailed insight into how columns are handled, take a look at
	 * WP_List_Table::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 * @param array $column_name The name/slug of the column to be processed
	 * @return string Text or HTML to be placed inside the column <td>
	 **************************************************************************/
	function column_default($item, $column_name){
		switch($column_name){
			case 'debt':
			case 'phone':
			case 'email':
			case 'metadata':
				return $item[$column_name];
			default:
				return print_r($item,true); //Show the whole array for troubleshooting purposes
		}
	}


	/** ************************************************************************
	 * Recommended. This is a custom column method and is responsible for what
	 * is rendered in any column with a name/slug of 'title'. Every time the class
	 * needs to render a column, it first looks for a method named
	 * column_{$column_title} - if it exists, that method is run. If it doesn't
	 * exist, column_default() is called instead.
	 *
	 * This example also illustrates how to implement rollover actions. Actions
	 * should be an associative array formatted as 'slug'=>'link html' - and you
	 * will need to generate the URLs yourself. You could even ensure the links
	 *
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @param array $item A singular item (one full row's worth of data)
	 * @return string Text to be placed inside the column <td> (subscriber name only)
	 **************************************************************************/
	function column_name($item){
		$name=strtoupper($item['lastname']) . ", " . ucwords($item['firstname']);
		$payments = json_decode($item['payments']);

		//Build row actions
		$actions = array();
		if(0 == $item['stopped']){
			$actions['poke'] = sprintf('<a href="?page=%s&stopped=%s&action=%s&subscriber=%s&transient=%s"
					onclick="return confirm(\'Are you sure you want to charge '.esc_attr($name).'\\\'s bank account for '.number_format($payments[0]->amount/100, 2).' right now?\')">Poke</a>',
					$_REQUEST['page'],
					$_REQUEST['stopped'],
					'poke',
					$item['id'],
					get_transient( 'paystack_recurrent_billing_poke_transient' )
				);
			$actions['stop'] = sprintf('<a href="?page=%s&stopped=%s&action=%s&subscriber=%s"
			onclick="return confirm(\'Are you sure you want to stop '.esc_attr($name).'\\\'s subscription?\')">Stop</a>',$_REQUEST['page'],$_REQUEST['stopped'],'stop',$item['id']);
		}

		if(1 == $item['stopped'] && $item['debt'] > 0){
			$actions['resume'] = sprintf('<a href="?page=%s&stopped=%s&action=%s&subscriber=%s"
			onclick="return confirm(\'Are you sure you want to resume '.esc_attr($name).'\\\'s subscription?\')">Resume</a>',$_REQUEST['page'],$_REQUEST['stopped'],'resume',$item['id']);
		}

		//Return the name contents
		return sprintf('%1$s <span style="color:silver">(ip:%2$s)</span>%3$s',
			/*$1%s*/ esc_html($name),
			/*$2%s*/ $item['ip'],
			/*$3%s*/ $this->row_actions($actions)
		);
	}

	function column_payments($item){
		$payments = json_decode($item['payments']);
		$content = "";
		foreach($payments as $p){
			$content .= $content ? '<br />' : '';
			if(property_exists($p, 'event')){
				$content.= number_format($p->data->amount/100, 2) . ' on ' . date('M jS, Y H:ia', strtotime($p->data->paid_at));
			} else if(property_exists($p, 'data')){
				$content.= number_format($p->data->amount/100, 2) . ' on ' . date('M jS, Y H:ia', strtotime($p->data->transaction_date));
			} else {
				$content.= number_format($p->amount/100, 2) . ' on ' . date('M jS, Y H:ia', strtotime($p->transaction_date));
			}
		}

		//Return the payments contents
		return $content;
	}

	function column_stopped($item){
		$stopped = $item['stopped'];

		//Return the payments contents
		return $stopped ? 'Yes' : 'No';
	}

	function column_whensubscribed($item){
		return date('F jS, Y H:ia', strtotime($item['whensubscribed']));
	}


	/** ************************************************************************
	 * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
	 * is given special treatment when columns are processed. It ALWAYS needs to
	 * have it's own method.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @param array $item A singular item (one full row's worth of data)
	 * @return string Text to be placed inside the column <td> (subscriber name only)
	 **************************************************************************/
	function column_cb($item){
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ $this->_args['singular'],
			/*$2%s*/ $item['id']				//The value of the checkbox should be the record's id
		);
	}


	/** ************************************************************************
	 * REQUIRED! This method dictates the table's columns and titles. This should
	 * return an array where the key is the column slug (and class) and the value
	 * is the column's title text. If you need a checkbox for bulk actions, refer
	 * to the $columns array below.
	 *
	 * The 'cb' column is treated differently than the rest. If including a checkbox
	 * column in your table you must create a column_cb() method. If you don't need
	 * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	function get_columns(){
		$columns = array(
			'cb'	=>	'<input type="checkbox" />', //Render a checkbox instead of text
			'name'	=>	'Name',
			'debt'	=>	'Debt',
			'email'	=>	'Email',
			'phone'	=>	'Phone',
			'metadata'	=>	'Metadata',
			'stopped'	=>	'Stopped?',
			'payments'	=>	'Payments?',
			'whensubscribed'	=> 'Since',
		);
		return $columns;
	}


	/** ************************************************************************
	 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	 * you will need to register it here. This should return an array where the
	 * key is the column that needs to be sortable, and the value is db column to
	 * sort by. Often, the key and value will be the same, but this is not always
	 * the case (as the value is a column name from the database, not the list table).
	 *
	 * This method merely defines which columns should be sortable and makes them
	 * clickable - it does not handle the actual sorting. You still need to detect
	 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
	 * your data accordingly (usually by modifying your query).
	 *
	 * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
	 **************************************************************************/
	function get_sortable_columns() {
		$sortable_columns = array(
			'name'	 => array('name',false),	 //true means it's already sorted
			'debt'	=> array('debt',false),
			'whensubscribed'	=> array('whensubscribed',false)
		);
		return $sortable_columns;
	}


	/** ************************************************************************
	 * Optional. If you need to include bulk actions in your list table, this is
	 * the place to define them. Bulk actions are an associative array in the format
	 * 'slug'=>'Visible Title'
	 *
	 * If this method returns an empty value, no bulk action will be rendered. If
	 * you specify any bulk actions, the bulk actions box will be rendered with
	 * the table automatically on display().
	 *
	 * Also note that list tables are not automatically wrapped in <form> elements,
	 * so you will need to create those manually in order for bulk actions to function.
	 *
	 * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	function get_bulk_actions() {
		$actions = array(
			'stop'	=> 'Stop',
		);
		return $actions;
	}


	/** ************************************************************************
	 * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
	 * For this example package, we will handle it in the class to keep things
	 * clean and organized.
	 *
	 * @see $this->prepare_items()
	 **************************************************************************/
	function process_bulk_action() {
		// security check!
		if ( isset( $_POST['_wpnonce'] ) && ! empty( $_POST['_wpnonce'] ) ) {
			$nonce  = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );
			$action = 'bulk-' . $this->_args['plural'];

			if ( ! wp_verify_nonce( $nonce, $action ) ){
				echo "nonce verification failed";
				return;
			}

		}

		$ids = array_key_exists($this->_args['singular'] , $_GET) ? $_GET[$this->_args['singular']] : [];

		//Detect when a bulk action is being triggered...
		if( 'stop'===$this->current_action() ) {
			if(is_array($ids)){
				foreach($ids as $subscriber_id) {
					paystack_recurrent_billing_stop_subscription_for_id($subscriber_id);
				}
			} else {
				paystack_recurrent_billing_stop_subscription_for_id($ids);
			}
			set_transient( 'paystack_recurrent_billing_message_transient', "Subscription has been stopped." );
			set_transient( 'paystack_recurrent_billing_message_type_transient', 'updated' );
		}

		if( 'resume'===$this->current_action() ) {
			if(paystack_recurrent_billing_resume_subscription_for_id($ids)){
				set_transient( 'paystack_recurrent_billing_message_transient', "Subscription has been resumed." );
				set_transient( 'paystack_recurrent_billing_message_type_transient', 'updated' );
			} else {
				set_transient( 'paystack_recurrent_billing_message_transient', "Please try again" );
				set_transient( 'paystack_recurrent_billing_message_type_transient', 'error' );
			}
		}

		if( 'poke'===$this->current_action() ) {
			if( ($_GET['transient'] == get_transient('paystack_recurrent_billing_poke_transient'))) {
				$subscriber = paystack_recurrent_billing_get_subscriber_by_id($ids);
				$paid = paystack_recurrent_billing_poke_subscriber($subscriber);
				delete_transient('paystack_recurrent_billing_poke_transient');
				if($paid){
					$message = "You have successfully charged {$subscriber->email} for their repayment.";
				} else {
					$message = "There was a problem charging {$subscriber->email} for their repayment.";
				}
				set_transient( 'paystack_recurrent_billing_message_transient', $message );
				set_transient( 'paystack_recurrent_billing_message_type_transient', ($paid ? 'updated' : 'error') );
			} else {
				set_transient( 'paystack_recurrent_billing_message_transient', "Please try again" );
				set_transient( 'paystack_recurrent_billing_message_type_transient', 'error' );
			}
		}


	}


	/** ************************************************************************
	 * REQUIRED! This is where you prepare your data for display. This method will
	 * usually be used to query the database, sort and filter the data, and generally
	 * get it ready to be displayed. At a minimum, we should set $this->items and
	 * $this->set_pagination_args(), although the following properties and methods
	 * are frequently interacted with here...
	 *
	 * @global WPDB $wpdb
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {
		global $wpdb; //This is used only if making any database queries

		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = 50;

		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & titles), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		 */
		$this->_column_headers = array($columns, $hidden, $sortable);

		/**
		 * Optional. You can handle your bulk actions however you see fit. In this
		 * case, we'll handle them within our package just to keep things clean.
		 */
		$this->process_bulk_action();

		/***********************************************************************
		 * ---------------------------------------------------------------------
		 * vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv
		 *
		 * In a real-world situation, this is where you would place your query.
		 *
		 * For information on making queries in WordPress, see this Codex entry:
		 * http://codex.wordpress.org/Class_Reference/wpdb
		 *
		 * ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
		 * ---------------------------------------------------------------------
		 **********************************************************************/

		/**
		 * REQUIRED for pagination. Let's figure out what page the user is currently
		 * looking at. We'll need this later, so you should always include it in
		 * your own package classes.
		 */
		$current_page = $this->get_pagenum();

		/**
		 * This checks for sorting input and sorts the data in our array accordingly.
		 *
		 * In a real-world situation involving a database, you would probably want
		 * to handle sorting by passing the 'orderby' and 'order' values directly
		 * to a custom query. The returned data will be pre-sorted, and this array
		 * sorting technique would be unnecessary.
		 */
		$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id'; //If no sort, default to title
		$orderby = ($orderby == 'name') ? 'lastname' : $orderby;
		$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc'; //If no order, default to desc

		/**
		 * Instead of querying a database, we're going to fetch the example data
		 * property we created for use in this plugin. This makes this example
		 * package slightly different than one you might build on your own. In
		 * this example, we'll be using array manipulation to sort and paginate
		 * our data. In a real-world implementation, you will probably want to
		 * use sort and pagination data to build a custom query instead, as you'll
		 * be able to use your precisely-queried data immediately.
		 */
		$data = $wpdb->get_results(
			'SELECT * FROM `'.paystack_recurrent_billing_table().'` ORDER BY '.$orderby.' '.$order.'
			LIMIT ' . (($current_page-1) * $per_page) . ' , ' . $per_page,
			ARRAY_A
		);

		/**
		 * REQUIRED for pagination. Let's check how many items are in our data array.
		 * In real-world use, this would be the total number of items in your database,
		 * without filtering. We'll need this later, so you should always include it
		 * in your own package classes.
		 */
		$total_items = $wpdb->get_var(
			'SELECT count(*) FROM `'.paystack_recurrent_billing_table().'` ORDER BY id DESC'
		);

		/**
		 * REQUIRED. Now we can add our *sorted* data to the items property, where
		 * it can be used by the rest of the class.
		 */
		$this->items = $data;


		/**
		 * REQUIRED. We also have to register our pagination options & calculations.
		 */
		$this->set_pagination_args( array(
			'total_items' => $total_items,					//WE have to calculate the total number of items
			'per_page'	=> $per_page,					 //WE have to determine how many items to show on a page
			'total_pages' => ceil($total_items/$per_page)	//WE have to calculate the total number of pages
		) );
	}


}





/** ************************ REGISTER THE TEST PAGE ****************************
 *******************************************************************************
 * Now we just need to define an admin page. For this example, we'll add a top-level
 * menu item to the bottom of the admin menus.
 */
function paystack_recurrent_billing_add_menu_items(){
	add_menu_page('Paystack Recurrent Billing Subscribers', 'Paystack Recurrent Billing Subscribers', 'activate_plugins', 'paystack_recurrent_billing_subscribers_table', 'paystack_recurrent_billing_render_subscribers_table');
}
add_action('admin_menu', 'paystack_recurrent_billing_add_menu_items');


/** *************************** RENDER TEST PAGE ********************************
 *******************************************************************************
 * This function renders the admin page and the example list table. Although it's
 * possible to call prepare_items() and display() from the constructor, there
 * are often times where you may need to include logic here between those steps,
 * so we've instead called those methods explicitly. It keeps things flexible, and
 * it's the way the list tables are used in the WordPress core.
 */
function paystack_recurrent_billing_render_subscribers_table(){

	//Create an instance of our package class...
	$testListTable = new Paystack_Recurrent_Billing_Subscribers();
	//Fetch, prepare, sort, and filter our data...
	$testListTable->prepare_items();

	// only one window can poke at a time
	set_transient( 'paystack_recurrent_billing_poke_transient', wp_generate_password(30, false), 30 * MINUTE_IN_SECONDS );
	$message = get_transient( 'paystack_recurrent_billing_message_transient' );
	$message_type = get_transient( 'paystack_recurrent_billing_message_type_transient' );
	delete_transient( 'paystack_recurrent_billing_message_transient' );
	delete_transient( 'paystack_recurrent_billing_message_type_transient' );
	?>
	<div class="wrap">

		<div id="icon-users" class="icon32"><br/></div>
		<h2>Paystack Recurrent Billing Subscribers</h2>

		<div style="background:#ECECEC;border:1px solid #CCC;padding:0 10px;margin-top:5px;border-radius:5px;-moz-border-radius:5px;-webkit-border-radius:5px;">
			<p>Below is a list of all subscribers. You can poke subscribers or stop their subscriptions</p>
		</div>

	<?php if($message){ ?>
		<div id="setting-error-settings_<?php echo esc_attr($message_type); ?>" class="<?php echo esc_attr($message_type); ?> settings-error notice is-dismissible">
			<p><strong><?php echo esc_html($message); ?></strong></p>
			<button type="button" class="notice-dismiss">
			<span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
	<?php }?>
		<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
		<form id="subscribers-filter" method="get">
			<!-- For plugins, we also need to ensure that the form posts back to our current page -->
			<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
			<!-- Now we can render the completed list table -->
			<?php $testListTable->display() ?>
		</form>

	</div>
	<?php
}
