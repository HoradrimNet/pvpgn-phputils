<?php
// ----------------------------------------------------------------------
// Player -vs- Player Gaming Network Statistics System
// http://pvpgn.spfree.net/
// ----------------------------------------------------------------------
// LICENSE
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License (GPL)
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// To read the license please visit http://www.gnu.org/copyleft/gpl.html
// ----------------------------------------------------------------------
// Author from 2.4.4: Pelish (pelish@gmail.com)
// Original author: jfro (imeepmeep@hotmail.com)
// Author from 2.3.20: Snaiperx (http://www.rino.com.co/)
// Author from 2.3.16: STORM (http://www.stormzone.ru/)
// Tanzania theme author: Tanzania (tanzania@gmx.net)
// ----------------------------------------------------------------------

include_once("config.inc.php");
global $db_type;
require_once("includes/".$db_type."_handler.php");
require_once("includes/pvpgn_rank.php");


class pvpgn_stats {
		var $stats,$game,$data,$user_count;
		var $sql_host,$sql_port,$sql_db,$sql_user,$sql_pass;
		var $sql_record,$sql_bnet,$sql_profile,$sql_teams;

// ----------------------------------------------------------------------------------------------
// This is how we get the binary (with fix for D2DV & D2XP)
// ----------------------------------------------------------------------------------------------

	function get_int($binary) {
		return sprintf ("%u",(ord("$binary{0}") | (ord("$binary{1}")<<8) | (ord("$binary{2}")<<16) | (ord("$binary{3}")<<24))) + 0.0;
	}

// ----------------------------------------------------------------------------------------------
// Defines connection to DB
// ----------------------------------------------------------------------------------------------

	function pvpgn_stats() {
		global $db_host,$db_port,$db_database,$db_user,$db_pass,$db_record,$db_bnet,$db_profile,$db_teams,$game;
		$this->stats = NULL;
		$this->game = $game;
		$this->sql_host = $db_host;
		$this->sql_port = $db_port;
		$this->sql_db = $db_database;
		$this->sql_user = $db_user;
		$this->sql_pass = $db_pass;
		$this->sql_record = $db_record;
		$this->sql_bnet = $db_bnet;
		$this->sql_profile = $db_profile;
		$this->sql_teams = $db_teams;
		$this->data = new sql_handle();
		$this->data->db_connect( $this->sql_host , $this->sql_user , $this->sql_pass, $this->sql_db);
		$this->user_count = 0;
	}

// ----------------------------------------------------------------------------------------------
// No description available
// ----------------------------------------------------------------------------------------------

	function load_external_stats($some_stats,$game_type) {
		$this->stats = $some_stats;
		$this->game = $game_type;
	}

// ----------------------------------------------------------------------------------------------
// This is how we load a given range of stats and sort by sort_by (with fix for WAR3 & W3XP)
// ----------------------------------------------------------------------------------------------

	function load_stats($game_type,$sort_by,$sort_direction,$start,$stop) {
		//print 'hola '.$db_record;
		global $db_record,$db_bnet,$db_profile,$game,$type;
		$test_results = $this->data->db_query("SHOW FIELDS FROM ".$db_record." like '$sort_by'");
		$valid = $this->data->sql_num_rows($test_results);
		if(!($valid < 1)) {
			if (strstr($game,"SEXP") || strstr($game,"STAR") || strstr($game,"W2BN") || strstr($game,"DRTL")) {
				$ranking = new pvpgn_rank();
				$ranking->update_ranks($game,$type);
			}
			$query = "SELECT *,`$sort_by`+0 as sortme FROM ".$db_record." WHERE uid !=0 and not (`$sort_by`=0) ORDER BY sortme $sort_direction LIMIT $start,$stop";
			$result = $this->data->db_query($query);
			$this->stats = $this->data->db_fetch($result);
			$this->game = $game_type;
			$this->build_other();
			$temp = $this->data->sql_fetch_row($this->data->db_query("SELECT COUNT(*) FROM ".$this->sql_record." WHERE uid !=0 and not (`".$sort_by."`=0)"));
			$this->user_count = $temp[0];
		}
		else $this->stats = array();
	}

// ----------------------------------------------------------------------------------------------
// This is how we load stats for a particular user
// ----------------------------------------------------------------------------------------------

