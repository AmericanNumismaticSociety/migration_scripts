<?php 
/*****
 * Author: Ethan Gruber
 * Date: February 2025
 * Function: Convert CH spreadsheets into NUDS/XML for coinhoards.org
 *****/

$data = generate_json('Asia_Minor_Hoards.csv');
$findspots = generate_json('Findspots_CH.csv');
$counts_csv = generate_json('Hoard_Total_Counts_CH.csv');
$contents_csv = generate_json('Hoard_contents_CH.csv');
$refs_csv = generate_json('Disposition,_Refs,_and_Notes_CH.csv');

//associative array of Nomisma IDs and preferred labels
$nomisma = array();

//generate an array of duplicate hoards to exclude
$duplicates = find_duplicates($data);

//process the hoard list, minus ones with duplicate entries, into a data object
$hoards = process_hoards_csv($data, $duplicates);

//var_dump($hoards);

//output CSV concordance of CHANGE hoards and IGCH
write_concordance($hoards);

$count = 1;
foreach ($hoards as $recordId=>$hoard) {    
    if ($count >= 1) {
        
        generate_nuds($recordId, $hoard, null);
        
        //generate abbreviated CH NUDS documents
        if (array_key_exists('replaces', $hoard)) {
            foreach($hoard['replaces'] as $replacesId) {
                generate_nuds($recordId, $hoard, $replacesId);
            }
        }
    }
    $count++;
}

/*****
 * FUNCTIONS *
*****/
function write_concordance($hoards) {
    echo "Writing IGCH concordance.\n";
    
    $writer = new XMLWriter();
    $writer->openURI("concordance.xml");
    //$writer->openURI('php://output');
    $writer->setIndent(true);
    $writer->setIndentString("    ");
    $writer->startDocument('1.0','UTF-8');
    
    $writer->startElement('concordance');    
    foreach ($hoards as $recordId=>$hoard){
        $writer->startElement('hoard');
            $writer->writeAttribute('change', $recordId);
            
            if (array_key_exists('igch', $hoard)){
                foreach($hoard['igch'] as $igch) {
                    $writer->writeElement('igch', $igch);
                }
            }           
        $writer->endElement();
    }
    $writer->endElement();
    //end document
    return $writer->flush();
}

