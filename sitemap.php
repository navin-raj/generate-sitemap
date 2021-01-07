<?php
echo "Enter domain : ";
$domain = trim(fgets(STDIN));
$domain = remove_prefix($domain);

$list = travel_path([], $domain);
echo "\n";
print_r(format_list($list, $domain));
echo "\n";

/**
 * Remove www prefix from domain url
 */
function remove_prefix($domain)
{
    if (substr($domain, 0, 4) !== "http") {
        die("domain should start with http or https \n");
    }

    $prefix = "http";
    if (substr($domain, 0, 5) === "https") {
        $prefix = "https";
    }
    $domain = str_replace(($prefix . "://www."), ($prefix . "://"), $domain);

    if (substr($domain, -1) === "/") {
        $domain = substr($domain, 0, strlen($domain) - 1);
    }

    return $domain;
}

/**
 * Remove http and https protocol from domain url
 */
function remove_http($domain)
{
    $prefix = "http";
    if (substr($domain, 0, 5) === "https") {
        $prefix = "https";
    }
    $domain = str_replace(($prefix . "://"), "", $domain);
    return $domain;
}

/**
 * Get raw html content
 */
function get_domain_content($domain)
{
    if (strlen($domain) <= 0) {
        return "";
    }

    //  check weather the domain returns ok status
    if (($code = getHttpResponseCode_using_curl($domain, false)) != 200) {
        echo $domain . " unable to access due to " . $code;
        return "";
    }

    $opts = array(
        'http' => array(
            'method' => "GET",
            'ignore_errors' => true,
        ),
    );
    $context = stream_context_create($opts);
    if (false === ($response = file_get_contents($domain, false, $context))) {
        return "";
    }
    return $response;
}

/**
 * extract all urls from raw html content
 */
function get_url_list($html, $domain)
{
    //  exclude file types dosn't required in sitemap list
    $exclude = ["css", "js", "ico", "png", "jpg", "jpeg", "gif", "svg", "json", "xml"];
    $url_list = [];

    $i = 0;
    //  loop each element to find and extract url
    while ($i <= strlen($html)) {
        $url = "";
        if (isset($html[$i])) {
            if ($html[$i] === "h" && $html[$i + 1] === "r" && $html[$i + 2] === "e" && $html[$i + 3] === "f" && $html[$i + 4] === "=" && (($html[$i + 6] . $html[$i + 7]) !== "//")) {
                $j = 5;
                if ($html[$i + 5] === "'" || $html[$i + 5] === "\"" || $html[$i + 5] === "/") {
                    $j = 6;
                    if (($html[$i + 5] . $html[$i + 6]) === "//") {
                        $j = 5;
                    }
                }

                while ($html[$i + $j] !== "\"" && $html[$i + $j] !== "'" && $html[$i + $j] !== ">" && $html[$i + $j] !== " ") {
                    $url .= $html[$i + $j];
                    $j++;
                }
                if (strlen($url) > 0) {
                    $domain = remove_http($domain);
                    $url = str_replace(("http://" . $domain), "", $url);
                    $url = str_replace(("https://" . $domain), "", $url);
                    $url = str_replace(("//" . $domain), "", $url);

                    if (strlen($url) > 0) {
                        $url = explode("?", $url)[0];
                        if (in_array($url, $url_list) || strpos($url, ":") || (strpos($url, "#") === 0)) {
                            $i++;
                            continue;
                        }
                        $url = explode("#", $url)[0];

                        $explode = explode(".", $url);
                        if (count($explode) > 1) {
                            if (in_array($explode[count($explode) - 1], $exclude)) {
                                $i++;
                                continue;
                            }
                        }

                        if (strpos($url, "/") !== 0) {
                            $url = "/" . $url;
                        }
                        array_push($url_list, $url);
                    }
                }
            }
        }
        $i++;
    }
    return $url_list;
}

function getHttpResponseCode_using_curl($url, $followredirects = true)
{
    if (!$url || !is_string($url)) {
        return false;
    }
    $ch = @curl_init($url);
    if ($ch === false) {
        return false;
    }
    @curl_setopt($ch, CURLOPT_HEADER, true);
    @curl_setopt($ch, CURLOPT_NOBODY, true);
    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($followredirects) {
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        @curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    } else {
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    }

    $response = @curl_exec($ch);

    if (@curl_errno($ch)) {
        @curl_close($ch);
        return false;
    }
    $code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);
    return $code;
}

function travel_path($url_list = [], $domain, $url_position = -1)
{
    $path = $domain;
    if ($url_position >= 0) {
        if (isset($url_list[$url_position])) {
            $path = $domain . $url_list[$url_position];
        }
    }

    $html = get_domain_content($path);
    if (strlen($html) > 0) {
        $list = get_url_list($html, $domain);
        if (count($list) > 0) {
            foreach ($list as $url) {
                if (in_array($url, $url_list)) {
                    continue;
                }
                array_push($url_list, $url);
            }
        }
    }

    if ($url_position >= (count($url_list) - 1)) {
        return $url_list;
    }

    echo "*";
    return travel_path($url_list, $domain, ($url_position + 1));
}

function format_list($list, $domain)
{
    $sitemap = [];
    foreach ($list as $url) {
        array_push($sitemap, ($domain . $url));
    }
    return $sitemap;
}
