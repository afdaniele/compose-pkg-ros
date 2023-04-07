<?php

use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class SensorMsgs_IMU_AngularVelocity extends BlockRenderer {
    
    static protected $ICON = [
        "class" => "fa",
        "name" => "compass"
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
        "service" => [
            "name" => "ROS Service (IMU Calibration)",
            "type" => "text",
            "mandatory" => True
        ],
        "fps" => [
            "name" => "Update frequency (Hz)",
            "type" => "numeric",
            "mandatory" => True,
            "default" => 5
        ]
    ];
    
    protected static function render($id, &$args) {
        ?>
        <a class="btn btn-default btn-xs" id="run_imu_calibration" role="button"
           style="position: absolute; right: 20px; top: 48px; padding: 0 12px">
            <i class="fa fa-compass" aria-hidden="true"></i>
            Calibrate IMU
        </a>

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
                    messageType: 'sensor_msgs/Imu',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['fps'] ?>
                });
                
                // Subscribe to the given topic
                let calibrate_imu = new ROSLIB.Service({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name : '<?php echo $args['service'] ?>',
                    messageType : 'std_srvs/Trigger'
                });
                
                $("#<?php echo $id ?> #run_imu_calibration").on("click", () => {
                    // send request
                    let request = new ROSLIB.ServiceRequest({});
                    calibrate_imu.callService(request, function(_) {});
                });

                let time_horizon_secs = 20;
                let color = Chart.helpers.color;
                let chart_config = {
                    type: 'line',
                    data: {
                        labels: range(time_horizon_secs - 1, 0, 1),
                        datasets: [{
                            label: 'X',
                            backgroundColor: color(window.chartColors.red).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.red,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(0)
                        }, {
                            label: 'Y',
                            backgroundColor: color(window.chartColors.green).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.green,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(0)
                        }, {
                            label: 'Z',
                            backgroundColor: color(window.chartColors.blue).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.blue,
                            fill: true,
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
                                    labelString: 'rad/s'
                                },
                                ticks: {
                                    suggestedMin: -4,
                                    suggestedMax: 4,
                                    stepSize: 1.0
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

                subscriber.subscribe(function (message) {
                    // get chart
                    let chart_desc = window.mission_control_page_blocks_data['<?php echo $id ?>'];
                    let chart = chart_desc.chart;
                    let config = chart_desc.config;
                    // cut the time horizon to `time_horizon_secs` points
                    config.data.datasets[0].data.shift();
                    config.data.datasets[1].data.shift();
                    config.data.datasets[2].data.shift();
                    // add new data
                    config.data.datasets[0].data.push(message.angular_velocity.x);
                    config.data.datasets[1].data.push(message.angular_velocity.y);
                    config.data.datasets[2].data.push(message.angular_velocity.z);
                    // refresh chart
                    chart.update();
                });
            });
        </script>
        <?php
    }//render
    
}//SensorMsgs_IMU_AngularVelocity
?>
