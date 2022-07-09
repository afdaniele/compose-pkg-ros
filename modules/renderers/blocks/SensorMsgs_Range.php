<?php

use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class SensorMsgs_Range extends BlockRenderer {
    
    static protected $ICON = [
        "class" => "fa",
        "name" => "crosshairs"
    ];
    
    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name" => "ROSbridge hostname",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "topic" => [
            "name" => "ROS Topic (Range)",
            "type" => "text",
            "mandatory" => True
        ],
        "reference" => [
            "name" => "ROS Topic (Reference)",
            "type" => "text",
            "mandatory" => True
        ],
        "label" => [
            "name" => "Label",
            "type" => "text",
            "mandatory" => True,
            "default" => "Distance"
        ],
        "fps" => [
            "name" => "Update frequency (Hz)",
            "type" => "numeric",
            "mandatory" => True,
            "default" => 5
        ],
        "rendering" => [
            "name" => "Rendering mode",
            "type" => "enum",
            "mandatory" => True,
            "default" => "number",
            "enum_values" => [
                "number",
                "plot"
            ]
        ],
    ];
    
    protected static function render($id, &$args) {
        if ($args["rendering"] == "number") {
            self::render_as_float($id, $args);
        } else if ($args["rendering"] == "plot") {
            self::render_as_plot($id, $args);
        }
    }
    
    protected static function render_as_float($id, &$args) {
        ?>
        <p class="range-float-container text-center text-bold" style="font-size: 28pt">
        </p>
        <?php
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        ?>
        
        <script type="text/javascript">
            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                // Subscribe to the given topics
                (new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['topic'] ?>',
                    messageType: 'sensor_msgs/Range',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['fps'] ?>
                })).subscribe(function (message) {
                    let out_of_range = message.range > message.max_range;
                    let text = (out_of_range)? "Out-of-Range" : "{0} m".format(message.range.toFixed(2));
                    $("#<?php echo $id ?> .range-float-container").text(text);
                });
            });
                
        </script>
        <?php
    }
    
    protected static function render_as_plot($id, &$args) {
        ?>
        <canvas class="resizable" style="width:100%; height:95%; padding:6px 16px"></canvas>
        <?php
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        ?>

        <script type="text/javascript">
            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                // Subscribe to the given topic
                let subscriber = new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['topic'] ?>',
                    messageType: 'sensor_msgs/Range',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['fps'] ?>
                });

                let time_horizon_secs = 20;
                let color = Chart.helpers.color;
                let chart_config = {
                    type: 'line',
                    data: {
                        labels: range(time_horizon_secs - 1, 0, 1),
                        datasets: [{
                            label: '<?php echo $args['label'] ?>',
                            backgroundColor: color(window.chartColors.red).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.red,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(0)
                        },
                        {
                            label: 'Reference',
                            backgroundColor: color(window.chartColors.blue).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.blue,
                            fill: false,
                            data: new Array(time_horizon_secs).fill(0)
                        }]
                    },
                    options: {
                        scales: {
                            xAxes: [{
                                scaleLabel: {
                                    display: false
                                }
                            }],
                            yAxes: [{
                                scaleLabel: {
                                    display: true,
                                    labelString: 'meters'
                                },
                                ticks: {
                                    suggestedMin: 0,
                                    suggestedMax: 2.0,
                                    stepSize: 0.2
                                }
                            }]
                        },
                        tooltips: {
                            enabled: false
                        },
                        maintainAspectRatio: false
                    }
                };
                // create chart obj
                let ctx = $("#<?php echo $id ?> .block_renderer_container canvas")[0].getContext('2d');
                let chart = new Chart(ctx, chart_config);
                window.mission_control_page_blocks_data['<?php echo $id ?>'] = {
                    chart: chart,
                    config: chart_config
                };
                
                let reference = 0.0;

                subscriber.subscribe(function (message) {
                    // get chart
                    let chart_desc = window.mission_control_page_blocks_data['<?php echo $id ?>'];
                    let chart = chart_desc.chart;
                    let config = chart_desc.config;
                    // cut the time horizon to `time_horizon_secs` points
                    config.data.datasets[0].data.shift();
                    config.data.datasets[1].data.shift();
                    // add new Y
                    config.data.datasets[0].data.push(
                        message.range
                    );
                    config.data.datasets[1].data.push(
                        reference
                    );
                    // update range
                    if (message.max_range != config.options.scales.yAxes[0].ticks.suggestedMax)
                        config.options.scales.yAxes[0].ticks.suggestedMax = message.max_range.toFixed(2);
                    // refresh chart
                    chart.update();
                });
                
                (new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['reference'] ?>',
                    messageType: 'std_msgs/Float32',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['fps'] ?>
                })).subscribe(function (message) {
                    reference = message.data;
                });
            });
        </script>
        <?php
    }//render_as_plot
    
}//SensorMsgs_Range
?>