	function load_user_stats($user,$game_type) {
		global $site_theme;
		$user_id = $this->data->sql_fetch_assoc($this->data->db_query("SELECT uid FROM ".$this->sql_bnet." WHERE `acct_username` = '".$user."' LIMIT 1"));
		$query = "SELECT * FROM ".$this->sql_record." WHERE uid = ".$user_id['uid'];
		$query_pro = "SELECT * FROM ".$this->sql_profile." WHERE uid = ".$user_id['uid'];
		$user_res = $this->data->db_query($query);
		$user_pro = $this->data->db_query($query_pro);
		$user_info = $this->data->sql_fetch_assoc($user_res);
		$user_profile = $this->data->sql_fetch_assoc($user_pro);
		$this->stats = array(array_merge($user_info,$user_profile));
		$this->stats[0]['description'] = str_replace("\\r\\n","<br />",$this->stats[0]['description']);
		$this->game = $game_type;
		if(strstr($game_type,"WAR3")&&!strstr($game_type,"W3XP")) $this->stats[0]['teams']=$this->get_at_teams($user,$site_theme);
		//print 'hola '.$game_type;
		$this->build_other();
	}

// ----------------------------------------------------------------------------------------------
// This is how we get Arranged Teams stats (unfinished)
// ----------------------------------------------------------------------------------------------

	function get_at_teams($user,$theme) {
		global $page;
		$game = substr($this->game,0,4);
		$user_id = $this->data->sql_fetch_assoc($this->data->db_query("SELECT uid FROM ".$this->sql_bnet." WHERE `acct_username` = '$user' LIMIT 1"));
		$query = "SELECT ".$game."_teamcount FROM ".$this->sql_record." WHERE uid = ".$user_id['uid'];
		
		$user_res = $this->data->db_query($query);
		$user_info = $this->data->sql_fetch_assoc($user_res);

		$at_team_count = $user_info[$game.'_teamcount'];
		for($i=1;($i<=$at_team_count)&&($i<6);$i++) {
			$cols = "uid,".$i."_teammembers,".$i."_teamwins,".$i."_teamlosses,".$i."_teamxp,".$i."_teamlevel";
			$query_teams = "SELECT $cols FROM ".$this->sql_teams." WHERE uid = ".$user_id['uid'];
			$result = $this->data->db_query($query_teams);
			$user_teams = $this->data->db_fetch($result);
			$user_teams[0]['teamwins'] = $user_teams[0][$i."_teamwins"];
			$user_teams[0]['teamlosses'] = $user_teams[0][$i."_teamlosses"];
			$user_teams[0]['teamlevel'] = $user_teams[0][$i."_teamlevel"];
			$user_teams[0]['teammembers'] = str_replace(" ","<br />",$user_teams[0][$i."_teammembers"]);
			$user_teams[0]['at_xp_perc'] = $this->xp_bar_calc($user_teams[0][$i."_teamxp"],$user_teams[0]["teamlevel"]);
		}
		return $content;
	}

// ----------------------------------------------------------------------------------------------
// This is how we make user search
// ----------------------------------------------------------------------------------------------

	function user_search($user_name,$game_type) {
		$user_ids=$this->data->db_search($user_name,"acct_username","uid",$this->sql_bnet);
	  	if(count($user_ids) > 0) {
	  		$where = "WHERE uid = ".$user_ids[0]['uid'];
			if(count($user_ids) > 1) {
		  		for($n=1;$n<count($user_ids);$n++) {
					$where .= " OR uid = ".$user_ids[$n]['uid'];
		  		}
			}
			$usr_result = $this->data->db_query("SELECT * FROM ".$this->sql_record." $where");
			$this->stats = $this->data->db_fetch($usr_result);
			$this->game = $game_type;
			$this->build_other();
			$this->user_count = $this->data->sql_num_rows($usr_result);
		}
		else $this->stats = array(array("image"=>"unknown.gif","username"=>"NO SUCH USER"));
	}

// ----------------------------------------------------------------------------------------------
// This is how we count total users number
// ----------------------------------------------------------------------------------------------

	function user_count() {
		return $this->user_count;
	}

// ----------------------------------------------------------------------------------------------
// This is how we grab the stats
// ----------------------------------------------------------------------------------------------

