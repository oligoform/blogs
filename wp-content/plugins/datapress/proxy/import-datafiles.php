<?php
require_once('web-tools.php');

function send_back($links, $errors, $warnings) {
    $response = array();
    if ($links != null) {
        $response["links"] = $links;
    }
    if ($errors != null) {
        $response["errors"] = $errors;
    
    }
    if ($warnings != null) {
        $response["warnings"] = $warnings;
    }
    echo(json_encode($response));
}

function is_google_spreadsheet($url, $type) {
    return ($type == "google-spreadsheet");
}

function is_json($type) {
    return ($type == "application/json");
}

function is_exhibit($type) {
    return ($type == "exhibit");
}

function scrape_google_spreadsheet($url, $type, $contents) {
    // Looking for
    // <link rel="alternate" type="application/rss+xml" title="Test - Google Docs" href="http://spreadsheets.google.com/feeds/list/pCz0APNvpf1wnpfX4oUZMWg/od6/public/basic">
    $pattern = "/<link[^>]*rel=\"alternate\"[^>]*type=\"application\/rss\+xml\"[^>]*>/";
    
	preg_match_all($pattern, $contents, $links, PREG_SET_ORDER);
	
	$linkdatas = array();
	$errors = array();
    $warnings = array();
    
	foreach ($links as $link) {
		$linkval = $link[0];

		$rel_pattern = "/rel=\"([^\"]*)\"/";
		$title_pattern = "/title=\"([^\"]*)\"/";
		$href_pattern = "/href=\"([^\"]*)\"/";
		$type_pattern = "/type=\"([^\"]*)\"/";
	
		$rel_matched = preg_match($rel_pattern, $linkval, $relmatch);		
		$href_matched = preg_match($href_pattern, $linkval, $hrefmatch);
		$title_matched = preg_match($title_pattern, $linkval, $titlematch);
		$type_matched = preg_match($type_pattern, $linkval, $typematch);
		
		if (($rel_matched && ($relmatch[1] == "alternate")) && ($type_matched && ($typematch[1] == "application/rss+xml")))  {
		    // This is the link to the rssfeed
			$href=$hrefmatch[1];
            $title=$titlematch[1];
			$type="google-spreadsheet";
			
         // Fix the href
         if (strpos($href, "?") === false) {
           $href .= "?";
         } else {
           $href .= "&";
         }
         $href .= "alt=json-in-script";

			
			$linkdata = array();
			$linkdata["href"] = $href;
			$linkdata["kind"] = $type;
			$linkdata["alt"] = $title;
			array_push($linkdatas, $linkdata);
		}	
	}
	if (count($linkdatas) == 0) {
	    array_push($warnings, "Datapress could not parse the provided URL as a Google Spreadsheet. Are you sure this is the publicly-shared sheet URL?");
	}
	send_back($linkdatas, $errors, $warnings);
    
}

function scrape_json($url, $type, $contents, $name) {
    $parsed = json_decode($contents);

	$linkdatas = array();
	$errors = array();
    $warnings = array();
    
    if ($parsed == null) {
        array_push($warnings, "The contents of this URL do not appear to be valid JSON. It may still work, but a strict JSON validator did not accept it.");
    }
    
    $linkdata = array();
	$linkdata["href"] = $url;
	$linkdata["kind"] = "application/json";
	$linkdata["alt"] = $name;
	array_push($linkdatas, $linkdata);
	
    send_back($linkdatas, $errors, $warnings);
}

function scrape_exhibit($url, $contents) {
  $pattern = "/<link[^>]*\/>/";
	preg_match_all($pattern, $contents, $links, PREG_SET_ORDER);
	
	$linkdatas = array();
	$errors = array();
    $warnings = array();
    
	foreach ($links as $link) {
		$linkval = $link[0];

		$rel_pattern = "/rel=\"([^\"]*)\"/";		
		$href_pattern = "/href=\"([^\"]*)\"/";
		$type_pattern = "/type=\"([^\"]*)\"/";
		$alt_pattern = "/alt=\"([^\"]*)\"/";
		$converter_pattern = "/ex:converter=\"([^\"]*)\"/";

		$rel_matched = preg_match($rel_pattern, $linkval, $relmatch);		
		$href_matched = preg_match($href_pattern, $linkval, $hrefmatch);
		$type_matched = preg_match($type_pattern, $linkval, $typematch);
		$alt_matched = preg_match($alt_pattern, $linkval, $altmatch);
		$converter_matched = preg_match($converter_pattern, $linkval, $convertermatch);
		
		if ($rel_matched && ($relmatch[1] == "exhibit/data")) {
			$href=$hrefmatch[1];
			$type=$typematch[1];
			
			// Special handling for Parroted links
    		$parrot_pattern = "/proxy\/parrot.php\?url=([^&]*)/";			
			$parrot_matched = preg_match($parrot_pattern, $href, $parrotmatch);	
			
			if ($parrot_matched) {
			    $href = urldecode($parrotmatch[1]);
			}
			
			// Fix the converter if it is a Google Spreadsheet
			if ($converter_matched && ($convertermatch[1] = "googleSpreadsheets")) {
				$type="google-spreadsheet";
			}
			
			// Fix the filename if it was a relative path
			if (strpos($href, '://')  === false) {
				$last_slash = strripos($url, '/');
				$prefix = substr($url, 0, $last_slash + 1);
				$href = $prefix . $href;
			}

			$alt = "";
			
			if ($alt_matched) {
				$alt=$altmatch[1];	
			}
			else {
				if ($type == "google-spreadsheet") {
					$alt = "UnnamedSpreadsheet";
				}
				else {
					$alt= substr($href, strripos($url, '/') - strlen($alt) + 1);	
				}
			}
			
			$linkdata = array();
			$linkdata["href"] = $href;
			$linkdata["kind"] = $type;
			$linkdata["alt"] = $alt;
			array_push($linkdatas, $linkdata);
		}
	}
	
	if (count($linkdatas) == 0) {
	    array_push($warnings, "Datapress could not locate any Exhibit data files referenced from the provided URL.");
	}
    send_back($linkdatas, $errors, $warnings);
}


/* ------------------------------------------------------------------
 *
 * Imports Data Files
 *
 *------------------------------------------------------------------*/

function import_data_files() {
    $url = urldecode($_GET['url']);
    $type = urldecode($_GET['type']);
    $name = urldecode($_GET['name']);

    /*
     * Catch any warnings generated into an error message.
     */
    ob_start();
    $contents = WebTools::do_get_request($url);
    $msg = ob_get_contents();
    ob_end_clean();
    
    if ($msg == null) {
        // OK to proceed!
        if (is_google_spreadsheet($url, $type)) {
            scrape_google_spreadsheet($url, $type, $contents);
        }
        else if (is_json($type)) {
            scrape_json($url, $type, $contents, $name);
        }
        else if (is_exhibit($type)) {
            scrape_exhibit($url, $contents);
        }
        else {
            $links = null;
            $errors = array("Datapress could not determine the type of data file you are trying to add.");
            $warnings = null;
            send_back($links, $errors, $warnings);
        }
    }
    else {
        // There was an error!
        $links = null;
        $errors = array("Datapress was not able to read the contents of the URL you linked to. Please check it and try again.");
        $warnings = null;
        send_back($links, $errors, $warnings);
    }
    die();
}
?>