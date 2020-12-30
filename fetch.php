<?php
ini_set('date.timezone', 'Asia/Jakarta');

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
$string1 = getRequest("http://api.aladhan.com/v1/gToHCalendar/".date('m/Y'));
$string2 = getRequest("http://api.aladhan.com/v1/gToHCalendar/".date('m/Y', strtotime('first day of +1 month')));


$islamic_calendar = json_decode($string1, true);
$islamic_calendar2 = json_decode($string2, true);

$calendar_data_merged = array_merge($islamic_calendar['data'],$islamic_calendar2['data']);
$islamic_calendar['data'] = $calendar_data_merged;

// print_r(json_encode($islamic_calendar));
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
    $currentDate = date("d-m-Y");
    $fasting = isset($fastingCalendar[removeYearFromDate($value['hijri']['date'])]) ? $fastingCalendar[removeYearFromDate($value['hijri']['date'])] : null;

    if (empty($fasting)) {

        switch ($value['gregorian']['weekday']['en']) {
            case 'Monday':
                $fasting = ['event_name' => "Puasa Senin & Kamis", 'faidah' => "Dihadapkannya berbagai amalan pada Allah di hari Senin dan Kamis.", 'reference' => 'lorem ipsum'];
                break;
            case 'Thursday':
                $fasting = ['event_name' => "Puasa Senin & Kamis", 'faidah' => "Dihadapkannya berbagai amalan pada Allah di hari Senin dan Kamis.", 'reference' => 'lorem ipsum'];
                break;

            default:
                $fasting = null;
                break;
        }
    }

    $date1 = new DateTime('00:00:00');
    $date2 = new DateTime($value['gregorian']['date']);

    $interval = date_diff($date1, $date2);
    // return print_r($interval->format("%d"));
    switch ($interval->format("%d")) {
        case 0:
            $value['gregorian']['dateTitle'] = "Hari ini";
            break;
        case 1:
            $value['gregorian']['dateTitle'] = "Besok";
            break;
        default:
            $value['gregorian']['dateTitle'] = $interval->format('%a Hari lagi');
            break;
    }
    $data = [
        'gregorian' => $value['gregorian'],
        'hijri' => $value['hijri'],
        'fasting' => $fasting
    ];


    
    // remove non fasting date & expired date
    
    if (!empty($fasting) && strtotime($value['gregorian']['date']) >= strtotime($currentDate)) {

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