	function get_stats() {
		return $this->stats;
	}
	


// ----------------------------------------------------------------------------------------------
// This is how we get game type
// ----------------------------------------------------------------------------------------------

	function get_game_type() {
		if(strstr($this->game,"WAR3")&&!strstr($this->game,"WAR3_0_")) {
			$temp_game = substr($this->game,strpos($this->game,"_")+1,strlen($this->game)-strpos($this->game,"_")+1);
		}
		else $temp_game = $this->game;
		$temp_game = $this->game;
		return $temp_game;
	}

// ----------------------------------------------------------------------------------------------
// This is how we retrieve username from a given user ID
// ----------------------------------------------------------------------------------------------

	function get_username($userid) {
		global $db_bnet,$date_format;
		$query = "SELECT acct_username,acct_lastlogin_time FROM ".$this->sql_bnet." WHERE uid = ".$userid." LIMIT 1";
		$usr_results = $this->data->db_query($query);
		$username = $this->data->sql_fetch_assoc($usr_results);
		return array("username"=>$username['acct_username'],"acct_lastlogin_time"=>date($date_format,$username['acct_lastlogin_time']));
	}

	function clantag_to_str($userid) {
		$query = "SELECT cid FROM pvpgn_clanmember WHERE uid = '".$userid."' LIMIT 1";
		$usr_results = $this->data->db_query($query);
		$clanid = $this->data->sql_fetch_assoc($usr_results);
		
		if ($clanid['cid'] != '') {
			$query = "SELECT short FROM pvpgn_clan WHERE cid = '".$clanid['cid']."' LIMIT 1";
			$usr_results = $this->data->db_query($query);
			$clantag = $this->data->sql_fetch_assoc($usr_results);
			$tag= pack("H*",dechex($clantag['short']));
			//print $tag;
    		return $tag;
		}
	}
//$clantag['short']
//1414681600
	
// ----------------------------------------------------------------------------------------------
// This is how we retrieve clanname from a given user ID
// ----------------------------------------------------------------------------------------------

	function get_clanname($userid) {
		global $db_profile;
		$query = "SELECT clanname FROM ".$this->sql_profile." WHERE uid = ".$userid." LIMIT 1";
		$usr_results = $this->data->db_query($query);
		$clanname = $this->data->sql_fetch_assoc($usr_results);
		return $clanname['clanname'];
	}


// ----------------------------------------------------------------------------------------------
// This is how we get total users
// ----------------------------------------------------------------------------------------------

	function get_totals($user) {
		$game = substr($this->game,0,5);
		if($game == "WAR3_" || $game == "W3XP_") {
			$temp[$game.'total_wins'] = $user[$game.'nightelves_wins'] + $user[$game.'orcs_wins'] + $user[$game.'humans_wins'] + $user[$game.'undead_wins'] + $user[$game.'random_wins'];
			$temp[$game.'total_losses'] = $user[$game.'nightelves_losses'] + $user[$game.'orcs_losses'] + $user[$game.'humans_losses'] + $user[$game.'undead_losses'] + $user[$game.'random_losses'];
			if(!($temp[$game.'total_wins'] + $temp[$game.'total_losses'] == 0)) {
				$temp[$game.'total_perc'] = sprintf("%2.2f",($temp[$game.'total_wins']/($temp[$game.'total_wins'] + $temp[$game.'total_losses']))*100);
			}
			else $temp[$game.'total_perc'] = "0.00";
			SetType($temp[$game.'total_wins'],String);
			SetType($temp[$game.'total_losses'],String);
			SetType($temp[$game.'total_perc'],String);
		}
		return $temp;
	}

// ----------------------------------------------------------------------------------------------
// This is how we get user wins and how we set null things
// ----------------------------------------------------------------------------------------------

