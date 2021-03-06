<?php
/* Pi-hole: A black hole for Internet advertisements
*  (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*  Network-wide ad blocking via your own hardware.
*
*  This file is copyright under the latest version of the EUPL.
*  Please see LICENSE file for your rights under this license. */

if(basename($_SERVER['SCRIPT_FILENAME']) !== "index.php")
{
	die("Direct access to this script is forbidden!");
}

function validIP($address){
	if (preg_match('/[.:0]/', $address) && !preg_match('/[1-9a-f]/', $address)) {
		// Test if address contains either `:` or `0` but not 1-9 or a-f
		return false;
	}
	return !filter_var($address, FILTER_VALIDATE_IP) === false;
}

// Check for existance of variable
// and test it only if it exists
function istrue(&$argument) {
	if(isset($argument))
	{
		if($argument)
		{
			return true;
		}
	}
	return false;
}

// Credit: http://stackoverflow.com/a/4694816/2087442
function validDomain($domain_name)
{
	$validChars = preg_match("/^([_a-z\d](-*[_a-z\d])*)(\.([_a-z\d](-*[a-z\d])*))*(\.([a-z\d])*)*$/i", $domain_name);
	$lengthCheck = preg_match("/^.{1,253}$/", $domain_name);
	$labelLengthCheck = preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name);
	return ( $validChars && $lengthCheck && $labelLengthCheck ); //length of each label
}

function validDomainWildcard($domain_name)
{
	// There has to be either no or at most one "*" at the beginning of a line
	$validChars = preg_match("/^((\*.)?[_a-z\d](-*[_a-z\d])*)(\.([_a-z\d](-*[a-z\d])*))*(\.([a-z\d])*)*$/i", $domain_name);
	$lengthCheck = preg_match("/^.{1,253}$/", $domain_name);
	$labelLengthCheck = preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name);
	return ( $validChars && $lengthCheck && $labelLengthCheck ); //length of each label
}

function validMAC($mac_addr)
{
  // Accepted input format: 00:01:02:1A:5F:FF (characters may be lower case)
  return (preg_match('/([a-fA-F0-9]{2}[:]?){6}/', $mac_addr) == 1);
}

function validEmail($email)
{
	return filter_var($email, FILTER_VALIDATE_EMAIL)
		// Make sure that the email does not contain special characters which
		// may be used to execute shell commands, even though they may be valid
		// in an email address. If the escaped email does not equal the original
		// email, it is not safe to store in setupVars.
		&& escapeshellcmd($email) === $email;
}

$dhcp_static_leases = array();
function readStaticLeasesFile()
{
	global $dhcp_static_leases;
	$dhcp_static_leases = array();
	try
	{
		$dhcpstatic = @fopen('/etc/dnsmasq.d/04-pihole-static-dhcp.conf', 'r');
	}
	catch(Exception $e)
	{
		echo "Warning: Failed to read /etc/dnsmasq.d/04-pihole-static-dhcp.conf, this is not an error";
		return false;
	}

	if(!is_resource($dhcpstatic))
		return false;

	while(!feof($dhcpstatic))
	{
		// Remove any possibly existing variable with this name
		$mac = ""; $one = ""; $two = "";
		sscanf(trim(fgets($dhcpstatic)),"dhcp-host=%[^,],%[^,],%[^,]",$mac,$one,$two);
		if(strlen($mac) > 0 && validMAC($mac))
		{
			if(validIP($one) && strlen($two) == 0)
				// dhcp-host=mac,IP - no HOST
				array_push($dhcp_static_leases,["hwaddr"=>$mac, "IP"=>$one, "host"=>""]);
			elseif(strlen($two) == 0)
				// dhcp-host=mac,hostname - no IP
				array_push($dhcp_static_leases,["hwaddr"=>$mac, "IP"=>"", "host"=>$one]);
			else
				// dhcp-host=mac,IP,hostname
				array_push($dhcp_static_leases,["hwaddr"=>$mac, "IP"=>$one, "host"=>$two]);
		}
		else if(validIP($one) && validDomain($mac))
		{
			// dhcp-host=hostname,IP - no MAC
			array_push($dhcp_static_leases,["hwaddr"=>"", "IP"=>$one, "host"=>$mac]);
		}
	}
	return true;
}

function isequal(&$argument, &$compareto) {
	if(isset($argument))
	{
		if($argument === $compareto)
		{
			return true;
		}
	}
	return false;
}

function isinserverlist($addr) {
	global $DNSserverslist;
	foreach ($DNSserverslist as $key => $value) {
		if (isequal($value['v4_1'],$addr) || isequal($value['v4_2'],$addr))
			return true;
		if (isequal($value['v6_1'],$addr) || isequal($value['v6_2'],$addr))
			return true;
	}
	return false;
}

$DNSserverslist = [];
function readDNSserversList()
{
	// Reset list
	$list = [];
	$handle = @fopen("/etc/pihole/dns-servers.conf", "r");
	if ($handle)
	{
		while (($line = fgets($handle)) !== false)
		{
			$line = rtrim($line);
			$line = explode(';', $line);
			$name = $line[0];
			$values = [];
			if (!empty($line[1])) {
				$values["v4_1"] = $line[1];
			}
			if (!empty($line[2])) {
				$values["v4_2"] = $line[2];
			}
			if (!empty($line[3])) {
				$values["v6_1"] = $line[3];
			}
			if (!empty($line[4])) {
				$values["v6_2"] = $line[4];
			}
            $list[$name] = $values;
		}
		fclose($handle);
	}
	return $list;
}

