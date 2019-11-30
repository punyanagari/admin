<?php /*
*    Pi-hole: A black hole for Internet advertisements
*    (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*    Network-wide ad blocking via your own hardware.
*
*    This file is copyright under the latest version of the EUPL.
*    Please see LICENSE file for your rights under this license. */
    $indexpage = true;
    require "scripts/pi-hole/php/header.php";
    require "scripts/pi-hole/php/savesettings.php";
    require_once("scripts/pi-hole/php/gravity.php");

   $setupVars = parse_ini_file("/etc/pihole/setupVars.conf");
   $arahasya = parse_ini_file("/etc/pihole/arahasya.conf");


   if(is_readable($piholeFTLConfFile))
   {
        $piholeFTLConf = parse_ini_file($piholeFTLConfFile);
   }
   else
   {
        $piholeFTLConf = array();
   }


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

//arahasya

if (isset($setupVars["WEBPASSWORD"])) {
    $currenthash = $setupVars["WEBPASSWORD"];
} else {
    $currenthash = "unknown";
}

if (isset($arahasya["VPN_MODE"])) {
    $vpnMode = $arahasya["VPN_MODE"];
    if("$vpnMode" == "Enabled"){
	$vpnModeStatus=true;
    }else{
	$vpnModeStatus=false;
    }
} else {
    $vpnMode = "unknown";
}
if (isset($arahasya["DEFAULT_COUNTRY"])) {
    $defaultCountry = $arahasya["DEFAULT_COUNTRY"];
} else {
    $defaultCountry = "unknown";
}
if (isset($arahasya["PROTOCOL"])) {
    $protocol = $arahasya["PROTOCOL"];
    if("$protocol" == "OpenVPN"){
        $protocolStatus=true;
    }else{
        $protocolStatus=false;
    }
} else {
    $protocol = "unknown";
}
if (isset($arahasya["DNS_CRYPT"])) {
    $dnsCrypt = $arahasya["DNS_CRYPT"];
    if("$dnsCrypt" == "Enabled"){
        $dnsCryptStatus=true;
    }else{
        $dnsCryptStatus=false;
    }
} else {
    $dnsCrypt = "unknown";
}
if (isset($setupVars["BLOCKING_ENABLED"])) {
    $pihole = $setupVars["BLOCKING_ENABLED"];
    if($pihole){
        $piholeStatus="Enabled";
    }else{
        $piholeStatus="Disabled";
    }
} else {
    $pihole = "unknown";
}