	function get_perc($user) {
		$temp_game = $this->get_game_type();
		$game = substr($this->game,0,5);
		if (($game == "SEXP_")||($game == "STAR_")||($game == "W2BN_")) {
			$s_game = substr($temp_game,0,5);
			for($i=0;$i<2;$i++) {
				if(count($user[$s_game.$i.'_losses']) == 0 ) $temp[$s_game.$i.'_losses'] = '0';
				if(count($user[$s_game.$i.'_wins']) == 0) $temp[$s_game.$i.'_wins'] = '0';
				if(count($user[$s_game.$i.'_draws']) == 0) $temp[$s_game.$i.'_draws'] = '0';
				if(count($user[$s_game.$i.'_disconnects']) == 0) $temp[$s_game.$i.'_disconnects'] = '0';
				$total = ($user[$s_game.$i.'_wins'])+($user[$s_game.$i.'_losses'])+($user[$s_game.$i.'_draws'])+($user[$s_game.$i.'_disconnects']);
				if($total != 0) $temp[$s_game.$i.'_perc']=sprintf("%2.2f",($user[$s_game.$i.'_wins']/($total))*100);
				else $temp[$s_game.$i.'_perc'] = "0.00";
			}
		}
		else if(($user[$temp_game.'losses']+$user[$temp_game.'wins']) != 0) $temp[$temp_game.'perc']=($user[$temp_game.'wins']/($user[$temp_game.'losses']+$user[$temp_game.'wins']))*100;

		if ($game == "WAR3_" || $game == "W3XP_") {
			if(($user[$game.'orcs_wins']+$user[$game.'orcs_losses'])!=0) {
			$temp[$game.'orcs_perc'] = sprintf("%2.2f",($user[$game.'orcs_wins']/($user[$game.'orcs_wins']+$user[$game.'orcs_losses']))*100);}
			else $temp[$game.'orcs_perc'] = "0.00";

			if(($user[$game.'undead_wins']+$user[$game.'undead_losses'])!=0) {
			$temp[$game.'undead_perc'] = sprintf("%2.2f",($user[$game.'undead_wins']/($user[$game.'undead_wins']+$user[$game.'undead_losses']))*100);}
			else $temp[$game.'undead_perc'] = "0.00";

			if(($user[$game.'nightelves_wins']+$user[$game.'nightelves_losses'])!=0) {
			$temp[$game.'nightelves_perc'] = sprintf("%2.2f",($user[$game.'nightelves_wins']/($user[$game.'nightelves_wins']+$user[$game.'nightelves_losses']))*100);}
			else $temp[$game.'nightelves_perc'] = "0.00";

			if(($user[$game.'humans_wins']+$user[$game.'humans_losses'])!=0) {
			$temp[$game.'humans_perc'] = sprintf("%2.2f",($user[$game.'humans_wins']/($user[$game.'humans_wins']+$user[$game.'humans_losses']))*100);}
			else $temp[$game.'humans_perc'] = "0.00";

			if(($user[$game.'random_wins']+$user[$game.'random_losses'])!=0) {
			$temp[$game.'random_perc'] = sprintf("%2.2f",($user[$game.'random_wins']/($user[$game.'random_wins']+$user[$game.'random_losses']))*100);}
			else $temp[$game.'random_perc'] = "0.00";
		}
		$temp[$temp_game.'perc'] = sprintf("%2.2f",$temp[$temp_game.'perc']);
		return $temp;
	}

// ----------------------------------------------------------------------------------------------
// This is how we build pows
// ----------------------------------------------------------------------------------------------

	function get_pow($rank) {
		$temp=substr($rank,-1);
		if (($rank<10)or($rank>20)) {
			if ($temp==1) $pow ='st';
			else if (($temp==2)) $pow ='nd';
			else if (($temp==3)) $pow ='rd';
			else $pow ='th';
		}
		else $pow ='th';
		return $pow;	
	}

// ----------------------------------------------------------------------------------------------
// This is how we build stats for DRTL, STAR, SEXP, W2BN, WAR3, W3XP
// ----------------------------------------------------------------------------------------------