function generate_nuds($recordId, $hoard, $replacesId) {
    
    $filename = ($replacesId == null) ? $recordId : 'ch.' . $replacesId;
    
    echo "Writing {$filename}\n";
    
    $writer = new XMLWriter();
    $writer->openURI("nuds/{$filename}.xml");
    //$writer->openURI('php://output');
    $writer->setIndent(true);
    $writer->setIndentString("    ");
    $writer->startDocument('1.0','UTF-8');
    
    //begin XML document
    $writer->startElement('nudsHoard');
        $writer->writeAttribute('xmlns', 'http://nomisma.org/nudsHoard');
        $writer->writeAttribute('xmlns:xlink', "http://www.w3.org/1999/xlink");
        $writer->writeAttribute('xmlns:nuds', "http://nomisma.org/nuds");
        $writer->writeAttribute('xmlns:mods', "http://www.loc.gov/mods/v3");
        $writer->writeAttribute('xmlns:gml', "http://www.opengis.net/gml");
        $writer->writeAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");	

        //begin control
        $writer->startElement('control');
        
            $writer->writeElement('recordId', ($replacesId == null) ? $recordId : 'ch.' . $replacesId);
            
            //other URIs
            if ($replacesId == null) {
                foreach ($hoard['replaces'] as $replacement) {
                    $writer->startElement('otherRecordId');
                        $writer->writeAttribute('semantic', 'skos:exactMatch');
                        $writer->text('http://coinhoards.org/id/ch.' . $replacement);
                    $writer->endElement();
                    $writer->startElement('otherRecordId');
                        $writer->writeAttribute('semantic', 'dcterms:replaces');
                        $writer->text('ch.' . $replacement);
                    $writer->endElement();
                }
            } else {
                $writer->startElement('otherRecordId');
                    $writer->writeAttribute('semantic', 'skos:exactMatch');
                    $writer->text('http://coinhoards.org/id/' . $recordId);
                $writer->endElement();
                $writer->startElement('otherRecordId');
                    $writer->writeAttribute('semantic', 'dcterms:isReplacedBy');
                    $writer->text($recordId);
                $writer->endElement();
            }
            
            if ($replacesId == null && array_key_exists('igch', $hoard)) {
                foreach ($hoard['igch'] as $replacement) {
                    $writer->startElement('otherRecordId');
                        $writer->writeAttribute('semantic', 'skos:exactMatch');
                        $writer->text('http://coinhoards.org/id/' . $replacement);
                    $writer->endElement();
                    $writer->startElement('otherRecordId');
                        $writer->writeAttribute('semantic', 'dcterms:replaces');
                        $writer->text($replacement);
                    $writer->endElement();
                }                    
            }
            
            $writer->writeElement('publicationStatus', ($replacesId == null) ? 'approved' : 'deprecatedType');
            $writer->writeElement('maintenanceStatus', ($replacesId == null) ? 'derived' : 'cancelledReplaced');
            $writer->startElement('maintenanceAgency');
                $writer->writeElement('agencyName', 'American Numismatic Society');
            $writer->endElement();
            $writer->startElement('maintenanceHistory');
                $writer->startElement('maintenanceEvent');
                    $writer->writeElement('eventType', 'derived');
                    $writer->startElement('eventDateTime');
                        $writer->writeAttribute('standardDateTime', date(DATE_W3C));
                        $writer->text(date(DATE_RFC2822));
                    $writer->endElement();
                    $writer->writeElement('agentType', 'human');
                    $writer->writeElement('agent', 'Ethan Gruber');
                    $writer->writeElement('eventDescription', 'NUDS-Hoard records generated by Ethan Gruber from spreadsheets created by Leah Lazar');
                $writer->endElement();
            $writer->endElement();
            $writer->startElement('rightsStmt');
                $writer->writeElement('copyrightHolder', 'American Numismatic Society');
                $writer->writeElement('license', 'http://opendatacommons.org/licenses/odbl/');
            $writer->endElement();
            
            $writer->startElement('semanticDeclaration');
                $writer->writeElement('prefix', 'skos');
                $writer->writeElement('namespace', 'http://www.w3.org/2004/02/skos/core#');
            $writer->endElement();
            $writer->startElement('semanticDeclaration');
                $writer->writeElement('prefix', 'dcterms');
                $writer->writeElement('namespace', 'http://purl.org/dc/terms/');
            $writer->endElement();
            
        //end control
        $writer->endElement();
        
        if ($replacesId == null) {
            $title = ltrim(str_replace('change.', 'CHANGE ', $recordId), '0');
        } else {
            $title = 'CH ' . $replacesId;
        }
        
        $writer->startElement('descMeta');
            $writer->startElement('title');
                $writer->writeAttribute('xml:lang', 'en');
                $writer->text($title);
            $writer->endElement();
            
            //period as subject
            if (array_key_exists('period', $hoard)){
                $writer->startElement('subjectSet');
                    $writer->startElement('subject');
                        $writer->writeAttribute('localType', 'period');
                        $writer->writeAttribute('xlink:type', 'simple');
                        $writer->writeAttribute('xlink:href', $hoard['period']);
                        
                        switch($hoard['period']){
                            case 'http://nomisma.org/id/archaic_greece':
                                $label = 'Archaic Greece';
                                break;
                            case 'http://nomisma.org/id/classical_greece':
                                $label = 'Classical Greece';
                                break;
                            case 'http://nomisma.org/id/hellenistic_greece':
                                $label = 'Hellenistic Period';
                                break;
                        }
                        $writer->text($label);
                    $writer->endElement();
                $writer->endElement();
                
                unset($label);
            }
            
            //notes
            if (array_key_exists('note', $hoard)) {
                $writer->startElement('noteSet');
                foreach($hoard['note'] as $note) {
                    $writer->writeElement('note', $note);
                }
                $writer->endElement();
            }
            
            //hoardDesc
            $writer->startElement('hoardDesc');            
                if (array_key_exists('findspot', $hoard)) {
                    $writer->startElement('findspot');
                        $writer->startElement('description');
                            $writer->writeAttribute('xml:lang', 'en');
                            $writer->text($hoard['findspot']['name']);
                        $writer->endElement();
                        
                        if (array_key_exists('geonames', $hoard['findspot']) || array_key_exists('pleiades', $hoard['findspot'])) {
                            $writer->startElement('fallsWithin');
                            if (array_key_exists('coords', $hoard['findspot'])){
                                $writer->startElement('gml:location');
                                    $writer->startElement('gml:Point');
                                        $writer->writeElement('gml:coordinates', $hoard['findspot']['coords']);
                                    $writer->endElement();
                                $writer->endElement();
                            }
                            
                            if (array_key_exists('geonames', $hoard['findspot'])) {
                                $writer->startElement('geogname');
                                    $writer->writeAttribute('xlink:type', 'simple');
                                    $writer->writeAttribute('xlink:role', 'findspot');
                                    $writer->writeAttribute('xlink:href', $hoard['findspot']['geonames']);
                                    $writer->text($hoard['findspot']['geonames_label']);
                                $writer->endElement();    
                            }
                            if (array_key_exists('pleiades', $hoard['findspot'])) {
                                $writer->startElement('geogname');
                                    $writer->writeAttribute('xlink:type', 'simple');
                                    $writer->writeAttribute('xlink:role', 'ancient_place');
                                    $writer->writeAttribute('xlink:href', $hoard['findspot']['pleiades']);                                    
                                    $writer->text($hoard['findspot']['pleiades_label']);
                                $writer->endElement();
                            }
                            
                            //feature type
                            if (array_key_exists('type', $hoard['findspot'])){
                                $writer->startElement('type');
                                    $writer->writeAttribute('xlink:type', 'simple');
                                    //$writer->writeAttribute('xlink:href', $hoard['findspot']['type']['uri']);
                                    $writer->text($hoard['findspot']['type']['label']);
                                $writer->endElement();
                            }
                            
                            $writer->endElement();
                        }
                    
                    $writer->endElement();
                    
                     //deposit
                    if ($replacesId == null && array_key_exists('deposit', $hoard['findspot'])){                    
                        $writer->startElement('deposit');
                        if ($hoard['findspot']['deposit']['fromDate'] == $hoard['findspot']['deposit']['toDate']){
                                $writer->startElement('date');
                                    $writer->writeAttribute('standardDate', number_pad($hoard['findspot']['deposit']['fromDate'], 4));
                                $writer->text(get_date_textual($hoard['findspot']['deposit']['fromDate']));
                            $writer->endElement();
                        } else {
                            $writer->startElement('dateRange');
                                $writer->startElement('fromDate');
                                    $writer->writeAttribute('standardDate', number_pad($hoard['findspot']['deposit']['fromDate'], 4));
                                    $writer->text(get_date_textual($hoard['findspot']['deposit']['fromDate']));
                                $writer->endElement();
                                $writer->startElement('toDate');
                                    $writer->writeAttribute('standardDate', number_pad($hoard['findspot']['deposit']['toDate'], 4));
                                    $writer->text(get_date_textual($hoard['findspot']['deposit']['toDate']));
                                $writer->endElement();
                            $writer->endElement();
                        }
                        $writer->endElement();
                    }
                    
                } //end findspot
                
               
                //discovery
                if ($replacesId == null && array_key_exists('discovery', $hoard)){
                    $writer->startElement('discovery');
                    if (array_key_exists('date', $hoard['discovery'])){
                        $writer->startElement('date');
                            $writer->writeAttribute('standardDate', number_pad($hoard['discovery']['date'], 4));
                            if (array_key_exists('uncertain', $hoard['discovery'])){
                                $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                            }
                            $writer->text(get_date_textual($hoard['discovery']['date']));
                        
                        $writer->endElement();
                    } elseif (array_key_exists('notAfter', $hoard['discovery'])){
                        $writer->startElement('date');
                            $writer->writeAttribute('notAfter', number_pad($hoard['discovery']['notAfter'], 4));
                            if (array_key_exists('uncertain', $hoard['discovery'])){
                                $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                            }
                            $writer->text('before ' . get_date_textual($hoard['discovery']['notAfter']));
                        $writer->endElement();
                    } elseif (array_key_exists('fromDate', $hoard['discovery']) && array_key_exists('toDate', $hoard['discovery'])){
                        $writer->startElement('dateRange');
                            $writer->startElement('fromDate');
                                $writer->writeAttribute('standardDate', number_pad($hoard['discovery']['fromDate'], 4));
                                if (array_key_exists('uncertain', $hoard['discovery'])){
                                    $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                }
                                $writer->text(get_date_textual($hoard['discovery']['fromDate']));
                            $writer->endElement();
                            $writer->startElement('toDate');
                                $writer->writeAttribute('standardDate', number_pad($hoard['discovery']['toDate'], 4));
                                if (array_key_exists('uncertain', $hoard['discovery'])){
                                    $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                }
                                $writer->text(get_date_textual($hoard['discovery']['toDate']));
                            $writer->endElement();
                        $writer->endElement();
                    }
                    $writer->endElement();
                }
                
                //disposition                
                if ($replacesId == null && array_key_exists('disposition', $hoard)) {
                    $writer->startElement('disposition');
                        $writer->startElement('description');
                            $writer->writeAttribute('xml:lang', 'en');
                            $writer->text($hoard['disposition'][0]);
                        $writer->endElement();
                    $writer->endElement();
                }
                
            //end hoardDesc
            $writer->endElement();
            
            //contentsDesc
            if ($replacesId == null && (array_key_exists('contents', $hoard) || array_key_exists('counts', $hoard))) {
                $writer->startElement('contentsDesc');
                    $writer->startElement('contents');
                    //total count attributes
                    if (array_key_exists('counts', $hoard)) {
                        if (array_key_exists('count', $hoard['counts'])) {
                            $writer->writeAttribute('count', $hoard['counts']['count']);
                        } else {
                            if (array_key_exists('minCount', $hoard['counts'])) {
                                $writer->writeAttribute('minCount', $hoard['counts']['minCount']);
                            }
                            if (array_key_exists('maxCount', $hoard['counts'])) {
                                $writer->writeAttribute('maxCount', $hoard['counts']['maxCount']);
                            }
                        }
                        
                        if (array_key_exists('description', $hoard['counts'])) {
                            $writer->startElement('description');
                                $writer->writeAttribute('xml:lang', 'en');
                                $writer->text($hoard['counts']['description']);
                            $writer->endElement();
                        }
                    }
                    
                
                    //coin groups
                    if (array_key_exists('contents', $hoard)) {
                        foreach ($hoard['contents'] as $group) {
                            if (array_key_exists('count', $group) && $group['count'] == 1) {
                                $element = 'coin';
                            } else {
                                $element = 'coinGrp';
                            }
                            
                            $writer->startElement($element);
                            
                                //insert count attributes into coin group
                                
                                if (array_key_exists('count', $group)) {
                                    //only include the count attribute in a coinGrp, not a single coin element
                                    if ($group['count'] > 1) {
                                        $writer->writeAttribute('count', $group['count']);
                                    }
                                } else {
                                    if (array_key_exists('minCount', $group)) {
                                        $writer->writeAttribute('minCount', $group['minCount']);
                                    }
                                    if (array_key_exists('maxCount', $group)) {
                                        $writer->writeAttribute('maxCount', $group['maxCount']);
                                    }
                                }
                                
                                //typeDesc
                                $writer->startElement('nuds:typeDesc');
                                    
                                if (array_key_exists('denomination', $group)) {
                                    foreach($group['denomination'] as $uri) {
                                        $nm = get_nomisma_data($uri);
                                        
                                        $writer->startElement('nuds:denomination');
                                            $writer->writeAttribute('xlink:type', 'simple');
                                            $writer->writeAttribute('xlink:href', $uri);
                                            
                                            if (array_key_exists('denomination_uncertain', $group)) {
                                                $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                            }
                                            
                                            $writer->text($nm['label']);
                                        $writer->endElement();
                                    }
                                }
                                
                                if (array_key_exists('material', $group)) {
                                    foreach($group['material'] as $uri) {
                                        $nm = get_nomisma_data($uri);
                                        
                                        $writer->startElement('nuds:material');
                                        $writer->writeAttribute('xlink:type', 'simple');
                                        $writer->writeAttribute('xlink:href', $uri);
                                        
                                        if (array_key_exists('material_uncertain', $group)) {
                                            $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                        }
                                        
                                        $writer->text($nm['label']);
                                        $writer->endElement();
                                    }
                                }
                                
                                //authority
                                if (array_key_exists('authority', $group) || array_key_exists('stated_authority', $group) || array_key_exists('dynasty', $group)) {
                                    $writer->startElement('nuds:authority');
                                        if (array_key_exists('authority', $group)) {
                                            foreach($group['authority'] as $uri) {
                                                $nm = get_nomisma_data($uri);
                                                
                                                $writer->startElement('nuds:persname');
                                                $writer->writeAttribute('xlink:type', 'simple');
                                                $writer->writeAttribute('xlink:role', 'authority');
                                                $writer->writeAttribute('xlink:href', $uri);
                                                
                                                if (array_key_exists('authority_uncertain', $group)) {
                                                    $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                                }
                                                
                                                $writer->text($nm['label']);
                                                $writer->endElement();
                                            }
                                        }
                                        if (array_key_exists('stated_authority', $group)) {
                                            foreach($group['stated_authority'] as $uri) {
                                                $nm = get_nomisma_data($uri);
                                                
                                                $writer->startElement('nuds:persname');
                                                $writer->writeAttribute('xlink:type', 'simple');
                                                $writer->writeAttribute('xlink:role', 'statedAuthority');
                                                $writer->writeAttribute('xlink:href', $uri);
                                                
                                                if (array_key_exists('stated_authority_uncertain', $group)) {
                                                    $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                                }
                                                
                                                $writer->text($nm['label']);
                                                $writer->endElement();
                                            }
                                        }
                                        if (array_key_exists('dynasty', $group)) {
                                            foreach($group['dynasty'] as $uri) {
                                                $nm = get_nomisma_data($uri);
                                                
                                                $writer->startElement('nuds:famname');
                                                $writer->writeAttribute('xlink:type', 'simple');
                                                $writer->writeAttribute('xlink:role', 'dynasty');
                                                $writer->writeAttribute('xlink:href', $uri);
                                                
                                                if (array_key_exists('dynasty_uncertain', $group)) {
                                                    $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                                }
                                                
                                                $writer->text($nm['label']);
                                                $writer->endElement();
                                            }
                                        }
                                    $writer->endElement();
                                }
                                
                                //geographic
                                if (array_key_exists('mint', $group) || array_key_exists('region', $group)) {
                                    $writer->startElement('nuds:geographic');
                                    if (array_key_exists('mint', $group)) {
                                        foreach($group['mint'] as $uri) {
                                            $nm = get_nomisma_data($uri);
                                            
                                            $writer->startElement('nuds:geogname');
                                            $writer->writeAttribute('xlink:type', 'simple');
                                            $writer->writeAttribute('xlink:role', 'mint');
                                            $writer->writeAttribute('xlink:href', $uri);
                                            
                                            if (array_key_exists('mint_uncertain', $group)) {
                                                $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                            }
                                            
                                            $writer->text($nm['label']);
                                            $writer->endElement();
                                        }
                                    }
                                    if (array_key_exists('region', $group)) {
                                        foreach($group['region'] as $uri) {
                                            $nm = get_nomisma_data($uri);
                                            
                                            $writer->startElement('nuds:geogname');
                                            $writer->writeAttribute('xlink:type', 'simple');
                                            $writer->writeAttribute('xlink:role', 'region');
                                            $writer->writeAttribute('xlink:href', $uri);
                                            
                                            if (array_key_exists('mint_uncertain', $group)) {
                                                $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                                            }
                                            
                                            $writer->text($nm['label']);
                                            $writer->endElement();
                                        }
                                    }
                                    $writer->endElement();
                                }
                                
                                $writer->endElement();
                            $writer->endElement();
                        }
                    }
                    $writer->endElement();
                //end contents and contents Desc
                $writer->endElement();
            }
            
            //refDesc
            if ($replacesId == null) {
                if (array_key_exists('reference', $hoard) || array_key_exists('igch', $hoard) || array_key_exists('replaces', $hoard)) {
                    $writer->startElement('refDesc');
                    if (array_key_exists('reference', $hoard)) {
                        foreach($hoard['reference'] as $ref) {
                            $writer->writeElement('reference', $ref);
                        }
                    }
                    if (array_key_exists('igch', $hoard)) {
                        foreach($hoard['igch'] as $ref) {
                            $igchNum = str_replace('igch', '', $ref);
                            $writer->startElement('reference');
                                $writer->writeAttribute('xlink:type', 'simple');
                                $writer->writeAttribute('xlink:href', 'http://coinhoards.org/id/' . $ref);
                                $writer->text('IGCH ' . ltrim($igchNum, '0'));
                            $writer->endElement();
                        }
                    }
                    if (array_key_exists('replaces', $hoard)) {
                        foreach($hoard['replaces'] as $ref) {
                            $writer->writeElement('reference', 'CH ' . $ref);
                        }
                    }                    
                    $writer->endElement();
                }
            }
            
            
        //end descMeta    
        $writer->endElement();
        
    //end nudsHoard
    $writer->endElement();
    //end document
    return $writer->flush();
   

}