$adlist = [];
function readAdlists()
{
	// Reset list
	$list = [];
	$handle = @fopen("/etc/pihole/adlists.list", "r");
	if ($handle)
	{
		while (($line = fgets($handle)) !== false)
		{
			if(strlen($line) < 3)
			{
				continue;
			}
			elseif($line[0] === "#")
			{
				// Comments start either with "##" or "# "
				if($line[1] !== "#" &&
				   $line[1] !== " ")
				{
					// Commented list
					array_push($list, [false,rtrim(substr($line, 1))]);
				}
			}
			else
			{
				// Active list
				array_push($list, [true,rtrim($line)]);
			}
		}
		fclose($handle);
	}
	return $list;
}

	// Read available adlists
	$adlist = readAdlists();
	// Read available DNS server list
	$DNSserverslist = readDNSserversList();

	$error = "";
	$success = "";

	if(isset($_POST["field"]))
	{
		// Handle CSRF

		// Process request
		switch ($_POST["field"]) {
			 case "vpnmode":
                                exec("sudo pihole -a changevpnmode");
                                $success = "Changed VPN Mode!";
                                break;

                        case "dnscrypt":
                                exec("sudo pihole -a changednsmode");
                                $success = "Changed DNS-Crypt Mode!";
                                break;


                        case "piholemode":
                                exec("sudo pihole -a changepiholemode");
                                $success = "Changed Pihole Mode!";
                                break;

			case "poweroff":
				exec("sudo pihole -a poweroff");
				$success = "The system will poweroff in 5 seconds...";
				break;

			case "reboot":
				exec("sudo pihole -a reboot");
				$success = "The system will reboot in 5 seconds...";
				break;

			case "restartdns":
				exec("sudo pihole -a restartdns");
				$success = "The DNS server has been restarted";
				break;


			case "changePassword":
                                        $currentpassword = $_POST["currentpassword"];
                                        $password = $_POST["password"];
                                        $confirm = $_POST["confirm"];
                                        if("$currentpassword" != "" && "$password" != "" && "$confirm" != ""){
                                                $hash = hash('sha256', $currentpassword);
                                                $hash1 = hash('sha256', $hash);
                                                $currenthash = $_POST["currenthash"];
                                                if("$hash1" == "$currenthash"){
                                                        if("$password" == "$confirm" && "$hash1" == "$currenthash"){
                                                                exec("sudo pihole -a -p ".$password);
                                                                $success .= "The password has been changed successfully!";
                                                        }
                                                        else{
                                                                $error .= "Password does not match!";
                                                        }
                                                }
                                                else{
                                                        $error .= "Please enter correct password!";
                                                }
                                        }
                                        else{
                                                $error .= "Password cannot be blank!";
                                        }

                                break;

			case "changeNord":
                                        $nordpass = $_POST["nordpass"];
                                        $nordmail = $_POST["nordmail"];
					$nordconfirm = $_POST["nordconfirm"];
                                        if("$nordmail" != "" && "$nordpass" != "" && "$nordconfirm" != ""){

						if("$nordpass" == "$nordconfirm"){
                                                	exec("sudo pihole -a changenord \"".$nordmail."\" "."\'$nordpass\'");
                                                	$success .= "NordVPN details  have been changed successfully!";
						}
						else{
							$error .= "Password does not match!";
						}
                                        }
                                        else{
                                                $error .= "Details cannot be blank!";
                                        }

                                break;

			case "updateNord":
					exec("sudo pihole -a updatenord");
					$success .= "Status updated...";
                                break;

                        case "changeDefault":

				$protocol1 =explode('|', $_POST['select1']);
				$country1 =explode('|', $_POST['select2']);
				if($protocol1[0] != ""){
					exec("sudo pihole -a changedefaults \"".$protocol1[1]."\" "."\"$country1[2]\"");
					$success .= "Default settings changed...";
				}
				else{
					$error .= "Please select the protocol and the country....";
				}
                                break;

                        case "changeServer":

                                $protocol =explode('|', $_POST['select3']);
                                $country =explode('|', $_POST['select4']);
                                if($protocol[0] != ""){
                                        exec("sudo pihole -a changeserver \"".$protocol[1]."\" ".$country[1]);
                                        $success .= "Connecting to $country[2]...";
                                }
                                else{
                                        $error .= "Please select the protocol and the country...";
                                }
                                break;
			case "quickConnect":
				exec("sudo pihole -a quickconnect");
                                $success .= "Connecting to the Fastest server...";
                                break;

                        case "disConnect":
				exec("sudo pihole -a disconnect");
                                $success .= "Disconnecting NordVPN connection...";
                                break;

			case "changeWifi":
				$wifiname = $_POST["wifiname"];
                                $wifipassword = $_POST["wifipassword"];
                                exec("sudo pihole -a changewifidetails \"".$wifiname."\" ".$wifipassword);
                                $success .= "Changed Wifi Details succesfully. Reboot to enable changes!";
                                break;
			default:
				// Option not found
				$debug = true;
				break;
		}
	}

	// Credit: http://stackoverflow.com/a/5501447/2087442
	function formatSizeUnits($bytes)
	{
		if ($bytes >= 1073741824)
		{
			$bytes = number_format($bytes / 1073741824, 2) . ' GB';
		}
		elseif ($bytes >= 1048576)
		{
			$bytes = number_format($bytes / 1048576, 2) . ' MB';
		}
		elseif ($bytes >= 1024)
		{
			$bytes = number_format($bytes / 1024, 2) . ' kB';
		}
		elseif ($bytes > 1)
		{
			$bytes = $bytes . ' bytes';
		}
		elseif ($bytes == 1)
		{
			$bytes = $bytes . ' byte';
		}
		else
		{
			$bytes = '0 bytes';
		}

		return $bytes;
	}
?>