	function build_other() {
		$game = substr($this->game,0,5);
		global $type;

		for($n=0;$n<count($this->stats);$n++) {
			$this->stats[$n] = array_merge($this->stats[$n],$this->get_username($this->stats[$n]['uid']));
			$this->stats[$n][$game.'lastlogin'] = $this->stats[$n]['acct_lastlogin_time'];

			//bgcolors
			if ($n%2==0) $this->stats[$n][$game.'bgcolor'] ='#271A13';
        		else $this->stats[$n][$game.'bgcolor'] ='#111111';
			
			//Diablo 1 games
			if ($game == "DRTL_") {
				$d1_classes = array(0 => "Warrior",1 => "Rogue", 2=> "Sorcerer");
				$this->stats[$n]['DRTL_0_class'] = $d1_classes[$this->stats[$n]['DRTL_0_class']];
				$this->stats[$n][$game.'0_pow'] = $this->get_pow($this->stats[$n][$game.'0_rank']);
				if (Empty($this->stats[$n]['DRTL_0_class'])) {
					$this->stats[$n]['DRTL_0_image'] = 'no.gif';
				}
				else $this->stats[$n]['DRTL_0_image'] = $this->stats[$n]['DRTL_0_class'].'.gif';
			}

			//StarCraft, StarCraft: BroodWar and WarCraft 2 BNE games
			if (($game == "STAR_")||($game == "SEXP_")||($game == "W2BN_")) {               
				$this->stats[$n]=array_merge($this->stats[$n],$this->get_perc($this->stats[$n]));
				if (($type=='0')or($type=='1')) {
					$this->stats[$n][$game.$type.'_pow'] = $this->get_pow($this->stats[$n][$game.$type.'_rank']);
				}
				else if (Empty($type)) {
					//normal rank
					$this->stats[$n][$game.'0_pow'] = $this->get_pow($this->stats[$n][$game.'0_rank']);
					//ladder rank
					$this->stats[$n][$game.'1_pow'] = $this->get_pow($this->stats[$n][$game.'1_rank']);
				}
				else die('Incorrect game type for StarCraft or WarCraft 2 games.<BR>Use 1 for ladder or 0 for normal games!');
			}

			//WarCraft 3 and WarCraft 3 The Frozen Throne games
			if (($game == "WAR3_")||($game == "W3XP_")) {
				$temp = $this->stats[$n];
				$temp2 = array("orcs_wins"=>$temp[$game.'orcs_wins'],
				"humans_wins"=>$temp[$game.'humans_wins'],
				"nightelves_wins"=>$temp[$game.'nightelves_wins'],
				"undead_wins"=>$temp[$game.'undead_wins'],
				"random_wins"=>$temp[$game.'random_wins']);
				$max_wins = max($temp2);
				$max_key = array_search($max_wins,$temp2);
				$max_race = substr($max_key,0,strpos($max_key,"_"));

				if ($game == "WAR3_") {
	    				if($max_wins < 25) $image = "tier1-orcs.gif";
					else if(($max_wins < 250) && ($max_wins >= 25)) $image = "tier2-".$max_race.".gif";
					else if(($max_wins < 500) && ($max_wins >= 250)) $image = "tier3-".$max_race.".gif";
					else if(($max_wins < 1500) && ($max_wins >= 500)) $image = "tier4-".$max_race.".gif";
					else if($max_wins >= 1500) $image = "tier5-".$max_race.".gif";
				}
				else if ($game == "W3XP_") {
					global $icon_level1,$icon_level2,$icon_level3,$icon_level4,$icon_level5;
					if($max_wins < $icon_level1) $image = "tier1-orcs.gif";
					else if(($max_wins < $icon_level2) && ($max_wins >= $icon_level1)) $image = "tier2-".$max_race.".gif";
					else if(($max_wins < $icon_level3) && ($max_wins >= $icon_level2)) $image = "tier3-".$max_race.".gif";
					else if(($max_wins < $icon_level4) && ($max_wins >= $icon_level3)) $image = "tier4-".$max_race.".gif";
					else if(($max_wins < $icon_level5) && ($max_wins >= $icon_level4)) $image = "tier5-".$max_race.".gif";
					else if($max_wins >= $icon_level5) $image = "tier6-".$max_race.".gif";
				}

				$fullimage = "full/".$image;
				$this->stats[$n]['image']=$image;
				$this->stats[$n]['full_image']=$fullimage;
				$this->stats[$n]=array_merge($this->stats[$n],$this->get_perc($this->stats[$n]));
				$this->stats[$n]=array_merge($this->stats[$n],$this->get_totals($this->stats[$n]));
				$this->stats[$n][$game.'solo_xp_perc'] = $this->xp_bar_calc($this->stats[$n][$game.'solo_xp'],$this->stats[$n][$game.'solo_level']);
				$this->stats[$n][$game.'solo_xpperc'] = $this->xp_bar_calc($this->stats[$n][$game.'solo_xp'],$this->stats[$n][$game.'solo_level']);
				$this->stats[$n][$game.'solo_level_min'] = ($this->stats[$n][$game.'solo_level']) - 1;
				$this->stats[$n][$game.'solo_level_max'] = ($this->stats[$n][$game.'solo_level']) + 1;
				$this->stats[$n][$game.'team_xp_perc'] = $this->xp_bar_calc($this->stats[$n][$game.'team_xp'],$this->stats[$n][$game.'team_level']);
				$this->stats[$n][$game.'team_level_min'] = ($this->stats[$n][$game.'team_level']) - 1;
				$this->stats[$n][$game.'team_level_max'] = ($this->stats[$n][$game.'team_level']) + 1;
				$this->stats[$n][$game.'ffa_perc'] = $this->xp_bar_calc($this->stats[$n][$game.'ffa_xp'],$this->stats[$n][$game.'ffa_level']);
				$this->stats[$n][$game.'ffa_level_min'] = ($this->stats[$n][$game.'ffa_level']) - 1;
				$this->stats[$n][$game.'ffa_level_max'] = ($this->stats[$n][$game.'ffa_level']) + 1;
				$this->stats[$n][$game.'clanname'] = $this->get_clanname($this->stats[$n]['uid']);
				$this->stats[$n][$game.'clantag'] = $this->clantag_to_str($this->stats[$n]['uid']);

				if (($type=='solo')||($type=='team')||($type=='ffa')) {
					$this->stats[$n][$game.'pow'] = $this->get_pow($this->stats[$n][$game.$type.'_rank']);
				}
				else if (Empty($type)) {
					//solo rank
					$this->stats[$n][$game.'solo_pow'] = $this->get_pow($this->stats[$n][$game.'solo_rank']);
					//team rank
					$this->stats[$n][$game.'team_pow'] = $this->get_pow($this->stats[$n][$game.'team_rank']);
					//ffa rank
					$this->stats[$n][$game.'ffa_pow'] = $this->get_pow($this->stats[$n][$game.'ffa_rank']);
				}
				else die('Incorrect game type.<br>Please use team, solo or ffa!');
			}
		}
	}

// ----------------------------------------------------------------------------------------------
// This is how we define how the experience calculated:FIXED!!
// ----------------------------------------------------------------------------------------------

