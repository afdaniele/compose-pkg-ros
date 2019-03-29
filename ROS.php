<?php
# @Author: Andrea F. Daniele <afdaniele>
# @Date:   Wednesday, July 18th 2018
# @Email:  afdaniele@ttic.edu
# @Last modified by:   afdaniele


namespace system\packages\ros;

use \system\classes\Core;
use \system\classes\Utils;
use \system\classes\Database;
use \system\classes\Configuration;

/**
*   Module for managing connection to ROSbridge websocket
*/
class ROS{

	private static $initialized = false;

  public static $ROSBRIDGE_CONNECTED = "ROSBRIDGE_CONNECTED";
  public static $ROSBRIDGE_ERROR = "ROSBRIDGE_ERROR";
  public static $ROSBRIDGE_CLOSED = "ROSBRIDGE_CLOSED";

	// disable the constructor
	private function __construct() {}

	/** Initializes the module.
     *
     *	@retval array
	 *		a status array of the form
	 *	<pre><code class="php">[
	 *		"success" => boolean, 	// whether the function succeded
	 *		"data" => mixed 		// error message or NULL
	 *	]</code></pre>
	 *		where, the `success` field indicates whether the function succeded.
	 *		The `data` field contains errors when `success` is `FALSE`.
     */
	public static function init(){
		if( !self::$initialized ){
			// do stuff
			//
			self::$initialized = true;
			return ['success' => true, 'data' => null];
		}else{
			return ['success' => true, 'data' => "Module already initialized!"];
		}
	}//init

	/** Returns whether the module is initialized.
     *
     *	@retval boolean
	 *		whether the module is initialized.
     */
	public static function isInitialized(){
		return self::$initialized;
	}//isInitialized

    /** Safely terminates the module.
     *
     *	@retval array
	 *		a status array of the form
	 *	<pre><code class="php">[
	 *		"success" => boolean, 	// whether the function succeded
	 *		"data" => mixed 		// error message or NULL
	 *	]</code></pre>
	 *		where, the `success` field indicates whether the function succeded.
	 *		The `data` field contains errors when `success` is `FALSE`.
     */
	public static function close(){
		// do stuff
		return [ 'success' => true, 'data' => null ];
	}//close



	// =======================================================================================================
	// Public functions

	public static function connect($custom_url = null){
    // use url from configuration if a custom url is not passed
    $ws_url = $custom_url;
    if(is_null($custom_url)){
      // get WebSocket hostname (defaults to HTTP_HOST if not set)
      $ws_hostname = Core::getSetting('rosbridge_hostname', 'ros');
      if(strlen($ws_hostname) < 2){
        $ws_hostname = $_SERVER['HTTP_HOST'];
      }
      // compile the Websocket URL
      $ws_url = sprintf(
        "ws://%s:%d",
        $ws_hostname,
        Core::getSetting('rosbridge_port', 'ros')
      );
    }
    // ---
    echo sprintf("
      <script type='text/javascript' src='%s'></script>

      <script type='text/javascript'>
      $(document).ready(function() {
        // Connect to ROS
        window.ros = new ROSLIB.Ros({
          url : '%s'
        });
        ros.on('connection', function() {
          $(document).trigger('%s');
        });
        ros.on('error', function(error) {
          $(document).trigger('%s', [error]);
        });
        ros.on('close', function() {
          $(document).trigger('%s');
        });
      });
      </script>
      ",
      Core::getJSscriptURL('roslibjs.min.js', 'ros'),
      $ws_url,
      self::$ROSBRIDGE_CONNECTED,
      self::$ROSBRIDGE_ERROR,
      self::$ROSBRIDGE_CLOSED
    );
  }//connect



	// =======================================================================================================
	// Private functions

	// YOUR PRIVATE METHODS HERE

}//Duckiebot
?>
