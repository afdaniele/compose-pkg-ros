<?php

use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class SensorMsgs_IMU_Orientation extends BlockRenderer {
    
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
        <a class="btn btn-default btn-sm" id="run_imu_calibration" role="button"
           style="position: absolute; right: 16px; top: 45px;">
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
                            label: 'Roll',
                            backgroundColor: color(window.chartColors.red).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.red,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(0)
                        }, {
                            label: 'Pitch',
                            backgroundColor: color(window.chartColors.green).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.green,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(0)
                        }, {
                            label: 'Yaw',
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
                                    labelString: 'deg'
                                },
                                ticks: {
                                    suggestedMin: -180,
                                    suggestedMax: 180,
                                    stepSize: 45
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
                    // convert quaternion to RPY
                    q = [
                        message.orientation.w,
                        message.orientation.x,
                        message.orientation.y,
                        message.orientation.z,
                    ];
                    rpy = eulerFromQuaternion(q, "XYZ");
                    // add new Y
                    config.data.datasets[0].data.push(
                        rpy[0] * (180/Math.PI)
                    );
                    config.data.datasets[1].data.push(
                        rpy[1] * (180/Math.PI)
                    );
                    config.data.datasets[2].data.push(
                        rpy[2] * (180/Math.PI)
                    );
                    // refresh chart
                    chart.update();
                });
            });
            
            function eulerFromQuaternion( quaternion, order ) {
                // Quaternion to matrix.
                const w = quaternion[0], x = quaternion[1], y = quaternion[2], z = quaternion[3];
                const x2 = x + x, y2 = y + y, z2 = z + z;
                const xx = x * x2, xy = x * y2, xz = x * z2;
                const yy = y * y2, yz = y * z2, zz = z * z2;
                const wx = w * x2, wy = w * y2, wz = w * z2;
                const matrix = [
                    1 - ( yy + zz ),
                    xy + wz,
                    xz - wy,
                    0,
                    xy - wz,
                    1 - ( xx + zz ),
                    yz + wx,
                    0,
                    xz + wy,
                    yz - wx,
                    1 - ( xx + yy ),
                    0,
                    0,
                    0,
                    0,
                    1
                ];
                // Matrix to euler
                function clamp( value, min, max ) {
                    return Math.max( min, Math.min( max, value ) );
                }
                const m11 = matrix[ 0 ], m12 = matrix[ 4 ], m13 = matrix[ 8 ];
                const m21 = matrix[ 1 ], m22 = matrix[ 5 ], m23 = matrix[ 9 ];
                const m31 = matrix[ 2 ], m32 = matrix[ 6 ], m33 = matrix[ 10 ];
                var euler = [ 0, 0, 0 ];
                switch ( order ) {
                    case "XYZ":
                        euler[1] = Math.asin( clamp( m13, - 1, 1 ) );
                        if ( Math.abs( m13 ) < 0.9999999 ) {
                            euler[0] = Math.atan2( - m23, m33 );
                            euler[2] = Math.atan2( - m12, m11 );
                        } else {
                            euler[0] = Math.atan2( m32, m22 );
                            euler[2] = 0;
                        }
                        break;
                    case "YXZ":
                        euler[0] = Math.asin( - clamp( m23, - 1, 1 ) );
                        if ( Math.abs( m23 ) < 0.9999999 ) {
                            euler[1] = Math.atan2( m13, m33 );
                            euler[2] = Math.atan2( m21, m22 );
                        } else {
                            euler[1] = Math.atan2( - m31, m11 );
                            euler[2] = 0;
                        }
                        break;
                    case "ZXY":
                        euler[0] = Math.asin( clamp( m32, - 1, 1 ) );
                        if ( Math.abs( m32 ) < 0.9999999 ) {
                            euler[1] = Math.atan2( - m31, m33 );
                            euler[2] = Math.atan2( - m12, m22 );
                        } else {
                            euler[1] = 0;
                            euler[2] = Math.atan2( m21, m11 );
                        }
                        break;
                    case "ZYX":
                        euler[1] = Math.asin( - clamp( m31, - 1, 1 ) );
                        if ( Math.abs( m31 ) < 0.9999999 ) {
                            euler[0] = Math.atan2( m32, m33 );
                            euler[2] = Math.atan2( m21, m11 );
                        } else {
                            euler[0] = 0;
                            euler[2] = Math.atan2( - m12, m22 );
                        }
                        break;
                    case "YZX":
                        euler[2] = Math.asin( clamp( m21, - 1, 1 ) );
                        if ( Math.abs( m21 ) < 0.9999999 ) {
                            euler[0] = Math.atan2( - m23, m22 );
                            euler[1] = Math.atan2( - m31, m11 );
                        } else {
                            euler[0] = 0;
                            euler[1] = Math.atan2( m13, m33 );
                        }
                        break;
                    case "XZY":
                        euler[2] = Math.asin( - clamp( m12, - 1, 1 ) );
                        if ( Math.abs( m12 ) < 0.9999999 ) {
                            euler[0] = Math.atan2( m32, m22 );
                            euler[1] = Math.atan2( m13, m11 );
                        } else {
                            euler[0] = Math.atan2( - m23, m33 );
                            euler[1] = 0;
                        }
                        break;
                }
                return euler;
            }
        
        </script>
        <?php
    }//render
    
}//SensorMsgs_IMU_Orientation
?>