	function xp_bar_calc($xp,$j) {
  		$xp_min = array(-1, 0, 100, 200, 400, 600, 900, 1200, 1600, 2000, 2500, 
  					3000, 3500, 4000, 4500, 5000, 5500,
          			6000, 6500, 7000, 7500, 8000, 8500, 9000);
  		$xp_max = array(-1, 200, 400, 600, 900, 1200, 1600, 2000, 2500, 
  					3000, 3500, 4000, 4500, 5000, 5500,
          			6000, 6500, 7000, 7500, 8000, 8500, 9000, 9500);
          
		for ($i = 0; $i < count($xp_min); $i++) {
			if ($xp >= $xp_min[$i] && $xp < $xp_max[$i]) {
				if ($xp> 0) {
					if($xp>= $xp_min[$j]) $t = (((($xp - $xp_min[$j]) / ($xp_max[$i] - $xp_min[$j])) * 100)/2)+50;
					else $t = (((($xp - $xp_min[$i+1]) / ($xp_max[$i] - $xp_min[$i+1])) * 100)/2);
				}
			else $t=50;
			if ($i < $j) return $t;
			else return $t;
			}
  		}
  		return 0;
	}

// ----------------------------------------------------------------------------------------------
// This is how we grab stats from ladder.D2DV and insert them into the SQL database
// ----------------------------------------------------------------------------------------------

