<?php
/**
 * Beginning of a CRUD system
 *
 * Adapted from...
 * https://woocommerce.wordpress.com/2016/10/27/the-new-crud-classes-in-woocommerce-2-7/
 *
 * @since 1.3
 * @package Mesh
 * @subpackage CRUD
 */

namespace includes\abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Mesh Data Class
 *
 * Implemented by classes using the same CRUD(s) pattern. This abstract should be pretty simplistic
 * compared to something like WooCommerce. There aren't as many complexities in functionality.
 *
 * Store Child sections and blocks within a blog of post meta.
 *
 *
 * @version  1.2.0
 * @package  Mesh/Abstracts
 * @category Abstract Class
 * @author   Linchpin
 */
abstract class Mesh_Data {

	/**
	 * ID for this object.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Core data for this object. Name value pairs (name + default value).
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * @var string
	 */
	protected $data_type = 'data';

	/**
	 * Extra data for this object. Name value pairs (name + default value).
	 * Used as a standard way for sub classes (like block types) to add
	 * additional information to an inherited class.
	 *
	 * @var array
	 */
	protected $extra_data = array();

	/**
	 * Set to _data on construct so we can track and reset data if needed.
	 *
	 * @var array
	 */
	protected $default_data = array();

	/**
	 * Stores additonal meta data.
	 *
	 * @var array
	 */
	protected $meta_data = null;

	/**
	 * Default constructor.
	 *
	 * @param int|object|array $read ID to load from the DB (optional) or already queried data.
	 */
	public function __construct( $read = 0 ) {
		$this->default_data = $this->data;
	}

	/**
	 * Returns the unique ID for this object.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Delete an object, set the ID to 0, and return result.
	 *
	 * @param  bool $force_delete Force the deletion of data.
	 * @return bool result        Sucess|Fail.
	 */
	public function delete( $force_delete = false ) {
	}

	/**
	 * Save should create or update based on object existance.
	 *
	 * @return int
	 */
	public function save() {

		// Trigger action before saving to the DB. Use a pointer to adjust object props before save.
		do_action( 'mesh_before_' . $this->data_type . '_object_save', $this );

		if ( $this->get_id() ) {
			$this->update( $this );
		} else {
			$this->create( $this );
		}

		return $this->get_id();
	}

	/**
	 * Change data to JSON format.
	 *
	 * @return string Data in JSON format.
	 */
	public function __toString() {
		return wp_json_encode( $this->get_data() );
	}

	/**
	 * Returns all data for this object.
	 *
	 * @return array
	 */
	public function get_data() {
		return array_merge(
			array(
				'id' => $this->get_id(),
			),
			$this->data, array(
				'meta_data' => $this->get_meta_data(),
			)
		);
	}

	/**
	 * Returns array of expected data keys for this object.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	public function get_data_keys() {
		return array_keys( $this->data );
	}

	/**
	 * Returns all "extra" data keys for an object.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	public function get_extra_data_keys() {
		return array_keys( $this->extra_data );
	}

	/**
	 * Filter null meta values from array.
	 * @return bool
	 */
	protected function filter_null_meta( $meta ) {
		return ! is_null( $meta->value );
	}

	/**
	 * Get All Meta Data.
	 * @since 2.6.0
	 * @return array
	 */
	public function get_meta_data() {
		$this->maybe_read_meta_data();
		return array_filter( $this->meta_data, array( $this, 'filter_null_meta' ) );
	}

	/**
	 * Get Meta Data by Key.
	 *
	 * @since  1.2.0
	 * @param  string $key
	 * @param  bool $single return first found meta with key, or all with $key
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return mixed
	 */
	public function get_meta( $key = '', $single = true, $context = 'view' ) {
		$this->maybe_read_meta_data();
		$array_keys = array_keys( wp_list_pluck( $this->get_meta_data(), 'key' ), $key );
		$value    = '';

		if ( ! empty( $array_keys ) ) {
			if ( $single ) {
				$value = $this->meta_data[ current( $array_keys ) ]->value;
			} else {
				$value = array_intersect_key( $this->meta_data, array_flip( $array_keys ) );
			}

			if ( 'view' === $context ) {
				$value = apply_filters( $this->get_hook_prefix() . $key, $value, $this );
			}
		}

		return $value;
	}

