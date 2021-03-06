<?php

/**
 * This is the default config file. Copy it to ".htconfig.php" and make your
 * local changes there.
 */

//MySQL host.
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root';
$db_data = 'friendica_dir';

// Choose a legal default timezone. If you are unsure, use "America/Los_Angeles".
// It can be changed later and only applies to timestamps for anonymous viewers.
$default_timezone = 'Europe/Amsterdam';

// What is your site name?
$a->config['sitename'] = "EXPERIMENTAL Friendica public directory";

//Statistic display settings.
$a->config['stats'] = array(

  //For site health, the max age for which to display data.
  'maxDataAge' => 3600*24*30*4 //120 days = ~4 months

);

//Settings related to the syncing feature.
$a->config['syncing'] = array(

  //Pulling may be quite intensive at first when it has to do a full sync and your directory is empty.
  //This timeout should be shorter than your cronjob interval. Preferably with a little breathing room.
  'timeout' => 3*60, //3 minutes

  //Push new submits to the `sync-target` entries?
  'enable_pushing' => true,

  //Maximum amount of items per batch per target to push to other sync-targets.
  //For example: 3 targets x20 items = 60 requests.
  'max_push_items' => 10,

  //Pull updates from the `sync-target` entries?
  'enable_pulling' => true,

  //This is your normal amount of threads for pulling.
  //With regular intervals, there's no need to give this a high value.
  //But when your server is brand new, you may want to keep this high for the first day or two.
  'pulling_threads' => 25,

  //How many items should we crawl per sync?
  'max_pull_items' => 250

);

//Things related to site-health monitoring.
$a->config['site-health'] = array(

  //Wait for at least ... before probing a site again.
  //The longer this value, the more "stable" site-healths will be over time.
  //Note: If a bad (negative) health site submits something, a probe will be performed regardless.
  'min_probe_delay' => 24*3600, // 1 day

  //Probes get a simple /friendica/json file from the server.
  //Feel free to set this timeout to a very tight value.
  'probe_timeout' => 5, // seconds

  //Imports should be fast. Feel free to prioritize healthy sites.
  'skip_import_threshold' => -20

);

//Things related to the maintenance cronjob.
$a->config['maintenance'] = array(

  //This is to prevent I/O blocking. Will cost you some RAM overhead though.
  //A good server should handle much more than this default, so you can tweak this.
  'threads' => 10,

  //Limit the amount of scrapes per execution of the maintainer.
  //This will depend a lot on the frequency with which you call the maintainer.
  //If you have 10 threads and 80 max_scrapes, that means each thread will handle 8 scrapes.
  'max_scrapes' => 80,

  //Wait for at least ... before scraping a profile again.
  'min_scrape_delay' => 3*24*3600, // 3 days

  //At which health value should we start removing profiles?
  'remove_profile_health_threshold' => -60

);