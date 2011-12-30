<?php

require_once 'S3.php';

$contents = require_once 'contents.php';

$mimetype = (@$_GET['type'] === 'html' ? 'text/html' : 'application/json');

/*
$s3 = new S3('accessKey', 'secretKey');
$contents = $s3->getBucket('packages.couchbase.com', 'releases', null, null, '|');
*/

function collectFor($product_string) {
	global $contents;
	
	$output = array('name' => $product_string,
	            'releases' => array());
	
	$last_version = 0;
	foreach ($contents as $file) {
		$url = $file['name'];
	  list(, $version, $filename) = explode('/', $file['name']);

	  if ($filename === "") continue;
	  else if (!is_numeric($version[0])) continue;
	  else if (strpos($version, '-') !== false) continue;
	  else if ($filename === 'index.html') continue;
	  else if (substr($filename, 0, strlen($product_string)) !== $product_string
	    || substr($filename, -3, 3) === 'xml'
	    || substr($filename, -3, 3) === 'txt'
	    || substr($filename, 0, 10) === 'northscale'
	    || substr($filename, 0, 15) === 'CouchbaseServer') continue;

	  if (count($output['releases']) > 0) {
		  $last_entry =& $output['releases'][count($output['releases'])-1];
		  $last_downloads_entry =& $last_entry['downloads'][count($last_entry['downloads'])-1];
	  }
	  if (substr($filename, -3, 3) === 'md5') {
	    $last_downloads_entry['md5'] = $file['name'];
	    continue;
	  }
	  
	  // source only package...no edition
	  if (preg_match("/([A-Za-z\-]*)_src-([0-9\.]*)[\.|_](.*)/", $filename, $matches) > 0) {
	  	list(, $product, $version, $postfix) = $matches;
	  } else {
	    preg_match("/([A-Za-z\-]*)[-](enterprise|community)([_]?(win2008)?_(x86)[_]?(64)?)?[_]([0-9\.]*)[\.|_](.*)/",
		    $filename, $matches);
	
	    list(, $product, $edition, , $type, $arch, $bits, $version, $postfix) = $matches;
	
	    if ($bits === '64') $arch .= '/64';
	
		  if ($type === 'win2008')     $type = 'exe';
		  else if ($postfix === 'rpm') $type = 'rpm';
		  else if ($postfix === 'deb') $type = 'deb';
		  else                         $type = 'source';
	  }
	
	  $created = date('Y-m-d', $file['time']);
	
	  if ($last_version === /*this*/ $version) {
	  	// append to the previous entry
	  	$last_entry['downloads'][] = array_filter(compact('url', 'edition', 'type', 'arch'));
	  } else {
	  	// create a new entry
		  $downloads = array(array_filter(compact('url', 'edition', 'type', 'arch')));
		  $output['releases'][] = compact('version', 'created', 'downloads');
	  }
	
	  $last_version = $version;
	  
	  unset($version, $product, $edition, $type, $arch, $bits, $version, $postfix, $downloads);
	}

	return $output;
}

header('Content-Type: ' . $mimetype);

$membase_releases = collectFor('membase-server');

if ($mimetype === 'application/json') {
	print_r(json_encode($membase_releases));
} else {
	require_once 'Mustache.php';
	$m = new Mustache();
	echo $m->render(file_get_contents('downloads.html'), $membase_releases);
}