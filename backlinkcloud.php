<?php
/*
Plugin Name: BackLinkCloud
Plugin URI: http://www.backlinkcloud.com
Description: Creates easy automated system to participate in link recipication
Version: 1.0.0
Author: Dan Morgan
Author URI: http://www.danmorgan.net
*/

function backlinkcloud_handler() {

	$pdn = trim(strtolower(substr($_SERVER["REQUEST_URI"],1)));
	if ($pdn[strlen($pdn)-1] == "/") $pdn = substr_replace($pdn,"",-1);
	$ip = get_option("ignore_patters");
	if (!$pdn) {
		return false;
	}
	if (stristr($ip,$pdn)) { return; }

	//Is Domain Valid

	if(@checkdnsrr($pdn, 'A') ) {
		$surl = "http://{$pdn}";
		//Is Domain Cached
		$cache_threshold = time() - (3600 * 24);
		$store_threshold = time() - (3600 * 120);
		$s_sites = get_option("backlinkcloud_sites");

		if ($sites = unserialize($s_sites)) {
			foreach($sites as $k=>$v) {
				if ($k != $surl) {
					if ($v["last_update"] < $store_threshold) { //Remove old listings
						unset($sites[$k]);
					}
				}
			}
			if (is_array($sites[$surl])) { //Site exists
				if ($sites[$surl]["last_update"] > $cache_threshhold) {
					$site_info = $sites[$surl]; //Load Cached
				}
			}
		} else {
			$sites = array();
		}

		if (!$site_info) {
			$ud = parse_url(get_option("siteurl"));
			$ohost = $ud["host"];
			$spre = file_get_contents("http://www.backlinkcloud.com/{$ohost}/{$pdn}");
			$site_info = unserialize($spre);
			if (is_array($site_info)) {
				$site_info["site_url"] = $surl;
				$site_info["site_domain"] = $pdn;
				$site_info["last_update"] = time();
				if (!$site_info["title"]) {
					$site_info["title"] = $surl;
				}
				$sites[$surl] = $site_info;
				update_option("backlinkcloud_sites",serialize($sites)); //Store cached
				add_action( 'the_content', 'backlinkcloud_render' );
				query_posts('showposts=1');
			}
		}


		if (is_array($site_info)) {
			query_posts('');
			add_filter( 'the_content', 'backlinkcloud_render' );
			$GLOBALS["__backlinkcloud"]["site_info"] = $site_info;
			$GLOBALS["__backlinkcloud"]["is_valid"] = true;
		}
	}
}


function backlinkcloud_activate() {
	$ud = parse_url(get_option("siteurl"));
	$ohost = $ud["host"];
	$spre = file_get_contents("http://www.backlinkcloud.com/{$ohost}/backlinkcloud.com"); //Register to directory
}

function backlinkcloud_redirect_canonical_filter($redirect, $request) {

	if ( is_404() ) {
		// 404s are our domain now - keep redirect_canonical out of it!
		return false;
	}

	// redirect_canonical is good to go
	return $redirect;
}

function backlinkcloud_setup_admin() {
	add_options_page( 'Backlink Cloud', 'Backlink Cloud', 5, __FILE__, 'backlinkcloud_options_page' );
}

function backlinkcloud_options_page() {
	?>
	<div class="wrap">
	<h2>Backlink Cloud</h2>

	<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>

	<table class="form-table">

	<tr valign="top">
		<th scope="row"><?php _e('Ignored patterns:') ?></th>
		<td>
			<textarea name="ignored_patterns" cols="44" rows="5"><?php echo htmlspecialchars(get_option('ignored_patterns')); ?></textarea><br />
			<?php _e("One domain per line"); ?>
		</td>
	</tr>

	</table>

	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="ignored_patterns" />

	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
	</p>
	<p>Last 10 Requests:</p>
<?
	$gosites = get_option("backlinkcloud_sites");
	if ($sites = unserialize($gosites)) {
		$sites = array_reverse($sites);
		$count = "0";
		echo "<OL>\n";
		foreach($sites as $k=>$v) {
			if ($count++ < 11) {
				echo "<LI><A HREF=\"{$v["site_url"]}\">{$v["title"]}</A></LI>\n";
			}
		}
		echo "</OL>\n";
	}
?>

	</form>
	</div>
	<?php
}

function backlinkcloud_title($display=true) {
	$str = $GLOBALS["__backlinkcloud"]["site_info"]["site_url"];
	if ($display) {
		echo $str;
		return $str;
	} else {
		return $str;
	}
}

function backlinkcloud_render($content) {
	$out = "<HR><A HREF=\"{$AGLOBALS["__backlinkcloud"]["site_info"]["site_url"]}\">{$GLOBALS["__backlinkcloud"]["site_info"]["title"]}<BR><img style=\"float:left;padding:5px;\" src=\"http://www.backlinkcloud.com/thumbs/{$GLOBALS["__backlinkcloud"]["site_info"]["site_domain"]}.jpg\"></A><BR><P style=\"clear:both\">{$GLOBALS["__backlinkcloud"]["site_info"]["metaTags"]["description"]["value"]}</P><HR><BR>";
	remove_filter( 'the_content', 'backlinkcloud_render' );
	echo $out.$content;
	return false;
}


// Set up plugin

add_action( 'init', 'backlinkcloud_handler' );
add_filter( 'redirect_canonical', 'backlinkcloud_redirect_canonical_filter', 10, 2 );
add_action( 'admin_menu', 'backlinkcloud_setup_admin' );
add_option( 'ignored_patterns', '' );
register_activation_hook( __FILE__, 'backlinkcloud_activate' );

?>
