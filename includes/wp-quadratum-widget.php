<?php

require_once (WPQUADRATUM_PATH . '/foursquare-helper/foursquare-helper.php');

class WPQuadratumWidget extends WP_Widget {
	function __construct() {
		$widget_ops = array (
			'description' => __('Displays your last Foursquare checkin')
			);
		parent::WP_Widget ('WPQuadratumWidget', __('WP Quadratum'), $widget_ops);
		if (is_active_widget (false, false, $this->id_base)) {
			add_action ('template_redirect', array ($this, 'widget_external'));
		}
	}
	
	function widget_external() {
		wp_register_script ('nokiamaps', 'http://api.maps.nokia.com/2.1.1/jsl.js');
		wp_enqueue_script ('nokiamaps');
	}
	
	function form($instance) {
		$text_stub = '<label for="%s">%s</label><input type="text" id="%s" name="%s" value="%s" class="widefat" />';
		$check_stub = '<input type="checkbox" id="%s" name="%s" %s /><label for="%s">%s</label>';
		$content = '';

		$instance = wp_parse_args (
			(array)$instance,
			array (
				'title' => __('Last Foursquare Checkin'),
				'width' => 200,
				'height' => 200,
				'zoom' => 16,
				'private' => 0,
				'id' => 1
				)
			);

		$content = '<p>'
			. sprintf ($text_stub,
				$this->get_field_id ('title'),
				__('Widget Title'),
				$this->get_field_id ('title'),
				$this->get_field_name ('title'),
				attribute_escape ($instance['title'])
				)
			. '</p>';

		$content .= '<p>'
			. sprintf ($text_stub,
				$this->get_field_id ('width'),
				__('Widget Width'),
				$this->get_field_id ('width'),
				$this->get_field_name ('width'),
				attribute_escape ($instance['width'])
				)
			. '</p>';

		$content .= '<p>'
			. sprintf ($text_stub,
				$this->get_field_id ('height'),
				__('Map Height'),
				$this->get_field_id ('height'),
				$this->get_field_name ('height'),
				attribute_escape ($instance['height'])
				)
			. '</p>';

		$content .= '<p>'
			. sprintf ($text_stub,
				$this->get_field_id ('zoom'),
				__('Map Zoom Level'),
				$this->get_field_id ('zoom'),
				$this->get_field_name ('zoom'),
				attribute_escape ($instance['zoom'])
				)
			. '</p>';

		$content .= '<p>'
			. sprintf ($check_stub,
				$this->get_field_id ('private'),
				$this->get_field_name ('private'),
				checked ($instance['private'], true, false),
				$this->get_field_id ('private'),
				__('Show Private Checkins')
				)
			. '</p>';

		$content .= '<p>'
			. sprintf ($text_stub,
				$this->get_field_id ('id'),
				__('Widget Id'),
				$this->get_field_id ('id'),
				$this->get_field_name ('id'),
				attribute_escape ($instance['id'])
				)
			. '</p>';
			
		echo $content;
	}
	
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		
		$instance['title'] = strip_tags ($new_instance['title']);
		$instance['width'] = (int)strip_tags ($new_instance['width']);
		$instance['height'] = (int)strip_tags ($new_instance['height']);
		$instance['zoom'] = (int)strip_tags ($new_instance['zoom']);
		$instance['private'] = (int)$new_instance['private'];
		$instance['id'] = (int)strip_tags($new_instance['id']);
		
		return $instance;
	}
	
	function widget($args, $instance) {
		extract ($args, EXTR_SKIP);

		$content = $before_widget;
		if ($instance['title']) {
			$content .= $before_title
				. $instance['title']
				. $after_title;
		}
		$content .= $this->show_checkin_map ($instance);
		$content .= $after_widget;
		
		echo $content;
	}
	
	function show_checkin_map($instance) {
		$content = array ();

		$options = get_option ('wp_quadratum_settings');

		$client_id = $options['client_id'];
		$client_secret = $options['client_secret'];
		$redirect_url = plugins_url ()
			. '/'
			. dirname (plugin_basename (__FILE__))
			. '/wp-quadratum-callback.php';

		$fh = new FoursquareHelper ($client_id, $client_secret, $redirect_url);
		$fh->set_access_token ($options['oauth_token']);
		$params = array (
			'limit' => 1
			);
		$endpoint = "users/self/checkins";
		$response = $fh->get_private ($endpoint, $params);
		$json = json_decode ($response);
		$checkins = $json->response->checkins->items;

		// TODO: Handle response caching

		foreach ($checkins as $checkin) {
			$venue = $checkin->venue;
			$location = $venue->location;
			$categories = $venue->categories;
			$map_id = 'wp-quadratum-map-' . $instance['id'];

			$venue_url = 'https://foursquare.com/v/'
				. $venue->id;
			
			foreach ($categories as $category) {
				$icon_url = $category->icon;
				break;
			}

			if (is_object ($icon_url)) {
				$icon_url = $icon_url->prefix . '32' . $icon_url->name;
			}
			
			$content[] = '<div id="wp-quadratum-container-'
				. $instance['id']
				. '" class="wp-quadratum-container" style="width:'
				. $instance['width']
				. 'px;">';
				
			$content[] = '<div id="'
				. $map_id
				. '" class="wp-quadratum-map" style="width:'
				. $instance['width']
				. 'px; height:'
				. $instance['height']
				. 'px;">';
			$content[] = '</div>';
			
			$app_id = NULL;
			$app_token = NULL;
			
			if (WPQuadratum::is_wpna_installed () && WPQuadratum::is_wpna_active ()) {
				$helper = new WPNokiaAuthHelper ();
				
				$tmp = $helper->get_id ();
				if (!empty ($tmp)) {
					$app_id = $tmp;
				}
				
				$tmp = $helper->get_token ();
				if (!empty ($tmp)) {
					$app_token = $tmp;
				}
			}
			
			else {
				if (!empty ($options['app_id'])) {
					$app_id = $options['app_id'];
				}
				if (!empty ($options['app_token'])) {
					$app_token = $options['app_token'];
				}
			}
			
			if (!empty ($app_id) && !empty ($app_id)) {
				$content[] = '<script type="text/javascript">
				nokia.maps.util.ApplicationContext.set (
					{
						"appId": "' . $app_id . '",
						"authenticationToken": "' . $app_token . '"
					}
				);';
			}

			$content[] = 'var coords = new nokia.maps.geo.Coordinate (' . $location->lat . ',' . $location->lng . ');
			var map = new nokia.maps.map.Display (
				document.getElementById ("' . $map_id . '"),
				{
					\'zoomLevel\': ' . $instance['zoom'] . ',
					\'center\': coords
				}
				);
			var marker = new nokia.maps.map.Marker (
				coords,
				{
					\'icon\': "' . $icon_url . '"
				});
			map.objects.add (marker);
			</script>';

			$content[] = '<div class="wp-quadratum-venue-name"><h5>'
				. 'Last seen at '
				. '<a href="'
				. $venue_url
				. '" target="_blank">'
				. $checkin->venue->name
				. '</a>'
				. ' on '
				. date ("d M Y G:i T", $checkin->createdAt)
				. '</h5></div>';

			$content[] = '</div>';
			break;	// Not really needed as we only return a single checkin item
		}

		return implode ('', $content);
	}
}
?>