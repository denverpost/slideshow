<?php 
/** 
 * phpSmug - phpSmug is a PHP wrapper class for the SmugMug API. The intention 
 *		     of this class is to allow PHP application developers to quickly 
 *			 and easily interact with the SmugMug API in their applications, 
 *			 without having to worry about the finer details of the API.
 *
 * @author Colin Seymour <lildood@gmail.com>
 * @version 3.4
 * @package phpSmug
 * @license GPL 3 {@link http://www.gnu.org/copyleft/gpl.html}
 * @copyright Copyright (c) 2008 Colin Seymour
 * 
 * This file is part of phpSmug.
 *
 * phpSmug is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * phpSmug is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with phpSmug.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * For more information about the class and upcoming tools and toys using it,
 * visit {@link http://phpsmug.com/}.
 *
 * For installation and usage instructions, open the README.txt file 
 * packaged with this class. If you don't have a copy, you can refer to the
 * documentation at:
 * 
 *          {@link http://phpsmug.com/docs/}
 * 
 * phpSmug is inspired by phpFlickr 2.1.0 ({@link http://www.phpflickr.com}) by Dan Coulter
 * 
 * Please help support the maintenance and development of phpSmug by making
 * a donation ({@link http://phpsmug.com/donate/}).
 **/


/**
 * phpSmug - all of the phpSmug functionality is provided in this class
 *
 * @package phpSmug
 **/
class phpSmugAPCcache extends phpSmug {
	public function enableCacheAPC()
	{
		$args = phpSmug::processArgs( func_get_args() );
		$this->cacheType = $args['type'];

		$this->cache_expire = ( array_key_exists( 'cache_expire', $args ) ) ? $args['cache_expire'] : '3600';
		$this->cache_table  = ( array_key_exists( 'table', $args ) ) ? $args['table'] : 'phpsmug_cache';

        if ( $this->cacheType == 'db' ) {
    		require_once 'MDB2.php';

			$db =& MDB2::connect( $args['dsn'] );
			if ( PEAR::isError( $db ) ) {
				$this->cacheType = FALSE;
				return "CACHING DISABLED: {$db->getMessage()} {$db->getUserInfo()} ({$db->getCode()})";
			}
			$this->cache_db = $db;

			$options = array( 'comment' => 'phpSmug cache', 'charset' => 'utf8', 'collate' => 'utf8_unicode_ci' );
			$fields = array( 'request' => array( 'type' => 'text', 'length' => '35', 'notnull' => TRUE ),
							 'response' => array( 'type' => 'clob', 'notnull' => TRUE ),
							 'expiration' => array( 'type' => 'integer', 'notnull' => TRUE )
						   );
			$db->loadModule('Manager');
			$db->createTable( $this->cache_table, $fields, $options );
			$db->setOption('idxname_format', '%s'); // Make sure index name doesn't have the prefix
			$db->createIndex( $this->cache_table, 'request', array( 'fields' => array( 'request' => array() ) ) );

            if ( $db->queryOne( "SELECT COUNT(*) FROM $this->cache_table") > $this->max_cache_rows ) {
				$diff = time() - $this->cache_expire;
                $db->exec( "DELETE FROM {$this->cache_table} WHERE expiration < {$diff}" );
                $db->query( 'OPTIMIZE TABLE ' . $this->cache_table );
            }
        } elseif ( $this->cacheType ==  'fs' ) {
			if ( file_exists( $args['cache_dir'] ) && ( is_dir( $args['cache_dir'] ) ) ) {
				$this->cache_dir = realpath( $args['cache_dir'] ).'/phpSmug/';
				if ( is_writeable( realpath( $args['cache_dir'] ) ) ) {
					if ( !is_dir( $this->cache_dir ) ) {
						mkdir( $this->cache_dir, 0755 );
					}
					$dir = opendir( $this->cache_dir );
                	while ( $file = readdir( $dir ) ) {
                    	if ( substr( $file, -6 ) == '.cache' && ( ( filemtime( $this->cache_dir . '/' . $file ) + $this->cache_expire ) < time() ) ) {
                        	unlink( $this->cache_dir . '/' . $file );
                    	}
                	}
				} else {
					$this->cacheType = FALSE;
					return 'CACHING DISABLED: Cache Directory "'.$args['cache_dir'].'" is not writeable.';
				}
        } 
			else 	{
				$this->cacheType = FALSE;
				return 'CACHING DISABLED: Cache Directory "'.$args['cache_dir'].'" doesn\'t exist, is a file or is not readable.';
			}
		}
        elseif ( $this->cacheType ==  'apc' ) 
        {
            // DFM's APC caching layer goes here.
            if ( !function_exists('apc_fetch') )
            {
				$this->cacheType = FALSE;
				return 'CACHING DISABLED: APC caching function apc_fetch doesn\'t exist.';
            }
            
        } 
		return (bool) TRUE;
    }

