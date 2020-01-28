<?php
# @Author: Andrea F. Daniele <afdaniele>
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
  private static $roslibjs_initialized = false;
	private static $initialized_ws_clients = [];

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

	public static function connect($ws_hostname = null, $ws_port = null){
    // initialize roslibjs (if not done yet)
    $res = self::_initialize_roslibjs();
    if (!$res['success']) {
      return $res;
    }
    // initialize weboskcet client (if not done yet)
    return self::_initialize_ws_client($ws_hostname, $ws_port);
  }//connect


  public static function get_event($event_type, $ros_hostname = null) {
    if (is_null($ros_hostname)) {
      $ros_hostname = self::_get_default_ros_hostname();
    }
    return sprintf(
      '%s_%s',
      $event_type,
      $ros_hostname
    );
  }//get_event


  public static function sanitize_hostname($ws_hostname) {
    return self::_get_final_ws_hostname($ws_hostname);
  }//sanitize_hostname


	// =======================================================================================================
	// Private functions

	private static function _initialize_roslibjs() {
    if (!self::$roslibjs_initialized) {
      self::$roslibjs_initialized = true;
      echo sprintf(
        "<script type='text/javascript' src='%s'></script>",
        Core::getJSscriptURL('roslibjs.min.js', 'ros')
      );
			//
			return ['success' => true, 'data' => null];
		}else{
			return ['success' => true, 'data' => "ROSlibjs already initialized!"];
		}
  }//_initialize_roslibjs

  private static function _initialize_ws_client($ws_hostname, $ws_port) {
    if (array_key_exists($ws_hostname, self::$initialized_ws_clients)) {
      return ['success' => true, 'data' => "Websocket client for '{$ws_hostname}' already initialized!"];
    }
    // mark the ws client as 'initialized'
    self::$initialized_ws_clients[$ws_hostname] = null;
    // compile $ws_hostname into a WS url
    $ws_url = self::_get_ros_ws_url($ws_hostname, $ws_port);
    $ws_alias = $ws_hostname;
    if (is_null($ws_hostname)) {
      $ws_hostname = self::_get_default_ros_hostname();
      $ws_alias = 'local';
    }
    // dump some js
    echo sprintf("
      <script type='text/javascript'>
      $(document).ready(function() {
        if (!window.hasOwnProperty('ros')) {
          window.ros = {};
        }
        // Connect to ROS
        window.ros['%s'] = new ROSLIB.Ros({
          url : '%s'
        });
        window.ros['%s'].on('connection', function() {
          $(document).trigger('%s');
        });
        window.ros['%s'].on('error', function(error) {
          $(document).trigger('%s', [error]);
        });
        window.ros['%s'].on('close', function() {
          $(document).trigger('%s');
        });
        window.ros['%s'] = window.ros['%s'];
      });
      </script>
      ",
      $ws_hostname, $ws_url,
      $ws_hostname, self::get_event(self::$ROSBRIDGE_CONNECTED, $ws_hostname),
      $ws_hostname, self::get_event(self::$ROSBRIDGE_ERROR, $ws_hostname),
      $ws_hostname, self::get_event(self::$ROSBRIDGE_CLOSED, $ws_hostname),
      $ws_alias, $ws_hostname
    );
  }//_initialize_ws_client

  private static function _get_final_ws_hostname($ws_hostname) {
    if (is_null($ws_hostname) || strlen($ws_hostname) < 2) {
      // get WebSocket hostname from config or HTTP_HOST
      $ws_hostname = self::_get_default_ros_hostname();
    }
    return $ws_hostname;
  }//_get_final_ws_hostname

  private static function _get_default_ros_hostname() {
    // get WebSocket hostname from config (defaults to HTTP_HOST if not set)
    $ws_hostname = Core::getSetting('rosbridge_hostname', 'ros');
    if(strlen($ws_hostname) < 2){
      $ws_hostname = strstr($_SERVER['HTTP_HOST'].':'.$_SERVER['SERVER_PORT'], ':', true);
      // remove port (if any) from the http host string
      $ws_hostname_parts = explode(':', $ws_hostname);
      $ws_hostname = $ws_hostname_parts[0];
    }
    return $ws_hostname;
  }//_get_default_ros_hostname


  private static function _get_ros_ws_url($ws_hostname = null, $ws_port = null) {
    $ws_hostname = self::_get_final_ws_hostname($ws_hostname);
    if (is_null($ws_port)) {
      $ws_port = Core::getSetting('rosbridge_port', 'ros');
    }
    // compile the Websocket URL
    return sprintf(
      "ws://%s:%d",
      $ws_hostname,
      $ws_port
    );
  }//_get_ros_ws_url

}//Duckiebot
?>
