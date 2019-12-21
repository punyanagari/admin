<?php
/* Pi-hole: A black hole for Internet advertisements
*  (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*  Network-wide ad blocking via your own hardware.
*
*  This file is copyright under the latest version of the EUPL.
*  Please see LICENSE file for your rights under this license. */

    require "scripts/pi-hole/php/auth.php";
    require "scripts/pi-hole/php/password.php";

    check_cors();

    // Generate CSRF token
    if(empty($_SESSION['token'])) {
        $_SESSION['token'] = base64_encode(openssl_random_pseudo_bytes(32));
    }
    $token = $_SESSION['token'];

    // Try to get temperature value from different places (OS dependent)
    if(file_exists("/sys/class/thermal/thermal_zone0/temp"))
    {
        $output = rtrim(file_get_contents("/sys/class/thermal/thermal_zone0/temp"));
    }
    elseif (file_exists("/sys/class/hwmon/hwmon0/temp1_input"))
    {
        $output = rtrim(file_get_contents("/sys/class/hwmon/hwmon0/temp1_input"));
    }
    else
    {
        $output = "";
    }

    // Test if we succeeded in getting the temperature
    if(is_numeric($output))
    {
        // $output could be either 4-5 digits or 2-3, and we only divide by 1000 if it's 4-5
        // ex. 39007 vs 39
        $celsius = intVal($output);

        // If celsius is greater than 1 degree and is in the 4-5 digit format
        if($celsius > 1000) {
            // Use multiplication to get around the division-by-zero error
            $celsius *= 1e-3;
        }

        $kelvin = $celsius + 273.15;
        $fahrenheit = ($celsius*9./5)+32.0;

        if(isset($setupVars['TEMPERATUREUNIT']))
        {
            $temperatureunit = $setupVars['TEMPERATUREUNIT'];
        }
        else
        {
            $temperatureunit = "C";
        }
        // Override temperature unit setting if it is changed via Settings page
        if(isset($_POST["tempunit"]))
        {
            $temperatureunit = $_POST["tempunit"];
        }
        // Get user-defined temperature limit if set
        if(isset($setupVars['TEMPERATURE_LIMIT']))
        {
            $temperaturelimit = intval($setupVars['TEMPERATURE_LIMIT']);
        }
        else
        {
            $temperaturelimit = 60;
        }
    }
    else
    {
        // Nothing can be colder than -273.15 degree Celsius (= 0 Kelvin)
        // This is the minimum temperature possible (AKA absolute zero)
        $celsius = -273.16;
    }

    // Get load
    $loaddata = sys_getloadavg();
    foreach ($loaddata as $key => $value) {
        $loaddata[$key] = round($value, 2);
    }
    // Get number of processing units available to PHP
    // (may be less than the number of online processors)
    $nproc = shell_exec('nproc');
    if(!is_numeric($nproc))
    {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        preg_match_all('/^processor/m', $cpuinfo, $matches);
        $nproc = count($matches[0]);
    }

    // Get memory usage
    $data = explode("\n", file_get_contents("/proc/meminfo"));
    $meminfo = array();
    if(count($data) > 0)
    {
        foreach ($data as $line) {
            $expl = explode(":", trim($line));
            if(count($expl) == 2)
            {
                // remove " kB" from the end of the string and make it an integer
                $meminfo[$expl[0]] = intVal(substr($expl[1],0, -3));
            }
        }
        $memory_used = $meminfo["MemTotal"]-$meminfo["MemFree"]-$meminfo["Buffers"]-$meminfo["Cached"];
        $memory_total = $meminfo["MemTotal"];
        $memory_usage = $memory_used/$memory_total;
    }
    else
    {
        $memory_usage = -1;
    }

    if($auth) {
        // For session timer
        $maxlifetime = ini_get("session.gc_maxlifetime");

        // Generate CSRF token
        if(empty($_SESSION['token'])) {
            $_SESSION['token'] = base64_encode(openssl_random_pseudo_bytes(32));
        }
        $token = $_SESSION['token'];
    }

    if(isset($setupVars['WEBUIBOXEDLAYOUT']))
    {
        if($setupVars['WEBUIBOXEDLAYOUT'] === "boxed")
        {
            $boxedlayout = true;
        }
        else
        {
            $boxedlayout = false;
        }
    }
    else
    {
        $boxedlayout = true;
    }

    // Override layout setting if layout is changed via Settings page
    if(isset($_POST["field"]))
    {
        if($_POST["field"] === "webUI" && isset($_POST["boxedlayout"]))
        {
            $boxedlayout = true;
        }
        elseif($_POST["field"] === "webUI" && !isset($_POST["boxedlayout"]))
        {
            $boxedlayout = false;
        }
    }

    function pidofFTL()
    {
        return shell_exec("pidof pihole-FTL");
    }
    $FTLpid = intval(pidofFTL());
    $FTL = ($FTLpid !== 0 ? true : false);

    $piholeFTLConfFile = "/etc/pihole/pihole-FTL.conf";
    if(is_readable($piholeFTLConfFile))
    {
        $piholeFTLConf = parse_ini_file($piholeFTLConfFile);
    }
    else
    {
        $piholeFTLConf = array();
    }