	/**
	 * 	Checks the database or filesystem for a cached result to the request.
	 *
	 * @access	private
	 * @return	mixed		Unparsed serialized PHP, or FALSE
	 * @param	array		$request Request to the SmugMug created by one of the later functions in phpSmug.
	 **/
    private function getCached( $request )
	{
		$request['SessionID']       = ''; // Unset SessionID
		$request['oauth_nonce']     = '';     // --\
		$request['oauth_signature'] = '';  //    |-Unset OAuth info
		$request['oauth_timestamp'] = ''; // --/
       	$reqhash = md5( serialize( $request ).$this->loginType );
		$expire = ( strpos( $request['method'], 'login.with' ) ) ? 21600 : $this->cache_expire;
		$diff = time() - $expire;

		if ( $this->cacheType == 'db' ) {
			$result = $this->cache_db->queryOne( 'SELECT response FROM ' . $this->cache_table . ' WHERE request = ' . $this->cache_db->quote( $reqhash ) . ' AND ' . $this->cache_db->quote( $diff ) . ' < expiration' );
			if ( PEAR::isError( $result ) ) {
				throw new PhpSmugException( $result );
			}
			if ( !empty( $result ) ) {
                return $result;
            }
        } elseif ( $this->cacheType == 'fs' ) {
            $file = $this->cache_dir . '/' . $reqhash . '.cache';
			if ( file_exists( $file ) && ( ( filemtime( $file ) + $expire ) > time() ) ) {
					return file_get_contents( $file );
            }
        }
        elseif ( $this->cacheType == 'apc' ) 
        {
//            return apc_fetch($reqhash);
        }
    	return FALSE;
    }

	/**
	 * Caches the unparsed serialized PHP of a request. 
	 *
	 * @access	private
	 * @param	array		$request Request to the SmugMug created by one of the
	 *						later functions in phpSmug.
	 * @param	string		$response Response from a successful request() method
	 *						call.
	 * @return	null|TRUE
	 **/
    private function cache( $request, $response )
	{
		$request['SessionID']       = ''; // Unset SessionID
		$request['oauth_nonce']     = ''; // --\
		$request['oauth_signature'] = ''; //    |-Unset OAuth info
		$request['oauth_timestamp'] = ''; // --/
		if ( ! strpos( $request['method'], '.auth.' ) ) {
			$reqhash = md5( serialize( $request ).$this->loginType );
			if ( $this->cacheType == 'db' ) {
				if ( $this->cache_db->queryOne( "SELECT COUNT(*) FROM {$this->cache_table} WHERE request = '$reqhash'" ) ) {
					$sql = 'UPDATE ' . $this->cache_table . ' SET response = '. $this->cache_db->quote( $response ) . ', expiration = ' . $this->cache_db->quote( time() ) . ' WHERE request = ' . $this->cache_db->quote( $reqhash ) ;
					$result = $this->cache_db->exec( $sql );
				} else {
					$sql = 'INSERT INTO ' . $this->cache_table . ' (request, response, expiration) VALUES (' . $this->cache_db->quote( $reqhash ) .', ' . $this->cache_db->quote( strtr( $response, "'", "\'" ) ) . ', ' . $this->cache_db->quote( time() ) . ')';
					$result = $this->cache_db->exec( $sql );
				}
				if ( PEAR::isError( $result ) ) {
					// TODO: Create unit test for this
					throw new PhpSmugException( $result );
				}
				return $result;
			} elseif ( $this->cacheType == 'fs' ) {
				$file = $this->cache_dir . '/' . $reqhash . '.cache';
				$fstream = fopen( $file, 'w' );
				$result = fwrite( $fstream,$response );
				fclose( $fstream );
				return $result;
			}
            elseif ( $this->cacheType == 'apc' ) 
            {
                return apc_add($reqhash, $response, 600);
            }
		}
        return TRUE;
    }

	/**
	 * Forcefully clear the cache.
	 *
	 * This is useful if you've made changes to your SmugMug galleries and want
	 * to ensure the changes are reflected by your application immediately.
	 *
	 * @access	public
	 * @param	boolean		$delete Set to TRUE to delete the cache after
	 *						clearing it
	 * @return	boolean
	 * @since 1.1.7
	 **/
    public function clearCache( $delete = FALSE )
	{
		$result = FALSE;
   		if ( $this->cacheType == 'db' ) {
			if ( $delete ) {
				$result = $this->cache_db->exec( 'DROP TABLE ' . $this->cache_table );
			} else {
				$result = $this->cache_db->exec( 'DELETE FROM ' . $this->cache_table );
			}
			if ( ! PEAR::isError( $result ) ) {
				$result = TRUE;
			}
	   	} elseif ( $this->cacheType == 'fs' ) {
            $dir = opendir( $this->cache_dir );
	       	if ( $dir ) {
				foreach ( glob( $this->cache_dir."/*.cache" ) as $filename ) {
					$result = unlink( $filename );
				}
	       	}
			closedir( $dir );
			if ( $delete ) {
				$result = rmdir( $this->cache_dir );
			}
	   	}
        elseif ( $this->cacheType == 'apc' ) 
        {
        
        }
		return (bool) $result;
	}

}

?>
