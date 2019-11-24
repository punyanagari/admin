<?php /*
*    Pi-hole: A black hole for Internet advertisements
*    (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*    Network-wide ad blocking via your own hardware.
*
*    This file is copyright under the latest version of the EUPL.
*    Please see LICENSE file for your rights under this license. */
    $indexpage = true;
    require_once("scripts/pi-hole/php/gravity.php");
    require "scripts/pi-hole/php/header.php";
    require "scripts/pi-hole/php/savesettings.php";

function getinterval()
    {
        global $piholeFTLConf;
        if(isset($piholeFTLConf["MAXLOGAGE"]))
        {
             return round(floatval($piholeFTLConf["MAXLOGAGE"]), 1);
        }
        else
        {
             return "24";
        }
    }
// Reread ini file as things might have been changed
$setupVars = parse_ini_file("/etc/pihole/setupVars.conf");
if(is_readable($piholeFTLConfFile))
{
	$piholeFTLConf = parse_ini_file($piholeFTLConfFile);
}
else
{
	$piholeFTLConf = array();
}

// Handling of PHP internal errors
$last_error = error_get_last();
if($last_error["type"] === E_WARNING || $last_error["type"] === E_ERROR)
{
	$error .= "There was a problem applying your settings.<br>Debugging information:<br>PHP error (".htmlspecialchars($last_error["type"])."): ".htmlspecialchars($last_error["message"])." in ".htmlspecialchars($last_error["file"]).":".htmlspecialchars($last_error["line"]);
}

?>
<style type="text/css">
	.tooltip-inner {
		max-width: none;
		white-space: nowrap;
	}
</style>

<?php // Check if ad lists should be updated after saving ...
if (isset($_POST["submit"])) {
    if ($_POST["submit"] == "saveupdate") {
        // If that is the case -> refresh to the gravity page and start updating immediately
        ?>
        <meta http-equiv="refresh" content="1;url=gravity.php?go">
    <?php }
} ?>

<?php if (isset($debug)) { ?>
    <div id="alDebug" class="alert alert-warning alert-dismissible fade in" role="alert">
        <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span>
        </button>
        <h4><i class="icon fa fa-exclamation-triangle"></i> Debug</h4>
        <pre><?php print_r($_POST); ?></pre>
    </div>
<?php } ?>

<?php if (strlen($success) > 0) { ?>
    <div id="alInfo" class="alert alert-info alert-dismissible fade in" role="alert">
        <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span>
        </button>
        <h4><i class="icon fa fa-info"></i> Info</h4>
        <?php echo $success; ?>
    </div>
<?php } ?>

<?php if (strlen($error) > 0) { ?>
    <div id="alError" class="alert alert-danger alert-dismissible fade in" role="alert">
        <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span>
        </button>
        <h4><i class="icon fa fa-ban"></i> Error</h4>
        <?php echo $error; ?>
    </div>
<?php } ?>


<?php
// Networking
if (isset($setupVars["PIHOLE_INTERFACE"])) {
    $piHoleInterface = $setupVars["PIHOLE_INTERFACE"];
} else {
    $piHoleInterface = "unknown";
}
if (isset($setupVars["IPV4_ADDRESS"])) {
    $piHoleIPv4 = $setupVars["IPV4_ADDRESS"];
} else {
    $piHoleIPv4 = "unknown";
}
$IPv6connectivity = false;
if (isset($setupVars["IPV6_ADDRESS"])) {
    $piHoleIPv6 = $setupVars["IPV6_ADDRESS"];
    sscanf($piHoleIPv6, "%2[0-9a-f]", $hexstr);
    if (strlen($hexstr) == 2) {
        // Convert HEX string to number
        $hex = hexdec($hexstr);
        // Global Unicast Address (2000::/3, RFC 4291)
        $GUA = (($hex & 0x70) === 0x20);
        // Unique Local Address   (fc00::/7, RFC 4193)
        $ULA = (($hex & 0xfe) === 0xfc);
        if ($GUA || $ULA) {
            // Scope global address detected
            $IPv6connectivity = true;
        }
    }
} else {
    $piHoleIPv6 = "unknown";
}
$hostname = trim(file_get_contents("/etc/hostname"), "\x00..\x1F");
?>


//top

