<?php
require_once 'datetime.php';
require_once 'site-health.php';
require_once 'Scrape.php';
require_once 'Photo.php';

function run_submit($url)
{
	global $a;

	if (!strlen($url)) {
		return false;
	}

	logger('Updating: ' . $url);

	//First run a notice script for the site it is hosted on.
	$site_health = notice_site($url, true);

	$submit_start = microtime(true);

	$nurl = str_replace(array('https:', '//www.'), array('http:', '//'), $url);

	$profile_exists = false;

	$r = q("SELECT * FROM `profile` WHERE ( `homepage` = '%s' OR `nurl` = '%s' )",
		dbesc($url),
		dbesc($nurl)
	);

	$profile_id = null;

	if (count($r)) {
		$profile_exists = true;
		$profile_id = $r[0]['id'];

		$r = q("UPDATE `profile` SET
			`available` = 0,
			`updated` = '%s'
			WHERE `id` = %d LIMIT 1", dbesc(datetime_convert()), intval($profile_id)
		);
		$r = q("DELETE FROM `tag` WHERE `nurl` = '%s'",
			dbesc($r[0]['nurl'])
		);
	}

	//Remove duplicates.
	if (count($r) > 1) {
		for ($i = 1; $i < count($r); $i++) {
			logger('Removed duplicate profile ' . intval($r[$i]['id']));
			q("DELETE FROM `photo` WHERE `profile-id` = %d LIMIT 1",
				intval($r[$i]['id'])
			);
			q("DELETE FROM `profile` WHERE `id` = %d LIMIT 1",
				intval($r[$i]['id'])
			);
		}
	}

	//Skip the scrape? :D
	$noscrape = $site_health && $site_health['no_scrape_url'];

	if ($noscrape) {
		//Find out who to look up.
		$which = str_replace($site_health['base_url'], '', $url);
		$noscrape = preg_match('~/profile/([^/]+)~', $which, $matches) === 1;

		//If that did not fail...
		if ($noscrape) {
			$params = noscrape_dfrn($site_health['no_scrape_url'] . '/' . $matches[1]);
			$noscrape = !!$params; //If the result was false, do a scrape after all.
		}
	}

	if (!$noscrape) {
		$params = scrape_dfrn($url);
	}

	// Empty result is due to an offline site.
	if (!count($params) > 1) {
		//For large sites this could lower the health too quickly, so don't track health.
		//But for sites that are already in bad status. Do a cleanup now.
		if ($profile_exists && $site_health['health_score'] < $a->config['maintenance']['remove_profile_health_threshold']) {
			logger('Nuked bad health record.');
			nuke_record($url);
		}

		return false;
	} elseif (x($params, 'explicit-hide') && $profile_exists) {
		// We don't care about valid dfrn if the user indicates to be hidden.
		logger('User opted out of the directory.');
		nuke_record($url);
		return true; //This is a good update.
	}

	if ((x($params, 'hide')) || (!(x($params, 'fn')) && (x($params, 'photo')))) {
		if ($profile_exists) {
			logger('Profile inferred to be opted out of the directory.');
			nuke_record($url);
		}
		return true; //This is a good update.
	}

	// This is most likely a problem with the site configuration. Ignore.
	if (validate_dfrn($params)) {
		logger('Site is unavailable');
		return false;
	}

	$photo = $params['photo'];

	dbesc_array($params);

	if (x($params, 'comm')) {
		$params['comm'] = intval($params['comm']);
	}

	if ($profile_exists) {
		$r = q("UPDATE `profile` SET
			`name` = '%s',
			`pdesc` = '%s',
			`locality` = '%s',
			`region` = '%s',
			`postal-code` = '%s',
			`country-name` = '%s',
			`homepage` = '%s',
			`nurl` = '%s',
			`comm` = %d,
			`tags` = '%s',
			`available` = 1,
			`updated` = '%s'
			WHERE `id` = %d LIMIT 1",
			$params['fn'],
			$params['pdesc'],
			$params['locality'],
			$params['region'],
			$params['postal-code'],
			$params['country-name'],
			dbesc($url),
			dbesc($nurl),
			intval($params['comm']),
			$params['tags'],
			dbesc(datetime_convert()),
			intval($profile_id)
		);
		logger('Update returns: ' . $r);
	} else {
		$r = q("INSERT INTO `profile` ( `name`, `pdesc`, `locality`, `region`, `postal-code`, `country-name`, `homepage`, `nurl`, `comm`, `tags`, `created`, `updated` )
			VALUES ( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s' )",
			$params['fn'],
			x($params, 'pdesc') ? $params['pdesc'] : '',
			x($params, 'locality') ? $params['locality'] : '',
			x($params, 'region') ? $params['region'] : '',
			x($params, 'postal-code') ? $params['postal-code'] : '',
			x($params, 'country-name') ? $params['country-name'] : '',
			dbesc($url),
			dbesc($nurl),
			intval($params['comm']),
			x($params, 'tags') ? $params['tags'] : '',
			dbesc(datetime_convert()),
			dbesc(datetime_convert())
		);
		logger('Insert returns: ' . $r);

		$r = q("SELECT `id` FROM `profile` WHERE ( `homepage` = '%s' or `nurl` = '%s' ) ORDER BY `id` ASC",
			dbesc($url),
			dbesc($nurl)
		);

		if (count($r)) {
			$profile_id = $r[count($r) - 1]['id'];
		}

		if (count($r) > 1) {
			q("DELETE FROM `photo` WHERE `profile-id` = %d LIMIT 1",
				intval($r[0]['id'])
			);
			q("DELETE FROM `profile` WHERE `id` = %d LIMIT 1",
				intval($r[0]['id'])
			);
		}
	}

	if ($params['tags']) {
		$arr = explode(' ', $params['tags']);
		foreach ($arr as $t) {
			$t = strip_tags(trim($t));
			$t = substr($t, 0, 254);

			if (strlen($t)) {
				$r = q("INSERT INTO `tag` (`term`, `nurl`) VALUES ('%s', '%s') ",
					dbesc($t),
					dbesc($nurl)
				);
			}
		}
	}

	$submit_photo_start = microtime(true);

	$photo_failure = false;

	$status = false;

	if ($profile_id) {
		$img_str = fetch_url($photo, true);
		$img = new Photo($img_str);
		if ($img) {
			$img->scaleImageSquare(80);
			$r = $img->store($profile_id);
		}
		$r = q("UPDATE `profile` SET `photo` = '%s' WHERE `id` = %d LIMIT 1", dbesc($a->get_baseurl() . '/photo/' . $profile_id . '.jpg'),
			intval($profile_id)
		);
		$status = true;
	} else {
		nuke_record($url);
		return false;
	}

	$submit_end = microtime(true);
	$photo_time = round(($submit_end - $submit_photo_start) * 1000);
	$time = round(($submit_end - $submit_start) * 1000);

	//Record the scrape speed in a scrapes table.
	if ($site_health && $status) {
		q(
			"INSERT INTO `site-scrape` (`site_health_id`, `dt_performed`, `request_time`, `scrape_time`, `photo_time`, `total_time`)" .
			"VALUES (%u, NOW(), %u, %u, %u, %u)",
			$site_health['id'],
			$params['_timings']['fetch'],
			$params['_timings']['scrape'],
			$photo_time,
			$time
		);
	}

	return $status;
}

function nuke_record($url)
{
	$nurl = str_replace(array('https:', '//www.'), array('http:', '//'), $url);

	$r = q("SELECT `id` FROM `profile` WHERE ( `homepage` = '%s' OR `nurl` = '%s' ) ",
		dbesc($url),
		dbesc($nurl)
	);

	if (count($r)) {
		foreach ($r as $rr) {
			q("DELETE FROM `photo` WHERE `profile-id` = %d LIMIT 1",
				intval($rr['id'])
			);
			q("DELETE FROM `profile` WHERE `id` = %d LIMIT 1",
				intval($rr['id'])
			);
		}
	}
	return;
}
