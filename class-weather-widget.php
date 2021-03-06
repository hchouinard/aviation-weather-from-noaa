<?php
/**
 *
 */
class Machouinard_Adds_Weather_Widget extends WP_Widget {

	function machouinard_adds_weather_widget() {
		$options = array(
			'classname'   => 'machouinard_adds_widget_class',
			'description' => __( 'Displays METAR & other info from NOAA\'s Aviation Digital Data Service', 'machouinard_adds' )
		);
		$this->WP_Widget( 'machouinard_adds_weather_widget', 'ADDS Weather Info', $options );
	}

	function form( $instance ) {
		$defaults = array(
			'icao'        => 'KZZV',
			'hours'       => 2,
			'show_taf'    => true,
			'show_pireps' => true,
			'radial_dist' => '30',
			'title'       => null,
		);
		$instance = wp_parse_args( $instance, $defaults );

		$icao        = $instance['icao'];
		$hours       = absint( $instance['hours'] );
		$show_taf    = (bool) $instance['show_taf'];
		$show_pireps = (bool) $instance['show_pireps'];
		$radial_dist = absint( $instance['radial_dist'] );
		$title       = sanitize_text_field( $instance['title'] );
		?>
		<label
			for="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"><?php _e( 'Title', 'machouinard_adds' ); ?></label>
		<input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
		       value="<?php echo esc_html( $title ); ?>"/>

		<label
			for="<?php echo esc_attr( $this->get_field_name( 'icao' ) ); ?>"><?php _e( 'ICAO', 'machouinard_adds' ); ?></label>
		<input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'icao' ) ); ?>" type="text"
		       value="<?php echo esc_attr( $icao ); ?>" placeholder="Please Enter a Valid ICAO"/>
		<label for="<?php echo esc_attr( $this->get_field_name( 'hours' ) ); ?>">Hours before now</label>
		<select name="<?php echo esc_attr( $this->get_field_name( 'hours' ) ); ?>"
		        id="<?php echo esc_attr( $this->get_field_id( 'hours' ) ); ?>" class="widefat">

			<?php
			for ( $i = 1; $i < 7; $i ++ ) {
				echo '<option value="' . absint( $i ) . '" id="' . absint( $i ) . '"', $hours == $i ? ' selected="selected"' : '', '>', $i, '</option>';
			}
			?>
		</select>

		<label
			for="<?php echo esc_attr( $this->get_field_id( 'show_pireps' ) ); ?>"><?php _e( 'Display PIREPS?', 'machouinard_adds' ); ?></label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'show_pireps' ) ); ?>"
		       name="<?php echo esc_attr( $this->get_field_name( 'show_pireps' ) ); ?>" type="checkbox"
		       value="1" <?php checked( true, $show_pireps ); ?> class="checkbox"/>
		<label
			for="<?php echo esc_attr( $this->get_field_id( 'show_taf' ) ); ?>"><?php _e( 'Display TAF?', 'machouinard_adds' ); ?></label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'show_taf' ) ); ?>"
		       name="<?php echo esc_attr( $this->get_field_name( 'show_taf' ) ); ?>" type="checkbox"
		       value="1" <?php checked( true, $show_taf ); ?> class="checkbox"/><br/>
		<label
			for="<?php echo esc_attr( $this->get_field_name( 'radial_dist' ) ); ?>"><?php _e( 'Radial Distance', 'machouinard_adds' ); ?></label>
		<select name="<?php echo esc_attr( $this->get_field_name( 'radial_dist' ) ); ?>"
		        id="<?php echo esc_attr( $this->get_field_id( 'radial_dist' ) ); ?>" class="widefat">
			<?php
			for ( $i = 10; $i < 210; $i += 10 ) {
				echo '<option value="' . absint( $i ) . '" id="' . absint( $i ) . '"', $radial_dist == $i ? ' selected="selected"' : '', '>', $i, '</option>';
			}
			?>
		</select>
	<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance                = $old_instance;
		$instance['icao']        = $this->clean_icao( $new_instance['icao'] );
		$instance['hours']       = absint( $new_instance['hours'] );
		$instance['show_taf']    = (bool) $new_instance['show_taf'];
		$instance['show_pireps'] = (bool) $new_instance['show_pireps'];
		$instance['radial_dist'] = absint( $new_instance['radial_dist'] );
		$instance['title']       = sanitize_text_field( $new_instance['title'] );
		if ( ! $this->get_apt_info( $instance['icao'] ) ) {
			$instance['icao'] = '';
		}
		// Delete old transient data
		delete_transient( 'noaa_wx_' . $old_instance['icao'] );
		delete_transient( 'noaa_pireps_' . $old_instance['icao'] );

		return $instance;
	}

	static function clean_icao( $icao ) {
		preg_match( '/^[A-Za-z]{3,4}$/', $icao, $matches );

		if ( strlen( $matches[0] ) == 3 ) {
			foreach ( array( 'K', 'Y', 'C' ) as $a ) {
				if ( $icao = self::get_apt_info( $a . $matches[0] ) ) {
					return strtoupper( $a . $matches[0] );
				}
			}
		}

		return strtoupper( $matches[0] );
	}

	function widget( $args, $instance ) {
		$icao        = empty( $instance['icao'] ) ? '' : self::clean_icao( $instance['icao'] );
		$hours       = empty( $instance['hours'] ) ? '' : absint( $instance['hours'] );
		$radial_dist = empty( $instance['radial_dist'] ) ? '' : absint( $instance['radial_dist'] );
		$show_taf    = isset( $instance['show_taf'] ) ? (bool) $instance['show_taf'] : false;
		$show_pireps = isset( $instance['show_pireps'] ) ? (bool) $instance['show_pireps'] : false;
		$title       = empty( $instance['title'] ) ? sprintf( _n( 'Available data for %s from the past hour', 'Available data for %s from the past %d hours', $hours, 'machouinard_adds' ), $icao, $hours ) : $instance['title'];
		$hours       = apply_filters( 'hours_before_now', $hours );
		$radial_dist = apply_filters( 'radial_dist', $radial_dist );
		$title       = apply_filters( 'machouinard_title', $title );

		$wx = $this->get_metar( $icao, $hours );

		$pireps[] = $this->get_pireps( $icao, $radial_dist, $hours );

		extract( $args );
		echo $before_widget;

		if ( ! empty( $wx['metar'] ) ) {
			echo '<p><strong>';
			echo esc_html( $title );
			echo '</strong></p>';
			foreach ( $wx as $type => $info ) {

				if ( $type == 'taf' && $show_taf || $type == 'metar' ) {
					echo '<strong>' . esc_html( strtoupper( $type ) ) . '</strong><br />';
				}

				if ( $type == 'taf' && ! $show_taf ) {
					continue;
				}
				if ( is_array( $info ) ) {
					foreach ( $info as $value ) {
						if ( ! empty( $value ) ) {
							echo esc_html( $value ) . "<br />\n";
						}
					}
				} else {
					echo esc_html( $info ) . "<br />\n";
				}
			}
		}
		if ( ! empty( $pireps[0] ) && $show_pireps ) {
			echo '<strong>PIREPS ' . absint( $radial_dist ) . 'sm</strong><br />';
			foreach ( $pireps[0] as $pirep ) {
				echo esc_html( $pirep ) . '<br />';
			}
		}
		echo $after_widget;
	}

	/**
	 * Attempt to get METAR for selected ICAO in timeframe
	 *
	 * @param  string $icao string of airport identifieers
	 * @param  int $hours number of hours history to include
	 *
	 * @return array  $wx     metar and taf arrays containing weather data
	 */
	static function get_metar( $icao, $hours ) {

		if ( false == $wx = get_transient( 'noaa_wx_' . $icao ) ) {
			$metar_url = sprintf( 'http://www.aviationweather.gov/adds/dataserver_current/httpparam?dataSource=metars&requestType=retrieve&format=xml&stationString=%s&hoursBeforeNow=%d', $icao, absint( $hours ) );
			$tafs_url  = sprintf( 'http://www.aviationweather.gov/adds/dataserver_current/httpparam?dataSource=tafs&requestType=retrieve&format=xml&stationString=%s&hoursBeforeNow=%d', $icao, absint( $hours ) );

			$xml['metar'] = self::load_xml( esc_url_raw( $metar_url ) );

			$xml['taf'] = self::load_xml( esc_url_raw( $tafs_url ) );

			// Store the METAR for display
			$count = count( $xml['metar']->data->METAR );
			if ( $count > 0 ) {
				for ( $i = 0; $i < $count; $i ++ ) {
					$wx['metar'][ $i ] = (string) $xml['metar']->data->METAR[ $i ]->raw_text;
				}
			}

			// Only store the most recent forecast
			if ( isset ( $xml['taf']->data->TAF ) ) {
				$wx['taf'][0] = (string) $xml['taf']->data->TAF[0]->raw_text;
			}
			// save wx data for 15 minutes

			set_transient( 'noaa_wx_' . $icao, $wx, 60 * 15 );

		}

		return $wx;
	}

	/**
	 * Attempt to retrieve PIREPS for selected ICAO distance and hours
	 *
	 * @param  string $icao Airport Identifier
	 * @param  int $radial_dist include pireps this distance from airport
	 * @param  int $hours hours before now
	 *
	 * @return array    $pireps           pirep data
	 */
	static function get_pireps( $icao, $radial_dist, $hours ) {
		if ( ! get_transient( 'noaa_pireps_' . $icao ) ) {
			$info      = self::get_apt_info( $icao );
			$pirep_url = sprintf( 'http://aviationweather.gov/adds/dataserver_current/httpparam?dataSource=aircraftreports&requestType=retrieve&format=xml&radialDistance=%d;%f,%f&hoursBeforeNow=%d', $radial_dist, $info['lon'], $info['lat'], $hours );
			$xml       = self::load_xml( $pirep_url );
			$pireps    = array();
			for ( $i = 0; $i < count( $xml->data->AircraftReport ); $i ++ ) {
				$pireps[] = (string) $xml->data->AircraftReport[ $i ]->raw_text;
			}
			// save pirep data for 15 minutes
			set_transient( 'noaa_pireps_' . $icao, $pireps, 60 * 15 );
		}

		$pireps = get_transient( 'noaa_pireps_' . $icao );

		return $pireps;
	}

	/**
	 * Attempt to validate ICAO
	 *
	 * @param  string $icao Airport Identifier
	 *
	 * @return array  $info | false     array containing lat & lon for provided airport or false if ICAO is not alpha-num or 4 chars
	 */
	public static function get_apt_info( $icao ) {
		if ( ! preg_match( '~^[A-Za-z0-9]{4,4}$~', $icao, $matches ) ) {
			return false;
		}
		$url = sprintf( 'http://aviationweather.gov/adds/dataserver_current/httpparam?dataSource=stations&requestType=retrieve&format=xml&stationString=%s', $icao );
		$xml = self::load_xml( esc_url_raw( $url ) );
		if ( isset( $xml->data->Station ) ) {
			$info['station_id'] = $xml->data->Station->station_id;
			$info['lat']        = $xml->data->Station->latitude;
			$info['lon']        = $xml->data->Station->longitude;
			$info['city']       = $xml->data->Station->site;
		} else {
			$info = false;
		}

		return $info;
	}

	// Retrieve XML from URL
	private static function load_xml( $url ) {
		$xml_raw = wp_remote_get( $url );
		$body    = wp_remote_retrieve_body( $xml_raw );

		return simplexml_load_string( $body );
	}

}