	/**
	 * Set all meta data from array.
	 *
	 * @since 1.2.0
	 * @param array $data Key/Value pairs
	 */
	public function set_meta_data( $data ) {
		if ( ! empty( $data ) && is_array( $data ) ) {
			$this->maybe_read_meta_data();
			foreach ( $data as $meta ) {
				$meta = (array) $meta;
				if ( isset( $meta['key'], $meta['value'], $meta['id'] ) ) {
					$this->meta_data[] = (object) array(
						'id'    => $meta['id'],
						'key'   => $meta['key'],
						'value' => $meta['value'],
					);
				}
			}
		}
	}

	/**
	 * Add meta data.
	 * @since 1.2.0
	 * @param string $key Meta key
	 * @param string $value Meta value
	 * @param bool $unique Should this be a unique key?
	 */
	public function add_meta_data( $key, $value, $unique = false ) {
		$this->maybe_read_meta_data();
		if ( $unique ) {
			$this->delete_meta_data( $key );
		}
		$this->meta_data[] = (object) array(
			'key'   => $key,
			'value' => $value,
		);
	}

	/**
	 * Update meta data by key or ID, if provided.
	 *
	 * @since 1.3.0
	 * @param  string     $key
	 * @param  string     $value
	 * @param  int|string $meta_id
	 */
	public function update_meta_data( $key, $value, $meta_id = '' ) {
		$this->maybe_read_meta_data();
		if ( $array_key = $meta_id ? array_keys( wp_list_pluck( $this->meta_data, 'id' ), $meta_id ) : '' ) {
			$this->meta_data[ current( $array_key ) ] = (object) array(
				'id'    => $meta_id,
				'key'   => $key,
				'value' => $value,
			);
		} else {
			$this->add_meta_data( $key, $value, true );
		}
	}

	/**
	 * Delete meta data.
	 *
	 * @since 1.3.0
	 * @param array $key Meta key
	 */
	public function delete_meta_data( $key ) {
		$this->maybe_read_meta_data();
		if ( $array_keys = array_keys( wp_list_pluck( $this->meta_data, 'key' ), $key ) ) {
			foreach ( $array_keys as $array_key ) {
				$this->meta_data[ $array_key ]->value = null;
			}
		}
	}

	/**
	 * Delete meta data.
	 *
	 * @since 1.2.0
	 * @param int $mid Meta ID
	 */
	public function delete_meta_data_by_mid( $mid ) {
		$this->maybe_read_meta_data();
		if ( $array_keys = array_keys( wp_list_pluck( $this->meta_data, 'id' ), $mid ) ) {
			foreach ( $array_keys as $array_key ) {
				$this->meta_data[ $array_key ]->value = null;
			}
		}
	}

	/**
	 * Read meta data if null.
	 *
	 * @since 1.2.0
	 */
	protected function maybe_read_meta_data() {
		if ( is_null( $this->meta_data ) ) {
			$this->read_meta_data();
		}
	}

	/**
	 * Read Meta Data from the database. Ignore any internal properties.
	 * Uses it's own caches because get_metadata does not provide meta_ids.
	 *
	 * @since 1.2.0
	 * @param bool $force_read True to force a new DB read (and update cache).
	 */
	public function read_meta_data( $force_read = false ) {
		$this->meta_data  = array();
		$cache_loaded     = false;

		if ( ! $this->get_id() ) {
			return;
		}

		$raw_meta_data   = $this->data_store->read_meta( $this );

		if ( $raw_meta_data ) {
			foreach ( $raw_meta_data as $meta ) {
				$this->meta_data[] = (object) array(
					'id'    => (int) $meta->meta_id,
					'key'   => $meta->meta_key,
					'value' => maybe_unserialize( $meta->meta_value ),
				);
			}

			if ( ! empty( $this->cache_group ) && ! empty( $cache_key ) ) {
				wp_cache_set( $cache_key, $this->meta_data, $this->cache_group );
			}
		}
	}