//process all CSV files into an array of objects
function process_hoards_csv($data, $duplicates) {
    
    GLOBAL $findspots;
    GLOBAL $counts_csv;
    GLOBAL $contents_csv;
    GLOBAL $refs_csv;
    
    //generate a concordance structure between CH volumes and IGCH hoards
    $hoards = generate_concordance($data, $duplicates);
    
    foreach ($hoards as $id=>$hoard) {
        
        //iterate through findspots CSV and look for the CH ID that matches the $hoard ID
        foreach ($findspots as $row) {
            foreach ($row as $k=>$v) {
                if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                    if (in_array($v, $hoard['replaces'])) {
                        
                        if (strlen($row['Period']) > 0) {
                            $hoards[$id]['period'] = $row['Period'];
                        }
                        
                        $findspot = array();
                        $findspot['name'] = $row['Name'];
                        
                        if (strlen($row['Canonical Geonames URI'] > 0)) {
                            $findspot['geonames'] = trim($row['Canonical Geonames URI']);
                        }
                        if (strlen($row['Place Name'] > 0)) {
                            $findspot['geonames_label'] = trim($row['Place Name']);
                        }
                        if (strlen($row['Pleiades URI'] > 0)) {
                            $findspot['pleiades'] = trim($row['Pleiades URI']);
                        }
                        if (strlen($row['Pleiades label']) > 0) {
                            $findspot['pleiades_label'] = trim($row['Pleiades label']);
                        }
                        if (strlen($row['GML-compliant Coordinates']) > 0) {
                            $findspot['coords'] = trim($row['GML-compliant Coordinates']);
                        }
                        /*if (strlen($row['Geonames Feature Code Cat']) > 0) {
                            $findspot['type'] = $row['Geonames Feature Code Cat'];
                        }*/
                        
                        //discovery
                        if (is_numeric($row['Discovery Date Start']) || is_numeric($row['Discovery Date End'])) {
                            $discovery = array();
                            
                            if (is_numeric($row['Discovery Date Start']) && is_numeric($row['Discovery Date End'])){
                                if ($row['Discovery Date Start'] == $row['Discovery Date End']) {
                                    $discovery['date'] = $row['Discovery Date Start'];
                                } else {
                                    $discovery['fromDate'] = $row['Discovery Date Start'];
                                    $discovery['toDate'] = $row['Discovery Date End'];
                                }
                            } elseif (!is_numeric($row['Discovery Date Start']) && is_numeric($row['Discovery Date End'])) {
                                $discovery['notAfter'] = $row['Discovery Date End'];
                            }
                            
                            //uncertain
                            if ($row['Discovery Date Uncertain'] == 'true') {
                                $discovery['uncertain'] = true;
                            }
                            
                            $hoards[$id]['discovery'] = $discovery;
                            unset($discovery);
                        }
                       
                        
                        //burial
                        if (strlen($row['Burial Date Start']) > 0) {
                            $findspot['deposit']['fromDate'] = $row['Burial Date Start'];
                        }
                        if (strlen($row['Burial Date End']) > 0) {
                            $findspot['deposit']['toDate'] = $row['Burial Date End'];
                        }
                        
                        $hoards[$id]['findspot'] = $findspot;
                        unset($findspot);
                        
                        break;
                    }
                }
            }
        } // end findspots
        
        //disposition, ref, notes
        foreach ($refs_csv as $row) {
            foreach ($row as $k=>$v) {
                if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                    if (in_array($v, $hoard['replaces'])) {
                        if (strlen($row['value']) > 0 && strlen($row['type']) > 0) {
                            $type = strtolower($row['type']);
                            
                            $hoards[$id][$type][] = trim($row['value']);
                        }
                        break;
                    }
                }
            }
        }
        
        //process total counts
        foreach ($counts_csv as $row) {
            foreach ($row as $k=>$v) {
                if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                    if (in_array($v, $hoard['replaces'])) {
                        $counts = array();
                        
                        if (strlen($row['Contents']) > 0) {
                            $counts['description'] = trim($row['Contents']);
                        }
                        
                        if (is_numeric($row['Total count'])) {
                            $counts['count'] = (int)trim($row['Total count']);
                        }
                        if (is_numeric($row['Min. count'])) {
                            $counts['minCount'] = (int)trim($row['Min. count']);
                        }
                        if (is_numeric($row['Max. count'])) {
                            $counts['maxCount'] = (int)trim($row['Max. count']);
                        }
                        if ($row['Approximate'] == 'yes') {
                            $counts['approximate'] = true;
                        }
                        
                        
                        $hoards[$id]['counts'] = $counts;
                        break;
                    }
                }
            }
        } //end counts
        
        //contents
        $contents = array();
        foreach ($contents_csv as $row) {
            foreach ($row as $k=>$v) {
                if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                    if (in_array($v, $hoard['replaces'])) {
                        $group = array();
                        //counts
                        if (is_numeric($row['Coin count'])) {
                            //echo $row['Coin count'] . "\n";
                            $group['count'] = (int)trim($row['Coin count']);
                        }
                        if (is_numeric($row['min coin count'])) {
                            $group['minCount'] = (int)trim($row['min coin count']);
                        }
                        if (is_numeric($row['max coin count'])) {
                            $group['maxCount'] = (int)trim($row['max coin count']);
                        }
                        //uncertainty
                        if ($row['coin count approximate'] == 'TRUE') {
                            $group['approximate'] = true;
                        }
                        if ($row['coin count uncertain'] == 'TRUE') {
                            $group['uncertain'] = true;
                        }
                        
                        //Nomisma types
                        //geographic
                        if (strpos($row['Mint 1 URI'], 'nomisma.org') !== FALSE) {
                            $group['mint'][] = trim($row['Mint 1 URI']);
                        }
                        if (strpos($row['Mint 2 URI'], 'nomisma.org') !== FALSE) {
                            $group['mint'][] = trim($row['Mint 2 URI']);
                        }
                        if ($row['mint uncertain'] == 'TRUE') {
                            $group['mint_uncertain'] = true;
                        }
                        if (strpos($row['Region 1 URI'], 'nomisma.org') !== FALSE) {
                            $group['region'][] = trim($row['Region 1 URI']);
                        }
                        if (strpos($row['Region 2 URI'], 'nomisma.org') !== FALSE) {
                            $group['region'][] = trim($row['Region 2 URI']);
                        }
                        if (strpos($row['Region 3 URI'], 'nomisma.org') !== FALSE) {
                            $group['region'][] = trim($row['Region 3 URI']);
                        }
                        
                        //authority
                        if (strpos($row['Authority 1 URI'], 'nomisma.org') !== FALSE) {
                            $group['authority'][] = trim($row['Authority 1 URI']);
                        }
                        if (strpos($row['Authority 2 URI'], 'nomisma.org') !== FALSE) {
                            $group['authority'][] = trim($row['Authority 2 URI']);
                        }
                        if ($row['authority uncertain'] == 'TRUE') {
                            $group['authority_uncertain'] = true;
                        }
                        
                        if (strpos($row['Stated authority'], 'nomisma.org') !== FALSE) {
                            $group['stated_authority'][] = trim($row['Stated authority']);
                        }
                        if (strpos($row['Dynasty URI'], 'nomisma.org') !== FALSE) {
                            $group['dynasty'][] = trim($row['Dynasty URI']);
                        }
                        
                        //material
                        if (strpos($row['Material 1 URI'], 'nomisma.org') !== FALSE) {
                            $group['material'][] = trim($row['Material 1 URI']);
                        }
                        if (strpos($row['Material 2 URI'], 'nomisma.org') !== FALSE) {
                            $group['material'][] = trim($row['Material 2 URI']);
                        }
                        if ($row['material uncertain'] == 'TRUE') {
                            $group['material_uncertain'] = true;
                        }
                        
                        //denomination
                        if (strpos($row['Denomination 1 URI'], 'nomisma.org') !== FALSE) {
                            $group['denomination'][] = trim($row['Denomination 1 URI']);
                        }
                        if (strpos($row['Denomination 2 URI'], 'nomisma.org') !== FALSE) {
                            $group['denomination'][] = trim($row['Denomination 2 URI']);
                        }
                        if ($row['denomination uncertain'] == 'TRUE') {
                            $group['denomination_uncertain'] = true;
                        }
                        
                        if (strpos($row['Authenticity'], 'nomisma.org') !== FALSE) {
                            $group['authenticity'] = trim($row['Authenticity']);
                        }
                        
                        //add coinGrp into $contents array
                        $contents[] = $group;
                        break;
                    }
                }
            }
        }
        //end contents
        
        if (count($contents) !== 0) {
            $hoards[$id]['contents'] = $contents;
        }
        
        unset($contents);
    }
    
    return $hoards;
}


