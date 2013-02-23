<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_shipping_qt_p_location_p_item_p_itemtype extends Cartthrob_shipping
{
	public $title = 'Quantity Threshold + Free + Per Item + Per ItemType Override';
	public $classname = __CLASS__;
	public $note = 'Custom shipping plugin for cost per item, per item type, per region (ignores per item shipping settings)';
  
	public $settings = array(
			array(
				'name' => 'set_shipping_cost_by',
				'short_name' => 'mode',
				'default' => 'price',
				'type' => 'radio',
				'options' => array(
					'price' => 'rate_amount'
				)
			),
			array(
				'name' => 'primary_location_field',
				'short_name' => 'location_field',
				'type' => 'select',
				'default'	=> 'country_code',
				'options' => array(
					'zip' => 'zip',
					'state'	=> 'state', 
					'region' => 'Region',
					'country_code' => 'settings_country_code',
					'shipping_zip' => 'shipping_zip',
					'shipping_state' => 'shipping_state',
					'shipping_region' => 'shipping_region', 
					'shipping_country_code' => 'settings_shipping_country_code'
				)
			),
			array(
				'name' => 'backup_location_field',
				'short_name' => 'backup_location_field',
				'type' => 'select',
				'default'	=> 'country_code',
				'options' => array(
					'zip' => 'zip',
					'state'	=> 'state', 
					'region' => 'Region',
					'country_code' => 'settings_country_code',
					'shipping_zip' => 'shipping_zip',
					'shipping_state' => 'shipping_state',
					'shipping_region' => 'shipping_region', 
					'shipping_country_code' => 'settings_shipping_country_code'
				)
			),
			array(
				'name' => 'Default Shipping Price',
				'short_name' => 'default_price',
				'note' => 'Default Price used if no match for region / item type',
				'type' => 'text'	
			),
			array(
				'name' => 'Default Shipping Price after the first',
				'short_name' => 'default_overone_rate_multiplier',
				'note' => 'Default Price used if no match for region / item type, multiple after one',
				'type' => 'text'	
			),
			array(
				'name' => 'thresholds',
				'short_name' => 'thresholds',
				'type' => 'matrix',
				'settings' => array(
					array(
						'name'			=>	'location_threshold',
						'short_name'	=>	'location',
						'type'			=>	'text',	
					),
					array(
						'name' => 'rate',
						'short_name' => 'rate',
						'note' => 'rate_example',
						'type' => 'text'
					),
					array(
						'name' => 'Item Type',
						'short_name' => 'itemtype_threshold',
						'note' => 'Item Type (44: Calendar)',
						'type' => 'text'	
					),
					array(
						'name' => 'Rate if over one in cart',
						'short_name' => 'overone_rate_multiplier',
						'note' => 'Calendar 4.50, +3.50 if more than 1',
						'type' => 'text'	
					)
				)
			)
		);

	public function get_shipping()
	{
		$customer_info = $this->core->cart->customer_info(); 

		$location_field = $this->plugin_settings('location_field', 'shipping_country_code');
		$backup_location_field = $this->plugin_settings('backup_location_field', 'country_code');
		$location = '';
		$default_price = $this->plugin_settings('default_price');
		$default_overone_rate_multiplier = $this->plugin_settings('default_overone_rate_multiplier');
    
    $shipping = 0;
		$price = $this->core->cart->shippable_subtotal();
    $prev_item = '';
		$priced = FALSE;
		$last_rate = ''; 
		$is_item_match_itemtype = FALSE;
		$items_per_type = array();
		$itemtypes = array();
		
		// get user's Location
		if ( !empty($customer_info[$location_field]))
		{
			$location = $customer_info[$location_field];
		}
		else if ( !empty($customer_info[$backup_location_field]))
		{
			$location = $customer_info[$backup_location_field];
		}
		
		// get all the itemtype id's in the threshold setting
		// set all the item types to be checked and set the values to 0
		foreach ($this->plugin_settings('thresholds', array()) as $threshold_setting)
	  {
	    $itemtype_threshold	= $threshold_setting['itemtype_threshold'];
	    $itemtypes[$itemtype_threshold] = $itemtype_threshold;
	    $items_per_type[$itemtype_threshold] = 0;
 		}
    
    // loop through each item in the cart, checking against the item type
    // if a match is found, push into the itemtypes lookup array itemtype and qty
    // if item doesn't match any listed itemtype, update shipping price to default price + multiplier based on item quantity
    foreach ($this->core->cart->shippable_items() as $row_id => $item)
		{
		  $is_item_match_itemtype = false;
			$meta = $item->meta('product_category');
			
			// check each item type to see
			foreach ($itemtypes as $item_type) {
			  if(strpos($meta, $item_type)) {
			    $items_per_type[$item_type] += $item->quantity();
			    $is_item_match_itemtype = true;
			  } 
			}
			
			if(!$is_item_match_itemtype) {
        $shipping += ($default_price + ($default_overone_rate_multiplier * ($item->quantity()-1)));
			}
 		}
    
 		// loop through the user-generated thresholds (region, price, multiplier, itemtype)
 		foreach ($this->plugin_settings('thresholds', array()) as $threshold_setting)
		{
			$itemtype_threshold_check	= $threshold_setting['itemtype_threshold'];
      $item_qty = $items_per_type[$itemtype_threshold_check];
      
      if($prev_item != $itemtype_threshold_check) {
        $is_location_match = false;
      }
      
      // check if an item qty of item type exists in the cart and matches region threshold
      // if found region to user location, update shipping based on qty for item, and stop looking for a region match for that item type
      if($item_qty > 0 && !$is_location_match)
			{
			  $location_array	= preg_split('/\s*,\s*/', trim($threshold_setting['location']));
  		  
  			if (in_array($location, $location_array))
  			{
  			  $shipping += $threshold_setting['rate'] + ($threshold_setting['overone_rate_multiplier'] * ($item_qty-1));
          $last_rate = $shipping;
          $priced = true;
				  $is_location_match = true;
  			}
  			elseif (in_array('GLOBAL',$location_array)) 
  			{
  			  $shipping += $threshold_setting['rate'] + ($threshold_setting['overone_rate_multiplier'] * ($item_qty-1));
          $last_rate = $shipping;
          $priced = true;
  			}
  		}
  		
  		$prev_item = $itemtype_threshold_check;
		}

		if (!$priced)
		{
			$shipping = $last_rate;
		}
		
		return $shipping;
	}
}//END CLASS

/* End of file Cartthrob_shipping_qt_p_location_p_item_p_itemtype.php */
/* Location: ./assets/third_party/cartthrob/cartthrob/plugins/shipping/Cartthrob_shipping_qt_p_location_p_item_p_itemtype.php */