?>
<!DOCTYPE html>
<!-- Pi-hole: A black hole for Internet advertisements
*  (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*  Network-wide ad blocking via your own hardware.
*
*  This file is copyright under the latest version of the EUPL.
*  Please see LICENSE file for your rights under this license. -->
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https://api.github.com; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'">
    <title>Arahasya</title>
    <!-- Usually browsers proactively perform domain name resolution on links that the user may choose to follow. We disable DNS prefetching here -->
    <meta http-equiv="x-dns-prefetch-control" content="off">
    <meta http-equiv="cache-control" content="max-age=60,private">
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link rel="shortcut icon" href="img/logo.svg" type="image/x-icon" />
    <meta name="theme-color" content="#367fa9">
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicon.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="img/logo.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="img/logo.svg">
    <meta name="msapplication-TileColor" content="#367fa9">
    <meta name="msapplication-TileImage" content="img/logo.svg">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link href="style/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="style/vendor/font-awesome-5.6.3/css/all.min.css" rel="stylesheet" type="text/css" />
    <link href="style/vendor/ionicons-2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="style/vendor/dataTables.bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="style/vendor/daterangepicker.css" rel="stylesheet" type="text/css" />

    <link href="style/vendor/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="style/vendor/skin-blue.min.css" rel="stylesheet" type="text/css" />
    <link href="style/pi-hole.css" rel="stylesheet" type="text/css" />
    <link href="style/toggle.css" rel="stylesheet"/>
    <link rel="icon" type="image/png" sizes="160x160" href="img/logo.svg" />

    <!--[if lt IE 9]>
    <script src="scripts/vendor/html5shiv.min.js"></script>
    <script src="scripts/vendor/respond.min.js"></script>
    <![endif]-->
</head>
<body class="skin-blue sidebar-mini <?php if($boxedlayout){ ?>layout-boxed<?php } ?>">
<!-- JS Warning -->
<div>
    <link rel="stylesheet" type="text/css" href="style/vendor/js-warn.css">
    <input type="checkbox" id="js-hide" />
    <div class="js-warn" id="js-warn-exit"><h1>Javascript Is Disabled</h1><p>Javascript seems to be disabled. This will break some site features.</p>
        <p>To enable Javascript click <a href="http://www.enable-javascript.com/" target="_blank">here</a></p><label for="js-hide">Close</label></div>
</div>
<!-- /JS Warning -->
<?php
if($auth) {
    echo "<div id='token' hidden>$token</div>";
}
?>
<script src="scripts/pi-hole/js/header.js"></script>

<script src="scripts/vendor/jquery.min.js"></script>
<script src="scripts/vendor/jquery-ui.min.js"></script>
<script src="style/vendor/bootstrap/js/bootstrap.min.js"></script>
<script src="scripts/vendor/app.min.js"></script>

<script src="scripts/vendor/jquery.dataTables.min.js"></script>
<script src="scripts/vendor/dataTables.bootstrap.min.js"></script>
<script src="scripts/vendor/Chart.bundle.min.js"></script>

<!-- Send token to JS -->
<div id="token" hidden><?php if($auth) echo $token; ?></div>
<div id="enableTimer" hidden><?php if(file_exists("../custom_disable_timer")){ echo file_get_contents("../custom_disable_timer"); } ?></div>
<div class="wrapper">
    <header class="main-header">
        <!-- Logo -->
        <a href="https://arahasya.com" class="logo" target="_blank">
            <!-- mini logo for sidebar mini 50x50 pixels -->
            <span class="logo-mini">J<b>atayu</b></span>
            <!-- logo for regular state and mobile devices -->
            <span class="logo-lg">J<b>atayu</b></span>
        </a>
        <!-- Header Navbar: style can be found in header.less -->
        <nav class="navbar navbar-static-top" role="navigation">
            <!-- Sidebar toggle button-->
            <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
                <span class="sr-only">Toggle navigation</span>
            </a>
            <div class="navbar-custom-menu">
                <ul class="nav navbar-nav">
                    <li><a style="pointer-events:none;"><samp><?php echo gethostname(); ?></samp></a></li>
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                            <img src="img/logo.svg" class="user-image" style="border-radius: initial" sizes="160x160" alt="Pi-hole logo" />
                            <span class="hidden-xs">Arahasya</span>
                        </a>
                        <ul class="dropdown-menu" style="right:0">
                            <!-- User image -->
                            <li class="user-header">
                                <img src="img/logo.svg" sizes="160x160" alt="User Image" style="border-color:transparent" />
                                <p>
                                    Arahasya VPN Router
                                    <small>Designed For Internet Privacy</small>
                                </p>
                            </li>
                            <!-- Menu Body -->
                            <li class="user-body">
                                <div class="col-xs-4 text-center">
                                    <a class="btn-link" href="https://www.arahasya.com" target="_blank">Website</a>
                                </div>
                                <div class="col-xs-12 text-center" id="sessiontimer">
                                    <b>Session is valid for <span id="sessiontimercounter"><?php if($auth && strlen($pwhash) > 0){echo $maxlifetime;}else{echo "0";} ?></span></b>
                                </div>
                            </li>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </header>
    <!-- Left side column. contains the logo and sidebar -->
    <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <section class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel">
                <div class="pull-left image">
                    <img src="img/logo.svg" class="img-responsive" alt="Pi-hole logo" style="display: table; table-layout: fixed; height: 67px;" />
                </div>
                <div class="pull-left info">
                    <p>Status</p>
                        <?php
                        $pistatus = exec('sudo pihole status web');
                        if ($pistatus == "1") {
                            echo '<a id="status"><i class="fa fa-circle" style="color:#7FFF00"></i> Active</a>';
                        } elseif ($pistatus == "0") {
                            echo '<a id="status"><i class="fa fa-circle" style="color:#FF0000"></i> Offline</a>';
                        } elseif ($pistatus == "-1") {
                            echo '<a id="status"><i class="fa fa-circle" style="color:#FF0000"></i> DNS service not running</a>';
                        } else {
                            echo '<a id="status"><i class="fa fa-circle" style="color:#ff9900"></i> Unknown</a>';
                        }

                        // CPU Temp
                        if($FTL)
                        {
                            if ($celsius >= -273.15) {
                                echo "<a id=\"temperature\"><i class=\"fa fa-fire\" style=\"color:";
                                if ($celsius > $temperaturelimit) {
                                    echo "#FF0000";
                                }
                                else
                                {
                                    echo "#3366FF";
                                }
                                echo "\"></i> Temp:&nbsp;";
                                if($temperatureunit === "F")
                                {
                                    echo round($fahrenheit,1) . "&nbsp;&deg;F";
                                }
                                elseif($temperatureunit === "K")
                                {
                                    echo round($kelvin,1) . "&nbsp;K";
                                }
                                else
                                {
                                    echo round($celsius,1) . "&nbsp;&deg;C";
                                }
                                echo "</a>";
                            }
                        }
                        else
                        {
                            echo '<a id=\"temperature\"><i class="fa fa-circle" style="color:#FF0000"></i> FTL offline</a>';
                        }
                    ?>
                    <br/>
                    <?php
                    echo "<a title=\"Detected $nproc cores\"><i class=\"fa fa-circle\" style=\"color:";
                        if ($loaddata[0] > $nproc) {
                            echo "#FF0000";
                        }
                        else
                        {
                            echo "#7FFF00";
                        }
                        echo "\"></i> Load:&nbsp;&nbsp;" . $loaddata[0] . "&nbsp;&nbsp;" . $loaddata[1] . "&nbsp;&nbsp;". $loaddata[2] . "</a>";
                    ?>
                    <br/>
                    <?php
                    echo "<a><i class=\"fa fa-circle\" style=\"color:";
                        if ($memory_usage > 0.75 || $memory_usage < 0.0) {
                            echo "#FF0000";
                        }
                        else
                        {
                            echo "#7FFF00";
                        }
                        if($memory_usage > 0.0)
                        {
                            echo "\"></i> Memory usage:&nbsp;&nbsp;" . sprintf("%.1f",100.0*$memory_usage) . "&thinsp;%</a>";
                        }
                        else
                        {
                            echo "\"></i> Memory usage:&nbsp;&nbsp; N/A</a>";
                        }
                    ?>
                </div>
            </div>
            <!-- sidebar menu: : style can be found in sidebar.less -->
            <?php
            $scriptname = basename($_SERVER['SCRIPT_FILENAME']);
            if($scriptname === "list.php")
            {
                if($_GET["l"] === "white")
                {
                    $scriptname = "whitelist";
                }
                elseif($_GET["l"] === "black")
                {
                    $scriptname = "blacklist";
                }
            }
            if(!$auth && (!isset($indexpage) || isset($_GET['login'])))
            {
                $scriptname = "login";
            }
            ?>
            <ul class="sidebar-menu">
                <li class="header">MAIN NAVIGATION</li>
                <!-- Home Page -->
                <li<?php if($scriptname === "index.php"){ ?> class="active"<?php } ?>>
                    <a href="index.php">
                        <i class="fa fa-home"></i> <span>Dashboard</span>
                    </a>
                </li>
                <?php if($auth){ ?>
                <!-- Pihole -->
                <li<?php if($scriptname === "/admin/pihole/index.php"){ ?> class="active"<?php } ?>>
                    <a href="/admin/pihole/index.php">
                        <i class="fas fa-lock"></i> <span> Pihole</span>
                    </a>
                </li>
                <!-- Support -->
                <li>
                    <a href="https://www.arahasya.com" target="_blank">
			 <i class="fa fa-headset"></i> <span>Support</span>
                    </a>
                </li>
                <?php if($auth){ ?>
                <!-- Help -->
                <li<?php if($scriptname === "help.php"){ ?> class="active"<?php } ?>>
                    <a href="help.php">
                        <i class="fa fa-question-circle"></i> <span>Help</span>
                    </a>
                </li>
                <?php } ?>
		 <!-- Logout -->
                <?php
                // Show Logout button if $auth is set and authorization is required
                if(strlen($pwhash) > 0) { ?>
                <li>
                    <a href="?logout">
                        <i class="fa fa-user-times"></i> <span>Logout</span>
                    </a>
                </li>
                <?php } ?>
                <?php } ?>
                <!-- Login -->
                <?php
                // Show Login button if $auth is *not* set and authorization is required
                if(strlen($pwhash) > 0 && !$auth) { ?>
                <li<?php if($scriptname === "login"){ ?> class="active"<?php } ?>>
                    <a href="index.php?login">
                        <i class="fa far fa-user"></i> <span>Login</span>
                    </a>
                </li>
                <?php } ?>

            </ul>
        </section>
        <!-- /.sidebar -->
    </aside>
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Main content -->
        <section class="content">
<?php
    // If password is not equal to the password set
    // in the setupVars.conf file, then we skip any
    // content and just complete the page. If no
    // password is set at all, we keep the current
    // behavior: everything is always authorized
    // and will be displayed
    //
    // If auth is required and not set, i.e. no successfully logged in,
    // we show the reduced version of the summary (index) page
    if(!$auth && (!isset($indexpage) || isset($_GET['login']))){
        require "scripts/pi-hole/php/loginpage.php";
        require "footer.php";
        exit();
    }
?>