	function d2ladder_update() {
		
		global $d2ladder_file,$d2update_time,$game,$type,$db_d2;
		$ranking = new pvpgn_rank();
		$S_INIT = 0x1;
		$S_EXP  = 0x20;
		$S_HC   = 0x04;
		$S_DEAD = 0x08;
		$sexes = array("Amazon"=>"f","Sorceress"=>"f","Necromancer"=>"m","Paladin"=>"m","Barbarian"=>"m","Druid"=>"m","Assassin"=>"f");
		$diff = array("D2XP"=>array(
			1=>array(
				"SC"=>array("m"=>"Slayer","f"=>"Slayer"),
				"HC"=>array("m"=>"Destroyer","f"=>"Destroyer")),
			2=>array(
				"SC"=>array("m"=>"Champion","f"=>"Champion"),
				"HC"=>array("m"=>"Conqueror","f"=>"Conqueror")),
			3=>array(
				"SC"=>array("m"=>"Patriarch","f"=>"Matriarch"),
				"HC"=>array("m"=>"Guardian","f"=>"Guardian"))),
			"D2DV"=>array(
			1=>array(
				"SC"=>array("m"=>"Sir","f"=>"Dame"),
				"HC"=>array("m"=>"Count","f"=>"Countess")),
			2=>array(
				"SC"=>array("m"=>"Lord","f"=>"Lady"),
				"HC"=>array("m"=>"Duke","f"=>"Duchess")),
			3=>array(
				"SC"=>array("m"=>"Baron","f"=>"Baroness"),
				"HC"=>array("m"=>"King","f"=>"Queen"))));
		$classes = array("Amazon","Sorceress","Necromancer","Paladin","Barbarian","Druid","Assassin");
		$query = "SELECT d2ladder_time FROM counters";
		$result = $this->data->db_query($query);
		$last_update = $this->data->sql_fetch_assoc($result);
		$now = time();
		if($d2update_time != 0) {
			if($last_update['d2ladder_time'] == ''){	
				$update = true;
				$query = "INSERT INTO `counters` (`d2ladder_time`) VALUES ('$now')";
				$result = $this->data->db_query($query);
			}	
			else if(($now >= ($last_update['d2ladder_time'] + $d2update_time)) || ($last_update['d2ladder_time'] == 0)){	
				$update = true;
				$query = "UPDATE counters SET d2ladder_time = $now LIMIT 1";
				$result = $this->data->db_query($query);
			}
			else $update = false;
		}
		else $update = false;

		if($update) {
			$fp = fopen($d2ladder_file,"rb");
			$size = filesize($d2ladder_file);
			$maxtype = fread($fp,4);
			$checksum = fread($fp,4);
			$size = $size - 8;
			$checksumi = $this->get_int($checksum);
			$maxtypei = $this->get_int($maxtype);
			
			$types=array();	
			
			while (!feof ($fp) && ($maxtypei>($type+1))) {
				$typ_a=array();
				$type = $this->get_int(fread($fp,4));
				$typ_a[offset]=$this->get_int(fread($fp,4));
				$typ_a[records]=$this->get_int(fread($fp,4));
				$types[$type]=$typ_a;
			}
			$size = (filesize($d2ladder_file) - ftell ($fp));

			$ladder=array();
			foreach ($types as $type => $typ_a) {
				 for($i=0;$i<=($typ_a[records]-1);$i++){
					
					$offset=($i*24+$typ_a[offset]);
					fseek($fp, $offset, SEEK_SET);
					$xp_r = fread($fp,4);
					$status_r = fread($fp,2);
					$level_r = fread($fp,1);
					$class_r = fread($fp,1);
					$charname_r = fread($fp,16);
					
					if(trim($charname_r)!==''){
					$charakter=array();
					$status=$this->get_int($status_r);

						$charakter[xp]=$this->get_int($xp_r);
						$charakter[level]=$this->get_int($level_r);
						$charakter[classs]=$classes[$this->get_int($class_r)];
						$charakter[game]=($status & $S_EXP)?"D2XP":"D2DV";
						$charakter[hc]=($status & $S_HC)?"HC":"SC";
						if($status & $S_HC) $charakter[dead]=(($status & $S_DEAD))?"DEAD":"ALIVE";
						$charakter[difficulty]=floor((($status >> 0x08) & 0x0f) / 5);
						$charakter[prefix]=$diff[$charakter[game]][$charakter[difficulty]][$charakter[hc]][$sexes[$charakter[classs]]];
						$charakter[charname]=trim($charname_r);
						$ladder[$type][]=$charakter;
					}
				}

			}
			foreach ($ladder as $type => $charakters) {
				foreach ($charakters as $charakter) {
					$query = "SELECT * FROM `$db_d2` WHERE charname = '$charakter[charname]'";
					$result = $this->data->db_query($query);
					$row = $this->data->db_fetch($result);
				
					if($this->data->sql_num_rows($result) == 0) {
						$query = "INSERT INTO `$db_d2` (`charname`,`title`,`level`,`class`,`experience`,`type`,`dead`,`game`) VALUES ('$charakter[charname]','$charakter[prefix]','$charakter[level]','$charakter[classs]','$charakter[xp]','$charakter[hc]','$charakter[dead]','$charakter[game]')";
						$result = $this->data->db_query($query);
					}else{
						$query = "UPDATE `$db_d2` SET experience=$charakter[xp], title='$charakter[prefix]', level=$charakter[level], class='".$charakter[classs]."', type='$charakter[hc]', dead='$charakter[dead]', game='$charakter[game]' WHERE charname='$charakter[charname]'";
						$result = $this->data->db_query($query);
							if($result != 1) {
								print "Failed UPDATE Query: $query <br />";
							}
					}
				}
				$ranking->update_ranks($charakter[game],$charakter[hc]);
			}
	
		}

	}

// ----------------------------------------------------------------------------------------------
// This is how we load Diablo II stats
// ----------------------------------------------------------------------------------------------