//generate concordance structure between hoards
function generate_concordance($data, $duplicates) {
    
    $hoards = array();
    
    $count = 1;
    foreach ($data as $row) {
        
        $recordId = 'change.' . number_pad($count, 4);
        $hasDuplicates = false;
        $replaces = array();
        
        foreach ($row as $k=>$v) {
            if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                $id = trim($v);
                
                //create ID records only for unique IDs and generate concordances
                if (strpos($id, '|') !== FALSE) {
                    $ids = explode('|', $id);
                    
                    foreach ($ids as $item) {
                        if (!in_array($item, $duplicates)) {
                            $replaces[] = $item;
                        } else {
                            $hasDuplicates = true;
                        }
                    }
                } else {
                    if (!in_array($id, $duplicates)) {
                        $replaces[] = $id;
                    } else {
                        $hasDuplicates = true;
                    }
                }
            }            
        }
        
        if ($hasDuplicates == false) {
            $hoards[$recordId]['replaces'] = $replaces;
            
            if (strlen($row['IGCH']) > 0) {
                $igch_nums = explode(',', $row['IGCH']);
                
                foreach ($igch_nums as $igch_num) {
                    $hoards[$recordId]['igch'][] = 'igch' . number_pad(trim($igch_num), 4);
                }
            }
            
            $count++;
        }        
    }
    
    return $hoards;
}

