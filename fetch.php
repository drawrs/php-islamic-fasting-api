<?php
/**
* Curl send get request, support HTTPS protocol
* @param string $url The request url
* @param string $refer The request refer
* @param int $timeout The timeout seconds
* @return mixed
*/

function getRequest($url, $refer = "", $timeout = 10)
{
    $ssl = stripos($url,'https://') === 0 ? true : false;
    $curlObj = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_AUTOREFERER => 1,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
        CURLOPT_HTTPHEADER => ['Expect:'],
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    if ($refer) {
        $options[CURLOPT_REFERER] = $refer;
    }
    if ($ssl) {
        //support https
        $options[CURLOPT_SSL_VERIFYHOST] = false;
        $options[CURLOPT_SSL_VERIFYPEER] = false;
    }
    curl_setopt_array($curlObj, $options);
    $returnData = curl_exec($curlObj);
    if (curl_errno($curlObj)) {
        //error message
        $returnData = curl_error($curlObj);
    }
    curl_close($curlObj);
    return $returnData;
}
// php function to convert csv to json format
function csvToArray($fname) {
    // open csv file
    if (!($fp = fopen($fname, 'r'))) {
        die("Can't open file...");
    }
    
    //read csv headers
    $key = fgetcsv($fp,"1024",";");
    
    // parse csv rows into array
    $json = array();
        while ($row = fgetcsv($fp,"1024",";")) {
        $json[] = array_combine($key, $row);
    }
    
    // release file handle
    fclose($fp);
    
    // encode array to json
    return $json;
    // return json_encode($json);
}
function removeYearFromDate($date) {
    return substr($date, 0, -5);
}

$dates = csvToArray('jadwal.csv');
// $string = file_get_contents("islamic_date_2020.json");
$string = getRequest("http://api.aladhan.com/v1/gToHCalendar/".date('m/Y'));
$islamic_calendar = json_decode($string, true);
//13-05
// print_r(json_encode($dates));
$fastingCalendar = [];
foreach ($dates as $key => $value) {
    $startDate = substr($value['hijri_date_start'], 0, -3);
    $endDate = substr($value['hijri_date_end'], 0, -3);
    for ($i=$startDate; $i <= $endDate ; $i++) { 
        $newDate = $i. substr($value['hijri_date_start'], 2, 3);
        $fastingCalendar[$newDate] = [
                                        "event_name" => $value['event'],
                                        "faidah" => $value['faidah'],
                                        "reference" => $value['reference']
                                    ];
    }
}

foreach ($islamic_calendar['data'] as $key => $value) {
    $fasting = $fastingCalendar[removeYearFromDate($value['hijri']['date'])];
    $data = [
        'gregorian' => $value['gregorian'],
        'hijri' => $value['hijri'],
        'fasting' => $fasting
    ];
    // remove non fasting date
    if (!empty($fasting)) {
        // unset($islamic_calendar['data'][$key]);
        // Create new group
        $islamic_calendar['result'][] = $data;
    }


}

// Remove unnecessery part
unset($islamic_calendar['data']);

// Sort ascending based on date
usort($islamic_calendar['result'], function ($item1, $item2) {
        return strtotime($item1['gregorian']['date']) <=> strtotime($item2['gregorian']['date']);
});
// Print json
echo json_encode($islamic_calendar);

