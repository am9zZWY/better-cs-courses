<?php

function clean_string($string): string
{
    $string = str_replace(PHP_EOL, '', $string);
    $string = trim($string);

    return $string;
}

function fetch_and_parse_cs_courses(): void
{
    // Fetch the HTML from the URL
    $html = file_get_contents('https://courses.cs.uni-tuebingen.de/main/zuordnungstabelle-master');

    // Put the HTML into a DomXPath
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Find the table
    $table = $xpath->query('//table[@id="kreuzchenliste"]');

    // Find the modules
    $modules = $xpath->query('//tr[contains(@class, "moduleTR")]', $table[0]);
    $modules_length = $modules->length;

    // Find categories
    $headers_and_area_descriptors = $xpath->query('//tr[@id="headers_and_area_descriptors"]', $table[0]);
    $categories = $xpath->query('td[contains(@class, "data-column")]/*/span/text()', $headers_and_area_descriptors[0]);

    // Build JSON Array
    $json = array();
    for ($i = 0; $i < $modules_length; $i++) {
        $module = new stdClass();

        // Get module name
        $module_name = $xpath->query('td[contains(@class, "moduleTitle")]/text()', $modules[$i])[0]->nodeValue;
        $module->name = clean_string($module_name);

        // Get module lecturer
        $moduleLecturer = clean_string($xpath->query('td[contains(@class, "moduleLecturer")]/text()', $modules[$i])[0]->nodeValue);
        $module->lecturer = $moduleLecturer;

        // Get module credits
        $moduleCredits = clean_string($xpath->query('td[contains(@class, "moduleCredits")]/text()', $modules[$i])[0]->nodeValue);
        $module->credits = $moduleCredits;

        // Get more module information
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://courses.cs.uni-tuebingen.de/main/fetchModuleInfo?id=' . $i,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'x-requested-with: XMLHttpRequest'
            ),
        ));
        $result = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($result !== false && $http_code === 200) {
            // Parse JSON
            $result = json_decode($result);
            $data = $result->data;
            $module->description = $data->module_description_de;
            $module->identifier = $data->module_descriptor;
        }

        // Get module categories
        $crosses = $xpath->query('td[contains(@class, "kreuzchen")]', $modules[$i]);
        $module->categories = array();

        // Go through all categories
        for ($j = 0; $j < $categories->length; $j++) {
            // Check if cross has child that is an icon
            $cross = $crosses[$j];
            $cross_icon = $xpath->query('i', $cross)[0];
            if ($cross_icon) {
                $module->categories[] = clean_string($categories[$j]->nodeValue);
            }
        }


        $json[] = $module;
    }

    // Print JSON
    echo json_encode($json);
}