<?php

/**
 * 异步curl
 *
 * @param [type] $urls
 * @param [type] $callback
 * @param [type] $custom_options
 * @return void
 */
function rolling_curl($urls, $callback, $custom_options = null)
{
    // 确保滚动窗口不大于网址数量
    $rolling_window = 5;
    $rolling_window = (sizeof($urls) < $rolling_window) ? sizeof($urls) : $rolling_window;

    $master   = curl_multi_init();
    $curl_arr = array();

    // add additional curl options here
    $std_options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5
    );
    $options = ($custom_options) ? ($std_options + $custom_options) : $std_options;

    // start the first batch of requests
    for ($i = 0; $i < $rolling_window; $i++) {
        $ch                   = curl_init();
        $options[CURLOPT_URL] = $urls[$i];
        curl_setopt_array($ch, $options);
        curl_multi_add_handle($master, $ch);
    }

    do {
        while (($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
        if ($execrun != CURLM_OK) {
            break;
        }

        // a request was just completed -- find out which one
        while ($done = curl_multi_info_read($master)) {
            $info = curl_getinfo($done['handle']);
            var_dump($info);

            if ($info['http_code'] == 200) {
                $output = curl_multi_getcontent($done['handle']);

                // request successful.  process output using the callback function.
                $callback($output);

                // start a new request (it's important to do this before removing the old one)
                // 当$i等于$urls数组大小时不用再增加了
                if ($i < sizeof($urls)) {
                    $ch                   = curl_init();
                    $options[CURLOPT_URL] = $urls[$i++]; // increment i
                    curl_setopt_array($ch, $options);
                    curl_multi_add_handle($master, $ch);
                }
                // remove the curl handle that just completed
                curl_multi_remove_handle($master, $done['handle']);
            } else {
                // request failed.  add error handling.
            }
        }
    } while ($running);

    curl_multi_close($master);
    return true;
}


$urls = [
    'http://localhost',
    'http://localhost/index2.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index3.php',
    // 'http://localhost/index4.php',
];

rolling_curl($urls, function($res){
    var_dump($res);
});