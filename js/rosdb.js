window.ROSDB = (function () {

  var IN_DB = {};
  var OUT_DB = {};

  // private functions
  function _list_in() {
    var out = [];
    for (var data_key in IN_DB) {
      out.push(data_key);
    }
    return out;
  }//_list_in

  function _list_out() {
    var out = [];
    for (var data_key in OUT_DB) {
      out.push(data_key);
    }
    return out;
  }//_list_out

  function _list(direction) {
    if(direction == undefined){
      return {
        'in' : _list_in(),
        'out' : _list_out()
      };
    }
    // ---
    if(['in', 'out'].indexOf(direction) < 0)
      return [];
    // ---
    if(direction == 'in'){
      return _list_in();
    }
    if(direction == 'out'){
      return _list_out();
    }
    return [];
  }//_list

  function _subscribe(resource_name, topic_name, topic_msg_type, frequency, queue_size, latched){
    if(latched == undefined){
      latched = false;
    }
    if(queue_size == undefined){
      queue_size = 1;
    }
    var throttle_rate = 0;
    if(frequency != undefined && frequency != 0){
      throttle_rate = Math.round(1000/frequency);
    }
    // ---
    if (IN_DB.hasOwnProperty(resource_name)){
      console.log('Request to subscribe to origin for resource "{0}". Incrementing counter.'.format(resource_name));
      IN_DB[resource_name]['subscribers'] += 1;
      return;
    }
    console.log('Request to subscribe to origin for a new resource "{0}". Creating resource.'.format(resource_name));
    // ---
    var topic = new ROSLIB.Topic({
      ros : window.ros['local'],
      name : topic_name,
      messageType : topic_msg_type,
      queue_size : queue_size,
      throttle_rate : throttle_rate,
      latch : latched,
      reconnect_on_close : true
    });
    var cb_fcn = function(message) {
      var event_name = '{0}_NEW_DATA'.format(resource_name);
      IN_DB[resource_name]['data'] = message;
      IN_DB[resource_name]['timestamp2'] = IN_DB[resource_name]['timestamp1'];
      IN_DB[resource_name]['timestamp1'] = Date.now();
      $(window.ROSDB).trigger(event_name);
    }
    IN_DB[resource_name] = {
      'subscribers' : 1,
      'topic' : topic,
      'timestamp1' : null,
      'timestamp2' : null,
      'data' : null,
      'callback' : cb_fcn
    };
    // ---
    topic.subscribe(cb_fcn);
  }//_subscribe

  function _unsubscribe(resource_name){
    // ---
    if (!IN_DB.hasOwnProperty(resource_name)){
      return;
    }
    if(IN_DB[resource_name]['subscribers'] <= 0){
      return;
    }
    if(IN_DB[resource_name]['subscribers'] > 1){
      console.log('Request to unsubscribe from origin of resource "{0}". Decreasing counter.'.format(resource_name));
      IN_DB[resource_name]['subscribers'] -= 1;
      return;
    }
    // ---
    console.log('Request to unsubscribe from origin of resource "{0}". Closing stream.'.format(resource_name));
    IN_DB[resource_name]['topic'].unsubscribe(
      IN_DB[resource_name]['callback']
    );
    delete IN_DB[resource_name];
  }//_unsubscribe

  function _frequency(resource_name){
    if (!IN_DB.hasOwnProperty(resource_name) && !OUT_DB.hasOwnProperty(resource_name)){
      return 0;
    }
    var DB = IN_DB;
    if (OUT_DB.hasOwnProperty(resource_name)){
      DB = OUT_DB;
    }
    // ---
    if(DB[resource_name]['timestamp1'] == null || DB[resource_name]['timestamp2'] == null){
      return 0;
    }
    msg_hz = 1000 / (DB[resource_name]['timestamp1'] - DB[resource_name]['timestamp2']);
    last_msg_hz = 1000 / (Date.now() - DB[resource_name]['timestamp1']);
    return Math.min(msg_hz, last_msg_hz);
  }//_frequency

  function _get(resource_name){
    if (IN_DB.hasOwnProperty(resource_name)){
      return IN_DB[resource_name]['data'];
    }
    // ---
    if (OUT_DB.hasOwnProperty(resource_name)){
      return OUT_DB[resource_name]['data'];
    }
    // ---
    return null;
  }//_get

  function _publish(resource_name, data){
    if (!OUT_DB.hasOwnProperty(resource_name)){
      console.log('Publishing data for a non-existing resource "{0}"'.format(resource_name));
      return;
    }
    OUT_DB[resource_name]['data'] = data;
    OUT_DB[resource_name]['flushed'] = false;
    _resume(resource_name);
  }//_publish

  function _advertise(resource_name, topic_name, topic_msg_type, frequency, queue_size, latched){
    if (OUT_DB.hasOwnProperty(resource_name)){
      return;
    }
    console.log('Request to advertise a new resource "{0}"'.format(resource_name));
    // ---
    var topic = new ROSLIB.Topic({
      ros : window.ros['local'],
      name : topic_name,
      messageType : topic_msg_type,
      queue_size : queue_size,
      latch : latched,
      reconnect_on_close : true
    });
    var pub_fcn = function() {
      data = OUT_DB[resource_name]['data'];
      if(data == null)
        return;
      // ---
      var message = new ROSLIB.Message(data);
      topic.publish(message);
      // ---
      OUT_DB[resource_name]['timestamp2'] = OUT_DB[resource_name]['timestamp1'];
      OUT_DB[resource_name]['timestamp1'] = Date.now();
      OUT_DB[resource_name]['flushed'] = true;
      // ---
      if(OUT_DB[resource_name]['requested_pause']){
        _pause(resource_name);
      }
    }
    OUT_DB[resource_name] = {
      'subscribers' : 0,
      'topic' : topic,
      'frequency' : frequency,
      'timestamp1' : null,
      'timestamp2' : null,
      'data' : null,
      'handler' : pub_fcn,
      'handler_id' : null,
      'flushed' : true,
      'requested_pause' : false
    };
    // ---
    _resume(resource_name);
  }//_advertise

  function _resume(resource_name){
    if (!OUT_DB.hasOwnProperty(resource_name)){
      return;
    }
    if( OUT_DB[resource_name]['handler_id'] != null ){
      return;
    }
    // ---
    var frequency = OUT_DB[resource_name]['frequency'];
    timer = 1000;
    if(frequency != undefined && frequency != 0){
      timer = Math.round(1000/frequency);
    }
    var pub_fcn = OUT_DB[resource_name]['handler'];
    var handler_id = setInterval(pub_fcn, timer);
    OUT_DB[resource_name]['handler_id'] = handler_id;
  }//_resume

  function _pause(resource_name){
    if (!OUT_DB.hasOwnProperty(resource_name)){
      return;
    }
    // ---
    if(!OUT_DB[resource_name]['flushed']){
      OUT_DB[resource_name]['requested_pause'] = true;
      return;
    }
    OUT_DB[resource_name]['requested_pause'] = false;
    // ---
    clearInterval(OUT_DB[resource_name]['handler_id']);
    OUT_DB[resource_name]['handler_id'] = null;
  }//_pause

  function _unadvertise(resource_name){
    if (!OUT_DB.hasOwnProperty(resource_name)){
      return;
    }
    console.log('Request to unadvertise the resource "{0}". Closing stream.'.format(resource_name));
    // ---
    _pause(resource_name);
    delete OUT_DB[resource_name];
  }//_unadvertise

  // public functions
  return {
    list: _list,
    subscribe: _subscribe,
    unsubscribe: _unsubscribe,
    get: _get,
    hz: _frequency,
    advertise: _advertise,
    publish: _publish,
    pause: _pause,
    resume: _resume,
    unadvertise: _unadvertise
  };
})();
