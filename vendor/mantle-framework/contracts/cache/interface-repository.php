<?php
/**
 * Repository interface file.
 *
 * @package Mantle
 */

namespace Mantle\Contracts\Cache;

use Closure;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache Repository
 * Implements PSR-16 standard and follows PSR code naming conventions.
 *
 * @link https://www.php-fig.org/psr/psr-16/
 */
interface Repository extends CacheInterface {
	/**
	 * Retrieve a value from cache.
	 *
	 * @template TCacheValue
	 *
	 * @param string                                $key Cache key.
	 * @param TCacheValue|(\Closure(): TCacheValue) $default Default value.
	 * @return (TCacheValue is null ? mixed : TCacheValue)
	 */
	public function get( string $key, mixed $default = null ): mixed;

	/**
	 * Retrieve an item from the cache and delete it.
	 *
	 * @template TCacheValue
	 *
	 * @param  string                                $key
	 * @param TCacheValue|(\Closure(): TCacheValue) $default Default value.
	 * @return (TCacheValue is null ? mixed : TCacheValue)
	 */
	public function pull( $key, $default = null );

	/**
	 * Store an item in the cache.
	 *
	 * @param  string                                    $key
	 * @param  mixed                                     $value
	 * @param  \DateTimeInterface|\DateInterval|int|null $ttl
	 * @return bool
	 */
	public function put( $key, $value, $ttl = null );

	/**
	 * Store an item in the cache if the key does not exist.
	 *
	 * @param  string                                    $key
	 * @param  mixed                                     $value
	 * @param  \DateTimeInterface|\DateInterval|int|null $ttl
	 * @return bool
	 */
	public function add( $key, $value, $ttl = null );

	/**
	 * Increment the value of an item in the cache.
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 * @return int|bool
	 */
	public function increment( $key, $value = 1 );

	/**
	 * Decrement the value of an item in the cache.
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 * @return int|bool
	 */
	public function decrement( $key, $value = 1 );

	/**
	 * Store an item in the cache indefinitely.
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 * @return bool
	 */
	public function forever( $key, $value );

	/**
	 * Get an item from the cache, or execute the given Closure and store the result.
	 *
	 * @template TCacheValue
	 *
	 * @param  string                                    $key
	 * @param  \DateTimeInterface|\DateInterval|int|null $ttl
	 * @param  (\Closure(): TCacheValue)                 $callback
	 * @return mixed
	 */
	public function remember( $key, $ttl, Closure $callback );

	/**
	 * Get an item from the cache, or execute the given Closure and store the result forever.
	 *
	 * @param  string   $key
	 * @param  \Closure $callback
	 * @return mixed
	 */
	public function sear( $key, Closure $callback );

	/**
	 * Get an item from the cache, or execute the given Closure and store the result forever.
	 *
	 * @template TCacheValue
	 *
	 * @param  string                    $key
	 * @param  (\Closure(): TCacheValue) $callback
	 * @return mixed
	 */
	public function rememberForever( $key, Closure $callback );

	/**
	 * Remove an item from the cache.
	 *
	 * @param  string $key
	 * @return bool
	 */
	public function forget( $key );
}