	/**
	 * Update Meta Data in the database.
	 *
	 * @since 1.2.0
	 */
	public function save_meta_data() {
		if ( ! $this->data_store || is_null( $this->meta_data ) ) {
			return;
		}
		foreach ( $this->meta_data as $array_key => $meta ) {
			if ( is_null( $meta->value ) ) {
				if ( ! empty( $meta->id ) ) {
					$this->data_store->delete_meta( $this, $meta );
				}
			} elseif ( empty( $meta->id ) ) {
				$new_meta_id                       = $this->data_store->add_meta( $this, $meta );
				$this->meta_data[ $array_key ]->id = $new_meta_id;
			} else {
				$this->data_store->update_meta( $this, $meta );
			}
		}

		$this->read_meta_data( true );
	}

	/**
	 * Set ID.
	 *
	 * @param int $id
	 */
	public function set_id( $id ) {
		$this->id = absint( $id );
	}

	/**
	 * Set all props to default values.
	 */
	public function set_defaults() {
		$this->data        = $this->default_data;
		$this->changes     = array();
		$this->set_object_read( false );
	}

	/**
	 * Set object read property.
	 *
	 * @param boolean $read
	 */
	public function set_object_read( $read = true ) {
		$this->object_read = (bool) $read;
	}

	/**
	 * Get object read property.
	 *
	 * @return boolean
	 */
	public function get_object_read() {
		return (bool) $this->object_read;
	}

	/**
	 * Set a collection of props in one go, collect any errors, and return the result.
	 * Only sets using public methods.
	 *
	 * @param array $props Key value pairs to set. Key is the prop and should map to a setter function name.
	 * @return WP_Error|bool
	 */
	public function set_props( $props, $context = 'set' ) {
		$errors = new WP_Error();

		foreach ( $props as $prop => $value ) {
			try {
				if ( 'meta_data' === $prop ) {
					continue;
				}
				$setter = "set_$prop";
				if ( ! is_null( $value ) && is_callable( array( $this, $setter ) ) ) {
					$reflection = new ReflectionMethod( $this, $setter );

					if ( $reflection->isPublic() ) {
						$this->{$setter}( $value );
					}
				}
			} catch ( Mesh_Data_Exception $e ) {
				$errors->add( $e->getErrorCode(), $e->getMessage() );
			}
		}

		return sizeof( $errors->get_error_codes() ) ? $errors : true;
	}

	/**
	 * Sets a prop for a setter method.
	 *
	 * This stores changes in a special array so we can track what needs saving
	 * the the DB later.
	 *
	 * @since 1.2.0
	 * @param string $prop Name of prop to set.
	 * @param mixed  $value Value of the prop.
	 */
	protected function set_prop( $prop, $value ) {
		if ( array_key_exists( $prop, $this->data ) ) {
			if ( true === $this->object_read ) {
				if ( $value !== $this->data[ $prop ] || array_key_exists( $prop, $this->changes ) ) {
					$this->changes[ $prop ] = $value;
				}
			} else {
				$this->data[ $prop ] = $value;
			}
		}
	}

	/**
	 * Return data changes only.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	public function get_changes() {
		return $this->changes;
	}

	/**
	 * Merge changes with data and clear.
	 *
	 * @since 1.2.0
	 */
	public function apply_changes() {
		$this->data = array_merge( $this->data, $this->changes );
		$this->changes = array();
	}

	/**
	 * Prefix for action and filter hooks on data.
	 *
	 * @since  1.2.0
	 * @return string
	 */
	protected function get_hook_prefix() {
		return 'mesh_get_' . $this->object_type . '_';
	}

	/**
	 * Gets a prop for a getter method.
	 *
	 * Gets the value from either current pending changes, or the data itself.
	 * Context controls what happens to the value before it's returned.
	 *
	 * @since  1.2.0
	 * @param  string $prop Name of prop to get.
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return mixed
	 */
	protected function get_prop( $prop, $context = 'view' ) {
		$value = null;

		if ( array_key_exists( $prop, $this->data ) ) {
			$value = isset( $this->changes[ $prop ] ) ? $this->changes[ $prop ] : $this->data[ $prop ];

			if ( 'view' === $context ) {
				$value = apply_filters( $this->get_hook_prefix() . $prop, $value, $this );
			}
		}
		return $value;
	}

	/**
	 * When invalid data is found, throw an exception unless reading from the DB.
	 *
	 * @param string $error_code Error code.
	 * @param string $error_message Error message.
	 * @throws Mesh_Data_Exception
	 */
	protected function error( $error_code, $error_message ) {
		throw new Mesh_Data_Exception( $error_code, $error_message );
	}
}