	function load_d2stats($game,$sort_by,$sort_direction,$start,$stop) {
		global $db_d2;
		//$this->stats->d2ladder_update();
		list($game,$type,$junk) = explode("_",$game);
		$query = "SELECT * FROM  $db_d2 WHERE game = '$game' and type='$type' ORDER BY $sort_by $sort_direction LIMIT $start,$stop";
		$result = $this->data->db_query($query);
		//print 'hola '.$stop;
		$this->stats = $this->data->db_fetch($result);
		$this->game = $game;
		$temp = $this->data->sql_fetch_row($this->data->db_query("SELECT COUNT(*) FROM $db_d2 WHERE game='$game' and type='$type'"));
		$this->user_count = $temp[0];
		//print 'hola '.$this->user_count;
		for($n=0;$n<count($this->stats);$n++) {
			if ($n%2==0) $this->stats[$n][$game.'_bgcolor'] ='#271A13';
			else $this->stats[$n][$game.'_bgcolor'] ='#111111';
			global $type;
			//if ($type=='SC') {
			$temp=$this->stats[$n]['rank'];
			if (($temp==11)||($temp==12)||($temp==13)) $temp=15;
			$solo_rank=substr($temp,-1);
			// print $solo_rank;  
			if ($solo_rank==1) $this->stats[$n][$game.'_pow'] ='st';
			else if ($solo_rank==2) $this->stats[$n][$game.'_pow'] ='nd';
			else if ($solo_rank==3) $this->stats[$n][$game.'_pow'] ='rd';
			else $this->stats[$n][$game.'_pow'] ='th';
		}
	}

// ----------------------------------------------------------------------------------------------
// This is how we stop stats grabbing
// ----------------------------------------------------------------------------------------------

	function shutdown() {
		$this->data->db_disconnect();
		$this->data = NULL;
	}

// ----------------------------------------------------------------------------------------------
// This is how we load user files from "users" directory (unfinished)
// ----------------------------------------------------------------------------------------------

	function load_user_files($start,$stop) {

	}

// ----------------------------------------------------------------------------------------------
// This is how we loads user file and grab info from it (unfinished)
// ----------------------------------------------------------------------------------------------

	function load_user_file($userFile,$game_type) {
		global $pvpgn_users;
		$file_array = file($pvpgn_users.$userFile);
		for($n = 0; $n < count($file_array); $n++) {
			$buffer=$file_array[$n];
			list($nothing,$key,$nothing,$value) = explode('"',$buffer);
			$pos1 = strpos($key,"\\");
			$table = substr($key,0,$pos1);
			$key = str_replace($table."\\\\","",$key);
			$key = str_replace("\\\\","_",$key);
			$user[$key] = $value;
			if($key == "acct_userid") $user['uid'] = $value;
			if($key == "acct_username") $user['username'] = $value;
		}
		$this->game = $game_type;
		$user = array_merge($user,$this->get_perc($user));
		return $user;
	}
}

?>