//find CH hoards which appear more than once in the spreadsheet, suggesting single bibliographic entries citing multiple hoards
function find_duplicates($data) {    
    $dup = array();
    
    foreach ($data as $row) {
        foreach ($row as $k=>$v) {
            if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                $id = trim($v);
                
                if (strpos($id, '|') !== FALSE) {
                    $ids = explode('|', $id);
                    
                    foreach ($ids as $item) {
                        if (!array_key_exists($item, $dup)) {
                            $dup[$item] = 1;
                        } else {
                            $dup[$item] = $dup[$item] + 1;
                        }                    }
                        
                } else {
                    if (!array_key_exists($id, $dup)) {
                        $dup[$id] = 1;
                    } else {
                        $dup[$id] = $dup[$id] + 1;
                    }
                }
            }
        }
    }
    
    foreach ($dup as $k=>$v) {
        if ($v > 1) {
            $duplicates[] = $k;
        }
    }
    
    return $duplicates;
    
}

/*****
 * Nomisma Lookup
*****/

//get label from Nomisma JSON API
function get_nomisma_data($uri){
    GLOBAL $nomisma;
    
    if (preg_match('/^http:\/\/nomisma.org\/id\//', $uri)){
        $nm = array();
        
        if (array_key_exists($uri, $nomisma)){
            return $nomisma[$uri];
        } else {
            //get label and class from Nomisma JSON-LD
            $string = file_get_contents($uri . '.jsonld');
            $json = json_decode($string, true);
            
            echo "Reading {$uri}\n";
            
            foreach ($json["@graph"] as $obj){
                $curie = str_replace('http://nomisma.org/id/', 'nm:', $uri);
                
                if ($obj["@id"] == $uri || $obj["@id"] == $curie){
                    //get the English label                    
                    if (array_key_exists(0, $obj["skos:prefLabel"])) {
                        foreach ($obj["skos:prefLabel"] as $prefLabel){
                            if ($prefLabel["@language"] == 'en'){
                                $nm['label'] = $prefLabel["@value"];
                                break;
                            }
                        }
                    } else {
                        $nm['label'] = $obj["skos:prefLabel"]["@value"];
                    }
                    
                    
                    
                    $nomisma[$uri] = $nm;
                    return $nm;
                   
                }
            }
        }
    }
}

