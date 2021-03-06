<?php
use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class SensorMsgs_CompressedImage extends BlockRenderer{

  static protected $ICON = [
    "class" => "glyphicon",
    "name" => "camera"
  ];

  static protected $ARGUMENTS = [
    "ros_hostname" => [
      "name" => "ROSbridge hostname",
      "type" => "text",
      "mandatory" => False,
      "default" => ""
    ],
    "topic" => [
      "name" => "ROS Topic",
      "type" => "text",
      "mandatory" => True
    ],
    "fps" => [
      "name" => "Update frequency (Hz)",
      "type" => "numeric",
      "mandatory" => True,
      "default" => 5
    ],
    "position" => [
      "name" => "Image position",
      "type" => "enum",
      "mandatory" => True,
      "default" => "center center",
      "enum_values" => [
        "left top",
        "left center",
        "left bottom",
        "right top",
        "right center",
        "right bottom",
        "center top",
        "center center",
        "center bottom"
      ]
    ],
    "style" => [
      "name" => "Image size",
      "type" => "enum",
      "mandatory" => True,
      "default" => "cover",
      "enum_values" => [
        "contain",
        "cover"
      ]
    ],
    "background_color" => [
      "name" => "Background color",
      "type" => "color",
      "mandatory" => True,
      "default" => "#fff"
    ]
  ];

  protected static function render($id, &$args){
    ?>
    <div id="image_placeholder"></div>
    <?php
    $ros_hostname = $args['ros_hostname'] ?? null;
    $ros_hostname = ROS::sanitize_hostname($ros_hostname);
    $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
    ?>

    <script type="text/javascript">
        $(document).on("<?php echo $connected_evt ?>", function (evt) {
            // Subscribe to the CompressedImage topic
            subscriber = new ROSLIB.Topic({
                ros: window.ros['<?php echo $ros_hostname ?>'],
                name: '<?php echo $args['topic'] ?>',
                messageType: 'sensor_msgs/CompressedImage',
                queue_size: 1,
                throttle_rate: <?php echo intval(1000 / $args['fps']) ?>
            });

            subscriber.subscribe(function (message) {
                canvas = $('#<?php echo $id ?>');
                base64_string = 'data:image/jpg;base64,' + message['data'];
                canvas.css('background-image', 'url(' + base64_string + ')');
                // hide placeholder
                $('#<?php echo $id ?> #image_placeholder').css('display', 'none');
                // refresh style
                $('#<?php echo $id ?>').css('background-position', '<?php echo $args['position'] ?>');
                $('#<?php echo $id ?>').css('background-size', '<?php echo $args['style'] ?>');
            });
        });
    </script>

    <?php
    ROS::connect($ros_hostname);
    ?>

    <style type="text/css">
      #<?php echo $id ?>{
        background-color: <?php echo $args['background_color'] ?>;
        background-position: <?php echo $args['position'] ?>;
        background-size: <?php echo $args['style'] ?>;
        background-repeat: no-repeat;
      }

      #<?php echo $id ?> .block_renderer_header{
        background-color:rgba(0, 0, 0, 0.1);
        color: white;
      }

      #<?php echo $id ?> #image_placeholder{
        width:100%;
        height:100%;
        max-height: 100px;
        background-image: url('<?php echo Core::getImageURL('placeholder.png') ?>');
        background-position: center center;
        background-size: auto 100%;
        background-repeat: no-repeat;
      }
    </style>
    <?php
  }//render

}//SensorMsgs_CompressedImage
?>