?>


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
  // If the user is logged in, then we show the more detailed index page.
  // Even if we would include them here anyhow, there would be nothing to
  // show since the API will respect the privacy of the user if he defines
  // a password
  if($auth){ ?>

<?php
if (isset($_GET['tab']) && in_array($_GET['tab'], array("server", "settings", "changepassword"))) {
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
                <li<?php if($tab === "changepassword"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#changepassword">Change Password</a></li>
            </ul>
            <div class="tab-content">
 	<!-- ######################################################### Server  ######################################################### -->
		 <div id="server" class="tab-pane fade<?php if($tab === "server"){ ?> in active<?php } ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="box">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Chnage Server Location</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <table class="table table-striped table-bordered dt-responsive nowrap">
                                                <tbody>
						<form role="form" method="post">
                                                <tr>
                                                    <th scope="row">Select Protocol:</th>
                                                    <td>
                                                        <div>
                                                                <select class="form-control" name="select1" id="select1">
								<option selected disabled>--Select Protocol--</option>
                                                                <option value="1|OpenVPN">OpenVPN</option>
                                                                <option value="2|Wireguard">Wireguard</option>
                                                                </select>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Select Country:</th>
                                                    <td>
                                                        <div>
                                                                <select class="form-control" name="select2" id="select2">
                                                                <option value="1" selected disabled>Select Country</option>
								<option value="1|in|India">India</option>
								<option value="1|au|Australia">Australia</option>
                                                                <option value="1|us|United States">United States</option>
                                                                <option value="1|uk|United Kingdom">United Kingdom</option>
								<option value="1|fr|France">France</option>
                                                                <option value="1|de|Germany">Germany</option>
                                                                <option value="1|ar|Argentina">Argentina</option>
                                                                <option value="1|at|Austria">Austria</option>
								<option value="1|be|Belgium">Belgium</option>
                                                                <option value="1|ba|Bosnia & Herzegovina">Bosnia & Herzegovina</option>
                                                                <option value="1|br|Brazil">Brazil</option>
                                                                <option value="1|bg|Bulgaria">Bulgaria</option>
                                                                <option value="1|ca|Canada">Canada</option>
                                                                <option value="1|cl|Chile">Chile</option>
                                                                <option value="1|cr|Costa Rica">Costa Rica</option>
                                                                <option value="1|hr|Croatia">Croatia</option>
								<option value="1|cy|Cyprus">Cyprus</option>
                                                                <option value="1|cz|Czech Republic">Czech Republic</option>
                                                                <option value="1|dk|Denmark">Denmark</option>
                                                                <option value="1|ee|Estonia">Estonia</option>
                                                                <option value="1|fi|Finland">Finland</option>
                                                                <option value="1|ge|Georgia">Georgia</option>
                                                                <option value="1|gr|Greece">Greece</option>
                                                                <option value="1|hk|Hong Kong">Hong Kong</option>
								<option value="1|hu|Hungary">Hungary</option>
                                                                <option value="1|is|Iceland">Iceland</option>
                                                                <option value="1|id|Indonesia">Indonesia</option>
                                                                <option value="1|ie|Ireland">Ireland</option>
                                                                <option value="1|il|Israel">Israel</option>
                                                                <option value="1|it|Italy">Italy</option>
                                                                <option value="1|jp|Japan">Japan</option>
                                                                <option value="1|lv|Latvia">Latvia</option>
								<option value="1|lu|Luxembourg">Luxembourg</option>
                                                                <option value="1|my|Malaysia">Malaysia</option>
                                                                <option value="1|mx|Mexico">Mexico</option>
                                                                <option value="1|md|Moldova">Moldova</option>
                                                                <option value="1|nl|Netherlands">Netherlands</option>
                                                                <option value="1|nz|New Zealand">New Zealand</option>
                                                                <option value="1|mk|North Macedonia">North Macedoania</option>
                                                                <option value="1|no|Norway">Norway</option>
                                                                <option value="1|pl|Poland">Poland</option>
                                                                <option value="1|pt|Poland">Portugal</option>
                                                                <option value="1|ro|Romania">Romania</option>
                                                                <option value="1|rs|Serbia">Serbia</option>
								<option value="1|sk|Slovakia">Slovakia</option>
                                                                <option value="1|si|Slovenia">Slovenia</option>
                                                                <option value="1|za|Soutch Africa">South Africa</option>
                                                                <option value="1|ka|South Korea">South Korea</option>
                                                                <option value="1|es|Spain">Spain</option>
                                                                <option value="1|se|Sweden">Sweden</option>
                                                                <option value="1|ch|Switzerland">Switzerland</option>
                                                                <option value="1|tw|Taiwan">Taiwan</option>
                                                                <option value="1|th|Thailand">Thailand</option>
                                                                <option value="1|tr|Turkey">Turkey</option>
                                                                <option value="1|ua|Ukraine">Ukraine</option>
                                                                <option value="1|vn|Vietnam">Vietnam</option>
								<option value="2" selected disabled>Select Country</option>
                                                                <option value="2|au|Australia">Australia</option>
								<option value="2|at|Austria">Australia</option>
                                                                <option value="2|ca|Canada">Canada</option>
                                                                <option value="2|fr|France">France</option>
                                                                <option value="2|de|Germany">Germany</option>
                                                                <option value="2|sg|Singapore">Singapore</option>
								<option value="2|hk|Hong Kong">Japan</option>
								<option value="2|uk|United Kingdom">United Kingdom</option>
                                                                <option value="2|us|United Stauts">United States</option>
								<option value="2|nl|Netherlands">Netherlands</option>
                                                                <option value="2|jp|Japan">Japan</option>
                                                                </select>
                                                        </div>
                                                    </td>
                                                </tr>
                                                                <input type="hidden" name="token" value="<?php echo $token ?>">

						<tr><th><td>
							<button type="submit" class="btn btn-primary pull-right" name="field" value="changeServer">Connect</button>
						</td></th></tr></form>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                          </div>
		       </div>
		   </div>
		</div>
	<!-- ######################################################### Change Password ######################################################### -->
                <div id="changepassword" class="tab-pane fade<?php if($tab === "changepassword"){ ?> in active<?php } ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <form role="form" method="post">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"Change Web Admin Password</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h4>Enter current password:</h4>
                                                <div class="form-group">
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="currentpassword"
                                                               value="<?php echo $currentpassword; ?>">
                                                    </div>
                                                </div>
                                                <h4>Enter new password:</h4>
                                                <div class="form-group">
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="password"
                                                               value="<?php echo $password; ?>">
                                                    </div>
                                                </div>
                                                <h4>Confirm new password:</h4>
                                                <div class="form-group">
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="confirm"
                                                               value="<?php echo $confirm; ?>">
                                                    </div>
                                                </div>
                                                <input type="hidden" name="currenthash" value="<?php echo $currenthash ?>">
                                                <input type="hidden" name="token" value="<?php echo $token ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="box-footer clearfix">
                                        <button type="submit" class="btn btn-primary pull-right" name="field" value="changePassword">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

        <!-- ######################################################### Settings ######################################################### -->
                <div id="settings" class="tab-pane fade<?php if($tab === "settings"){ ?> in active<?php } ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="box">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Settings</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <table class="table table-striped table-bordered dt-responsive nowrap">
                                                <tbody>
                                                <tr>
                                                    <th scope="row">VPN Mode:</th>
                                                    <td><?php echo htmlentities($vpnMode); ?></td><td>
                                                        <label class="switch">
                                                                <input class ="confirm-vpnmode form-control" type="checkbox" name="active" id="vpnModeCheck" <?php if ($vpnModeStatus){ ?>checked<?php }
                                                                      ?>>
                                                                <span class="slider round"></span>
                                                        </label>
                                                    </td>
                                                </tr>
                                                 <tr>
                                                    <th scope="row">DNS-Crypt:</th>
                                                    <td><?php echo htmlentities($dnsCrypt); ?></td><td>
                                                        <label class="switch">
                                                                <input class ="confirm-dns form-control" type="checkbox" name="active" id="dnsCryptCheck" <?php if ($dnsCryptStatus){ ?>checked<?php }
                                                                      ?>>
                                                                <span class="slider round"></span>
                                                        </label>
                                                    </td>
                                                </tr>
                                                 <tr>
                                                    <th scope="row">Pihole:</th>
                                                    <td><?php echo htmlentities($piholeStatus); ?></td><td>
                                                        <label class="switch">
                                                                <input class ="confirm-pihole form-control" type="checkbox" name="active" id="piholeCheck" <?php if ($pihole){ ?>checked<?php }
                                                                      ?>>
                                                                <span class="slider round"></span>
                                                        </label>
                                                    </td>
                                                </tr><form role="form" method="post">
						<tr>
                                                    <th scope="row">Default Protocol:</th>
                                                    <td><?php echo htmlentities($protocol); ?></td><td>
                                                        <div>
                                                                <select class="form-control" name="select1" id="select1">
								<option selected disabled>--Select Protocol--</option>
                                                                <option value="1|OpenVPN">OpenVPN</option>
                                                                <option value="2|Wireguard">Wireguard</option>
                                                                </select>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Default Country:</th>
                                                    <td><?php echo htmlentities($defaultCountry); ?></td><td>
                                                        <div>
                                                                <select class="form-control" name="select2" id="select2">
                                                                <option value="1" selected disabled>Select Country</option>
								<option value="1|in|India">India</option>
								<option value="1|au|Australia">Australia</option>
                                                                <option value="1|us|United States">United States</option>
                                                                <option value="1|uk|United Kingdom">United Kingdom</option>
								<option value="1|fr|France">France</option>
                                                                <option value="1|de|Germany">Germany</option>
                                                                <option value="1|ar|Argentina">Argentina</option>
                                                                <option value="1|at|Austria">Austria</option>
								<option value="1|be|Belgium">Belgium</option>
                                                                <option value="1|ba|Bosnia & Herzegovina">Bosnia & Herzegovina</option>
                                                                <option value="1|br|Brazil">Brazil</option>
                                                                <option value="1|bg|Bulgaria">Bulgaria</option>
                                                                <option value="1|ca|Canada">Canada</option>
                                                                <option value="1|cl|Chile">Chile</option>
                                                                <option value="1|cr|Costa Rica">Costa Rica</option>
                                                                <option value="1|hr|Croatia">Croatia</option>
								<option value="1|cy|Cyprus">Cyprus</option>
                                                                <option value="1|cz|Czech Republic">Czech Republic</option>
                                                                <option value="1|dk|Denmark">Denmark</option>
                                                                <option value="1|ee|Estonia">Estonia</option>
                                                                <option value="1|fi|Finland">Finland</option>
                                                                <option value="1|ge|Georgia">Georgia</option>
                                                                <option value="1|gr|Greece">Greece</option>
                                                                <option value="1|hk|Hong Kong">Hong Kong</option>
								<option value="1|hu|Hungary">Hungary</option>
                                                                <option value="1|is|Iceland">Iceland</option>
                                                                <option value="1|id|Indonesia">Indonesia</option>
                                                                <option value="1|ie|Ireland">Ireland</option>
                                                                <option value="1|il|Israel">Israel</option>
                                                                <option value="1|it|Italy">Italy</option>
                                                                <option value="1|jp|Japan">Japan</option>
                                                                <option value="1|lv|Latvia">Latvia</option>
								<option value="1|lu|Luxembourg">Luxembourg</option>
                                                                <option value="1|my|Malaysia">Malaysia</option>
                                                                <option value="1|mx|Mexico">Mexico</option>
                                                                <option value="1|md|Moldova">Moldova</option>
                                                                <option value="1|nl|Netherlands">Netherlands</option>
                                                                <option value="1|nz|New Zealand">New Zealand</option>
                                                                <option value="1|mk|North Macedonia">North Macedoania</option>
                                                                <option value="1|no|Norway">Norway</option>
                                                                <option value="1|pl|Poland">Poland</option>
                                                                <option value="1|pt|Poland">Portugal</option>
                                                                <option value="1|ro|Romania">Romania</option>
                                                                <option value="1|rs|Serbia">Serbia</option>
								<option value="1|sk|Slovakia">Slovakia</option>
                                                                <option value="1|si|Slovenia">Slovenia</option>
                                                                <option value="1|za|Soutch Africa">South Africa</option>
                                                                <option value="1|ka|South Korea">South Korea</option>
                                                                <option value="1|es|Spain">Spain</option>
                                                                <option value="1|se|Sweden">Sweden</option>
                                                                <option value="1|ch|Switzerland">Switzerland</option>
                                                                <option value="1|tw|Taiwan">Taiwan</option>
                                                                <option value="1|th|Thailand">Thailand</option>
                                                                <option value="1|tr|Turkey">Turkey</option>
                                                                <option value="1|ua|Ukraine">Ukraine</option>
                                                                <option value="1|vn|Vietnam">Vietnam</option>
								<option value="2" selected disabled>Select Country</option>
                                                                <option value="2|au|Australia">Australia</option>
								<option value="2|at|Austria">Australia</option>
                                                                <option value="2|ca|Canada">Canada</option>
                                                                <option value="2|fr|France">France</option>
                                                                <option value="2|de|Germany">Germany</option>
                                                                <option value="2|sg|Singapore">Singapore</option>
								<option value="2|hk|Hong Kong">Japan</option>
								<option value="2|uk|United Kingdom">United Kingdom</option>
                                                                <option value="2|us|United Stauts">United States</option>
								<option value="2|nl|Netherlands">Netherlands</option>
                                                                <option value="2|jp|Japan">Japan</option>
                                                                </select>
                                                        </div>
                                                    </td>
                                                </tr>
                                                                <input type="hidden" name="token" value="<?php echo $token ?>">

						<tr><th><td><td>
							<button type="submit" class="btn btn-primary pull-right" name="field" value="changeDefault">Save</button>
						</td></td></th></tr></form>
                                                </tbody>
                                            </table>
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

                                    <form class="pull-right" role="form" method="post" id="changevpnmodeform">
                                        <input type="hidden" name="field" value="vpnmode">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form class="pull-right" role="form" method="post" id="changednsform">
                                        <input type="hidden" name="field" value="dnscrypt">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form class="pull-right" role="form" method="post" id="changepiholeform">
                                        <input type="hidden" name="field" value="piholemode">
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

<script src="scripts/vendor/jquery.confirm.min.js"></script>
<script src="scripts/pi-hole/js/index.js"></script>
<?php } ?>



<?php
    require "scripts/pi-hole/php/footer.php";
?>