/*****
 * Date Parsing
 *****/

//generate human-readable date based on the integer value
function get_date_textual($year){
    $textual_date = '';
    //display start date
    if($year < 0){
        $textual_date .= abs($year) . ' BCE';
    } elseif ($year > 0) {
        if ($year <= 600){
            $textual_date .= $year . ' CE';
        }
        $textual_date .= $year;
    }
    return $textual_date;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
    if ($number > 0){
        $gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
    } elseif ($number < 0) {
        $bcNum = (int)abs($number);
        $gYear = '-' . str_pad($bcNum,$n,"0",STR_PAD_LEFT);
    }
    return $gYear;
}

/*****
 * CSV Parsing
 *****/

//parse CSV
function generate_json($doc){
    $keys = array();
    $geoData = array();
    
    $data = csvToArray($doc, ',');
    
    // Set number of elements (minus 1 because we shift off the first row)
    $count = count($data) - 1;
    
    //Use first row for names
    $labels = array_shift($data);
    
    foreach ($labels as $label) {
        $keys[] = $label;
    }
    
    // Bring it all together
    for ($j = 0; $j < $count; $j++) {
        $d = array_combine($keys, $data[$j]);
        $geoData[$j] = $d;
    }
    return $geoData;
}

// Function to convert CSV into associative array
function csvToArray($file, $delimiter) {
    if (($handle = fopen($file, 'r')) !== FALSE) {
        $i = 0;
        while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== FALSE) {
            for ($j = 0; $j < count($lineArray); $j++) {
                $arr[$i][$j] = $lineArray[$j];
            }
            $i++;
        }
        fclose($handle);
    }
    return $arr;
}

?>