<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-green" id="total_queries" title="only A + AAAA queries">
            <div class="inner">
                <p>Total queries (<span id="unique_clients">-</span> clients)</p>
                <h3 class="statistic"><span id="dns_queries_today">---</span></h3>
            </div>
            <div class="icon">
                <i class="ion ion-earth"></i>
            </div>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-lg-3 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <p>Queries Blocked</p>
                <h3 class="statistic"><span id="ads_blocked_today">---</span></h3>
            </div>
            <div class="icon">
                <i class="ion ion-android-hand"></i>
            </div>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-lg-3 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <p>Percent Blocked</p>
                <h3 class="statistic"><span id="ads_percentage_today">---</span></h3>
            </div>
            <div class="icon">
                <i class="ion ion-pie-graph"></i>
            </div>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-lg-3 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-red" title="<?php echo gravity_last_update(); ?>">
            <div class="inner">
                <p>Domains on Blocklist</p>
                <h3 class="statistic"><span id="domains_being_blocked">---</span></h3>
            </div>
            <div class="icon">
                <i class="ion ion-ios-list"></i>
            </div>
        </div>
    </div>
    <!-- ./col -->
</div>






<?php

	 if($auth){ ?>

if (isset($_GET['tab']) && in_array($_GET['tab'], array("server", "settings"))) {
    $tab = $_GET['tab'];
} else {
    $tab = "server";
}
?>
<div class="row justify-content-md-center">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li<?php if($tab === "server"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#server">Server</a></li>
                <li<?php if($tab === "settings"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#settings">Settings</a></li>
            </ul>
            <div class="tab-content">
                <!-- ######################################################### Teleporter ######################################################### -->
                <div id="server" class="tab-pane fade<?php if($tab === "server"){ ?> in active<?php } ?>">
                    <div class="row">
                        <?php if (extension_loaded('Phar')) { ?>
                        <form role="form" method="post" id="takeoutform"
                              action="scripts/pi-hole/php/teleporter.php"
                              target="_blank" enctype="multipart/form-data">
                            <input type="hidden" name="token" value="<?php echo $token ?>">
                            <div class="col-lg-6 col-md-12">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Teleporter Export</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <p>Export your Pi-hole lists as downloadable archive</p>
                                                <button type="submit" class="btn btn-default">Export</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-12">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Teleporter Import</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-lg-6 col-md-12">
                                                <label>Import ...</label>
                                                <div class="form-group">
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="whitelist" value="true"
                                                                      checked>
                                                            Whitelist</label>
                                                    </div>
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="blacklist" value="true"
                                                                      checked>
                                                            Blacklist (exact)</label>
                                                    </div>
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="regexlist" value="true"
                                                                      checked>
                                                            Regex filters</label>
                                                    </div>
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="auditlog" value="true"
                                                                      checked>
                                                            Audit log</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 col-md-12">
                                                <div class="form-group">
                                                    <label for="zip_file">File input</label>
                                                    <input type="file" name="zip_file" id="zip_file">
                                                    <p class="help-block">Upload only Pi-hole backup files.</p>
                                                    <button type="submit" class="btn btn-default" name="action"
                                                            value="in">Import
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <?php } else { ?>
                        <div class="col-lg-12">
                            <div class="box box-warning">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Teleporter</h3>
                                </div>
                                <div class="box-body">
                                    <p>The PHP extension <code>Phar</code> is not loaded. Please ensure it is installed and loaded if you want to use the Pi-hole teleporter.</p>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <!-- ######################################################### System admin ######################################################### -->
                <div id="server" class="tab-pane fade<?php if($tab === "server"){ ?> in active<?php } ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="box">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Network Information</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <table class="table table-striped table-bordered dt-responsive nowrap">
                                                <tbody>
                                                <tr>
                                                    <th scope="row">Pi-hole Ethernet Interface:</th>
                                                    <td><?php echo htmlentities($piHoleInterface); ?></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Pi-hole IPv4 address:</th>
                                                    <td><?php echo htmlentities($piHoleIPv4); ?></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Pi-hole IPv6 address:</th>
                                                    <td><?php echo htmlentities($piHoleIPv6); ?></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Pi-hole hostname:</th>
                                                    <td><?php echo htmlentities($hostname); ?></td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="box">
                                <div class="box-header with-border">
                                    <h3 class="box-title">FTL Information</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <?php
                                            if ($FTL) {
                                                function get_FTL_data($arg)
                                                {
                                                    global $FTLpid;
                                                    return trim(exec("ps -p " . $FTLpid . " -o " . $arg));
                                                }

                                                $FTLversion = exec("/usr/bin/pihole-FTL version");
                                            ?>
                                            <table class="table table-striped table-bordered dt-responsive nowrap">
                                                <tbody>
                                                    <tr>
                                                        <th scope="row">FTL version:</th>
                                                        <td><?php echo $FTLversion; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">Process identifier (PID):</th>
                                                        <td><?php echo $FTLpid; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">Time FTL started:</th>
                                                        <td><?php print_r(get_FTL_data("start")); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">User / Group:</th>
                                                        <td><?php print_r(get_FTL_data("euser")); ?> / <?php print_r(get_FTL_data("egroup")); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">Total CPU utilization:</th>
                                                        <td><?php print_r(get_FTL_data("%cpu")); ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">Memory utilization:</th>
                                                        <td><?php print_r(get_FTL_data("%mem")); ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">
                                                            <span title="Resident memory is the portion of memory occupied by a process that is held in main memory (RAM). The rest of the occupied memory exists in the swap space or file system.">Used memory:</span>
                                                        </th>
                                                        <td><?php echo formatSizeUnits(1e3 * floatval(get_FTL_data("rss"))); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">
                                                            <span title="Size of the DNS domain cache">DNS cache size:</span>
                                                        </th>
                                                        <td id="cache-size">&nbsp;</td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">
                                                            <span title="Number of cache insertions">DNS cache insertions:</span>
                                                        </th>
                                                        <td id="cache-inserted">&nbsp;</td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">
                                                            <span title="Number of cache entries that had to be removed although they are not expired (increase cache size to reduce this number)">DNS cache evictions:</span>
                                                        </th>
                                                        <td id="cache-live-freed">&nbsp;</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            See also our <a href="https://docs.pi-hole.net/ftldns/dns-cache/" target="_blank">DNS cache documentation</a>.
                                            <?php } else { ?>
                                            <div>The FTL service is offline!</div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box box-warning">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Danger Zone!</h3><br/>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <?php if ($piHoleLogging) { ?>
                                                <button type="button" class="btn btn-warning confirm-disablelogging-noflush form-control">Disable query logging</button>
                                            <?php } else { ?>
                                                <form role="form" method="post">
                                                    <input type="hidden" name="action" value="Enable">
                                                    <input type="hidden" name="field" value="Logging">
                                                    <input type="hidden" name="token" value="<?php echo $token ?>">
                                                    <button type="submit" class="btn btn-success form-control">Enable query logging</button>
                                                </form>
                                            <?php } ?>
                                        </div>
                                        <p class="hidden-md hidden-lg"></p>
                                        <div class="col-md-4">
                                            <?php if ($piHoleLogging) { ?>
                                                <button type="button" class="btn btn-danger confirm-disablelogging form-control">Disable query logging and flush logs</button>
                                            <?php } ?>
                                        </div>
                                        <p class="hidden-md hidden-lg"></p>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-warning confirm-restartdns form-control">Restart DNS resolver</button>
                                        </div>
                                    </div>
                                    <br/>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-danger confirm-flushlogs form-control">Flush logs</button>
                                        </div>
                                        <p class="hidden-md hidden-lg"></p>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-danger confirm-poweroff form-control">Power off system</button>
                                        </div>
                                        <p class="hidden-md hidden-lg"></p>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-danger confirm-reboot form-control">Restart system</button>
                                        </div>
                                    </div>

                                    <form role="form" method="post" id="flushlogsform">
                                        <input type="hidden" name="field" value="flushlogs">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="disablelogsform">
                                        <input type="hidden" name="field" value="Logging">
                                        <input type="hidden" name="action" value="Disable">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="disablelogsform-noflush">
                                        <input type="hidden" name="field" value="Logging">
                                        <input type="hidden" name="action" value="Disable-noflush">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="poweroffform">
                                        <input type="hidden" name="field" value="poweroff">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="rebootform">
                                        <input type="hidden" name="field" value="reboot">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="restartdnsform">
                                        <input type="hidden" name="field" value="restartdns">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php } ?>

<script src="scripts/vendor/jquery.confirm.min.js"></script>
<script src="scripts/pi-hole/js/index.js"></script>

<?php
require "scripts/pi-hole/php/footer.php";
?>
