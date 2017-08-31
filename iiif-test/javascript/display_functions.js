/************************************
VISUALIZATION FUNCTIONS
Written by Ethan Gruber, gruber@numismatics.org
Library: jQuery
Description: Rendering graphics based on hoard counts
 ************************************/
$(document).ready(function () {
    var service = 'http://images.numismatics.org/';

    var iiifImage = L.map('iiif-container', {
        center:[0, 0],
        crs: L.CRS.Simple,
        zoom: 0
    });
    
    var iiifLayers = {
    };
    
    // For each image create a L.TileLayer.Iiif object and add that to an object literal for the layer control
    $('#images option').each(function () {
        var image = $(this).text();
        var info = info_path(service, image);
        
        iiifLayers[image] = L.tileLayer.iiif(info);
    });
    // Add layers control to the map
    L.control.layers(iiifLayers).addTo(iiifImage);
    
    // Access the first Iiif object and add it to the map
    iiifLayers[Object.keys(iiifLayers)[0]].addTo(iiifImage);
});

function info_path(service, image) {
    return service + encodeURIComponent(image) + '/info.json';
}